<?php
/**
 * The public-facing functionality of the plugin.
 */

class Event_Quote_Cart_Public {

    private $plugin_name;
    private $version;
    private $date_handler;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->date_handler = new Event_Quote_Cart_Date_Handler();
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     */
    public function enqueue_styles() {
		


        // Enqueue Flatpickr CSS if not already enqueued
        if (!wp_style_is('flatpickr', 'enqueued')) {
            wp_enqueue_style(
                'flatpickr',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
                array(),
                '4.6.9'
            );
        }
		
		if (!wp_style_is('font-awesome', 'enqueued')) {
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            array(),
            '5.15.4'
        );
    }
		
		wp_enqueue_style(
        $this->plugin_name . '-context-panel',
        EQ_CART_PLUGIN_URL . 'public/css/quote-context.css',
        array(),
        $this->version
    );
		
		wp_enqueue_style(
			'eq-notifications', 
			plugin_dir_url(__FILE__) . 'css/notifications.css', 
			array(), $this->version, 'all'
		);

		
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     */
    public function enqueue_scripts() {
        // Flatpickr
        wp_enqueue_script(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr',
            array('jquery'),
            '4.6.9',
            true
        );

        // Main plugin script
        wp_enqueue_script(
            $this->plugin_name,
            EQ_CART_PLUGIN_URL . 'public/js/quote-cart.js',
            array('jquery', 'flatpickr'),
            $this->version,
            true
        );
		
		wp_enqueue_script(
    'eq-cart-validation-js',
    EQ_CART_PLUGIN_URL . 'public/js/quote-cart-validation.js',
    array('jquery'),
    $this->version,
    true
);
		
		wp_enqueue_script(
        $this->plugin_name . '-context-panel',
        EQ_CART_PLUGIN_URL . 'public/js/quote-context.js',
        array('jquery', 'flatpickr'),
        $this->version,
        true
    );

        // Localize script
        wp_localize_script(
            $this->plugin_name,
            'eqCartData',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('eq_cart_public_nonce'),
                'cartUrl' => $this->get_cart_page_url(),
                'i18n' => array(
                    'addedToCart' => __('Added to quote cart', 'event-quote-cart'),
                    'errorAdding' => __('Error adding to quote cart', 'event-quote-cart'),
                    'removedFromCart' => __('Removed from quote cart', 'event-quote-cart'),
                    'errorRemoving' => __('Error removing from quote cart', 'event-quote-cart'),
                    'dateNotAvailable' => __('Selected date is not available', 'event-quote-cart'),
                    'invalidQuantity' => __('Please enter a valid quantity', 'event-quote-cart'),
                    'confirmRemove' => __('Are you sure you want to remove this item?', 'event-quote-cart'),
					'viewQuotes' => get_locale() === 'es_ES' ? 'Ver Cotizaciones' : 'View Quotes',
					'viewQuote' => get_locale() === 'es_ES' ? 'Ver Cotización' : 'View Quote'


                ),
				'isPrivilegedUser' => current_user_can('administrator') || current_user_can('ejecutivo_de_ventas'),
       				 'hasContextPanel' => $this->is_context_panel_active(),
       			 'taxRate' => floatval(get_option('eq_tax_rate', 16))
            )
        );
		
		// Obtener status dinámicamente de Leads Management
$status_options = array();
if (class_exists('LTB_Leads_Status_Utils')) {
    $status_options = LTB_Leads_Status_Utils::get_status_options();
} else {
    // Valores de respaldo si la clase no está disponible
    $status_options = array(
        'nuevo' => 'Nuevo',
        'con-presupuesto' => 'Con Presupuesto',
        'por-cerrar' => 'Por cerrar',
        'con-contrato' => 'Con contrato',
        'perdido' => 'Perdido'
    );
}

// Pasar los status al script del context panel
wp_localize_script(
    $this->plugin_name . '-context-panel',
    'eqStatusConfig',
    array(
        'statusOptions' => $status_options
    )
);
		
		 wp_localize_script(
        $this->plugin_name . '-context-panel',
        'eqContextData',
        array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eq_cart_public_nonce'),
            'canUseContextPanel' => function_exists('eq_can_view_quote_button') && eq_can_view_quote_button() ? true : false,
            'i18n' => array(
                'leadLabel' => __('Lead:', 'event-quote-cart'),
                'eventLabel' => __('Evento:', 'event-quote-cart'),
                'notSelected' => __('No seleccionado', 'event-quote-cart'),
                'selectLead' => __('Seleccionar Lead', 'event-quote-cart'),
                'selectEvent' => __('Seleccionar Evento', 'event-quote-cart'),
                'endSession' => __('Finalizar Sesión', 'event-quote-cart')
            )
        )
    );
		
		// Single integration script
				wp_enqueue_script(
					$this->plugin_name . '-single',
					EQ_CART_PLUGIN_URL . 'public/js/single-integration.js',
					array('jquery', 'flatpickr'),
					$this->version,
					true
				);
    }

    /**
 * Get the URL of the cart page
 */
private function get_cart_page_url() {
    $cart_page_id = get_option('eq_cart_page_id');
    if (!$cart_page_id) {
        return home_url(); // Fallback a home si no existe
    }
    
    $cart_url = get_permalink($cart_page_id);
    return $cart_url ? $cart_url : home_url();
}


    /**
 * Initialize hooks
 */
public function init() {
    // Assets
    add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

    // Template rendering
    add_action('wp_footer', array($this, 'render_cart_sidebar'));
    add_action('wp_footer', array($this, 'render_header_cart'));
    add_action('wp_footer', array($this, 'render_context_panel')); // Añadir esta línea
    add_action('eq_cart_add_quote_button', array($this, 'render_quote_button'));
	
	add_action('wp_ajax_eq_search_leads', array($this, 'search_leads'));
add_action('wp_ajax_eq_create_lead', array($this, 'create_lead'));
add_action('wp_ajax_eq_get_lead_events', array($this, 'get_lead_events'));
add_action('wp_ajax_eq_create_event', array($this, 'create_event'));

    // AJAX handlers
    add_action('wp_ajax_eq_remove_from_cart', array($this, 'remove_from_cart'));
    
    // Iniciar sesión si no está iniciada
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}

    /**
     * Render the quote button
     */
    public function render_quote_button($listing_id) {
        if (!function_exists('eq_can_view_quote_button') || !eq_can_view_quote_button()) {
            return;
        }

        $button_text = get_locale() === 'es_ES' ? 
            get_option('eq_cart_button_text', '+ Cotización') : 
            get_option('eq_cart_button_text_en', '+ Quote');

        include EQ_CART_PLUGIN_DIR . 'templates/quote-button.php';
    }

    /**
     * Render the cart sidebar
     */
    public function render_cart_sidebar() {
        if (!function_exists('eq_can_view_quote_button') || !eq_can_view_quote_button()) {
            return;
        }

        include EQ_CART_PLUGIN_DIR . 'templates/cart-sidebar.php';
    }
	
	public function render_header_cart() {
    if (!function_exists('eq_can_view_quote_button') || !eq_can_view_quote_button()) {
        return;
    }

    include EQ_CART_PLUGIN_DIR . 'templates/header-cart.php';
}
	
/**
 * Renderizar el panel de contexto
 */
public function render_context_panel() {
    // Verificación estricta de permisos
    if (!function_exists('eq_can_view_quote_button') || !eq_can_view_quote_button()) {
        return;
    }
    
    // Verificación adicional - comprobar roles directamente
    $user = wp_get_current_user();
    if (!$user || !in_array('administrator', $user->roles) && !in_array('ejecutivo_de_ventas', $user->roles)) {
        return;
    }
	
	// Verificar si estamos en una página relevante
    $show_panel = false;
    
    // 1. Verificar si es un hp_listing individual
    if (is_singular('hp_listing')) {
        $show_panel = true;
    }
    
    // 2. Verificar si estamos en la página del carrito
    $cart_page_id = get_option('eq_cart_page_id');
    if ($cart_page_id && is_page($cart_page_id)) {
        $show_panel = true;
    }
    
    // 3. Verificar si estamos en una página con el shortcode de cotizador-eventos
    global $post;
    if ($post && has_shortcode($post->post_content, 'cotizador_eventos')) {
        $show_panel = true;
    }
    
    // 4. Alternativa: verificar por URL si no podemos detectar por shortcode
    if (strpos($_SERVER['REQUEST_URI'], 'cotizador-de-eventos') !== false) {
        $show_panel = true;
    }
	
	if (!$show_panel) {
        return;
    }
    
    // Obtener datos de contexto (se cargarán vía JS, esto es solo para SSR inicial)
    $context_data = array(
        'lead_id' => null,
        'lead_name' => null,
        'event_id' => null,
        'event_date' => null,
        'event_type' => null
    );
    
    // Si hay datos en la sesión, extraerlos
    if (isset($_SESSION['eq_quote_context'])) {
        $context_data = $_SESSION['eq_quote_context'];
    }
    
    // Incluir template con los datos
    include EQ_CART_PLUGIN_DIR . 'templates/context-panel.php';
}

    /**
     * Format extras for response
     */
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

    /**
     * Remove item from cart
     */
    public function remove_from_cart() {
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
        
        // Verificar si el item ya está marcado como eliminado
        $existing_removed = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_cart_items 
            WHERE id = %d AND status = 'removed'",
            $item_id
        ));
        
        if ($existing_removed) {
            // Ya está marcado como eliminado, no hacer nada
            wp_send_json_success(array(
                'message' => 'Item was already removed'
            ));
            return;
        }
        
        // Obtener información del item antes de eliminarlo (para debugging)
        $item_info = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_cart_items WHERE id = %d",
            $item_id
        ));
        
        // Eliminar físicamente el item en lugar de cambiar su estado
        $result = $wpdb->delete(
            $wpdb->prefix . 'eq_cart_items',
            array('id' => $item_id),
            array('%d')
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }
        
        if (!$result) {
            throw new Exception('No se pudo eliminar el item');
        }

        wp_send_json_success(array(
            'message' => 'Item removed successfully'
        ));

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

    /**
     * Get cart item data
     */
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
            'price_formatted' => $this->calculate_item_price($item)
        );
    }

    /**
     * Calculate item price
     */
    private function calculate_item_price($item) {
        $form_data = json_decode($item->form_data, true);
        $base_price = get_post_meta($item->listing_id, 'hp_price', true);
        $total = floatval($base_price);

        // Apply quantity
        if (isset($form_data['quantity'])) {
            $total *= intval($form_data['quantity']);
        }

        // Add extras
        if (!empty($form_data['extras'])) {
            $extras = get_post_meta($item->listing_id, 'hp_price_extras', true);
            foreach ($form_data['extras'] as $extra_id) {
                if (isset($extras[$extra_id])) {
                    $total += floatval($extras[$extra_id]['price']);
                }
            }
        }

        return hivepress()->woocommerce->format_price($total);
    }

private function get_or_create_cart() {
    global $wpdb;
    
    $user_id = get_current_user_id();
    
    // Obtener datos de contexto desde sessionStorage (si están disponibles)
    $lead_id = null;
    $event_id = null;
    
    // Verificar si el usuario es admin o ejecutivo de ventas
    $is_privileged_user = current_user_can('administrator') || current_user_can('ejecutivo_de_ventas');
    
    // Si hay un contexto activo en la sesión, usarlo
    if (isset($_SESSION['eq_quote_context']) && 
        isset($_SESSION['eq_quote_context']['lead_id']) && 
        isset($_SESSION['eq_quote_context']['event_id'])) {
        
        $lead_id = intval($_SESSION['eq_quote_context']['lead_id']);
        $event_id = intval($_SESSION['eq_quote_context']['event_id']);
        
        // Si es usuario privilegiado, buscar el carrito específico para este lead/evento
        if ($is_privileged_user && $lead_id && $event_id) {
            $exact_cart = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eq_carts 
                WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
                ORDER BY created_at DESC LIMIT 1",
                $user_id, $lead_id, $event_id
            ));
            
            // Si existe un carrito específico, usarlo
            if ($exact_cart) {
                // Guardar el ID del carrito activo en user meta para referencia global
                update_user_meta($user_id, 'eq_active_cart_id', $exact_cart);
                return $exact_cart;
            }
            
            // Si no existe un carrito específico, crear uno nuevo
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
            
            // Guardar el ID del carrito activo en user meta
            update_user_meta($user_id, 'eq_active_cart_id', $new_cart_id);
            
            return $new_cart_id;
        }
    }
    
    // Para usuarios normales o si no hay contexto específico
    
    // Verificar si hay un carrito activo guardado en user meta
    $active_cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
    if ($active_cart_id) {
        // Verificar que el carrito exista y esté activo
        $cart_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE id = %d AND user_id = %d AND status = 'active'",
            $active_cart_id, $user_id
        ));
        
        if ($cart_exists) {
            return $active_cart_id;
        }
    }
    
    // Si no hay carrito en user meta o no existe, buscar cualquier carrito activo
    $cart_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}eq_carts 
        WHERE user_id = %d AND status = 'active'
        ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));
    
    if ($cart_id) {
        // Guardar este carrito como activo
        update_user_meta($user_id, 'eq_active_cart_id', $cart_id);
        return $cart_id;
    }
    
    // Si no hay carrito, crear uno nuevo
    $cart_data = array(
        'user_id' => $user_id,
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    );
    
    // Añadir lead_id y event_id si están disponibles
    if ($lead_id !== null) {
        $cart_data['lead_id'] = $lead_id;
    }
    
    if ($event_id !== null) {
        $cart_data['event_id'] = $event_id;
    }
    
    $wpdb->insert(
        $wpdb->prefix . 'eq_carts',
        $cart_data,
        array('%d', '%s', '%s', '%s', '%d', '%d')
    );
    
    $new_cart_id = $wpdb->insert_id;
    
    // Guardar el ID del carrito activo en user meta
    update_user_meta($user_id, 'eq_active_cart_id', $new_cart_id);
    
    return $new_cart_id;
}
	
private function get_cart_items_count() {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Obtener el carrito activo del usuario actual
    $cart_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}eq_carts 
        WHERE user_id = %d AND status = 'active'
        ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));
    
    // Si no hay carrito, devolver 0
    if (!$cart_id) {
        return 0;
    }
    
    // Contar solo los items del carrito actual
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}eq_cart_items 
        WHERE cart_id = %d AND status = 'active'",
        $cart_id
    ));
}
	
	/**
 * Verifica si el panel de contexto está activo
 * @return bool
 */
private function is_context_panel_active() {
    if (!is_user_logged_in()) {
        return false;
    }
    
    // Verificar si el usuario tiene permisos
    if (!current_user_can('administrator') && !current_user_can('ejecutivo_de_ventas')) {
        return false;
    }
    
    // Verificar si hay contexto activo en la sesión
    if (isset($_SESSION['eq_quote_context']) && 
        !empty($_SESSION['eq_quote_context']['lead_id']) && 
        !empty($_SESSION['eq_quote_context']['event_id'])) {
        return true;
    }
    
    // Verificar si hay lead_id y event_id en el carrito activo
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Verificar primero si hay un carrito activo en user meta
    $active_cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
    
    if ($active_cart_id) {
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT lead_id, event_id FROM {$wpdb->prefix}eq_carts 
            WHERE id = %d AND status = 'active'",
            $active_cart_id
        ));
    } else {
        // Si no hay carrito específico en meta, buscar cualquier carrito activo
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT lead_id, event_id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
    }
    
    return $cart && !empty($cart->lead_id) && !empty($cart->event_id);
}
	
	/**
 * AJAX: Buscar leads
 */
public function search_leads() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
    }
    
    $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
    
    // Crear instancia de la clase de leads
    require_once WP_PLUGIN_DIR . '/leads-management/includes/class-leads-query.php';
    $leads_query = new LTB_Leads_Query();
    
    // Buscar leads
    $args = array();
    if ($term) {
        $args['search'] = $term;
    }
    
    try {
        $leads = $leads_query->get_leads($args);
        
        wp_send_json_success(array(
            'leads' => $leads
        ));
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

	/**
 * AJAX: Crear lead
 */
public function create_lead() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
    }
    
    $nombre = isset($_POST['nombre']) ? sanitize_text_field($_POST['nombre']) : '';
    $apellido = isset($_POST['apellido']) ? sanitize_text_field($_POST['apellido']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $celular = isset($_POST['celular']) ? sanitize_text_field($_POST['celular']) : '';
    
    if (!$nombre || !$apellido) {
        wp_send_json_error('Nombre y apellido son obligatorios');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'jet_cct_leads';
    
    // Preparar datos
    $data = array(
        'lead_nombre' => $nombre,
        'lead_apellido' => $apellido,
        'lead_e_mail' => $email,
        'lead_celular' => $celular,
        'cct_status' => 'publish',
        'cct_created' => current_time('mysql'),
        'cct_modified' => current_time('mysql')
    );
    
    // Insertar lead
    $result = $wpdb->insert(
        $table_name,
        $data,
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        wp_send_json_error('Error al crear lead: ' . $wpdb->last_error);
    }
    
    $lead_id = $wpdb->insert_id;
    
    wp_send_json_success(array(
        'lead_id' => $lead_id,
        'message' => 'Lead creado correctamente'
    ));
}

/**
 * AJAX: Obtener eventos de un lead
 */
public function get_lead_events() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    
    if (!$lead_id) {
        wp_send_json_error('ID de lead inválido');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'jet_cct_eventos';
    
   // Obtener eventos del lead
$eventos = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        _ID as evento_id,
        evento_status,
        fecha_de_evento,
        tipo_de_evento,
        evento_asistentes,
        evento_servicio_de_interes
    FROM $table_name
    WHERE lead_id = %d
    AND cct_status = 'publish'
    ORDER BY fecha_de_evento DESC",
    $lead_id
));

// Formatear fechas para cada evento
foreach ($eventos as $evento) {
    if (is_numeric($evento->fecha_de_evento)) {
        $evento->fecha_formateada = date_i18n(get_option('date_format'), $evento->fecha_de_evento);
    }
}
    
    wp_send_json_success(array(
        'eventos' => $eventos
    ));
}

public function create_event() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $tipo_evento = isset($_POST['tipo_evento']) ? sanitize_text_field($_POST['tipo_evento']) : '';
    $fecha_evento = isset($_POST['fecha_de_evento']) ? sanitize_text_field($_POST['fecha_de_evento']) : '';
    $asistentes = isset($_POST['asistentes']) ? intval($_POST['asistentes']) : 0;
    
    // Campos adicionales
    $status = isset($_POST['evento_status']) ? sanitize_text_field($_POST['evento_status']) : 'nuevo';
    $ubicacion = isset($_POST['ubicacion_evento']) ? sanitize_text_field($_POST['ubicacion_evento']) : '';
    $categoria = isset($_POST['categoria_listing_post']) ? sanitize_text_field($_POST['categoria_listing_post']) : '';
    $direccion = isset($_POST['direccion_evento']) ? sanitize_text_field($_POST['direccion_evento']) : '';
    $comentarios = isset($_POST['comentarios_evento']) ? sanitize_textarea_field($_POST['comentarios_evento']) : '';
    
    if (!$lead_id || !$fecha_evento || !$tipo_evento) {
        wp_send_json_error('Faltan datos obligatorios');
        return;
    }
    
    // IMPORTANTE: Convertir fecha a timestamp
    $fecha_timestamp = strtotime($fecha_evento);
    if ($fecha_timestamp === false) {
        $fecha_timestamp = time(); // Usar timestamp actual como fallback
    }
    
    global $wpdb;
    
    // Preparar datos del evento
    $evento_data = array(
        'lead_id' => $lead_id,
        'fecha_de_evento' => $fecha_timestamp, // Guardar como timestamp
        'tipo_de_evento' => $tipo_evento,
        'evento_asistentes' => $asistentes,
        'evento_status' => $status,
        'ubicacion_evento' => $ubicacion,
        'categoria_listing_post' => $categoria,
        'direccion_evento' => $direccion,
        'comentarios_evento' => $comentarios,
        'cct_status' => 'publish',
        'cct_created' => current_time('mysql'),
        'cct_modified' => current_time('mysql')
    );
    
    // Insertar evento
    $result = $wpdb->insert(
        $wpdb->prefix . 'jet_cct_eventos',
        $evento_data,
        array('%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
    
    if (!$result) {
        wp_send_json_error('Error al crear evento: ' . $wpdb->last_error);
        return;
    }
    
    $evento_id = $wpdb->insert_id;
    
    wp_send_json_success(array(
        'evento_id' => $evento_id,
        'message' => 'Evento creado correctamente'
    ));
}

	
}