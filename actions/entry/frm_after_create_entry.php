<?php

add_action('frm_after_create_entry', function ($entry_id, $form_id) {

    switch ($form_id) {
        case 1:
            // Duplicate entry Id into the meta_value field
            dotExtDuplicateOrderId($entry_id);
            break;

        case 7:
            // Update final image by AI image enhancer
            dotExtUpdateImageEnhancer($entry_id);
            break;
    }

}, 20, 2);

function dotExtDuplicateOrderId( int $entry_id ) {

    $field_id = 792;

    $entryHelper = new DotFrmEntryHelper();
    $entryHelper->updateMetaField($entry_id, $field_id, $entry_id);

}

function dotExtUpdateImageEnhancer( int $entry_id ) {

    // Schedule single event to update image enhancer
    DotFrmImageEnhancerSingleEvent::schedule($entry_id, 30);

}