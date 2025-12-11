<?php
class Dotfiler_authnet_error_admin {

    // For meta fields
    private $meta_box_id = 'authnet_metabox';
    private $meta_box_title = 'Authorize.net Error Details';
    private $post_type = 'authnet_error';

    private $errors;

    public function __construct() {

        // For meta fields
        add_action('add_meta_boxes', array($this, 'add_authnet_metabox'));
        add_action('save_post', array($this, 'save_authnet_metabox'));

        // Listing columns
        add_filter('manage_authnet_error_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_authnet_error_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);

        $this->errors = new Dotfiler_authnet_errors();

    }

    public function add_custom_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['authnet_error_code'] = 'Error code';
                $new_columns['authnet_error_message'] = 'Error message';
            }
        }
        return $new_columns;
    }

    
    public function render_custom_columns($column, $post_id) {
        if ($column === 'authnet_error_code') {
            echo get_post_meta($post_id, 'authnet_error_code', true);
        }
        if ($column === 'authnet_error_message') {
            echo get_post_meta($post_id, 'authnet_error_message', true);
        }
    }

    public function add_authnet_metabox() {
        add_meta_box(
            $this->meta_box_id,
            $this->meta_box_title,
            array($this, 'render_authnet_metabox'),
            $this->post_type,
            'normal',
            'high'
        );
    }

    public function render_authnet_metabox($post) {

        wp_nonce_field(basename(__FILE__), 'authnet_metabox_nonce');
        
        // Get saved values
        $authnet_error_code = get_post_meta($post->ID, 'authnet_error_code', true);
        $authnet_error_message = get_post_meta($post->ID, 'authnet_error_message', true);

        if( isset($authnet_error_code) ) {
            $suggestion = $this->errors->retrieve_suggestion_json( $authnet_error_code );
            $suggestion_fields = ['text', 'description', 'other_suggestions', 'integration_suggestions'];
        }
        ?>

        <?php if( isset($suggestion) ) { ?>
            <div class="error-suggestion">
                <?php foreach( $suggestion_fields as $field ) { ?>
                    <?php if( isset( $suggestion[$field] ) ) { ?>
                        <b><?php echo $field; ?>:</b>
                        <p class=""> <?php echo $suggestion[$field]; ?> </p>
                    <?php } ?>
                <?php } ?>
            </div>
        <?php } ?>    

        <p>
            <label for="authnet_error_code">Error code:</label><br>
            <input type="text" id="authnet_error_code" name="authnet_error_code" value="<?php echo esc_attr($authnet_error_code); ?>">
        </p>
        <p>
            <label for="authnet_error_message">Error message:</label><br>
            <textarea name="authnet_error_message" style="width: 50%; height: 100px;"><?php echo esc_attr($authnet_error_message); ?></textarea>    
        </p>
    
        <?php
    }
    
    public function save_authnet_metabox($post_id) {
    
        if( !$this->check_rights( $post_id ) ) { return ; }
    
        $fields = array('authnet_error_code', 'authnet_error_message');
    
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    
    }

    private function check_rights( $post_id ) {

        if (!isset($_POST['authnet_metabox_nonce']) || !wp_verify_nonce($_POST['authnet_metabox_nonce'], basename(__FILE__))) {
            return;
        }
    
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
    
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        return true;

    }

}

new Dotfiler_authnet_error_admin();

