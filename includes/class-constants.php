<?php
/**
 * Constants Class
 * 
 * Centraliza todas las constantes y valores configurables del plugin
 * para evitar valores hardcodeados en el código
 * 
 * @package Event_Quote_Cart
 * @since 2.0.0
 */

if (!defined('WPINC')) {
    die;
}

class Event_Quote_Cart_Constants {
    
    /**
     * Estados de carrito
     */
    const CART_STATUS_ACTIVE = 'active';
    const CART_STATUS_COMPLETED = 'completed';
    const CART_STATUS_CANCELLED = 'cancelled';
    const CART_STATUS_EXPIRED = 'expired';
    
    /**
     * Estados de leads
     */
    const LEAD_STATUS_NEW = 'nuevo';
    const LEAD_STATUS_WITH_QUOTE = 'con-presupuesto';
    const LEAD_STATUS_CONTACTED = 'contactado';
    const LEAD_STATUS_CLOSED = 'cerrado';
    
    /**
     * Tipos de eventos
     */
    const EVENT_TYPE_WEDDING = 'boda';
    const EVENT_TYPE_CORPORATE = 'corporativo';
    const EVENT_TYPE_SOCIAL = 'social';
    const EVENT_TYPE_BIRTHDAY = 'cumpleanos';
    const EVENT_TYPE_OTHER = 'otro';
    
    /**
     * Configuración de precios
     */
    const DEFAULT_TAX_RATE = 16; // Porcentaje de IVA por defecto
    const DEFAULT_CURRENCY = 'MXN';
    const PRICE_DECIMALS = 2;
    
    /**
     * Límites del sistema
     */
    const MAX_CART_ITEMS = 50;
    const MAX_EXTRAS_PER_ITEM = 20;
    const CART_EXPIRATION_HOURS = 72;
    const SESSION_TIMEOUT_MINUTES = 30;
    
    /**
     * Configuración de archivos
     */
    const MAX_FILE_SIZE = 10485760; // 10MB en bytes
    const ALLOWED_IMAGE_TYPES = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    const PDF_TEMP_DIR = 'eq-cart-temp';
    
    /**
     * Configuración AJAX
     */
    const AJAX_TIMEOUT = 30000; // 30 segundos en milisegundos
    const AJAX_RETRY_ATTEMPTS = 3;
    
    /**
     * Mensajes de error estándar
     */
    const ERROR_UNAUTHORIZED = 'No tienes permisos para realizar esta acción';
    const ERROR_INVALID_DATA = 'Los datos proporcionados no son válidos';
    const ERROR_NOT_FOUND = 'El elemento solicitado no fue encontrado';
    const ERROR_SYSTEM = 'Ha ocurrido un error en el sistema. Por favor, intenta nuevamente';
    
    /**
     * Configuración de notificaciones
     */
    const NOTIFICATION_SUCCESS_DURATION = 3000; // milisegundos
    const NOTIFICATION_ERROR_DURATION = 5000; // milisegundos
    
    /**
     * Obtiene todos los estados de carrito disponibles
     * 
     * @return array
     */
    public static function get_cart_statuses() {
        return array(
            self::CART_STATUS_ACTIVE => __('Activo', 'event-quote-cart'),
            self::CART_STATUS_COMPLETED => __('Completado', 'event-quote-cart'),
            self::CART_STATUS_CANCELLED => __('Cancelado', 'event-quote-cart'),
            self::CART_STATUS_EXPIRED => __('Expirado', 'event-quote-cart')
        );
    }
    
    /**
     * Obtiene todos los estados de leads disponibles
     * 
     * @return array
     */
    public static function get_lead_statuses() {
        return array(
            self::LEAD_STATUS_NEW => __('Nuevo', 'event-quote-cart'),
            self::LEAD_STATUS_WITH_QUOTE => __('Con Presupuesto', 'event-quote-cart'),
            self::LEAD_STATUS_CONTACTED => __('Contactado', 'event-quote-cart'),
            self::LEAD_STATUS_CLOSED => __('Cerrado', 'event-quote-cart')
        );
    }
    
    /**
     * Obtiene todos los tipos de eventos disponibles
     * 
     * @return array
     */
    public static function get_event_types() {
        return array(
            self::EVENT_TYPE_WEDDING => __('Boda', 'event-quote-cart'),
            self::EVENT_TYPE_CORPORATE => __('Corporativo', 'event-quote-cart'),
            self::EVENT_TYPE_SOCIAL => __('Social', 'event-quote-cart'),
            self::EVENT_TYPE_BIRTHDAY => __('Cumpleaños', 'event-quote-cart'),
            self::EVENT_TYPE_OTHER => __('Otro', 'event-quote-cart')
        );
    }
    
    /**
     * Obtiene la configuración de impuestos
     * 
     * @return float
     */
    public static function get_tax_rate() {
        // Intentar obtener de WooCommerce primero
        if (class_exists('WC_Tax')) {
            $rates = WC_Tax::get_rates();
            if (!empty($rates)) {
                $first_rate = reset($rates);
                return floatval($first_rate['rate']);
            }
        }
        
        // Intentar obtener de la configuración del plugin
        $custom_rate = get_option('eq_cart_tax_rate');
        if ($custom_rate !== false) {
            return floatval($custom_rate);
        }
        
        // Retornar valor por defecto
        return self::DEFAULT_TAX_RATE;
    }
    
    /**
     * Obtiene la configuración de moneda
     * 
     * @return array
     */
    public static function get_currency_config() {
        $config = array(
            'currency' => self::DEFAULT_CURRENCY,
            'symbol' => '$',
            'thousand_separator' => ',',
            'decimal_separator' => '.',
            'decimals' => self::PRICE_DECIMALS
        );
        
        // Si WooCommerce está activo, usar su configuración
        if (function_exists('get_woocommerce_currency')) {
            $config['currency'] = get_woocommerce_currency();
            $config['symbol'] = get_woocommerce_currency_symbol();
            $config['thousand_separator'] = wc_get_price_thousand_separator();
            $config['decimal_separator'] = wc_get_price_decimal_separator();
            $config['decimals'] = wc_get_price_decimals();
        }
        
        return $config;
    }
    
    /**
     * Valida si un estado es válido
     * 
     * @param string $status Estado a validar
     * @param string $type Tipo de estado (cart, lead, event)
     * @return bool
     */
    public static function is_valid_status($status, $type = 'cart') {
        switch ($type) {
            case 'cart':
                return array_key_exists($status, self::get_cart_statuses());
            case 'lead':
                return array_key_exists($status, self::get_lead_statuses());
            case 'event':
                return array_key_exists($status, self::get_event_types());
            default:
                return false;
        }
    }
    
    /**
     * Obtiene un mensaje de error localizado
     * 
     * @param string $error_key Clave del error
     * @return string
     */
    public static function get_error_message($error_key) {
        $messages = array(
            'unauthorized' => self::ERROR_UNAUTHORIZED,
            'invalid_data' => self::ERROR_INVALID_DATA,
            'not_found' => self::ERROR_NOT_FOUND,
            'system' => self::ERROR_SYSTEM
        );
        
        return isset($messages[$error_key]) ? $messages[$error_key] : self::ERROR_SYSTEM;
    }
    
    /**
     * Obtiene la configuración para JavaScript
     * 
     * @return array
     */
    public static function get_js_config() {
        return array(
            'statuses' => array(
                'cart' => self::get_cart_statuses(),
                'lead' => self::get_lead_statuses(),
                'event' => self::get_event_types()
            ),
            'limits' => array(
                'maxCartItems' => self::MAX_CART_ITEMS,
                'maxExtrasPerItem' => self::MAX_EXTRAS_PER_ITEM,
                'cartExpirationHours' => self::CART_EXPIRATION_HOURS,
                'sessionTimeoutMinutes' => self::SESSION_TIMEOUT_MINUTES
            ),
            'ajax' => array(
                'timeout' => self::AJAX_TIMEOUT,
                'retryAttempts' => self::AJAX_RETRY_ATTEMPTS
            ),
            'notifications' => array(
                'successDuration' => self::NOTIFICATION_SUCCESS_DURATION,
                'errorDuration' => self::NOTIFICATION_ERROR_DURATION
            ),
            'currency' => self::get_currency_config(),
            'taxRate' => self::get_tax_rate()
        );
    }
}