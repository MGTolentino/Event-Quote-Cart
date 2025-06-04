(function($) {
    'use strict';

    class QuoteView {
        constructor() {
            this.initButtons();
            this.quoteData = this.getQuoteData();
        }

        initButtons() {
            $('.eq-email-button').on('click', () => this.handleEmail());
            $('.eq-whatsapp-button').on('click', () => this.handleWhatsApp());
        }

        getQuoteData() {
            // Obtener datos de la cotización
            const items = [];
            $('.eq-quote-table tr.eq-main-item').each(function() {
                const $row = $(this);
                items.push({
                    title: $row.find('td:first').text(),
                    quantity: $row.find('td:nth-child(3)').text(),
                    total: $row.find('td:last').text()
                });
            });

            return {
                items: items,
                total: $('.eq-total-row.total span:last').text(),
                clientName: $('.eq-quote-client-info .eq-value:first').text(),
                eventDate: $('.eq-quote-client-info .eq-value:eq(1)').text()
            };
        }

        handleEmail() {
            // Construir el asunto y cuerpo del email
            const subject = encodeURIComponent(`Cotización para ${this.quoteData.clientName}`);
            let body = encodeURIComponent(
                `Hola ${this.quoteData.clientName},\n\n` +
                `Aquí está tu cotización para el evento del ${this.quoteData.eventDate}:\n\n` +
                this.quoteData.items.map(item => 
                    `${item.title} x${item.quantity}: ${item.total}`
                ).join('\n') +
                `\n\nTotal: ${this.quoteData.total}\n\n` +
                `Para ver la cotización completa, por favor accede a: ${window.location.href}`
            );

            // Abrir el cliente de correo predeterminado
            window.location.href = `mailto:?subject=${subject}&body=${body}`;
        }

        handleWhatsApp() {
            // Construir el mensaje para WhatsApp
            const message = encodeURIComponent(
                `*Cotización - ${this.quoteData.clientName}*\n\n` +
                `Fecha del evento: ${this.quoteData.eventDate}\n\n` +
                `*Servicios:*\n` +
                this.quoteData.items.map(item => 
                    `• ${item.title} x${item.quantity}: ${item.total}`
                ).join('\n') +
                `\n\n*Total: ${this.quoteData.total}*\n\n` +
                `Para ver la cotización completa, accede a:\n${window.location.href}`
            );

            // Abrir WhatsApp
            window.open(`https://wa.me/?text=${message}`, '_blank');
        }
    }

    // Inicializar cuando el documento esté listo
    $(document).ready(() => {
        new QuoteView();
    });

})(jQuery);