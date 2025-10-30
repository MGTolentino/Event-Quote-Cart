/**
 * Event Quote Cart - Contracts functionality
 */
(function($) {
    'use strict';

    // Contract data storage
    let contractData = {};
    let paymentSchedule = [];
    let validationRules = {};

    // Initialize contracts functionality
    $(document).ready(function() {
        initContractModal();
        bindContractEvents();
    });

    /**
     * Initialize contract modal
     */
    function initContractModal() {
        // Bind contract generation button
        $('.eq-generate-contract').on('click', function(e) {
            e.preventDefault();
            openContractModal();
        });

        // Bind modal close
        $('.eq-modal-close').on('click', function() {
            const modal = $(this).closest('.eq-modal');
            if (modal.attr('id') === 'eq-contract-modal') {
                modal.removeClass('show');
            } else {
                modal.hide();
            }
        });

        // Close modal when clicking outside
        $('.eq-modal').on('click', function(e) {
            if (e.target === this) {
                if ($(this).attr('id') === 'eq-contract-modal') {
                    $(this).removeClass('show');
                } else {
                    $(this).hide();
                }
            }
        });
    }

    /**
     * Bind contract events
     */
    function bindContractEvents() {
        // Remove existing listeners to prevent duplicates
        $(document).off('click', '.eq-contract-tab-nav li');
        
        // Tab navigation - use direct method to avoid conflicts
        $('#eq-contract-modal .eq-contract-tab-nav li').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            switchContractTab($(this).data('tab'));
            return false;
        });
        
        // Add real-time field validation
        $(document).on('blur', '#eq-contract-form input[required], #eq-contract-form textarea[required]', function() {
            validateSingleField($(this));
            updateTabValidationStatus();
        });
        
        // Clear error on focus
        $(document).on('focus', '#eq-contract-form input.error, #eq-contract-form textarea.error', function() {
            const $field = $(this);
            const $formGroup = $field.closest('.eq-form-group');
            $field.removeClass('error');
            $formGroup.removeClass('error');
            $formGroup.find('.field-error-message').remove();
            updateTabValidationStatus();
        });

        // Form navigation buttons
        $(document).on('click', '.eq-contract-next', function() {
            goToNextTab();
        });

        $(document).on('click', '.eq-contract-prev', function() {
            goToPrevTab();
        });

        // Payment template selection
        $(document).on('change', '#eq-payment-template', function() {
            loadPaymentTemplate($(this).val());
        });

        // Add payment button
        $(document).on('click', '.eq-add-payment-btn', function() {
            addPaymentItem();
        });

        // Remove payment button
        $(document).on('click', '.eq-remove-payment', function() {
            removePaymentItem($(this));
        });

        // Payment schedule changes
        $(document).on('input change', '.eq-payment-item input, .eq-payment-item select', function() {
            updatePaymentSchedule();
            validatePaymentSchedule();
        });

        // Event date change
        $(document).on('change', '#eq-event-date', function() {
            validateEventDate($(this).val());
            updatePaymentSchedule();
        });

        // Contract form submission
        $(document).on('submit', '#eq-contract-form', function(e) {
            e.preventDefault();
            generateContract();
        });


        // Preview button - use more specific selector
        $('#eq-contract-modal').on('click', '.eq-contract-preview', function(e) {
            e.preventDefault();
            e.stopPropagation();
            previewContract();
        });
        
        // Backup selector for preview button
        $(document).on('click', 'button.eq-contract-preview', function(e) {
            e.preventDefault();
            e.stopPropagation();
            previewContract();
        });

        // Success actions
        $(document).on('click', '#eq-contract-send', function() {
            sendContract();
        });
        
        // Edit contract - go back to form with current data
        $('#eq-contract-modal').on('click', '#eq-edit-contract', function(e) {
            e.preventDefault();
            e.stopPropagation();
            editContract();
        });
        
        // Generate new contract - clear form and start fresh
        $('#eq-contract-modal').on('click', '#eq-generate-new-contract', function(e) {
            e.preventDefault();
            e.stopPropagation();
            generateNewContract();
        });
        
        // Backup selectors
        $(document).on('click', '#eq-edit-contract', function(e) {
            e.preventDefault();
            e.stopPropagation();
            editContract();
        });
        
        $(document).on('click', '#eq-generate-new-contract', function(e) {
            e.preventDefault();
            e.stopPropagation();
            generateNewContract();
        });
    }

    /**
     * Open contract modal and load data
     */
    function openContractModal() {
        showLoading('Loading contract data...');
        
        $.ajax({
            url: eqCartData.ajaxurl,
            type: 'POST',
            data: {
                action: 'eq_get_contract_data',
                nonce: eqCartData.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    contractData = response.data;
                    
                    // Debug: Log contract data to see what's being received
                    console.log('Contract Data Received:', contractData);
                    console.log('Cart Total Raw:', contractData.cart_total_raw);
                    console.log('Debug Calculation:', contractData.debug_total_calculation);
                    
                    populateContractForm();
                    $('#eq-contract-modal').addClass('show');
                    
                    // Re-bind events after modal is shown
                    setTimeout(() => {
                        bindContractEvents();
                        
                        // Also verify elements exist
                    }, 100);
                } else {
                    showNotification('error', response.data || 'Error loading contract data');
                }
            },
            error: function() {
                hideLoading();
                showNotification('error', 'Network error. Please try again.');
            }
        });
    }

    /**
     * Populate contract form with data
     */
    function populateContractForm() {
        // Company data
        if (contractData.company_data) {
            $('#eq-company-name').val(contractData.company_data.name || '').attr('required', true);
            $('#eq-company-address').val(contractData.company_data.address || '').attr('required', true);
            $('#eq-company-phone').val(contractData.company_data.phone || '').attr('required', true);
            $('#eq-company-email').val(contractData.company_data.email || '').attr('required', true);
            $('#eq-company-rfc').val(contractData.company_data.rfc || '');
        }

        // Client data
        if (contractData.client_data) {
            $('#eq-client-name').val(contractData.client_data.name || '').attr('required', true);
            $('#eq-client-phone').val(contractData.client_data.phone || '').attr('required', true);
            $('#eq-client-email').val(contractData.client_data.email || '').attr('required', true);
        }

        // Event data
        if (contractData.event_data) {
            $('#eq-event-date').val(contractData.event_data.date || '').attr('required', true);
            $('#eq-event-address').val(contractData.event_data.address || '').attr('required', true);
            $('#eq-event-guests').val(contractData.event_data.guests || '').attr('required', true);
            // Add required to time fields
            $('#eq-event-start-time').attr('required', true);
            $('#eq-event-end-time').attr('required', true);
        }

        // Contract terms
        if (contractData.contract_terms) {
            $('#eq-contract-terms').val(contractData.contract_terms);
        }

        // Bank data
        if (contractData.bank_data) {
            $('#eq-bank-name').val(contractData.bank_data.bank_name || '');
            $('#eq-bank-account').val(contractData.bank_data.account_number || '');
            $('#eq-bank-clabe').val(contractData.bank_data.clabe || '');
        }

        // Validation rules
        if (contractData.validation_rules) {
            validationRules = contractData.validation_rules;
        }

        // Contract total
        if (contractData.cart_total) {
            $('.eq-contract-total').text(decodeHtmlEntities(contractData.cart_total));
        }
        
        // Ensure cart_total_raw is available
        if (!contractData.cart_total_raw && contractData.cart_total) {
            // Fallback calculation if cart_total_raw is missing - decode HTML entities first
            const decodedTotal = decodeHtmlEntities(contractData.cart_total);
            const cleanTotal = decodedTotal.replace(/[$,]/g, '');
            contractData.cart_total_raw = parseFloat(cleanTotal) || 0;
        }

        // Load payment templates
        if (contractData.payment_templates) {
            populatePaymentTemplates(contractData.payment_templates);
        }

        // Validate initial event date
        if (contractData.event_data && contractData.event_data.date) {
            validateEventDate(contractData.event_data.date);
        }
        
        // Run initial validation after populating
        setTimeout(() => {
            updateTabValidationStatus();
        }, 100);
    }

    /**
     * Populate payment templates dropdown
     */
    function populatePaymentTemplates(templates) {
        const $select = $('#eq-payment-template');
        $select.empty();

        let defaultTemplate = null;

        templates.forEach(function(template) {
            const option = $('<option>').val(template.id).text(template.name);
            
            if (template.is_default) {
                option.attr('selected', true);
                defaultTemplate = template;
            }
            
            $select.append(option);
        });

        // Load default template
        if (defaultTemplate) {
            loadPaymentTemplate(defaultTemplate.id);
        } else if (templates.length > 0) {
            loadPaymentTemplate(templates[0].id);
        }
    }

    /**
     * Load payment template
     */
    function loadPaymentTemplate(templateId) {
        if (!contractData.payment_templates) return;

        const template = contractData.payment_templates.find(t => t.id === templateId);
        if (!template) return;

        // Clear existing payments
        $('#eq-payment-schedule-items').empty();
        paymentSchedule = [];

        // Check if template is valid for current event date but don't prevent loading
        const eventDate = $('#eq-event-date').val();
        if (eventDate && template.min_days_required && template.min_days_required > 0) {
            const daysUntilEvent = getDaysUntilEvent(eventDate);
            if (daysUntilEvent > 0 && daysUntilEvent < template.min_days_required) {
                showValidationNotice('warning', `This template typically requires ${template.min_days_required} days before the event. You have ${daysUntilEvent} days. Please adjust payment dates as needed.`);
                // Continue loading the template anyway
            }
        }

        // Add payments from template
        template.payments.forEach(function(payment, index) {
            addPaymentItemFromTemplate(payment, index);
        });

        updatePaymentSchedule();
        hideValidationNotice();
    }

    /**
     * Add payment item from template
     */
    function addPaymentItemFromTemplate(payment, index) {
        const $container = $('#eq-payment-schedule-items');
        const paymentId = 'payment_' + Date.now() + '_' + index;

        // Calculate payment date
        let paymentDate = '';
        if (payment.days_from_contract !== undefined) {
            const contractDate = new Date();
            contractDate.setDate(contractDate.getDate() + payment.days_from_contract);
            paymentDate = contractDate.toISOString().split('T')[0];
        } else if (payment.days_before_event !== undefined) {
            const eventDate = new Date($('#eq-event-date').val());
            if (!isNaN(eventDate.getTime())) {
                eventDate.setDate(eventDate.getDate() - payment.days_before_event);
                paymentDate = eventDate.toISOString().split('T')[0];
            }
        }

        // Calculate amount
        const totalAmount = contractData.cart_total_raw || 0;
        const amount = (totalAmount * payment.percentage / 100).toFixed(2);

        const html = `
            <div class="eq-payment-item" data-payment-id="${paymentId}">
                <div class="eq-payment-item-header">
                    <span class="eq-payment-number">Payment ${index + 1}</span>
                    <button type="button" class="eq-remove-payment">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="eq-payment-item-fields">
                    <div class="eq-field-group">
                        <label>Amount ($)</label>
                        <input type="number" class="eq-payment-amount" value="${amount}" step="0.01" min="0">
                    </div>
                    <div class="eq-field-group">
                        <label>Percentage (%)</label>
                        <input type="number" class="eq-payment-percentage" value="${payment.percentage}" step="0.01" min="0" max="100">
                    </div>
                    <div class="eq-field-group">
                        <label>Payment Date</label>
                        <input type="date" class="eq-payment-date" value="${paymentDate}">
                    </div>
                    <div class="eq-field-group">
                        <label>Description</label>
                        <input type="text" class="eq-payment-description" value="${payment.description || ''}" placeholder="Payment description">
                    </div>
                </div>
                <div class="eq-payment-status">
                    <i class="fas fa-check-circle eq-status-valid" style="display: none;"></i>
                    <i class="fas fa-exclamation-triangle eq-status-warning" style="display: none;"></i>
                    <i class="fas fa-times-circle eq-status-error" style="display: none;"></i>
                </div>
            </div>
        `;

        $container.append(html);
    }

    /**
     * Add new payment item
     */
    function addPaymentItem() {
        const $container = $('#eq-payment-schedule-items');
        const paymentIndex = $container.find('.eq-payment-item').length;
        const paymentId = 'payment_' + Date.now() + '_' + paymentIndex;

        // Default to total amount if this is the first payment and no existing payments
        const contractTotal = contractData.cart_total_raw || 0;
        const defaultAmount = (paymentIndex === 0 && contractTotal > 0) ? contractTotal.toFixed(2) : '';
        const defaultPercentage = (paymentIndex === 0 && contractTotal > 0) ? '100.00' : '';

        const html = `
            <div class="eq-payment-item" data-payment-id="${paymentId}">
                <div class="eq-payment-item-header">
                    <span class="eq-payment-number">Payment ${paymentIndex + 1}</span>
                    <button type="button" class="eq-remove-payment">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="eq-payment-item-fields">
                    <div class="eq-field-group">
                        <label>Amount ($)</label>
                        <input type="number" class="eq-payment-amount" value="${defaultAmount}" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="eq-field-group">
                        <label>Percentage (%)</label>
                        <input type="number" class="eq-payment-percentage" value="${defaultPercentage}" step="0.01" min="0" max="100" placeholder="0.00">
                    </div>
                    <div class="eq-field-group">
                        <label>Payment Date</label>
                        <input type="date" class="eq-payment-date" value="">
                    </div>
                    <div class="eq-field-group">
                        <label>Description</label>
                        <input type="text" class="eq-payment-description" value="" placeholder="Payment description">
                    </div>
                </div>
                <div class="eq-payment-status">
                    <i class="fas fa-check-circle eq-status-valid" style="display: none;"></i>
                    <i class="fas fa-exclamation-triangle eq-status-warning" style="display: none;"></i>
                    <i class="fas fa-times-circle eq-status-error" style="display: none;"></i>
                </div>
            </div>
        `;

        $container.append(html);
        updatePaymentNumbers();
        updatePaymentSchedule(); // Update schedule to reflect the default values
    }

    /**
     * Remove payment item
     */
    function removePaymentItem($button) {
        $button.closest('.eq-payment-item').remove();
        updatePaymentNumbers();
        updatePaymentSchedule();
        validatePaymentSchedule();
    }

    /**
     * Update payment numbers
     */
    function updatePaymentNumbers() {
        $('#eq-payment-schedule-items .eq-payment-item').each(function(index) {
            $(this).find('.eq-payment-number').text('Payment ' + (index + 1));
        });
    }

    /**
     * Update payment schedule data
     */
    function updatePaymentSchedule() {
        paymentSchedule = [];
        let totalScheduled = 0;

        console.log('UpdatePaymentSchedule - contractData.cart_total_raw:', contractData.cart_total_raw);

        $('#eq-payment-schedule-items .eq-payment-item').each(function() {
            const $item = $(this);
            const amount = parseFloat($item.find('.eq-payment-amount').val()) || 0;
            const percentage = parseFloat($item.find('.eq-payment-percentage').val()) || 0;
            const date = $item.find('.eq-payment-date').val();
            const description = $item.find('.eq-payment-description').val();
            
            console.log('Payment item:', { amount, percentage, date, description });

            // Sync amount and percentage
            const totalAmount = contractData.cart_total_raw || 0;
            if (totalAmount > 0) {
                if ($item.find('.eq-payment-amount').is(':focus')) {
                    // Amount changed, update percentage
                    const newPercentage = (amount / totalAmount * 100).toFixed(2);
                    $item.find('.eq-payment-percentage').val(newPercentage);
                } else if ($item.find('.eq-payment-percentage').is(':focus')) {
                    // Percentage changed, update amount
                    const newAmount = (totalAmount * percentage / 100).toFixed(2);
                    $item.find('.eq-payment-amount').val(newAmount);
                }
            }

            totalScheduled += amount;

            paymentSchedule.push({
                amount: amount,
                amount_formatted: '$' + amount.toFixed(2),
                percentage: percentage,
                date: date,
                description: description
            });
        });

        // Update summary
        console.log('Final totals:', {
            totalScheduled: totalScheduled,
            contractTotal: contractData.cart_total_raw,
            difference: Math.abs((contractData.cart_total_raw || 0) - totalScheduled)
        });
        
        $('.eq-scheduled-total').text('$' + totalScheduled.toFixed(2));
        
        const contractTotal = contractData.cart_total_raw || 0;
        const difference = Math.abs(contractTotal - totalScheduled);
        
        if (difference > 0.01) {
            $('.eq-difference').show();
            $('.eq-payment-difference').text((contractTotal - totalScheduled >= 0 ? '+' : '-') + '$' + difference.toFixed(2));
        } else {
            $('.eq-difference').hide();
        }
    }

    /**
     * Validate event date and payment availability
     */
    function validateEventDate(eventDate) {
        if (!eventDate) return;

        const daysUntilEvent = getDaysUntilEvent(eventDate);
        const $templateSelect = $('#eq-payment-template');

        // Filter templates based on event date
        if (contractData.payment_templates) {
            $templateSelect.find('option').each(function() {
                const $option = $(this);
                const templateId = $option.val();
                const template = contractData.payment_templates.find(t => t.id === templateId);
                
                if (template && template.min_days_required && daysUntilEvent > 0 && template.min_days_required > daysUntilEvent) {
                    // Add warning but don't disable
                    $option.text(template.name + ' (⚠️ ' + Math.abs(daysUntilEvent) + ' days - may not be enough time)');
                } else if (template) {
                    $option.text(template.name);
                }
                
                // Never disable options - let user decide
                $option.prop('disabled', false);
            });
        }

        // Show warnings for close or past events
        if (daysUntilEvent < 0) {
            showValidationNotice('warning', `Event date is in the past (${Math.abs(daysUntilEvent)} days ago). Please verify the date is correct.`);
        } else if (daysUntilEvent === 0) {
            showValidationNotice('warning', 'Event is today. Only immediate payment is recommended.');
        } else if (daysUntilEvent <= 7) {
            showValidationNotice('warning', `Event is only ${daysUntilEvent} days away. Full payment is recommended.`);
        } else if (daysUntilEvent <= 30) {
            showValidationNotice('info', `Event is ${daysUntilEvent} days away. Consider using fewer payment installments.`);
        } else {
            hideValidationNotice();
        }
    }

    /**
     * Validate payment schedule
     */
    function validatePaymentSchedule() {
        const eventDate = $('#eq-event-date').val();
        if (!eventDate || paymentSchedule.length === 0) return;

        $.ajax({
            url: eqCartData.ajaxurl,
            type: 'POST',
            data: {
                action: 'eq_validate_payment_schedule',
                nonce: eqCartData.nonce,
                event_date: eventDate,
                payment_schedule: JSON.stringify(paymentSchedule)
            },
            success: function(response) {
                if (response.success) {
                    const validation = response.data;
                    
                    // Update individual payment statuses
                    updatePaymentItemStatuses(validation);
                    
                    // Show global validation messages
                    if (validation.errors && validation.errors.length > 0) {
                        showValidationNotice('error', validation.errors.join('<br>'));
                    } else if (validation.warnings && validation.warnings.length > 0) {
                        showValidationNotice('warning', validation.warnings.join('<br>'));
                    } else if (validation.valid) {
                        hideValidationNotice();
                    }
                }
            }
        });
    }

    /**
     * Update payment item statuses
     */
    function updatePaymentItemStatuses(validation) {
        $('#eq-payment-schedule-items .eq-payment-item').each(function(index) {
            const $item = $(this);
            const $status = $item.find('.eq-payment-status');
            const date = $item.find('.eq-payment-date').val();
            
            // Reset status
            $status.find('i').hide();
            
            if (!date) {
                $status.find('.eq-status-error').show().attr('title', 'Date required');
                return;
            }
            
            const paymentDate = new Date(date);
            const eventDate = new Date($('#eq-event-date').val());
            const today = new Date();
            
            if (paymentDate > eventDate) {
                $status.find('.eq-status-error').show().attr('title', 'Payment after event');
            } else if (paymentDate < today) {
                $status.find('.eq-status-error').show().attr('title', 'Date in the past');
            } else if (getDaysBetweenDates(paymentDate, eventDate) < (validationRules.min_days_before_event || 7)) {
                $status.find('.eq-status-warning').show().attr('title', 'Very close to event');
            } else {
                $status.find('.eq-status-valid').show().attr('title', 'Valid date');
            }
        });
    }

    /**
     * Switch contract tab
     */
    function switchContractTab(tabName) {
        
        // Update nav
        $('.eq-contract-tab-nav li').removeClass('active');
        const $tabNav = $(`.eq-contract-tab-nav li[data-tab="${tabName}"]`);
        $tabNav.addClass('active');
        
        // Update content
        $('.eq-contract-tab-content').removeClass('active');
        const $tabContent = $(`.eq-contract-tab-content[data-tab="${tabName}"]`);
        $tabContent.addClass('active');
        
    }

    /**
     * Go to next tab
     */
    function goToNextTab() {
        const currentTab = $('.eq-contract-tab-nav li.active').data('tab');
        const tabs = ['company', 'client', 'event', 'payment', 'terms'];
        const currentIndex = tabs.indexOf(currentTab);
        
        if (currentIndex < tabs.length - 1) {
            switchContractTab(tabs[currentIndex + 1]);
        }
    }

    /**
     * Go to previous tab
     */
    function goToPrevTab() {
        const currentTab = $('.eq-contract-tab-nav li.active').data('tab');
        const tabs = ['company', 'client', 'event', 'payment', 'terms'];
        const currentIndex = tabs.indexOf(currentTab);
        
        if (currentIndex > 0) {
            switchContractTab(tabs[currentIndex - 1]);
        }
    }

    /**
     * Generate contract
     */
    function generateContract() {
        // Validate form
        if (!validateContractForm()) {
            return;
        }

        // Show loading with progress
        showContractLoading();
        
        // Show progress indicator
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += 5;
            if (progress <= 90) {
                updateContractProgress(progress);
            }
        }, 200);

        // Prepare form data
        const formData = {
            action: 'eq_generate_contract_pdf',
            nonce: eqCartData.nonce,
            
            // Company data
            company_name: $('#eq-company-name').val(),
            company_address: $('#eq-company-address').val(),
            company_phone: $('#eq-company-phone').val(),
            company_email: $('#eq-company-email').val(),
            company_rfc: $('#eq-company-rfc').val(),
            
            // Client data
            client_name: $('#eq-client-name').val(),
            client_phone: $('#eq-client-phone').val(),
            client_email: $('#eq-client-email').val(),
            
            // Event data
            event_date: $('#eq-event-date').val(),
            event_start_time: $('#eq-event-start-time').val(),
            event_end_time: $('#eq-event-end-time').val(),
            event_address: $('#eq-event-address').val(),
            event_guests: $('#eq-event-guests').val(),
            
            // Payment schedule
            payment_schedule: JSON.stringify(paymentSchedule),
            
            // Terms and bank
            contract_terms: $('#eq-contract-terms').val(),
            bank_name: $('#eq-bank-name').val(),
            bank_account: $('#eq-bank-account').val(),
            bank_clabe: $('#eq-bank-clabe').val(),
            razon_social: $('#eq-razon-social').val()
        };

        $.ajax({
            url: eqCartData.ajaxurl,
            type: 'POST',
            data: formData,
            timeout: 60000, // 60 seconds timeout
            success: function(response) {
                clearInterval(progressInterval);
                updateContractProgress(100);
                
                setTimeout(() => {
                    hideContractLoading();
                    
                    if (response.success) {
                        showContractSuccess(response.data);
                    } else {
                        showNotification('error', response.data || 'Error generating contract');
                        showContractForm();
                    }
                }, 500);
            },
            error: function() {
                clearInterval(progressInterval);
                hideContractLoading();
                showNotification('error', 'Network error or timeout. Please try again.');
                showContractForm();
            }
        });
    }

    /**
     * Validate single field
     */
    function validateSingleField($field) {
        const $formGroup = $field.closest('.eq-form-group');
        const fieldValue = $field.val() ? $field.val().trim() : '';
        const isRequired = $field.attr('required') || $field.hasClass('required');
        
        if (isRequired && !fieldValue) {
            $field.addClass('error');
            $formGroup.addClass('error');
            if (!$formGroup.find('.field-error-message').length) {
                $formGroup.append('<span class="field-error-message">This field is required</span>');
            }
            return false;
        }
        
        // Email validation
        if ($field.attr('type') === 'email' && fieldValue) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(fieldValue)) {
                $field.addClass('error');
                $formGroup.addClass('error');
                if (!$formGroup.find('.field-error-message').length) {
                    $formGroup.append('<span class="field-error-message">Please enter a valid email address</span>');
                }
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Update tab validation status
     */
    function updateTabValidationStatus() {
        const tabs = ['company', 'client', 'event', 'payment', 'terms'];
        
        tabs.forEach(tab => {
            const $tabNav = $(`.eq-contract-tab-nav li[data-tab="${tab}"]`);
            const $tabContent = $(`.eq-contract-tab-content[data-tab="${tab}"]`);
            const hasErrors = $tabContent.find('.eq-form-group.error').length > 0;
            
            if (hasErrors) {
                markTabWithError($tabNav);
            } else {
                clearTabError($tabNav);
            }
        });
    }
    
    /**
     * Mark tab with error
     */
    function markTabWithError($tab) {
        if (!$tab.hasClass('has-error')) {
            $tab.addClass('has-error');
            $tab[0].style.setProperty('color', '#dc3545', 'important');
            $tab[0].style.setProperty('background-color', '#ffebee', 'important');
            
            if (!$tab.find('.error-indicator').length) {
                const indicator = $('<span class="error-indicator">!</span>');
                indicator.css({
                    'position': 'absolute',
                    'top': '5px',
                    'right': '10px',
                    'background': '#dc3545',
                    'color': 'white',
                    'width': '20px',
                    'height': '20px',
                    'border-radius': '50%',
                    'display': 'flex',
                    'align-items': 'center',
                    'justify-content': 'center',
                    'font-size': '12px',
                    'font-weight': 'bold'
                });
                $tab.append(indicator);
            }
        }
    }
    
    /**
     * Clear tab error
     */
    function clearTabError($tab) {
        $tab.removeClass('has-error');
        $tab[0].style.removeProperty('color');
        $tab[0].style.removeProperty('background-color');
        $tab.find('.error-indicator').remove();
    }
    
    /**
     * Validate contract form
     */
    function validateContractForm() {
        let isValid = true;
        let missingFields = [];
        let tabsWithErrors = new Set();
        
        const requiredFields = [
            { selector: '#eq-company-name', label: 'Company Name', tab: 'company' },
            { selector: '#eq-company-address', label: 'Company Address', tab: 'company' },
            { selector: '#eq-company-phone', label: 'Company Phone', tab: 'company' },
            { selector: '#eq-company-email', label: 'Company Email', tab: 'company' },
            { selector: '#eq-client-name', label: 'Client Name', tab: 'client' },
            { selector: '#eq-client-phone', label: 'Client Phone', tab: 'client' },
            { selector: '#eq-client-email', label: 'Client Email', tab: 'client' },
            { selector: '#eq-event-date', label: 'Event Date', tab: 'event' },
            { selector: '#eq-event-start-time', label: 'Event Start Time', tab: 'event' },
            { selector: '#eq-event-end-time', label: 'Event End Time', tab: 'event' },
            { selector: '#eq-event-address', label: 'Event Address', tab: 'event' },
            { selector: '#eq-event-guests', label: 'Event Guests', tab: 'event' }
        ];
        
        // Clear previous tab errors
        $('#eq-contract-modal .eq-contract-tab-nav li').removeClass('has-error').css('color', '').find('.error-indicator').remove();

        requiredFields.forEach(function(field) {
            const $field = $(field.selector);
            if ($field.length === 0) {
                return;
            }
            
            const isFieldValid = validateSingleField($field);
            if (!isFieldValid) {
                missingFields.push(field);
                tabsWithErrors.add(field.tab);
                isValid = false;
            }
        });
        
        // Update tab validation status immediately
        updateTabValidationStatus();

        // Validate payment schedule
        if (paymentSchedule.length === 0) {
            showValidationNotice('error', 'At least one payment is required');
            tabsWithErrors.add('payment');
            isValid = false;
        }

        // Validate payment totals
        const totalScheduled = paymentSchedule.reduce((sum, payment) => sum + payment.amount, 0);
        const contractTotal = contractData.cart_total_raw || 0;
        const difference = Math.abs(contractTotal - totalScheduled);
        
        console.log('Payment Validation Debug:', {
            totalScheduled: totalScheduled,
            contractTotal: contractTotal,
            cart_total_raw: contractData.cart_total_raw,
            difference: difference,
            paymentSchedule: paymentSchedule
        });
        
        if (difference > 0.01) {
            showValidationNotice('error', `Payment schedule must equal contract total. Scheduled: ${totalScheduled.toFixed(2)}, Contract: ${contractTotal.toFixed(2)}, Difference: ${difference.toFixed(2)}`);
            tabsWithErrors.add('payment');
            isValid = false;
        }

        if (!isValid) {
            // Get the current active tab
            const currentTab = $('.eq-contract-tab-content.active').data('tab');
            
            // Build error message by tab
            let errorsByTab = {};
            missingFields.forEach(function(field) {
                if (!errorsByTab[field.tab]) {
                    errorsByTab[field.tab] = [];
                }
                errorsByTab[field.tab].push(field.label);
            });
            
            // Create detailed error message
            let errorMessage = '<div class="eq-contract-error-notification">';
            errorMessage += '<i class="fas fa-exclamation-triangle"></i>';
            errorMessage += '<div>';
            errorMessage += '<strong>Please complete the following required fields:</strong>';
            
            // Show errors grouped by tab
            for (let tab in errorsByTab) {
                let tabLabel = $('.eq-contract-tab-nav li[data-tab="' + tab + '"]').text();
                errorMessage += '<div style="margin-top: 10px;"><strong>' + tabLabel + ':</strong>';
                errorMessage += '<ul style="margin: 5px 0 0 20px;">';
                errorsByTab[tab].forEach(function(field) {
                    errorMessage += '<li>' + field + '</li>';
                });
                errorMessage += '</ul></div>';
            }
            
            if (paymentSchedule.length === 0 || tabsWithErrors.has('payment')) {
                errorMessage += '<div style="margin-top: 10px;"><strong>Payment Schedule:</strong>';
                errorMessage += '<ul style="margin: 5px 0 0 20px;">';
                if (paymentSchedule.length === 0) {
                    errorMessage += '<li>At least one payment is required</li>';
                }
                const totalScheduled = paymentSchedule.reduce((sum, payment) => sum + payment.amount, 0);
                const contractTotal = contractData.cart_total_raw || 0;
                const difference = Math.abs(contractTotal - totalScheduled);
                if (difference > 0.01) {
                    errorMessage += '<li>Payment schedule must equal contract total</li>';
                }
                errorMessage += '</ul></div>';
            }
            
            errorMessage += '</div></div>';
            
            // Remove any existing error message
            $('.eq-contract-error-notification').remove();
            
            // If not on tab with errors, switch to first tab with errors
            if (!tabsWithErrors.has(currentTab) && missingFields.length > 0) {
                const firstErrorTab = missingFields[0].tab;
                switchContractTab(firstErrorTab);
            }
            
            // Add error message at the top of modal content
            $('#eq-contract-form').prepend(errorMessage);
            
            // Scroll to top of modal to show error
            $('.eq-contract-modal-content').animate({ scrollTop: 0 }, 300);
            
            showNotification('error', 'Please fill in all required fields marked with (!)');
        }

        return isValid;
    }

    /**
     * Show contract loading state
     */
    function showContractLoading() {
        $('#eq-contract-form').hide();
        $('#eq-contract-loading').show();
        
        // Add progress bar if it doesn't exist
        if (!$('#eq-contract-progress').length) {
            $('#eq-contract-loading').append(`
                <div id="eq-contract-progress" style="margin-top: 20px;">
                    <div style="background: #f0f0f0; border-radius: 10px; overflow: hidden; height: 20px;">
                        <div id="eq-progress-bar" style="background: #007cba; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                    </div>
                    <p id="eq-progress-text" style="text-align: center; margin-top: 10px; color: #666;">Preparing contract...</p>
                </div>
            `);
        }
    }

    /**
     * Update contract progress
     */
    function updateContractProgress(percentage) {
        $('#eq-progress-bar').css('width', percentage + '%');
        
        let message = 'Preparing contract...';
        if (percentage > 20) message = 'Processing cart data...';
        if (percentage > 40) message = 'Generating PDF...';
        if (percentage > 70) message = 'Saving contract...';
        if (percentage >= 100) message = 'Complete!';
        
        $('#eq-progress-text').text(message);
    }

    /**
     * Hide contract loading state
     */
    function hideContractLoading() {
        $('#eq-contract-loading').hide();
    }

    /**
     * Show contract form
     */
    function showContractForm() {
        $('#eq-contract-success').hide();
        $('#eq-contract-loading').hide();
        $('#eq-contract-form').show();
    }

    /**
     * Show contract success
     */
    function showContractSuccess(data) {
        $('#eq-contract-form').hide();
        $('#eq-contract-loading').hide();
        
        if (data.pdf_url) {
            $('#eq-contract-download').attr('href', data.pdf_url);
        }
        
        // Add "Generate Another Contract" button if it doesn't exist
        if (!$('#eq-generate-another-contract').length) {
            const generateAnotherBtn = $('<button>', {
                id: 'eq-generate-another-contract',
                type: 'button',
                class: 'button button-secondary',
                text: 'Generate Another Contract',
                style: 'margin-left: 10px;'
            });
            
            generateAnotherBtn.on('click', function() {
                showContractForm();
                // Clear previous form data but keep company data for same vendor
                if (typeof saveContractMemory === 'function') {
                    saveContractMemory();
                }
            });
            
            $('#eq-contract-success .eq-contract-actions').append(generateAnotherBtn);
        }
        
        $('#eq-contract-success').show();
    }

    /**
     * Send contract to client
     */
    function sendContract() {
        // Implementation for sending contract via email/WhatsApp
        showNotification('info', 'Send contract functionality to be implemented');
    }
    
    /**
     * Edit current contract - go back to form with existing data
     */
    function editContract() {
        try {
            // Clear any error notifications
            $('.eq-contract-error-notification').remove();
            $('.eq-contract-tab-nav li').removeClass('has-error').css('color', '').find('.error-indicator').remove();
            
            showContractForm();
            
            // Reset tab to first one for clean editing experience
            switchContractTab('company');
            
            showNotification('info', 'You can now edit the contract details and regenerate');
        } catch (error) {
            showNotification('error', 'Error opening contract for editing');
        }
    }
    
    /**
     * Generate new contract - clear form and start fresh but keep cart data
     */
    function generateNewContract() {
        try {
            
            // Save cart data before clearing
            const savedCartData = contractData.cart_items ? {
                cart_items: contractData.cart_items,
                cart_totals: contractData.cart_totals,
                context: contractData.context
            } : {};
            
            // Clear form data
            $('#eq-contract-form')[0].reset();
            
            // Clear payment schedule but keep cart data
            paymentSchedule = [];
            
            // Restore cart data but clear contract-specific data
            contractData = {
                ...savedCartData,
                // Keep the base information that should persist
                cart_items: savedCartData.cart_items,
                cart_totals: savedCartData.cart_totals,
                context: savedCartData.context
            };
            
            // Clear validation errors
            $('.eq-form-group').removeClass('error');
            $('.field-error-message').remove();
            $('.eq-contract-error-notification').remove();
            $('.eq-contract-tab-nav li').removeClass('has-error').css('color', '').find('.error-indicator').remove();
            
            // Show form and go to first tab
            showContractForm();
            switchContractTab('company');
            
            // Re-populate the form with fresh cart data
            setTimeout(() => {
                // If we don't have cart data, reload it
                if (!contractData.cart_items) {
                    openContractModal();
                } else {
                    populateContractForm();
                }
            }, 100);
            
            showNotification('success', 'New contract form ready with current cart data');
        } catch (error) {
            showNotification('error', 'Error preparing new contract form');
        }
    }

    /**
     * Preview contract
     */
    function previewContract() {
        try {
            // Don't validate for preview - show with whatever data is available
            // Collect form data
            const formData = collectFormData();
            
            // Directly show inline preview (simpler approach)
            showInlinePreview(formData);
            
        } catch (error) {
            showNotification('error', 'Error generating preview: ' + error.message);
        }
    }
    
    /**
     * Show inline preview as fallback
     */
    function showInlinePreview(formData) {
        try {
            // Skip popup, go directly to modal preview for reliability
            showModalPreview(formData);
            return;
            
            previewWindow.document.write(`
            <html>
            <head><title>Contract Preview</title></head>
            <body style="font-family: Arial, sans-serif; padding: 20px; line-height: 1.6;">
                <h1>Contract Preview</h1>
                <h2>Company Information</h2>
                <p><strong>Name:</strong> ${formData.company_name}</p>
                <p><strong>Address:</strong> ${formData.company_address}</p>
                <p><strong>Phone:</strong> ${formData.company_phone}</p>
                <p><strong>Email:</strong> ${formData.company_email}</p>
                
                <h2>Client Information</h2>
                <p><strong>Name:</strong> ${formData.client_name}</p>
                <p><strong>Address:</strong> ${formData.client_address}</p>
                <p><strong>Phone:</strong> ${formData.client_phone}</p>
                <p><strong>Email:</strong> ${formData.client_email}</p>
                
                <h2>Event Details</h2>
                <p><strong>Date:</strong> ${formData.event_date}</p>
                <p><strong>Time:</strong> ${formData.event_start_time} - ${formData.event_end_time}</p>
                <p><strong>Location:</strong> ${formData.event_location}</p>
                <p><strong>Guests:</strong> ${formData.event_guests}</p>
                
                <h2>Contract Terms</h2>
                <p>${formData.contract_terms.replace(/\n/g, '<br>')}</p>
                
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Close</button>
            </body>
            </html>
        `);
        previewWindow.document.close();
        
        // Focus the new window
        previewWindow.focus();
        
        } catch (error) {
            showNotification('error', 'Error opening preview. Please try again.');
        }
    }
    
    /**
     * Show modal preview when popups are blocked
     */
    function showModalPreview(formData) {
        // Create preview modal if it doesn't exist
        if ($('#eq-preview-modal').length === 0) {
            $('body').append(`
                <div id="eq-preview-modal" class="eq-modal">
                    <div class="eq-modal-content eq-preview-modal-content">
                        <span class="eq-modal-close">&times;</span>
                        <h2>Contract Preview</h2>
                        <div id="eq-preview-content"></div>
                        <div class="eq-preview-actions">
                            <button class="eq-btn eq-btn-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button class="eq-btn eq-btn-secondary eq-close-preview">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            `);
            
            // Bind close events
            $('#eq-preview-modal .eq-modal-close, #eq-preview-modal .eq-close-preview').on('click', function() {
                $('#eq-preview-modal').hide();
            });
            
        }
        
        // Generate preview content in contract format
        const previewContent = `
            <div class="eq-contract-preview-content" style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; line-height: 1.6;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #2c3e50; margin-bottom: 10px;">CONTRATO DE PRESTACIÓN DE SERVICIOS</h1>
                    <p style="margin: 0;">No. ${Date.now()}</p>
                </div>
                
                <div style="margin-bottom: 25px;">
                    <h3 style="color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 5px;">DATOS DE LA EMPRESA</h3>
                    <p><strong>Nombre:</strong> ${formData.company_name || '[NOMBRE DE LA EMPRESA]'}</p>
                    <p><strong>Dirección:</strong> ${formData.company_address || '[DIRECCIÓN DE LA EMPRESA]'}</p>
                    <p><strong>Teléfono:</strong> ${formData.company_phone || '[TELÉFONO]'}</p>
                    <p><strong>Email:</strong> ${formData.company_email || '[EMAIL]'}</p>
                </div>
                
                <div style="margin-bottom: 25px;">
                    <h3 style="color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 5px;">DATOS DEL CLIENTE</h3>
                    <p><strong>Nombre:</strong> ${formData.client_name || '[NOMBRE DEL CLIENTE]'}</p>
                    <p><strong>Dirección:</strong> ${formData.client_address || '[DIRECCIÓN DEL CLIENTE]'}</p>
                    <p><strong>Teléfono:</strong> ${formData.client_phone || '[TELÉFONO DEL CLIENTE]'}</p>
                    <p><strong>Email:</strong> ${formData.client_email || '[EMAIL DEL CLIENTE]'}</p>
                </div>
                
                <div style="margin-bottom: 25px;">
                    <h3 style="color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 5px;">DETALLES DEL EVENTO</h3>
                    <p><strong>Fecha:</strong> ${formData.event_date || '[FECHA DEL EVENTO]'}</p>
                    <p><strong>Hora:</strong> ${formData.event_start_time || '[HORA INICIO]'} - ${formData.event_end_time || '[HORA FIN]'}</p>
                    <p><strong>Lugar:</strong> ${formData.event_location || '[LUGAR DEL EVENTO]'}</p>
                    <p><strong>Número de Invitados:</strong> ${formData.event_guests || '[NÚMERO DE INVITADOS]'}</p>
                </div>
                
                <div style="margin-bottom: 25px;">
                    <h3 style="color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 5px;">SERVICIOS CONTRATADOS</h3>
                    ${generateServicesPreview()}
                </div>
                
                <div style="margin-bottom: 25px;">
                    <h3 style="color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 5px;">PROGRAMACIÓN DE PAGOS</h3>
                    ${generatePaymentSchedulePreviewForContract()}
                </div>
                
                <div style="margin-bottom: 25px;">
                    <h3 style="color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 5px;">TÉRMINOS Y CONDICIONES</h3>
                    <div style="text-align: justify;">
                        ${(formData.contract_terms || 'Se aplicarán los términos y condiciones estándar.').replace(/\n/g, '<br>')}
                    </div>
                </div>
                
                <div style="margin-top: 50px;">
                    <div style="display: flex; justify-content: space-between;">
                        <div style="text-align: center; width: 45%;">
                            <div style="border-bottom: 1px solid #000; margin-bottom: 10px; height: 50px;"></div>
                            <p><strong>FIRMA DEL CONTRATANTE</strong></p>
                            <p>${formData.client_name || '[NOMBRE DEL CLIENTE]'}</p>
                        </div>
                        <div style="text-align: center; width: 45%;">
                            <div style="border-bottom: 1px solid #000; margin-bottom: 10px; height: 50px;"></div>
                            <p><strong>FIRMA DE LA EMPRESA</strong></p>
                            <p>${formData.company_name || '[NOMBRE DE LA EMPRESA]'}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#eq-preview-content').html(previewContent);
        $('#eq-preview-modal').show();
    }
    
    /**
     * Generate payment schedule preview HTML
     */
    function generatePaymentSchedulePreview() {
        if (paymentSchedule.length === 0) {
            return '<p>No payment schedule defined</p>';
        }
        
        let html = '<table class="eq-payment-schedule-preview"><thead><tr>';
        html += '<th>Payment</th><th>Amount</th><th>Date</th><th>Description</th>';
        html += '</tr></thead><tbody>';
        
        paymentSchedule.forEach((payment, index) => {
            html += `<tr>
                <td>Payment ${index + 1}</td>
                <td>${formatCurrency(payment.amount)}</td>
                <td>${payment.date || 'Not set'}</td>
                <td>${payment.description || ''}</td>
            </tr>`;
        });
        
        html += '</tbody></table>';
        return html;
    }
    
    /**
     * Generate services preview for contract using same logic as generate quote
     */
    function generateServicesPreview() {
        if (!contractData || !contractData.cart_items) {
            return '<p>Los servicios contratados aparecerán aquí basados en el carrito actual.</p>';
        }
        
        let html = '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
        html += '<thead><tr style="background-color: #f8f9fa;">';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Título</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Descripción de Servicios Contratados</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Cantidad</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Precio</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Sub Total</th>';
        html += '</tr></thead><tbody>';
        
        contractData.cart_items.forEach(item => {
            // Calcular precio base sin impuestos (como en PDF handler)
            const tax_rate = contractData.tax_rate || 16; // Default 16%
            const item_unit_price_with_tax = parseFloat(item.base_price) || 0;
            const item_unit_price_without_tax = item_unit_price_with_tax / (1 + (tax_rate / 100));
            const item_subtotal = item_unit_price_without_tax * (item.quantity || 1);
            
            // Handle date display like PDF handler
            let date_display = '';
            if (item.is_date_range && item.start_date && item.end_date) {
                date_display = `<br><strong>Fecha del evento:</strong> ${item.start_date} a ${item.end_date}`;
            } else if (item.date) {
                date_display = `<br><strong>Fecha del evento:</strong> ${item.date}`;
            }
            
            // Handle quantity display like PDF handler
            let quantity_display = '';
            if (item.is_date_range && item.days_count) {
                quantity_display = item.days_count;
            } else {
                quantity_display = item.quantity || 1;
            }
            
            html += `<tr>
                <td style="border: 1px solid #ddd; padding: 8px;"><strong>${item.title || 'Servicio'}</strong></td>
                <td style="border: 1px solid #ddd; padding: 8px;">
                    ${item.description || ''}
                    ${date_display}
                </td>
                <td style="border: 1px solid #ddd; padding: 8px;">${quantity_display}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${formatCurrency(item_unit_price_without_tax)}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${formatCurrency(item_subtotal)}</td>
            </tr>`;
            
            // Add extras if any (separate rows like in PDF)
            if (item.extras && item.extras.length > 0) {
                item.extras.forEach(extra => {
                    let extra_price = 0;
                    let display_quantity = item.quantity;
                    
                    // Same logic as PDF handler for extra pricing
                    switch(extra.type) {
                        case 'per_quantity':
                            extra_price = extra.price * item.quantity;
                            break;
                        case 'per_order':
                        case 'per_booking':
                        case 'per_item':
                            extra_price = extra.price;
                            display_quantity = 1;
                            break;
                        default:
                            extra_price = extra.price * item.quantity;
                    }
                    
                    html += `<tr>
                        <td style="border: 1px solid #ddd; padding: 8px;">${extra.name || 'Extra'} by ${item.title}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">${extra.description || ''}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">${display_quantity}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">${formatCurrency(extra.price)}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">${formatCurrency(extra_price)}</td>
                    </tr>`;
                });
            }
        });
        
        html += '</tbody></table>';
        
        if (contractData.cart_totals) {
            html += '<div style="text-align: right; margin-top: 15px;">';
            html += `<p><strong>Subtotal: ${decodeHtmlEntities(contractData.cart_totals.subtotal) || '$0.00'}</strong></p>`;
            html += `<p><strong>IVA (${contractData.tax_rate || 16}%): ${decodeHtmlEntities(contractData.cart_totals.tax) || '$0.00'}</strong></p>`;
            html += `<p style="font-size: 1.2em; color: #2c3e50;"><strong>Total: ${decodeHtmlEntities(contractData.cart_totals.total) || '$0.00'}</strong></p>`;
            html += '</div>';
        }
        
        return html;
    }
    
    /**
     * Generate payment schedule preview for contract format
     */
    function generatePaymentSchedulePreviewForContract() {
        if (paymentSchedule.length === 0) {
            return '<p>La programación de pagos se definirá según lo acordado.</p>';
        }
        
        let html = '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
        html += '<thead><tr style="background-color: #f8f9fa;">';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Fecha</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Cantidad</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Descripción</th>';
        html += '</tr></thead><tbody>';
        
        paymentSchedule.forEach((payment, index) => {
            html += `<tr>
                <td style="border: 1px solid #ddd; padding: 8px;">${payment.date || 'Por definir'}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${formatCurrency(payment.amount)}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${payment.description || `Pago ${index + 1}`}</td>
            </tr>`;
        });
        
        html += '</tbody></table>';
        return html;
    }
    
    /**
     * Format currency
     */
    function formatCurrency(amount) {
        if (!amount || isNaN(amount)) return '$0.00';
        return '$' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    function decodeHtmlEntities(text) {
        if (typeof text !== 'string') return text;
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }
    
    function collectFormData() {
        return {
            company_name: $('#eq-company-name').val(),
            company_address: $('#eq-company-address').val(),
            company_phone: $('#eq-company-phone').val(),
            company_email: $('#eq-company-email').val(),
            client_name: $('#eq-client-name').val(),
            client_address: $('#eq-client-address').val(),
            client_phone: $('#eq-client-phone').val(),
            client_email: $('#eq-client-email').val(),
            event_date: $('#eq-event-date').val(),
            event_start_time: $('#eq-event-start-time').val(),
            event_end_time: $('#eq-event-end-time').val(),
            event_address: $('#eq-event-address').val(),
            event_guests: $('#eq-event-guests').val(),
            contract_terms: $('#eq-contract-terms').val()
        };
    }

    /**
     * Show validation notice
     */
    function showValidationNotice(type, message) {
        const $notice = $('.eq-payment-validation-notice');
        const $message = $notice.find('.eq-validation-message');
        
        $notice.removeClass('error warning info').addClass(type);
        $message.html(message);
        $notice.show();
    }

    /**
     * Hide validation notice
     */
    function hideValidationNotice() {
        $('.eq-payment-validation-notice').hide();
    }

    /**
     * Show notification
     */
    function showNotification(type, message) {
        // Create notification element
        const $notification = $(`
            <div class="eq-notification eq-notification-${type}">
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                <span>${message}</span>
                <button type="button" class="eq-notification-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `);

        // Add to page
        $('body').append($notification);

        // Auto hide after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Manual close
        $notification.find('.eq-notification-close').on('click', function() {
            $notification.remove();
        });
    }

    /**
     * Show loading overlay
     */
    function showLoading(message) {
        const $loading = $(`
            <div class="eq-loading-overlay">
                <div class="eq-loading-content">
                    <div class="eq-spinner"></div>
                    <p>${message}</p>
                </div>
            </div>
        `);

        $('body').append($loading);
    }

    /**
     * Hide loading overlay
     */
    function hideLoading() {
        $('.eq-loading-overlay').remove();
    }

    /**
     * Utility: Get days until event
     */
    function getDaysUntilEvent(eventDate) {
        const today = new Date();
        const event = new Date(eventDate);
        const diffTime = event.getTime() - today.getTime();
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    }

    /**
     * Utility: Get days between two dates
     */
    function getDaysBetweenDates(date1, date2) {
        const diffTime = Math.abs(date2.getTime() - date1.getTime());
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    }

    /**
     * Save contract memory for this vendor/lead/event combination
     */
    function saveContractMemory() {
        if (typeof contractData === 'undefined' || !contractData) return;
        
        const memoryData = {
            company_data: {
                name: $('#eq-company-name').val(),
                address: $('#eq-company-address').val(),
                phone: $('#eq-company-phone').val(),
                email: $('#eq-company-email').val()
            },
            bank_data: {
                bank_name: $('#eq-bank-name').val(),
                account_number: $('#eq-account-number').val(),
                routing_number: $('#eq-routing-number').val(),
                account_type: $('#eq-account-type').val()
            },
            contract_terms: $('#eq-contract-terms').val(),
            timestamp: Date.now()
        };
        
        // Save to server via AJAX
        $.ajax({
            url: contractData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'eq_save_contract_memory',
                nonce: contractData.nonce,
                memory_data: JSON.stringify(memoryData)
            },
            success: function(response) {
                if (response.success) {
                }
            }
        });
    }
    
    /**
     * Load contract memory for this vendor/lead/event combination
     */
    function loadContractMemory() {
        if (typeof contractData === 'undefined' || !contractData) return;
        
        $.ajax({
            url: contractData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'eq_load_contract_memory',
                nonce: contractData.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const data = response.data;
                    
                    // Fill company data
                    if (data.company_data) {
                        $('#eq-company-name').val(data.company_data.name || '');
                        $('#eq-company-address').val(data.company_data.address || '');
                        $('#eq-company-phone').val(data.company_data.phone || '');
                        $('#eq-company-email').val(data.company_data.email || '');
                    }
                    
                    // Fill bank data
                    if (data.bank_data) {
                        $('#eq-bank-name').val(data.bank_data.bank_name || '');
                        $('#eq-account-number').val(data.bank_data.account_number || '');
                        $('#eq-routing-number').val(data.bank_data.routing_number || '');
                        $('#eq-account-type').val(data.bank_data.account_type || '');
                    }
                    
                    // Fill contract terms
                    if (data.contract_terms) {
                        $('#eq-contract-terms').val(data.contract_terms);
                    }
                    
                }
            }
        });
    }
    
    /**
     * Load vendor data from Vendor Dashboard Pro
     */
    function loadVendorData() {
        $.ajax({
            url: eqCartData.ajaxurl,
            type: 'POST',
            data: {
                action: 'eq_get_vendor_contract_data',
                nonce: eqCartData.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const data = response.data;
                    
                    // Pre-populate razon social if available and field is empty
                    if (data.razon_social && !$('#eq-razon-social').val()) {
                        $('#eq-razon-social').val(data.razon_social);
                    }
                }
            }
        });
    }

    
    // Load memory when modal opens
    $(document).on('click', '[data-target="#eq-contract-modal"]', function() {
        setTimeout(function() {
            loadContractMemory();
            loadVendorData();
        }, 500); // Small delay to ensure modal is fully loaded
    });

})(jQuery);