<?php
class Dotfiler_authnet_refund {

    public function refund_payment( $payment_id, $order_id, $sum=false ) {

		if ( $payment_id ) {
            
			$ref = new Dotfiler_authnet_refund();
			$frm_payment = new FrmTransPayment();

			$payment = $frm_payment->get_one( $payment_id );

            $full_amount = $payment->amount;

            // If already refunded (full sum)
            if( $payment->status == 'refunded' ) {
                return array(
                    'message' => __( 'Already refunded', 'formidable-payments' ),
                    'payment_status' => 'refunded'
                );
            }

            // Set amount here
            $payment->amount = $sum;

            // API request
			$class_name = FrmTransAppHelper::get_setting_for_gateway( $payment->paysys, 'class' );
			$class_name = 'Frm' . $class_name . 'ApiHelper'; // FrmAuthNetAimApiHelper
			$refunded   = $class_name::refund_payment( $payment->receipt_id, compact( 'payment' ) );

            if( $refunded ) {

                // Save to DB
                $fields = array(
                    'sum' => $payment->amount,
                    'payment_id' => $payment_id,
                );
                $ref->save_refund($fields);

                // Check if it's fully refunded
                $payment = $ref->get_payment( $order_id );
                if( !$payment['refund_amount'] ) {
                    $status = 'refunded';
                    
                } else {
                    $status = 'completed';
                }

                // completed, refunded
                $frm_payment->update( $payment_id, array( 'status' => $status ) );
                
                $message = __( 'Refunded', 'formidable-payments' );

            } else {
                $message = __( 'Failed', 'formidable-payments' );
            }

		} else {
			$message = __( 'Oops! No payment was selected for refund.', 'formidable-payments' );
		}

        return array(
            'message' => $message,
            'payment_status' => $status
        );

    }

    private function save_refund( $fields ) {
        global $wpdb;

        // Prepare fields
        $fields['created_at'] = date('Y-m-d H:i:s');

        $table_name = $wpdb->prefix . 'frm_refunds_authnet';
        $wpdb->insert($table_name, $fields);

    }

    public function check_rights() {

        $access_roles = array('administrator', 'admin2', 'admin3');

        foreach ($access_roles as $role) {
            if (in_array($role, wp_get_current_user()->roles)) {
                return true;
            }
        }
        return false;

    }

    public function get_payment( $order_id ) {

        $payment_id = $this->get_payment_by_orderid( $order_id );

        if ( $payment_id ) {
			$frm_payment = new FrmTransPayment();
			$payment = $frm_payment->get_one( $payment_id );

            if( $payment->paysys != 'authnet_aim' ) { return ; }  

            $refund_amount = $payment->amount - $this->get_refunds_by_payment_id( $payment_id );

            $data = array(
                "id" => $payment->id,
                "status" => $payment->status,
                "full_amount" => $payment->amount,
                "refunded_amount" => $this->get_refunds_by_payment_id( $payment_id ),
                "refund_amount" => $refund_amount,
                "receipt_id" => $payment->receipt_id,
                "pay_method" => $payment->paysys,
            );

            if( $payment->status == 'refunded' ) {
                $data["refunded_amount"] = $payment->amount;
                $data["refund_amount"] = 0;
            }

			return $data;
        }    

    }

    private function get_payment_by_orderid( $order_id ) {

        global $wpdb;

        $table_name = $wpdb->prefix . 'frm_payments';
        $query = $wpdb->prepare( "SELECT * FROM $table_name WHERE `item_id`=%s", $order_id);
        $payment = $wpdb->get_row( $query );

        return $payment->id;

    }

    private function get_refunds_by_payment_id( $payment_id ) {

        global $wpdb;

        $table_name = $wpdb->prefix . 'frm_refunds_authnet';
        $query = $wpdb->prepare( "SELECT * FROM $table_name WHERE `payment_id`=%s", $payment_id);
        $refunds = $wpdb->get_results( $query, ARRAY_A );

        return array_sum(array_column($refunds, 'sum'));

    }


}