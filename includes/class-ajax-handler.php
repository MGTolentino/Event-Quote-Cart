<?php
/**
 * AJAX Handler Class
 * Maneja todas las operaciones AJAX del carrito de cotizaciones
 */

class Event_Quote_Cart_Ajax_Handler {
    private $date_handler;

    public function __construct() {
        $this->date_handler = new Event_Quote_Cart_Date_Handler();
        $this->init_hooks();
    }

    public function init_hooks() {
        // Hooks para manejo del carrito
        add_action('wp_ajax_eq_get_listing_data', array($this, 'get_listing_data'));
        add_action('wp_ajax_eq_add_to_cart', array($this, 'add_to_cart'));
        add_action('wp_ajax_eq_update_cart_item', array($this, 'update_cart_item'));
        add_action('wp_ajax_eq_get_cart_items', array($this, 'get_cart_items'));
		add_action('wp_ajax_eq_get_cart_item', array($this, 'get_cart_item'));
		add_action('wp_ajax_eq_generate_quote_pdf', array($this, 'generate_quote_pdf'));
		add_action('wp_ajax_eq_send_quote_email', array($this, 'send_quote_email'));
		add_action('wp_ajax_eq_generate_whatsapp_link', array($this, 'generate_whatsapp_link'));
		add_action('wp_ajax_eq_validate_cart_date', array($this, 'validate_cart_date'));
		add_action('wp_ajax_eq_validate_cart_date_change', array($this, 'validate_cart_date_change'));
add_action('wp_ajax_eq_update_cart_date', array($this, 'update_cart_date'));
		add_action('wp_ajax_eq_get_cart_master_date', array($this, 'get_cart_master_date'));
		add_action('wp_ajax_eq_get_cart_totals', array($this, 'get_cart_totals'));
		add_action('wp_ajax_eq_clear_context_meta', array($this, 'clear_context_meta'));
		add_action('wp_ajax_eq_check_context_status', array($this, 'check_context_status'));
		add_action('wp_ajax_eq_update_cart_context', array($this, 'update_cart_context'));
		add_action('wp_ajax_eq_update_event_date', array($this, 'update_event_date'));
add_action('wp_ajax_eq_duplicate_event', array($this, 'duplicate_event'));
		add_action('wp_ajax_eq_check_event_exists', array($this, 'check_event_exists'));
		add_action('wp_ajax_eq_get_lead_email', array($this, 'get_lead_email'));
		add_action('wp_ajax_eq_get_email_template', array($this, 'get_email_template'));
		add_action('wp_ajax_eq_get_whatsapp_template', array($this, 'get_whatsapp_template'));
		add_action('wp_ajax_eq_validate_all_cart_items', array($this, 'validate_all_cart_items'));
        
        // Hooks para manejo de fechas
        add_action('wp_ajax_eq_validate_date', array($this, 'validate_date'));
        add_action('wp_ajax_eq_validate_cart_items', array($this, 'validate_cart_items'));
		    add_action('wp_ajax_eq_update_cart_event', array($this, 'update_cart_event'));

     // Nuevos hooks para sincronización de contexto
    add_action('wp_ajax_eq_create_context_session', array($this, 'create_context_session'));
    add_action('wp_ajax_eq_verify_context_session', array($this, 'verify_context_session'));
		add_action('wp_ajax_eq_verify_context_cleared', array($this, 'verify_context_cleared'));
		add_action('wp_ajax_eq_check_item_in_cart', array($this, 'check_item_in_cart'));
		
		// Hooks para historial de carrito
		add_action('wp_ajax_eq_save_cart_history', array($this, 'save_cart_history'));
		add_action('wp_ajax_eq_get_cart_history', array($this, 'get_cart_history'));
		add_action('wp_ajax_eq_restore_cart_history', array($this, 'restore_cart_history'));
		
		// Hook para limpiar contexto al hacer logout
		add_action('wp_logout', array($this, 'clear_context_on_logout'));
}

    public function get_listing_data() {
		
		
		
        check_ajax_referer('eq_cart_public_nonce', 'nonce');

        // Verificación estricta de permisos
    if (!function_exists('eq_can_view_quote_button') || !eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    // Verificación adicional - comprobar roles directamente
    $user = wp_get_current_user();
    if (!$user || !in_array('administrator', $user->roles) && !in_array('ejecutivo_de_ventas', $user->roles)) {
        wp_send_json_error('Unauthorized');
        return;
    }

        $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
        
        if (!$listing_id) {
            wp_send_json_error('Invalid listing ID');
        }

        try {
            // Obtener datos del listing
            $listing = get_post($listing_id);
            if (!$listing || $listing->post_type !== 'hp_listing') {
                throw new Exception('Listing not found');
            }

            // Obtener imagen destacada
            $image = get_the_post_thumbnail_url($listing_id, 'medium');
            
            // Obtener datos adicionales
            $response_data = array(
                'id' => $listing_id,
                'title' => get_the_title($listing_id),
                'image' => $image ? $image : '',
                'price' => get_post_meta($listing_id, 'hp_price', true),
                'min_quantity' => get_post_meta($listing_id, 'hp_booking_min_quantity', true),
                'max_quantity' => get_post_meta($listing_id, 'hp_booking_max_quantity', true)
            );

            // Obtener extras si existen
            $extras = get_post_meta($listing_id, 'hp_price_extras', true);
            if (is_array($extras)) {
                $response_data['extras'] = $this->format_extras($extras);
            }

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

public function add_to_cart() {
    global $wpdb;
    check_ajax_referer('eq_cart_public_nonce', 'nonce');

     // Verificación estricta de permisos
    if (!function_exists('eq_can_view_quote_button') || !eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    // Verificación adicional - comprobar roles directamente
    $user = wp_get_current_user();
    if (!$user || !in_array('administrator', $user->roles) && !in_array('ejecutivo_de_ventas', $user->roles)) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $extras = isset($_POST['extras']) ? $_POST['extras'] : array();
    
    // Datos adicionales para rangos de fechas
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    $days_count = isset($_POST['days_count']) ? intval($_POST['days_count']) : 1;
    $is_date_range = isset($_POST['is_date_range']) ? (bool)$_POST['is_date_range'] : false;

    // Validar y convertir fecha si es timestamp
    if (!empty($date)) {
        if (is_numeric($date) && strlen($date) == 10) {
            // Es un timestamp Unix, convertir a fecha Y-m-d
            $date = date('Y-m-d', intval($date));
        } elseif (is_numeric($date) && strlen($date) == 13) {
            // Es un timestamp en milisegundos, convertir a fecha Y-m-d
            $date = date('Y-m-d', intval($date) / 1000);
        }
        
        // Validar que la fecha tenga formato correcto
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error('Invalid date format. Expected Y-m-d format.');
            return;
        }
    }

    if (!$listing_id || !$date) {
        wp_send_json_error('Missing required data');
    }

    try {
		
        // 1. Primero obtener el cart_id
        // Obtener lead_id y event_id del contexto de cotización (si existe)
$lead_id = null;
$event_id = null;

// Si hay un contexto activo en sessionStorage, los parámetros vendrán en la solicitud AJAX
if (isset($_POST['context_lead_id']) && !empty($_POST['context_lead_id'])) {
    $lead_id = intval($_POST['context_lead_id']);
}

if (isset($_POST['context_event_id']) && !empty($_POST['context_event_id'])) {
    $event_id = intval($_POST['context_event_id']);
}

$cart_id = $this->get_or_create_cart($lead_id, $event_id);
		
		
$total_price = isset($_POST['calculated_price']) ? 
    floatval($_POST['calculated_price']) : 
    $this->calculate_price($listing_id, $quantity, $extras, $date);

if (!$this->date_handler->check_listing_availability($listing_id, $date)) {
    throw new Exception('Date not available for this listing');
}

$base_price = get_post_meta($listing_id, 'hp_price', true);

$listing_extras = get_post_meta($listing_id, 'hp_price_extras', true);

foreach ($extras as &$extra) {
    $extra_id = isset($extra['id']) ? $extra['id'] : '';
    if (isset($listing_extras[$extra_id])) {
        $listing_extra = $listing_extras[$extra_id];
        
        // Copiar información importante al array de extras
        $extra['price'] = floatval($listing_extra['price']);
        $extra['name'] = $listing_extra['name'];
            }
}
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_cart_items 
            WHERE cart_id = %d 
            AND listing_id = %d 
            AND status = 'active'",
            $cart_id,
            $listing_id
        );
        
        $existing_item = $wpdb->get_row($query);
		

// Verificación de último momento para asegurar el precio correcto
if (!empty($date)) {
    // Código para obtener el precio específico del día, similar al de calculate_price
    $booking_ranges = get_post_meta($listing_id, 'hp_booking_ranges', true);
    if (!empty($booking_ranges) && is_array($booking_ranges)) {
        $date_obj = new DateTime($date);
        $day_of_week = intval($date_obj->format('w'));
        
        foreach ($booking_ranges as $range) {
            if (isset($range['days']) && is_array($range['days']) && 
                in_array($day_of_week, $range['days']) && 
                isset($range['price'])) {
                // Forzar el precio correcto
                $day_price = floatval($range['price']);
                
                // Si el base_price no es el precio del día, corregirlo
                if ($base_price != $day_price) {
                    $base_price = $day_price;
                    $total_price = $this->recalculate_total_with_new_base($day_price, $quantity, $extras);
                }
                break;
            }
        }
    }
}

        if ($existing_item) {
            // 8a. Actualizar el item existente
            $wpdb->update(
                $wpdb->prefix . 'eq_cart_items',
                array(
                    'form_data' => json_encode(array(
                        'date' => $date,
                        'quantity' => $quantity,
                        'extras' => $extras,
                        'base_price' => $base_price,
                        'total_price' => $total_price
                    )),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing_item->id),
                array('%s', '%s'),
                array('%d')
            );

            $item_id = $existing_item->id;
            
            // Eliminar rango de fechas existente si hay uno
            if ($is_date_range) {
                $wpdb->delete(
                    $wpdb->prefix . 'eq_cart_date_ranges',
                    array('cart_item_id' => $item_id),
                    array('%d')
                );
            }
			
			// Verificar otros ítems en el carrito
$other_items = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}eq_cart_items 
    WHERE cart_id = %d AND status = 'active' AND id != %d",
    $cart_id, $item_id
));

foreach ($other_items as $other_item) {
    $other_form_data = json_decode($other_item->form_data, true);
}
			
        } else {
            // 8b. Crear nuevo item
				$item_data = array(
					'cart_id' => $cart_id,
					'listing_id' => $listing_id,
					'status' => 'active',
					'form_data' => json_encode(array(
						'date' => $date,
						'quantity' => $quantity,
						'extras' => $extras,
						'base_price' => $base_price,
						'total_price' => $total_price
					)),
					'created_at' => current_time('mysql'),
					'updated_at' => current_time('mysql'),
					'order_in_cart' => $this->get_next_order_position($cart_id)
				);

            $wpdb->insert(
                $wpdb->prefix . 'eq_cart_items',
                $item_data,
                    array('%d', '%d', '%s', '%s', '%s', '%s', '%d') 
            );

            $item_id = $wpdb->insert_id;
        }

        // Guardar información de rango de fechas si aplica
        if ($is_date_range && $end_date && $item_id) {
            // Preparar información de extras que se multiplicaron por días
            $extras_info = array();
            foreach ($extras as $extra) {
                if (isset($extra['multiplied_by_days']) && $extra['multiplied_by_days']) {
                    $extras_info[] = array(
                        'id' => $extra['id'],
                        'original_days' => isset($extra['original_days']) ? $extra['original_days'] : $days_count
                    );
                }
            }

            $wpdb->insert(
                $wpdb->prefix . 'eq_cart_date_ranges',
                array(
                    'cart_item_id' => $item_id,
                    'start_date' => $date,
                    'end_date' => $end_date,
                    'days_count' => $days_count,
                    'extras_info' => json_encode($extras_info),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%s', '%s')
            );
        }

        // 9. Obtener datos completos del item
        $item_data = $this->get_cart_item_data($item_id);

        // 10. Save history snapshot for admins and sales executives
        if (current_user_can('administrator') || current_user_can('ejecutivo_de_ventas')) {
            $this->save_cart_history_snapshot($existing_item ? 'item_updated' : 'item_added');
        }

        wp_send_json_success(array(
            'item' => $item_data,
            'message' => $existing_item ? 'Item updated successfully' : 'Item added to cart successfully'
        ));

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

    public function update_cart_item() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');

    // Verificación estricta de permisos
    if (!function_exists('eq_can_view_quote_button') || !eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    // Verificación adicional - comprobar roles directamente
    $user = wp_get_current_user();
    if (!$user || !in_array('administrator', $user->roles) && !in_array('ejecutivo_de_ventas', $user->roles)) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // Procesar extras_data si viene como JSON string (desde quote-cart-page.js)
    $extras = [];
    if (isset($_POST['extras_data']) && !empty($_POST['extras_data'])) {
        // Decodificar extras_data
        $extras_data = json_decode(stripslashes($_POST['extras_data']), true);
        if (is_array($extras_data)) {
            $extras = $extras_data;
        }
    } elseif (isset($_POST['extras']) && is_array($_POST['extras'])) {
        // Procesar extras en el formato tradicional (para compatibilidad)
        $extras = $_POST['extras'];
    }
    

    if (!$item_id) {
        wp_send_json_error('Invalid item ID');
    }

    try {
        global $wpdb;
        
        // Obtener item actual
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_cart_items WHERE id = %d",
            $item_id
        ));

        if (!$item) {
            throw new Exception('Item not found');
        }
        
        $listing_id = $item->listing_id;
        
        // Verificar disponibilidad si la fecha cambió
        if ($date && isset($current_data['date']) && $date !== $current_data['date']) {
            if (!$this->date_handler->check_listing_availability($listing_id, $date)) {
                throw new Exception('Date not available for this listing');
            }
        }

        // CAMBIO IMPORTANTE: Usar recalculate_total_with_new_base para obtener el precio correcto
        // Primero, obtener el precio base según el día de la semana
        $base_price = get_post_meta($listing_id, 'hp_price', true);
        
        // Verificar si hay precio específico para el día
        $booking_ranges = get_post_meta($listing_id, 'hp_booking_ranges', true);
        if (!empty($booking_ranges) && is_array($booking_ranges) && !empty($date)) {
            $date_obj = new DateTime($date);
            $day_of_week = intval($date_obj->format('w'));
            
            foreach ($booking_ranges as $range) {
                if (isset($range['days']) && is_array($range['days']) && 
                    in_array($day_of_week, $range['days']) && 
                    isset($range['price'])) {
                    $base_price = floatval($range['price']);
                    break;
                }
            }
        }
        
        // Calcular el precio total usando recalculate_total_with_new_base (que ahora incluye impuestos)
        $total_price = $this->recalculate_total_with_new_base($base_price, $quantity, $extras);
        
        // Preparar datos actualizados
        $updated_form_data = array(
            'date' => $date,
            'quantity' => $quantity,
            'extras' => $extras,
            'base_price' => $base_price,
            'total_price' => $total_price
        );
        

        $wpdb->update(
            $wpdb->prefix . 'eq_cart_items',
            array(
                'form_data' => json_encode($updated_form_data),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $item_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        // Obtener datos actualizados
        $item_data = $this->get_cart_item_data($item_id);

        wp_send_json_success(array(
            'item' => $item_data,
            'message' => 'Item updated successfully'
        ));

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
	
	/**
 * Actualiza el evento asociado al carrito
 */
public function update_cart_event() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $event_id = isset($_POST['eq_event_id']) ? sanitize_text_field($_POST['eq_event_id']) : '';
    
    if (empty($event_id)) {
        wp_send_json_error('No event selected');
        return;
    }
    
    try {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Si es un nuevo evento, crearlo primero
        if ($event_id === 'new') {
            $event_type = isset($_POST['eq_new_event_type']) ? sanitize_text_field($_POST['eq_new_event_type']) : '';
            $event_date = isset($_POST['eq_new_event_date']) ? sanitize_text_field($_POST['eq_new_event_date']) : '';
            $event_guests = isset($_POST['eq_new_event_guests']) ? intval($_POST['eq_new_event_guests']) : 0;
            
            if (empty($event_type) || empty($event_date)) {
                wp_send_json_error('Event type and date are required');
                return;
            }
            
            // Buscar el lead del usuario
            $lead = $wpdb->get_row($wpdb->prepare(
                "SELECT _ID FROM {$wpdb->prefix}jet_cct_leads WHERE lead_e_mail = %s",
                wp_get_current_user()->user_email
            ));
            
            if (!$lead) {
                wp_send_json_error('Lead not found');
                return;
            }
            
            // Convertir fecha a timestamp
            
            // Intentar usar DateTime para conversión más robusta
            try {
                // Intentar crear DateTime con zona horaria específica
                $timezone = new DateTimeZone(get_option('timezone_string') ?: 'America/Mexico_City');
                $dateObj = new DateTime($event_date . ' 00:00:00', $timezone);
                $date_timestamp = $dateObj->getTimestamp();
            } catch (Exception $e) {
                // Fallback a strtotime
                $date_timestamp = strtotime($event_date . ' 00:00:00');
            }
            
            if ($date_timestamp === false || $date_timestamp === 0) {
                $date_timestamp = strtotime('+1 day'); // Usar mañana como fallback
            }
            
            // Crear nuevo evento
            $result = $wpdb->insert(
                $wpdb->prefix . 'jet_cct_eventos',
                array(
                    'lead_id' => $lead->_ID,
                    'fecha_de_evento' => $date_timestamp,
                    'tipo_de_evento' => $event_type,
                    'evento_asistentes' => $event_guests,
                    'evento_status' => 'nuevo-no-contactado',
                    'cct_status' => 'publish',
                    'cct_created' => current_time('mysql'),
                    'cct_modified' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s')
            );
            
            if (!$result) {
            }
            
            if (!$result) {
                throw new Exception($wpdb->last_error);
            }
            
            $event_id = $wpdb->insert_id;
            
            // Verificar qué se guardó realmente en la BD
            $saved_event = $wpdb->get_row($wpdb->prepare(
                "SELECT fecha_de_evento FROM {$wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
                $event_id
            ));
            if ($saved_event) {
            }
        }
        
        // Actualizar carrito con el nuevo evento
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
        
        if (!$cart) {
            throw new Exception('Cart not found');
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'eq_carts',
            array(
                'event_id' => $event_id,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $cart->id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception($wpdb->last_error);
        }
        
        // Preguntamos si quiere actualizar también las fechas de los items
        $update_dates = isset($_POST['eq_update_dates']) && $_POST['eq_update_dates'] === 'yes';
        
        if ($update_dates) {
            // Obtener la fecha del evento
            $evento = $wpdb->get_row($wpdb->prepare(
                "SELECT fecha_de_evento FROM {$wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
                $event_id
            ));
            
            if ($evento) {
                $event_date = date('Y-m-d', $evento->fecha_de_evento);
                
                // Actualizar fechas de los items
                $items = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}eq_cart_items 
                    WHERE cart_id = %d AND status = 'active'",
                    $cart->id
                ));
                
                foreach ($items as $item) {
                    $form_data = json_decode($item->form_data, true);
                    $form_data['date'] = $event_date;
                    
                    $wpdb->update(
                        $wpdb->prefix . 'eq_cart_items',
                        array(
                            'form_data' => json_encode($form_data),
                            'updated_at' => current_time('mysql')
                        ),
                        array('id' => $item->id),
                        array('%s', '%s'),
                        array('%d')
                    );
                }
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Event updated successfully',
            'event_id' => $event_id
        ));
        
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
	

/**
 * Calcula el precio total de un item basado en el precio base, cantidad y extras
 *
 * @param int $listing_id ID del listing
 * @param int $quantity Cantidad
 * @param array $extras Array de extras seleccionados
 * @return float Precio total calculado
 */
private function calculate_price($listing_id, $quantity, $extras, $date = '') {
    
    // Obtener precio base del listing
    $base_price = floatval(get_post_meta($listing_id, 'hp_price', true));
    
    // Si no se proporcionó fecha como parámetro, intentar obtenerla de POST
    if (empty($date) && isset($_POST['date'])) {
        $date = sanitize_text_field($_POST['date']);
    }
    
    // Verificar si hay una fecha seleccionada para aplicar precio específico del día
    if (!empty($date)) {
        // Obtener rangos de precios por día si existen
        $booking_ranges = get_post_meta($listing_id, 'hp_booking_ranges', true);
        
        if (!empty($booking_ranges) && is_array($booking_ranges)) {
            // Convertir la fecha a día de la semana (0 domingo, 6 sábado)
            $date_obj = new DateTime($date);
            $day_of_week = intval($date_obj->format('w'));
            
            // Buscar si hay un precio específico para este día
            foreach ($booking_ranges as $range) {
                if (isset($range['days']) && is_array($range['days']) && 
                    in_array($day_of_week, $range['days']) && 
                    isset($range['price'])) {
                    $base_price = floatval($range['price']);
                    break;
                }
            }
        }
    }
    
    // Calcular subtotal (precio base * cantidad)
    $total_price = $base_price * $quantity;
    
    // Procesar extras si existen
    if (!empty($extras) && is_array($extras)) {
        // Obtener todos los extras disponibles para este listing
        $listing_extras = get_post_meta($listing_id, 'hp_price_extras', true);
        
        foreach ($extras as $extra) {
            // Verificar que el extra exista en los extras del listing
            $extra_id = isset($extra['id']) ? $extra['id'] : '';
            $extra_price = 0;
            $extra_type = '';
            $extra_name = '';
            
            // Obtener datos del extra
            if (!empty($extra_id) && isset($listing_extras[$extra_id])) {
                $extra_price = floatval($listing_extras[$extra_id]['price']);
                $extra_type = isset($listing_extras[$extra_id]['type']) ? $listing_extras[$extra_id]['type'] : '';
                $extra_name = isset($listing_extras[$extra_id]['name']) ? $listing_extras[$extra_id]['name'] : '';
            } else {
                // Si no tenemos datos del listing, usar los proporcionados en el extra
                $extra_price = isset($extra['price']) ? floatval($extra['price']) : 0;
                $extra_type = isset($extra['type']) ? $extra['type'] : '';
                $extra_name = isset($extra['name']) ? $extra['name'] : '';
            }
            
            // Obtener la cantidad del extra
            $extra_quantity = isset($extra['quantity']) ? intval($extra['quantity']) : 1;
            
            
            // Variable para almacenar cuánto se suma por este extra
            $extra_total = 0;
            
            // Calcular precio según el tipo de extra
            switch($extra_type) {
                case 'per_quantity':
                    // Para extras que se aplican por cada ítem
                    $extra_total = $extra_price * $quantity;
                    break;
                case 'variable_quantity':
                    // Para extras con cantidad variable
                    $extra_total = $extra_price * $extra_quantity;
                    break;
                case 'per_order':
                case 'per_booking':
                case 'per_item': // Tratar per_item igual que per_order
                    // Para extras que se aplican una vez por orden
                    $extra_total = $extra_price;
                    break;
                default:
                    // Si no hay tipo definido o no reconocido, tratar como precio por ítem
                    $extra_total = $extra_price * $quantity;
            }
            
            // Sumar al total
            $total_price += $extra_total;
            
          
        }
    }
    
    
    return $total_price;
}
	
	private function recalculate_total_with_new_base($day_price, $quantity, $extras) {
    $total = $day_price * $quantity;
    
    if (!empty($extras) && is_array($extras)) {
        foreach ($extras as $extra) {
            $extra_price = isset($extra['price']) ? floatval($extra['price']) : 0;
            $extra_type = isset($extra['type']) ? $extra['type'] : '';
            $extra_quantity = isset($extra['quantity']) ? intval($extra['quantity']) : 1;
            
            switch($extra_type) {
                case 'per_quantity':
                    $total += $extra_price * $quantity;
                    break;
                case 'variable_quantity':
                    $total += $extra_price * $extra_quantity;
                    break;
                case 'per_order':
                case 'per_booking':
                case 'per_item':
                    $total += $extra_price;
                    break;
                default:
                    $total += $extra_price * $quantity;
            }
        }
    }
    
    // Aplicar impuestos al total usando la misma fuente que el tema
    global $wpdb;
    $tax_rate_db = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d",
            1
        )
    );
    $tax_rate = floatval($tax_rate_db) ?: 16;
    $total_with_taxes = $total * (1 + ($tax_rate / 100));
    
    return $total_with_taxes;
}

 public function get_cart_items() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');

    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
    }

    try {
        // Usar la función auxiliar que ahora respeta el contexto de sesión
        $items = eq_get_cart_items();
        
        // Obtener información del carrito actual
        $user_id = get_current_user_id();
        $active_cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
        
        // Contexto para la respuesta
        $context = null;
        if ($active_cart_id) {
            global $wpdb;
            $cart_info = $wpdb->get_row($wpdb->prepare(
                "SELECT lead_id, event_id FROM {$wpdb->prefix}eq_carts 
                WHERE id = %d",
                $active_cart_id
            ));
            
            if ($cart_info) {
                $context = array(
                    'lead_id' => $cart_info->lead_id,
                    'event_id' => $cart_info->event_id
                );
            }
        }
        
        wp_send_json_success(array(
            'items' => $items,
            'cart_id' => $active_cart_id ? $active_cart_id : 0,
            'context' => $context
        ));

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

    public function validate_date() {
        check_ajax_referer('eq_cart_public_nonce', 'nonce');

        if (!eq_can_view_quote_button()) {
            wp_send_json_error('Unauthorized');
        }

        $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if (!$listing_id || !$date) {
            wp_send_json_error('Missing required data');
        }

        try {
            $is_available = $this->date_handler->check_listing_availability($listing_id, $date);
            
            wp_send_json_success(array(
                'available' => $is_available,
                'message' => $is_available ? 'Date is available' : 'Date is not available for this listing'
            ));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function validate_cart_items() {
        check_ajax_referer('eq_cart_public_nonce', 'nonce');

        if (!eq_can_view_quote_button()) {
            wp_send_json_error('Unauthorized');
        }

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if (!$date) {
            wp_send_json_error('Missing date');
        }

        try {
            $validation_results = $this->date_handler->validate_cart_items($date);
            
            wp_send_json_success(array(
                'validations' => $validation_results,
                'date' => $date
            ));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

  private function get_or_create_cart($lead_id = null, $event_id = null, $date = null) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $user = get_user_by('id', $user_id);
    
    
    if ($lead_id !== null && $event_id !== null) {
        // Buscar un carrito existente con esta combinación exacta lead_id/event_id
        $existing_cart = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id, $lead_id, $event_id
        ));
        
        // Si existe, usarlo
        if ($existing_cart) {
            
            // Actualizar como carrito activo del usuario
            update_user_meta($user_id, 'eq_active_cart_id', $existing_cart);
            
            return intval($existing_cart);
        }
        
        // Si no existe, crear uno nuevo específicamente para esta combinación
        
        $cart_data = array(
            'user_id' => $user_id,
            'lead_id' => $lead_id,
            'event_id' => $event_id,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'eq_carts',
            $cart_data,
            array('%d', '%d', '%d', '%s', '%s', '%s')
        );
        
        $new_cart_id = $wpdb->insert_id;
        
        // Actualizar como carrito activo del usuario
        update_user_meta($user_id, 'eq_active_cart_id', $new_cart_id);
        
        return intval($new_cart_id);
    }
    
    $is_privileged_user = current_user_can('administrator') || current_user_can('ejecutivo_de_ventas');
    
    // Para usuarios normales, intentar buscar o crear automáticamente
    if (!$is_privileged_user) {
        // 2.1. Buscar lead basado en el email del usuario
        if ($lead_id === null && $user) {
            $user_email = $user->user_email;
            
            $lead = $wpdb->get_row($wpdb->prepare(
                "SELECT _ID FROM {$wpdb->prefix}jet_cct_leads 
                WHERE lead_e_mail = %s",
                $user_email
            ));
            
            if ($lead) {
                $lead_id = $lead->_ID;
            }
        }
        
        // 2.2. Si encontramos un lead y no se proporcionó un event_id, buscar el evento apropiado
        if ($lead_id !== null && $event_id === null) {
            // Obtener la fecha de cotización del POST o del parámetro
            if ($date === null && isset($_POST['date'])) {
                $date = sanitize_text_field($_POST['date']);
            }
            
            if ($date) {
                // Convertir fecha a timestamp para comparación
                $date_timestamp = strtotime($date);
                
                if ($date_timestamp) {
                    // 2.2.1. Buscar evento con coincidencia exacta de fecha
                    $evento = $wpdb->get_row($wpdb->prepare(
                        "SELECT _ID FROM {$wpdb->prefix}jet_cct_eventos 
                        WHERE lead_id = %d AND fecha_de_evento = %s",
                        $lead_id, $date_timestamp
                    ));
                    
                    if ($evento) {
                        $event_id = $evento->_ID;
                    } else {
                        // 2.2.2. Si no hay coincidencia exacta, buscar un evento cercano (±7 días)
                        $siete_dias = 7 * 24 * 60 * 60; // 7 días en segundos
                        $fecha_min = $date_timestamp - $siete_dias;
                        $fecha_max = $date_timestamp + $siete_dias;
                        
                        $evento_cercano = $wpdb->get_row($wpdb->prepare(
                            "SELECT _ID FROM {$wpdb->prefix}jet_cct_eventos 
                            WHERE lead_id = %d AND fecha_de_evento BETWEEN %s AND %s
                            ORDER BY ABS(fecha_de_evento - %s) ASC LIMIT 1",
                            $lead_id, $fecha_min, $fecha_max, $date_timestamp
                        ));
                        
                        if ($evento_cercano) {
                            $event_id = $evento_cercano->_ID;
                        } else {
                            // 2.2.3. Si no hay evento cercano, crear uno nuevo
                            $event_id = $this->create_new_event_for_lead($lead_id, $date_timestamp);
                            if ($event_id) {
                            }
                        }
                    }
                }
            }
        }
    }
    
    if ($lead_id !== null && $event_id !== null) {
        // Buscar un carrito existente con esta combinación exacta lead_id/event_id
        $existing_cart = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id, $lead_id, $event_id
        ));
        
        // Si existe, usarlo
        if ($existing_cart) {
            
            // Actualizar como carrito activo del usuario
            update_user_meta($user_id, 'eq_active_cart_id', $existing_cart);
            
            return intval($existing_cart);
        }
        
        // Si no existe, crear uno nuevo específicamente para esta combinación        
        $cart_data = array(
            'user_id' => $user_id,
            'lead_id' => $lead_id,
            'event_id' => $event_id,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'eq_carts',
            $cart_data,
            array('%d', '%d', '%d', '%s', '%s', '%s')
        );
        
        $new_cart_id = $wpdb->insert_id;
        
        // Actualizar como carrito activo del usuario
        update_user_meta($user_id, 'eq_active_cart_id', $new_cart_id);
        
        return intval($new_cart_id);
    }
    
    $active_cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
    
    if ($active_cart_id) {
        // Verificar que el carrito exista y esté activo
        $cart_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE id = %d AND user_id = %d AND status = 'active'",
            $active_cart_id, $user_id
        ));
        
        if ($cart_exists) {
            return intval($active_cart_id);
        }
    }
    
    $recent_cart_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}eq_carts 
        WHERE user_id = %d AND status = 'active'
        ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));
    
    if ($recent_cart_id) {
        // Actualizar meta para futuras referencias
        update_user_meta($user_id, 'eq_active_cart_id', $recent_cart_id);
        
        return intval($recent_cart_id);
    }
    
    $cart_data = array(
        'user_id' => $user_id,
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    );
    
    $wpdb->insert(
        $wpdb->prefix . 'eq_carts',
        $cart_data,
        array('%d', '%s', '%s', '%s')
    );
    
    $new_cart_id = $wpdb->insert_id;
    
    // Actualizar meta para futuras referencias
    update_user_meta($user_id, 'eq_active_cart_id', $new_cart_id);
    
    return intval($new_cart_id);
}
		
	
	
/**
 * Crea un nuevo evento para un lead
 *
 * @param int $lead_id ID del lead
 * @param int $date_timestamp Timestamp de la fecha del evento
 * @return int|null ID del evento creado o null en caso de error
 */
private function create_new_event_for_lead($lead_id, $date_timestamp) {
    global $wpdb;
    
    // Datos básicos para el nuevo evento
    $event_data = array(
        'lead_id' => $lead_id,
        'fecha_de_evento' => $date_timestamp,
        'tipo_de_evento' => 'Evento Cotizado', // Valor predeterminado
        'evento_status' => 'nuevo-no-contactado',
        'cct_status' => 'publish',
        'cct_created' => current_time('mysql'),
        'cct_modified' => current_time('mysql')
    );
    
    // Insertar el nuevo evento
    $result = $wpdb->insert(
        $wpdb->prefix . 'jet_cct_eventos',
        $event_data,
        array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
    );
    
    if ($result) {
        return $wpdb->insert_id;
    }
    
    return null;
}

   private function get_cart_item_data($item_id) {
    global $wpdb;
    
    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_cart_items WHERE id = %d",
        $item_id
    ));

    if (!$item) {
        throw new Exception('Item not found');
    }

    $listing = get_post($item->listing_id);
    $form_data = json_decode($item->form_data, true);

    // Asegurarse de que base_price refleje el precio específico del día si es aplicable
    $base_price = isset($form_data['base_price']) ? $form_data['base_price'] : 0;
    
    // Si no hay base_price en form_data, verificar si hay precio específico para el día
    if (!$base_price && !empty($form_data['date'])) {
        $base_price = $this->get_day_specific_price($item->listing_id, $form_data['date']);
    }

    return array(
        'id' => $item->id,
        'listing_id' => $item->listing_id,
        'title' => get_the_title($listing),
        'image' => get_the_post_thumbnail_url($listing->ID, 'medium'),
        'date' => $form_data['date'],
        'quantity' => $form_data['quantity'],
        'extras' => $form_data['extras'],
        'status' => $item->status,
        'created_at' => $item->created_at,
        'updated_at' => $item->updated_at,
        'base_price' => $base_price,
        'total_price' => $form_data['total_price'],
        'price_formatted' => hivepress()->woocommerce->format_price($form_data['total_price'])
    );
}

// Función auxiliar para obtener el precio específico del día
private function get_day_specific_price($listing_id, $date) {
    $base_price = get_post_meta($listing_id, 'hp_price', true);
    
    $booking_ranges = get_post_meta($listing_id, 'hp_booking_ranges', true);
    if (!empty($booking_ranges) && is_array($booking_ranges) && !empty($date)) {
        $date_obj = new DateTime($date);
        $day_of_week = intval($date_obj->format('w'));
        
        foreach ($booking_ranges as $range) {
            if (isset($range['days']) && is_array($range['days']) && 
                in_array($day_of_week, $range['days']) && 
                isset($range['price'])) {
                $base_price = floatval($range['price']);
                break;
            }
        }
    }
    
    return $base_price;
}

    private function format_extras($extras) {
        $formatted = array();
        foreach ($extras as $key => $extra) {
            $formatted[] = array(
                'id' => $key,
                'name' => $extra['name'],
                'price' => $extra['price'],
                'price_formatted' => hivepress()->woocommerce->format_price($extra['price']),
                'type' => isset($extra['type']) ? $extra['type'] : ''
            );
        }
        return $formatted;
    }
	
	private function get_next_order_position($cart_id) {
    global $wpdb;
    
    $max_order = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(order_in_cart) 
        FROM {$wpdb->prefix}eq_cart_items 
        WHERE cart_id = %d AND status = 'active'",
        $cart_id
    ));
    
    return (int)$max_order + 1;
}
	
	/**
 * Obtener un item específico del carrito
 */
public function get_cart_item() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');

    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
    }

    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

    if (!$item_id) {
        wp_send_json_error('Invalid item ID');
    }

    try {
        global $wpdb;
        
        // Obtener item
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_cart_items WHERE id = %d",
            $item_id
        ));

        if (!$item) {
            throw new Exception('Item not found');
        }

        // Parsear form_data
        $form_data = json_decode($item->form_data, true);
        
        // Obtener la fecha del ítem
        $item_date = isset($form_data['date']) ? $form_data['date'] : '';
        
        // Verificar si existe un rango de fechas para este item
        $date_range = $wpdb->get_row($wpdb->prepare(
            "SELECT start_date, end_date, days_count FROM {$wpdb->prefix}eq_cart_date_ranges WHERE cart_item_id = %d",
            $item_id
        ));
        
        // Obtener datos del listing pasando la fecha
        $listing_data = $this->get_listing_data_for_edit($item->listing_id, $item_date);
        
        // Combinar datos para la respuesta
        $response_data = array(
            'item_id' => $item->id,
            'listing_id' => $item->listing_id,
            'listing_data' => $listing_data,
            'form_data' => $form_data
        );
        
        // Agregar datos de rango de fechas si existen
        if ($date_range) {
            $response_data['is_date_range'] = true;
            $response_data['start_date'] = $date_range->start_date;
            $response_data['end_date'] = $date_range->end_date;
            $response_data['days_count'] = $date_range->days_count;
        }

        wp_send_json_success($response_data);

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

/**
 * Obtener datos del listing para la edición
 */
private function get_listing_data_for_edit($listing_id, $date = null) {
    $listing = get_post($listing_id);
    if (!$listing || $listing->post_type !== 'hp_listing') {
        throw new Exception('Listing not found');
    }
    
    // Obtener el precio base estándar
    $base_price = get_post_meta($listing_id, 'hp_price', true);
    
    // Verificar si hay precio específico para el día si se proporciona una fecha
    if (!empty($date)) {
        $booking_ranges = get_post_meta($listing_id, 'hp_booking_ranges', true);
        if (!empty($booking_ranges) && is_array($booking_ranges)) {
            $date_obj = new DateTime($date);
            $day_of_week = intval($date_obj->format('w'));
            
            foreach ($booking_ranges as $range) {
                if (isset($range['days']) && is_array($range['days']) && 
                    in_array($day_of_week, $range['days']) && 
                    isset($range['price'])) {
                    $base_price = floatval($range['price']);
                    break;
                }
            }
        }
    }
    
    $data = array(
        'id' => $listing_id,
        'title' => get_the_title($listing_id),
        'image' => get_the_post_thumbnail_url($listing_id, 'medium'),
        'price' => $base_price, // Ahora este puede ser el precio específico del día
        'min_quantity' => get_post_meta($listing_id, 'hp_booking_min_quantity', true),
        'max_quantity' => get_post_meta($listing_id, 'hp_booking_max_quantity', true),
        'has_quantity' => true
    );
    
    // Obtener extras si existen
    $extras = get_post_meta($listing_id, 'hp_price_extras', true);
    if (is_array($extras)) {
        $data['extras'] = array();
        foreach ($extras as $key => $extra) {
            $data['extras'][] = array(
                'id' => $key,
                'name' => $extra['name'],
                'price' => $extra['price'],
                'price_formatted' => hivepress()->woocommerce->format_price($extra['price']),
                'type' => isset($extra['type']) ? $extra['type'] : ''
            );
        }
    }
    
    return $data;
}
	
	public function generate_quote_pdf() {
    $pdf_handler = new Event_Quote_Cart_PDF_Handler();
    $pdf_handler->generate_quote_pdf();
}

public function send_quote_email() {
    $pdf_handler = new Event_Quote_Cart_PDF_Handler();
    $pdf_handler->send_quote_email();
}

public function generate_whatsapp_link() {
    $pdf_handler = new Event_Quote_Cart_PDF_Handler();
    $pdf_handler->generate_whatsapp_link();
}
	
	/**
 * Validar si la fecha es compatible con los items existentes en el carrito
 */
public function validate_cart_date() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');

    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
    }
	
	

    $new_date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

    if (!$new_date) {
        wp_send_json_error('Missing date');
    }

    try {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Obtener carrito activo
        $cart_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));

        if (!$cart_id) {
            // No hay carrito, no hay conflictos
            wp_send_json_success(array(
                'hasConflicts' => false,
                'message' => 'No items in cart'
            ));
            return;
        }

        // Obtener fechas de los items existentes
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_cart_items 
            WHERE cart_id = %d AND status = 'active'",
            $cart_id
        ));

        $has_conflicts = false;
        $different_dates = array();

        // Convertir la nueva fecha a un formato estándar para comparación
        $new_date_obj = new DateTime($new_date);
        $new_date_formatted = $new_date_obj->format('Y-m-d');
		


        foreach ($items as $item) {
            $form_data = json_decode($item->form_data, true);

            if (isset($form_data['date'])) {
                // Convertir también la fecha del ítem para comparación estándar
                $item_date_obj = new DateTime($form_data['date']);
                $item_date_formatted = $item_date_obj->format('Y-m-d');
                
                if ($item_date_formatted !== $new_date_formatted) {
                    $has_conflicts = true;
                    $different_dates[] = $form_data['date'];
                }
            }
        }

        wp_send_json_success(array(
            'hasConflicts' => $has_conflicts,
            'differentDates' => array_unique($different_dates),
            'message' => $has_conflicts ? 'Date conflicts detected' : 'No conflicts'
        ));

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
	
	/**
 * Validar cambio de fecha para todos los items del carrito
 */
/**
 * Validar cambio de fecha para todos los items del carrito
 */
public function validate_cart_date_change() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');

    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
    }

    $new_date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

    if (!$new_date) {
        wp_send_json_error('Missing date');
    }

    try {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Obtener carrito activo - verificar primero user meta
        $active_cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
        
        if (!$active_cart_id) {
            $active_cart_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eq_carts 
                WHERE user_id = %d AND status = 'active'
                ORDER BY created_at DESC LIMIT 1",
                $user_id
            ));
        }

        if (!$active_cart_id) {
            // No hay carrito, no hay conflictos
            wp_send_json_success(array(
                'hasItems' => false,
                'unavailableItems' => [],
                'allSameDate' => true // Nueva propiedad para indicar si todas las fechas son iguales
            ));
            return;
        }
        
        // Obtener items existentes
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_cart_items 
            WHERE cart_id = %d AND status = 'active'",
            $active_cart_id
        ));

        if (empty($items)) {
            // No hay items en el carrito
            wp_send_json_success(array(
                'hasItems' => false,
                'unavailableItems' => [],
                'allSameDate' => true
            ));
            return;
        }

        $unavailable_items = array();
        $date_handler = new Event_Quote_Cart_Date_Handler();
        $all_same_date = true; // Flag para verificar si todas las fechas son iguales

        // Normalizar la nueva fecha para comparación
        // Normalizar la nueva fecha para comparación - usar formato estricto
$new_date_obj = new DateTime($new_date);
$new_date_normalized = $new_date_obj->format('Y-m-d');

// Verificar disponibilidad y comparar fechas
foreach ($items as $item) {
    // Verificar disponibilidad
    $listing_id = $item->listing_id;
    $is_available = $date_handler->check_listing_availability($listing_id, $new_date);
    
    if (!$is_available) {
        $listing = get_post($listing_id);
        $unavailable_items[] = array(
            'id' => $item->id,
            'listing_id' => $listing_id,
            'title' => get_the_title($listing_id)
        );
    }
    
    // Comparar fecha del item con la nueva fecha
    $form_data = json_decode($item->form_data, true);
    if (isset($form_data['date'])) {
        // Normalizar la fecha del item usando DateTime para estandarización
        try {
            $item_date_obj = new DateTime($form_data['date']);
            $item_date_normalized = $item_date_obj->format('Y-m-d');
            
            // Verificar si son diferentes - comparar las cadenas normalizadas
            if ($item_date_normalized !== $new_date_normalized) {
                $all_same_date = false;
            }
        } catch (Exception $e) {
            $all_same_date = false;
        }
    }
}

       wp_send_json_success(array(
				'hasItems' => true,
				'itemCount' => count($items),
				'unavailableItems' => $unavailable_items,
				'unavailableCount' => count($unavailable_items),
				'allSameDate' => $all_same_date // Nueva propiedad importante
			));

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

		/**
		 * Actualizar fecha de todos los items del carrito
		 */
		public function update_cart_date() {
			check_ajax_referer('eq_cart_public_nonce', 'nonce');

			if (!eq_can_view_quote_button()) {
				wp_send_json_error('Unauthorized');
			}

			$new_date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
			$remove_unavailable = isset($_POST['remove_unavailable']) && $_POST['remove_unavailable'] === 'true';
			$unavailable_ids = isset($_POST['unavailable_ids']) ? $_POST['unavailable_ids'] : [];

			if (!$new_date) {
				wp_send_json_error('Missing date');
			}

			try {
				global $wpdb;
				$user_id = get_current_user_id();

				// Obtener carrito activo
				$cart_id = $wpdb->get_var($wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}eq_carts 
					WHERE user_id = %d AND status = 'active'
					ORDER BY created_at DESC LIMIT 1",
					$user_id
				));

				if (!$cart_id) {
					wp_send_json_error('No active cart found');
					return;
				}

				// Si se deben eliminar items no disponibles
				if ($remove_unavailable && !empty($unavailable_ids)) {
					foreach ($unavailable_ids as $item_id) {
						$wpdb->update(
							$wpdb->prefix . 'eq_cart_items',
							array(
								'status' => 'removed',
								'updated_at' => current_time('mysql')
							),
							array('id' => $item_id),
							array('%s', '%s'),
							array('%d')
						);
					}
				}

				// Actualizar fecha para todos los items restantes
				$items = $wpdb->get_results($wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}eq_cart_items 
					WHERE cart_id = %d AND status = 'active'",
					$cart_id
				));

				foreach ($items as $item) {
					
					// Antes de actualizar, registrar precio actual
					$form_data_before = json_decode($item->form_data, true);    
					
					// Obtener datos actuales
					$form_data = json_decode($item->form_data, true);

					// Si este item está en la lista de no disponibles y no se van a eliminar, omitir
					if ($remove_unavailable === false && in_array($item->id, $unavailable_ids)) {
						continue;
					}

					// Actualizar la fecha y recalcular precio si es necesario
					$form_data['date'] = $new_date;

					// Si hay precio base, recalcular el precio total considerando la nueva fecha
					if (isset($form_data['base_price']) && isset($form_data['quantity'])) {
						$base_price = floatval($form_data['base_price']);
						$quantity = intval($form_data['quantity']);
						$extras = isset($form_data['extras']) ? $form_data['extras'] : [];

						// Obtener precio para la nueva fecha si hay ranges
						$booking_ranges = get_post_meta($item->listing_id, 'hp_booking_ranges', true);
						if (!empty($booking_ranges)) {
							$date_obj = new DateTime($new_date);
							$day_of_week = intval($date_obj->format('w')); // 0 (domingo) a 6 (sábado)

							foreach ($booking_ranges as $range) {
								if (isset($range['days']) && in_array($day_of_week, $range['days']) && isset($range['price'])) {
									$base_price = floatval($range['price']);
									break;
								}
							}
						}

						// Recalcular precio total
						$total_price = $base_price * $quantity;

						// Añadir extras
						if (!empty($extras)) {
							foreach ($extras as $extra) {
								$extra_price = isset($extra['price']) ? floatval($extra['price']) : 0;
								$extra_type = isset($extra['type']) ? $extra['type'] : '';
								$extra_quantity = isset($extra['quantity']) ? intval($extra['quantity']) : 1;

								if ($extra_type === 'per_quantity') {
									$total_price += $extra_price * $quantity;
								} else if ($extra_type === 'variable_quantity') {
									$total_price += $extra_price * $extra_quantity;
								} else if ($extra_type === 'per_order' || $extra_type === 'per_booking' || $extra_type === 'per_item') {
									$total_price += $extra_price;
								} else {
									$total_price += $extra_price * $quantity;
								}
							}
						}

						$form_data['total_price'] = $total_price;
					}

					$wpdb->update(
						$wpdb->prefix . 'eq_cart_items',
						array(
							'form_data' => json_encode($form_data),
							'updated_at' => current_time('mysql')
						),
						array('id' => $item->id),
						array('%s', '%s'),
						array('%d')
					);
					
					// Después de actualizar, registrar nuevo precio
    $form_data_after = json_decode($item->form_data, true);
				}

				// Guardar la nueva fecha maestra en una opción de usuario
				update_user_meta($user_id, 'eq_cart_master_date', $new_date);

				wp_send_json_success(array(
					'message' => 'Cart date updated successfully',
					'removedItems' => $remove_unavailable ? count($unavailable_ids) : 0
				));

			} catch (Exception $e) {
				wp_send_json_error($e->getMessage());
			}
		}

			/**
		 * Obtener la fecha maestra del carrito
		 */
		public function get_cart_master_date() {
			check_ajax_referer('eq_cart_public_nonce', 'nonce');

			$user_id = get_current_user_id();

			if (!$user_id) {
				wp_send_json_success(array('date' => null));
				return;
			}

			try {
				global $wpdb;

				// Obtener carrito activo
				$cart_id = $wpdb->get_var($wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}eq_carts 
					WHERE user_id = %d AND status = 'active'
					ORDER BY created_at DESC LIMIT 1",
					$user_id
				));

				if (!$cart_id) {
					// No hay carrito, no hay fecha maestra
					wp_send_json_success(array('date' => null));
					return;
				}

				// Verificar si hay una fecha maestra guardada
				$master_date = get_user_meta($user_id, 'eq_cart_master_date', true);

				if ($master_date) {
					wp_send_json_success(array('date' => $master_date));
					return;
				}

				// Si no hay fecha maestra guardada, obtener la del primer item
				$first_item = $wpdb->get_row($wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}eq_cart_items 
					WHERE cart_id = %d AND status = 'active'
					ORDER BY id ASC LIMIT 1",
					$cart_id
				));

				if ($first_item) {
					$form_data = json_decode($first_item->form_data, true);
					$first_date = isset($form_data['date']) ? $form_data['date'] : null;

					if ($first_date) {
						// Guardar como fecha maestra
						update_user_meta($user_id, 'eq_cart_master_date', $first_date);
						wp_send_json_success(array('date' => $first_date));
						return;
					}
				}

				wp_send_json_success(array('date' => null));

			} catch (Exception $e) {
				wp_send_json_error($e->getMessage());
			}
		}
	
/**
 * Obtener totales actualizados del carrito
 */
public function get_cart_totals() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');

    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
    }

    try {
        // Obtener items del carrito
        $cart_items = eq_get_cart_items();
        
        // Calcular totales
        $totals = eq_calculate_cart_totals($cart_items);
        
        // Remover HTML escaping y devolver valores sin procesar
        $totals['subtotal'] = wp_strip_all_tags($totals['subtotal']);
        $totals['tax'] = wp_strip_all_tags($totals['tax']);
        $totals['total'] = wp_strip_all_tags($totals['total']);
        
        // Añadir cuenta de items
        $totals['itemCount'] = count($cart_items);
        
        wp_send_json_success($totals);
        
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
	
public function clear_context_meta() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $user_id = get_current_user_id();
    $force_delete = isset($_POST['force_delete']) && $_POST['force_delete'] === 'true';
        
    // Establecer señal de "no restaurar" para evitar recreación automática
    $_SESSION['eq_context_no_restore'] = true;
    
    // Limpiar carrito activo en user meta
    delete_user_meta($user_id, 'eq_active_cart_id');
    
    // Limpiar contexto en sesión PHP
    if (isset($_SESSION['eq_quote_context'])) {
        unset($_SESSION['eq_quote_context']);
    }
    
    // Limpiar otras variables de sesión relacionadas
    $session_vars_to_clean = [
        'eq_current_cart_id',
        'eq_quote_lead_id',
        'eq_quote_event_id'
    ];
    
    foreach ($session_vars_to_clean as $var) {
        if (isset($_SESSION[$var])) {
            unset($_SESSION[$var]);
        }
    }
    
    // Eliminar la sesión de contexto de la BD de forma agresiva
    global $wpdb;
    
    // Primero intentar con ID de usuario
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}eq_context_sessions WHERE user_id = %d",
        $user_id
    ));
        
    // Si se usó force_delete o la primera eliminación no funcionó, intentar con consulta directa
    if ($force_delete || $deleted === false || $deleted === 0) {
        // Método más agresivo: obtener todos los IDs de sesión para este usuario
        $session_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_context_sessions WHERE user_id = %d",
            $user_id
        ));
        
        if (!empty($session_ids)) {
            foreach ($session_ids as $session_id) {
                $force_delete = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}eq_context_sessions WHERE id = %d",
                    $session_id
                ));
                
            }
        }
        
        // Como último recurso, usar consulta sin preparar pero segura
        $table_name = $wpdb->prefix . 'eq_context_sessions';
        $wpdb->query("DELETE FROM {$table_name} WHERE user_id = {$user_id}");
    }
    
    // Verificar que la sesión se eliminó correctamente
    $session_check = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_context_sessions WHERE user_id = %d LIMIT 1",
        $user_id
    ));
    
    if ($session_check) {
        wp_send_json_error([
            'message' => 'No se pudo eliminar la sesión completamente',
            'cleared' => false
        ]);
        return;
    } else {
    }
    
    // Establecer cookie para indicar que la sesión se ha finalizado
    // Esta cookie durará 24 horas y se usará para evitar reconstrucciones automáticas
    setcookie('eq_session_ended', 'true', time() + 86400, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    
    wp_send_json_success(array(
        'message' => 'Context cleared successfully',
        'cleared' => true,
        'timestamp' => time()
    ));
}
	
	public function check_context_status() {
    // Primero, cerrar cualquier output buffer que pueda estar interfiriendo
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    
    // Verificar si session_start ya fue llamado
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    try {
        check_ajax_referer('eq_cart_public_nonce', 'nonce');
    } catch (Exception $e) {
        session_write_close();
        wp_send_json_error('Nonce verification failed');
        return;
    }
    
    if (!function_exists('eq_can_view_quote_button') || !eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    global $wpdb;
    $user_id = get_current_user_id();
    $response = array('isActive' => false);
    
    // Verificar primero si hay cookie de sesión finalizada
    if (isset($_COOKIE['eq_session_ended']) && $_COOKIE['eq_session_ended'] === 'true') {
        wp_send_json_success(array('isActive' => false));
        return;
    }
    
    // Query simplificada: obtener solo sesión primero
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT id, lead_id, event_id, session_token 
        FROM {$wpdb->prefix}eq_context_sessions 
        WHERE user_id = %d 
        ORDER BY id DESC 
        LIMIT 1",
        $user_id
    ));
    
    if ($wpdb->last_error) {
        wp_send_json_error('Database error');
        return;
    }
    

    if (!$session) {
        // Si no hay sesión en BD pero sí en PHP, limpiarla
        if (isset($_SESSION['eq_quote_context'])) {
            unset($_SESSION['eq_quote_context']);
        }
        
        
        // Cerrar sesión antes de enviar respuesta
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        wp_send_json_success(array(
            'isActive' => false,
            'message' => 'No active session found in database',
            'timestamp' => time()
        ));
        return;
    }
    
    
    // Queries rápidas separadas solo si necesitamos los datos
    $lead_name = $wpdb->get_var($wpdb->prepare(
        "SELECT CONCAT(lead_nombre, ' ', lead_apellido) 
        FROM {$wpdb->prefix}jet_cct_leads 
        WHERE _ID = %d",
        $session->lead_id
    ));
    
    if ($wpdb->last_error) {
        wp_send_json_error('Database error in lead query');
        return;
    }
    
    
    $event_data = $wpdb->get_row($wpdb->prepare(
        "SELECT tipo_de_evento, fecha_de_evento 
        FROM {$wpdb->prefix}jet_cct_eventos 
        WHERE _ID = %d",
        $session->event_id
    ));
    
    if ($wpdb->last_error) {
        wp_send_json_error('Database error in event query');
        return;
    }
    
    
    // Verificar que lead y evento existen
    if (!$lead_name || !$event_data) {
        // Eliminar sesión inválida
        $wpdb->delete(
            $wpdb->prefix . 'eq_context_sessions',
            array('id' => $session->id),
            array('%d')
        );
        
        // Limpiar sesión PHP
        if (isset($_SESSION['eq_quote_context'])) {
            unset($_SESSION['eq_quote_context']);
        }
        
        
        // Cerrar sesión antes de enviar respuesta
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        wp_send_json_success(array('isActive' => false));
        return;
    }
    
    
    // Sesión válida encontrada, devolver datos completos
    $response = array(
        'isActive' => true,
        'leadId' => $session->lead_id,
        'leadName' => $lead_name,
        'eventId' => $session->event_id,
        'eventType' => $event_data->tipo_de_evento,
        'eventDate' => is_numeric($event_data->fecha_de_evento) ? 
            date('Y-m-d', $event_data->fecha_de_evento) : $event_data->fecha_de_evento,
        'sessionToken' => $session->session_token
    );
    
    
    // Actualizar sesión PHP para mantener sincronización
    $_SESSION['eq_quote_context'] = array(
        'lead_id' => $session->lead_id,
        'event_id' => $session->event_id,
        'user_id' => $user_id,
        'session_token' => $session->session_token,
        'lead_name' => $lead_name,
        'event_type' => $event_data->tipo_de_evento,
        'event_date' => $event_data->fecha_de_evento,
        'last_update' => time()
    );
    
    
    // IMPORTANTE: Cerrar la sesión PHP para liberar el lock antes de enviar la respuesta
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    wp_send_json_success($response);
}
	
	/**
 * Verificar si la sesión realmente se eliminó
 */
public function verify_context_cleared() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $user_id = get_current_user_id();
    
    // Verificar en la base de datos
    global $wpdb;
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_context_sessions 
        WHERE user_id = %d
        LIMIT 1",
        $user_id
    ));
    
    // Si existe sesión en BD, intentar eliminarla nuevamente
    if ($session) {
        
        // Intentar eliminar directamente
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}eq_context_sessions WHERE id = %d",
            $session->id
        ));
        
        
        // Verificar nuevamente
        $still_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}eq_context_sessions WHERE id = %d",
            $session->id
        ));
        
        if ($still_exists) {
            wp_send_json_success(array('isActive' => true, 'forceReload' => true));
            return;
        }
    }
    
    // Verificar en sesión PHP
    if (isset($_SESSION['eq_quote_context'])) {
        unset($_SESSION['eq_quote_context']);
    }
    
    // Asegurarse de que la señal de no restaurar esté establecida
    $_SESSION['eq_context_no_restore'] = true;
    
    wp_send_json_success(array('isActive' => false));
}

	public function create_context_session() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!function_exists('eq_can_view_quote_button') || !eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    // Verificación adicional - comprobar roles directamente
    $user = wp_get_current_user();
    if (!$user || !in_array('administrator', $user->roles) && !in_array('ejecutivo_de_ventas', $user->roles)) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    
    if (!$lead_id || !$event_id) {
        wp_send_json_error('Faltan IDs de lead y/o evento');
        return;
    }
    
    // Generar token único para esta sesión
    $session_token = wp_generate_password(32, false);
    
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Eliminar cualquier cookie de sesión finalizada
    if (isset($_COOKIE['eq_session_ended'])) {
        setcookie('eq_session_ended', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
    
    // Asegurarnos de que no haya señal de "no restaurar" en la sesión
    if (isset($_SESSION['eq_context_no_restore'])) {
        unset($_SESSION['eq_context_no_restore']);
    }
    
    // IMPORTANTE: Eliminar cualquier sesión existente primero para evitar conflictos
    $deleted = $wpdb->delete(
        $wpdb->prefix . 'eq_context_sessions',
        array('user_id' => $user_id),
        array('%d')
    );
    
    // Crear la nueva sesión
    $insert_result = $wpdb->insert(
        $wpdb->prefix . 'eq_context_sessions',
        array(
            'user_id' => $user_id,
            'session_token' => $session_token,
            'lead_id' => $lead_id,
            'event_id' => $event_id,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ),
        array('%d', '%s', '%d', '%d', '%s', '%s')
    );
    
    if ($insert_result === false) {
        wp_send_json_error('Error al crear sesión de contexto en la base de datos');
        return;
    }
    
    $new_session_id = $wpdb->insert_id;
    
    // Actualizar context en sesión PHP
    $_SESSION['eq_quote_context'] = array(
        'lead_id' => $lead_id,
        'event_id' => $event_id,
        'user_id' => $user_id,
        'session_token' => $session_token
    );
    
    wp_send_json_success(array(
        'message' => 'Sesión de contexto actualizada',
        'session_token' => $session_token,
        'session_id' => $new_session_id
    ));
}

/**
 * Verificar el estado actual de la sesión de contexto
 */
public function verify_context_session() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    // Verificación adicional - comprobar roles directamente
    $user = wp_get_current_user();
    if (!$user || !in_array('administrator', $user->roles) && !in_array('ejecutivo_de_ventas', $user->roles)) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $session_token = isset($_POST['session_token']) ? sanitize_text_field($_POST['session_token']) : '';
    
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Buscar sesión activa para este usuario y token
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_context_sessions 
        WHERE user_id = %d AND session_token = %s AND status = 'active'
        ORDER BY updated_at DESC LIMIT 1",
        $user_id, $session_token
    ));
    
    if ($session) {
        // Obtener información del lead
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT lead_nombre, lead_apellido FROM {$wpdb->prefix}jet_cct_leads WHERE _ID = %d",
            $session->lead_id
        ));
        
        // Obtener información del evento
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT tipo_de_evento, fecha_de_evento FROM {$wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
            $session->event_id
        ));
        
        wp_send_json_success(array(
            'isActive' => true,
            'leadId' => $session->lead_id,
            'leadName' => $lead ? $lead->lead_nombre . ' ' . $lead->lead_apellido : '',
            'eventId' => $session->event_id,
            'eventType' => $event ? $event->tipo_de_evento : '',
            'eventDate' => $event ? (is_numeric($event->fecha_de_evento) ? 
                date('Y-m-d', $event->fecha_de_evento) : $event->fecha_de_evento) : '',
            'sessionToken' => $session->session_token,
            'lastUpdated' => $session->updated_at
        ));
    } else {
        wp_send_json_success(array(
            'isActive' => false
        ));
    }
}
	
public function update_cart_context() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    
    if (!$lead_id || !$event_id) {
        wp_send_json_error('Faltan IDs de lead y/o evento');
        return;
    }
    
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Limpiar cualquier cookie o señal de sesión finalizada
    if (isset($_COOKIE['eq_session_ended'])) {
        setcookie('eq_session_ended', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
    
    if (isset($_SESSION['eq_context_no_restore'])) {
        unset($_SESSION['eq_context_no_restore']);
    }
    
    // Verificar que el usuario tenga acceso a este lead/evento
    $user = wp_get_current_user();
    $is_admin = in_array('administrator', $user->roles);
    $is_sales = in_array('ejecutivo_de_ventas', $user->roles);
    $has_access = false;
    
    // Los administradores pueden acceder a cualquier lead/evento
    if ($is_admin) {
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT _ID FROM {$wpdb->prefix}jet_cct_leads WHERE _ID = %d",
            $lead_id
        ));
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT _ID FROM {$wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
            $event_id
        ));
        
        $has_access = ($lead && $event);
    } 
    // Los ejecutivos de ventas solo pueden acceder a leads/eventos asignados a ellos
    else if ($is_sales) {
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT _ID FROM {$wpdb->prefix}jet_cct_leads 
            WHERE _ID = %d AND usuario_asignado = %d",
            $lead_id, $user_id
        ));
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT _ID FROM {$wpdb->prefix}jet_cct_eventos 
            WHERE _ID = %d AND lead_id = %d",
            $event_id, $lead_id
        ));
        
        $has_access = ($lead && $event);
    }
    // Usuarios normales solo pueden acceder a sus propios leads/eventos
    else {
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT _ID FROM {$wpdb->prefix}jet_cct_leads 
            WHERE _ID = %d AND lead_e_mail = %s",
            $lead_id, $user->user_email
        ));
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT _ID FROM {$wpdb->prefix}jet_cct_eventos 
            WHERE _ID = %d AND lead_id = %d",
            $event_id, $lead_id
        ));
        
        $has_access = ($lead && $event);
    }
    
    if (!$has_access) {
        wp_send_json_error('No tienes permiso para acceder a este lead o evento');
        return;
    }
    
    // Actualizar contexto en sesión
    $_SESSION['eq_quote_context'] = array(
        'lead_id' => $lead_id,
        'event_id' => $event_id,
        'user_id' => $user_id, // Importante: incluir el user_id en la sesión
        'timestamp' => time()
    );
    
    // Buscar si ya existe un carrito para este par lead-evento
    $existing_cart = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}eq_carts 
        WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
        ORDER BY created_at DESC LIMIT 1",
        $user_id, $lead_id, $event_id
    ));
    
    if ($existing_cart) {
        // Si existe, establecerlo como carrito activo
        update_user_meta($user_id, 'eq_active_cart_id', $existing_cart);
        
        wp_send_json_success(array(
            'message' => 'Contexto actualizado a carrito existente',
            'cart_id' => $existing_cart
        ));
        return;
    }
    
    // Si no existe, crear uno nuevo
    $new_cart = array(
        'user_id' => $user_id,
        'lead_id' => $lead_id,
        'event_id' => $event_id,
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    );
    
    $wpdb->insert(
        $wpdb->prefix . 'eq_carts',
        $new_cart,
        array('%d', '%d', '%d', '%s', '%s', '%s')
    );
    
    $new_cart_id = $wpdb->insert_id;
    
    // Establecer como carrito activo
    update_user_meta($user_id, 'eq_active_cart_id', $new_cart_id);
    
    wp_send_json_success(array(
        'message' => 'Creado nuevo carrito para contexto',
        'cart_id' => $new_cart_id
    ));
}    
	
	public function check_item_in_cart() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
    
    if (!$listing_id) {
        wp_send_json_error('Invalid listing ID');
        return;
    }
    
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Obtener carrito activo
$cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
    
    if (!$cart_id) {
        wp_send_json_success(array('in_cart' => false));
        return;
    }
    
    // Verificar si el listing está en el carrito
    $item = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}eq_cart_items 
        WHERE cart_id = %d AND listing_id = %d AND status = 'active'
        LIMIT 1",
        $cart_id, $listing_id
    ));
    
    wp_send_json_success(array('in_cart' => !empty($item)));
}
	
	/**
 * Actualizar la fecha de un evento existente
 */
public function update_event_date() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $new_date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    
    if (!$new_date) {
        wp_send_json_error('Missing date');
        return;
    }
    
    try {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Obtener la sesión de contexto activa
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_context_sessions 
            WHERE user_id = %d
            LIMIT 1",
            $user_id
        ));
        
        if (!$session || !$session->event_id) {
            wp_send_json_error('No active context session found');
            return;
        }
        
        // Convertir fecha a timestamp si es necesario
        
        // Test múltiples métodos de conversión
        
        // Test específico para 2025-09-26
        if ($new_date === '2025-09-26') {
            try {
                $test_dt = new DateTime('2025-09-26');
            } catch (Exception $e) {
            }
        }
        
        // Intentar usar DateTime para conversión más robusta
        try {
            // Intentar crear DateTime con zona horaria específica
            $timezone = new DateTimeZone(get_option('timezone_string') ?: 'America/Mexico_City');
            $dateObj = new DateTime($new_date . ' 00:00:00', $timezone);
            $date_timestamp = $dateObj->getTimestamp();
        } catch (Exception $e) {
            // Fallback a strtotime
            $date_timestamp = strtotime($new_date . ' 00:00:00');
        }
        
        if ($date_timestamp === false || $date_timestamp === 0) {
            wp_send_json_error('Fecha inválida: ' . $new_date);
            return;
        }
        
        // Actualizar fecha del evento
        $updated = $wpdb->update(
            $wpdb->prefix . 'jet_cct_eventos',
            array(
                'fecha_de_evento' => $date_timestamp,
                'cct_modified' => current_time('mysql')
            ),
            array('_ID' => $session->event_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($updated === false) {
            throw new Exception($wpdb->last_error);
        }
        
        // Actualizar también todos los ítems del carrito
        $cart_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id, $session->lead_id, $session->event_id
        ));
        
        if ($cart_id) {
            // Actualizar fecha de todos los ítems
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eq_cart_items 
                WHERE cart_id = %d AND status = 'active'",
                $cart_id
            ));
            
            foreach ($items as $item) {
                $form_data = json_decode($item->form_data, true);
                $form_data['date'] = $new_date;
                
                $wpdb->update(
                    $wpdb->prefix . 'eq_cart_items',
                    array(
                        'form_data' => json_encode($form_data),
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $item->id),
                    array('%s', '%s'),
                    array('%d')
                );
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Event date updated successfully',
            'event_id' => $session->event_id,
            'new_date' => $new_date
        ));
        
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

/**
 * Duplicar un evento con nueva fecha
 */
public function duplicate_event() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $new_date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $transfer_items = isset($_POST['transfer_items']) && $_POST['transfer_items'] === 'yes';
    
    if (!$new_date) {
        wp_send_json_error('Missing date');
        return;
    }
    
    try {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Obtener la sesión de contexto activa
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_context_sessions 
            WHERE user_id = %d
            LIMIT 1",
            $user_id
        ));
        
        if (!$session || !$session->event_id || !$session->lead_id) {
            wp_send_json_error('No active context session found');
            return;
        }
        
        // Obtener datos del evento original
        $original_event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jet_cct_eventos 
            WHERE _ID = %d",
            $session->event_id
        ));
        
        if (!$original_event) {
            wp_send_json_error('Original event not found');
            return;
        }
        
        // Convertir fecha a timestamp si es necesario
        $date_timestamp = strtotime($new_date);
        
        // Crear nuevo evento copiando datos del original
        $new_event = array(
            'lead_id' => $session->lead_id,
            'tipo_de_evento' => $original_event->tipo_de_evento,
            'fecha_de_evento' => $date_timestamp,
            'evento_asistentes' => $original_event->evento_asistentes,
            'evento_status' => $original_event->evento_status,
            'evento_servicio_de_interes' => $original_event->evento_servicio_de_interes,
            'cct_status' => 'publish',
            'cct_created' => current_time('mysql'),
            'cct_modified' => current_time('mysql')
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'jet_cct_eventos',
            $new_event,
            array('%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }
        
        $new_event_id = $wpdb->insert_id;
        
        // Crear nuevo carrito para este evento
        $new_cart = array(
            'user_id' => $user_id,
            'lead_id' => $session->lead_id,
            'event_id' => $new_event_id,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'eq_carts',
            $new_cart,
            array('%d', '%d', '%d', '%s', '%s', '%s')
        );
        
        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }
        
        $new_cart_id = $wpdb->insert_id;
        
        // Transferir ítems si se solicitó
        if ($transfer_items) {
            $original_cart_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eq_carts 
                WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
                ORDER BY created_at DESC LIMIT 1",
                $user_id, $session->lead_id, $session->event_id
            ));
            
            if ($original_cart_id) {
                $items = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}eq_cart_items 
                    WHERE cart_id = %d AND status = 'active'",
                    $original_cart_id
                ));
                
                foreach ($items as $item) {
                    $form_data = json_decode($item->form_data, true);
                    $form_data['date'] = $new_date;
                    
                    $new_item = array(
                        'cart_id' => $new_cart_id,
                        'listing_id' => $item->listing_id,
                        'status' => 'active',
                        'form_data' => json_encode($form_data),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                        'order_in_cart' => $item->order_in_cart
                    );
                    
                    $wpdb->insert(
                        $wpdb->prefix . 'eq_cart_items',
                        $new_item,
                        array('%d', '%d', '%s', '%s', '%s', '%s', '%d')
                    );
                }
            }
        }
        
        // Actualizar sesión con el nuevo evento
        $session_token = wp_generate_password(32, false);
        
        $wpdb->update(
            $wpdb->prefix . 'eq_context_sessions',
            array(
                'event_id' => $new_event_id,
                'session_token' => $session_token,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $session->id),
            array('%d', '%s', '%s'),
            array('%d')
        );
        
        // Actualizar metadatos del usuario
        update_user_meta($user_id, 'eq_active_cart_id', $new_cart_id);
        
        // Actualizar sesión PHP
        $_SESSION['eq_quote_context'] = array(
            'lead_id' => $session->lead_id,
            'event_id' => $new_event_id,
            'user_id' => $user_id,
            'session_token' => $session_token,
            'timestamp' => time()
        );
        
        wp_send_json_success(array(
            'message' => 'Event duplicated successfully',
            'event_id' => $new_event_id,
            'cart_id' => $new_cart_id,
            'new_date' => $new_date
        ));
        
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
	
	/**
 * Verificar si existe un evento para una fecha específica
 */
public function check_event_exists() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    
    if (!$lead_id || !$date) {
        wp_send_json_error('Missing required parameters');
        return;
    }
    
    try {
        global $wpdb;
        
        // Convertir fecha a timestamp
        $date_timestamp = strtotime($date);
        
        // Buscar evento existente para esta fecha y lead
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jet_cct_eventos 
            WHERE lead_id = %d 
            AND fecha_de_evento = %d 
            AND cct_status = 'publish'",
            $lead_id, $date_timestamp
        ));
        
        if ($event) {
            wp_send_json_success(array(
                'exists' => true,
                'event_id' => $event->_ID,
                'event_type' => $event->tipo_de_evento
            ));
        } else {
            wp_send_json_success(array(
                'exists' => false
            ));
        }
        
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
	
public function get_lead_email() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
    }
    
    // Obtener el contexto activo usando la función centralizada
    $context = eq_get_active_context();
    $lead_email = '';
    
    // Si hay un contexto con lead, obtener su email
    if ($context && isset($context['lead']) && $context['lead']) {
        $lead = $context['lead'];
        // El nombre del campo de email puede variar según tu base de datos
        $lead_email = !empty($lead->lead_e_mail) ? $lead->lead_e_mail : '';
    }
    
    // Si no se encontró email en el contexto, intentar el método antiguo como respaldo
    if (empty($lead_email)) {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Obtener carrito activo usando la función centralizada
        $cart = eq_get_active_cart();
        
        if ($cart && !empty($cart->lead_id)) {
            // Obtener email del lead
            $lead_email = $wpdb->get_var($wpdb->prepare(
                "SELECT lead_e_mail FROM {$wpdb->prefix}jet_cct_leads 
                WHERE _ID = %d",
                $cart->lead_id
            ));
            
        }
    }
    
    wp_send_json_success(array(
        'email' => $lead_email ?: ''
    ));
}
	
	/**
 * Validar todos los items del carrito para la disponibilidad actual
 */
public function validate_all_cart_items() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    try {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Obtener carrito activo
        $cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
        
        if (!$cart_id) {
            wp_send_json_success(array('items' => array()));
            return;
        }
        
        // Obtener items del carrito
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_cart_items 
            WHERE cart_id = %d AND status = 'active'
            ORDER BY created_at DESC",
            $cart_id
        ));
        
        $unavailable_items = array();
        
        foreach ($items as $item) {
            $form_data = json_decode($item->form_data, true);
            $date = isset($form_data['date']) ? $form_data['date'] : '';
            
            if (!$date) {
                // Si no hay fecha, marcar como no disponible
                $unavailable_items[] = array(
                    'id' => $item->id,
                    'listing_id' => $item->listing_id,
                    'title' => get_the_title($item->listing_id),
                    'reason' => 'missing_date'
                );
                continue;
            }
            
            // Verificar disponibilidad con date_handler
            $is_available = $this->date_handler->check_listing_availability($item->listing_id, $date);
            
            if (!$is_available) {
                $unavailable_items[] = array(
                    'id' => $item->id,
                    'listing_id' => $item->listing_id,
                    'title' => get_the_title($item->listing_id),
                    'reason' => 'unavailable'
                );
            }
        }
        
        wp_send_json_success(array(
            'unavailable_items' => $unavailable_items,
            'has_unavailable' => count($unavailable_items) > 0
        ));
        
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

    /**
     * Limpiar contexto al hacer logout
     */
    public function clear_context_on_logout() {
        $user_id = get_current_user_id();
        if (!$user_id) return;
        
        global $wpdb;
        
        // Limpiar sesión PHP
        if (isset($_SESSION['eq_quote_context'])) {
            unset($_SESSION['eq_quote_context']);
        }
        
        // Limpiar contexto de sesiones en BD
        $wpdb->delete(
            $wpdb->prefix . 'eq_context_sessions',
            array('user_id' => $user_id),
            array('%d')
        );
        
        // Limpiar meta de usuario
        delete_user_meta($user_id, 'eq_quote_context');
        delete_user_meta($user_id, 'eq_context_session_token');
        
        // Establecer cookies para que el frontend sepa que debe limpiar
        setcookie('eq_session_ended', 'true', time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
        setcookie('eq_context_force_clear', 'true', time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
    }

    /**
     * Get email template and lead email - integrates with Vendor Dashboard PRO plugin
     */
    public function get_email_template() {
        check_ajax_referer('eq_cart_public_nonce', 'nonce');
        
        if (!eq_can_view_quote_button()) {
            wp_send_json_error('Unauthorized');
        }
        
        try {
            // Get lead email
            $context = eq_get_active_context();
            $lead_email = '';
            if ($context && isset($context['lead'])) {
                $lead = $context['lead'];
                if (!empty($lead->lead_e_mail)) {
                    $lead_email = sanitize_email($lead->lead_e_mail);
                }
            }
            
            // Get vendor email template
            $email_template = '';
            if (function_exists('vdp_get_current_vendor')) {
                $vendor = vdp_get_current_vendor();
                if ($vendor) {
                    $email_template = get_post_meta($vendor->get_id(), 'email_message_template', true);
                }
            }
            
            // If no template, provide default
            if (empty($email_template)) {
                $user = wp_get_current_user();
                $email_template = "Hola {customer_name},\n\nTe comparto la cotización #{quote_number} que solicitaste.\n\nSaludos,\n{vendor_name}";
            }
            
            // Replace placeholders with actual values
            $user = wp_get_current_user();
            $quote_number = 'COT-' . date('Ymd') . '-' . get_current_user_id();
            $lead_name = '';
            if ($context && isset($context['lead'])) {
                $lead_name = $context['lead']->lead_nombre ?: 'cliente';
            }
            
            // Get product name for template
            $cart_items = eq_get_cart_items();
            $product_name = "";
            if (count($cart_items) == 1) {
                $product_name = $cart_items[0]->title;
            } else {
                $product_name = "los productos seleccionados";
            }
            
            $message = str_replace(
                ['{customer_name}', '{quote_number}', '{vendor_name}', '{product_name}'],
                [$lead_name ?: 'cliente', $quote_number, $user->display_name, $product_name],
                $email_template
            );
            
            wp_send_json_success(array(
                'email' => $lead_email,
                'template' => $message
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get WhatsApp template and lead phone - integrates with Vendor Dashboard PRO plugin
     */
    public function get_whatsapp_template() {
        check_ajax_referer('eq_cart_public_nonce', 'nonce');
        
        if (!eq_can_view_quote_button()) {
            wp_send_json_error('Unauthorized');
        }
        
        try {
            // Get lead phone
            $context = eq_get_active_context();
            $lead_phone = '';
            $lead_name = 'cliente';
            
            if ($context && isset($context['lead'])) {
                $lead = $context['lead'];
                if (!empty($lead->lead_celular)) {
                    $lead_phone = preg_replace('/[^0-9]/', '', $lead->lead_celular);
                    if (substr($lead_phone, 0, 1) === '+') {
                        $lead_phone = substr($lead_phone, 1);
                    }
                }
                $lead_name = $lead->lead_nombre ?: 'cliente';
            }
            
            // Get vendor WhatsApp template
            $whatsapp_template = '';
            if (function_exists('vdp_get_current_vendor')) {
                $vendor = vdp_get_current_vendor();
                if ($vendor) {
                    $whatsapp_template = get_post_meta($vendor->get_id(), 'whatsapp_message_template', true);
                }
            }
            
            // If no template, provide default
            if (empty($whatsapp_template)) {
                $user = wp_get_current_user();
                $whatsapp_template = "¡Hola! Qué tal {customer_name}, soy {vendor_name}. Te comparto la cotización #{quote_number}. ¿Tienes alguna pregunta?";
            }
            
            // Replace placeholders with actual values
            $user = wp_get_current_user();
            $quote_number = 'COT-' . date('Ymd') . '-' . get_current_user_id();
            
            $message = str_replace(
                ['{customer_name}', '{quote_number}', '{vendor_name}'],
                [$lead_name, $quote_number, $user->display_name],
                $whatsapp_template
            );
            
            wp_send_json_success(array(
                'lead_phone' => $lead_phone,
                'message' => $message
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Guardar un snapshot del carrito en el historial
     */
    public function save_cart_history() {
        check_ajax_referer('eq_cart_public_nonce', 'nonce');
        
        if (!current_user_can('administrator') && !current_user_can('ejecutivo_de_ventas')) {
            wp_send_json_error('Unauthorized');
        }
        
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'manual_save';
        
        try {
            global $wpdb;
            $user_id = get_current_user_id();
            
            // Obtener el carrito activo
            $cart = eq_get_active_cart();
            if (!$cart) {
                wp_send_json_error('No active cart found');
            }
            
            // Obtener items del carrito
            $cart_items = eq_get_cart_items();
            if (empty($cart_items)) {
                wp_send_json_error('Cart is empty');
            }
            
            // Calcular totales
            $totals = eq_calculate_cart_totals($cart_items);
            $total_amount = isset($totals['total_raw']) ? $totals['total_raw'] : 0;
            
            // Obtener el siguiente número de versión
            $version = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(MAX(version), 0) + 1 FROM {$wpdb->prefix}eq_cart_history 
                WHERE cart_id = %d",
                $cart->id
            ));
            
            // Preparar snapshot de items
            $items_snapshot = json_encode($cart_items);
            
            // Insertar en historial
            $result = $wpdb->insert(
                $wpdb->prefix . 'eq_cart_history',
                array(
                    'cart_id' => $cart->id,
                    'lead_id' => $cart->lead_id,
                    'event_id' => $cart->event_id,
                    'user_id' => $user_id,
                    'version' => $version,
                    'items_snapshot' => $items_snapshot,
                    'total_amount' => $total_amount,
                    'action' => $action
                ),
                array('%d', '%d', '%d', '%d', '%d', '%s', '%f', '%s')
            );
            
            if ($result === false) {
                wp_send_json_error('Failed to save cart history');
            }
            
            wp_send_json_success(array(
                'message' => 'Cart history saved successfully',
                'version' => $version
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Obtener el historial del carrito
     */
    public function get_cart_history() {
        check_ajax_referer('eq_cart_public_nonce', 'nonce');
        
        if (!current_user_can('administrator') && !current_user_can('ejecutivo_de_ventas')) {
            error_log('Cart History Debug: Unauthorized user');
            wp_send_json_error('Unauthorized');
        }
        
        try {
            global $wpdb;
            $user_id = get_current_user_id();
            error_log('Cart History Debug: User ID = ' . $user_id);
            
            // Obtener el carrito activo
            $cart = eq_get_active_cart();
            if (!$cart) {
                error_log('Cart History Debug: No active cart found for user ' . $user_id);
                wp_send_json_error('No active cart found');
            }
            
            error_log('Cart History Debug: Active cart ID = ' . $cart->id);
            
            // Obtener historial del carrito
            $history = $wpdb->get_results($wpdb->prepare(
                "SELECT id, version, total_amount, action, created_at, items_snapshot 
                FROM {$wpdb->prefix}eq_cart_history 
                WHERE cart_id = %d 
                ORDER BY created_at DESC",
                $cart->id
            ));
            
            error_log('Cart History Debug: Found ' . count($history) . ' history entries for cart ' . $cart->id);
            error_log('Cart History Debug: History query: ' . $wpdb->last_query);
            
            if (empty($history)) {
                error_log('Cart History Debug: No history entries found, returning empty');
                wp_send_json_success(array(
                    'history' => array(),
                    'cart_id' => $cart->id,
                    'message' => 'No history found for cart ' . $cart->id
                ));
                return;
            }
            
            $formatted_history = array();
            foreach ($history as $entry) {
                error_log('Cart History Debug: Processing entry ID ' . $entry->id . ', version ' . $entry->version);
                
                // Decodificar items_snapshot (como objetos para consistencia)
                $items_data = json_decode($entry->items_snapshot);
                $items_summary = array();
                $total_items = 0;
                
                if ($items_data && is_array($items_data)) {
                    foreach ($items_data as $item) {
                        $total_items += isset($item->quantity) ? intval($item->quantity) : 1;
                        $items_summary[] = array(
                            'title' => isset($item->title) ? $item->title : 'Unknown Item',
                            'quantity' => isset($item->quantity) ? intval($item->quantity) : 1,
                            'price_formatted' => isset($item->price_formatted) ? $item->price_formatted : '$0.00',
                            'date' => isset($item->date) ? $item->date : '',
                            'image' => isset($item->image) ? $item->image : '',
                            'extras' => isset($item->extras) ? $item->extras : array()
                        );
                    }
                }
                
                $formatted_history[] = array(
                    'id' => $entry->id,
                    'version' => $entry->version,
                    'total_amount' => $entry->total_amount,
                    'total_formatted' => hivepress()->woocommerce->format_price($entry->total_amount),
                    'action' => $entry->action,
                    'created_at' => $entry->created_at,
                    'created_formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->created_at)),
                    'items_summary' => $items_summary,
                    'total_items' => $total_items
                );
            }
            
            error_log('Cart History Debug: Sending success response with ' . count($formatted_history) . ' entries');
            
            wp_send_json_success(array(
                'history' => $formatted_history,
                'cart_id' => $cart->id
            ));
            
        } catch (Exception $e) {
            error_log('Cart History Debug: Exception = ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Restaurar el carrito desde una versión del historial
     */
    public function restore_cart_history() {
        check_ajax_referer('eq_cart_public_nonce', 'nonce');
        
        if (!current_user_can('administrator') && !current_user_can('ejecutivo_de_ventas')) {
            wp_send_json_error('Unauthorized');
        }
        
        $history_id = isset($_POST['history_id']) ? intval($_POST['history_id']) : 0;
        
        if (!$history_id) {
            wp_send_json_error('Invalid history ID');
        }
        
        try {
            global $wpdb;
            $user_id = get_current_user_id();
            
            // Obtener la versión del historial
            $history_entry = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eq_cart_history 
                WHERE id = %d AND user_id = %d",
                $history_id, $user_id
            ));
            
            if (!$history_entry) {
                wp_send_json_error('History entry not found');
            }
            
            // Verificar que el carrito existe
            $cart = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eq_carts 
                WHERE id = %d AND user_id = %d",
                $history_entry->cart_id, $user_id
            ));
            
            if (!$cart) {
                wp_send_json_error('Cart not found');
            }
            
            // Obtener el snapshot de items (sin true para mantener como objetos)
            $items_snapshot = json_decode($history_entry->items_snapshot);
            if (!$items_snapshot) {
                wp_send_json_error('Invalid items snapshot');
            }
            
            // Guardar snapshot actual antes de restaurar
            $this->save_cart_history_internal($cart->id, 'before_restore');
            
            // Eliminar items actuales del carrito
            $wpdb->delete(
                $wpdb->prefix . 'eq_cart_items',
                array('cart_id' => $cart->id),
                array('%d')
            );
            
            // También eliminar rangos de fechas asociados
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}eq_cart_date_ranges 
                WHERE cart_item_id IN (
                    SELECT id FROM {$wpdb->prefix}eq_cart_items 
                    WHERE cart_id = %d
                )",
                $cart->id
            ));
            
            // Restaurar items desde el snapshot
            foreach ($items_snapshot as $item) {
                // Insertar item
                $result = $wpdb->insert(
                    $wpdb->prefix . 'eq_cart_items',
                    array(
                        'cart_id' => $cart->id,
                        'listing_id' => $item->listing_id,
                        'form_data' => $item->form_data,
                        'status' => 'active'
                    ),
                    array('%d', '%d', '%s', '%s')
                );
                
                if ($result) {
                    $new_item_id = $wpdb->insert_id;
                    
                    // Si el item tenía rango de fechas, restaurarlo
                    if (isset($item->is_date_range) && $item->is_date_range) {
                        $wpdb->insert(
                            $wpdb->prefix . 'eq_cart_date_ranges',
                            array(
                                'cart_item_id' => $new_item_id,
                                'start_date' => $item->start_date,
                                'end_date' => $item->end_date,
                                'days_count' => $item->days_count,
                                'extras_info' => isset($item->extras_info) ? json_encode($item->extras_info) : null
                            ),
                            array('%d', '%s', '%s', '%d', '%s')
                        );
                    }
                }
            }
            
            // Guardar snapshot después de restaurar
            $this->save_cart_history_internal($cart->id, 'after_restore');
            
            wp_send_json_success(array(
                'message' => 'Cart restored successfully',
                'version' => $history_entry->version
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Método interno para guardar historial sin verificar AJAX nonce
     */
    private function save_cart_history_internal($cart_id, $action = 'automatic') {
        global $wpdb;
        $user_id = get_current_user_id();
        
        try {
            // Obtener items del carrito
            $cart_items = eq_get_cart_items();
            if (empty($cart_items)) {
                return false;
            }
            
            // Calcular totales
            $totals = eq_calculate_cart_totals($cart_items);
            $total_amount = isset($totals['total_raw']) ? $totals['total_raw'] : 0;
            
            // Obtener el siguiente número de versión
            $version = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(MAX(version), 0) + 1 FROM {$wpdb->prefix}eq_cart_history 
                WHERE cart_id = %d",
                $cart_id
            ));
            
            // Preparar snapshot de items
            $items_snapshot = json_encode($cart_items);
            
            // Obtener información del carrito
            $cart = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eq_carts WHERE id = %d",
                $cart_id
            ));
            
            // Insertar en historial
            $result = $wpdb->insert(
                $wpdb->prefix . 'eq_cart_history',
                array(
                    'cart_id' => $cart_id,
                    'lead_id' => $cart ? $cart->lead_id : null,
                    'event_id' => $cart ? $cart->event_id : null,
                    'user_id' => $user_id,
                    'version' => $version,
                    'items_snapshot' => $items_snapshot,
                    'total_amount' => $total_amount,
                    'action' => $action
                ),
                array('%d', '%d', '%d', '%d', '%d', '%s', '%f', '%s')
            );
            
            return $result !== false;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Save cart history snapshot (internal method)
     */
    private function save_cart_history_snapshot($action = 'automatic') {
        // Only proceed if user has proper permissions
        if (!current_user_can('administrator') && !current_user_can('ejecutivo_de_ventas')) {
            return;
        }

        try {
            global $wpdb;
            $user_id = get_current_user_id();
            
            // Get active cart
            $cart = eq_get_active_cart();
            if (!$cart) {
                return;
            }
            
            // Get cart items
            $cart_items = eq_get_cart_items();
            if (empty($cart_items)) {
                return;
            }
            
            // Calculate totals
            $totals = eq_calculate_cart_totals($cart_items);
            $total_amount = isset($totals['total_raw']) ? $totals['total_raw'] : 0;
            
            // Get next version number
            $version = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(MAX(version), 0) + 1 FROM {$wpdb->prefix}eq_cart_history 
                WHERE cart_id = %d",
                $cart->id
            ));
            
            // Prepare items snapshot
            $items_snapshot = json_encode($cart_items);
            
            // Insert into history
            $wpdb->insert(
                $wpdb->prefix . 'eq_cart_history',
                array(
                    'cart_id' => $cart->id,
                    'lead_id' => $cart->lead_id,
                    'event_id' => $cart->event_id,
                    'user_id' => $user_id,
                    'version' => $version,
                    'items_snapshot' => $items_snapshot,
                    'total_amount' => $total_amount,
                    'action' => $action
                ),
                array('%d', '%d', '%d', '%d', '%d', '%s', '%f', '%s')
            );
            
        } catch (Exception $e) {
            // Silent fail - history is not critical
            error_log('EQ Cart History Error: ' . $e->getMessage());
        }
    }
	
}