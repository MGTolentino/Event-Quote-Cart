jQuery(document).ready(function($) {
    // Solo ejecutar en la página del carrito
    if ($('.eq-cart-page-container').length > 0) {
        validateCartItems();
    }
    
    function validateCartItems() {
        $.ajax({
            url: eqCartData.ajaxurl,
            type: 'POST',
            data: {
                action: 'eq_validate_all_cart_items',
                nonce: eqCartData.nonce
            },
            success: function(response) {
                if (response.success && response.data.has_unavailable) {
                    showUnavailableItemsWarning(response.data.unavailable_items);
                }
            }
        });
    }
    
    function showUnavailableItemsWarning(items) {
        // Crear mensaje de advertencia
        let message = '<div class="eq-cart-validation-warning">';
        message += '<h3>Advertencia: Algunos items ya no están disponibles</h3>';
        message += '<p>Los siguientes items en tu carrito ya no están disponibles para las fechas seleccionadas:</p>';
        message += '<ul>';
        
        items.forEach(item => {
            message += `<li>${item.title} - `;
            if (item.reason === 'missing_date') {
                message += 'No tiene fecha seleccionada';
            } else {
                message += 'Ya no está disponible para la fecha seleccionada';
            }
            message += '</li>';
        });
        
        message += '</ul>';
        message += '<p>Por favor, elimina estos items o actualiza sus fechas antes de continuar.</p>';
        message += '</div>';
        
        // Insertar al principio de la página de carrito
        $('.eq-cart-page-container').prepend(message);
        
        // Opcional: deshabilitar el botón de generar cotización
        $('.eq-generate-quote-btn').prop('disabled', true)
                                  .css('opacity', '0.5')
                                  .attr('title', 'No puedes generar una cotización con items no disponibles');
    }
});