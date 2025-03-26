<?php
/**
* Handler para la página del carrito de cotizaciones
*/

defined('ABSPATH') || exit;

class Event_Quote_Cart_Page_Handler {
   private $ajax_handler;

   public function __construct() {
       $this->ajax_handler = new Event_Quote_Cart_Ajax_Handler();
       $this->init_hooks();
   }

   public function init_hooks() {
       add_filter('template_include', array($this, 'load_quote_template'));
       add_shortcode('quote_cart_page', array($this, 'render_quote_page'));
   }

   public function load_quote_template($template) {
    $quote_page_id = get_option('eq_quote_page_id');
    
    // Solo aplicar si el ID existe y estamos en esa página específica
    if ($quote_page_id && is_page($quote_page_id)) {
        $new_template = plugin_dir_path(EQ_CART_PLUGIN_FILE) . 'templates/quote-cart-page.php';
        if (file_exists($new_template)) {
            return $new_template;
        }
    }
    return $template;
}

  public function render_quote_page() {
    // Obtener contexto activo
    $context = $this->get_active_context();
    
    // Log para depuración
    if ($context) {
        error_log('render_quote_page: Active context found - Lead: ' . 
            (isset($context['lead']->lead_nombre) ? $context['lead']->lead_nombre . ' ' . $context['lead']->lead_apellido : 'unknown') . 
            ', Event: ' . (isset($context['event']->tipo_de_evento) ? $context['event']->tipo_de_evento : 'unknown'));
    } else {
        error_log('render_quote_page: No active context found');
    }
    
    // Verificar si el usuario debe ver el context panel
    $user = wp_get_current_user();
    $is_privileged = in_array('administrator', $user->roles) || in_array('ejecutivo_de_ventas', $user->roles);
    
    // Para usuarios privilegiados sin contexto, verificar si hay sesión en la BD
    if (!$context && $is_privileged) {
        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_context_sessions 
            WHERE user_id = %d
            LIMIT 1",
            get_current_user_id()
        ));
        
        if ($session) {
            error_log('render_quote_page: Session found in DB but not loaded in context. Deleting orphaned session.');
            $wpdb->delete(
                $wpdb->prefix . 'eq_context_sessions',
                array('id' => $session->id),
                array('%d')
            );
        }
    }
    
    // Obtener items del carrito
    $cart_items = $this->get_cart_items();
    
    // Log para depuración
    error_log('render_quote_page: Number of cart items returned: ' . count($cart_items));
    
    // Calcular totales
    $totals = $this->calculate_totals($cart_items);
    
    ob_start();
    
    // Incluir template con los datos
    include EQ_CART_PLUGIN_DIR . 'templates/quote-cart-page.php';
    
    return ob_get_clean();
}

 private function get_cart_items() {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // PASO 1: Verificar si hay un contexto activo en la sesión PHP
    $context_lead_id = null;
    $context_event_id = null;
    $context_user_id = null;
    
    if (isset($_SESSION['eq_quote_context'])) {
        $context_lead_id = isset($_SESSION['eq_quote_context']['lead_id']) ? 
            intval($_SESSION['eq_quote_context']['lead_id']) : null;
        $context_event_id = isset($_SESSION['eq_quote_context']['event_id']) ? 
            intval($_SESSION['eq_quote_context']['event_id']) : null;
        $context_user_id = isset($_SESSION['eq_quote_context']['user_id']) ? 
            intval($_SESSION['eq_quote_context']['user_id']) : null;
        
        // Verificar que el contexto pertenezca al usuario actual
        if ($context_user_id !== null && $context_user_id !== $user_id) {
            error_log('get_cart_items: Context in session belongs to different user, ignoring. Session user: ' . 
                $context_user_id . ', Current user: ' . $user_id);
            $context_lead_id = null;
            $context_event_id = null;
        } else if ($context_lead_id && $context_event_id) {
            error_log('get_cart_items: Using context from session - lead_id: ' . 
                $context_lead_id . ', event_id: ' . $context_event_id);
        }
    }
    
    // PASO 2: Si tenemos contexto, buscar un carrito específico para este lead/evento
    if ($context_lead_id && $context_event_id) {
        $context_cart_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id, $context_lead_id, $context_event_id
        ));
        
        if ($context_cart_id) {
            error_log('get_cart_items: Found cart ID ' . $context_cart_id . 
                ' for context lead_id: ' . $context_lead_id . ', event_id: ' . $context_event_id);
                
            // Actualizar meta de usuario para consistencia
            update_user_meta($user_id, 'eq_active_cart_id', $context_cart_id);
            
            // Usar este carrito específico
            $active_cart_id = $context_cart_id;
        } else {
            error_log('get_cart_items: No cart found for current context');
            
            // Si no existe carrito para este contexto, no mostrar nada
            return array();
        }
    } 
    // PASO 3: Si no hay contexto, usar el carrito marcado como activo o el más reciente
    else {
        // Verificar primero si hay un carrito activo en user meta
        $active_cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
        
        // Verificar que el carrito exista y esté activo
        if ($active_cart_id) {
            $cart_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eq_carts 
                WHERE id = %d AND user_id = %d AND status = 'active'",
                $active_cart_id, $user_id
            ));
            
            if (!$cart_exists) {
                // Si el carrito no existe o no está activo, limpiar meta
                delete_user_meta($user_id, 'eq_active_cart_id');
                $active_cart_id = null;
            }
        }
        
        // Si no hay carrito activo válido, buscar el más reciente
        if (!$active_cart_id) {
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
        
        // Si aún no hay carrito, no hay items
        if (!$active_cart_id) {
            return array();
        }
    }

    // Log para depuración - muestra el ID del carrito activo
    error_log('Active cart ID in get_cart_items: ' . $active_cart_id);

    // Obtener items del carrito activo
    $items_query = $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_cart_items
        WHERE cart_id = %d AND status = 'active'
        ORDER BY created_at DESC",
        $active_cart_id
    );
    
    // Log para depuración - muestra la consulta
    error_log('Items query: ' . $items_query);
    
    $items = $wpdb->get_results($items_query);
    
    // Log para depuración - muestra número de items encontrados
    error_log('Number of items found: ' . count($items));

    // Procesar cada item
    $processed_items = array();
    foreach ($items as $item) {
        try {
            $listing = get_post($item->listing_id);
            if (!$listing) {
                error_log('Listing not found: ' . $item->listing_id);
                continue;
            }
            
            $form_data = json_decode($item->form_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON error decoding form_data: ' . json_last_error_msg());
                error_log('Raw form_data: ' . $item->form_data);
                continue;
            }

            $processed_item = new stdClass();
            $processed_item->id = $item->id;
            $processed_item->listing_id = $item->listing_id;
            $processed_item->title = get_the_title($listing);
            $processed_item->image = get_the_post_thumbnail_url($listing->ID, 'thumbnail');
            $processed_item->date = isset($form_data['date']) ? $form_data['date'] : '';
            $processed_item->quantity = isset($form_data['quantity']) ? $form_data['quantity'] : 1;
            $processed_item->extras = isset($form_data['extras']) ? $form_data['extras'] : array();
            $processed_item->total_price = isset($form_data['total_price']) ? $form_data['total_price'] : 0;
            $processed_item->price_formatted = hivepress()->woocommerce->format_price($processed_item->total_price);
            
            $processed_items[] = $processed_item;
        } catch (Exception $e) {
            error_log('Error processing item #' . $item->id . ': ' . $e->getMessage());
        }
    }

    // Log para depuración - muestra número de items procesados
    error_log('Number of processed items: ' . count($processed_items));
    
    return $processed_items;
}

  private function calculate_totals($items) {
    $total = 0;
    
    foreach ($items as $item) {
        $total += floatval($item->total_price);
    }

    // Calcular el subtotal y los impuestos basados en el total
    $tax_rate = floatval(get_option('eq_tax_rate', 16));
    $subtotal = $total / (1 + ($tax_rate / 100));
    $tax = $total - $subtotal;

    return array(
        'subtotal' => hivepress()->woocommerce->format_price($subtotal),
        'taxes' => hivepress()->woocommerce->format_price($tax),
        'total' => hivepress()->woocommerce->format_price($total)
    );
}

	private function get_active_context() {
    return eq_get_active_context();
}
	
}