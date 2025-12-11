<?php

class FrmAutoresponderHelpers {

    public static function triggerEntryWithAction( int $entry_id, int $action_id ) {

        $entry = FrmEntry::getOne( $entry_id, true );

        $action = get_post( $action_id );
        $action->post_content = json_decode( $action->post_content, true );

        // Check conditional logic.
        $stop = FrmFormAction::action_conditions_met( $action, $entry );
        if ( !$stop ) {
            $entry->is_draft = 0;

            FrmAutoresponderAppController::trigger_autoresponder( $entry, $action );
        } 

    }

    public static function triggerEntryWithActions( int $entry_id, array $actions ) {

        foreach ( $actions as $action_id ) {
            self::triggerEntryWithAction( $entry_id, $action_id );
        }


    }

    public static function remove_single_event(array $params): void {
        $hook = 'formidable_send_autoresponder';
    
        // First check Action Scheduler (used by most Formidable installs)
        if (function_exists('as_unschedule_action')) {
            as_unschedule_action($hook, $params);
        }
    
        // Fallback: WP-Cron (if used instead)
        if (function_exists('wp_get_scheduled_event') && function_exists('wp_unschedule_event')) {
            $event = wp_get_scheduled_event($hook, $params);
            if ($event) {
                wp_unschedule_event($event->timestamp, $hook, $params);
            }
        }
    }

}