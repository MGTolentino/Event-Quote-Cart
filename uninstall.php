<?php
/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if we should delete all data
$delete_data = get_option('eq_cart_delete_data_on_uninstall', 'no');

if ($delete_data === 'yes') {
    global $wpdb;

    // Delete tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}eq_carts");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}eq_cart_items");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}eq_cart_metadata");

    // Delete options
    $options = array(
        'eq_cart_db_version',
        'eq_cart_enabled',
        'eq_cart_button_text',
        'eq_cart_button_text_en',
        'eq_cart_delete_data_on_uninstall',
        'eq_cart_page_id'
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // Remove capabilities
    $roles = array('administrator', 'ventas-team');
    $caps = array(
        'view_eq_cart',
        'edit_eq_cart',
        'delete_eq_cart',
        'create_eq_cart',
        'manage_eq_cart_settings'
    );

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($caps as $cap) {
                $role->remove_cap($cap);
            }
        }
    }
}