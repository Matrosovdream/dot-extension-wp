<?php
class Numverify {

    private $access_key;
    private $api_url = '';
    private $api_url_base = 'http://apilayer.net/api/';

    protected $response;
    protected $errors = [];

    public function __construct( $access_key ) {

        $this->access_key = $access_key;

    }

    public function verify_number($number) {

        // Settings
        $this->api_url = $this->api_url_base . 'validate';

        $params = [ 'number' => $number ];
        return $this->call( $params );

    }

    protected function get_countries() {

        // Settings
        $this->api_url = $this->api_url_base . 'countries';
        return $this->call();

    }

    private function call( $params = [] ) {

        $default_params = array(
            'access_key' => $this->access_key,
        );
        $url = $this->api_url . '?' . http_build_query( array_merge( $params, $default_params ) );

        $response = wp_remote_get($url);
        $this->response = $response;

        return $this->process_response();
        
    }

    private function process_response() {

        // Process HTTP errors
        if (is_wp_error($this->response)) {
            $this->errors[] = $this->response->get_error_message();
            return false;
        }

        $body = wp_remote_retrieve_body( $this->response );
        $body = json_decode( $body, true );

        // Process response errors
        if( isset($body['error']) ) {
            $this->errors[] = $body['error'];
            return false;
        }

        return $body;

    }

    public function get_errors() {
        return $this->errors;
    }


}