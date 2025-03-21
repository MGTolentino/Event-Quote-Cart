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