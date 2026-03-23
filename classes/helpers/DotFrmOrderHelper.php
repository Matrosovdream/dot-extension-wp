<?php

class DotFrmOrderHelper {

    private const FIELD_SELECT_VALUES = [
        [
            'form_id' => 1,
            'references' => [
                'status' => [
                    'label' => 'Status',
                    'field_id' => 7
                ],
                'application_status' => [
                    'label' => 'Application status',
                    'field_id' => 386
                ],
            ]
        ]
    ];

    private const FIELDS_MAP = FRM_FORM_1_FIELDS_MAP;

    public function getOrderById(int $item_id) {

        $entryHelper = new DotFrmEntryHelper();
        $entry = $entryHelper->getEntryById($item_id);

        // Prepare / normalize entry before returning
        $entry = $entryHelper->prepareEntryItem($entry, self::FIELDS_MAP);

        // Get payment details
        $entry['payment'] = $this->getOrderPayment( $item_id );

        return $entry;

        echo '<pre>';
        print_r($entry);
        echo '</pre>';

    }

    public function getOrderPayment(int $entry_id): ?array {

        $ref = new Dotfiler_authnet_refund();
        return $ref->get_payment( $entry_id );

    }

    public function refundPaymentByOrderId( int $entry_id, float $amount, string $reason, bool $fullSum ):mixed {

        $ref = new Dotfiler_authnet_refund();

        $payment = $ref->get_payment( $entry_id );
        $payment_id = $payment['id'];

        if ($fullSum) {
            $amount = $payment['full_amount'];
        }

        if( isset($payment) ) {
            $refundRes = $ref->refund_payment( $payment_id, $entry_id, $amount );
            if( !$refundRes['ok'] ) {
                return new WP_Error('refund_failed', ($refundRes['message'] ?? 'Unknown error'));
            }
            return $refundRes;
        } else {
            return new WP_Error('payment_not_found', 'Payment not found for entry ID: ' . $entry_id);
        }

    }

    public function runFullRefund( int $entry_id, string $reason='' ):mixed {

        $entryHelper = new DotFrmEntryHelper();

        $refundRes = $this->refundPaymentByOrderId( $entry_id, 0, $reason, true );
        if (is_wp_error($refundRes)) {
            return $refundRes;
        }

        // Set status to refunded
        $this->setStatus( $entry_id, 'Refunded' );

        // Set process field to 'chargedback' (for reporting purposes)
        $entryHelper->updateMetaField( $entry_id, self::FIELDS_MAP['process'] ?? 0, ['chargedback'] );

        // Get order data after refund
        $orderData = $this->getOrderById( $entry_id );

        return [
            'ok' => true,
            'message' => 'Refund successful',
            'refund_result' => $refundRes,
            'order_data' => $orderData
        ];

    }

    public function setOrderCardData( int $item_id, string $bin, string $last4, string $full_card = '' ): void {

        $helper = new DotFrmEntryHelper();

        $helper->updateMetaField( $item_id, self::FIELDS_MAP['card_cc_bin']   ?? 0, $bin );
        $helper->updateMetaField( $item_id, self::FIELDS_MAP['card_last4']    ?? 0, $last4 );

        if ( $full_card !== '' ) {
            $helper->updateMetaField( $item_id, self::FIELDS_MAP['card_data_full'] ?? 0, $full_card );
        }

    }

    public function getSelectRefs(int $form_id): array {
        $helper = new DotFrmEntryHelper();
        return $helper->getSelectRefs($form_id, self::FIELD_SELECT_VALUES);
    }

    public function setStatus(int $item_id, string $status): bool {

        $helper = new DotFrmEntryHelper();
        return $helper->updateMetaField(
            $item_id,
            self::FIELDS_MAP['status'] ?? 0,
            $status
        );

    }

    /**
     * Find items (entries) by card BIN + last4.
     *
     * @param string|int $bin
     * @param string|int $last4
     * @return array list of rows (item_id, created_at, updated_at)
     */
    public function getItemsByCardValues(int $bin, int $last4): array {

        global $wpdb;

        if ($bin === '' || $last4 === '') {
            return [];
        }

        $items = $wpdb->prefix . 'frm_items';
        $metas = $wpdb->prefix . 'frm_item_metas';

        $fieldBin  = (int) (self::FIELDS_MAP['card_cc_bin'] ?? 0); 
        $fieldCard = (int) (self::FIELDS_MAP['card_last4'] ?? 0);

        if ($fieldBin <= 0 || $fieldCard <= 0) {
            return [];
        }

        // card_cc stored as full card? -> match by ending digits
        $likeLast4 = $wpdb->esc_like($last4);

        $sql = "
            SELECT i.id AS item_id, i.created_at, i.updated_at
            FROM {$items} i
            INNER JOIN {$metas} m_bin
                ON m_bin.item_id = i.id AND m_bin.field_id = %d AND m_bin.meta_value = %s
            INNER JOIN {$metas} m_card
                ON m_card.item_id = i.id AND m_card.field_id = %d AND m_card.meta_value = %s
            WHERE i.form_id = %d
            ORDER BY i.id DESC
            LIMIT 200
        ";

        if( isset( $_GET['log2'] ) ) {
            echo '<pre>';
            echo $wpdb->prepare($sql, $fieldBin, $bin, $fieldCard, $likeLast4, 1);
            echo '</pre>';
        }

        $res = $wpdb->get_results(
            $wpdb->prepare($sql, $fieldBin, $bin, $fieldCard, $likeLast4, 1),
            ARRAY_A
        );

        $items = [];
        foreach($res as $row) {
            $items[] = $this->getOrderById( (int) $row['item_id'] );
        }

        return $items;

    }

}