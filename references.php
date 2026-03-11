<?php

// Developer
define('DEVELOPER_EMAIL', 'matrosovdream@gmail.com');

// Passport photo form #1
define('FRM_FORM_1_FIELDS_MAP',[
    'status' => 7,
    'notes' => 5,
    'application_status' => 386,
    'process' => 273, // array
    'tracking_number' => 344,
    'payment_sum' => 148,
    'card_data_full' => 120,
    'card_last4' => 770,
    'card_cc_bin' => 771,
    'entry_created_date' => 874
]);

// Passport photo form #7
define('FRM_FORM_7_FIELDS_MAP',[
    'order_id' => 194,
    'status' => 193,
    'notes' => 257,
    'service' => 330,
    'email' => 664,
    'email_client' => 662,
    'message_addon' => 663,
    'uploaded_photo_id' => 215,
    'final_photo_id' => 668,
    'deny_reasons' => 712,
    'photo_complete' => 669,
    'photo_uploaded' => 274,
    'ai_image_processing' => 823
]);
define('FRM_FORM_7_FIELD_SELECT_VALUES', [
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
        'deny_reason' => [
            'label' => 'Deny Reason',
            'field_id' => 712
        ]
    ]
]);