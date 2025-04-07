<?php
/**
 * Fired during plugin activation
 *
 * @since      1.0.0
 */

class Event_Quote_Cart_Activator {

    /**
     * Activate the plugin.
     *
     * Create necessary database tables and configure initial settings
     *
     * @since    1.0.0
     */
    public static function activate() {
    self::create_tables();
    self::update_tables();
    self::update_cart_table_structure();
    self::create_quotes_table();
    self::create_context_sessions_table(); 
    self::add_capabilities();
    self::set_default_options();
}

    /**
     * Create the necessary database tables.
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table names with prefix
        $carts_table = $wpdb->prefix . 'eq_carts';
        $cart_items_table = $wpdb->prefix . 'eq_cart_items';

        // SQL for carts table
        $sql_carts = "CREATE TABLE IF NOT EXISTS $carts_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            notes text,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // SQL for cart items table
        $sql_items = "CREATE TABLE IF NOT EXISTS $cart_items_table (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				cart_id bigint(20) unsigned NOT NULL,
				listing_id bigint(20) unsigned NOT NULL,
				form_data longtext,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				status varchar(50) NOT NULL DEFAULT 'active',
				order_in_cart int(11) NOT NULL DEFAULT 0,
				PRIMARY KEY (id),
				KEY cart_id (cart_id),
				KEY listing_id (listing_id),
				UNIQUE KEY unique_listing_in_cart (cart_id, listing_id, status) /* Agregar esta línea */
			) $charset_collate;";

             // Tabla de órdenes
    $table_name = $wpdb->prefix . 'eq_orders';
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        cart_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        lead_id bigint(20) DEFAULT NULL,
        event_id bigint(20) DEFAULT NULL,
        status varchar(50) NOT NULL DEFAULT 'pending',
        payment_id varchar(255) DEFAULT NULL,
        payment_method varchar(255) DEFAULT NULL,
        amount decimal(10,2) NOT NULL DEFAULT 0,
        currency varchar(10) NOT NULL DEFAULT 'MXN',
        billing_name varchar(255) DEFAULT NULL,
        billing_email varchar(255) DEFAULT NULL,
        billing_phone varchar(100) DEFAULT NULL,
        created_by bigint(20) DEFAULT NULL,
        checkout_url varchar(512) DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY cart_id (cart_id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";
    
    // Tabla de vendors para órdenes
    $table_name_vendors = $wpdb->prefix . 'eq_order_vendors';
    $sql_vendors = "CREATE TABLE $table_name_vendors (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        vendor_id bigint(20) NOT NULL,
        listing_id bigint(20) NOT NULL,
        amount decimal(10,2) NOT NULL DEFAULT 0,
        status varchar(50) NOT NULL DEFAULT 'pending',
        transfer_id varchar(255) DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY vendor_id (vendor_id)
    ) $charset_collate;";
    
    // Tabla de reservas (bookings)
    $table_name_bookings = $wpdb->prefix . 'eq_bookings';
    $sql_bookings = "CREATE TABLE $table_name_bookings (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        cart_item_id bigint(20) NOT NULL,
        booking_id bigint(20) DEFAULT NULL,
        listing_id bigint(20) NOT NULL,
        vendor_id bigint(20) DEFAULT NULL,
        status varchar(50) NOT NULL DEFAULT 'pending',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY cart_item_id (cart_item_id)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables
        dbDelta($sql);
        dbDelta($sql_vendors);
        dbDelta($sql_bookings);
		
        // Store database version
        add_option('eq_cart_db_version', EQ_CART_VERSION);
    }

    /**
     * Add necessary capabilities to roles.
     */
    private static function add_capabilities() {
        // Get roles
        $admin = get_role('administrator');
        $ventas = get_role('ventas-team');

        // Capabilities to add
        $caps = array(
            'view_eq_cart',
            'edit_eq_cart',
            'delete_eq_cart',
            'create_eq_cart',
            'manage_eq_cart_settings'
        );

        // Add capabilities to administrator
        if ($admin) {
            foreach ($caps as $cap) {
                $admin->add_cap($cap);
            }
        }

        // Add capabilities to ventas-team
        if ($ventas) {
            foreach ($caps as $cap) {
                $ventas->add_cap($cap);
            }
        }
    }

    /**
     * Set default options for the plugin.
     */
    private static function set_default_options() {
        $default_options = array(
            'eq_cart_button_text' => '+ Cotización',
            'eq_cart_button_text_en' => '+ Quote',
            'eq_cart_enabled' => 'yes'
        );

        foreach ($default_options as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
            }
        }
    }
	
	public static function update_tables() {
    global $wpdb;
    
    // Agregar índice único a la tabla de items
    $cart_items_table = $wpdb->prefix . 'eq_cart_items';
    
    // Primero verificar si el índice ya existe
    $check_index = $wpdb->get_results("SHOW INDEX FROM $cart_items_table WHERE Key_name = 'unique_listing_in_cart'");
    
    if (empty($check_index)) {
        // Si hay duplicados, actualizar su status a 'removed' excepto el más reciente
        $wpdb->query("
            UPDATE $cart_items_table t1
            LEFT JOIN (
                SELECT cart_id, listing_id, MAX(id) as max_id
                FROM $cart_items_table
                WHERE status = 'active'
                GROUP BY cart_id, listing_id
            ) t2 ON t1.cart_id = t2.cart_id 
                AND t1.listing_id = t2.listing_id
            SET t1.status = 'removed'
            WHERE t1.status = 'active'
            AND t1.id != t2.max_id
        ");

        // Agregar el índice único
        $wpdb->query("
            ALTER TABLE $cart_items_table
            ADD UNIQUE KEY unique_listing_in_cart (cart_id, listing_id, status)
        ");
    }
		
		    self::update_cart_table_structure();

}
	
	/**
 * Actualizar estructura de la tabla de carritos para soportar lead_id y event_id
 */
private static function update_cart_table_structure() {
    global $wpdb;
    $carts_table = $wpdb->prefix . 'eq_carts';

    // Verificar si las columnas ya existen
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$carts_table} LIKE 'lead_id'");
    if (empty($columns)) {
        // Añadir columna lead_id
        $wpdb->query("ALTER TABLE {$carts_table} ADD COLUMN lead_id bigint(20) unsigned NULL DEFAULT NULL AFTER user_id");
    }

    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$carts_table} LIKE 'event_id'");
    if (empty($columns)) {
        // Añadir columna event_id
        $wpdb->query("ALTER TABLE {$carts_table} ADD COLUMN event_id bigint(20) unsigned NULL DEFAULT NULL AFTER lead_id");
    }

    // Verificar si el índice ya existe
    $indexes = $wpdb->get_results("SHOW INDEX FROM {$carts_table} WHERE Key_name = 'lead_event_idx'");
    if (empty($indexes)) {
        // Añadir índice para optimizar búsquedas
        $wpdb->query("ALTER TABLE {$carts_table} ADD INDEX lead_event_idx (lead_id, event_id)");
    }
}
	
	/**
 * Crear tabla para registrar cotizaciones generadas
 */
private static function create_quotes_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Nombre de la tabla con prefijo
    $quotes_table = $wpdb->prefix . 'eq_quotes';

    // SQL para tabla de cotizaciones
    $sql_quotes = "CREATE TABLE IF NOT EXISTS $quotes_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        cart_id bigint(20) unsigned NOT NULL,
        lead_id bigint(20) unsigned NULL DEFAULT NULL,
        event_id bigint(20) unsigned NULL DEFAULT NULL,
        user_id bigint(20) unsigned NOT NULL,
        pdf_url varchar(255) NOT NULL,
        pdf_path varchar(255) NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status varchar(50) NOT NULL DEFAULT 'active',
        nombre_pdf varchar(255) NULL,
        notes text NULL,
        PRIMARY KEY (id),
        KEY cart_id (cart_id),
        KEY lead_id (lead_id),
        KEY event_id (event_id),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Crear tabla
    dbDelta($sql_quotes);
}
	
	/**
 * Crear tabla para gestionar sesiones de contexto
 */
private static function create_context_sessions_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Nombre de la tabla con prefijo
    $context_sessions_table = $wpdb->prefix . 'eq_context_sessions';

    // SQL para tabla de sesiones de contexto
    $sql_context_sessions = "CREATE TABLE IF NOT EXISTS $context_sessions_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        session_token varchar(64) NOT NULL,
        lead_id bigint(20) unsigned NULL DEFAULT NULL,
        event_id bigint(20) unsigned NULL DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id),
        KEY session_token (session_token)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Crear tabla
    dbDelta($sql_context_sessions);
}
	
}