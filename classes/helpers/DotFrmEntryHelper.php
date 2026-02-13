<?php

class DotFrmEntryHelper {

    public function getEntryById($entryId) {

        if (class_exists('FrmEntry')) {
            return FrmEntry::getOne($entryId, true);
        }
        return null;

    }

    public function updateMetaField(int $entry_id, int $field_id, $value): bool
    {
        $ok = \FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $value);
        if (!$ok) {
            if (method_exists('\FrmEntryMeta', 'delete_entry_meta')) {
                \FrmEntryMeta::delete_entry_meta($entry_id, $field_id);
            }
            \FrmEntryMeta::add_entry_meta($entry_id, $field_id, '', $value);
        }
        return true;
    }

     /**
     * Get dropdown/select options from wp_frm_fields.options
     */
    public function getSelectFieldValues(int $field_id): array {
        global $wpdb;

        $field_id = (int) $field_id;
        if ($field_id <= 0) { return []; }

        $fields_table = $wpdb->prefix . 'frm_fields';

        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `options` FROM {$fields_table} WHERE id = %d LIMIT 1",
                $field_id
            )
        );

        if (!$raw) { return []; }

        $opts = maybe_unserialize($raw);
        if (!is_array($opts)) { return []; }

        $out = [];

        foreach ($opts as $row) {
            if (!is_array($row)) { continue; }

            $label = trim((string)($row['label'] ?? ''));
            $value = trim((string)($row['value'] ?? ''));

            if ($label === '' && $value === '') { continue; }

            if ($label === '') { $label = $value; }
            if ($value === '') { $value = $label; }

            $out[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $out;
    }

    public function getSelectRefs(int $form_id, array $fieldsMap): array {
        
        $form_id = (int) $form_id;
        if ($form_id <= 0) {
            return [];
        }

        $entryHelper = new DotFrmEntryHelper();

        foreach ($fieldsMap as $row) {

            if ((int)($row['form_id'] ?? 0) !== $form_id) {
                continue;
            }

            if (empty($row['references']) || !is_array($row['references'])) {
                return [];
            }

            $out = [];

            foreach ($row['references'] as $key => $ref) {

                if (
                    !is_array($ref) ||
                    empty($ref['field_id'])
                ) {
                    continue;
                }

                $field_id = (int) $ref['field_id'];

                $out[$key] = [
                    'label'    => (string) ($ref['label'] ?? $key),
                    'field_id' => $field_id,
                    'values'   => $entryHelper->getSelectFieldValues($field_id),
                ];
            }

            return $out;
        }

        return [];

    }

    /**
     * Get paginated list of Formidable entries with meta filters
     *
     * Filters format:
     * [
     *   [
     *     'field_id' => 123,
     *     'value'    => 'test',
     *     'compare'  => '=', '!=', '%', '%%'
     *   ],
     * ]
     */
    public function getList(
        array $filters,
        int $page = 1,
        int $paginate = 20,
        int $form_id
    ): array {
    
        global $wpdb;
    
        $items_table = $wpdb->prefix . 'frm_items';
        $metas_table = $wpdb->prefix . 'frm_item_metas';
    
        $page     = max(1, (int) $page);
        $paginate = max(1, (int) $paginate);
        $offset   = ($page - 1) * $paginate;
    
        $form_id = (int) $form_id;
        if ($form_id <= 0) {
            return ['ok' => false, 'error' => 'Invalid form_id'];
        }
    
        $where = ['i.form_id = %d'];
        $args  = [$form_id];
    
        foreach ($filters as $filter) {
            if (!is_array($filter)) { continue; }
            if (empty($filter['field_id']) || !array_key_exists('value', $filter)) { continue; }
    
            $field_id = (int) $filter['field_id'];
            if ($field_id <= 0) { continue; }
    
            $compare = strtoupper((string)($filter['compare'] ?? '='));
            $allowed = ['=', '!=', '%', '%%', 'IN', 'NOT IN'];
            if (!in_array($compare, $allowed, true)) {
                $compare = '=';
            }
    
            // Build condition for meta_value + args
            $cmp_sql  = '';
            $cmp_args = [];
    
            switch ($compare) {
                case '=':
                    $cmp_sql  = "m.meta_value = %s";
                    $cmp_args = [(string) $filter['value']];
                    break;
    
                case '!=':
                    $cmp_sql  = "m.meta_value <> %s";
                    $cmp_args = [(string) $filter['value']];
                    break;
    
                case '%':
                    $cmp_sql  = "m.meta_value LIKE %s";
                    $cmp_args = [$wpdb->esc_like((string) $filter['value']) . '%'];
                    break;
    
                case '%%':
                    $cmp_sql  = "m.meta_value LIKE %s";
                    $cmp_args = ['%' . $wpdb->esc_like((string) $filter['value']) . '%'];
                    break;
    
                case 'IN':
                case 'NOT IN':
                    $vals = $filter['value'];
    
                    // Must be a non-empty array
                    if (!is_array($vals) || !$vals) { continue; }
    
                    // Normalize values as strings (meta_value is stored as text)
                    $vals = array_values(array_filter(array_map(
                        static fn($v) => (string) $v,
                        $vals
                    ), static fn($v) => $v !== ''));
    
                    if (!$vals) { continue; }
    
                    $ph = implode(',', array_fill(0, count($vals), '%s'));
                    $cmp_sql  = "m.meta_value {$compare} ({$ph})";
                    $cmp_args = $vals;
                    break;
            }
    
            $where[] = "
                EXISTS (
                    SELECT 1
                    FROM {$metas_table} m
                    WHERE m.item_id = i.id
                      AND m.field_id = %d
                      AND {$cmp_sql}
                )
            ";
    
            $args[] = $field_id;
            foreach ($cmp_args as $a) {
                $args[] = $a;
            }
        }
    
        $where_sql = implode(' AND ', $where);
    
        // Total count
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$items_table} i WHERE {$where_sql}",
                $args
            )
        );
    
        // Items
        $items_sql = "
            SELECT i.*
            FROM {$items_table} i
            WHERE {$where_sql}
            ORDER BY i.id DESC
            LIMIT %d OFFSET %d
        ";
    
        $items = $wpdb->get_results(
            $wpdb->prepare($items_sql, array_merge($args, [$paginate, $offset])),
            ARRAY_A
        );
    
        if (!$items) {
            return [
                'ok' => true,
                'data' => [],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $paginate,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $paginate),
                ],
            ];
        }
    
        $item_ids = array_values(array_unique(array_map(static fn($i) => (int) $i['id'], $items)));
    
        $out = [];
        foreach ($item_ids as $id) {
            $out[] = $this->getEntryById($id);
        }
    
        return [
            'ok' => true,
            'data' => $out,
            'pagination' => [
                'page' => $page,
                'per_page' => $paginate,
                'total' => $total,
                'total_pages' => (int) ceil($total / $paginate),
            ],
        ];
    }
    

    // Easypost shipments for an entry
    public function getEntryShipments( int $entry_id ): array {

        $model     = new FrmEasypostShipmentModel();
        $shipments = $model->getAllByEntryId( $entry_id );
  
        // Collect stats
        $stats = [
          'total' => count( $shipments ),
          'voided' => 0,
          'refunded' => 0,
          'delivered' => 0,
          'active' => 0
        ];
  
        foreach ( $shipments as $s ) {
  
          if ( isset( $s['status'] ) ) {
            $status = strtolower( (string) $s['status'] );
            if ( $status === 'voided' ) {
              $stats['voided']++;
            } elseif ( $status === 'delivered' ) {
              $stats['delivered']++;
            }
  
            if( $status !== 'voided' && empty( $s['refund_status'] ) ) {
              $stats['active']++;
            }
  
          }
  
          if ( ! empty( $s['refund_status'] ) ) {
            $stats['refunded']++;
          }
  
        }
  
        return [
          'stats'     => $stats,
          'shipments' => $shipments,
        ];
    }

    /**
     * Get entry IDs from a given Formidable form
     * where:
     *  - created_at <= $dateLimit
     *  - not draft
     *  - and referenced via another entry meta field (field_id = $fieldId)
     *
     * @param int    $formId     Form ID to filter (e.g. 1)
     * @param string $dateLimit  MySQL datetime string (Y-m-d H:i:s)
     * @param int    $fieldId    Meta field that stores the reference (default 152)
     *
     * @return int[] Array of entry IDs
     */
    public function getEntryItemsConnWithMeta(
        int $formId,
        int $fieldId = 152,
        string $operator = '>=',        // >= | <= | BETWEEN
        string $dateFrom,
        ?string $dateTo = null          // требуется только для BETWEEN
    ): array {
    
        global $wpdb;
    
        $items = $wpdb->prefix . 'frm_items';
        $metas = $wpdb->prefix . 'frm_item_metas';
    
        $allowedOperators = ['>=', '<=', 'BETWEEN'];
    
        if (!in_array($operator, $allowedOperators, true)) {
            $operator = '>=';
        }
    
        $whereDate = '';
        $args = [$formId];
    
        if ($operator === 'BETWEEN') {
    
            if (!$dateTo) {
                return [];
            }
    
            $whereDate = "i.created_at BETWEEN %s AND %s";
            $args[] = $dateFrom;
            $args[] = $dateTo;
    
        } else {
    
            $whereDate = "i.created_at {$operator} %s";
            $args[] = $dateFrom;
        }
    
        $sql = "
            SELECT i.id
            FROM {$items} i
            WHERE i.form_id = %d
            AND i.is_draft = 0
            AND {$whereDate}
            AND EXISTS (
                SELECT 1
                FROM {$metas} im
                WHERE im.field_id = %d
                  AND im.meta_value = CAST(i.id AS CHAR)
            )
            ORDER BY i.id DESC
        ";
    
        $args[] = $fieldId;
    
        $prepared = $wpdb->prepare($sql, $args);
    
        $ids = $wpdb->get_col($prepared);
    
        return array_map('intval', $ids);
    }
    


}