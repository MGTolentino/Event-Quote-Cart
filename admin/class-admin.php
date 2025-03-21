<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 */

class Event_Quote_Cart_Admin {

    private $plugin_name;
    private $version;


    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version) {
    $this->plugin_name = $plugin_name;
    $this->version = $version;
   
}

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name . '-admin',
            EQ_CART_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $this->version,
            'all'
        );

    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name . '-admin',
            EQ_CART_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            $this->version,
            false
        );

        // Localize script
        wp_localize_script(
            'event-quote-cart-admin',
            'eqCartAdmin',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('eq_cart_admin_nonce')
            )
        );
	
		
    }

    /**
     * Add menu items to the admin area.
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Event Quote Cart', 'event-quote-cart'),
            __('Quote Cart', 'event-quote-cart'),
            'manage_eq_cart_settings',
            'event-quote-cart',
            array($this, 'display_plugin_admin_page'),
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'event-quote-cart',
            __('Settings', 'event-quote-cart'),
            __('Settings', 'event-quote-cart'),
            'manage_eq_cart_settings',
            'event-quote-cart-settings',
            array($this, 'display_plugin_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'eq_cart_settings',
            'eq_cart_button_text',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '+ Cotización'
            )
        );

        register_setting(
            'eq_cart_settings',
            'eq_cart_button_text_en',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '+ Quote'
            )
        );

        register_setting(
            'eq_cart_settings',
            'eq_cart_enabled',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'yes'
            )
        );

        add_settings_section(
            'eq_cart_general_settings',
            __('General Settings', 'event-quote-cart'),
            array($this, 'render_settings_section'),
            'event-quote-cart-settings'
        );

        add_settings_field(
            'eq_cart_button_text',
            __('Button Text (Spanish)', 'event-quote-cart'),
            array($this, 'render_text_field'),
            'event-quote-cart-settings',
            'eq_cart_general_settings',
            array('label_for' => 'eq_cart_button_text')
        );

        add_settings_field(
            'eq_cart_button_text_en',
            __('Button Text (English)', 'event-quote-cart'),
            array($this, 'render_text_field'),
            'event-quote-cart-settings',
            'eq_cart_general_settings',
            array('label_for' => 'eq_cart_button_text_en')
        );

        add_settings_field(
            'eq_cart_enabled',
            __('Enable Quote Cart', 'event-quote-cart'),
            array($this, 'render_enabled_field'),
            'event-quote-cart-settings',
            'eq_cart_general_settings',
            array('label_for' => 'eq_cart_enabled')
        );
    }

    /**
     * Render the main admin page
     */
    public function display_plugin_admin_page() {
        require_once EQ_CART_PLUGIN_DIR . 'admin/partials/admin-display.php';
    }

    /**
     * Render the settings page
     */
    public function display_plugin_settings_page() {
        if (!current_user_can('manage_eq_cart_settings')) {
            return;
        }

        // Check if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'eq_cart_messages',
                'eq_cart_message',
                __('Settings Saved', 'event-quote-cart'),
                'updated'
            );
        }

        require_once EQ_CART_PLUGIN_DIR . 'admin/partials/admin-settings-display.php';
    }

    /**
     * Render settings section description
     */
    public function render_settings_section($args) {
        ?>
        <p><?php _e('Configure the general settings for the Event Quote Cart plugin.', 'event-quote-cart'); ?></p>
        <?php
    }

    /**
     * Render text field
     */
    public function render_text_field($args) {
        $option_name = $args['label_for'];
        $value = get_option($option_name);
        ?>
        <input type="text" 
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($option_name); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <?php
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
		

    }
}