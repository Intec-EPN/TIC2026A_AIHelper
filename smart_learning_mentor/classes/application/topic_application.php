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
 * Caso de uso: gestionar temas y conceptos del catalogo pedagogico.
 *
 * Descripcion del flujo para LECTURA:
 *   1. output/teacher/catalog.php llama a get_page_data().
 *   2. topic_application llama a topic_storage para obtener temas, conceptos y conceptos IA.
 *   3. Estructura los datos para Mustache (temas con conceptos anidados, panel IA).
 *
 * Descripcion del flujo para ESCRITURA (desde endpoints AJAX):
 *   1. external/* llama al metodo correspondiente.
 *   2. topic_application valida la logica del negocio.
 *   3. Llama a topic_storage para persistir en BD.
 *
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\application;

defined('MOODLE_INTERNAL') || die();

class topic_application {

    /**
     * 1. Obtiene todos los datos para la pagina de Temas y Conceptos.
     *
     * @param int $courseid
     * @return array Datos listos para Mustache
     */
    public static function get_page_data(int $courseid): array {
        $topics = \local_smart_learning_mentor\storage\topic_storage::get_course_topics($courseid);

        // Preparar temas para Mustache: calcular contadores.
        $topicsformustache = array_map(function($topic) {
            $conceptcount = count($topic['concepts'] ?? []);
            return array_merge($topic, [
                'concept_count' => $conceptcount,
                'concept_label' => $conceptcount === 1
                    ? get_string('concept_singular', 'local_smart_learning_mentor')
                    : get_string('concept_plural',   'local_smart_learning_mentor'),
                'concepts' => array_map(fn($c) => array_merge($c, [
                    'nombre'      => $c['name'],
                    'descripcion' => $c['description'],
                ]), $topic['concepts'] ?? []),
            ]);
        }, $topics);

        // Conceptos IA sugeridos (para el panel lateral).
        $aiconcepts = \local_smart_learning_mentor\storage\topic_storage::get_ai_concepts_for_course($courseid);

        return [
            'topics'             => $topicsformustache,
            'topics_json'        => json_encode($topics, JSON_UNESCAPED_UNICODE),
            'has_topics'         => !empty($topics),
            'ai_concepts'        => $aiconcepts,
            'has_ai_concepts'    => !empty($aiconcepts),
            'ai_concept_count'   => count($aiconcepts),
        ];
    }

    /**
     * 2. Crea un nuevo tema.
     *    Regla: el nombre no puede estar vacio.
     *
     * @param int    $courseid
     * @param string $nombre
     * @param string $descripcion
     * @return array ['success' => bool, 'id' => int, 'message' => string]
     */
    public static function create_theme(int $courseid, string $nombre, string $descripcion = ''): array {
        $nombre = trim($nombre);

        if (empty($nombre)) {
            return ['success' => false, 'id' => 0, 'message' => get_string('error_theme_name_empty', 'local_smart_learning_mentor')];
        }

        $id = \local_smart_learning_mentor\storage\topic_storage::create_theme($courseid, $nombre, $descripcion);

        return [
            'success' => $id > 0,
            'id'      => $id,
            'message' => $id > 0
                ? get_string('success_theme_created', 'local_smart_learning_mentor')
                : get_string('error_theme_create',    'local_smart_learning_mentor'),
        ];
    }

    /**
     * 3. Actualiza el nombre de un tema.
     *
     * @param int    $themeid
     * @param int    $courseid
     * @param string $nombre
     * @param string $descripcion
     * @return array
     */
    public static function update_theme(int $themeid, int $courseid, string $nombre, string $descripcion = ''): array {
        $nombre = trim($nombre);

        if (empty($nombre)) {
            return ['success' => false, 'message' => get_string('error_theme_name_empty', 'local_smart_learning_mentor')];
        }

        // Verificar que pertenece al curso.
        if (!\local_smart_learning_mentor\storage\topic_storage::theme_belongs_to_course($themeid, $courseid)) {
            return ['success' => false, 'message' => get_string('error_no_permission', 'local_smart_learning_mentor')];
        }

        $ok = \local_smart_learning_mentor\storage\topic_storage::update_theme($themeid, $nombre, $descripcion);

        return [
            'success' => $ok,
            'message' => $ok
                ? get_string('success_theme_updated', 'local_smart_learning_mentor')
                : get_string('error_theme_update',    'local_smart_learning_mentor'),
        ];
    }

    /**
     * 4. Elimina un tema y todos sus conceptos.
     *
     * @param int $themeid
     * @param int $courseid
     * @return array
     */
    public static function delete_theme(int $themeid, int $courseid): array {
        $ok = \local_smart_learning_mentor\storage\topic_storage::delete_theme($themeid, $courseid);

        return [
            'success' => $ok,
            'message' => $ok
                ? get_string('success_theme_deleted', 'local_smart_learning_mentor')
                : get_string('error_theme_delete',    'local_smart_learning_mentor'),
        ];
    }

    /**
     * 5. Crea un nuevo concepto dentro de un tema.
     *
     * @param int    $themeid
     * @param int    $courseid
     * @param string $nombre
     * @param string $descripcion
     * @return array
     */
    public static function create_concept(int $themeid, int $courseid, string $nombre, string $descripcion = ''): array {
        $nombre = trim($nombre);

        if (empty($nombre)) {
            return ['success' => false, 'id' => 0, 'message' => get_string('error_concept_name_empty', 'local_smart_learning_mentor')];
        }

        // Verificar que el tema pertenece al curso.
        if (!\local_smart_learning_mentor\storage\topic_storage::theme_belongs_to_course($themeid, $courseid)) {
            return ['success' => false, 'id' => 0, 'message' => get_string('error_no_permission', 'local_smart_learning_mentor')];
        }

        $id = \local_smart_learning_mentor\storage\topic_storage::create_concept($themeid, $nombre, $descripcion);

        return [
            'success' => $id > 0,
            'id'      => $id,
            'nombre'  => $nombre,
            'message' => $id > 0
                ? get_string('success_concept_created', 'local_smart_learning_mentor')
                : get_string('error_concept_create',    'local_smart_learning_mentor'),
        ];
    }

    /**
     * 6. Elimina un concepto.
     *
     * @param int $conceptid
     * @param int $courseid
     * @return array
     */
    public static function delete_concept(int $conceptid, int $courseid): array {
        $ok = \local_smart_learning_mentor\storage\topic_storage::delete_concept($conceptid, $courseid);

        return [
            'success' => $ok,
            'message' => $ok
                ? get_string('success_concept_deleted', 'local_smart_learning_mentor')
                : get_string('error_concept_delete',    'local_smart_learning_mentor'),
        ];
    }

    /**
     * 7. Promueve un concepto IA al catalogo del profesor.
     *    Crea el concepto en local_slm_conceptos y marca los registros IA como promovidos.
     *
     * @param int    $themeid       Tema destino
     * @param int    $courseid      Para validacion
     * @param string $nombre        Nombre del concepto (puede editarse antes de promover)
     * @param array  $iaconceptids  IDs en local_slm_ia_concepto a marcar como promovidos
     * @return array
     */
    public static function promote_ai_concept(
        int    $themeid,
        int    $courseid,
        string $nombre,
        array  $iaconceptids
    ): array {
        $nombre = trim($nombre);

        if (empty($nombre)) {
            return ['success' => false, 'id' => 0, 'message' => get_string('error_concept_name_empty', 'local_smart_learning_mentor')];
        }

        if (!\local_smart_learning_mentor\storage\topic_storage::theme_belongs_to_course($themeid, $courseid)) {
            return ['success' => false, 'id' => 0, 'message' => get_string('error_no_permission', 'local_smart_learning_mentor')];
        }

        // Crear el concepto real.
        $conceptid = \local_smart_learning_mentor\storage\topic_storage::create_concept($themeid, $nombre);

        if ($conceptid <= 0) {
            return ['success' => false, 'id' => 0, 'message' => get_string('error_concept_create', 'local_smart_learning_mentor')];
        }

        // Marcar los conceptos IA como promovidos.
        if (!empty($iaconceptids)) {
            \local_smart_learning_mentor\storage\topic_storage::promote_ai_concept($iaconceptids, $conceptid);
        }

        return [
            'success' => true,
            'id'      => $conceptid,
            'message' => get_string('success_concept_promoted', 'local_smart_learning_mentor'),
        ];
    }
}

