<?php
/**
 * Template para el Panel de Contexto Persistente
 */
defined('ABSPATH') || exit;
?>

<div class="eq-context-panel">
    <div class="eq-context-panel-info">
        <div class="eq-context-panel-section">
            <span class="eq-context-panel-label"><?php _e('Lead:', 'event-quote-cart'); ?></span>
            <span class="eq-context-panel-value" id="eq-context-lead-name">
                <?php echo isset($lead_name) ? esc_html($lead_name) : __('No seleccionado', 'event-quote-cart'); ?>
            </span>
        </div>
        <div class="eq-context-panel-section">
            <span class="eq-context-panel-label"><?php _e('Evento:', 'event-quote-cart'); ?></span>
            <span class="eq-context-panel-value" id="eq-context-event-info">
                <?php 
                if (isset($event_id) && $event_id) {
                    $event_info = '';
                    if (isset($event_type) && $event_type) {
                        $event_info .= esc_html($event_type);
                    }
                    if (isset($event_date) && $event_date) {
                        if ($event_info) $event_info .= ' - ';
                        $event_info .= esc_html($event_date);
                    }
                    echo $event_info ?: sprintf(__('Evento #%d', 'event-quote-cart'), $event_id);
                } else {
                    _e('No seleccionado', 'event-quote-cart');
                }
                ?>
            </span>
        </div>
    </div>
    <div class="eq-context-panel-actions">
        <button type="button" class="eq-context-panel-button change-lead">
            <?php _e('Cambiar Lead', 'event-quote-cart'); ?>
        </button>
        <button type="button" class="eq-context-panel-button change-event">
            <?php _e('Cambiar Evento', 'event-quote-cart'); ?>
        </button>
        <button type="button" class="eq-context-panel-button end-session">
            <?php _e('Finalizar Sesión', 'event-quote-cart'); ?>
        </button>
    </div>
</div>