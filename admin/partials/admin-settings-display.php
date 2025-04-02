<?php
/**
 * Admin settings page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap eq-cart-admin-wrap">
    <div class="eq-cart-admin-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    </div>

    <form method="post" action="options.php" class="eq-cart-settings-form">
        <?php
        settings_fields('eq_cart_settings');
        do_settings_sections('event-quote-cart-settings');
        submit_button();
        ?>
    </form>

    <div class="eq-cart-admin-footer">
        <p>
            <?php _e('For more information about Event Quote Cart, please visit our documentation.', 'event-quote-cart'); ?>
        </p>
    </div>
</div>

<h2 class="nav-tab-wrapper">
    <a href="?page=event-quote-cart&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('General', 'event-quote-cart'); ?></a>
    <a href="?page=event-quote-cart&tab=stripe" class="nav-tab <?php echo $active_tab == 'stripe' ? 'nav-tab-active' : ''; ?>"><?php _e('Stripe', 'event-quote-cart'); ?></a>
</h2>

<?php if ($active_tab == 'general'): ?>
    <!-- Contenido existente para la pestaña general -->
<?php elseif ($active_tab == 'stripe'): ?>
    <div class="eq-settings-section">
        <h2><?php _e('Stripe Settings', 'event-quote-cart'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="eq_stripe_enabled"><?php _e('Enable Stripe Payments', 'event-quote-cart'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="eq_stripe_enabled" name="eq_stripe_enabled" value="yes" <?php checked(get_option('eq_stripe_enabled'), 'yes'); ?> />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="eq_stripe_test_mode"><?php _e('Test Mode', 'event-quote-cart'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="eq_stripe_test_mode" name="eq_stripe_test_mode" value="yes" <?php checked(get_option('eq_stripe_test_mode'), 'yes'); ?> />
                    <p class="description"><?php _e('Enable test mode to use Stripe test API keys', 'event-quote-cart'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="eq_stripe_test_publishable_key"><?php _e('Test Publishable Key', 'event-quote-cart'); ?></label>
                </th>
                <td>
                    <input type="text" id="eq_stripe_test_publishable_key" name="eq_stripe_test_publishable_key" value="<?php echo esc_attr(get_option('eq_stripe_test_publishable_key', '')); ?>" class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="eq_stripe_test_secret_key"><?php _e('Test Secret Key', 'event-quote-cart'); ?></label>
                </th>
                <td>
                    <input type="password" id="eq_stripe_test_secret_key" name="eq_stripe_test_secret_key" value="<?php echo esc_attr(get_option('eq_stripe_test_secret_key', '')); ?>" class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="eq_stripe_test_webhook_secret"><?php _e('Test Webhook Secret', 'event-quote-cart'); ?></label>
                </th>
                <td>
                    <input type="password" id="eq_stripe_test_webhook_secret" name="eq_stripe_test_webhook_secret" value="<?php echo esc_attr(get_option('eq_stripe_test_webhook_secret', '')); ?>" class="regular-text" />
                    <p class="description"><?php _e('Used to verify webhook events', 'event-quote-cart'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="eq_stripe_publishable_key"><?php _e('Live Publishable Key', 'event-quote-cart'); ?></label>
                </th>
                <td>
                    <input type="text" id="eq_stripe_publishable_key" name="eq_stripe_publishable_key" value="<?php echo esc_attr(get_option('eq_stripe_publishable_key', '')); ?>" class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="eq_stripe_secret_key"><?php _e('Live Secret Key', 'event-quote-cart'); ?></label>
                </th>
                <td>
                    <input type="password" id="eq_stripe_secret_key" name="eq_stripe_secret_key" value="<?php echo esc_attr(get_option('eq_stripe_secret_key', '')); ?>" class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="eq_stripe_webhook_secret"><?php _e('Live Webhook Secret', 'event-quote-cart'); ?></label>
                </th>
                <td>
                    <input type="password" id="eq_stripe_webhook_secret" name="eq_stripe_webhook_secret" value="<?php echo esc_attr(get_option('eq_stripe_webhook_secret', '')); ?>" class="regular-text" />
                    <p class="description"><?php _e('Used to verify webhook events', 'event-quote-cart'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="eq_stripe_default_vendor_id"><?php _e('Default Vendor ID', 'event-quote-cart'); ?></label>
                </th>
                <td>
                    <input type="number" id="eq_stripe_default_vendor_id" name="eq_stripe_default_vendor_id" value="<?php echo esc_attr(get_option('eq_stripe_default_vendor_id', 0)); ?>" class="regular-text" />
                    <p class="description"><?php _e('Default vendor to receive payments (hp_vendor post ID)', 'event-quote-cart'); ?></p>
                </td>
            </tr>
        </table>
    </div>
<?php endif; ?>