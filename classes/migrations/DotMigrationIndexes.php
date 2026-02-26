<?php
if ( ! defined('ABSPATH') ) { exit; }

class DotMigrationIndexes {

    // Change this if you modify the migration later
    private const VERSION = '2026-01-25-2';
    private const OPTION_KEY = 'dot_migration_indexes_version';

    public static function maybe_run(): void {
        // Run only in wp-admin and only for admins
        if ( ! is_admin() || ! current_user_can('manage_options') ) {
            return;
        }

        $done = (string) get_option(self::OPTION_KEY, '');
        if ($done === self::VERSION) {
            return; // already applied
        }

        self::up();

        update_option(self::OPTION_KEY, self::VERSION, false);
    }

    public static function up(): void {
        global $wpdb;

        $payments_authnet = $wpdb->prefix . 'frm_payments_authnet';
        $payments_failed  = $wpdb->prefix . 'frm_payments_failed';
        $refunds_authnet  = $wpdb->prefix . 'frm_refunds_authnet';
        $item_metas       = $wpdb->prefix . 'frm_item_metas';
        $items            = $wpdb->prefix . 'frm_items';

        // ---- wp_frm_payments_authnet ----
        self::ensure_index(
            $payments_authnet,
            'idx_payment_id',
            "ALTER TABLE `{$payments_authnet}` ADD INDEX `idx_payment_id` (`payment_id`)"
        );

        self::ensure_index(
            $payments_authnet,
            'idx_form_created',
            "ALTER TABLE `{$payments_authnet}` ADD INDEX `idx_form_created` (`form_id`, `created_at`)"
        );

        // invoice_id is varchar(1000) -> use prefix index (191 is safe for utf8mb4)
        self::ensure_index(
            $payments_authnet,
            'idx_invoice_id',
            "ALTER TABLE `{$payments_authnet}` ADD INDEX `idx_invoice_id` (`invoice_id`(191))"
        );

        // ---- wp_frm_payments_failed ----
        self::ensure_index(
            $payments_failed,
            'idx_entry_id',
            "ALTER TABLE `{$payments_failed}` ADD INDEX `idx_entry_id` (`entry_id`)"
        );

        self::ensure_index(
            $payments_failed,
            'idx_payment_id',
            "ALTER TABLE `{$payments_failed}` ADD INDEX `idx_payment_id` (`payment_id`)"
        );

        self::ensure_index(
            $payments_failed,
            'idx_form_created',
            "ALTER TABLE `{$payments_failed}` ADD INDEX `idx_form_created` (`form_id`, `created_at`)"
        );

        // ---- wp_frm_refunds_authnet ----
        self::ensure_index(
            $refunds_authnet,
            'idx_payment_id',
            "ALTER TABLE `{$refunds_authnet}` ADD INDEX `idx_payment_id` (`payment_id`)"
        );

        self::ensure_index(
            $refunds_authnet,
            'idx_payment_created',
            "ALTER TABLE `{$refunds_authnet}` ADD INDEX `idx_payment_created` (`payment_id`, `created_at`)"
        );

        // ---- wp_frm_item_metas ----
        // These two help for equality / prefix searches. They do NOT speed up LIKE '%text%' substring searches.
        self::ensure_index(
            $item_metas,
            'idx_field_meta191',
            "ALTER TABLE `{$item_metas}` ADD INDEX `idx_field_meta191` (`field_id`, `meta_value`(191))"
        );

        self::ensure_index(
            $item_metas,
            'idx_item_meta191',
            "ALTER TABLE `{$item_metas}` ADD INDEX `idx_item_meta191` (`item_id`, `meta_value`(191))"
        );

        // Important for EXISTS/JOIN patterns: item_id correlation + field filter
        self::ensure_index(
            $item_metas,
            'idx_item_field',
            "ALTER TABLE `{$item_metas}` ADD INDEX `idx_item_field` (`item_id`, `field_id`)"
        );

        // (Optional) Also useful if you often start from field_id first, then item_id
        self::ensure_index(
            $item_metas,
            'idx_field_item',
            "ALTER TABLE `{$item_metas}` ADD INDEX `idx_field_item` (`field_id`, `item_id`)"
        );

        // Recommended: index for frm_items filtering by form_id + ordering by id (common admin list patterns)
        self::ensure_index(
            $items,
            'idx_form_id_id',
            "ALTER TABLE `{$items}` ADD INDEX `idx_form_id_id` (`form_id`, `id`)"
        );

        // Refresh optimizer statistics after migration/import so MySQL chooses the new indexes
        self::analyze_tables([ $payments_authnet, $payments_failed, $refunds_authnet, $item_metas, $items ]);

        // If you suspect table/index bloat after big import, you can rebuild (heavy operation):
        // self::optimize_tables([ $item_metas ]);
    }

    /**
     * Ensure an index exists, otherwise create it via ALTER.
     */
    private static function ensure_index(string $table, string $index_name, string $alter_sql): void {
        global $wpdb;

        $exists = self::index_exists($table, $index_name);
        if ($exists) {
            return;
        }

        $result = $wpdb->query($alter_sql);

        if ($result === false) {
            // Keep output minimal (avoid breaking admin pages). You can swap to error_log if you prefer.
            echo '[DotMigrationIndexes] Failed: ' . esc_html($alter_sql) . ' | Error: ' . esc_html($wpdb->last_error);
        } else {
            echo '[DotMigrationIndexes] Added index ' . esc_html($index_name) . ' on ' . esc_html($table);
        }
    }

    /**
     * Reliable check using INFORMATION_SCHEMA.STATISTICS
     */
    private static function index_exists(string $table, string $index_name): bool {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1)
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = %s
                   AND INDEX_NAME = %s",
                $table,
                $index_name
            )
        );

        return intval($exists) > 0;
    }

    /**
     * Refresh MySQL optimizer stats (very useful after server migration / dump+restore)
     */
    public static function analyze_tables(array $tables): void {
        global $wpdb;

        $tables = array_values(array_filter(array_map('strval', $tables)));
        if (empty($tables)) {
            return;
        }

        $quoted = array_map(static function(string $t): string {
            // $t is already like wp_prefix_table; quote with backticks
            return '`' . str_replace('`', '', $t) . '`';
        }, $tables);

        $sql = 'ANALYZE TABLE ' . implode(', ', $quoted); 
        echo '[DotMigrationIndexes] Analyzing tables: ' . esc_html($sql);
        $wpdb->query($sql);
    }

    /**
     * Heavy operation: rebuild table and indexes (can lock / take time).
     * Use only if you really need it after massive imports/deletes.
     */
    public static function optimize_tables(array $tables): void {
        global $wpdb;

        $tables = array_values(array_filter(array_map('strval', $tables)));
        if (empty($tables)) {
            return;
        }

        foreach ($tables as $t) {
            $tq = '`' . str_replace('`', '', $t) . '`';
            $wpdb->query("OPTIMIZE TABLE {$tq}");
        }
    }
}

add_action('admin_init', function () {

    if ( isset($_GET['run_dot_indexes']) ) {
        DotMigrationIndexes::maybe_run();
        exit();
    }

    if( isset($_GET['run_analyze_tables']) ) {

        $tables = [
            'frm_payments_authnet',
            'frm_payments_failed',
            'frm_refunds_authnet',
            'frm_item_metas',
            'frm_items',
        ];
        DotMigrationIndexes::analyze_tables( $tables );
        exit();
    }

});
