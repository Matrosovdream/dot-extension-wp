<?php
class EntryHelper {

    public static function get_draft_entries( $forms=[], $statuses=[1,2,3], $count=1000, $ago=1 ) {

        if( empty($forms) ) { return false; }

        global $wpdb;
        $table = $wpdb->prefix . 'frm_items';

        $query = "  SELECT * FROM $table
                    WHERE 
                        form_id IN (".implode(",", $forms).") AND
                        is_draft IN (".implode(",", $statuses).") AND
                        created_at < DATE_SUB(NOW(), INTERVAL $ago DAY)
                    LIMIT $count";
        
        $res = $wpdb->get_results( $query );

        $entries = [];
        foreach( $res as $entry ) {
            $entries[] = $entry->id;
        }

        return $entries;

    }

    public static function find_not_empty_entries( $form_id, $statuses=[1,2,3], $count=1000, $ago=1 ) {

        // Prepare query
        $fields = self::get_contact_fields_for_form( $form_id );

        // Make SQL query
        global $wpdb;
        $table = $wpdb->prefix . 'frm_item_metas';

        $query = "  SELECT item_id FROM $table
                    WHERE
                        field_id IN (".implode(",", array_merge( $fields['phone'], $fields['email'] )).") AND
                        meta_value != '' AND
                        created_at < DATE_SUB(NOW(), INTERVAL $ago DAY)
                    GROUP BY item_id
                    LIMIT $count";
        $res = $wpdb->get_results( $query, ARRAY_A );

        $items = array_column( $res, 'item_id' );

        return $items;

    }

    public static function get_duplicate_entries_by_form( $form_id ) {

        // Get all repeating emails
        $similar_emails = self::get_similar_emails( $form_id );

        // We take just if one of the entries has status = 0
        $filtered = array_filter( $similar_emails, function($item) {
            return in_array(0, array_column($item, 'status'));
        });

        // Take only item_id where status != 0 from similar_emails
        $entries = [];
        foreach( $filtered as $item ) {
            $entries[] = array_column( array_filter($item, function($item) {
                return $item['status'] != 0;
            }), 'item_id' );
        }

        // Let's finally combine all item_id's
        $entries = array_unique( array_merge( ...$entries ) );
        
        return $entries;

    }

    public static function get_similar_entries_by_fields( $form_id, $phone_field_id, $email_field_id ) {

        global $wpdb;
        $table = $wpdb->prefix . 'frm_item_metas';

        $query = "  SELECT item_id FROM $table
                    WHERE
                        field_id = $phone_field_id AND
                        meta_value IN (
                            SELECT meta_value FROM $table
                            WHERE field_id = $email_field_id
                        )
                    GROUP BY item_id";
        echo $query; echo "<br/>";
        return ;
        //$res = $wpdb->get_results( $query, ARRAY_A );

        $items = array_column( $res, 'item_id' );

        return $items;

    }

    public static function get_entries_statuses() {

        global $wpdb;
        $table = $wpdb->prefix . 'frm_items';

        $sql = "SELECT id, is_draft FROM $table LIMIT 100000";
        $res = $wpdb->get_results( $sql, ARRAY_A );

        $entries = [];
        foreach( $res as $row ) {
            $entries[ $row['id'] ] = $row['is_draft'];
        }

        return $entries;

    }

    public static function get_similar_emails( $form_id ) {

        // A short preparation
        $entries_statuses = self::get_entries_statuses();

        $fields = self::get_contact_fields_for_form( $form_id );
        $email_fields = $fields['email'];

        // Main query
        global $wpdb;

        $sql = 'SELECT m1.field_id, m1.item_id, m1.meta_value
        FROM wp_frm_item_metas m1
        JOIN (
            SELECT meta_value
            FROM wp_frm_item_metas
            WHERE field_id IN ('.implode(",", $email_fields).')
            GROUP BY meta_value
            HAVING COUNT(DISTINCT item_id) > 1
        ) m2 ON m1.meta_value = m2.meta_value
        WHERE m1.field_id IN ('.implode(",", $email_fields).')
        ORDER BY m1.meta_value';

        $res = $wpdb->get_results( $sql, ARRAY_A );

        // Combine
        $items = [];
        foreach( $res as $row ) {

            $items[ $row['meta_value'] ][] = array(
                'field_id' => $row['field_id'],
                'item_id' => $row['item_id'],
                'status' => $entries_statuses[ $row['item_id'] ]
            );
   
        }

        return $items;

    }

    public static function get_duplicate_entries_for_entry( $entry_id ) {

        global $wpdb;

        // Get entry
        $entry = FrmEntry::getOne( $entry_id );

        // Get email fields
        $email_fields = self::get_form_email_fields( $entry->form_id );

        // Get entry email
        $entry_email = self::get_entry_email( $entry_id );
        
        // Get duplicate entries by email
        $sql = "SELECT * FROM wp_frm_item_metas 
            WHERE 
                meta_value = '$entry_email' AND 
                field_id IN (".implode(",", $email_fields).") AND 
                item_id != $entry_id
                ORDER BY created_at DESC
                ";
        $duplicates = $wpdb->get_results( $sql );

        // Get entries and statuses
        $entries = [];
        foreach( $duplicates as $entry ) {

            $data = FrmEntry::getOne( $entry->item_id );
            $entries[ $entry->item_id ] = array(
                'id' => $entry->item_id,
                'status' => $data->is_draft,
                'created_at' => $data->created_at
            );
        }

        return $entries;

    }

    public static function get_form_email_fields( $form_id ) {

        $fields = FrmField::get_all_types_in_form( $form_id, 'email', '', 'include' );

        $email_fields = [];
        foreach( $fields as $field ) {
            $email_fields[] = $field->id;
        }

        return $email_fields;

    }

    public static function get_entry_email( $entry_id ) {

        global $wpdb;

        // Get entry
        $entry = FrmEntry::getOne( $entry_id );
        $form_id = $entry->form_id;

        // Get email and phone fields
        $fields = self::get_contact_fields_for_form( $form_id );
        $email_fields = $fields['email'];

        // Get entry email
        $sql = "SELECT meta_value FROM wp_frm_item_metas WHERE item_id = $entry_id AND field_id IN (".implode(",", $email_fields).")";
        $entry_email = $wpdb->get_var( $sql );

        return $entry_email;

    }

    public static function get_empty_entries_by_form( $form_id, $statuses=[1,2,3] ) {

        // All entries
        $entries = self::get_draft_entries( 
            array($form_id), 
            $statuses, 
            $count=100000 
        );

        // Not empty entries, if entry has phone or email
        $full_entries = self::find_not_empty_entries( 
            $form_id, 
            $statuses, 
            $count=100000 
        );

        // Exclude not empty entries
        $diff = array_diff( $entries, $full_entries );

        return $diff;

    }

    public static function get_contact_fields_for_form( $form_id ) {

        $phones = FrmField::get_all_types_in_form( $form_id, 'phone', '', 'include' );
        $emails = FrmField::get_all_types_in_form( $form_id, 'email', '', 'include' );

        foreach( $phones as $phone ) {
            $contact_fields['phone'][] = $phone->id;
        }

        foreach( $emails as $email ) {
            $contact_fields['email'][] = $email->id;
        }

        return $contact_fields;

    }

    public static function is_contact_form( $form_id ) {

        $fields = EntryHelper::get_contact_fields_for_form( $form_id );

        if( !empty($fields['phone']) || !empty($fields['email']) ) {
            return true;
        }

        return false;

    }

    public static function generate_combinations($array1, $array2) {

        $combinations = [];
    
        foreach ($array1 as $item1) {
            foreach ($array2 as $item2) {
                $combinations[] = [$item1, $item2];
            }
        }
    
        return $combinations;

    }

}