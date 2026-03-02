<?php
if ( ! defined('ABSPATH') ) { exit; }

final class DotFrmCCCardCron
{
    /** Settings */
    public const FORM_ID              = 1;
    public const CHECK_FIELD_ID       = FRM_FORM_7_FIELDS_MAP['card_cc'];         // Must be empty or not exist
    public const REQUIRED_FIELD_ID    = FRM_FORM_7_FIELDS_MAP['card_data_full'];  // Must exist and NOT be empty
    public const BATCH_LIMIT          = 500;

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
     * Main cron execution
     */
    public static function run(): void
    {
        // Prevent overlapping runs
        if (get_transient(self::LOCK_KEY)) {
            return;
        }

        set_transient(self::LOCK_KEY, 1, 55);

        try {
            $ids = self::get_entry_ids_to_process();

            if (empty($ids)) {
                // Send informational email if no entries found
                $admin_email = '';
                $subject = 'Dot Extension: No entries to process';
                $message = 'The scheduled credit card processing task executed, but no qualifying entries were found. This is for informational purposes only.';
                //wp_mail($admin_email, $subject, $message);
            }

            foreach ($ids as $entry_id) {
                parseUpdateCreditCard((int)$entry_id);
            }

        } catch (\Throwable $e) {
            error_log('[DotFrmCCCardCron] ' . $e->getMessage());
        } finally {
            delete_transient(self::LOCK_KEY);
        }
    }

    /**
     * Conditions:
     * - FORM_ID = 1
     * - is_draft = 0
     * - CHECK_FIELD_ID is missing OR empty
     * - REQUIRED_FIELD_ID exists AND is NOT empty
     */
    private static function get_entry_ids_to_process(): array
    {
        global $wpdb;

        $items = $wpdb->prefix . 'frm_items';
        $metas = $wpdb->prefix . 'frm_item_metas';

        $sql = "
            SELECT i.id
            FROM {$items} i

            /* Field that must be empty or not exist */
            LEFT JOIN {$metas} m_check
                ON m_check.item_id = i.id
               AND m_check.field_id = %d

            /* Field that must exist and not be empty */
            INNER JOIN {$metas} m_required
                ON m_required.item_id = i.id
               AND m_required.field_id = %d

            WHERE i.form_id = %d
              AND i.is_draft = 0

              AND (
                    m_check.item_id IS NULL
                    OR m_check.meta_value IS NULL
                    OR m_check.meta_value = ''
                  )

              AND m_required.meta_value IS NOT NULL
              AND m_required.meta_value <> ''

            ORDER BY i.id ASC
            LIMIT %d
        ";

        $prepared = $wpdb->prepare(
            $sql,
            self::CHECK_FIELD_ID,
            self::REQUIRED_FIELD_ID,
            self::FORM_ID,
            self::BATCH_LIMIT
        );

        $ids = $wpdb->get_col($prepared);

        return array_map('intval', $ids ?: []);
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
});