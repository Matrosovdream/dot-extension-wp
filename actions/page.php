<?php
function enqueue_dotfiler_api_scripts() {

    // CSS
    wp_enqueue_style('dotfiler-api-css', plugins_url('dot-extension/assets/dotfiler-api.css?time='.time() ));

    // JS
    wp_enqueue_script('dotfiler-api-js', plugins_url('dot-extension/assets/dotfiler-api.js?time='.time() ), array('jquery'), '1.0.0', true);

}
add_action('wp_enqueue_scripts', 'enqueue_dotfiler_api_scripts');