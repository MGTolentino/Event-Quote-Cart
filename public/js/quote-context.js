/**
 * Panel de Contexto Persistente para Cotizaciones
 */
(function($) {
    'use strict';

    // Objeto principal
    var EQContext = {
        
        // Almacenamiento de datos
       data: {
    isActive: false,
    isMinimized: false,
    leadId: null,
    leadName: null,
    eventId: null,
    eventDate: null,
    eventType: null
		},
		
		    isCreatingNewEvent: false,

        
init: function() {
	
    // Verificar si el usuario puede usar el panel
    if (typeof eqContextData === 'undefined' || !eqContextData.canUseContextPanel) {
        return; // No inicializar si el usuario no tiene permisos
    }
    
    // Verificar si hay cookies que indican que se debe limpiar el contexto
    if (document.cookie.indexOf('eq_session_ended=true') !== -1 || 
        document.cookie.indexOf('eq_context_force_clear=true') !== -1) {
        
        // Limpiar completamente el contexto
        sessionStorage.removeItem('eqQuoteContext');
        localStorage.removeItem('eq_context_session_ended');
        localStorage.removeItem('eq_context_session_force_clear');
        
        // Limpiar datos locales
        this.data = {
            isActive: false,
            isMinimized: false,
            leadId: null,
            leadName: null,
            eventId: null,
            eventDate: null,
            eventType: null,
            sessionToken: null
        };
        
        // Limpiar cookies
        document.cookie = 'eq_session_ended=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;';
        document.cookie = 'eq_context_force_clear=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;';
        
        // No continuar con la inicialización del contexto
        return;
    }
    
    // Limpiar datos obsoletos antes de verificar con el servidor
    this.clearStaleDataOnInit();
    
    // Iniciar sistema de sincronización entre pestañas primero
    this.initTabsSynchronization();
    
    // IMPORTANTE: NO renderizar nada hasta verificar con el servidor
    
    var self = this;
    
    // Solo ocultar panel si ya existe durante la inicialización
    if ($('.eq-context-panel').length > 0) {
        $('.eq-context-panel').hide();
    }
    
    // Primero intentar cargar datos locales si existen
    self.loadFromStorage();
    
    // Si tenemos datos locales válidos, mostrar el panel inmediatamente
    if (self.data.isActive && self.data.leadId && self.data.eventId) {
        console.log('DEBUG: Found valid local data on init, showing panel immediately');
        
        // Marcar que estamos sincronizando
        self.data.isSyncing = true;
        
        self.renderPanel();
        self.initEventListeners();
        self.initModals();
        
        // Mostrar indicador de sincronización
        self.showSyncIndicator();
        
        // Verificar con servidor en background DESPUÉS de mostrar el panel
        setTimeout(function() {
            console.log('DEBUG: Background verification starting');
            self.checkServerContextSilent(function(response) {
                // Ocultar indicador de sincronización
                self.hideSyncIndicator();
                self.data.isSyncing = false;
                
                if (response && response.success && response.data && response.data.isActive) {
                    // Solo actualizar si hay cambios significativos
                    if (response.data.leadId !== self.data.leadId || 
                        response.data.eventId !== self.data.eventId) {
                        console.log('DEBUG: Server has different data, updating');
                        
                        // Mostrar notificación de actualización
                        self.showNotification('Contexto actualizado con datos del servidor', 'info');
                        
                        self.data.leadId = response.data.leadId;
                        self.data.leadName = response.data.leadName;
                        self.data.eventId = response.data.eventId;
                        self.data.eventDate = response.data.eventDate;
                        self.data.eventType = response.data.eventType;
                        self.saveToStorage();
                        self.renderPanel();
                    }
                } else if (response && response.success && !response.data.isActive) {
                    // Servidor dice que no hay sesión activa, limpiar
                    console.log('DEBUG: Server says no active session, clearing');
                    self.showNotification('La sesión ya no está activa en el servidor', 'warning');
                    setTimeout(function() {
                        self.endSession();
                    }, 1000);
                }
            });
        }, 1000); // Reducido a 1 segundo para respuesta más rápida
        
        return; // No hacer la verificación normal
    }
    
    // Solo si NO hay datos locales, intentar cargar del servidor
    console.log('DEBUG: No valid local data, checking server');
    setTimeout(function() {
        // Verificar con el servidor si hay un contexto activo con timeout mejorado
        self.checkServerContextWithErrorHandling(function(success, response) {
        // Debug: log what we're getting from server
        console.log('DEBUG: Server response:', response);
        
        if (success && response && response.success) {

            // Si el servidor dice que hay un contexto activo
            if (response.data && response.data.isActive) {
                console.log('DEBUG: Active session found with data:', response.data);
                
                // Cargar datos locales para comparar
                var localData = JSON.parse(sessionStorage.getItem('eqQuoteContext') || '{}');
                var useServerData = true;
                
                // Algoritmo robusto para evitar race conditions
                var localTimestamp = localData.lastUpdate || 0;
                var serverTimestamp = response.data.lastUpdate || 0;
                var currentTime = Date.now();
                
                // Verificar validez de timestamps (no futuro, no muy viejo)
                var localValid = localTimestamp > 0 && localTimestamp <= currentTime && (currentTime - localTimestamp) < 3600000; // < 1 hora
                var serverValid = serverTimestamp > 0 && serverTimestamp <= currentTime && (currentTime - serverTimestamp) < 3600000; // < 1 hora
                
                if (localValid && serverValid) {
                    // Ambos válidos: usar el más reciente con margen de tolerancia
                    useServerData = serverTimestamp > (localTimestamp + 1000); // 1 segundo de tolerancia
                } else if (localValid && !serverValid) {
                    // Solo local válido: usar local si es reciente
                    useServerData = (currentTime - localTimestamp) > 300000; // 5 minutos
                } else if (!localValid && serverValid) {
                    // Solo servidor válido: usar servidor
                    useServerData = true;
                } else {
                    // Ninguno válido: usar servidor si tiene datos, sino local
                    useServerData = response.data.leadId && response.data.eventId;
                    if (!useServerData && !localData.leadId) {
                        // Ninguno tiene datos válidos
                        useServerData = false;
                    }
                }
                
                console.log('DEBUG: useServerData decision:', useServerData);
                console.log('DEBUG: localData:', localData);
                
                if (useServerData) {
                    // Actualizar datos locales con los del servidor
                    self.data.isActive = true;
                    self.data.leadId = response.data.leadId;
                    self.data.leadName = response.data.leadName;
                    self.data.eventId = response.data.eventId;
                    self.data.eventDate = response.data.eventDate;
                    self.data.eventType = response.data.eventType;
                    if (response.data.sessionToken) self.data.sessionToken = response.data.sessionToken;
                    console.log('DEBUG: Using server data. Final data:', self.data);
                } else {
                    // Usar datos locales más recientes
                    self.data.isActive = true;
                    self.data.leadId = localData.leadId;
                    self.data.leadName = localData.leadName;
                    self.data.eventId = localData.eventId;
                    self.data.eventDate = localData.eventDate;
                    self.data.eventType = localData.eventType;
                    if (localData.sessionToken) self.data.sessionToken = localData.sessionToken;
                    console.log('DEBUG: Using local data. Final data:', self.data);
                }
                
                // Guardar en sessionStorage
                self.saveToStorage();
                
                // SIEMPRE recrear el panel para evitar datos obsoletos
                $('.eq-context-panel').remove();
                
                // Renderizar panel completamente nuevo con datos actualizados
                console.log('DEBUG: About to render panel with data:', self.data);
                self.renderPanel();
            } else {
                
                // Limpiar datos locales
                self.data.isActive = false;
                self.data.leadId = null;
                self.data.leadName = null;
                self.data.eventId = null;
                self.data.eventDate = null;
                self.data.eventType = null;
                self.data.sessionToken = null;
                
                // Guardar en sessionStorage
                self.saveToStorage();
                
                // Limpiar elementos del DOM si existen Y FORZAR REMOCIÓN
                $('.eq-context-panel').remove();
                $('body').find('.eq-context-panel').remove();
                
                // Mostrar botón toggle
                self.renderToggleButton();
            }
            
            // Inicializar controladores de eventos
            self.initEventListeners();
            
            // Inicializar modales
            self.initModals();
            
            // Iniciar verificación periódica del estado del servidor (con menor frecuencia)
            self.startSessionPolling();
        } else {
            console.log('DEBUG: Server timeout/error. success:', success, 'response:', response);
            
            // Server timeout or error - mantener datos locales si existen
            self.loadFromStorage();
            console.log('DEBUG: After loadFromStorage, data is:', self.data);
            
            if (self.data.isActive && self.data.leadId && self.data.eventId) {
                // Tenemos datos válidos localmente, mantener sesión activa
                console.log('DEBUG: Using valid local data');
                self.renderPanel();
                self.initEventListeners();
                self.initModals();
                
                // Mostrar notificación de que no se pudo verificar con servidor
                setTimeout(function() {
                    self.showNotification('No se pudo verificar con el servidor, usando datos locales', 'warning');
                }, 500);
            } else {
                console.log('DEBUG: Local data is invalid or incomplete, clearing and showing toggle button');
                // No hay datos locales válidos, limpiar sessionStorage corrupto
                sessionStorage.removeItem('eqQuoteContext');
                
                // Intentar reconectar una vez más después de limpiar datos corruptos
                setTimeout(function() {
                    console.log('DEBUG: Attempting retry after clearing corrupted data');
                    self.checkServerContextWithErrorHandling(function(retrySuccess, retryResponse) {
                        console.log('DEBUG: Retry result - success:', retrySuccess, 'response:', retryResponse);
                        
                        if (retrySuccess && retryResponse && retryResponse.success && retryResponse.data && retryResponse.data.isActive) {
                            // Éxito en el retry, usar datos del servidor
                            self.data.isActive = true;
                            self.data.leadId = retryResponse.data.leadId;
                            self.data.leadName = retryResponse.data.leadName;
                            self.data.eventId = retryResponse.data.eventId;
                            self.data.eventDate = retryResponse.data.eventDate;
                            self.data.eventType = retryResponse.data.eventType;
                            if (retryResponse.data.sessionToken) self.data.sessionToken = retryResponse.data.sessionToken;
                            
                            self.saveToStorage();
                            $('.eq-context-panel').remove();
                            $('.eq-context-toggle-button').remove();
                            self.renderPanel();
                            self.initEventListeners();
                            self.initModals();
                            
                            console.log('DEBUG: Session recovered successfully with retry');
                        } else {
                            // Retry también falló, mostrar toggle button
                            self.data = {
                                isActive: false,
                                isMinimized: false,
                                leadId: null,
                                leadName: null,
                                eventId: null,
                                eventDate: null,
                                eventType: null,
                                sessionToken: null
                            };
                            
                            self.saveToStorage();
                            $('.eq-context-panel').remove();
                            self.renderToggleButton();
                            self.initEventListeners();
                            self.initModals();
                            
                            console.log('DEBUG: Retry also failed, showing toggle button');
                        }
                    });
                }, 1000); // Esperar 1 segundo antes del retry
            }
            
            // Iniciar polling con menor frecuencia cuando hay problemas de conectividad
            self.startSessionPolling();
        }
    });
    }, 500); // Esperar 500ms antes de verificar para evitar conflictos de session lock
},

clearStaleDataOnInit: function() {
    // Verificar si hay datos en sessionStorage  
    var savedData = sessionStorage.getItem('eqQuoteContext');
    if (savedData) {
        try {
            var parsedData = JSON.parse(savedData);
            var currentTime = Date.now();
            
            // Si los datos son muy antiguos (más de 30 minutos), limpiarlos
            if (parsedData.lastUpdate && (currentTime - parsedData.lastUpdate) > 1800000) {
                sessionStorage.removeItem('eqQuoteContext');
                this.data = {
                    isActive: false,
                    isMinimized: false,
                    leadId: null,
                    leadName: null,
                    eventId: null,
                    eventDate: null,
                    eventType: null,
                    sessionToken: null
                };
            }
        } catch (e) {
            sessionStorage.removeItem('eqQuoteContext');
            this.data = {
                isActive: false,
                isMinimized: false,
                leadId: null,
                leadName: null,
                eventId: null,
                eventDate: null,
                eventType: null,
                sessionToken: null
            };
        }
    }
    
    // Eliminar cualquier panel existente para empezar limpio
    $('.eq-context-panel').remove();
    $('.eq-context-toggle-button').remove();
},

initTabsSynchronization: function() {
    var self = this;
    
    
    // Verificar bandera global de sesión finalizada al inicio
    if (localStorage.getItem('eq_context_session_ended')) {
        this.data.isActive = false;
        
        // Limpiar estado local
        this.clearLocalState();
        
        // Eliminar la bandera para que no se vuelva a procesar
        localStorage.removeItem('eq_context_session_ended');
    }
    
    // Escuchar cambios en localStorage de otras pestañas
    window.addEventListener('storage', function(e) {
        // Manejar diferentes tipos de eventos de sincronización
        if (e.key === 'eq_context_session_ended') {
            // Manejo de sesión finalizada
            self.data.isActive = false;
            self.clearLocalState();
            $('.eq-context-panel').remove();
            $('body').removeClass('has-eq-context-panel');
            self.renderToggleButton();
            self.showNotification('Sesión finalizada en otra pestaña', 'info');
        } else if (e.key === 'eq_context_data_sync') {
            // Sincronización de datos del contexto entre pestañas
            try {
                var syncData = JSON.parse(e.newValue || '{}');
                if (syncData.leadId && syncData.eventId && syncData.lastUpdate) {
                    // Verificar si los datos son más recientes (con margen de tolerancia)
                    if (!self.data.lastUpdate || syncData.lastUpdate > (self.data.lastUpdate + 1000)) {
                        self.data = syncData;
                        self.saveToStorage(true); // skipSync para evitar loop
                        self.renderPanel();
                    }
                }
            } catch (e) {
                console.error('Error synchronizing context from another tab:', e);
            }
        } else if (e.key === 'eq_context_force_sync') {
            // Sincronización forzada (después de cambios de lead/evento)
            try {
                var syncData = JSON.parse(e.newValue || '{}');
                if (syncData.leadId && syncData.eventId) {
                    // Actualización forzada sin verificación de timestamp
                    self.data = syncData;
                    self.saveToStorage(true); // skipSync para evitar loop
                    self.renderPanel();
                }
            } catch (e) {
                console.error('Error in forced sync from another tab:', e);
            }
        }
    });
},
		
		// Obtener datos del listing actual si estamos en una página de listing
getCurrentListingData: function() {
    var listingData = {
        id: null,
        ubicacion: '',
        categoria: ''
    };
    
    // Intentar obtener el ID del listing actual
    var listingId = this.getCurrentListingId();
    if (!listingId) {
        return listingData;
    }
    
    listingData.id = listingId;
    
    // Extraer datos de data attributes si están disponibles
    var listingElement = document.querySelector('.hp-listing[data-id="' + listingId + '"]');
    if (listingElement) {
        if (listingElement.dataset.ubicacion) {
            listingData.ubicacion = listingElement.dataset.ubicacion;
        }
        if (listingElement.dataset.categoria) {
            listingData.categoria = listingElement.dataset.categoria;
        }
    }
    
    return listingData;
},

startSessionPolling: function() {
    var self = this;
    
    // Borrar intervalo existente si hay uno
    if (this.pollingInterval) {
        clearInterval(this.pollingInterval);
    }
    
    // Verificar con menos frecuencia (2 minutos en lugar de 30 segundos)
    this.pollingInterval = setInterval(function() {
        if (self.data.isActive && self.data.sessionToken) {
            
            // Verificar sesión sin recargar automáticamente
            $.ajax({
                url: eqCartData.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'eq_check_context_status',
                    nonce: eqCartData.nonce,
                    timestamp: Date.now() // Evitar caché
                },
                success: function(response) {
                    if (response.success) {
                        if (!response.data.isActive && self.data.isActive) {
                            self.showNotification('La sesión ha cambiado. Actualice la página si ve inconsistencias.', 'warning');
                        }
                    }
                }
            });
        }
    }, 120000); // 2 minutos
},

verifySessionToken: function(token) {
    var self = this;
        
    /* Versión de respaldo
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'eq_verify_context_session',
            nonce: eqCartData.nonce,
            session_token: token
        },
        success: function(response) {
            if (response.success) {
                // Solo registrar el estado pero NO recargar
                if (!response.data.isActive && self.data.isActive) {
                    // Session change detected but not forcing reload
                }
            }
        }
    });
    */
},

clearLocalState: function() {
    
    // Limpiar objeto de datos
    this.data = {
        isActive: false,
        isMinimized: false,
        leadId: null,
        leadName: null,
        eventId: null,
        eventDate: null,
        eventType: null,
        sessionToken: null
    };
    
    // Guardar el estado limpio en sessionStorage
    this.saveToStorage();
    
    // Limpiar otras variables de sessionStorage relacionadas
    var keysToRemove = [];
    for (var i = 0; i < sessionStorage.length; i++) {
        var key = sessionStorage.key(i);
        if (key && (key.indexOf('eq_') === 0 || key.indexOf('eqQuote') === 0)) {
            keysToRemove.push(key);
        }
    }
    
    keysToRemove.forEach(function(key) {
        sessionStorage.removeItem(key);
    });
    
    // Limpiar localStorage relacionado (solo nuestras claves)
    localStorage.removeItem('eq_date_source');
    localStorage.removeItem('eq_panel_selected_date');
    localStorage.removeItem('eq_selected_date');
    localStorage.removeItem('eq_date_timestamp');
    
    // Limpiar DOM
    $('.eq-context-panel').remove();
    $('body').removeClass('has-eq-context-panel');
    
    // Mostrar botón toggle
    this.renderToggleButton();
},

// Método para verificar contexto en el servidor
checkServerContext: function(callback) {
    var self = this;
    
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        dataType: 'json',
        timeout: 5000, // 5 segundos timeout
        data: {
            action: 'eq_check_context_status',
            nonce: eqCartData.nonce,
            timestamp: Date.now() // Evitar caché
        },
        success: function(response) {
            if (typeof callback === 'function') {
                callback(response);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error checking context status:', status, error);
            
            // Si hay error, intentar cargar desde sessionStorage como fallback
            self.loadFromStorage();
            
            // Si tenemos datos válidos en sessionStorage, usarlos
            if (self.data.isActive && self.data.leadId && self.data.eventId) {
                if (typeof callback === 'function') {
                    callback({
                        success: true,
                        data: {
                            isActive: self.data.isActive,
                            leadId: self.data.leadId,
                            leadName: self.data.leadName,
                            eventId: self.data.eventId,
                            eventDate: self.data.eventDate,
                            eventType: self.data.eventType,
                            sessionToken: self.data.sessionToken
                        }
                    });
                }
            } else {
                if (typeof callback === 'function') {
                    callback({success: false, error: error});
                }
            }
        }
    });
}, 
        // Cargar datos desde sessionStorage
        loadFromStorage: function() {
    try {
        var savedData = sessionStorage.getItem('eqQuoteContext');
        if (savedData) {
            var parsedData = JSON.parse(savedData);
            this.data = parsedData;
            
            // Asegurar que isMinimized exista
            if (typeof this.data.isMinimized === 'undefined') {
                this.data.isMinimized = false;
            }
        }
    } catch (e) {
        console.error('Error loading context from sessionStorage', e);
    }
},
        
        // Guardar datos en sessionStorage
        saveToStorage: function(skipSync) {
            try {
                // Agregar timestamp de última actualización
                this.data.lastUpdate = Date.now();
                sessionStorage.setItem('eqQuoteContext', JSON.stringify(this.data));
                
                // Solo sincronizar si no se especifica skipSync
                if (!skipSync) {
                    // Sincronizar con otras pestañas usando localStorage temporal
                    localStorage.setItem('eq_context_data_sync', JSON.stringify(this.data));
                    // Eliminar inmediatamente para evitar acumulación
                    setTimeout(function() {
                        localStorage.removeItem('eq_context_data_sync');
                    }, 100);
                }
            } catch (e) {
                console.error('Error saving context to sessionStorage', e);
            }
        },
        
  renderPanel: function() {
    // SIEMPRE eliminar panel existente para evitar datos obsoletos
    $('.eq-context-panel').remove();
    
    
    
    // Crear HTML del panel usando traducciones
    var texts = eqCartData.texts || {};
    var panelHtml = 
        '<div class="eq-context-panel">' +
            '<div class="eq-context-panel-info">' +
                '<div class="eq-context-panel-section">' +
                    '<span class="eq-context-panel-label">' + (texts.lead || 'Lead') + ':</span>' +
                    '<span class="eq-context-panel-value" id="eq-context-lead-name">' + 
                        (this.data.leadName || texts.notSelected || 'No seleccionado') + 
                    '</span>' +
                '</div>' +
                '<div class="eq-context-panel-section">' +
                    '<span class="eq-context-panel-label">' + (texts.event || 'Evento') + ':</span>' +
                    '<span class="eq-context-panel-value" id="eq-context-event-info">' + 
                        this.formatEventInfo() + 
                    '</span>' +
                '</div>' +
            '</div>' +
            '<div class="eq-context-panel-actions">' +
                '<button type="button" class="eq-context-panel-button change-lead">' + (texts.changeLead || 'Seleccionar Lead') + '</button>' +
                '<button type="button" class="eq-context-panel-button change-event' + (this.data.leadId ? '' : ' disabled') + '">' + (texts.changeEvent || 'Seleccionar Evento') + '</button>' +
                '<button type="button" class="eq-context-panel-button end-session' + (this.data.isActive ? '' : ' disabled') + '">' + (texts.endSession || 'Finalizar Sesión') + '</button>' +
                '<button type="button" class="eq-context-panel-button toggle-panel">' + (texts.minimize || 'Minimizar') + '</button>' +
            '</div>' +
        '</div>';
	  
	 
        
    // Eliminar panel anterior si existe (seguridad adicional)
    $('.eq-context-panel').remove();
    
    // Añadir panel al body
    $('body').prepend(panelHtml);
    $('body').addClass('has-eq-context-panel');
    
    // FORZAR visibilidad del panel inmediatamente después de crearlo
    $('.eq-context-panel')
        .removeClass('eq-loading')
        .removeClass('eq-hidden')
        .show()
        .css('display', '')
        .css('visibility', 'visible');
    
    // Actualizar carrito si existe
    if (this.data.isActive) {
        this.updateCartWithContext();
    }

},


    // Función para verificar servidor en background sin afectar UI
    checkServerContextSilent: function(callback) {
        $.ajax({
            url: eqCartData.ajaxurl,
            type: 'POST',
            dataType: 'json',
            timeout: 5000,
            data: {
                action: 'eq_check_context_status',
                nonce: eqCartData.nonce,
                timestamp: Date.now()
            },
            success: function(response) {
                if (typeof callback === 'function') {
                    callback(response);
                }
            },
            error: function() {
                // Ignorar errores en verificación silent
                if (typeof callback === 'function') {
                    callback(null);
                }
            }
        });
    },

    // Función para verificar servidor con manejo completo de errores
    checkServerContextWithErrorHandling: function(callback) {
        var self = this;
        
        $.ajax({
            url: eqCartData.ajaxurl,
            type: 'POST',
            dataType: 'json',
            timeout: 5000,
            data: {
                action: 'eq_check_context_status',
                nonce: eqCartData.nonce,
                timestamp: Date.now()
            },
            success: function(response) {
                if (typeof callback === 'function') {
                    callback(true, response);
                }
            },
            error: function(xhr, status, error) {
                // Solo loggear errores no-timeout para debug
                if (status !== 'timeout') {
                    console.error('Context server error:', status);
                }
                
                // Intentar usar datos locales como fallback
                self.loadFromStorage();
                
                if (typeof callback === 'function') {
                    callback(false, null);
                }
            }
        });
    },
		
		renderToggleButton: function() {
    // Si ya existe el botón, no hacer nada
    if ($('.eq-context-toggle-button').length > 0) {
        return;
    }
    
    // Crear HTML del botón
    var buttonHtml = 
        '<button class="eq-context-toggle-button">' +
            '<i class="fas fa-clipboard-list"></i> ' +
            '<span>' + (eqCartData.texts?.quote || 'Quote') + '</span>' +
        '</button>';
        
    // Añadir botón al body
    $('body').append(buttonHtml);
    
    // Añadir evento click al botón
    $('.eq-context-toggle-button').on('click', this.activatePanel.bind(this));
},
		
		activatePanel: function() {
    var self = this;
    
    // Limpiar todas las señales de sesión finalizada
    document.cookie = 'eq_session_ended=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;';
    localStorage.removeItem('eq_context_session_force_clear');
    localStorage.removeItem('eq_context_session_ended');
    
    // Cargar datos locales si existen
    self.loadFromStorage();
    
    // Activar panel y mostrar INMEDIATAMENTE
    self.data.isActive = true;
    self.data.isMinimized = false;
    self.saveToStorage();
    
    // Renderizar panel con datos actuales (vacío o con datos)
    self.renderPanel();
    self.initEventListeners();
    
    // Eliminar botón toggle
    $('.eq-context-toggle-button').remove();
    
    // Verificar que el panel realmente se esté mostrando
    setTimeout(function() {
        if (!$('.eq-context-panel').is(':visible')) {
            // Intentar forzar visibilidad una vez más
            $('.eq-context-panel')
                .removeClass('eq-hidden')
                .removeClass('eq-loading')
                .css({'display': 'flex', 'visibility': 'visible', 'opacity': '1'});
        }
    }, 100);
    
    // Si no hay datos completos, abrir modal de lead automáticamente
    if (!self.data.leadId || !self.data.eventId) {
        setTimeout(function() {
            self.openLeadModal();
        }, 100);
    }
    
    // Verificar servidor en BACKGROUND sin bloquear UI (opcional)
    self.checkServerContextSilent(function(response) {
        if (response && response.success && response.data && response.data.isActive) {
            // Solo actualizar si hay diferencias significativas
            if (response.data.leadId !== self.data.leadId || 
                response.data.eventId !== self.data.eventId) {
                self.data.leadId = response.data.leadId;
                self.data.leadName = response.data.leadName;
                self.data.eventId = response.data.eventId;
                self.data.eventDate = response.data.eventDate;
                self.data.eventType = response.data.eventType;
                self.data.sessionToken = response.data.sessionToken;
                self.saveToStorage();
                self.renderPanel();
            }
        }
    });
},
togglePanel: function() {
    var self = this;
    
    // Cambiar estado de minimizado
    this.data.isMinimized = !this.data.isMinimized;
    
    if (this.data.isMinimized) {
        // Minimizar panel
        $('.eq-context-panel').slideUp(200, function() {
            // Después de la animación, mostrar el botón toggle
            self.renderToggleButton();
            
            // Ajustar padding del body
            $('body').removeClass('has-eq-context-panel').addClass('has-eq-context-minimized');
        });
    } else {
        // Maximizar panel
        $('.eq-context-toggle-button').remove();
        $('.eq-context-panel').slideDown(200);
        $('body').addClass('has-eq-context-panel').removeClass('has-eq-context-minimized');
    }
    
    // Guardar estado
    this.saveToStorage();
},
		// Función para formatear fechas en formato amigable
formatFriendlyDate: function(date) {
    if (!date || date === '0' || date === 0 || date === '0000-00-00') return '';
    
    
    let dateObj;
    
    // Si es un número o una cadena que parece un timestamp completo
    if (typeof date === 'number' || 
        (typeof date === 'string' && !isNaN(parseInt(date)) && 
         !date.includes('-') && date.length > 8)) {
        
        let timestamp = parseInt(date);
        
        // Validar que el timestamp no sea 0 o inválido
        if (timestamp === 0 || timestamp < 86400) {
            return '';
        }
        
        if (timestamp < 10000000000) {
            timestamp = timestamp * 1000;
        }
        dateObj = new Date(timestamp);
    } 
    // Si es una cadena en formato YYYY-MM-DD
    else if (typeof date === 'string' && date.match(/^\d{4}-\d{2}-\d{2}$/)) {
        // Dividir la fecha en partes
        const parts = date.split('-');
        // Crear un objeto Date con el año, mes (0-11) y día, forzando UTC
        // Usamos 12:00:00 UTC para evitar problemas de zona horaria
        dateObj = new Date(Date.UTC(parseInt(parts[0]), parseInt(parts[1])-1, parseInt(parts[2]), 12, 0, 0));
    } 
    // Cualquier otro formato
    else {
        dateObj = new Date(date);
    }
    
    
    if (isNaN(dateObj.getTime())) {
        console.error('Fecha inválida:', date);
        return date;
    }
    
    try {
        const options = { 
            day: 'numeric', 
            month: 'long', 
            year: 'numeric',
            timeZone: 'UTC' // Forzar a que se interprete en UTC
        };
        
        const formatted = dateObj.toLocaleDateString('es-ES', options);
        return formatted;
    } catch (e) {
        console.error('Error al formatear fecha:', e);
        return date;
    }
},
        
        // Formatear información del evento para mostrar
       formatEventInfo: function() {
		   

    if (!this.data.eventId) {
        return eqCartData.texts?.notSelected || 'No seleccionado';
    }
    
    var info = '';
    if (this.data.eventType) {
        info += this.data.eventType;
    }
    
    if (this.data.eventDate) {
        if (info) info += ' - ';
        // Usar el nuevo formateador
        info += this.formatFriendlyDate(this.data.eventDate);
    }
    
    return info || 'Evento #' + this.data.eventId;
},
        
        // Actualizar el carrito con el contexto actual
        updateCartWithContext: function() {
            // Si estamos en la página del carrito, actualizar estados
            if (window.location.href.indexOf('quote-cart') > -1) {
                // Añadir información del contexto a la página
                var contextInfo = '<div class="eq-context-info-banner">' +
                    '<strong>Cotizando para:</strong> ' + this.data.leadName + ' - ' + this.formatEventInfo() +
                    '</div>';
                    
                $('.quote-cart-header').after(contextInfo);
                
                // Actualizar estado de los botones si es necesario
            }
        },
        
       initEventListeners: function() {
    var self = this;
    
    
    // Eliminar handlers previos para evitar duplicados
    $(document).off('click', '.eq-context-panel-button.change-lead');
    $(document).off('click', '.eq-context-panel-button.toggle-panel');
    $(document).off('click', '.eq-context-panel-button.change-event');
    $(document).off('click', '.eq-context-panel-button.end-session');
    
    // Delegación de eventos para los botones del panel
    $(document).on('click', '.eq-context-panel-button.change-lead', function() {
        self.openLeadModal();
    });
    
    $(document).on('click', '.eq-context-panel-button.toggle-panel', function() {
        self.togglePanel();
    });
    
    $(document).on('click', '.eq-context-panel-button.change-event', function() {
        if (!self.data.leadId) {
            alert('Primero debe seleccionar un lead');
            return;
        }
        self.openEventModal();
    });
    
    $(document).on('click', '.eq-context-panel-button.end-session', function() {
        self.endSession();
    });
            
            // Interceptar añadir al carrito para incluir contexto
            $(document).on('click', '.eq-quote-button', function() {
                // Si estamos sincronizando, prevenir acciones
                if (self.data.isSyncing) {
                    self.showNotification('Por favor espere mientras se sincroniza el contexto', 'warning');
                    return false;
                }
                
                // Si hay un contexto activo, verificar que estemos listos para añadir al carrito
                if (self.data.isActive && (!self.data.leadId || !self.data.eventId)) {
                    alert('Debe seleccionar un lead y un evento antes de añadir productos al carrito');
                    return false;
                }
            });
        },
        
       initModals: function() {
    var modalsHtml = 
        // Modal de selección de lead
        '<div class="eq-modal-backdrop" id="eq-lead-modal-backdrop"></div>' +
        '<div class="eq-modal" id="eq-lead-modal">' +
            '<div class="eq-modal-header">' +
                '<h3 class="eq-modal-title">' + (eqCartData.texts?.selectLead || 'Select Lead') + '</h3>' +
                '<button type="button" class="eq-modal-close">&times;</button>' +
            '</div>' +
            '<div class="eq-modal-body">' +
                '<input type="text" class="eq-search-input" id="eq-lead-search" placeholder="' + (eqCartData.texts?.searchLead || 'Search lead...') + '">' +
                '<div class="eq-search-results" id="eq-lead-results"></div>' +
                '<div class="eq-create-new">' +
                    '<h4>' + (eqCartData.texts?.createNewLead || 'Create new lead') + '</h4>' +
                    '<div class="eq-form-row eq-form-two-columns">' +
                        '<div class="eq-form-column">' +
                            '<div class="eq-form-field">' +
                                '<label class="eq-form-label">' + (eqCartData.texts?.companyName || 'Company Name') + '</label>' +
                                '<input type="text" class="eq-form-input" id="eq-new-lead-razon-social">' +
                            '</div>' +
                            '<div class="eq-form-field">' +
                                '<label class="eq-form-label">' + (eqCartData.texts?.firstName || 'First Name') + '</label>' +
                                '<input type="text" class="eq-form-input" id="eq-new-lead-name">' +
                            '</div>' +
                            '<div class="eq-form-field">' +
                                '<label class="eq-form-label">' + (eqCartData.texts?.lastName || 'Last Name') + '</label>' +
                                '<input type="text" class="eq-form-input" id="eq-new-lead-apellido">' +
                            '</div>' +
                        '</div>' +
                        '<div class="eq-form-column">' +
                            '<div class="eq-form-field">' +
                                '<label class="eq-form-label">Email</label>' +
                                '<input type="email" class="eq-form-input" id="eq-new-lead-email">' +
                            '</div>' +
                            '<div class="eq-form-field">' +
                                '<label class="eq-form-label">Teléfono</label>' +
                                '<input type="tel" class="eq-form-input" id="eq-new-lead-phone">' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div id="eq-lead-exists-message" class="eq-form-message" style="display: none;"></div>' +
                '</div>' +
            '</div>' +
            '<div class="eq-modal-footer">' +
                '<button type="button" class="button" id="eq-lead-cancel">Cancelar</button>' +
                '<button type="button" class="button button-primary" id="eq-lead-create">Crear Lead</button>' +
                '<button type="button" class="button button-primary" id="eq-lead-select">' + (eqCartData.texts?.select || 'Select') + '</button>' +
            '</div>' +
        '</div>' +
                
                // Modal de selección de evento
'<div class="eq-modal-backdrop" id="eq-event-modal-backdrop"></div>' +
'<div class="eq-modal" id="eq-event-modal">' +
    '<div class="eq-modal-header">' +
        '<h3 class="eq-modal-title">' + (eqCartData.texts?.selectEvent || 'Select Event') + '</h3>' +
        '<button type="button" class="eq-modal-close">&times;</button>' +
    '</div>' +
    '<div class="eq-modal-body">' +
        '<div class="eq-search-results" id="eq-event-results"></div>' +
        '<div class="eq-create-new">' +
            '<h4>Crear nuevo evento</h4>' +
            '<div class="eq-form-row eq-form-two-columns">' +
                '<div class="eq-form-column">' +
                    '<div class="eq-form-field">' +
                        '<label class="eq-form-label">Tipo de Evento</label>' +
                        '<select class="eq-form-input" id="eq-new-event-type">' +
                            '<option value="">Seleccione...</option>' +
                            '<option value="Boda">Boda</option>' +
                            '<option value="Corporativo">Corporativo</option>' +
                            '<option value="Social">Social</option>' +
                            '<option value="Cumpleaños">Cumpleaños</option>' +
                            '<option value="Otro">Otro</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="eq-form-field">' +
                        '<label class="eq-form-label">Fecha del Evento</label>' +
                        '<input type="text" class="eq-form-input flatpickr" id="eq-new-event-date">' +
                    '</div>' +
                    '<div class="eq-form-field">' +
    '<label class="eq-form-label">Estado</label>' +
    '<select class="eq-form-input" id="eq-new-event-status">';

// Verificar si tenemos los status dinámicos
if (typeof eqStatusConfig !== 'undefined' && eqStatusConfig.statusOptions) {
    // Agregar opciones dinámicamente
    for (var statusValue in eqStatusConfig.statusOptions) {
        modalsHtml += '<option value="' + statusValue + '">' + 
                      eqStatusConfig.statusOptions[statusValue] + '</option>';
    }
} else {
    // Opciones de respaldo por si no están disponibles los status dinámicos
    modalsHtml += 
        '<option value="nuevo">Nuevo</option>' +
        '<option value="con-presupuesto">Con Presupuesto</option>' +
        '<option value="por-cerrar">Por cerrar</option>' +
        '<option value="con-contrato">Con contrato</option>' +
        '<option value="perdido">Perdido</option>';
}

modalsHtml += '</select>' +
'</div>' + 
                '</div>' +
                '<div class="eq-form-column">' +
                    '<div class="eq-form-field">' +
                        '<label class="eq-form-label">Número de Invitados</label>' +
                        '<input type="number" class="eq-form-input" id="eq-new-event-guests">' +
                    '</div>' +
                  
                    '<div class="eq-form-field">' +
                        '<label class="eq-form-label">Dirección</label>' +
                        '<input type="text" class="eq-form-input" id="eq-new-event-direccion">' +
                    '</div>' +
							// Campos ocultos para ubicación y categoría
					'<input type="hidden" id="eq-new-event-ubicacion" name="ubicacion_evento" value="">' +
					'<input type="hidden" id="eq-new-event-categoria" name="categoria_listing_post" value="">' +
                '</div>' +
            '</div>' +
            '<div class="eq-form-field eq-form-full-width">' +
                '<label class="eq-form-label">Comentarios</label>' +
                '<textarea class="eq-form-input" id="eq-new-event-comentarios" rows="3"></textarea>' +
            '</div>' +
        '</div>' +
    '</div>' +
    '<div class="eq-modal-footer">' +
        '<button type="button" class="button" id="eq-event-cancel">Cancelar</button>' +
        '<button type="button" class="button button-primary" id="eq-event-create">Crear Evento</button>' +
        '<button type="button" class="button button-primary" id="eq-event-select">' + (eqCartData.texts?.select || 'Select') + '</button>' +
    '</div>' +
'</div>';
                
            // Añadir modales al final del body
            $('body').append(modalsHtml);
            
            // Inicializar controladores de eventos para modales
            this.initModalEvents();
        },
        
        // Inicializar eventos para modales
        initModalEvents: function() {
            var self = this;
            
            // Cerrar modales
            $('.eq-modal-close, #eq-lead-cancel, #eq-event-cancel').on('click', function() {
                self.closeModals();
            });
            
            // Cerrar al hacer clic en el backdrop
            $('.eq-modal-backdrop').on('click', function() {
                self.closeModals();
            });
            
            // Evitar cierre al hacer clic en el modal
            $('.eq-modal').on('click', function(e) {
                e.stopPropagation();
            });
            
            // Buscar leads
            var leadSearchTimeout;
            $('#eq-lead-search').on('keyup', function() {
                clearTimeout(leadSearchTimeout);
                var searchTerm = $(this).val();
                
                leadSearchTimeout = setTimeout(function() {
                    self.searchLeads(searchTerm);
                }, 300);
            });
            
            // Seleccionar lead
            $('#eq-lead-select').on('click', function() {
                var selectedLead = $('#eq-lead-results .eq-search-item.selected');
                if (selectedLead.length === 0) {
                    alert('Por favor seleccione un lead');
                    return;
                }
                
                self.selectLead(
                    selectedLead.data('id'),
                    selectedLead.data('name')
                );
                
                self.closeModals();
                self.openEventModal();
            });
            
            // Crear nuevo lead
            $('#eq-lead-create').on('click', function() {
                var name = $('#eq-new-lead-name').val();
                var apellido = $('#eq-new-lead-apellido').val();
                var email = $('#eq-new-lead-email').val();
                var phone = $('#eq-new-lead-phone').val();
                
                if (!name || !apellido) {
                    alert('Nombre y apellido son obligatorios');
                    return;
                }
                
                self.createLead(name, apellido, email, phone);
            });
            
            // Seleccionar evento desde la lista
            $('#eq-event-select').on('click', function() {
                var selectedEvent = $('#eq-event-results .eq-search-item.selected');
                if (selectedEvent.length === 0) {
                    alert('Por favor seleccione un evento');
                    return;
                }
                
                self.selectEvent(
                    selectedEvent.data('id'),
                    selectedEvent.data('date'),
                    selectedEvent.data('type')
                );
                
                self.closeModals();
            });
            
            // Crear nuevo evento
            $('#eq-event-create').on('click', function() {
                var type = $('#eq-new-event-type').val();
                var date = $('#eq-new-event-date').val();
                var guests = $('#eq-new-event-guests').val();
                
                if (!type || !date) {
                    alert('Tipo y fecha son obligatorios');
                    return;
                }
                
                // Validar formato de fecha
                var dateRegex = /^\d{4}-\d{2}-\d{2}$/;
                if (!dateRegex.test(date)) {
                    alert('Formato de fecha inválido. Debe ser YYYY-MM-DD');
                    return;
                }
                
                // Validar que la fecha sea parseable
                var testDate = new Date(date);
                if (isNaN(testDate.getTime())) {
                    alert('Fecha inválida');
                    return;
                }
                
                self.createEvent(type, date, guests);
            });
            
// Inicializar flatpickr para selector de fecha
if (typeof flatpickr !== 'undefined') {
    flatpickr('#eq-new-event-date', {
        dateFormat: 'Y-m-d',
        minDate: 'today',
        locale: 'es',  // Configurar español
        disableMobile: false,  // Permitir que funcione en móviles
        position: 'auto', // Posicionamiento automático
        static: true,  // Hace que el calendario no se oculte en dispositivos táctiles
        appendTo: document.getElementById('eq-event-modal'),  // Lo anexa directamente al modal
        onOpen: function() {
            // Asegurar que sea visible en móviles
            setTimeout(function() {
                var calendar = document.querySelector('.flatpickr-calendar');
                if (calendar) {
                    calendar.style.zIndex = "1000010"; // Z-index más alto
                    calendar.style.visibility = "visible";
                }
            }, 100);
        },
        onChange: function(selectedDates, dateStr, instance) {
            // Validar si la fecha seleccionada es pasada
            if (selectedDates.length > 0) {
                var selectedDate = selectedDates[0];
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate < today) {
                    // Mostrar warning para fechas pasadas
                    var warning = document.getElementById('eq-past-date-warning');
                    if (!warning) {
                        warning = document.createElement('div');
                        warning.id = 'eq-past-date-warning';
                        warning.style.cssText = 'color: #d63384; font-size: 12px; margin-top: 5px; font-weight: bold;';
                        warning.textContent = (eqCartData.texts?.pastDateWarning || 'Warning: This date is in the past. Please ensure all information is completed.');
                        instance.input.parentNode.appendChild(warning);
                    }
                } else {
                    // Remover warning si existe
                    var warning = document.getElementById('eq-past-date-warning');
                    if (warning) {
                        warning.remove();
                    }
                }
            }
        }
    });
}
        },
        
        // Abrir modal de lead
        openLeadModal: function() {
            $('#eq-lead-modal-backdrop, #eq-lead-modal').show();
            $('#eq-lead-search').focus();
            this.searchLeads('');
        },
        
        // Abrir modal de evento
        openEventModal: function() {
    // Obtener datos del listing actual si estamos en una página de listing
    var listingData = this.getCurrentListingData();
    
    // Mostrar modal
    $('#eq-event-modal-backdrop, #eq-event-modal').show();
    
    // Establecer valores de campos ocultos si están disponibles
    if (listingData.ubicacion) {
        $('#eq-new-event-ubicacion').val(listingData.ubicacion);
    }
    if (listingData.categoria) {
        $('#eq-new-event-categoria').val(listingData.categoria);
    }
    
    // Cargar eventos existentes
    this.loadEvents();
},
        
        // Cerrar todos los modales
        closeModals: function() {
            $('.eq-modal-backdrop, .eq-modal').hide();
        },
        
        // Buscar leads
        searchLeads: function(term) {
            var self = this;
            
            $('#eq-lead-results').html('<div class="eq-loading">Buscando...</div>');
            
            $.ajax({
                url: eqCartData.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'eq_search_leads',
                    nonce: eqCartData.nonce,
                    term: term
                },
                success: function(response) {
                    if (response.success) {
                        self.renderLeadResults(response.data.leads);
                    } else {
                        $('#eq-lead-results').html('<div class="eq-no-results">Error: ' + response.data + '</div>');
                    }
                },
                error: function() {
                    $('#eq-lead-results').html('<div class="eq-no-results">Error de conexión</div>');
                }
            });
        },
        
        // Renderizar resultados de búsqueda de leads
        renderLeadResults: function(leads) {
            var resultsHtml = '';
            
            if (leads.length === 0) {
                resultsHtml = '<div class="eq-no-results">No se encontraron resultados</div>';
            } else {
                for (var i = 0; i < leads.length; i++) {
                    var lead = leads[i];
                    resultsHtml += 
                        '<div class="eq-search-item" ' +
                            'data-id="' + lead.lead_id + '" ' +
                            'data-name="' + lead.lead_nombre + ' ' + lead.lead_apellido + '">' +
                            '<strong>' + lead.lead_nombre + ' ' + lead.lead_apellido + '</strong><br>' +
                            '<small>' + lead.lead_e_mail + ' | ' + lead.lead_celular + '</small>' +
                        '</div>';
                }
            }
            
            $('#eq-lead-results').html(resultsHtml);
            
            // Añadir evento de clic a los items
            $('#eq-lead-results .eq-search-item').on('click', function() {
                $('#eq-lead-results .eq-search-item').removeClass('selected');
                $(this).addClass('selected');
            });
        },
        
        // Seleccionar un lead
        selectLead: function(leadId, leadName) {
            this.data.leadId = leadId;
            this.data.leadName = leadName;
            this.data.eventId = null;
            this.data.eventDate = null;
            this.data.eventType = null;
            this.data.isActive = true;
            
            // SIEMPRE recrear panel para mostrar datos actualizados inmediatamente
            this.renderPanel();
            
            this.saveToStorage();
        },
        
        // Crear un nuevo lead
        createLead: function(name, apellido, email, phone) {
            var self = this;
            
            $.ajax({
                url: eqCartData.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'eq_create_lead',
                    nonce: eqCartData.nonce,
                    nombre: name,
                    apellido: apellido,
                    email: email,
                    celular: phone
                },
                success: function(response) {
                    if (response.success) {
                        var leadId = response.data.lead_id;
                        var leadName = name + ' ' + apellido;
                        
                        self.selectLead(leadId, leadName);
                        self.closeModals();
                        self.openEventModal();
                    } else {
                        alert('Error al crear lead: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error de conexión');
                }
            });
        },
        
        // Cargar eventos de un lead
        loadEvents: function() {
            var self = this;
            
            if (!this.data.leadId) {
                $('#eq-event-results').html('<div class="eq-no-results">Primero debe seleccionar un lead</div>');
                return;
            }
            
            $('#eq-event-results').html('<div class="eq-loading">Cargando eventos...</div>');
            
            $.ajax({
                url: eqCartData.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'eq_get_lead_events',
                    nonce: eqCartData.nonce,
                    lead_id: this.data.leadId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderEventResults(response.data.eventos);
                    } else {
                        $('#eq-event-results').html('<div class="eq-no-results">Error: ' + response.data + '</div>');
                    }
                },
                error: function() {
                    $('#eq-event-results').html('<div class="eq-no-results">Error de conexión</div>');
                }
            });
        },
        
        // Renderizar resultados de eventos
        renderEventResults: function(eventos) {
            var resultsHtml = '';
            
            if (eventos.length === 0) {
                resultsHtml = '<div class="eq-no-events-message">' +
                    '<div class="eq-no-events-icon"><i class="fas fa-calendar-times"></i></div>' +
                    '<div class="eq-no-events-text">' +
                        '<strong>No hay eventos futuros asignados</strong><br>' +
                        '<small>Los eventos pasados no se muestran para nuevas cotizaciones</small>' +
                    '</div>' +
                '</div>';
            } else {
                for (var i = 0; i < eventos.length; i++) {
                    var evento = eventos[i];
                    resultsHtml += 
                        '<div class="eq-search-item" ' +
                            'data-id="' + evento.evento_id + '" ' +
                            'data-date="' + evento.fecha_de_evento + '" ' +
                            'data-type="' + evento.tipo_de_evento + '">' +
                            '<strong>' + evento.tipo_de_evento + '</strong><br>' +
							'<small>' + (evento.fecha_formateada || evento.fecha_de_evento) + ' | ' +						
                            evento.evento_asistentes + ' invitados</small>' +
                        '</div>';
                }
            }
            
            $('#eq-event-results').html(resultsHtml);
            
            // Añadir evento de clic a los items
            $('#eq-event-results .eq-search-item').on('click', function() {
                $('#eq-event-results .eq-search-item').removeClass('selected');
                $(this).addClass('selected');
            });
        },
        
  selectEvent: function(eventId, eventDate, eventType) {
    var self = this;
    
    // Eliminar la cookie de sesión finalizada
    document.cookie = 'eq_session_ended=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;';
    // Eliminar flags en localStorage
    localStorage.removeItem('eq_context_session_force_clear');
    localStorage.removeItem('eq_context_session_ended');
    
    // Actualizar datos locales
    this.data.eventId = eventId;
    this.data.eventDate = eventDate;
    this.data.eventType = eventType;
    
    // SIEMPRE recrear panel para mostrar datos actualizados inmediatamente
    this.renderPanel();
    
    // Crear sesión de contexto en el servidor
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'eq_create_context_session',
            nonce: eqCartData.nonce,
            lead_id: this.data.leadId,
            event_id: eventId
        },
        success: function(response) {
    if (response.success) {
        
        // Guardar token de sesión
        self.data.sessionToken = response.data.session_token;
        
        // Sincronizar fecha con otros componentes
        self.syncEventDate(eventDate);
        
        // Guardar en sessionStorage
        self.saveToStorage();
        
        // Actualizar carrito con lead_id y event_id
$.ajax({
    url: eqCartData.ajaxurl,
    type: 'POST',
    dataType: 'json',
    data: {
        action: 'eq_update_cart_context',
        nonce: eqCartData.nonce,
        lead_id: self.data.leadId,
        event_id: eventId
    },
    success: function(response) {
        if (response.success) {
            
            // Notificar al usuario
            self.showNotification('Evento seleccionado y contexto actualizado', 'success');
            
            // Disparar evento de cambio de contexto
            $(document).trigger('eqContextChanged', [{
                leadId: self.data.leadId,
                leadName: self.data.leadName,
                eventId: eventId,
                eventType: eventType,
                eventDate: eventDate
            }]);
            
            // NO recargar página automáticamente - mantener actualización en tiempo real
            if (self.isCreatingNewEvent) {
                // Limpiar el flag
                self.isCreatingNewEvent = false;
            }
            
            // Removed automatic forceRefreshPanel() to prevent race condition
            // Panel is already updated correctly with local data above
        } else {
                    console.error('Error updating cart context:', response.data);
                    self.showNotification('Error al actualizar contexto', 'error');
                }
            },
            error: function() {
                console.error('Error connecting to server');
                self.showNotification('Error de conexión', 'error');
            }
        });
    } else {
        console.error('Error creating context session:', response.data);
        self.showNotification('Error al crear sesión de contexto', 'error');
    }
},
        error: function() {
            console.error('Error connecting to server');
            self.showNotification('Error de conexión', 'error');
        }
    });
},

// Método para sincronizar fecha con otros componentes
syncEventDate: function(date) {
    // 1. Parsear la fecha correctamente asegurando formato universal
    var formattedDate = date;
    var dateObj;


    // Si es timestamp
    if (typeof date === 'number' || (typeof date === 'string' && !isNaN(parseInt(date)) && !date.includes('-'))) {
        var timestamp = parseInt(date);
        if (timestamp > 1000000000000) { // Si es en milisegundos
            timestamp = Math.floor(timestamp / 1000);
        }
        
        // Crear fecha a partir del timestamp y normalizarla a UTC a mediodía
        var tempDate = new Date(timestamp * 1000);
        var year = tempDate.getUTCFullYear();
        var month = tempDate.getUTCMonth(); // 0-indexed
        var day = tempDate.getUTCDate();
        
        // Crear nueva fecha a las 12:00 UTC para evitar problemas de zonas horarias
        dateObj = new Date(Date.UTC(year, month, day, 12, 0, 0));
    } 
    // Si ya es string en formato fecha con guiones (YYYY-MM-DD)
    else if (typeof date === 'string' && date.includes('-')) {
        // Extraer directamente los componentes de la fecha
        var parts = date.split('-');
        if (parts.length === 3) {
            var year = parseInt(parts[0]);
            var month = parseInt(parts[1]) - 1; // Meses en JS son 0-indexed
            var day = parseInt(parts[2]);
            
            // Verificar que sean números válidos
            if (!isNaN(year) && !isNaN(month) && !isNaN(day)) {
                // Usar directamente los componentes extraídos para la fecha formateada
                formattedDate = year + '-' + 
                               String(month + 1).padStart(2, '0') + '-' + 
                               String(day).padStart(2, '0');
                
                // También crear un objeto de fecha para otras operaciones
                dateObj = new Date(Date.UTC(year, month, day, 12, 0, 0));
                
            } else {
                console.error('Error parseando partes de la fecha:', parts);
                return;
            }
        } else {
            console.error('Formato de fecha no reconocido:', date);
            return;
        }
    }
    // Cualquier otro formato de fecha
    else if (typeof date === 'string') {
        // Intentar parsear y normalizar a UTC mediodía
        var tempDate = new Date(date);
        if (!isNaN(tempDate.getTime())) {
            var year = tempDate.getFullYear();
            var month = tempDate.getMonth();
            var day = tempDate.getDate();
            dateObj = new Date(Date.UTC(year, month, day, 12, 0, 0));
            
            // Formatear manualmente
            formattedDate = year + '-' + 
                          String(month + 1).padStart(2, '0') + '-' + 
                          String(day).padStart(2, '0');
        } else {
            console.error('No se pudo parsear la fecha:', date);
            return;
        }
    }

    if (!dateObj) {
        console.error('Unable to parse date:', date);
        return; // No continuar si no podemos parsear la fecha
    }

    // Para casos de fecha ISO, ya tenemos formattedDate directamente de los componentes
    // Para otros casos, lo calculamos a partir del objeto de fecha
    if (!(typeof date === 'string' && date.includes('-'))) {
        formattedDate = dateObj.getUTCFullYear() + '-' + 
                    String(dateObj.getUTCMonth() + 1).padStart(2, '0') + '-' + 
                    String(dateObj.getUTCDate()).padStart(2, '0');
    }

    
    // Guardar en localStorage para referencia
    localStorage.setItem('eq_date_source', 'panel');
    localStorage.setItem('eq_panel_selected_date', formattedDate);
    localStorage.setItem('eq_selected_date', formattedDate);
    localStorage.setItem('eq_date_timestamp', Date.now().toString());
    
    var listingId = this.getCurrentListingId();
    
    if (listingId) {
        // Si estamos en una página de listing, verificar disponibilidad primero
        this.checkDateAvailability(listingId, formattedDate, function(isAvailable) {
            if (isAvailable) {
                // Solo actualizar si la fecha está disponible
                this.updateDateDisplays(formattedDate, dateObj);
            } else {
                // Mostrar alerta si la fecha no está disponible
                this.showNotification('La fecha del panel (' + formattedDate + ') no está disponible para este listing. Por favor seleccione otra fecha.', 'warning');
            }
        }.bind(this));
    } else {
        // Si no estamos en una página de listing, actualizar sin verificar
        this.updateDateDisplays(formattedDate, dateObj);
    }
},

// NUEVA FUNCIÓN: Obtener el ID del listing actual
getCurrentListingId: function() {
    // Intentar obtener el listing ID de varias fuentes posibles
    var listingId = null;
    
    // 1. Verificar si hay un campo oculto en el formulario de booking
    var hiddenField = document.querySelector('input[name="listing_id"]');
    if (hiddenField) {
        listingId = hiddenField.value;
    }
    
    // 2. Verificar si está en una URL de listing
    if (!listingId && window.location.pathname.includes('/listing/')) {
        // Intentar extraer de la URL
        var matches = window.location.pathname.match(/\/listing\/([^\/]+)/);
        if (matches && matches[1]) {
            // Buscar el ID en el DOM
            var bodyClasses = document.body.className;
            var idMatch = bodyClasses.match(/postid-(\d+)/);
            if (idMatch && idMatch[1]) {
                listingId = idMatch[1];
            }
        }
    }
    
    // 3. Verificar data attributes en botones de cotización
    if (!listingId) {
        var quoteButton = document.querySelector('.boton-cotizar');
        if (quoteButton && quoteButton.dataset.listingId) {
            listingId = quoteButton.dataset.listingId;
        }
    }
    
    return listingId;
},

// NUEVA FUNCIÓN: Verificar disponibilidad de fecha para un listing
checkDateAvailability: function(listingId, date, callback) {
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'eq_validate_date',
            nonce: eqCartData.nonce,
            listing_id: listingId,
            date: date
        },
        success: function(response) {
            if (response.success) {
                callback(response.data.available);
            } else {
                console.error('Error validating date:', response.data);
                callback(false); // Asumir que no está disponible en caso de error
            }
        },
        error: function() {
            console.error('AJAX error checking date availability');
            callback(false); // Asumir que no está disponible en caso de error
        }
    });
},

// NUEVA FUNCIÓN: Actualizar todos los displays de fecha
updateDateDisplays: function(formattedDate, dateObj) {
    
    // 1. Actualizar selectores genéricos
    var bookingDatepickers = document.querySelectorAll('.eq-date-picker, .bv-date-input');
    var updatedAny = false;
    
    bookingDatepickers.forEach(function(input) {
        // Verificar si el input está visible - no actualizar campos ocultos
        if (input.offsetParent !== null) {
            
            // Forzar la actualización del valor
            input.value = formattedDate;
            
            // Disparar evento de cambio
            var event = new Event('change', { bubbles: true });
            input.dispatchEvent(event);
            
            if (input._flatpickr) {
                input._flatpickr.setDate(dateObj);
            }
            
            updatedAny = true;
        } else {
            // Skipping hidden input
        }
    });
    
    // 2. Actualizar también el filtro del cotizador si está presente y visible
    var filterInput = document.getElementById('fecha');
    if (filterInput && filterInput.offsetParent !== null) {
        filterInput.value = formattedDate;
        updatedAny = true;
    }
    
    // 3. Interfaz directa con BookingForm si está disponible
    if (window._bookingFormInstance) {
        try {
            // Verificar si los inputs son visibles antes de actualizar
            var visibleInputs = window._bookingFormInstance.dateInputs.filter(function() {
                return $(this).is(':visible');
            });
            
            if (visibleInputs.length > 0) {
                // Actualizar sólo inputs visibles
                visibleInputs.val(formattedDate);
                
                // Actualizar flatpickr si existe
                if (visibleInputs[0]._flatpickr) {
                    visibleInputs[0]._flatpickr.setDate(dateObj);
                }
                
                // Actualizar el display manualmente con formato específico
                var displayValue = window._bookingFormInstance.dateBlock.find('.bv-block-value');
                if (displayValue.length && displayValue.is(':visible')) {
                    // Usar el formato nativo americano (MM/DD/YYYY) como el resto del sistema
                    var displayDate = (dateObj.getMonth() + 1) + '/' + dateObj.getDate() + '/' + dateObj.getFullYear();
                    displayValue.text(displayDate);
                }
                
                // Recalcular totales
                setTimeout(function() {
                    if (typeof window._bookingFormInstance.calculateTotals === 'function') {
                        window._bookingFormInstance.calculateTotals();
                    }
                }, 100);
                
                updatedAny = true;
            } else {
                // BookingForm inputs are hidden, skipping update
            }
        } catch (e) {
            console.error('Error updating BookingForm:', e);
        }
    }
    
    // 4. Disparar eventos personalizados solo si actualizamos algún campo
    if (updatedAny) {
        // Evento específico para el panel
        $(document).trigger('eqContextDateChanged', [formattedDate]);
        
        // Evento general con flags adicionales
        $(document).trigger('eqDateChanged', [formattedDate, { 
            fromPanel: true,
            timestamp: Date.now(),
            force: true // Indicar que debe tener prioridad
        }]);
        
    }
},
        
     createEvent: function(type, date, guests) {
    var self = this;
    
    if (!this.data.leadId) {
        alert('Primero debe seleccionar un lead');
        return;
    }
    
    // Establecer flag para indicar que estamos creando un nuevo evento
    this.isCreatingNewEvent = true;
    
    // Obtener valores adicionales
    var status = $('#eq-new-event-status').val() || 'nuevo';
    var ubicacion = $('#eq-new-event-ubicacion').val() || '';
    var categoria = $('#eq-new-event-categoria').val() || '';
    var direccion = $('#eq-new-event-direccion').val() || '';
    var comentarios = $('#eq-new-event-comentarios').val() || '';
    
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'eq_create_event',
            nonce: eqCartData.nonce,
            lead_id: this.data.leadId,
            tipo_evento: type,
            fecha_de_evento: date,
            asistentes: guests,
            evento_status: status,
            ubicacion_evento: ubicacion,
            categoria_listing_post: categoria,
            direccion_evento: direccion,
            comentarios_evento: comentarios
        },
        success: function(response) {
            if (response.success) {
                var eventId = response.data.evento_id;
                
                self.selectEvent(eventId, date, type);
                self.closeModals();
            } else {
                self.isCreatingNewEvent = false;
                alert('Error al crear evento: ' + response.data);
            }
        },
        error: function() {
            self.isCreatingNewEvent = false;
            alert('Error de conexión');
        }
    });
},
        
        // Actualizar carrito con lead_id y event_id
        updateCartWithLeadEvent: function() {
            var self = this;
            
            if (!this.data.leadId || !this.data.eventId) {
                return;
            }
            
            $.ajax({
                url: eqCartData.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'eq_update_cart_context',
                    nonce: eqCartData.nonce,
                    lead_id: this.data.leadId,
                    event_id: this.data.eventId
                },
                success: function(response) {
                    if (!response.success) {
                        console.error('Error al actualizar contexto del carrito', response.data);
                    }
                },
                error: function() {
                    console.error('Error de conexión al actualizar contexto');
                }
            });
        },
		
// Método para mostrar notificaciones mejoradas
showNotification: function(message, type) {
    type = type || 'info';
    
    // Eliminar notificaciones anteriores
    $('.eq-notification').remove();
    
    var notification = $('<div class="eq-notification ' + type + '">' + message + '</div>');
    $('body').append(notification);
    
    setTimeout(function() {
        notification.addClass('show');
    }, 10);
    
    setTimeout(function() {
        notification.removeClass('show');
        setTimeout(function() {
            notification.remove();
        }, 300);
    }, 3000);
},

// Mostrar indicador de sincronización
showSyncIndicator: function() {
    if ($('.eq-sync-indicator').length === 0) {
        var indicator = $('<div class="eq-sync-indicator">' +
            '<i class="fas fa-sync fa-spin"></i> Sincronizando...' +
        '</div>');
        $('.eq-context-panel').append(indicator);
    }
},

// Ocultar indicador de sincronización
hideSyncIndicator: function() {
    $('.eq-sync-indicator').fadeOut(300, function() {
        $(this).remove();
    });
},
        
 endSession: function() {
    var self = this;
    
    if (confirm('¿Está seguro que desea finalizar la sesión de cotización?')) {
        
        // Mostrar notificación de proceso
        self.showNotification('Finalizando sesión...', 'info');
        
        // Limpiar estado local inmediatamente
        this.data = {
            isActive: false,
            isMinimized: false,
            leadId: null,
            leadName: null,
            eventId: null,
            eventDate: null,
            eventType: null,
            sessionToken: null
        };
        
        // Limpiar sessionStorage 
        sessionStorage.removeItem('eqQuoteContext');
        
        // Limpiar localStorage relacionado con fechas
        localStorage.removeItem('eq_date_source');
        localStorage.removeItem('eq_panel_selected_date');
        localStorage.removeItem('eq_selected_date');
        localStorage.removeItem('eq_date_timestamp');
        
        // Establecer bandera global para todas las pestañas
        localStorage.setItem('eq_context_session_ended', Date.now().toString());
        
        // Establecer cookies
        document.cookie = 'eq_session_ended=true; path=/; max-age=86400';
        
        
        // Eliminar panel del DOM inmediatamente
        $('.eq-context-panel').remove();
        $('.eq-context-toggle-button').remove();
        $('body').removeClass('has-eq-context-panel');
        
        // Intentar renderizar el botón toggle inmediatamente
        self.renderToggleButton();
        
        // PRIMERO eliminar la sesión en el servidor
        $.ajax({
            url: eqCartData.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'eq_clear_context_meta',
                nonce: eqCartData.nonce,
                force_clear: true,
                timestamp: Date.now()
            },
            success: function(response) {
                
                if (response.success) {
                    self.showNotification('Sesión finalizada correctamente', 'success');
                    
                    // Verificar que el panel ya no está visible
                    if ($('.eq-context-panel').length > 0) {
                        $('.eq-context-panel').remove();
                        self.renderToggleButton();
                    }
                    
                    // Recargar página para asegurar estado limpio
                    setTimeout(function() {
                        window.location.reload(true);
                    }, 1000);
                } else {
                    console.error('Error ending session on server');
                    self.showNotification('Error al finalizar sesión', 'error');
                    
                    // Forzar recarga de todos modos
                    setTimeout(function() {
                        window.location.reload(true);
                    }, 1000);
                }
            },
            error: function() {
                console.error('AJAX error ending session');
                self.showNotification('Error de conexión', 'error');
                
                // Forzar recarga como último recurso
                setTimeout(function() {
                    window.location.reload(true);
                }, 1000);
            }
        });
    }
},

forceEndSession: function() {
    var self = this;
    
    // Intentar nueva solicitud AJAX con parámetros adicionales
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'eq_clear_context_meta',
            nonce: eqCartData.nonce,
            force_clear: true,
            force_delete: true,
            user_id: true, // Este flag indica que debemos usar user_id explícitamente
            timestamp: Date.now() // Evitar caché
        },
        success: function() {
            // No importa la respuesta, siempre forzamos recarga
            self.showNotification('Finalizando sesión...', 'info');
            setTimeout(function() {
                window.location.reload(true);
            }, 1000);
        },
        error: function() {
            // Error crítico, forzar recarga después de mostrar mensaje
            self.showNotification('Error en servidor. Reiniciando...', 'error');
            setTimeout(function() {
                window.location.reload(true);
            }, 1500);
        }
    });
},

// Nueva función para limpiar con reintentos
clearContextWithRetry: function(retries) {
    var self = this;
    
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'eq_clear_context_meta',
            nonce: eqCartData.nonce,
            force_clear: true,
            timestamp: Date.now() // Evitar caché
        },
        success: function(response) {
            
            if (response.success && response.data && response.data.cleared) {
                
                // Verificar después de un breve retraso si la sesión realmente se eliminó
                setTimeout(function() {
                    self.verifyContextCleared();
                }, 500);
            } else {
                console.error('Error clearing context or server did not confirm clearance');
                
                if (retries > 0) {
                    setTimeout(function() {
                        self.clearContextWithRetry(retries - 1);
                    }, 1000);
                } else {
                    console.error('Failed to clear context after multiple attempts');
                    self.showNotification('Error al finalizar sesión después de varios intentos', 'error');
                    window.location.reload();
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error clearing context:', status, error);
            
            if (retries > 0) {
                setTimeout(function() {
                    self.clearContextWithRetry(retries - 1);
                }, 1000);
            } else {
                console.error('Failed to clear context after multiple attempts');
                self.showNotification('Error de conexión al finalizar sesión', 'error');
                window.location.reload();
            }
        }
    });
},

// Nueva función para verificar que la sesión se limpió correctamente
verifyContextCleared: function() {
    var self = this;
    
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'eq_check_context_status',
            nonce: eqCartData.nonce,
            verify_cleared: true,
            timestamp: Date.now() // Evitar caché
        },
        success: function(response) {
            if (response.success) {
                if (response.data.isActive) {
                    console.error('Context still active after clearing! Retrying...');
                    self.clearContextWithRetry(1);
                } else {
                    self.showNotification('Sesión finalizada correctamente', 'success');
                    
                    // Recargar después de un breve retraso
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                }
            } else {
                console.error('Error verifying context clearance');
                self.showNotification('Error al verificar finalización de sesión', 'error');
                window.location.reload();
            }
        },
        error: function() {
            console.error('AJAX error verifying context clearance');
            self.showNotification('Error de conexión al verificar', 'error');
            window.location.reload();
        }
    });
},

forceRefreshPanel: function() {
    var self = this;
    
    // Verificar con el servidor para obtener datos más recientes
    this.checkServerContextWithErrorHandling(function(success, response) {
        if (success && response && response.success && response.data && response.data.isActive) {
            // Actualizar datos locales con datos del servidor
            self.data.leadId = response.data.leadId;
            self.data.leadName = response.data.leadName;
            self.data.eventId = response.data.eventId;
            self.data.eventDate = response.data.eventDate;
            self.data.eventType = response.data.eventType;
            if (response.data.sessionToken) self.data.sessionToken = response.data.sessionToken;
            
            // Guardar datos actualizados
            self.saveToStorage();
            
            // Recrear panel con datos frescos del servidor
            self.renderPanel();
            
            // Sincronizar con otras pestañas
            localStorage.setItem('eq_context_force_sync', JSON.stringify({
                ...self.data,
                timestamp: Date.now()
            }));
            setTimeout(function() {
                localStorage.removeItem('eq_context_force_sync');
            }, 100);
        }
    });
},
		
validateCartDateChange: function(date, callback) {
    // Verificar que callback sea una función antes de usarlo
    if (typeof callback !== 'function') {
        console.error('EQ Context: validateCartDateChange called without callback');
        return;
    }
    
    // Esta función verificará si la fecha está disponible para todos los ítems
    $.ajax({
        url: eqCartData.ajaxurl,
        type: 'POST',
        data: {
            action: 'eq_validate_cart_date_change',
            nonce: eqCartData.nonce,
            date: date
        },
        success: function(response) {
            if (response.success) {
                callback(response.data);
            } else {
                console.error('Error validating date:', response.data);
                callback({ hasItems: false, unavailableItems: [] });
            }
        },
        error: function() {
            console.error('Error connecting to server');
            callback({ hasItems: false, unavailableItems: [] });
        }
    });
},
		
		}; 
	
	
	
   // Inicializar cuando el DOM esté listo
   $(document).ready(function() {
       EQContext.init();
   });
   
   // Exponer como variable global para acceso desde otros scripts
   window.EQQuoteContext = EQContext;
   
})(jQuery);
					