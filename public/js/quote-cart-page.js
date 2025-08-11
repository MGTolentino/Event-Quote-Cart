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
            this.initSortable();
            this.initDiscounts();
        }
        
        initSortable() {
            const $itemsContainer = this.container.find('.eq-cart-items');
            
            // Hacer los items arrastrables
            $itemsContainer.sortable({
                items: '.eq-cart-item',
                handle: '.eq-item-drag-handle',
                placeholder: 'eq-sortable-placeholder',
                axis: 'y',
                opacity: 0.8,
                cursor: 'move',
                start: function(event, ui) {
                    // Crear placeholder con la misma altura
                    ui.placeholder.height(ui.item.height());
                },
                update: (event, ui) => {
                    // Guardar el nuevo orden para el PDF
                    this.updateItemOrder();
                }
            });
        }
        
        updateItemOrder() {
            const itemOrder = [];
            $('.eq-cart-item').each(function(index) {
                const itemId = $(this).data('item-id');
                itemOrder.push({
                    id: itemId,
                    order: index
                });
            });
            
            // Guardar el orden en una variable para usarlo al generar el PDF
            this.itemOrder = itemOrder;
        }
        
        initDiscounts() {
            // Limpiar todos los inputs de descuento para evitar persistencia del navegador
            $('.eq-item-discount-value').val('');
            $('#eq-global-discount-value').val('');
            $('.eq-item-discount-type').val('fixed');
            $('#eq-global-discount-type').val('fixed');
            
            // Ocultar precios descontados
            $('.eq-discounted-price').hide();
            $('.eq-original-price').css('text-decoration', 'none');
            
            // Calcular totales correctos iniciales
            this.calculateDiscounts();
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
    
    // Eventos para descuentos
    this.container.on('input change', '.eq-item-discount-value, .eq-item-discount-type', (e) => {
        this.calculateDiscounts();
    });
    
    this.container.on('input change', '#eq-global-discount-value, #eq-global-discount-type', (e) => {
        this.calculateDiscounts();
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
                            ${itemData.is_date_range ? `
                                <div class="eq-date-range-display">
                                    <span>${itemData.start_date} to ${itemData.end_date}</span>
                                    ${itemData.days_count ? ` (${itemData.days_count} days)` : ''}
                                </div>
                                <input type="hidden" name="date" value="${itemData.form_data.date}">
                            ` : `
                                <input type="date" name="date" value="${itemData.form_data.date}" required readonly>
                            `}
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
    
    // Formatear con el número correcto de decimales usando redondeo matemático estándar
    let formattedPrice = (Math.round(amount * 100) / 100).toFixed(num_decimals);
    
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
            this.showNotification('Error removing item', 'error');
            button.prop('disabled', false).text('Remove');
        }
    });
}

        handleViewQuote() {
            window.location.href = eqCartData.quoteViewUrl;
        }
        
        calculateDiscounts() {
            let totalWithTax = 0;
            let subtotalWithoutTax = 0;
            let totalItemDiscounts = 0;
            let globalDiscountAmount = 0;
            const taxRate = parseFloat(eqCartData.taxRate) || 16;
            const taxMultiplier = 1 + (taxRate / 100);
            
            // Guardar referencia a this para usar dentro del each
            const instance = this;
            
            // Calcular subtotal sin impuestos y descuentos por item
            $('.eq-cart-item').each(function() {
                const $item = $(this);
                const itemId = $item.data('item-id');
                const priceText = $item.find('.eq-original-price').text();
                const itemPriceWithTax = parseFloat(priceText.replace(/[^0-9.-]+/g, ''));
                
                if (!isNaN(itemPriceWithTax)) {
                    // Calcular precio sin impuestos
                    const itemPriceWithoutTax = itemPriceWithTax / taxMultiplier;
                    
                    totalWithTax += itemPriceWithTax;
                    subtotalWithoutTax += itemPriceWithoutTax;
                    
                    // Calcular descuento del item (aplicado al precio sin impuestos)
                    const discountValue = parseFloat($item.find('.eq-item-discount-value').val()) || 0;
                    const discountType = $item.find('.eq-item-discount-type').val();
                    
                    let itemDiscount = 0;
                    if (discountValue > 0) {
                        if (discountType === 'percentage') {
                            itemDiscount = itemPriceWithoutTax * (discountValue / 100);
                        } else {
                            // Para descuento fijo, aplicarlo directamente (asumiendo que es sin impuestos)
                            itemDiscount = Math.min(discountValue, itemPriceWithoutTax);
                        }
                    }
                    
                    totalItemDiscounts += itemDiscount;
                    
                    // Mostrar precio con descuento si aplica (con impuestos para mostrar)
                    if (itemDiscount > 0) {
                        const discountedPriceWithoutTax = itemPriceWithoutTax - itemDiscount;
                        const discountedPriceWithTax = discountedPriceWithoutTax * taxMultiplier;
                        $item.find('.eq-discounted-price')
                            .text(instance.formatPrice(discountedPriceWithTax))
                            .show();
                        $item.find('.eq-original-price').css('text-decoration', 'line-through');
                    } else {
                        $item.find('.eq-discounted-price').hide();
                        $item.find('.eq-original-price').css('text-decoration', 'none');
                    }
                }
            });
            
            // Calcular descuento global (aplicado al subtotal sin impuestos después de descuentos individuales)
            const globalDiscountValue = parseFloat($('#eq-global-discount-value').val()) || 0;
            const globalDiscountType = $('#eq-global-discount-type').val();
            
            if (globalDiscountValue > 0) {
                const subtotalAfterItemDiscounts = subtotalWithoutTax - totalItemDiscounts;
                if (globalDiscountType === 'percentage') {
                    globalDiscountAmount = subtotalAfterItemDiscounts * (globalDiscountValue / 100);
                } else {
                    globalDiscountAmount = Math.min(globalDiscountValue, subtotalAfterItemDiscounts);
                }
            }
            
            // Calcular totales finales
            const finalSubtotal = subtotalWithoutTax - totalItemDiscounts - globalDiscountAmount;
            const tax = finalSubtotal * (taxRate / 100);
            const total = finalSubtotal + tax;
            
            // Actualizar valores en pantalla - NUEVO DISEÑO
            // Si hay descuentos, mostrar el subtotal original y luego el descontado
            if (totalItemDiscounts > 0 || globalDiscountAmount > 0) {
                // Mostrar subtotal con descuentos aplicados como el principal
                $('.eq-subtotal-amount').text(instance.formatPrice(finalSubtotal));
                
                // Mostrar descuentos aplicados
                if (totalItemDiscounts > 0) {
                    $('.eq-item-discounts-amount').text('-' + instance.formatPrice(totalItemDiscounts));
                    $('.item-discounts').show();
                } else {
                    $('.item-discounts').hide();
                }
                
                if (globalDiscountAmount > 0) {
                    $('.eq-global-discount-amount').text('-' + instance.formatPrice(globalDiscountAmount));
                } else {
                    $('.eq-global-discount-amount').text(instance.formatPrice(0));
                }
                
                // Ocultar la línea redundante "Subtotal after Discounts"
                $('.subtotal-after-discounts').hide();
                
            } else {
                // Sin descuentos, mostrar subtotal normal
                $('.eq-subtotal-amount').text(instance.formatPrice(subtotalWithoutTax));
                $('.eq-global-discount-amount').text(instance.formatPrice(0));
                $('.item-discounts').hide();
                $('.subtotal-after-discounts').hide();
            }
            
            $('.eq-tax-amount').text(instance.formatPrice(tax));
            $('.eq-total-amount').text(instance.formatPrice(total));
            
            // Guardar descuentos en datos para el PDF (ahora con valores correctos)
            this.discountData = {
                itemDiscounts: {},
                globalDiscount: {
                    value: globalDiscountValue,
                    type: globalDiscountType,
                    amount: globalDiscountAmount
                },
                totalItemDiscounts: totalItemDiscounts,
                subtotalWithoutTax: subtotalWithoutTax,
                taxRate: taxRate
            };
            
            // Guardar descuentos individuales con sus montos calculados
            $('.eq-cart-item').each((index, element) => {
                const $item = $(element);
                const itemId = $item.data('item-id');
                const discountValue = parseFloat($item.find('.eq-item-discount-value').val()) || 0;
                const discountType = $item.find('.eq-item-discount-type').val();
                
                if (discountValue > 0) {
                    const priceText = $item.find('.eq-original-price').text();
                    const itemPriceWithTax = parseFloat(priceText.replace(/[^0-9.-]+/g, ''));
                    const itemPriceWithoutTax = itemPriceWithTax / taxMultiplier;
                    
                    let itemDiscountAmount = 0;
                    if (discountType === 'percentage') {
                        itemDiscountAmount = itemPriceWithoutTax * (discountValue / 100);
                    } else {
                        itemDiscountAmount = Math.min(discountValue, itemPriceWithoutTax);
                    }
                    
                    instance.discountData.itemDiscounts[itemId] = {
                        value: discountValue,
                        type: discountType,
                        amount: itemDiscountAmount
                    };
                }
            });
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
                // Error updating cart totals
            }
        },
        error: (xhr, status, error) => {
            // AJAX error when updating totals
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
    // Calcular descuentos antes de generar el PDF
    this.calculateDiscounts();
    
    // Mostrar indicador de carga
    const button = this.container.find('.eq-generate-quote');
    button.prop('disabled', true).text('Generating...');

    // Llamada AJAX para generar PDF
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_generate_quote_pdf',
            nonce: eqCartData.nonce,
            discounts: JSON.stringify(this.discountData || {}),
            itemOrder: JSON.stringify(this.itemOrder || [])
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
    const buttonOffset = button.offset();
    
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
    
    const $menu = $(shareMenu).appendTo('body');
    
    // Calcular posición óptima
    const menuWidth = 150;
    const menuHeight = 80; // aprox
    const windowWidth = $(window).width();
    const windowHeight = $(window).height();
    
    let top = buttonOffset.top + button.outerHeight() + 5;
    let left = buttonOffset.left;
    
    // Ajustar si se sale de la pantalla horizontalmente
    if (left + menuWidth > windowWidth) {
        left = buttonOffset.left + button.outerWidth() - menuWidth;
    }
    
    // Ajustar si se sale de la pantalla verticalmente
    if (top + menuHeight > windowHeight) {
        top = buttonOffset.top - menuHeight - 5;
    }
    
    $menu.css({ top, left });
    
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
    // Obtener email del lead y template del vendor
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_get_email_template',
            nonce: eqCartData.nonce
        },
        success: (response) => {
            const data = response.success ? response.data : {};
            const leadEmail = data.email || '';
            const emailTemplate = data.template || '';
            
            // Mostrar modal con email y mensaje editables
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
                                <div class="eq-form-group">
                                    <label for="share-email-message">Mensaje</label>
                                    <textarea id="share-email-message" name="message" rows="6" 
                                        required placeholder="Ingrese mensaje para el email">${emailTemplate}</textarea>
                                    <small>Puede usar: {customer_name}, {quote_number}, {vendor_name}, {product_name}</small>
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
                const message = $modal.find('#share-email-message').val();
                const submitButton = $modal.find('.eq-send-email');
                submitButton.prop('disabled', true).text('Sending...');
                
                $.ajax({
                    url: eqCartData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'eq_send_quote_email',
                        nonce: eqCartData.nonce,
                        email: email,
                        custom_message: message
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
            this.showNotification('Error al obtener template de email', 'error');
        }
    });
}

handleShareByWhatsApp() {
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_get_whatsapp_template',
            nonce: eqCartData.nonce
        },
        success: (response) => {
            if (response.success) {
                const data = response.data;
                
                // Mostrar modal con teléfono y mensaje editables
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
                                            value="${data.lead_phone || ''}" 
                                            placeholder="Ingrese número de teléfono" required>
                                    </div>
                                    <div class="eq-form-group">
                                        <label for="share-whatsapp-message">Mensaje</label>
                                        <textarea id="share-whatsapp-message" name="message" rows="4" 
                                            required placeholder="Ingrese mensaje para WhatsApp">${data.message}</textarea>
                                        <small>Puede usar: {customer_name}, {quote_number}, {vendor_name}</small>
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
                    const message = $modal.find('#share-whatsapp-message').val();
                    const whatsappLink = 'https://wa.me/' + (phone ? phone : '') + 
                                        '?text=' + encodeURIComponent(message);
                    
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