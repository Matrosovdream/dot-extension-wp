<?php
add_filter( 'frm_display_gateway_value_custom', 'frm_gateway_val', 15, 2 );
function frm_gateway_val( $value, $atts ) {
  
    // Show just on admin entries page
    if( $_GET['page'] == 'formidable-entries' && $value == 'authnet_aim' ) {

        $payment_item_id = $_GET['id'];

        $authnet = new Dotfiler_authnet();
        $payment_info = $authnet->get_payment_by_id( $payment_item_id );
        $authnet_login_id = $payment_info['authnet_login_id'];

        if( !$authnet_login_id ) { return $value; }

        if( $authnet_login_id == 'AUTHORIZENET_API_LOGIN_ID' ) {

            $default_set = $authnet->get_creds_default();
            $authnet_login_id = $default_set['login_id'];
        }

        $value .= " (<b>".$authnet_login_id."</b> account)";

    }

    return $value;
}