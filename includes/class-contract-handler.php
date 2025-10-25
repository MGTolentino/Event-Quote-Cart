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
            // Obtener datos del formulario
            $contract_data = $this->sanitize_contract_data($_POST);
            
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
            
            // Generar HTML del contrato
            $html = $this->generate_contract_html($contract_data, $cart_items, $totals, $context);
            
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
                error_log('DOMPDF Render Error: ' . $e->getMessage());
                // Si falla, intentar con HTML simplificado
                $html = $this->generate_simplified_contract_html($contract_data, $cart_items, $totals, $context);
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
            error_log('Contract generation error: ' . $e->getMessage());
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
                'address' => sanitize_textarea_field($post_data['client_address'] ?? ''),
                'phone' => sanitize_text_field($post_data['client_phone'] ?? ''),
                'email' => sanitize_email($post_data['client_email'] ?? '')
            ),
            'event_data' => array(
                'date' => sanitize_text_field($post_data['event_date'] ?? ''),
                'start_time' => sanitize_text_field($post_data['event_start_time'] ?? ''),
                'end_time' => sanitize_text_field($post_data['event_end_time'] ?? ''),
                'location' => sanitize_text_field($post_data['event_location'] ?? ''),
                'guests' => intval($post_data['event_guests'] ?? 0)
            ),
            'payment_schedule' => json_decode(stripslashes($post_data['payment_schedule'] ?? '[]'), true),
            'contract_terms' => wp_kses_post($post_data['contract_terms'] ?? ''),
            'bank_data' => array(
                'bank_name' => sanitize_text_field($post_data['bank_name'] ?? ''),
                'account_number' => sanitize_text_field($post_data['bank_account'] ?? ''),
                'clabe' => sanitize_text_field($post_data['bank_clabe'] ?? ''),
                'account_holder' => sanitize_text_field($post_data['company_name'] ?? '')
            )
        );
    }
    
    /**
     * Generate contract HTML
     */
    private function generate_contract_html($contract_data, $cart_items, $totals, $context = null) {
        $company = $contract_data['company_data'];
        $client = $contract_data['client_data'];
        $event = $contract_data['event_data'];
        $payment_schedule = $contract_data['payment_schedule'];
        $terms = $contract_data['contract_terms'];
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
            <script type="text/php">
            if (isset($pdf)) {
                $fontMetrics = $pdf->getFontMetrics();
                $font = $fontMetrics->getFont("helvetica", "normal");
                $size = 10;
                $color = array(0, 0, 0);
                
                // Add footer to every page
                $pdf->page_text(50, $pdf->get_height() - 60, "FIRMA DEL CONTRATANTE", $font, $size, $color);
                $pdf->page_line(50, $pdf->get_height() - 45, 200, $pdf->get_height() - 45, $color, 1);
                
                $pdf->page_text(350, $pdf->get_height() - 60, "FIRMA DE LA EMPRESA", $font, $size, $color);
                $pdf->page_line(350, $pdf->get_height() - 45, 500, $pdf->get_height() - 45, $color, 1);
            }
            </script>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.4;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    border-bottom: 2px solid #333;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                .logo {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2c3e50;
                    margin-bottom: 10px;
                }
                .contract-date {
                    text-align: right;
                    margin-bottom: 20px;
                    font-weight: bold;
                }
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
                }
                .services-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
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
                    background-color: #f39c12;
                    color: white;
                    font-weight: bold;
                }
                .terms {
                    font-size: 10px;
                    line-height: 1.3;
                    text-align: justify;
                }
                @page {
                    margin: 20px 20px 60px 20px; /* Reduced bottom margin for footer */
                }
                
                .signatures {
                    display: none; /* Hide as we use script-based footer */
                }
                .signature-block {
                    width: 45%;
                    float: left;
                    text-align: center;
                    padding: 10px;
                    box-sizing: border-box;
                }
                .signature-block:last-child {
                    float: right;
                }
                .signature-line {
                    border-top: 1px solid #333;
                    margin-top: 40px;
                    padding-top: 5px;
                    font-weight: bold;
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
            <!-- Header -->
            <div class="header">
                <div class="logo">Reservas Events</div>
            </div>
            
            <!-- Fecha -->
            <div class="contract-date">
                <?php echo esc_html($company['address'] ? explode(',', $company['address'])[0] : 'Monterrey N.L.'); ?> México <?php echo date_i18n('j \d\e F Y'); ?>
            </div>
            
            <!-- Datos de la Empresa y Contratante -->
            <div class="section">
                <table class="info-table">
                    <tr>
                        <td class="header">Datos de la Empresa</td>
                        <td class="header">Datos del Contratante</td>
                    </tr>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($company['name']); ?></strong><br>
                            <?php echo nl2br(esc_html($company['address'])); ?><br>
                            Teléfonos: <?php echo esc_html($company['phone']); ?><br>
                            E-mail: <?php echo esc_html($company['email']); ?>
                            <?php if ($company['rfc']): ?>
                                <br>RFC: <?php echo esc_html($company['rfc']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($client['name']); ?></strong><br>
                            <?php echo nl2br(esc_html($client['address'])); ?>
                            <?php if ($client['email']): ?>
                                <br><?php echo esc_html($client['email']); ?>
                            <?php endif; ?>
                            <?php if ($client['phone']): ?>
                                <br>Cel. <?php echo esc_html($client['phone']); ?>
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
                    <strong>Lugar:</strong> <?php echo esc_html($event['location']); ?>
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
                <table class="services-table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Descripción de Servicios Contratados</th>
                            <th>Cantidad</th>
                            <th>Precio</th>
                            <th>Sub Total</th>
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
                        Valor del contrato, Importe Con Letra: (<?php echo esc_html($this->number_to_words($totals['total'])); ?>)
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
                        <?php if ($bank['account_holder']): ?>
                            <strong>Razón Social:</strong> <?php echo esc_html($bank['account_holder']); ?><br>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </body>
        
        <!-- Firmas en footer de cada página -->
        <div class="signatures">
            <div class="signature-block">
                <div><?php echo esc_html($client['name']); ?></div>
                <div class="signature-line">FIRMA DEL CONTRATANTE</div>
            </div>
            <div class="signature-block">
                <div class="signature-line">FIRMA DE LA EMPRESA</div>
            </div>
        </div>
        
        </html>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Convert number to words (Spanish)
     */
    private function number_to_words($amount_string) {
        // Extract numeric value from formatted string
        $amount = floatval(str_replace(['$', ','], '', $amount_string));
        
        // Basic number to words conversion (simplified)
        $amount_rounded = round($amount);
        
        if ($amount_rounded < 1000) {
            return $amount_string . ' pesos 00/100 M.N.';
        } elseif ($amount_rounded < 1000000) {
            $thousands = floor($amount_rounded / 1000);
            $remainder = $amount_rounded % 1000;
            return $this->get_word_for_number($thousands) . ' mil ' . 
                   ($remainder > 0 ? $this->get_word_for_number($remainder) : '') . ' pesos 00/100 M.N.';
        } else {
            return $amount_string . ' pesos 00/100 M.N.';
        }
    }
    
    /**
     * Get word representation for a number (simplified)
     */
    private function get_word_for_number($number) {
        $words = array(
            1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
            6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
            // Add more as needed
        );
        
        if ($number <= 10 && isset($words[$number])) {
            return $words[$number];
        }
        
        return (string) $number;
    }
    
    /**
     * Clean HTML for DOMPDF to avoid rendering errors
     */
    private function clean_html_for_dompdf($html) {
        // Remove problematic CSS properties
        $html = preg_replace('/position\s*:\s*fixed\s*;?/i', '', $html);
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
    private function generate_simplified_contract_html($contract_data, $cart_items, $totals, $context) {
        $company = $contract_data['company_data'];
        $client = $contract_data['client_data'];
        $event = $contract_data['event_data'];
        $payment_schedule = $contract_data['payment_schedule'] ?? [];
        $contract_terms = $contract_data['contract_terms'];
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
                <h2>Datos de la Empresa</h2>
                <p><strong>' . esc_html($company['name']) . '</strong><br>
                ' . esc_html($company['address']) . '<br>
                Tel: ' . esc_html($company['phone']) . '<br>
                Email: ' . esc_html($company['email']) . '</p>
            </div>
            
            <div class="section">
                <h2>Datos del Cliente</h2>
                <p><strong>' . esc_html($client['name']) . '</strong><br>
                ' . esc_html($client['address']) . '<br>
                Tel: ' . esc_html($client['phone']) . '<br>
                Email: ' . esc_html($client['email']) . '</p>
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
                <p>' . nl2br(esc_html($contract_terms)) . '</p>
            </div>
            
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