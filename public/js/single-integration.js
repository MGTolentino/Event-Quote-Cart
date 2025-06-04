(function($) {
    'use strict';

    class SingleListingIntegration {
        constructor() {
            this.init();
            this.bindEvents();
        }

        init() {
            this.state = {
                selectedDate: localStorage.getItem('eq_selected_date') || null
            };

            // Verificar si estamos en single listing
            this.isCustomBookingForm = $('.hp-form--booking-make').length > 0;
        }

        bindEvents() {
            // Escuchar cambios en el filtro del cotizador
            $(document).on('eqDateChanged', (e, date) => {
                this.handleDateChange(date);
            });

            // Si estamos en single listing, inicializar
            if (this.isCustomBookingForm) {
                this.initializeCustomBookingForm();
            }
        }

        initializeCustomBookingForm() {
            const dateField = $('[data-component="date"]');
            if (!dateField.length) return;

            // Esperar a que Flatpickr se inicialice
            const checkFlatpickr = setInterval(() => {
                if (dateField[0]._flatpickr) {
                    clearInterval(checkFlatpickr);
                    this.setupCustomBookingForm(dateField[0]._flatpickr);
                }
            }, 100);
        }

        setupCustomBookingForm(flatpickrInstance) {
            // Si hay una fecha guardada, establecerla
            if (this.state.selectedDate) {
                flatpickrInstance.setDate(this.state.selectedDate);
            }

            // Escuchar cambios en el date picker
            flatpickrInstance.config.onChange.push((selectedDates) => {
                if (selectedDates[0]) {
                    const date = selectedDates[0].toISOString().split('T')[0];
                    this.handleCustomBookingDateChange(date);
                }
            });
        }

        handleDateChange(date) {
            this.state.selectedDate = date;

            // Si estamos en single listing, actualizar el form
            if (this.isCustomBookingForm) {
                const dateField = $('[data-component="date"]');
                if (dateField.length && dateField[0]._flatpickr) {
                    dateField[0]._flatpickr.setDate(date);
                }
            }

            // Verificar disponibilidad si hay un listing ID
            const listingId = $('input[name="listing"]').val();
            if (listingId) {
                this.validateAvailability(listingId, date);
            }
        }

        handleCustomBookingDateChange(date) {
            // Guardar fecha en localStorage
            localStorage.setItem('eq_selected_date', date);
            this.state.selectedDate = date;

            // Actualizar filtro del cotizador si existe
            const filterInput = $('#fecha');
            if (filterInput.length) {
                filterInput.val(date).trigger('change');
            }
        }

        validateAvailability(listingId, date) {
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
                if (response.success) {
                    if (!response.data.available) {
                        // Mostrar mensaje de no disponible
                        this.showAvailabilityMessage(false);
                    } else {
                        this.showAvailabilityMessage(true);
                    }
                }
                return response;
            });
        }

        showAvailabilityMessage(isAvailable) {
            const messageContainer = $('.eq-availability-message');
            if (!messageContainer.length) {
                // Crear contenedor si no existe
                $('.hp-form--booking-make').prepend(`
                    <div class="eq-availability-message ${isAvailable ? 'available' : 'not-available'}">
                        ${isAvailable ? 
                            eqCartData.i18n.dateAvailable : 
                            eqCartData.i18n.dateNotAvailable
                        }
                    </div>
                `);
            } else {
                // Actualizar mensaje existente
                messageContainer
                    .removeClass('available not-available')
                    .addClass(isAvailable ? 'available' : 'not-available')
                    .text(isAvailable ? 
                        eqCartData.i18n.dateAvailable : 
                        eqCartData.i18n.dateNotAvailable
                    );
            }
        }
    }

    // Inicializar cuando el documento esté listo
    $(document).ready(() => {
        window.singleListingIntegration = new SingleListingIntegration();
    });

})(jQuery);