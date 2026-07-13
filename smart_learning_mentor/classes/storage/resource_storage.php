<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version metadata for the repository_pluginname plugin.
 *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\storage;
 
defined('MOODLE_INTERNAL') || die();
 
class resource_storage {
 
    // =========================================================================
    // ARBOL DE MODULOS DEL CURSO
    // =========================================================================
 
    /**
     * 1. Construye el arbol completo de secciones → subsecciones → modulos.
     *    Respeta el orden real de Moodle usando el campo sequence de cada seccion.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_modules_tree(int $courseid): array {
        global $DB;
 
        // 1a. Todas las secciones del curso.
        $allsections = $DB->get_records(
            'course_sections',
            ['course' => $courseid],
            'section ASC',
            'id, section, name, component, itemid, sequence'
        );
 
        if (empty($allsections)) {
            return [];
        }
 
        // 1b. Todos los modulos del curso indexados por cmid.
        $sql = "SELECT cm.id AS cmid, cm.section AS sectionid, cm.instance, m.name AS modname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid AND cm.deletioninprogress = 0";
 
        $modulesbycmid = [];
        foreach ($DB->get_records_sql($sql, ['courseid' => $courseid]) as $mod) {
            $modulesbycmid[(int)$mod->cmid] = $mod;
        }
 
        // 1c. Conceptos asociados y recursos guardados.
        $associationsmap = self::get_concept_associations_by_cmid($courseid);
        $savedmap        = self::get_saved_resources_map($courseid);
 
        // 1d. Separar secciones principales de delegadas.
        //     Delegadas: component='mod_subsection', itemid = instance del modulo subsection.
        $mainsections = [];   // [sectionid => record]  solo las principales (component IS NULL)
        $delegatedmap = [];   // [instance => record]   itemid = instance del cm subsection
 
        foreach ($allsections as $sec) {
            $component = isset($sec->component) ? (string)$sec->component : '';
            $itemid    = isset($sec->itemid)    ? (int)$sec->itemid       : 0;
 
            if ($component === 'mod_subsection' && $itemid > 0) {
                // Indexar por itemid (= instance del modulo subsection).
                $delegatedmap[$itemid] = $sec;
            } else {
                $mainsections[(int)$sec->id] = $sec;
            }
        }
 
        // 1e. Construir el arbol respetando el orden del campo sequence.
        $tree = [];
 
        foreach ($mainsections as $section) {
            // La seccion 0 sin nombre solo si tiene modulos visibles.
            $sectionname = !empty($section->name)
                ? $section->name
                : (((int)$section->section === 0) ? 'General' : get_string('section') . ' ' . $section->section);
 
            // Leer los cmids en el orden que define Moodle (campo sequence).
            $cmids = self::parse_sequence($section->sequence ?? '');
 
            if (empty($cmids)) {
                continue;
            }
 
            $directitems = [];
            $subsections = [];
 
            foreach ($cmids as $cmid) {
                $mod = $modulesbycmid[$cmid] ?? null;
                if (!$mod) {
                    continue;
                }
 
                if ($mod->modname === 'subsection') {
                    // Este cm es una subseccion: buscar su seccion delegada por instance.
                    $instance = (int)$mod->instance;
 
                    if (isset($delegatedmap[$instance])) {
                        $delsec  = $delegatedmap[$instance];
                        $delname = !empty($delsec->name) ? $delsec->name : 'Subseccion';
 
                        // Modulos de la seccion delegada, en orden del sequence.
                        $delcmids = self::parse_sequence($delsec->sequence ?? '');
                        $subitems = [];
 
                        foreach ($delcmids as $delcmid) {
                            $delmod = $modulesbycmid[$delcmid] ?? null;
                            if (!$delmod || in_array($delmod->modname, ['subsection'])) {  // build_item filtra ademas por categoria content
                                continue;
                            }
                            $item = self::build_item($delmod, $associationsmap, $savedmap, $courseid);
                            if ($item) {
                                $subitems[] = $item;
                            }
                        }
 
                        $subsections[] = [
                            'subsectionid'   => $cmid,
                            'subsectionname' => $delname,
                            'items'          => $subitems,
                            'item_count'     => count($subitems),
                            'has_items'      => !empty($subitems),
                        ];
                    }
                } else if (!in_array($mod->modname, ['subsection'])) {
                    // Item directo en la seccion principal (build_item filtra por categoria content).
                    $item = self::build_item($mod, $associationsmap, $savedmap, $courseid);
                    if ($item) {
                        $directitems[] = $item;
                    }
                }
            }
 
            // Solo incluir la seccion si tiene contenido.
            if (empty($directitems) && empty($subsections)) {
                continue;
            }
 
            $totalitems = count($directitems);
            foreach ($subsections as $sub) {
                $totalitems += $sub['item_count'];
            }
 
            $tree[] = [
                'sectionid'       => (int)$section->id,
                'sectionnum'      => (int)$section->section,
                'sectionname'     => $sectionname,
                'has_direct'      => !empty($directitems),
                'direct_items'    => $directitems,
                'has_subsections' => !empty($subsections),
                'subsections'     => $subsections,
                'item_count'      => $totalitems,
            ];
        }
 
        return $tree;
    }
 
    // =========================================================================
    // RECURSOS EN BD
    // =========================================================================
 
    /**
     * 2. Crea o recupera un recurso del tipo 'curso'.
     *
     * @param int    $courseid
     * @param int    $cmid
     * @param string $titulo
     * @return int
     */
    public static function upsert_course_resource(int $courseid, int $cmid, string $titulo): int {
        global $DB;
 
        $existing = $DB->get_record('local_slm_recurso', ['courseid' => $courseid, 'cmid' => $cmid, 'tipo' => 'curso']);
        if ($existing) {
            return (int)$existing->id;
        }
 
        $now = time();
        return (int)$DB->insert_record('local_slm_recurso', (object)[
            'courseid'     => $courseid,
            'titulo'       => $titulo,
            'tipo'         => 'curso',
            'cmid'         => $cmid,
            'url'          => '',
            'descripcion'  => '',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }
 
    // =========================================================================
    // ASOCIACIONES RECURSO <-> CONCEPTO (N:M)
    // =========================================================================
 
    /**
     * 3. Sincroniza asociaciones de un recurso con conceptos.
     *
     * @param int   $recursoid
     * @param array $conceptids
     */
    public static function sync_resource_concepts(int $recursoid, array $conceptids): void {
        global $DB;
 
        // Estrategia: borrar todas las asociaciones actuales y reinsertar las nuevas.
        // Es mas simple y fiable que un diff, y evita problemas con array_diff y claves.
        $DB->delete_records('local_slm_recurso_concepto', ['recursoid' => $recursoid]);
 
        $now = time();
 
        // Deduplicar y filtrar IDs validos antes de insertar.
        $unique = array_unique(array_filter(array_map('intval', $conceptids), fn($id) => $id > 0));
 
        foreach ($unique as $cid) {
            $DB->insert_record('local_slm_recurso_concepto', (object)[
                'recursoid'   => $recursoid,
                'conceptoid'  => (int)$cid,
                'timecreated' => $now,
            ]);
        }
    }
 
    /**
     * 4. Mapa cmid => lista de conceptos asociados.
     *
     * @param int $courseid
     * @return array [cmid => [['id' => int, 'nombre' => string], ...]]
     */
    public static function get_concept_associations_by_cmid(int $courseid): array {
        global $DB;
 
        // IMPORTANTE: get_records_sql indexa por la primera columna.
        // Si la primera columna no es unica (como cmid que se repite por concepto),
        // Moodle solo guarda el ultimo registro por clave.
        // Solucion: usar rc.id como primera columna (es unico) y luego agrupar manualmente.
        $sql = "SELECT rc.id AS rcid, r.cmid, c.id AS conceptoid, c.nombre
                  FROM {local_slm_recurso} r
                  JOIN {local_slm_recurso_concepto} rc ON rc.recursoid = r.id
                  JOIN {local_slm_conceptos} c ON c.id = rc.conceptoid
                 WHERE r.courseid = :courseid AND r.tipo = 'curso' AND r.cmid IS NOT NULL
              ORDER BY r.cmid ASC, c.nombre ASC";
 
        $map = [];
        foreach ($DB->get_records_sql($sql, ['courseid' => $courseid]) as $r) {
            $cmid = (int)$r->cmid;
            if (!isset($map[$cmid])) {
                $map[$cmid] = [];
            }
            $map[$cmid][] = ['id' => (int)$r->conceptoid, 'nombre' => $r->nombre];
        }
        return $map;
    }
 
    /**
     * 5. Conceptos de un recurso especifico.
     *
     * @param int $recursoid
     * @return array
     */
    public static function get_concepts_by_resource(int $recursoid): array {
        global $DB;
 
        // rc.id como primera columna para que get_records_sql no sobreescriba registros.
        $sql = "SELECT rc.id AS rcid, c.id AS conceptoid, c.nombre
                  FROM {local_slm_recurso_concepto} rc
                  JOIN {local_slm_conceptos} c ON c.id = rc.conceptoid
                 WHERE rc.recursoid = :rid ORDER BY c.nombre ASC";
 
        return array_values(array_map(
            fn($r) => ['id' => (int)$r->conceptoid, 'nombre' => $r->nombre],
            $DB->get_records_sql($sql, ['rid' => $recursoid])
        ));
    }
 
    // =========================================================================
    // UTILIDADES PRIVADAS
    // =========================================================================
 
    /**
     * 6. Parsea el campo sequence de course_sections en un array de cmids enteros.
     *    El sequence es una cadena como "2,19,10" o puede estar vacio.
     *
     * @param string $sequence
     * @return array [cmid, cmid, ...]
     */
    private static function parse_sequence(string $sequence): array {
        if (empty(trim($sequence))) {
            return [];
        }
        return array_values(array_filter(
            array_map('intval', explode(',', $sequence)),
            fn($id) => $id > 0
        ));
    }
 
    /**
     * 7. Mapa cmid => recursoid de recursos ya guardados en BD.
     *
     * @param int $courseid
     * @return array [cmid => recursoid]
     */
    private static function get_saved_resources_map(int $courseid): array {
        global $DB;
 
        $map = [];
        foreach ($DB->get_records('local_slm_recurso', ['courseid' => $courseid, 'tipo' => 'curso'], '', 'cmid, id') as $r) {
            if ($r->cmid) {
                $map[(int)$r->cmid] = (int)$r->id;
            }
        }
        return $map;
    }
 
    /**
     * 8. Construye el array de un item de modulo para Mustache.
     *
     * @param object $mod
     * @param array  $associationsmap
     * @param array  $savedmap
     * @param int    $courseid
     * @return array|null
     */
    private static function build_item(object $mod, array $associationsmap, array $savedmap, int $courseid): ?array {
        // Filtro: solo mostrar modulos de la categoria "Recursos" de Moodle.
        // Confirmado en el selector de actividades (pestana "Recursos"):
        //   Archivo, Area de texto y medios, Carpeta, Glosario,
        //   Libro, Pagina, Paquete IMS, Paquete SCORM, URL.
        // Excluye: vpl, forum, assign, quiz, feedback, wiki, data, choice, etc.
        // Para mostrar otros tipos en el futuro, agregar aqui.
        static $contentmodules = [
            'resource',  // Archivo
            'label',     // Area de texto y medios
            'folder',    // Carpeta
            'glossary',  // Glosario
            'book',      // Libro
            'page',      // Pagina
            'imscp',     // Paquete de contenido IMS
            'scorm',     // Paquete SCORM
            'url',       // URL
        ];
 
        if (!in_array($mod->modname, $contentmodules)) {
            return null;
        }
 
        $cmid       = (int)$mod->cmid;
        $title      = self::get_module_title($mod->modname, (int)$mod->instance);
        $concepts   = $associationsmap[$cmid] ?? [];
        $resourceid = $savedmap[$cmid] ?? 0;
 
        return [
            'id'                  => $resourceid,
            'cmid'                => $cmid,
            'courseid'            => $courseid,
            'modname'             => $mod->modname,
            'titulo'              => $title,
            'typelabel'           => self::get_module_type_label($mod->modname),
            'icon'                => self::get_module_icon($mod->modname),
            'associated_concepts' => $concepts,
            'concept_count'       => count($concepts),
            'conceptids_csv'      => implode(',', array_column($concepts, 'id')),
        ];
    }
 
    /**
     * 9. Titulo del modulo desde su tabla nativa.
     *
     * @param string $modname
     * @param int    $instance
     * @return string
     */
    private static function get_module_title(string $modname, int $instance): string {
        global $DB;
 
        // label no tiene campo name, solo intro (contenido HTML).
        // Se extrae un fragmento del texto plano como titulo.
        if ($modname === 'label') {
            try {
                $intro = $DB->get_field('label', 'intro', ['id' => $instance]);
                if ($intro !== false && $intro !== null && trim($intro) !== '') {
                    $text = trim(strip_tags((string)$intro));
                    $text = (string)preg_replace('/\s+/', ' ', $text);
                    if (\core_text::strlen($text) > 60) {
                        $text = \core_text::substr($text, 0, 57) . '...';
                    }
                    return $text ?: 'Area de texto';
                }
            } catch (\Exception $e) {
                // Fallback.
            }
            return 'Area de texto';
        }
 
        static $tableswithname = [
            'assign', 'book', 'chat', 'choice', 'data', 'feedback', 'folder',
            'forum', 'glossary', 'h5pactivity', 'imscp', 'lesson', 'lti',
            'page', 'quiz', 'resource', 'scorm', 'survey', 'url', 'vpl', 'wiki', 'workshop',
        ];
 
        if (in_array($modname, $tableswithname)) {
            try {
                $title = $DB->get_field($modname, 'name', ['id' => $instance]);
                if ($title !== false && $title !== null) {
                    return (string)$title;
                }
            } catch (\Exception $e) {
                // Tabla no existe en esta instalacion.
            }
        }
 
        return ucfirst($modname) . ' #' . $instance;
    }
 
    /**
     * 10. Etiqueta legible del tipo de modulo.
     *
     * @param string $modname
     * @return string
     */
    private static function get_module_type_label(string $modname): string {
        $labels = [
            'assign'      => 'Tarea',
            'book'        => 'Libro',
            'chat'        => 'Chat',
            'choice'      => 'Consulta',
            'data'        => 'Base de datos',
            'feedback'    => 'Retroalimentación',
            'folder'      => 'Carpeta',
            'forum'       => 'Foro',
            'glossary'    => 'Glosario',
            'h5pactivity' => 'H5P',
            'imscp'       => 'IMS',
            'lesson'      => 'Lección',
            'lti'         => 'Herramienta externa',
            'page'        => 'Página',
            'quiz'        => 'Cuestionario',
            'resource'    => 'Archivo',
            'scorm'       => 'SCORM',
            'survey'      => 'Encuesta',
            'url'         => 'URL',
            'vpl'         => 'VPL',
            'wiki'        => 'Wiki',
            'workshop'    => 'Taller',
        ];
        return $labels[$modname] ?? ucfirst($modname);
    }
 
    /**
     * 11. Icono FontAwesome del tipo de modulo.
     *
     * @param string $modname
     * @return string HTML del icono
     */
    private static function get_module_icon(string $modname): string {
        $icons = [
            'assign'      => 'fa-pen-to-square',
            'book'        => 'fa-book-bookmark',
            'chat'        => 'fa-message',
            'choice'      => 'fa-list-check',
            'data'        => 'fa-database',
            'feedback'    => 'fa-star',
            'folder'      => 'fa-folder',
            'forum'       => 'fa-comments',
            'glossary'    => 'fa-book',
            'h5pactivity' => 'fa-play-circle',
            'lesson'      => 'fa-graduation-cap',
            'lti'         => 'fa-puzzle-piece',
            'page'        => 'fa-file-lines',
            'quiz'        => 'fa-circle-question',
            'resource'    => 'fa-file',
            'scorm'       => 'fa-play-circle',
            'survey'      => 'fa-clipboard-list',
            'url'         => 'fa-link',
            'vpl'         => 'fa-code',
            'wiki'        => 'fa-book-open',
            'workshop'    => 'fa-users',
        ];
        $icon = $icons[$modname] ?? 'fa-cube';
        return '<i class="fa ' . $icon . ' fa-fw text-secondary"></i>';
    }
}