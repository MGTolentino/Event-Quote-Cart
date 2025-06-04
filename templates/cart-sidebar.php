<?php
/**
 * Quote Cart Sidebar Template
 *
 * This template handles the display of the quote cart sidebar.
 * It shows the current product being added and already added products.
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;
?>

<div id="eq-cart-sidebar" class="eq-sidebar">
    <!-- Header -->
    <div class="eq-sidebar-header">
        <h2 class="eq-sidebar-title"><?php esc_html_e('Quote Cart', 'event-quote-cart'); ?></h2>
        <button class="eq-sidebar-close" aria-label="<?php esc_attr_e('Close', 'event-quote-cart'); ?>">&times;</button>
    </div>

    <!-- Content Area -->
    <div class="eq-sidebar-content">
        <!-- Current Product Form (when adding new) -->
        <div id="eq-current-product" class="eq-product-form" style="display: none;">
            <div class="eq-product-header">
                <img src="" alt="" class="eq-product-image">
                <h3 class="eq-product-title"></h3>
            </div>

            <form id="eq-add-to-cart-form" class="eq-form">
                <input type="hidden" name="listing_id" value="">

                <!-- Dates Section -->
						<div class="eq-form-group eq-date-field">
							<label><?php esc_html_e('Date', 'event-quote-cart'); ?></label>

							<!-- Date Display/Edit Container -->
							<div class="eq-date-container">
								<!-- Hidden Input for Flatpickr -->
								<input type="text" 
									   class="eq-date-picker"
									   name="_dates"
									   data-input
									   style="display: none;"
									   required>

								<!-- Visual Date Display -->
								<div class="eq-date-display">
									<span class="eq-date-value">
										<?php esc_html_e('Select a date', 'event-quote-cart'); ?>
									</span>
									<button type="button" class="eq-date-edit">
										<i class="fas fa-calendar-alt"></i>
									</button>
								</div>

								<!-- Loading State -->
								<div class="eq-date-loading" style="display: none;">
									<span class="eq-loading-spinner"></span>
									<?php esc_html_e('Checking availability...', 'event-quote-cart'); ?>
								</div>
                                    
								<!-- Date Error State -->
								<div class="eq-date-error" style="display: none;">
								</div>
							</div>
						</div>

                <!-- Quantity Section (will be shown/hidden based on listing settings) -->
                <div class="eq-form-group eq-quantity-group" style="display: none;">
                    <label for="eq-quantity"><?php esc_html_e('Quantity', 'event-quote-cart'); ?></label>
                    <input type="number" 
                           id="eq-quantity" 
                           name="_quantity" 
                           class="eq-form-control" 
                           min="1" 
                           value="1">
                </div>

                <!-- Extras Section -->
                <div class="eq-extras-section" style="display: none;">
                    <div class="eq-form-group">
                        <label><?php esc_html_e('Extras', 'event-quote-cart'); ?></label>
                        <div class="eq-extras-container"></div>
                    </div>
                </div>

                <!-- Price Summary -->
                <div class="eq-price-summary">
                    <div class="eq-base-price">
                        <span><?php esc_html_e('Base Price:', 'event-quote-cart'); ?></span>
                        <span class="eq-price-value"></span>
                    </div>
                    <div class="eq-total-price">
                        <span><?php esc_html_e('Total:', 'event-quote-cart'); ?></span>
                        <span class="eq-price-value"></span>
                    </div>
                </div>

                <button type="submit" class="eq-quote-button">
                    <?php esc_html_e('Add to Quote', 'event-quote-cart'); ?>
                </button>
            </form>
        </div>

        <!-- List of Added Products -->
        <div id="eq-added-products" class="eq-products-list">
            <!-- Products will be added here dynamically -->
        </div>
    </div>

    <!-- Footer -->
    <div class="eq-sidebar-footer">
        <!-- Total del carrito -->
        <div class="eq-cart-total">
            <div class="eq-total-row">
            <span class="eq-total-label"><?php esc_html_e('Total (incl. taxes)', 'event-quote-cart'); ?></span>
                <span class="eq-cart-total-amount">MXN $0.00</span>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="eq-cart-actions">
            <button type="button" 
                    class="eq-view-quote-button" 
                    disabled>
                    <?php esc_html_e('View Complete Quote', 'event-quote-cart'); ?>                
            </button>
        </div>

        <!-- Indicador de items -->
        <div class="eq-cart-summary">
            <span class="eq-cart-items-count">0</span> <?php esc_html_e('items in quote', 'event-quote-cart'); ?>
        </div>
    </div>
</div>

<!-- Template for mini-card (used by JavaScript) -->
<template id="eq-mini-card-template">
    <div class="eq-mini-card">
        <span class="eq-mini-card-status">✓</span>
        <div class="eq-mini-card-info">
            <h4 class="eq-mini-card-title"></h4>
            <div class="eq-mini-card-dates"></div>
            <div class="eq-mini-card-quantity"></div>
            <div class="eq-mini-card-price"></div>
        </div>
        <div class="eq-mini-card-actions">
            <button type="button" class="eq-mini-card-button edit" title="<?php esc_attr_e('Edit', 'event-quote-cart'); ?>">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="eq-mini-card-button remove" title="<?php esc_attr_e('Remove', 'event-quote-cart'); ?>">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
</template>