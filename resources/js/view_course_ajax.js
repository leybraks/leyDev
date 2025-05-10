document.addEventListener('DOMContentLoaded', function() {
        const chatMessagesList = document.getElementById('chat-messages-list-container');
        const chatForm = document.getElementById('chat-form');
        const chatMessageTextarea = document.getElementById('chat_message_text');
        const chatAjaxMessageDiv = document.getElementById('chat-ajax-message');
        const chatAttachmentInput = document.getElementById('chat_attachment_input');

        function scrollToChatBottom() {
            if (chatMessagesList) {
                chatMessagesList.scrollTop = chatMessagesList.scrollHeight;
            }
        }
        scrollToChatBottom(); // Al cargar la página

        if (chatForm && chatMessageTextarea && chatAjaxMessageDiv) {
            chatForm.addEventListener('submit', function(event) {
                event.preventDefault();
                console.log("CHAT_AJAX: Submit de #chat-form interceptado.");

                // Limpiar mensaje AJAX previo
                chatAjaxMessageDiv.textContent = '';
                chatAjaxMessageDiv.style.display = 'none';
                chatAjaxMessageDiv.classList.remove('success-message', 'error-message');
                if (!chatAjaxMessageDiv.classList.contains('message')) {
                     chatAjaxMessageDiv.classList.add('message');
                }


                const submitButton = chatForm.querySelector('button[type="submit"]');
                const originalButtonText = submitButton ? submitButton.textContent : 'Enviar';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Enviando...';
                }

                const formData = new FormData(chatForm);

                fetch('../Scripts/handle_chat_message.php', {
                    method: 'POST',
                    body: formData 
                })
                .then(response => {
                    console.log("CHAT_AJAX: Respuesta Fetch recibida, status:", response.status);
                    return response.json().then(data => ({ ok: response.ok, status: response.status, data }));
                })
                .then(result => {
                    const data = result.data;
                    console.log("CHAT_AJAX: Datos JSON procesados:", data);

                    if (data.success) {
                        chatMessageTextarea.value = ''; 
                        if(chatAttachmentInput) chatAttachmentInput.value = ''; 

                        if (data.new_message_html && chatMessagesList) {
                            // Quitar mensaje de "No hay mensajes" 
                            const noMessagesP = chatMessagesList.querySelector('.mensaje-info-inline');
                            if (noMessagesP && chatMessagesList.children.length <= 1) noMessagesP.remove();
                            
                            chatMessagesList.insertAdjacentHTML('beforeend', data.new_message_html);
                            scrollToChatBottom();
                        }
                        console.log("CHAT_AJAX: Mensaje de chat enviado y añadido al DOM.");

                    } else { // data.success es false
                        chatAjaxMessageDiv.textContent = data.message || 'Error al enviar el mensaje.';
                        chatAjaxMessageDiv.classList.add('error-message');
                        chatAjaxMessageDiv.style.display = 'block';
                        console.warn("CHAT_AJAX: Falló el envío del mensaje:", data.message);
                        if (data.errors) {
                            let errorDetails = "";
                            for(const key in data.errors){
                                errorDetails += `${data.errors[key]}\n`;
                            }
                            if(errorDetails) alert("Errores:\n" + errorDetails);
                        }
                    }

                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalButtonText;
                    }
                })
                .catch(error => {
                    console.error('CHAT_AJAX: Error en la petición Fetch o al procesar JSON:', error);
                    chatAjaxMessageDiv.textContent = 'Error de conexión al enviar el mensaje. Inténtalo más tarde.';
                    chatAjaxMessageDiv.style.display = 'block';
                    chatAjaxMessageDiv.classList.add('error-message');

                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalButtonText;
                    }
                });
            });
            console.log("CHAT_AJAX: Listener de submit adjuntado a #chat-form.");
        } else {
            if (!chatForm) console.error("CHAT_AJAX: Formulario '#chat-form' no encontrado.");
            if (!chatAjaxMessageDiv) console.error("CHAT_AJAX: Div '#chat-ajax-message' no encontrado.");
        }
    });