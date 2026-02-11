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

}