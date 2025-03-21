(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Manejar cambio en el selector de eventos
        $('#eq-event-select').on('change', function() {
            if ($(this).val() === 'new') {
                $('#eq-new-event-form').slideDown();
            } else {
                $('#eq-new-event-form').slideUp();
            }
        });
        
        // Inicializar datepicker para la fecha del evento
        if ($.fn.flatpickr && $('#eq-new-event-date').length) {
            $('#eq-new-event-date').flatpickr({
                dateFormat: 'Y-m-d',
                minDate: 'today'
            });
        }
        
        // Manejar envío del formulario
        $('#eq-change-event-form').on('submit', function(e) {
            e.preventDefault();
            
            const eventId = $('#eq-event-select').val();
            const formData = new FormData(this);
            
            // Añadir acción y nonce
            formData.append('action', 'eq_update_cart_event');
            formData.append('nonce', eqCartData.nonce);
            
            // Mostrar cargando
            $('.eq-update-event-button').prop('disabled', true).text('Updating...');
            
            // Enviar solicitud AJAX
            $.ajax({
                url: eqCartData.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Mostrar mensaje de éxito
                        alert(response.data.message);
                        
                        // Recargar página para reflejar cambios
                        window.location.reload();
                    } else {
                        alert(response.data || 'Error updating event');
                    }
                },
                error: function() {
                    alert('Error connecting to server');
                },
                complete: function() {
                    $('.eq-update-event-button').prop('disabled', false).text('Update Event');
                }
            });
        });
    });
    
})(jQuery);