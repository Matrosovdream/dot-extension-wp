<?php
/*
Allows the admin to attach a file to an email. 
This is useful when you want to send a file to the user after they submit a form.
Just insert the shortcode [attach field={field_id}] in the email message.
*/

class AttachFilesToEmail {

    private $shortcode_pattern = '/\[attach field=(\d+)\]/';

    public function __construct() {

        // Read files the from form and attach it to the email
        add_filter('frm_notification_attachment', array($this, 'addAttachmentsToEmail'), 10, 3);

        // Remove attach cursors from email message
        add_filter('frm_email_message', array($this, 'modifyMessage'), 10, 2);
    }

    public function addAttachmentsToEmail($attachments, $form, $args) {

        $entry = $args['entry'];
        $entry_id = $entry->id;
        $email_message = $args['settings']['email_message'];

        // Extract all field ids from the email message
        $field_ids = $this->extractFileIds($email_message);

        $value = FrmEntryMeta::get_entry_meta_by_field( $entry_id, $field_ids[0], true );

        if( is_array($value) ) {
            foreach( $value as $file_id ) {
                $files_url = wp_get_attachment_url($file_id);
    
                // Convert $files_url to a file path
                $files_url = str_replace(site_url(), ABSPATH, $files_url);
    
                if (file_exists($files_url)) {
                    $attachments[] = $files_url;
                }
            }
        } else {
            $file_id = $value;
            $files_url = wp_get_attachment_url($file_id);
    
            // Convert $files_url to a file path
            $files_url = str_replace(site_url(), ABSPATH, $files_url);
    
            if (file_exists($files_url)) {
                $attachments[] = $files_url;
            }
        }
    
        return $attachments;

    }

    private function extractFileIds($email_message) {

        preg_match_all($this->shortcode_pattern, $email_message, $matches);
    
        // Get the field ids from the shortcodes
        $field_ids = [];
        foreach ($matches[0] as $match) {
            preg_match('/\d+/', $match, $field_id);
            $field_ids[] = $field_id[0];
        }

        return $field_ids;
    }

    public function modifyMessage($subject, $atts) {
  
        // Remove shortcode links
        $subject = preg_replace($this->shortcode_pattern, '', $subject);

        // Remove File upload table
        $subject = preg_replace('/<table.*?File Upload.*?<\/table>/s', '', $subject);

        return $subject;

    }

}


// Initialize filters/hooks
new AttachFilesToEmail();




add_action('init', 'init_attach_files_to_email');
function init_attach_files_to_email() {
    
    if( isset( $_GET['attach_email'] ) ) {

        $result = frm_simulate_email_for_entry(
            $_GET['entry'],
            "Hello! Here is your file: [attach field=671]"
        );
        
        echo "<pre>";
        print_r($result);
        echo "</pre>";

        die();
        
    }

}


/**
 * Simulate Formidable email building for a given entry_id.
 *
 * @param int    $entry_id       The Formidable entry ID to use.
 * @param string $email_message  The raw email body that may include [attach field=ID] shortcodes.
 * @param array  $extra_settings Optional. Extra settings you want available on $args['settings'].
 *
 * @return array {
 *   @type string $message      The filtered email message (shortcodes/table removed).
 *   @type array  $attachments  Absolute file paths selected by the [attach field=...] shortcodes.
 * }
 */
function frm_simulate_email_for_entry( int $entry_id, string $email_message, array $extra_settings = [] ): array {
    if ( ! class_exists('FrmEntry') ) {
        return [
            'message'     => $email_message,
            'attachments' => [],
            'error'       => 'Formidable Forms is not loaded (FrmEntry missing).',
        ];
    }

    // 1) Load entry + form like Formidable would.
    $entry = \FrmEntry::getOne( $entry_id, true );
    if ( ! $entry ) {
        return [
            'message'     => $email_message,
            'attachments' => [],
            'error'       => 'Entry not found.',
        ];
    }

    // Form can be helpful for downstream filters, but not strictly required for your class.
    $form = null;
    if ( class_exists('FrmForm') ) {
        $form = \FrmForm::getOne( $entry->form_id );
    }

    // 2) Build args the way Formidable does when sending notifications.
    //    Your class expects $args['entry'] and $args['settings']['email_message'].
    $args = [
        'entry'    => $entry,
        'settings' => array_merge(
            [
                'email_message' => $email_message,
                // add any other defaults Formidable might include if you need them
            ],
            $extra_settings
        ),
    ];

    // 3) Run the attachments filter first (this will call AttachFilesToEmail::addAttachmentsToEmail).
    $attachments = apply_filters( 'frm_notification_attachment', [], $form, $args );

    // 4) Run the message filter (this will call AttachFilesToEmail::modifyMessage).
    //    Second parameter is usually an $atts array; pass something harmless.
    $filtered_message = apply_filters( 'frm_email_message', $email_message, [ 'context' => 'simulate' ] );

    return [
        'message'     => $filtered_message,
        'attachments' => is_array( $attachments ) ? $attachments : [],
    ];
}
