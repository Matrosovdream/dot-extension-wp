<?php
function phone_validate_func($atts) {

    ob_start();
    ?>

    <div class="validate-phone-wrapper">

        <form id="phone-validate-form" class="phone-validate-form" action="" method="post">
            <input type="text" name="phone" id="phone" placeholder="Enter phone number" required>
            <button type="submit">Check</button>
        </form>    

        <div id="phone-validate-result" class="phone-validate-result"></div>

    </div>

    <?php
    $html = ob_get_clean();
    return $html;
}
add_shortcode('phone-validate', 'phone_validate_func');





