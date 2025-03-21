<?php
/**
 * Handler para la vista detallada de cotización
 */

defined('ABSPATH') || exit;

class Event_Quote_Cart_Quote_View_Handler {
    private $ajax_handler;

    public function __construct() {
        $this->ajax_handler = new Event_Quote_Cart_Ajax_Handler();
        $this->init_hooks();
    }

    public function init_hooks() {
        add_action('init', array($this, 'register_quote_view_page'));
        add_filter('template_include', array($this, 'load_quote_template'));
        add_shortcode('quote_view_page', array($this, 'render_quote_view'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function register_quote_view_page() {
        // Registrar página de vista de cotización si no existe
        if (!get_option('eq_quote_view_page_id')) {
            $page_data = array(
                'post_title'    => 'Quote View',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_content'  => '[quote_view_page]'
            );
            $page_id = wp_insert_post($page_data);
            update_option('eq_quote_view_page_id', $page_id);
        }
    }

    public function load_quote_template($template) {
        if (is_page(get_option('eq_quote_view_page_id'))) {
            $new_template = plugin_dir_path(EQ_CART_PLUGIN_FILE) . 'templates/quote-view.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    public function enqueue_scripts() {
    if (is_page(get_option('eq_quote_view_page_id'))) {
        wp_enqueue_style(
            'eq-quote-view',
            plugins_url('public/css/quote-view.css', EQ_CART_PLUGIN_FILE),
            array(),
            EQ_CART_VERSION
        );

        wp_enqueue_script(
            'eq-quote-view',
            plugins_url('public/js/quote-view.js', EQ_CART_PLUGIN_FILE),
            array('jquery'),
            EQ_CART_VERSION,
            true
        );

        // Agregar Font Awesome para los iconos
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css'
        );
    }
}

    public function render_quote_view() {
        // Verificar si el usuario está logueado
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your quote.</p>';
        }

        // Obtener datos necesarios
        $cart_items = $this->get_cart_items();
        $totals = $this->calculate_totals($cart_items);
        $quote_data = $this->get_quote_data();

        ob_start();
        include plugin_dir_path(EQ_CART_PLUGIN_FILE) . 'templates/quote-view.php';
        return ob_get_clean();
    }

    private function get_cart_items() {
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
            return array();
        }

        // Obtener items
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_cart_items 
            WHERE cart_id = %d AND status = 'active'
            ORDER BY created_at DESC",
            $cart_id
        ));

        // Procesar cada item
        foreach ($items as &$item) {
            $listing = get_post($item->listing_id);
            $form_data = json_decode($item->form_data, true);
            
            $item->title = get_the_title($listing);
            $item->description = get_post_field('post_content', $listing->ID);
            $item->image = get_the_post_thumbnail_url($listing->ID, 'thumbnail');
            $item->form_data = $form_data;
        }

        return $items;
    }

    private function calculate_totals($items) {
        $subtotal = 0;
        
        foreach ($items as $item) {
            $form_data = $item->form_data;
            $subtotal += floatval($form_data['total_price']);
        }

        $tax_rate = floatval(get_option('eq_tax_rate', 16));
        $taxes = $subtotal * ($tax_rate / 100);
        $total = $subtotal + $taxes;

        return array(
            'subtotal' => hivepress()->woocommerce->format_price($subtotal),
            'taxes' => hivepress()->woocommerce->format_price($taxes),
            'total' => hivepress()->woocommerce->format_price($total)
        );
    }

    private function get_quote_data() {
        // Por ahora retornamos datos estáticos
        // TODO: Implementar sistema para guardar estos datos
        return array(
            'employee_name' => 'Nombre del Vendedor',
            'event_date' => '',
            'event_city' => '',
            'client_name' => wp_get_current_user()->display_name
        );
    }

    // Método para actualizar datos de la cotización
    public function update_quote_data($data) {
        // TODO: Implementar sistema para guardar estos datos
        return true;
    }
}