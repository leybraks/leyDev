document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.querySelector('form.sign-in-form');
    const loginErrorDiv = document.getElementById('login-ajax-error');
    // También podrías querer limpiar el error de sesión si existe
    const sessionErrorDiv = document.querySelector('.login-form-error');

    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevenir envío normal

            // Limpiar errores previos
            if (loginErrorDiv) loginErrorDiv.style.display = 'none'; loginErrorDiv.textContent = '';
            if (sessionErrorDiv) sessionErrorDiv.style.display = 'none'; // Ocultar error de sesión

            const formData = new FormData(loginForm);
            const submitButton = loginForm.querySelector('input[type="submit"]');
            const originalButtonText = submitButton.value;
            submitButton.value = 'Procesando...';
            submitButton.disabled = true;

            fetch(loginForm.action, {
                method: 'POST',
                headers: { // Necesario si NO usas FormData para enviar JSON directamente
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(formData) // FormData se envía como x-www-form-urlencoded
            })
            .then(response => {
                // Primero verificar si la respuesta en sí es un error de red o no JSON
                if (!response.ok && response.headers.get("content-type")?.indexOf("application/json") === -1 ) {
                    // Si no es OK y no es JSON, podría ser un error HTML del servidor
                    return response.text().then(text => {
                        throw new Error(`Error del servidor: ${response.status} ${response.statusText}. Respuesta: ${text.substring(0, 200)}`);
                    });
                }
                return response.json(); // Intentar parsear como JSON
            })
            .then(data => {
                if (data.success && data.redirectUrl) {
                    window.location.href = data.redirectUrl; // Redirigir en éxito
                } else {
                    // Mostrar error de login
                    if (loginErrorDiv && data.message) {
                        loginErrorDiv.innerHTML = data.message; // Usar innerHTML si el mensaje puede tener <A>
                        loginErrorDiv.style.display = 'block';
                    } else if (data.message) {
                        alert('Error: ' + data.message); // Fallback
                    } else {
                         alert('Error desconocido al iniciar sesión.');
                    }
                }
            })
            .catch(error => {
                console.error('Error en fetch de login:', error);
                if (loginErrorDiv) {
                    loginErrorDiv.textContent = 'Error de comunicación. Intenta de nuevo.';
                    loginErrorDiv.style.display = 'block';
                } else {
                    alert('Error de comunicación. Intenta de nuevo.');
                }
            })
            .finally(() => {
                submitButton.value = originalButtonText;
                submitButton.disabled = false;
            });
        });
    }

});
