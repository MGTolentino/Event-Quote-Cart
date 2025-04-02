<?php
/**
 * Template for the quote cart page
 */

defined('ABSPATH') || exit;

// Asegurar que el usuario está logueado
if (!is_user_logged_in()) {
    ?>
    <div class="eq-login-required">
        <h2><?php esc_html_e('Please log in to view your quote cart', 'event-quote-cart'); ?></h2>
        <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="eq-login-button">
            <?php esc_html_e('Log In', 'event-quote-cart'); ?>
        </a>
    </div>
    <?php
    return;
}

// Obtener items del carrito
$cart_items = eq_get_cart_items();
?>

<div class="eq-cart-page">
    <?php 
// Verificar que existe contexto y que no hay señal de "no restaurar"
$show_context_banner = isset($context) && $context && 
                       (!isset($_SESSION['eq_context_no_restore']) || $_SESSION['eq_context_no_restore'] !== true) &&
                       (!isset($_COOKIE['eq_session_ended']) || $_COOKIE['eq_session_ended'] !== 'true');

if ($show_context_banner): 
?>
<div class="quote-context-banner">
    <div class="quote-context-banner">
        <div class="quote-context-info">
            <span class="quote-context-label"><?php esc_html_e('Quoting for:', 'event-quote-cart'); ?></span>
            <span class="quote-context-lead">
                <?php echo esc_html($context['lead']->lead_nombre . ' ' . $context['lead']->lead_apellido); ?>
            </span>
            <span class="quote-context-separator">|</span>
            <span class="quote-context-event">
                <?php echo esc_html($context['event']->tipo_de_evento); ?>
                <?php if (!empty($context['event']->fecha_formateada)): ?>
                    - <?php echo esc_html($context['event']->fecha_formateada); ?>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
    <!-- Header -->
<div class="eq-cart-header">
    <h1><?php esc_html_e('Your Quote Cart', 'event-quote-cart'); ?></h1>
    <a href="<?php echo esc_url(home_url()); ?>" class="eq-continue-shopping">
        <?php esc_html_e('Continue Shopping', 'event-quote-cart'); ?>
    </a>
</div>

<?php
// Nuevo código: Selector de evento para usuarios normales
if (!empty($cart_items) && !(current_user_can('administrator') || current_user_can('ejecutivo_de_ventas'))):
    // Obtener el lead del usuario
    global $wpdb;
    $user = wp_get_current_user();
    $lead = $wpdb->get_row($wpdb->prepare(
        "SELECT _ID FROM {$wpdb->prefix}jet_cct_leads WHERE lead_e_mail = %s",
        $user->user_email
    ));
    
    if ($lead):
        // Obtener la fecha actual del carrito
        $cart_date = null;
        if (!empty($cart_items)) {
            $cart_date = $cart_items[0]->date;
        }
        
        // Convertir fecha a timestamp
        $date_timestamp = strtotime($cart_date);
        
        if ($date_timestamp):
            // Obtener eventos del lead para esta fecha o cercanos
            $siete_dias = 7 * 24 * 60 * 60; // 7 días en segundos
            $fecha_min = $date_timestamp - $siete_dias;
            $fecha_max = $date_timestamp + $siete_dias;
            
            $eventos = $wpdb->get_results($wpdb->prepare(
                "SELECT _ID, tipo_de_evento, fecha_de_evento 
                FROM {$wpdb->prefix}jet_cct_eventos 
                WHERE lead_id = %d AND fecha_de_evento BETWEEN %s AND %s
                ORDER BY ABS(fecha_de_evento - %s) ASC",
                $lead->_ID, $fecha_min, $fecha_max, $date_timestamp
            ));
            
            // Obtener el evento actual (si existe)
            $current_cart = $wpdb->get_row($wpdb->prepare(
                "SELECT event_id FROM {$wpdb->prefix}eq_carts 
                WHERE user_id = %d AND status = 'active'
                ORDER BY created_at DESC LIMIT 1",
                get_current_user_id()
            ));
            $current_event_id = $current_cart ? $current_cart->event_id : null;
?>
    <div class="eq-event-selector">
        <h3><?php esc_html_e('Select Event for this Quote', 'event-quote-cart'); ?></h3>
        
        <form id="eq-change-event-form" method="post" action="">
            <?php wp_nonce_field('eq_change_event_nonce', 'eq_change_event_nonce'); ?>
            
            <div class="eq-event-options">
                <select name="eq_event_id" id="eq-event-select">
                    <?php foreach ($eventos as $evento): 
                        $fecha_formato = date_i18n(get_option('date_format'), $evento->fecha_de_evento);
                        $selected = ($current_event_id == $evento->_ID) ? 'selected' : '';
                    ?>
                        <option value="<?php echo esc_attr($evento->_ID); ?>" <?php echo $selected; ?>>
                            <?php echo esc_html("{$evento->tipo_de_evento} - {$fecha_formato}"); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="new"><?php esc_html_e('Create New Event', 'event-quote-cart'); ?></option>
                </select>
                
                <button type="submit" class="eq-update-event-button">
                    <?php esc_html_e('Update Event', 'event-quote-cart'); ?>
                </button>
            </div>
            
            <div id="eq-new-event-form" style="display: none;">
                <div class="eq-form-row">
                    <label for="eq-new-event-type"><?php esc_html_e('Event Type', 'event-quote-cart'); ?></label>
                    <input type="text" id="eq-new-event-type" name="eq_new_event_type" required>
                </div>
                
                <div class="eq-form-row">
                    <label for="eq-new-event-date"><?php esc_html_e('Event Date', 'event-quote-cart'); ?></label>
                    <input type="text" id="eq-new-event-date" name="eq_new_event_date" 
                           value="<?php echo esc_attr($cart_date); ?>" required>
                </div>
                
                <div class="eq-form-row">
                    <label for="eq-new-event-guests"><?php esc_html_e('Number of Guests', 'event-quote-cart'); ?></label>
                    <input type="number" id="eq-new-event-guests" name="eq_new_event_guests" min="1">
                </div>
            </div>
        </form>
    </div>
<?php 
        endif; // if $date_timestamp
    endif; // if $lead
endif; // if !empty($cart_items) && !(admin || ejecutivo)
?>

    <?php if (!empty($cart_items)): ?>
        <!-- Items List -->
        <div class="eq-cart-items">
            <?php foreach ($cart_items as $item): ?>
                <div class="eq-cart-item" data-item-id="<?php echo esc_attr($item->id); ?>">
                    <!-- Imagen del Item -->
                    <div class="eq-item-image">
                        <?php if ($item->image): ?>
                            <img src="<?php echo esc_url($item->image); ?>" alt="<?php echo esc_attr($item->title); ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Detalles del Item -->
                    <div class="eq-item-details">
                        <h3 class="eq-item-title"><?php echo esc_html($item->title); ?></h3>
                        <div class="eq-item-meta">
                            <span class="eq-item-date">
                                <?php esc_html_e('Event Date:', 'event-quote-cart'); ?> 
                                <?php echo esc_html($item->date); ?>
                            </span>
                            <span class="eq-item-quantity">
                                <?php esc_html_e('Quantity:', 'event-quote-cart'); ?> 
                                <?php echo esc_html($item->quantity); ?>
                            </span>
                        </div>

                        <?php if (!empty($item->extras)): ?>
                            <div class="eq-item-extras">
                                <h4><?php esc_html_e('Extras:', 'event-quote-cart'); ?></h4>
                                <ul>
                                    <?php foreach ($item->extras as $extra): ?>
                                        <li>
                                            <?php 
                                            echo esc_html($extra['name']);
                                            if ($extra['quantity'] > 1) {
                                                echo ' × ' . esc_html($extra['quantity']);
                                            }
                                            ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Precio -->
                    <div class="eq-item-price">
                        <?php echo esc_html($item->price_formatted); ?>
                    </div>

                    <!-- Acciones -->
                    <div class="eq-item-actions">
                        <button type="button" class="eq-edit-item">
                            <?php esc_html_e('Edit', 'event-quote-cart'); ?>
                        </button>
                        <button type="button" class="eq-remove-item">
                            <?php esc_html_e('Remove', 'event-quote-cart'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Totales y Acciones -->
            <div class="eq-cart-summary">
                <div class="eq-cart-totals">
                    <?php
                    $totals = eq_calculate_cart_totals($cart_items);
                    ?>
                    <div class="eq-total-row subtotal">
                        <span><?php esc_html_e('Subtotal:', 'event-quote-cart'); ?></span>
                        <span><?php echo esc_html($totals['subtotal']); ?></span>
                    </div>
                    <div class="eq-total-row tax">
                        <span><?php esc_html_e('Tax:', 'event-quote-cart'); ?></span>
                        <span><?php echo esc_html($totals['tax']); ?></span>
                    </div>
                    <div class="eq-total-row total">
                        <span><?php esc_html_e('Total:', 'event-quote-cart'); ?></span>
                        <span><?php echo esc_html($totals['total']); ?></span>
                    </div>
                </div>

                <div class="eq-cart-actions">
    <button class="eq-generate-quote">
        <?php esc_html_e('Generate Quote', 'event-quote-cart'); ?>
    </button>
    <button class="eq-share-quote">
        <?php esc_html_e('Share Quote', 'event-quote-cart'); ?>
    </button>
    
    <?php if (get_option('eq_stripe_enabled') === 'yes'): ?>
        <?php 
        // Verificar si es usuario administrador o ejecutivo con contexto activo
        $is_privileged = current_user_can('administrator') || current_user_can('ejecutivo_de_ventas');
        $has_context = isset($context) && $context && $context['lead'] && $context['event'];
        ?>
        
        <?php if ($is_privileged && $has_context): ?>
            <!-- Para usuarios privilegiados con contexto: generar enlace de pago -->
            <button class="eq-generate-payment-link">
                <?php esc_html_e('Generate Payment Link', 'event-quote-cart'); ?>
            </button>
        <?php else: ?>
            <!-- Para usuarios regulares o admin sin contexto: pago directo -->
            <button class="eq-pay-now">
                <?php esc_html_e('Pay Now', 'event-quote-cart'); ?>
            </button>
        <?php endif; ?>
    <?php endif; ?>
</div>
            </div>
        </div>
    <?php else: ?>
        <!-- Carrito Vacío -->
        <div class="eq-empty-cart">
            <p><?php esc_html_e('Your quote cart is empty.', 'event-quote-cart'); ?></p>
            <a href="<?php echo esc_url(home_url()); ?>" class="eq-start-shopping">
                <?php esc_html_e('Start Shopping', 'event-quote-cart'); ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (get_option('eq_stripe_enabled') === 'yes'): ?>
<!-- Modal de pago -->
<div id="eq-payment-modal" class="eq-modal">
    <div class="eq-modal-content">
        <span class="eq-modal-close">&times;</span>
        <h2><?php esc_html_e('Complete Payment', 'event-quote-cart'); ?></h2>
        
        <div id="eq-payment-form-container">
            <div class="eq-payment-info">
                <p><?php esc_html_e('Total Amount:', 'event-quote-cart'); ?> <span id="eq-payment-total"><?php echo esc_html($totals['total']); ?></span></p>
            </div>
            
            <form id="eq-payment-form">
                <div class="eq-form-row">
                    <label for="eq-payment-name"><?php esc_html_e('Full Name', 'event-quote-cart'); ?></label>
                    <input type="text" id="eq-payment-name" required>
                </div>
                
                <div class="eq-form-row">
                    <label for="eq-payment-email"><?php esc_html_e('Email', 'event-quote-cart'); ?></label>
                    <input type="email" id="eq-payment-email" required>
                </div>
                
                <div class="eq-form-row">
                    <label for="eq-payment-phone"><?php esc_html_e('Phone', 'event-quote-cart'); ?></label>
                    <input type="tel" id="eq-payment-phone">
                </div>
                
                <div class="eq-form-row">
                    <label><?php esc_html_e('Card Information', 'event-quote-cart'); ?></label>
                    <div id="eq-card-element">
                        <!-- Stripe Elements se montará aquí -->
                    </div>
                    <div id="eq-card-errors" role="alert"></div>
                </div>
                
                <button id="eq-submit-payment" type="submit">
                    <span id="eq-button-text"><?php esc_html_e('Pay Now', 'event-quote-cart'); ?></span>
                    <span id="eq-spinner" class="hidden"></span>
                </button>
            </form>
        </div>
        
        <div id="eq-payment-success" class="hidden">
            <div class="eq-success-icon">✓</div>
            <h3><?php esc_html_e('Payment Successful!', 'event-quote-cart'); ?></h3>
            <p><?php esc_html_e('Your payment has been processed successfully.', 'event-quote-cart'); ?></p>
            <p><?php esc_html_e('Order Number:', 'event-quote-cart'); ?> <strong id="eq-order-number"></strong></p>
            <p><?php esc_html_e('A confirmation email has been sent to your email address.', 'event-quote-cart'); ?></p>
            <button id="eq-payment-done"><?php esc_html_e('Done', 'event-quote-cart'); ?></button>
        </div>
        
        <div id="eq-payment-error" class="hidden">
            <div class="eq-error-icon">✗</div>
            <h3><?php esc_html_e('Payment Failed', 'event-quote-cart'); ?></h3>
            <p id="eq-error-message"></p>
            <button id="eq-payment-retry"><?php esc_html_e('Try Again', 'event-quote-cart'); ?></button>
        </div>
    </div>
</div>

<!-- Modal para compartir enlace de pago -->
<div id="eq-payment-link-modal" class="eq-modal">
    <div class="eq-modal-content">
        <span class="eq-modal-close">&times;</span>
        <h2><?php esc_html_e('Payment Link Generated', 'event-quote-cart'); ?></h2>
        
        <div id="eq-payment-link-container">
            <p><?php esc_html_e('Share this link with your client to complete the payment:', 'event-quote-cart'); ?></p>
            
            <div class="eq-payment-link-box">
                <input type="text" id="eq-payment-link-input" readonly>
                <button id="eq-copy-link"><?php esc_html_e('Copy', 'event-quote-cart'); ?></button>
            </div>
            
            <div class="eq-payment-link-actions">
                <button id="eq-email-link">
                    <i class="fas fa-envelope"></i> <?php esc_html_e('Send via Email', 'event-quote-cart'); ?>
                </button>
                
                <button id="eq-whatsapp-link">
                    <i class="fab fa-whatsapp"></i> <?php esc_html_e('Share via WhatsApp', 'event-quote-cart'); ?>
                </button>
            </div>
            
            <div class="eq-payment-link-note">
                <p><?php esc_html_e('Note: This link is valid for 24 hours and can only be used once.', 'event-quote-cart'); ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>