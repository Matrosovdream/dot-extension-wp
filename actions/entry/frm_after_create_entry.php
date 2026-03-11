<?php

add_action('frm_after_create_entry', function ($entry_id, $form_id) {

    switch ($form_id) {
        case 1:

            // Duplicate entry Id into the meta_value field
            dotExtDuplicateOrderId($entry_id);

            // Parse and update credit card number into the card field
            parseUpdateCreditCard($entry_id);

            // Set entry created date meta field
            setEntryCreatedDate($entry_id);
            
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

function setEntryCreatedDate( int $entry_id ) {

    $field_id = FRM_FORM_1_FIELDS_MAP['entry_created_date'];
    $currentDateTime = current_time('Y-m-d H:i:s');

    $entryHelper = new DotFrmEntryHelper();
    $entryHelper->updateMetaField($entry_id, $field_id, $currentDateTime);

}

function parseUpdateCreditCard(int $entry_id) {

    $fullCardField = FRM_FORM_1_FIELDS_MAP['card_data_full'];
    $cardField     = FRM_FORM_1_FIELDS_MAP['card_last4'];
    $cardBinField  = FRM_FORM_1_FIELDS_MAP['card_cc_bin'];

    $entryHelper = new DotFrmEntryHelper();

    $entry = $entryHelper->getEntryById($entry_id);
    $metas = $entry->metas;

    $fullCardValue = $metas[$fullCardField] ?? [];
    $ccValue = $fullCardValue['cc'] ?? '';

    // Save last 4 digits
    if (!empty($ccValue) && strlen($ccValue) >= 4) {
        $last4 = substr($ccValue, -4);
        $entryHelper->updateMetaField($entry_id, $cardField, $last4);
    }

    // Save BIN (first 6 digits)
    if (!empty($ccValue) && strlen($ccValue) >= 6) {
        $cardBinValue = substr($ccValue, 0, 6);
        $entryHelper->updateMetaField($entry_id, $cardBinField, $cardBinValue);
    }

}

add_action('init', function() {
    
    if( isset( $_GET['cc_card'] ) ) {
        $entry_id = intval($_GET['cc_card']);
        parseUpdateCreditCard($entry_id);
        exit();
    }

});