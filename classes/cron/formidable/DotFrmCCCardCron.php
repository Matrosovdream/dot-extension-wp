<?php
if ( ! defined('ABSPATH') ) { exit; }



final class DotFrmCCCardCron
{
    /** Settings */
    public const FORM_ID           = 1;

    /**
     * If ANY of these fields is missing or empty -> process the entry (and update BOTH).
     */
    public const CHECK_FIELD_ID    = [
        FRM_FORM_1_FIELDS_MAP['card_last4'],
        FRM_FORM_1_FIELDS_MAP['card_cc_bin'],
    ];

    /**
     * Must exist and NOT be empty.
     */
    public const REQUIRED_FIELD_ID = FRM_FORM_1_FIELDS_MAP['card_data_full'];

    public const BATCH_LIMIT       = 1500;

    /** WP-Cron */
    private const CRON_INTERVAL_KEY = 'dot_every_minute';
    private const CRON_HOOK         = 'dot_frm_cc_card_cron_run';
    private const LOCK_KEY          = 'dot_frm_cc_card_cron_lock';

    public static function init(): void
    {
        add_filter('cron_schedules', [__CLASS__, 'add_schedule']);
        add_action('init', [__CLASS__, 'schedule']);
        add_action(self::CRON_HOOK, [__CLASS__, 'run']);
    }

    /**
     * Register custom 1-minute cron interval
     */
    public static function add_schedule(array $schedules): array
    {
        if (!isset($schedules[self::CRON_INTERVAL_KEY])) {
            $schedules[self::CRON_INTERVAL_KEY] = [
                'interval' => 60,
                'display'  => 'Dot: Every Minute',
            ];
        }
        return $schedules;
    }

    /**
     * Schedule cron event if not already scheduled
     */
    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 30, self::CRON_INTERVAL_KEY, self::CRON_HOOK);
        }
    }

    /**
     * Unschedule the cron event (if needed)
     */
    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Main cron execution
     */
    public static function run(): bool
    {
        // Prevent overlapping runs
        if (get_transient(self::LOCK_KEY)) {
            //return;
        }

        set_transient(self::LOCK_KEY, 1, 55);

        // Disable sending changes to Storage app, plugin formidable-entries-history
        do_action('frm_entry_change_tracker_disable_queue');

        try {
            $ids = self::get_entry_ids_to_process();

            if (empty($ids)) {

                // Informational email (optional)
                $admin_email = DEVELOPER_EMAIL; // from references.php
                $subject = 'Dot Extension: No entries to process';
                $message = 'The scheduled credit card processing task executed, but no qualifying entries were found. This is for informational purposes only.';
                wp_mail($admin_email, $subject, $message);

                // Unschedule cron
                self::unschedule();

            }

            echo "<pre>";
            print_r($ids);
            echo "</pre>";

            foreach ($ids as $entry_id) {
                // Your handler should update BOTH fields (card_cc + card_cc_bin)
                parseUpdateCreditCard( $entry_id );
            }

        } catch (\Throwable $e) {
            error_log('[DotFrmCCCardCron] ' . $e->getMessage());
        } finally {
            delete_transient(self::LOCK_KEY);
        }

        // Enable it back, plugin formidable-entries-history
        do_action('frm_entry_change_tracker_enable_queue');

        return true;

    }

    /**
     * Conditions:
     * - FORM_ID = 1
     * - is_draft = 0
     * - REQUIRED_FIELD_ID exists AND is NOT empty
     * - ANY field in CHECK_FIELD_ID is missing OR empty
     */
    private static function get_entry_ids_to_process(): array
    {
        global $wpdb;

        $items = $wpdb->prefix . 'frm_items';
        $metas = $wpdb->prefix . 'frm_item_metas';

        $check_fields = self::CHECK_FIELD_ID;

        if (empty($check_fields) || !is_array($check_fields)) {
            return [];
        }

        $joins = [];
        $where_empty_conditions = [];
        $args = [];

        // LEFT JOIN for each check field
        foreach ($check_fields as $index => $field_id) {

            $alias = "m_check_{$index}";

            $joins[] = "
                LEFT JOIN {$metas} {$alias}
                    ON {$alias}.item_id = i.id
                   AND {$alias}.field_id = %d
            ";

            // If the joined row is missing OR empty -> this check field is "not filled"
            $where_empty_conditions[] = "(
                {$alias}.item_id IS NULL
                OR {$alias}.meta_value IS NULL
                OR {$alias}.meta_value = ''
            )";

            $args[] = (int) $field_id;
        }

        $sql = "
            SELECT i.id
            FROM {$items} i

            " . implode("\n", $joins) . "

            /* Required field must exist and not be empty */
            INNER JOIN {$metas} m_required
                ON m_required.item_id = i.id
               AND m_required.field_id = %d

            WHERE i.form_id = %d
              AND i.is_draft = 0

              AND (
                    " . implode(" OR ", $where_empty_conditions) . "
                  )

              AND m_required.meta_value IS NOT NULL
              AND m_required.meta_value <> ''

            ORDER BY i.id DESC
            LIMIT %d
        ";

        $args[] = (int) self::REQUIRED_FIELD_ID;
        $args[] = (int) self::FORM_ID;
        $args[] = (int) self::BATCH_LIMIT;

        $prepared = $wpdb->prepare($sql, $args);

        $ids = $wpdb->get_col($prepared);

        return array_map('intval', $ids ?: []);
    }

    /**
     * Count how many entries still need to be processed
     *
     * Conditions:
     * - FORM_ID = 1
     * - is_draft = 0
     * - REQUIRED_FIELD_ID exists AND is NOT empty
     * - ANY field in CHECK_FIELD_ID is missing OR empty
     */
    public static function get_entries_to_process_count(): int
    {
        global $wpdb;

        $items = $wpdb->prefix . 'frm_items';
        $metas = $wpdb->prefix . 'frm_item_metas';

        $check_fields = self::CHECK_FIELD_ID;

        if (empty($check_fields) || !is_array($check_fields)) {
            return 0;
        }

        $joins = [];
        $where_empty_conditions = [];
        $args = [];

        foreach ($check_fields as $index => $field_id) {
            $alias = "m_check_{$index}";

            $joins[] = "
                LEFT JOIN {$metas} {$alias}
                    ON {$alias}.item_id = i.id
                AND {$alias}.field_id = %d
            ";

            $where_empty_conditions[] = "(
                {$alias}.item_id IS NULL
                OR {$alias}.meta_value IS NULL
                OR {$alias}.meta_value = ''
            )";

            $args[] = (int) $field_id;
        }

        $sql = "
            SELECT COUNT(DISTINCT i.id)
            FROM {$items} i

            " . implode("\n", $joins) . "

            INNER JOIN {$metas} m_required
                ON m_required.item_id = i.id
            AND m_required.field_id = %d

            WHERE i.form_id = %d
            AND i.is_draft = 0
            AND (
                    " . implode(" OR ", $where_empty_conditions) . "
                )
            AND m_required.meta_value IS NOT NULL
            AND m_required.meta_value <> ''
        ";

        $args[] = (int) self::REQUIRED_FIELD_ID;
        $args[] = (int) self::FORM_ID;

        $prepared = $wpdb->prepare($sql, $args);

        return (int) $wpdb->get_var($prepared);
    }

}

DotFrmCCCardCron::init();

/**
 * Manual trigger (for testing)
 * Example: ?run_cc_card_cron=1
 */
add_action('init', function() {
    
    if (isset($_GET['run_cc_card_cron']) && current_user_can('manage_options')) {
        DotFrmCCCardCron::run();
        exit('Cron executed');
    }

    if( isset( $_GET['cc_card_count']) ) {
        $count = DotFrmCCCardCron::get_entries_to_process_count();
        exit("Entries to process: {$count}");
    }

});