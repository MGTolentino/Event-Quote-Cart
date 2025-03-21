<?php
/**
 * Date Handler Class
 * 
 * Maneja la validación centralizada de fechas para el carrito de cotizaciones
 */

class Event_Quote_Cart_Date_Handler {
    
    /**
 * Verifica la disponibilidad de un listing para una fecha específica
 */
public function check_listing_availability($listing_id, $date) {
    // Convertir fecha a formato Y-m-d para normalizar
    $check_date = date('Y-m-d', strtotime($date));
    
    // Log para depuración
    error_log("Checking availability for listing {$listing_id} on date {$date} (normalized to {$check_date})");
    
    // Obtener reservas existentes
    $blocked_dates = $this->get_listing_reservations($listing_id);
    
    // Log para depuración
    error_log("Blocked dates for listing {$listing_id}: " . implode(', ', $blocked_dates));
    
    // Verificar si la fecha está bloqueada por una reserva
    $is_available = !in_array($check_date, $blocked_dates);
    
    // Log para depuración
    error_log("Availability result for listing {$listing_id} on date {$check_date}: " . ($is_available ? 'Available' : 'Not available'));
    
    return $is_available;
}
	
	private function get_listing_reservations($listing_id) {
    $blocked_dates = array();
    
    // Obtener bookings existentes
    $bookings = get_posts(array(
        'post_type' => 'hp_booking',
        'post_status' => array('publish', 'draft', 'private'),
        'post_parent' => $listing_id,
        'posts_per_page' => -1,
    ));
    
    foreach ($bookings as $booking) {
        $start_time = get_post_meta($booking->ID, 'hp_start_time', true);
        if ($start_time) {
            $blocked_dates[] = date('Y-m-d', $start_time);
        }
    }
    
    return array_unique($blocked_dates);
}

    /**
     * Valida las restricciones específicas del listing
     */
    private function validate_listing_restrictions($listing_id, $check_date) {
        $offset = intval(get_post_meta($listing_id, 'hp_booking_offset', true)) ?: 0;
        $window = intval(get_post_meta($listing_id, 'hp_booking_window', true)) ?: 365;
        
        $current = new DateTime();
        $check = new DateTime($check_date);
        
        // Verificar offset
        $min_date = new DateTime();
        $min_date->modify("+{$offset} days");
        
        if ($check < $min_date) {
            return false;
        }
        
        // Verificar window
        $max_date = new DateTime();
        $max_date->modify("+{$window} days");
        
        if ($check > $max_date) {
            return false;
        }
        
        return true;
    }

    /**
     * Verifica la disponibilidad de items en el carrito
     */
    public function validate_cart_items($date) {
    global $wpdb;
    
    $items_table = $wpdb->prefix . 'eq_cart_items';
    $items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$items_table} WHERE status = %s",
            'active'
        )
    );
    
    $validation_results = array();
    
    foreach ($items as $item) {
        // Verificar disponibilidad para el nuevo item
        $is_available = $this->check_listing_availability($item->listing_id, $date);
        
        // Si no está disponible, verificar si es el mismo item con la misma fecha
        if (!$is_available) {
            $form_data = json_decode($item->form_data, true);
            if (isset($form_data['date']) && $form_data['date'] === $date) {
                $is_available = true;
            }
        }
        
        $validation_results[$item->id] = array(
            'available' => $is_available,
            'listing_id' => $item->listing_id,
            'date' => $date
        );
    }
    
    return $validation_results;
}
}