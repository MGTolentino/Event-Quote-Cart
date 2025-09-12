/**
 * Date Utilities Module
 * 
 * Funciones de utilidad para manejo de fechas
 * 
 * @package Event_Quote_Cart
 * @since 2.0.0
 */

(function(window) {
    'use strict';
    
    const DateUtils = {
        
        /**
         * Formatea una fecha al formato Y-m-d
         */
        formatDate: function(date) {
            if (!date) return '';
            
            // Si es string, intentar parsearlo
            if (typeof date === 'string') {
                date = new Date(date);
            }
            
            // Si es timestamp
            if (typeof date === 'number') {
                date = new Date(date);
            }
            
            if (!(date instanceof Date) || isNaN(date)) {
                return '';
            }
            
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            
            return `${year}-${month}-${day}`;
        },
        
        /**
         * Formatea una fecha para mostrar al usuario
         */
        formatDisplayDate: function(date, locale = 'es-ES') {
            if (!date) return '';
            
            if (typeof date === 'string') {
                date = new Date(date + 'T00:00:00');
            }
            
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            
            return date.toLocaleDateString(locale, options);
        },
        
        /**
         * Parsea una fecha desde formato Y-m-d
         */
        parseDate: function(dateString) {
            if (!dateString) return null;
            
            const parts = dateString.split('-');
            if (parts.length !== 3) return null;
            
            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);
            
            return new Date(year, month, day);
        },
        
        /**
         * Valida si una fecha es válida
         */
        isValidDate: function(date) {
            if (!date) return false;
            
            if (typeof date === 'string') {
                date = this.parseDate(date);
            }
            
            return date instanceof Date && !isNaN(date);
        },
        
        /**
         * Compara dos fechas
         */
        compareDates: function(date1, date2) {
            const d1 = typeof date1 === 'string' ? this.parseDate(date1) : date1;
            const d2 = typeof date2 === 'string' ? this.parseDate(date2) : date2;
            
            if (!d1 || !d2) return 0;
            
            return d1.getTime() - d2.getTime();
        },
        
        /**
         * Verifica si una fecha está en el pasado
         */
        isPastDate: function(date) {
            const checkDate = typeof date === 'string' ? this.parseDate(date) : date;
            if (!checkDate) return false;
            
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            return checkDate < today;
        },
        
        /**
         * Calcula la diferencia en días entre dos fechas
         */
        daysDifference: function(date1, date2) {
            const d1 = typeof date1 === 'string' ? this.parseDate(date1) : date1;
            const d2 = typeof date2 === 'string' ? this.parseDate(date2) : date2;
            
            if (!d1 || !d2) return 0;
            
            const diffTime = Math.abs(d2 - d1);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            return diffDays;
        },
        
        /**
         * Agrega días a una fecha
         */
        addDays: function(date, days) {
            const result = typeof date === 'string' ? this.parseDate(date) : new Date(date);
            if (!result) return null;
            
            result.setDate(result.getDate() + days);
            return result;
        },
        
        /**
         * Obtiene el primer día del mes
         */
        getFirstDayOfMonth: function(date) {
            const d = typeof date === 'string' ? this.parseDate(date) : new Date(date);
            if (!d) return null;
            
            return new Date(d.getFullYear(), d.getMonth(), 1);
        },
        
        /**
         * Obtiene el último día del mes
         */
        getLastDayOfMonth: function(date) {
            const d = typeof date === 'string' ? this.parseDate(date) : new Date(date);
            if (!d) return null;
            
            return new Date(d.getFullYear(), d.getMonth() + 1, 0);
        },
        
        /**
         * Formatea un rango de fechas
         */
        formatDateRange: function(startDate, endDate, locale = 'es-ES') {
            const start = this.formatDisplayDate(startDate, locale);
            const end = this.formatDisplayDate(endDate, locale);
            
            if (!start || !end) return '';
            
            return `${start} - ${end}`;
        },
        
        /**
         * Valida que una fecha esté dentro de un rango
         */
        isDateInRange: function(date, startDate, endDate) {
            const check = typeof date === 'string' ? this.parseDate(date) : date;
            const start = typeof startDate === 'string' ? this.parseDate(startDate) : startDate;
            const end = typeof endDate === 'string' ? this.parseDate(endDate) : endDate;
            
            if (!check || !start || !end) return false;
            
            return check >= start && check <= end;
        },
        
        /**
         * Obtiene el timestamp Unix de una fecha
         */
        getTimestamp: function(date) {
            const d = typeof date === 'string' ? this.parseDate(date) : date;
            if (!d) return null;
            
            return Math.floor(d.getTime() / 1000);
        },
        
        /**
         * Crea una fecha desde un timestamp Unix
         */
        fromTimestamp: function(timestamp) {
            if (!timestamp || isNaN(timestamp)) return null;
            
            // Si el timestamp está en milisegundos
            if (timestamp > 9999999999) {
                return new Date(timestamp);
            }
            
            // Si está en segundos
            return new Date(timestamp * 1000);
        }
    };
    
    // Exportar para uso global
    window.EQDateUtils = DateUtils;
    
    // Exportar para módulos si están disponibles
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = DateUtils;
    }
    
})(window);