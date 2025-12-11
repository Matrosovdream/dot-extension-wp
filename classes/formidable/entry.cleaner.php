<?php


class FRM_entry_cleaner {

    private $period = 90; // days
    private $forms = [];
    private $statuses = [1, 2, 3]; // 1 - draft, 2 - in progress, 3 - abandoned
    private $paged = 100000;
    private $archive = true;

    public function __construct() {

        // Set initial settings
        if( get_option('old_entries_period') ) {
            $this->period = get_option('old_entries_period');
        }
        if( get_option('frm_cleaner_forms') ) {
            $this->forms = get_option('frm_cleaner_forms');
        }

        // For safety needs we turn it off
        if( get_option('frm_cleaner_statuses') ) {
            //$this->statuses = get_option('frm_cleaner_statuses');
        }

    }

    public function remove_old_entries() {

        $entries = $this->get_old_entries();

        foreach( $entries as $entry_id ) {
            $this->remove_entry( $entry_id );
        }

        echo "<pre>";
        print_r($entries);
        echo "</pre>";

    }

    public function remove_similar_entries() {

        $entries = $this->get_similar_entries();

        foreach( $entries as $entry_id ) {
            $this->remove_entry( $entry_id );
        }

        echo "<pre>";
        print_r($entries);
        echo "</pre>";

    }

    public function remove_empty_entries() {

        $entries = $this->get_empty_entries();

        foreach( $entries as $entry_id ) {
            $this->remove_entry( $entry_id );
        }

        echo "<pre>";
        print_r($entries);
        echo "</pre>";

    }

    private function remove_entry( $entry_id ) {

        if( $this->archive ) {
            $entry = new EntryArchive( $entry_id );
            $entry->archive();
        } else {
            FrmEntry::destroy( $entry_id );
        }

    }   

    private function get_old_entries() {

        if( !$this->check_settings() ) {
            return [];
        }

        $entries = EntryHelper::get_draft_entries( 
            $this->forms, 
            $this->statuses, 
            $count=$this->paged, 
            $ago=$this->period 
        );

        return $entries;

    }

    private function get_similar_entries() {

        if( !$this->check_settings() ) {
            return [];
        }

        $entries = [];
        foreach( $this->forms as $form_id ) {

            // Check if the form has any phone or email fields
            if( !EntryHelper::is_contact_form( $form_id ) ) {
                continue;
            }

            $duplicates = EntryHelper::get_duplicate_entries_by_form( $form_id );
            if( empty($duplicates) ) { continue; }

            $entries = array_merge( $entries, $duplicates );

        }

        return $entries;
        
    }

    private function get_empty_entries() {

        if( !$this->check_settings() ) {
            return [];
        }

        $entries = [];
        foreach( $this->forms as $form_id ) {

            // Check if the form has any phone or email fields
            if( !EntryHelper::is_contact_form( $form_id ) ) {
                continue;
            }

            $diff = EntryHelper::get_empty_entries_by_form( $form_id, $this->statuses );
            $entries = array_merge( $entries, $diff );

        }

        return $entries;

    }

    private function check_settings() {

        if( empty($this->forms) ) {
            return false;
        }

        if( empty($this->statuses) ) {
            return false;
        }

        return true;

    }    

    public function get_period() {
        return $this->period;
    }

    public function set_period( $period ) {
        $this->period = $period;
    }

    public function set_forms( $forms ) {
        $this->forms = $forms;
    }

    public function set_statuses( $statuses ) {
        $this->statuses = $statuses;
    }

    public function set_paged( $paged ) {
        $this->paged = $paged;
    }

    public function set_archive( $archive ) {
        $this->archive = $archive;
    }

}


