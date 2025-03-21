(function($) {
    'use strict';

    $(document).ready(function() {
        // Save settings using AJAX
        $('.eq-cart-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitButton = form.find(':submit');
            
            submitButton.prop('disabled', true);
            
            $.ajax({
                url: eqCartAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eq_save_settings',
                    nonce: eqCartAdmin.nonce,
                    formData: form.serialize()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Settings saved successfully.');
                    } else {
                        alert('Error saving settings.');
                    }
                },
                error: function() {
                    alert('Error saving settings.');
                },
                complete: function() {
                    submitButton.prop('disabled', false);
                }
            });
        });
    });

})(jQuery);