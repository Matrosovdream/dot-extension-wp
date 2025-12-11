<?php
function order_credentials_shortcode($atts) {
    $atts = shortcode_atts(array(
        'order' => ''
    ), $atts);

    $ref = new Dotfiler_authnet_refund();

    $order_id = $atts['order'];
    
    $authnet = new Dotfiler_authnet();
    $payment_his = $authnet->get_payment_by_id( $order_id );

    if( isset($payment_his) ) {
        $authnet_login_id = $payment_his['authnet_login_id'];
        //$creds = $authnet->get_creds_by_login_id( $authnet_login_id );
    }
    ob_start();
    ?>
    
        <label class="credentials-stat">
            Authnet login: <?php echo $authnet_login_id; ?>
        </label>

    <?php
    $html = ob_get_clean();

    return $html;
}
add_shortcode('order-credentials', 'order_credentials_shortcode');