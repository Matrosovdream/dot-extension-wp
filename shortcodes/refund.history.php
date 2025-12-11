<?php
function order_refund_shortcode($atts) {
    $atts = shortcode_atts(array(
        'order' => ''
    ), $atts);

    $order_id = $atts['order'];

    $ref = new Dotfiler_authnet_refund();
    $payment = $ref->get_payment( $order_id );

    ob_start();
    ?>
    
        <label class="refund-stat">
            <?php echo $payment['refunded_amount']; ?> of <?php echo $payment['full_amount']; ?>
        </label>

    <?php
    $refund_history = ob_get_clean();

    return $refund_history;
}
add_shortcode('order-refund', 'order_refund_shortcode');