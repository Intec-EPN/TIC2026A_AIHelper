/**
 * Módulo AMD que controla la interacción del panel flotante del estudiante.
 *
 * flujo:
 *   1. lib.php registra este módulo para cargarlo en páginas VPL.
 *   2. init() inicializa los eventos del panel (abrir, cerrar, botón de ayuda).
 *   3. El estudiante hace clic en "Obtener ayuda".
 *   4. Se obtiene el cmid desde la URL actual.
 *   5. Se llama al endpoint AJAX local_smart_learning_mentor_request_vpl_analysis.
 *   6. Se recibe la respuesta de la IA y se renderiza en el panel.
 *
 * REGLAS:
 *   X No contiene lógica del negocio.
 *   X No genera HTML complejo de forma manual (usa funciones auxiliares).
 *   ✔ Solo controla la interacción del usuario y llama al endpoint AJAX.
 *
 * @module      local_smart_learning_mentor/student_panel
 * @package     local_smart_learning_mentor
 * @copyright   2026 Estefania Martinez <joselyn.martinez@epn.edu.ec>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define(['core/ajax'], function(Ajax) {

    // Configuración del VPL leída del DOM al init.
    let panelConfig = {
        canRequest:          true,
        habilitarTemas:      true,
        habilitarRecursos:   true,
        habilitarEjemplos:   true,
        solicitudesUsadas:   0,
        maxSolicitudes:      3,
    };

    const SEL = {
        TAB_BUTTON:        '#slm-tab-button',
        PANEL_CONFIG:      '#slm-panel-config',
        PANEL:             '#slm-panel',
        CLOSE_BUTTON:      '#slm-panel-close',
        GET_HELP_BUTTON:   '#slm-get-help-btn',
        LOADING:           '#slm-loading',
        RESULTS:           '#slm-results',
        ANALYSIS_MESSAGE:  '#slm-analysis-message',
        ERRORS_CONTAINER:  '#slm-errors-container',
        MODAL:             '#slm-example-modal',
        MODAL_OVERLAY:     '#slm-modal-overlay',
        MODAL_CLOSE:       '#slm-modal-close',
        MODAL_CLOSE_BTN:   '#slm-modal-close-btn',
        MODAL_TITLE:       '#slm-modal-title',
        MODAL_DESCRIPCION: '#slm-modal-descripcion',
        MODAL_CODIGO:      '#slm-modal-codigo',
        MODAL_EXPLICACION: '#slm-modal-explicacion',
        MODAL_RESULTADO:   '#slm-modal-resultado',
        REQUEST_COUNTER:   '#slm-request-counter',
        REQUESTS_USED:     '#slm-requests-used',
    };

    return {

        /**
         * 1. Inicializa todos los eventos del panel y del modal.
         */
        init: function() {
            const tabButton = document.querySelector(SEL.TAB_BUTTON);
            const panel     = document.querySelector(SEL.PANEL);
            const closeBtn  = document.querySelector(SEL.CLOSE_BUTTON);
            const helpBtn   = document.querySelector(SEL.GET_HELP_BUTTON);

            if (!tabButton || !panel || !closeBtn || !helpBtn) {
                return;
            }

            tabButton.addEventListener('click', () => panel.classList.toggle('slm-panel--open'));
            tabButton.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    panel.classList.toggle('slm-panel--open');
                }
            });

            closeBtn.addEventListener('click', () => panel.classList.remove('slm-panel--open'));
            helpBtn.addEventListener('click', () => this._requestAnalysis());

            const resultsSection = document.querySelector(SEL.RESULTS);
            if (resultsSection) {
                resultsSection.addEventListener('click', (e) => {
                    const btn = e.target.closest('.slm-accordion-toggle');
                    if (btn && resultsSection.contains(btn)) {
                        this._toggleAccordion(btn);
                    }
                });
            }

            this._initModal();

            // Leer configuración del VPL desde el div #slm-panel-config.
            const cfg = document.querySelector(SEL.PANEL_CONFIG);
            if (cfg) {
                panelConfig.canRequest        = cfg.dataset.canRequest        !== '0';
                panelConfig.habilitarTemas    = cfg.dataset.habilitarTemas    !== '0';
                panelConfig.habilitarRecursos = cfg.dataset.habilitarRecursos !== '0';
                panelConfig.habilitarEjemplos = cfg.dataset.habilitarEjemplos !== '0';
                panelConfig.solicitudesUsadas = parseInt(cfg.dataset.solicitudesUsadas || '0', 10);
                panelConfig.maxSolicitudes    = parseInt(cfg.dataset.maxSolicitudes    || '3', 10);
            }
        },

        /**
         * 2. Inicializa los eventos del modal de ejemplos.
         */
        _initModal: function() {
            const modal      = document.querySelector(SEL.MODAL);
            const overlay    = document.querySelector(SEL.MODAL_OVERLAY);
            const closeBtn   = document.querySelector(SEL.MODAL_CLOSE);
            const closeBtnFt = document.querySelector(SEL.MODAL_CLOSE_BTN);

            if (!modal) { return; }

            const closeModal = () => modal.classList.add('hidden');
            if (overlay)    { overlay.addEventListener('click', closeModal); }
            if (closeBtn)   { closeBtn.addEventListener('click', closeModal); }
            if (closeBtnFt) { closeBtnFt.addEventListener('click', closeModal); }

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        },

        /**
         * 3. Abre el modal y rellena los datos del ejemplo seleccionado.
         */
        _openExampleModal: function(example) {
            const modal = document.querySelector(SEL.MODAL);
            if (!modal) { return; }

            const set = (sel, value) => {
                const el = document.querySelector(sel);
                if (el) { el.textContent = value || ''; }
            };

            set(SEL.MODAL_TITLE,       example.title              || 'Ejemplo');
            set(SEL.MODAL_DESCRIPCION, example.descripcion        || '');
            set(SEL.MODAL_CODIGO,      example.codigo             || '');
            set(SEL.MODAL_EXPLICACION, example.explicacion        || '');
            set(SEL.MODAL_RESULTADO,   example.resultado_esperado || '');

            modal.classList.remove('hidden');

            const title = document.querySelector(SEL.MODAL_TITLE);
            if (title) { title.focus(); }
        },

        /**
         * 4. Solicita el análisis de la actividad VPL al endpoint AJAX.
         */
        _requestAnalysis: function() {
            // Verificar que el botón esté habilitado por configuración.
            if (!panelConfig.canRequest) {
                return; // El botón ya está deshabilitado en el DOM, doble seguridad.
            }

            const cmid = this._getCmidFromUrl();
            if (!cmid) {
                this._showAnalysisMessage('No estás en una actividad válida.');
                return;
            }

            this._setLoading(true);

            Ajax.call([{
                methodname: 'local_smart_learning_mentor_request_vpl_analysis',
                args: {cmid: parseInt(cmid, 10)},
            }])[0]
            .then((response) => {
                this._setLoading(false);

                console.log('RESPUESTA COMPLETA:', response);

                if (!response || !response.status) {
                    this._showAnalysisMessage(
                        (response && response.message) ? response.message : 'Error al procesar la solicitud.'
                    );
                    return;
                }

                let aiResponse = null;
                try {
                    const data = JSON.parse(response.data);

                    console.log('JSON PARSEADO ENVIADO A LA IA:', data);

                    aiResponse = (data && data.n8n_response && data.n8n_response.output)
                        ? data.n8n_response.output
                        : null;
                    
                    console.log('RESPUESTA IA COMPLETA:', aiResponse);

                } catch (e) {
                    window.console.error('slm: error parseando JSON', e);
                }

                if (!aiResponse) {
                    this._showAnalysisMessage('No se encontró respuesta de la IA.');
                    return;
                }

                this._renderAiResponse(aiResponse);

                // Actualizar el contador de solicitudes en el DOM.
                panelConfig.solicitudesUsadas += 1;
                this._updateRequestCounter();

                // Si se alcanza el máximo, deshabilitar el botón.
                if (panelConfig.solicitudesUsadas >= panelConfig.maxSolicitudes) {
                    const btn = document.querySelector(SEL.GET_HELP_BUTTON);
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                        btn.style.cursor = 'not-allowed';
                    }
                    const counter = document.querySelector(SEL.REQUEST_COUNTER);
                    if (counter) {
                        const msg = document.createElement('p');
                        msg.className = 'slm-blocked-msg';
                        msg.textContent = 'Has alcanzado el límite de solicitudes para esta actividad.';
                        counter.after(msg);
                    }
                    panelConfig.canRequest = false;
                }
            })
            .catch((error) => {
                this._setLoading(false);
                this._showAnalysisMessage('Error al comunicarse con el servidor.');
                window.console.error('slm: error AJAX', error);
            });
        },

        /**
         * 5. Renderiza la respuesta completa de la IA en el panel.
         */
        _renderAiResponse: function(aiResponse) {
            if (!aiResponse) { return; }
            this._showAnalysisMessage(aiResponse.student_message || 'Análisis completado');

            // Aplicar filtros de configuración del profesor antes de renderizar.
            const errors = (aiResponse.errors || []).map(error => {
                let filtered = Object.assign({}, error);

                // Si no se muestran temas/conceptos → vaciar conceptos del profesor.
                if (!panelConfig.habilitarTemas) {
                    filtered.concepts = (filtered.concepts || []).filter(
                        c => typeof c !== 'object' // mantener solo strings (IA), quitar objetos (profesor)
                    );
                }

                // Si no se muestran recursos → quitar recursos de cada concepto del profesor.
                if (!panelConfig.habilitarRecursos) {
                    filtered.concepts = (filtered.concepts || []).map(c => {
                        if (typeof c === 'object' && c !== null) {
                            return Object.assign({}, c, {resources: []});
                        }
                        return c;
                    });
                }

                // Si no se muestran ejemplos → vaciar ejemplos.
                if (!panelConfig.habilitarEjemplos) {
                    filtered.examples = [];
                }

                return filtered;
            });

            this._renderErrors(errors);

            const results = document.querySelector(SEL.RESULTS);
            if (results) { results.classList.remove('hidden'); }
        },

        /**
         * 6. Renderiza la lista de errores en el contenedor.
         */
        _renderErrors: function(errors) {
            const container = document.querySelector(SEL.ERRORS_CONTAINER);
            if (!container) { return; }

            const list = Array.isArray(errors) ? errors : [];

            if (!list.length) {
                container.innerHTML = `
                    <div class="slm-card slm-card--empty">
                        <p>No se detectaron errores frecuentes. ¡Buen trabajo!</p>
                    </div>`;
                return;
            }

            container.innerHTML = list.map((error, index) =>
                this._buildErrorHtml(error, index)
            ).join('');

            container.addEventListener('click', (e) => {
                const exBtn = e.target.closest('.slm-example-title-btn');
                if (!exBtn) { return; }
                const exIndex  = parseInt(exBtn.dataset.exindex,  10);
                const errIndex = parseInt(exBtn.dataset.errindex, 10);
                const example  = (list[errIndex].examples || [])[exIndex];
                if (example) { this._openExampleModal(example); }
            });
        },

        /**
         * 7. Construye el HTML de un ítem de error.
         *    Muestra: Meta, Error/Recomendación, Conceptos del profesor (con recursos),
         *    Ejemplos IA.
         *    DESHABILITADO (comentado): Conceptos IA, Recursos IA.
         */
        _buildErrorHtml: function(error, index) {
            const title          = this._esc(error.title || 'Error detectado');
            const percentage     = parseInt(error.percentage ?? 0, 10) || 0;
            const badgeClass     = this._badgeClass(error.badge);
            const detected       = parseInt(error.detected ?? 0, 10) || 0;
            const errorText      = this._esc(error.error || '');
            const recommendation = this._esc(error.recommendation || '');

            // Conceptos del profesor: objetos {name, resources[]}.
            // Conceptos IA: strings con prefijo "IA:".
            const allConcepts = Array.isArray(error.concepts) ? error.concepts : [];
            const teacherConcepts = allConcepts.filter(c =>
                typeof c === 'object' && c !== null
            );
            // const aiConcepts = allConcepts.filter(c =>   // TODO: habilitar cuando el ingeniero lo pida
            //     typeof c !== 'object' && String(c).startsWith('IA:')
            // );

            // const resources = Array.isArray(error.resources) ? error.resources : [];  // TODO: recursos IA
            const examples  = Array.isArray(error.examples)  ? error.examples  : [];

            const bodyId     = `slm-err-body-${index}`;
            const examplesId = `slm-err-ex-${index}`;

            return `
            <div class="slm-card slm-card--error">

                <button type="button"
                        class="slm-error-header slm-accordion-toggle"
                        data-target="${bodyId}"
                        aria-expanded="false">
                    <div class="slm-error-header-left">
                        <span class="slm-error-title">${title}</span>
                    </div>
                    <span class="slm-arrow" aria-hidden="true">▼</span>
                </button>

                <div class="slm-error-body hidden" id="${bodyId}">

                    <div class="slm-error-meta">
                        <span><strong>Detectado:</strong> ${detected} ${detected === 1 ? 'vez' : 'veces'}</span>
                    </div>

                    <p><strong>Error:</strong> ${errorText}</p>
                    <p><strong>Recomendación:</strong> ${recommendation}</p>

                    ${this._buildTeacherConceptsBlock(teacherConcepts)}

                    ${this._buildExamplesList(examples, index, examplesId)}

                </div>
            </div>`;
        },
/*
                        <span class="slm-badge ${badgeClass}">${percentage}%</span>


                        <span><strong>Gravedad:</strong> ${this._esc(error.badge || 'low')}</span>

*/
        /* TODO: descomentar cuando el ingeniero lo pida
        ${this._buildAiConceptsBlock(aiConcepts, index)}
        <div class="slm-mini-accordion">
            ${this._buildNestedSection(
                resourcesId,
                'Recursos recomendados por la IA',
                resources,
                'slm-chip--resource',
                'Sin recursos recomendados.'
            )}
        </div>
        */
       /*
                               <span class="slm-badge ${badgeClass}">${percentage}%</span>

       */

        /**
         * 8. Construye el bloque de conceptos del profesor con sus recursos.
         *    Si no hay conceptos, muestra un mensaje orientativo.
         */
        _buildTeacherConceptsBlock: function(concepts) {
            if (!concepts || !concepts.length) {
                return `
                <div class="slm-inline-section">
                    <div class="slm-inline-title">Conceptos del catálogo del profesor</div>
                    <p class="slm-empty-inline">
                        No hay conceptos del catálogo asociados a este error.
                        El profesor puede agregarlos en la sección de Catálogo.
                    </p>
                </div>`;
            }

            const items = concepts.map((concept) => {
                let name, resources;
                if (typeof concept === 'object' && concept !== null) {
                    name      = this._esc(String(concept.name || '').trim());
                    resources = Array.isArray(concept.resources) ? concept.resources : [];
                } else {
                    name      = this._esc(String(concept).trim());
                    resources = [];
                }

                let resourcesHtml;
                if (resources.length) {
                    const chips = resources.map((r) => {
                        const titulo = this._esc(String(r.titulo || r.title || '').trim());
                        const url    = String(r.url || '').trim();
                        if (url) {
                            return `<a href="${url}" target="_blank" class="slm-chip slm-chip--resource">
                                        ${titulo} <span style="font-size:10px;">↗</span>
                                    </a>`;
                        }
                        return `<span class="slm-chip slm-chip--resource"> ${titulo}</span>`;
                    }).join('');
                    resourcesHtml = `<div class="slm-chip-group slm-resource-chips mt-1">${chips}</div>`;
                } else {
                    resourcesHtml = `<p class="slm-empty-inline">No hay recursos disponibles.</p>`;
                }

                return `
                <div class="slm-teacher-concept-item">
                    <div class="slm-chip-group">
                        <span class="slm-chip slm-chip--concept"> ${name}</span>
                    </div>
                    <div class="slm-teacher-resources">
                        <p class="slm-teacher-resources-label">Recursos del profesor:</p>
                        ${resourcesHtml}
                    </div>
                </div>`;
            }).join('');

            return `
            <div class="slm-inline-section">
                <div class="slm-inline-title"> Conceptos del catálogo del profesor</div>
                ${items}
            </div>`;
        },

        /**
         * 9. Construye el bloque de conceptos IA.
         *    DESHABILITADO — no se llama desde _buildErrorHtml.
         *    TODO: habilitar cuando el ingeniero lo pida.
         */
        _buildAiConceptsBlock: function(concepts, index) {
            if (!concepts || !concepts.length) { return ''; }

            const chips = concepts.map((c) => {
                const clean = this._esc(String(c).replace(/^IA:\s*/u, '').trim());
                return `<span class="slm-chip slm-chip--ai-concept"> ${clean}</span>`;
            }).join('');

            const sectionId = `slm-ai-concepts-${index}`;

            return `
            <div class="slm-mini-accordion" style="margin-top:12px;">
                <div class="slm-mini-accordion-item">
                    <button type="button"
                            class="slm-mini-accordion-header slm-accordion-toggle"
                            data-target="${sectionId}"
                            aria-expanded="false">
                        <span> Conceptos sugeridos por la IA</span>
                        <span class="slm-arrow" aria-hidden="true">▼</span>
                    </button>
                    <div class="slm-mini-accordion-content hidden" id="${sectionId}">
                        <p class="slm-ai-concepts-note">
                            La IA sugirió estos conceptos adicionales.
                        </p>
                        <div class="slm-chip-group">${chips}</div>
                    </div>
                </div>
            </div>`;
        },

        /**
         * 10. Construye la lista de títulos de ejemplos (abren modal).
         */
        _buildExamplesList: function(examples, errIndex, sectionId) {
            if (!examples.length) { return ''; }

            const items = examples.map((ex, exIndex) => {
                const title = this._esc(ex.title || `Ejemplo ${exIndex + 1}`);
                return `
                <button type="button"
                        class="slm-example-title-btn"
                        data-errindex="${errIndex}"
                        data-exindex="${exIndex}"
                        title="Ver ejemplo completo">
                    <span class="slm-example-icon"></span>
                    <span class="slm-example-label">${title}</span>
                    <span class="slm-example-open">Ver →</span>
                </button>`;
            }).join('');

            return `
            <div class="slm-mini-accordion" style="margin-top:12px;">
                <div class="slm-mini-accordion-item">
                    <button type="button"
                            class="slm-mini-accordion-header slm-accordion-toggle"
                            data-target="${sectionId}"
                            aria-expanded="false">
                        <span> Ver ejemplos (${examples.length})</span>
                        <span class="slm-arrow" aria-hidden="true">▼</span>
                    </button>
                    <div class="slm-mini-accordion-content hidden" id="${sectionId}">
                        <p class="slm-ai-concepts-note">Haz clic en un ejemplo para verlo completo.</p>
                        <div class="slm-examples-list">${items}</div>
                    </div>
                </div>
            </div>`;
        },

        /**
         * 11. Construye una sección colapsable genérica (mini-acordeón con chips).
         *     DESHABILITADO — solo se usa para recursos IA (comentado en _buildErrorHtml).
         *     TODO: habilitar cuando el ingeniero lo pida.
         */
        _buildNestedSection: function(sectionId, title, items, chipClass, emptyMsg) {
            const clean = items.map(i => String(i || '').trim()).filter(Boolean);
            const content = clean.length
                ? `<div class="slm-chip-group">${clean.map(i =>
                    `<span class="slm-chip ${chipClass}">${this._esc(i)}</span>`
                  ).join('')}</div>`
                : `<p class="slm-empty-inline">${this._esc(emptyMsg)}</p>`;

            return `
            <div class="slm-mini-accordion-item">
                <button type="button"
                        class="slm-mini-accordion-header slm-accordion-toggle"
                        data-target="${sectionId}"
                        aria-expanded="false">
                    <span>${this._esc(title)}</span>
                    <span class="slm-arrow" aria-hidden="true">▼</span>
                </button>
                <div class="slm-mini-accordion-content hidden" id="${sectionId}">
                    ${content}
                </div>
            </div>`;
        },

        /**
         * 12. Alterna la visibilidad de un acordeón.
         */
        _toggleAccordion: function(button) {
            const targetId = button.getAttribute('data-target');
            const body     = document.getElementById(targetId);
            if (!body) { return; }
            const willOpen = body.classList.contains('hidden');
            body.classList.toggle('hidden', !willOpen);
            button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            const arrow = button.querySelector('.slm-arrow');
            if (arrow) { arrow.classList.toggle('slm-arrow--open', willOpen); }
        },

        _updateRequestCounter: function() {
            const el = document.querySelector(SEL.REQUESTS_USED);
            if (el) { el.textContent = panelConfig.solicitudesUsadas; }
        },

        _showAnalysisMessage: function(msg) {
            const el = document.querySelector(SEL.ANALYSIS_MESSAGE);
            if (el) { el.textContent = msg; }
        },

        _setLoading: function(show) {
            const el = document.querySelector(SEL.LOADING);
            if (el) { el.classList.toggle('hidden', !show); }
        },

        _badgeClass: function(badge) {
            if (badge === 'high')   { return 'slm-badge--high'; }
            if (badge === 'medium') { return 'slm-badge--medium'; }
            return 'slm-badge--low';
        },

        _getCmidFromUrl: function() {
            return new URLSearchParams(window.location.search).get('id');
        },

        _esc: function(value) {
            return String(value)
                .replace(/&/g,  '&amp;')
                .replace(/</g,  '&lt;')
                .replace(/>/g,  '&gt;')
                .replace(/"/g,  '&quot;')
                .replace(/'/g,  '&#39;');
        },
    };
});
