(function($) {

	'use strict';

    class QuoteCart {
      constructor() {
    this.init();
    this.bindEvents();
    this.bindDateEvents();
    
    // Añadir esto:
    this.bindContextEvents();
}

init() {
    // Estado inicial
    this.state = {
        isOpen: false,
        items: [],
        currentItem: null,
        selectedDate: localStorage.getItem('eq_selected_date') || null,
        dateInitialized: false
    };

    // Referencias DOM
    this.sidebar = $('.eq-sidebar');
    this.content = this.sidebar.find('.eq-sidebar-content');
    this.footer = this.sidebar.find('.eq-sidebar-footer');
    this.headerCart = $('.eq-header-cart');

    // Obtener items existentes solo si el usuario ha iniciado sesión
    if (eqCartData.userLoggedIn) {
        this.loadCartItems();
    } else {
        // Ocultar o desactivar elementos para usuarios no logueados
        this.headerCart.addClass('eq-hide-cart');
    }
    
    // Asegurar que la fecha se muestre correctamente en la inicialización
    if (this.state.selectedDate) {
        // Usar timeout para asegurar que el DOM esté listo
        setTimeout(() => {
            this.updateDateDisplay(this.state.selectedDate);
        }, 100);
    }
}
		
bindContextEvents() {
    // Escuchar cambios en el contexto
    $(document).on('eqContextChanged', (e, contextData) => {
        
        // Actualizar estado interno si es necesario
        if (contextData && contextData.eventDate) {
            this.state.selectedDate = contextData.eventDate;
            
            // Almacenar en localStorage para persistencia
            localStorage.setItem('eq_selected_date', contextData.eventDate);
            
            // Actualizar la visualización de la fecha
            this.updateDateDisplay(contextData.eventDate);
        }
        
        // Si estamos en una página de carrito, recargar para mostrar el nuevo contexto
        if (window.location.href.indexOf('quote-cart') > -1) {
            // Recargar los items del carrito con el nuevo contexto
            this.loadCartItems();
        }
    });

    // Verificar si el panel de contexto está activo al cargar la página
    this.checkContextPanelState();
    
    // Escuchar eventos de activación/desactivación del panel de contexto
    $(document).on('eqContextPanelActivated', () => {
        $('body').addClass('has-eq-context-panel');
    });
    
    $(document).on('eqContextPanelDeactivated', () => {
        $('body').removeClass('has-eq-context-panel');
    });
    
    // Escuchar eventos de minimización/maximización
    $(document).on('eqContextPanelMinimized', () => {
        $('body').removeClass('has-eq-context-panel').addClass('has-eq-context-minimized');
    });
    
    $(document).on('eqContextPanelMaximized', () => {
        $('body').addClass('has-eq-context-panel').removeClass('has-eq-context-minimized');
    });
    
    // Escuchar clicks en el botón de toggle del panel
    $(document).on('click', '.eq-context-panel-button.toggle-panel', () => {
        if ($('.eq-context-panel').hasClass('minimized')) {
            // Si está minimizado, maximizarlo
            $('.eq-context-panel').removeClass('minimized');
            $('body').addClass('has-eq-context-panel').removeClass('has-eq-context-minimized');
        } else {
            // Si está maximizado, minimizarlo
            $('.eq-context-panel').addClass('minimized');
            $('body').removeClass('has-eq-context-panel').addClass('has-eq-context-minimized');
        }
    });
}

// Método para verificar el estado inicial del panel de contexto
checkContextPanelState() {
    // Verificar si el panel de contexto existe y está visible
    if ($('.eq-context-panel').length && !$('.eq-context-panel').hasClass('minimized')) {
        $('body').addClass('has-eq-context-panel');
    } else if ($('.eq-context-panel').length && $('.eq-context-panel').hasClass('minimized')) {
        $('body').addClass('has-eq-context-minimized');
    }
}

updateDateDisplay(dateString) {
    try {
        let dateObj;
        
        // Manejar diferentes formatos de fecha
        if (!dateString) {
            return;
        }
        
        // Si es un timestamp numérico (Unix timestamp)
        if (typeof dateString === 'number' || (typeof dateString === 'string' && /^\d+$/.test(dateString))) {
            const timestamp = parseInt(dateString);
            // Si es timestamp en segundos (10 dígitos)
            if (timestamp.toString().length === 10) {
                dateObj = new Date(timestamp * 1000);
            }
            // Si es timestamp en milisegundos (13 dígitos)
            else if (timestamp.toString().length === 13) {
                dateObj = new Date(timestamp);
            }
            else {
                dateObj = new Date(dateString);
            }
        } else {
            // Es una fecha en formato string
            dateObj = new Date(dateString);
        }
        
        if (isNaN(dateObj.getTime())) {
            $('.eq-date-value').text('Invalid date');
            return;
        }
        
        // Formatear la fecha para mostrar
        const formattedDate = dateObj.toLocaleDateString();
        
        // Actualizar todos los campos de fecha en la página
        $('.eq-date-value').text(formattedDate);
        $('.eq-date-display').addClass('has-date');
        
        // Si hay un datepicker flatpickr, actualizarlo también
        const datePicker = $('.eq-date-picker');
        if (datePicker.length && datePicker[0]._flatpickr) {
            // Para flatpickr, usar formato Y-m-d
            const formattedForPicker = dateObj.getFullYear() + '-' + 
                String(dateObj.getMonth() + 1).padStart(2, '0') + '-' + 
                String(dateObj.getDate()).padStart(2, '0');
            datePicker[0]._flatpickr.setDate(formattedForPicker);
        }
        
    } catch (e) {
        $('.eq-date-value').text('Error displaying date');
    }
}
		
		bindDateEvents() {
    // 1. Evento para cambios desde el context panel (máxima prioridad)
    $(document).on('eqContextDateChanged', (e, date) => {
        this.state.selectedDate = date;
        localStorage.setItem('eq_selected_date', date);
        
        // Actualizar la visualización de la fecha
        this.updateDateDisplay(date);
        
        // Actualizar datepicker si existe
        const dateInput = this.content.find('.eq-date-picker');
        if (dateInput.length && dateInput[0]._flatpickr) {
            dateInput[0]._flatpickr.setDate(date);
        }
        
        // No validar contra el carrito, ya que esta fecha tiene prioridad
    });
    
    // 2. Evento para cambios desde otros componentes (segunda prioridad)
    $(document).on('eqDateChanged', (e, date, options) => {
        // Ignorar si el usuario es admin/ventas y hay un contexto activo
        if (eqCartData.hasContextPanel && eqCartData.isPrivilegedUser) {
            return;
        }
        
        this.state.selectedDate = date;
        
        // Actualizar la visualización de la fecha
        this.updateDateDisplay(date);
        
        // Update datepicker if it exists
        const dateInput = this.content.find('.eq-date-picker');
        if (dateInput.length && dateInput[0]._flatpickr) {
            dateInput[0]._flatpickr.setDate(date);
        }

        // Validate items in cart if any
        if (this.state.items.length > 0) {
            this.validateCartItems(date);
        }
    });
    
    // 3. Evento para cambios desde el filtro del cotizador (tercera prioridad)
    $(document).on('eqFilterDateApplied', (e, date) => {
        // Ignorar si el usuario es admin/ventas y hay un contexto activo
        if (eqCartData.hasContextPanel && eqCartData.isPrivilegedUser) {
            return;
        }
        
        this.state.selectedDate = date;
        
        // Actualizar la visualización de la fecha
        this.updateDateDisplay(date);
        
        // Update datepicker if it exists
        const dateInput = this.content.find('.eq-date-picker');
        if (dateInput.length && dateInput[0]._flatpickr) {
            dateInput[0]._flatpickr.setDate(date);
        }
        
        // Validate items in cart if any
        if (this.state.items.length > 0) {
            this.validateCartItems(date);
        }
    });

    // Escuchar limpieza de filtros
    $('#limpiar-filtros').on('click', () => {
        // Ignorar si el usuario es admin/ventas y hay un contexto activo
        if (eqCartData.hasContextPanel && eqCartData.isPrivilegedUser) {
            return;
        }
        
        this.state.selectedDate = null;
        
        const dateInput = this.content.find('.eq-date-picker');
        if (dateInput.length && dateInput[0]._flatpickr) {
            dateInput[0]._flatpickr.clear();
        }
    });
}
		
	handleFormSubmit(e, form) {
   if (e) {
       e.preventDefault();
       e.stopPropagation();
   }

   form = form || (e && e.target);
   if (!form) {
       return;
   }

   const formData = new FormData(form);
   const listingId = formData.get('listing_id');
   const selectedDate = formData.get('_dates');
   
   // Validaciones básicas
   if (!selectedDate) {
       alert(eqCartData.i18n.dateRequired);
       return;
   }

   // Mostrar estado de carga
   const submitButton = $(form).find('.eq-include-button');
   const originalText = submitButton.text();
   submitButton.prop('disabled', true).text(eqCartData.i18n.adding);

   // Verificar fecha primero
   $.ajax({
       url: eqCartData.ajaxurl,
       type: 'POST',
       data: {
           action: 'eq_validate_cart_date_change',
           nonce: eqCartData.nonce,
           date: selectedDate
       },
       success: (validateResponse) => {
           if (validateResponse.success) {
              // Si hay items en el carrito
if (validateResponse.data.hasItems) {
    // Verificar si hay items no disponibles
    if (validateResponse.data.unavailableItems.length > 0) {
        const confirmMessage = `Esta fecha afecta a ${validateResponse.data.unavailableItems.length} item(s) en tu carrito que no están disponibles en esta fecha:\n\n${validateResponse.data.unavailableItems.map(item => item.title).join('\n')}\n\n¿Quieres eliminar estos items y actualizar la fecha para el resto?`;
        
        if (confirm(confirmMessage)) {
            // Actualizar fecha y eliminar items no disponibles
            $.ajax({
                url: eqCartData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eq_update_cart_date',
                    nonce: eqCartData.nonce,
                    date: selectedDate,
                    remove_unavailable: 'true',
                    unavailable_ids: validateResponse.data.unavailableItems.map(item => item.id)
                },
                success: () => {
                    // Proceder con añadir al carrito
                    this.proceedWithAddToCart(form, submitButton, originalText);
                },
                error: () => {
                    submitButton.prop('disabled', false).text(originalText);
                    alert('Error updating cart date');
                }
            });
        } else {
            // Cancelar la operación
            submitButton.prop('disabled', false).text(originalText);
        }
    } 
    // Verificar si todas las fechas son iguales usando la nueva propiedad
    else if (validateResponse.data.allSameDate) {
        // Si todas las fechas son iguales, proceder sin confirmar
        this.proceedWithAddToCart(form, submitButton, originalText);
    }
    else {
        // Si todos los items están disponibles pero con fecha diferente, preguntar si actualizar todo
        const confirmMessage = `Ya tienes ${validateResponse.data.itemCount} item(s) en tu carrito con una fecha diferente. ¿Quieres actualizar todos a la fecha ${selectedDate}?`;
        
        if (confirm(confirmMessage)) {
            // Actualizar fecha para todos
            $.ajax({
                url: eqCartData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'eq_update_cart_date',
                    nonce: eqCartData.nonce,
                    date: selectedDate,
                    remove_unavailable: 'false',
                    unavailable_ids: []
                },
                success: () => {
                    // Proceder con añadir al carrito
                    this.proceedWithAddToCart(form, submitButton, originalText);
                },
                error: () => {
                    submitButton.prop('disabled', false).text(originalText);
                    alert('Error updating cart date');
                }
            });
        } else {
            // Cancelar la operación
            submitButton.prop('disabled', false).text(originalText);
        }
    }
} else {
    // No hay items, proceder directamente
    this.proceedWithAddToCart(form, submitButton, originalText);
}
           } else {
               submitButton.prop('disabled', false).text(originalText);
               alert(validateResponse.data || 'Error validating date');
           }
       },
       error: () => {
           submitButton.prop('disabled', false).text(originalText);
           alert('Error validating date');
       }
   });
}

// Método auxiliar para proceder con la adición al carrito
proceedWithAddToCart(form, submitButton, originalText) {
   const formData = new FormData(form);
   const listingId = formData.get('listing_id');
   
   // Validar y formatear la fecha antes de enviarla
   let dateToSend = formData.get('_dates');
   if (dateToSend) {
       // Si es un timestamp, convertirlo a formato Y-m-d
       if (typeof dateToSend === 'string' && /^\d+$/.test(dateToSend)) {
           const timestamp = parseInt(dateToSend);
           let dateObj;
           
           if (timestamp.toString().length === 10) {
               // Timestamp en segundos
               dateObj = new Date(timestamp * 1000);
           } else if (timestamp.toString().length === 13) {
               // Timestamp en milisegundos
               dateObj = new Date(timestamp);
           } else {
               dateObj = new Date(dateToSend);
           }
           
           if (!isNaN(dateObj.getTime())) {
               dateToSend = dateObj.getFullYear() + '-' + 
                           String(dateObj.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(dateObj.getDate()).padStart(2, '0');
           }
       }
   }
   
   // Preparar datos para el servidor
   const data = {
       action: 'eq_add_to_cart',
       nonce: eqCartData.nonce,
       listing_id: listingId,
       date: dateToSend,
       quantity: formData.get('_quantity') || 1,
       extras: this.getExtrasData(form)
   };
	
   try {
    const contextData = sessionStorage.getItem('eqQuoteContext');
    if (contextData) {
        const parsedContext = JSON.parse(contextData);
        if (parsedContext.isActive && parsedContext.leadId && parsedContext.eventId) {
            data.context_lead_id = parsedContext.leadId;
            data.context_event_id = parsedContext.eventId;
        }
    }
} catch (e) {
    // Error reading context from sessionStorage
}

   // Llamada AJAX
   $.ajax({
       url: eqCartData.ajaxurl,
       type: 'POST',
       data: data,
       success: (response) => {
           if (response.success) {
               const listingId = parseInt(formData.get('listing_id'));
               
               // Buscar si el item ya existe en el state
               const existingIndex = this.state.items.findIndex(item => 
                   parseInt(item.listing_id) === listingId && 
                   item.status === 'active'
               );

               if (existingIndex !== -1) {
                   // Si existe, actualizar el item en el array
                   this.state.items[existingIndex] = response.data.item;
               } else {
                   // Si no existe, agregar el nuevo item
                   this.state.items.push(response.data.item);
               }

               this.renderItems();
               this.updateCartSummary();
               this.updateHeaderCart();
                   
               // Cerrar el formulario y mostrar los items
               this.content.find('#eq-current-product').slideUp();
               this.content.find('#eq-added-products').slideDown();

               // Mostrar mensaje apropiado
               const message = existingIndex !== -1 ? 'Item updated successfully' : 'Item added to quote successfully';
               alert(message);
           } else {
               alert(response.data || eqCartData.i18n.errorAdding);
           }
       },
       error: (jqXHR, textStatus, errorThrown) => {
           alert(eqCartData.i18n.errorAdding);
       },
       complete: () => {
           submitButton.prop('disabled', false).text(originalText);
       }
   });
}
		
		updateHeaderCart() {
    const headerCount = $('.eq-cart-count');
    const count = this.state.items.length;
    
    // Actualizar número
    if (count > 0) {
        if (headerCount.length) {
            headerCount.text(count);
        } else {
            $('.eq-header-cart-button').append(
                `<span class="eq-cart-count">${count}</span>`
            );
        }
    } else {
        headerCount.remove();
    }
}

handleIncludeItem(itemId) {
    // Primero validar la fecha
    if (!this.state.selectedDate) {
        alert(eqCartData.i18n.dateRequired);
        return;
    }

    // Verificar disponibilidad primero
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_validate_date',
            nonce: eqCartData.nonce,
            listing_id: itemId,
            date: this.state.selectedDate
        },
        success: (response) => {
            if (response.success && response.data.available) {
                this.addItemToCart(itemId);
            } else {
                alert(eqCartData.i18n.dateNotAvailable);
            }
        },
        error: () => {
            alert(eqCartData.i18n.errorValidating);
        }
    });
}

getExtrasData(form) {
    const extras = [];
    const $form = $(form);

    // Procesar extras normales (checkbox)
    $form.find('.eq-extras-checkbox:checked').each(function() {
        extras.push({
            id: $(this).val(),
            type: $(this).data('type') || '',
            name: $(this).data('name'),
            price: $(this).data('price'),
            quantity: 1
        });
    });

    // Procesar extras variables (inputs numéricos)
    $form.find('.eq-extra-quantity').each(function() {
        const quantity = parseInt($(this).val()) || 0;
        if (quantity > 0) {
            extras.push({
                id: $(this).data('extra-id'),
                type: 'variable_quantity',
                name: $(this).data('name'),
                price: $(this).data('price'),
                quantity: quantity
            });
        }
    });

    return extras;
}

        bindEvents() {
            // Click en botón de cotización
            $(document).on('click', '.boton-cotizar', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const listingId = $(e.currentTarget).data('listing-id');
                this.handleAddToCart(listingId);
            });

            // Eventos del sidebar
            this.sidebar.on('click', '.eq-sidebar-close', () => this.closeSidebar());

            $(document).on('click', (e) => {
                // Si el sidebar está abierto y el clic no fue dentro del sidebar ni en un botón que lo abre
                if (this.state.isOpen && 
                    !$(e.target).closest('.eq-sidebar').length && 
                    !$(e.target).closest('.boton-cotizar').length &&
                    !$(e.target).closest('.eq-header-cart-button').length) {
                    this.closeSidebar();
                }
            });
        
			
	// Eventos de items
this.content.on('submit', '#eq-add-to-cart-form', (e) => {
    this.handleFormSubmit(e);
});

// Eventos de formulario y botón include
this.content.on('click', '.eq-include-button', (e) => {
    e.preventDefault();
    e.stopPropagation();
    const form = $(e.target).closest('form')[0];
    if (form) {
        this.handleFormSubmit(e, form);
    }
});

         

            this.content.on('click', '.eq-mini-card-button.see-more', (e) => {
                const itemId = $(e.target).closest('.eq-mini-card').data('item-id');
                this.handleSeeMore(itemId);
            });

            this.content.on('click', '.eq-mini-card-button.remove', (e) => {
                const itemId = $(e.target).closest('.eq-mini-card').data('item-id');
                this.handleRemoveItem(itemId);
            });

             // Reemplazar el evento del filtro de fecha
				$(document).on('eqFilterDateApplied', (e, date) => {
					this.handleFilterDateChange(date);
				});

            // View Quote button removido - ahora en menú
			
			
            // Extras y cantidad
            this.content.on('change', '.eq-extras-checkbox', (e) => {
                this.handleExtraChange(e);
            });

            this.content.on('input', '.eq-extra-quantity', (e) => {
                this.handleVariableExtraChange(e);
            });

            this.content.on('input', '.eq-quantity-input', (e) => {
                this.handleQuantityChange(e);
            });
        }

        async loadCartItems() {
            // Verificar si el usuario ha iniciado sesión
            if (!eqCartData.userLoggedIn) {
                return;
            }
            
            try {
                const response = await $.ajax({
                    url: eqCartData.ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'eq_get_cart_items',
                        nonce: eqCartData.nonce
                    }
                });
        
                if (response.success) {
                    this.state.items = response.data.items || [];
                    this.renderItems();
                    this.updateCartSummary();
                    this.updateHeaderCart();
                }
            } catch (error) {
                // Error loading cart items
            }
        }

      async handleAddToCart(listingId) {
    try {
        // Verificar si el context panel está minimizado y mostrarlo si es necesario
        if (window.EQQuoteContext && window.EQQuoteContext.data.isActive && window.EQQuoteContext.data.isMinimized) {
            window.EQQuoteContext.data.isMinimized = false;
            $('.eq-context-toggle-button').remove();
            $('.eq-context-panel').slideDown(200);
            $('body').addClass('has-eq-context-panel').removeClass('has-eq-context-minimized');
            window.EQQuoteContext.saveToStorage();
        }
        
        // Mejorar la detección de elementos existentes asegurando conversión numérica
const listingIdNum = parseInt(listingId);

// Buscar el elemento en los items existentes, con log para debug
let existingItem = null;
for (let i = 0; i < this.state.items.length; i++) {
    const currentItem = this.state.items[i];
    const currentListingId = parseInt(currentItem.listing_id);
    
    
    if (currentListingId === listingIdNum && currentItem.status === 'active') {
        existingItem = currentItem;
        break;
    }
}

        const listingData = await this.fetchListingData(listingIdNum);
        
        if (existingItem) {
            // En lugar de buscar form_data, usar directamente los datos del item
            const formData = {
                date: existingItem.date,
                quantity: existingItem.quantity,
                extras: existingItem.extras,
                base_price: existingItem.base_price,
                total_price: existingItem.total_price
            };
            
            this.state.currentItem = {
                ...listingData,
                existingItem: true,
                formData: formData
            };
        } else {
            this.state.currentItem = listingData;
        }

        this.renderAddForm();
        
        // Asegurar que la fecha se muestre correctamente después de renderizar el formulario
        setTimeout(() => {
            const dateValue = this.state.selectedDate || localStorage.getItem('eq_selected_date');
            if (dateValue) {
                this.updateDateDisplay(dateValue);
            }
        }, 100);
        
        this.openSidebar();
    } catch (error) {
        alert('Error loading product data');
    }
}

        async fetchListingData(listingId) {
            try {

                const response = await $.ajax({
                    url: eqCartData.ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'eq_get_listing_data',
                        nonce: eqCartData.nonce,
                        listing_id: listingId,
                        date: this.state.selectedDate
                    }
                });

                if (!response.success) {
                    throw new Error(response.data || 'Error fetching listing data');
                }

                return response.data;
            } catch (error) {
                throw error;
            }
        }

       
renderAddForm() {
    const item = this.state.currentItem;

    // Manejar fechas de forma segura
    let formData = {};
    if (item.existingItem && item.formData) {
        formData = item.formData;
    }

    const existingDate = formData.date || null;
    const storedDate = localStorage.getItem('eq_selected_date');
    let rawDateValue = existingDate || storedDate || '';
    
    // Convertir timestamp a formato de fecha si es necesario
    let dateValue = '';
    let dateDisplay = 'Select date';
    
    if (rawDateValue) {
        // Si es un timestamp numérico
        if (typeof rawDateValue === 'string' && /^\d+$/.test(rawDateValue)) {
            const timestamp = parseInt(rawDateValue);
            let dateObj;
            
            if (timestamp.toString().length === 10) {
                // Timestamp en segundos
                dateObj = new Date(timestamp * 1000);
            } else if (timestamp.toString().length === 13) {
                // Timestamp en milisegundos
                dateObj = new Date(timestamp);
            } else {
                dateObj = new Date(rawDateValue);
            }
            
            if (!isNaN(dateObj.getTime())) {
                // Convertir a formato Y-m-d para el input
                dateValue = dateObj.getFullYear() + '-' + 
                           String(dateObj.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(dateObj.getDate()).padStart(2, '0');
                // Formato local para mostrar
                dateDisplay = dateObj.toLocaleDateString();
            }
        } else {
            // Es una fecha en formato string
            dateValue = rawDateValue;
            try {
                const [year, month, day] = rawDateValue.split('-');
                const localDate = new Date(year, month - 1, day);
                if (!isNaN(localDate.getTime())) {
                    dateDisplay = localDate.toLocaleDateString();
                }
            } catch (e) {
                dateDisplay = 'Invalid date';
            }
        }
    }

    // Obtener cantidad de forma segura
    const quantity = formData.quantity || item.min_quantity || 1;

    const formHtml = `
        ${item.existingItem ? `
            <div class="eq-editing-banner">
                <span>✏️ Editando reserva existente</span>
            </div>
        ` : ''}
        <div class="eq-product-form">
            <div class="eq-product-header">
                <img src="${item.image}" alt="${item.title}" class="eq-product-image">
                <h3 class="eq-product-title">${item.title}</h3>
            </div>
            <form id="eq-add-to-cart-form">
                <input type="hidden" name="listing_id" value="${item.id}">
                
                <!-- Date Field -->
                <div class="eq-form-group eq-date-field">
                    <label>Date</label>
                    <div class="eq-date-container">
                        <input type="text" 
                               class="eq-date-picker"
                               name="_dates"
                               value="${dateValue}"
                               placeholder="Select a date"
                               required>
                        <div class="eq-date-display ${dateValue ? 'has-date' : ''}">
                            <span class="eq-date-value">${dateDisplay}</span>
                            <button type="button" class="eq-date-edit">
                                <i class="fas fa-calendar-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
				
				

               <!-- Quantity Section -->
${(parseInt(item.min_quantity) === 1 && parseInt(item.max_quantity) === 1) ? `
    <!-- Hidden Quantity Input when min=max=1 -->
    <input type="hidden" 
           name="_quantity" 
           value="1"
           class="eq-quantity-input">
` : `
    <div class="eq-form-group eq-quantity-group">
        <label>Quantity</label>
        <input type="number" 
               class="eq-quantity-input"
               name="_quantity" 
               min="${parseInt(item.min_quantity) || 1}" 
               max="${parseInt(item.max_quantity) || 999}" 
               value="${parseInt(quantity) || 1}"
               required>
    </div>
`}

                <!-- Extras Section -->
                <div class="eq-extras-section" style="display: ${item.extras && item.extras.length ? 'block' : 'none'}">
                    <div class="eq-form-group">
                        <label>Extras</label>
                        <div class="eq-extras-container">
                            ${item.extras ? item.extras.map(extra => {
                                if (extra.type === 'variable_quantity') {
                                    return `
                                        <div class="eq-extra-item variable">
                                            <label class="eq-extra-label">
                                                <span class="eq-extra-name">${extra.name}</span>
                                                <div class="eq-extra-quantity-wrapper">
                                                    <input type="number"
                                                           class="eq-extra-quantity"
                                                           name="extras[${extra.id}]"
                                                           min="0"
                                                           value="0"
                                                           data-extra-id="${extra.id}"
                                                           data-price="${extra.price}">
                                                    <span class="eq-extra-price">${extra.price_formatted}/unit</span>
                                                </div>
                                            </label>
                                        </div>
                                    `;
                                } else {
                                    return `
                                        <div class="eq-extra-item">
                                            <label class="eq-extra-label">
                                                <input type="checkbox"
                                                       class="eq-extras-checkbox"
                                                       name="extras[]"
                                                       value="${extra.id}"
                                                       data-price="${extra.price}"
                                                       data-type="${extra.type || ''}">
                                                <span class="eq-extra-name">${extra.name}</span>
                                                <span class="eq-extra-price">${extra.price_formatted}</span>
                                            </label>
                                        </div>
                                    `;
                                }
                            }).join('') : ''}
                        </div>
                    </div>
                </div>

                <!-- Price Summary -->
					<div class="eq-price-summary">
						<div class="eq-base-price">
							<span>Base Price:</span>
							<span class="eq-price-value">${this.formatPrice(item.price)}</span>
						</div>
						<div class="eq-total-price">
							<span>Total (incl. taxes):</span>
							<span class="eq-price-value">${this.formatPrice(item.price)}</span>
						</div>
					</div>

                <button type="button" class="eq-include-button">
    ${item.existingItem ? 'Update Quote' : 'Include in Quote'}
</button>
            </form>
        </div>
    `;

     // Pre-llenar los valores del form si existen
    this.content.html(formHtml);
    
    if (item.existingItem && formData) {
        // Pre-llenar fecha
        const dateInput = this.content.find('.eq-date-picker');
        if (dateInput.length && dateInput[0]._flatpickr) {
            dateInput[0]._flatpickr.setDate(formData.date);
        }

        // Pre-llenar cantidad
        this.content.find('.eq-quantity-input').val(formData.quantity);

        // Pre-llenar extras
        if (formData.extras && formData.extras.length) {
            formData.extras.forEach(extra => {
                if (extra.type === 'variable_quantity') {
                    this.content.find(`.eq-extra-quantity[data-extra-id="${extra.id}"]`)
                        .val(extra.quantity);
                } else {
                    this.content.find(`.eq-extras-checkbox[value="${extra.id}"]`)
                        .prop('checked', true);
                }
            });
        }

        // Actualizar precios
        this.recalculatePrice();
    }
}
		
		
		
initializeFormData() {
    const item = this.state.currentItem;
    if (!item) return;

    // Inicializar precio base
    this.updatePriceSummary({
        basePrice: parseFloat(item.price) || 0,
        quantity: 1,
        extras: []
    });

    // Mostrar/ocultar sección de quantity según configuración
    const quantitySection = this.content.find('.eq-quantity-group');
    if (item.has_quantity) {
        quantitySection.show();
        // Establecer límites
        const quantityInput = quantitySection.find('.eq-quantity-input');
        quantityInput.attr({
            'min': item.min_quantity || 1,
            'max': item.max_quantity || 999,
            'value': item.min_quantity || 1
        });
    } else {
        quantitySection.hide();
    }

    // Inicializar extras
    const extrasSection = this.content.find('.eq-extras-section');
    if (item.extras && item.extras.length > 0) {
        this.renderExtras(item.extras);
        extrasSection.show();
    } else {
        extrasSection.hide();
    }
}

renderExtras(extras) {
    const container = this.content.find('.eq-extras-container');
    container.empty();

    extras.forEach(extra => {
        const extraElement = this.createExtraElement(extra);
        container.append(extraElement);
    });

    // Inicializar listeners
    this.initializeExtrasListeners();
}

createExtraElement(extra) {
    if (extra.type === 'variable') {
        return `
            <div class="eq-extra-item variable">
                <label class="eq-extra-label">
                    <span class="eq-extra-name">${extra.name}</span>
                    <div class="eq-extra-quantity-wrapper">
                        <input type="number"
                               class="eq-extra-quantity"
                               name="extras[${extra.id}]"
                               min="0"
                               value="0"
                               data-extra-id="${extra.id}"
                               data-price="${extra.price}">
                        <span class="eq-extra-price">${extra.price_formatted}/unit</span>
                    </div>
                </label>
            </div>
        `;
    } else {
        return `
            <div class="eq-extra-item">
                <label class="eq-extra-label">
                    <input type="checkbox"
                           class="eq-extras-checkbox"
                           name="extras[]"
                           value="${extra.id}"
                           data-price="${extra.price}"
                           data-type="${extra.type || ''}">
                    <span class="eq-extra-name">${extra.name}</span>
                    <span class="eq-extra-price">${extra.price_formatted}</span>
                </label>
            </div>
        `;
    }
}

updatePriceSummary({ basePrice, quantity, extras }) {
    const subtotal = this.calculateSubtotal(basePrice, quantity, extras);
    const tax = subtotal * (parseFloat(eqCartData.taxRate) || 0) / 100;
    const total = subtotal + tax;

    this.content.find('.eq-base-price .eq-price-value').text(
        this.formatPrice(basePrice * quantity)
    );
    this.content.find('.eq-total-price .eq-price-value').text(
        this.formatPrice(total)
    );
}

calculateSubtotal(basePrice, quantity, extras) {
    let subtotal = basePrice * quantity;

    extras.forEach(extra => {
        const extraPrice = parseFloat(extra.price) || 0;
        const extraQuantity = parseInt(extra.quantity) || 0;
        
        switch(extra.type) {
            case 'variable':
            case 'variable_quantity':
                subtotal += extraPrice * extraQuantity;
                break;
            case 'per_quantity':
                subtotal += extraPrice * quantity;
                break;
            case 'per_order':
            case 'per_booking':
            case 'per_item': // Añadir caso per_item como precio fijo
                subtotal += extraPrice;
                break;
            default:
                subtotal += extraPrice * quantity;
        }
    });

    return subtotal;
}

initializeExtrasListeners() {
    // Escuchar cambios en checkboxes
    this.content.find('.eq-extras-checkbox').on('change', () => {
        this.recalculatePrice();
    });

    // Escuchar cambios en quantities variables
    this.content.find('.eq-extra-quantity').on('input', () => {
        this.recalculatePrice();
    });

    // Escuchar cambios en quantity general
    this.content.find('.eq-quantity-input').on('input', () => {
        this.recalculatePrice();
    });
}

recalculatePrice() {
    const basePrice = parseFloat(this.state.currentItem.price) || 0;
    const quantity = parseInt(this.content.find('.eq-quantity-input').val()) || 1;
    const extras = this.collectExtrasData();

    this.updatePriceSummary({ basePrice, quantity, extras });
}

collectExtrasData() {
    const extras = [];
    
    // Recolectar datos de checkboxes
    this.content.find('.eq-extras-checkbox:checked').each(function() {
        extras.push({
            id: $(this).val(),
            price: parseFloat($(this).data('price')),
            type: $(this).data('type'),
            quantity: 1
        });
    });

    // Recolectar datos de quantities variables
    this.content.find('.eq-extra-quantity').each(function() {
        const quantity = parseInt($(this).val()) || 0;
        if (quantity > 0) {
            extras.push({
                id: $(this).data('extra-id'),
                price: parseFloat($(this).data('price')),
                type: 'variable',
                quantity: quantity
            });
        }
    });

    return extras;
}

        renderQuantityField(item) {
            if (!item.has_quantity) return '';

            return `
                <div class="eq-quantity-field">
                    <label>Quantity</label>
                    <input type="number" 
                           class="eq-quantity-input"
                           name="quantity" 
                           min="${item.min_quantity || 1}" 
                           max="${item.max_quantity || 999}" 
                           value="${item.min_quantity || 1}"
                           required>
                </div>
            `;
        }

        renderExtrasField(item) {
            if (!item.extras || !item.extras.length) return '';

            return `
                <div class="eq-extras-field">
                    <label>Extras</label>
                    <div class="eq-extras-list">
                        ${item.extras.map(extra => {
                            if (extra.type === 'variable') {
                                return `
                                    <div class="eq-extra-item">
                                        <label>
                                            <span class="eq-extra-name">${extra.name}</span>
                                            <input type="number"
                                                   class="eq-extra-quantity"
                                                   name="extras[${extra.id}]"
                                                   min="0"
                                                   value="0"
                                                   data-price="${extra.price}"
                                                   data-name="${extra.name}">
                                            <span class="eq-extra-price">${extra.price_formatted}</span>
                                        </label>
                                    </div>
                                `;
                            } else {
                                return `
                                    <div class="eq-extra-item">
                                        <label>
                                            <input type="checkbox"
                                                   class="eq-extras-checkbox"
                                                   name="extras[]"
                                                   value="${extra.id}"
                                                   data-price="${extra.price}"
                                                   data-name="${extra.name}"
                                                   data-type="${extra.type}">
                                            <span class="eq-extra-name">${extra.name}</span>
                                            <span class="eq-extra-price">${extra.price_formatted}</span>
                                        </label>
                                    </div>
                                `;
                            }
                        }).join('')}
                    </div>
                </div>
            `;
        }

        initializeFormComponents() {
    // Date Picker Initialization
    const dateInput = this.content.find('.eq-date-picker');
    if (dateInput.length) {
        const minLength = parseInt(dateInput.data('min-length')) || 1;
        const maxLength = parseInt(dateInput.data('max-length')) || 1;
        const bookingOffset = parseInt(dateInput.data('booking-offset')) || 0;
        const bookingWindow = parseInt(dateInput.data('booking-window')) || 365;

        const flatpickrInstance = flatpickr(dateInput[0], {
            dateFormat: "Y-m-d",
            minDate: new Date().fp_incr(bookingOffset),
            maxDate: new Date().fp_incr(bookingWindow),
            defaultDate: this.state.selectedDate || null,
            locale: "es",
            static: true,
            onChange: (selectedDates) => {
    if (selectedDates[0]) {
        const date = selectedDates[0].toISOString().split('T')[0];
        
        // Actualizar display primero
        const displayElement = this.content.find('.eq-date-display');
        displayElement.addClass('has-date');
        displayElement.find('.eq-date-value').text(
            selectedDates[0].toLocaleDateString()
        );

        // Validar la fecha
        this.validateDate(date).then(isAvailable => {
            if (isAvailable) {
                // Guardar la fecha si está disponible
                localStorage.setItem('eq_selected_date', date);
                this.state.selectedDate = date;
                
                // Validar otros items en el carrito
                this.validateCartItems(date);
            } else {
                // Limpiar si no está disponible
                flatpickrInstance.clear();
                displayElement.removeClass('has-date');
                displayElement.find('.eq-date-value').text('Select date');
            }
        });
    }
}
        });

        // Click en botón de editar
        this.content.find('.eq-date-edit').on('click', () => {
            flatpickrInstance.open();
        });
    }
	
    this.state.dateInitialized = true;
			 // Inicializar precio inicial
    this.calculateTotals();
}
        

        async handleDateChange(selectedDates) {
            const date = selectedDates[0].toISOString().split('T')[0];
            
            try {
                const isAvailable = await this.validateDate(date);
                if (!isAvailable) {
                    alert(eqCartData.i18n.dateNotAvailable);
                    return;
                }

                this.state.selectedDate = date;
                localStorage.setItem('eq_selected_date', date);
                
                // Actualizar filtro si existe
                const filterInput = $('.filtro-fecha');
                if (filterInput.length) {
                    filterInput.val(date).trigger('change');
                }

                this.calculateTotals();
            } catch (error) {
                // Error validating date
            }
        }

        validateDate(date) {
    const listingId = this.content.find('input[name="listing_id"]').val();
    
    // Mostrar estado de carga
    const dateField = this.content.find('.eq-date-field');
    dateField.addClass('validating');
    
    return $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_validate_date',
            nonce: eqCartData.nonce,
            listing_id: listingId,
            date: date
        }
    }).then(response => {
        dateField.removeClass('validating');
        
        if (response.success) {
            dateField.removeClass('invalid');
            return response.data.available;
        } else {
            dateField.addClass('invalid');
            alert(response.data || eqCartData.i18n.errorValidating);
            return false;
        }
    }).catch(() => {
        dateField.removeClass('validating').addClass('invalid');
        alert(eqCartData.i18n.errorValidating);
        return false;
    });
}

        handleExtraChange(e) {
            this.calculateTotals();
        }

        handleVariableExtraChange(e) {
            this.calculateTotals();
        }

        handleQuantityChange(e) {
            this.calculateTotals();
        }

        calculateTotals() {
    const form = this.content.find('#eq-add-to-cart-form');
    if (!form.length) return;

    let basePrice = this.cleanPrice(this.state.currentItem.price);
    const quantity = parseInt(form.find('.eq-quantity-input').val()) || 1;

    // Base price * quantity
    let subtotal = basePrice * quantity;

    // Add extras
    form.find('.eq-extras-checkbox:checked').each((_, checkbox) => {
        const $checkbox = $(checkbox);
        const price = this.cleanPrice($checkbox.data('price'));
        const extraType = $checkbox.data('type');

        if (extraType === 'per_order' || extraType === 'per_booking' || extraType === 'per_item') {
            // Para extras por orden/booking/per_item, NO multiplicar por cantidad
            subtotal += price;
        } else {
            // Para todos los demás tipos, multiplicar por cantidad
            subtotal += price * quantity;
        }
    });

    // Add variable extras
    form.find('.eq-extra-quantity').each((_, input) => {
        const $input = $(input);
        const extraQuantity = parseInt($input.val()) || 0;
        if (extraQuantity > 0) {
            const price = this.cleanPrice($input.data('price'));
            subtotal += price * extraQuantity;
        }
    });

    // Apply tax
    const taxRate = parseFloat(eqCartData.taxRate) || 0;
    const taxAmount = subtotal * (taxRate / 100);
    const total = subtotal + taxAmount;

    // Update UI
    form.find('.eq-base-price .eq-price-value').text(this.formatPrice(basePrice * quantity));
    form.find('.eq-total-price .eq-price-value').text(this.formatPrice(total));
}
		
        renderItems() {
            if (!this.state.items.length) {
                this.content.html('<p class="eq-empty-cart">No items in quote cart</p>');
                return;
            }

            const itemsHtml = this.state.items.map(item => this.renderMiniCard(item)).join('');
            this.content.html(itemsHtml);
        }

        renderMiniCard(item) {
            return `
                <div class="eq-mini-card" data-item-id="${item.id}">
                    <div class="eq-mini-card-header">
                        <h4 class="eq-mini-card-title">${item.title}</h4>
                    </div>
                    
                    <div class="eq-mini-card-details">
                        <div class="eq-date-field">
                            <span class="eq-date-display">${item.date}</span>
                        </div>
                        
                        ${item.quantity > 1 ? `
                            <div class="eq-quantity-field">
                                <span>Quantity: ${item.quantity}</span>
                            </div>
                        ` : ''}
                        
                        ${this.renderMiniCardExtras(item.extras)}

                        <div class="eq-card-price">
                            <span>Total (incl. taxes)</span>
                            <span class="eq-price-value">${item.price_formatted}</span>
                        </div>
                    </div>
                    
                    <div class="eq-mini-card-actions">
                        <button type="button" class="eq-mini-card-button see-more">
                            See Details
                        </button>
                        <button type="button" class="eq-mini-card-button remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        }

       	renderMiniCardExtras(extras) {
            if (!extras || !extras.length) return '';

            return `
                <div class="eq-extras-field">
                    <span>Extras:</span>
                    <ul class="eq-extras-list">
                        ${extras.map(extra => `
                            <li>${extra.name}${extra.quantity ? ` × ${extra.quantity}` : ''}</li>
                        `).join('')}
                    </ul>
                </div>
            `;
        }

        handleSeeMore(itemId) {
            const miniCard = $(`.eq-mini-card[data-item-id="${itemId}"]`);
            const details = miniCard.find('.eq-mini-card-details');
            
            if (miniCard.hasClass('collapsed')) {
                details.slideDown(300);
                miniCard.removeClass('collapsed');
                miniCard.find('.eq-mini-card-button.see-more').text('Hide Details');
            } else {
                details.slideUp(300);
                miniCard.addClass('collapsed');
                miniCard.find('.eq-mini-card-button.see-more').text('See Details');
            }
        }

        async handleRemoveItem(itemId) {
            if (!confirm(eqCartData.i18n.confirmRemove)) return;

            try {
                const response = await $.ajax({
                    url: eqCartData.ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'eq_remove_from_cart',
                        nonce: eqCartData.nonce,
                        item_id: itemId
                    }
                });

                if (response.success) {
                    this.state.items = this.state.items.filter(item => item.id !== itemId);
                    $(`.eq-mini-card[data-item-id="${itemId}"]`).fadeOut(() => {
                        $(this).remove();
						this.renderItems();
                        this.updateCartSummary();
						this.updateHeaderCart(); // Añadir esta línea

                    });
                }
            } catch (error) {
                alert(eqCartData.i18n.errorRemoving);
            }
        }

        updateCartSummary() {
    let total = 0;
    this.state.items.forEach(item => {
        // Usar directamente total_price en lugar de price_formatted
        const price = parseFloat(item.total_price || 0);
        
        if (!isNaN(price)) {
            total += price;
        }
    });

    this.footer.find('.eq-cart-total-amount').text(this.formatPrice(total));
    this.footer.find('.eq-cart-items-count').text(this.state.items.length);
    
    // Actualizar visibilidad del menú dinámicamente
    this.updateMenuVisibility();

}

       handleViewQuote(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    if (eqCartData.cartUrl) {
        window.location.href = eqCartData.cartUrl;
    }
}

        updateMenuVisibility() {
            const menuItem = $('.eq-view-quotes-menu-item');
            if (menuItem.length > 0) {
                if (this.state.items.length === 0) {
                    menuItem.addClass('eq-hide-when-empty');
                } else {
                    menuItem.removeClass('eq-hide-when-empty');
                }
            }
        }

        handleFilterDateChange(date) {
    if (!date) return;

    this.state.selectedDate = date;
    
    // Si el sidebar está abierto y hay un item actual
    if (this.state.isOpen && this.state.currentItem) {
        const datePicker = this.content.find('.eq-date-picker').get(0);
        if (datePicker && datePicker._flatpickr) {
            // Actualizar la fecha en el date picker
            datePicker._flatpickr.setDate(date, false);
            
            // Actualizar el display usando parseado específico
            const [year, month, day] = date.split('-');
            const localDate = new Date(year, month - 1, day);
            
            const displayElement = this.content.find('.eq-date-display');
            displayElement.addClass('has-date');
            displayElement.find('.eq-date-value').text(
                localDate.toLocaleDateString()
            );
        }
    }
    
    // Si hay items en el carrito, validar disponibilidad
    if (this.state.items.length > 0) {
        this.validateCartItems(date);
    }
}

        async validateCartItems(date) {
    if (!this.state.items.length) return;

    return $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_validate_cart_items',
            nonce: eqCartData.nonce,
            date: date
        }
    }).then(response => {
        if (response.success) {
            this.updateItemsAvailability(response.data.validations);
        }
    }).catch(() => {
        alert(eqCartData.i18n.errorValidating);
    });
}

        updateItemsAvailability(validations) {
            Object.entries(validations).forEach(([itemId, validation]) => {
                const itemElement = $(`.eq-mini-card[data-item-id="${itemId}"]`);
                if (itemElement.length) {
                    if (!validation.available) {
                        itemElement.addClass('not-available');
                        itemElement.find('.eq-availability-warning').remove();
                        itemElement.append(`
                            <div class="eq-availability-warning">
                                Not available for selected date
                            </div>
                        `);
                    } else {
                        itemElement.removeClass('not-available');
                        itemElement.find('.eq-availability-warning').remove();
                    }
                }
            });
        }
		
		cleanPrice(price) {
    // En lugar de usar price_formatted deberíamos usar total_price directamente
    if (typeof price === 'number') return price;
    if (typeof price === 'string') {
        return parseFloat(price.replace(/[^0-9.-]/g, ''));
    }
    return 0;
}

       formatPrice(amount) {

    // Limpiar y asegurar que es número
    amount = this.cleanPrice(amount);
    
    // Formatear con el número correcto de decimales
    let formattedPrice = amount.toFixed(eqCartData.num_decimals);
    
    // Dividir en parte entera y decimal
    let parts = formattedPrice.split('.');
    
    // Agregar separadores de miles a la parte entera
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, eqCartData.thousand_sep);
    
    // Juntar con el separador decimal correcto
    formattedPrice = parts.join(eqCartData.decimal_sep);
    
    // Agregar el símbolo de moneda
    return eqCartData.currency + formattedPrice;
}

        openSidebar() {
            this.sidebar.addClass('active');
            this.state.isOpen = true;
            $('body').addClass('eq-sidebar-open');
        }

        closeSidebar() {
            this.sidebar.removeClass('active');
            this.state.isOpen = false;
            $('body').removeClass('eq-sidebar-open');
            
            if (this.state.currentItem) {
                this.state.currentItem = null;
                this.content.html('');
                this.renderItems();
            }
        }
    }

    // Inicializar cuando el documento esté listo
    $(document).ready(() => {
        window.quoteCart = new QuoteCart();
    });
	
	// Exponer función para actualizar contador del header
window.updateHeaderCartCount = function(count) {
    const headerCount = $('.eq-cart-count');
    if (count > 0) {
        if (headerCount.length) {
            headerCount.text(count);
        } else {
            $('.eq-header-cart-button').append(
                `<span class="eq-cart-count">${count}</span>`
            );
        }
    } else {
        headerCount.remove();
    }
};

// Cart History functionality
window.EQCartHistory = {
    init: function() {
        this.bindEvents();
    },
    
    bindEvents: function() {
        // Open history modal
        $(document).on('click', '#eq-cart-history-btn', this.openHistoryModal.bind(this));
        
        // Close modal
        $(document).on('click', '#eq-cart-history-modal .eq-modal-close', this.closeHistoryModal.bind(this));
        
        // Close modal on outside click
        $(document).on('click', '#eq-cart-history-modal', function(e) {
            if (e.target === this) {
                window.EQCartHistory.closeHistoryModal();
            }
        });
        
        // History item selection
        $(document).on('change', '.eq-history-item input[type="radio"]', this.onHistorySelection.bind(this));
        
        // Restore button
        $(document).on('click', '#eq-restore-history', this.restoreHistory.bind(this));
    },
    
    openHistoryModal: function() {
        $('#eq-cart-history-modal').show();
        $('#eq-history-loading').show();
        $('#eq-history-content').hide();
        $('#eq-history-empty').hide();
        
        this.loadHistory();
    },
    
    closeHistoryModal: function() {
        $('#eq-cart-history-modal').hide();
    },
    
    loadHistory: function() {
        console.log('Cart History Debug: Starting loadHistory');
        console.log('Cart History Debug: ajaxUrl =', eqCartData.ajaxurl);
        console.log('Cart History Debug: nonce =', eqCartData.nonce);
        
        $.ajax({
            url: eqCartData.ajaxurl,
            type: 'POST',
            data: {
                action: 'eq_get_cart_history',
                nonce: eqCartData.nonce
            },
            success: (response) => {
                console.log('Cart History Debug: AJAX Success Response:', response);
                $('#eq-history-loading').hide();
                
                if (response.success && response.data.history && response.data.history.length > 0) {
                    console.log('Cart History Debug: Found history entries:', response.data.history.length);
                    this.renderHistory(response.data.history);
                    $('#eq-history-content').show();
                } else {
                    console.log('Cart History Debug: No history entries found or response failed');
                    console.log('Cart History Debug: response.success =', response.success);
                    console.log('Cart History Debug: response.data =', response.data);
                    $('#eq-history-empty').show();
                }
            },
            error: (xhr, status, error) => {
                console.log('Cart History Debug: AJAX Error:', error);
                console.log('Cart History Debug: XHR:', xhr);
                console.log('Cart History Debug: Status:', status);
                $('#eq-history-loading').hide();
                $('#eq-history-empty').show();
                this.showNotification('Error loading cart history', 'error');
            }
        });
    },
    
    renderHistory: function(history) {
        const historyList = $('.eq-history-list');
        historyList.empty();
        
        history.forEach((entry, index) => {
            const isFirst = index === 0;
            const itemHtml = `
                <div class="eq-history-item ${isFirst ? 'current' : ''}">
                    <div class="eq-history-radio">
                        <input type="radio" name="history_selection" value="${entry.id}" ${isFirst ? 'disabled' : ''}>
                    </div>
                    <div class="eq-history-details">
                        <div class="eq-history-version">
                            Version ${entry.version} ${isFirst ? '(Current)' : ''}
                        </div>
                        <div class="eq-history-date">
                            ${entry.created_formatted}
                        </div>
                        <div class="eq-history-action">
                            Action: ${this.formatAction(entry.action)}
                        </div>
                        <div class="eq-history-total">
                            Total: ${entry.total_formatted}
                        </div>
                    </div>
                </div>
            `;
            historyList.append(itemHtml);
        });
    },
    
    formatAction: function(action) {
        const actionMap = {
            'manual_save': 'Manual Save',
            'item_added': 'Item Added',
            'item_removed': 'Item Removed',
            'item_updated': 'Item Updated',
            'before_restore': 'Before Restore',
            'after_restore': 'After Restore',
            'automatic': 'Automatic'
        };
        
        return actionMap[action] || action;
    },
    
    onHistorySelection: function() {
        const hasSelection = $('.eq-history-list input[type="radio"]:checked').length > 0;
        $('#eq-restore-history').prop('disabled', !hasSelection);
    },
    
    restoreHistory: function() {
        const selectedHistoryId = $('.eq-history-list input[type="radio"]:checked').val();
        
        if (!selectedHistoryId) {
            this.showNotification('Please select a version to restore', 'error');
            return;
        }
        
        const confirmRestore = confirm('Are you sure you want to restore this cart version? This will replace your current cart items.');
        
        if (!confirmRestore) {
            return;
        }
        
        $('#eq-restore-history').prop('disabled', true).text('Restoring...');
        
        $.ajax({
            url: eqCartData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'eq_restore_cart_history',
                history_id: selectedHistoryId,
                nonce: eqCartData.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.showNotification('Cart restored successfully', 'success');
                    this.closeHistoryModal();
                    
                    // Reload the page to show restored cart
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    this.showNotification(response.data || 'Failed to restore cart', 'error');
                }
            },
            error: () => {
                this.showNotification('Error restoring cart', 'error');
            },
            complete: () => {
                $('#eq-restore-history').prop('disabled', false).text('Restore Selected Version');
            }
        });
    },
    
    saveHistorySnapshot: function(action = 'automatic') {
        // This method can be called from other parts of the code to save history
        $.ajax({
            url: eqCartData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'eq_save_cart_history',
                action_type: action,
                nonce: eqCartData.nonce
            },
            success: (response) => {
                // Silent operation, only log for debugging
                console.log('Cart history saved:', response);
            },
            error: () => {
                console.log('Error saving cart history');
            }
        });
    },
    
    showNotification: function(message, type) {
        // Use existing notification system if available, otherwise use alert
        if (window.showNotification) {
            window.showNotification(message, type);
        } else {
            alert(message);
        }
    }
};

// Initialize cart history when document is ready
$(document).ready(function() {
    if (typeof eqCartData !== 'undefined') {
        window.EQCartHistory.init();
    }
});

})(jQuery);