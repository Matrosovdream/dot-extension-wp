// Variables
const ajaxurl = '/wp-admin/admin-ajax.php';


// Formidable Forms - Phone number validation by API
jQuery(document).on('change', '.validate-api input[type="tel"]', function () {
    validate_phone_api( jQuery(this).closest('.validate-api') );
});


jQuery(document).ready(function() {

    jQuery('.validate-force .validate-api').each(function() {
        validate_phone_api( jQuery(this) );
    })

});

function validate_phone_api( element ) {

    var value = element.find('input[type="tel"]').val();

    // Extract attached field
    var siblingClass = element.attr('class').split(' ').find(function (className) {
        return className.startsWith('sibling-');
    });
    var sibling = '#field_' + siblingClass.split('-')[1];

    // Checking the phone number by API
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'phone_validate',
            phone: value
        },
        success: function (response) {

            if (response.fields) {
                var line_type = response.fields.line_type.value;
                jQuery(sibling).val(line_type);
            } else {
                jQuery(sibling).val('');
            }

        }
    });

}


// Phone number validation by API in a shortcode
jQuery(document).ready(function($) {
    jQuery('#phone-validate-form').submit(function(e) {

        e.preventDefault();
        var phone = $('#phone').val();

        jQuery('#phone-validate-result').html('<p class="validation-process">Loading...</p>');

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'phone_validate',
                phone: phone
            },
            success: function(response) {

                if (response.error == false) {
                    var fields = response.fields;
                    var html = '<ul class="phone-validation-list">';
                    for (var key in fields) {

                        var item = fields[key];
                        if( item.value == '' ) { item.value = '-'; }
                        
                        html += '<li><strong>' + item.label + ':</strong> ' + item.value + '</li>';
                    }
                    html += '</ul>';
                    jQuery('#phone-validate-result').html(html);
                } else {
                    jQuery('#phone-validate-result').html('<p class="validation-error">' + response.message + '</p>');
                }

            }
        });

        return false;
        
    });
});
