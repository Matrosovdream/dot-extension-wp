<?php
function order_paystatus_shortcode($atts) {

    $atts = shortcode_atts(array(
        'order' => ''
    ), $atts);
    $order_id = $atts['order'];

    $failed_payment = Dotfiler_authnet::get_failed_payment( $order_id );

    if( !isset($failed_payment) ) { return ; }
    ob_start();
    ?>
    
        <label class="credentials-stat">
            Error: <?php echo $failed_payment['error_code']; ?> <?php echo $failed_payment['error_message']; ?>
        </label>

    <?php
    $html = ob_get_clean();

    return $html;
}
add_shortcode('order-paystatus', 'order_paystatus_shortcode');

/*
Usage:
[order-paystatus order=22087]
*/