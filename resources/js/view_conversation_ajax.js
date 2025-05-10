document.addEventListener('DOMContentLoaded', function() {
        const messagesListContainer = document.getElementById('messages-list-container');
        const replyForm = document.getElementById('reply-form');
        const messageTextarea = document.getElementById('message_text_reply');
        const ajaxMessageDiv = document.getElementById('reply-ajax-message');

        function scrollToBottom() {
            if (messagesListContainer) {
                messagesListContainer.scrollTop = messagesListContainer.scrollHeight;
            }
        }
        scrollToBottom(); 

        if (replyForm && messageTextarea && ajaxMessageDiv) {
            replyForm.addEventListener('submit', function(event) {
                event.preventDefault();
                console.log("REPLY_AJAX: Submit de #reply-form interceptado.");

                ajaxMessageDiv.textContent = '';
                ajaxMessageDiv.style.display = 'none';
                ajaxMessageDiv.classList.remove('success-message', 'error-message');
                if (!ajaxMessageDiv.classList.contains('message')) {
                    ajaxMessageDiv.classList.add('message');
                }

                const submitButton = replyForm.querySelector('button[type="submit"]');
                const originalButtonText = submitButton ? submitButton.textContent : 'Enviar Mensaje';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Enviando...';
                }

                const formData = new FormData(replyForm);

                fetch('../Scripts/handle_send_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log("REPLY_AJAX: Respuesta Fetch recibida, status:", response.status);
                    return response.json().then(data => ({ ok: response.ok, status: response.status, data }));
                })
                .then(result => {
                    const data = result.data;
                    console.log("REPLY_AJAX: Datos JSON procesados:", data);

                    if (data.success) {
                        // Limpiar el textarea
                        messageTextarea.value = '';

                        if (data.new_message_html && messagesListContainer) {
                            messagesListContainer.insertAdjacentHTML('beforeend', data.new_message_html);
                            scrollToBottom(); // Hacer scroll al nuevo mensaje
                        } else if (data.new_message_data && messagesListContainer) {
                            const msgData = data.new_message_data;
                            const timeFormatted = new Date(msgData.sent_at.replace(/-/g, '/')).toLocaleTimeString([], { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                            const textFormatted = msgData.message_text.replace(/\n/g, '<br>');
                            
                            const newMessageDiv = document.createElement('div');
                            newMessageDiv.classList.add('chat-message', 'current-user-message'); 
                            newMessageDiv.dataset.messageId = msgData.message_id;

                            const timeSpan = document.createElement('span');
                            timeSpan.classList.add('message-time');
                            timeSpan.textContent = timeFormatted;
                            newMessageDiv.appendChild(timeSpan);

                            const textDiv = document.createElement('div');
                            textDiv.classList.add('message-text');
                            textDiv.innerHTML = textFormatted; 
                            newMessageDiv.appendChild(textDiv);
                            
                            const noMessagesP = messagesListContainer.querySelector('.mensaje-info');
                            if (noMessagesP) noMessagesP.remove();

                            messagesListContainer.appendChild(newMessageDiv);
                            scrollToBottom();
                        }
                        console.log("REPLY_AJAX: Mensaje enviado y añadido al DOM.");

                    } else { // data.success es false
                        ajaxMessageDiv.textContent = data.message || 'Error al enviar el mensaje.';
                        ajaxMessageDiv.classList.add('error-message');
                        ajaxMessageDiv.style.display = 'block';
                        console.warn("REPLY_AJAX: Falló el envío del mensaje:", data.message);
                    }

                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalButtonText;
                    }
                })
                .catch(error => {
                    console.error('REPLY_AJAX: Error en la petición Fetch o al procesar JSON:', error);
                    ajaxMessageDiv.textContent = 'Error de conexión al enviar el mensaje. Inténtalo más tarde.';
                    ajaxMessageDiv.style.display = 'block';
                    ajaxMessageDiv.classList.add('error-message');

                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalButtonText;
                    }
                });
            });
            console.log("REPLY_AJAX: Listener de submit adjuntado a #reply-form.");
        } else {
            if (!replyForm) console.error("REPLY_AJAX: Formulario '#reply-form' no encontrado.");
            if (!ajaxMessageDiv) console.error("REPLY_AJAX: Div '#reply-ajax-message' no encontrado.");
        }

        function escapeHTML(str) {
            if (typeof str !== 'string') return '';
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    });