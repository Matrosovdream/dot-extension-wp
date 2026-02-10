<?php
if ( ! defined('ABSPATH') ) { exit; }

class DotFrmPhotoEntryHelper {

    private const FIELD_SELECT_VALUES = [
        [
            'form_id' => 7,
            'references' => [
                'status' => [
                    'label' => 'Status',
                    'field_id' => 193
                ],
                'agent' => [
                    'label' => 'Agent',
                    'field_id' => 694
                ],
                'passport_type' => [
                    'label' => 'Passport Type',
                    'field_id' => 330
                ],
            ]
        ]
    ];

    private const FIELDS_MAP = [
        'order_id' => 194,
        'status' => 193,
        'service' => 330,
        'email' => 664,
        'uploaded_photo_id' => 215,
        'final_photo_id' => 668,
    ];

    /**
     * Prepare / normalize entry before returning
     */
    public function prepareEntryItem(array $item): array {
        $item['id']      = (int) ($item['id'] ?? 0);
        $item['form_id'] = (int) ($item['form_id'] ?? 0);
        $item['metas']   = (isset($item['metas']) && is_array($item['metas'])) ? $item['metas'] : [];

        // Prepare mapped fields
        $fields = [];
        foreach (self::FIELDS_MAP as $key => $field_id) {
            $field_id = (int) $field_id;
            if ($field_id <= 0) { continue; }
            $fields[$key] = $item['metas'][$field_id] ?? null;

            if( $key === 'uploaded_photo_id' && !empty( $fields[$key] ) ) {
                $fields['uploaded_photo_url'] = wp_get_attachment_url( (int)$fields[$key] );
            }

            if( $key === 'final_photo_id' && !empty( $fields[$key] ) ) {
                $fields[$key] = $fields[$key][1];
                $fields['final_photo_url'] = wp_get_attachment_url( (int)$fields[$key] );
            }

        }

        $item['field_values'] = $fields;

        return $item;
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

        $page     = max(1, (int)$page);
        $paginate = max(1, (int)$paginate);
        $offset   = ($page - 1) * $paginate;

        $form_id = (int) $form_id;
        if ($form_id <= 0) {
            return [
                'ok' => false,
                'error' => 'Invalid form_id',
            ];
        }

        $where = [ 'i.form_id = %d' ];
        $args  = [ $form_id ];

        foreach ($filters as $filter) {

            if (!is_array($filter)) { continue; }
            if (empty($filter['field_id']) || !array_key_exists('value', $filter)) { continue; }

            $field_id = (int) $filter['field_id'];
            if ($field_id <= 0) { continue; }

            $value   = (string) $filter['value'];
            $compare = $filter['compare'] ?? '=';

            if (!in_array($compare, ['=', '!=', '%', '%%'], true)) {
                $compare = '=';
            }

            switch ($compare) {
                case '=':
                    $cmp_sql = "m.meta_value = %s";
                    $cmp_val = $value;
                    break;

                case '!=':
                    $cmp_sql = "m.meta_value <> %s";
                    $cmp_val = $value;
                    break;

                case '%':
                    $cmp_sql = "m.meta_value LIKE %s";
                    $cmp_val = $wpdb->esc_like($value) . '%';
                    break;

                case '%%':
                default:
                    $cmp_sql = "m.meta_value LIKE %s";
                    $cmp_val = '%' . $wpdb->esc_like($value) . '%';
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
            $args[] = $cmp_val;
        }

        $where_sql = implode(' AND ', $where);

        /**
         * Total count
         */
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$items_table} i WHERE {$where_sql}",
                $args
            )
        );

        /**
         * Items
         */
        $items_sql = "
            SELECT i.*
            FROM {$items_table} i
            WHERE {$where_sql}
            ORDER BY i.id DESC
            LIMIT %d OFFSET %d
        ";

        $items = $wpdb->get_results(
            $wpdb->prepare($items_sql, array_merge($args, [ $paginate, $offset ])),
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

        /**
         * Load metas
         */
        $item_ids = array_values(array_unique(array_map(fn($i) => (int)$i['id'], $items)));
        $placeholders = implode(',', array_fill(0, count($item_ids), '%d'));

        $items = [];
        foreach ($item_ids as $k => $id) {
            $items[] = $this->getEntryById($id);
        }

        return [
            'ok' => true,
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $paginate,
                'total' => $total,
                'total_pages' => (int) ceil($total / $paginate),
            ],
        ];
    }

    public function getEntryById(int $entry_id): ?array {

        $entryRaw = FrmEntry::getOne($entry_id, true);
        // To array by json encode/decode
        $entry = json_decode(json_encode($entryRaw), true);

        return $this->prepareEntryItem($entry);

    }

    public function getSelectRefs(int $form_id): array {

        $helper = new DotFrmEntryHelper();
        return $helper->getSelectRefs($form_id, self::FIELD_SELECT_VALUES);
        
    }

}
