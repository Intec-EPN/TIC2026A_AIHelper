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
 * Acceso a BD para temas y conceptos del catalogo pedagogico del profesor.
 *
 * Descripcion del flujo:
 *   1. topic_application necesita leer o guardar temas/conceptos.
 *   2. Llama a los metodos de esta clase.
 *   3. topic_storage es el unico lugar donde se usa $DB para estas tablas.
 *
 *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\storage;

defined('MOODLE_INTERNAL') || die();

class topic_storage {

    // =========================================================================
    // TEMAS
    // =========================================================================

    /**
     * 1. Obtiene todos los temas del curso con sus conceptos.
     *    Usado por la application para construir el catalogo completo.
     *
     * @param int $courseid
     * @return array Lista de temas con campo 'concepts' anidado
     */
    public static function get_course_topics(int $courseid): array {
        global $DB;

        try {
            $themes = $DB->get_records(
                'local_slm_temas',
                ['courseid' => $courseid],
                'nombre ASC'
            );

            if (empty($themes)) {
                return [];
            }

            return self::build_topics_from_db($themes);

        } catch (\dml_exception $e) {
            debugging('topic_storage: tabla local_slm_temas no disponible.', DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * 2. Obtiene todos los temas del curso SIN conceptos (para listados).
     *
     * @param int $courseid
     * @return array
     */
    public static function get_themes_only(int $courseid): array {
        global $DB;

        $records = $DB->get_records('local_slm_temas', ['courseid' => $courseid], 'nombre ASC');

        return array_values(array_map(fn($r) => [
            'id'          => (int)$r->id,
            'nombre'      => $r->nombre,
            'descripcion' => $r->descripcion ?? '',
        ], $records));
    }

    /**
     * 3. Crea un nuevo tema para el curso.
     *
     * @param int    $courseid
     * @param string $nombre
     * @param string $descripcion
     * @return int   ID del tema creado
     */
    public static function create_theme(int $courseid, string $nombre, string $descripcion = ''): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('local_slm_temas', (object)[
            'courseid'     => $courseid,
            'nombre'       => trim($nombre),
            'descripcion'  => trim($descripcion),
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * 4. Actualiza el nombre/descripcion de un tema existente.
     *
     * @param int    $themeid
     * @param string $nombre
     * @param string $descripcion
     * @return bool
     */
    public static function update_theme(int $themeid, string $nombre, string $descripcion = ''): bool {
        global $DB;

        return $DB->update_record('local_slm_temas', (object)[
            'id'           => $themeid,
            'nombre'       => trim($nombre),
            'descripcion'  => trim($descripcion),
            'timemodified' => time(),
        ]);
    }

    /**
     * 5. Elimina un tema y todos sus conceptos en cascada.
     *
     * @param int $themeid
     * @param int $courseid Verificacion de seguridad
     * @return bool
     */
    public static function delete_theme(int $themeid, int $courseid): bool {
        global $DB;

        // Verificar que el tema pertenece al curso.
        if (!$DB->record_exists('local_slm_temas', ['id' => $themeid, 'courseid' => $courseid])) {
            return false;
        }

        // 5a. Obtener conceptos del tema.
        $conceptids = $DB->get_fieldset_select(
            'local_slm_conceptos', 'id', 'temaid = :temaid', ['temaid' => $themeid]
        );

        // 5b. Eliminar relaciones error_concepto de esos conceptos.
        if (!empty($conceptids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($conceptids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_slm_error_concepto', "conceptoid $insql", $inparams);
            // Eliminar conceptos.
            $DB->delete_records_select('local_slm_conceptos', "id $insql", $inparams);
        }

        // 5c. Eliminar el tema.
        $DB->delete_records('local_slm_temas', ['id' => $themeid]);

        return true;
    }

    /**
     * 6. Verifica si un tema pertenece al curso dado.
     *
     * @param int $themeid
     * @param int $courseid
     * @return bool
     */
    public static function theme_belongs_to_course(int $themeid, int $courseid): bool {
        global $DB;
        return $DB->record_exists('local_slm_temas', ['id' => $themeid, 'courseid' => $courseid]);
    }

    // =========================================================================
    // CONCEPTOS
    // =========================================================================

    /**
     * 7. Obtiene todos los conceptos de un tema.
     *
     * @param int $themeid
     * @return array
     */
    public static function get_concepts_by_theme(int $themeid): array {
        global $DB;

        $records = $DB->get_records('local_slm_conceptos', ['temaid' => $themeid], 'nombre ASC');

        return array_values(array_map(fn($r) => [
            'id'          => (int)$r->id,
            'temaid'      => (int)$r->temaid,
            'nombre'      => $r->nombre,
            'descripcion' => $r->descripcion ?? '',
        ], $records));
    }

    /**
     * 8. Crea un nuevo concepto dentro de un tema.
     *
     * @param int    $themeid
     * @param string $nombre
     * @param string $descripcion
     * @return int   ID del concepto creado
     */
    public static function create_concept(int $themeid, string $nombre, string $descripcion = ''): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('local_slm_conceptos', (object)[
            'temaid'       => $themeid,
            'nombre'       => trim($nombre),
            'descripcion'  => trim($descripcion),
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * 9. Actualiza un concepto existente.
     *
     * @param int    $conceptid
     * @param string $nombre
     * @param string $descripcion
     * @return bool
     */
    public static function update_concept(int $conceptid, string $nombre, string $descripcion = ''): bool {
        global $DB;

        return $DB->update_record('local_slm_conceptos', (object)[
            'id'           => $conceptid,
            'nombre'       => trim($nombre),
            'descripcion'  => trim($descripcion),
            'timemodified' => time(),
        ]);
    }

    /**
     * 10. Elimina un concepto y sus relaciones en error_concepto.
     *
     * @param int $conceptid
     * @param int $courseid Verificacion de seguridad (el concepto debe pertenecer al curso)
     * @return bool
     */
    public static function delete_concept(int $conceptid, int $courseid): bool {
        global $DB;

        // Verificar que el concepto pertenece a un tema del curso.
        $sql = "SELECT c.id FROM {local_slm_conceptos} c
                  JOIN {local_slm_temas} t ON t.id = c.temaid
                 WHERE c.id = :cid AND t.courseid = :courseid";

        if (!$DB->record_exists_sql($sql, ['cid' => $conceptid, 'courseid' => $courseid])) {
            return false;
        }

        // Eliminar relaciones.
        $DB->delete_records('local_slm_error_concepto', ['conceptoid' => $conceptid]);

        // Eliminar concepto.
        $DB->delete_records('local_slm_conceptos', ['id' => $conceptid]);

        return true;
    }

    // =========================================================================
    // CONCEPTOS IA (sugeridos, para el panel lateral del profesor)
    // =========================================================================

    /**
     * 11. Obtiene los conceptos propuestos por la IA para el curso que aun no fueron promovidos.
     *     Agrupados y deduplicados por nombre para mostrar los mas frecuentes.
     *
     * @param int $courseid
     * @param int $limit    Maximo de conceptos a retornar
     * @return array [['nombre' => string, 'count' => int, 'ids' => [int, ...]], ...]
     */
    public static function get_ai_concepts_for_course(int $courseid, int $limit = 30): array {
        global $DB;

        $sql = "SELECT ic.nombre, COUNT(ic.id) AS cnt,
                       GROUP_CONCAT(ic.id ORDER BY ic.id ASC SEPARATOR ',') AS ids
                  FROM {local_slm_ia_concepto} ic
                  JOIN {local_slm_error_detectado} ed ON ed.id = ic.errorid
                  JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                  JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                 WHERE sa.courseid = :courseid
                   AND ic.promovido = 0
              GROUP BY ic.nombre
              ORDER BY cnt DESC";

        $records = $DB->get_records_sql($sql, ['courseid' => $courseid], 0, $limit);

        return array_values(array_map(fn($r) => [
            'nombre' => $r->nombre,
            'count'  => (int)$r->cnt,
            'ids'    => array_map('intval', explode(',', $r->ids)),
        ], $records));
    }

    /**
     * 12. Marca como promovido un concepto IA y lo vincula al nuevo concepto del catalogo.
     *
     * @param array $iaconceptids IDs en local_slm_ia_concepto a marcar como promovidos
     * @param int   $newconceptid ID del concepto creado en local_slm_conceptos
     * @return void
     */
    public static function promote_ai_concept(array $iaconceptids, int $newconceptid): void {
        global $DB;

        if (empty($iaconceptids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($iaconceptids, SQL_PARAMS_NAMED);
        $params['conceptoid'] = $newconceptid;

        $DB->execute(
            "UPDATE {local_slm_ia_concepto}
                SET promovido = 1, conceptoid_promovido = :conceptoid
              WHERE id $insql",
            $params
        );
    }

    /**
     * 14. Construye el array de temas desde registros de BD.
     *
     * @param array $themes
     * @return array
     */
    private static function build_topics_from_db(array $themes): array {
        global $DB;

        $result = [];
        foreach ($themes as $theme) {
            $concepts = $DB->get_records('local_slm_conceptos', ['temaid' => $theme->id], 'nombre ASC');

            $conceptlist = array_values(array_map(fn($c) => [
                'id'          => (int)$c->id,
                'name'        => $c->nombre,
                'description' => $c->descripcion ?? '',
            ], $concepts));

            $result[] = [
                'id'          => (int)$theme->id,
                'name'        => $theme->nombre,
                'description' => $theme->descripcion ?? '',
                'concepts'    => $conceptlist,
            ];
        }

        return $result;
    }
}
