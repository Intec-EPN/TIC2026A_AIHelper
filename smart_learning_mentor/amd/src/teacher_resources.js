/**
 * Modulo AMD para gestionar la asociacion de recursos del curso con conceptos.
 *
 * Descripcion del flujo:
 *   1. lib.php carga este modulo cuando view=catalog&subview=resources.
 *   2. init() inicializa el sidebar colapsable y los botones "Asociar".
 *   3. Al hacer clic en "Asociar", se abre el modal con los checkboxes de conceptos.
 *   4. Los checkboxes preseleccionados corresponden a conceptos ya asociados.
 *   5. Al confirmar, se llama al endpoint AJAX manage_resource.
 *   6. La UI se actualiza con los nuevos chips de conceptos y el contador.
 *
 */

define(['core/ajax', 'core/notification', 'theme_boost/bootstrap/modal'],
function(Ajax, Notification, Modal) {

    /** @type {Object|null} Recurso actualmente seleccionado en el modal */
    var currentResource = null;

    /** @type {Object|null} Instancia del modal Bootstrap */
    var modalInstance = null;

    return {

        /**
         * Inicializa todos los eventos de la pagina de recursos.
         */
        init: function() {
            var modalEl = document.getElementById('slm-association-modal');
            if (!modalEl) {
                return;
            }

            modalInstance = new Modal(modalEl);

            var self = this;

            // 1. Sidebar colapsable (temas y conceptos).
            this._initSidebar();

            // 2. Botones "Asociar" de cada recurso.
            document.querySelectorAll('.slm-assign-resource-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    self._openAssociationModal(btn);
                });
            });

            // 3. Checkboxes del modal: actualizar resumen al cambiar.
            modalEl.querySelectorAll('.slm-concept-checkbox').forEach(function(chk) {
                chk.addEventListener('change', function() {
                    self._renderSelectedConcepts();
                });
            });

            // 4. Buscador de conceptos en el modal.
            var searchInput = document.getElementById('slm-concept-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    self._filterConcepts(searchInput.value);
                });
            }

            // 5. Boton "Guardar asociacion".
            var saveBtn = document.getElementById('slm-associate-save-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    self._saveAssociation();
                });
            }
        },

        /**
         * Inicializa el sidebar colapsable de temas y conceptos.
         */
        _initSidebar: function() {
            var sidebar = document.getElementById('slm-resources-sidebar');
            var toggleBtn = document.getElementById('slm-resources-sidebar-toggle');

            if (sidebar && toggleBtn) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.toggle('collapsed');
                });
            }
        },

        /**
         * Abre el modal de asociacion con los datos del recurso seleccionado.
         *
         * @param {HTMLElement} btn Boton "Asociar" del recurso
         */
        _openAssociationModal: function(btn) {
            var modalEl = document.getElementById('slm-association-modal');
            if (!modalEl) {
                return;
            }

            // Leer datos del boton.
            currentResource = {
                id:           btn.dataset.resourceId    || '',
                cmid:         parseInt(btn.dataset.cmid || '0', 10),
                courseid:     parseInt(btn.dataset.courseid || '0', 10),
                titulo:       btn.dataset.resourceTitle || '',
                typelabel:    btn.dataset.resourceType  || '',
                categorylabel: btn.dataset.resourceCategory || '',
                row:          btn.closest('.slm-resource-row'),
                conceptids:   (btn.dataset.associatedConceptids || '')
                                  .split(',')
                                  .filter(Boolean)
                                  .map(Number),
            };

            // Actualizar cabecera del modal.
            var titleEl    = document.getElementById('slm-modal-resource-title');
            var typeEl     = document.getElementById('slm-modal-resource-type');
            if (titleEl) { titleEl.textContent = currentResource.titulo; }
            if (typeEl)  { typeEl.textContent  = currentResource.typelabel; }

            // Preseleccionar conceptos ya asociados.
            modalEl.querySelectorAll('.slm-concept-checkbox').forEach(function(chk) {
                var id = parseInt(chk.value, 10);
                chk.checked = currentResource.conceptids.indexOf(id) !== -1;
            });

            this._renderSelectedConcepts();
            modalInstance.show();

            // Limpiar busqueda al abrir.
            var searchInput = document.getElementById('slm-concept-search');
            if (searchInput) {
                searchInput.value = '';
                this._filterConcepts('');
            }
        },

        /**
         * Filtra los conceptos del modal por nombre.
         *
         * @param {string} term Termino de busqueda
         */
        _filterConcepts: function(term) {
            var lower = (term || '').toLowerCase().trim();
            var modalEl = document.getElementById('slm-association-modal');
            if (!modalEl) {
                return;
            }

            modalEl.querySelectorAll('.slm-modal-topic-group').forEach(function(group) {
                var hasVisible = false;
                group.querySelectorAll('.slm-concept-pill').forEach(function(pill) {
                    var chk  = pill.querySelector('.slm-concept-checkbox');
                    var name = (chk ? chk.dataset.conceptName || '' : '').toLowerCase();
                    var show = !lower || name.indexOf(lower) !== -1;
                    pill.style.display = show ? '' : 'none';
                    if (show) {
                        hasVisible = true;
                    }
                });
                // Ocultar grupo completo si ninguno coincide.
                group.style.display = hasVisible ? '' : 'none';
            });
        },

        /**
         * Actualiza el resumen de conceptos seleccionados en el panel derecho del modal.
         */
        _renderSelectedConcepts: function() {
            var selectedEl = document.getElementById('slm-selected-concepts');
            if (!selectedEl) {
                return;
            }

            var selected = this._getSelectedConcepts();

            if (!selected.length) {
                selectedEl.innerHTML = '<span class="text-muted small">Ninguno seleccionado</span>';
                return;
            }

            selectedEl.innerHTML = selected.map(function(item) {
                return '<span class="badge bg-primary me-1 mb-1">' + item.name + '</span>';
            }).join('');
        },

        /**
         * Obtiene los conceptos actualmente marcados en el modal.
         *
         * @return {Array} Lista de {id, name}
         */
        _getSelectedConcepts: function() {
            var modalEl = document.getElementById('slm-association-modal');
            if (!modalEl) {
                return [];
            }

            var items = [];
            modalEl.querySelectorAll('.slm-concept-checkbox:checked').forEach(function(chk) {
                items.push({
                    id:   parseInt(chk.value, 10),
                    name: chk.dataset.conceptName || chk.value,
                });
            });
            return items;
        },

        /**
         * Guarda la asociacion del recurso con los conceptos seleccionados via AJAX.
         */
        _saveAssociation: function() {
            if (!currentResource) {
                return;
            }

            var selected   = this._getSelectedConcepts();
            var conceptids = selected.map(function(c) { return c.id; });

            var self = this;

            Ajax.call([{
                methodname: 'local_smart_learning_mentor_manage_resource',
                args: {
                    courseid:   currentResource.courseid,
                    cmid:       currentResource.cmid,
                    titulo:     currentResource.titulo,
                    conceptids: JSON.stringify(conceptids),
                },
            }])[0]
            .then(function(response) {
                if (!response.success) {
                    Notification.alert('', response.message);
                    return;
                }

                // Parsear conceptos retornados por el servidor.
                var concepts = [];
                try {
                    concepts = JSON.parse(response.concepts || '[]');
                } catch (e) {
                    concepts = [];
                }

                // Buscar la fila por cmid (mas robusto que usar currentResource.row
                // que puede quedar obsoleto si el DOM cambia).
                var cmid = response.cmid || (currentResource ? currentResource.cmid : 0);
                var row = currentResource ? currentResource.row : null;
                if (!row && cmid) {
                    row = document.querySelector('.slm-resource-row[data-cmid="' + cmid + '"]');
                }

                self._updateResourceRow(row, concepts, cmid);
                modalInstance.hide();

                Notification.addNotification({
                    message: response.message,
                    type:    'success',
                });
            })
            .catch(Notification.exception);
        },

        /**
         * Actualiza visualmente los chips de conceptos y el contador del recurso.
         *
         * @param {HTMLElement} row      Fila del recurso en la tabla
         * @param {Array}       concepts Lista de {id, nombre} ahora asociados
         * @param {number}      cmid     ID del modulo (para actualizar el boton)
         */
        _updateResourceRow: function(row, concepts, cmid) {
            if (!row) {
                return;
            }

            // 1. Actualizar chips de conceptos.
            var chipsContainer = row.querySelector('.slm-resource-concepts');
            if (chipsContainer) {
                if (concepts.length) {
                    chipsContainer.innerHTML = concepts.map(function(c) {
                        return '<span class="slm-resource-concept-chip">' + c.nombre + '</span>';
                    }).join(' ');
                } else {
                    chipsContainer.innerHTML = '';
                }
            }

            // 2. Actualizar o crear el badge de conteo.
            var actionsDiv = row.querySelector('.d-flex.align-items-center.gap-2.flex-shrink-0');
            var badge = row.querySelector('.slm-resource-count-badge');

            if (concepts.length) {
                if (badge) {
                    badge.textContent = concepts.length + ' concepto' + (concepts.length !== 1 ? 's' : '');
                    badge.style.display = '';
                } else if (actionsDiv) {
                    // Crear el badge si no existia (recurso sin conceptos previos).
                    var newBadge = document.createElement('span');
                    newBadge.className = 'badge bg-info slm-resource-count-badge';
                    newBadge.textContent = concepts.length + ' concepto' + (concepts.length !== 1 ? 's' : '');
                    var btn2 = actionsDiv.querySelector('.slm-assign-resource-btn');
                    if (btn2) {
                        actionsDiv.insertBefore(newBadge, btn2);
                    } else {
                        actionsDiv.appendChild(newBadge);
                    }
                }
            } else if (badge) {
                badge.style.display = 'none';
            }

            // 3. Actualizar data-associated-conceptids del boton para futuras aperturas del modal.
            var btn = row.querySelector('.slm-assign-resource-btn');
            if (btn) {
                btn.dataset.associatedConceptids = concepts.map(function(c) { return c.id; }).join(',');
            }
        },
    };
});