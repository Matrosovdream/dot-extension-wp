<?php
class Dotfiler_authnet_admin {

    // For meta fields
    private $meta_box_id = 'authnet_metabox';
    private $meta_box_title = 'Authorize.net Settings';
    private $post_type = 'authnet_account';

    private $service;

    public function __construct() {

        // Add custom column to post type list
        add_filter('manage_authnet_account_posts_columns', array($this, 'add_custom_column_to_post_type_list'));
        add_action('manage_authnet_account_posts_custom_column', array($this, 'populate_custom_column_with_data'), 10, 2);

        
        add_filter('manage_edit-authnet_account_sortable_columns', array($this, 'make_custom_column_sortable'));
        add_action('pre_get_posts', array($this, 'modify_query_to_sort_by_custom_column'));

        // For meta fields
        add_action('add_meta_boxes', array($this, 'add_authnet_metabox'));
        add_action('save_post', array($this, 'save_authnet_metabox'));

        //$this->service = new Dotfiler_authnet;

    }

    // Make custom column sortable
    public function make_custom_column_sortable($columns) {
        $columns['total_sum'] = 'total_sum';
        $columns['login_id'] = 'login_id';
        return $columns;
    }

    // Modify query to sort by custom column
    public function modify_query_to_sort_by_custom_column($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');
        if ($orderby === 'total_sum') {
            $query->set('meta_key', 'authnet_login_id');
            $query->set('orderby', 'meta_value_num');
        }
    }

    // Populate custom column with data
    public function populate_custom_column_with_data($column, $post_id) {
        if ($column === 'total_sum') {
            $authnet_login_id = get_post_meta($post_id, 'authnet_login_id', true);
            $total_sum = Dotfiler_authnet::get_account_total_sum($authnet_login_id);
            $monthly_limit = get_post_meta($post_id, 'monthly_limit', true);

            echo round($total_sum, 2)." of ".$monthly_limit;
        }
        if ($column === 'login_id') {
            echo get_post_meta($post_id, 'authnet_login_id', true);
        }
    }

    // Add custom column to post type list
    public function add_custom_column_to_post_type_list($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['total_sum'] = 'Accumulated sum $ (this month)';
                $new_columns['login_id'] = 'Login ID';
            }
        }
        return $new_columns;
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
        $authnet_login_id = get_post_meta($post->ID, 'authnet_login_id', true);
        $authnet_transaction_key = get_post_meta($post->ID, 'authnet_transaction_key', true);
        $authnet_signature_key = get_post_meta($post->ID, 'authnet_signature_key', true);
        $formidable_form_id = get_post_meta($post->ID, 'formidable_form_id', true);
        $monthly_limit = get_post_meta($post->ID, 'monthly_limit', true);
    
        $formidable_forms = Dotfiler_authnet::get_formidadable_forms();
        ?>
        <p>
            <label for="authnet_login_id">API Login ID:</label><br>
            <input type="text" id="authnet_login_id" name="authnet_login_id" value="<?php echo esc_attr($authnet_login_id); ?>">
        </p>
        <p>
            <label for="authnet_transaction_key">Transaction Key:</label><br>
            <input type="text" id="authnet_transaction_key" name="authnet_transaction_key" value="<?php echo esc_attr($authnet_transaction_key); ?>">
        </p>
        <p>
            <label for="authnet_signature_key">Signature Key:</label><br>
            <input type="text" id="authnet_signature_key" name="authnet_signature_key" value="<?php echo esc_attr($authnet_signature_key); ?>">
        </p>
        <?php  ?>
        <p>
            <label for="formidable_form_id">Formidable Form ID:</label><br>
            <select id="formidable_form_id" name="formidable_form_id">
                <option></option>
                <?php foreach ($formidable_forms as $form) : ?>
                    <option value="<?php echo esc_attr($form->id); ?>" <?php selected($form->id, $formidable_form_id); ?>><?php echo esc_html($form->name); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php  ?>
        <p>
            <label for="monthly_limit">Monthly Limit ($):</label><br>
            <input type="number" id="monthly_limit" name="monthly_limit" value="<?php echo esc_attr($monthly_limit); ?>">
        </p>
    
        <?php
    }
    
    public function save_authnet_metabox($post_id) {
    
        if (!isset($_POST['authnet_metabox_nonce']) || !wp_verify_nonce($_POST['authnet_metabox_nonce'], basename(__FILE__))) {
            return;
        }
    
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
    
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    
        $fields = array('authnet_login_id', 'authnet_transaction_key', 'authnet_signature_key', 'formidable_form_id', 'monthly_limit');
    
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    
    }

}

new Dotfiler_authnet_admin();

