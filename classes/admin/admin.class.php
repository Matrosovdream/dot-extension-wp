<?php
class Dotfiler_admin {
    private $url;

    public function __construct() {

        $this->setup_admin_page();

    }


    private function setup_admin_page() {
        add_action('admin_menu', array($this, 'dotfiler_api_menu'));
        add_action('admin_init', array($this, 'dotfiler_api_settings_init'));
    }

    public function dotfiler_api_menu() {
        add_menu_page(
            'Dotfiler Settings',
            'Dotfiler API',
            'manage_options',
            'dotfiler_api_settings',
            array($this, 'dotfiler_api_page')
        );
    }

    public function dotfiler_api_page() {
        ?>
        <div class="wrap">
            <h1>Dotfiler Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('dotfiler_api_group');
                do_settings_sections('dotfiler_api_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function dotfiler_api_settings_init() {

        register_setting('dotfiler_api_group', 'frm_remove_failed_entries');
        register_setting('dotfiler_api_group', 'frm_phone_validator_service');
        register_setting('dotfiler_api_group', 'numverify_access_key');
        register_setting('dotfiler_api_group', 'old_entries_period');
        register_setting('dotfiler_api_group', 'frm_cleaner_forms');
        register_setting('dotfiler_api_group', 'frm_cleaner_statuses');


        // Add new settings section for Formidable entries
        add_settings_section(
            'formidable_entries_section',
            'Formidable Entries Settings',
            array($this, 'formidable_entries_section_callback'),
            'dotfiler_api_settings'
        );
    
        // Add new checkbox field for removing failed entries
        add_settings_field(
            'frm_remove_failed_entries',
            'Remove Failed Entries',
            array($this, 'frm_remove_failed_entries_callback'),
            'dotfiler_api_settings',
            'formidable_entries_section'
        );

        // Phone validator settings
        add_settings_section(
            'formidable_phone_validator_section',
            'Phone Validator Settings',
            array($this, 'formidable_phone_validator_section_callback'),
            'dotfiler_api_settings'
        );

        add_settings_field(
            'frm_phone_validator_service',
            'Phone Validator Service',
            array($this, 'frm_phone_validator_service_callback'),
            'dotfiler_api_settings',
            'formidable_phone_validator_section'
        );

        add_settings_field(
            'numverify_access_key',
            'Numverify Access Key',
            array($this, 'numverify_access_key_callback'),
            'dotfiler_api_settings',
            'formidable_phone_validator_section'
        );

        // Entry cleaner settings (Formidable)
        add_settings_section(
            'entry_cleaner_section',
            'Entry cleaner settings (Formidable)',
            array($this, 'entry_cleaner_section_callback'),
            'dotfiler_api_settings'
        );

        // Old entries period
        add_settings_field(
            'old_entries_period',
            'Old entries period',
            array($this, 'old_entries_period_callback'),
            'dotfiler_api_settings',
            'entry_cleaner_section'
        );

        // Forms
        add_settings_field(
            'frm_cleaner_forms',
            'Forms',
            array($this, 'frm_cleaner_forms_callback'),
            'dotfiler_api_settings',
            'entry_cleaner_section'
        );

        // Statuses
        add_settings_field(
            'frm_cleaner_statuses',
            'Statuses',
            array($this, 'frm_cleaner_statuses_callback'),
            'dotfiler_api_settings',
            'entry_cleaner_section'
        );

    }

    public function formidable_entries_section_callback() {
        
    }

    public function entry_cleaner_section_callback() {
        
    }

    public function formidable_phone_validator_section_callback() {
        
    }

    public function old_entries_period_callback() {

        $default_days = ( new FRM_entry_cleaner() )->get_period();


        $value = get_option('old_entries_period');
        echo '<input type="number" name="old_entries_period" value="' . esc_attr($value) . '" style="width: 100px;" />';
        echo "<br/><span>days, $default_days - by default</span>";
    }

    public function frm_cleaner_forms_callback() {
        $forms = FrmForm::get_published_forms();
        $value = get_option('frm_cleaner_forms');
        if (empty($value)) {
            $value = [];
        }
        foreach ($forms as $id=>$form) {
            if( $form->name == '' )  { continue; } 
            $id = 'frm_cleaner_forms_'.$id;
            echo '<input type="checkbox" id="'.$id.'" name="frm_cleaner_forms[]" value="' . esc_attr($form->id) . '" ' . checked(true, in_array($form->id, $value), false) . ' />';
            echo '<label for="'.$id.'">' . esc_html($form->name) . '</label><br>';
        }
    }

    public function frm_cleaner_statuses_callback() {
        $statuses = [
            1 => 'Draft',
            2 => 'In Progress',
            3 => 'Abandoned',
        ];
        $value = get_option('frm_cleaner_statuses');
        if (empty($value)) {
            $value = [1,2,3];
        }
        $disabled = 'disabled';
        foreach ($statuses as $key => $label) {
            echo '<input type="checkbox" '.$disabled.' name="frm_cleaner_statuses[]" value="' . esc_attr($key) . '" ' . checked(true, in_array($key, $value), false) . ' />';
            echo '<label>' . esc_html($label) . '</label><br>';
        }
    }

    public function frm_phone_validator_service_callback() {
        $value = get_option('frm_phone_validator_service');
        if (empty($value)) {
            $value = 'numverify';
        }
        
        $services = [
            'numverify' => 'Numverify',
        ];
        foreach ($services as $key => $label) {
            echo '<input type="radio" name="frm_phone_validator_service" value="' . esc_attr($key) . '" ' . checked($key, $value, false) . ' />';
            echo '<label>' . esc_html($label) . '</label><br>';
        }

    }

    public function numverify_access_key_callback() {
        $value = get_option('numverify_access_key');
        echo '<input type="text" name="numverify_access_key" value="' . esc_attr($value) . '" style="width: 400px;" />';
    }
    
    public function frm_remove_failed_entries_callback() {
        $value = get_option('frm_remove_failed_entries');
        echo '<input type="checkbox" name="frm_remove_failed_entries" value="1" ' . checked(1, $value, false) . ' />';
    }
    
}

new Dotfiler_admin();