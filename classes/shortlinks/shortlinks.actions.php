<?php
class Formidable_shortlinks_actions {

    private $shortener;

    public function __construct() {

        // Shortener Service
        $this->shortener = new Formidable_shortlinks();

        // Wordpress actions
        $this->add_actions();

    }

    
    public function add_actions() {

        add_action( 'template_redirect', [$this, 'formidable_shortlinks_redirect'] );

    }

    public function formidable_shortlinks_redirect() {

        global $wp;
        $new_url = $this->verify_shortlink( $url = home_url( $wp->request ) );

        // Redirect
        if( $new_url ) {
            wp_redirect( $new_url );
            exit();
        }
      
    }

    private function verify_shortlink( $url ) {
        
        
        // For optimization, we don't make SQL request every hit
        if( strpos($url, $this->shortener->short_url_base) === false ) {
            return;
        }

        // Remove expired links, just one query, no load at all
        $this->shortener->remove_expired_links();

        // Search in Database
        return $this->shortener->find_shortlink( $url, 'short_url' )->original_url;

    }

}

new Formidable_shortlinks_actions();