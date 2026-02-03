<?php
/**
 * Plugin Name: Frm Entries Mass Photo Shortcode
 * Description: Shortcode [frm-entries-mass-photo] with 2-step entry picker + 12 image grid. Detects GET ?entries=... and supports Back + Print (prints exact sheet area).
 * Version: 1.0.3
 */

if ( ! defined('ABSPATH') ) { exit; }

final class Frm_Entries_Mass_Photo_Shortcode {

    private const SHORTCODE     = 'frm-entries-mass-photo';
    private const AJAX_ACTION   = 'femp_lookup_entry';
    private const NONCE_ACTION  = 'femp_lookup_entry_nonce';
    private const MAX_IDS       = 6; // selection max

    /**
     * We use a separate param "step" to control UI navigation:
     * - step=1 -> picker
     * - step=2 -> grid
     *
     * Selection is stored in GET "entries" and carried forward/back.
     */
    private const GET_STEP_KEY    = 'step';
    private const GET_ENTRIES_KEY = 'entries';

    // Letter size in mm: 215.90 x 279.40
    private const SHEET_W_MM = '215.90mm';
    private const SHEET_H_MM = '279.40mm';

    // Grid and margins (from your drawing)
    private const GRID_CELL_MM = '50.80mm'; // each photo box
    private const GRID_GAP_MM  = '10mm';    // spacing between boxes
    private const PAD_X_MM     = '21.75mm'; // left/right margin
    // Vertical total remaining = 279.4 - (4*50.8 + 3*10) = 46.2mm
    // We'll center vertically -> 23.1mm top & bottom
    private const PAD_Y_MM     = '23.10mm'; // top/bottom margin (centered)

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'render_shortcode']);

        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajax_lookup_entry']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'ajax_lookup_entry']); // remove if you want logged-in only
    }

    public static function render_shortcode($atts): string {
        $atts = shortcode_atts([
            'entries' => '',
        ], $atts, self::SHORTCODE);

        $step_get = isset($_GET[self::GET_STEP_KEY]) ? (string) wp_unslash($_GET[self::GET_STEP_KEY]) : '';
        $step     = ($step_get === '2') ? 2 : 1;

        if (isset($_GET[self::GET_ENTRIES_KEY]) && $_GET[self::GET_ENTRIES_KEY] !== '') {
            $entries_raw = (string) wp_unslash($_GET[self::GET_ENTRIES_KEY]);
        } else {
            $entries_raw = trim((string) $atts['entries']);
        }

        $entries_ids = self::parse_entries_csv($entries_raw);

        if ($step === 2 && !empty($entries_ids)) {
            return self::render_step2($entries_ids);
        }

        return self::render_step1($entries_ids);
    }

    private static function render_step1(array $prefill_ids = []): string {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce    = wp_create_nonce(self::NONCE_ACTION);

        $prefill_json = wp_json_encode(array_map('intval', $prefill_ids));

        ob_start();
        ?>
        <div class="femp-wrap"
             data-ajax-url="<?php echo esc_attr($ajax_url); ?>"
             data-nonce="<?php echo esc_attr($nonce); ?>"
             data-max="<?php echo esc_attr((string) self::MAX_IDS); ?>"
             data-prefill="<?php echo esc_attr($prefill_json ?: '[]'); ?>">

            <div class="femp-step femp-step-1">
                <div class="femp-title">Step 1 — Select entries</div>

                <div class="femp-row">
                    <input class="femp-input" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Enter Entry ID (e.g. 15414500)" />
                    <button class="femp-btn femp-btn-primary" type="button">Search</button>
                </div>

                <div class="femp-hint">
                    Click a found entry to add it to the selection (max <?php echo (int) self::MAX_IDS; ?>).
                </div>

                <div class="femp-results">
                    <div class="femp-subtitle">Results</div>
                    <ul class="femp-list femp-list-results"></ul>
                </div>

                <div class="femp-chosen">
                    <div class="femp-subtitle">Chosen Entry IDs</div>
                    <div class="femp-chosen-ids">—</div>

                    <div class="femp-chosen-actions">
                        <button class="femp-btn femp-btn-clear" type="button">Clear</button>
                    </div>
                </div>

                <div class="femp-actions">
                    <button class="femp-btn femp-btn-next" type="button" disabled>Next</button>
                </div>

                <div class="femp-msg" aria-live="polite"></div>
            </div>
        </div>

        <style>
            .femp-wrap{max-width:760px;margin:18px auto;padding:16px;border:1px solid #e6e6e6;border-radius:12px;background:#fff}
            .femp-title{font-size:18px;font-weight:700;margin-bottom:12px}
            .femp-row{display:flex;gap:10px;align-items:center}
            .femp-input{flex:1;padding:10px 12px;border:1px solid #d9d9d9;border-radius:10px;font-size:14px}
            .femp-btn{padding:10px 14px;border-radius:10px;border:1px solid #d9d9d9;background:#f6f6f6;cursor:pointer;font-weight:600}
            .femp-btn-primary{background:#111;color:#fff;border-color:#111}
            .femp-btn[disabled]{opacity:.5;cursor:not-allowed}
            .femp-hint{margin:10px 0 14px;color:#666;font-size:13px}
            .femp-subtitle{font-weight:700;margin:10px 0 8px}
            .femp-list{list-style:none;margin:0;padding:0}
            .femp-list li{padding:10px 12px;border:1px solid #eee;border-radius:10px;margin-bottom:8px;cursor:pointer;display:flex;justify-content:space-between;gap:10px}
            .femp-list li:hover{background:#fafafa}
            .femp-pill{font-size:12px;padding:2px 8px;border-radius:999px;border:1px solid #ddd;background:#fff;color:#333;white-space:nowrap}
            .femp-chosen-ids{padding:10px 12px;border:1px dashed #ddd;border-radius:10px;color:#333}
            .femp-chosen-actions{margin-top:10px;display:flex;justify-content:flex-end}
            .femp-actions{margin-top:14px;display:flex;justify-content:flex-end}
            .femp-msg{margin-top:10px;font-size:13px}
            .femp-msg .ok{color:#0a7a28}
            .femp-msg .err{color:#b00020}
        </style>

        <script>
        (function(){
            function qs(sel, root){ return (root||document).querySelector(sel); }
            function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

            function escapeHtml(s){
                return String(s).replace(/[&<>"']/g, function(m){
                    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
                });
            }

            function parseId(v){
                v = String(v||'').trim();
                if(!/^\d+$/.test(v)) return '';
                return v;
            }

            function setMsg(wrap, type, text){
                var box = qs('.femp-msg', wrap);
                if(!box){ return; }
                box.innerHTML = '<span class="'+(type==='ok'?'ok':'err')+'">'+escapeHtml(text)+'</span>';
            }

            function updateChosenUI(wrap, chosen){
                var box = qs('.femp-chosen-ids', wrap);
                var btnNext = qs('.femp-btn-next', wrap);

                if(chosen.length){
                    box.textContent = chosen.join(', ');
                    btnNext.disabled = false;
                }else{
                    box.textContent = '—';
                    btnNext.disabled = true;
                }
            }

            function urlForStep(step, entriesCsv){
                var url = new URL(window.location.href);
                url.searchParams.set('step', String(step));
                if(entriesCsv && String(entriesCsv).trim() !== ''){
                    url.searchParams.set('entries', entriesCsv);
                }else{
                    url.searchParams.delete('entries');
                }
                return url.toString();
            }

            function redirectToStep2(chosen){
                window.location.href = urlForStep(2, chosen.join(','));
            }

            function addResultItem(list, entry){
                var li = document.createElement('li');
                li.setAttribute('data-entry-id', entry.id);
                li.innerHTML =
                    '<div><strong>#'+escapeHtml(entry.id)+'</strong>'
                    + (entry.title ? ' — '+escapeHtml(entry.title) : '')
                    + '</div>'
                    + '<div class="femp-pill">add</div>';
                list.prepend(li);
            }

            function initOne(wrap){
                var ajaxUrl = wrap.getAttribute('data-ajax-url') || '';
                var nonce = wrap.getAttribute('data-nonce') || '';
                var max = parseInt(wrap.getAttribute('data-max') || '6', 10);
                var prefill = [];
                try { prefill = JSON.parse(wrap.getAttribute('data-prefill') || '[]'); } catch(e){ prefill = []; }
                if(!ajaxUrl) return;

                var input = qs('.femp-input', wrap);
                var btnSearch = qs('.femp-btn-primary', wrap);
                var listResults = qs('.femp-list-results', wrap);
                var btnNext = qs('.femp-btn-next', wrap);
                var btnClear = qs('.femp-btn-clear', wrap);

                var chosen = [];

                function chosenHas(id){ return chosen.indexOf(id) !== -1; }

                function addChosen(id){
                    if(chosenHas(id)){
                        setMsg(wrap, 'err', 'Already selected: ' + id);
                        return;
                    }
                    if(chosen.length >= max){
                        setMsg(wrap, 'err', 'Maximum selected IDs is ' + max);
                        return;
                    }
                    chosen.push(id);
                    updateChosenUI(wrap, chosen);
                    setMsg(wrap, 'ok', 'Added: ' + id);
                }

                function setChosenFromPrefill(){
                    if(!Array.isArray(prefill) || !prefill.length) return;
                    prefill.forEach(function(v){
                        var id = parseId(v);
                        if(id && !chosenHas(id) && chosen.length < max){
                            chosen.push(id);
                        }
                    });
                    updateChosenUI(wrap, chosen);
                }

                listResults.addEventListener('click', function(e){
                    var li = e.target.closest('li[data-entry-id]');
                    if(!li) return;
                    var id = li.getAttribute('data-entry-id');
                    if(id) addChosen(id);
                });

                btnNext.addEventListener('click', function(){
                    if(!chosen.length) return;
                    redirectToStep2(chosen);
                });

                if(btnClear){
                    btnClear.addEventListener('click', function(){
                        chosen = [];
                        updateChosenUI(wrap, chosen);
                        setMsg(wrap, 'ok', 'Cleared.');
                    });
                }

                function doSearch(){
                    var id = parseId(input.value);
                    if(!id){
                        setMsg(wrap, 'err', 'Enter a numeric Entry ID.');
                        return;
                    }

                    btnSearch.disabled = true;
                    setMsg(wrap, 'ok', 'Searching #' + id + '...');

                    var fd = new FormData();
                    fd.append('action', '<?php echo esc_js(self::AJAX_ACTION); ?>');
                    fd.append('_ajax_nonce', nonce);
                    fd.append('entry_id', id);

                    fetch(ajaxUrl, { method:'POST', credentials:'same-origin', body: fd })
                        .then(function(r){ return r.json(); })
                        .then(function(json){
                            if(json && json.ok && json.entry && json.entry.id){
                                addResultItem(listResults, json.entry);
                                setMsg(wrap, 'ok', 'Found entry #' + json.entry.id + '. Click it to add.');
                            }else{
                                setMsg(wrap, 'err', (json && json.message) ? json.message : 'Not found.');
                            }
                        })
                        .catch(function(){
                            setMsg(wrap, 'err', 'Request failed.');
                        })
                        .finally(function(){
                            btnSearch.disabled = false;
                        });
                }

                btnSearch.addEventListener('click', doSearch);
                input.addEventListener('keydown', function(e){
                    if(e.key === 'Enter'){ e.preventDefault(); doSearch(); }
                });

                setChosenFromPrefill();
                updateChosenUI(wrap, chosen);
            }

            document.addEventListener('DOMContentLoaded', function(){
                qsa('.femp-wrap').forEach(initOne);
            });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_step2(array $entries_ids): string {

        /**
         * 12 example images (internet).
         * If you want these to be based on the selected entries later, you can replace URLs with your own.
         */
        $imgs = [
            'https://picsum.photos/id/1011/1200/900',
            'https://picsum.photos/id/1015/1200/900',
            'https://picsum.photos/id/1025/1200/900',
            'https://picsum.photos/id/1035/1200/900',
            'https://picsum.photos/id/1040/1200/900',
            'https://picsum.photos/id/1050/1200/900',
            'https://picsum.photos/id/1060/1200/900',
            'https://picsum.photos/id/1070/1200/900',
            'https://picsum.photos/id/1080/1200/900',
            'https://picsum.photos/id/1084/1200/900',
            'https://picsum.photos/id/1081/1200/900',
            'https://picsum.photos/id/1082/1200/900',
        ];

        $ids_clean = array_map('intval', $entries_ids);
        $ids_str   = implode(', ', $ids_clean);

        // Back URL keeps entries and goes to step=1
        $back_url = self::url_for_step(1, implode(',', $ids_clean));

        ob_start();
        ?>

        <div class="femp-wrap femp-wrap-wide">
            <div class="femp-topbar">
                <a class="femp-btn femp-btn-back" href="<?php echo esc_url($back_url); ?>">&larr; Back</a>
                <button class="femp-btn femp-btn-print" type="button" id="femp-print-btn">Print</button>
            </div>

            <div class="femp-title">Step 2 — Photos sheet</div>
            <div class="femp-hint">Selected entries: <strong><?php echo esc_html($ids_str); ?></strong></div>

            <!-- This exact element will be printed -->
            <div class="femp-print-sheet" id="femp-print-area" aria-label="Print area">
                <div class="femp-print-grid">
                    <?php foreach ($imgs as $url): ?>
                        <div class="femp-print-cell">
                            <img src="<?php echo esc_url($url); ?>" alt="" loading="lazy" />
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <style>
            .femp-wrap{max-width:900px;margin:18px auto;padding:16px;border:1px solid #e6e6e6;border-radius:12px;background:#fff}
            .femp-wrap-wide{max-width:980px}
            .femp-topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
            .femp-title{font-size:18px;font-weight:700;margin-bottom:8px}
            .femp-hint{margin:0 0 14px;color:#666;font-size:13px}
            .femp-btn{padding:10px 14px;border-radius:10px;border:1px solid #d9d9d9;background:#f6f6f6;cursor:pointer;font-weight:600;text-decoration:none;display:inline-block;color:#111}
            .femp-btn-back{background:#fff}
            .femp-btn-print{background:#111;color:#fff;border-color:#111}

            /**
             * Print sheet dimensions EXACTLY (Letter): 215.90mm x 279.40mm
             * Margins (from your drawing): 21.75mm left/right
             * Grid: 3 cols x 4 rows, each 50.80mm, gaps 10mm
             * Top/bottom: centered (23.10mm)
             */
            .femp-print-sheet{
                width: <?php echo esc_html(self::SHEET_W_MM); ?>;
                height: <?php echo esc_html(self::SHEET_H_MM); ?>;
                background:#fff;
                border: 1px solid #000; /* the outer print line */
                box-sizing: border-box;
                padding: <?php echo esc_html(self::PAD_Y_MM); ?> <?php echo esc_html(self::PAD_X_MM); ?>;
                margin: 0 auto;
            }

            .femp-print-grid{
                display: grid;
                grid-template-columns: repeat(3, <?php echo esc_html(self::GRID_CELL_MM); ?>);
                grid-auto-rows: <?php echo esc_html(self::GRID_CELL_MM); ?>;
                gap: <?php echo esc_html(self::GRID_GAP_MM); ?>;
                width: fit-content;
                height: fit-content;
                margin: 0 auto;
            }

            .femp-print-cell{
                width: <?php echo esc_html(self::GRID_CELL_MM); ?>;
                height: <?php echo esc_html(self::GRID_CELL_MM); ?>;
                border: 1px solid #000;  /* the square print line */
                box-sizing: border-box;
                overflow: hidden;
                background:#fff;
                display:flex;
                align-items:center;
                justify-content:center;
            }

            .femp-print-cell img{
                width:100%;
                height:100%;
                object-fit: cover;
                display:block;
            }

            /* On screen, keep it responsive-ish but preserve dimensions */
            @media (max-width: 1100px){
                .femp-print-sheet{max-width:100%; overflow:auto}
            }
        </style>

        <script>
        (function(){
            function byId(id){ return document.getElementById(id); }

            function openPrintWindow(printEl){
                var html = '<!doctype html><html><head><meta charset="utf-8"><title>Print</title>';
                html += '<style>';
                html += '@page{size: letter; margin: 0;}';
                html += 'html,body{margin:0;padding:0;background:#fff;}';
                html += '*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact;}';
                // ensure the print area is at top-left, no extra UI
                html += '.print-wrap{width:<?php echo esc_js(self::SHEET_W_MM); ?>;height:<?php echo esc_js(self::SHEET_H_MM); ?>;margin:0;}';
                html += '</style></head><body>';
                html += '<div class="print-wrap">' + printEl.outerHTML + '</div>';
                html += '</body></html>';

                var w = window.open('', '_blank', 'noopener,noreferrer');
                if(!w){
                    alert('Pop-up blocked. Please allow pop-ups for printing.');
                    return;
                }
                w.document.open();
                w.document.write(html);
                w.document.close();

                // Wait for images to load then print
                var tryPrint = function(){
                    try { w.focus(); w.print(); } catch(e) {}
                };

                w.onload = function(){
                    var imgs = w.document.images;
                    if(!imgs || imgs.length === 0){
                        setTimeout(tryPrint, 100);
                        return;
                    }

                    var remaining = imgs.length;
                    var done = function(){
                        remaining--;
                        if(remaining <= 0){
                            setTimeout(tryPrint, 150);
                        }
                    };

                    for(var i=0;i<imgs.length;i++){
                        if(imgs[i].complete) done();
                        else {
                            imgs[i].addEventListener('load', done);
                            imgs[i].addEventListener('error', done);
                        }
                    }
                };
            }

            document.addEventListener('DOMContentLoaded', function(){
                var btn = byId('femp-print-btn');
                var area = byId('femp-print-area');
                if(!btn || !area) return;

                btn.addEventListener('click', function(){
                    openPrintWindow(area);
                });
            });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    public static function ajax_lookup_entry(): void {
        $nonce = isset($_POST['_ajax_nonce']) ? (string) $_POST['_ajax_nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_ACTION) ) {
            wp_send_json(['ok' => false, 'message' => 'Security check failed.']);
        }

        $entry_id = isset($_POST['entry_id']) ? preg_replace('/\D+/', '', (string) $_POST['entry_id']) : '';
        if ($entry_id === '') {
            wp_send_json(['ok' => false, 'message' => 'Missing entry_id.']);
        }

        try {
            if (!class_exists('DotFrmEntryHelper')) {
                wp_send_json(['ok' => false, 'message' => 'DotFrmEntryHelper class not found.']);
            }

            $helper = new DotFrmEntryHelper();
            $entry  = $helper->getEntryById((int) $entry_id);

            if (is_object($entry) && !empty($entry->id)) {
                $title = '';
                if (!empty($entry->name)) { $title = (string) $entry->name; }
                elseif (!empty($entry->title)) { $title = (string) $entry->title; }

                wp_send_json([
                    'ok' => true,
                    'entry' => [
                        'id'    => (int) $entry->id,
                        'title' => $title,
                    ],
                ]);
            }

            wp_send_json(['ok' => false, 'message' => 'Entry not found: #' . (int) $entry_id]);

        } catch (\Throwable $e) {
            wp_send_json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    private static function parse_entries_csv(string $csv): array {
        if ($csv === '') return [];

        $parts = preg_split('/\s*,\s*/', $csv);
        if (!is_array($parts)) return [];

        $ids = [];
        foreach ($parts as $p) {
            $p = preg_replace('/\D+/', '', (string) $p);
            if ($p === '') continue;
            $ids[] = (int) $p;
        }

        $ids = array_values(array_unique($ids));
        $ids = array_slice($ids, 0, self::MAX_IDS);

        return $ids;
    }

    private static function url_for_step(int $step, string $entries_csv = ''): string {
        // Use current URL, but normalize step/entries
        $url = remove_query_arg([self::GET_STEP_KEY, self::GET_ENTRIES_KEY]);
        $url = add_query_arg(self::GET_STEP_KEY, (string) $step, $url);

        if (trim($entries_csv) !== '') {
            $url = add_query_arg(self::GET_ENTRIES_KEY, $entries_csv, $url);
        }

        return $url;
    }
}

Frm_Entries_Mass_Photo_Shortcode::init();
