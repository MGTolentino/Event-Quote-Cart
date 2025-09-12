<?php
/**
 * Context AJAX Handler Class
 * 
 * Maneja todas las operaciones AJAX relacionadas con el contexto de sesión
 * 
 * @package Event_Quote_Cart
 * @since 2.0.0
 */

if (!defined('WPINC')) {
    die;
}

class Event_Quote_Cart_Context_Ajax_Handler {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    public function init_hooks() {
        add_action('wp_ajax_eq_create_context_session', array($this, 'create_context_session'));
        add_action('wp_ajax_eq_verify_context_session', array($this, 'verify_context_session'));
        add_action('wp_ajax_eq_verify_context_cleared', array($this, 'verify_context_cleared'));
        add_action('wp_ajax_eq_clear_context_meta', array($this, 'clear_context_meta'));
        add_action('wp_ajax_eq_check_context_status', array($this, 'check_context_status'));
        add_action('wp_ajax_eq_update_cart_context', array($this, 'update_cart_context'));
        add_action('wp_logout', array($this, 'clear_context_on_logout'));
    }
    
    /**
     * Crea una nueva sesión de contexto
     */
    public function create_context_session() {
        global $wpdb;
        
        // Verificar seguridad
        $security_check = Event_Quote_Cart_Security_Helper::verify_ajax_request('manage_quotes');
        if (is_wp_error($security_check)) {
            wp_send_json_error($security_check->get_error_message());
            return;
        }
        
        // Sanitizar inputs
        $sanitize_rules = array(
            'lead_id' => 'int',
            'event_id' => 'int'
        );
        
        $data = Event_Quote_Cart_Security_Helper::sanitize_array($_POST, $sanitize_rules);
        
        $lead_id = $data['lead_id'] ?? 0;
        $event_id = $data['event_id'] ?? 0;
        $user_id = get_current_user_id();
        
        if (!$lead_id || !$event_id) {
            wp_send_json_error('Missing required context data');
            return;
        }
        
        try {
            // Verificar que el lead y evento existen
            if (!$this->verify_lead_exists($lead_id) || !$this->verify_event_exists($event_id)) {
                throw new Exception('Invalid lead or event ID');
            }
            
            // Generar token único de sesión
            $session_token = wp_generate_password(32, false);
            
            // Eliminar sesiones anteriores del usuario
            $wpdb->delete(
                $wpdb->prefix . 'eq_context_sessions',
                array('user_id' => $user_id),
                array('%d')
            );
            
            // Crear nueva sesión
            $result = $wpdb->insert(
                $wpdb->prefix . 'eq_context_sessions',
                array(
                    'user_id' => $user_id,
                    'lead_id' => $lead_id,
                    'event_id' => $event_id,
                    'session_token' => $session_token,
                    'created_at' => current_time('mysql'),
                    'last_activity' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                throw new Exception('Error creating context session');
            }
            
            // Actualizar sesión PHP
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            $_SESSION['eq_quote_context'] = array(
                'lead_id' => $lead_id,
                'event_id' => $event_id,
                'user_id' => $user_id,
                'timestamp' => time(),
                'session_token' => $session_token
            );
            
            // Buscar o crear carrito para este contexto
            $cart_id = $this->get_or_create_context_cart($user_id, $lead_id, $event_id);
            
            // Actualizar meta del usuario
            update_user_meta($user_id, 'eq_active_cart_id', $cart_id);
            
            wp_send_json_success(array(
                'message' => 'Context session created',
                'session_token' => $session_token,
                'cart_id' => $cart_id,
                'lead_id' => $lead_id,
                'event_id' => $event_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Verifica una sesión de contexto existente
     */
    public function verify_context_session() {
        global $wpdb;
        
        // Verificar seguridad
        $security_check = Event_Quote_Cart_Security_Helper::verify_ajax_request('view_quotes');
        if (is_wp_error($security_check)) {
            wp_send_json_error($security_check->get_error_message());
            return;
        }
        
        $user_id = get_current_user_id();
        
        try {
            // Buscar sesión activa
            $session = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eq_context_sessions 
                WHERE user_id = %d 
                AND TIMESTAMPDIFF(MINUTE, last_activity, NOW()) < %d
                LIMIT 1",
                $user_id,
                Event_Quote_Cart_Constants::SESSION_TIMEOUT_MINUTES
            ));
            
            if (!$session) {
                wp_send_json_success(array(
                    'has_context' => false,
                    'message' => 'No active context session'
                ));
                return;
            }
            
            // Verificar que lead y evento aún existen
            if (!$this->verify_lead_exists($session->lead_id) || 
                !$this->verify_event_exists($session->event_id)) {
                
                // Eliminar sesión inválida
                $wpdb->delete(
                    $wpdb->prefix . 'eq_context_sessions',
                    array('id' => $session->id),
                    array('%d')
                );
                
                wp_send_json_success(array(
                    'has_context' => false,
                    'message' => 'Context session invalid'
                ));
                return;
            }
            
            // Actualizar última actividad
            $wpdb->update(
                $wpdb->prefix . 'eq_context_sessions',
                array('last_activity' => current_time('mysql')),
                array('id' => $session->id),
                array('%s'),
                array('%d')
            );
            
            // Obtener información adicional
            $lead_info = $this->get_lead_info($session->lead_id);
            $event_info = $this->get_event_info($session->event_id);
            
            wp_send_json_success(array(
                'has_context' => true,
                'context' => array(
                    'lead_id' => $session->lead_id,
                    'event_id' => $session->event_id,
                    'session_token' => $session->session_token,
                    'lead_name' => $lead_info['name'],
                    'event_name' => $event_info['name'],
                    'event_date' => $event_info['date']
                )
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Limpia el contexto de sesión
     */
    public function clear_context_meta() {
        global $wpdb;
        
        // Verificar seguridad
        $security_check = Event_Quote_Cart_Security_Helper::verify_ajax_request('manage_quotes');
        if (is_wp_error($security_check)) {
            wp_send_json_error($security_check->get_error_message());
            return;
        }
        
        $user_id = get_current_user_id();
        
        try {
            // Eliminar sesión de base de datos
            $wpdb->delete(
                $wpdb->prefix . 'eq_context_sessions',
                array('user_id' => $user_id),
                array('%d')
            );
            
            // Limpiar sesión PHP
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            if (isset($_SESSION['eq_quote_context'])) {
                unset($_SESSION['eq_quote_context']);
            }
            
            $_SESSION['eq_context_no_restore'] = true;
            
            // Limpiar meta del usuario
            delete_user_meta($user_id, 'eq_active_cart_id');
            
            // Establecer cookie para prevenir restauración
            setcookie('eq_session_ended', 'true', time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
            
            wp_send_json_success(array(
                'message' => 'Context cleared successfully'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Verifica el estado del contexto
     */
    public function check_context_status() {
        // Verificar seguridad
        $security_check = Event_Quote_Cart_Security_Helper::verify_ajax_request('view_quotes');
        if (is_wp_error($security_check)) {
            wp_send_json_error($security_check->get_error_message());
            return;
        }
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $has_context = isset($_SESSION['eq_quote_context']) && 
                      !empty($_SESSION['eq_quote_context']['lead_id']) &&
                      !empty($_SESSION['eq_quote_context']['event_id']);
        
        $context_data = null;
        if ($has_context) {
            $context_data = array(
                'lead_id' => $_SESSION['eq_quote_context']['lead_id'],
                'event_id' => $_SESSION['eq_quote_context']['event_id']
            );
        }
        
        wp_send_json_success(array(
            'has_context' => $has_context,
            'context' => $context_data
        ));
    }
    
    /**
     * Limpia el contexto al hacer logout
     */
    public function clear_context_on_logout() {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Eliminar sesiones de base de datos
        $wpdb->delete(
            $wpdb->prefix . 'eq_context_sessions',
            array('user_id' => $user_id),
            array('%d')
        );
        
        // Limpiar sesión PHP
        if (isset($_SESSION['eq_quote_context'])) {
            unset($_SESSION['eq_quote_context']);
        }
        
        // Limpiar meta del usuario
        delete_user_meta($user_id, 'eq_active_cart_id');
    }
    
    /**
     * Helpers privados
     */
    
    private function verify_lead_exists($lead_id) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_leads WHERE _ID = %d",
            $lead_id
        ));
    }
    
    private function verify_event_exists($event_id) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
            $event_id
        ));
    }
    
    private function get_lead_info($lead_id) {
        global $wpdb;
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT cct_single_text_nombre as name, cct_single_text_email as email 
            FROM {$wpdb->prefix}jet_cct_leads 
            WHERE _ID = %d",
            $lead_id
        ), ARRAY_A);
        
        return $lead ?: array('name' => '', 'email' => '');
    }
    
    private function get_event_info($event_id) {
        global $wpdb;
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT cct_single_text_nombre_evento as name, cct_single_text_fecha_evento as date 
            FROM {$wpdb->prefix}jet_cct_eventos 
            WHERE _ID = %d",
            $event_id
        ), ARRAY_A);
        
        return $event ?: array('name' => '', 'date' => '');
    }
    
    private function get_or_create_context_cart($user_id, $lead_id, $event_id) {
        global $wpdb;
        
        // Buscar carrito existente
        $cart_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND lead_id = %d AND event_id = %d 
            AND status = %s
            ORDER BY created_at DESC LIMIT 1",
            $user_id, $lead_id, $event_id,
            Event_Quote_Cart_Constants::CART_STATUS_ACTIVE
        ));
        
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
}