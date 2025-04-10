<?php
/**
 * Manejador de generación de PDF
 */
defined('ABSPATH') || exit;

// include autoloader
require_once EQ_CART_PLUGIN_DIR . 'vendor/dompdf/autoload.inc.php';

class Event_Quote_Cart_PDF_Handler {
    
    /**
     * Generar PDF de cotización
     */
    public function generate_quote_pdf() {
        // Verificar nonce y permisos
        check_ajax_referer('eq_cart_public_nonce', 'nonce');
        
        if (!eq_can_view_quote_button()) {
            wp_send_json_error('Unauthorized');
        }
		
		    global $wpdb;

        
        try {
            // Obtener items detallados del carrito
$cart_items = eq_get_cart_items();

if (empty($cart_items)) {
    throw new Exception('No items in cart');
}

// Calcular totales
$totals = eq_calculate_cart_totals($cart_items);
			
			
// Obtener contexto activo
$context = eq_get_active_context();

// Si no hay contexto pero hay items, intentar obtener los datos del carrito
if (!$context && !empty($cart_items)) {
    $cart = eq_get_active_cart();
    
    if ($cart && !empty($cart->lead_id) && !empty($cart->event_id)) {
        global $wpdb;
        
        // Obtener información del lead
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jet_cct_leads 
            WHERE _ID = %d",
            $cart->lead_id
        ));
        
        // Obtener información del evento
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jet_cct_eventos 
            WHERE _ID = %d",
            $cart->event_id
        ));
        
        if ($lead && $event) {
            $context = [
                'lead' => $lead,
                'event' => $event
            ];
        }
    }
}
			
// Obtener el carrito activo para usar en la inserción de la cotización
$cart = eq_get_active_cart();

// Generar HTML para el PDF
$html = $this->generate_pdf_html($cart_items, $totals, $context);
            
            // Usar DOMPDF para convertir HTML a PDF
        if (class_exists('Dompdf\Dompdf')) {
            // Configurar opciones para permitir carga de imágenes
            $options = new \Dompdf\Options();
			$options->set('isRemoteEnabled', true);
			$dompdf = new \Dompdf\Dompdf($options);
            
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
                
            // Guardar el PDF en uploads
            $upload_dir = wp_upload_dir();
            // Crear carpeta para el plugin si no existe
            $plugin_dir = $upload_dir['basedir'] . '/event-quote-cart';
            if (!file_exists($plugin_dir)) {
                wp_mkdir_p($plugin_dir);
            }
			
            // Crear carpeta para el usuario si no existe
            $user_id = get_current_user_id();
            $user_dir = $plugin_dir . '/' . $user_id;
            if (!file_exists($user_dir)) {
                wp_mkdir_p($user_dir);
            }
			
            // Nombre del archivo
            $filename = 'quote_' . date('Y-m-d_H-i-s') . '.pdf';
            $pdf_path = $user_dir . '/' . $filename;
            $pdf_url = $upload_dir['baseurl'] . '/event-quote-cart/' . $user_id . '/' . $filename;
                
            // Guardar el archivo
file_put_contents($pdf_path, $dompdf->output());

// Registrar la cotización en la base de datos
$quote_data = array(
    'cart_id' => $cart->id,
    'lead_id' => !empty($cart->lead_id) ? $cart->lead_id : null,
    'event_id' => !empty($cart->event_id) ? $cart->event_id : null,
    'user_id' => get_current_user_id(),
    'pdf_url' => $pdf_url,
    'pdf_path' => $pdf_path,
    'status' => 'active',
    'nombre_pdf' => 'Quote_' . date('Y-m-d_H-i-s')
);

$wpdb->insert(
    $wpdb->prefix . 'eq_quotes',
    $quote_data,
    array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
);

$quote_id = $wpdb->insert_id;
    
// Devolver URL del PDF y el ID de la cotización
wp_send_json_success(array(
    'pdf_url' => $pdf_url,
    'quote_id' => $quote_id,
    'message' => 'Quote generated successfully'
));
        } else{
            throw new Exception('DOMPDF not available');
        }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
 * Generar HTML para el PDF
 */
private function generate_pdf_html($cart_items, $totals, $context = null) {
    // Preparar datos
    $site_name = get_bloginfo('name');
    $date = date_i18n(get_option('date_format'));
    $user = wp_get_current_user();
    
    // Obtener datos detallados
    $detailed_items = $this->get_detailed_cart_items();
    
    // Iniciar buffer de salida
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Quote - <?php echo esc_html($site_name); ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                color: #333;
                line-height: 1.5;
                margin: 0;
                padding: 20px;
            }
            .header {
				text-align: center;
				margin-bottom: 30px;
				padding: 0; 
				border-bottom: 1px solid #ccc;
				margin-left: -20px;
				margin-right: -20px;
				margin-top: -20px;
				width: calc(100% + 40px);
			}
			.header img {
				width: 100%;
				height: auto;
				display: block; 
				margin: 0;
				padding: 0;
			}
			.header p {
				margin: 10px 0;
				padding: 0 20px;
			}
            .header h1 {
                color: #444;
                font-size: 24px;
                margin: 0;
            }
            .quote-info {
                margin-bottom: 20px;
            }
            .quote-info p {
                margin: 5px 0;
            }
            .greeting {
                margin-bottom: 20px;
                font-style: italic;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            th, td {
                padding: 8px;
                text-align: left;
                border: 1px solid #ddd;
                vertical-align: top;
            }
            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }
            .description {
                font-size: 11px;
                color: #666;
            }
            .extras-list {
                font-size: 11px;
                margin: 5px 0 0 0;
                padding-left: 15px;
            }
            .totals-table {
                width: 300px;
                margin-left: auto;
                margin-top: 30px;
            }
            .totals-table td {
                text-align: right;
            }
            .total-row {
                font-weight: bold;
                font-size: 14px;
                background-color: #f9f9f9;
            }
            .footer {
                margin-top: 50px;
                font-size: 10px;
                color: #999;
                text-align: center;
                border-top: 1px solid #eee;
                padding-top: 20px;
            }
            .vendor-info {
                margin-top: 30px;
                border-top: 1px solid #eee;
                padding-top: 10px;
            }
        </style>
    </head>
    <body>
    <div class="header">
    <?php 
    // Usar imagen en lugar del texto del encabezado
    $banner_path = EQ_CART_PLUGIN_DIR . 'assets/images/banner-pdf.jpg';
    
    $image_data_url = $this->ImageToDataUrl($banner_path);
    if ($image_data_url) {
        ?>
        <img src="<?php echo $image_data_url; ?>" alt="<?php echo esc_attr($site_name); ?>">
    <?php } else { ?>
    <?php } ?>
<p>Fecha: <?php echo date_i18n(get_option('date_format')); ?></p>
</div>
    
    <div class="quote-info">
        <?php if ($context && $context['lead']): ?>
            <p><strong>Cliente:</strong> <?php echo esc_html($context['lead']->lead_nombre . ' ' . $context['lead']->lead_apellido); ?></p>
            <?php if ($context['event'] && !empty($context['event']->tipo_de_evento)): ?>
                <p><strong>Tipo de Evento:</strong> <?php echo esc_html($context['event']->tipo_de_evento); ?></p>
            <?php endif; ?>
        <?php else: ?>
            <p><strong>Atención:</strong> <?php echo esc_html($user->display_name); ?></p>
        <?php endif; ?>
<p><strong>Fecha de Evento:</strong> <?php echo date_i18n(get_option('date_format'), strtotime($detailed_items[0]->date)); ?></p>
        <?php if ($context && $context['event'] && !empty($context['event']->ubicacion_evento)): ?>
            <p><strong>Ciudad del Evento:</strong> <?php echo esc_html($context['event']->ubicacion_evento); ?></p>
        <?php endif; ?>
<p><strong>Referencia:</strong> COT-<?php echo date('Ymd') . '-' . get_current_user_id(); ?></p>
    </div>
    
    <?php if ($context && $context['lead']): ?>
        <div class="greeting">
            <p>Estimado <?php echo esc_html($context['lead']->lead_nombre); ?>, con gran placer te comparto esta información esperando cumpla con los requerimientos para tu evento.</p>
        </div>
    <?php elseif (eq_can_view_quote_button()): ?>
        <div class="greeting">
            <p>Estimado <?php echo esc_html($user->display_name); ?>, gracias por tu interés. Te comparto esta información esperando cumpla con tus requerimientos.</p>
        </div>
    <?php endif; ?>
    
    <table>
        <thead>
            <tr>
                <th>SERVICIO</th>
                <th>DESCRIPCIÓN</th>
                <th>CANTIDAD</th>
                <th>PRECIO UNITARIO</th>
                <th>SUB-TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Procesar cada item
            foreach ($detailed_items as $item): 
                // Separar extras con descripción y los de tipo variable
                $extras_with_desc = array();
                $variable_extras = array();
                $extras_without_desc = array();
                
                foreach ($item->extras as $extra) {
                    if ($extra['is_variable']) {
                        $variable_extras[] = $extra;
                    } elseif ($extra['has_description']) {
                        $extras_with_desc[] = $extra;
                    } else {
                        $extras_without_desc[] = $extra;
                    }
                }
                
                // 1. Fila principal del servicio
                ?>
                <tr>
                    <td><?php echo esc_html($item->title); ?></td>
                    <td class="description">
<?php echo nl2br(esc_html($item->description)); ?>

                        
                        
                    </td>
                    <td><?php echo esc_html($item->quantity); ?></td>
                    <td><?php echo hivepress()->woocommerce->format_price($item->base_price); ?></td>
                    <td><?php echo hivepress()->woocommerce->format_price($item->base_price * $item->quantity); ?></td>
                </tr>
                
                <?php 
                // 2. Filas para extras con descripción
foreach ($extras_with_desc as $extra): 
    // Calcular precio según el tipo de extra
    $extra_price = 0;
    $display_quantity = $item->quantity; // Por defecto, mostrar la cantidad del ítem principal
    
    switch($extra['type']) {
        case 'per_quantity':
            // Para extras tipo per_quantity, precio unitario × cantidad del ítem
            $extra_price = $extra['price'] * $item->quantity;
            break;
            
        case 'per_order':
        case 'per_booking':
        case 'per_item': // Tratar per_item igual que per_order/per_booking
            // Para extras tipo per_order/per_item, mostrar solo el precio sin multiplicar
            $extra_price = $extra['price'];
            // Para estos tipos, mostramos "1" como cantidad para claridad
            $display_quantity = 1;
            break;
            
        default:
            // Si no hay tipo especificado, tratarlo como per_quantity
            $extra_price = $extra['price'] * $item->quantity;
    }
?>
    <tr>
        <td><?php echo esc_html($extra['name']); ?> by <?php echo esc_html($item->title); ?></td>
<td class="description"><?php echo nl2br(esc_html($extra['description'])); ?></td>		
        <td><?php echo esc_html($display_quantity); ?></td>
        <td><?php echo hivepress()->woocommerce->format_price($extra['price']); ?></td>
        <td><?php echo hivepress()->woocommerce->format_price($extra_price); ?></td>
    </tr>
<?php endforeach; ?>
			
			<?php 
// 2.5 Filas para extras sin descripción
foreach ($extras_without_desc as $extra): 
    // Calcular precio según el tipo de extra
    $extra_price = 0;
    $display_quantity = $item->quantity; // Por defecto, mostrar la cantidad del ítem principal
    
    switch($extra['type']) {
        case 'per_quantity':
            // Para extras tipo per_quantity, precio unitario × cantidad del ítem
            $extra_price = $extra['price'] * $item->quantity;
            break;
            
        case 'per_order':
        case 'per_booking':
        case 'per_item': // Tratar per_item igual que per_order/per_booking
            // Para extras tipo per_order/per_item, mostrar solo el precio sin multiplicar
            $extra_price = $extra['price'];
            // Para estos tipos, mostramos "1" como cantidad para claridad
            $display_quantity = 1;
            break;
            
        default:
            // Si no hay tipo especificado, tratarlo como per_quantity
            $extra_price = $extra['price'] * $item->quantity;
    }
?>
    <tr>
        <td><?php echo esc_html($extra['name']); ?> by <?php echo esc_html($item->title); ?></td>
        <td class="description"></td>
        <td><?php echo esc_html($display_quantity); ?></td>
        <td><?php echo hivepress()->woocommerce->format_price($extra['price']); ?></td>
        <td><?php echo hivepress()->woocommerce->format_price($extra_price); ?></td>
    </tr>
<?php endforeach; ?>
                
                <?php 
                // 3. Filas para extras de tipo variable
                foreach ($variable_extras as $extra): 
                    $extra_price = $extra['price'] * $extra['quantity'];
                ?>
                    <tr>
                        <td><?php echo esc_html($extra['name']); ?> by <?php echo esc_html($item->title); ?></td>
                        <td class="description">
                            <?php if ($extra['has_description']): ?>
                                <?php echo esc_html($extra['description']); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($extra['quantity']); ?></td>
                        <td><?php echo hivepress()->woocommerce->format_price($extra['price']); ?></td>
                        <td><?php echo hivepress()->woocommerce->format_price($extra_price); ?></td>
                    </tr>
                <?php endforeach; ?>
                
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <table class="totals-table">
        <tr>
            <td>SUB TOTAL:</td>
            <td><?php echo esc_html($totals['subtotal']); ?></td>
        </tr>
        <tr>
            <td>TAX:</td>
            <td><?php echo esc_html($totals['tax']); ?></td>
        </tr>
        <tr class="total-row">
            <td>TOTAL:</td>
            <td><?php echo esc_html($totals['total']); ?></td>
        </tr>
    </table>
    
    <?php if (eq_can_view_quote_button()): ?>
        <div class="vendor-info">
            <p><strong>ATENDIDO POR:</strong> <?php echo esc_html($user->display_name); ?> CONTACTO: 8444-550-550</p>
            <p>Precios sujetos a cambios sin previo aviso y sujetos a disponibilidad</p>
        </div>
    <?php endif; ?>
    
    <div class="footer">
    <p>Esta cotización es válida por 30 días a partir de la fecha de emisión.</p>
    <p>¡Gracias por elegir <?php echo esc_html($site_name); ?>!</p>
</div>
</body>
    </html>
    <?php
    return ob_get_clean();
}
    
    /**
     * Enviar cotización por email
     */
    public function send_quote_email() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
    }
    
    // Obtener contexto y datos del lead primero
$context = eq_get_active_context();
$user = wp_get_current_user();

// Primero verificar si hay un email en el POST
$email_from_post = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

// Obtener el email del lead como respaldo
$lead_email = '';
if ($context && isset($context['lead']) && $context['lead']) {
    $lead = $context['lead'];
    if (!empty($lead->lead_e_mail)) {
        $lead_email = sanitize_email($lead->lead_e_mail);
    }
}

// Usar el email del POST si existe y es válido, sino usar el del lead
if (!empty($email_from_post) && is_email($email_from_post)) {
    $email = $email_from_post;
} else if (!empty($lead_email) && is_email($lead_email)) {
    $email = $lead_email;
} else {
    wp_send_json_error('Dirección de email inválida proporcionada');
    return;
}
    
    try {
        // Obtener contexto del carrito
$lead_name = "";
if ($context && isset($context['lead']) && $context['lead']) {
    $lead_name = $context['lead']->lead_nombre;
}

// Obtener items del carrito para determinar si hay uno o varios
$cart_items = eq_get_cart_items();
$product_name = "";

if (count($cart_items) == 1) {
    // Si hay un solo item, usar su título
    $product_name = $cart_items[0]->title;
} else {
    // Si hay múltiples items, mencionar la fecha del evento
    $event_date = "";
    if ($context && isset($context['event'])) {
        $event = $context['event'];
        
        if ($event && !empty($event->fecha_de_evento)) {
            $event_date = date_i18n(get_option('date_format'), is_numeric($event->fecha_de_evento) ? $event->fecha_de_evento : strtotime($event->fecha_de_evento));
            $product_name = "el evento programado para el " . $event_date;
        } else {
            $product_name = "los productos seleccionados";
        }
    } else {
        $product_name = "los productos seleccionados";
    }
}
        
        // Generar PDF
        $pdf_data = $this->generate_pdf_data();
        
        // Preparar mensaje personalizado
$subject = sprintf('🎉 ¡Tu cotización de %s está lista! Hagamos de tu evento algo inolvidable ✨', $product_name);
        
        $phone_number = "+528444550550"; // Número de contacto
        $user_email = $user->user_email; // Email del usuario que cotiza
        
        $message = sprintf('**Hola %s,**

Con gran placer te comparto la propuesta de %s, espero sea de tu completo agrado.
  
✨ **Imagina un evento sin preocupaciones, donde cada detalle está cuidado para que tú solo disfrutes.** ✨ 
  
En **Reservas Events**, sabemos que cada celebración es única, y por eso hemos preparado una cotización personalizada para ti. En el archivo adjunto encontrarás todos los detalles sobre nuestros servicios y lo que podemos ofrecerte para hacer de tu evento un momento inolvidable. 
  
📌 **¿Qué encontrarás en la cotización?** 

✅ Servicios incluidos y opciones personalizadas  
✅ Beneficios exclusivos al reservar con nosotros  
✅ Detalles sobre disponibilidad y próximos pasos 
  
Si tienes alguna duda o necesitas ajustes, estaré encantado de ayudarte a afinar cada detalle. 
  
📞 **Hablemos**: Responde a este correo o contáctame directamente al %s.  

📆 **Asegura tu fecha**: La disponibilidad es limitada, así que te recomiendo confirmar lo antes posible. 
  
¡Espero tu respuesta y espero poder ser parte de tu gran celebración! 
  
**Saludos cordiales,** 
%s  
📍 **Reservas Events** 
📧 contacto@reservas.events | 📞 +528444550550 | 🌐 https://reservas.events',
            $lead_name ?: 'cliente',
            $product_name,
            $phone_number,  // Añadir este argumento
            $user->display_name
        );
        
        // Enviar email con adjunto
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // Generar PDF y adjuntarlo
        $upload_dir = wp_upload_dir();
        $filename = 'cotizacion_' . get_current_user_id() . '_' . time() . '.pdf';
        $pdf_path = $upload_dir['path'] . '/' . $filename;
        
        // Guardar el archivo
        file_put_contents($pdf_path, $pdf_data);
        
        // Adjuntar y enviar
        $attachments = array($pdf_path);
        
        // Convertir el mensaje de formato markdown a HTML
        $message_html = $this->markdown_to_html($message);
        
        $sent = wp_mail($email, $subject, $message_html, $headers, $attachments);
        
        // Eliminar el archivo temporal
        @unlink($pdf_path);
        
        if ($sent) {
            wp_send_json_success('Cotización enviada exitosamente');
        } else {
            throw new Exception('Error al enviar el email');
        }
        
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

/**
 * Función auxiliar para convertir markdown a HTML
 */
private function markdown_to_html($text) {
    // Convertir encabezados
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/✨ (.*?) ✨/s', '<p style="font-style:italic;color:#cbb881;">✨ $1 ✨</p>', $text);
    
    // Convertir listas
    $text = preg_replace('/✅ (.*?)$/m', '<li style="list-style-type: none;">✅ $1</li>', $text);
    
    // Convertir saltos de línea
    $text = nl2br($text);
    
    // Reemplazar múltiples <br> seguidos por uno solo + espacio
    $text = preg_replace('/<br \/>(\s*<br \/>)+/', '<br><br>', $text);
    
    return '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">' . $text . '</div>';
}
    
    /**
     * Generar datos del PDF para adjuntar
     */
    private function generate_pdf_data() {
        // Obtener items del carrito
        $cart_items = eq_get_cart_items();
        
        if (empty($cart_items)) {
            throw new Exception('No items in cart');
        }
        
        // Calcular totales
        $totals = eq_calculate_cart_totals($cart_items);
        
        // Generar HTML para el PDF
        $html = $this->generate_pdf_html($cart_items, $totals);
        
        // Usar DOMPDF para convertir HTML a PDF
        if (!class_exists('Dompdf\Dompdf')) {
            throw new Exception('DOMPDF not available');
        }
        
        // Configurar opciones para permitir carga de imágenes
       $options = new \Dompdf\Options();
$options->set('isRemoteEnabled', true);
$dompdf = new \Dompdf\Dompdf($options);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();
    }
    
   public function generate_whatsapp_link() {
    check_ajax_referer('eq_cart_public_nonce', 'nonce');
    
    if (!eq_can_view_quote_button()) {
        wp_send_json_error('Unauthorized');
    }
    
    try {
    // Obtener contexto activo
    $context = eq_get_active_context();
    $user = wp_get_current_user();
    
    $lead_name = "cliente";
    $lead_phone = "";
    
    // Obtener datos del lead si existe
    if ($context && isset($context['lead'])) {
        $lead = $context['lead'];
        
        if ($lead) {
            $lead_name = $lead->lead_nombre;
            $lead_phone = $lead->lead_celular;
            
            // Limpiar el número de teléfono (solo dígitos)
            $lead_phone = preg_replace('/[^0-9]/', '', $lead_phone);
            
            // Asegurar formato internacional (quitar el + inicial si existe)
            if (substr($lead_phone, 0, 1) === '+') {
                $lead_phone = substr($lead_phone, 1);
            }
        }
    }
        
        // Obtener URL de cotización
        $quote_view_url = home_url('/quote-view/');
        
        // Preparar mensaje personalizado para WhatsApp
        $message = sprintf(
            __('¡Hola! Qué tal %s, soy %s integrante del equipo de Planner\'s de Reservas.events. Me reporto para saludarte y ayudarte con el servicio. ¿Tienes alguna pregunta sobre la cotización?', 'event-quote-cart'),
            $lead_name,
            $user->display_name
        );
        
        // Generar link de WhatsApp
        $whatsapp_link = 'https://wa.me/' . ($lead_phone ? $lead_phone : '') . '?text=' . urlencode($message);
        
        wp_send_json_success(array(
            'whatsapp_link' => $whatsapp_link,
            'lead_phone' => $lead_phone, // Enviar también el teléfono para permitir edición
            'message' => $message // Enviar el mensaje para posible preview
        ));
        
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
	
	/**
 * Obtiene datos completos de los listings y extras para el PDF
 */
private function get_detailed_cart_items() {
    // Obtener items básicos del carrito
    $cart_items = eq_get_cart_items();
    $detailed_items = array();
    
    foreach ($cart_items as $item) {
        // Obtener datos completos del listing
        $listing_id = $item->listing_id;
        $listing = get_post($listing_id);
        
        $form_data = json_decode($item->form_data, true);

$detailed_item = (object) array(
    'id' => $item->id,
    'listing_id' => $listing_id,
    'title' => $item->title,
    'description' => wp_strip_all_tags(get_post_field('post_content', $listing_id)),
    'image' => $item->image,
    'date' => $item->date,
    'quantity' => $item->quantity,
    'price_formatted' => $item->price_formatted,
    'total_price' => isset($item->total_price) ? $item->total_price : 0,
    // Usar el precio base almacenado en form_data en lugar de obtenerlo nuevamente
    'base_price' => isset($form_data['base_price']) ? floatval($form_data['base_price']) : 
                   floatval(get_post_meta($listing_id, 'hp_price', true)),
    'extras' => array()
);
        
        // Procesar extras con detalles adicionales
        if (!empty($item->extras)) {
            // Obtener metadatos completos de los extras
            $listing_extras = get_post_meta($listing_id, 'hp_price_extras', true);
            
            foreach ($item->extras as $extra) {
                $extra_id = isset($extra['id']) ? $extra['id'] : '';
                $extra_detail = null;
                
                // Buscar datos completos del extra en los metadatos
                if (is_array($listing_extras) && isset($listing_extras[$extra_id])) {
                    $extra_detail = $listing_extras[$extra_id];
                }
                
                // Añadir datos completos del extra
                $extra_data = array(
                    'id' => $extra_id,
                    'name' => isset($extra['name']) ? $extra['name'] : '',
                    'price' => isset($extra['price']) ? $extra['price'] : 0,
                    'quantity' => isset($extra['quantity']) ? $extra['quantity'] : 1,
                    'type' => isset($extra['type']) ? $extra['type'] : '',
                    'description' => isset($extra_detail['description']) ? $extra_detail['description'] : '',
                    'has_description' => isset($extra_detail['description']) && !empty($extra_detail['description']),
                    'is_variable' => (isset($extra['type']) && $extra['type'] === 'variable_quantity')
                );
                
                $detailed_item->extras[] = $extra_data;
            }
        }
        
        $detailed_items[] = $detailed_item;
    }
    
    return $detailed_items;
}
	
	/**
 * Convierte una imagen a formato base64 para incluir en HTML
 */
private function ImageToDataUrl(String $filename) {
    if(!file_exists($filename))
        return false;
    
    $mime = mime_content_type($filename);
    if($mime === false) 
        return false;

    $raw_data = file_get_contents($filename);
    if(empty($raw_data))
        return false;
    
    return "data:{$mime};base64," . base64_encode($raw_data);
}
}