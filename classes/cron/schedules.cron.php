<?php
class Dotfiler_cron_schedules {

    public $schedules;

    public function __construct() {

        // Set schedules
        $this->schedules = array(
            'every_five_minutes' => array(
                'interval' => 300,
                'display'  => __( 'Every 5 Minutes' ),
            ),
            'every_ten_minutes' => array(
                'interval' => 600,
                'display'  => __( 'Every 10 Minutes' ),
            ),
            'every_fifteen_minutes' => array(
                'interval' => 900,
                'display'  => __( 'Every 15 Minutes' ),
            ),
            'every_thirty_minutes' => array(
                'interval' => 1800,
                'display'  => __( 'Every 30 Minutes' ),
            ),
        );

        // Add schedules
        $this->add_schedules();

    }

    public function add_schedules() {
        add_filter( 'cron_schedules', array( $this, 'add_schedules_callback' ) );
    }

    public function add_schedules_callback( $schedules ) {
        foreach ( $this->schedules as $key => $value ) {
            $schedules[$key] = $value;
        }
        return $schedules;
    }


}

new Dotfiler_cron_schedules();