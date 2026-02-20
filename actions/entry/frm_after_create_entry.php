<?php

add_action('frm_after_create_entry', function ($entry_id, $form_id) {

    if ( $form_id !== 1 ) {
        return;
    }

    // Duplicate entry Id into the meta_value field
    $field_id = 792;

    $entryHelper = new DotFrmEntryHelper();
    $entryHelper->updateMetaField($entry_id, $field_id, $entry_id);

}, 20, 2);