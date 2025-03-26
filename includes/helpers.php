<?php
/**
 * Helper functions for the cart
 */

defined('ABSPATH') || exit;

/**
 * Get cart items for current user based on active context
 */
function eq_get_cart_items() {
	
    global $wpdb;
    
    if (!is_user_logged_in()) {
        return array();
    }
	
	
    
    $user_id = get_current_user_id();
  // Si existe la señal de "no restaurar", mostrar un carrito personal para usuarios privilegiados
$user = wp_get_current_user();
$is_privileged = in_array('administrator', $user->roles) || in_array('ejecutivo_de_ventas', $user->roles);

// PRIORIDAD: Si hay un contexto activo en sesión, no considerar las señales de finalización
$has_active_context = isset($_SESSION['eq_quote_context']) && 
                      isset($_SESSION['eq_quote_context']['lead_id']) && 
                      isset($_SESSION['eq_quote_context']['event_id']);

// Solo verificar señales de finalización si NO hay contexto activo
if (!$has_active_context) {
    $session_ended = (isset($_SESSION['eq_context_no_restore']) && $_SESSION['eq_context_no_restore'] === true) || 
                     (isset($_COOKIE['eq_session_ended']) && $_COOKIE['eq_session_ended'] === 'true');
                     
    // Si la sesión ha terminado y es usuario privilegiado, buscar un carrito personal
    if ($session_ended && $is_privileged) {
        error_log('eq_get_cart_items: No active context AND session ended flag found, looking for personal cart');
        
        // Buscar un carrito sin lead_id o event_id
        $personal_cart_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d 
            AND (lead_id IS NULL OR lead_id = 0) 
            AND (event_id IS NULL OR event_id = 0) 
            AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
        
        if ($personal_cart_id) {
            error_log('eq_get_cart_items: Found personal cart ID ' . $personal_cart_id);
            update_user_meta($user_id, 'eq_active_cart_id', $personal_cart_id);
            
            // Obtener items de este carrito personal
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eq_cart_items 
                WHERE cart_id = %d AND status = 'active'
                ORDER BY created_at DESC",
                $personal_cart_id
            ));
            
            // Procesar items como de costumbre
            foreach ($items as &$item) {
                $listing = get_post($item->listing_id);
                $form_data = json_decode($item->form_data, true);
                
                $item->title = get_the_title($listing);
                $item->image = get_the_post_thumbnail_url($listing->ID, 'thumbnail');
                $item->date = isset($form_data['date']) ? $form_data['date'] : '';
                $item->quantity = isset($form_data['quantity']) ? $form_data['quantity'] : 1;
                $item->extras = isset($form_data['extras']) ? $form_data['extras'] : array();
                $item->total_price = isset($form_data['total_price']) ? floatval($form_data['total_price']) : 0;
                $item->price_formatted = hivepress()->woocommerce->format_price($item->total_price);
            }
            
            return $items;
        }
    }
}
    
    // PASO 1: Verificar si hay un contexto activo en la sesión PHP
    $context_lead_id = null;
    $context_event_id = null;
    $context_user_id = null;
    $has_valid_context = false;
    
    if (isset($_SESSION['eq_quote_context'])) {
        $context_lead_id = isset($_SESSION['eq_quote_context']['lead_id']) ? 
            intval($_SESSION['eq_quote_context']['lead_id']) : null;
        $context_event_id = isset($_SESSION['eq_quote_context']['event_id']) ? 
            intval($_SESSION['eq_quote_context']['event_id']) : null;
        $context_user_id = isset($_SESSION['eq_quote_context']['user_id']) ? 
            intval($_SESSION['eq_quote_context']['user_id']) : null;
        
        // Verificar que el contexto pertenezca al usuario actual
        if ($context_user_id !== null && $context_user_id !== $user_id) {
            error_log('eq_get_cart_items: Context in session belongs to different user, ignoring. Session user: ' . 
                $context_user_id . ', Current user: ' . $user_id);
            $context_lead_id = null;
            $context_event_id = null;
        } else if ($context_lead_id && $context_event_id) {
            error_log('eq_get_cart_items: Using context from session - lead_id: ' . 
                $context_lead_id . ', event_id: ' . $context_event_id);
            $has_valid_context = true;
        }
    }
    
    // PASO 2: Verificar en la base de datos si no encontramos contexto en la sesión
    if (!$has_valid_context && $is_privileged) {
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_context_sessions 
            WHERE user_id = %d
            LIMIT 1",
            $user_id
        ));
        
        if ($session && $session->lead_id && $session->event_id) {
            $context_lead_id = $session->lead_id;
            $context_event_id = $session->event_id;
            $has_valid_context = true;
            
            error_log('eq_get_cart_items: Using context from database - lead_id: ' . 
                $context_lead_id . ', event_id: ' . $context_event_id);
            
            // Actualizar la sesión PHP para mantener consistencia
            $_SESSION['eq_quote_context'] = array(
                'lead_id' => $context_lead_id,
                'event_id' => $context_event_id,
                'user_id' => $user_id,
                'session_token' => $session->session_token
            );
        }
    }
    
    // PASO 3: Si tenemos contexto válido, buscar el carrito correspondiente
    if ($has_valid_context) {
        $context_cart_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id, $context_lead_id, $context_event_id
        ));
        
        if ($context_cart_id) {
            error_log('eq_get_cart_items: Found cart ID ' . $context_cart_id . 
                ' for context lead_id: ' . $context_lead_id . ', event_id: ' . $context_event_id);
                
            // Actualizar meta de usuario para consistencia
            update_user_meta($user_id, 'eq_active_cart_id', $context_cart_id);
            
            // Usar este carrito específico
            $active_cart_id = $context_cart_id;
        } else {
            error_log('eq_get_cart_items: No cart found for current context');
            
            // Si no existe carrito para este contexto, no mostrar nada para usuarios privilegiados
            // pero buscar carrito personal para usuarios normales
            if ($is_privileged) {
                return array();
            }
        }
    } 
    
    // PASO 4: Si no hay contexto válido o no se encontró un carrito para el contexto,
    // buscar el carrito personal del usuario
    if (!isset($active_cart_id)) {
        // Para usuarios privilegiados sin contexto, buscar carritos sin lead_id/event_id
        if ($is_privileged) {
            $active_cart_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eq_carts 
                WHERE user_id = %d AND (lead_id IS NULL OR lead_id = 0) 
                AND (event_id IS NULL OR event_id = 0) AND status = 'active'
                ORDER BY created_at DESC LIMIT 1",
                $user_id
            ));
            
            error_log('eq_get_cart_items: Looking for personal cart for privileged user: ' . 
                ($active_cart_id ? 'Found ID ' . $active_cart_id : 'Not found'));
        } 
        // Para usuarios normales, buscar cualquier carrito activo
        else {
            $active_cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
            
            if (!$active_cart_id) {
                $active_cart_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}eq_carts 
                    WHERE user_id = %d AND status = 'active'
                    ORDER BY created_at DESC LIMIT 1",
                    $user_id
                ));
                
                if ($active_cart_id) {
                    update_user_meta($user_id, 'eq_active_cart_id', $active_cart_id);
                }
            }
            
            error_log('eq_get_cart_items: Looking for any cart for normal user: ' . 
                ($active_cart_id ? 'Found ID ' . $active_cart_id : 'Not found'));
        }
        
        // Si aún no hay carrito activo, no hay items
        if (!$active_cart_id) {
            return array();
        }
    }
    
    // Para depuración - registra el carrito que se está usando
    error_log('eq_get_cart_items using cart ID: ' . $active_cart_id);
    
    // Get items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_cart_items 
        WHERE cart_id = %d AND status = 'active'
        ORDER BY created_at DESC",
        $active_cart_id
    ));
    
    // Process items
    foreach ($items as &$item) {
        $listing = get_post($item->listing_id);
        if (!$listing) {
            continue;
        }
        
        $form_data = json_decode($item->form_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            continue;
        }
        
        $item->title = get_the_title($listing);
        $item->image = get_the_post_thumbnail_url($listing->ID, 'thumbnail');
        $item->date = isset($form_data['date']) ? $form_data['date'] : '';
        $item->quantity = isset($form_data['quantity']) ? $form_data['quantity'] : 1;
        $item->extras = isset($form_data['extras']) ? $form_data['extras'] : array();
        $item->total_price = isset($form_data['total_price']) ? floatval($form_data['total_price']) : 0;
        $item->price_formatted = hivepress()->woocommerce->format_price($item->total_price);
    }
    
    return $items;
}


/**
 * Calculate cart totals
 */
function eq_calculate_cart_totals($items) {
    $total = 0;
    
    foreach ($items as $item) {
        // Sumar los precios totales (que ya incluyen impuestos)
        $total += isset($item->total_price) ? $item->total_price : 0;
    }
    
    // Calcular el subtotal y los impuestos basados en el total
    $tax_rate = floatval(get_option('eq_tax_rate', 16));
    $subtotal = $total / (1 + ($tax_rate / 100));
    $tax = $total - $subtotal;
    
    return array(
        'subtotal' => hivepress()->woocommerce->format_price($subtotal),
        'tax' => hivepress()->woocommerce->format_price($tax),
        'total' => hivepress()->woocommerce->format_price($total)
    );
}

/**
 * Obtiene el contexto activo (lead y evento) para el usuario actual
 *
 * @return array|null Array con 'lead' y 'event', o null si no hay contexto activo
 */
function eq_get_active_context() {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // PASO 1: Verificar si existe la señal de "no restaurar"
    if (isset($_SESSION['eq_context_no_restore']) && $_SESSION['eq_context_no_restore'] === true) {
        return null;
    }
    
    // PASO 2: Verificar si hay contexto en la sesión
    if (isset($_SESSION['eq_quote_context']) && 
        !empty($_SESSION['eq_quote_context']['lead_id']) && 
        !empty($_SESSION['eq_quote_context']['event_id'])) {
        
        $lead_id = $_SESSION['eq_quote_context']['lead_id'];
        $event_id = $_SESSION['eq_quote_context']['event_id'];
        $session_user_id = isset($_SESSION['eq_quote_context']['user_id']) ? $_SESSION['eq_quote_context']['user_id'] : 0;
        
        // Verificar que la sesión pertenece al usuario actual
        if ($session_user_id != $user_id) {
            return null;
        }
        
        // Verificar que el usuario tenga acceso a este lead/evento
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles);
        $is_sales = in_array('ejecutivo_de_ventas', $user->roles);
        
        // Los administradores pueden acceder a cualquier lead/evento
        if ($is_admin) {
            $lead = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}jet_cct_leads WHERE _ID = %d",
                $lead_id
            ));
            
            $event = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
                $event_id
            ));
        } 
        // Los ejecutivos de ventas solo pueden acceder a leads/eventos asignados a ellos
        else if ($is_sales) {
            $lead = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}jet_cct_leads 
                WHERE _ID = %d AND (usuario_asignado = %d OR usuario_asignado IS NULL)",
                $lead_id, $user_id
            ));
            
            $event = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}jet_cct_eventos 
                WHERE _ID = %d AND lead_id = %d",
                $event_id, $lead_id
            ));
        }
        // Usuarios normales solo pueden acceder a sus propios leads/eventos
        else {
            $lead = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}jet_cct_leads 
                WHERE _ID = %d AND lead_e_mail = %s",
                $lead_id, $user->user_email
            ));
            
            $event = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}jet_cct_eventos 
                WHERE _ID = %d AND lead_id = %d",
                $event_id, $lead_id
            ));
        }
        
        if ($lead && $event) {
            // Formatear fechas si es necesario
            if (!empty($event->fecha_de_evento)) {
                // Si es un timestamp numérico
                if (is_numeric($event->fecha_de_evento)) {
                    $event->fecha_formateada = date_i18n(get_option('date_format'), intval($event->fecha_de_evento));
                } else {
                    // Si es una cadena de fecha
                    $event->fecha_formateada = date_i18n(get_option('date_format'), strtotime($event->fecha_de_evento));
                }
            }
            
            return [
                'lead' => $lead,
                'event' => $event
            ];
        }
    }
    
    // PASO 3: No reconstruir automáticamente desde el carrito si hay señal de "sesión finalizada"
    if (isset($_COOKIE['eq_session_ended']) && $_COOKIE['eq_session_ended'] === 'true') {
        return null;
    }
    
    // PASO 4: Si no hay contexto en sesión, solo para usuarios privilegiados verificar el carrito
    $user = wp_get_current_user();
    $is_privileged = in_array('administrator', $user->roles) || in_array('ejecutivo_de_ventas', $user->roles);
    
    if (!$is_privileged) {
        return null;
    }
    
    // PASO 5: Para usuarios privilegiados, intentar reconstruir desde el carrito
    $active_cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
    
    if (!$active_cart_id) {
        // Si no hay carrito activo en meta, buscar el más reciente
        $active_cart_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
        
        // Si se encontró un carrito, guardarlo como activo
        if ($active_cart_id) {
            update_user_meta($user_id, 'eq_active_cart_id', $active_cart_id);
        }
    }
    
    // Si no hay carrito activo, no hay contexto
    if (!$active_cart_id) {
        return null;
    }
    
    // Obtener información del carrito
    $cart = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_carts WHERE id = %d AND user_id = %d",
        $active_cart_id, $user_id
    ));
    
    if (!$cart || empty($cart->lead_id) || empty($cart->event_id)) {
        return null;
    }
    
    // Verificar que el usuario tenga acceso a este lead/evento
    $is_admin = in_array('administrator', $user->roles);
    $is_sales = in_array('ejecutivo_de_ventas', $user->roles);
    
    // Los administradores pueden acceder a cualquier lead/evento
    if ($is_admin) {
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jet_cct_leads WHERE _ID = %d",
            $cart->lead_id
        ));
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
            $cart->event_id
        ));
    } 
    // Los ejecutivos de ventas solo pueden acceder a leads/eventos asignados a ellos
    else if ($is_sales) {
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jet_cct_leads 
            WHERE _ID = %d AND (usuario_asignado = %d OR usuario_asignado IS NULL)",
            $cart->lead_id, $user_id
        ));
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jet_cct_eventos 
            WHERE _ID = %d AND lead_id = %d",
            $cart->event_id, $cart->lead_id
        ));
    }
    // Usuarios normales solo pueden acceder a sus propios leads/eventos
    else {
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jet_cct_leads 
            WHERE _ID = %d AND lead_e_mail = %s",
            $cart->lead_id, $user->user_email
        ));
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jet_cct_eventos 
            WHERE _ID = %d AND lead_id = %d",
            $cart->event_id, $cart->lead_id
        ));
    }
    
    if (!$lead || !$event) {
        return null;
    }
    
    // Formatear fechas si es necesario
    if (!empty($event->fecha_de_evento)) {
        // Si es un timestamp numérico
        if (is_numeric($event->fecha_de_evento)) {
            $event->fecha_formateada = date_i18n(get_option('date_format'), intval($event->fecha_de_evento));
        } else {
            // Si es una cadena de fecha
            $event->fecha_formateada = date_i18n(get_option('date_format'), strtotime($event->fecha_de_evento));
        }
    }
    
    // Actualizar la sesión con estos datos si no hay señal de "no restaurar"
    if (!isset($_SESSION['eq_context_no_restore']) || $_SESSION['eq_context_no_restore'] !== true) {
        $_SESSION['eq_quote_context'] = array(
            'lead_id' => $cart->lead_id,
            'event_id' => $cart->event_id,
            'user_id' => $user_id,
            'timestamp' => time()
        );
    }
    
    return [
        'lead' => $lead,
        'event' => $event
    ];
}

/**
 * Obtiene el carrito activo para el usuario actual basado en el contexto
 *
 * @return object|null Objeto del carrito o null si no hay carrito activo
 */
function eq_get_active_cart() {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // PASO 1: Verificar si hay un contexto activo
    $context = eq_get_active_context();
    if ($context && isset($context['lead']) && isset($context['event'])) {
        $lead_id = $context['lead']->_ID;
        $event_id = $context['event']->_ID;
        
        // Buscar carrito para este contexto
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id, $lead_id, $event_id
        ));
        
        if ($cart) {
            return $cart;
        }
    }
    
    // PASO 2: Si no hay contexto o no se encontró carrito, usar el carrito marcado como activo
    $active_cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
    
    if ($active_cart_id) {
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_carts 
            WHERE id = %d AND user_id = %d AND status = 'active'",
            $active_cart_id, $user_id
        ));
        
        if ($cart) {
            return $cart;
        }
    }
    
    // PASO 3: Si no se encontró ningún carrito, buscar el más reciente
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_carts 
        WHERE user_id = %d AND status = 'active'
        ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));
}