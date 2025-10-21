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
        console.log('Contract JS ready, initializing...');
        initContractModal();
        bindContractEvents();
        
        // Debug: Check if jQuery is working
        console.log('jQuery version:', $.fn.jquery);
        console.log('Contract modal exists:', $('#eq-contract-modal').length > 0);
        
        // Add global click handler to debug button clicks
        $(document).on('click', '*', function(e) {
            const element = $(this);
            if (element.is('#eq-edit-contract') || element.is('#eq-generate-new-contract') || element.hasClass('eq-contract-preview')) {
                console.log('GLOBAL CLICK DETECTED:');
                console.log('- Element ID:', element.attr('id'));
                console.log('- Element classes:', element.attr('class'));
                console.log('- Element visible:', element.is(':visible'));
                console.log('- Element parent visible:', element.parent().is(':visible'));
                console.log('- Event propagation stopped?', e.isPropagationStopped());
                console.log('- Event default prevented?', e.isDefaultPrevented());
            }
        });
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

        // Preview button - use more specific selector
        $('#eq-contract-modal').on('click', '.eq-contract-preview', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Preview button clicked via modal selector');
            previewContract();
        });
        
        // Backup selector for preview button
        $(document).on('click', 'button.eq-contract-preview', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Preview button clicked via backup selector');
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
            console.log('Edit contract button clicked via modal selector');
            editContract();
        });
        
        // Generate new contract - clear form and start fresh
        $('#eq-contract-modal').on('click', '#eq-generate-new-contract', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Generate new contract button clicked via modal selector');
            generateNewContract();
        });
        
        // Backup selectors
        $(document).on('click', '#eq-edit-contract', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Edit contract button clicked via document selector');
            editContract();
        });
        
        $(document).on('click', '#eq-generate-new-contract', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Generate new contract button clicked via document selector');
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
                    populateContractForm();
                    $('#eq-contract-modal').show();
                    
                    // Re-bind events after modal is shown
                    setTimeout(() => {
                        console.log('Re-binding contract events...');
                        bindContractEvents();
                        
                        // Also verify elements exist
                        console.log('Preview button exists:', $('.eq-contract-preview').length > 0);
                        console.log('Edit contract button exists:', $('#eq-edit-contract').length > 0);
                        console.log('Generate new button exists:', $('#eq-generate-new-contract').length > 0);
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
        console.log('switchContractTab called with:', tabName);
        
        // Update nav
        $('.eq-contract-tab-nav li').removeClass('active');
        const $tabNav = $(`.eq-contract-tab-nav li[data-tab="${tabName}"]`);
        console.log('Tab nav element found:', $tabNav.length > 0);
        $tabNav.addClass('active');
        
        // Update content
        $('.eq-contract-tab-content').removeClass('active');
        const $tabContent = $(`.eq-contract-tab-content[data-tab="${tabName}"]`);
        console.log('Tab content element found:', $tabContent.length > 0);
        $tabContent.addClass('active');
        
        console.log('Tab switch completed');
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
            { selector: '#eq-client-address', label: 'Client Address', tab: 'client' },
            { selector: '#eq-event-date', label: 'Event Date', tab: 'event' },
            { selector: '#eq-event-location', label: 'Event Location', tab: 'event' }
        ];
        
        // Clear previous tab errors
        $('#eq-contract-modal .eq-contract-tab-nav li').removeClass('has-error').css('color', '').find('.error-indicator').remove();

        requiredFields.forEach(function(field) {
            const $field = $(field.selector);
            const $formGroup = $field.closest('.eq-form-group');
            const fieldValue = $field.val() ? $field.val().trim() : '';
            
            if (!fieldValue) {
                $field.addClass('error');
                $formGroup.addClass('error');
                
                // Add error message below field if not present
                if (!$formGroup.find('.field-error-message').length) {
                    $formGroup.append('<span class="field-error-message">This field is required</span>');
                }
                
                missingFields.push(field);
                tabsWithErrors.add(field.tab);
                isValid = false;
            } else {
                $field.removeClass('error');
                $formGroup.removeClass('error');
                $formGroup.find('.field-error-message').remove();
            }
        });
        
        // Mark tabs with errors - FORZAR que se marquen siempre
        console.log('Tabs with errors:', Array.from(tabsWithErrors));
        
        // Wait a bit for modal to be fully rendered
        setTimeout(() => {
            tabsWithErrors.forEach(function(tab) {
                const $tab = $(`#eq-contract-modal .eq-contract-tab-nav li[data-tab="${tab}"]`);
                console.log(`Marking tab ${tab} with error. Tab found:`, $tab.length > 0);
                console.log(`Tab element text:`, $tab.text());
                console.log(`Tab current style:`, $tab.attr('style'));
                
                $tab.addClass('has-error');
                
                // FORZAR el estilo directamente en el DOM
                $tab[0].style.setProperty('color', '#dc3545', 'important');
                $tab[0].style.setProperty('background-color', '#ffebee', 'important');
                $tab[0].style.setProperty('border', '2px solid #dc3545', 'important');
                $tab[0].style.setProperty('position', 'relative', 'important');
                
                console.log(`Tab style after forcing:`, $tab.attr('style'));
                
                // Agregar indicador visual si no existe
                if (!$tab.find('.error-indicator').length) {
                    const indicator = $('<span class="error-indicator">!</span>');
                    indicator[0].style.setProperty('position', 'absolute', 'important');
                    indicator[0].style.setProperty('top', '5px', 'important');
                    indicator[0].style.setProperty('right', '10px', 'important');
                    indicator[0].style.setProperty('background', '#dc3545', 'important');
                    indicator[0].style.setProperty('color', 'white', 'important');
                    indicator[0].style.setProperty('width', '20px', 'important');
                    indicator[0].style.setProperty('height', '20px', 'important');
                    indicator[0].style.setProperty('border-radius', '50%', 'important');
                    indicator[0].style.setProperty('display', 'flex', 'important');
                    indicator[0].style.setProperty('align-items', 'center', 'important');
                    indicator[0].style.setProperty('justify-content', 'center', 'important');
                    indicator[0].style.setProperty('font-size', '12px', 'important');
                    indicator[0].style.setProperty('font-weight', 'bold', 'important');
                    indicator[0].style.setProperty('z-index', '1000', 'important');
                    
                    $tab.append(indicator);
                    console.log(`Added error indicator to tab ${tab}`);
                }
            });
        }, 100);

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
        
        if (difference > 0.01) {
            showValidationNotice('error', 'Payment schedule must equal contract total');
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
        console.log('showContractForm called');
        $('#eq-contract-success').hide();
        $('#eq-contract-loading').hide();
        $('#eq-contract-form').show();
        console.log('Contract form should now be visible');
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
        console.log('editContract function called');
        try {
            // Clear any error notifications
            $('.eq-contract-error-notification').remove();
            $('.eq-contract-tab-nav li').removeClass('has-error').css('color', '').find('.error-indicator').remove();
            
            console.log('Showing contract form...');
            showContractForm();
            
            // Reset tab to first one for clean editing experience
            switchContractTab('company');
            
            console.log('Edit contract completed');
            showNotification('info', 'You can now edit the contract details and regenerate');
        } catch (error) {
            console.error('Error in editContract:', error);
            showNotification('error', 'Error opening contract for editing');
        }
    }
    
    /**
     * Generate new contract - clear form and start fresh but keep cart data
     */
    function generateNewContract() {
        console.log('generateNewContract function called');
        try {
            console.log('Clearing form data but keeping cart information...');
            
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
            
            console.log('Clearing validation errors...');
            // Clear validation errors
            $('.eq-form-group').removeClass('error');
            $('.field-error-message').remove();
            $('.eq-contract-error-notification').remove();
            $('.eq-contract-tab-nav li').removeClass('has-error').css('color', '').find('.error-indicator').remove();
            
            console.log('Showing form and switching to company tab...');
            // Show form and go to first tab
            showContractForm();
            switchContractTab('company');
            
            // Re-populate the form with fresh cart data
            setTimeout(() => {
                console.log('Re-populating form with base cart data...');
                // If we don't have cart data, reload it
                if (!contractData.cart_items) {
                    openContractModal();
                } else {
                    populateContractForm();
                }
            }, 100);
            
            console.log('Generate new contract completed');
            showNotification('success', 'New contract form ready with current cart data');
        } catch (error) {
            console.error('Error in generateNewContract:', error);
            showNotification('error', 'Error preparing new contract form');
        }
    }

    /**
     * Preview contract
     */
    function previewContract() {
        console.log('previewContract function called');
        try {
            // Don't validate for preview - show with whatever data is available
            // Collect form data
            console.log('Collecting form data...');
            const formData = collectFormData();
            console.log('Form data collected:', formData);
            
            // Directly show inline preview (simpler approach)
            console.log('Showing inline preview...');
            showInlinePreview(formData);
            
        } catch (error) {
            console.error('Preview error:', error);
            showNotification('error', 'Error generating preview: ' + error.message);
        }
    }
    
    /**
     * Show inline preview as fallback
     */
    function showInlinePreview(formData) {
        console.log('showInlinePreview called with data:', formData);
        try {
            // Skip popup, go directly to modal preview for reliability
            console.log('Showing modal preview directly...');
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
            console.error('Error opening preview:', error);
            showNotification('error', 'Error opening preview. Please try again.');
        }
    }
    
    /**
     * Show modal preview when popups are blocked
     */
    function showModalPreview(formData) {
        console.log('showModalPreview called');
        // Create preview modal if it doesn't exist
        if ($('#eq-preview-modal').length === 0) {
            console.log('Creating preview modal...');
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
            
            console.log('Preview modal created and added to body');
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
        console.log('Setting modal content and showing...');
        $('#eq-preview-modal').show();
        console.log('Modal should be visible now. Display style:', $('#eq-preview-modal').css('display'));
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
     * Generate services preview for contract
     */
    function generateServicesPreview() {
        if (!contractData || !contractData.cart_items) {
            return '<p>Los servicios contratados aparecerán aquí basados en el carrito actual.</p>';
        }
        
        let html = '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
        html += '<thead><tr style="background-color: #f8f9fa;">';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Servicio</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Descripción</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Cantidad</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Precio</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Total</th>';
        html += '</tr></thead><tbody>';
        
        contractData.cart_items.forEach(item => {
            html += `<tr>
                <td style="border: 1px solid #ddd; padding: 8px;"><strong>${item.title || 'Servicio'}</strong></td>
                <td style="border: 1px solid #ddd; padding: 8px;">Fecha: ${item.date || 'Por definir'}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${item.quantity || 1}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${item.price_formatted || '$0.00'}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${item.total_formatted || '$0.00'}</td>
            </tr>`;
            
            // Add extras if any
            if (item.extras && item.extras.length > 0) {
                item.extras.forEach(extra => {
                    html += `<tr>
                        <td style="border: 1px solid #ddd; padding: 8px;">${extra.name || 'Extra'} (por ${item.title})</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">${extra.description || ''}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">${extra.quantity || 1}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">${formatCurrency(extra.price)}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">${formatCurrency(extra.price * (extra.quantity || 1))}</td>
                    </tr>`;
                });
            }
        });
        
        html += '</tbody></table>';
        
        if (contractData.cart_totals) {
            html += '<div style="text-align: right; margin-top: 15px;">';
            html += `<p><strong>Subtotal: ${contractData.cart_totals.subtotal || '$0.00'}</strong></p>`;
            html += `<p><strong>IVA: ${contractData.cart_totals.tax || '$0.00'}</strong></p>`;
            html += `<p style="font-size: 1.2em; color: #2c3e50;"><strong>Total: ${contractData.cart_totals.total || '$0.00'}</strong></p>`;
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