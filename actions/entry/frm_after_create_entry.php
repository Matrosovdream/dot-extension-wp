<?php

add_action('frm_after_create_entry', function ($entry_id, $form_id) {

    switch ($form_id) {
        case 1:

            // Duplicate entry Id into the meta_value field
            dotExtDuplicateOrderId($entry_id);

            // Parse and update credit card number into the card field
            parseUpdateCreditCard($entry_id);
            
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
    DotFrmImageEnhancerSingleEvent::schedule($entry_id, 3);

}

function parseUpdateCreditCard( int $entry_id ) {

    $fullCardField = FRM_FORM_7_FIELDS_MAP['card_data_full'];
    $cardField = FRM_FORM_7_FIELDS_MAP['card_cc'];

    $entryHelper = new DotFrmEntryHelper();

    $entry = $entryHelper->getEntryById($entry_id);
    $metas = $entry->metas;

    /*
    echo "<pre>";
    print_r($entry->metas);
    echo "</pre>";
    */

    $fullCardValue = $metas[$fullCardField] ?? '';
    $ccValue = $fullCardValue['cc'] ?? '';

    // Update the card field with the extracted credit card number
    if( !empty($ccValue) ) {
        $entryHelper->updateMetaField($entry_id, $cardField, $ccValue);
    }

}


add_action('init', function() {
    
    if( isset( $_GET['cc_card'] ) ) {
        $entry_id = intval($_GET['cc_card']);
        parseUpdateCreditCard($entry_id);
        exit();
    }

});