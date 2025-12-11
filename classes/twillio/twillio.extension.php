<?php
class FRM_Twillio_extension {

    public function __construct() {

        /* 
        Attach trigger "abandoned" to Twillio actions list.
        Abandonded comes from extension plugin Formidable-abandonment.
        */
        add_filter('frm_twilio_action_options', [$this, 'frm_twlo_action_options'], 10, 2);


        // Turn on Autoresponder for abandoned entries with all statuses
        // By default it doesn't take Draft entries
        add_filter('formidable_autoresponder_should_trigger', [$this, 'frm_twilio_autoresponder_should_trigger'], 10, 3);

        // Prevent trigger action
        //add_filter('frm_custom_trigger_action', [$this, 'frm_trigger_action_func'], 10, 3);

        // Set the period of time to mark the entry as abandoned
        //add_filter( 'frm_mark_abandonment_entries_period', [$this, 'frm_mark_abandonment_entries_period_func'], 10, 2 );

    }

    public function frm_mark_abandonment_entries_period_func($interval_in_minutes) {
        return 60 * 24;
    }

    public function frm_twlo_action_options($actions) {

        $actions['event'][] = 'abandoned';

        return $actions;
    
    }

    public function frm_twilio_autoresponder_should_trigger($should_trigger, $entry, $action) {

        if( 
            in_array( 'abandoned', $action->post_content['event'], true )
            ) { 

            // Avoid running on other actions
            if( !wp_doing_cron() ) {
                return false;
            }

            if( 
                $entry->is_draft
                ) {
                    
                // The list of rules for similar entries
                //return $this->check_for_duplicate_entries($entry, $action);
                return true;
            } else {
                return false;
            }

            
        }

        return $should_trigger;
    }

    public function check_for_duplicate_entries($entry, $action) {

        $all_entries = EntryHelper::get_duplicate_entries_for_entry( $entry->id );
        $all_entries[] = array(
            'id' => $entry->id,
            'status' => $entry->is_draft,
            'created_at' => $entry->created_at
        );

        // Sort all entries by created_at
        usort($all_entries, function($b, $a) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });

        // if no similar entries, then we can trigger the action
        if( count($all_entries) == 1 ) {
            return true;
        }

        // If one entry is paid then no trigger
        foreach( $all_entries as $entry ) {
            if( !$entry['status'] ) {
                return false;
            }
        }

        // If all entries are draft then we trigger just the latest entry
        if( $all_entries[0]['id'] != $entry->id ) {
            return false;
        }

        return true;

    }

    public function frm_trigger_action_func($prevent, $action, $entry, $form, $event) {
        return $prevent;
    }


}

new FRM_Twillio_extension();


