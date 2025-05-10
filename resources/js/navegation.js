document.addEventListener('DOMContentLoaded', function() {
    console.log("navegation.js: DOMContentLoaded - Script iniciado");
    const sidebarNav = document.getElementById('sidebar-nav');
    const mainContentArea = document.getElementById('main-content-area');
    const defaultSectionName = 'inicio';

    const sectionInitializers = {
        configuracion: function() { /* ... tu código ... */ 
            console.log("navegation.js: Inicializando listeners para la sección 'configuracion'");
            if (typeof window.initProfileFormEventListeners === 'function') {
                window.initProfileFormEventListeners();
            } else {
                console.warn("navegation.js: La función global 'window.initProfileFormEventListeners' no está definida.");
            }
        },
        calificaciones: function() { /* ... tu código ... */ 
            console.log("navegation.js: Inicializando listeners para la sección 'calificaciones'");
            const selectClassElement = document.getElementById('selected_class_id_tutor_grades');
            if (selectClassElement) {
                if (!selectClassElement.handleCalificacionesChange) {
                    selectClassElement.handleCalificacionesChange = function() {
                        const selectedClassId = this.value;
                        window.loadSectionGlobal('calificaciones', { selected_class_id: selectedClassId }); // Usar global
                    };
                }
                selectClassElement.removeEventListener('change', selectClassElement.handleCalificacionesChange);
                selectClassElement.addEventListener('change', selectClassElement.handleCalificacionesChange);
            }
        },
        mensajes: function() { /* ... tu código ... */ 
            console.log("navegation.js: Inicializando listeners para la sección 'mensajes'");
            const selectClassElement = document.getElementById('selected_class_id_for_message');
            if (selectClassElement) {
                if (!selectClassElement.handleMensajesClaseChange) {
                    selectClassElement.handleMensajesClaseChange = function() {
                        const selectedClassId = this.value;
                        window.loadSectionGlobal('mensajes', { selected_class_id_for_message: selectedClassId }); // Usar global
                    };
                }
                selectClassElement.removeEventListener('change', selectClassElement.handleMensajesClaseChange);
                selectClassElement.addEventListener('change', selectClassElement.handleMensajesClaseChange);
            }
        },
        catalogo: function() { // Inicializador para catálogo
            console.log("navegation.js: Inicializando listeners para la sección 'catalogo'");
            if (typeof window.initEnrollmentForms === 'function') {
                window.initEnrollmentForms();
            } else {
                console.warn("navegation.js: La función global 'window.initEnrollmentForms' no está definida.");
            }
        }
    };

    // --- FUNCIÓN PRINCIPAL PARA CARGAR SECCIONES (AHORA GLOBAL) ---
    window.loadSectionGlobal = function(sectionName, params = {}, pushToHistory = true) { // ASIGNADA A WINDOW
        if (!sectionName) {
            console.warn("loadSectionGlobal: Nombre de sección no proporcionado.");
            if (mainContentArea) mainContentArea.innerHTML = '<p class="error-message" style="padding:20px;">Error: Sección no especificada.</p>';
            return;
        }
        console.log(`loadSectionGlobal: Llamada para: '${sectionName}', params:`, params, ", pushToHistory:", pushToHistory);

        let sectionFileName = sectionName;
        switch (sectionName) {
            case 'inicio': sectionFileName = 'inicio'; break;
            case 'catalogo': sectionFileName = 'catalogo'; break;
            case 'cursos': sectionFileName = 'mis_cursos'; break;
            case 'notas': sectionFileName = 'mis_notas'; break;
            case 'cursos-asignados': sectionFileName = 'cursos_asignados'; break;
            case 'mis-estudiantes': sectionFileName = 'mis_estudiantes'; break;
            case 'calificaciones': sectionFileName = 'calificaciones_tutor'; break;
            case 'mensajes': sectionFileName = 'mensajes'; break;
            case 'configuracion': sectionFileName = 'configuracion'; break;
            default:
                console.log(`loadSectionGlobal: No hay mapeo específico para data-target '${sectionName}'.`);
        }
        console.log(`loadSectionGlobal: sectionFileName determinado: '${sectionFileName}'`);

        let sectionUrl = `sections/${sectionFileName}_content.php`;
        console.log(`loadSectionGlobal: URL base: '${sectionUrl}'`);

        if (Object.keys(params).length > 0) {
            const queryParams = new URLSearchParams(params).toString();
            sectionUrl += `?${queryParams}`;
            console.log(`loadSectionGlobal: URL final con params: '${sectionUrl}'`);
        }

        if (mainContentArea) mainContentArea.innerHTML = '<p class="loading-message" style="padding:20px; text-align:center;">Cargando...</p>';

        fetch(sectionUrl)
            .then(response => {
                console.log(`loadSectionGlobal: Fetch para '${sectionUrl}' - Status: ${response.status}`);
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`Error HTTP ${response.status} al cargar ${sectionUrl}.`);
                    });
                }
                return response.text();
            })
            .then(htmlContent => {
                if (mainContentArea) {
                    mainContentArea.innerHTML = htmlContent;
                    const loadedContentWrapper = mainContentArea.querySelector('.dynamic-section');
                    if (loadedContentWrapper) {
                        loadedContentWrapper.style.display = 'block';
                    } else if (mainContentArea.firstChild && mainContentArea.firstChild.style) {
                        mainContentArea.firstChild.style.display = 'block';
                    }
                } else { return; }

                if (sidebarNav) {
                    document.querySelectorAll('.sidebar-link.active').forEach(activeLink => activeLink.classList.remove('active'));
                    const newActiveLink = sidebarNav.querySelector(`.sidebar-link[data-target="${sectionName}"]`);
                    if (newActiveLink) newActiveLink.classList.add('active');
                }

                const pageTitleBase = "Intranet | LeyCode";
                const sectionTitle = sectionName.charAt(0).toUpperCase() + sectionName.slice(1).replace(/-/g, ' ');
                document.title = `${pageTitleBase} | ${sectionTitle}`;

                if (pushToHistory) {
                    const newHash = `#${sectionName}`;
                    history.pushState({ section: sectionName, params: params }, sectionTitle, newHash);
                }

                if (sectionInitializers[sectionName] && typeof sectionInitializers[sectionName] === 'function') {
                    sectionInitializers[sectionName]();
                }
            })
            .catch(error => {
                console.error(`loadSectionGlobal: Error para '${sectionName}':`, error);
                if (mainContentArea) mainContentArea.innerHTML = `<p class="error-message" style="padding:20px;">Error al cargar '<em>${sectionName}</em>'. (${error.message})</p>`;
            });
    }; // FIN DE window.loadSectionGlobal

    // --- MANEJO DE CLICS EN LA BARRA LATERAL ---
    if (sidebarNav) {
        sidebarNav.addEventListener('click', function(event) {
            const link = event.target.closest('.sidebar-link');
            if (link && link.dataset.target) {
                event.preventDefault();
                const targetSection = link.dataset.target;
                window.loadSectionGlobal(targetSection, {}, true); // Usar la función global
            }
        });
    }

    // --- MANEJO DE BOTONES ATRÁS/ADELANTE DEL NAVEGADOR ---
    window.addEventListener('popstate', function(event) {
        if (event.state && event.state.section) {
            window.loadSectionGlobal(event.state.section, event.state.params || {}, false); // Usar global
        } else {
            const currentHashSection = window.location.hash.substring(1);
            window.loadSectionGlobal(currentHashSection || defaultSectionName, {}, false); // Usar global
        }
    });

    // --- CARGA DE SECCIÓN INICIAL ---
    function loadInitialSection() {
        const currentHash = window.location.hash.substring(1);
        let sectionToLoad = defaultSectionName;
        let paramsToLoad = {};

        if (currentHash) {
            const potentialTargetLink = sidebarNav ? sidebarNav.querySelector(`.sidebar-link[data-target="${currentHash}"]`) : null;
            if (potentialTargetLink) {
                sectionToLoad = currentHash;
            } else {
                history.replaceState({ section: defaultSectionName, params: {} }, document.title, `#${defaultSectionName}`);
            }
        } else {
            history.replaceState({ section: defaultSectionName, params: {} }, document.title, `#${defaultSectionName}`);
        }
        window.loadSectionGlobal(sectionToLoad, paramsToLoad, false); // Usar la función global
    }

    if (mainContentArea && sidebarNav) {
        loadInitialSection();
    }
    
    const modalDeleteAccount = document.getElementById('delete-account-modal');

    if (mainContentArea) {
        mainContentArea.addEventListener('click', function(event) {
            if (event.target.closest('#btn-show-delete-modal')) {
                event.preventDefault();
                if (modalDeleteAccount) {
                    // ... (mostrar modal) ...
                    const passwordInput = modalDeleteAccount.querySelector('#delete-confirm-password');
                    const errorMessage = modalDeleteAccount.querySelector('#delete-error-message');
                    modalDeleteAccount.style.display = 'flex';
                    setTimeout(() => { modalDeleteAccount.classList.add('is-visible'); }, 10);
                    if (passwordInput) { passwordInput.value = ''; passwordInput.focus(); }
                    if (errorMessage) { errorMessage.style.display = 'none'; errorMessage.textContent = ''; }
                }
            }
        });
    }

    if (modalDeleteAccount) {
        // ... (listeners para cerrar y confirmar eliminación) ...
        const btnCloseModal = modalDeleteAccount.querySelector('#btn-close-delete-modal');
        const btnCancelDelete = modalDeleteAccount.querySelector('#btn-cancel-delete');
        const btnConfirmDelete = modalDeleteAccount.querySelector('#btn-confirm-delete');

        function hideDeleteModal() { /* ... */ 
             if (modalDeleteAccount) {
                modalDeleteAccount.classList.remove('is-visible');
                setTimeout(() => { modalDeleteAccount.style.display = 'none'; }, 300);
            }
        }

        if (btnCloseModal) btnCloseModal.addEventListener('click', hideDeleteModal);
        if (btnCancelDelete) btnCancelDelete.addEventListener('click', hideDeleteModal);
        modalDeleteAccount.addEventListener('click', function(event) {
            if (event.target === modalDeleteAccount) hideDeleteModal();
        });

        if (btnConfirmDelete) {
            btnConfirmDelete.addEventListener('click', function() {

                const passwordInput = modalDeleteAccount.querySelector('#delete-confirm-password');
                const errorMessage = modalDeleteAccount.querySelector('#delete-error-message');
                const csrfToken = modalDeleteAccount.dataset.csrfToken;
                if (!passwordInput || !errorMessage) return;
                const password = passwordInput.value;
                if (!password) { /* ... mostrar error ... */ return; }

                btnConfirmDelete.disabled = true;
                btnConfirmDelete.textContent = 'Eliminando...';
                errorMessage.style.display = 'none';

                const formData = new FormData();
                formData.append('password', password);
                formData.append('csrf_token', csrfToken);

                fetch('../Scripts/handle_delete_account.php', { method: 'POST', body: formData })
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(result => {
                    if (!result.ok) throw result.data;
                    return result.data;
                })
                .then(data => {
                    if (data.success && data.redirectUrl) {
                        alert(data.message); window.location.href = data.redirectUrl;
                    } else if (data.success) {
                        alert(data.message); hideDeleteModal();
                    } else {
                        errorMessage.textContent = data.message; errorMessage.style.display = 'block';
                        btnConfirmDelete.disabled = false; btnConfirmDelete.textContent = 'Sí, Eliminar mi Cuenta';
                    }
                })
                .catch(errorData => {
                    errorMessage.textContent = errorData.message || 'Error.'; errorMessage.style.display = 'block';
                    btnConfirmDelete.disabled = false; btnConfirmDelete.textContent = 'Sí, Eliminar mi Cuenta';
                });
            });
        }
    }

}); // Fin de DOMContentLoaded
