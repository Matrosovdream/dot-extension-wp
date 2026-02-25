<?php

if ( ! defined('ABSPATH') ) { exit; }


add_filter('rest_pre_dispatch', function ($result, $server, $request) {

    // Only intercept Formidable v2 "form entries" route
    $route = (string) $request->get_route();
    // Typical route: /frm/v2/forms/(?P<id>[\d]+)/entries
    if (strpos($route, '/frm/v2/forms/') !== 0 || substr($route, -8) !== '/entries') {
        return $result;
    }

    $where = $request->get_param('where');
    if (empty($where) || ! is_array($where)) {
        return $result; // no where[] => let Formidable handle normally
    }

    // Extract form id from route or params
    $form_id = (int) $request->get_param('id');
    if ($form_id <= 0) {
        // fallback parse: /frm/v2/forms/1/entries
        if (preg_match('~^/frm/v2/forms/(\d+)/entries$~', $route, $m)) {
            $form_id = (int) $m[1];
        }
    }
    if ($form_id <= 0) {
        return $result; // can't determine form id; let default
    }

    // Normalize where conditions: only numeric field ids
    $conditions = [];
    foreach ($where as $field_id => $value) {
        $field_id = (int) $field_id;
        if ($field_id <= 0) { continue; }
        $value = is_scalar($value) ? (string) $value : '';
        if ($value === '') { continue; }
        $conditions[$field_id] = $value;
    }

    if (empty($conditions)) {
        return $result; // nothing valid; let default
    }

    global $wpdb;

    $items = $wpdb->prefix . 'frm_items';
    $metas = $wpdb->prefix . 'frm_item_metas';

    // Pagination (optional)
    $page_size = (int) $request->get_param('page_size');
    if ($page_size <= 0) { $page_size = 25; }
    $page = (int) $request->get_param('page');
    if ($page <= 0) { $page = 1; }
    $offset = ($page - 1) * $page_size;

    // Build an AND filter across multiple field_id/meta_value pairs.
    // We do this by joining metas multiple times: m1, m2, ...
    $joins = [];
    $params = [ $form_id ];

    $i = 0;
    foreach ($conditions as $fid => $val) {
        $i++;
        $alias = "m{$i}";
        $joins[] = $wpdb->prepare(
            "INNER JOIN {$metas} {$alias}
                ON {$alias}.item_id = e.id
               AND {$alias}.field_id = %d
               AND {$alias}.meta_value = %s",
            $fid,
            $val
        );
        // prepare already injected values; no need to add to $params here
    }

    // Draft filter if column exists (some installs have it)
    $draft_sql = '';
    $has_is_draft = $wpdb->get_var("SHOW COLUMNS FROM {$items} LIKE 'is_draft'");
    if ($has_is_draft) {
        $draft_sql = " AND e.is_draft = 0 ";
    }

    $sql = "
        SELECT e.id
        FROM {$items} e
        " . implode("\n", $joins) . "
        WHERE e.form_id = %d
        {$draft_sql}
        ORDER BY e.id DESC
        LIMIT %d OFFSET %d
    ";

    // Add paging params
    $params = [ $form_id, $page_size, $offset ];

    // IMPORTANT: because we used $wpdb->prepare inside joins already,
    // we only prepare the outer query for the remaining placeholders.
    $prepared = $wpdb->prepare($sql, $params);

    $entry_ids = $wpdb->get_col($prepared);
    $entry_ids = array_map('intval', (array) $entry_ids);

    // Return in a "Formidable-like" structure (best effort)
    $data = [];

    if (class_exists('FrmEntry') && method_exists('FrmEntry', 'getOne')) {
        foreach ($entry_ids as $eid) {
            $entry = FrmEntry::getOne($eid, true); // include meta if supported
            if (! $entry) { continue; }
            $data[] = (array) $entry;
        }
    } else {
        // Fallback: return just IDs
        $data = $entry_ids;
    }

    return new WP_REST_Response($data, 200);

}, 10, 3);
