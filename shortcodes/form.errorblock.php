<?php
add_shortcode('api-error-block', 'api_error_block_func');
function api_error_block_func() {
    if( isset($_REQUEST['error']) ) {
        return "<p class='api-error'><span style='color: #ff0000;line-height: 1.2;'>Invalid USDOT#, Please enter a valid USDOT#</span></p>";
    }
}