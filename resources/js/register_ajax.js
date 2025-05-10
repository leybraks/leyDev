document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.querySelector('form.sign-up-form');
    const registerAjaxErrorDiv = document.getElementById('register-ajax-error');
    // Limpiar errores de campo previos
    const fieldErrorSpans = registerForm ? registerForm.querySelectorAll('.field-error') : [];
    // Limpiar errores de sesión (de recargas completas)
    const sessionRegisterErrorDiv = document.querySelector('.register-form-error');
    const sessionRegisterSuccessDiv = document.querySelector('.register-form-success');


    if (registerForm && registerAjaxErrorDiv) {
        registerForm.addEventListener('submit', function(event) {
            event.preventDefault();

            // Limpiar todos los mensajes de error antes de un nuevo envío
            registerAjaxErrorDiv.style.display = 'none'; registerAjaxErrorDiv.textContent = '';
            fieldErrorSpans.forEach(span => { span.style.display = 'none'; span.textContent = ''; });
            if (sessionRegisterErrorDiv) sessionRegisterErrorDiv.style.display = 'none';
            if (sessionRegisterSuccessDiv) sessionRegisterSuccessDiv.style.display = 'none';


            const formData = new FormData(registerForm);
            const submitButton = registerForm.querySelector('input[type="submit"]'); 
            const originalButtonText = submitButton.value;

            submitButton.value = 'Registrando...';
            submitButton.disabled = true;

            fetch(registerForm.action, { // action es '../Scripts/handle_registro.php'
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(formData)
            })
            .then(response => {
                if (!response.ok && response.headers.get("content-type")?.indexOf("application/json") === -1 ) {
                    return response.text().then(text => {
                        const error = new Error(`Error del servidor: ${response.status} ${response.statusText}.`);
                        error.responseContent = text.substring(0, 300);
                        throw error;
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Éxito en registro
                    if (registerAjaxErrorDiv && data.message) { // Mostrar mensaje de éxito
                        registerAjaxErrorDiv.textContent = data.message;
                        registerAjaxErrorDiv.className = 'success-message'; // Cambiar a clase de éxito
                        registerAjaxErrorDiv.style.display = 'block';
                    } else if (data.message) {
                        alert(data.message); // Fallback
                    }
                    // Redirigir después de un momento
                    if (data.redirectUrl) {
                        setTimeout(() => {
                            window.location.href = data.redirectUrl;
                        }, 1500); 
                    }
                } else {
                    // Hubo errores de validación o de otro tipo
                    registerAjaxErrorDiv.className = 'error-message'; 
                    if (data.errors && typeof data.errors === 'object') {
                        // Mostrar errores específicos de campo
                        let firstErrorField = null;
                        for (const fieldName in data.errors) {
                            const fieldErrorElement = document.getElementById(fieldName + '-error');
                            if (fieldErrorElement) {
                                fieldErrorElement.textContent = data.errors[fieldName];
                                fieldErrorElement.style.display = 'block';
                                if (!firstErrorField) firstErrorField = document.getElementsByName(fieldName)[0];
                            }
                        }

                        if (data.message && registerAjaxErrorDiv) {
                            registerAjaxErrorDiv.textContent = data.message;
                            registerAjaxErrorDiv.style.display = 'block';
                        } else if (!data.message && registerAjaxErrorDiv) { // Si solo hay errores de campo, poner un msg general
                             registerAjaxErrorDiv.textContent = 'Por favor, corrige los errores indicados.';
                             registerAjaxErrorDiv.style.display = 'block';
                        }

                    } else if (data.message && registerAjaxErrorDiv) {
                        // Solo error general
                        registerAjaxErrorDiv.textContent = data.message;
                        registerAjaxErrorDiv.style.display = 'block';
                    } else {
                        registerAjaxErrorDiv.textContent = 'Error desconocido al registrar. Intenta de nuevo.';
                        registerAjaxErrorDiv.style.display = 'block';
                    }
                }
            })
            .catch(error => {
                console.error('Error en fetch de registro:', error);
                let displayError = 'Error de comunicación. Intenta de nuevo más tarde.';
                 if (error.responseContent) {
                     console.error('Respuesta del servidor (error):', error.responseContent);
                     displayError += ' (Detalle: ' + error.message + ')';
                 }
                registerAjaxErrorDiv.textContent = displayError;
                registerAjaxErrorDiv.className = 'error-message';
                registerAjaxErrorDiv.style.display = 'block';
            })
            .finally(() => {
                submitButton.value = originalButtonText;
                submitButton.disabled = false;
            });
        });
    } else {
         if (!registerForm) console.error("Formulario de registro ('form.sign-up-form') no encontrado.");
         if (!registerAjaxErrorDiv) console.error("Elemento para errores AJAX de registro ('#register-ajax-error') no encontrado.");
    }

});