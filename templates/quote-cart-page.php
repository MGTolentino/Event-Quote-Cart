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
    <div class="quote-context-info">
        <span class="quote-context-label"><?php esc_html_e('Quoting for:', 'event-quote-cart'); ?></span>
        <span class="quote-context-lead">
            <?php echo esc_html($context['lead']->lead_nombre . ' ' . $context['lead']->lead_apellido); ?>
        </span>
        <span class="quote-context-separator">|</span>
        <span class="quote-context-event">
            <?php echo esc_html($context['event']->tipo_de_evento); ?>
            <?php if (!empty($context['event']->fecha_de_evento)): ?>
                - <?php 
                    if (is_numeric($context['event']->fecha_de_evento)) {
                        $timestamp = intval($context['event']->fecha_de_evento);
                        if ($timestamp > 0) {
                            echo esc_html(date_i18n('j \d\e F, Y', $timestamp));
                        }
                    } else {
                        $timestamp = strtotime($context['event']->fecha_de_evento);
                        if ($timestamp !== false && $timestamp > 0) {
                            echo esc_html(date_i18n('j \d\e F, Y', $timestamp));
                        }
                    }
                ?>
            <?php endif; ?>
        </span>
    </div>
</div>
<?php endif; ?>
    <!-- Header -->
<div class="eq-cart-header">
    <h1><?php esc_html_e('Your Quote Cart', 'event-quote-cart'); ?></h1>
    <div class="eq-header-actions">
        <?php if (current_user_can('administrator') || current_user_can('ejecutivo_de_ventas')): ?>
            <button type="button" class="eq-cart-history-button" id="eq-cart-history-btn">
                <i class="fas fa-history"></i> <?php esc_html_e('Cart History', 'event-quote-cart'); ?>
            </button>
        <?php endif; ?>
        <a href="<?php echo esc_url(home_url()); ?>" class="eq-continue-shopping">
            <?php esc_html_e('Continue Shopping', 'event-quote-cart'); ?>
        </a>
    </div>
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
            $cart_date = isset($cart_items[0]->date) ? $cart_items[0]->date : null;
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
                    <!-- Handle para arrastrar -->
                    <div class="eq-item-drag-handle">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    
                    <!-- Imagen del Item -->
                    <div class="eq-item-image">
                        <?php if (isset($item->image) && $item->image): ?>
                            <img src="<?php echo esc_url($item->image); ?>" alt="<?php echo esc_attr(isset($item->title) ? $item->title : ''); ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Detalles del Item -->
                    <div class="eq-item-details">
                        <h3 class="eq-item-title"><?php echo esc_html(isset($item->title) ? $item->title : ''); ?></h3>
                        <div class="eq-item-meta">
                            <span class="eq-item-date">
                                <?php esc_html_e('Event Date:', 'event-quote-cart'); ?> 
                                <?php 
                                if (isset($item->is_date_range) && $item->is_date_range) {
                                    echo esc_html(isset($item->start_date) ? $item->start_date : '') . ' to ' . esc_html(isset($item->end_date) ? $item->end_date : '');
                                } else {
                                    echo esc_html(isset($item->date) ? $item->date : '');
                                }
                                ?>
                            </span>
                            <span class="eq-item-quantity">
                                <?php esc_html_e('Quantity:', 'event-quote-cart'); ?> 
                                <?php echo esc_html(isset($item->quantity) ? $item->quantity : 1); ?>
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
                                            
                                            // Mostrar quantity apropiada para extras
                                            if (isset($extra['display_quantity']) && $extra['display_quantity'] > 1) {
                                                echo ' × ' . esc_html($extra['display_quantity']);
                                            } elseif ($extra['quantity'] > 1) {
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
                        <div class="eq-original-price"><?php echo esc_html(isset($item->price_formatted) ? $item->price_formatted : '$0.00'); ?></div>
                        <div class="eq-discounted-price" style="display: none;"></div>
                    </div>

                    <!-- Descuento por Item -->
                    <div class="eq-item-discount">
                        <label><?php esc_html_e('Discount:', 'event-quote-cart'); ?></label>
                        <div class="eq-discount-input-group">
                            <input type="number" class="eq-item-discount-value" data-item-id="<?php echo esc_attr($item->id); ?>" placeholder="0" min="0" step="0.01" value="" autocomplete="off">
                            <select class="eq-item-discount-type" data-item-id="<?php echo esc_attr($item->id); ?>" autocomplete="off">
                                <option value="fixed" selected>$</option>
                                <option value="percentage">%</option>
                            </select>
                        </div>
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
                        <span class="eq-subtotal-amount"><?php echo esc_html($totals['subtotal']); ?></span>
                    </div>
                    
                    <!-- Descuento Global -->
                    <div class="eq-total-row global-discount">
                        <span><?php esc_html_e('Global Discount:', 'event-quote-cart'); ?></span>
                        <div class="eq-global-discount-input">
                            <input type="number" id="eq-global-discount-value" placeholder="0" min="0" step="0.01" value="" autocomplete="off">
                            <select id="eq-global-discount-type" autocomplete="off">
                                <option value="fixed" selected>$</option>
                                <option value="percentage">%</option>
                            </select>
                        </div>
                        <span class="eq-global-discount-amount">$0.00</span>
                    </div>
                    
                    <!-- Descuentos por Item -->
                    <div class="eq-total-row item-discounts" style="display: none;">
                        <span><?php esc_html_e('Item Discounts:', 'event-quote-cart'); ?></span>
                        <span class="eq-item-discounts-amount">$0.00</span>
                    </div>
                    
                    <!-- Subtotal con Descuentos -->
                    <div class="eq-total-row subtotal-after-discounts" style="display: none;">
                        <span><?php esc_html_e('Subtotal after Discounts:', 'event-quote-cart'); ?></span>
                        <span class="eq-subtotal-after-discounts-amount"><?php echo esc_html($totals['subtotal']); ?></span>
                    </div>
                    
                    <div class="eq-total-row tax">
                        <span><?php esc_html_e('Tax:', 'event-quote-cart'); ?></span>
                        <span class="eq-tax-amount"><?php echo esc_html($totals['tax']); ?></span>
                    </div>
                    <div class="eq-total-row total">
                        <span><?php esc_html_e('Total:', 'event-quote-cart'); ?></span>
                        <span class="eq-total-amount"><?php echo esc_html($totals['total']); ?></span>
                    </div>
                </div>

                <div class="eq-cart-actions">
    <button class="eq-generate-contract">
        <?php esc_html_e('Generate Contract', 'event-quote-cart'); ?>
    </button>
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

<!-- Cart History Modal -->
<?php if (current_user_can('administrator') || current_user_can('ejecutivo_de_ventas')): ?>
<div id="eq-cart-history-modal" class="eq-modal">
    <div class="eq-modal-content">
        <span class="eq-modal-close">&times;</span>
        <h2><?php esc_html_e('Cart History', 'event-quote-cart'); ?></h2>
        
        <div id="eq-history-loading">
            <p><?php esc_html_e('Loading cart history...', 'event-quote-cart'); ?></p>
        </div>
        
        <div id="eq-history-content" style="display: none;">
            <div class="eq-history-list">
                <!-- History items will be populated here -->
            </div>
            
            <div class="eq-history-actions">
                <button id="eq-restore-history" class="eq-restore-button" disabled>
                    <?php esc_html_e('Restore Selected Version', 'event-quote-cart'); ?>
                </button>
            </div>
        </div>
        
        <div id="eq-history-empty" style="display: none;">
            <p><?php esc_html_e('No cart history found.', 'event-quote-cart'); ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Contract Generation Modal -->
<div id="eq-contract-modal" class="eq-modal">
    <div class="eq-modal-content eq-contract-modal-content">
        <span class="eq-modal-close">&times;</span>
        <h2><?php esc_html_e('Generate Contract', 'event-quote-cart'); ?></h2>
        
        <form id="eq-contract-form">
            <div class="eq-contract-tabs">
                <ul class="eq-contract-tab-nav">
                    <li class="active" data-tab="company"><?php esc_html_e('Company Info', 'event-quote-cart'); ?></li>
                    <li data-tab="client"><?php esc_html_e('Client Info', 'event-quote-cart'); ?></li>
                    <li data-tab="event"><?php esc_html_e('Event Details', 'event-quote-cart'); ?></li>
                    <li data-tab="payment"><?php esc_html_e('Payment Schedule', 'event-quote-cart'); ?></li>
                    <li data-tab="terms"><?php esc_html_e('Terms & Bank', 'event-quote-cart'); ?></li>
                </ul>
                
                <!-- Company Info Tab -->
                <div class="eq-contract-tab-content active" data-tab="company">
                    <h3><?php esc_html_e('Company Information', 'event-quote-cart'); ?></h3>
                    <div class="eq-form-group">
                        <label><?php esc_html_e('Company Name', 'event-quote-cart'); ?></label>
                        <input type="text" name="company_name" id="eq-company-name" required>
                    </div>
                    <div class="eq-form-group">
                        <label><?php esc_html_e('Company Address', 'event-quote-cart'); ?></label>
                        <textarea name="company_address" id="eq-company-address" rows="3" required></textarea>
                    </div>
                    <div class="eq-form-row">
                        <div class="eq-form-group">
                            <label><?php esc_html_e('Phone', 'event-quote-cart'); ?></label>
                            <input type="tel" name="company_phone" id="eq-company-phone" required>
                        </div>
                        <div class="eq-form-group">
                            <label><?php esc_html_e('Email', 'event-quote-cart'); ?></label>
                            <input type="email" name="company_email" id="eq-company-email" required>
                        </div>
                    </div>
                </div>
                
                <!-- Client Info Tab -->
                <div class="eq-contract-tab-content" data-tab="client">
                    <h3><?php esc_html_e('Client Information', 'event-quote-cart'); ?></h3>
                    <div class="eq-form-group">
                        <label><?php esc_html_e('Client Name', 'event-quote-cart'); ?></label>
                        <input type="text" name="client_name" id="eq-client-name" required>
                    </div>
                    <div class="eq-form-group">
                        <label><?php esc_html_e('Client Address', 'event-quote-cart'); ?></label>
                        <textarea name="client_address" id="eq-client-address" rows="3" required></textarea>
                    </div>
                    <div class="eq-form-row">
                        <div class="eq-form-group">
                            <label><?php esc_html_e('Phone', 'event-quote-cart'); ?></label>
                            <input type="tel" name="client_phone" id="eq-client-phone">
                        </div>
                        <div class="eq-form-group">
                            <label><?php esc_html_e('Email', 'event-quote-cart'); ?></label>
                            <input type="email" name="client_email" id="eq-client-email">
                        </div>
                    </div>
                </div>
                
                <!-- Event Details Tab -->
                <div class="eq-contract-tab-content" data-tab="event">
                    <h3><?php esc_html_e('Event Information', 'event-quote-cart'); ?></h3>
                    <div class="eq-form-group">
                        <label><?php esc_html_e('Event Date', 'event-quote-cart'); ?></label>
                        <input type="date" name="event_date" id="eq-event-date" required>
                    </div>
                    <div class="eq-form-group">
                        <label><?php esc_html_e('Event Time', 'event-quote-cart'); ?></label>
                        <div class="eq-time-inputs">
                            <input type="time" name="event_start_time" id="eq-event-start-time" placeholder="Start time">
                            <span><?php esc_html_e('to', 'event-quote-cart'); ?></span>
                            <input type="time" name="event_end_time" id="eq-event-end-time" placeholder="End time">
                        </div>
                    </div>
                    <div class="eq-form-group">
                        <label><?php esc_html_e('Event Location', 'event-quote-cart'); ?></label>
                        <input type="text" name="event_location" id="eq-event-location" required>
                    </div>
                    <div class="eq-form-group">
                        <label><?php esc_html_e('Number of Guests', 'event-quote-cart'); ?></label>
                        <input type="number" name="event_guests" id="eq-event-guests" min="1">
                    </div>
                </div>
                
                <!-- Payment Schedule Tab -->
                <div class="eq-contract-tab-content" data-tab="payment">
                    <h3><?php esc_html_e('Payment Schedule', 'event-quote-cart'); ?></h3>
                    
                    <div class="eq-payment-template-selector">
                        <label><?php esc_html_e('Select Template', 'event-quote-cart'); ?></label>
                        <select id="eq-payment-template">
                            <option value="full"><?php esc_html_e('Full Payment (100%)', 'event-quote-cart'); ?></option>
                            <option value="50-50"><?php esc_html_e('50% - 50%', 'event-quote-cart'); ?></option>
                            <option value="3-months"><?php esc_html_e('3 Monthly Payments', 'event-quote-cart'); ?></option>
                            <option value="6-months"><?php esc_html_e('6 Monthly Payments', 'event-quote-cart'); ?></option>
                            <option value="custom"><?php esc_html_e('Custom', 'event-quote-cart'); ?></option>
                        </select>
                    </div>
                    
                    <div class="eq-payment-schedule-container">
                        <div class="eq-payment-validation-notice" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span class="eq-validation-message"></span>
                        </div>
                        
                        <div id="eq-payment-schedule-items">
                            <!-- Payment items will be dynamically added here -->
                        </div>
                        
                        <button type="button" class="eq-add-payment-btn">
                            <i class="fas fa-plus"></i> <?php esc_html_e('Add Payment', 'event-quote-cart'); ?>
                        </button>
                        
                        <div class="eq-payment-summary">
                            <div class="eq-summary-row">
                                <span><?php esc_html_e('Contract Total:', 'event-quote-cart'); ?></span>
                                <span class="eq-contract-total">$0.00</span>
                            </div>
                            <div class="eq-summary-row">
                                <span><?php esc_html_e('Scheduled Payments:', 'event-quote-cart'); ?></span>
                                <span class="eq-scheduled-total">$0.00</span>
                            </div>
                            <div class="eq-summary-row eq-difference" style="display: none;">
                                <span><?php esc_html_e('Difference:', 'event-quote-cart'); ?></span>
                                <span class="eq-payment-difference">$0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Terms & Bank Tab -->
                <div class="eq-contract-tab-content" data-tab="terms">
                    <h3><?php esc_html_e('Terms and Bank Information', 'event-quote-cart'); ?></h3>
                    
                    <div class="eq-form-group">
                        <label><?php esc_html_e('Contract Terms', 'event-quote-cart'); ?></label>
                        <textarea name="contract_terms" id="eq-contract-terms" rows="8"></textarea>
                    </div>
                    
                    <h4><?php esc_html_e('Bank Information', 'event-quote-cart'); ?></h4>
                    <div class="eq-form-row">
                        <div class="eq-form-group">
                            <label><?php esc_html_e('Bank Name', 'event-quote-cart'); ?></label>
                            <input type="text" name="bank_name" id="eq-bank-name">
                        </div>
                        <div class="eq-form-group">
                            <label><?php esc_html_e('Account Number', 'event-quote-cart'); ?></label>
                            <input type="text" name="bank_account" id="eq-bank-account">
                        </div>
                    </div>
                    <div class="eq-form-row">
                        <div class="eq-form-group">
                            <label><?php esc_html_e('CLABE', 'event-quote-cart'); ?></label>
                            <input type="text" name="bank_clabe" id="eq-bank-clabe">
                        </div>
                        <div class="eq-form-group">
                            <label><?php esc_html_e('Company Tax ID', 'event-quote-cart'); ?></label>
                            <input type="text" name="company_rfc" id="eq-company-rfc">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="eq-contract-actions">
                <button type="button" class="eq-btn eq-btn-secondary eq-contract-preview">
                    <i class="fas fa-eye"></i> <?php esc_html_e('Preview', 'event-quote-cart'); ?>
                </button>
                <button type="submit" class="eq-btn eq-btn-primary eq-contract-generate">
                    <i class="fas fa-file-contract"></i> <?php esc_html_e('Generate Contract', 'event-quote-cart'); ?>
                </button>
            </div>
        </form>
        
        <div id="eq-contract-loading" style="display: none;">
            <div class="eq-spinner"></div>
            <p><?php esc_html_e('Generating contract...', 'event-quote-cart'); ?></p>
        </div>
        
        <div id="eq-contract-success" style="display: none;">
            <div class="eq-success-icon">✓</div>
            <h3><?php esc_html_e('Contract Generated Successfully!', 'event-quote-cart'); ?></h3>
            <p><?php esc_html_e('The contract has been generated and saved.', 'event-quote-cart'); ?></p>
            <div class="eq-contract-success-actions">
                <a href="#" id="eq-contract-download" class="eq-btn eq-btn-primary" target="_blank">
                    <i class="fas fa-download"></i> <?php esc_html_e('Download Contract', 'event-quote-cart'); ?>
                </a>
                <button type="button" id="eq-contract-send" class="eq-btn eq-btn-secondary">
                    <i class="fas fa-envelope"></i> <?php esc_html_e('Send to Client', 'event-quote-cart'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo esc_url(EQ_CART_PLUGIN_URL . 'public/js/contracts.js'); ?>?v=<?php echo EQ_CART_VERSION; ?>"></script>
<link rel="stylesheet" href="<?php echo esc_url(EQ_CART_PLUGIN_URL . 'public/css/contracts.css'); ?>?v=<?php echo EQ_CART_VERSION; ?>">