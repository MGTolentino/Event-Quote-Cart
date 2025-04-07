(function ($) {
    'use strict';

    // Variables principales
    let stripe, elements, cardElement;
    let paymentIntentId = null;
    let orderId = null;
    let clientSecret = null;
    let modal, paymentLinkModal;

// Inicializar modales
function initModals() {
    modal = document.getElementById('eq-payment-modal');
    paymentLinkModal = document.getElementById('eq-payment-link-modal');
    
    // Cerrar modal al hacer clic en la X
    const closeButtons = document.getElementsByClassName('eq-modal-close');
    for (let i = 0; i < closeButtons.length; i++) {
        closeButtons[i].addEventListener('click', function() {
            modal.style.display = 'none';
            paymentLinkModal.style.display = 'none';
        });
    }
    
    // Cerrar modal al hacer clic fuera del contenido
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
        if (event.target === paymentLinkModal) {
            paymentLinkModal.style.display = 'none';
        }
    });
    
    // Botón "Done" en el mensaje de éxito
    document.getElementById('eq-payment-done').addEventListener('click', function() {
        modal.style.display = 'none';
        window.location.reload(); // Recargar página después del pago exitoso
    });
    
    // Botón "Retry" en el mensaje de error
    document.getElementById('eq-payment-retry').addEventListener('click', function() {
        showPaymentForm();
    });
    
    // Botón "Copy" para copiar enlace de pago
    document.getElementById('eq-copy-link').addEventListener('click', function() {
        const linkInput = document.getElementById('eq-payment-link-input');
        linkInput.select();
        document.execCommand('copy');
        this.textContent = 'Copied!';
        setTimeout(() => { this.textContent = 'Copy'; }, 2000);
    });
    
    // Botón para enviar por email
    document.getElementById('eq-email-link').addEventListener('click', function() {
        const linkUrl = document.getElementById('eq-payment-link-input').value;
        const emailSubject = encodeURIComponent('Payment Link for Your Order');
        const emailBody = encodeURIComponent('Here is your payment link: ' + linkUrl);
        window.open(`mailto:?subject=${emailSubject}&body=${emailBody}`);
    });
    
    // Botón para compartir por WhatsApp
    document.getElementById('eq-whatsapp-link').addEventListener('click', function() {
        const linkUrl = document.getElementById('eq-payment-link-input').value;
        const whatsappText = encodeURIComponent('Here is your payment link: ' + linkUrl);
        window.open(`https://wa.me/?text=${whatsappText}`);
    });
}

// Mostrar formulario de pago
function showPaymentForm() {
    document.getElementById('eq-payment-form-container').style.display = 'block';
    document.getElementById('eq-payment-success').classList.add('hidden');
    document.getElementById('eq-payment-error').classList.add('hidden');
    
    // Pre-llenar campos si el usuario está logueado y tiene datos
    if (eqStripeData.userData) {
        document.getElementById('eq-payment-name').value = eqStripeData.userData.name || '';
        document.getElementById('eq-payment-email').value = eqStripeData.userData.email || '';
    }
}

// Mostrar mensaje de éxito
function showPaymentSuccess(orderId) {
    document.getElementById('eq-payment-form-container').style.display = 'none';
    document.getElementById('eq-payment-success').classList.remove('hidden');
    document.getElementById('eq-order-number').textContent = orderId;
}

// Mostrar mensaje de error
function showPaymentError(errorMessage) {
    document.getElementById('eq-payment-form-container').style.display = 'none';
    document.getElementById('eq-payment-error').classList.remove('hidden');
    document.getElementById('eq-error-message').textContent = errorMessage;
}

// Crear pago
function createPayment() {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: 'POST',
            url: eqCartData.ajaxurl,
            data: {
                action: 'eq_create_payment_intent',
                nonce: eqCartData.nonce
            },
            success: function(response) {
                if (response.success) {
                    clientSecret = response.data.clientSecret;
                    orderId = response.data.order_id;
                    resolve(response.data);
                } else {
                    reject(new Error(response.data.message || 'Error creating payment'));
                }
            },
            error: function(xhr, status, error) {
                reject(new Error('Network error occurred'));
            }
        });
    });
}

// Generar enlace de pago
function generatePaymentLink() {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: 'POST',
            url: eqCartData.ajaxurl,
            data: {
                action: 'eq_create_checkout_session',
                nonce: eqCartData.nonce
            },
            success: function(response) {
                if (response.success) {
                    resolve(response.data);
                } else {
                    reject(new Error(response.data.message || 'Error generating payment link'));
                }
            },
            error: function(xhr, status, error) {
                reject(new Error('Network error occurred'));
            }
        });
    });
}

// Verificar estado de pago
function checkPaymentStatus(paymentId, orderId) {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: 'POST',
            url: eqCartData.ajaxurl,
            data: {
                action: 'eq_check_payment_status',
                nonce: eqCartData.nonce,
                payment_id: paymentId,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    resolve(response.data);
                } else {
                    reject(new Error(response.data.message || 'Error checking payment status'));
                }
            },
            error: function(xhr, status, error) {
                reject(new Error('Network error occurred'));
            }
        });
    });
}

// Inicializar el módulo
function init() {
    if (!eqStripeData || !eqStripeData.publishableKey) return;
    
    // Inicializar sólo los modales, no Stripe todavía
    initModals();
    
    // Evento para mostrar el modal de pago
    $(document).on('click', '.eq-pay-now', function(e) {
        e.preventDefault();
        
        // Mostrar el modal
        modal.style.display = 'block';
        showPaymentForm();
        
        // Inicializar Stripe sólo cuando el modal está visible
        try {
            // Inicializar Stripe y montar el elemento de tarjeta
            stripe = Stripe(eqStripeData.publishableKey);
            elements = stripe.elements();
            
            // Verificar si el elemento ya existe para evitar remontarlo
            if (!cardElement) {
                const style = {
                    base: {
                        color: '#32325d',
                        fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                        fontSmoothing: 'antialiased',
                        fontSize: '16px',
                        '::placeholder': {
                            color: '#aab7c4'
                        }
                    },
                    invalid: {
                        color: '#fa755a',
                        iconColor: '#fa755a'
                    }
                };
                
                // Verificar que el elemento existe antes de montar
                if (document.getElementById('eq-card-element')) {
                    cardElement = elements.create('card', {style: style});
                    cardElement.mount('#eq-card-element');
                    
                    // Manejar errores de validación en tiempo real
                    cardElement.on('change', function(event) {
                        var displayError = document.getElementById('eq-card-errors');
                        if (displayError) {
                            if (event.error) {
                                displayError.textContent = event.error.message;
                            } else {
                                displayError.textContent = '';
                            }
                        }
                    });
                }
            }
            
            // Crear el Payment Intent
            createPayment().catch(error => {
                showPaymentError(error.message);
            });
        } catch (error) {
            console.error('Error initializing Stripe:', error);
            showPaymentError('Error initializing payment system. Please try again later.');
        }
    });
    
    // Evento para generar enlace de pago
    $(document).on('click', '.eq-generate-payment-link', function(e) {
        e.preventDefault();
        
        // Mostrar indicador de carga
        const originalText = $(this).text();
        $(this).text('Generating...').prop('disabled', true);
        
        generatePaymentLink()
            .then(data => {
                // Mostrar modal con el enlace
                paymentLinkModal.style.display = 'block';
                document.getElementById('eq-payment-link-input').value = data.checkout_url;
                
                // Restaurar botón
                $(this).text(originalText).prop('disabled', false);
            })
            .catch(error => {
                alert(error.message);
                $(this).text(originalText).prop('disabled', false);
            });
    });
    
    // Manejar envío del formulario de pago
    $(document).on('submit', '#eq-payment-form', async function(e) {
        e.preventDefault();
        
        const submitButton = document.getElementById('eq-submit-payment');
        const spinnerElement = document.getElementById('eq-spinner');
        const buttonText = document.getElementById('eq-button-text');
        
        // Mostrar spinner
        submitButton.disabled = true;
        spinnerElement.classList.remove('hidden');
        buttonText.classList.add('hidden');
        
        const billingDetails = {
            name: document.getElementById('eq-payment-name').value,
            email: document.getElementById('eq-payment-email').value,
            phone: document.getElementById('eq-payment-phone').value
        };
        
        try {
            // Confirmar el pago con Stripe
            const result = await stripe.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: cardElement,
                    billing_details: billingDetails
                }
            });
            
            if (result.error) {
                // Mostrar error
                showPaymentError(result.error.message);
            } else if (result.paymentIntent.status === 'succeeded') {
                // El pago se completó exitosamente
                showPaymentSuccess(orderId);
            } else {
                // Verificar estado del pago
                const status = await checkPaymentStatus(result.paymentIntent.id, orderId);
                
                if (status.order_status === 'paid') {
                    showPaymentSuccess(orderId);
                } else {
                    showPaymentError('Payment is in an unexpected state. Please contact support.');
                }
            }
        } catch (error) {
            showPaymentError(error.message);
        } finally {
            // Ocultar spinner
            submitButton.disabled = false;
            spinnerElement.classList.add('hidden');
            buttonText.classList.remove('hidden');
        }
    });
    
    // Verificar estado de pago desde URL (después de redirección de Checkout)
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const sessionId = urlParams.get('session_id');
    
    if (status === 'success' && sessionId) {
        // Verificar estado del pago en el servidor
        checkPaymentStatus(sessionId)
            .then(status => {
                if (status.order_status === 'paid') {
                    modal.style.display = 'block';
                    showPaymentSuccess(status.order_id);
                }
            })
            .catch(error => console.error('Error checking payment status:', error));
    }
}

// Inicializar cuando el documento esté listo
$(document).ready(init);

})(jQuery);