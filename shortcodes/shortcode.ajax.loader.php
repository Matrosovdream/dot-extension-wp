<?php

/**
 * Universal AJAX wrapper shortcode
 *
 * Usage:
 * [shortcode-ajax cache="0"]
 *   [frm-stats id=148 type=count 7="Verified"]
 * [/shortcode-ajax]
 *
 * Optional:
 * [shortcode-ajax cache="60" loader="<span>Loading...</span>" class="my-class"]...[/shortcode-ajax]
 */
final class Shortcode_Ajax_Wrapper {

    private const AJAX_ACTION = 'shortcode_ajax_wrapper_render';

    public static function init(): void {
        add_shortcode('shortcode-ajax', [__CLASS__, 'shortcode']);

        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajax_render']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'ajax_render']);

        // Inline CSS (so you don't need to enqueue anything)
        add_action('wp_head', [__CLASS__, 'print_css'], 20);
    }

    /**
     * Small styled loader (spinner)
     */
    public static function print_css(): void {
        ?>
        <style id="shortcode-ajax-wrapper-css">
            .shortcode-ajax-spinner{
                display:inline-block;
                width:14px;
                height:14px;
                vertical-align:middle;
                border:2px solid rgba(0,0,0,.2);
                border-top-color: rgba(0,0,0,.6);
                border-radius:50%;
                animation: scajax-spin .6s linear infinite;
            }
            @keyframes scajax-spin{ to{ transform: rotate(360deg); } }

            .shortcode-ajax-error{
                display:inline-block;
                padding:2px 6px;
                font-size:12px;
                border-radius:6px;
                background: rgba(255,0,0,.08);
                border:1px solid rgba(255,0,0,.25);
            }
        </style>
        <?php
    }

    /**
     * Shortcode handler
     */
    public static function shortcode($atts, $content = null): string {
        $atts = shortcode_atts([
            'cache'  => '60', // seconds; 0 = no cache
            'loader' => '<span class="shortcode-ajax-spinner" aria-label="Loading"></span>',
            'class'  => '',
        ], $atts, 'shortcode-ajax');

        $content = trim((string) $content);
        if ($content === '') {
            return '<span class="shortcode-ajax-error">Empty shortcode content.</span>';
        }

        $cache = max(0, (int) $atts['cache']);

        // Unique instance id (separate per shortcode content + cache + logged-in state)
        $hash   = substr(sha1($content . '|' . $cache . '|' . (is_user_logged_in() ? '1' : '0')), 0, 12);
        $dom_id = 'scajax_' . $hash . '_' . wp_rand(1000, 9999);

        $ajax_url    = admin_url('admin-ajax.php');
        $content_js  = wp_json_encode($content);

        $extra_class = trim((string) $atts['class']);
        $extra_class = $extra_class !== '' ? ' ' . esc_attr($extra_class) : '';

        // Placeholder
        $out  = '<span id="' . esc_attr($dom_id) . '" class="shortcode-ajax-wrap' . $extra_class . '"';
        $out .= ' data-scajax-cache="' . esc_attr((string)$cache) . '">';
        $out .= (string) $atts['loader']; // allow HTML loader
        $out .= '</span>';

        // Inline JS (no dependencies)
        $out .= '<script>(function(){';
        $out .= 'var el=document.getElementById(' . wp_json_encode($dom_id) . '); if(!el) return;';
        $out .= 'var xhr=new XMLHttpRequest();';
        $out .= 'xhr.open("POST",' . wp_json_encode($ajax_url) . ',true);';
        $out .= 'xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded; charset=UTF-8");';
        $out .= 'xhr.onreadystatechange=function(){ if(xhr.readyState!==4) return;';
        $out .= ' if(xhr.status>=200 && xhr.status<300){';
        $out .= '  try{ var r=JSON.parse(xhr.responseText);';
        $out .= '   if(r && r.ok){ el.innerHTML=r.html; }';
        $out .= '   else{ el.innerHTML="<span class=\\"shortcode-ajax-error\\">Error: "+(r && r.error ? r.error : "unknown")+"</span>"; }';
        $out .= '  }catch(e){ el.innerHTML="<span class=\\"shortcode-ajax-error\\">Bad response</span>"; }';
        $out .= ' } else { el.innerHTML="<span class=\\"shortcode-ajax-error\\">HTTP "+xhr.status+"</span>"; }';
        $out .= '};';
        $out .= 'var body="action=' . rawurlencode(self::AJAX_ACTION) . '"';
        $out .= ' + "&cache=" + encodeURIComponent(el.getAttribute("data-scajax-cache")||"0")';
        $out .= ' + "&content=" + encodeURIComponent(' . $content_js . ');';
        $out .= 'xhr.send(body);';
        $out .= '})();</script>';

        return $out;
    }

    /**
     * AJAX renderer
     */
    public static function ajax_render(): void {
        $content = isset($_POST['content']) ? (string) wp_unslash($_POST['content']) : '';
        $content = trim($content);

        if ($content === '') {
            wp_send_json(['ok' => false, 'error' => 'empty_content']);
        }

        $cache = isset($_POST['cache']) ? max(0, (int) $_POST['cache']) : 0;

        $cache_key = 'scajax_' . md5($content . '|' . $cache . '|' . (is_user_logged_in() ? '1' : '0'));

        if ($cache > 0) {
            $cached = get_transient($cache_key);
            if (is_string($cached)) {
                wp_send_json(['ok' => true, 'html' => $cached, 'cached' => true]);
            }
        }

        // Render nested shortcodes
        $html = do_shortcode($content);
        if ($html === null) { $html = ''; }

        if ($cache > 0) {
            set_transient($cache_key, (string) $html, $cache);
        }

        wp_send_json(['ok' => true, 'html' => (string) $html, 'cached' => false]);
    }
}

add_action('init', ['Shortcode_Ajax_Wrapper', 'init']);
