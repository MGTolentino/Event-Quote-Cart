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
            
            // Obtener items del carrito
            $cart_items = eq_get_cart_items();
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
            
            // Generar PDF
            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
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
                    margin-bottom: 25px;
                }
                .section-title {
                    font-size: 16px;
                    font-weight: bold;
                    color: #2c3e50;
                    border-bottom: 1px solid #bdc3c7;
                    padding-bottom: 5px;
                    margin-bottom: 15px;
                }
                .info-grid {
                    display: table;
                    width: 100%;
                    border-collapse: collapse;
                }
                .info-row {
                    display: table-row;
                }
                .info-cell {
                    display: table-cell;
                    padding: 10px;
                    border: 1px solid #bdc3c7;
                    vertical-align: top;
                }
                .info-cell.header {
                    background-color: #ecf0f1;
                    font-weight: bold;
                }
                .services-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
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
                .signatures {
                    margin-top: 50px;
                    display: table;
                    width: 100%;
                }
                .signature-block {
                    display: table-cell;
                    width: 50%;
                    text-align: center;
                    padding: 20px;
                }
                .signature-line {
                    border-top: 1px solid #333;
                    margin-top: 60px;
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
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-cell header">Datos de la Empresa</div>
                        <div class="info-cell header">Datos del Contratante</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell">
                            <strong><?php echo esc_html($company['name']); ?></strong><br>
                            <?php echo nl2br(esc_html($company['address'])); ?><br>
                            Teléfonos: <?php echo esc_html($company['phone']); ?><br>
                            E-mail: <?php echo esc_html($company['email']); ?>
                            <?php if ($company['rfc']): ?>
                                <br>RFC: <?php echo esc_html($company['rfc']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="info-cell">
                            <strong><?php echo esc_html($client['name']); ?></strong><br>
                            <?php echo nl2br(esc_html($client['address'])); ?>
                            <?php if ($client['email']): ?>
                                <br><?php echo esc_html($client['email']); ?>
                            <?php endif; ?>
                            <?php if ($client['phone']): ?>
                                <br>Cel. <?php echo esc_html($client['phone']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Información del Evento -->
            <div class="section">
                <div class="section-title">Información del evento</div>
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-cell">
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
                            <tr>
                                <td><strong><?php echo esc_html($item->title); ?></strong></td>
                                <td>
                                    <?php if (!empty($item->extras)): ?>
                                        <?php esc_html_e('Incluye:', 'event-quote-cart'); ?><br>
                                        <?php foreach ($item->extras as $extra): ?>
                                            - <?php echo esc_html($extra['name']); ?>
                                            <?php if ($extra['quantity'] > 1): ?>
                                                × <?php echo esc_html($extra['quantity']); ?>
                                            <?php endif; ?><br>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($item->is_date_range) && $item->is_date_range): ?>
                                        <br><strong>Fecha del evento:</strong> <?php echo esc_html($item->start_date); ?> a <?php echo esc_html($item->end_date); ?>
                                    <?php else: ?>
                                        <br><strong>Fecha del evento:</strong> <?php echo esc_html($item->date); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="number"><?php echo esc_html($item->quantity); ?></td>
                                <td class="number"><?php echo esc_html($item->price_formatted); ?></td>
                                <td class="number"><?php echo esc_html($item->price_formatted); ?></td>
                            </tr>
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
            
            <!-- Firmas -->
            <div class="signatures">
                <div class="signature-block">
                    <div><?php echo esc_html($client['name']); ?></div>
                    <div class="signature-line">FIRMA DEL CONTRATANTE</div>
                </div>
                <div class="signature-block">
                    <div class="signature-line">FIRMA DE LA EMPRESA</div>
                </div>
            </div>
        </body>
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