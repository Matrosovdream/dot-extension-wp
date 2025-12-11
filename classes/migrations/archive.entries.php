<?php
class Migration_entries {

    public function __construct() {

        add_action('init', [$this, 'execute_query_init']);

    }

    public function execute_query_init() {

        if( isset($_GET['migrate']) ) {
            $this->execute_query();
        }

    }

    public function execute_query() {

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $queries = $this->queries();
        
        foreach( $queries as $query ) {
            dbDelta( $query );
        }

    }

    private function queries() {

        $queries = [];

        $queries[] = "CREATE TABLE `wp_frm_items_archive` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `item_key` varchar(100) DEFAULT NULL,
            `name` varchar(255) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `ip` text DEFAULT NULL,
            `form_id` bigint(20) DEFAULT NULL,
            `post_id` bigint(20) DEFAULT NULL,
            `user_id` bigint(20) DEFAULT NULL,
            `parent_item_id` bigint(20) DEFAULT 0,
            `is_draft` tinyint(1) DEFAULT 0,
            `updated_by` bigint(20) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `item_key` (`item_key`),
            KEY `form_id` (`form_id`),
            KEY `post_id` (`post_id`),
            KEY `user_id` (`user_id`),
            KEY `parent_item_id` (`parent_item_id`),
            KEY `idx_is_draft_created_at` (`is_draft`,`created_at`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $queries[] = "CREATE TABLE `wp_frm_item_metas_archive` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `meta_value` longtext DEFAULT NULL,
            `field_id` bigint(20) NOT NULL,
            `item_id` bigint(20) NOT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `field_id` (`field_id`),
            KEY `item_id` (`item_id`),
            KEY `idx_field_id_item_id` (`field_id`,`item_id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $queries[] = "CREATE TABLE `wp_frm_payments_archive` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `meta_value` longtext DEFAULT NULL,
            `receipt_id` varchar(100) DEFAULT NULL,
            `invoice_id` varchar(100) DEFAULT NULL,
            `sub_id` varchar(100) DEFAULT NULL,
            `item_id` bigint(20) NOT NULL,
            `action_id` bigint(20) NOT NULL,
            `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
            `status` varchar(100) DEFAULT NULL,
            `begin_date` date NOT NULL,
            `expire_date` date DEFAULT NULL,
            `paysys` varchar(100) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `test` tinyint(1) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `item_id` (`item_id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        return $queries; 

    }

}


new Migration_entries();


  

  
