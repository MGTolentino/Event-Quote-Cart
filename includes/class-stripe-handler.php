<?php
/**
 * Stripe Handler Class
 * Maneja la integración con Stripe para procesamiento de pagos
 */

defined('ABSPATH') || exit;

class Event_Quote_Cart_Stripe_Handler {
    private $secret_key;
    private $publishable_key;
    private $endpoint_secret;
    private $test_mode;
    private $vendor_id; // Para pagos a un solo vendor

    public function __construct() {
        $this->test_mode = get_option('eq_stripe_test_mode', 'yes') === 'yes';
        
        if ($this->test_mode) {
            $this->secret_key = get_option('eq_stripe_test_secret_key', '');
            $this->publishable_key = get_option('eq_stripe_test_publishable_key', '');
            $this->endpoint_secret = get_option('eq_stripe_test_webhook_secret', '');
        } else {
            $this->secret_key = get_option('eq_stripe_secret_key', '');
            $this->publishable_key = get_option('eq_stripe_publishable_key', '');
            $this->endpoint_secret = get_option('eq_stripe_webhook_secret', '');
        }
        
        $this->vendor_id = get_option('eq_stripe_default_vendor_id', 0);
        
        // Inicializar Stripe con la clave secreta
        $this->init_stripe();
        
        // Hooks
        $this->init_hooks();
    }

    private function init_stripe() {
        if (!class_exists('\Stripe\Stripe')) {
            require_once EQ_CART_PLUGIN_DIR . 'vendor/autoload.php';
        }
        
        \Stripe\Stripe::setApiKey($this->secret_key);
        \Stripe\Stripe::setAppInfo(
            'Event Quote Cart',
            EQ_CART_VERSION,
            'https://yourdomain.com',
            'pp_partner_JUYvCbCaSESLjA'
        );
    }

    private function init_hooks() {
        // AJAX actions
        add_action('wp_ajax_eq_create_payment_intent', array($this, 'create_payment_intent'));
        add_action('wp_ajax_eq_create_checkout_session', array($this, 'create_checkout_session'));
        add_action('wp_ajax_eq_check_payment_status', array($this, 'check_payment_status'));
        add_action('wp_ajax_eq_generate_payment_link', array($this, 'generate_payment_link'));
        
        // Webhook handling
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }

    public function get_publishable_key() {
        return $this->publishable_key;
    }

    public function register_webhook_endpoint() {
        register_rest_route('event-quote-cart/v1', '/stripe-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
 * Crear un Payment Intent de Stripe para pagos directos
 */
public function create_payment_intent() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'User not logged in'));
        return;
    }

    try {
        // Obtener items del carrito y calcular total
        $cart_items = eq_get_cart_items();
        $totals = eq_calculate_cart_totals($cart_items);
        
        // Quitar formato de precio y convertir a centavos para Stripe
        $total_amount = $this->parse_amount($totals['total']);
        
        $metadata = array(
            'user_id' => get_current_user_id(),
            'cart_id' => $this->get_active_cart_id()
        );
        
        // Añadir datos de contexto si existen
        $context = eq_get_active_context();
        if ($context) {
            $metadata['lead_id'] = $context['lead']->_ID;
            $metadata['event_id'] = $context['event']->_ID;
            $metadata['lead_name'] = $context['lead']->lead_nombre . ' ' . $context['lead']->lead_apellido;
            $metadata['event_type'] = $context['event']->tipo_de_evento;
        }
        
        // Crear el Payment Intent
        $payment_intent = \Stripe\PaymentIntent::create([
            'amount' => $total_amount,
            'currency' => 'mxn',
            'metadata' => $metadata,
            'description' => 'Orden #' . time(),
            'statement_descriptor' => 'EVENT-QUOTE'
        ]);
        
        // Crear una orden preliminar en la base de datos
        $order_id = $this->create_order([
            'cart_id' => $this->get_active_cart_id(),
            'payment_id' => $payment_intent->id,
            'amount' => $total_amount / 100,
            'status' => 'pending'
        ]);
        
        wp_send_json_success([
            'clientSecret' => $payment_intent->client_secret,
            'order_id' => $order_id
        ]);
        
    } catch (\Exception $e) {
        wp_send_json_error([
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Crear una sesión de checkout que puede ser compartida
 */
public function create_checkout_session() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'User not logged in'));
        return;
    }
    
    try {
        // Obtener datos básicos
        $cart_items = eq_get_cart_items();
        $totals = eq_calculate_cart_totals($cart_items);
        $cart_id = $this->get_active_cart_id();
        
        // Obtener contexto activo
        $context = eq_get_active_context();
        
        // Cliente email
        $customer_email = '';
        if ($context && isset($context['lead']) && !empty($context['lead']->lead_e_mail)) {
            $customer_email = $context['lead']->lead_e_mail;
        } else {
            $user = wp_get_current_user();
            $customer_email = $user->user_email;
        }
        
        // Configurar items de línea
        $line_items = [];
        
        foreach ($cart_items as $item) {
            $line_items[] = [
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => $item->title,
                        'description' => 'Fecha: ' . $item->date,
                        'metadata' => [
                            'listing_id' => $item->listing_id
                        ]
                    ],
                    'unit_amount' => $this->parse_amount($item->price_formatted),
                ],
                'quantity' => 1,
            ];
        }
        
        // Metadata para la sesión
        $metadata = [
            'cart_id' => $cart_id,
            'created_by' => get_current_user_id()
        ];
        
        if ($context) {
            $metadata['lead_id'] = $context['lead']->_ID;
            $metadata['event_id'] = $context['event']->_ID;
        }
        
        // URLs de confirmación
        $success_url = add_query_arg(['status' => 'success', 'session_id' => '{CHECKOUT_SESSION_ID}'], $this->get_cart_url());
        $cancel_url = add_query_arg(['status' => 'cancel'], $this->get_cart_url());
        
        // Crear sesión de Checkout
        $checkout_session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $line_items,
            'mode' => 'payment',
            'customer_email' => $customer_email,
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'metadata' => $metadata,
        ]);
        
        // Crear una orden preliminar
        $order_id = $this->create_order([
            'cart_id' => $cart_id,
            'payment_id' => $checkout_session->id,
            'amount' => $this->parse_amount($totals['total']) / 100,
            'status' => 'pending',
            'lead_id' => $context ? $context['lead']->_ID : null,
            'event_id' => $context ? $context['event']->_ID : null,
            'created_by' => get_current_user_id(),
            'checkout_url' => $checkout_session->url
        ]);
        
        wp_send_json_success([
            'session_id' => $checkout_session->id,
            'checkout_url' => $checkout_session->url,
            'order_id' => $order_id
        ]);
        
    } catch (\Exception $e) {
        wp_send_json_error([
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Manejar webhooks de Stripe
 */
public function handle_webhook($request) {
    $payload = $request->get_body();
    $sig_header = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
    
    try {
        // Verificar la firma del webhook para seguridad
        $event = \Stripe\Webhook::constructEvent(
            $payload, $sig_header, $this->endpoint_secret
        );
        
        // Manejar diferentes tipos de eventos
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handle_checkout_session_completed($event);
                break;
                
            case 'payment_intent.succeeded':
                $this->handle_payment_intent_succeeded($event);
                break;
                
            case 'payment_intent.payment_failed':
                $this->handle_payment_failed($event);
                break;
        }
        
        return new WP_REST_Response(['status' => 'success']);
        
    } catch (\UnexpectedValueException $e) {
        // Invalid payload
        return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid payload'], 400);
        
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        // Invalid signature
        return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid signature'], 400);
        
    } catch (\Exception $e) {
        // Other errors
        return new WP_REST_Response(['status' => 'error', 'message' => $e->getMessage()], 400);
    }
}

/**
 * Manejar evento de sesión de checkout completada
 */
private function handle_checkout_session_completed($event) {
    $session = $event->data->object;
    
    // Actualizar orden a pagada
    $this->update_order_status($session->id, 'paid');
    
    // Crear reservas
    $this->create_bookings_for_order($session->id);
    
    // Enviar email de confirmación
    $this->send_order_confirmation($session->id);
}

/**
 * Manejar evento de payment intent exitoso
 */
private function handle_payment_intent_succeeded($event) {
    $payment_intent = $event->data->object;
    
    // Actualizar orden a pagada
    $this->update_order_status($payment_intent->id, 'paid');
    
    // Crear reservas
    $this->create_bookings_for_order($payment_intent->id);
    
    // Enviar email de confirmación
    $this->send_order_confirmation($payment_intent->id);
}

/**
 * Manejar evento de pago fallido
 */
private function handle_payment_failed($event) {
    $payment_intent = $event->data->object;
    
    // Actualizar orden a fallida
    $this->update_order_status($payment_intent->id, 'failed');
    
    // Notificar al administrador
    $this->notify_payment_failure($payment_intent->id);
}

/**
 * Crear una orden en la base de datos
 */
private function create_order($data) {
    global $wpdb;
    
    $defaults = [
        'user_id' => get_current_user_id(),
        'status' => 'pending',
        'currency' => 'MXN',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    $order_data = wp_parse_args($data, $defaults);
    
    $wpdb->insert(
        $wpdb->prefix . 'eq_orders',
        $order_data,
        [
            '%d', // cart_id
            '%d', // user_id
            '%d', // lead_id (if present)
            '%d', // event_id (if present)
            '%s', // status
            '%s', // payment_id
            '%s', // payment_method (if present)
            '%f', // amount
            '%s', // currency
            '%s', // billing_name (if present)
            '%s', // billing_email (if present)
            '%s', // billing_phone (if present)
            '%d', // created_by (if present)
            '%s', // checkout_url (if present)
            '%s', // created_at
            '%s'  // updated_at
        ]
    );
    
    $order_id = $wpdb->insert_id;
    
    // Registrar vendors para esta orden
    $this->register_vendors_for_order($order_id);
    
    return $order_id;
}

/**
 * Registrar detalles de vendors para la orden
 */
private function register_vendors_for_order($order_id) {
    global $wpdb;
    
    // Obtener datos de la orden
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_orders WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        return;
    }
    
    // Obtener items del carrito
    $cart_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_cart_items 
        WHERE cart_id = %d AND status = 'active'",
        $order->cart_id
    ));
    
    foreach ($cart_items as $item) {
        // Obtener el vendor_id del listing
        $listing_id = $item->listing_id;
        $vendor_id = $this->get_vendor_id_from_listing($listing_id);
        
        // Si no hay vendor asignado, usar el default
        if (!$vendor_id) {
            $vendor_id = $this->vendor_id;
        }
        
        // Obtener precio del item
        $form_data = json_decode($item->form_data, true);
        $amount = isset($form_data['total_price']) ? floatval($form_data['total_price']) : 0;
        
        // Insertar registro de vendor
        $wpdb->insert(
            $wpdb->prefix . 'eq_order_vendors',
            [
                'order_id' => $order_id,
                'vendor_id' => $vendor_id,
                'listing_id' => $listing_id,
                'amount' => $amount,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            [
                '%d', // order_id
                '%d', // vendor_id
                '%d', // listing_id
                '%f', // amount
                '%s', // status
                '%s', // created_at
                '%s'  // updated_at
            ]
        );
    }
}

/**
 * Obtener el vendor ID a partir de un listing
 */
private function get_vendor_id_from_listing($listing_id) {
    // Obtener el post_parent de hp_listing que es el hp_vendor
    $listing = get_post($listing_id);
    
    if (!$listing || $listing->post_type !== 'hp_listing') {
        return null;
    }
    
    return $listing->post_parent;
}

/**
 * Actualizar el estado de una orden
 */
private function update_order_status($payment_id, $status) {
    global $wpdb;
    
    return $wpdb->update(
        $wpdb->prefix . 'eq_orders',
        [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ],
        ['payment_id' => $payment_id],
        ['%s', '%s'],
        ['%s']
    );
}

/**
 * Crear reservas (hp_booking) para una orden pagada
 */
private function create_bookings_for_order($payment_id) {
    global $wpdb;
    
    // Obtener la orden por payment_id
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_orders WHERE payment_id = %s",
        $payment_id
    ));
    
    if (!$order) {
        return;
    }
    
    // Obtener items del carrito
    $cart_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eq_cart_items 
        WHERE cart_id = %d AND status = 'active'",
        $order->cart_id
    ));
    
    foreach ($cart_items as $item) {
        $form_data = json_decode($item->form_data, true);
        
        // Crear hp_booking
        $booking_id = $this->create_hp_booking([
            'listing_id' => $item->listing_id,
            'customer_id' => $order->user_id,
            'date' => $form_data['date'],
            'quantity' => $form_data['quantity'],
            'extras' => $form_data['extras'],
            'total_price' => $form_data['total_price']
        ]);
        
        if ($booking_id) {
            // Registrar en nuestra tabla
            $vendor_id = $this->get_vendor_id_from_listing($item->listing_id);
            
            $wpdb->insert(
                $wpdb->prefix . 'eq_bookings',
                [
                    'order_id' => $order->id,
                    'cart_item_id' => $item->id,
                    'booking_id' => $booking_id,
                    'listing_id' => $item->listing_id,
                    'vendor_id' => $vendor_id ?: $this->vendor_id,
                    'status' => 'confirmed',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                [
                    '%d', // order_id
                    '%d', // cart_item_id
                    '%d', // booking_id
                    '%d', // listing_id
                    '%d', // vendor_id
                    '%s', // status
                    '%s', // created_at
                    '%s'  // updated_at
                ]
            );
            
            // Marcar el ítem del carrito como procesado
            $wpdb->update(
                $wpdb->prefix . 'eq_cart_items',
                ['status' => 'completed'],
                ['id' => $item->id],
                ['%s'],
                ['%d']
            );
         }
            }
         }
         
         /**
         * Crear hp_booking
         */
         private function create_hp_booking($data) {
            // Verificar si la clase HivePress está disponible
            if (!class_exists('HivePress') || !function_exists('hivepress')) {
                return false;
            }
            
            try {
                // Crear booking utilizando HivePress
                $booking = new \HivePress\Models\Booking();
                
                $booking->fill([
                    'listing' => $data['listing_id'],
                    'user' => $data['customer_id'],
                    'status' => 'pending',
                    'start_time' => strtotime($data['date']),
                    'end_time' => strtotime($data['date'] . ' +1 day'),
                    'quantity' => $data['quantity'],
                    'price' => $data['total_price'],
                ]);
                
                // Añadir meta para extras si existen
                if (!empty($data['extras'])) {
                    $booking->set_extras($data['extras']);
                }
                
                // Guardar booking
                $booking->save();
                
                // Marcar como confirmado
                $booking->set_status('confirmed');
                $booking->save();
                
                return $booking->get_id();
                
            } catch (\Exception $e) {
                error_log('Error creating booking: ' . $e->getMessage());
                return false;
            }
         }
         
         /**
         * Enviar email de confirmación para una orden
         */
         private function send_order_confirmation($payment_id) {
            global $wpdb;
            
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eq_orders WHERE payment_id = %s",
                $payment_id
            ));
            
            if (!$order) {
                return;
            }
            
            // Obtener información de usuario/lead
            $user_email = null;
            $user_name = '';
            
            if (!empty($order->lead_id)) {
                // Si hay un lead, obtener su información
                $lead = $wpdb->get_row($wpdb->prepare(
                    "SELECT lead_nombre, lead_apellido, lead_e_mail FROM {$wpdb->prefix}jet_cct_leads WHERE _ID = %d",
                    $order->lead_id
                ));
                
                if ($lead && !empty($lead->lead_e_mail)) {
                    $user_email = $lead->lead_e_mail;
                    $user_name = $lead->lead_nombre . ' ' . $lead->lead_apellido;
                }
            }
            
            // Si no hay email de lead, usar el del usuario
            if (!$user_email) {
                $user = get_user_by('id', $order->user_id);
                if ($user) {
                    $user_email = $user->user_email;
                    $user_name = $user->display_name;
                }
            }
            
            if (!$user_email) {
                return;
            }
            
            // Obtener items para la factura
            $cart_items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eq_cart_items 
                WHERE cart_id = %d AND status IN ('active', 'completed')",
                $order->cart_id
            ));
            
            // Preparar el HTML del email
            $items_html = '';
            $total = 0;
            
            foreach ($cart_items as $item) {
                $listing = get_post($item->listing_id);
                $form_data = json_decode($item->form_data, true);
                
                $title = get_the_title($listing);
                $date = isset($form_data['date']) ? $form_data['date'] : '';
                $quantity = isset($form_data['quantity']) ? $form_data['quantity'] : 1;
                $extras_html = '';
                
                if (!empty($form_data['extras'])) {
                    $extras_html = '<ul style="margin: 5px 0; padding-left: 20px;">';
                    foreach ($form_data['extras'] as $extra) {
                        $extras_html .= '<li>' . esc_html($extra['name']) . '</li>';
                    }
                    $extras_html .= '</ul>';
                }
                
                $price = isset($form_data['total_price']) ? floatval($form_data['total_price']) : 0;
                $total += $price;
                
                $price_formatted = hivepress()->woocommerce->format_price($price);
                
                $items_html .= "
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$title}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$date}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$quantity}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$extras_html}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$price_formatted}</td>
                </tr>";
            }
            
            $total_formatted = hivepress()->woocommerce->format_price($total);
            
            // Calcular subtotal e impuestos (inverso)
            $tax_rate = floatval(get_option('eq_tax_rate', 16));
            $subtotal = $total / (1 + ($tax_rate / 100));
            $tax = $total - $subtotal;
            
            $subtotal_formatted = hivepress()->woocommerce->format_price($subtotal);
            $tax_formatted = hivepress()->woocommerce->format_price($tax);
            
            // Generar factura HTML
            $invoice_html = "
            <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;'>
                <h2>Confirmación de Compra</h2>
                <p>Estimado/a {$user_name},</p>
                <p>Gracias por tu compra. A continuación, encontrarás los detalles de tu pedido:</p>
                
                <div style='margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;'>
                    <p><strong>Número de Orden:</strong> {$order->id}</p>
                    <p><strong>Fecha:</strong> " . date_i18n(get_option('date_format'), strtotime($order->created_at)) . "</p>
                    <p><strong>Estado:</strong> Pagado</p>
                </div>
                
                <h3>Artículos comprados</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <thead>
                        <tr style='background-color: #f5f5f5;'>
                            <th style='text-align: left; padding: 10px; border-bottom: 2px solid #ddd;'>Servicio</th>
                            <th style='text-align: left; padding: 10px; border-bottom: 2px solid #ddd;'>Fecha</th>
                            <th style='text-align: left; padding: 10px; border-bottom: 2px solid #ddd;'>Cantidad</th>
                            <th style='text-align: left; padding: 10px; border-bottom: 2px solid #ddd;'>Extras</th>
                            <th style='text-align: left; padding: 10px; border-bottom: 2px solid #ddd;'>Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$items_html}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='4' style='text-align: right; padding: 10px;'><strong>Subtotal:</strong></td>
                            <td style='padding: 10px;'>{$subtotal_formatted}</td>
                        </tr>
                        <tr>
                            <td colspan='4' style='text-align: right; padding: 10px;'><strong>IVA ({$tax_rate}%):</strong></td>
                            <td style='padding: 10px;'>{$tax_formatted}</td>
                        </tr>
                        <tr>
                            <td colspan='4' style='text-align: right; padding: 10px;'><strong>Total:</strong></td>
                            <td style='padding: 10px; font-weight: bold;'>{$total_formatted}</td>
                        </tr>
                    </tfoot>
                </table>
                
                <div style='margin-top: 30px; font-size: 14px; color: #666;'>
                    <p>Si tienes alguna pregunta sobre tu pedido, por favor contáctanos.</p>
                    <p>Gracias por tu compra.</p>
                </div>
            </div>";
            
            // Enviar email
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            $subject = 'Confirmación de Compra - Orden #' . $order->id;
            
            wp_mail($user_email, $subject, $invoice_html, $headers);
            
            // También notificar al administrador
            $admin_email = get_option('admin_email');
            wp_mail($admin_email, 'Nueva Compra - Orden #' . $order->id, $invoice_html, $headers);
         }
         
         /**
         * Notificar al administrador sobre un pago fallido
         */
         private function notify_payment_failure($payment_id) {
            global $wpdb;
            
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}eq_orders WHERE payment_id = %s",
                $payment_id
            ));
            
            if (!$order) {
                return;
            }
            
            // Email al administrador
            $admin_email = get_option('admin_email');
            $subject = 'Alerta: Pago Fallido - Orden #' . $order->id;
            
            $message = "
            <p>Se ha registrado un pago fallido para la orden #{$order->id}.</p>
            <p><strong>ID de Pago:</strong> {$payment_id}</p>
            <p><strong>Monto:</strong> " . hivepress()->woocommerce->format_price($order->amount) . "</p>
            <p><strong>Usuario:</strong> " . get_user_by('id', $order->user_id)->display_name . "</p>
            <p>Por favor, revise el panel de Stripe para más detalles.</p>
            ";
            
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            wp_mail($admin_email, $subject, $message, $headers);
         }
         
         /**
         * Generar un enlace de pago para compartir
         */
         public function generate_payment_link() {
            check_ajax_referer('eq_cart_public_nonce', 'nonce');
            
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'User not logged in']);
                return;
            }
            
            try {
                // Verificar si es un usuario privilegiado
                $is_privileged = current_user_can('administrator') || current_user_can('ejecutivo_de_ventas');
                
                // Obtener contexto
                $context = eq_get_active_context();
                
                // Si no hay contexto y es usuario privilegiado, no permitir
                if (!$context && $is_privileged) {
                    wp_send_json_error([
                        'message' => 'Debe seleccionar un lead y evento para generar un enlace de pago'
                    ]);
                    return;
                }
                
                // Crear checkout session
                $this->create_checkout_session();
                
            } catch (\Exception $e) {
                wp_send_json_error([
                    'message' => $e->getMessage()
                ]);
            }
         }
         
         /**
         * Verificar el estado de un pago
         */
         public function check_payment_status() {
            check_ajax_referer('eq_cart_public_nonce', 'nonce');
            
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'User not logged in']);
                return;
            }
            
            $payment_id = isset($_POST['payment_id']) ? sanitize_text_field($_POST['payment_id']) : '';
            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            
            if (!$payment_id && !$order_id) {
                wp_send_json_error(['message' => 'Missing payment ID or order ID']);
                return;
            }
            
            try {
                global $wpdb;
                
                // Buscar por payment_id o order_id
                if ($payment_id) {
                    $order = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}eq_orders WHERE payment_id = %s",
                        $payment_id
                    ));
                } else {
                    $order = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}eq_orders WHERE id = %d",
                        $order_id
                    ));
                    
                    $payment_id = $order ? $order->payment_id : '';
                }
                
                if (!$order) {
                    wp_send_json_error(['message' => 'Order not found']);
                    return;
                }
                
                // Verificar si es una sesión de checkout o un payment intent
                if (strpos($payment_id, 'cs_') === 0) {
                    // Es una sesión de checkout
                    $session = \Stripe\Checkout\Session::retrieve($payment_id);
                    
                    wp_send_json_success([
                        'status' => $session->payment_status,
                        'order_status' => $order->status,
                        'order_id' => $order->id
                    ]);
                    
                } else if (strpos($payment_id, 'pi_') === 0) {
                    // Es un payment intent
                    $payment_intent = \Stripe\PaymentIntent::retrieve($payment_id);
                    
                    wp_send_json_success([
                        'status' => $payment_intent->status,
                        'order_status' => $order->status,
                        'order_id' => $order->id
                    ]);
                    
                } else {
                    wp_send_json_error(['message' => 'Invalid payment ID format']);
                }
                
            } catch (\Exception $e) {
                wp_send_json_error([
                    'message' => $e->getMessage()
                ]);
            }
         }
         
         /**
         * Convertir precio formateado a centavos para Stripe
         */
         private function parse_amount($formatted_price) {
            // Eliminar el símbolo de moneda, separador de miles y cualquier otro carácter no numérico
            $amount = preg_replace('/[^0-9\.]/', '', $formatted_price);
            
            // Convertir a float
            $amount = floatval($amount);
            
            // Convertir a centavos (multiplicar por 100)
            return round($amount * 100);
         }
         
         /**
         * Obtener el ID del carrito activo
         */
         private function get_active_cart_id() {
            $user_id = get_current_user_id();
            
            // Primero buscar en user_meta
            $cart_id = get_user_meta($user_id, 'eq_active_cart_id', true);
            
            if ($cart_id) {
                return $cart_id;
            }
            
            // Si no hay en user_meta, buscar el más reciente
            global $wpdb;
            
            return $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eq_carts 
                WHERE user_id = %d AND status = 'active'
                ORDER BY created_at DESC LIMIT 1",
                $user_id
            ));
         }
         
         /**
         * Obtener URL de la página del carrito
         */
         private function get_cart_url() {
            $cart_page_id = get_option('eq_cart_page_id');
            
            if (!$cart_page_id) {
                return home_url();
            }
            
            return get_permalink($cart_page_id);
         }
}