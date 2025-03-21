<?php
/**
 * Fired during plugin deactivation
 *
 * @since      1.0.0
 */

class Event_Quote_Cart_Deactivator {

    /**
     * Deactivate the plugin.
     *
     * Clean up plugin data if necessary
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Remove capabilities if necessary
        self::remove_capabilities();
        
        // Store deactivation time
        update_option('eq_cart_deactivated_time', current_time('mysql'));
        
        // Set plugin status
        update_option('eq_cart_plugin_status', 'inactive');
        
        // Clear any scheduled hooks
        wp_clear_scheduled_hook('eq_cart_cleanup_expired_items');
    }

    /**
     * Remove plugin specific capabilities from roles.
     */
    private static function remove_capabilities() {
        // Only remove if the option is set to clean up on deactivation
        if ('yes' === get_option('eq_cart_cleanup_on_deactivate', 'no')) {
            // Get roles
            $admin = get_role('administrator');
            $ventas = get_role('ventas-team');

            // Capabilities to remove
            $caps = array(
                'view_eq_cart',
                'edit_eq_cart',
                'delete_eq_cart',
                'create_eq_cart',
                'manage_eq_cart_settings'
            );

            // Remove capabilities from administrator
            if ($admin) {
                foreach ($caps as $cap) {
                    $admin->remove_cap($cap);
                }
            }

            // Remove capabilities from ventas-team
            if ($ventas) {
                foreach ($caps as $cap) {
                    $ventas->remove_cap($cap);
                }
            }
        }
    }

    /**
     * Clean up transients and temporary data
     */
    private static function cleanup_temporary_data() {
        global $wpdb;
        
        // Delete all transients related to our plugin
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                '%_transient_eq_cart_%'
            )
        );
    }
}