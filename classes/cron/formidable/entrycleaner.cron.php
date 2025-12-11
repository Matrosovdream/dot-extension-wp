<?php
class Entrycleaner_cron {

    // List of cleaners
    private $cleaners; 
    // Delay between cron jobs, in seconds
    private $delay = 2; 

    public function __construct() {

        // Set cleaners
        $this->cleaners = array(
            'empty' => array(
                'interval' => 'every_five_minutes',
                'action' => 'remove_empty_entries',
            ),
            'old' => array(
                'interval' => 'every_five_minutes',
                'action' => 'remove_old_entries',
            ),
            /*'similar' => array(
                'interval' => 'every_five_minutes',
                'action' => 'remove_similar_entries',
            ),*/
        );

        // Set cron jobs
        $this->set_cron_jobs();

        // Remove cron jobs
        //$this->remove_cron_jobs();

        // Add actions
        $this->add_actions();

    }

    public function set_cron_jobs() {
        
        // Set cron jobs
        foreach ( $this->cleaners as $cleaner ) {

            if ( ! wp_next_scheduled( $cleaner['action'] ) ) {

                // Set a delay between them for better performance
                sleep( $this->delay );

                wp_schedule_event( time(), $cleaner['interval'], $cleaner['action'] );
            }

        }

    }

    public function remove_cron_jobs() {

        // Remove cron jobs
        foreach ( $this->cleaners as $cleaner ) {
            wp_clear_scheduled_hook( $cleaner['action'] );
        }

    }

    public function add_actions() {

        foreach ( $this->cleaners as $cleaner ) {
            add_action( $cleaner['action'], [$this, $cleaner['action']] );
        }

    }

    // Empty entries
    public function remove_empty_entries() {

        $cln = new FRM_entry_cleaner();
        $cln->set_paged(1000);
        $cln->remove_empty_entries();

        // for Wordpress CRON
        return true;

    }

    // Old entries
    public function remove_old_entries() {

        $cln = new FRM_entry_cleaner();
        $cln->set_paged(5000);
        $cln->remove_old_entries();

        // for Wordpress CRON
        return true;

    }

    // Similar entries
    public function remove_similar_entries() {

        $cln = new FRM_entry_cleaner();
        $cln->remove_similar_entries();

        // for Wordpress CRON
        return true;

    }

}

new Entrycleaner_cron();