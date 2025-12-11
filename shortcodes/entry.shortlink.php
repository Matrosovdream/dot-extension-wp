<?php
function frm_shortlink_func($atts) {

    // Variables
    $entry_id = $atts["id"];
    $page_id = $atts["page_id"];

    // Replace long link with short link
    $shortcode = "[frm-signed-edit-link id={$entry_id} page_id={$page_id}]";
    $wrapper = new Formidable_shortlinks_wrapper( $shortcode );

    // Process and return result
    return $wrapper->replace_link()->get_short_url();

}
add_shortcode('frm-short-link', 'frm_shortlink_func');


