<?php
/**
 * AJAX Handler Loader Class
 * 
 * Carga y coordina todos los manejadores AJAX modulares
 * 
 * @package Event_Quote_Cart
 * @since 2.0.0
 */

if (!defined('WPINC')) {
    die;
}

class Event_Quote_Cart_Ajax_Handler_Loader {
    
    private $handlers = array();
    
    public function __construct() {
        $this->load_dependencies();
        $this->init_handlers();
    }
    
    /**
     * Carga las dependencias necesarias
     */
    private function load_dependencies() {
        // Cargar clases de utilidad
        require_once EQ_CART_PLUGIN_DIR . 'includes/class-constants.php';
        require_once EQ_CART_PLUGIN_DIR . 'includes/class-security-helper.php';
        
        // Cargar manejadores AJAX modulares
        require_once EQ_CART_PLUGIN_DIR . 'includes/ajax/class-cart-ajax-handler.php';
        require_once EQ_CART_PLUGIN_DIR . 'includes/ajax/class-context-ajax-handler.php';
        
        // Mantener el handler original para funciones no migradas aún
        // TODO: Migrar todas las funciones y eliminar este archivo
        require_once EQ_CART_PLUGIN_DIR . 'includes/class-ajax-handler.php';
    }
    
    /**
     * Inicializa todos los manejadores AJAX
     */
    private function init_handlers() {
        // Inicializar manejadores modulares
        $this->handlers['cart'] = new Event_Quote_Cart_Cart_Ajax_Handler();
        $this->handlers['context'] = new Event_Quote_Cart_Context_Ajax_Handler();
        
        // Inicializar el handler original para funcionalidad no migrada
        // TODO: Eliminar cuando toda la funcionalidad esté migrada
        $this->handlers['legacy'] = new Event_Quote_Cart_Ajax_Handler();
    }
    
    /**
     * Obtiene un manejador específico
     * 
     * @param string $handler_name Nombre del manejador
     * @return object|null
     */
    public function get_handler($handler_name) {
        return isset($this->handlers[$handler_name]) ? $this->handlers[$handler_name] : null;
    }
    
    /**
     * Registra hooks adicionales si es necesario
     */
    public function register_additional_hooks() {
        // Hook para limpiar sesiones expiradas
        add_action('eq_cleanup_expired_sessions', array($this, 'cleanup_expired_sessions'));
        
        // Programar limpieza diaria si no está programada
        if (!wp_next_scheduled('eq_cleanup_expired_sessions')) {
            wp_schedule_event(time(), 'daily', 'eq_cleanup_expired_sessions');
        }
    }
    
    /**
     * Limpia sesiones expiradas
     */
    public function cleanup_expired_sessions() {
        global $wpdb;
        
        // Eliminar sesiones inactivas por más del tiempo límite
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}eq_context_sessions 
            WHERE TIMESTAMPDIFF(MINUTE, last_activity, NOW()) > %d",
            Event_Quote_Cart_Constants::SESSION_TIMEOUT_MINUTES
        ));
        
        // Marcar carritos como expirados si han pasado el límite de tiempo
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}eq_carts 
            SET status = %s 
            WHERE status = %s 
            AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > %d",
            Event_Quote_Cart_Constants::CART_STATUS_EXPIRED,
            Event_Quote_Cart_Constants::CART_STATUS_ACTIVE,
            Event_Quote_Cart_Constants::CART_EXPIRATION_HOURS
        ));
    }
}