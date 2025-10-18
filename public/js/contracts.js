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
            $(this).closest('.eq-modal').hide();
        });

        // Close modal when clicking outside
        $('.eq-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
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

        // Preview button
        $(document).on('click', '.eq-contract-preview', function() {
            previewContract();
        });

        // Success actions
        $(document).on('click', '#eq-contract-send', function() {
            sendContract();
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
                    populateContractForm();
                    $('#eq-contract-modal').show();
                    
                    // Re-bind events after modal is shown
                    setTimeout(() => {
                        bindContractEvents();
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
            $('#eq-company-name').val(contractData.company_data.name || '');
            $('#eq-company-address').val(contractData.company_data.address || '');
            $('#eq-company-phone').val(contractData.company_data.phone || '');
            $('#eq-company-email').val(contractData.company_data.email || '');
            $('#eq-company-rfc').val(contractData.company_data.rfc || '');
        }

        // Client data
        if (contractData.client_data) {
            $('#eq-client-name').val(contractData.client_data.name || '');
            $('#eq-client-address').val(contractData.client_data.address || '');
            $('#eq-client-phone').val(contractData.client_data.phone || '');
            $('#eq-client-email').val(contractData.client_data.email || '');
        }

        // Event data
        if (contractData.event_data) {
            $('#eq-event-date').val(contractData.event_data.date || '');
            $('#eq-event-location').val(contractData.event_data.location || '');
            $('#eq-event-guests').val(contractData.event_data.guests || '');
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
            $('.eq-contract-total').text(contractData.cart_total);
        }

        // Load payment templates
        if (contractData.payment_templates) {
            populatePaymentTemplates(contractData.payment_templates);
        }

        // Validate initial event date
        if (contractData.event_data && contractData.event_data.date) {
            validateEventDate(contractData.event_data.date);
        }
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
                        <input type="number" class="eq-payment-amount" value="" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="eq-field-group">
                        <label>Percentage (%)</label>
                        <input type="number" class="eq-payment-percentage" value="" step="0.01" min="0" max="100" placeholder="0.00">
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

        $('#eq-payment-schedule-items .eq-payment-item').each(function() {
            const $item = $(this);
            const amount = parseFloat($item.find('.eq-payment-amount').val()) || 0;
            const percentage = parseFloat($item.find('.eq-payment-percentage').val()) || 0;
            const date = $item.find('.eq-payment-date').val();
            const description = $item.find('.eq-payment-description').val();

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
        $(`.eq-contract-tab-nav li[data-tab="${tabName}"]`).addClass('active');
        
        // Update content
        $('.eq-contract-tab-content').removeClass('active');
        $(`.eq-contract-tab-content[data-tab="${tabName}"]`).addClass('active');
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

        // Show loading
        showContractLoading();

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
            client_address: $('#eq-client-address').val(),
            client_phone: $('#eq-client-phone').val(),
            client_email: $('#eq-client-email').val(),
            
            // Event data
            event_date: $('#eq-event-date').val(),
            event_start_time: $('#eq-event-start-time').val(),
            event_end_time: $('#eq-event-end-time').val(),
            event_location: $('#eq-event-location').val(),
            event_guests: $('#eq-event-guests').val(),
            
            // Payment schedule
            payment_schedule: JSON.stringify(paymentSchedule),
            
            // Terms and bank
            contract_terms: $('#eq-contract-terms').val(),
            bank_name: $('#eq-bank-name').val(),
            bank_account: $('#eq-bank-account').val(),
            bank_clabe: $('#eq-bank-clabe').val()
        };

        $.ajax({
            url: eqCartData.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                hideContractLoading();
                
                if (response.success) {
                    showContractSuccess(response.data);
                } else {
                    showNotification('error', response.data || 'Error generating contract');
                    showContractForm();
                }
            },
            error: function() {
                hideContractLoading();
                showNotification('error', 'Network error. Please try again.');
                showContractForm();
            }
        });
    }

    /**
     * Validate contract form
     */
    function validateContractForm() {
        let isValid = true;
        let missingFields = [];
        const requiredFields = [
            { selector: '#eq-company-name', label: 'Company Name', tab: 'company' },
            { selector: '#eq-company-address', label: 'Company Address', tab: 'company' },
            { selector: '#eq-company-phone', label: 'Company Phone', tab: 'company' },
            { selector: '#eq-company-email', label: 'Company Email', tab: 'company' },
            { selector: '#eq-client-name', label: 'Client Name', tab: 'client' },
            { selector: '#eq-client-address', label: 'Client Address', tab: 'client' },
            { selector: '#eq-event-date', label: 'Event Date', tab: 'event' },
            { selector: '#eq-event-location', label: 'Event Location', tab: 'event' }
        ];

        requiredFields.forEach(function(field) {
            const $field = $(field.selector);
            const $formGroup = $field.closest('.eq-form-group');
            const fieldValue = $field.val().trim();
            
            if (!fieldValue) {
                $field.addClass('error');
                $formGroup.addClass('error');
                missingFields.push(field);
                isValid = false;
            } else {
                $field.removeClass('error');
                $formGroup.removeClass('error');
            }
        });

        // Validate payment schedule
        if (paymentSchedule.length === 0) {
            showValidationNotice('error', 'At least one payment is required');
            switchContractTab('payment');
            isValid = false;
        }

        // Validate payment totals
        const totalScheduled = paymentSchedule.reduce((sum, payment) => sum + payment.amount, 0);
        const contractTotal = contractData.cart_total_raw || 0;
        const difference = Math.abs(contractTotal - totalScheduled);
        
        if (difference > 0.01) {
            showValidationNotice('error', 'Payment schedule must equal contract total');
            switchContractTab('payment');
            isValid = false;
        }

        if (!isValid) {
            // Show specific error message with missing fields
            if (missingFields.length > 0) {
                const firstMissingField = missingFields[0];
                switchContractTab(firstMissingField.tab);
                
                // Wait for tab switch to complete before adding error message
                setTimeout(function() {
                    // Show error in modal
                    let errorHtml = '<div class="eq-validation-errors">';
                    errorHtml += '<p style="color: #e74c3c; font-weight: bold;">Please fill in the following required fields:</p>';
                    errorHtml += '<ul style="color: #e74c3c;">';
                    missingFields.forEach(function(field) {
                        errorHtml += '<li>' + field.label + '</li>';
                    });
                    errorHtml += '</ul></div>';
                    
                    // Remove any existing error message
                    $('.eq-validation-errors').remove();
                    
                    // Add error message to the active tab
                    $('.eq-contract-tab-content.active').prepend(errorHtml);
                    
                    // Auto-hide after 5 seconds
                    setTimeout(function() {
                        $('.eq-validation-errors').fadeOut(function() {
                            $(this).remove();
                        });
                    }, 5000);
                }, 100); // Small delay to ensure tab switch completes
            }
            
            showNotification('error', 'Please fill in all required fields');
        }

        return isValid;
    }

    /**
     * Show contract loading state
     */
    function showContractLoading() {
        $('#eq-contract-form').hide();
        $('#eq-contract-loading').show();
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
     * Preview contract
     */
    function previewContract() {
        try {
            // Don't validate for preview - show with whatever data is available
            // Collect form data
            const formData = collectFormData();
            
            // Check for popup blockers
            const previewWindow = window.open('', '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
            
            if (!previewWindow || previewWindow.closed || typeof previewWindow.closed == 'undefined') {
                // Popup was blocked
                showNotification('error', 'Popup blocked. Please allow popups for this site and try again.');
                return;
            }
            
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
            console.error('Error opening preview:', error);
            showNotification('error', 'Error opening preview. Please try again.');
        }
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
            event_location: $('#eq-event-location').val(),
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
                    console.log('Contract memory saved');
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
                    
                    console.log('Contract memory loaded');
                }
            }
        });
    }
    
    // Load memory when modal opens
    $(document).on('click', '[data-target="#eq-contract-modal"]', function() {
        setTimeout(loadContractMemory, 500); // Small delay to ensure modal is fully loaded
    });

})(jQuery);