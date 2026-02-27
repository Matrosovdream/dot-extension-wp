<?php
if ( ! defined('ABSPATH') ) { exit; }

final class DotFrmPhotosPageShortcode {

    public const SHORTCODE = 'frm-entries-ai-photos';
    public const FORM_ID   = 7;

    /** @var DotFrmPhotoEntryHelper */
    private $helper;

    /** AJAX */
    private const AJAX_ACTION_FIX     = 'dot_frm_ai_fix_photo';
    private const AJAX_ACTION_APPROVE = 'dot_frm_ai_approve_photo';
    private const NONCE_ACTION        = 'dot_frm_ai_fix_photo_nonce';
    private const AJAX_ACTION_DENY = 'dot_frm_ai_deny_photo';
    private const AJAX_ACTION_UPLOAD_FINAL = 'dot_frm_ai_upload_final_photo';
    private const AJAX_ACTION_EDIT_META    = 'dot_frm_ai_edit_status_notes';
    private const AJAX_ACTION_APPROVE_ROW = 'dot_frm_ai_approve_row';

    // Status → color class (keep keys exactly as your status values)
    private const STATUS_COLOR_MAP = [
        'Processing' => 'mrf-st--processing', // blue
        'Denied'      => 'mrf-st--issue',      // red
        'On Hold'    => 'mrf-st--hold',       // yellow
        'Approved'   => 'mrf-st--complete',   // green
    ];

    
    /** GET params */
    private const QP_PAGE   = 'faip_page';
    private const QP_STATUS = 'faip_status';
    private const QP_Q      = 'faip_q';
    private const QP_ORDER_ID      = 'faip_order_id';

    private $DENY_REASONS = [];
    private $AI_PROMPTS = [];    
    

    public function __construct() {
        $this->helper = new DotFrmPhotoEntryHelper();

        add_shortcode(self::SHORTCODE, [ $this, 'render_shortcode' ]);

        // AJAX handler (logged-in)
        add_action('wp_ajax_' . self::AJAX_ACTION_FIX, [ $this, 'ajax_ai_fix_photo' ]);
        add_action('wp_ajax_' . self::AJAX_ACTION_APPROVE, [ $this, 'ajax_ai_approve_photo' ]);
        add_action('wp_ajax_' . self::AJAX_ACTION_DENY, [ $this, 'ajax_ai_deny_photo' ]);
        add_action('wp_ajax_' . self::AJAX_ACTION_UPLOAD_FINAL, [ $this, 'ajax_ai_upload_final_photo' ]);
        add_action('wp_ajax_' . self::AJAX_ACTION_EDIT_META,    [ $this, 'ajax_ai_edit_status_notes' ]);
        add_action('wp_ajax_' . self::AJAX_ACTION_APPROVE_ROW, [ $this, 'ajax_ai_approve_row' ]);

        $this->AI_PROMPTS = $this->prepareDefaultPrompts();
        $this->DENY_REASONS = $this->prepareDenyReasons();
        
    }

    public function prepareDefaultPrompts(): array {
        
        $prompts = FrmAiSettingsHelper::getDefaultPrompts();

        $promptSet = [];
        foreach ($prompts as &$p) {
            $promptSet[] = [
                'label' => isset($p['title']) ? (string)$p['title'] : '',
                'value' => isset($p['text']) ? (string)$p['text'] : '',
                'checked' => !empty($p['selected']),
            ];
        }

        return $promptSet;

    }

    public function prepareDenyReasons(): array {
        
        $refs = $this->safe_get_select_refs( self::FORM_ID );
        $reasonsRaw = $refs['deny_reason']['values'] ?? [];
        
        $reasons = [];
        foreach ($reasonsRaw as $r) {

            if( $r['label'] == 'Other' ) continue;

            $reasons[] = [
                'label' => isset($r['label']) ? (string)$r['label'] : '',
                'message' => '',
            ];
        }
        return $reasons;

    }

    public function render_shortcode($atts = []): string {

        $form_id = self::FORM_ID;

        // Enqueue assets
        $this->enqueue_assets($form_id);

        // Select refs for Status
        $refs = $this->safe_get_select_refs($form_id);
        $status_ref = isset($refs['status']) && is_array($refs['status']) ? $refs['status'] : [];
        $status_field_id = isset($status_ref['field_id']) ? (int) $status_ref['field_id'] : 0;
        $status_values = isset($status_ref['values']) && is_array($status_ref['values']) ? $status_ref['values'] : [];

        // Read filters from GET
        $page = isset($_GET[self::QP_PAGE]) ? max(1, (int) $_GET[self::QP_PAGE]) : 1;

        $status_value = isset($_GET[self::QP_STATUS]) ? sanitize_text_field((string) $_GET[self::QP_STATUS]) : '';
        $q_raw = isset($_GET[self::QP_Q]) ? (string) $_GET[self::QP_Q] : '';
        $q_order_id = isset($_GET[self::QP_ORDER_ID]) ? (string) $_GET[self::QP_ORDER_ID] : '';
        $entry_id = preg_replace('/[^0-9]/', '', $q_raw);
        $entry_id = $entry_id !== '' ? $entry_id : '';

        $per_page = 20;

        // List from helper
        $list = $this->safe_get_list(
            $form_id,
            $status_field_id,
            $status_value,
            $entry_id,
            $q_order_id,
            $page,
            $per_page
        );

        if ( isset($_GET['log']) && $_GET['log'] ) {
            echo "<pre>";
            print_r($list);
            echo "</pre>";
        }

        // Pagination values
        $p = isset($list['pagination']) && is_array($list['pagination']) ? $list['pagination'] : [];
        $cur_page = isset($p['page']) ? max(1, (int)$p['page']) : $page;
        $total_pages = isset($p['total_pages']) ? max(1, (int)$p['total_pages']) : 1;

        // Build current URL without faip_page
        $base_url = $this->current_url_without([ self::QP_PAGE ]);

        // Current filters for rendering inputs
        $current_status = $status_value;
        $current_q = $entry_id;
        $current_order_id = $q_order_id;

        ob_start();
        ?>
        <div class="faip-wrap" id="faip"
             data-form-id="<?php echo esc_attr($form_id); ?>"
             data-status-field-id="<?php echo esc_attr($status_field_id); ?>"
        >
            <div class="faip-head">
                <div>
                    <div class="faip-title">AI Photos Review</div>
                </div>

                <div class="faip-actions">
                <select id="faipPromptSelect" class="faip-prompt-select" style="min-width:220px; height:39px; border:1px solid #d0d7de; border-radius:8px; padding:0 10px;">
                    <?php foreach ($this->AI_PROMPTS as $p): ?>
                        <?php
                        $label = (string)($p['label'] ?? '');
                        $value = (string)($p['value'] ?? '');
                        $checked = !empty($p['checked']);
                        ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php echo $checked ? 'selected' : ''; ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                    <button class="faip-btn faip-btn-primary" id="faipBulkFix" disabled>Edit (AI fix photos)</button>
                    <button class="faip-btn faip-btn-success" id="faipBulkApprove" disabled>Approve</button>
                </div>
            </div>

            <!-- Filters (GET form, auto submit on change) -->
            <form method="get" class="faip-filters" id="faipFiltersForm">
                <?php
                // Preserve unrelated query params
                foreach ($_GET as $k => $v) {
                    if (in_array($k, [self::QP_PAGE, self::QP_STATUS, self::QP_Q], true)) continue;
                    if (is_array($v)) continue;
                    echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string)$v) . '">';
                }
                ?>

                <div class="faip-filter">
                    <label>Status</label>
                    <select id="faipFilterStatus" name="<?php echo esc_attr(self::QP_STATUS); ?>" data-autosubmit="1">
                        <option value="">All</option>
                        <?php foreach ($status_values as $opt): ?>
                            <?php
                            $label = isset($opt['label']) ? (string)$opt['label'] : '';
                            $value = isset($opt['value']) ? (string)$opt['value'] : $label;
                            $selected = ($value !== '' && $value === $current_status) ? 'selected' : '';
                            ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php echo $selected; ?>>
                                <?php echo esc_html($label ?: $value); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="faip-filter">
                    <label>Entry ID</label>
                    <input
                        id="faipFilterQ"
                        name="<?php echo esc_attr(self::QP_Q); ?>"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        value="<?php echo esc_attr($current_q); ?>"
                        placeholder="e.g. 122751"
                        data-enter-submit="1"
                        style="width: 150px;"
                    />
                </div>

                <div class="faip-filter">
                    <label>Order ID</label>
                    <input
                        id="faipFilterQ"
                        name="<?php echo esc_attr(self::QP_ORDER_ID); ?>"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        value="<?php echo esc_attr($current_order_id); ?>"
                        placeholder="e.g. 122751"
                        data-enter-submit="1"
                        style="width: 150px;"
                    />
                </div>

                <!-- Reset -->
                <div class="faip-filter" style="grid-column: span 2;">
                    <label>&nbsp;</label>
                    <div class="faip-inline">
                        <button type="submit" class="faip-btn faip-btn-primary" id="mrfApplyFilters">Apply</button>
                        <a class="faip-btn" href="<?php echo esc_url($this->current_url_without([self::QP_PAGE, self::QP_STATUS, self::QP_Q])); ?>">Reset</a>
                    </div>
                </div>

                <!-- Always reset to page 1 when filtering -->
                <input type="hidden" name="<?php echo esc_attr(self::QP_PAGE); ?>" value="1">
            </form>

            <div class="faip-statusbar" id="faipStatusbar">
                <?php
                if (!empty($list['error'])) {
                    echo '<span class="ffda-inline-msg err">' . esc_html((string)$list['error']) . '</span>';
                }
                ?>
            </div>

            <table class="table table-striped table-listing table-ai-photos" style="width:100%;">
                <thead>
                <tr>
                    <th style="width:34px;"><input type="checkbox" id="faipCheckAll"></th>
                    <th style="width:120px;">Order #</th>
                    <th style="width:170px;">Date Created</th>
                    <th>Service</th>
                    <th style="width:200px;">Status</th>
                    <th>Original image</th>
                    <th>Final image</th>
                    <th style="width:280px;">Actions</th>
                </tr>
                </thead>
                <tbody id="faipTbody">
                <?php echo $this->render_rows_html($list, $status_field_id); ?>
                </tbody>
            </table>

            <div class="faip-footer">
                <div class="faip-muted">
                    <?php echo esc_html($this->footer_count_text($list)); ?>
                </div>

                <div class="faip-pager">
                    <?php
                    $prev_disabled = ($cur_page <= 1);
                    $next_disabled = ($cur_page >= $total_pages);

                    $prev_url = $this->add_query_arg_safe($base_url, self::QP_PAGE, (string) max(1, $cur_page - 1));
                    $next_url = $this->add_query_arg_safe($base_url, self::QP_PAGE, (string) min($total_pages, $cur_page + 1));
                    ?>
                    <a class="faip-btn <?php echo $prev_disabled ? 'is-disabled' : ''; ?>"
                       href="<?php echo $prev_disabled ? '#' : esc_url($prev_url); ?>"
                       <?php echo $prev_disabled ? 'aria-disabled="true"' : ''; ?>
                    >Prev</a>

                    <span class="faip-page"><?php echo esc_html("Page {$cur_page} / {$total_pages}"); ?></span>

                    <a class="faip-btn <?php echo $next_disabled ? 'is-disabled' : ''; ?>"
                       href="<?php echo $next_disabled ? '#' : esc_url($next_url); ?>"
                       <?php echo $next_disabled ? 'aria-disabled="true"' : ''; ?>
                    >Next</a>
                </div>
            </div>
        </div>

        <!-- Deny modal (kept for later wiring) -->
        <div class="ffda-confirm-backdrop" id="faipDenyModal">
            <div class="ffda-confirm-box">
                <div class="ffda-confirm-title">Deny photos</div>
                <div class="ffda-confirm-text">
                    Select reasons.
                    <div class="faip-muted" style="margin-top:6px; display: none;">
                        Field <code>662</code> = checked, Field <code>663</code> = reason, Field <code>224</code> = unchecked
                    </div>
                </div>

                <div class="ffda-rate-group">
                    <div class="ffda-rate-group-title">Reasons</div>

                    <select id="faipDenyReason" multiple="multiple" style="width:100%; height: 350px;">
                        <?php foreach ($this->DENY_REASONS as $r): ?>
                            <option value="<?php echo esc_attr($r['label']); ?>">
                                <?php echo esc_html($r['label']); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="__custom__">Custom…</option>
                    </select>
                </div>

                <div class="ffda-confirm-actions">
                    <button class="faip-btn" id="faipDenyCancel" type="button">Cancel</button>
                    <button class="faip-btn faip-btn-danger" id="faipDenyConfirm" type="button" disabled>Deny</button>
                </div>
            </div>
        </div>

        <!-- Compare modal -->
        <div class="ffda-confirm-backdrop" id="faipCompareModal">
            <div class="ffda-confirm-box faip-compare-box">
                <button type="button" class="faip-compare-close" id="faipCompareClose" aria-label="Close">×</button>

                <div class="ffda-confirm-title" style="margin-right:32px;">Compare images</div>

                <div class="faip-compare-grid">
                    <div class="faip-compare-col">
                        <div class="faip-muted" style="margin-bottom:6px;">Original</div>
                        <img id="faipCompareOriginal" src="" alt="Original" class="faip-compare-img">
                    </div>

                    <div class="faip-compare-col">
                        <div class="faip-muted" style="margin-bottom:6px;">Final</div>
                        <img id="faipCompareFinal" src="" alt="Final" class="faip-compare-img">
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Final modal -->
        <div class="ffda-confirm-backdrop" id="faipUploadModal">
            <div class="ffda-confirm-box" style="width:100%; max-width:520px; position:relative;">
                <button type="button" class="faip-compare-close" id="faipUploadClose" aria-label="Close">×</button>

                <div class="ffda-confirm-title" style="margin-right:32px;">Upload final photo</div>

                <div class="ffda-rate-group">
                    <div class="ffda-rate-group-title">File</div>
                    <input type="file" id="faipUploadFile" accept="image/*" style="width:100%; border:1px solid #d0d7de; border-radius:8px; padding:8px 10px; font-size:14px;">
                    <div class="faip-muted" style="margin-top:6px;">Uploads and sets as <b>final</b> image for this entry.</div>
                </div>

                <div class="faip-modal-statusbar" id="faipUploadStatusbar" style="margin:10px 0; min-height:20px;"></div>

                <div class="ffda-confirm-actions">
                    <button class="faip-btn" id="faipUploadCancel" type="button">Cancel</button>
                    <button class="faip-btn faip-btn-primary" id="faipUploadConfirm" type="button" disabled>Update</button>
                </div>

            </div>
        </div>

        <!-- Edit Status/Notes modal -->
        <div class="ffda-confirm-backdrop" id="faipEditMetaModal">
            <div class="ffda-confirm-box" style="width:100%; max-width:560px; position:relative;">
                <button type="button" class="faip-compare-close" id="faipEditMetaClose" aria-label="Close">×</button>

                <div class="ffda-confirm-title" style="margin-right:32px;">Edit status & notes</div>

                <div class="ffda-rate-group">
                    <div class="ffda-rate-group-title">Status</div>
                    <select id="faipEditMetaStatus" style="width:100%; border:1px solid #d0d7de; border-radius:8px; padding:8px 10px; font-size:14px;">
                        <option value="">— Select —</option>
                        <?php foreach ($status_values as $opt): ?>
                            <?php
                            $lbl = isset($opt['label']) ? (string)$opt['label'] : '';
                            $val = isset($opt['value']) ? (string)$opt['value'] : $lbl;
                            ?>
                            <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($lbl ?: $val); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ffda-rate-group">
                    <div class="ffda-rate-group-title">Notes</div>
                    <textarea id="faipEditMetaNotes" rows="4" style="width:100%; border:1px solid #d0d7de; border-radius:8px; padding:8px 10px; font-size:14px;" placeholder="Type notes…"></textarea>
                </div>

                <div class="ffda-confirm-actions">
                    <button class="faip-btn" id="faipEditMetaCancel" type="button">Cancel</button>
                    <button class="faip-btn faip-btn-primary" id="faipEditMetaConfirm" type="button">Update</button>
                </div>
            </div>
        </div>

        <?php
        return (string) ob_get_clean();
    }

    private function enqueue_assets(int $form_id): void {

        wp_enqueue_style(
            'dotfiler-ai-photos-page-css',
            esc_url(DOTFILER_BASE_PATH . 'assets/ai-photos-page.css?time=' . time()),
            [],
            '1.0.0'
        );

        // Minimal spinner + label styling (safe even if you already have css)
        wp_add_inline_style('dotfiler-ai-photos-page-css', '
            .faip-ai-label{display:inline-block;margin-top:6px;font-size:12px;color:#57606a}
            .faip-processing{position:relative;min-height:44px}
            .faip-spinner{display:inline-block;width:18px;height:18px;border:2px solid rgba(0,0,0,.15);border-top-color:rgba(0,0,0,.55);border-radius:50%;animation:faipspin .7s linear infinite}
            @keyframes faipspin{to{transform:rotate(360deg)}}
        ');

        wp_enqueue_script(
            'dotfiler-ai-photos-page-js',
            esc_url(DOTFILER_BASE_PATH . 'assets/ai-photos-page.js?time=' . time()),
            [],
            '1.0.0',
            true
        );

        wp_localize_script('dotfiler-ai-photos-page-js', 'FAIP_AJAX', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE_ACTION),
            'action'   => self::AJAX_ACTION_FIX,
        ]);

        wp_localize_script('dotfiler-ai-photos-page-js', 'FAIP_AJAX', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE_ACTION),
            'action'   => self::AJAX_ACTION_FIX,
            'action_approve' => self::AJAX_ACTION_APPROVE,
            'action_deny' => self::AJAX_ACTION_DENY,
            'action_upload_final' => self::AJAX_ACTION_UPLOAD_FINAL,
            'action_edit_meta'    => self::AJAX_ACTION_EDIT_META,
            'action_approve_row' => self::AJAX_ACTION_APPROVE_ROW,
        ]);

        // Select2 (jQuery multi-select)
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0'
        );

        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            ['jquery'],
            '4.1.0',
            true
        );

        // Small tweaks so Select2 matches your UI a bit
        wp_add_inline_style('select2', '
        .select2-container .select2-selection--multiple{
            min-height: 38px; border:1px solid #d0d7de; border-radius:8px;
            padding:4px 6px; font-size:14px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice{
            border-radius:999px;
        }
        ');
    
    }

    /**
     * AJAX: AI fix a single entry (sequential from JS)
     */
    public function ajax_ai_fix_photo(): void {

        if ( ! is_user_logged_in() ) {
            wp_send_json([ 'ok' => false, 'error' => 'Not logged in' ], 401);
        }

        // Adjust capability if needed
        if ( ! current_user_can('manage_options') ) {
            //wp_send_json([ 'ok' => false, 'error' => 'Forbidden' ], 403);
        }

        $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_ACTION) ) {
            wp_send_json([ 'ok' => false, 'error' => 'Bad nonce' ], 403);
        }

        $entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        if ( $entry_id <= 0 ) {
            wp_send_json([ 'ok' => false, 'error' => 'Missing entry_id' ], 400);
        }

        $prompts = [];
        if (isset($_POST['prompts'])) {
            // supports prompts[] or prompts (array)
            $raw = $_POST['prompts'];
            if (is_array($raw)) {
                $prompts = array_values(array_filter(array_map('sanitize_text_field', $raw)));
            } else {
                // fallback: comma separated
                $prompts = array_values(array_filter(array_map('sanitize_text_field', explode(',', (string)$raw))));
            }
        }

        try {
            $entry = $this->helper->getEntryById($entry_id);
            if ( ! is_array($entry) ) {
                wp_send_json([ 'ok' => false, 'error' => 'Entry not found' ], 404);
            }

            $fv = isset($entry['field_values']) && is_array($entry['field_values']) ? $entry['field_values'] : [];
            $uploaded_url = isset($fv['uploaded_photo_url']) ? (string) $fv['uploaded_photo_url'] : '';

            if ( $uploaded_url === '' ) {
                wp_send_json([ 'ok' => false, 'error' => 'uploaded_photo_url is empty' ], 400);
            }

            $imagePath = $this->url_to_local_path($uploaded_url);
            if ( ! $imagePath || ! file_exists($imagePath) ) {
                wp_send_json([
                    'ok' => false,
                    'error' => 'Cannot resolve image path from URL',
                    'uploaded_photo_url' => $uploaded_url,
                    'resolved_path' => (string) $imagePath,
                ], 400);
            }

            $aiHelper = new FrmAiImageHelper();

            $res = $aiHelper->processImage($imagePath, $prompts);
            if ( ! is_array($res) ) {
                wp_send_json([ 'ok' => false, 'error' => 'geminiTest() returned invalid response' ], 500);
            }

            $data = isset($res['data']) && is_array($res['data']) ? $res['data'] : [];
            $final_url = isset($data['final_url']) ? (string) $data['final_url'] : '';

            if ( $final_url === '' ) {
                wp_send_json([ 'ok' => false, 'error' => 'final_url is empty', 'data' => $data, 'api_res' => $res ], 500);
            }

            wp_send_json([
                'ok' => true,
                'entry_id' => $entry_id,
                'data' => $data,
                'final_url' => $final_url,
                'label' => 'Edited by AI',
            ]);

        } catch (\Throwable $e) {
            wp_send_json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function ajax_ai_approve_photo(): void {

        if ( ! is_user_logged_in() ) {
            wp_send_json([ 'ok' => false, 'error' => 'Not logged in' ], 401);
        }
        if ( ! current_user_can('manage_options') ) {
            //wp_send_json([ 'ok' => false, 'error' => 'Forbidden' ], 403);
        }
    
        $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_ACTION) ) {
            wp_send_json([ 'ok' => false, 'error' => 'Bad nonce' ], 403);
        }
    
        $entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        $tmp_url  = isset($_POST['tmp_url']) ? esc_url_raw((string) $_POST['tmp_url']) : '';
    
        if ( $entry_id <= 0 ) {
            wp_send_json([ 'ok' => false, 'error' => 'Missing entry_id' ], 400);
        }
        if ( $tmp_url === '' ) {
            wp_send_json([ 'ok' => false, 'error' => 'Missing tmp_url' ], 400);
        }
    
        try {
            
            $tmp_path = $this->url_to_local_path($tmp_url);
            if ( ! $tmp_path || ! file_exists($tmp_path) ) {
                wp_send_json([
                    'ok' => false,
                    'error' => 'tmp_url file not found',
                    'tmp_url' => $tmp_url,
                    'tmp_path' => (string) $tmp_path,
                ], 400);
            }
    
            // Update entry's image URL to the new AI-generated one
            $res = $this->helper->updateEntryImage($entry_id, [
                'new_image_url' => $tmp_url,
            ]);

            // Approve entry
            $this->helper->approveEntry($entry_id);
    
            if (!is_array($res) || empty($res['ok'])) {
                wp_send_json([
                    'ok' => false,
                    'error' => $res['error'] ?? 'updateEntryImage failed',
                    'data' => $res,
                ], 500);
            }
    
            wp_send_json([
                'ok' => true,
                'entry_id' => $entry_id,
                'data' => $res['data'] ?? [],
            ]);
    
        } catch (\Throwable $e) {
            wp_send_json([ 'ok' => false, 'error' => $e->getMessage() ], 500);
        }
    }

    public function ajax_ai_deny_photo(): void {

        // Auth
        if ( ! is_user_logged_in() ) {
            wp_send_json([ 'ok' => false, 'error' => 'Not logged in' ], 401);
        }
        if ( ! current_user_can('manage_options') ) {
            //wp_send_json([ 'ok' => false, 'error' => 'Forbidden' ], 403);
        }
    
        // Nonce
        $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_ACTION) ) {
            wp_send_json([ 'ok' => false, 'error' => 'Bad nonce' ], 403);
        }
    
        // Params
        $entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        $order_id = isset($_POST['order_id']) ? sanitize_text_field((string) $_POST['order_id']) : '';
        $custom_message = isset($_POST['custom_message']) ? sanitize_textarea_field((string) $_POST['custom_message']) : '';
    
        // Multi reasons: reasons[] or reasons (csv)
        $reasons = isset($_POST['reasons']) ? $_POST['reasons'] : [];
    
        if ( $entry_id <= 0 ) {
            wp_send_json([ 'ok' => false, 'error' => 'Missing entry_id' ], 400);
        }
        if ( $order_id === '' ) {
            wp_send_json([ 'ok' => false, 'error' => 'Missing order_id' ], 400);
        }
        if ( empty($reasons) ) {
            wp_send_json([ 'ok' => false, 'error' => 'Missing reasons' ], 400);
        }
    
        // Resolve message(s)
        $messages = [];
    
        foreach ($reasons as $reason) {
    
            // Custom
            if ($reason === '__custom__') {
                if ($custom_message !== '') {
                    $messages[] = $custom_message;
                }
                continue;
            }
    
            $messages[] = $reason;
        }
    
        // Normalize messages
        $messages = array_values(array_filter(array_unique(array_map('trim', $messages))));
    
        if (empty($messages)) {
            wp_send_json([
                'ok' => false,
                'error' => 'Invalid reasons or empty messages',
                'reasons' => $reasons,
            ], 400);
        }
    
        // Optional: If custom was selected but empty => error
        if (in_array('__custom__', $reasons, true) && $custom_message === '') {
            wp_send_json([
                'ok' => false,
                'error' => 'Custom message is required',
            ], 400);
        }
    
        try {
            // Deny entry (your helper should do the status update + store message + email etc.)
            $res = $this->helper->denyEntry($entry_id, $order_id, $messages);

            // Set status - Denied
            $this->helper->updateEntryStatus($entry_id, 'Denied');
    
            if ( ! is_array($res) || empty($res['ok']) ) {
                wp_send_json([
                    'ok' => false,
                    'error' => (is_array($res) && !empty($res['error'])) ? (string)$res['error'] : 'denyEntry failed',
                    'data' => $res,
                ], 500);
            }
    
            wp_send_json([
                'ok' => true,
                'entry_id' => $entry_id,
                'order_id' => $order_id,
                'reasons' => $reasons,
                'message' => $message,
            ]);
    
        } catch (\Throwable $e) {
            wp_send_json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function ajax_ai_upload_final_photo(): void {

        if ( ! is_user_logged_in() ) {
            wp_send_json([ 'ok' => false, 'error' => 'Not logged in' ], 401);
        }
        if ( ! current_user_can('manage_options') ) {
            //wp_send_json([ 'ok' => false, 'error' => 'Forbidden' ], 403);
        }
    
        $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_ACTION) ) {
            wp_send_json([ 'ok' => false, 'error' => 'Bad nonce' ], 403);
        }
    
        $entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        if ($entry_id <= 0) {
            wp_send_json([ 'ok' => false, 'error' => 'Missing entry_id' ], 400);
        }
    
        if (empty($_FILES['photo']) || !is_array($_FILES['photo'])) {
            wp_send_json([ 'ok' => false, 'error' => 'Missing file: photo' ], 400);
        }
    
        try {
            if (!function_exists('wp_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
    
            $file = $_FILES['photo'];
    
            $overrides = [
                'test_form' => false,
                'mimes' => [
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                    'webp'         => 'image/webp',
                ],
            ];
    
            $uploaded = wp_handle_upload($file, $overrides);
    
            if (!is_array($uploaded) || !empty($uploaded['error'])) {
                wp_send_json([
                    'ok' => false,
                    'error' => !empty($uploaded['error']) ? (string)$uploaded['error'] : 'Upload failed',
                    'data' => $uploaded,
                ], 500);
            }
    
            $url = isset($uploaded['url']) ? (string)$uploaded['url'] : '';
            if ($url === '') {
                wp_send_json([ 'ok' => false, 'error' => 'Uploaded url missing' ], 500);
            }
    
            // REQUIRED: call updateEntryImage with imageType='final'
            // Adjust signature if your helper differs.
            $res = $this->helper->updateEntryImage($entry_id, [
                'new_image_url' => $url,
            ], 'final');
    
            if (!is_array($res) || empty($res['ok'])) {
                wp_send_json([
                    'ok' => false,
                    'error' => $res['error'] ?? 'updateEntryImage failed',
                    'data' => $res,
                ], 500);
            }
    
            wp_send_json([
                'ok' => true,
                'entry_id' => $entry_id,
                'final_url' => $url,
                'data' => $res['data'] ?? [],
            ]);
    
        } catch (\Throwable $e) {
            wp_send_json([ 'ok' => false, 'error' => $e->getMessage() ], 500);
        }
    }

    public function ajax_ai_edit_status_notes(): void {

        $entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        $status  = isset($_POST['status']) ? sanitize_text_field((string) $_POST['status']) : '';
        $notes   = isset($_POST['notes']) ? sanitize_textarea_field((string) $_POST['notes']) : '';

        // Update Status
        $this->helper->updateEntryStatus($entry_id, $status);

        // Update Notes
        $this->helper->updateEntryNotes($entry_id, $notes);

        // TODO: implement later
        wp_send_json([
            'ok' => true,
            'error' => 'Status/Notes updated.',
        ]);
    }

    public function ajax_ai_approve_row(): void {

        if ( ! is_user_logged_in() ) {
            wp_send_json([ 'ok' => false, 'error' => 'Not logged in' ], 401);
        }
    
        $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_ACTION) ) {
            wp_send_json([ 'ok' => false, 'error' => 'Bad nonce' ], 403);
        }
    
        $entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        $order_id = isset($_POST['order_id']) ? sanitize_text_field((string) $_POST['order_id']) : '';
    
        // Approve entry
        $this->helper->approveEntry($entry_id, $order_id);
    
        wp_send_json([
            'ok' => true,
            'entry_id' => $entry_id,
            'order_id' => $order_id,
        ]);
    }
    

    /**
     * Convert uploads URL -> local path (best effort)
     */
    private function url_to_local_path(string $url): string {

        $url = trim($url);
        if ($url === '') return '';

        // Common WP uploads mapping
        $uploads = wp_upload_dir();
        $baseurl = isset($uploads['baseurl']) ? (string) $uploads['baseurl'] : '';
        $basedir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';

        if ($baseurl && $basedir && strpos($url, $baseurl) === 0) {
            $rel = ltrim(substr($url, strlen($baseurl)), '/');
            return rtrim($basedir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        }

        // If url is on same host and contains /wp-content/
        $parts = wp_parse_url($url);
        $path  = isset($parts['path']) ? (string) $parts['path'] : '';

        if ($path && strpos($path, '/wp-content/') !== false) {
            // ABSPATH points to WP root
            $abs = rtrim(ABSPATH, '/\\');
            return $abs . str_replace('/', DIRECTORY_SEPARATOR, $path);
        }

        return '';
    }

    /**
     * Server-side list fetch (no AJAX)
     */
    private function safe_get_list(
        int $form_id,
        int $status_field_id,
        string $status_value,
        string $entry_id,
        string $order_id,
        int $page,
        int $per_page
    ): array {

        $conditions = [];

        if ($status_field_id > 0 && $status_value !== '') {
            $conditions[] = [
                'field_id' => $status_field_id,
                'value'    => $status_value,
                'compare'  => '=',
            ];
        }

        // Entry ID filter
        if ($entry_id !== '') {
            $conditions[] = [
                'item_id'  => (int) $entry_id,
                'compare'  => '=',
            ];
        }

        // Order ID filter
        if ($order_id > 0) {
            $conditions[] = [
                'field_id' => FRM_FORM_7_FIELDS_MAP['order_id'],
                'value'    => $order_id,
                'compare'  => '=',
            ];
        }

        try {
            $list = $this->helper->getList(
                $conditions,
                page: $page,
                paginate: $per_page,
                form_id: $form_id
            );

            if( isset( $_GET['lg'] ) ) {
                echo "<pre>";
                print_r($list['data']);
                echo "</pre>";
            }

            return is_array($list) ? $list : [];
        } catch (\Throwable $e) {
            return [
                'data' => [],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => 0,
                    'total_pages' => 1,
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function safe_get_select_refs(int $form_id): array {
        try {
            $refs = $this->helper->getSelectRefs($form_id);
            return is_array($refs) ? $refs : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function render_rows_html(array $list, int $status_field_id): string {

        $items = isset($list['data']) && is_array($list['data']) ? $list['data'] : [];
    
        if (empty($items)) {
            return '<tr><td colspan="8" class="faip-muted">No results.</td></tr>';
        }
    
        ob_start();
    
        foreach ($items as $item) {
    
            $id = 0;
            if (is_array($item) && isset($item['id'])) {
                $id = (int) $item['id'];
            } elseif (is_object($item) && isset($item->id)) {
                $id = (int) $item->id;
            }
    
            $fieldValues = $item['field_values'] ?? [];

            $entryId = $id;
    
            $status           = $fieldValues['status'] ?? '';
            $createdAtRaw     = $item['created_at'] ?? 'now';
            $createdAt        = date('Y-m-d H:i', strtotime($createdAtRaw));
            $orderId          = $fieldValues['order_id'] ?? '';
            $service          = $fieldValues['service'] ?? '';
            $email            = $fieldValues['email'] ?? '';
            $uploadedPhotoUrl = $fieldValues['uploaded_photo_url'] ?? '';
            $finalPhotoUrl    = $fieldValues['final_photo_url'] ?? '';
            $notes            = $fieldValues['notes'] ?? '';
            $ai_image_proc   = $fieldValues['ai_image_processing'] ?? '';
    
            $denyBtn = '<button class="faip-btn faip-btn-danger" data-action="deny" data-id="' . esc_attr($id) . '" type="button">Deny</button>';
            $approveBtn = '<button class="faip-btn faip-btn-success" data-action="approve_row" data-id="' . esc_attr($id) . '" type="button">Approve</button>';
            ?>
    
            <tr data-row="<?php echo esc_attr($id); ?>"  data-order-id="<?php echo esc_attr((string)$orderId); ?>" data-ai-tmp-url="">
                <td>
                    <input type="checkbox" data-id="<?php echo esc_attr($id); ?>">
                </td>
    
                <td>
                    <b><?php echo esc_html($orderId); ?></b>

                    <div class="faip-muted">
                        Entry #<?php echo esc_html($id); ?>
                    </div>

                </td>
    
                <td>
                    <?php echo esc_html((string) $createdAt); ?>
                </td>
    
                <td>
                    <b><?php echo esc_html((string) $service); ?></b><br>
                    <?php echo esc_html((string) $email); ?>

                    <?php if ($notes !== ''): ?>
                        <div class="ffda-note-text"><?php echo $notes; ?></div>
                    <?php endif; ?>
                </td>
    
                <td>

                    <?php
                    $st = (string)($status ?? '');
                    $st_key = trim($st);
                    $st_class = self::STATUS_COLOR_MAP[$st_key] ?? 'mrf-st--default';
                    ?>
                    <div class="mrf-status-text <?php echo esc_attr($st_class); ?>">
                        <?php echo esc_html($st); ?>
                    </div>

                    <div style="margin-top:6px;">
                        <button
                            type="button"
                            class="faip-btn faip-btn-compare"
                            data-action="edit_meta"
                            data-id="<?php echo esc_attr($id); ?>"
                        >Edit</button>
                    </div>
                </td>

                <td>
                    <?php if (!empty($uploadedPhotoUrl)) : ?>
                        <img 
                            src="<?php echo esc_url((string) $uploadedPhotoUrl); ?>" 
                            alt="Original Image" 
                            class="original-image"
                        >
                    <?php endif; ?>
                </td>
    
                <td>
                    <div class="fixed-image-block">
                        <img 
                            src="<?php echo esc_url((string) $finalPhotoUrl); ?>" 
                            alt="Final Image" 
                            class="final-image"
                        >
                    </div>
                    <div class="faip-ai-label-wrap"></div>

                    <?php if (!empty($uploadedPhotoUrl) && !empty($finalPhotoUrl)) : ?>
                        <div style="margin-top:6px; display:flex; gap:8px; flex-wrap:wrap;">
                            <button
                                type="button"
                                class="faip-btn faip-btn-compare"
                                data-action="compare"
                                data-id="<?php echo esc_attr($id); ?>"
                                data-original="<?php echo esc_url((string)$uploadedPhotoUrl); ?>"
                                data-final="<?php echo esc_url((string)$finalPhotoUrl); ?>"
                            >Compare</button>

                            <button
                                type="button"
                                class="faip-btn faip-btn-compare"
                                data-action="upload_final"
                                data-id="<?php echo esc_attr($id); ?>"
                            >Upload</button>
                        </div>
                    <?php elseif (!empty($uploadedPhotoUrl)) : ?>
                        <div style="margin-top:6px;">
                            <button
                                type="button"
                                class="faip-btn faip-btn-compare"
                                data-action="upload_final"
                                data-id="<?php echo esc_attr($id); ?>"
                            >Upload</button>
                        </div>
                    <?php endif; ?>

                    <?php if ($ai_image_proc !== ''): ?>
                        <div class="faip-ai-label">
                            Processing image on background..
                        </div>
                    <?php endif; ?>

                </td>
    
                <td>
                    <div class="faip-row-actions">
                        
                        <a href="/orders/entry/<?php echo esc_attr($orderId); ?>" target="_blank">
                            <button class="faip-btn" type="button">
                                Open order
                            </button>
                        </a>

                        <?php echo $denyBtn; ?>
                        <?php echo $approveBtn; ?>
                    </div>
                </td>
            </tr>
    
            <?php
        }
    
        return ob_get_clean();
    }
    

    private function footer_count_text(array $list): string {
        $p = isset($list['pagination']) && is_array($list['pagination']) ? $list['pagination'] : [];
        $page = isset($p['page']) ? (int)$p['page'] : 1;
        $per  = isset($p['per_page']) ? (int)$p['per_page'] : 20;
        $total= isset($p['total']) ? (int)$p['total'] : 0;

        $start = $total > 0 ? (($page - 1) * $per + 1) : 0;
        $end   = $total > 0 ? min($total, $page * $per) : 0;

        return $total > 0
            ? "Showing {$start}-{$end} of {$total}"
            : "Showing 0 of 0";
    }

    private function current_url_without(array $remove_keys): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $url = $scheme . '://' . $host . $uri;

        $parts = wp_parse_url($url);
        $path = $parts['path'] ?? '';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($remove_keys as $k) {
            unset($query[$k]);
        }

        $base = $scheme . '://' . $host . $path;
        if (!empty($query)) {
            $base .= '?' . http_build_query($query);
        }
        return $base;
    }

    private function add_query_arg_safe(string $url, string $key, string $value): string {
        return add_query_arg([ $key => $value ], $url);
    }
}

add_action('init', function(){
    if (class_exists('DotFrmPhotosPageShortcode')) {
        new DotFrmPhotosPageShortcode();
    }
});
