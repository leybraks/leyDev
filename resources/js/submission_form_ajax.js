document.addEventListener('DOMContentLoaded', function() {
    const submissionForm = document.getElementById('submission-form');
    const ajaxMessageDiv = document.getElementById('submission-ajax-message');
    const currentSubmissionInfoDiv = document.querySelector('.current-submission-info');


    if (submissionForm && ajaxMessageDiv) {
        submissionForm.addEventListener('submit', function(event) {
            event.preventDefault();
            console.log("SUBMISSION_AJAX: Submit de #submission-form interceptado.");

            ajaxMessageDiv.textContent = '';
            ajaxMessageDiv.style.display = 'none';
            ajaxMessageDiv.classList.remove('success-message', 'error-message');
            if (!ajaxMessageDiv.classList.contains('message')) {
                 ajaxMessageDiv.classList.add('message');
            }

            const submitButton = submissionForm.querySelector('button[type="submit"].btn-submit');
            const originalButtonText = submitButton ? submitButton.textContent : 'Enviar';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...'; 
            }

            const formData = new FormData(submissionForm);

            fetch('../Scripts/handle_submission_upload.php', {
                method: 'POST',
                body: formData // FormData maneja enctype="multipart/form-data" automáticamente
            })
            .then(response => {
                console.log("SUBMISSION_AJAX: Respuesta Fetch recibida, status:", response.status);
                return response.json().then(data => ({ ok: response.ok, status: response.status, data }));
            })
            .then(result => {
                const data = result.data;
                console.log("SUBMISSION_AJAX: Datos JSON procesados:", data);

                ajaxMessageDiv.style.display = 'block';

                if (data.success) {
                    ajaxMessageDiv.textContent = data.message;
                    ajaxMessageDiv.classList.add('success-message');
                    console.log("SUBMISSION_AJAX: Entrega procesada:", data.message);

                    // Actualizar la UI para reflejar la nueva entrega o la edición
                    if (currentSubmissionInfoDiv && data.submission_details) {
                        const details = data.submission_details;
                        currentSubmissionInfoDiv.querySelector('h4').innerHTML = '<i class="fas fa-check-circle" style="color: green;"></i> Tarea enviada/actualizada';
                        currentSubmissionInfoDiv.querySelector('p:nth-of-type(1)').innerHTML = `<strong>Archivo enviado:</strong> ${escapeHTML(details.original_filename)}`;
                        currentSubmissionInfoDiv.querySelector('p:nth-of-type(2)').innerHTML = `<strong>Fecha de entrega:</strong> ${new Date(details.submission_time.replace(/-/g, '/')).toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}`;
                        
                        if (submitButton && data.action_taken === 'updated') {
                            submitButton.textContent = 'Entrega Actualizada';
                        } else if (submitButton && data.action_taken === 'created') {

                            const fileInput = submissionForm.querySelector('input[type="file"]');
                            if(fileInput) fileInput.value = '';
                            const commentsInput = submissionForm.querySelector('textarea[name="submission_comments"]');
                            if(commentsInput) commentsInput.value = ''; // Limpiar comentarios
                            
                            // Cambiar el botón a "Actualizar Entrega" y la acción del form
                            if(submitButton) submitButton.textContent = 'Actualizar Entrega';
                            const actionInput = submissionForm.querySelector('input[name="action"]');
                            if(actionInput) actionInput.value = 'edit';
                            // Añadir existing_submission_id si no existe
                            let existingIdInput = submissionForm.querySelector('input[name="existing_submission_id"]');
                            if(!existingIdInput) {
                                existingIdInput = document.createElement('input');
                                existingIdInput.type = 'hidden';
                                existingIdInput.name = 'existing_submission_id';
                                submissionForm.appendChild(existingIdInput);
                            }
                            existingIdInput.value = details.submission_id;

                        }
                    } else if (data.action_taken === 'created') {
                         const fileInput = submissionForm.querySelector('input[type="file"]');
                         if(fileInput) fileInput.value = '';
                         const commentsInput = submissionForm.querySelector('textarea[name="submission_comments"]');
                         if(commentsInput) commentsInput.value = '';
                    }

                    if (submitButton) {
                        // No deshabilitar permanentemente para permitir edición
                        submitButton.disabled = false;
                        if (data.action_taken === 'updated') {
                             submitButton.textContent = 'Actualizar Entrega';
                        } else if (data.action_taken === 'created') {
                             submitButton.textContent = 'Actualizar Entrega'; // Ahora es para actualizar
                        } else {
                             submitButton.textContent = originalButtonText;
                        }
                    }

                } else { // data.success es false
                    ajaxMessageDiv.textContent = data.message || 'Error al procesar la entrega.';
                    ajaxMessageDiv.classList.add('error-message');
                    if (data.errors) {
                        let errorListHTML = '<ul>';
                        for (const key in data.errors) {
                            errorListHTML += `<li>${escapeHTML(data.errors[key])}</li>`;
                        }
                        errorListHTML += '</ul>';
                        ajaxMessageDiv.innerHTML = `<strong>${escapeHTML(data.message)}</strong>${errorListHTML}`;
                    }
                    console.warn("SUBMISSION_AJAX: Falló la entrega:", data.message);
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalButtonText; 
                    }
                }
            })
            .catch(error => {
                console.error('SUBMISSION_AJAX: Error en la petición Fetch o al procesar JSON:', error);
                ajaxMessageDiv.textContent = 'Error de conexión o respuesta inesperada. Inténtalo más tarde.';
                ajaxMessageDiv.style.display = 'block';
                ajaxMessageDiv.classList.add('error-message');

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            });
        });
        console.log("SUBMISSION_AJAX: Listener de submit adjuntado a #submission-form.");
    } else {
        if (!submissionForm) console.error("SUBMISSION_AJAX: Formulario '#submission-form' no encontrado.");
        if (!ajaxMessageDiv) console.error("SUBMISSION_AJAX: Div '#submission-ajax-message' no encontrado.");
    }

    function escapeHTML(str) {
        if (typeof str !== 'string') return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
});
