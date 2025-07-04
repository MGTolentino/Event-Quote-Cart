# Análisis del Problema de Timeout en Quote Context

## Problema Identificado

El panel de contexto se queda mostrando "Verificando contexto..." indefinidamente después de 5 minutos, y al recargar la página aparece inmediatamente "timeout timeout".

## Análisis de la Función `checkServerContext()`

### Configuración del Timeout
```javascript
$.ajax({
    url: eqCartData.ajaxurl,
    type: 'POST',
    dataType: 'json',
    timeout: 3000, // 3 segundos timeout
    // ...
});
```

### Problema 1: Manejo del Timeout
**¿Qué pasa si el timeout de 3s se agota?**

El código actual tiene un **BUG CRÍTICO** en el manejo del timeout:

```javascript
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
                    // ... más datos
                }
            });
        }
    } else {
        if (typeof callback === 'function') {
            callback({success: false, error: error});
        }
    }
}
```

### Problema 2: Callback de Error NO se Ejecuta Correctamente

**En la función `init()` (líneas 142-151):**
```javascript
} else {
    console.error('Error checking context status');
    
    // Si hay un error, solo mostrar botón toggle
    self.data.isActive = false;
    self.saveToStorage();
    $('.eq-context-panel').remove();
    self.renderToggleButton();
}
```

**PROBLEMA**: El callback solo maneja `response.success`, pero cuando hay un timeout, se ejecuta `callback({success: false, error: error})`. Sin embargo, el código en `init()` no verifica si `response` es `null` o `undefined`.

### Problema 3: Panel de Carga Nunca se Remueve

**En la función `activatePanel()` (líneas 540-582):**
```javascript
} else {
    // No hay datos locales, verificar servidor
    this.checkServerContext(function(response) {
        if (response && response.success && response.data && response.data.isActive) {
            // ... manejar contexto activo
        } else {
            // No hay contexto, mostrar panel vacío para seleccionar lead/evento
            self.renderPanel();
            
            // Reinicializar eventos
            self.initEventListeners();
            
            // Eliminar botón toggle solo después de éxito
            $('.eq-context-toggle-button').remove();
            
            // Abrir modal de selección de lead automáticamente
            setTimeout(function() {
                self.openLeadModal();
            }, 300);
        }
    });
}
```

**PROBLEMA CRÍTICO**: Cuando `checkServerContext()` falla por timeout, el panel de carga (`renderLoadingPanel()`) nunca se elimina porque el código asume que siempre habrá una respuesta válida.

## Análisis del Servidor PHP

### Función `check_context_status()`

El código PHP parece optimizado y no debería tomar más de 3 segundos:

```php
// Query simplificada: obtener solo sesión primero
$session = $wpdb->get_row($wpdb->prepare(
    "SELECT id, lead_id, event_id, session_token 
    FROM {$wpdb->prefix}eq_context_sessions 
    WHERE user_id = %d 
    LIMIT 1",
    $user_id
));
```

### Posibles Problemas en el Servidor

1. **Tabla `eq_context_sessions` sin índices**
2. **Deadlock en queries simultáneas**
3. **Problema de permisos o nonces expirados**
4. **Sesiones PHP bloqueadas**

## Problema 4: Bucle Infinito o Llamadas Recursivas

**NO HAY** bucles infinitos o llamadas recursivas en `checkServerContext()`. Es una función simple que hace una sola llamada AJAX.

## Problema 5: El "timeout timeout" al Recargar

**¿Por qué inmediatamente aparece "timeout timeout" al recargar?**

```javascript
// En init(), línea 68:
$('.eq-context-panel').addClass('eq-loading').addClass('eq-hidden');

// En el callback de error de checkServerContext:
console.error('Error checking context status:', status, error);
```

Cuando el navegador recarga, si hay un problema persistente en el servidor, la primera llamada a `checkServerContext()` falla inmediatamente y se ejecuta el callback de error que muestra "timeout timeout".

## Soluciones Propuestas

### 1. Arreglar el Manejo del Timeout en `init()`
```javascript
this.checkServerContext(function(response) {
    // Remover clases de carga SIEMPRE
    $('.eq-context-panel').removeClass('eq-loading').removeClass('eq-hidden');
    
    if (response && response.success) {
        // ... código existente
    } else {
        console.error('Error checking context status', response);
        
        // CRÍTICO: Remover panel de carga
        $('.eq-context-panel').remove();
        
        // Si hay un error, solo mostrar botón toggle
        self.data.isActive = false;
        self.saveToStorage();
        self.renderToggleButton();
    }
});
```

### 2. Arreglar el Manejo del Timeout en `activatePanel()`
```javascript
this.checkServerContext(function(response) {
    if (response && response.success && response.data && response.data.isActive) {
        // ... código existente
    } else {
        // CRÍTICO: Remover panel de carga antes de mostrar panel vacío
        $('.eq-context-panel.eq-loading-state').remove();
        
        // No hay contexto, mostrar panel vacío
        self.renderPanel();
        // ... resto del código
    }
});
```

### 3. Agregar Timeout de Seguridad
```javascript
checkServerContext: function(callback) {
    var self = this;
    
    // Timeout de seguridad para evitar cuelgues
    var safetyTimeout = setTimeout(function() {
        console.error('Safety timeout reached for checkServerContext');
        if (typeof callback === 'function') {
            callback({success: false, error: 'safety_timeout'});
        }
    }, 5000); // 5 segundos como máximo
    
    $.ajax({
        // ... configuración existente
        success: function(response) {
            clearTimeout(safetyTimeout);
            if (typeof callback === 'function') {
                callback(response);
            }
        },
        error: function(xhr, status, error) {
            clearTimeout(safetyTimeout);
            // ... resto del código
        }
    });
}
```

### 4. Verificar Problemas en el Servidor
- Revisar índices en la tabla `eq_context_sessions`
- Verificar logs de errores PHP
- Revisar nonces y permisos
- Optimizar queries si es necesario

## Conclusión

El problema principal es que el código JavaScript no maneja correctamente los casos de timeout y error, dejando el panel de carga visible indefinidamente. La solución requiere:

1. **Siempre** remover el estado de carga, independientemente del resultado
2. Agregar timeouts de seguridad adicionales
3. Verificar problemas en el servidor que puedan estar causando los timeouts
4. Mejorar el manejo de errores en ambas funciones (`init` y `activatePanel`)