<?php
/**
 * Template for header cart button
 */

defined('ABSPATH') || exit;

$cart_count = $this->get_cart_items_count(); // Necesitaremos agregar esta función
$cart_url = $this->get_cart_page_url();

?>

<div class="eq-header-cart">
    <a href="<?php echo esc_url($cart_url); ?>" class="eq-header-cart-button">
        <i class="fas fa-file-invoice"></i>
        <span class="eq-cart-label">
            <?php echo get_locale() === 'es_ES' ? 'Ver Cotizaciones' : 'View Quotes'; ?>
        </span>
        <?php if ($cart_count > 0): ?>
            <span class="eq-cart-count"><?php echo esc_html($cart_count); ?></span>
        <?php endif; ?>
    </a>
</div>