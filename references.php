<?php

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
    'photo_uploaded' => 274
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