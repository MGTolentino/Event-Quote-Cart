<?php
/**
 * Template for the quote button in listing item
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('eq_can_view_quote_button') || !eq_can_view_quote_button()) {
    return;
}
?>
<a href="#" 
   class="boton-cotizar" 
   data-listing-id="<?php echo esc_attr($listing_id); ?>"
   data-listing-title="<?php echo esc_attr(get_the_title($listing_id)); ?>">
		<i class="fas fa-plus"></i> <?php esc_html_e('Quote', 'event-quote-cart'); ?>	
</a>