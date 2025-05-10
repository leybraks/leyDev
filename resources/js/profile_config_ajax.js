document.addEventListener('DOMContentLoaded', function() {
    const formProfileUpdate = document.getElementById('profile-form');
    const messageDiv = document.getElementById('profile-ajax-message');

    if (formProfileUpdate && messageDiv) {
        formProfileUpdate.addEventListener('submit', function(event) {
            event.preventDefault(); // Evitar el envío tradicional del formulario

            messageDiv.textContent = '';
            messageDiv.style.display = 'none';
            messageDiv.className = ''; 

            const submitButton = formProfileUpdate.querySelector('button[type="submit"].btn-submit');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Guardando...';
            }

            const formData = new FormData(formProfileUpdate);
            // El token CSRF incluido en el formulario se enviará automáticamente con FormData

            fetch('../Scripts/handle_profile_update.php', { 
                method: 'POST',
                body: formData
            })
            .then(response => {
                return response.json(); // Convertir la respuesta a JSON
            })
            .then(data => {
                messageDiv.style.display = 'block'; // Mostrar el div de mensajes

                if (data.success) {
                    messageDiv.textContent = data.message;
                    // Aplicar estilos de éxito
                    messageDiv.style.color = '#155724'; // Verde oscuro (éxito)
                    messageDiv.style.backgroundColor = '#d4edda';
                    messageDiv.style.borderColor = '#c3e6cb';

                if (data.updated_greeting_data) {
                    const greetingData = data.updated_greeting_data;
                    const primerNombre = greetingData.first_name ? escapeHTML(greetingData.first_name.split(' ')[0]) : 'Usuario';
                    const genero = greetingData.gender;
                    const role = greetingData.role;

                    let saludoBase = 'Bienvenide'; // Saludo por defecto
                    if (genero === 'Masculino') {
                        saludoBase = 'Bienvenido';
                    } else if (genero === 'Femenino') {
                        saludoBase = 'Bienvenida';
                    }

                    let rolDisplay = '';
                    if (role === 'alumno') {
                            if (genero === 'Masculino') rolDisplay = 'Alumno';
                            else if (genero === 'Femenino') rolDisplay = 'Alumna';
                            else rolDisplay = 'Alumne'; // Por defecto o para no binario/otro
                        } else if (role === 'tutor') {
                            if (genero === 'Masculino') rolDisplay = 'Tutor';
                            else if (genero === 'Femenino') rolDisplay = 'Tutora';
                            else rolDisplay = 'Tutore'; // Por defecto o para no binario/otro
                        }

                        const saludoCompleto = `${saludoBase}${rolDisplay ? ' ' + rolDisplay : ''}, ${primerNombre}!`;

                                // Actualizar el elemento h2 en la sección de inicio
                        const saludoElement = document.querySelector('#inicio-content h2');
                        if (saludoElement) {
                            saludoElement.textContent = saludoCompleto;
                        }

                        const firstNameInput = document.getElementById('first_name');
                        if (firstNameInput && greetingData.first_name) {
                            firstNameInput.value = greetingData.first_name;
                        }
                        const genderSelect = document.getElementById('gender');
                        if (genderSelect && greetingData.gender) {
                            genderSelect.value = greetingData.gender;
                        }
                    }

                    // --- FIN LÓGICA PARA ACTUALIZAR EL SALUDO ---
                    if (data.redirectUrl) {
                        setTimeout(() => {
                            window.location.href = data.redirectUrl;
                        }, 1000); 
                        return;
                    }
                } else { 
                    messageDiv.textContent = data.message; // Mensaje de error general
                    // Aplicar estilos de error
                    messageDiv.style.color = '#721c24'; // Rojo oscuro (error)
                    messageDiv.style.backgroundColor = '#f8d7da';
                    messageDiv.style.borderColor = '#f5c6cb';

                    if (data.errors && data.errors.length > 0) {
                        let errorListHTML = '<ul>';
                        data.errors.forEach(errorMessage => {
                            errorListHTML += `<li>${escapeHTML(errorMessage)}</li>`;
                        });
                        errorListHTML += '</ul>';
                        messageDiv.innerHTML = `<strong>${escapeHTML(data.message)}</strong>${errorListHTML}`;
                    }
                }

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Guardar Cambios';
                }
            })
            .catch(error => {
                console.error('Error en la petición Fetch:', error);
                messageDiv.textContent = 'Error de conexión al guardar los cambios. Inténtalo más tarde.';
                messageDiv.style.display = 'block';
                // Aplicar estilos de error
                messageDiv.style.color = '#721c24';
                messageDiv.style.backgroundColor = '#f8d7da';
                messageDiv.style.borderColor = '#f5c6cb';

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Guardar Cambios';
                }
            });
        });
    } else {

        if (!formProfileUpdate) {
            console.error("El formulario con ID 'profile-form' no fue encontrado.");
        }
        if (!messageDiv) {
            console.error("El div con ID 'profile-ajax-message' no fue encontrado.");
        }
    }

    function escapeHTML(str) {
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    }
});