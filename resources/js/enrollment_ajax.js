
window.initEnrollmentForms = function() {
    console.log("ENROLLMENT_AJAX: initEnrollmentForms() llamado.");

    const enrollmentForms = document.querySelectorAll('.enroll-form-select');
   
    const immediateMessageDiv = document.getElementById('enrollment-ajax-message'); 

    if (!immediateMessageDiv) {
        console.warn("ENROLLMENT_AJAX: Div '#enrollment-ajax-message' no encontrado. Los mensajes inmediatos de inscripción no se mostrarán.");
    }

    if (enrollmentForms.length === 0) {
        return;
    }

    enrollmentForms.forEach(form => {
        if (form.dataset.enrollListenerAttached === 'true') {
            return;
        }

        form.addEventListener('submit', function(event) {
            event.preventDefault();
            console.log("ENROLLMENT_AJAX: Submit interceptado para el formulario:", form);

            if (immediateMessageDiv) {
                immediateMessageDiv.textContent = '';
                immediateMessageDiv.style.display = 'none';
                immediateMessageDiv.className = '';
            }

            const submitButton = form.querySelector('button[type="submit"].catalogo-boton-inscribir');
            const originalButtonText = submitButton ? submitButton.textContent : 'Inscribirme';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Procesando...';
            }

            const classIdSelect = form.querySelector('select[name="class_id"]');
            if (classIdSelect && !classIdSelect.value) {
                if (immediateMessageDiv) {
                    immediateMessageDiv.textContent = 'Por favor, selecciona un horario/clase antes de inscribirte.';
                    immediateMessageDiv.style.color = 'var(--intranet-error-text, #721c24)';
                    immediateMessageDiv.style.backgroundColor = 'var(--intranet-error-bg, #f8d7da)';
                    immediateMessageDiv.style.borderColor = 'var(--intranet-error-border, #f5c6cb)';
                    immediateMessageDiv.style.display = 'block';
                }
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }
                console.warn("ENROLLMENT_AJAX: No se seleccionó class_id.");
                return;
            }

            const formData = new FormData(form);

            fetch('../Scripts/handle_enrollment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log("ENROLLMENT_AJAX: Respuesta Fetch recibida, status:", response.status);
                return response.json().then(data => ({ ok: response.ok, status: response.status, data }));
            })
            .then(result => {
                const data = result.data;
                console.log("ENROLLMENT_AJAX: Datos JSON procesados:", data);

                if (data.success) {
                    console.log("ENROLLMENT_AJAX: Inscripción exitosa:", data.message);
                    if (typeof window.loadSectionGlobal === 'function') { 
                        window.loadSectionGlobal('catalogo', { 
                            enrollment_status: 'success', 
                            enrollment_msg: data.message 
                        });
                    } else {
                        console.error("ENROLLMENT_AJAX: La función global loadSectionGlobal no está definida. No se puede recargar el catálogo.");
                        // Como fallback, mostrar mensaje en el div actual
                        if (immediateMessageDiv) {
                            immediateMessageDiv.textContent = data.message;
                            immediateMessageDiv.style.color = 'var(--intranet-success-text, #155724)';
                            immediateMessageDiv.style.backgroundColor = 'var(--intranet-success-bg, #d4edda)';
                            immediateMessageDiv.style.borderColor = 'var(--intranet-success-border, #c3e6cb)';
                            immediateMessageDiv.style.display = 'block';
                        }
                         if (submitButton) { 
                            submitButton.textContent = '¡Inscrito!';
                        }
                    }
                } else { 
                    if (immediateMessageDiv) {
                        immediateMessageDiv.textContent = data.message || 'Error en la inscripción.';
                        immediateMessageDiv.style.color = 'var(--intranet-error-text, #721c24)';
                        immediateMessageDiv.style.backgroundColor = 'var(--intranet-error-bg, #f8d7da)';
                        immediateMessageDiv.style.borderColor = 'var(--intranet-error-border, #f5c6cb)';
                        immediateMessageDiv.style.display = 'block';
                    }
                    console.warn("ENROLLMENT_AJAX: Falló la inscripción:", data.message, "Action:", data.action);
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalButtonText;
                    }
                }
            })
            .catch(error => {
                console.error('ENROLLMENT_AJAX: Error en la petición Fetch o al procesar JSON:', error);
                if (immediateMessageDiv) {
                    immediateMessageDiv.textContent = 'Error de conexión al procesar la inscripción. Inténtalo más tarde.';
                    immediateMessageDiv.style.display = 'block';
                    immediateMessageDiv.style.color = 'var(--intranet-error-text, #721c24)';
                }
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }
            });
        });
        form.dataset.enrollListenerAttached = 'true';
    });
    console.log(`ENROLLMENT_AJAX: Listeners adjuntados a ${enrollmentForms.length} formularios de inscripción.`);
};
