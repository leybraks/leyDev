    document.addEventListener('DOMContentLoaded', function() {
        const createAssignmentForm = document.getElementById('create-assignment-form');
        const ajaxMessageDiv = document.getElementById('assignment-creation-ajax-message');

        if (createAssignmentForm && ajaxMessageDiv) {
            createAssignmentForm.addEventListener('submit', function(event) {
                event.preventDefault();
                console.log("CREATE_ASSIGNMENT_AJAX: Submit interceptado.");

                ajaxMessageDiv.textContent = '';
                ajaxMessageDiv.style.display = 'none';
                ajaxMessageDiv.className = 'message'; // Clase base

                const submitButton = createAssignmentForm.querySelector('button[type="submit"].btn-submit');
                const originalButtonText = submitButton ? submitButton.textContent : 'Guardar Tarea';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Guardando Tarea...';
                }

                const formData = new FormData(createAssignmentForm);

                fetch('../Scripts/handle_assignment_create.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, data })))
                .then(result => {
                    const data = result.data;
                    console.log("CREATE_ASSIGNMENT_AJAX: Respuesta JSON:", data);
                    ajaxMessageDiv.style.display = 'block';

                    if (data.success) {
                        ajaxMessageDiv.textContent = data.message;
                        ajaxMessageDiv.classList.add('success-message');
                        createAssignmentForm.reset(); // Limpiar formulario en éxito

                        if (data.redirect_url) {
                            // Opcional: Mostrar mensaje por un momento antes de redirigir
                            setTimeout(() => { window.location.href = data.redirect_url; }, 2000);
                        } else if (submitButton) { // Si no hay redirección, rehabilitar botón
                             submitButton.disabled = false;
                             submitButton.textContent = originalButtonText;
                        }
                    } else {
                        ajaxMessageDiv.textContent = data.message || 'Error al crear la tarea.';
                        ajaxMessageDiv.classList.add('error-message');
                        if (data.errors && Object.keys(data.errors).length > 0) {
                            let errorListHTML = '<ul>';
                            for (const key in data.errors) {
                                errorListHTML += `<li>${escapeHTML(data.errors[key])}</li>`;
                            }
                            errorListHTML += '</ul>';
                            ajaxMessageDiv.innerHTML = `<strong>${escapeHTML(data.message)}</strong>${errorListHTML}`;
                        }
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = originalButtonText;
                        }
                    }
                })
                .catch(error => {
                    console.error('CREATE_ASSIGNMENT_AJAX: Error en Fetch:', error);
                    ajaxMessageDiv.textContent = 'Error de conexión o respuesta inesperada del servidor.';
                    ajaxMessageDiv.style.display = 'block';
                    ajaxMessageDiv.classList.add('error-message');
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalButtonText;
                    }
                });
            });
        } else {
            if(!createAssignmentForm) console.error("Formulario #create-assignment-form no encontrado para AJAX.");
            if(!ajaxMessageDiv) console.error("Div #assignment-creation-ajax-message no encontrado.");
        }

        function escapeHTML(str) { // Helper function
            if (typeof str !== 'string') return '';
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    });