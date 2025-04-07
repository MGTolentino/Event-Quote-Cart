<?php
/**
 * Template para el Panel de Contexto Persistente
 */
defined('ABSPATH') || exit;
?>

<div class="eq-context-panel">
    <div class="eq-context-panel-info">
        <div class="eq-context-panel-section">
            <span class="eq-context-panel-label"><?php esc_html_e('Lead:', 'event-quote-cart'); ?></span>
            <span class="eq-context-panel-value" id="eq-context-lead-name">
                <?php echo isset($lead_name) ? esc_html($lead_name) : esc_html__('Not selected', 'event-quote-cart'); ?>
            </span>
        </div>
        <div class="eq-context-panel-section">
            <span class="eq-context-panel-label"><?php esc_html_e('Event:', 'event-quote-cart'); ?></span>
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
                    echo $event_info ?: sprintf(esc_html__('Event #%d', 'event-quote-cart'), $event_id);
                } else {
                    esc_html_e('Not selected', 'event-quote-cart');
                }
                ?>
            </span>
        </div>
    </div>
        <div class="eq-context-panel-actions">
        <button type="button" class="eq-context-panel-button change-lead">
            <?php esc_html_e('Change Lead', 'event-quote-cart'); ?>
        </button>
        <button type="button" class="eq-context-panel-button change-event">
            <?php esc_html_e('Change Event', 'event-quote-cart'); ?>
        </button>
        <button type="button" class="eq-context-panel-button end-session">
            <?php esc_html_e('End Session', 'event-quote-cart'); ?>
        </button>
        <!-- Añadir este botón de minimizar -->
        <button type="button" class="eq-context-panel-button toggle-panel">
            <?php esc_html_e('Minimize', 'event-quote-cart'); ?>
        </button>
        </div>
</div>