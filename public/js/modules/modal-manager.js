/**
 * Modal Manager Module
 * 
 * Gestiona todos los modales del sistema
 * 
 * @package Event_Quote_Cart
 * @since 2.0.0
 */

(function($, window) {
    'use strict';
    
    const ModalManager = {
        
        activeModals: [],
        zIndexBase: 1000,
        
        /**
         * Inicializa el gestor de modales
         */
        init: function() {
            this.bindGlobalEvents();
        },
        
        /**
         * Crea un nuevo modal
         */
        create: function(options) {
            const defaults = {
                id: 'modal-' + Date.now(),
                title: '',
                content: '',
                size: 'medium', // small, medium, large, full
                closeButton: true,
                backdrop: true,
                keyboard: true,
                buttons: [],
                onShow: null,
                onShown: null,
                onHide: null,
                onHidden: null,
                customClass: ''
            };
            
            const settings = $.extend({}, defaults, options);
            
            // Crear estructura del modal
            const modal = this.buildModalStructure(settings);
            
            // Agregar al DOM
            $('body').append(modal);
            
            // Configurar eventos
            this.setupModalEvents(modal, settings);
            
            return modal;
        },
        
        /**
         * Construye la estructura HTML del modal
         */
        buildModalStructure: function(settings) {
            const sizeClass = 'eq-modal-' + settings.size;
            
            const modal = $('<div>', {
                class: `eq-modal ${settings.customClass}`,
                id: settings.id,
                'data-modal-id': settings.id
            });
            
            if (settings.backdrop) {
                modal.append($('<div>', { class: 'eq-modal-backdrop' }));
            }
            
            const dialog = $('<div>', { class: `eq-modal-dialog ${sizeClass}` });
            const content = $('<div>', { class: 'eq-modal-content' });
            
            // Header
            if (settings.title || settings.closeButton) {
                const header = $('<div>', { class: 'eq-modal-header' });
                
                if (settings.title) {
                    header.append($('<h4>', { 
                        class: 'eq-modal-title',
                        text: settings.title 
                    }));
                }
                
                if (settings.closeButton) {
                    header.append($('<button>', {
                        class: 'eq-modal-close',
                        html: '&times;',
                        'aria-label': 'Cerrar'
                    }));
                }
                
                content.append(header);
            }
            
            // Body
            const body = $('<div>', { 
                class: 'eq-modal-body',
                html: settings.content 
            });
            content.append(body);
            
            // Footer con botones
            if (settings.buttons && settings.buttons.length > 0) {
                const footer = $('<div>', { class: 'eq-modal-footer' });
                
                settings.buttons.forEach(button => {
                    const btn = $('<button>', {
                        class: `eq-btn ${button.class || ''}`,
                        text: button.text,
                        'data-action': button.action || ''
                    });
                    
                    if (button.click) {
                        btn.on('click', button.click);
                    }
                    
                    footer.append(btn);
                });
                
                content.append(footer);
            }
            
            dialog.append(content);
            modal.append(dialog);
            
            return modal;
        },
        
        /**
         * Configura los eventos del modal
         */
        setupModalEvents: function(modal, settings) {
            const self = this;
            
            // Cerrar con botón X
            modal.find('.eq-modal-close').on('click', function() {
                self.hide(modal.attr('id'));
            });
            
            // Cerrar con backdrop
            if (settings.backdrop) {
                modal.find('.eq-modal-backdrop').on('click', function() {
                    self.hide(modal.attr('id'));
                });
            }
            
            // Cerrar con ESC
            if (settings.keyboard) {
                $(document).on('keydown.modal-' + settings.id, function(e) {
                    if (e.keyCode === 27) {
                        self.hide(settings.id);
                    }
                });
            }
            
            // Callbacks
            modal.data('callbacks', {
                onShow: settings.onShow,
                onShown: settings.onShown,
                onHide: settings.onHide,
                onHidden: settings.onHidden
            });
        },
        
        /**
         * Muestra un modal
         */
        show: function(modalId) {
            const modal = $('#' + modalId);
            if (!modal.length) return;
            
            const callbacks = modal.data('callbacks') || {};
            
            // Callback onShow
            if (callbacks.onShow) {
                callbacks.onShow(modal);
            }
            
            // Calcular z-index
            const zIndex = this.zIndexBase + (this.activeModals.length * 10);
            modal.css('z-index', zIndex);
            
            // Agregar a activos
            this.activeModals.push(modalId);
            
            // Mostrar con animación
            modal.fadeIn(300, function() {
                modal.addClass('eq-modal-open');
                
                // Focus en primer input
                modal.find('input:first').focus();
                
                // Callback onShown
                if (callbacks.onShown) {
                    callbacks.onShown(modal);
                }
            });
            
            // Prevenir scroll del body
            $('body').addClass('eq-modal-body-open');
        },
        
        /**
         * Oculta un modal
         */
        hide: function(modalId) {
            const modal = $('#' + modalId);
            if (!modal.length) return;
            
            const callbacks = modal.data('callbacks') || {};
            
            // Callback onHide
            if (callbacks.onHide) {
                callbacks.onHide(modal);
            }
            
            // Ocultar con animación
            modal.fadeOut(300, function() {
                modal.removeClass('eq-modal-open');
                
                // Callback onHidden
                if (callbacks.onHidden) {
                    callbacks.onHidden(modal);
                }
                
                // Remover de activos
                const index = this.activeModals.indexOf(modalId);
                if (index > -1) {
                    this.activeModals.splice(index, 1);
                }
                
                // Restaurar scroll si no hay más modales
                if (this.activeModals.length === 0) {
                    $('body').removeClass('eq-modal-body-open');
                }
            }.bind(this));
            
            // Remover eventos de teclado
            $(document).off('keydown.modal-' + modalId);
        },
        
        /**
         * Destruye un modal
         */
        destroy: function(modalId) {
            this.hide(modalId);
            setTimeout(function() {
                $('#' + modalId).remove();
            }, 300);
        },
        
        /**
         * Actualiza el contenido del modal
         */
        updateContent: function(modalId, content) {
            const modal = $('#' + modalId);
            if (!modal.length) return;
            
            modal.find('.eq-modal-body').html(content);
        },
        
        /**
         * Actualiza el título del modal
         */
        updateTitle: function(modalId, title) {
            const modal = $('#' + modalId);
            if (!modal.length) return;
            
            modal.find('.eq-modal-title').text(title);
        },
        
        /**
         * Muestra modal de confirmación
         */
        confirm: function(options) {
            const defaults = {
                title: 'Confirmar',
                message: '¿Está seguro?',
                confirmText: 'Confirmar',
                cancelText: 'Cancelar',
                onConfirm: null,
                onCancel: null,
                type: 'warning' // info, warning, danger, success
            };
            
            const settings = $.extend({}, defaults, options);
            
            const modal = this.create({
                title: settings.title,
                content: `<div class="eq-modal-confirm eq-modal-confirm-${settings.type}">
                    <p>${settings.message}</p>
                </div>`,
                size: 'small',
                buttons: [
                    {
                        text: settings.cancelText,
                        class: 'eq-btn-secondary',
                        click: function() {
                            if (settings.onCancel) {
                                settings.onCancel();
                            }
                            this.destroy(modal.attr('id'));
                        }.bind(this)
                    },
                    {
                        text: settings.confirmText,
                        class: 'eq-btn-primary',
                        click: function() {
                            if (settings.onConfirm) {
                                settings.onConfirm();
                            }
                            this.destroy(modal.attr('id'));
                        }.bind(this)
                    }
                ]
            });
            
            this.show(modal.attr('id'));
            
            return modal;
        },
        
        /**
         * Muestra modal de alerta
         */
        alert: function(options) {
            const defaults = {
                title: 'Alerta',
                message: '',
                buttonText: 'Aceptar',
                type: 'info' // info, warning, danger, success
            };
            
            const settings = $.extend({}, defaults, options);
            
            const modal = this.create({
                title: settings.title,
                content: `<div class="eq-modal-alert eq-modal-alert-${settings.type}">
                    <p>${settings.message}</p>
                </div>`,
                size: 'small',
                buttons: [{
                    text: settings.buttonText,
                    class: 'eq-btn-primary',
                    click: function() {
                        this.destroy(modal.attr('id'));
                    }.bind(this)
                }]
            });
            
            this.show(modal.attr('id'));
            
            return modal;
        },
        
        /**
         * Muestra modal de carga
         */
        loading: function(message = 'Cargando...') {
            const modal = this.create({
                id: 'eq-modal-loading',
                content: `<div class="eq-modal-loading">
                    <div class="eq-spinner"></div>
                    <p>${message}</p>
                </div>`,
                size: 'small',
                closeButton: false,
                backdrop: false,
                keyboard: false
            });
            
            this.show(modal.attr('id'));
            
            return modal;
        },
        
        /**
         * Oculta modal de carga
         */
        hideLoading: function() {
            this.destroy('eq-modal-loading');
        },
        
        /**
         * Eventos globales
         */
        bindGlobalEvents: function() {
            // Prevenir propagación de clicks dentro del modal
            $(document).on('click', '.eq-modal-dialog', function(e) {
                e.stopPropagation();
            });
        }
    };
    
    // Inicializar al cargar
    $(document).ready(function() {
        ModalManager.init();
    });
    
    // Exportar para uso global
    window.EQModalManager = ModalManager;
    
})(jQuery, window);