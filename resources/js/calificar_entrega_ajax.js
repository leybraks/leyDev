document.addEventListener('DOMContentLoaded', function() {
    const gradingForm = document.getElementById('grading-form');
    const messageDiv = document.getElementById('grading-ajax-message'); // Div para mensajes AJAX

    if (gradingForm && messageDiv) {
        gradingForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevenir el envío tradicional
            console.log("GRADING_AJAX: Submit de #grading-form interceptado.");

            messageDiv.textContent = '';
            messageDiv.style.display = 'none';
            messageDiv.className = ''; // Resetear clases

            const submitButton = gradingForm.querySelector('button[type="submit"].btn-submit');
            const originalButtonText = submitButton ? submitButton.textContent : 'Guardar Calificación';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Guardando...';
            }

            const formData = new FormData(gradingForm);

            fetch('../Scripts/handle_grading.php', { // Ruta al script PHP
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log("GRADING_AJAX: Respuesta Fetch recibida, status:", response.status);
                return response.json().then(data => ({ ok: response.ok, status: response.status, data }));
            })
            .then(result => {
                const data = result.data;
                console.log("GRADING_AJAX: Datos JSON procesados:", data);

                messageDiv.style.display = 'block';

                if (data.success) {
                    messageDiv.textContent = data.message;
                    // Aplicar estilos de éxito (puedes usar tus variables CSS o clases)
                    messageDiv.style.color = 'var(--intranet-success-text, #155724)';
                    messageDiv.style.backgroundColor = 'var(--intranet-success-bg, #d4edda)';
                    messageDiv.style.borderColor = 'var(--intranet-success-border, #c3e6cb)';
                    // O: messageDiv.className = 'message success-message';
                    console.log("GRADING_AJAX: Calificación guardada:", data.message);

                    // Actualizar los campos de nota y feedback en la página con los nuevos valores
                    if (data.updated_grade_info) {
                        const gradeInput = document.getElementById('grade');
                        const feedbackTextarea = document.getElementById('tutor_feedback');
                        if (gradeInput) gradeInput.value = data.updated_grade_info.grade;
                        if (feedbackTextarea) feedbackTextarea.value = data.updated_grade_info.tutor_feedback;
                        // Podrías también mostrar la fecha de calificación actualizada
                    }

                    // Dejar el botón como "Guardado" o similar, o rehabilitarlo
                    if (submitButton) {
                        submitButton.textContent = '¡Guardado!';
                        // No lo deshabilitamos permanentemente por si quiere re-calificar
                        setTimeout(() => {
                            if(submitButton.textContent === '¡Guardado!') { // Solo si no ha habido otro error mientras tanto
                                submitButton.disabled = false;
                                submitButton.textContent = originalButtonText;
                            }
                        }, 3000);
                    }

                } else { // data.success es false
                    messageDiv.textContent = data.message || 'Error al guardar la calificación.';
                    messageDiv.style.color = 'var(--intranet-error-text, #721c24)';
                    messageDiv.style.backgroundColor = 'var(--intranet-error-bg, #f8d7da)';
                    messageDiv.style.borderColor = 'var(--intranet-error-border, #f5c6cb)';
                    // O: messageDiv.className = 'message error-message';
                    console.warn("GRADING_AJAX: Falló el guardado de calificación:", data.message);

                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalButtonText;
                    }
                }
            })
            .catch(error => {
                console.error('GRADING_AJAX: Error en la petición Fetch o al procesar JSON:', error);
                messageDiv.textContent = 'Error de conexión al guardar la calificación. Inténtalo más tarde.';
                messageDiv.style.display = 'block';
                messageDiv.style.color = 'var(--intranet-error-text, #721c24)';

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }
            });
        });
        console.log("GRADING_AJAX: Listener de submit adjuntado a #grading-form.");
    } else {
        if (!gradingForm) console.error("GRADING_AJAX: Formulario '#grading-form' no encontrado.");
        if (!messageDiv) console.error("GRADING_AJAX: Div '#grading-ajax-message' no encontrado.");
    }
});
