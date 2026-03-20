<?php
class Dotfiler_authnet_refund {

    public function refund_payment( $payment_id, $order_id, $sum = false ) {

        $status = null;

        if ( $payment_id ) {

            $ref         = new Dotfiler_authnet_refund();
            $frm_payment = new FrmTransPayment();

            $payment = $frm_payment->get_one( $payment_id );

            if ( ! $payment ) {
                return array(
                    'ok'             => false,
                    'message'        => __( 'Payment not found.', 'formidable-payments' ),
                    'payment_status' => null,
                );
            }

            $full_amount = (float) $payment->amount;

            // If already refunded (full sum)
            if ( $payment->status === 'refunded' ) {
                return array(
                    'ok'             => false,
                    'message'        => __( 'Already refunded', 'formidable-payments' ),
                    'payment_status' => 'refunded',
                );
            }

            // Set amount here
            $payment->amount = $sum !== false ? (float) $sum : $payment->amount;

            // Try replace API credentials for this order before refund API call
            $this->maybeReplaceApiCredentials( $order_id );

            // API request
            $class_name  = FrmTransAppHelper::get_setting_for_gateway( $payment->paysys, 'class' );
            $class_name  = 'Frm' . $class_name . 'ApiHelper'; // FrmAuthNetAimApiHelper
            $refundedRes = $class_name::refund_payment( $payment->receipt_id, compact( 'payment' ) );

            if ( ! is_wp_error( $refundedRes ) ) {

                // Save to DB
                $fields = array(
                    'sum'        => $payment->amount,
                    'payment_id' => $payment_id,
                );
                $ref->save_refund( $fields );

                // Check if it's fully refunded
                $payment = $ref->get_payment( $order_id );

                if ( ! empty( $payment ) && (float) $payment['refund_amount'] <= 0 ) {
                    $status = 'refunded';
                } else {
                    $status = 'completed';
                }

                // completed, refunded
                $frm_payment->update( $payment_id, array( 'status' => $status ) );

                $ok      = true;
                $message = __( 'Refunded', 'formidable-payments' );

            } else {
                $ok      = false;
                $message = __( $refundedRes->get_error_message(), 'formidable-payments' );
            }

        } else {
            $ok      = false;
            $message = __( 'Oops! No payment was selected for refund.', 'formidable-payments' );
        }

        return array(
            'ok'             => $ok,
            'message'        => $message,
            'payment_status' => $status,
        );
    }

    /**
     * Replace Authorize.Net API credentials dynamically for current order.
     * Needed because Formidable gateway helper reads AUTHORIZENET_* constants.
     */
    public function maybeReplaceApiCredentials( $order_id ): bool {

        $authnet     = new Dotfiler_authnet();
        $payment_his = $authnet->get_payment_by_id( $order_id );

        if ( empty( $payment_his ) || ! is_array( $payment_his ) ) {
            return false;
        }

        if ( ! defined( 'AUTHORIZENET_API_LOGIN_ID' ) && ! empty( $payment_his['authnet_login_id'] ) ) {
            define( 'AUTHORIZENET_API_LOGIN_ID', $payment_his['authnet_login_id'] );
        }

        if ( ! defined( 'AUTHORIZENET_TRANSACTION_KEY' ) && ! empty( $payment_his['authnet_transaction_key'] ) ) {
            define( 'AUTHORIZENET_TRANSACTION_KEY', $payment_his['authnet_transaction_key'] );
        }

        return true;
    }

    private function save_refund( $fields ) {
        global $wpdb;

        $fields['created_at'] = date( 'Y-m-d H:i:s' );

        $table_name = $wpdb->prefix . 'frm_refunds_authnet';
        $wpdb->insert( $table_name, $fields );
    }

    public function check_rights() {

        $access_roles = array( 'administrator', 'admin2', 'admin3' );

        foreach ( $access_roles as $role ) {
            if ( in_array( $role, wp_get_current_user()->roles, true ) ) {
                return true;
            }
        }

        return false;
    }

    public function get_payment( $order_id ) {

        $payment_id = $this->get_payment_by_orderid( $order_id );

        if ( $payment_id ) {
            $frm_payment = new FrmTransPayment();
            $payment     = $frm_payment->get_one( $payment_id );

            if ( ! $payment || $payment->paysys !== 'authnet_aim' ) {
                return;
            }

            $refunded_total = $this->get_refunds_by_payment_id( $payment_id );
            $refund_amount  = $payment->amount - $refunded_total;

            $data = array(
                'id'              => $payment->id,
                'status'          => $payment->status,
                'full_amount'     => $payment->amount,
                'refunded_amount' => $refunded_total,
                'refund_amount'   => $refund_amount,
                'receipt_id'      => $payment->receipt_id,
                'pay_method'      => $payment->paysys,
            );

            if ( $payment->status === 'refunded' ) {
                $data['refunded_amount'] = $payment->amount;
                $data['refund_amount']   = 0;
            }

            return $data;
        }
    }

    private function get_payment_by_orderid( $order_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'frm_payments';
        $query      = $wpdb->prepare( "SELECT * FROM $table_name WHERE `item_id` = %s", $order_id );
        $payment    = $wpdb->get_row( $query );

        return $payment ? $payment->id : 0;
    }

    private function get_refunds_by_payment_id( $payment_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'frm_refunds_authnet';
        $query      = $wpdb->prepare( "SELECT * FROM $table_name WHERE `payment_id` = %s", $payment_id );
        $refunds    = $wpdb->get_results( $query, ARRAY_A );

        return array_sum( array_column( $refunds, 'sum' ) );
    }
}