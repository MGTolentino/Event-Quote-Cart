<?php
/**
 * Register all actions and filters for the plugin
 */

class Event_Quote_Cart_Loader {

    protected $actions;
    protected $filters;
    protected $plugin_admin;
    protected $plugin_public;

    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single
     * collection.
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress.
     */
    public function run() {
        // Load dependencies first
        $this->load_dependencies();
        
        // Initialize plugin components
        $this->init_components();

        // Register all actions
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register all filters
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize admin
        $this->plugin_admin = new Event_Quote_Cart_Admin('event-quote-cart', EQ_CART_VERSION);
        $this->plugin_admin->init();

        // Initialize public
        $this->plugin_public = new Event_Quote_Cart_Public('event-quote-cart', EQ_CART_VERSION);
        $this->plugin_public->init();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        $plugin_root = dirname(dirname(__FILE__));
        
        // Load admin class
        require_once $plugin_root . '/admin/class-admin.php';
        
        // Load public class
        require_once $plugin_root . '/public/class-public.php';

        //Stripe Handler
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-stripe-handler.php';

    }

    /**
     * Define the locale for this plugin for internationalization.
     */
    private function set_locale() {
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
    }

    /**
     * Load the plugin text domain for translation.
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'event-quote-cart',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}