<?php
/**
 * Handler for single listing integration
 */

class Event_Quote_Cart_Single_Handler {
    
    private $date_handler;
    
    public function __construct() {
        $this->date_handler = new Event_Quote_Cart_Date_Handler();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook into custom booking form
        add_action('hivepress/v1/templates/booking_form_dates_field', array($this, 'modify_booking_form_dates'), 10, 2);
        
        // Add quote button to single listing
        add_action('hp_listing_after_content', array($this, 'add_quote_button'));
        
        // AJAX handlers
        add_action('wp_ajax_eq_get_single_listing_data', array($this, 'get_single_listing_data'));
    }
    
    /**
     * Modify booking form dates field to sync with quote cart
     */
    public function modify_booking_form_dates($output, $context) {
        // Get stored date from quote cart
        $stored_date = isset($_COOKIE['eq_selected_date']) ? sanitize_text_field($_COOKIE['eq_selected_date']) : '';
        
        if ($stored_date) {
            // Add data attribute for JavaScript
            $output = str_replace(
                'data-component="date"',
                'data-component="date" data-eq-stored-date="' . esc_attr($stored_date) . '"',
                $output
            );
        }
        
        return $output;
    }
    
    /**
     * Add quote button to single listing
     */
    public function add_quote_button($listing) {
        if (!eq_can_view_quote_button()) {
            return;
        }
        
        include EQ_CART_PLUGIN_DIR . 'templates/single-listing/listing-item.php';
    }
    
    /**
     * Get single listing data for quote cart
     */
    public function get_single_listing_data() {
        check_ajax_referer('eq_cart_public_nonce', 'nonce');
        
        if (!eq_can_view_quote_button()) {
            wp_send_json_error('Unauthorized');
        }
        
        $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
        
        if (!$listing_id) {
            wp_send_json_error('Invalid listing ID');
        }
        
        try {
            $listing = \HivePress\Models\Listing::query()->get_by_id($listing_id);
            
            if (!$listing) {
                throw new Exception('Listing not found');
            }
            
            $response_data = array(
                'id' => $listing_id,
                'title' => $listing->get_title(),
                'price' => $listing->get_price(),
                'min_quantity' => $listing->get_booking_min_quantity(),
                'max_quantity' => $listing->get_booking_max_quantity(),
                'min_length' => $listing->get_booking_min_length(),
                'max_length' => $listing->get_booking_max_length(),
                'booking_offset' => $listing->get_booking_offset(),
                'booking_window' => $listing->get_booking_window(),
                'has_quantity' => true
            );
            
            // Get extras if exist
            $extras = get_post_meta($listing_id, 'hp_price_extras', true);
            if (is_array($extras)) {
                $response_data['extras'] = $this->format_extras($extras);
            }
            
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Format extras for response
     */
    private function format_extras($extras) {
        $formatted = array();
        foreach ($extras as $key => $extra) {
            $formatted[] = array(
                'id' => $key,
                'name' => $extra['name'],
                'price' => $extra['price'],
                'price_formatted' => hivepress()->woocommerce->format_price($extra['price']),
                'type' => isset($extra['type']) ? $extra['type'] : ''
            );
        }
        return $formatted;
    }
}