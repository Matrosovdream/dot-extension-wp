<?php
function order_charged_shortcode($atts) {
    $atts = shortcode_atts(array(
        'order' => ''
    ), $atts);

    $order_id = $atts['order'];

    $ref = new Dotfiler_authnet_refund();
    $payment = $ref->get_payment( $order_id );

    $sum = $payment['full_amount'] - $payment['refunded_amount'];

    ob_start();
    ?>
    
        <label class="charged-stat">
            <?php echo $sum; ?>
        </label>

    <?php
    $refund_history = ob_get_clean();

    return $refund_history;
}
add_shortcode('order-charged', 'order_charged_shortcode');