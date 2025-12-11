<?php
class Formidable_shortlinks {

    private $table = 'frm_shortlinks';
    private $hash_length = 10;
    public $short_url_base = '/app/';
    public $expires = 30; // Days

    public function __construct() {

        $this->prepare_database();

    }

    public function generate_shortlink( $url ) {

        $item = $this->find_shortlink( $url );

        if( !isset($item) ) {
            while( !$item ) {
                $item = $this->create_shortlink( $url );
            }
        }

        return $item->short_url;

    }

    public function find_shortlink( $url, $column = 'original_url' ) {

        global $wpdb;

        $sql = "SELECT * FROM $this->table WHERE {$column} = %s";
        $sql = $wpdb->prepare( $sql, $url );

        return $wpdb->get_row( $sql );

    }

    private function create_shortlink( $url ) {

        global $wpdb;

        $hash = $this->make_hash();
        $url = $this->prepare_url( $url );

        $data = array(
            'original_url' => $url,
            'short_url' => home_url( $this->short_url_base.$hash ),
            'hash' => $hash,
            'created_at' => date('Y-m-d'),
        );

        $wpdb->insert( $this->table, $data );

        if( !$wpdb->last_error ) {
            return $this->find_shortlink( $url );
        }

    }
    

    private function make_hash() {

        $length = $this->hash_length;

        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;

    }

    private function prepare_url( $url ) {

        return html_entity_decode( $url );

    }
    
    private function prepare_database() {

        global $wpdb;
        $this->table = $wpdb->prefix . $this->table;

    }

    public function remove_expired_links() {

        global $wpdb;

        $sql = "DELETE FROM $this->table WHERE created_at < DATE_SUB(CURDATE(), INTERVAL {$this->expires} DAY)";
        $wpdb->query($sql);

    }

}