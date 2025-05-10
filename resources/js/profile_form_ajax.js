window.initProfileFormEventListeners = function() {
    console.log("PROFILE_FORM_HANDLER: Se llamó a initProfileFormEventListeners().");

    const formProfileUpdate = document.getElementById('profile-form');
    const messageDiv = document.getElementById('profile-ajax-message');

    if (!formProfileUpdate) {
        console.error("PROFILE_FORM_HANDLER: ¡ERROR! Formulario con ID 'profile-form' NO encontrado en el DOM.");
        return;
    }
    if (!messageDiv) {
        console.warn("PROFILE_FORM_HANDLER: Advertencia: Div con ID 'profile-ajax-message' no encontrado. Los mensajes AJAX no se mostrarán allí.");
    }

    if (formProfileUpdate.dataset.listenerAttached === 'true') {
        console.log("PROFILE_FORM_HANDLER: Listener de submit ya estaba adjunto a #profile-form.");
        return;
    }

    formProfileUpdate.addEventListener('submit', function(event) {
        event.preventDefault();
        console.log("PROFILE_FORM_HANDLER: Evento 'submit' de #profile-form INTERCEPTADO. preventDefault() llamado.");

        if (messageDiv) {
            messageDiv.textContent = '';
            messageDiv.style.display = 'none';
            messageDiv.className = '';
        }

        const submitButton = formProfileUpdate.querySelector('button[type="submit"].btn-submit');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Guardando...';
        }

        const formData = new FormData(formProfileUpdate);

        fetch('../Scripts/handle_profile_update.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log("PROFILE_FORM_HANDLER: Respuesta Fetch recibida, status:", response.status);
            if (!response.ok) {
                return response.text().then(text => {
                    console.error("PROFILE_FORM_HANDLER: Respuesta Fetch NO OK. Texto de respuesta:", text);
                    throw new Error(`Error del servidor: ${response.status}. Respuesta: ${text.substring(0,200)}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log("PROFILE_FORM_HANDLER: Datos JSON procesados:", data);

            if (messageDiv) {
                messageDiv.style.display = 'block';
                if (data.success) {
                    messageDiv.textContent = data.message;
                    messageDiv.style.color = 'var(--intranet-success-text, #155724)';
                    messageDiv.style.backgroundColor = 'var(--intranet-success-bg, #d4edda)';
                    messageDiv.style.borderColor = 'var(--intranet-success-border, #c3e6cb)';
                    console.log("PROFILE_FORM_HANDLER: Mensaje de éxito mostrado:", data.message);

                    if (data.updated_greeting_data) {
                        console.log("PROFILE_FORM_HANDLER: Intentando actualizar saludo con:", data.updated_greeting_data);
                        const greetingData = data.updated_greeting_data;
                        const primerNombre = greetingData.first_name ? escapeHTML(greetingData.first_name.split(' ')[0]) : 'Usuario';
                        const genero = greetingData.gender;
                        const role = greetingData.role;

                        let saludoBase = 'Bienvenide';
                        if (genero && genero.toLowerCase() === 'masculino') saludoBase = 'Bienvenido';
                        else if (genero && genero.toLowerCase() === 'femenino') saludoBase = 'Bienvenida';

                        let rolDisplay = '';
                        if (role) {
                            const roleLower = role.toLowerCase();
                            if (roleLower === 'alumno') {
                                if (genero && genero.toLowerCase() === 'masculino') rolDisplay = 'Alumno';
                                else if (genero && genero.toLowerCase() === 'femenino') rolDisplay = 'Alumna';
                                else rolDisplay = 'Alumne';
                            } else if (roleLower === 'tutor') {
                                if (genero && genero.toLowerCase() === 'masculino') rolDisplay = 'Tutor';
                                else if (genero && genero.toLowerCase() === 'femenino') rolDisplay = 'Tutora';
                                else rolDisplay = 'Tutore';
                            } else {
                                rolDisplay = escapeHTML(role.charAt(0).toUpperCase() + role.slice(1));
                            }
                        }
                        const saludoCompleto = `${saludoBase}${rolDisplay ? ' ' + rolDisplay : ''}, ${primerNombre}!`;

                        const saludoElement = document.querySelector('#main-content-area div[data-section-name="inicio"] h2, #inicio-content-loaded h2');

                        if (saludoElement) {
                            saludoElement.textContent = saludoCompleto;
                            console.log("PROFILE_FORM_HANDLER: Saludo en DOM actualizado a:", saludoCompleto);
                        } else {
                            console.warn("PROFILE_FORM_HANDLER: Elemento H2 para el saludo no encontrado en el DOM (esto es normal si la sección 'inicio' no está activa).");
                        }
                    }
                } else { // data.success es false
                    messageDiv.textContent = data.message;
                    messageDiv.style.color = 'var(--intranet-error-text, #721c24)';
                    messageDiv.style.backgroundColor = 'var(--intranet-error-bg, #f8d7da)';
                    messageDiv.style.borderColor = 'var(--intranet-error-border, #f5c6cb)';
                    console.log("PROFILE_FORM_HANDLER: Mensaje de error mostrado:", data.message);
                    if (data.errors && data.errors.length > 0) {
                        let errorListHTML = '<ul>';
                        data.errors.forEach(errorMessage => {
                            errorListHTML += `<li>${escapeHTML(errorMessage)}</li>`;
                        });
                        errorListHTML += '</ul>';
                        messageDiv.innerHTML = `<strong>${escapeHTML(data.message)}</strong>${errorListHTML}`;
                    }
                }
            } 

            if (data.redirectUrl && data.success) {
                console.log("PROFILE_FORM_HANDLER: Redirigiendo a:", data.redirectUrl);
                setTimeout(() => { window.location.href = data.redirectUrl; }, 1500);
            } else if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Guardar Cambios';
                console.log("PROFILE_FORM_HANDLER: Botón de submit rehabilitado.");
            }
        })
        .catch(error => {
            console.error('PROFILE_FORM_HANDLER: Error en la petición Fetch o al procesar JSON:', error);
            if (messageDiv) {
                messageDiv.textContent = 'Error de comunicación o respuesta inesperada. Revisa la consola.';
                messageDiv.style.display = 'block';
                messageDiv.style.color = 'var(--intranet-error-text, #721c24)';
            }
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Guardar Cambios';
            }
        });
    });

    formProfileUpdate.dataset.listenerAttached = 'true';
    console.log("PROFILE_FORM_HANDLER: Listener de submit adjuntado exitosamente a #profile-form.");
};

function escapeHTML(str) {
    if (typeof str !== 'string') return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}
