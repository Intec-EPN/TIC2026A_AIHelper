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
 
class student_storage {
 
    /**
     * Actividades VPL del curso donde el estudiante tiene solicitudes
     */
    public static function get_student_activities(int $courseid, int $userid): array {
        global $DB;
 
        $sql = "SELECT sa.vplid, v.name AS vplname,
                       COUNT(sa.id) AS solicitud_count,
                       MAX(sa.fecha_peticion) AS last_request,
                       MIN(sa.cmid) AS cmid
                  FROM {local_slm_solicitud_ayuda} sa
                  JOIN {vpl} v ON v.id = sa.vplid
                 WHERE sa.courseid = :courseid AND sa.userid = :userid
              GROUP BY sa.vplid, v.name
              ORDER BY MAX(sa.fecha_peticion) DESC";
 
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
 
        $result = [];
        foreach ($rows as $row) {
            $errorsql = "SELECT ed.titulo
                           FROM {local_slm_error_detectado} ed
                           JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                           JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                          WHERE sa.courseid = :courseid AND sa.userid = :userid AND sa.vplid = :vplid
                       ORDER BY ed.porcentaje DESC";
 
            $errs = $DB->get_records_sql($errorsql, [
                'courseid' => $courseid, 'userid' => $userid, 'vplid' => $row->vplid,
            ], 0, 3);
 
            $result[] = [
                'vplid' => (int)$row->vplid,
                'cmid'=> (int)$row->cmid,
                'name'=> (string)$row->vplname,
                'solicitud_count'=> (int)$row->solicitud_count,
                'last_request' => userdate((int)$row->last_request, get_string('strftimedatetimeshort', 'langconfig')),
                'top_errors' => array_values(array_map(fn($e) => ['titulo' => (string)$e->titulo], $errs)),
                'has_errors' => !empty($errs) ? 1 : 0,
                'detail_url' => (new \moodle_url('/local/smart_learning_mentor/student.php', [
                    'courseid' => $courseid, 'subview' => 'history', 'vplid' => $row->vplid,
                ]))->out(false),
            ];
        }
 
        return $result;
    }
 
    /**
     * Conceptos del catalogo detectado en los errores del estudiante, por tema
     */
    public static function get_student_concepts(int $courseid, int $userid): array {
        global $DB;
 
        // consulta
        $sql = "SELECT c.id AS conceptoid, t.id AS temaid, t.nombre AS temanombre,
                       c.nombre AS conceptonombre,
                       COUNT(ec.id) AS ocurrencias
                  FROM {local_slm_error_concepto} ec
                  JOIN {local_slm_conceptos} c ON c.id = ec.conceptoid
                  JOIN {local_slm_temas} t ON t.id = c.temaid
                  JOIN {local_slm_error_detectado} ed ON ed.id = ec.errorid
                  JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                  JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                 WHERE sa.courseid = :courseid AND sa.userid = :userid
                   AND t.courseid = :courseid2
              GROUP BY c.id, t.id, t.nombre, c.nombre
              ORDER BY t.nombre ASC, ocurrencias DESC";
 
        $rows = $DB->get_records_sql($sql, [
            'courseid' => $courseid, 'userid' => $userid, 'courseid2' => $courseid,
        ]);
 
        $temamap = [];
        foreach ($rows as $row) {
            $tid = (int)$row->temaid;
            if (!isset($temamap[$tid])) {
                $temamap[$tid] = [
                'temaid' => $tid,
                'nombre' => $row->temanombre,
                'conceptos' => [],
            ];
            }
            $cid = (int)$row->conceptoid;
 
            // Top 2 errores del estudiante para este concep
            $errsql = "SELECT ed.titulo
                         FROM {local_slm_error_concepto} ec2
                         JOIN {local_slm_error_detectado} ed ON ed.id = ec2.errorid
                         JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                         JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                        WHERE ec2.conceptoid = :cid AND sa.courseid = :courseid AND sa.userid = :userid
                     ORDER BY ed.porcentaje DESC";
            $errs = $DB->get_records_sql($errsql, ['cid' => $cid, 'courseid' => $courseid, 'userid' => $userid], 0, 2);
 
            // Recursos del profesor para este concepto
            $resources = self::get_resources_for_concept($cid, $courseid, 4);
 
            $temamap[$tid]['conceptos'][] = [
                'conceptoid' => $cid,
                'nombre' => (string)$row->conceptonombre,
                'ocurrencias' => (int)$row->ocurrencias,
                'top_errors' => array_values(array_map(fn($e) => ['titulo' => (string)$e->titulo], $errs)),
                'has_errors' => !empty($errs) ? 1 : 0,
                'resources' => $resources,
                'has_resources' => !empty($resources) ? 1 : 0,
                'detail_url' => (new \moodle_url('/local/smart_learning_mentor/student.php', [
                    'courseid' => $courseid,
                    'subview' => 'concept_detail',
                    'conceptid' => $cid,
                ]))->out(false),
            ];
        }
 
        return array_values($temamap);
    }
 
    /**
     * Detalle de un concepto para el estudiante
     * errores detectados EN ESTE ESTUDIANTE con sus ejemplos IA + recursos del profesor
     */
    public static function get_student_concept_detail(int $conceptoid, int $courseid, int $userid): array {
        global $DB;
 
        $concepto = $DB->get_record('local_slm_conceptos', ['id' => $conceptoid], '*', IGNORE_MISSING);
        if (!$concepto) {
            return [];
        }
        $tema = $DB->get_record('local_slm_temas', ['id' => $concepto->temaid], 'nombre', IGNORE_MISSING);
 
        // Errores del estudiante asociados a este concepto
        $sql = "SELECT ec.id AS ecid, ed.id AS errorid, ed.titulo,
                ed.severidad, ed.porcentaje, ed.descripcion_error, ed.recomendacion,
                       COUNT(ec.id) AS ocurrencias
                  FROM {local_slm_error_concepto} ec
                  JOIN {local_slm_error_detectado} ed ON ed.id = ec.errorid
                  JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                  JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                 WHERE ec.conceptoid = :cid AND sa.courseid = :courseid AND sa.userid = :userid
              GROUP BY ec.id, ed.id, ed.titulo, ed.severidad, ed.porcentaje,
                       ed.descripcion_error, ed.recomendacion
              ORDER BY ocurrencias DESC, ed.porcentaje DESC";
 
        $errorrows = $DB->get_records_sql($sql, [
            'cid' => $conceptoid, 'courseid' => $courseid, 'userid' => $userid,
        ]);
 
        // Agrupar errores por titulo y cargar sus ejemplos I
        $errormap = [];
        foreach ($errorrows as $e) {
            $key = md5($e->titulo);
            if (!isset($errormap[$key])) {
                $errormap[$key] = [
                    'titulo' => (string)$e->titulo,
                    'severidad' => (string)$e->severidad,
                    'porcentaje' => (float)$e->porcentaje,
                    'descripcion' => (string)($e->descripcion_error ?? ''),
                    'recomendacion'=> (string)($e->recomendacion ?? ''),
                    'ocurrencias' => 0,
                    'ejemplos' => [],
                ];
            }
            $errormap[$key]['ocurrencias'] += (int)$e->ocurrencias;
 
            // Ejemplos IA de este error
            $ejemplos = $DB->get_records(
                'local_slm_ia_ejemplo', ['errorid' => $e->errorid],
                'id ASC', 'id, titulo, descripcion_ejemplo, codigo, explicacion, resultado_esperado', 0, 3
            );
            foreach ($ejemplos as $ej) {
                $already = array_column($errormap[$key]['ejemplos'], 'titulo');
                if (!in_array($ej->titulo, $already)) {
                    $errormap[$key]['ejemplos'][] = [
                        'ejemploid' => (int)$ej->id,
                        'titulo' => (string)$ej->titulo,
                        'descripcion_ejemplo'=> (string)($ej->descripcion_ejemplo ?? ''),
                        'codigo' => (string)($ej->codigo ?? ''),
                        'explicacion' => (string)($ej->explicacion ?? ''),
                        'resultado_esperado' => (string)($ej->resultado_esperado  ?? ''),
                        'has_codigo' => !empty($ej->codigo) ? 1 : 0,
                        'has_resultado' => !empty($ej->resultado_esperado) ? 1 : 0,
                    ];
                }
            }
        }
 
        // Serializar ejemplos
        $errors = array_values(array_map(function($e) {
            return array_merge($e, [
                'has_ejemplos' => !empty($e['ejemplos']) ? 1 : 0,
                'ejemplos' => array_values($e['ejemplos']),
            ]);
        }, $errormap));
 
        return [
            'concepto' => [
                'id' => $conceptoid,
                'nombre' => (string)$concepto->nombre,
                'tema' => $tema ? (string)$tema->nombre : '',
            ],
            'errors' => $errors,
            'resources' => self::get_resources_for_concept($conceptoid, $courseid),
            'has_errors' => !empty($errors) ? 1 : 0,
            'has_resources' => !empty(self::get_resources_for_concept($conceptoid, $courseid)) ? 1 : 0,
        ];
    }
 
    /**
     * Recursos del profesor asociados a un concepto.
     */
    public static function get_resources_for_concept(int $conceptoid, int $courseid, int $limit = 10): array {
        global $DB;
 
        $sql = "SELECT rc.id AS rcid, r.titulo, r.cmid, r.url, r.tipo
                  FROM {local_slm_recurso_concepto} rc
                  JOIN {local_slm_recurso} r ON r.id = rc.recursoid
                 WHERE rc.conceptoid = :cid AND r.courseid = :courseid
              ORDER BY r.titulo ASC";
 
        $rows = $DB->get_records_sql($sql, ['cid' => $conceptoid, 'courseid' => $courseid], 0, $limit);
        $result = [];
        foreach ($rows as $r) {
            $url = '';
            if (!empty($r->url)) {
                $url = $r->url;
            } else if (!empty($r->cmid)) {
                try { $cm = get_coursemodule_from_id('', $r->cmid); $mod = $cm ? $cm->modname : 'resource'; }
                catch (\Exception $e) { $mod = 'resource'; }
                $url = (new \moodle_url("/mod/{$mod}/view.php", ['id' => $r->cmid]))->out(false);
            }
            $result[] = ['titulo' => (string)$r->titulo, 'url' => $url, 'has_url' => !empty($url) ? 1 : 0];
        }
        return $result;
    }
 
    /**
     * Detalle de un TEMA para el estudiante
     * todos los conceptos del tema con sus errores detectados + ejempls + recurs
     */
    public static function get_student_theme_detail(int $themeid, int $courseid, int $userid): array {
        global $DB;
 
        $tema = $DB->get_record('local_slm_temas', ['id' => $themeid], 'id, nombre', IGNORE_MISSING);
        if (!$tema) { return []; }
 
        // Conceptos del tema que aparecen en los errores del estudiante
        $sql = "SELECT c.id AS conceptoid, c.nombre AS cnombre, COUNT(ec.id) AS ocurrencias
                  FROM {local_slm_conceptos} c
                  JOIN {local_slm_error_concepto} ec ON ec.conceptoid = c.id
                  JOIN {local_slm_error_detectado} ed ON ed.id = ec.errorid
                  JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                  JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                 WHERE c.temaid = :temaid AND sa.courseid = :courseid AND sa.userid = :userid
              GROUP BY c.id, c.nombre
              ORDER BY ocurrencias DESC, c.nombre ASC";
 
        $conceptrows = $DB->get_records_sql($sql, [
            'temaid' => $themeid, 'courseid' => $courseid, 'userid' => $userid,
        ]);
 
        $conceptos = [];
        foreach ($conceptrows as $row) {
            $cid = (int)$row->conceptoid;
 
            // Errores del estudiante para este concepto.
            $errsql = "SELECT ec.id AS ecid, ed.id AS errorid, ed.titulo,
                              ed.severidad, ed.porcentaje, ed.descripcion_error, ed.recomendacion,
                              COUNT(ec.id) AS ocurrencias
                         FROM {local_slm_error_concepto} ec
                         JOIN {local_slm_error_detectado} ed ON ed.id = ec.errorid
                         JOIN {local_slm_respuesta_ia} ri ON ri.id = ed.respuestaid
                         JOIN {local_slm_solicitud_ayuda} sa ON sa.id = ri.solicitudid
                        WHERE ec.conceptoid = :cid AND sa.courseid = :courseid AND sa.userid = :userid
                     GROUP BY ec.id, ed.id, ed.titulo, ed.severidad, ed.porcentaje,
                              ed.descripcion_error, ed.recomendacion
                     ORDER BY ocurrencias DESC, ed.porcentaje DESC";
 
            $errorrows = $DB->get_records_sql($errsql, [
                'cid' => $cid, 'courseid' => $courseid, 'userid' => $userid,
            ]);
 
            // Agrupar errores por título + cargar ejemplos IA.
            $errormap = [];
            foreach ($errorrows as $e) {
                $key = md5($e->titulo);
                if (!isset($errormap[$key])) {
                    $errormap[$key] = [
                        'titulo' => (string)$e->titulo,
                        'severidad' => (string)$e->severidad,
                        'porcentaje' => (float)$e->porcentaje,
                        'descripcion' => (string)($e->descripcion_error ?? ''),
                        'recomendacion' => (string)($e->recomendacion ?? ''),
                        'ocurrencias' => 0,
                        'ejemplos' => [],
                    ];
                }
                $errormap[$key]['ocurrencias'] += (int)$e->ocurrencias;
 
                $ejemplos = $DB->get_records(
                    'local_slm_ia_ejemplo', ['errorid' => $e->errorid],
                    'id ASC', 'id, titulo, descripcion_ejemplo, codigo, explicacion, resultado_esperado', 0, 2
                );
                foreach ($ejemplos as $ej) {
                    $already = array_column($errormap[$key]['ejemplos'], 'titulo');
                    if (!in_array($ej->titulo, $already)) {
                        $errormap[$key]['ejemplos'][] = [
                            'ejemploid' => (int)$ej->id,
                            'titulo' => (string)$ej->titulo,
                            'descripcion_ejemplo'=> (string)($ej->descripcion_ejemplo ?? ''),
                            'codigo' => (string)($ej->codigo ?? ''),
                            'explicacion' => (string)($ej->explicacion ?? ''),
                            'resultado_esperado' => (string)($ej->resultado_esperado ?? ''),
                            'has_codigo' => !empty($ej->codigo) ? 1 : 0,
                            'has_resultado' => !empty($ej->resultado_esperado) ? 1 : 0,
                        ];
                    }
                }
            }
 
            $errors = array_values(array_map(function($e) {
                return array_merge($e, [
                    'has_ejemplos' => !empty($e['ejemplos']) ? 1 : 0,
                    'ejemplos' => array_values($e['ejemplos']),
                ]);
            }, $errormap));
 
            $resources = self::get_resources_for_concept($cid, $courseid);
 
            $conceptos[] = [
                'conceptoid' => $cid,
                'nombre' => (string)$row->cnombre,
                'ocurrencias' => (int)$row->ocurrencias,
                'errors' => $errors,
                'has_errors' => !empty($errors) ? 1 : 0,
                'resources' => $resources,
                'has_resources' => !empty($resources) ? 1 : 0,
            ];
        }
 
        return [
            'tema' => ['id' => $themeid, 'nombre' => (string)$tema->nombre],
            'conceptos' => $conceptos,
            'has_conceptos' => !empty($conceptos) ? 1 : 0,
        ];
    }
 
}
 