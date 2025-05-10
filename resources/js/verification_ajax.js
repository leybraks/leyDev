document.addEventListener('DOMContentLoaded', function() {
    console.log("VERIFICATION_AYAX: DOMContentLoaded - Script iniciado.");
    const formVerificacion = document.getElementById('formVerificacionCodigo'); // ID de tu formulario en verificarCorreo.php
    const codigoInput = document.getElementById('codigo'); // ID de tu input de código
    const messageDiv = document.getElementById('verification-ajax-message'); // ID del div para mensajes

    if (!formVerificacion) {
        console.error("VERIFICATION_AYAX: Formulario '#formVerificacionCodigo' no encontrado.");
        return;
    }
    if (!messageDiv) {
        console.warn("VERIFICATION_AYAX: Div '#verification-ajax-message' no encontrado. Los mensajes no se mostrarán allí.");
    }

    formVerificacion.addEventListener('submit', function(event) {
        event.preventDefault(); 
        console.log("VERIFICATION_AYAX: Submit de #formVerificacionCodigo interceptado.");

        if (messageDiv) {
            messageDiv.textContent = '';
            messageDiv.style.display = 'none';
            messageDiv.classList.remove('success-message', 'error-message');
            if (!messageDiv.classList.contains('message')) { // Asegurar clase base si la usas
                 messageDiv.classList.add('message');
            }
        }

        const submitButton = formVerificacion.querySelector('input[type="submit"]');
        const originalButtonText = submitButton ? submitButton.value : 'Verificar';
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.value = 'Verificando...';
        }

        const codigoIngresado = codigoInput ? codigoInput.value : '';
        const formData = new FormData();
        formData.append('codigo', codigoIngresado);

        fetch('../Scripts/handle_verificacion_codigo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log("VERIFICATION_AYAX: Respuesta Fetch recibida, status:", response.status);
            return response.json().then(data => ({ ok: response.ok, status: response.status, data }));
        })
        .then(result => {
            const data = result.data;
            console.log("VERIFICATION_AYAX: Datos JSON procesados:", data);

            if (messageDiv) {
                messageDiv.textContent = data.message || 'Respuesta del servidor.';
                messageDiv.style.display = 'block';
            }

            if (data.success) {
                if (messageDiv) {
                    messageDiv.classList.add('success-message');
                }
                console.log("VERIFICATION_AYAX: Verificación exitosa:", data.message);

                if (data.redirectUrl) {
                    console.log("VERIFICATION_AYAX: Redirigiendo a:", data.redirectUrl);
                    setTimeout(() => {
                        window.location.href = data.redirectUrl;
                    }, 1500); 
                    return; 
                } else {
                    console.warn("VERIFICATION_AYAX: Éxito pero no redirectUrl proporcionada.");
                }
            } else { // data.success es false
                if (messageDiv) {
                    messageDiv.classList.add('error-message');
                }
                console.warn("VERIFICATION_AYAX: Falló la verificación:", data.message, "Status:", data.status);
            }

            if (submitButton) {
                submitButton.disabled = false;
                submitButton.value = originalButtonText;
            }
        })
        .catch(error => {
            console.error('VERIFICATION_AYAX: Error en la petición Fetch o al procesar JSON:', error);
            if (messageDiv) {
                messageDiv.textContent = 'Error de conexión o respuesta inesperada. Inténtalo más tarde.';
                messageDiv.style.display = 'block';
                messageDiv.classList.add('error-message');
            }
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.value = originalButtonText;
            }
        });
    });
    console.log("VERIFICATION_AYAX: Listener de submit adjuntado a #formVerificacionCodigo.");
});
