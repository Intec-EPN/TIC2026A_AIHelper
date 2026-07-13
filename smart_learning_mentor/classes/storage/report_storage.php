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
 * Consultas SQL para los reportes del profesor y del estudiante.
 *
 * Descripción del flujo:
 *   1. teacher_report_application necesita datos agregados por actividad VPL.
 *   2. Llama a report_storage::get_vpl_activities_report() con el courseid.
 *   3. report_storage consulta las tablas del plugin y retorna datos estructurados.
 *   4. La application procesa esos datos y los pasa al output.
 *   5. El output los adapta para Mustache.
 *
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



namespace local_smart_learning_mentor\storage;

defined('MOODLE_INTERNAL') || die();

class report_storage {

    // =========================================================================
    // VISTA: ACTIVIDADES (tabla resumen por VPL)
    // =========================================================================

    /**
     * 1. Lista de VPLs del curso con estadisticas agregadas de todos los estudiantes.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_vpl_activities_report(int $courseid): array {
        global $DB;

        $sql = "SELECT cm.id AS cmid, v.id AS vplid, v.name AS vplname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {vpl} v ON v.id = cm.instance
                 WHERE cm.course = :courseid AND m.name = 'vpl' AND cm.deletioninprogress = 0
              ORDER BY v.name ASC";

        $vpls = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        if (empty($vpls)) {
            return [];
        }

        $result = [];
        foreach ($vpls as $vpl) {
            $cmid  = (int)$vpl->cmid;
            $vplid = (int)$vpl->vplid;
            $url   = (new \moodle_url('/mod/vpl/view.php', ['id' => $cmid]))->out(false);

            $studentcount = (int)$DB->count_records_select(
                'local_slm_solicitud_ayuda',
                'vplid = :vplid AND courseid = :courseid',
                ['vplid' => $vplid, 'courseid' => $courseid]
            );

            $result[] = [
                'cmid'          => $cmid,
                'vplid'         => $vplid,
                'name'          => $vpl->vplname,
                'url'           => $url,
                'student_count' => $studentcount,
                'has_data'      => $studentcount > 0,
                'top_errors'    => self::get_top_errors($vplid, $courseid, 4),
                'top_concepts'  => self::get_top_teacher_concepts($vplid, $courseid, 4),
                'top_resources' => self::get_top_teacher_resources($vplid, $courseid, 4),
                'top_examples'  => self::get_top_ia_examples($vplid, $courseid, 4),
            ];
        }

        return $result;
    }

    // =========================================================================
    // VISTA: DETALLE DE ACTIVIDAD VPL
    // =========================================================================

    /**
     * 2. Info basica de un VPL.
     *
     * @param int $cmid
     * @param int $courseid
     * @return array|null
     */
    public static function get_vpl_info(int $cmid, int $courseid): ?array {
        global $DB;

        $sql = "SELECT cm.id AS cmid, v.id AS vplid, v.name AS vplname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {vpl} v ON v.id = cm.instance
                 WHERE cm.id = :cmid AND cm.course = :courseid AND m.name = 'vpl'";

        $rec = $DB->get_record_sql($sql, ['cmid' => $cmid, 'courseid' => $courseid]);
        if (!$rec) {
            return null;
        }

        return [
            'cmid'   => (int)$rec->cmid,
            'vplid'  => (int)$rec->vplid,
            'name'   => $rec->vplname,
            'url'    => (new \moodle_url('/mod/vpl/view.php', ['id' => $cmid]))->out(false),
        ];
    }

    /**
     * 3. Lista de estudiantes que solicitaron ayuda en una actividad VPL.
     *    Muestra el ultimo analisis de cada uno.
     *
     * @param int $vplid
     * @param int $courseid
     * @return array
     */
    public static function get_students_for_vpl(int $vplid, int $courseid): array {
        global $DB;

        // Obtener userid y cantidad de solicitudes por estudiante.
        $sql = "SELECT sa.userid,
                       COUNT(sa.id) AS solicitud_count,
                       MAX(sa.fecha_peticion) AS last_request
                  FROM {local_slm_solicitud_ayuda} sa
                 WHERE sa.vplid = :vplid AND sa.courseid = :courseid
              GROUP BY sa.userid
              ORDER BY last_request DESC";

        $rows = $DB->get_records_sql($sql, ['vplid' => $vplid, 'courseid' => $courseid]);

        $result = [];
        foreach ($rows as $row) {
            $userid = (int)$row->userid;
            $user   = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname', IGNORE_MISSING);
            if (!$user) {
                continue;
            }

            $fullname = fullname($user);

            // Ultima solicitud del estudiante para este VPL.
            $sqlast = "SELECT * FROM {local_slm_solicitud_ayuda}
                        WHERE userid = :uid AND vplid = :vid AND courseid = :cid
                        ORDER BY fecha_peticion DESC LIMIT 1";
            $lastsolicitud = $DB->get_record_sql($sqlast, [
                'uid' => $userid, 'vid' => $vplid, 'cid' => $courseid,
            ]);

            $topconcepts  = [];
            $topresources = [];
            $topexamples  = [];
            $toperrors    = [];

            if ($lastsolicitud) {
                $respuesta = $DB->get_record('local_slm_respuesta_ia', ['solicitudid' => $lastsolicitud->id]);
                if ($respuesta) {
                    $toperrors    = self::get_errors_for_response($respuesta->id, 3);
                    $topconcepts  = self::get_concepts_for_response($respuesta->id, 3);
                    $topresources = self::get_resources_for_response($respuesta->id, 3);
                    $topexamples  = self::get_examples_for_response($respuesta->id, 3);
                }
            }

            $result[] = [
                'userid'         => $userid,
                'fullname'       => $fullname,
                'solicitud_count'=> (int)$row->solicitud_count,
                'last_request'   => userdate((int)$row->last_request),
                'top_errors'     => $toperrors,
                'top_concepts'   => $topconcepts,
                'top_resources'  => $topresources,
                'top_examples'   => $topexamples,
            ];
        }

        return $result;
    }

    // =========================================================================
    // VISTA: DETALLE DE ESTUDIANTE EN VPL
    // =========================================================================

    /**
     * 4. Historial completo de solicitudes de un estudiante en un VPL.
     *
     * @param int $vplid
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function get_student_history(int $vplid, int $courseid, int $userid): array {
        global $DB;

        $solicitudes = $DB->get_records_select(
            'local_slm_solicitud_ayuda',
            'vplid = :vplid AND courseid = :courseid AND userid = :userid',
            ['vplid' => $vplid, 'courseid' => $courseid, 'userid' => $userid],
            'fecha_peticion DESC'
        );

        $result = [];
        $num    = count($solicitudes);

        foreach ($solicitudes as $sol) {
            $respuesta = $DB->get_record('local_slm_respuesta_ia', ['solicitudid' => $sol->id]);

            $errors    = [];
            $concepts  = [];
            $resources = [];
            $examples  = [];
            $errors_enriched = [];

            if ($respuesta) {
                $errors_enriched = self::get_errors_enriched_for_response($respuesta->id, $courseid);
            }

            /*if ($respuesta) {
                $errors    = self::get_errors_for_response($respuesta->id, 5);
                $concepts  = self::get_concepts_for_response($respuesta->id, 5);
                $resources = self::get_resources_for_response($respuesta->id, 5);
                $examples  = self::get_examples_for_response($respuesta->id, 5);
                $errors_enriched = self::get_errors_enriched_for_response($respuesta->id, $courseid);*/

                /*var_dump(count($errors));
                var_dump(count($errors_enriched));
                die();*/
            /*}*/

            $result[] = [
                'solicitudid'    => (int)$sol->id,
                'numero'         => $num--,
                'fecha'          => userdate((int)$sol->fecha_peticion),
                'student_message'=> $respuesta ? $respuesta->student_message : '',
                /*'errors'         => $errors,
                'concepts'       => $concepts,
                'resources'      => $resources,
                'examples'       => $examples,*/
                'errors_enriched'  => $errors_enriched,
                'has_errors'       => !empty($errors_enriched),
            ];
        }

        /*var_dump($result);
        die();*/

        return $result;
    }

    // =========================================================================
    // HELPERS: top items agregados por VPL (para tabla resumen)
    // =========================================================================

    /**
     * Top errores mas frecuentes en un VPL (conteo entre todos los estudiantes).
     *
     * @param int $vplid
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public static function get_top_errors(int $vplid, int $courseid, int $limit = 4): array {
        global $DB;

        $sql = "SELECT ed.titulo, COUNT(ed.id) AS cnt
                  FROM {local_slm_error_detectado} ed
                  JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                  JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                 WHERE sa.vplid = :vplid AND sa.courseid = :courseid
              GROUP BY ed.titulo ORDER BY cnt DESC";

        $records = $DB->get_records_sql($sql, ['vplid' => $vplid, 'courseid' => $courseid], 0, $limit);
        return array_values(array_map(fn($r) => [
            'titulo' => $r->titulo,
            'count'  => (int)$r->cnt,
        ], $records));
    }

    /**
     * Top conceptos del profesor mas frecuentes en un VPL.
     *
     * @param int $vplid
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public static function get_top_teacher_concepts(int $vplid, int $courseid, int $limit = 4): array {
        global $DB;

        // Conceptos del catálogo del profesor (local_slm_conceptos) que aparecen
        // en error_concepto para solicitudes de esta actividad.
        // Cuenta cuántas veces aparece cada concepto entre todos los estudiantes.
        $sql = "SELECT ec.id AS ecid, c.nombre, c.id AS cid
                  FROM {local_slm_error_concepto} ec
                  JOIN {local_slm_conceptos} c ON c.id = ec.conceptoid
                  JOIN {local_slm_error_detectado} ed ON ed.id = ec.errorid
                  JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                  JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                 WHERE sa.vplid = :vplid AND sa.courseid = :courseid
              ORDER BY c.nombre ASC";

        $records = $DB->get_records_sql($sql, ['vplid' => $vplid, 'courseid' => $courseid]);

        // Contar por concepto (agrupación manual porque ecid es unico).
        $counts = [];
        foreach ($records as $r) {
            $key = $r->cid;
            if (!isset($counts[$key])) {
                $counts[$key] = ['nombre' => $r->nombre, 'count' => 0];
            }
            $counts[$key]['count']++;
        }

        // Ordenar por frecuencia descendente.
        usort($counts, fn($a, $b) => $b['count'] - $a['count']);
        return array_values(array_slice($counts, 0, $limit));
    }

    /**
     * Top recursos del profesor mas frecuentes en un VPL.
     * Se obtienen via recurso_concepto → error_concepto.
     *
     * @param int $vplid
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public static function get_top_teacher_resources(int $vplid, int $courseid, int $limit = 4): array {
        global $DB;

        // Paso 1: obtener los conceptoid del profesor que aparecen en esta actividad.
        $sqlconc = "SELECT ec.id AS ecid, ec.conceptoid
                      FROM {local_slm_error_concepto} ec
                      JOIN {local_slm_error_detectado} ed ON ed.id = ec.errorid
                      JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                      JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                     WHERE sa.vplid = :vplid AND sa.courseid = :courseid";

        $concrecords = $DB->get_records_sql($sqlconc, ['vplid' => $vplid, 'courseid' => $courseid]);
        if (empty($concrecords)) {
            return [];
        }

        $conceptids = array_values(array_unique(
            array_map(fn($r) => (int)$r->conceptoid, $concrecords)
        ));

        // Paso 2: obtener los recursos vinculados a esos conceptos via recurso_concepto.
        [$insql, $params] = $DB->get_in_or_equal($conceptids, SQL_PARAMS_NAMED);
        $sqlres = "SELECT rc2.id AS rcid, r.id AS rid, r.titulo, ec2.conceptoid
                     FROM {local_slm_recurso_concepto} rc2
                     JOIN {local_slm_recurso} r ON r.id = rc2.recursoid
                     -- contar cuantas veces aparece ese concepto en la actividad
                     JOIN {local_slm_error_concepto} ec2 ON ec2.conceptoid = rc2.conceptoid
                     JOIN {local_slm_error_detectado} ed2 ON ed2.id = ec2.errorid
                     JOIN {local_slm_respuesta_ia} ri2 ON ri2.id = ed2.respuestaid
                     JOIN {local_slm_solicitud_ayuda} sa2 ON sa2.id = ri2.solicitudid
                    WHERE rc2.conceptoid $insql
                      AND sa2.vplid = :vplid AND sa2.courseid = :courseid";

        $params['vplid']    = $vplid;
        $params['courseid'] = $courseid;
        $resrecords = $DB->get_records_sql($sqlres, $params);

        // Contar por recurso.
        $counts = [];
        foreach ($resrecords as $r) {
            $key = (int)$r->rid;
            if (!isset($counts[$key])) {
                $counts[$key] = ['titulo' => $r->titulo, 'count' => 0];
            }
            $counts[$key]['count']++;
        }

        usort($counts, fn($a, $b) => $b['count'] - $a['count']);
        return array_values(array_slice($counts, 0, $limit));
    }

    /**
     * Top ejemplos IA mas frecuentes en un VPL.
     *
     * @param int $vplid
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public static function get_top_ia_examples(int $vplid, int $courseid, int $limit = 4): array {
        global $DB;

        $sql = "SELECT ie.titulo, COUNT(ie.id) AS cnt
                  FROM {local_slm_ia_ejemplo} ie
                  JOIN {local_slm_error_detectado} ed ON ed.id = ie.errorid
                  JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                  JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                 WHERE sa.vplid = :vplid AND sa.courseid = :courseid
              GROUP BY ie.titulo ORDER BY cnt DESC";

        $records = $DB->get_records_sql($sql, ['vplid' => $vplid, 'courseid' => $courseid], 0, $limit);
        return array_values(array_map(fn($r) => ['titulo' => $r->titulo, 'count' => (int)$r->cnt], $records));
    }

    // =========================================================================
    // HELPERS: items de una respuesta especifica
    // =========================================================================

    /**
     * Errores de una respuesta IA especifica.
     *
     * @param int $respuestaid
     * @param int $limit
     * @return array
     */
    public static function get_errors_for_response(int $respuestaid, int $limit = 5): array {
        global $DB;

        $records = $DB->get_records_select(
            'local_slm_error_detectado',
            'respuestaid = :rid',
            ['rid' => $respuestaid],
            'porcentaje DESC',
            'id, titulo, severidad, porcentaje',
            0, $limit
        );
        return array_values(array_map(fn($r) => [
            'titulo'    => $r->titulo,
            'severidad' => $r->severidad,
            'porcentaje'=> (float)$r->porcentaje,
        ], $records));
    }

    /**
     * Conceptos del catalogo del profesor para una respuesta IA especifica.
     *
     * @param int $respuestaid
     * @param int $limit
     * @return array
     */
    public static function get_concepts_for_response(int $respuestaid, int $limit = 5): array {
        global $DB;

        // ec.id es unico → get_records_sql no sobreescribe filas.
        $sql = "SELECT ec.id AS ecid, c.nombre
                  FROM {local_slm_error_concepto} ec
                  JOIN {local_slm_conceptos} c ON c.id = ec.conceptoid
                  JOIN {local_slm_error_detectado} ed ON ed.id = ec.errorid
                 WHERE ed.respuestaid = :rid
              ORDER BY c.nombre ASC";

        $records = $DB->get_records_sql($sql, ['rid' => $respuestaid]);
        $seen   = [];
        $result = [];
        foreach ($records as $r) {
            if (!in_array($r->nombre, $seen)) {
                $seen[]   = $r->nombre;
                $result[] = ['nombre' => $r->nombre];
                if (count($result) >= $limit) { break; }
            }
        }
        return $result;
    }

    /**
     * Recursos del profesor para una respuesta IA especifica.
     *
     * @param int $respuestaid
     * @param int $limit
     * @return array
     */
    public static function get_resources_for_response(int $respuestaid, int $limit = 5): array {
        global $DB;

        // Paso 1: conceptos vinculados a esta respuesta.
        $sqlconc = "SELECT ec.id AS ecid, ec.conceptoid
                      FROM {local_slm_error_concepto} ec
                      JOIN {local_slm_error_detectado} ed ON ed.id = ec.errorid
                     WHERE ed.respuestaid = :rid";

        $concrecords = $DB->get_records_sql($sqlconc, ['rid' => $respuestaid]);
        if (empty($concrecords)) {
            return [];
        }

        $conceptids = array_values(array_unique(
            array_map(fn($r) => (int)$r->conceptoid, $concrecords)
        ));

        // Paso 2: recursos asociados a esos conceptos.
        [$insql, $params] = $DB->get_in_or_equal($conceptids, SQL_PARAMS_NAMED);
        $sqlres = "SELECT rc2.id AS rcid, r.titulo
                     FROM {local_slm_recurso_concepto} rc2
                     JOIN {local_slm_recurso} r ON r.id = rc2.recursoid
                    WHERE rc2.conceptoid $insql
                 ORDER BY r.titulo ASC";

        $records = $DB->get_records_sql($sqlres, $params);
        $seen    = [];
        $result  = [];
        foreach ($records as $r) {
            if (!in_array($r->titulo, $seen)) {
                $seen[]   = $r->titulo;
                $result[] = ['titulo' => $r->titulo];
                if (count($result) >= $limit) { break; }
            }
        }
        return $result;
    }

    /**
     * Ejemplos IA para una respuesta especifica.
     *
     * @param int $respuestaid
     * @param int $limit
     * @return array
     */
    public static function get_examples_for_response(int $respuestaid, int $limit = 5): array {
        global $DB;

        $sql = "SELECT ie.titulo
                  FROM {local_slm_ia_ejemplo} ie
                  JOIN {local_slm_error_detectado} ed ON ed.id = ie.errorid
                 WHERE ed.respuestaid = :rid
              ORDER BY ie.id ASC";

        $records = $DB->get_records_sql($sql, ['rid' => $respuestaid], 0, $limit);
        return array_values(array_map(fn($r) => ['titulo' => $r->titulo], $records));
    }

    // =========================================================================
    // VISTA: CONCEPTOS (organizados por tema)
    // =========================================================================

    /**
     * 5. Datos de todos los temas y conceptos del curso con sus estadísticas.
     *    Para cada concepto: errores más frecuentes, recursos asociados.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_concepts_report(int $courseid): array {
        global $DB;

        $temas = $DB->get_records('local_slm_temas', ['courseid' => $courseid], 'nombre ASC');
        if (empty($temas)) {
            return [];
        }

        $result = [];
        foreach ($temas as $tema) {
            $conceptos = $DB->get_records(
                'local_slm_conceptos', ['temaid' => $tema->id], 'nombre ASC'
            );
            if (empty($conceptos)) {
                continue;
            }

            $conceptosdata = [];
            foreach ($conceptos as $concepto) {
                $conceptoid = (int)$concepto->id;

                // Top errores asociados a este concepto (via error_concepto).
                $toperrors = self::get_top_errors_by_concept($conceptoid, $courseid, 4);

                // Recursos asociados a este concepto (via recurso_concepto).
                $resources = self::get_resources_by_concept($conceptoid, $courseid, 4);

                // Contar cuántas veces aparece este concepto en el curso.
                $occurrences = self::count_concept_occurrences($conceptoid, $courseid);

                $detailurl = (new \moodle_url('/local/smart_learning_mentor/teacher.php', [
                    'courseid'   => $courseid,
                    'view'       => 'general',
                    'subview'    => 'concept_detail',
                    'conceptid'  => $conceptoid,
                ]))->out(false);

                $conceptosdata[] = [
                    'conceptoid'   => $conceptoid,
                    'nombre'       => $concepto->nombre,
                    'occurrences'  => $occurrences,
                    'has_data'     => $occurrences > 0 ? 1 : 0,
                    'top_errors'   => $toperrors,
                    'resources'    => $resources,
                    'detail_url'   => $detailurl,
                    'has_errors'   => !empty($toperrors) ? 1 : 0,
                    'has_resources'=> !empty($resources) ? 1 : 0,
                ];
            }

            $result[] = [
                'temaid'    => (int)$tema->id,
                'nombre'    => $tema->nombre,
                'conceptos' => $conceptosdata,
                'count'     => count($conceptosdata),
            ];
        }

        return $result;
    }

    /**
     * 6. Detalle completo de un concepto: todos sus errores con ejemplos IA.
     *
     * @param int $conceptoid
     * @param int $courseid
     * @return array
     */
    public static function get_concept_detail(int $conceptoid, int $courseid): array {
        global $DB;

        $concepto = $DB->get_record('local_slm_conceptos', ['id' => $conceptoid], '*', IGNORE_MISSING);
        if (!$concepto) {
            return [];
        }

        $tema = $DB->get_record('local_slm_temas', ['id' => $concepto->temaid], 'nombre', IGNORE_MISSING);

        // Todos los errores vinculados a este concepto en el curso.
        $sql = "SELECT ec.id AS ecid, ed.id AS errorid, ed.titulo, ed.severidad,
                       ed.porcentaje, ed.descripcion_error, ed.recomendacion,
                       COUNT(ec.id) AS ocurrencias
                  FROM {local_slm_error_concepto} ec
                  JOIN {local_slm_error_detectado} ed ON ed.id = ec.errorid
                  JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                  JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                 WHERE ec.conceptoid = :cid AND sa.courseid = :courseid
              GROUP BY ec.id, ed.id, ed.titulo, ed.severidad,
                       ed.porcentaje, ed.descripcion_error, ed.recomendacion
              ORDER BY ocurrencias DESC, ed.porcentaje DESC";

        $errors = $DB->get_records_sql($sql, ['cid' => $conceptoid, 'courseid' => $courseid]);

        // Agrupar por titulo de error para consolidar duplicados.
        $errormap = [];
        foreach ($errors as $e) {
            $key = md5($e->titulo);
            if (!isset($errormap[$key])) {
                $errormap[$key] = [
                    'errorid'     => (int)$e->errorid,
                    'titulo'      => $e->titulo,
                    'severidad'   => $e->severidad,
                    'porcentaje'  => (float)$e->porcentaje,
                    'descripcion' => $e->descripcion_error,
                    'recomendacion' => $e->recomendacion,
                    'ocurrencias' => 0,
                    'ejemplos'    => [],
                ];
            }
            $errormap[$key]['ocurrencias'] += (int)$e->ocurrencias;

            // Ejemplos IA para este error.
            $ejemplos = $DB->get_records(
                'local_slm_ia_ejemplo', ['errorid' => $e->errorid],
                'id ASC', 'id, titulo, codigo', 0, 3
            );
            foreach ($ejemplos as $ej) {
                $errormap[$key]['ejemplos'][] = [
                    'titulo' => $ej->titulo,
                    'codigo' => $ej->codigo ?? '',
                ];
            }
        }

        // Recursos del profesor asociados a este concepto.
        $resources = self::get_resources_by_concept($conceptoid, $courseid, 10);

        return [
            'concepto' => [
                'id'     => $conceptoid,
                'nombre' => $concepto->nombre,
                'tema'   => $tema ? $tema->nombre : '',
            ],
            'errors'    => array_values($errormap),
            'resources' => $resources,
        ];
    }

    /**
     * 7. Top errores asociados a un concepto específico.
     *
     * @param int $conceptoid
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public static function get_top_errors_by_concept(int $conceptoid, int $courseid, int $limit = 4): array {
        global $DB;

        $sql = "SELECT ec.id AS ecid, ed.titulo
                  FROM {local_slm_error_concepto} ec
                  JOIN {local_slm_error_detectado} ed ON ed.id = ec.errorid
                  JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                  JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                 WHERE ec.conceptoid = :cid AND sa.courseid = :courseid
              ORDER BY ed.porcentaje DESC";

        $records = $DB->get_records_sql($sql, ['cid' => $conceptoid, 'courseid' => $courseid]);

        // Contar por titulo.
        $counts = [];
        foreach ($records as $r) {
            $key = md5($r->titulo);
            if (!isset($counts[$key])) {
                $counts[$key] = ['titulo' => $r->titulo, 'count' => 0];
            }
            $counts[$key]['count']++;
        }

        usort($counts, fn($a, $b) => $b['count'] - $a['count']);
        return array_values(array_slice($counts, 0, $limit));
    }

    /**
     * 8. Recursos del profesor vinculados a un concepto.
     *
     * @param int $conceptoid
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public static function get_resources_by_concept(int $conceptoid, int $courseid, int $limit = 4): array {
        global $DB;

        $sql = "SELECT rc.id AS rcid, r.titulo, r.tipo, r.url, r.cmid
                  FROM {local_slm_recurso_concepto} rc
                  JOIN {local_slm_recurso} r ON r.id = rc.recursoid
                 WHERE rc.conceptoid = :cid AND r.courseid = :courseid
              ORDER BY r.titulo ASC";

        $records = $DB->get_records_sql($sql, ['cid' => $conceptoid, 'courseid' => $courseid], 0, $limit);
        return array_values(array_map(fn($r) => [
            'titulo' => $r->titulo,
            'tipo'   => $r->tipo,
            'url'    => !empty($r->url) ? $r->url
                        : (!empty($r->cmid) ? (new \moodle_url('/mod/' . ($r->tipo === 'curso' ? 'resource' : $r->tipo) . '/view.php', ['id' => $r->cmid]))->out(false) : ''),
        ], $records));
    }

    /**
     * 9. Cuenta cuántas veces aparece un concepto en respuestas del curso.
     *
     * @param int $conceptoid
     * @param int $courseid
     * @return int
     */
    public static function count_concept_occurrences(int $conceptoid, int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(ec.id) AS cnt
                  FROM {local_slm_error_concepto} ec
                  JOIN {local_slm_error_detectado} ed ON ed.id = ec.errorid
                  JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                  JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                 WHERE ec.conceptoid = :cid AND sa.courseid = :courseid";

        $rec = $DB->get_record_sql($sql, ['cid' => $conceptoid, 'courseid' => $courseid]);
        return $rec ? (int)$rec->cnt : 0;
    }


    /**
     * Errores enriquecidos para la vista detalle del estudiante.
     * Devuelve todos los campos editables + conceptos con recursos + ejemplos IA.
     *
     * @param int $respuestaid
     * @param int $courseid   Para generar URLs de recursos del curso.
     * @return array
     */
    public static function get_errors_enriched_for_response(int $respuestaid, int $courseid = 0): array {
        global $DB;

        // 1. Obtener todos los errores de esta respuesta.
        $errors = $DB->get_records_select(
            'local_slm_error_detectado',
            'respuestaid = :rid',
            ['rid' => $respuestaid],
            'porcentaje DESC',
            'id, titulo, descripcion_error, recomendacion, severidad, porcentaje'
        );

        if (empty($errors)) {
            return [];
        }

        $result = [];
        foreach ($errors as $error) {
            $errorid = (int)$error->id;

            // 2. Conceptos del catálogo vinculados a este error + recursos.
            $sql = "SELECT ec.id AS ecid, c.id AS cid, c.nombre AS cnombre,
                           rc.id AS rcid, r.titulo AS rtitulo, r.cmid, r.url, r.tipo
                      FROM {local_slm_error_concepto} ec
                      JOIN {local_slm_conceptos} c ON c.id = ec.conceptoid
                      LEFT JOIN {local_slm_recurso_concepto} rc ON rc.conceptoid = c.id
                      LEFT JOIN {local_slm_recurso} r ON r.id = rc.recursoid
                     WHERE ec.errorid = :eid
                  ORDER BY c.nombre ASC, r.titulo ASC";

            $rows = $DB->get_records_sql($sql, ['eid' => $errorid]);

            // Agrupar conceptos y sus recursos.
            $conceptmap = [];
            foreach ($rows as $row) {
                $cid = (int)$row->cid;
                if (!isset($conceptmap[$cid])) {
                    $conceptmap[$cid] = ['nombre' => $row->cnombre, 'resources' => []];
                }
                if (!empty($row->rcid)) {
                    $url = '';
                    if (!empty($row->url)) {
                        $url = $row->url;
                    } else if (!empty($row->cmid)) {
                        try {
                            $cm = get_coursemodule_from_id('', $row->cmid);
                            $mod = $cm ? $cm->modname : 'resource';
                        } catch (\Exception $e) {
                            $mod = 'resource';
                        }
                        $url = (new \moodle_url("/mod/{$mod}/view.php", ['id' => $row->cmid]))->out(false);
                    }
                    // Evitar duplicados de recurso en el mismo concepto.
                    $existing = array_column($conceptmap[$cid]['resources'], 'titulo');
                    if (!in_array($row->rtitulo, $existing)) {
                        $conceptmap[$cid]['resources'][] = ['titulo' => $row->rtitulo, 'url' => $url];
                    }
                }
            }

            // Convertir a array para Mustache.
            $concepts = array_values(array_map(function($c) {
                $resources = array_values(array_map(fn($r) => [
                    'titulo'  => (string)$r['titulo'],
                    'url'     => (string)$r['url'],
                    'has_url' => !empty($r['url']) ? 1 : 0,
                ], $c['resources']));
                return [
                    'nombre'       => (string)$c['nombre'],
                    'has_resources'=> !empty($resources) ? 1 : 0,
                    'resources'    => $resources,
                ];
            }, $conceptmap));

            // 3. Ejemplos IA de este error.
            $ejemplos = $DB->get_records(
                'local_slm_ia_ejemplo',
                ['errorid' => $errorid],
                'id ASC',
                'id, titulo, descripcion_ejemplo, codigo, explicacion, resultado_esperado'
            );

            $examples = array_values(array_map(fn($ej) => [
                'ejemploid'          => (int)$ej->id,
                'titulo'             => (string)$ej->titulo,
                'descripcion_ejemplo'=> (string)($ej->descripcion_ejemplo ?? ''),
                'codigo'             => (string)($ej->codigo              ?? ''),
                'explicacion'        => (string)($ej->explicacion         ?? ''),
                'resultado_esperado' => (string)($ej->resultado_esperado  ?? ''),
                'has_codigo'         => !empty($ej->codigo)             ? 1 : 0,
                'has_resultado'      => !empty($ej->resultado_esperado) ? 1 : 0,
            ], $ejemplos));

            $result[] = [
                'errorid'           => $errorid,
                'titulo'            => (string)$error->titulo,
                'descripcion_error' => (string)($error->descripcion_error ?? ''),
                'recomendacion'     => (string)($error->recomendacion     ?? ''),
                'severidad'         => (string)($error->severidad         ?? ''),
                'porcentaje'        => (float)$error->porcentaje,
                'has_concepts'      => !empty($concepts) ? 1 : 0,
                'concepts'          => $concepts,
                'has_examples'      => !empty($examples) ? 1 : 0,
                'examples'          => $examples,
            ];
        }

        return $result;
    }


}
