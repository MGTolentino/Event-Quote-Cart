<?php
/**
 * Plugin Name: Event Quote Cart
 * Plugin URI: 
 * Description: A custom quote cart system for event services
 * Version: 1.0.0
 * Author: Miguel Tolentino
 * Author URI: 
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: event-quote-cart
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin version
define('EQ_CART_VERSION', '1.0.0');

// Plugin Paths
define('EQ_CART_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EQ_CART_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EQ_CART_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('EQ_CART_PLUGIN_FILE', __FILE__);

// Cargar Composer autoload si existe
$composer_autoload = EQ_CART_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

// Definir constante para usar con DOMPDF
define('DOMPDF_ENABLE_REMOTE', true);

/**
 * Activation function
 */
function activate_event_quote_cart() {
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-activator.php';
    Event_Quote_Cart_Activator::activate();
}

/**
 * Deactivation function
 */
function deactivate_event_quote_cart() {
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-deactivator.php';
    Event_Quote_Cart_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_event_quote_cart');
register_deactivation_hook(__FILE__, 'deactivate_event_quote_cart');

/**
 * Check required plugins
 */
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


/**
 * Permission check function
 */
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

/**
 * Verifica si el usuario puede usar la integración con leads/eventos
 * Restringido a administradores y ejecutivos de ventas
 */
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

/**
 * Initialize plugin
 */
function init_event_quote_cart() {
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-loader.php';
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-ajax-handler.php';
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-date-handler.php';
	require_once EQ_CART_PLUGIN_DIR . 'includes/class-single-handler.php';
	require_once EQ_CART_PLUGIN_DIR . 'includes/helpers.php';
	require_once EQ_CART_PLUGIN_DIR . 'includes/class-pdf-handler.php';

	new Event_Quote_Cart_Single_Handler();
	 // Cargar el manejador de la página del carrito
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-cart-page-handler.php';
    new Event_Quote_Cart_Page_Handler();
	// Cargar el manejador de la vista de cotización
    require_once EQ_CART_PLUGIN_DIR . 'includes/class-quote-view-handler.php';
    new Event_Quote_Cart_Quote_View_Handler();
    
    $plugin = new Event_Quote_Cart_Loader();
    new Event_Quote_Cart_Ajax_Handler();
    
    $plugin->run();
    
    // Enqueue scripts y estilos
    add_action('wp_enqueue_scripts', 'eq_cart_enqueue_scripts');
}

function eq_cart_enqueue_scripts() {
    // CSS
    wp_enqueue_style(
        'event-quote-cart',
        EQ_CART_PLUGIN_URL . 'public/css/sidebar.css',
        array(),
        EQ_CART_VERSION
    );
	
	   // Cargar CSS de página de carrito basado en la URL actual
    $current_url = $_SERVER['REQUEST_URI']; // Obtiene la URL actual
    
    // Si la URL contiene 'quote-cart'
    if (strpos($current_url, 'quote-cart') !== false) {
        wp_enqueue_style(
            'event-quote-cart-page',
            EQ_CART_PLUGIN_URL . 'public/css/quote-cart-page.css',
            array('event-quote-cart'),
            EQ_CART_VERSION
        );
        
        // También cargar el JavaScript correspondiente
        wp_enqueue_script(
            'event-quote-cart-page',
            EQ_CART_PLUGIN_URL . 'public/js/quote-cart-page.js',
            array('jquery'),
            EQ_CART_VERSION,
            true
        );
        
        // Pasar datos al JavaScript
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
	

    // JavaScript
    wp_enqueue_script(
        'event-quote-cart',
        EQ_CART_PLUGIN_URL . 'public/js/quote-cart.js',
        array('jquery'),
        EQ_CART_VERSION,
        true
    );
	
	// Obtener tax rate
    global $wpdb;
    $tax_rate = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d",
            1
        )
    );

    // Localizar script
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
        'i18n' => array(
            'addedToCart' => __('Added to quote cart', 'event-quote-cart'),
            'errorAdding' => __('Error adding to quote cart', 'event-quote-cart'),
            'removedFromCart' => __('Removed from quote cart', 'event-quote-cart'),
            'errorRemoving' => __('Error removing from quote cart', 'event-quote-cart'),
            'dateRequired' => __('Please select a date', 'event-quote-cart'),
            'dateNotAvailable' => __('Selected date is not available', 'event-quote-cart'),
            'dateAvailable' => __('Date is available', 'event-quote-cart'),
            'errorValidating' => __('Error validating date', 'event-quote-cart'),
            'adding' => __('Adding...', 'event-quote-cart'),
            'updating' => __('Updating...', 'event-quote-cart'),
            'checking' => __('Checking availability...', 'event-quote-cart'),
            'selectDate' => __('Select date', 'event-quote-cart'),
            'editDate' => __('Edit date', 'event-quote-cart'),
            'confirmRemove' => __('Are you sure you want to remove this item?', 'event-quote-cart'),
            'dateConflict' => __('This date conflicts with another booking', 'event-quote-cart'),
            'quantityRequired' => __('Please enter a valid quantity', 'event-quote-cart'),
            'invalidQuantity' => __('Quantity must be between %d and %d', 'event-quote-cart'),
            'dateOutOfRange' => __('Selected date is outside the allowed booking window', 'event-quote-cart'),
            'loadingError' => __('Error loading data', 'event-quote-cart')
        )
    )
);

    // Localizar script de integración single
    wp_localize_script(
        'event-quote-cart-single',
        'eqSingleData',
        array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eq_cart_public_nonce'),
            'i18n' => array(
                'checkingAvailability' => __('Checking availability...', 'event-quote-cart'),
                'dateAvailable' => __('This date is available', 'event-quote-cart'),
                'dateNotAvailable' => __('This date is not available', 'event-quote-cart'),
                'errorChecking' => __('Error checking availability', 'event-quote-cart')
            )
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

// Agregar este código junto a otros add_action
add_action('wp_logout', 'eq_clear_context_on_logout');

/**
 * Limpiar cualquier sesión de contexto cuando el usuario cierra sesión de WordPress
 */
function eq_clear_context_on_logout() {
    $user_id = get_current_user_id();
    
    // Limpiar carrito activo en user meta
    delete_user_meta($user_id, 'eq_active_cart_id');
    
    // Limpiar contexto en sesión
    if (isset($_SESSION['eq_quote_context'])) {
        unset($_SESSION['eq_quote_context']);
    }
    
    // Eliminar la entrada de sesión en la BD con verificación de errores
    global $wpdb;
    $result = $wpdb->delete(
        $wpdb->prefix . 'eq_context_sessions',
        array('user_id' => $user_id),
        array('%d')
    );
    
    // Verificar si hubo algún error
    if ($result === false) {
        error_log('wp_logout: Error deleting context session - ' . $wpdb->last_error);
    } else {
        error_log('wp_logout: Context cleared for user ' . $user_id . ' - Rows affected: ' . $result);
    }
    
    // Limpiar cookies relacionadas
    if (isset($_COOKIE['eq_session_ended'])) {
        setcookie('eq_session_ended', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
    
    // Limpiar localStorage mediante JavaScript
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

// Agregar este código junto a otros add_action
add_action('init', 'eq_sync_context_session', 5); // Prioridad 5 para ejecutar temprano


/**
 * Sincroniza la sesión PHP con la información de la tabla eq_context_sessions
 */
function eq_sync_context_session() {
    // Solo para usuarios logueados
    if (!is_user_logged_in()) {
        return;
    }
	
	// Verificar si hay cookie de sesión finalizada
if (isset($_COOKIE['eq_session_ended']) && $_COOKIE['eq_session_ended'] === 'true') {
    error_log('eq_sync_context_session: Session ended cookie found, skipping sync');
    
    // Asegurar que la sesión PHP esté limpia
    if (isset($_SESSION['eq_quote_context'])) {
        unset($_SESSION['eq_quote_context']);
    }
    
    // Establecer flag de no restaurar
    $_SESSION['eq_context_no_restore'] = true;
    
    return;
}
    
    $user_id = get_current_user_id();
    
    // Verificar si se inició la sesión PHP
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // PASO 1: Verificar si existe una señal de "no restaurar"
    if (isset($_SESSION['eq_context_no_restore']) && $_SESSION['eq_context_no_restore'] === true) {
        error_log('eq_sync_context_session: No-restore flag found, skipping session restoration');
        return;
    }
    
    // PASO 2: Buscar sesión activa en la base de datos
    global $wpdb;
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_context_sessions 
        WHERE user_id = %d
        LIMIT 1",
        $user_id
    ));
    
    // PASO 3: Si no hay sesión en la BD, limpiar también la sesión PHP (sincronizar estados)
    if (!$session) {
        error_log('eq_sync_context_session: No session in DB for user ' . $user_id);
        
        // Si hay datos en la sesión PHP pero no en la BD, limpiarlos para sincronizar
        if (isset($_SESSION['eq_quote_context'])) {
            error_log('eq_sync_context_session: Session found in PHP but not in DB, clearing PHP session');
            unset($_SESSION['eq_quote_context']);
        }
        
        return;
    }
    
    // PASO 4: Si hay sesión en la BD, verificar que los IDs sean válidos
    $lead_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_leads WHERE _ID = %d",
        $session->lead_id
    ));
    
    $event_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
        $session->event_id
    ));
    
    // PASO 5: Si los IDs no son válidos, eliminar la sesión de la BD
    if (!$lead_exists || !$event_exists) {
        error_log('eq_sync_context_session: Invalid lead/event IDs in DB session, deleting session');
        
        $wpdb->delete(
            $wpdb->prefix . 'eq_context_sessions',
            array('id' => $session->id),
            array('%d')
        );
        
        // Limpiar también la sesión PHP
        if (isset($_SESSION['eq_quote_context'])) {
            unset($_SESSION['eq_quote_context']);
        }
        
        return;
    }
    
    // PASO 6: Si todo es válido, actualizar sesión PHP solo si es necesario
    $needs_update = false;
    
    // Verificar si la sesión PHP existe y contiene los mismos valores
    if (!isset($_SESSION['eq_quote_context']) || 
        !isset($_SESSION['eq_quote_context']['lead_id']) || 
        !isset($_SESSION['eq_quote_context']['event_id']) ||
        $_SESSION['eq_quote_context']['lead_id'] != $session->lead_id || 
        $_SESSION['eq_quote_context']['event_id'] != $session->event_id) {
        
        $needs_update = true;
    }
    
    if ($needs_update) {
        error_log('eq_sync_context_session: Updating PHP session with DB values - lead_id: ' . 
            $session->lead_id . ', event_id: ' . $session->event_id);
        
        $_SESSION['eq_quote_context'] = array(
            'lead_id' => $session->lead_id,
            'event_id' => $session->event_id,
            'user_id' => $user_id,
            'timestamp' => time(),
            'session_token' => $session->session_token
        );
        
        // También actualizar el active_cart_id en user meta para consistencia
        $cart_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eq_carts 
            WHERE user_id = %d AND lead_id = %d AND event_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1",
            $user_id, $session->lead_id, $session->event_id
        ));
        
        if ($cart_id) {
            update_user_meta($user_id, 'eq_active_cart_id', $cart_id);
            error_log('eq_sync_context_session: Updated active_cart_id to ' . $cart_id);
        }
    } else {
        error_log('eq_sync_context_session: PHP session already synchronized with DB');
    }
}

// Ejecutar la actualización de tabla de sesiones al activar el plugin
add_action('plugins_loaded', 'eq_update_sessions_table');

/**
 * Actualiza la estructura de la tabla de sesiones de contexto
 */
function eq_update_sessions_table() {
    // Solo ejecutar una vez verificando una opción
    $current_version = get_option('eq_sessions_table_version', '0.0');
    if (version_compare($current_version, '1.1', '>=')) {
        return;
    }
    
    global $wpdb;
    
    // Verificar si la tabla existe
    $table_name = $wpdb->prefix . 'eq_context_sessions';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // Si la tabla no existe, no hacer nada (se creará con la estructura correcta)
        update_option('eq_sessions_table_version', '1.1');
        return;
    }
    
    // Verificar si la columna status existe
    $status_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'status'");
    if ($status_exists) {
        // 1. Eliminar sesiones inactivas
        $wpdb->query("DELETE FROM $table_name WHERE status = 'inactive'");
        
        // 2. Eliminar duplicados
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
        
        // 3. Eliminar columna status
        $wpdb->query("ALTER TABLE $table_name DROP COLUMN status");
    }
    
    // 4. Verificar si ya existe la restricción UNIQUE en user_id
    $unique_exists = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'user_id' AND Non_unique = 0");
    if (empty($unique_exists)) {
        try {
            // 5. Añadir restricción UNIQUE para user_id
            $wpdb->query("ALTER TABLE $table_name ADD UNIQUE INDEX user_id (user_id)");
        } catch (Exception $e) {
            error_log('Error adding UNIQUE constraint: ' . $e->getMessage());
        }
    }
    
    // Actualizar versión
    update_option('eq_sessions_table_version', '1.1');
    error_log('eq_update_sessions_table: Table updated to version 1.1');
}

// Limpiar cookies de sesión finalizada al iniciar una nueva sesión
add_action('wp_login', 'eq_clear_session_cookies');

/**
 * Limpiar cookies relacionadas con sesiones finalizadas al iniciar sesión
 */
function eq_clear_session_cookies() {
    if (isset($_COOKIE['eq_session_ended'])) {
        setcookie('eq_session_ended', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
}