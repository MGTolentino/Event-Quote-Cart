<?php
/**
 * Contract Handler Class
 */
defined('ABSPATH') || exit;

// include autoloader
require_once EQ_CART_PLUGIN_DIR . 'vendor/dompdf/autoload.inc.php';

class Event_Quote_Cart_Contract_Handler {
    
    /**
     * Generate contract PDF
     */
    public function generate_contract_pdf() {
        // Verificar nonce y permisos
        check_ajax_referer('eq_cart_public_nonce', 'nonce');
        
        if (!eq_can_view_quote_button()) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        
        try {
            // Default logo URL (will be used as fallback)
            $default_logo_url = EQ_CART_PLUGIN_URL . 'assets/contract-logo.png';
            
            // Obtener datos del formulario
            $contract_data = $this->sanitize_contract_data($_POST);
            $contract_data['default_logo_url'] = $default_logo_url;
            
            // Obtener items del carrito con detalles completos
            $cart_items = $this->get_detailed_cart_items();
            if (empty($cart_items)) {
                throw new Exception('No items in cart');
            }
            
            // Calcular totales
            $totals = eq_calculate_cart_totals($cart_items);
            
            // Obtener contexto activo
            $context = eq_get_active_context();
            
            // Si no hay contexto, intentar obtenerlo del carrito
            if (!$context && !empty($cart_items)) {
                $cart = eq_get_active_cart();
                if ($cart && !empty($cart->lead_id) && !empty($cart->event_id)) {
                    $lead = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}jet_cct_leads WHERE _ID = %d",
                        $cart->lead_id
                    ));
                    
                    $event = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
                        $cart->event_id
                    ));
                    
                    if ($lead && $event) {
                        $context = array('lead' => $lead, 'event' => $event);
                    }
                }
            }
            
            // Obtener datos del vendor si está disponible
            $vendor_data = $this->get_vendor_contract_data();
            
            // Debug logging for vendor data
            error_log('CONTRACT DEBUG - Vendor data: ' . print_r($vendor_data, true));
            if ($vendor_data) {
                error_log('CONTRACT DEBUG - Logo URL: ' . ($vendor_data['logo_url'] ?? 'NOT SET'));
                error_log('CONTRACT DEBUG - Logo Base64 length: ' . strlen($vendor_data['logo_base64'] ?? ''));
            } else {
                error_log('CONTRACT DEBUG - Vendor data is NULL');
            }
            
            // Generar HTML del contrato
            $html = $this->generate_contract_html($contract_data, $cart_items, $totals, $context, $vendor_data);
            
            // Crear directorio si no existe
            $user_id = get_current_user_id();
            $upload_dir = wp_upload_dir();
            $plugin_upload_dir = $upload_dir['basedir'] . '/event-quote-cart/' . $user_id . '/contracts/';
            
            if (!file_exists($plugin_upload_dir)) {
                wp_mkdir_p($plugin_upload_dir);
            }
            
            // Generar PDF con optimizaciones y manejo de errores
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultPaperSize', 'A4');
            $options->set('defaultPaperOrientation', 'portrait');
            // Optimizaciones de rendimiento
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', true);  // Habilitar PHP para footer
            $options->set('debugCss', false);
            $options->set('debugKeepTemp', false);
            $options->set('debugPng', false);
            $options->set('defaultMediaType', 'print');
            $options->set('chroot', ABSPATH);
            // Habilitar PHP para script de footer
            $options->set('enable_php', true);
            $options->set('enable_javascript', false);
            
            $dompdf = new Dompdf\Dompdf($options);
            
            // Limpiar HTML antes de procesarlo
            $html = $this->clean_html_for_dompdf($html);
            
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            
            try {
                $dompdf->render();
            } catch (Exception $e) {
                // Si falla, intentar con HTML simplificado
                $html = $this->generate_simplified_contract_html($contract_data, $cart_items, $totals, $context, $vendor_data);
                $dompdf = new Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
            }
            
            // Generar nombre único para el archivo
            $filename = 'Contract_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.pdf';
            $file_path = $plugin_upload_dir . $filename;
            $file_url = $upload_dir['baseurl'] . '/event-quote-cart/' . $user_id . '/contracts/' . $filename;
            
            // Guardar PDF
            file_put_contents($file_path, $dompdf->output());
            
            // Verificar que tenemos lead_id y event_id requeridos
            if (!$context || !$context['lead'] || !$context['event']) {
                throw new Exception('Missing lead or event context for contract');
            }
            
            // Guardar registro en base de datos
            $contract_record = array(
                'quote_id' => null, // Puede relacionarse con un quote si existe
                'lead_id' => $context['lead']->_ID,
                'event_id' => $context['event']->_ID,
                'user_id' => $user_id,
                'pdf_url' => $file_url,
                'pdf_path' => $file_path,
                'contract_data' => json_encode($contract_data),
                'payment_schedule' => json_encode($contract_data['payment_schedule'] ?? []),
                'company_data' => json_encode($contract_data['company_data']),
                'bank_data' => json_encode($contract_data['bank_data']),
                'clauses' => $contract_data['contract_terms'],
                'total_amount' => floatval(str_replace(['$', ','], '', $totals['total'])),
                'status' => 'draft',
                'nombre_pdf' => $filename
            );
            
            $result = $wpdb->insert($wpdb->prefix . 'eq_contracts', $contract_record);
            
            if ($result === false) {
                throw new Exception('Error saving contract to database: ' . $wpdb->last_error);
            }
            
            $contract_id = $wpdb->insert_id;
            
            wp_send_json_success(array(
                'message' => 'Contract generated successfully',
                'contract_id' => $contract_id,
                'pdf_url' => $file_url,
                'filename' => $filename
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error generating contract: ' . $e->getMessage());
        }
    }
    
    /**
     * Sanitize contract data from form
     */
    private function sanitize_contract_data($post_data) {
        return array(
            'company_data' => array(
                'name' => sanitize_text_field($post_data['company_name'] ?? ''),
                'address' => sanitize_textarea_field($post_data['company_address'] ?? ''),
                'phone' => sanitize_text_field($post_data['company_phone'] ?? ''),
                'email' => sanitize_email($post_data['company_email'] ?? ''),
                'rfc' => sanitize_text_field($post_data['company_rfc'] ?? '')
            ),
            'client_data' => array(
                'name' => sanitize_text_field($post_data['client_name'] ?? ''),
                'phone' => sanitize_text_field($post_data['client_phone'] ?? ''),
                'email' => sanitize_email($post_data['client_email'] ?? ''),
                'business_name' => sanitize_text_field($post_data['client_business_name'] ?? ''),
                'business_address' => sanitize_textarea_field($post_data['client_business_address'] ?? '')
            ),
            'event_data' => array(
                'date' => sanitize_text_field($post_data['event_date'] ?? ''),
                'start_time' => sanitize_text_field($post_data['event_start_time'] ?? ''),
                'end_time' => sanitize_text_field($post_data['event_end_time'] ?? ''),
                'address' => sanitize_textarea_field($post_data['event_address'] ?? ''),
                'guests' => intval($post_data['event_guests'] ?? 0)
            ),
            'payment_schedule' => json_decode(stripslashes($post_data['payment_schedule'] ?? '[]'), true),
            'contract_terms' => wp_kses_post($post_data['contract_terms'] ?? ''), // Standard terms from VDP
            'additional_terms' => wp_kses_post($post_data['additional_terms'] ?? ''), // Additional terms from form
            'bank_data' => array(
                'bank_name' => sanitize_text_field($post_data['bank_name'] ?? ''),
                'account_number' => sanitize_text_field($post_data['bank_account'] ?? ''),
                'clabe' => sanitize_text_field($post_data['bank_clabe'] ?? ''),
                'account_holder' => sanitize_text_field($post_data['company_name'] ?? ''),
                'razon_social' => sanitize_text_field($post_data['razon_social'] ?? '')
            )
        );
    }
    
    /**
     * Generate contract HTML
     */
    private function generate_contract_html($contract_data, $cart_items, $totals, $context = null, $vendor_data = null) {
        $company = $contract_data['company_data'];
        $client = $contract_data['client_data'];
        $event = $contract_data['event_data'];
        $payment_schedule = $contract_data['payment_schedule'];
        $terms = $contract_data['contract_terms']; // Standard terms from VDP
        $additional_terms = $contract_data['additional_terms'] ?? ''; // Additional terms from form
        $bank = $contract_data['bank_data'];
        
        // Formatear fecha del evento
        $event_date_formatted = '';
        if (!empty($event['date'])) {
            $event_date_formatted = date_i18n('j \d\e F, Y', strtotime($event['date']));
        }
        
        // Formatear horario
        $event_time_formatted = '';
        if (!empty($event['start_time']) && !empty($event['end_time'])) {
            $event_time_formatted = date('g:i A', strtotime($event['start_time'])) . ' a ' . date('g:i A', strtotime($event['end_time']));
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Contrato de Servicios</title>
            <style>
                @page {
                    margin: 120px 30px 150px 30px; /* top right bottom left - Espacio para header y footer */
                }
                
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.4;
                    color: #333;
                    margin: 0;
                    padding: 0;
                }
                
                /* Header fijo en cada página */
                .fixed-header {
                    position: fixed;
                    top: -100px;
                    left: 0;
                    right: 0;
                    height: 80px;
                    padding: 10px 30px;
                }
                
                .fixed-header .logo-container {
                    float: left;
                    max-width: 50%;
                }
                
                .fixed-header .date-container {
                    float: right;
                    text-align: right;
                    font-weight: bold;
                    margin-top: 15px;
                }
                
                /* Footer fijo en cada página */
                .fixed-footer {
                    position: fixed;
                    bottom: -140px;
                    left: 0;
                    right: 0;
                    height: 100px;
                    padding: 20px 30px;
                    font-size: 10px;
                }
                
                .fixed-footer .signature-container {
                    width: 100%;
                    text-align: center;
                }
                
                .fixed-footer .signature-box {
                    display: inline-block;
                    width: 45%;
                    text-align: center;
                    vertical-align: top;
                    padding: 0 10px;
                }
                
                .fixed-footer .signature-line {
                    display: block;
                    width: 200px;
                    border-bottom: 1px solid #333;
                    margin: 20px auto 5px auto;
                    height: 1px;
                }
                
                .fixed-footer .client-name {
                    margin-bottom: 5px;
                    font-weight: bold;
                }
                
                /* Contenido principal */
                .main-content {
                    margin: 0;
                    padding: 20px;
                }
                
                /* Ocultar header y footer antiguos */
                .header {
                    display: none;
                }
                
                .contract-date {
                    display: none;
                }
                
                .contract-footer {
                    display: none;
                }
                
                /* Estilos del contenido */
                .section {
                    margin-bottom: 20px;
                    page-break-inside: auto;
                }
                .critical-section {
                    page-break-inside: avoid;
                }
                .section-title {
                    font-size: 16px;
                    font-weight: bold;
                    color: #2c3e50;
                    border-bottom: 1px solid #bdc3c7;
                    padding-bottom: 5px;
                    margin-bottom: 15px;
                }
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                .info-table td {
                    width: 50%;
                    padding: 15px;
                    border: 1px solid #bdc3c7;
                    vertical-align: top;
                    min-height: 120px;
                }
                .info-table .header {
                    background-color: #ecf0f1;
                    font-weight: bold;
                    text-align: center;
                    min-height: 40px;
                    padding: 15px;
                }
                .services-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                    page-break-inside: auto;
                }
                .services-table thead {
                    page-break-after: avoid;
                }
                .services-table tbody tr {
                    page-break-inside: avoid;
                }
                .services-table th,
                .services-table td {
                    border: 1px solid #bdc3c7;
                    padding: 8px;
                    text-align: left;
                }
                .services-table th {
                    background-color: #34495e;
                    color: white;
                    font-weight: bold;
                }
                .services-table .number {
                    text-align: right;
                }
                .totals-section {
                    margin-top: 20px;
                    text-align: right;
                }
                .total-row {
                    margin: 5px 0;
                }
                .total-row.final {
                    font-size: 14px;
                    font-weight: bold;
                    border-top: 2px solid #333;
                    padding-top: 5px;
                }
                .payment-schedule-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                }
                .payment-schedule-table th,
                .payment-schedule-table td {
                    border: 1px solid #bdc3c7;
                    padding: 8px;
                    text-align: center;
                }
                .payment-schedule-table th {
                    background-color: white;
                    color: black;
                    font-weight: bold;
                }
                .terms {
                    font-size: 10px;
                    line-height: 1.3;
                    text-align: justify;
                }
                .amount-in-words {
                    font-style: italic;
                    margin: 10px 0;
                }
                .bank-info {
                    background-color: #ecf0f1;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <!-- Header Fijo (aparecerá en cada página) -->
            <div class="fixed-header">
                <div class="logo-container">
                    <?php 
                    // Priority order: 1) Vendor Dashboard logo (Base64), 2) Default contract logo
                    $logo_src = '';
                    
                    error_log('CONTRACT DEBUG - In HTML generation');
                    error_log('CONTRACT DEBUG - Vendor data available: ' . (empty($vendor_data) ? 'NO' : 'YES'));
                    error_log('CONTRACT DEBUG - Vendor logo_base64 length: ' . strlen($vendor_data['logo_base64'] ?? ''));
                    error_log('CONTRACT DEBUG - Default logo URL: ' . ($contract_data['default_logo_url'] ?? 'NOT SET'));
                    
                    if (!empty($vendor_data['logo_base64'])) {
                        // Use Base64 encoded vendor logo
                        $logo_src = $vendor_data['logo_base64'];
                        error_log('CONTRACT DEBUG - Using vendor Base64 logo');
                    } elseif (!empty($contract_data['default_logo_url'])) {
                        // Convert default logo to Base64 as fallback
                        $default_logo_base64 = $this->convert_image_to_base64($contract_data['default_logo_url']);
                        $logo_src = !empty($default_logo_base64) ? $default_logo_base64 : $contract_data['default_logo_url'];
                        error_log('CONTRACT DEBUG - Using default logo (Base64 length: ' . strlen($default_logo_base64) . ')');
                    } else {
                        error_log('CONTRACT DEBUG - No logo available');
                    }
                    
                    if (!empty($logo_src)): 
                    ?>
                        <img src="<?php echo $logo_src; ?>" alt="Company Logo" style="max-height: 50px; max-width: 150px; object-fit: contain;">
                    <?php endif; ?>
                </div>
                <div class="date-container">
                    <?php echo date_i18n('j \d\e F Y'); ?>
                </div>
                <div style="clear: both;"></div>
            </div>
            
            <!-- Footer Fijo (aparecerá en cada página) -->
            <div class="fixed-footer">
                <div class="signature-container">
                    <div class="signature-box">
                        <div class="client-name"><?php echo esc_html($client['name']); ?></div>
                        <div class="signature-line"></div>
                        <strong>FIRMA DEL CONTRATANTE</strong>
                    </div>
                    <div class="signature-box">
                        <br>
                        <div class="signature-line"></div>
                        <strong>FIRMA DE LA EMPRESA</strong>
                    </div>
                </div>
            </div>
            
            <!-- Contenido Principal -->
            <div class="main-content">
                
            <!-- Datos de la Empresa y Contratante -->
            <div class="section">
                <table class="info-table">
                    <tr>
                        <td style="background-color: #ecf0f1; font-weight: bold; text-align: center; padding: 15px; border: 1px solid #bdc3c7;">Datos de la Empresa</td>
                        <td style="background-color: #ecf0f1; font-weight: bold; text-align: center; padding: 15px; border: 1px solid #bdc3c7;">Datos del Contratante</td>
                    </tr>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($company['name']); ?></strong><br>
                            <strong>Dirección:</strong> <?php echo nl2br(esc_html($company['address'])); ?><br>
                            <strong>Teléfono:</strong> <?php echo esc_html($company['phone']); ?><br>
                            <strong>E-mail:</strong> <?php echo esc_html($company['email']); ?>
                            <?php if ($company['rfc']): ?>
                                <br><strong>RFC:</strong> <?php echo esc_html($company['rfc']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($client['business_name'])): ?>
                                <strong><?php echo esc_html($client['business_name']); ?></strong><br>
                            <?php endif; ?>
                            <strong><?php echo esc_html($client['name']); ?></strong>
                            <?php if ($client['email']): ?>
                                <br><?php echo esc_html($client['email']); ?>
                            <?php endif; ?>
                            <?php if ($client['phone']): ?>
                                <br><?php echo esc_html($client['phone']); ?>
                            <?php endif; ?>
                            <?php if (!empty($client['business_address'])): ?>
                                <br><?php echo nl2br(esc_html($client['business_address'])); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Información del Evento -->
            <div class="section">
                <div class="section-title">Información del evento</div>
                <div style="padding: 10px; border: 1px solid #bdc3c7; background-color: #f8f9fa; margin-bottom: 10px;">
                    <strong>Fecha de Evento:</strong> <?php echo esc_html($event_date_formatted); ?><br>
                    <strong>Dirección del Evento:</strong> <?php echo nl2br(esc_html($event['address'])); ?>
                    <?php if ($event_time_formatted): ?>
                        <br><strong>Hora de inicio:</strong> <?php echo esc_html($event_time_formatted); ?>
                    <?php endif; ?>
                    <?php if ($event['guests']): ?>
                        <br><strong>Cantidad de Invitados:</strong> <?php echo intval($event['guests']); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Servicios Contratados -->
            <div class="section">
                <div class="section-title">Servicios Contratados</div>
                <table class="services-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Título</th>
                            <th style="width: 45%;">Descripción</th>
                            <th style="width: 10%;">Cantidad</th>
                            <th style="width: 15%;">Precio</th>
                            <th style="width: 15%;">Sub Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <?php 
                            // Calculate correct prices like in quote handler
                            $tax_rate = eq_get_woocommerce_tax_rate() ?: 16;
                            $tax_rate_decimal = $tax_rate / 100;
                            
                            // Si el item tiene precio base 0, usar ese precio base para el cálculo
                            if ($item->base_price == 0) {
                                $item_unit_price_without_tax = 0;
                                $item_subtotal = 0;
                            } else {
                                // Usar directamente el precio base del servicio principal (sin extras)
                                $item_unit_price_without_tax = $item->base_price;
                                $item_subtotal = $item->base_price * $item->quantity;
                            }
                            
                            // Dividir la descripción en chunks si es muy larga
                            $description_chunks = $this->split_long_text($item->description, 5);
                            $chunks_count = count($description_chunks);
                            $is_first_row = true;
                            ?>
                            
                            <?php foreach ($description_chunks as $chunk_index => $description_chunk): ?>
                                <?php
                                $row_classes = array();
                                if (!$is_first_row) {
                                    $row_classes[] = 'item-continuation';
                                }
                                if ($is_first_row && $chunks_count > 1) {
                                    $row_classes[] = 'has-continuation';
                                }
                                if ($chunk_index === $chunks_count - 1 && $chunks_count > 1) {
                                    $row_classes[] = 'last-of-group';
                                }
                                ?>
                                <!-- Main service row -->
                                <tr class="<?php echo esc_attr(implode(' ', $row_classes)); ?>">
                                    <td><strong><?php echo esc_html($item->title); ?></strong></td>
                                    <td>
                                        <?php echo nl2br(esc_html($description_chunk)); ?>
                                        
                                        <?php if ($is_first_row): ?>
                                            <br><br>
                                            <?php if (isset($item->is_date_range) && $item->is_date_range): ?>
                                                <strong>Fecha del evento:</strong> <?php echo esc_html($item->start_date); ?> a <?php echo esc_html($item->end_date); ?>
                                            <?php else: ?>
                                                <strong>Fecha del evento:</strong> <?php echo esc_html($item->date); ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="number"><?php echo $is_first_row ? esc_html($item->quantity) : ''; ?></td>
                                    <td class="number"><?php echo $is_first_row ? hivepress()->woocommerce->format_price($item_unit_price_without_tax) : ''; ?></td>
                                    <td class="number"><?php echo $is_first_row ? hivepress()->woocommerce->format_price($item_subtotal) : ''; ?></td>
                                </tr>
                                <?php $is_first_row = false; ?>
                            <?php endforeach; ?>
                            
                            <!-- Extra service rows -->
                            <?php if (!empty($item->extras)): ?>
                                <?php foreach ($item->extras as $extra): ?>
                                    <?php
                                    // Calculate extra pricing EXACTLY like in quote handler
                                    $extra_price = 0;
                                    $display_quantity = $item->quantity; // Por defecto, mostrar la cantidad del ítem principal
                                    
                                    // LÓGICA COMPLETA DEL QUOTE HANDLER
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
                                    
                                    // Cantidad final a mostrar
                                    if (isset($extra['display_quantity']) && $extra['display_quantity'] > 1) {
                                        $final_display_quantity = $extra['display_quantity'];
                                    } else {
                                        $final_display_quantity = $display_quantity;
                                    }
                                    
                                    // Dividir la descripción del extra si es muy larga
                                    $extra_description_chunks = !empty($extra['description']) ? 
                                        $this->split_long_text($extra['description'], 4) : array('');
                                    $extra_chunks_count = count($extra_description_chunks);
                                    $is_first_extra_row = true;
                                    ?>
                                    
                                    <?php foreach ($extra_description_chunks as $extra_chunk_index => $extra_chunk): ?>
                                        <?php
                                        $extra_row_classes = array('extra-service-row');
                                        if (!$is_first_extra_row) {
                                            $extra_row_classes[] = 'item-continuation';
                                        }
                                        if ($is_first_extra_row && $extra_chunks_count > 1) {
                                            $extra_row_classes[] = 'has-continuation';
                                        }
                                        if ($extra_chunk_index === $extra_chunks_count - 1 && $extra_chunks_count > 1) {
                                            $extra_row_classes[] = 'last-of-group';
                                        }
                                        ?>
                                        <tr class="<?php echo esc_attr(implode(' ', $extra_row_classes)); ?>">
                                            <td><?php echo $is_first_extra_row ? esc_html($extra['name']) . ' by ' . esc_html($item->title) : ''; ?></td>
                                            <td class="description"><?php echo nl2br(esc_html($extra_chunk)); ?></td>
                                            <td class="number"><?php echo $is_first_extra_row ? esc_html($final_display_quantity) : ''; ?></td>
                                            <td class="number"><?php echo $is_first_extra_row ? hivepress()->woocommerce->format_price($extra['price']) : ''; ?></td>
                                            <td class="number"><?php echo $is_first_extra_row ? hivepress()->woocommerce->format_price($extra_price) : ''; ?></td>
                                        </tr>
                                        <?php $is_first_extra_row = false; ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Totales -->
                <div class="totals-section">
                    <div class="total-row">Sub Total: <?php echo esc_html($totals['subtotal']); ?></div>
                    <div class="total-row">IVA: <?php echo esc_html($totals['tax']); ?></div>
                    <div class="total-row final">Total: <?php echo esc_html($totals['total']); ?></div>
                    
                    <div class="amount-in-words">
                        Valor del contrato, Importe Con Letra: (<?php echo esc_html($this->number_to_words($totals['total_raw'])); ?>)
                    </div>
                </div>
            </div>
            
            <!-- Programación de Pagos -->
            <?php if (!empty($payment_schedule)): ?>
                <div class="section">
                    <div class="section-title">Programación de Pagos</div>
                    <table class="payment-schedule-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cantidad</th>
                                <th>Fecha</th>
                                <th>Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $payment_pairs = array_chunk($payment_schedule, 2);
                            foreach ($payment_pairs as $pair): 
                            ?>
                                <tr>
                                    <td><?php echo isset($pair[0]) ? esc_html(date_i18n('j/m/Y', strtotime($pair[0]['date']))) : ''; ?></td>
                                    <td><?php echo isset($pair[0]) ? esc_html($pair[0]['amount_formatted']) : ''; ?></td>
                                    <td><?php echo isset($pair[1]) ? esc_html(date_i18n('j/m/Y', strtotime($pair[1]['date']))) : ''; ?></td>
                                    <td><?php echo isset($pair[1]) ? esc_html($pair[1]['amount_formatted']) : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Cláusulas -->
            <div class="section">
                <div class="section-title">Cláusulas</div>
                <div class="terms">
                    <?php echo nl2br(esc_html($terms)); ?>
                </div>
                
                <?php if (!empty($additional_terms)): ?>
                    <div class="section-title" style="margin-top: 20px;">Cláusulas Adicionales</div>
                    <div class="terms">
                        <?php echo nl2br(esc_html($additional_terms)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Datos Bancarios -->
            <?php if (!empty($bank['bank_name']) || !empty($bank['account_number'])): ?>
                <div class="section">
                    <div class="section-title">Datos Bancarios</div>
                    <div class="bank-info">
                        <?php if ($bank['bank_name']): ?>
                            <strong>Banco:</strong> <?php echo esc_html($bank['bank_name']); ?><br>
                        <?php endif; ?>
                        <?php if ($bank['account_number']): ?>
                            <strong>Cuenta:</strong> <?php echo esc_html($bank['account_number']); ?><br>
                        <?php endif; ?>
                        <?php if ($bank['clabe']): ?>
                            <strong>CLABE:</strong> <?php echo esc_html($bank['clabe']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($vendor_data['razon_social'])): ?>
                            <strong>Razón Social:</strong> <?php echo esc_html($vendor_data['razon_social']); ?><br>
                        <?php elseif (!empty($bank['razon_social'])): ?>
                            <strong>Razón Social:</strong> <?php echo esc_html($bank['razon_social']); ?><br>
                        <?php elseif ($bank['account_holder']): ?>
                            <strong>Razón Social:</strong> <?php echo esc_html($bank['account_holder']); ?><br>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            </div> <!-- Cierre de main-content -->
            
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get vendor contract data from Vendor Dashboard Pro
     */
    private function get_vendor_contract_data() {
        // Check if Vendor Dashboard Pro is active
        if (!class_exists('VDP_Contracts')) {
            return null;
        }
        
        // Get current user
        $user_id = get_current_user_id();
        if (!$user_id) {
            return null;
        }
        
        // Try to get vendor post for current user
        $vendor_posts = get_posts(array(
            'post_type' => 'vendor',
            'author' => $user_id,
            'post_status' => 'publish',
            'numberposts' => 1
        ));
        
        if (empty($vendor_posts)) {
            return null;
        }
        
        $vendor_id = $vendor_posts[0]->ID;
        
        // Get contract settings from Vendor Dashboard Pro
        $contracts_module = VDP_Contracts::get_instance();
        $contract_settings = $contracts_module->get_contract_settings($vendor_id);
        
        error_log('CONTRACT DEBUG - Vendor ID: ' . $vendor_id);
        error_log('CONTRACT DEBUG - Contract settings: ' . print_r($contract_settings, true));
        
        if (empty($contract_settings)) {
            error_log('CONTRACT DEBUG - Contract settings are empty');
            return null;
        }
        
        // Extract logo and razon social
        $logo_url = $contract_settings['company_data']['logo_url'] ?? '';
        $logo_base64 = '';
        
        error_log('CONTRACT DEBUG - Extracted logo URL: ' . $logo_url);
        
        // Convert logo to Base64 if it exists
        if (!empty($logo_url)) {
            error_log('CONTRACT DEBUG - Converting logo to Base64...');
            $logo_base64 = $this->convert_image_to_base64($logo_url);
            error_log('CONTRACT DEBUG - Base64 conversion result length: ' . strlen($logo_base64));
        } else {
            error_log('CONTRACT DEBUG - Logo URL is empty');
        }
        
        return array(
            'logo_url' => $logo_url,
            'logo_base64' => $logo_base64,
            'razon_social' => $contract_settings['company_data']['razon_social'] ?? ''
        );
    }

    /**
     * Convert image URL to Base64 data URI
     */
    private function convert_image_to_base64($image_url) {
        if (empty($image_url)) {
            return '';
        }
        
        try {
            // Handle both local paths and URLs
            if (strpos($image_url, 'http') === 0) {
                // It's a full URL, try to get the local path
                $upload_dir = wp_upload_dir();
                $base_url = $upload_dir['baseurl'];
                
                if (strpos($image_url, $base_url) === 0) {
                    // It's a local upload URL, convert to local path
                    $local_path = str_replace($base_url, $upload_dir['basedir'], $image_url);
                } else {
                    // External URL or assets folder
                    if (strpos($image_url, site_url()) === 0) {
                        // Local site URL, convert to filesystem path
                        $local_path = str_replace(site_url(), ABSPATH, $image_url);
                    } else {
                        // External URL, use file_get_contents with URL
                        $local_path = $image_url;
                    }
                }
            } else {
                // Assume it's already a local path
                $local_path = $image_url;
            }
            
            // Read the file
            if (strpos($local_path, 'http') === 0) {
                // External URL or if local conversion failed
                $image_data = file_get_contents($local_path);
            } else {
                // Local file
                if (!file_exists($local_path)) {
                    return '';
                }
                $image_data = file_get_contents($local_path);
            }
            
            if ($image_data === false) {
                return '';
            }
            
            // Get MIME type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            if (strpos($local_path, 'http') === 0) {
                // For URLs, try to determine from extension
                $extension = strtolower(pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION));
                $mime_types = array(
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif'
                );
                $mime_type = $mime_types[$extension] ?? 'image/jpeg';
            } else {
                $mime_type = $finfo->buffer($image_data);
            }
            
            // Create Base64 data URI
            $base64 = base64_encode($image_data);
            return 'data:' . $mime_type . ';base64,' . $base64;
            
        } catch (Exception $e) {
            error_log('Error converting image to Base64: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Convert number to words (Spanish)
     */
    private function number_to_words($amount) {
        // Now receiving raw number, no need to clean formatting
        $amount = floatval($amount);
        
        // Split into integer and decimal parts
        $integer_part = floor($amount);
        $decimal_part = round(($amount - $integer_part) * 100);
        
        // Convert integer part to words
        $words = $this->convert_number_to_words($integer_part);
        
        // Format final string
        if ($integer_part == 1) {
            return ucfirst($words) . ' Peso ' . sprintf('%02d', $decimal_part) . '/100 M.N.';
        } else {
            return ucfirst($words) . ' Pesos ' . sprintf('%02d', $decimal_part) . '/100 M.N.';
        }
    }
    
    /**
     * Convert number to words in Spanish
     */
    private function convert_number_to_words($number) {
        if ($number == 0) return 'cero';
        
        $units = array('', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve');
        $teens = array('diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve');
        $tens = array('', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa');
        $hundreds = array('', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos');
        
        if ($number < 10) {
            return $units[$number];
        } elseif ($number < 20) {
            return $teens[$number - 10];
        } elseif ($number < 100) {
            $ten = floor($number / 10);
            $unit = $number % 10;
            if ($ten == 2 && $unit > 0) {
                return 'veinti' . $units[$unit];
            }
            return $tens[$ten] . ($unit > 0 ? ' y ' . $units[$unit] : '');
        } elseif ($number < 1000) {
            $hundred = floor($number / 100);
            $remainder = $number % 100;
            if ($number == 100) return 'cien';
            return $hundreds[$hundred] . ($remainder > 0 ? ' ' . $this->convert_number_to_words($remainder) : '');
        } elseif ($number < 1000000) {
            $thousand = floor($number / 1000);
            $remainder = $number % 1000;
            $thousand_words = '';
            if ($thousand == 1) {
                $thousand_words = 'mil';
            } else {
                $thousand_words = $this->convert_number_to_words($thousand) . ' mil';
            }
            return $thousand_words . ($remainder > 0 ? ' ' . $this->convert_number_to_words($remainder) : '');
        } elseif ($number < 1000000000) {
            $million = floor($number / 1000000);
            $remainder = $number % 1000000;
            $million_words = '';
            if ($million == 1) {
                $million_words = 'un millón';
            } else {
                $million_words = $this->convert_number_to_words($million) . ' millones';
            }
            return $million_words . ($remainder > 0 ? ' ' . $this->convert_number_to_words($remainder) : '');
        }
        
        return 'número demasiado grande';
    }
    
    /**
     * Clean HTML for DOMPDF to avoid rendering errors
     */
    private function clean_html_for_dompdf($html) {
        // Remove problematic CSS properties but keep position fixed for footer
        // Skip position fixed removal to allow footer CSS to work
        // $html = preg_replace('/position\s*:\s*fixed\s*;?/i', '', $html); // Disabled for footer
        $html = preg_replace('/position\s*:\s*absolute\s*;?/i', '', $html);
        
        // Ensure all tables are properly closed
        $html = preg_replace('/<table([^>]*)>/i', '<table$1>', $html);
        
        // Remove empty table cells that might cause issues
        $html = preg_replace('/<td>\s*<\/td>/i', '<td>&nbsp;</td>', $html);
        $html = preg_replace('/<th>\s*<\/th>/i', '<th>&nbsp;</th>', $html);
        
        // Fix nested tables if any
        $html = str_replace('display: table-cell', 'display: inline-block', $html);
        $html = str_replace('display: table-row', 'display: block', $html);
        
        return $html;
    }
    
    /**
     * Generate simplified contract HTML for fallback
     */
    private function generate_simplified_contract_html($contract_data, $cart_items, $totals, $context, $vendor_data = null) {
        $company = $contract_data['company_data'];
        $client = $contract_data['client_data'];
        $event = $contract_data['event_data'];
        $payment_schedule = $contract_data['payment_schedule'] ?? [];
        $contract_terms = $contract_data['contract_terms']; // Standard terms from VDP
        $additional_terms = $contract_data['additional_terms'] ?? ''; // Additional terms from form
        $bank = $contract_data['bank_data'];
        
        // Simple HTML without complex CSS
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Contrato de Servicios</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                h1 { text-align: center; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                th { background-color: #f0f0f0; }
                .section { margin: 20px 0; }
                .signature { margin-top: 50px; text-align: center; }
            </style>
        </head>
        <body>
            <h1>CONTRATO DE SERVICIOS</h1>
            
            <div class="section">
                <table>
                    <tr>
                        <th style="background-color: #f0f0f0; text-align: center;">Datos de la Empresa</th>
                        <th style="background-color: #f0f0f0; text-align: center;">Datos del Contratante</th>
                    </tr>
                    <tr>
                        <td style="width: 50%; vertical-align: top;">
                            <strong>' . esc_html($company['name']) . '</strong><br>
                            <strong>Dirección:</strong> ' . esc_html($company['address']) . '<br>
                            <strong>Teléfono:</strong> ' . esc_html($company['phone']) . '<br>
                            <strong>E-mail:</strong> ' . esc_html($company['email']) . '
                            ' . (!empty($company['rfc']) ? '<br><strong>RFC:</strong> ' . esc_html($company['rfc']) : '') . '
                        </td>
                        <td style="width: 50%; vertical-align: top;">'
                        . (!empty($client['business_name']) ? '<strong>' . esc_html($client['business_name']) . '</strong><br>' : '')
                        . '<strong>' . esc_html($client['name']) . '</strong>'
                        . (!empty($client['email']) ? '<br>' . esc_html($client['email']) : '')
                        . (!empty($client['phone']) ? '<br>' . esc_html($client['phone']) : '')
                        . (!empty($client['business_address']) ? '<br>' . esc_html($client['business_address']) : '')
                        . (!empty($client['address']) ? '<br>' . esc_html($client['address']) : '') . '
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <h2>Servicios</h2>
                <table>
                    <tr>
                        <th>Servicio</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                    </tr>';
        
        foreach ($cart_items as $item) {
            $html .= '<tr>
                <td>' . esc_html($item->title) . '</td>
                <td>' . esc_html($item->quantity) . '</td>
                <td>' . esc_html($item->price_formatted) . '</td>
            </tr>';
        }
        
        $html .= '</table>
                <p><strong>Total: ' . esc_html($totals['total']) . '</strong></p>
            </div>
            
            <div class="section">
                <h2>Términos y Condiciones</h2>
                <p>' . nl2br(esc_html($contract_terms)) . '</p>'
                . (!empty($additional_terms) ? '<h3>Cláusulas Adicionales</h3><p>' . nl2br(esc_html($additional_terms)) . '</p>' : '') .
            '</div>
            
            <div class="signature">
                <p>_____________________<br>Firma del Cliente</p>
                <p>_____________________<br>Firma de la Empresa</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Get contracts for a lead
     */
    public function get_contracts_for_lead($lead_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, e.tipo_de_evento, e.fecha_de_evento 
             FROM {$wpdb->prefix}eq_contracts c 
             LEFT JOIN {$wpdb->prefix}jet_cct_eventos e ON c.event_id = e._ID
             WHERE c.lead_id = %d 
             ORDER BY c.created_at DESC",
            $lead_id
        ));
    }
    
    /**
     * Get detailed cart items with enriched data (same as quote handler)
     */
    private function get_detailed_cart_items($item_order = null) {
        // Obtener items básicos del carrito
        $cart_items = eq_get_cart_items();
        
        // Si hay un orden especificado, reordenar los items
        if (!empty($item_order) && is_array($item_order)) {
            $ordered_items = array();
            $item_map = array();
            
            // Crear mapa de items por ID
            foreach ($cart_items as $item) {
                $item_map[$item->id] = $item;
            }
            
            // Reordenar según el orden especificado
            foreach ($item_order as $order_info) {
                if (isset($item_map[$order_info['id']])) {
                    $ordered_items[] = $item_map[$order_info['id']];
                    unset($item_map[$order_info['id']]);
                }
            }
            
            // Agregar cualquier item que no esté en el orden al final
            foreach ($item_map as $item) {
                $ordered_items[] = $item;
            }
            
            $cart_items = $ordered_items;
        }
        
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
                'total_price' => isset($item->total_price) ? floatval($item->total_price) : 0,
                // Usar el precio base almacenado en form_data en lugar de obtenerlo nuevamente
                'base_price' => isset($form_data['base_price']) ? floatval($form_data['base_price']) : 
                               floatval(get_post_meta($listing_id, 'hp_price', true)),
                'extras' => array(),
                // Información de rango de fechas
                'is_date_range' => isset($item->is_date_range) ? $item->is_date_range : false,
                'start_date' => isset($item->start_date) ? $item->start_date : $item->date,
                'end_date' => isset($item->end_date) ? $item->end_date : '',
                'days_count' => isset($item->days_count) ? $item->days_count : 1
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
                        'is_variable' => (isset($extra['type']) && $extra['type'] === 'variable_quantity'),
                        // Información para extras que se multiplicaron por días
                        'display_quantity' => isset($extra['display_quantity']) ? $extra['display_quantity'] : (isset($extra['quantity']) ? $extra['quantity'] : 1),
                        'was_multiplied_by_days' => isset($extra['was_multiplied_by_days']) ? $extra['was_multiplied_by_days'] : false
                    );
                    
                    $detailed_item->extras[] = $extra_data;
                }
            }
            
            $detailed_items[] = $detailed_item;
        }
        
        return $detailed_items;
    }
    
    /**
     * Divide texto largo en chunks para evitar problemas de saltos de página
     */
    private function split_long_text($text, $max_lines = 5) {
        // Si el texto es corto o mediano, no dividir
        if (strlen($text) < 800) { // Aproximadamente 10-12 líneas
            return array($text);
        }
        
        // Primero, dividir por saltos de línea existentes
        $lines = explode("\n", $text);
        $chunks = array();
        $current_chunk = array();
        $current_line_count = 0;
        
        foreach ($lines as $line) {
            // Contar cuántas "líneas visuales" ocupa este texto
            // Ajustado para el ancho real de la columna de descripción (aproximadamente 70 caracteres)
            $visual_lines = max(1, ceil(strlen($line) / 70));
            
            // Si agregar esta línea excede el límite, crear un nuevo chunk
            if ($current_line_count + $visual_lines > $max_lines && !empty($current_chunk)) {
                $chunks[] = implode("\n", $current_chunk);
                $current_chunk = array();
                $current_line_count = 0;
            }
            
            $current_chunk[] = $line;
            $current_line_count += $visual_lines;
        }
        
        // Agregar el último chunk si existe
        if (!empty($current_chunk)) {
            $chunks[] = implode("\n", $current_chunk);
        }
        
        // Si no hay chunks (texto vacío), devolver array con string vacío
        if (empty($chunks)) {
            $chunks[] = '';
        }
        
        return $chunks;
    }
    
    /**
     * Get contracts for an event
     */
    public function get_contracts_for_event($event_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eq_contracts 
             WHERE event_id = %d 
             ORDER BY created_at DESC",
            $event_id
        ));
    }
    
    /**
     * Update contract status
     */
    public function update_contract_status($contract_id, $status) {
        global $wpdb;
        
        $allowed_statuses = array('draft', 'sent', 'signed', 'cancelled');
        
        if (!in_array($status, $allowed_statuses)) {
            return false;
        }
        
        $update_data = array('status' => $status);
        
        if ($status === 'signed') {
            $update_data['signed_at'] = current_time('mysql');
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'eq_contracts',
            $update_data,
            array('id' => $contract_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
}