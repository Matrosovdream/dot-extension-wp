<?php
/*
Plugin Name: Dotfiler API + Formiddable forms
Description: Dotfiler API + Formiddable forms
Version: 1.0
Plugin URI: 
Author URI: 
Author: Stanislav Matrosov
*/

// Variables
define('DOTFILER_BASE_URL', __DIR__);
define('DOTFILER_BASE_PATH', plugin_dir_url(__FILE__));

// Initialize core
require_once 'classes/init.class.php';

// Rest frm extension
require_once 'rest_frm.php';


add_action('init', 'init44');
function init44() {

    if( isset($_GET['migrate']) ) {

        global $wpdb;

        $table = $wpdb->prefix . 'frm_items_archive';
        $query = "SELECT COUNT(*) FROM $table";
        $results = $wpdb->get_results($query);

        print_r($results);

        die();

    }

    if( $_GET['linkss'] ) {

        //
        global $wpdb;
        $table = $wpdb->prefix . 'frm_shortlinks';
        $query = "SELECT COUNT(*) FROM $table";
        $results = $wpdb->get_results($query);

        // Delete all
        $query = "DELETE FROM $table";
        $results = $wpdb->query($query);

        print_r($results);
        die();

    }

    if( isset($_GET['phone']) ) {
    
        $data = PhoneChecker_helper::validate_phone( $phone = '14158586273' );
        //print_r($data);

    }

    if( isset($_GET['linkk']) ) {

        echo do_shortcode('[frm-short-link id=27315 page_id=2126]');
        die();

    }    

}





