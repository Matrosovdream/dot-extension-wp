<?php
if ( ! defined('ABSPATH') ) { exit; }

final class DotFrmTrackingStatusCron
{
    /** Settings */
    public const FORM_ID = 1;

    /**
     * source_field_id => target_field_id
     *
     * 344 (tracking number) => 826 (status)
     * 747 (tracking number) => 827 (status)
     */
    public const FIELD_PAIRS = [
        344 => 826,
        747 => 827,
    ];

    /**
     * Chunk size for source meta rows.
     * Default: 10000
     */
    public const CHUNK_SIZE = 10000;

    /**
     * Which schedule to use by default:
     * - every_5_minutes
     * - hourly
     * - every_12_hours
     */
    public const DEFAULT_SCHEDULE = 'every_5_minutes';

    /** WP-Cron */
    private const CRON_HOOK           = 'dot_frm_tracking_status_cron_run';
    private const LOCK_KEY            = 'dot_frm_tracking_status_cron_lock';
    private const OPTION_SCHEDULE_KEY = 'dot_frm_tracking_status_cron_schedule';
    private const OPTION_CURSOR_KEY   = 'dot_frm_tracking_status_cron_cursor';

    public static function init(): void
    {
        add_filter('cron_schedules', [__CLASS__, 'add_schedules']);
        add_action('init', [__CLASS__, 'schedule']);
        add_action(self::CRON_HOOK, [__CLASS__, 'run']);
    }

    /**
     * Add custom schedules
     */
    public static function add_schedules(array $schedules): array
    {
        if (!isset($schedules['every_5_minutes'])) {
            $schedules['every_5_minutes'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => 'Dot: Every 5 Minutes',
            ];
        }

        if (!isset($schedules['every_12_hours'])) {
            $schedules['every_12_hours'] = [
                'interval' => 12 * HOUR_IN_SECONDS,
                'display'  => 'Dot: Every 12 Hours',
            ];
        }

        return $schedules;
    }

    /**
     * Allowed schedule keys
     */
    public static function get_allowed_schedules(): array
    {
        return [
            'every_5_minutes',
            'hourly',
            'every_12_hours',
        ];
    }

    /**
     * Get current schedule from option or fallback to default
     */
    public static function get_schedule_key(): string
    {
        $value = get_option(self::OPTION_SCHEDULE_KEY, self::DEFAULT_SCHEDULE);

        if (!in_array($value, self::get_allowed_schedules(), true)) {
            $value = self::DEFAULT_SCHEDULE;
        }

        return $value;
    }

    /**
     * Save schedule and re-register cron
     */
    public static function set_schedule_key(string $schedule_key): bool
    {
        if (!in_array($schedule_key, self::get_allowed_schedules(), true)) {
            return false;
        }

        update_option(self::OPTION_SCHEDULE_KEY, $schedule_key);
        self::reschedule();

        return true;
    }

    /**
     * Schedule cron if not exists
     */
    public static function schedule(): void
    {
        $schedule_key = self::get_schedule_key();

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 30, $schedule_key, self::CRON_HOOK);
        }
    }

    /**
     * Unschedule and re-schedule cron
     */
    public static function reschedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);

        while ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
            $timestamp = wp_next_scheduled(self::CRON_HOOK);
        }

        wp_schedule_event(time() + 30, self::get_schedule_key(), self::CRON_HOOK);
    }

    /**
     * Main cron runner
     * Processes one chunk per pair on each run
     */
    public static function run(): void
    {
        if (get_transient(self::LOCK_KEY)) {
            return;
        }

        set_transient(self::LOCK_KEY, 1, 4 * MINUTE_IN_SECONDS);

        try {
            foreach (self::FIELD_PAIRS as $source_field_id => $target_field_id) {
                self::process_pair((int) $source_field_id, (int) $target_field_id);
            }
        } catch (\Throwable $e) {
            error_log('[DotFrmTrackingStatusCron] ' . $e->getMessage());
        } finally {
            delete_transient(self::LOCK_KEY);
        }
    }

    /**
     * Cursor option key per pair
     */
    private static function get_cursor_option_key(int $source_field_id, int $target_field_id): string
    {
        return self::OPTION_CURSOR_KEY . '_' . $source_field_id . '_' . $target_field_id . '_' . (int) self::FORM_ID;
    }

    /**
     * Current cursor for a pair
     */
    private static function get_cursor(int $source_field_id, int $target_field_id): int
    {
        return (int) get_option(self::get_cursor_option_key($source_field_id, $target_field_id), 0);
    }

    /**
     * Save cursor for a pair
     */
    private static function set_cursor(int $source_field_id, int $target_field_id, int $last_meta_id): void
    {
        update_option(
            self::get_cursor_option_key($source_field_id, $target_field_id),
            $last_meta_id,
            false
        );
    }

    /**
     * Reset cursor for a pair
     */
    private static function reset_cursor(int $source_field_id, int $target_field_id): void
    {
        update_option(
            self::get_cursor_option_key($source_field_id, $target_field_id),
            0,
            false
        );
    }

    /**
     * Process one source->target pair by chunk
     */
    private static function process_pair(int $source_field_id, int $target_field_id): void
    {
        global $wpdb;

        if ($source_field_id <= 0 || $target_field_id <= 0) {
            return;
        }

        $metas     = $wpdb->prefix . 'frm_item_metas';
        $items     = $wpdb->prefix . 'frm_items';
        $shipments = $wpdb->prefix . 'frm_easypost_shipments';

        $cursor     = self::get_cursor($source_field_id, $target_field_id);
        $chunk_size = (int) self::CHUNK_SIZE;

        /**
         * 1. Get current chunk of source meta rows
         */
        $sql_chunk = "
            SELECT source.id, source.item_id, source.meta_value
            FROM {$metas} source
            INNER JOIN {$items} i
                ON i.id = source.item_id
            WHERE source.field_id = %d
              AND source.meta_value IS NOT NULL
              AND TRIM(source.meta_value) <> ''
              AND i.form_id = %d
              AND i.is_draft = 0
              AND source.id > %d
            ORDER BY source.id ASC
            LIMIT %d
        ";

        $prepared_chunk = $wpdb->prepare(
            $sql_chunk,
            $source_field_id,
            self::FORM_ID,
            $cursor,
            $chunk_size
        );

        $chunk_rows = $wpdb->get_results($prepared_chunk, ARRAY_A);

        if (empty($chunk_rows)) {
            self::reset_cursor($source_field_id, $target_field_id);
            return;
        }

        $source_ids   = [];
        $last_meta_id = 0;

        foreach ($chunk_rows as $row) {
            $source_id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($source_id > 0) {
                $source_ids[] = $source_id;
                $last_meta_id = $source_id;
            }
        }

        if (empty($source_ids)) {
            self::reset_cursor($source_field_id, $target_field_id);
            return;
        }

        $source_ids_placeholders = implode(', ', array_fill(0, count($source_ids), '%d'));

        /**
         * 2. Update existing target meta rows only for this chunk
         */
        $sql_update = "
            UPDATE {$metas} target
            INNER JOIN {$metas} source
                ON source.item_id = target.item_id
               AND source.id IN ({$source_ids_placeholders})
            INNER JOIN {$shipments} eps
                ON eps.tracking_code = source.meta_value
            SET target.meta_value = eps.status,
                target.created_at = NOW()
            WHERE target.field_id = %d
        ";

        $args_update = array_merge($source_ids, [$target_field_id]);

        $prepared_update = $wpdb->prepare($sql_update, $args_update);
        $wpdb->query($prepared_update);

        /**
         * 3. Insert missing target meta rows only for this chunk
         */
        $sql_insert = "
            INSERT INTO {$metas} (meta_value, field_id, item_id, created_at)
            SELECT
                eps.status AS meta_value,
                %d AS field_id,
                source.item_id,
                NOW() AS created_at
            FROM {$metas} source
            INNER JOIN {$shipments} eps
                ON eps.tracking_code = source.meta_value
            LEFT JOIN {$metas} target
                ON target.item_id = source.item_id
               AND target.field_id = %d
            WHERE source.id IN ({$source_ids_placeholders})
              AND target.id IS NULL
        ";

        $args_insert = array_merge(
            [$target_field_id, $target_field_id],
            $source_ids
        );

        $prepared_insert = $wpdb->prepare($sql_insert, $args_insert);
        $wpdb->query($prepared_insert);

        /**
         * 4. Save cursor
         */
        self::set_cursor($source_field_id, $target_field_id, $last_meta_id);
    }
}

DotFrmTrackingStatusCron::init();

/**
 * Manual trigger for testing
 * Example:
 * ?run_tracking_status_cron=1
 */
add_action('init', function() {
    if (isset($_GET['run_tracking_status_cron']) && current_user_can('manage_options')) {
        DotFrmTrackingStatusCron::run();
        exit('Tracking status cron executed');
    }
});

/**
 * Change schedule manually for testing/admin
 *
 * Examples:
 * ?set_tracking_status_cron_schedule=every_5_minutes
 * ?set_tracking_status_cron_schedule=hourly
 * ?set_tracking_status_cron_schedule=every_12_hours
 */
add_action('init', function() {
    if (
        isset($_GET['set_tracking_status_cron_schedule']) &&
        current_user_can('manage_options')
    ) {
        $schedule = sanitize_text_field(wp_unslash($_GET['set_tracking_status_cron_schedule']));
        $ok = DotFrmTrackingStatusCron::set_schedule_key($schedule);

        if ($ok) {
            exit('Tracking status cron schedule updated to: ' . esc_html($schedule));
        }

        exit('Invalid schedule');
    }
});

/**
 * Reset all cursors manually for testing
 * Example:
 * ?reset_tracking_status_cron_cursor=1
 */
add_action('init', function() {
    if (isset($_GET['reset_tracking_status_cron_cursor']) && current_user_can('manage_options')) {
        foreach (DotFrmTrackingStatusCron::FIELD_PAIRS as $source_field_id => $target_field_id) {
            $ref = new ReflectionClass(DotFrmTrackingStatusCron::class);
            $method = $ref->getMethod('reset_cursor');
            $method->setAccessible(true);
            $method->invoke(null, (int) $source_field_id, (int) $target_field_id);
        }

        exit('Tracking status cron cursors reset');
    }
});