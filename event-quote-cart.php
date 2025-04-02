<?php
/**
 * Plugin Name: Event Quote Cart
 * Plugin URI: 
 * Description: A custom quote cart system for event services
 * Version: 2.0.0
 * Author: Miguel Tolentino
 * Author URI: 
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: event-quote-cart
 * Domain Path: /languages
 */

if (!defined('WPINC')) {
    die;
}

define('EQ_CART_VERSION', '1.0.0');

define('EQ_CART_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EQ_CART_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EQ_CART_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('EQ_CART_PLUGIN_FILE', __FILE__);

$composer_autoload = EQ_CART_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

define('DOMPDF_ENABLE_REMOTE', true);

function activate_event_quote_cart() {
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-activator.php';
    Event_Quote_Cart_Activator::activate();
}

function deactivate_event_quote_cart() {
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-deactivator.php';
    Event_Quote_Cart_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_event_quote_cart');
register_deactivation_hook(__FILE__, 'deactivate_event_quote_cart');

function eq_cart_check_requirements() {
    $required_plugins = array(
        'woocommerce/woocommerce.php' => 'WooCommerce',
        'hivepress/hivepress.php' => 'HivePress',
        'cotizador-eventos/cotizador-eventos.php' => 'Cotizador Eventos'
    );

    $missing_plugins = array();

    foreach ($required_plugins as $plugin => $name) {
        if (!is_plugin_active($plugin)) {
            $missing_plugins[] = $name;
        }
    }

    if (!empty($missing_plugins)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                esc_html__('Event Quote Cart requires the following plugins: %s. Please install and activate them first.', 'event-quote-cart'),
                implode(', ', $missing_plugins)
            )
        );
    }
}
add_action('admin_init', 'eq_cart_check_requirements');

function eq_can_view_quote_button() {
    if (!is_user_logged_in()) {
        return false;
    }

    $user = wp_get_current_user();
    return (
        in_array('administrator', $user->roles) || 
        in_array('ejecutivo_de_ventas', $user->roles)
    );
}

function eq_can_use_leads_integration() {
    if (!is_user_logged_in()) {
        return false;
    }
    $user = wp_get_current_user();
    return (
        in_array('administrator', $user->roles) || 
        in_array('ejecutivo_de_ventas', $user->roles)
    );
}

function init_event_quote_cart() {
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-loader.php';
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-ajax-handler.php';
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-date-handler.php';
	require_once EQ_CART_PLUGIN_DIR . 'includes/class-single-handler.php';
	require_once EQ_CART_PLUGIN_DIR . 'includes/helpers.php';
	require_once EQ_CART_PLUGIN_DIR . 'includes/class-pdf-handler.php';

	new Event_Quote_Cart_Single_Handler();

    require_once EQ_CART_PLUGIN_DIR . 'includes/class-cart-page-handler.php';
    new Event_Quote_Cart_Page_Handler();

    require_once EQ_CART_PLUGIN_DIR . 'includes/class-quote-view-handler.php';
    new Event_Quote_Cart_Quote_View_Handler();
    
    $plugin = new Event_Quote_Cart_Loader();
    new Event_Quote_Cart_Ajax_Handler();
    
    $plugin->run();

    // Inicializar Stripe si está habilitado
    if (get_option('eq_stripe_enabled') === 'yes') {
        new Event_Quote_Cart_Stripe_Handler();
    }
    
    add_action('wp_enqueue_scripts', 'eq_cart_enqueue_scripts');
}

function eq_cart_enqueue_scripts() {
    wp_enqueue_style(
        'event-quote-cart',
        EQ_CART_PLUGIN_URL . 'public/css/sidebar.css',
        array(),
        EQ_CART_VERSION
    );
	
    $current_url = $_SERVER['REQUEST_URI']; 
    
    if (strpos($current_url, 'quote-cart') !== false) {
        wp_enqueue_style(
            'event-quote-cart-page',
            EQ_CART_PLUGIN_URL . 'public/css/quote-cart-page.css',
            array('event-quote-cart'),
            EQ_CART_VERSION
        );
        
        wp_enqueue_script(
            'event-quote-cart-page',
            EQ_CART_PLUGIN_URL . 'public/js/quote-cart-page.js',
            array('jquery'),
            EQ_CART_VERSION,
            true
        );
        
        wp_localize_script(
            'event-quote-cart-page',
            'eqCartData',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('eq_cart_public_nonce'),
                'quoteViewUrl' => home_url('/quote-view/')
            )
        );
    }
	
    wp_enqueue_script(
        'event-quote-cart',
        EQ_CART_PLUGIN_URL . 'public/js/quote-cart.js',
        array('jquery'),
        EQ_CART_VERSION,
        true
    );
	
    global $wpdb;
    $tax_rate = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d",
            1
        )
    );

wp_localize_script(
    'event-quote-cart',
    'eqCartData',
    array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('eq_cart_public_nonce'),
        'cartUrl' => get_permalink(get_option('eq_cart_page_id')),
        'taxRate' => get_option('eq_cart_tax_rate', 0),
        'currency' => get_woocommerce_currency_symbol(),
        'thousand_sep' => wc_get_price_thousand_separator(),
        'decimal_sep' => wc_get_price_decimal_separator(),
        'num_decimals' => wc_get_price_decimals(),
        'i18n' => eq_get_js_translations('main')
    )
);

wp_localize_script(
    'event-quote-cart-single',
    'eqSingleData',
    array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('eq_cart_public_nonce'),
        'i18n' => eq_get_js_translations('single')
    )
);

}
add_action('plugins_loaded', 'init_event_quote_cart');


add_action('eq_cart_add_quote_to_booking_form', 'eq_render_booking_form_quote_button');

function eq_render_booking_form_quote_button($listing_id) {
    ?>
    <button type="button" 
            class="eq-quote-button boton-cotizar" 
            data-listing-id="<?php echo esc_attr($listing_id); ?>">
        + Quote
    </button>
    <?php
}

add_action('wp_logout', 'eq_clear_context_on_logout');

function eq_clear_context_on_logout() {
    $user_id = get_current_user_id();
    
    delete_user_meta($user_id, 'eq_active_cart_id');
    
    if (isset($_SESSION['eq_quote_context'])) {
        unset($_SESSION['eq_quote_context']);
    }
    
    global $wpdb;
    $result = $wpdb->delete(
        $wpdb->prefix . 'eq_context_sessions',
        array('user_id' => $user_id),
        array('%d')
    );
    
    if (isset($_COOKIE['eq_session_ended'])) {
        setcookie('eq_session_ended', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
    
    ?>
    <script>
    try {
        localStorage.removeItem('eq_context_session_force_clear');
        localStorage.removeItem('eq_context_session_ended');
        localStorage.removeItem('eq_date_source');
        localStorage.removeItem('eq_panel_selected_date');
        sessionStorage.removeItem('eqQuoteContext');
    } catch(e) {
        console.error('Error clearing local storage:', e);
    }
    </script>
    <?php
}

add_action('init', 'eq_sync_context_session', 5); 

function eq_sync_context_session() {

    if (!is_user_logged_in()) {
        return;
    }
	
if (isset($_COOKIE['eq_session_ended']) && $_COOKIE['eq_session_ended'] === 'true') {
    
    if (isset($_SESSION['eq_quote_context'])) {
        unset($_SESSION['eq_quote_context']);
    }
    
    $_SESSION['eq_context_no_restore'] = true;
    
    return;
}
    
    $user_id = get_current_user_id();
    
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    if (isset($_SESSION['eq_context_no_restore']) && $_SESSION['eq_context_no_restore'] === true) {
        return;
    }
    
    global $wpdb;
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_context_sessions 
        WHERE user_id = %d
        LIMIT 1",
        $user_id
    ));
    
    if (!$session) {
        
        if (isset($_SESSION['eq_quote_context'])) {
            unset($_SESSION['eq_quote_context']);
        }
        
        return;
    }
    
    $lead_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_leads WHERE _ID = %d",
        $session->lead_id
    ));
    
    $event_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
        $session->event_id
    ));
    
    if (!$lead_exists || !$event_exists) {
        
        $wpdb->delete(
            $wpdb->prefix . 'eq_context_sessions',
            array('id' => $session->id),
            array('%d')
        );
        
        if (isset($_SESSION['eq_quote_context'])) {
            unset($_SESSION['eq_quote_context']);
        }
        
        return;
    }
    
    $needs_update = false;
    
    if (!isset($_SESSION['eq_quote_context']) || 
        !isset($_SESSION['eq_quote_context']['lead_id']) || 
        !isset($_SESSION['eq_quote_context']['event_id']) ||
        $_SESSION['eq_quote_context']['lead_id'] != $session->lead_id || 
        $_SESSION['eq_quote_context']['event_id'] != $session->event_id) {
        
        $needs_update = true;
    }
    
    if ($needs_update) {
        
        $_SESSION['eq_quote_context'] = array(
            'lead_id' => $session->lead_id,
            'event_id' => $session->event_id,
            'user_id' => $user_id,
            'timestamp' => time(),
            'session_token' => $session->session_token
        );
        
        $cart_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id, $session->lead_id, $session->event_id
        ));
        
        if ($cart_id) {
            update_user_meta($user_id, 'eq_active_cart_id', $cart_id);
        }
    } else {
    }
}

add_action('plugins_loaded', 'eq_update_sessions_table');

function eq_update_sessions_table() {

    $current_version = get_option('eq_sessions_table_version', '0.0');
    if (version_compare($current_version, '1.1', '>=')) {
        return;
    }
    
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'eq_context_sessions';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

        update_option('eq_sessions_table_version', '1.1');
        return;
    }
    
    $status_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'status'");
    if ($status_exists) {

        $wpdb->query("DELETE FROM $table_name WHERE status = 'inactive'");
        
        $wpdb->query("
            CREATE TEMPORARY TABLE temp_sessions AS
            SELECT MAX(id) as max_id, user_id
            FROM $table_name
            GROUP BY user_id
        ");
        
        $wpdb->query("
            DELETE s FROM $table_name s
            LEFT JOIN temp_sessions t ON s.id = t.max_id
            WHERE t.max_id IS NULL
        ");
        
        $wpdb->query("DROP TEMPORARY TABLE IF EXISTS temp_sessions");
        
        $wpdb->query("ALTER TABLE $table_name DROP COLUMN status");
    }
    
    $unique_exists = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'user_id' AND Non_unique = 0");
    if (empty($unique_exists)) {
        try {

            $wpdb->query("ALTER TABLE $table_name ADD UNIQUE INDEX user_id (user_id)");
        } catch (Exception $e) {
            error_log('Error adding UNIQUE constraint: ' . $e->getMessage());
        }
    }
    
    update_option('eq_sessions_table_version', '1.1');
}

add_action('wp_login', 'eq_clear_session_cookies');

function eq_clear_session_cookies() {
    if (isset($_COOKIE['eq_session_ended'])) {
        setcookie('eq_session_ended', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
}