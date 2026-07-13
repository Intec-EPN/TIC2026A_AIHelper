/**
 * Modulo AMD para gestionar temas y conceptos del catalogo del profesor.
 *
 */
define(['core/ajax', 'core/notification', 'theme_boost/bootstrap/modal'], function(Ajax, Notification, Modal) {
    let courseid = 0;
    let editMode = false;

    return {
        /**
         * 1. Inicializa todos los eventos del gestor de temas y conceptos.
         */
        init: function() {
            const root = document.getElementById('slm-topics-root');
            if (!root) {
                return;
            }
            courseid = parseInt(root.dataset.courseid, 10);

            // 2. Edit mode toggle.
            const editmodeSwitch = document.getElementById('slm-editmode-switch');
            if (editmodeSwitch) {
                editmodeSwitch.addEventListener('change', () => {
                    editMode = editmodeSwitch.checked;
                    this._applyEditMode(editMode);
                });
            }

            // 3. Boton nuevo tema (abre modal).
            const addThemeBtn = document.getElementById('slm-add-theme-btn');
            if (addThemeBtn) {
                addThemeBtn.addEventListener('click', () => this._openNewThemeModal());
            }

            // 4. Guardar nuevo tema desde modal.
            const newThemeSaveBtn = document.getElementById('slm-new-theme-save-btn');
            if (newThemeSaveBtn) {
                newThemeSaveBtn.addEventListener('click', () => this._saveNewTheme());
            }

            // 5. Boton "+ Agregar" concepto dentro del modal de nuevo tema.
            const addConceptBtn = document.getElementById('slm-new-theme-add-concept-btn');
            if (addConceptBtn) {
                addConceptBtn.addEventListener('click', () => this._addPendingConcept());
            }

            // 6. Enter en el input de concepto del modal de nuevo tema agrega el concepto.
            const conceptModalInput = document.getElementById('slm-new-theme-concept-input');
            if (conceptModalInput) {
                conceptModalInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this._addPendingConcept();
                    }
                });
            }

            // 7. Enter en el input de nombre del tema mueve el foco al input de concepto.
            const newThemeInput = document.getElementById('slm-new-theme-name');
            if (newThemeInput) {
                newThemeInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('slm-new-theme-concept-input')?.focus();
                    }
                });
            }

            // 8. Delegacion de eventos en el contenedor de temas.
            const container = document.getElementById('slm-themes-container');
            if (container) {
                container.addEventListener('click', (e) => this._handleContainerClick(e));
                container.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && e.target.classList.contains('slm-new-concept-input')) {
                        const saveBtn = e.target.closest('.slm-add-concept-form')?.querySelector('.slm-save-concept-btn');
                        if (saveBtn) { saveBtn.click(); }
                    }
                });
            }

            // 9. Panel lateral de conceptos IA.
            this._initAiPanel();

            // 10. Modal para promover concepto IA.
            const promoteBtn = document.getElementById('slm-modal-promote-btn');
            if (promoteBtn) {
                promoteBtn.addEventListener('click', () => this._promoteAiConcept());
            }
        },

        // =====================================================================
        // EDIT MODE
        // =====================================================================

        /**
         * Activa o desactiva el modo de edicion en toda la pagina.
         *
         * @param {boolean} active
         */
        _applyEditMode: function(active) {
            document.querySelectorAll('.slm-theme-actions').forEach(el => {
                el.classList.toggle('d-none', !active);
                el.classList.toggle('d-flex', active);
            });
            document.querySelectorAll('.slm-delete-concept-btn').forEach(el => {
                el.classList.toggle('d-none', !active);
            });
            document.querySelectorAll('.slm-show-add-concept').forEach(el => {
                el.classList.toggle('d-none', !active);
            });
            if (!active) {
                document.querySelectorAll('.slm-theme-editor').forEach(el => el.classList.add('d-none'));
                document.querySelectorAll('.slm-add-concept-form').forEach(el => el.classList.add('d-none'));
            }
        },

        // =====================================================================
        // DELEGACION DE CLICKS EN EL CONTENEDOR DE TEMAS
        // =====================================================================

        /**
         * Maneja todos los clicks delegados dentro del contenedor de temas.
         *
         * @param {Event} e
         */
        _handleContainerClick: function(e) {
            if (e.target.closest('.slm-edit-theme-btn')) {
                const card = e.target.closest('.slm-theme-card');
                if (card) { this._showThemeEditor(card); }
                return;
            }
            if (e.target.closest('.slm-save-theme-name-btn')) {
                const card = e.target.closest('.slm-theme-card');
                if (card) { this._saveThemeName(card); }
                return;
            }
            if (e.target.closest('.slm-cancel-theme-edit-btn')) {
                const card = e.target.closest('.slm-theme-card');
                if (card) { this._hideThemeEditor(card); }
                return;
            }
            if (e.target.closest('.slm-delete-theme-btn')) {
                const card = e.target.closest('.slm-theme-card');
                if (card) { this._confirmDeleteTheme(card); }
                return;
            }
            if (e.target.closest('.slm-show-add-concept')) {
                const card = e.target.closest('.slm-theme-card');
                if (card) {
                    card.querySelector('.slm-add-concept-form')?.classList.remove('d-none');
                    card.querySelector('.slm-new-concept-input')?.focus();
                }
                return;
            }
            if (e.target.closest('.slm-save-concept-btn')) {
                const card = e.target.closest('.slm-theme-card');
                if (card) { this._saveConcept(card); }
                return;
            }
            if (e.target.closest('.slm-delete-concept-btn')) {
                const chip = e.target.closest('.slm-concept-chip');
                const card = e.target.closest('.slm-theme-card');
                if (chip && card) { this._confirmDeleteConcept(chip, card); }
                return;
            }
        },

        // =====================================================================
        // TEMAS: MODAL NUEVO TEMA
        // =====================================================================

        /**
         * Abre el modal para crear un nuevo tema.
         * Limpia todos los campos y la lista de conceptos pendientes.
         */
        _openNewThemeModal: function() {
            const input = document.getElementById('slm-new-theme-name');
            if (input) {
                input.value = '';
                input.classList.remove('is-invalid');
            }
            // Limpiar input de concepto.
            const conceptInput = document.getElementById('slm-new-theme-concept-input');
            if (conceptInput) {
                conceptInput.value = '';
                conceptInput.classList.remove('is-invalid');
            }
            // Limpiar lista de conceptos pendientes.
            const list = document.getElementById('slm-new-theme-concepts-list');
            if (list) {
                list.innerHTML = '<span class="text-muted small slm-no-concepts-hint">Ningún concepto agregado aún.</span>';
            }
            // Ocultar mensajes de error.
            const errorMsg = document.getElementById('slm-new-theme-concepts-error');
            if (errorMsg) { errorMsg.classList.add('d-none'); }
            const nameError = document.getElementById('slm-new-theme-name-error');
            if (nameError) { nameError.classList.add('d-none'); }

            const modalEl = document.getElementById('slm-new-theme-modal');
            if (modalEl) {
                const modal = new Modal(modalEl);
                modal.show();
                setTimeout(() => input?.focus(), 300);
            }
        },

        /**
         * Agrega un concepto pendiente a la lista del modal de nuevo tema.
         * No llama al servidor — se acumula y se envía todo junto al guardar.
         */
        _addPendingConcept: function() {
            const input    = document.getElementById('slm-new-theme-concept-input');
            const list     = document.getElementById('slm-new-theme-concepts-list');
            const errorMsg = document.getElementById('slm-new-theme-concepts-error');
            const nombre   = input?.value.trim();

            if (!nombre) {
                input?.classList.add('is-invalid');
                return;
            }
            input?.classList.remove('is-invalid');
            if (errorMsg) { errorMsg.classList.add('d-none'); }

            if (list) {
                // Quitar el mensaje "ningún concepto".
                list.querySelector('.slm-no-concepts-hint')?.remove();

                // Crear el chip del concepto pendiente con boton X.
                const chip = document.createElement('span');
                chip.className = 'badge bg-primary d-inline-flex align-items-center gap-1 slm-pending-concept-chip';
                chip.dataset.nombre = nombre;
                chip.style.cssText = 'font-size:13px; padding:6px 10px;';
                chip.innerHTML = `${this._esc(nombre)}
                    <button type="button" class="btn-close btn-close-white ms-1"
                            style="font-size:10px;" aria-label="Quitar"></button>`;

                // Boton X: elimina el chip de la lista.
                chip.querySelector('.btn-close').addEventListener('click', () => {
                    chip.remove();
                    if (list && !list.querySelector('.slm-pending-concept-chip')) {
                        const hint = document.createElement('span');
                        hint.className = 'text-muted small slm-no-concepts-hint';
                        hint.textContent = 'Ningún concepto agregado aún.';
                        list.appendChild(hint);
                    }
                });

                list.appendChild(chip);
            }

            // Limpiar input y mantener foco para agregar otro concepto.
            if (input) {
                input.value = '';
                input.focus();
            }
        },

        /**
         * Guarda el nuevo tema con sus conceptos via AJAX.
         * Validaciones:
         *   - Nombre no vacio.
         *   - Nombre no duplicado (comparado con temas existentes en el DOM).
         *   - Al menos un concepto.
         */
        _saveNewTheme: function() {
            const input       = document.getElementById('slm-new-theme-name');
            const nombre      = input?.value.trim();
            const errorMsg    = document.getElementById('slm-new-theme-concepts-error');
            const nameError   = document.getElementById('slm-new-theme-name-error');
            const conceptList = document.getElementById('slm-new-theme-concepts-list');

            // Recoger conceptos pendientes.
            const chips    = conceptList ? conceptList.querySelectorAll('.slm-pending-concept-chip') : [];
            const conceptos = Array.from(chips).map(c => c.dataset.nombre).filter(Boolean);

            let valid = true;

            // 1. Validar nombre no vacio.
            if (!nombre) {
                input?.classList.add('is-invalid');
                if (nameError) {
                    nameError.textContent = 'Por favor, ingresa el nombre del tema.';
                    nameError.classList.remove('d-none');
                }
                valid = false;
            } else {
                // 2. Validar nombre no duplicado: comparar con temas existentes en el DOM.
                const existingNames = Array.from(
                    document.querySelectorAll('.slm-theme-name-display')
                ).map(el => el.textContent.trim().toLowerCase());

                if (existingNames.includes(nombre.toLowerCase())) {
                    input?.classList.add('is-invalid');
                    if (nameError) {
                        nameError.textContent = 'Ya existe un tema con ese nombre. Por favor, elige otro.';
                        nameError.classList.remove('d-none');
                    }
                    valid = false;
                } else {
                    input?.classList.remove('is-invalid');
                    if (nameError) { nameError.classList.add('d-none'); }
                }
            }

            // 3. Validar al menos un concepto.
            if (!conceptos.length) {
                if (errorMsg) {
                    errorMsg.textContent = 'Por favor, ingresa al menos un concepto.';
                    errorMsg.classList.remove('d-none');
                }
                valid = false;
            } else {
                if (errorMsg) { errorMsg.classList.add('d-none'); }
            }

            if (!valid) { return; }

            // 4. Crear el tema.
            Ajax.call([{
                methodname: 'local_smart_learning_mentor_manage_topic',
                args: {action: 'create', courseid, nombre, descripcion: '', themeid: 0},
            }])[0]
            .then((response) => {
                if (!response.success) {
                    Notification.alert('', response.message);
                    return Promise.reject(response.message);
                }

                const themeid = response.id;

                // 5. Crear todos los conceptos en paralelo.
                const calls = conceptos.map(conceptNombre => ({
                    methodname: 'local_smart_learning_mentor_manage_concept',
                    args: {
                        action:      'create',
                        courseid,
                        themeid,
                        nombre:      conceptNombre,
                        descripcion: '',
                        conceptid:   0,
                        ia_ids:      '[]',
                    },
                }));

                return Ajax.call(calls)[calls.length - 1];
            })
            .then(() => {
                const modalEl = document.getElementById('slm-new-theme-modal');
                if (modalEl) {
                    const modal = Modal.getInstance(modalEl);
                    if (modal) { modal.hide(); }
                }
                window.location.reload();
            })
            .catch(Notification.exception);
        },

        // =====================================================================
        // TEMAS: EDITAR / ELIMINAR
        // =====================================================================

        /**
         * Muestra el editor de nombre de un tema.
         *
         * @param {HTMLElement} card
         */
        _showThemeEditor: function(card) {
            card.querySelector('.slm-theme-editor')?.classList.remove('d-none');
            card.querySelector('.slm-theme-name-input')?.focus();
        },

        /**
         * Oculta el editor de nombre de un tema.
         *
         * @param {HTMLElement} card
         */
        _hideThemeEditor: function(card) {
            card.querySelector('.slm-theme-editor')?.classList.add('d-none');
        },

        /**
         * Guarda el nombre actualizado de un tema.
         *
         * @param {HTMLElement} card
         */
        _saveThemeName: function(card) {
            const themeid = parseInt(card.dataset.themeid, 10);
            const input   = card.querySelector('.slm-theme-name-input');
            const nombre  = input?.value.trim();
            if (!nombre) {
                input?.classList.add('is-invalid');
                return;
            }
            Ajax.call([{
                methodname: 'local_smart_learning_mentor_manage_topic',
                args: {action: 'update', courseid, themeid, nombre, descripcion: ''},
            }])[0]
            .then((response) => {
                if (response.success) {
                    card.querySelector('.slm-theme-name-display').textContent = nombre;
                    card.dataset.themeName = nombre;
                    this._hideThemeEditor(card);
                    const option = document.querySelector(`#slm-modal-theme-select option[value="${themeid}"]`);
                    if (option) { option.textContent = nombre; }
                } else {
                    Notification.alert('', response.message);
                }
            })
            .catch(Notification.exception);
        },

        /**
         * Confirma y elimina un tema.
         *
         * @param {HTMLElement} card
         */
        _confirmDeleteTheme: function(card) {
            const themeid   = parseInt(card.dataset.themeid, 10);
            const themename = card.dataset.themeName || '';
            if (!window.confirm(`¿Eliminar el tema "${themename}" y todos sus conceptos?`)) {
                return;
            }
            Ajax.call([{
                methodname: 'local_smart_learning_mentor_manage_topic',
                args: {action: 'delete', courseid, themeid, nombre: '', descripcion: ''},
            }])[0]
            .then((response) => {
                if (response.success) {
                    card.closest('.slm-theme-card')?.parentElement?.removeChild(card.closest('.slm-theme-card'));
                    if (!document.querySelector('.slm-theme-card')) {
                        const container = document.getElementById('slm-themes-container');
                        if (container) {
                            container.innerHTML = '<div class="alert alert-info">Sin temas. Agrega uno nuevo.</div>';
                        }
                    }
                } else {
                    Notification.alert('', response.message);
                }
            })
            .catch(Notification.exception);
        },

        // =====================================================================
        // CONCEPTOS
        // =====================================================================

        /**
         * Guarda un nuevo concepto dentro de un tema existente.
         *
         * @param {HTMLElement} card
         */
        _saveConcept: function(card) {
            const themeid = parseInt(card.dataset.themeid, 10);
            const input   = card.querySelector('.slm-new-concept-input');
            const nombre  = input?.value.trim();
            if (!nombre) {
                input?.classList.add('is-invalid');
                return;
            }
            input?.classList.remove('is-invalid');
            Ajax.call([{
                methodname: 'local_smart_learning_mentor_manage_concept',
                args: {action: 'create', courseid, themeid, nombre, descripcion: '', conceptid: 0, ia_ids: '[]'},
            }])[0]
            .then((response) => {
                if (response.success) {
                    this._addConceptChip(card, response.id, response.nombre || nombre);
                    if (input) { input.value = ''; }
                    card.querySelector('.slm-add-concept-form')?.classList.add('d-none');
                    this._updateConceptCount(card);
                } else {
                    Notification.alert('', response.message);
                }
            })
            .catch(Notification.exception);
        },

        /**
         * Agrega el chip visual de un concepto a la lista del tema.
         *
         * @param {HTMLElement} card
         * @param {number}      conceptid
         * @param {string}      nombre
         */
        _addConceptChip: function(card, conceptid, nombre) {
            const list = card.querySelector('.slm-concepts-list');
            if (!list) { return; }
            list.querySelector('.slm-no-concepts')?.remove();
            const chip = document.createElement('span');
            chip.className = 'badge bg-primary slm-concept-chip d-inline-flex align-items-center gap-1';
            chip.dataset.conceptid = conceptid;
            chip.style.cssText = 'font-size:13px; padding:6px 10px;';
            chip.innerHTML = `
                ${this._esc(nombre)}
                <button type="button"
                        class="btn-close btn-close-white slm-delete-concept-btn ${editMode ? '' : 'd-none'}"
                        style="font-size:10px;"
                        aria-label="Eliminar concepto"></button>
            `;
            list.appendChild(chip);
        },

        /**
         * Confirma y elimina un concepto.
         *
         * @param {HTMLElement} chip
         * @param {HTMLElement} card
         */
        _confirmDeleteConcept: function(chip, card) {
            const conceptid   = parseInt(chip.dataset.conceptid, 10);
            const conceptname = chip.textContent.trim();
            if (!window.confirm(`¿Eliminar el concepto "${conceptname}"?`)) { return; }
            Ajax.call([{
                methodname: 'local_smart_learning_mentor_manage_concept',
                args: {action: 'delete', courseid, conceptid, themeid: 0, nombre: '', descripcion: '', ia_ids: '[]'},
            }])[0]
            .then((response) => {
                if (response.success) {
                    chip.remove();
                    this._updateConceptCount(card);
                    if (!card.querySelector('.slm-concept-chip')) {
                        const list = card.querySelector('.slm-concepts-list');
                        if (list && !list.querySelector('.slm-no-concepts')) {
                            const msg = document.createElement('span');
                            msg.className = 'text-muted small slm-no-concepts';
                            msg.textContent = 'Sin conceptos aun.';
                            list.appendChild(msg);
                        }
                    }
                } else {
                    Notification.alert('', response.message);
                }
            })
            .catch(Notification.exception);
        },

        /**
         * Actualiza el badge de conteo de conceptos de un tema.
         *
         * @param {HTMLElement} card
         */
        _updateConceptCount: function(card) {
            const count = card.querySelectorAll('.slm-concept-chip').length;
            const badge = card.querySelector('.slm-concept-count-badge');
            if (badge) { badge.textContent = count; }
        },

        // =====================================================================
        // PANEL LATERAL IA
        // =====================================================================

        /**
         * Inicializa el panel lateral de conceptos IA.
         */
        _initAiPanel: function() {
            const toggle    = document.getElementById('slm-ai-panel-toggle');
            const panel     = document.getElementById('slm-ai-panel');
            const closeBtn  = document.getElementById('slm-ai-panel-close');
            const topicsCol = document.getElementById('slm-topics-col');
            if (!toggle || !panel) { return; }

            toggle.addEventListener('click', () => {
                panel.classList.remove('d-none');
                topicsCol?.classList.replace('col-12', 'col-lg-8');
            });
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    panel.classList.add('d-none');
                    topicsCol?.classList.replace('col-lg-8', 'col-12');
                });
            }
            panel.addEventListener('click', (e) => {
                const btn  = e.target.closest('.slm-ai-add-btn');
                const item = e.target.closest('.slm-ai-concept-item');
                if (btn && item) {
                    this._openAiPromoteModal(item.dataset.aiNombre, item.dataset.aiIds);
                }
            });
        },

        /**
         * Abre el modal para promover un concepto IA al catalogo.
         *
         * @param {string} nombre
         * @param {string} idsJson
         */
        _openAiPromoteModal: function(nombre, idsJson) {
            const nameInput = document.getElementById('slm-modal-concept-name');
            const idsInput  = document.getElementById('slm-modal-ai-ids');
            if (nameInput) { nameInput.value = nombre; }
            if (idsInput)  { idsInput.value  = idsJson || '[]'; }
            const modalEl = document.getElementById('slm-ai-promote-modal');
            if (modalEl) {
                const modal = new Modal(modalEl);
                modal.show();
            }
        },

        /**
         * Guarda el concepto IA como concepto real en el catalogo.
         */
        _promoteAiConcept: function() {
            const nombre    = document.getElementById('slm-modal-concept-name')?.value.trim();
            const idsRaw    = document.getElementById('slm-modal-ai-ids')?.value || '[]';
            const themeidEl = document.getElementById('slm-modal-theme-select');
            const themeid   = parseInt(themeidEl?.value || '0', 10);
            if (!nombre || !themeid) {
                if (!nombre) { document.getElementById('slm-modal-concept-name')?.classList.add('is-invalid'); }
                if (!themeid) { themeidEl?.classList.add('is-invalid'); }
                return;
            }
            Ajax.call([{
                methodname: 'local_smart_learning_mentor_manage_concept',
                args: {action: 'promote_ai', courseid, themeid, nombre, descripcion: '', conceptid: 0, ia_ids: idsRaw},
            }])[0]
            .then((response) => {
                if (response.success) {
                    const modalEl = document.getElementById('slm-ai-promote-modal');
                    if (modalEl) {
                        const modal = Modal.getInstance(modalEl);
                        if (modal) { modal.hide(); }
                    }
                    const card = document.querySelector(`.slm-theme-card[data-themeid="${themeid}"]`);
                    if (card) {
                        this._addConceptChip(card, response.id, nombre);
                        this._updateConceptCount(card);
                    }
                } else {
                    Notification.alert('', response.message);
                }
            })
            .catch(Notification.exception);
        },

        // =====================================================================
        // UTILIDADES
        // =====================================================================

        /**
         * Escapa HTML para prevenir XSS.
         *
         * @param {string} value
         * @return {string}
         */
        _esc: function(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },
    };
});
