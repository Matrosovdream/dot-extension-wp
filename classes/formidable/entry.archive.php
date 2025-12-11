<?php
class EntryArchive {

    public $entry_id;

    private $tables = [
        'main' => 'frm_items',
        'meta' => 'frm_item_metas',
        'payments' => 'frm_payments'
    ];

    private $tables_archive = [
        'main' => 'frm_items_archive',
        'meta' => 'frm_item_metas_archive',
        'payments' => 'frm_payments_archive'
    ];

    public function __construct( $entry_id ) {
        $this->entry_id = $entry_id;
    }

    public function archive() {

        // Main record
        $this->archive_main();

        // Meta records
        $this->archive_meta();

        // Payment records
        $this->archive_payments();

    }

    public function restore() {

        // Main record
        $this->restore_main();

        // Meta records
        $this->restore_meta();

        // Payment records
        $this->restore_payments();

    }

    private function archive_main() {
        $this->archive_cycle( 
            $this->tables['main'], 
            $this->tables_archive['main'], 
            $operations = ['insert', 'delete'], 
            ['id' => $this->entry_id] 
        );
    }

    public function archive_meta() {
        $this->archive_cycle( 
            $this->tables['meta'], 
            $this->tables_archive['meta'], 
            $operations = ['insert', 'delete'], 
            ['item_id' => $this->entry_id] 
        );
    }

    public function archive_payments() {
        $this->archive_cycle( 
            $this->tables['payments'], 
            $this->tables_archive['payments'], 
            $operations = ['insert', 'delete'], 
            ['item_id' => $this->entry_id] 
        );
    }

    public function restore_main() {
        $this->archive_cycle( 
            $this->tables_archive['main'], 
            $this->tables['main'], 
            $operations = ['insert', 'delete'], 
            ['id' => $this->entry_id] 
        );
    }

    public function restore_meta() {
        $this->archive_cycle( 
            $this->tables_archive['meta'], 
            $this->tables['meta'], 
            $operations = ['insert', 'delete'], 
            ['item_id' => $this->entry_id] 
        );
    }

    public function restore_payments() {
        $this->archive_cycle( 
            $this->tables_archive['payments'], 
            $this->tables['payments'], 
            $operations = ['insert', 'delete'], 
            ['item_id' => $this->entry_id] 
        );
    }

    public function is_archived() {

        global $wpdb;

        $table = $this->tables_archive['main'];
        $entry_id = $this->entry_id;

        $query = "SELECT id FROM $table WHERE id = $entry_id";
        $res = $wpdb->get_results( $query, ARRAY_A );

        if( !empty($res) ) {
            return true;
        }

        return false;

    }

    private function archive_cycle( $table_from, $table_to, $operations, $where ) {

        foreach( $operations as $operation ) {

            if( $operation == 'insert' ) {
                $this->insert_select( $table_from, $table_to, $where );
            }

            if( $operation == 'delete' ) {
                $this->delete( $table_from, $where );
            }
            
        }

    }

    private function insert_select( $table_from, $table_to, $where ) {

        global $wpdb;
        $table_from = $wpdb->prefix . $table_from;
        $table_to = $wpdb->prefix . $table_to;

        // Prepare WHERE string
        $where_str = '';
        foreach ($where as $column => $value) {
            $where_str .= "$column = '$value' AND ";
        }
        $where_str = rtrim($where_str, ' AND ');

        // Query
        $query = "INSERT INTO $table_to SELECT * FROM $table_from WHERE $where_str";
        $wpdb->query( $query );

    }

    private function delete( $table, $where ) {

        global $wpdb;
        $table = $wpdb->prefix . $table;

        // Prepare WHERE string
        $where_str = '';
        foreach ($where as $column => $value) {
            $where_str .= "$column = '$value' AND ";
        }
        $where_str = rtrim($where_str, ' AND ');

        // Query
        $wpdb->query("DELETE FROM $table WHERE $where_str");

    }

}


/*
CREATE TABLE `wp_frm_items_archive` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `wp_frm_item_metas_archive` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `meta_value` longtext DEFAULT NULL,
  `field_id` bigint(20) NOT NULL,
  `item_id` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `field_id` (`field_id`),
  KEY `item_id` (`item_id`),
  KEY `idx_field_id_item_id` (`field_id`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `wp_frm_payments_archive` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
*/