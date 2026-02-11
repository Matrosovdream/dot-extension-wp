<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Shortcode: [frm-entries-mass-refund]
 *
 * Adds:
 * - "Edit refund" button per row (Actions cell)
 * - Popup editor (reason/status/amount)
 * - AJAX: load refund fields + save refund fields
 * - Updates table cells after save
 */
final class DotFrmMassRefundShortcode_Simple {

    public const SHORTCODE = 'frm-entries-mass-refund';
    public const FORM_ID   = 4;

    /** GET params */
    private const QP_PAGE        = 'mrf_page';
    private const QP_STATUS      = 'mrf_status';
    private const QP_TIME        = 'mrf_time';
    private const QP_AMOUNT_TYPE = 'mrf_amount_type';

    /** AJAX */
    private const NONCE_ACTION    = 'dot_mrf_nonce';
    private const AJAX_REFUND     = 'dot_mrf_bulk_refund';
    private const AJAX_COMPLETE   = 'dot_mrf_bulk_complete';
    private const AJAX_EMAIL      = 'dot_mrf_bulk_email';
    private const AJAX_F1STAT     = 'dot_mrf_bulk_f1status';

    // NEW
    private const AJAX_EDIT_LOAD  = 'dot_mrf_edit_load';
    private const AJAX_EDIT_SAVE  = 'dot_mrf_edit_save';

    private $helper;

    public function __construct() {

        $this->helper = new DotFrmMassRefundHelper();

        add_shortcode(self::SHORTCODE, [ $this, 'render_shortcode' ]);

        add_action('wp_ajax_' . self::AJAX_REFUND,   [ $this, 'ajax_bulk_refund' ]);
        add_action('wp_ajax_' . self::AJAX_COMPLETE, [ $this, 'ajax_bulk_complete' ]);
        add_action('wp_ajax_' . self::AJAX_EMAIL,    [ $this, 'ajax_bulk_email' ]);
        add_action('wp_ajax_' . self::AJAX_F1STAT,   [ $this, 'ajax_bulk_f1status' ]);

        // NEW
        add_action('wp_ajax_' . self::AJAX_EDIT_LOAD,[ $this, 'ajax_edit_load' ]);
        add_action('wp_ajax_' . self::AJAX_EDIT_SAVE,[ $this, 'ajax_edit_save' ]);
    }

    public function render_shortcode($atts = []): string {

        $form_id = self::FORM_ID;

        $this->enqueue_assets();

        $page = isset($_GET[self::QP_PAGE]) ? max(1, (int)$_GET[self::QP_PAGE]) : 1;
        $per_page = 20;

        $status = isset($_GET[self::QP_STATUS]) ? sanitize_text_field((string)$_GET[self::QP_STATUS]) : '';
        $time   = isset($_GET[self::QP_TIME]) ? sanitize_text_field((string)$_GET[self::QP_TIME]) : '';
        $atype  = isset($_GET[self::QP_AMOUNT_TYPE]) ? sanitize_text_field((string)$_GET[self::QP_AMOUNT_TYPE]) : '';

        // Select refs for Status / Amount Type
        $refs = $this->helper->getSelectRefs($form_id);
        $status_values = $refs['status'] ?? [];
        $amount_type   = $refs['amount_type'] ?? [];

        $filters = [];

        if( !empty($status) ) {
            $filters[] = [
                'field_id' => 150,
                'value'    => $status,
                'compare'  => '=',
            ];
        }
        if( !empty($atype) ) {
            $filters[] = [
                'field_id' => 156,
                'value'    => $atype,
                'compare'  => '=',
            ];
        }

        $list = $this->helper->getList($filters, $page, $paginate = $per_page, $form_id);

        $p = (array)($list['pagination'] ?? []);
        $cur_page = max(1, (int)($p['page'] ?? $page));
        $total_pages = max(1, (int)($p['total_pages'] ?? 1));

        $base_url = $this->current_url_without([ self::QP_PAGE ]);

        ob_start();
        ?>
        <div class="faip-wrap" id="mrf"
             data-nonce="<?php echo esc_attr(wp_create_nonce(self::NONCE_ACTION)); ?>"
        >
            <div class="faip-head">
                <div>
                    <div class="faip-title">Mass Refunds</div>
                    <div class="faip-muted" style="margin-top:4px;">
                        Form4 refunds + Form1 transactions
                    </div>
                </div>

                <div class="faip-actions">
                    <button class="faip-btn faip-btn-primary" id="mrfBulkRefund" disabled>Issue refund</button>
                    <button class="faip-btn faip-btn-success" id="mrfBulkComplete" disabled>Mark Refund Complete</button>
                    <button class="faip-btn" id="mrfBulkEmail" disabled>Send Email</button>

                    <!-- your updated block -->
                    <div style="display:flex; gap:8px; align-items:center;">
                        <select id="mrfF1StatusVal" style="height:34px; border:1px solid #d0d7de; border-radius:8px; padding:0 10px;">
                            <option></option>
                            <option value="Refunded">Refunded</option>
                            <option value="Refund Requested">Refund Requested</option>
                            <option value="Refund Complete">Refund Complete</option>
                        </select>
                        <button class="faip-btn" id="mrfBulkF1Status" disabled>Update Order Status</button>
                    </div>
                </div>
            </div>

            <!-- FILTERS -->
            <form method="get" class="faip-filters" id="mrfFiltersForm">
                <?php
                foreach ($_GET as $k => $v) {
                    if (in_array($k, [self::QP_PAGE, self::QP_STATUS, self::QP_TIME, self::QP_AMOUNT_TYPE], true)) continue;
                    if (is_array($v)) continue;
                    echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string)$v) . '">';
                }
                ?>

                <div class="faip-filter">
                    <label>Status</label>
                    <select name="<?php echo esc_attr(self::QP_STATUS); ?>">
                        <option value="">All</option>
                        <?php foreach (($status_values['values'] ?? []) as $opt): ?>
                            <?php
                            $label = isset($opt['label']) ? (string)$opt['label'] : '';
                            $value = isset($opt['value']) ? (string)$opt['value'] : $label;
                            $selected = ($value !== '' && $value === $status) ? 'selected' : '';
                            ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php echo $selected; ?>>
                                <?php echo esc_html($label ?: $value); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="faip-filter">
                    <label>Time window (2:30PM PST)</label>
                    <select name="<?php echo esc_attr(self::QP_TIME); ?>">
                        <option value="" <?php selected($time, ''); ?>>All</option>
                        <option value="between_230" <?php selected($time, 'between_230'); ?>>Between 2:30PM–2:30PM</option>
                        <option value="before_yesterday_230" <?php selected($time, 'before_yesterday_230'); ?>>Before 2:30PM Yesterday</option>
                        <option value="after_today_230" <?php selected($time, 'after_today_230'); ?>>After 2:30PM Today</option>
                    </select>
                </div>

                <div class="faip-filter">
                    <label>Amount Type</label>
                    <select name="<?php echo esc_attr(self::QP_AMOUNT_TYPE); ?>">
                        <option value="">All</option>
                        <?php foreach (($amount_type['values'] ?? []) as $opt): ?>
                            <?php
                            $label = isset($opt['label']) ? (string)$opt['label'] : '';
                            $value = isset($opt['value']) ? (string)$opt['value'] : $label;
                            $selected = ($value !== '' && $value === $atype) ? 'selected' : '';
                            ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php echo $selected; ?>>
                                <?php echo esc_html($label ?: $value); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="faip-filter">
                    <label>&nbsp;</label>
                    <div class="faip-inline" style="gap:10px;">
                        <button type="submit" class="faip-btn faip-btn-primary" id="mrfApplyFilters">Apply</button>
                        <a class="faip-btn" href="<?php echo esc_url($this->current_url_without([self::QP_PAGE, self::QP_STATUS, self::QP_TIME, self::QP_AMOUNT_TYPE])); ?>">Reset</a>
                    </div>
                </div>

                <input type="hidden" name="<?php echo esc_attr(self::QP_PAGE); ?>" value="1">
            </form>

            <div class="faip-statusbar" id="mrfStatusbar">
                <?php if (!empty($list['error'])): ?>
                    <span class="ffda-inline-msg err"><?php echo esc_html((string)$list['error']); ?></span>
                <?php endif; ?>
            </div>

            <table class="table table-striped table-listing table-ai-photos" style="width:100%;">
                <thead>
                <tr>
                    <th style="width:34px;"><input type="checkbox" id="mrfCheckAll"></th>
                    <th style="width:120px;">Order #</th>
                    <th style="width:170px;">Date Created</th>
                    <th style="width:260px;">Reason</th>
                    <th style="width:160px;">Status</th>
                    <th style="width:160px;">Amount</th>
                    <th style="width:280px;">Actions</th>
                </tr>
                </thead>
                <tbody id="mrfTbody">
                <?php echo $this->render_rows_html($list); ?>
                </tbody>
            </table>

            <div class="faip-footer">
                <div class="faip-muted"><?php echo esc_html($this->footer_count_text($list)); ?></div>

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

            <!-- EDIT POPUP -->
            <div class="mrf-modal" id="mrfEditModal" aria-hidden="true">
                <div class="mrf-modal__backdrop" data-mrf-close="1"></div>
                <div class="mrf-modal__panel" role="dialog" aria-modal="true" aria-label="Edit refund">
                    <div class="mrf-modal__head">
                        <div>
                            <div class="mrf-modal__title">Edit refund</div>
                            <div class="mrf-modal__sub mrf-muted" id="mrfEditSub">Refund #</div>
                        </div>
                        <button type="button" class="mrf-x" data-mrf-close="1">×</button>
                    </div>

                    <div class="mrf-modal__body">
                        <div class="mrf-form-row">
                            <label>Reason</label>
                            <textarea id="mrfEditReason" rows="4" style="width:100%; border:1px solid #d0d7de; border-radius:10px; padding:10px;"></textarea>
                        </div>

                        <div class="mrf-form-row" style="margin-top:10px;">
                            <label>Status</label>
                            <select id="mrfEditStatus" style="width:100%; height:38px; border:1px solid #d0d7de; border-radius:10px; padding:0 10px;"></select>
                        </div>

                        <div class="mrf-form-row" style="margin-top:10px;">
                            <label>Amount</label>
                            <input id="mrfEditAmount" type="text" style="width:100%; height:38px; border:1px solid #d0d7de; border-radius:10px; padding:0 10px;">
                        </div>

                        <div id="mrfEditMsg" style="margin-top:12px;"></div>
                    </div>

                    <div class="mrf-modal__foot">
                        <button type="button" class="faip-btn" data-mrf-close="1">Cancel</button>
                        <button type="button" class="faip-btn faip-btn-primary" id="mrfEditSave">Save</button>
                    </div>
                </div>
            </div>

        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** ---------------- HTML ---------------- */
    private function render_rows_html(array $list): string {
        $items = isset($list['data']) && is_array($list['data']) ? $list['data'] : [];
        if (!$items) {
            return '<tr><td colspan="9" class="faip-muted">No results.</td></tr>';
        }

        $html = '';

        foreach ($items as $row) {
            $refund_id = (int)($row['id'] ?? 0);
            $values    = $row['field_values'] ?? [];
            $order_sum = $row['order']['field_values']['payment_sum'] ?? 0.00;

            $order_id = $values['order_id'] ?? '';

            $html .= '
              <tr data-refund-row="' . esc_attr($refund_id) . '"
                  data-order-id="' . esc_attr($order_id) . '">

                <td><input type="checkbox" data-refund-id="' . esc_attr($refund_id) . '"></td>

                <td><b>' . esc_html($order_id) . '</b><div class="faip-muted">Refund #' . esc_html($refund_id) . '</div></td>

                <td>' . esc_html(($row['created_at'] ?? '')) . '</td>

                <td data-col="reason">' . esc_html(($values['refund_reason'] ?? '')) . '</td>

                <td data-col="status">
                    <div class="mrf-status-text">' . esc_html(($values['status'] ?? '')) . '</div>
                    <div class="mrf-status-note" style="margin-top:6px;"></div>
                </td>

                <td data-col="amount">
                    <b><span class="mrf-amount-val">' . esc_html(($values['amount'] ?? '')) . '</span>$ / ' . esc_html($order_sum) . '$</b>
                </td>

                <td>
                    <div class="mrf-actions-wrap" style="display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a href="/orders/entry/' . esc_attr($order_id) . '" target="_blank">
                                <button class="faip-btn" type="button">Open order</button>
                            </a>

                            <button class="faip-btn" type="button" data-mrf-edit="' . esc_attr($refund_id) . '">Edit refund</button>
                        </div>

                        <div class="mrf-under-actions" data-under-actions="' . esc_attr($refund_id) . '"></div>
                    </div>
                </td>
              </tr>
            ';
        }

        return $html;
    }

    private function footer_count_text(array $list): string {
        $p = (array)($list['pagination'] ?? []);
        $page = (int)($p['page'] ?? 1);
        $per  = (int)($p['per_page'] ?? 20);
        $total= (int)($p['total'] ?? 0);

        $start = $total > 0 ? (($page - 1) * $per + 1) : 0;
        $end   = $total > 0 ? min($total, $page * $per) : 0;

        return $total > 0 ? "Showing {$start}-{$end} of {$total}" : "Showing 0 of 0";
    }

    /** ---------------- AJAX helpers ---------------- */

    private function guard_ajax(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json([ 'ok' => false, 'error' => 'Not logged in' ], 401);
        }
        if ( ! current_user_can('manage_options') ) {
            wp_send_json([ 'ok' => false, 'error' => 'Forbidden' ], 403);
        }
        $nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_ACTION) ) {
            wp_send_json([ 'ok' => false, 'error' => 'Bad nonce' ], 403);
        }
    }

    /** ---------------- AJAX (bulk) ---------------- */

    public function ajax_bulk_complete(): void {
        $this->guard_ajax();

        $refund_id = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
        if ($refund_id <= 0) wp_send_json([ 'ok'=>false,'error'=>'Missing refund_id' ], 400);

        $this->helper->setRefundStatus( $refund_id, 'Complete' );

        wp_send_json([ 'ok' => true, 'msg' => 'Form4 status -> Complete', 'new_status' => 'Complete' ]);
    }

    public function ajax_bulk_email(): void {
        $this->guard_ajax();

        $refund_id = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
        if ($refund_id <= 0) wp_send_json([ 'ok'=>false,'error'=>'Missing refund_id' ], 400);

        wp_send_json([ 'ok' => true, 'msg' => 'Email flag set' ]);
    }

    public function ajax_bulk_f1status(): void {
        $this->guard_ajax();

        $refund_id   = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
        $order_id    = isset($_POST['order_id']) ? sanitize_text_field((string)$_POST['order_id']) : '';
        $status_val  = isset($_POST['f1_status_value']) ? sanitize_text_field((string)$_POST['f1_status_value']) : 'Refunded';

        if ($refund_id <= 0) wp_send_json([ 'ok'=>false,'error'=>'Missing refund_id' ], 400);

        $this->helper->setOrderStatus( $order_id, $status_val );

        wp_send_json([ 'ok' => true, 'msg' => 'Order status updated' ]);
    }

    public function ajax_bulk_refund(): void {
        $this->guard_ajax();

        $refund_id = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
        if ($refund_id <= 0) wp_send_json([ 'ok'=>false,'error'=>'Missing refund_id' ], 400);

        $refundData = $this->helper->getEntryById($refund_id);

        $order_id = isset($refundData['field_values']['order_id']) ? $refundData['field_values']['order_id'] : 0;
        $amount   = isset($refundData['field_values']['amount']) ? $refundData['field_values']['amount'] : 0.00;
        $reason   = isset($refundData['field_values']['refund_reason']) ? $refundData['field_values']['refund_reason'] : '';

        if ($order_id <= 0) wp_send_json([ 'ok'=>false,'error'=>'Missing order_id' ], 400);

        $refundRes = $this->helper->refundPaymentByOrderId( $order_id, $amount, $reason );

        $ok  = (bool)($refundRes['ok'] ?? false);
        $msg = (string)($refundRes['message'] ?? 'Unknown error');

        wp_send_json([ 'ok' => $ok, 'msg' => $msg ]);
    }

    /** ---------------- AJAX (edit refund) ---------------- */

    public function ajax_edit_load(): void {
        $this->guard_ajax();

        $refund_id = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
        if ($refund_id <= 0) wp_send_json([ 'ok'=>false,'error'=>'Missing refund_id' ], 400);

        $entry = $this->helper->getEntryById($refund_id);
        $fv = isset($entry['field_values']) && is_array($entry['field_values']) ? $entry['field_values'] : [];

        $refs = $this->helper->getSelectRefs(self::FORM_ID);
        $status = $refs['status']['values'] ?? [];

        wp_send_json([
            'ok' => true,
            'data' => [
                'refund_id'      => $refund_id,
                'refund_reason'  => (string)($fv['refund_reason'] ?? ''),
                'status'         => (string)($fv['status'] ?? ''),
                'amount'         => (string)($fv['amount'] ?? ''),
                'status_options' => array_values(is_array($status) ? $status : []),
            ],
        ]);
    }

    public function ajax_edit_save(): void {
        $this->guard_ajax();

        $refund_id = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
        if ($refund_id <= 0) wp_send_json([ 'ok'=>false,'error'=>'Missing refund_id' ], 400);

        $refund_reason = isset($_POST['refund_reason']) ? wp_kses_post((string)$_POST['refund_reason']) : '';
        $status        = isset($_POST['status']) ? sanitize_text_field((string)$_POST['status']) : '';
        $amount_raw    = isset($_POST['amount']) ? sanitize_text_field((string)$_POST['amount']) : '';

        // keep amount format simple (your helper can validate more strictly)
        $amount = preg_replace('/[^0-9\.\,]/', '', $amount_raw);
        $amount = str_replace(',', '.', $amount);

        $res = $this->helper->updateRefundFields($refund_id, [
            'refund_reason' => $refund_reason,
            'status'        => $status,
            'amount'        => $amount,
        ]);

        $ok  = (bool)($res['ok'] ?? true);
        $msg = (string)($res['message'] ?? 'Saved');

        if (!$ok) {
            wp_send_json([ 'ok' => false, 'error' => $msg ], 200);
        }

        wp_send_json([
            'ok' => true,
            'msg' => $msg,
            'data' => [
                'refund_id'     => $refund_id,
                'refund_reason' => $refund_reason,
                'status'        => $status,
                'amount'        => $amount,
            ],
        ]);
    }

    /** ---------------- assets + JS ---------------- */
    private function enqueue_assets(): void {

        wp_enqueue_style(
            'dotfiler-ai-photos-page-css',
            esc_url(DOTFILER_BASE_PATH . 'assets/ai-photos-page.css?time=' . time()),
            [],
            '1.0.0'
        );
    
        wp_add_inline_style('dotfiler-ai-photos-page-css', '
            .mrf-spinner{display:inline-block;width:18px;height:18px;border:2px solid rgba(0,0,0,.15);border-top-color:rgba(0,0,0,.55);border-radius:50%;animation:mrfspin .7s linear infinite}
            @keyframes mrfspin{to{transform:rotate(360deg)}}
    
            .mrf-pill{display:inline-block;padding:2px 8px;border:1px solid #d0d7de;border-radius:999px;font-size:12px;line-height:1.3}
            .mrf-pill.ok{border-color:#2da44e}
            .mrf-pill.err{border-color:#cf222e}
    
            .mrf-mini{display:inline-block;padding:2px 8px;border:1px solid #d0d7de;border-radius:999px;font-size:12px;line-height:1.3}
            .mrf-mini.ok{border-color:#2da44e}
    
            .mrf-under-actions .mrf-pill{margin-right:6px}
            .mrf-under-actions .mrf-msg{font-size:12px}
    
            /* modal */
            .mrf-modal{position:fixed;inset:0;display:none;z-index:99999}
            .mrf-modal.is-open{display:block}
            .mrf-modal__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.35)}
            .mrf-modal__panel{position:relative;max-width:720px;width:calc(100% - 24px);margin:8vh auto 0;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden}
            .mrf-modal__head{display:flex;align-items:flex-start;justify-content:space-between;padding:16px 16px;border-bottom:1px solid #eee}
            .mrf-modal__title{font-size:18px;font-weight:700}
            .mrf-modal__sub{font-size:12px}
            .mrf-modal__body{padding:16px}
            .mrf-modal__foot{display:flex;justify-content:flex-end;gap:10px;padding:16px;border-top:1px solid #eee}
            .mrf-x{border:0;background:transparent;font-size:28px;line-height:1;cursor:pointer;padding:0 6px}
            .mrf-muted{color:#6a737d}
        ');
    
        wp_enqueue_script('jquery');
        wp_register_script('dot-mrf-js', '', ['jquery'], '1.0.0', true);
        wp_enqueue_script('dot-mrf-js');
    
        // Pass PHP vars safely (no string interpolation in the big JS)
        $cfg = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'actions' => [
                'refund'    => self::AJAX_REFUND,
                'complete'  => self::AJAX_COMPLETE,
                'email'     => self::AJAX_EMAIL,
                'f1stat'    => self::AJAX_F1STAT,
                'editLoad'  => self::AJAX_EDIT_LOAD,
                'editSave'  => self::AJAX_EDIT_SAVE,
            ],
        ];
    
        wp_add_inline_script('dot-mrf-js', 'window.DotMRF=' . wp_json_encode($cfg) . ';', 'before');
    
        $js = <<<'JS'
    (function($){
      function ids(){
        var out=[];
        $('#mrfTbody input[type=checkbox][data-refund-id]:checked').each(function(){
          out.push(parseInt($(this).attr('data-refund-id')||'0',10));
        });
        return out.filter(function(x){return x>0;});
      }
      function toggleBtns(){
        var on = ids().length>0;
        $('#mrfBulkRefund,#mrfBulkComplete,#mrfBulkEmail,#mrfBulkF1Status').prop('disabled', !on);
      }
      function setStatus(type, text){
        $('#mrfStatusbar').html('<span class="ffda-inline-msg '+(type||'')+'">'+String(text||'')+'</span>');
      }
    
      function rowEl(id){ return $('tr[data-refund-row="'+id+'"]'); }
      function getOrderId(id){ return rowEl(id).attr('data-order-id') || ''; }
    
      function underActions(id, html){ $('[data-under-actions="'+id+'"]').html(html||''); }
    
      function setRowStatus(id, statusText){
        rowEl(id).find('[data-col="status"] .mrf-status-text').text(String(statusText||''));
      }
      function setRowStatusNote(id, html){
        rowEl(id).find('[data-col="status"] .mrf-status-note').html(html||'');
      }
    
      function setRowReason(id, text){
        rowEl(id).find('[data-col="reason"]').text(String(text||''));
      }
      function setRowAmount(id, val){
        rowEl(id).find('[data-col="amount"] .mrf-amount-val').text(String(val||''));
      }
    
      function badge(type, msg){
        var cls = (type === 'ok') ? 'ok' : (type === 'err' ? 'err' : '');
        var label = (type === 'ok') ? 'OK' : (type === 'err' ? 'ERR' : '');
        var safeMsg = String(msg||'');
        return '<span class="mrf-pill '+cls+'">'+label+'</span><span class="mrf-msg">'+safeMsg+'</span>';
      }
    
      function runBulk(action, extra){
        var list=ids();
        if(!list.length) return;
        var nonce=$('#mrf').attr('data-nonce')||'';
        var i=0;
    
        setStatus('', 'Processing '+list.length+'…');
    
        function next(){
          if(i>=list.length){
            setStatus('ok','Done: '+list.length);
            return;
          }
          var rid=list[i++];
          var oid=getOrderId(rid);
    
          if(action === DotMRF.actions.refund){
            underActions(rid, '<span class="mrf-pill ok">Processing refund</span> <span class="mrf-spinner" style="vertical-align:-4px"></span>');
          } else {
            underActions(rid, '<span class="mrf-spinner"></span>');
          }
    
          var data=$.extend({}, extra||{}, {
            action: action,
            nonce: nonce,
            refund_id: rid,
            order_id: oid
          });
    
          $.post(DotMRF.ajaxUrl, data).done(function(res){
            if(res && res.ok){
              underActions(rid, badge('ok', res.msg ? res.msg : 'OK'));
    
              if(action === DotMRF.actions.complete){
                var newStatus = (res && res.new_status) ? res.new_status : 'Complete';
                setRowStatus(rid, newStatus);
                setRowStatusNote(rid, '<span class="mrf-mini ok">Status changed</span>');
              }
            } else {
              var emsg = (res && (res.error || res.msg)) ? (res.error || res.msg) : 'Unknown';
              underActions(rid, badge('err', emsg));
            }
            next();
          }).fail(function(xhr){
            underActions(rid, badge('err', 'HTTP '+xhr.status));
            next();
          });
        }
        next();
      }
    
      // check all
      $('#mrfCheckAll').on('change', function(){
        var on=$(this).is(':checked');
        $('#mrfTbody input[type=checkbox][data-refund-id]').prop('checked', on);
        toggleBtns();
      });
      $(document).on('change','#mrfTbody input[type=checkbox][data-refund-id]', toggleBtns);
    
      // bulk buttons
      $('#mrfBulkRefund').on('click', function(e){
        e.preventDefault();
        if(!confirm('Issue refunds for selected rows?')) return;
        runBulk(DotMRF.actions.refund);
      });
      $('#mrfBulkComplete').on('click', function(e){
        e.preventDefault();
        runBulk(DotMRF.actions.complete);
      });
      $('#mrfBulkEmail').on('click', function(e){
        e.preventDefault();
        runBulk(DotMRF.actions.email);
      });
      $('#mrfBulkF1Status').on('click', function(e){
        e.preventDefault();
        var v=$('#mrfF1StatusVal').val()||'';
        if(!v){ alert('Choose status'); return; }
        runBulk(DotMRF.actions.f1stat, { f1_status_value: v });
      });
    
      // Apply safeguard
      $('#mrfFiltersForm').on('submit', function(){
        $(this).find('input[name="mrf_page"]').val('1');
      });
    
      // -------- EDIT MODAL --------
      var edit = { refund_id: 0 };
    
      function openModal(){
        $('#mrfEditModal').addClass('is-open').attr('aria-hidden','false');
      }
      function closeModal(){
        $('#mrfEditModal').removeClass('is-open').attr('aria-hidden','true');
        $('#mrfEditMsg').empty();
        edit.refund_id = 0;
      }
      function setEditMsg(type, text){
        $('#mrfEditMsg').html('<span class="ffda-inline-msg '+(type||'')+'">'+String(text||'')+'</span>');
      }
    
      function escHtml(s){ return $('<div>').text(String(s||'')).html(); }
    
      function fillStatusOptions(options, current){
        var $s = $('#mrfEditStatus');
        $s.empty();
        $s.append('<option value=""></option>');
        if($.isArray(options)){
          options.forEach(function(opt){
            var val = (opt && opt.value != null) ? String(opt.value) : '';
            var lbl = (opt && opt.label != null) ? String(opt.label) : val;
            var sel = (val !== '' && val === String(current||'')) ? ' selected' : '';
            $s.append('<option value="'+escHtml(val)+'"'+sel+'>'+escHtml(lbl)+'</option>');
          });
        }
      }
    
      $(document).on('click','[data-mrf-edit]', function(e){
        e.preventDefault();
        var rid = parseInt($(this).attr('data-mrf-edit')||'0',10);
        if(!rid){ return; }
    
        edit.refund_id = rid;
        $('#mrfEditSub').text('Refund #' + rid);
        $('#mrfEditReason').val('');
        $('#mrfEditAmount').val('');
        fillStatusOptions([], '');
    
        setEditMsg('', 'Loading…');
        openModal();
    
        var nonce=$('#mrf').attr('data-nonce')||'';
        $.post(DotMRF.ajaxUrl, { action: DotMRF.actions.editLoad, nonce: nonce, refund_id: rid })
          .done(function(res){
            if(res && res.ok && res.data){
              $('#mrfEditReason').val(res.data.refund_reason || '');
              $('#mrfEditAmount').val(res.data.amount || '');
              fillStatusOptions(res.data.status_options || [], res.data.status || '');
              $('#mrfEditStatus').val(res.data.status || '');
              $('#mrfEditMsg').empty();
            } else {
              setEditMsg('err', (res && res.error) ? res.error : 'Load failed');
            }
          })
          .fail(function(xhr){
            setEditMsg('err', 'HTTP ' + xhr.status);
          });
      });
    
      $(document).on('click','[data-mrf-close]', function(e){
        e.preventDefault();
        closeModal();
      });
    
      $(document).on('keydown', function(e){
        if(e.key === 'Escape' && $('#mrfEditModal').hasClass('is-open')) closeModal();
      });
    
      $('#mrfEditSave').on('click', function(e){
        e.preventDefault();
        if(!edit.refund_id){ return; }
    
        var rid = edit.refund_id;
        var nonce=$('#mrf').attr('data-nonce')||'';
    
        var payload = {
          action: DotMRF.actions.editSave,
          nonce: nonce,
          refund_id: rid,
          refund_reason: $('#mrfEditReason').val() || '',
          status: $('#mrfEditStatus').val() || '',
          amount: $('#mrfEditAmount').val() || ''
        };
    
        setEditMsg('', 'Saving…');
    
        $.post(DotMRF.ajaxUrl, payload).done(function(res){
          if(res && res.ok && res.data){
            setRowReason(rid, res.data.refund_reason || '');
            setRowStatus(rid, res.data.status || '');
            setRowAmount(rid, res.data.amount || '');
            setRowStatusNote(rid, '<span class="mrf-mini ok">Status changed</span>');
    
            underActions(rid, badge('ok', res.msg ? res.msg : 'Saved'));
            closeModal();
          } else {
            setEditMsg('err', (res && (res.error || res.msg)) ? (res.error || res.msg) : 'Save failed');
          }
        }).fail(function(xhr){
          setEditMsg('err', 'HTTP ' + xhr.status);
        });
      });
    
      toggleBtns();
    })(jQuery);
    JS;
    
        wp_add_inline_script('dot-mrf-js', $js, 'after');
    }
    

    /** ---------------- url helpers ---------------- */

    private function current_url_without(array $remove_keys): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $url = $scheme . '://' . $host . $uri;

        $parts = wp_parse_url($url);
        $path = $parts['path'] ?? '';
        $query = [];
        if (!empty($parts['query'])) parse_str($parts['query'], $query);

        foreach ($remove_keys as $k) unset($query[$k]);

        $base = $scheme . '://' . $host . $path;
        if (!empty($query)) $base .= '?' . http_build_query($query);

        return $base;
    }

    private function add_query_arg_safe(string $url, string $key, string $value): string {
        return add_query_arg([ $key => $value ], $url);
    }
}

add_action('init', function(){
    if (class_exists('DotFrmMassRefundShortcode_Simple')) {
        new DotFrmMassRefundShortcode_Simple();
    }
});
