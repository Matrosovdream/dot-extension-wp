<?php

class DotFrmMassRefundHelper {

    private const FIELD_SELECT_VALUES = [
        [
            'form_id' => 4,
            'references' => [
                'status' => [
                    'label' => 'Status',
                    'field_id' => 150
                ],
                'service' => [
                    'label' => 'Service',
                    'field_id' => 151
                ],
                'amount_type' => [
                    'label' => 'Amount Type',
                    'field_id' => 156
                ],
            ]
        ]
    ];

    private const FIELDS_MAP = [
        'order_id' => 152,
        'status' => 150,
        'service' => 151,
        'email' => 154,
        'amount' => 155,
        'amount_type' => 156,
        'refund_reason' => 157,
    ];

    /**
     * Prepare / normalize entry before returning
     */
    public function prepareEntryItem(array $item): array {
        $item['id']      = (int) ($item['id'] ?? 0);
        $item['form_id'] = (int) ($item['form_id'] ?? 0);
        $item['metas']   = (isset($item['metas']) && is_array($item['metas'])) ? $item['metas'] : [];

        $item['created_at'] = date('Y-m-d H:i', strtotime($item['created_at']));

        // Prepare mapped fields
        $fields = [];
        foreach (self::FIELDS_MAP as $key => $field_id) {
            $field_id = (int) $field_id;
            if ($field_id <= 0) { continue; }
            $fields[$key] = $item['metas'][$field_id] ?? null;
        }

        $item['field_values'] = $fields;
        $order_id =$item['field_values']['order_id'] ?? null;

        if( $order_id ) {
            $item['order'] = $this->getOriginalOrder( $order_id );
            $item['order']['refund_history'] = $this->getOrderRefundHistory( $order_id );
            $item['order']['payment'] = $this->getOrderPayment( $order_id );
        }

        // Get similar refunds requests with the same order_id
        $item['similar_refunds'] = $this->getSimilarRefunds( $order_id, $item['id'] );

        return $item;
    }

    public function getSimilarRefunds( int $order_id, int $exclude_entry_id ): array {

        $helper = new DotFrmEntryHelper();
        $filters = [
            [
                'field_id' => self::FIELDS_MAP['order_id'],
                'value'    => $order_id,
                'compare'  => '=',
            ],
        ];

        $res = $helper->getList( $filters, 1, 100, 4 );

        $items = [];
        $items_ids = [];
        foreach( $res['data'] as $item ) {
            if( $item->id == $exclude_entry_id ) { continue; }
            $items[] = $item;
            $items_ids[] = $item->id;
        }

        return [
            //'items' => $items,
            'item_ids' => $items_ids,
            'count' => count( $items_ids )
        ];

    }

    public function getEntryById(int $entry_id): ?array {

        $entry = $this->getEntryRawById( $entry_id );
        return $this->prepareEntryItem($entry);

    }

    public function getOriginalOrder(int $entry_id) {

        $entry = $this->getEntryRawById($entry_id);

        $metas = $entry['metas'] ?? [];

        $field_values = [
            'status' => $metas[7] ?? null,
            'payment_sum' => $metas[148] ?? null,
            'payment_card' => $metas[120] ?? null,
            'notes' => $metas[5] ?? null,
        ];

        // Process fields
        $entry['created_at'] = date( 'Y-m-d H:i', strtotime( $entry['created_at'] ) );

        unset( $entry['metas'] );
        $entry['field_values'] = $field_values;
        $entry['shipments'] = $this->getOrderShipments( $entry_id );

        return $entry;

    }

    public function getEntryRawById(int $entry_id): ?array {
        $entryRaw = FrmEntry::getOne( $entry_id, true );
        $entry = json_decode( json_encode( $entryRaw ), true );
        return $entry;
    }

    public function getOrderRefundHistory( int $order_id ): array {

        $ref = new Dotfiler_authnet_refund();
        $payment = $ref->get_payment( $order_id );
        return $payment;

    }

     /**
      * Get dropdown/select options from wp_frm_fields.options
      */

    public function getSelectRefs(int $form_id): array {

        $helper = new DotFrmEntryHelper();
        return $helper->getSelectRefs($form_id, self::FIELD_SELECT_VALUES);
        
    }

    public function getList( array $filters, int $page = 1, int $paginate = 20, int $form_id ) {

        $helper = new DotFrmEntryHelper();
        $res = $helper->getList( $filters, $page, $paginate, $form_id );

        $items = [];
        foreach( $res['data'] as $item ) {
            $items[] = $this->getEntryById($item->id );
        }
        $res['data'] = $items;

        return $res;

    }

    public function refundPaymentByOrderId( int $entry_id, float $amount, string $reason ):array {

        $ref = new Dotfiler_authnet_refund();

        $payment = $ref->get_payment( $entry_id );
        $payment_id = $payment['id'];

        if( isset($payment) ) {
            $refundRes = $ref->refund_payment( $payment_id, $entry_id, $amount );
        } else {
            $refundRes = [ 'ok'=>false,'error'=>'Payment not found' ];
        }

        return $refundRes;

    }

    public function setRefundStatus( int $refund_id, string $status ): void {

        $helper = new DotFrmEntryHelper();
        $helper->updateMetaField( $refund_id, self::FIELDS_MAP['status'], $status );

    }

    public function setOrderStatus( int $order_id, string $status ): void {

        $helper = new DotFrmEntryHelper();
        $helper->updateMetaField( $order_id, 7, $status );

    }

    public function updateRefundFields( int $refund_id, array $fields ): void {

        $helper = new DotFrmEntryHelper();

        foreach( $fields as $key => $value ) {
            if( !isset( self::FIELDS_MAP[$key] ) ) { continue; }
            $field_id = self::FIELDS_MAP[$key];
            $helper->updateMetaField( $refund_id, $field_id, $value );
        }

    }

    public function getOrderShipments( int $entry_id ): array {

        $helper = new DotFrmEntryHelper();
        return $helper->getEntryShipments( $entry_id );

    }

    public function getOrderRefStatuses() {

        return [
            'values' => [
                'Refunded' => 'Refunded',
             ]
        ];

    }

    public function getOrderPayment( int $order_id ) {

        $ref = new Dotfiler_authnet_refund();
        return $ref->get_payment( $order_id );

    }

    

}