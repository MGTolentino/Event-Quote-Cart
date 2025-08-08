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
            $context_lead_id = null;
            $context_event_id = null;
        } else if ($context_lead_id && $context_event_id) {
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
        // Limpiar carritos duplicados para este contexto
        $duplicates = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
            ORDER BY created_at DESC",
            $user_id, $context_lead_id, $context_event_id
        ));
        
        if (count($duplicates) > 1) {
            // Mantener solo el más reciente, desactivar el resto
            $keep_cart = array_shift($duplicates);
            $context_cart_id = $keep_cart->id;
            
            foreach ($duplicates as $duplicate) {
                $wpdb->update(
                    $wpdb->prefix . 'eq_carts',
                    array('status' => 'inactive'),
                    array('id' => $duplicate->id)
                );
            }
        } else {
            $context_cart_id = $duplicates ? $duplicates[0]->id : null;
        }
        
        if ($context_cart_id) {
            // Actualizar meta de usuario para consistencia
            update_user_meta($user_id, 'eq_active_cart_id', $context_cart_id);
            
            // Usar este carrito específico
            $active_cart_id = $context_cart_id;
        } else {
            // Si no existe un carrito para este contexto, crear uno
            if ($is_privileged) {
                $new_cart_id = $wpdb->get_var($wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}eq_carts (user_id, lead_id, event_id, status, created_at) 
                    VALUES (%d, %d, %d, 'active', NOW()) 
                    ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",
                    $user_id, $context_lead_id, $context_event_id
                ));
                
                if (!$new_cart_id) {
                    $new_cart_id = $wpdb->insert_id;
                }
                
                if ($new_cart_id) {
                    update_user_meta($user_id, 'eq_active_cart_id', $new_cart_id);
                    $active_cart_id = $new_cart_id;
                } else {
                    return array();
                }
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
            
        }
        
        // Si aún no hay carrito activo, no hay items
        if (!$active_cart_id) {
            return array();
        }
    }
    
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_cart_items 
        WHERE cart_id = %d AND status = 'active'
        ORDER BY created_at DESC",
        $active_cart_id
    ));
    
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
        $total += isset($item->total_price) ? floatval($item->total_price) : 0;
    }
    
    // Calcular el subtotal y los impuestos basados en el total
    // Usar exactamente la misma fuente de tax rate que el tema Kava-Child
    global $wpdb;
    $tax_rate_db = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d",
            1
        )
    );
    
    // DEBUG: Log para ver qué está pasando
    $debug_log = "TAX CALCULATION DEBUG:\n";
    $debug_log .= "  - Tax rate from DB: " . var_export($tax_rate_db, true) . "\n";
    $debug_log .= "  - Total items sum: " . $total . "\n";
    
    // Si no se encuentra, usar 16 como fallback (no 0)
    $tax_rate = floatval($tax_rate_db) ?: 16;
    $debug_log .= "  - Final tax rate used: " . $tax_rate . "\n";
    
    $subtotal = $total / (1 + ($tax_rate / 100));
    $tax = $total - $subtotal;
    
    $debug_log .= "  - Calculated subtotal: " . $subtotal . "\n";
    $debug_log .= "  - Calculated tax: " . $tax . "\n";
    
    error_log("QUOTE TAX DEBUG: " . $debug_log);
    
    // Guardar los valores numéricos también para evitar recálculos
    return array(
        'subtotal' => hivepress()->woocommerce->format_price($subtotal),
        'tax' => hivepress()->woocommerce->format_price($tax),
        'total' => hivepress()->woocommerce->format_price($total),
        'subtotal_raw' => $subtotal,
        'tax_raw' => $tax,
        'total_raw' => $total
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
    
    if (isset($_SESSION['eq_context_no_restore']) && $_SESSION['eq_context_no_restore'] === true) {
        return null;
    }
    
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
                WHERE _ID = %d AND usuario_asignado = %d",
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
    
    if (isset($_COOKIE['eq_session_ended']) && $_COOKIE['eq_session_ended'] === 'true') {
        return null;
    }
    
    $user = wp_get_current_user();
    $is_privileged = in_array('administrator', $user->roles) || in_array('ejecutivo_de_ventas', $user->roles);
    
    if (!$is_privileged) {
        return null;
    }
    
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

/**
 * Obtiene traducciones para JavaScript
 * 
 * @param string $context Contexto de las traducciones ('main' o 'single')
 * @return array Array con traducciones
 */
function eq_get_js_translations($context = 'main') {
    $translations = array(
        'addedToCart' => __('Added to quote cart', 'event-quote-cart'),
        'errorAdding' => __('Error adding to quote cart', 'event-quote-cart'),
        'removedFromCart' => __('Removed from quote cart', 'event-quote-cart'),
        'errorRemoving' => __('Error removing from quote cart', 'event-quote-cart'),
        'dateRequired' => __('Please select a date', 'event-quote-cart'),
        'dateNotAvailable' => __('Selected date is not available', 'event-quote-cart'),
        'dateAvailable' => __('Date is available', 'event-quote-cart'),
        'errorValidating' => __('Error validating date', 'event-quote-cart'),
        'adding' => __('Adding...', 'event-quote-cart'),
        'updating' => __('Updating...', 'event-quote-cart'),
        'checking' => __('Checking availability...', 'event-quote-cart'),
        'selectDate' => __('Select date', 'event-quote-cart'),
        'editDate' => __('Edit date', 'event-quote-cart'),
        'confirmRemove' => __('Are you sure you want to remove this item?', 'event-quote-cart'),
        'dateConflict' => __('This date conflicts with another booking', 'event-quote-cart'),
        'quantityRequired' => __('Please enter a valid quantity', 'event-quote-cart'),
        'invalidQuantity' => __('Quantity must be between %d and %d', 'event-quote-cart'),
        'dateOutOfRange' => __('Selected date is outside the allowed booking window', 'event-quote-cart'),
        'loadingError' => __('Error loading data', 'event-quote-cart'),
        'checkingAvailability' => __('Checking availability...', 'event-quote-cart'),
        'dateAvailable' => __('This date is available', 'event-quote-cart'),
        'dateNotAvailable' => __('This date is not available', 'event-quote-cart'),
        'errorChecking' => __('Error checking availability', 'event-quote-cart')
    );

    if ($context === 'main') {
        $translations = array_merge($translations, array(
            'seeDetails' => __('See Details', 'event-quote-cart'),
            'hideDetails' => __('Hide Details', 'event-quote-cart'),
            'noItems' => __('No items in quote cart', 'event-quote-cart'),
            'basePrice' => __('Base Price:', 'event-quote-cart'),
            'totalWithTaxes' => __('Total (incl. taxes):', 'event-quote-cart'),
            'updateQuote' => __('Update Quote', 'event-quote-cart'),
            'includeInQuote' => __('Include in Quote', 'event-quote-cart'),
            'editingReservation' => __('Editing existing reservation', 'event-quote-cart'),
            'date' => __('Date', 'event-quote-cart'),
            'quantity' => __('Quantity', 'event-quote-cart'),
            'extras' => __('Extras', 'event-quote-cart'),
            'extras2' => __('Extras:', 'event-quote-cart'),
            'notAvailable' => __('Not available for selected date', 'event-quote-cart'),
            'itemUpdated' => __('Item updated successfully', 'event-quote-cart'),
            'itemAdded' => __('Item added to quote successfully', 'event-quote-cart'),
            'dateConflictItems' => __('This date affects %s item(s) in your cart that are not available on this date: %s. Would you like to remove these items and update the date for the rest?', 'event-quote-cart'),
            'dateConflictUpdate' => __('You already have %s item(s) in your cart with a different date. Would you like to update all to the date %s?', 'event-quote-cart'),
            'errorUpdatingDate' => __('Error updating cart date', 'event-quote-cart')
        ));
    }

    return $translations;
}