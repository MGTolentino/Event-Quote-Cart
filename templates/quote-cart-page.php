<?php

error_log('Loading template: ' . __FILE__);
/**
 * Template for the quote cart page
 */

defined('ABSPATH') || exit;

// Asegurar que el usuario está logueado
if (!is_user_logged_in()) {
    ?>
    <div class="eq-login-required">
        <h2><?php _e('Please log in to view your quote cart', 'event-quote-cart'); ?></h2>
        <a href="<?php echo wp_login_url(get_permalink()); ?>" class="eq-login-button">
            <?php _e('Log In', 'event-quote-cart'); ?>
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
            <span class="quote-context-label"><?php _e('Cotizando para:', 'event-quote-cart'); ?></span>
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
    <h1><?php _e('Your Quote Cart', 'event-quote-cart'); ?></h1>
    <a href="<?php echo esc_url(home_url()); ?>" class="eq-continue-shopping">
        <?php _e('Continue Shopping', 'event-quote-cart'); ?>
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
        <h3><?php _e('Select Event for this Quote', 'event-quote-cart'); ?></h3>
        
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
                    <option value="new"><?php _e('Create New Event', 'event-quote-cart'); ?></option>
                </select>
                
                <button type="submit" class="eq-update-event-button">
                    <?php _e('Update Event', 'event-quote-cart'); ?>
                </button>
            </div>
            
            <div id="eq-new-event-form" style="display: none;">
                <div class="eq-form-row">
                    <label for="eq-new-event-type"><?php _e('Event Type', 'event-quote-cart'); ?></label>
                    <input type="text" id="eq-new-event-type" name="eq_new_event_type" required>
                </div>
                
                <div class="eq-form-row">
                    <label for="eq-new-event-date"><?php _e('Event Date', 'event-quote-cart'); ?></label>
                    <input type="text" id="eq-new-event-date" name="eq_new_event_date" 
                           value="<?php echo esc_attr($cart_date); ?>" required>
                </div>
                
                <div class="eq-form-row">
                    <label for="eq-new-event-guests"><?php _e('Number of Guests', 'event-quote-cart'); ?></label>
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
                                <?php _e('Event Date:', 'event-quote-cart'); ?> 
                                <?php echo esc_html($item->date); ?>
                            </span>
                            <span class="eq-item-quantity">
                                <?php _e('Quantity:', 'event-quote-cart'); ?> 
                                <?php echo esc_html($item->quantity); ?>
                            </span>
                        </div>

                        <?php if (!empty($item->extras)): ?>
                            <div class="eq-item-extras">
                                <h4><?php _e('Extras:', 'event-quote-cart'); ?></h4>
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
                            <?php _e('Edit', 'event-quote-cart'); ?>
                        </button>
                        <button type="button" class="eq-remove-item">
                            <?php _e('Remove', 'event-quote-cart'); ?>
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
                        <span><?php _e('Subtotal:', 'event-quote-cart'); ?></span>
                        <span><?php echo esc_html($totals['subtotal']); ?></span>
                    </div>
                    <div class="eq-total-row tax">
                        <span><?php _e('Tax:', 'event-quote-cart'); ?></span>
                        <span><?php echo esc_html($totals['tax']); ?></span>
                    </div>
                    <div class="eq-total-row total">
                        <span><?php _e('Total:', 'event-quote-cart'); ?></span>
                        <span><?php echo esc_html($totals['total']); ?></span>
                    </div>
                </div>

                <div class="eq-cart-actions">
                    <button class="eq-generate-quote">
                        <?php _e('Generar Cotización', 'event-quote-cart'); ?>
                    </button>
                    <button class="eq-share-quote">
                        <?php _e('Compartir Cotización', 'event-quote-cart'); ?>
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Carrito Vacío -->
        <div class="eq-empty-cart">
            <p><?php _e('Your quote cart is empty.', 'event-quote-cart'); ?></p>
            <a href="<?php echo esc_url(home_url()); ?>" class="eq-start-shopping">
                <?php _e('Start Shopping', 'event-quote-cart'); ?>
            </a>
        </div>
    <?php endif; ?>
</div>