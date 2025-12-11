<?php
class PhoneChecker_helper
{

    public static function validate_phone($phone): array
    {

        $checker = (new PhoneChecker($phone))->verify();

        if ($checker->is_error()) {
            $data['error'] = true;
            $data['message'] = $checker->get_error();
        } elseif ($checker->is_valid()) {

            $data['error'] = false;
            $data['fields'] = self::prepare_fields($checker);

        } else {
            $data['error'] = true;
            $data['message'] = 'Invalid phone number';
        }

        return $data;

    }

    public static function prepare_fields($checker): array
    {

        $fields = [];

        $fields['country'] = array('label' => 'Country', 'value' => $checker->get_country());
        $fields['country_code'] = array('label' => 'Country code', 'value' => $checker->get_country_code());
        $fields['location'] = array('label' => 'Location', 'value' => $checker->get_location());
        $fields['carrier'] = array('label' => 'Carrier', 'value' => $checker->get_carrier());
        $fields['line_type'] = array('label' => 'Line type', 'value' => $checker->get_line_type());

        return $fields;

    }

}