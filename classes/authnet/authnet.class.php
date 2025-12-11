<?php
class Dotfiler_authnet {

    private $post_type = 'authnet_account';

    public function __construct() {

        // Catch Authorize.net credentials and change it
        //add_action('init', array($this, 'init_authnet_credentials') );

    }

    // Calculate total sum paid by account
    public static function get_account_total_sum($login_id)
    {
        global $wpdb;

        $query = "SELECT SUM(amount) as total_sum
            FROM wp_frm_payments_authnet
            WHERE authnet_login_id = '{$login_id}'
            AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            AND created_at < DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 MONTH), '%Y-%m-01');
        ";
        $result = $wpdb->get_var($query);

        return $result;
    }

    public function init_authnet_credentials() {

        if( isset($_POST['frm_action']) && in_array( $_POST['frm_action'], ['create', 'update'] ) ) {

            $form_id = $_POST['form_id'];
            $creds = Dotfiler_authnet::get_custom_credentials( $form_id );

            // HERE, check!
            //print_r($creds);
            //die();  

            if( is_countable( $creds ) ) {

                if( 
                    trim($creds['login_id']) == '' || 
                    trim($creds['transaction_key']) == ''
                    ) { return true; }

                // Allows us not to modify Authorize.net FRM plugin
                define('AUTHORIZENET_API_LOGIN_ID', $creds['login_id']);
                define('AUTHORIZENET_TRANSACTION_KEY', $creds['transaction_key']);

            }
    
        }

    }

    public static function get_custom_credentials( $form_id=false ) {

        $res = get_posts( 
            array(
                "post_type" => "authnet_account",
                "meta_key" => "formidable_form_id",
                "meta_value" => $form_id,
                'post_status' => "publish"
            )
        );

        if( count( $res ) > 0 ) {

            foreach( $res as $post ) {

                $authnet_login_id = get_post_meta($post->ID, 'authnet_login_id', true);
                $account_info = Dotfiler_authnet::get_account_transactions( $authnet_login_id );
                $last_transaction = Dotfiler_authnet::get_last_transaction( $authnet_login_id );

                $data = array(
                    "login_id" => $authnet_login_id,
                    "transaction_key" => get_post_meta($post->ID, 'authnet_transaction_key', true),
                    "signature_key" => get_post_meta($post->ID, 'authnet_signature_key', true),
                    "monthly_limit" => get_post_meta($post->ID, 'monthly_limit', true),
                    "account" => Dotfiler_authnet::get_account_transactions( $authnet_login_id ),
                    "last_transaction" => $last_transaction['created_at']
                );

                if( $data['monthly_limit'] > $data['account']['sum'] ) {
                    $set[] = $data;
                }

            }

            /*
            echo "<pre>";
            print_r($set);
            echo "</pre>";
            */

            usort($set, function($a, $b) {
                return strtotime($a['last_transaction']) - strtotime($b['last_transaction']);
            });

            if( isset($set) ) {

                $to = 'matrosovdream@gmail.com';
                $subject = "Creds Data $form_id";
                $message = '$creds Data: ' . PHP_EOL . PHP_EOL;
                $message .= print_r($set, true);
                $headers = 'From: your_email@example.com' . PHP_EOL;
                $headers .= 'Content-Type: text/plain; charset=utf-8' . PHP_EOL;
    
                //wp_mail($to, $subject, $message, $headers);
    
            }

            return $set[0];

        } 

    }

    public static function get_last_transaction( $login_id ) {

        global $wpdb;

        $query = "SELECT *
            FROM wp_frm_payments_authnet
            WHERE authnet_login_id = '{$login_id}'
            ORDER BY created_at DESC
            LIMIT 1
        ";
        $results = $wpdb->get_results( $query, ARRAY_A );

        return $results[0];

    }

    static function get_formidadable_forms() {

        global $wpdb;

        $query = "SELECT * from wp_frm_forms";
        $res = $wpdb->get_results( $query );

        return $res;

    }
    
    public static function get_account_transactions( $login_id ) {

        global $wpdb;

        $query = "SELECT *
            FROM wp_frm_payments_authnet
            WHERE authnet_login_id = '{$login_id}'
            AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            AND created_at < DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 MONTH), '%Y-%m-01');
        ";
        $results = $wpdb->get_results( $query, ARRAY_A );

        $sum = array_sum(array_column($results, 'amount'));

        return array(
            "sum" => $sum
        );

    }

    public function get_payment_by_id( $payment_item_id ) {

        global $wpdb;
        $table = $wpdb->prefix.'frm_payments';

        // Get frm payment
        $query = "SELECT * FROM {$table} WHERE `item_id`={$payment_item_id}";
        $res = $wpdb->get_results( $query );

        // Get extra information
        if( isset( $res[0]->invoice_id ) && $res[0]->invoice_id != '' ) {

            $table = $wpdb->prefix.'frm_payments_authnet';
            $invoice_id = $res[0]->invoice_id;

            $query = "SELECT * FROM {$table} WHERE `invoice_id`={$invoice_id}";
            $records = $wpdb->get_results( $query, ARRAY_A );

            return $records[0];
        }

    }

    public function get_creds_by_login_id( $login_id ) {

        $args = array(
            'post_type' => 'authnet_account',
            'meta_key' => 'authnet_login_id',
            'meta_value' => $login_id,
            'posts_per_page' => -1
        );
        $posts = get_posts( $args );
        $post = $posts[0];

        if( $post ) {

            $data = array(
                "login_id" => get_post_meta($post->ID, 'authnet_login_id', true),
                "transaction_key" => get_post_meta($post->ID, 'authnet_transaction_key', true),
                "signature_key" => get_post_meta($post->ID, 'authnet_signature_key', true),
            );

            return $data;

        }

    }

    public static function insert_transaction( $values ) {

        // If it's empty then we take a default credentials
        if( !$values['authnet_login_id'] || $values['authnet_login_id'] == '' ) {
            $creds = Dotfiler_authnet::get_creds_default();
            $values['authnet_login_id'] = $creds['login_id'];
            $values['authnet_transaction_key'] = $creds['transaction_key'];
        }

        $fields = array(
            'form_id' => $values['form_id'],
            'payment_id' => '', 
            'invoice_id' => $values['invoice_id'],
            'authnet_login_id' => $values['authnet_login_id'],
            'authnet_transaction_key' => $values['authnet_transaction_key'],
            'created_at' => date('Y-m-d H:i:s'),
            'amount' => $values['amount'],
        );

        global $wpdb;
        $wpdb->insert( 'wp_frm_payments_authnet', $fields);

    }

    public static function insert_payment_failed( $values ) {

        global $wpdb;

        // Non-editable fields
        $fields_default = array(
            'created_at' => date('Y-m-d H:i:s'),
        );

        // Insert record
        $table = $wpdb->prefix . 'frm_payments_failed';
        $wpdb->insert($table, array_merge($values, $fields_default));

        // Catch errors
        //$wpdb->last_error;

    }

    public static function get_failed_payment( $entry_id ) {

        global $wpdb;

        $query = "SELECT * FROM wp_frm_payments_failed WHERE entry_id={$entry_id}";
        return $wpdb->get_results( $query, ARRAY_A )[0];

    }

    public static function get_creds_default() {

        $data = get_option('frm_authnet_options');
        return (array) $data;

    }
    
}

new Dotfiler_authnet();
