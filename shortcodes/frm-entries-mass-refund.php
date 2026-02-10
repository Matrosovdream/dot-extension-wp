<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Shortcode: [frm-entries-mass-refund]
 *
 * SIMPLE VERSION (no extra helper class):
 * - Hardcoded filter block (Status / Time window / Amount type)
 * - Same list of rows
 * - Bulk actions via AJAX
 *
 * IMPORTANT: You MUST set these constants to your real field IDs / matching logic:
 * - Form 1: field that stores Authorize transaction id (charge id)
 * - Form 1: field that stores charged amount (optional, only for Full/Partial filter)
 * - Form 1: field 7 = Status (you already said)
 *
 * Matching Form4 -> Form1:
 * - Default: Form4 Order# (Field152) == Form1 Entry ID (common in your setup)
 *   If your Form1 entry id is NOT equal to Order#, change find_form1_txn_entry_id().
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
    private const NONCE_ACTION = 'dot_mrf_nonce';
    private const AJAX_REFUND  = 'dot_mrf_bulk_refund';
    private const AJAX_COMPLETE= 'dot_mrf_bulk_complete';
    private const AJAX_EMAIL   = 'dot_mrf_bulk_email';
    private const AJAX_F1STAT  = 'dot_mrf_bulk_f1status';

    private $helper;

    public function __construct() {

        $this->helper = new DotFrmMassRefundHelper();

        add_shortcode(self::SHORTCODE, [ $this, 'render_shortcode' ]);

        add_action('wp_ajax_' . self::AJAX_REFUND,   [ $this, 'ajax_bulk_refund' ]);
        add_action('wp_ajax_' . self::AJAX_COMPLETE, [ $this, 'ajax_bulk_complete' ]);
        add_action('wp_ajax_' . self::AJAX_EMAIL,    [ $this, 'ajax_bulk_email' ]);
        add_action('wp_ajax_' . self::AJAX_F1STAT,   [ $this, 'ajax_bulk_f1status' ]);
    }

    public function render_shortcode($atts = []): string {

        $form_id = self::FORM_ID;

        $this->enqueue_assets();

        $page = isset($_GET[self::QP_PAGE]) ? max(1, (int)$_GET[self::QP_PAGE]) : 1;
        $per_page = 20;

        $status = isset($_GET[self::QP_STATUS]) ? sanitize_text_field((string)$_GET[self::QP_STATUS]) : '';
        $time   = isset($_GET[self::QP_TIME]) ? sanitize_text_field((string)$_GET[self::QP_TIME]) : '';
        $atype  = isset($_GET[self::QP_AMOUNT_TYPE]) ? sanitize_text_field((string)$_GET[self::QP_AMOUNT_TYPE]) : '';

        // Select refs for Status
        $refs = $this->helper->getSelectRefs($form_id);
        $status_values = $refs['status'] ?? [];
        $amount_type = $refs['amount_type'] ?? [];

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

        /*
        echo '<pre>';
        print_r($list);
        echo '</pre>';
        die();
        */
        

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

                    <div style="display:flex; gap:8px; align-items:center;">
                        <select id="mrfF1StatusVal" style="height:34px; border:1px solid #d0d7de; border-radius:8px; padding:0 10px;">
                            <option value="Refunded">Form1 Status: Refunded</option>
                            <option value="Refund Requested">Form1 Status: Refund Requested</option>
                            <option value="Refund Complete">Form1 Status: Refund Complete</option>
                        </select>
                        <button class="faip-btn" id="mrfBulkF1Status" disabled>Update Form1 Status</button>
                    </div>
                </div>
            </div>

            <!-- HARD CODED FILTERS (simple) -->
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
                        <?php foreach ($status_values['values'] as $opt): ?>
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
                        <?php foreach ($amount_type['values'] as $opt): ?>
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

                <!-- NEW: Apply button (submits filter form) -->
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
                    <th style="width:120px;">Amount</th>
                    <th style="width:180px;">Actions</th>
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
            $values = $row['field_values'] ?? [];
            $order_sum = $row['order']['field_values']['payment_sum'] ?? 0.00;

            $html .= '
              <tr data-refund-row="' . esc_attr($refund_id) . '">
                <td><input type="checkbox" data-refund-id="' . esc_attr($refund_id) . '"></td>
                <td><b>' . esc_html($values['order_id'] ?? '') . '</b><div class="faip-muted">Refund #' . esc_html($refund_id) . '</div></td>
                <td>' . esc_html(($row['created_at'] ?? '')) . '</td>
                <td>' . esc_html(($values['refund_reason'] ?? '')) . '</td>
                <td>' . esc_html(($values['status'] ?? '')) . '</td>
                <td><b>' . esc_html(($values['amount'] ?? '')) . '$ / '.$order_sum.'$</b></td>

                <td>
                    <a href="/orders/entry/'.$values['order_id'].'">
                        <button class="faip-btn" data-action="view" data-id="15414400" type="button">Open order</button>
                    </a>

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

    /** ---------------- AJAX (bulk) ---------------- */

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

    public function ajax_bulk_complete(): void {
        $this->guard_ajax();

        $refund_id = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
        if ($refund_id <= 0) wp_send_json([ 'ok'=>false,'error'=>'Missing refund_id' ], 400);

        $ok = true;
        wp_send_json([ 'ok' => (bool)$ok, 'msg' => 'Form4 status -> Complete' ]);
    }

    public function ajax_bulk_email(): void {
        $this->guard_ajax();

        $refund_id = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
        if ($refund_id <= 0) wp_send_json([ 'ok'=>false,'error'=>'Missing refund_id' ], 400);

        $ok = true;
        wp_send_json([ 'ok' => (bool)$ok, 'msg' => 'Email flag set' ]);
    }

    public function ajax_bulk_f1status(): void {
        $this->guard_ajax();

        $refund_id = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
        $val = isset($_POST['f1_status_value']) ? sanitize_text_field((string)$_POST['f1_status_value']) : 'Refunded';
        if ($refund_id <= 0) wp_send_json([ 'ok'=>false,'error'=>'Missing refund_id' ], 400);

        $ok = true;

        wp_send_json([ 'ok' => (bool)$ok, 'msg' => 'Form1 status updated' ]);
    }

    public function ajax_bulk_refund(): void {
        $this->guard_ajax();

        $refund_id = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
        if ($refund_id <= 0) wp_send_json([ 'ok'=>false,'error'=>'Missing refund_id' ], 400);

        

        wp_send_json([ 'ok'=>true, 'msg'=>'Refund issued' ]);
    }

    /** ---------------- assets + JS ---------------- */
    private function enqueue_assets(): void {

        // Reuse your existing style file (same as your AI photos page)
        wp_enqueue_style(
            'dotfiler-ai-photos-page-css',
            esc_url(DOTFILER_BASE_PATH . 'assets/ai-photos-page.css?time=' . time()),
            [],
            '1.0.0'
        );

        wp_add_inline_style('dotfiler-ai-photos-page-css', '
            .mrf-spinner{display:inline-block;width:18px;height:18px;border:2px solid rgba(0,0,0,.15);border-top-color:rgba(0,0,0,.55);border-radius:50%;animation:mrfspin .7s linear infinite}
            @keyframes mrfspin{to{transform:rotate(360deg)}}
            .mrf-pill{display:inline-block;padding:2px 8px;border:1px solid #d0d7de;border-radius:999px;font-size:12px}
            .mrf-pill.ok{border-color:#2da44e}
            .mrf-pill.err{border-color:#cf222e}
        ');

        wp_enqueue_script('jquery');
        wp_register_script('dot-mrf-js', '', ['jquery'], '1.0.0', true);
        wp_enqueue_script('dot-mrf-js');

        $ajax_url = admin_url('admin-ajax.php');

        wp_add_inline_script('dot-mrf-js', "
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
    $('#mrfStatusbar').html('<span class=\"ffda-inline-msg '+(type||'')+'\">'+String(text||'')+'</span>');
  }
  function rowRes(id, html){
    $('tr[data-refund-row=\"'+id+'\"]').find('[data-col=\"result\"]').html(html||'');
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
      rowRes(rid,'<span class=\"mrf-spinner\"></span>');

      var data=$.extend({}, extra||{}, { action: action, nonce: nonce, refund_id: rid });

      $.post('{$ajax_url}', data).done(function(res){
        if(res && res.ok){
          rowRes(rid,'<span class=\"mrf-pill ok\">OK</span>');
        } else {
          rowRes(rid,'<span class=\"mrf-pill err\">ERR</span> <span class=\"faip-muted\">'+(res && res.error ? res.error : 'Unknown')+'</span>');
        }
        next();
      }).fail(function(xhr){
        rowRes(rid,'<span class=\"mrf-pill err\">ERR</span> <span class=\"faip-muted\">HTTP '+xhr.status+'</span>');
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

  // bulk
  $('#mrfBulkRefund').on('click', function(e){
    e.preventDefault();
    if(!confirm('Issue refunds for selected rows?')) return;
    runBulk('" . self::AJAX_REFUND . "');
  });
  $('#mrfBulkComplete').on('click', function(e){
    e.preventDefault();
    runBulk('" . self::AJAX_COMPLETE . "');
  });
  $('#mrfBulkEmail').on('click', function(e){
    e.preventDefault();
    runBulk('" . self::AJAX_EMAIL . "');
  });
  $('#mrfBulkF1Status').on('click', function(e){
    e.preventDefault();
    var v=$('#mrfF1StatusVal').val()||'Refunded';
    runBulk('" . self::AJAX_F1STAT . "', { f1_status_value: v });
  });

  // Apply (optional safeguard: set page=1 before submit)
  $('#mrfFiltersForm').on('submit', function(){
    $(this).find('input[name=\"" . self::QP_PAGE . "\"]').val('1');
  });

  toggleBtns();
})(jQuery);
");
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
