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
 * Output del dashboard del estudiante.
 *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\output\student;
defined('MOODLE_INTERNAL') || die();

use renderer_base;
use moodle_url;

class dashboard {

    private int $courseid;
    private int $userid;
    private string $subview;
    private int $vplid;

    private int $conceptid;

    public function __construct(int $courseid, int $userid, string $subview = 'concepts', int $vplid = 0, int $conceptid = 0) {
        $this->courseid = $courseid;
        $this->userid = $userid;
        $this->subview = $subview;
        $this->vplid = $vplid;
        $this->conceptid  = $conceptid;
    }

    public function export_for_template(renderer_base $output): array {
        global $DB;

        $urlconcepts = (new moodle_url('/local/smart_learning_mentor/student.php', [
            'courseid' => $this->courseid, 'subview' => 'concepts',
        ]))->out(false);
        $urlactivities = (new moodle_url('/local/smart_learning_mentor/student.php', [
            'courseid' => $this->courseid, 'subview' => 'activities',
        ]))->out(false);

        $base = [
            'courseid' => $this->courseid,
            'userid' => $this->userid,
            'url_concepts' => $urlconcepts,
            'url_activities' => $urlactivities,
            'active_concepts' => ($this->subview === 'concepts') ? 1 : 0,
            'active_activities' => ($this->subview === 'activities') ? 1 : 0,
            'show_concepts' => ($this->subview === 'concepts') ? 1 : 0,
            'show_activities' => ($this->subview === 'activities') ? 1 : 0,
            'show_history' => ($this->subview === 'history') ? 1 : 0,
            'show_concept_detail' => ($this->subview === 'concept_detail') ? 1 : 0,
            // historiald
            'debug_vplid' => $this->vplid,
            'vplname' => '',
            'back_url' => $urlactivities,
            'history' => [],
            'has_history' => 0,
            'str_back' => 'Actividades',
            'str_no_history' => 'No tienes solicitudes de ayuda en esta actividad.',
        ];

        if ($this->subview === 'activities') {
            $activities = \local_smart_learning_mentor\storage\student_storage::get_student_activities(
                $this->courseid, $this->userid
            );
            $base = array_merge($base, [
                'activities' => $activities,
                'has_activities' => !empty($activities) ? 1 : 0,
                'str_col_activity' => 'Actividad',
                'str_col_requests' => 'Solicitudes',
                'str_col_last' => 'Última solicitud',
                'str_col_errors' => 'Errores frecuentes',
                'str_col_detail' => 'Detalle',
                'str_view_detail' => 'Ver historial',
                'str_no_activities' => 'Aún no has solicitado ayuda en ninguna actividad.',
            ]);

        } else if ($this->subview === 'concepts') {
            $temas = \local_smart_learning_mentor\storage\student_storage::get_student_concepts(
                $this->courseid, $this->userid
            );
            $temas = array_map(function($t) {
                $t['conceptos'] = array_map(function($c) {
                    $c['has_errors'] = (int)$c['has_errors'];
                    $c['has_resources'] = (int)$c['has_resources'];
                    return $c;
                }, $t['conceptos']);
                $t['count'] = count($t['conceptos']);
                $t['count_plural'] = count($t['conceptos']) !== 1 ? 1 : 0;
                return $t;
            }, $temas);
            $base = array_merge($base, [
                'temas' => $temas,
                'has_temas' => !empty($temas) ? 1 : 0,
                'str_no_concepts' => 'Aún no se han detectado conceptos en tus solicitudes.',
            ]);

        } else if ($this->subview === 'concept_detail' && $this->conceptid > 0) {

        debugging(
        "Entró a concept_detail. conceptid={$this->conceptid}",
        DEBUG_DEVELOPER
    );
    
            $data = \local_smart_learning_mentor\storage\student_storage::get_student_concept_detail(
                $this->conceptid, $this->courseid, $this->userid
            );
            $backurl = (new moodle_url('/local/smart_learning_mentor/student.php', [
                'courseid' => $this->courseid, 'subview' => 'concepts',
            ]))->out(false);

            $base = array_merge($base, [
                'concepto' => $data['concepto'] ?? null,
                'errors' => $data['errors']   ?? [],
                'resources' => $data['resources'] ?? [],
                'has_errors' => !empty($data['errors'])    ? 1 : 0,
                'has_resources' => !empty($data['resources']) ? 1 : 0,
                'back_url_concept' => $backurl,
                'str_recommendation' => 'Recomendación',
                'str_ia_examples'    => 'Ejemplos IA',
            ]);

        } else if ($this->subview === 'history' && $this->vplid > 0) {
            // Nombre del VPL
            $vpl = $DB->get_record('vpl', ['id' => $this->vplid], 'id, name', IGNORE_MISSING);
            $vplname = $vpl ? (string)$vpl->name : 'VPL #' . $this->vplid;

            $backurl = (new moodle_url('/local/smart_learning_mentor/student.php', [
                'courseid' => $this->courseid, 'subview' => 'activities',
            ]))->out(false);

            // Obtener historial con try/catch para capturar errores de BD
            $history = [];
            $dberror = '';
            try {
                $rawhistory = \local_smart_learning_mentor\storage\report_storage::get_student_history(
                    $this->vplid, $this->courseid, $this->userid
                );
                $history = self::normalize_history($rawhistory);
            } catch (\Exception $e) {
                $dberror = $e->getMessage();
                debugging('slm student history error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            $base = array_merge($base, [
                'vplname' => $vplname,
                'back_url' => $backurl,
                'history' => $history,
                'has_history' => !empty($history) ? 1 : 0,
                'str_back' => 'Actividades',
                'str_no_history'=> 'No tienes solicitudes de ayuda en esta actividad.',
                'debug_error' => $dberror, 
            ]);
        }

       /* debugging(print_r([
            'subview' => $this->subview,
            'show_concepts' => $base['show_concepts'],
            'show_concept_detail' => $base['show_concept_detail'],
        ], true), DEBUG_DEVELOPER);*/

        return $base;
    }

    private static function normalize_history(array $history): array {
        return array_values(array_map(function($h) {
            $errors_enriched = array_values(array_map(function($e) {
                return [
                    'errorid' => (int)$e['errorid'],
                    'titulo' => (string)$e['titulo'],
                    'descripcion_error' => (string)$e['descripcion_error'],
                    'recomendacion' => (string)$e['recomendacion'],
                    'severidad' => (string)$e['severidad'],
                    'porcentaje' => (float)$e['porcentaje'],
                    'has_concepts' => !empty($e['concepts']) ? 1 : 0,
                    'concepts' => array_values(array_map(fn($c) => [
                        'nombre' => (string)$c['nombre'],
                        'has_resources' => !empty($c['resources']) ? 1 : 0,
                        'resources' => array_values(array_map(fn($r) => [
                            'titulo' => (string)$r['titulo'],
                            'url' => (string)$r['url'],
                            'has_url' => !empty($r['url']) ? 1 : 0,
                        ], $c['resources'] ?? [])),
                    ], $e['concepts'] ?? [])),
                    'has_examples' => !empty($e['examples']) ? 1 : 0,
                    'examples' => array_values(array_map(fn($ej) => [
                        'ejemploid' => (int)$ej['ejemploid'],
                        'titulo' => (string)$ej['titulo'],
                        'descripcion_ejemplo'=> (string)$ej['descripcion_ejemplo'],
                        'codigo' => (string)$ej['codigo'],
                        'explicacion' => (string)$ej['explicacion'],
                        'resultado_esperado' => (string)$ej['resultado_esperado'],
                        'has_codigo' => !empty($ej['codigo']) ? 1 : 0,
                        'has_resultado' => !empty($ej['resultado_esperado']) ? 1 : 0,
                    ], $e['examples'] ?? [])),
                ];
            }, $h['errors_enriched'] ?? []));

            return [
                'solicitudid' => (int)$h['solicitudid'],
                'numero' => (int)$h['numero'],
                'fecha' => (string)$h['fecha'],
                'student_message'=> (string)($h['student_message'] ?? ''),
                'has_errors' => !empty($errors_enriched) ? 1 : 0,
                'errors_enriched'=> $errors_enriched,
            ];
        }, $history));
    }
}
