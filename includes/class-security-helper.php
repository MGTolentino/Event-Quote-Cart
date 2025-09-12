<?php
/**
 * Security Helper Class
 * 
 * Centraliza todas las funciones de seguridad y validación
 * para eliminar duplicación de código y mejorar la seguridad
 * 
 * @package Event_Quote_Cart
 * @since 2.0.0
 */

if (!defined('WPINC')) {
    die;
}

class Event_Quote_Cart_Security_Helper {
    
    /**
     * Capabilities requeridas para diferentes acciones
     */
    const CAP_VIEW_QUOTES = 'eq_view_quotes';
    const CAP_MANAGE_QUOTES = 'eq_manage_quotes';
    const CAP_USE_LEADS = 'eq_use_leads';
    const CAP_MANAGE_SETTINGS = 'eq_manage_settings';
    
    /**
     * Roles permitidos (para compatibilidad)
     */
    const ALLOWED_ROLES = array('administrator', 'ejecutivo_de_ventas');
    
    /**
     * Verifica si el usuario actual puede ver el botón de cotización
     * 
     * @return bool
     */
    public static function can_view_quote_button() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Verificar por capability primero
        if (current_user_can(self::CAP_VIEW_QUOTES)) {
            return true;
        }
        
        // Fallback a verificación por roles para compatibilidad
        $user = wp_get_current_user();
        
        // Verificar roles administrativos
        $has_role_access = array_intersect(self::ALLOWED_ROLES, $user->roles) ? true : false;
        
        // Verificar si es vendor (integración con Vendor Dashboard PRO)
        $is_vendor = function_exists('vdp_is_user_vendor') ? vdp_is_user_vendor() : false;
        
        return $has_role_access || $is_vendor;
    }
    
    /**
     * Verifica si el usuario puede usar la integración de leads
     * 
     * @return bool
     */
    public static function can_use_leads_integration() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Verificar por capability
        if (current_user_can(self::CAP_USE_LEADS)) {
            return true;
        }
        
        // Fallback a roles
        $user = wp_get_current_user();
        return array_intersect(self::ALLOWED_ROLES, $user->roles) ? true : false;
    }
    
    /**
     * Verifica permisos AJAX con nonce
     * 
     * @param string $action Acción específica a verificar
     * @param string $nonce_field Campo del nonce en $_POST
     * @param string $nonce_action Acción del nonce
     * @return bool|WP_Error
     */
    public static function verify_ajax_request($action = 'view_quotes', $nonce_field = 'nonce', $nonce_action = 'eq_cart_public_nonce') {
        // Verificar nonce
        if (!isset($_POST[$nonce_field]) || !wp_verify_nonce($_POST[$nonce_field], $nonce_action)) {
            return new WP_Error('invalid_nonce', 'Security check failed');
        }
        
        // Verificar permisos según la acción
        $permission_map = array(
            'view_quotes' => self::CAP_VIEW_QUOTES,
            'manage_quotes' => self::CAP_MANAGE_QUOTES,
            'use_leads' => self::CAP_USE_LEADS,
            'manage_settings' => self::CAP_MANAGE_SETTINGS
        );
        
        $required_cap = isset($permission_map[$action]) ? $permission_map[$action] : self::CAP_VIEW_QUOTES;
        
        // Verificar capability o roles
        if (!current_user_can($required_cap) && !self::has_allowed_role()) {
            return new WP_Error('unauthorized', 'Insufficient permissions');
        }
        
        return true;
    }
    
    /**
     * Verifica si el usuario tiene un rol permitido
     * 
     * @return bool
     */
    private static function has_allowed_role() {
        $user = wp_get_current_user();
        return array_intersect(self::ALLOWED_ROLES, $user->roles) ? true : false;
    }
    
    /**
     * Sanitiza un array de datos recursivamente
     * 
     * @param array $data Array a sanitizar
     * @param array $rules Reglas de sanitización por campo
     * @return array
     */
    public static function sanitize_array($data, $rules = array()) {
        if (!is_array($data)) {
            return array();
        }
        
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            $clean_key = sanitize_key($key);
            
            // Si hay una regla específica para este campo
            if (isset($rules[$clean_key])) {
                $sanitized[$clean_key] = self::apply_sanitization_rule($value, $rules[$clean_key]);
            }
            // Si es un array, sanitizar recursivamente
            elseif (is_array($value)) {
                $sanitized[$clean_key] = self::sanitize_array($value, $rules);
            }
            // Si es numérico
            elseif (is_numeric($value)) {
                $sanitized[$clean_key] = is_float($value) ? floatval($value) : intval($value);
            }
            // Por defecto, sanitizar como texto
            else {
                $sanitized[$clean_key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Aplica una regla de sanitización específica
     * 
     * @param mixed $value Valor a sanitizar
     * @param string $rule Tipo de sanitización
     * @return mixed
     */
    private static function apply_sanitization_rule($value, $rule) {
        switch ($rule) {
            case 'int':
                return intval($value);
            case 'float':
                return floatval($value);
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'html':
                return wp_kses_post($value);
            case 'key':
                return sanitize_key($value);
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return self::sanitize_json($value);
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Sanitiza y valida datos JSON
     * 
     * @param string $json String JSON a sanitizar
     * @return array|null
     */
    public static function sanitize_json($json) {
        if (is_array($json)) {
            return self::sanitize_array($json);
        }
        
        $decoded = json_decode(stripslashes($json), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return self::sanitize_array($decoded);
    }
    
    /**
     * Valida y sanitiza un ID de post
     * 
     * @param mixed $id ID a validar
     * @param string $post_type Tipo de post esperado (opcional)
     * @return int|false
     */
    public static function validate_post_id($id, $post_type = null) {
        $id = intval($id);
        
        if ($id <= 0) {
            return false;
        }
        
        $post = get_post($id);
        
        if (!$post) {
            return false;
        }
        
        if ($post_type && $post->post_type !== $post_type) {
            return false;
        }
        
        return $id;
    }
    
    /**
     * Escapa y prepara datos para salida segura
     * 
     * @param mixed $data Datos a escapar
     * @param string $context Contexto de salida (html, attr, js, url)
     * @return mixed
     */
    public static function escape_output($data, $context = 'html') {
        if (is_array($data)) {
            return array_map(function($item) use ($context) {
                return self::escape_output($item, $context);
            }, $data);
        }
        
        switch ($context) {
            case 'html':
                return esc_html($data);
            case 'attr':
                return esc_attr($data);
            case 'js':
                return esc_js($data);
            case 'url':
                return esc_url($data);
            case 'textarea':
                return esc_textarea($data);
            default:
                return esc_html($data);
        }
    }
    
    /**
     * Registra las capabilities personalizadas
     * Se debe llamar durante la activación del plugin
     */
    public static function register_capabilities() {
        $roles = array('administrator');
        
        $caps = array(
            self::CAP_VIEW_QUOTES,
            self::CAP_MANAGE_QUOTES,
            self::CAP_USE_LEADS,
            self::CAP_MANAGE_SETTINGS
        );
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($caps as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
        
        // Dar capabilities específicas al rol ejecutivo_de_ventas
        $sales_role = get_role('ejecutivo_de_ventas');
        if ($sales_role) {
            $sales_role->add_cap(self::CAP_VIEW_QUOTES);
            $sales_role->add_cap(self::CAP_MANAGE_QUOTES);
            $sales_role->add_cap(self::CAP_USE_LEADS);
        }
    }
    
    /**
     * Remueve las capabilities personalizadas
     * Se debe llamar durante la desactivación del plugin
     */
    public static function remove_capabilities() {
        $roles = get_editable_roles();
        $caps = array(
            self::CAP_VIEW_QUOTES,
            self::CAP_MANAGE_QUOTES,
            self::CAP_USE_LEADS,
            self::CAP_MANAGE_SETTINGS
        );
        
        foreach ($roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
}