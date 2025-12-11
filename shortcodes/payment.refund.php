<?php
function payment_refund_shortcode($atts) {

    $ref = new Dotfiler_authnet_refund();
    
    // Check rights, just admin or manager can see this page
    if( !$ref->check_rights() ) { echo "Oops!"; return false; }

    // Get variables
    $order_id = $_GET['id'];
    $payment = $ref->get_payment( $order_id );
    $payment_id = $payment['id'];

    // Process form
    if( isset($_POST['refund_payment']) ) {

        $payment_id = $_POST['payment_id'];
        $sum = $_POST['sum'];

        $authnet = new Dotfiler_authnet();
        $payment_his = $authnet->get_payment_by_id( $order_id );

        if( isset($payment_his) ) {

            // Allows us not to modify Authorize.net FRM plugin
            define('AUTHORIZENET_API_LOGIN_ID', $payment_his['authnet_login_id']);
            define('AUTHORIZENET_TRANSACTION_KEY', $payment_his['authnet_transaction_key']);

            //$authnet_login_id = $payment_his->authnet_login_id;
            //$creds = $authnet->get_creds_by_login_id( $authnet_login_id );

            /*
            if( isset($creds) && is_array($creds) ) {
                // Allows us not to modify Authorize.net FRM plugin
                define('AUTHORIZENET_API_LOGIN_ID', $creds['login_id']);
                define('AUTHORIZENET_TRANSACTION_KEY', $creds['transaction_key']);
            }            
            */

        }

        $ref->refund_payment( $payment_id, $order_id, $sum );

        // Refresh the page
        $url = '/orders/payment-refund/?id='.$_GET['id'];
        echo "<script>window.location.href = '".$url."'</script>";
        exit;

    }

    // Get max refund amount
    $sum_max = $payment['refund_amount'];
    $value = $sum_max;
    if( isset($_POST['sum']) ) {
        $value = $_POST['sum'];
    }
    
    ob_start();
    ?>

    <div class="refund-wrapper">

        <h1>Refund payment #<?php echo $order_id; ?></h1>

        <p class="refund-stat">
            Refunded: <?php echo $payment['refunded_amount']; ?> of <?php echo $payment['full_amount']; ?>
        </p>

        <?php if( $sum_max ) { ?>

            <form action="" method="post">
                <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>" />
                
                <div class="input-div">
                    <input type="number" step="0.01" max="<?php echo $sum_max; ?>" name="sum" class="sum" value="<?php echo $value; ?>" />
                    <br/>
                    <small>Max: <?php echo $sum_max; ?></small>
                </div>

                <div class="input-div">
                    <input type="submit" name="refund_payment" value="Process" />
                </div>
                
            </form>

        <?php } else { ?>

            <p style="color: red; font-weight: bold;">Nothing to refund!</p>

         <?php } ?>   

    </div>


    <style>
        .refund-wrapper {
            margin: 20px;
            width: 50%;
            margin: 0 auto;
        }
        .refund-wrapper h1 {
            text-align: center;
        }
        .input-div {
            margin: 10px 0;
        }
        .refund-wrapper form {
            display: block;
            justify-content: center;
            align-items: center;
        }
        .refund-wrapper input[type="submit"] {
            padding: 10px 20px;
            background: #0073aa;
            color: #fff;
            border: none;
            cursor: pointer;
        }
    </style>


    <?php
    $output = ob_get_clean();
    return $output;
}
add_shortcode('payment-refund', 'payment_refund_shortcode');


/*
DROP TABLE IF EXISTS `wp_frm_refunds_authnet`;
CREATE TABLE `wp_frm_refunds_authnet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sum` float NOT NULL,
  `payment_id` int(100) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/