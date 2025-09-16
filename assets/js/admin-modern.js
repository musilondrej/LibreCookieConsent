jQuery(document).ready(function($) {
    $('input[name*="gtm_id"], input[name*="ga4_id"], input[name*="meta_pixel_id"], input[name*="clarity_id"]').on('blur', function() {
        var $input = $(this);
        var value = $input.val().trim();
        
        $input.siblings('.inline-notice').remove();
        
        if ($input.attr('name').includes('gtm_id') && value && !value.match(/^GTM-[A-Z0-9]{7}$/)) {
            $input.after('<div class="inline-notice notice-error"><p>Formát: GTM-XXXXXXX</p></div>');
        } else if ($input.attr('name').includes('ga4_id') && value && !value.match(/^G-[A-Z0-9]{10}$/)) {
            $input.after('<div class="inline-notice notice-error"><p>Formát: G-XXXXXXXXXX</p></div>');
        } else if ($input.attr('name').includes('meta_pixel_id') && value && !value.match(/^[0-9]{15,16}$/)) {
            $input.after('<div class="inline-notice notice-error"><p>Formát: 15-16 číslic</p></div>');
        }
    });
    
    var saveNotificationShown = false;
    $('form input, form textarea, form select').on('change', function() {
        if (!saveNotificationShown) {
            $('.wrap h1').after('<div class="notice notice-info is-dismissible"><p><strong>Pozor:</strong> Nezapomeňte uložit změny.</p></div>');
            saveNotificationShown = true;
        }
    });
    
});
