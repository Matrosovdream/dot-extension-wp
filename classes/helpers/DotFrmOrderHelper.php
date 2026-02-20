<?php

class DotFrmOrderHelper {

    private const FIELD_SELECT_VALUES = [
        [
            'form_id' => 1,
            'references' => [
                'status' => [
                    'label' => 'Status',
                    'field_id' => 7
                ],
                'application_status' => [
                    'label' => 'Application status',
                    'field_id' => 386
                ],
            ]
        ]
    ];

    private const FIELDS_MAP = [

    ];

    public function getSelectRefs(int $form_id): array {
        
        $helper = new DotFrmEntryHelper();
        return $helper->getSelectRefs($form_id, self::FIELD_SELECT_VALUES);

    }

}