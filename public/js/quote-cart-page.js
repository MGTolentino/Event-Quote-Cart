(function($) {
    'use strict';

    class QuoteCartPage {
        constructor() {

            this.init();
            this.bindEvents();
        }

        init() {
            this.container = $('.eq-cart-page');
            this.items = this.container.find('.eq-cart-item');
        }

      bindEvents() {

        this.container.on('click', '.eq-edit-item', (e) => {
        this.handleEdit($(e.currentTarget));
    });

    this.container.on('click', '.eq-remove-item', (e) => {
        this.handleRemove($(e.currentTarget));
    });

    this.container.on('click', '.eq-generate-quote', (e) => {
        this.handleGenerateQuote();
    });

    this.container.on('click', '.eq-share-quote', (e) => {
        this.handleShare();
    });
}

        handleEdit(button) {
            const itemContainer = button.closest('.eq-cart-item');
            const itemId = itemContainer.data('item-id');

            // Mostrar loading state
            button.prop('disabled', true).text('Loading...');

            $.ajax({
                url: eqCartData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eq_get_cart_item',
                    nonce: eqCartData.nonce,
                    item_id: itemId
                },
                success: (response) => {
                    if (response.success) {
                        // Aquí vamos a abrir un modal para editar en lugar de usar el sidebar
                        this.openEditModal(response.data);
                    } else {
                        this.showNotification('Error loading item data', 'error');
                    }
                },
                error: () => {
                    this.showNotification('Error loading item data', 'error');
                },
                complete: () => {
                    button.prop('disabled', false).text('Edit');
                }
            });
        }

       openEditModal(itemData) {
    
    // Asegurar que los datos del listing estén presentes
    if (!itemData.listing_data) {
        itemData.listing_data = {
            price: 0,
            min_quantity: 1,
            max_quantity: 99,
            extras: []
        };
    }
    
    // Asegurar que los datos del formulario estén presentes
    if (!itemData.form_data) {
        itemData.form_data = {
            date: '',
            quantity: 1,
            extras: []
        };
    }
    
    // Crear un modal para editar el item
    const modalHtml = `
        <div class="eq-edit-modal">
            <div class="eq-edit-modal-content">
                <div class="eq-edit-modal-header">
                    <h3>Edit Item</h3>
                    <button type="button" class="eq-edit-modal-close">&times;</button>
                </div>
                <div class="eq-edit-modal-body">
                    <form id="eq-edit-form">
                        <input type="hidden" name="item_id" value="${itemData.item_id}">
                        <input type="hidden" name="listing_id" value="${itemData.listing_id}">
                        
                        <div class="eq-form-group">
                            <label>Date</label>
                            <input type="date" name="date" value="${itemData.form_data.date}" required readonly>
                            <small class="eq-date-info">The date is shared across all items. To change date, please remove and add items again.</small>
                        </div>
                        
                        ${(parseInt(itemData.listing_data.min_quantity) === 1 && parseInt(itemData.listing_data.max_quantity) === 1) ? `
    <!-- Hidden Quantity Input for fixed quantity -->
    <input type="hidden" 
           name="quantity" 
           value="1"
           class="eq-quantity-input">
` : `
    <div class="eq-form-group">
        <label>Quantity</label>
        <input type="number" 
               name="quantity" 
               min="${parseInt(itemData.listing_data.min_quantity) || 1}" 
               max="${parseInt(itemData.listing_data.max_quantity) || 99}" 
               value="${parseInt(itemData.form_data.quantity) || 1}" 
               required
               class="eq-quantity-input">
    </div>
`}
                        
                        ${this.renderExtrasForm(itemData.listing_data.extras, itemData.form_data.extras)}
                        
                        <!-- Añadir sección de totales -->
                        <div class="eq-price-summary">
    <div class="eq-subtotal">
        <span>Subtotal:</span>
        <span class="eq-price-value"></span>
    </div>
    <div class="eq-total-price">
        <span>Total (incl. taxes):</span>
        <span class="eq-price-value"></span>
    </div>
</div>
                        
                        <div class="eq-form-actions">
                            <button type="submit" class="eq-update-item">Update</button>
                            <button type="button" class="eq-cancel-edit">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    // Añadir al DOM y mostrar
    const $modal = $(modalHtml).appendTo('body');
    setTimeout(() => $modal.addClass('open'), 10);
    
    // Manejar cierre
    $modal.on('click', '.eq-edit-modal-close, .eq-cancel-edit', () => {
        $modal.removeClass('open');
        setTimeout(() => $modal.remove(), 300);
    });
    
    // Inicializar cálculo de totales
    this.initEditModalTotals($modal, itemData);
    
    // Manejar envío del formulario
    $modal.on('submit', '#eq-edit-form', (e) => {
        e.preventDefault();
        this.handleUpdateItem(e.target, $modal);
    });
}
		
		initEditModalTotals($modal, itemData) {
    const form = $modal.find('#eq-edit-form');
    // Cambiar el selector para usar eq-subtotal en lugar de eq-base-price
    const subtotalElement = $modal.find('.eq-subtotal .eq-price-value');
    const totalPriceElement = $modal.find('.eq-total-price .eq-price-value');
    
    // Obtener valores iniciales
    const basePrice = this.cleanPrice(itemData.listing_data.price) || 0;
    let quantity = parseInt(form.find('input[name="quantity"]').val()) || 1;
    
    // Calcular y mostrar totales iniciales
    this.calculateEditTotals(form, basePrice, quantity, subtotalElement, totalPriceElement);
    
    // Escuchar cambios en cantidad - solo si el campo visible existe
    const quantityInput = form.find('input[name="quantity"]:not([type="hidden"])');
    if (quantityInput.length) {
        $modal.on('input', 'input[name="quantity"]:not([type="hidden"])', () => {
            quantity = parseInt(form.find('input[name="quantity"]').val()) || 1;
            this.calculateEditTotals(form, basePrice, quantity, subtotalElement, totalPriceElement);
        });
    }
    
    // Escuchar cambios en checkboxes de extras
    $modal.on('change', 'input[type="checkbox"]', () => {
        this.calculateEditTotals(form, basePrice, quantity, subtotalElement, totalPriceElement);
    });
    
    // Escuchar cambios en extras variables
    $modal.on('input', 'input[type="number"][name^="extras"]', () => {
        this.calculateEditTotals(form, basePrice, quantity, subtotalElement, totalPriceElement);
    });
}

calculateEditTotals(form, basePrice, quantity, subtotalElement, totalPriceElement) {
	
    // Asegurar que basePrice sea un número limpio
    basePrice = this.cleanPrice(basePrice);
    
    // Inicializar subtotal con el precio base × cantidad
    let subtotal = basePrice * quantity;
    
    // Añadir extras con checkbox
    form.find('input[type="checkbox"]:checked').each((_, checkbox) => {
        const $checkbox = $(checkbox);
        const price = this.cleanPrice($checkbox.data('price')) || 0;
        const type = $checkbox.data('type');
        
        if (type === 'per_order' || type === 'per_booking' || type === 'per_item') {
            // Para extras por orden/booking/per_item, NO multiplicar por cantidad
            subtotal += price;
        } else {
            // Para todos los demás tipos, multiplicar por cantidad
            subtotal += price * quantity;
        }
    });
    
    // Añadir extras variables
    form.find('input[type="number"][name^="extras"]').each((_, input) => {
        const $input = $(input);
        const extraQuantity = parseInt($input.val()) || 0;
        if (extraQuantity > 0) {
            const price = this.cleanPrice($input.data('price')) || 0;
            subtotal += price * extraQuantity;
        }
    });
    
    // Calcular impuestos - asegurarse de que taxRate sea correcto
    const taxRate = (eqCartData && eqCartData.taxRate) ? parseFloat(eqCartData.taxRate) : 16;

    const taxAmount = subtotal * (taxRate / 100);
    const total = subtotal + taxAmount;
    
    // Actualizar UI
    subtotalElement.text(this.formatPrice(subtotal));
    totalPriceElement.text(this.formatPrice(total));
}
		
		cleanPrice(price) {
    if (typeof price === 'number') return price;
    if (typeof price === 'string') {
        return parseFloat(price.replace(/[^0-9.-]/g, ''));
    }
    return 0;
}

formatPrice(amount) {
    // Limpiar y asegurar que es número
    amount = this.cleanPrice(amount);
    
    // Usar valores predeterminados si las configuraciones no están disponibles
    const currency = (eqCartData && eqCartData.currency) ? eqCartData.currency : '$';
    const num_decimals = (eqCartData && eqCartData.num_decimals) ? eqCartData.num_decimals : 2;
    const thousand_sep = (eqCartData && eqCartData.thousand_sep) ? eqCartData.thousand_sep : ',';
    const decimal_sep = (eqCartData && eqCartData.decimal_sep) ? eqCartData.decimal_sep : '.';
    
    // Formatear con el número correcto de decimales
    let formattedPrice = amount.toFixed(num_decimals);
    
    // Dividir en parte entera y decimal
    let parts = formattedPrice.split('.');
    
    // Agregar separadores de miles a la parte entera
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousand_sep);
    
    // Juntar con el separador decimal correcto
    formattedPrice = parts.join(decimal_sep);
    
    // Agregar el símbolo de moneda
    return currency + formattedPrice;
}

        renderExtrasForm(availableExtras, selectedExtras) {
    if (!availableExtras || !availableExtras.length) {
        return '';
    }
    
    // Convertir los extras seleccionados a un formato más fácil de usar
    const selectedExtrasMap = {};
    if (selectedExtras && selectedExtras.length) {
        selectedExtras.forEach(extra => {
            selectedExtrasMap[extra.id] = extra;
        });
    }
    
    
    return `
        <div class="eq-form-group">
            <label>Extras</label>
            <div class="eq-extras-container">
                ${availableExtras.map(extra => {
                    const isSelected = selectedExtrasMap[extra.id] ? true : false;
                    
                    
                    if (extra.type === 'variable_quantity') {
                        const quantity = selectedExtrasMap[extra.id] ? selectedExtrasMap[extra.id].quantity : 0;
                        return `
                            <div class="eq-extra-item">
                                <label class="eq-extra-label">
                                    <span>${extra.name}</span>
                                    <input type="number" 
                                           name="extras[${extra.id}][quantity]" 
                                           min="0" 
                                           value="${quantity}"
                                           data-price="${extra.price}"
                                           data-name="${extra.name}"
                                           data-type="variable_quantity">
                                    <input type="hidden" name="extras[${extra.id}][type]" value="variable_quantity">
                                    <input type="hidden" name="extras[${extra.id}][name]" value="${extra.name}">
                                    <input type="hidden" name="extras[${extra.id}][price]" value="${extra.price}">
                                </label>
                                <span class="eq-extra-price">${extra.price_formatted}</span>
                            </div>
                        `;
                    } else {
                        return `
                            <div class="eq-extra-item">
                                <label class="eq-extra-label">
                                    <input type="checkbox" 
                                           name="extras[${extra.id}][selected]" 
                                           value="1"
                                           data-price="${extra.price}"
                                           data-name="${extra.name}"
                                           data-type="${extra.type || ''}"
                                           ${isSelected ? 'checked' : ''}>
                                    <span>${extra.name}</span>
                                    <input type="hidden" name="extras[${extra.id}][type]" value="${extra.type || ''}">
                                    <input type="hidden" name="extras[${extra.id}][name]" value="${extra.name}">
                                    <input type="hidden" name="extras[${extra.id}][price]" value="${extra.price}">
                                </label>
                                <span class="eq-extra-price">${extra.price_formatted || ''}</span>
                            </div>
                        `;
                    }
                }).join('')}
            </div>
        </div>
    `;
}

        handleUpdateItem(form, modal) {
            // Recopilar datos del formulario
            const $form = $(form);
            const itemId = $form.find('input[name="item_id"]').val();
            const listingId = $form.find('input[name="listing_id"]').val();
            const date = $form.find('input[name="date"]').val();
            const quantity = $form.find('input[name="quantity"]').val();
            
            // Preparar extras en el formato correcto
            let formattedExtras = [];
            
            // Procesar cada extra
            $form.find('.eq-extra-item').each(function() {
                const $this = $(this);
                const extraId = $this.find('input[name^="extras["]').attr('name').match(/\[([^\]]+)\]/)[1];
                const extraName = $this.find(`input[name="extras[${extraId}][name]"]`).val();
                const extraPrice = parseFloat($this.find(`input[name="extras[${extraId}][price]"]`).val());
                const extraType = $this.find(`input[name="extras[${extraId}][type]"]`).val();
                
                // Verificar si está seleccionado o tiene cantidad
                const isSelected = $this.find('input[type="checkbox"]').length ? 
                                  $this.find('input[type="checkbox"]').is(':checked') : 
                                  false;
                const extraQuantity = parseInt($this.find('input[type="number"]').val()) || 0;
                
                // Añadir extra si está seleccionado o tiene cantidad
                if (isSelected || extraQuantity > 0) {
                    formattedExtras.push({
                        id: extraId,
                        name: extraName,
                        price: extraPrice,
                        type: extraType,
                        quantity: extraQuantity || 1
                    });
                }
            });
            
            // Mostrar estado de carga
            const submitButton = $form.find('.eq-update-item');
            const originalText = submitButton.text();
            submitButton.prop('disabled', true).text('Updating...');
            
            
            // Enviar datos por AJAX
            $.ajax({
                url: eqCartData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eq_update_cart_item',
                    nonce: eqCartData.nonce,
                    item_id: itemId,
                    listing_id: listingId,
                    date: date,
                    quantity: quantity,
                    extras_data: JSON.stringify(formattedExtras)  // ¡Importante! Enviamos como JSON string
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Item updated successfully', 'success');
                        
                        // Cerrar modal
                        modal.removeClass('open');
                        
                        // Recargar la página de forma suave
                        setTimeout(() => {
                            window.location.href = window.location.href.split('#')[0];
                        }, 1000);
                    } else {
                        this.showNotification(response.data || 'Error updating item', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error:', status, error);
                    this.showNotification('Error updating item', 'error');
                },
                complete: () => {
                    submitButton.prop('disabled', false).text(originalText);
                }
            });
        }

        handleRemove(button) {
    const itemContainer = button.closest('.eq-cart-item');
    const itemId = itemContainer.data('item-id');

    if (!confirm('Are you sure you want to remove this item?')) {
        return;
    }

    button.prop('disabled', true).text('Removing...');

    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_remove_from_cart',
            nonce: eqCartData.nonce,
            item_id: itemId
        },
        success: (response) => {
            if (response.success) {
                // Animar la eliminación del item
                itemContainer.slideUp(300, () => {
                    // Remover el item del DOM
                    itemContainer.remove();
                    
                    // Verificar si quedan items
                    const remainingItems = $('.eq-cart-item').length;
                    
                    if (remainingItems === 0) {
                        // Si no quedan items, recargar la página para mostrar el estado vacío
                        location.reload();
                        return;
                    }
                    
                    // Actualizar los totales
                    this.updateCartTotals();
                });
                
                this.showNotification('Item removed successfully', 'success');
            } else {
                this.showNotification(response.data || 'Error removing item', 'error');
                button.prop('disabled', false).text('Remove');
            }
        },
        error: (xhr, status, error) => {
            console.error('Error removing item:', status, error);
            this.showNotification('Error removing item', 'error');
            button.prop('disabled', false).text('Remove');
        }
    });
}

        handleViewQuote() {
            window.location.href = eqCartData.quoteViewUrl;
        }

        handleShare() {
            // Por implementar en siguiente fase
            this.showNotification('Share functionality coming soon');
        }

        handleDownload() {
            // Por implementar en siguiente fase
            this.showNotification('Download functionality coming soon');
        }

        updateCartTotals() {
    // Mostrar indicador de carga en los totales
    $('.eq-total-row span:last-child').html('<small>Updating...</small>');
    
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_get_cart_totals',
            nonce: eqCartData.nonce
        },
        success: (response) => {
            if (response.success) {
                // Actualizar los valores de los totales (usando html para permitir símbolo $)
                $('.eq-total-row.subtotal span:last-child').html(response.data.subtotal);
                $('.eq-total-row.tax span:last-child').html(response.data.tax);
                $('.eq-total-row.total span:last-child').html(response.data.total);
                
                // Actualizar contador del header si existe
                if (typeof window.updateHeaderCartCount === 'function') {
                    window.updateHeaderCartCount(response.data.itemCount);
                }
                
            } else {
                console.error('Error updating cart totals:', response.data);
            }
        },
        error: (xhr, status, error) => {
            console.error('AJAX error when updating totals:', status, error);
        }
    });
}
		
        showNotification(message, type = 'success') {
            const notification = $(`
                <div class="eq-notification ${type}">
                    ${message}
                </div>
            `).appendTo('body');

            setTimeout(() => notification.addClass('show'), 100);
            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
		
		handleGenerateQuote() {
    // Mostrar indicador de carga
    const button = this.container.find('.eq-generate-quote');
    button.prop('disabled', true).text('Generating...');

    // Llamada AJAX para generar PDF
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_generate_quote_pdf',
            nonce: eqCartData.nonce
        },
        success: (response) => {
            if (response.success) {
                // Abrir el PDF en una nueva ventana o descargar
                window.open(response.data.pdf_url, '_blank');
                this.showNotification('Quote generated successfully', 'success');
            } else {
                this.showNotification(response.data || 'Error generating quote', 'error');
            }
        },
        error: () => {
            this.showNotification('Error generating quote', 'error');
        },
        complete: () => {
            button.prop('disabled', false).text('Generate Quote');
        }
    });
}

handleShare() {
    // Verificar si el menú de compartir ya está activo
    const existingMenu = this.container.find('.eq-share-options');
    if (existingMenu.length) {
        existingMenu.remove();
        return;
    }
    
    // Crear opciones de compartir
    const button = this.container.find('.eq-share-quote');
    const buttonPosition = button.position();
    
    const shareMenu = `
    <div class="eq-share-options">
        <button class="eq-share-email">
            <i class="fa fa-envelope"></i> Email
        </button>
        <button class="eq-share-whatsapp">
            <i class="fa fa-whatsapp"></i> WhatsApp
        </button>
    </div>
`;
    
    const $menu = $(shareMenu).appendTo(this.container);
    $menu.css({
        top: buttonPosition.top + button.outerHeight() + 5,
        left: buttonPosition.left
    });
    
    // Manejar clicks fuera del menú
    $(document).on('click', (event) => {
        if (!$(event.target).closest('.eq-share-options, .eq-share-quote').length) {
            $menu.remove();
            $(document).off('click');
        }
    });
    
    // Manejar opción de email
    $menu.on('click', '.eq-share-email', () => {
        this.handleShareByEmail();
        $menu.remove();
    });
    
    // Manejar opción de WhatsApp
    $menu.on('click', '.eq-share-whatsapp', () => {
        this.handleShareByWhatsApp();
        $menu.remove();
    });
}

handleShareByEmail() {
    // Primero obtener el email del lead
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_get_lead_email',
            nonce: eqCartData.nonce
        },
        success: (response) => {
            const leadEmail = response.success ? response.data.email : '';
            
            // Mostrar modal con email pre-rellenado
            const modalHtml = `
                <div class="eq-share-modal">
                    <div class="eq-share-modal-content">
                        <div class="eq-share-modal-header">
                            <h3>Compartir Cotización por Email</h3>
                            <button type="button" class="eq-share-modal-close">&times;</button>
                        </div>
                        <div class="eq-share-modal-body">
                            <form id="eq-share-email-form">
                                <div class="eq-form-group">
                                    <label for="share-email">Dirección de Email</label>
                                    <input type="email" id="share-email" name="email" 
                                        value="${leadEmail}" 
                                        required placeholder="Ingrese dirección de email">
                                </div>
                                <div class="eq-form-actions">
                                    <button type="submit" class="eq-send-email">Enviar Email</button>
                                    <button type="button" class="eq-cancel-share">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            const $modal = $(modalHtml).appendTo('body');
            setTimeout(() => $modal.addClass('open'), 10);
            
            // Manejar cierre del modal
            $modal.on('click', '.eq-share-modal-close, .eq-cancel-share', () => {
                $modal.removeClass('open');
                setTimeout(() => $modal.remove(), 300);
            });
            
            // Manejar envío del formulario
            $modal.on('submit', '#eq-share-email-form', (e) => {
                e.preventDefault();
                
                const email = $modal.find('#share-email').val();
                const submitButton = $modal.find('.eq-send-email');
                submitButton.prop('disabled', true).text('Sending...');
                
                $.ajax({
                    url: eqCartData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'eq_send_quote_email',
                        nonce: eqCartData.nonce,
                        email: email
                    },
                    success: (response) => {
                        if (response.success) {
                            this.showNotification('Quote sent successfully', 'success');
                            $modal.removeClass('open');
                            setTimeout(() => $modal.remove(), 300);
                        } else {
                            this.showNotification(response.data || 'Error sending email', 'error');
                            submitButton.prop('disabled', false).text('Send Email');
                        }
                    },
                    error: () => {
                        this.showNotification('Error sending email', 'error');
                        submitButton.prop('disabled', false).text('Send Email');
                    }
                });
            });
        },
        error: () => {
            // Mostrar modal sin email pre-rellenado en caso de error
            // (similar al código anterior pero sin valor en el input)
            this.showNotification('Error al obtener el email del cliente', 'error');
            // Aquí puedes incluir un código similar al de arriba pero sin pre-rellenar el email
        }
    });
}

handleShareByWhatsApp() {
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_generate_whatsapp_link',
            nonce: eqCartData.nonce
        },
        success: (response) => {
            if (response.success) {
                // Mostrar modal de confirmación
                const modalHtml = `
                    <div class="eq-share-modal">
                        <div class="eq-share-modal-content">
                            <div class="eq-share-modal-header">
                                <h3>Compartir por WhatsApp</h3>
                                <button type="button" class="eq-share-modal-close">&times;</button>
                            </div>
                            <div class="eq-share-modal-body">
                                <form id="eq-share-whatsapp-form">
                                    <div class="eq-form-group">
                                        <label for="share-phone">Número de teléfono</label>
                                        <input type="tel" id="share-phone" name="phone" 
                                            value="${response.data.lead_phone || ''}" 
                                            placeholder="Ingrese número de teléfono" required>
                                    </div>
                                    <div class="eq-form-group">
                                        <label>Mensaje:</label>
                                        <div class="eq-message-preview">${response.data.message}</div>
                                    </div>
                                    <div class="eq-form-actions">
                                        <button type="submit" class="eq-send-whatsapp">Enviar por WhatsApp</button>
                                        <button type="button" class="eq-cancel-share">Cancelar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                `;
                
                const $modal = $(modalHtml).appendTo('body');
                setTimeout(() => $modal.addClass('open'), 10);
                
                // Manejar cierre del modal
                $modal.on('click', '.eq-share-modal-close, .eq-cancel-share', () => {
                    $modal.removeClass('open');
                    setTimeout(() => $modal.remove(), 300);
                });
                
                // Manejar envío del formulario
                $modal.on('submit', '#eq-share-whatsapp-form', (e) => {
                    e.preventDefault();
                    
                    const phone = $modal.find('#share-phone').val().replace(/[^0-9]/g, '');
                    const whatsappLink = 'https://wa.me/' + (phone ? phone : '') + 
                                        '?text=' + encodeURIComponent(response.data.message);
                    
                    // Abrir WhatsApp en nueva ventana
                    window.open(whatsappLink, '_blank');
                    
                    // Cerrar modal
                    $modal.removeClass('open');
                    setTimeout(() => $modal.remove(), 300);
                });
            } else {
                this.showNotification(response.data || 'Error al generar enlace de WhatsApp', 'error');
            }
        },
        error: () => {
            this.showNotification('Error al generar enlace de WhatsApp', 'error');
        }
    });
}
    }

    // Inicializar cuando el documento esté listo
    $(document).ready(() => {
        if ($('.eq-cart-page').length) {
            new QuoteCartPage();
        }
    });

})(jQuery);