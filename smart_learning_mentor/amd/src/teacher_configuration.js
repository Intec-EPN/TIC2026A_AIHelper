/**
 * Modulo AMD que controla la interfaz de configuracion del profesor.
 *
 * Descripcion del flujo:
 *   1. lib.php carga este modulo en la pagina de configuracion.
 *   2. init() inicializa filtros, dependencias de toggles y boton de guardado.
 *   3. El toggle "Habilitar ayuda" controla si los dependientes estan habilitados.
 *   4. El boton "Guardar" recopila todos los datos y llama a save_configuration via AJAX.
 *   5. Se muestra mensaje de exito o error.
 *
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    return {

        /**
         * 1. Inicializa todos los eventos de la pagina de configuracion.
         */
        init: function() {
            const root = document.getElementById('slm-config-root');
            if (!root) {
                return;
            }

            // 2. Inicializar tooltips de Bootstrap (si estan disponibles).
            this._initTooltips();

            // 3. Registrar dependencias de toggles en todas las filas.
            root.querySelectorAll('.slm-config-row').forEach((row) => {
                this._initRowDependencies(row);
            });

            // 4. Filtro por nombre de actividad.
            const searchInput = document.getElementById('slm-config-search');
            if (searchInput) {
                searchInput.addEventListener('input', () => this._applyFilters());
            }

            // 5. Filtro por seccion.
            const sectionFilter = document.getElementById('slm-config-section-filter');
            if (sectionFilter) {
                sectionFilter.addEventListener('change', () => this._applyFilters());
            }

            // 6. Boton limpiar filtros.
            const resetBtn = document.getElementById('slm-config-reset');
            if (resetBtn) {
                resetBtn.addEventListener('click', () => this._resetFilters());
            }

            // 7. Boton guardar.
            const saveBtn = document.getElementById('slm-config-save-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', () => this._saveConfig(root));
            }
        },

        /**
         * 2. Inicializa los tooltips de Bootstrap en los iconos de ayuda.
         */
        _initTooltips: function() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
                    new bootstrap.Tooltip(el);
                });
            }
        },

        /**
         * 3. Inicializa las dependencias de toggles en una fila.
         *    - Toggle "ayuda" controla si recursos y ejemplos estan habilitados.
         *    - Toggle "recursos" o "ejemplos" activa automaticamente "ayuda".
         *
         * @param {HTMLElement} row Fila de la tabla
         */
        _initRowDependencies: function(row) {
            const toggleHelp      = row.querySelector('.slm-toggle-help');
            const toggleResources = row.querySelector('.slm-toggle-resources');
            const toggleExamples  = row.querySelector('.slm-toggle-examples');

            if (!toggleHelp || !toggleResources || !toggleExamples) {
                return;
            }

            // 3a. Al cambiar "Habilitar ayuda".
            toggleHelp.addEventListener('change', () => {
                if (!toggleHelp.checked) {
                    // Desactivar y deshabilitar dependientes.
                    toggleResources.checked  = false;
                    toggleResources.disabled = true;
                    toggleExamples.checked   = false;
                    toggleExamples.disabled  = true;
                } else {
                    // Habilitar dependientes.
                    toggleResources.disabled = false;
                    toggleExamples.disabled  = false;
                }
            });

            // 3b. Al activar "Recursos": activar ayuda automaticamente.
            toggleResources.addEventListener('change', () => {
                if (toggleResources.checked && !toggleHelp.checked) {
                    toggleHelp.checked = true;
                    toggleResources.disabled = false;
                    toggleExamples.disabled  = false;
                }
            });

            // 3c. Al activar "Ejemplos": activar ayuda automaticamente.
            toggleExamples.addEventListener('change', () => {
                if (toggleExamples.checked && !toggleHelp.checked) {
                    toggleHelp.checked = true;
                    toggleResources.disabled = false;
                    toggleExamples.disabled  = false;
                }
            });
        },

        /**
         * 4. Aplica los filtros de busqueda y seccion a la tabla.
         */
        _applyFilters: function() {
            const searchVal  = (document.getElementById('slm-config-search')?.value || '').toLowerCase().trim();
            const sectionVal = document.getElementById('slm-config-section-filter')?.value || '';

            let visible = 0;
            document.querySelectorAll('.slm-config-row').forEach(function(row) {
                const name    = (row.dataset.activityName || '').toLowerCase();
                const section = row.dataset.sectionnum || '';

                const matchesSearch  = !searchVal  || name.includes(searchVal);
                const matchesSection = !sectionVal || section === sectionVal;

                if (matchesSearch && matchesSection) {
                    row.classList.remove('d-none');
                    visible++;
                } else {
                    row.classList.add('d-none');
                }
            });

            const countEl = document.getElementById('slm-config-visible-count');
            if (countEl) {
                countEl.textContent = visible;
            }
        },

        /**
         * 5. Limpia todos los filtros y muestra todas las filas.
         */
        _resetFilters: function() {
            const searchInput = document.getElementById('slm-config-search');
            const sectionFilter = document.getElementById('slm-config-section-filter');

            if (searchInput)  { searchInput.value  = ''; }
            if (sectionFilter) { sectionFilter.value = ''; }

            this._applyFilters();
        },

        /**
         * 6. Recopila los datos de la tabla y los envia al endpoint AJAX.
         *
         * @param {HTMLElement} root Contenedor raiz del componente
         */
        _saveConfig: function(root) {
            const courseid = root.dataset.courseid;
            const saveBtn  = document.getElementById('slm-config-save-btn');
            const saveMsg  = document.getElementById('slm-config-save-msg');

            if (!courseid) {
                return;
            }

            // 6a. Recopilar datos de todas las filas (incluyendo las ocultas por filtros).
            const configs = [];
            root.querySelectorAll('.slm-config-row').forEach(function(row) {
                const vplid = parseInt(row.dataset.vplid, 10);
                if (!vplid) {
                    return;
                }

                const toggleHelp      = row.querySelector('.slm-toggle-help');
                const toggleResources = row.querySelector('.slm-toggle-resources');
                const toggleExamples  = row.querySelector('.slm-toggle-examples');
                const minEnvios       = row.querySelector('.slm-config-minenvios');
                const maxSolic        = row.querySelector('.slm-config-maxsolic');

                configs.push({
                    vplid:               vplid,
                    habilitar_ayuda:     toggleHelp?.checked      ? 1 : 0,
                    habilitar_recursos:  toggleResources?.checked ? 1 : 0,
                    habilitar_ejemplos:  toggleExamples?.checked  ? 1 : 0,
                    min_envios:          parseInt(minEnvios?.value || '1',  10),
                    max_solicitudes:     parseInt(maxSolic?.value  || '3',  10),
                });
            });

            if (!configs.length) {
                return;
            }

            // 6b. Deshabilitar el boton mientras se guarda.
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = '...';
            }
            if (saveMsg) {
                saveMsg.classList.add('d-none');
            }

            // 6c. Llamar al endpoint AJAX.
            Ajax.call([{
                methodname: 'local_smart_learning_mentor_save_configuration',
                args: {
                    courseid: parseInt(courseid, 10),
                    configs:  JSON.stringify(configs),
                },
            }])[0]
            .then(function(response) {
                if (saveBtn) {
                    saveBtn.disabled    = false;
                    saveBtn.textContent = saveBtn.dataset.originalText || 'Guardar configuracion';
                }
                if (saveMsg) {
                    saveMsg.textContent = response.message;
                    saveMsg.classList.remove('d-none', 'text-danger');
                    saveMsg.classList.add(response.success ? 'text-success' : 'text-danger');

                    // Ocultar el mensaje despues de 4 segundos.
                    setTimeout(function() {
                        saveMsg.classList.add('d-none');
                    }, 4000);
                }
            })
            .catch(function(error) {
                if (saveBtn) {
                    saveBtn.disabled    = false;
                    saveBtn.textContent = saveBtn.dataset.originalText || 'Guardar configuracion';
                }
                Notification.exception(error);
            });
        },
    };
});
