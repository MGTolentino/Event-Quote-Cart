<?php
/**
 * Main admin page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap eq-cart-admin-wrap">
    <div class="eq-cart-admin-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    </div>

    <div class="eq-cart-admin-content">
        <div class="eq-cart-stats">
            <h2><?php _e('Quote Cart Statistics', 'event-quote-cart'); ?></h2>
            <?php
            global $wpdb;
            
            // Get active carts count
            $active_carts = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}eq_carts 
                WHERE status = 'active'
            ");

            // Get total items in active carts
            $active_items = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}eq_cart_items ci
                JOIN {$wpdb->prefix}eq_carts c ON ci.cart_id = c.id
                WHERE c.status = 'active' AND ci.status = 'active'
            ");
            ?>
            <div class="eq-cart-stats-grid">
                <div class="eq-cart-stat-box">
                    <h3><?php _e('Active Carts', 'event-quote-cart'); ?></h3>
                    <p class="eq-cart-stat-number"><?php echo esc_html($active_carts); ?></p>
                </div>
                <div class="eq-cart-stat-box">
                    <h3><?php _e('Active Items', 'event-quote-cart'); ?></h3>
                    <p class="eq-cart-stat-number"><?php echo esc_html($active_items); ?></p>
                </div>
            </div>
        </div>

        <div class="eq-cart-recent">
            <h2><?php _e('Recent Quote Carts', 'event-quote-cart'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Cart ID', 'event-quote-cart'); ?></th>
                        <th><?php _e('User', 'event-quote-cart'); ?></th>
                        <th><?php _e('Items', 'event-quote-cart'); ?></th>
                        <th><?php _e('Created', 'event-quote-cart'); ?></th>
                        <th><?php _e('Status', 'event-quote-cart'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $recent_carts = $wpdb->get_results("
                        SELECT c.*, 
                               u.display_name as user_name,
                               COUNT(ci.id) as item_count
                        FROM {$wpdb->prefix}eq_carts c
                        LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                        LEFT JOIN {$wpdb->prefix}eq_cart_items ci ON c.id = ci.cart_id
                        GROUP BY c.id
                        ORDER BY c.created_at DESC
                        LIMIT 10
                    ");

                    if ($recent_carts) {
                        foreach ($recent_carts as $cart) {
                            ?>
                            <tr>
                                <td><?php echo esc_html($cart->id); ?></td>
                                <td><?php echo esc_html($cart->user_name); ?></td>
                                <td><?php echo esc_html($cart->item_count); ?></td>
                                <td><?php echo esc_html(
                                    date_i18n(
                                        get_option('date_format') . ' ' . get_option('time_format'),
                                        strtotime($cart->created_at)
                                    )
                                ); ?></td>
                                <td><?php echo esc_html(ucfirst($cart->status)); ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="5"><?php _e('No quote carts found.', 'event-quote-cart'); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="eq-cart-admin-footer">
        <p>
            <?php _e('For more information about Event Quote Cart, please visit our documentation.', 'event-quote-cart'); ?>
        </p>
    </div>
</div>