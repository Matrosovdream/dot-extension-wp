<?php
add_action('wp_ajax_phone_validate', 'phone_validate');
add_action('wp_ajax_nopriv_phone_validate', 'phone_validate');
function phone_validate()
{

    // Get data
    $data = PhoneChecker_helper::validate_phone($phone = $_POST['phone']);

    // Return JSON response
    wp_send_json($data);

}
