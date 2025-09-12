<?php
/**
 * Cart AJAX Handler Class
 * 
 * Maneja todas las operaciones AJAX relacionadas con el carrito
 * 
 * @package Event_Quote_Cart
 * @since 2.0.0
 */

if (!defined('WPINC')) {
    die;
}

class Event_Quote_Cart_Cart_Ajax_Handler {
    
    private $date_handler;
    
    public function __construct() {
        $this->date_handler = new Event_Quote_Cart_Date_Handler();
        $this->init_hooks();
    }
    
    public function init_hooks() {
        add_action('wp_ajax_eq_add_to_cart', array($this, 'add_to_cart'));
        add_action('wp_ajax_eq_update_cart_item', array($this, 'update_cart_item'));
        add_action('wp_ajax_eq_get_cart_items', array($this, 'get_cart_items'));
        add_action('wp_ajax_eq_get_cart_item', array($this, 'get_cart_item'));
        add_action('wp_ajax_eq_get_cart_totals', array($this, 'get_cart_totals'));
        add_action('wp_ajax_eq_validate_cart_date', array($this, 'validate_cart_date'));
        add_action('wp_ajax_eq_validate_cart_date_change', array($this, 'validate_cart_date_change'));
        add_action('wp_ajax_eq_update_cart_date', array($this, 'update_cart_date'));
        add_action('wp_ajax_eq_get_cart_master_date', array($this, 'get_cart_master_date'));
        add_action('wp_ajax_eq_validate_all_cart_items', array($this, 'validate_all_cart_items'));
        add_action('wp_ajax_eq_check_item_in_cart', array($this, 'check_item_in_cart'));
        add_action('wp_ajax_eq_update_cart_event', array($this, 'update_cart_event'));
    }
    
    /**
     * Agrega un item al carrito
     */
    public function add_to_cart() {
        global $wpdb;
        
        // Verificar seguridad y permisos
        $security_check = Event_Quote_Cart_Security_Helper::verify_ajax_request('manage_quotes');
        if (is_wp_error($security_check)) {
            wp_send_json_error($security_check->get_error_message());
            return;
        }
        
        // Sanitizar todos los inputs
        $sanitize_rules = array(
            'listing_id' => 'int',
            'date' => 'text',
            'quantity' => 'int',
            'end_date' => 'text',
            'days_count' => 'int',
            'is_date_range' => 'bool',
            'context_lead_id' => 'int',
            'context_event_id' => 'int',
            'calculated_price' => 'float'
        );
        
        $sanitized_data = Event_Quote_Cart_Security_Helper::sanitize_array($_POST, $sanitize_rules);
        
        $listing_id = Event_Quote_Cart_Security_Helper::validate_post_id(
            $sanitized_data['listing_id'] ?? 0,
            'hp_listing'
        );
        
        if (!$listing_id) {
            wp_send_json_error(Event_Quote_Cart_Constants::get_error_message('invalid_data'));
            return;
        }
        
        $date = $sanitized_data['date'] ?? '';
        $quantity = max(1, $sanitized_data['quantity'] ?? 1);
        $extras = Event_Quote_Cart_Security_Helper::sanitize_array($_POST['extras'] ?? array());
        
        // Datos adicionales para rangos de fechas
        $end_date = $sanitized_data['end_date'] ?? '';
        $days_count = $sanitized_data['days_count'] ?? 1;
        $is_date_range = $sanitized_data['is_date_range'] ?? false;
        
        // Validar fecha
        if (!$this->validate_date_format($date)) {
            wp_send_json_error('Invalid date format');
            return;
        }
        
        try {
            // Obtener o crear carrito
            $lead_id = $sanitized_data['context_lead_id'] ?? null;
            $event_id = $sanitized_data['context_event_id'] ?? null;
            $cart_id = $this->get_or_create_cart($lead_id, $event_id);
            
            // Verificar disponibilidad
            if (!$this->date_handler->check_listing_availability($listing_id, $date)) {
                throw new Exception('Date not available for this listing');
            }
            
            // Calcular precio
            $total_price = $sanitized_data['calculated_price'] ?? 
                $this->calculate_price($listing_id, $quantity, $extras, $date);
            
            // Preparar datos para inserción
            $item_data = array(
                'cart_id' => $cart_id,
                'listing_id' => $listing_id,
                'quantity' => $quantity,
                'date' => $date,
                'end_date' => $end_date,
                'days_count' => $days_count,
                'is_date_range' => $is_date_range,
                'base_price' => get_post_meta($listing_id, 'hp_price', true),
                'extras_data' => json_encode($extras),
                'total_price' => $total_price,
                'created_at' => current_time('mysql')
            );
            
            // Insertar en base de datos
            $result = $wpdb->insert(
                $wpdb->prefix . 'eq_cart_items',
                $item_data,
                array('%d', '%d', '%d', '%s', '%s', '%d', '%d', '%f', '%s', '%f', '%s')
            );
            
            if ($result === false) {
                throw new Exception('Error adding item to cart');
            }
            
            $item_id = $wpdb->insert_id;
            
            // Actualizar fecha maestra del carrito si es necesario
            $this->update_cart_master_date($cart_id, $date);
            
            wp_send_json_success(array(
                'message' => 'Item added to cart',
                'item_id' => $item_id,
                'cart_id' => $cart_id,
                'total_items' => $this->get_cart_item_count($cart_id)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Actualiza un item del carrito
     */
    public function update_cart_item() {
        global $wpdb;
        
        // Verificar seguridad
        $security_check = Event_Quote_Cart_Security_Helper::verify_ajax_request('manage_quotes');
        if (is_wp_error($security_check)) {
            wp_send_json_error($security_check->get_error_message());
            return;
        }
        
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        $extras = Event_Quote_Cart_Security_Helper::sanitize_array($_POST['extras'] ?? array());
        
        if (!$item_id) {
            wp_send_json_error('Invalid item ID');
            return;
        }
        
        try {
            // Verificar que el item pertenece al usuario
            $item = $this->get_cart_item_by_id($item_id);
            if (!$item) {
                throw new Exception('Item not found');
            }
            
            // Recalcular precio
            $total_price = $this->calculate_price($item->listing_id, $quantity, $extras, $item->date);
            
            // Actualizar item
            $result = $wpdb->update(
                $wpdb->prefix . 'eq_cart_items',
                array(
                    'quantity' => $quantity,
                    'extras_data' => json_encode($extras),
                    'total_price' => $total_price
                ),
                array('id' => $item_id),
                array('%d', '%s', '%f'),
                array('%d')
            );
            
            if ($result === false) {
                throw new Exception('Error updating cart item');
            }
            
            wp_send_json_success(array(
                'message' => 'Cart item updated',
                'new_total' => $total_price
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Obtiene los items del carrito
     */
    public function get_cart_items() {
        global $wpdb;
        
        // Verificar seguridad
        $security_check = Event_Quote_Cart_Security_Helper::verify_ajax_request('view_quotes');
        if (is_wp_error($security_check)) {
            wp_send_json_error($security_check->get_error_message());
            return;
        }
        
        $cart_id = intval($_POST['cart_id'] ?? 0);
        
        if (!$cart_id) {
            // Intentar obtener el carrito activo del usuario
            $cart_id = $this->get_active_cart_id();
        }
        
        if (!$cart_id) {
            wp_send_json_success(array('items' => array()));
            return;
        }
        
        try {
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT ci.*, p.post_title as listing_title
                FROM {$wpdb->prefix}eq_cart_items ci
                LEFT JOIN {$wpdb->posts} p ON ci.listing_id = p.ID
                WHERE ci.cart_id = %d
                ORDER BY ci.created_at DESC",
                $cart_id
            ));
            
            // Formatear items para respuesta
            $formatted_items = array();
            foreach ($items as $item) {
                $formatted_items[] = $this->format_cart_item($item);
            }
            
            wp_send_json_success(array(
                'items' => $formatted_items,
                'totals' => $this->calculate_cart_totals($cart_id)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Helpers privados
     */
    
    private function validate_date_format($date) {
        if (empty($date)) {
            return false;
        }
        
        // Convertir timestamp si es necesario
        if (is_numeric($date)) {
            $date = date('Y-m-d', strlen($date) == 13 ? intval($date) / 1000 : intval($date));
        }
        
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
    
    private function get_or_create_cart($lead_id = null, $event_id = null) {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Buscar carrito activo
        $query = "SELECT id FROM {$wpdb->prefix}eq_carts WHERE user_id = %d AND status = %s";
        $params = array($user_id, Event_Quote_Cart_Constants::CART_STATUS_ACTIVE);
        
        if ($lead_id && $event_id) {
            $query .= " AND lead_id = %d AND event_id = %d";
            $params[] = $lead_id;
            $params[] = $event_id;
        }
        
        $query .= " ORDER BY created_at DESC LIMIT 1";
        
        $cart_id = $wpdb->get_var($wpdb->prepare($query, $params));
        
        if (!$cart_id) {
            // Crear nuevo carrito
            $wpdb->insert(
                $wpdb->prefix . 'eq_carts',
                array(
                    'user_id' => $user_id,
                    'lead_id' => $lead_id,
                    'event_id' => $event_id,
                    'status' => Event_Quote_Cart_Constants::CART_STATUS_ACTIVE,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s', '%s')
            );
            $cart_id = $wpdb->insert_id;
        }
        
        return $cart_id;
    }
    
    private function get_active_cart_id() {
        $user_id = get_current_user_id();
        return get_user_meta($user_id, 'eq_active_cart_id', true);
    }
    
    private function get_cart_item_by_id($item_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_cart_items WHERE id = %d",
            $item_id
        ));
    }
    
    private function get_cart_item_count($cart_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}eq_cart_items WHERE cart_id = %d",
            $cart_id
        ));
    }
    
    private function update_cart_master_date($cart_id, $date) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'eq_carts',
            array('master_date' => $date),
            array('id' => $cart_id),
            array('%s'),
            array('%d')
        );
    }
    
    private function calculate_price($listing_id, $quantity, $extras, $date) {
        // Implementación del cálculo de precio
        // TODO: Mover a una clase separada PriceCalculator
        $base_price = floatval(get_post_meta($listing_id, 'hp_price', true));
        $total = $base_price * $quantity;
        
        // Agregar extras
        if (is_array($extras)) {
            foreach ($extras as $extra) {
                if (isset($extra['price']) && isset($extra['quantity'])) {
                    $total += floatval($extra['price']) * intval($extra['quantity']);
                }
            }
        }
        
        return $total;
    }
    
    private function calculate_cart_totals($cart_id) {
        global $wpdb;
        
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as item_count,
                SUM(total_price) as subtotal
            FROM {$wpdb->prefix}eq_cart_items 
            WHERE cart_id = %d",
            $cart_id
        ));
        
        $tax_rate = Event_Quote_Cart_Constants::get_tax_rate();
        $subtotal = floatval($totals->subtotal);
        $tax = $subtotal * ($tax_rate / 100);
        
        return array(
            'item_count' => intval($totals->item_count),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'tax_rate' => $tax_rate
        );
    }
    
    private function format_cart_item($item) {
        return array(
            'id' => $item->id,
            'listing_id' => $item->listing_id,
            'listing_title' => $item->listing_title,
            'quantity' => $item->quantity,
            'date' => $item->date,
            'end_date' => $item->end_date,
            'days_count' => $item->days_count,
            'is_date_range' => (bool)$item->is_date_range,
            'base_price' => floatval($item->base_price),
            'extras' => json_decode($item->extras_data, true),
            'total_price' => floatval($item->total_price),
            'image' => get_the_post_thumbnail_url($item->listing_id, 'thumbnail')
        );
    }
}