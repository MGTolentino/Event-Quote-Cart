<?php
/**
 * Template para la vista detallada de la cotización
 */

defined('ABSPATH') || exit;

$current_date = current_time('d/m/Y');
?>

<div class="eq-quote-view">
    <!-- Header -->
    <div class="eq-quote-header">
        <div class="eq-quote-logo">
            <?php 
            $logo_url = get_theme_mod('custom_logo') ? 
                wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full') : 
                plugins_url('assets/images/reservas-events-logo.png', EQ_CART_PLUGIN_FILE);
            ?>
            <img src="<?php echo esc_url($logo_url); ?>" alt="Reservas Events">
        </div>
        <div class="eq-quote-date">
            To day date <?php echo esc_html($current_date); ?>
        </div>
    </div>
	
	<!-- Agregar después del header y antes del contenido principal -->
<div class="eq-action-buttons">
    <button class="eq-print-button" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimir / Guardar PDF
    </button>
    <button class="eq-email-button" data-default-email="contacto@reservas.events">
        <i class="fas fa-envelope"></i> Enviar por Email
    </button>
    <button class="eq-whatsapp-button" data-phone="528444550550">
        <i class="fab fa-whatsapp"></i> Enviar por WhatsApp
    </button>
</div>

    <!-- Cliente Info -->
    <div class="eq-quote-client-info">
        <div class="eq-info-row">
            <span class="eq-label">Atención:</span>
            <span class="eq-value">Name + Apellido</span>
        </div>
        <div class="eq-info-row">
            <span class="eq-label">Fecha de Evento:</span>
            <span class="eq-value">(Event Date)</span>
        </div>
        <div class="eq-info-row">
            <span class="eq-label">Ciudad del Evento:</span>
            <span class="eq-value">(City)</span>
        </div>
    </div>

    <!-- Mensaje -->
    <div class="eq-quote-message">
        <p>Estimado (NAME + Apellido) con gran placer te comparto esta información esperando cumpla con los requerimientos para tu evento,</p>
    </div>

    <!-- Tabla de Servicios -->
    <table class="eq-quote-table">
        <thead>
            <tr>
                <th>SERVICIO<br>POST TITLE</th>
                <th>DESCRIPCIÓN<br>POST DESCRIPTION</th>
                <th>CANTIDAD</th>
                <th>PRECIO<br>UNITARIO<br>(Base price)</th>
                <th>SUB-<br>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cart_items as $item): 
                $form_data = json_decode($item->form_data, true);
            ?>
                <!-- Item principal -->
                <tr class="eq-main-item">
                    <td><?php echo esc_html($item->title); ?></td>
                    <td><?php echo wp_kses_post(get_post_field('post_content', $item->listing_id)); ?></td>
                    <td><?php echo esc_html($form_data['quantity']); ?></td>
                    <td><?php echo esc_html(hivepress()->woocommerce->format_price($form_data['base_price'])); ?></td>
                    <td><?php echo esc_html(hivepress()->woocommerce->format_price($form_data['base_price'] * $form_data['quantity'])); ?></td>
                </tr>
                
                <!-- Extras del item -->
                <?php if (!empty($form_data['extras'])): 
                    foreach ($form_data['extras'] as $extra): ?>
                    <tr class="eq-extra-item">
                        <td colspan="2">
                            <?php echo esc_html($extra['name']); ?> de <?php echo esc_html($item->title); ?>
                        </td>
                        <td><?php echo esc_html($extra['quantity']); ?></td>
                        <td><?php echo esc_html(hivepress()->woocommerce->format_price($extra['price'])); ?></td>
                        <td><?php echo esc_html(hivepress()->woocommerce->format_price($extra['price'] * $extra['quantity'])); ?></td>
                    </tr>
                    <?php endforeach;
                endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totales -->
    <div class="eq-quote-totals">
        <div class="eq-total-row">
            <span>SUB TOTAL:</span>
            <span><?php echo esc_html($totals['subtotal']); ?></span>
        </div>
        <div class="eq-total-row">
            <span>TAX:</span>
            <span><?php echo esc_html($totals['taxes']); ?></span>
        </div>
        <div class="eq-total-row total">
            <span>TOTAL:</span>
            <span><?php echo esc_html($totals['total']); ?></span>
        </div>
    </div>

    <!-- Footer -->
    <div class="eq-quote-footer">
        <p>ATENDIDO POR: (USER VENDOR NAME) CONTACTO: 8444-550-550</p>
        <p>Precios sujetos a cambios sin previo aviso y sujetos a disponibilidad</p>
    </div>
</div>