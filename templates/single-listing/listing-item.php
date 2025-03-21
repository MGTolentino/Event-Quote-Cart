<?php
/**
 * Template for the single listing item integration
 */

if (!defined('ABSPATH')) {
    exit;
}

// Solo proceder si tenemos permisos
if (!function_exists('eq_can_view_quote_button') || !eq_can_view_quote_button()) {
    return;
}
?>

<div class="eq-single-listing-quote">
    <?php do_action('eq_before_single_quote_button'); ?>
    
    <a href="#" 
       class="boton-cotizar" 
       data-listing-id="<?php echo esc_attr($listing->get_id()); ?>"
       data-listing-title="<?php echo esc_attr($listing->get_title()); ?>">
        <i class="fas fa-plus"></i> <?php esc_html_e('Quote', 'event-quote-cart'); ?>
    </a>
    
    <?php do_action('eq_after_single_quote_button'); ?>
</div>