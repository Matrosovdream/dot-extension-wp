<?php
class Dotfiler_authnet_errors {

    public $authnet_errors_json = '/wp-content/uploads/authnet/responseCodes.json';

    public function __construct() {

    }

    public static function get_error_by_code( $code ) {

        $posts = get_posts(
            array(
                'post_type' => 'authnet_error',
                'post_status' => 'publish',
                'meta_query' => array(
                    array(
                        'key' => 'authnet_error_code',
                        'value' => $code,
                        'compare' => '='
                    )
                )
            )
        );
        $post = $posts[0];

        if( isset($post) ) {
            return get_post_meta( $post->ID, 'authnet_error_message', true );
        } 

    }

    public function retrieve_suggestion_json( $error_code ) {

        $error_array = $this->get_errors_json();
        return $error_array[$error_code];

    }

    private function get_errors_json() {

        $error_json = file_get_contents( $_SERVER['DOCUMENT_ROOT'] . $this->authnet_errors_json );
        $errors = json_decode($error_json, true);

        $set = [];
        foreach( $errors as $error ) {
            $set[ $error['code'] ] = $error;
        }

        return $set;

    }

}