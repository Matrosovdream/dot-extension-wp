<?php
if ( ! defined('ABSPATH') ) { exit; }

final class DotMigrationIndexes {

    // Change this if you modify the migration later
    private const VERSION = '2026-01-14-1';
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
        // field_id, meta_value(191)
        self::ensure_index(
            $item_metas,
            'idx_field_meta191',
            "ALTER TABLE `{$item_metas}` ADD INDEX `idx_field_meta191` (`field_id`, `meta_value`(191))"
        );

        // item_id, meta_value(191)
        self::ensure_index(
            $item_metas,
            'idx_item_meta191',
            "ALTER TABLE `{$item_metas}` ADD INDEX `idx_item_meta191` (`item_id`, `meta_value`(191))"
        );

        // Refresh stats (safe; helps optimizer pick new indexes)
        $wpdb->query("ANALYZE TABLE `{$payments_authnet}`, `{$payments_failed}`, `{$refunds_authnet}`, `{$item_metas}`");
    }

    private static function ensure_index(string $table, string $index_name, string $alter_sql): void {
        global $wpdb;

        // Check existence via INFORMATION_SCHEMA (reliable)
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

        if (intval($exists) > 0) {
            return;
        }

        $result = $wpdb->query($alter_sql);

        if ($result === false) {
            echo '[DotMigrationIndexes] Failed: ' . esc_html($alter_sql) . ' | Error: ' . esc_html($wpdb->last_error);
        } else {
            echo '[DotMigrationIndexes] Added index ' . esc_html($index_name) . ' on ' . esc_html($table);
        }
    }
}

add_action('admin_init', function () {
    if ( isset($_GET['run_dot_indexes']) ) {
        DotMigrationIndexes::maybe_run();
        die();
    }
});
