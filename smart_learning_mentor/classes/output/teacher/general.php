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
 * Output para la vista General del profesor (Actividades | Conceptos).
 *
 * Descripción del flujo:
 *   1. teacher_controller retorna este renderable.
 *   2. teacher.php llama a $OUTPUT->render($renderable).
 *   3. export_for_template() prepara todos los datos: navegación, sub-navegación,
 *      cadenas de idioma y datos de la tabla de actividades VPL.
 *   4. Moodle pasa los datos a templates/teacher/general.mustache.
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_smart_learning_mentor\output\teacher;
 
defined('MOODLE_INTERNAL') || die();
 
use renderable;
use templatable;
use renderer_base;
use moodle_url;
 
class general implements renderable, templatable {
 
    private int    $courseid;
    private string $subview;
    private int    $cmid;
    private int    $userid;
 
    private int $conceptid;
 
    public function __construct(int $courseid, string $subview = 'activities', int $cmid = 0, int $userid = 0, int $conceptid = 0) {
        $this->courseid   = $courseid;
        $this->subview    = $subview;
        $this->cmid       = $cmid;
        $this->userid     = $userid;
        $this->conceptid  = $conceptid;
    }
 
    public function export_for_template(renderer_base $output): array {
        $s = fn(string $k) => get_string($k, 'local_smart_learning_mentor');
 
        // URLs de navegacion principal.
        $navgeneral = (new moodle_url('/local/smart_learning_mentor/teacher.php', [
            'courseid' => $this->courseid, 'view' => 'general', 'subview' => 'activities',
        ]))->out(false);
        $navcatalog = (new moodle_url('/local/smart_learning_mentor/teacher.php', [
            'courseid' => $this->courseid, 'view' => 'catalog', 'subview' => 'topics',
        ]))->out(false);
        $navconfig  = (new moodle_url('/local/smart_learning_mentor/teacher.php', [
            'courseid' => $this->courseid, 'view' => 'config',
        ]))->out(false);
 
        // URLs sub-selector Actividades | Conceptos.
        $urlactivities = (new moodle_url('/local/smart_learning_mentor/teacher.php', [
            'courseid' => $this->courseid, 'view' => 'general', 'subview' => 'activities',
        ]))->out(false);
        $urlconcepts   = (new moodle_url('/local/smart_learning_mentor/teacher.php', [
            'courseid' => $this->courseid, 'view' => 'general', 'subview' => 'concepts',
        ]))->out(false);
 
        $base = [
            'nav_general_url' => $navgeneral,
            'nav_catalog_url' => $navcatalog,
            'nav_config_url' => $navconfig,
            'active_general' => 1,
            'active_catalog' => 0,
            'active_config' => 0,
 
            'url_activities' => $urlactivities,
            'url_concepts' => $urlconcepts,
            'subview_label' => in_array($this->subview, ['activities', 'concepts'])
                ? ($this->subview === 'activities' ? $s('subview_activities') : $s('subview_concepts'))
                : $s('subview_activities'),
            'subview_activities_active' => $this->subview === 'activities' ? 1 : 0,
            'subview_concepts_active'   => $this->subview === 'concepts'   ? 1 : 0,
 
            'str_nav_general' => $s('nav_general'),
            'str_nav_catalog' => $s('nav_catalog'),
            'str_nav_config' => $s('nav_config'),
            'str_subview_activities' => $s('subview_activities'),
            'str_subview_concepts'   => $s('subview_concepts'),
            'courseid' => $this->courseid,
 
            // Flags de subvista.
            // Mustache solo renderiza #flag si el valor es truthy.
            // PHP false se serializa a 0 en JSON → falsy en Mustache. OK.
            // Pero para mayor seguridad usamos 1/0 explícitos.
            'show_activities' => $this->subview === 'activities'      ? 1 : 0,
            'show_concepts' => $this->subview === 'concepts'        ? 1 : 0,
            'show_vpl_detail' => $this->subview === 'vpl_detail'      ? 1 : 0,
            'show_student_detail' => $this->subview === 'student_detail'  ? 1 : 0,
            'show_concept_detail' => ($this->subview === 'concept_detail' && $this->conceptid > 0) ? 1 : 0,
        ];
 
        // Datos segun subvista.
        if ($this->subview === 'activities') {
            $activities = \local_smart_learning_mentor\application\teacher_report_application::get_activities_data(
                $this->courseid
            );
            $base = array_merge($base, [
                'activities' => self::normalize_activities($activities),
                'has_activities' => !empty($activities) ? 1 : 0,
                'str_col_activity'  => $s('col_activity'),
                'str_col_students' => $s('col_students'),
                'str_col_errors' => $s('col_errors'),
                'str_col_concepts'  => $s('col_concepts'),
                'str_col_resources' => $s('col_resources'),
                'str_col_examples'  => $s('col_examples'),
                'str_col_detail' => $s('col_detail'),
                'str_no_activities' => $s('no_activities'),
                'str_view_detail'   => $s('view_detail'),
            ]);
 
        } else if ($this->subview === 'vpl_detail' && $this->cmid > 0) {
            $data = \local_smart_learning_mentor\application\teacher_report_application::get_vpl_detail_data(
                $this->cmid, $this->courseid
            );
            $backurl = (new moodle_url('/local/smart_learning_mentor/teacher.php', [
                'courseid' => $this->courseid, 'view' => 'general', 'subview' => 'activities',
            ]))->out(false);
            $base = array_merge($base, [
                'vpl' => $data['vpl'],
                'students' => self::normalize_students($data['students']),
                'has_students'  => !empty($data['students']) ? 1 : 0,
                'back_url' => $backurl,
                'str_back' => $s('back_to_activities'),
                'str_col_student'   => $s('col_student'),
                'str_col_requests'  => $s('col_requests'),
                'str_col_last' => $s('col_last_request'),
                'str_col_errors'    => $s('col_errors'),
                'str_col_concepts'  => $s('col_concepts'),
                'str_col_resources' => $s('col_resources'),
                'str_col_examples'  => $s('col_examples'),
                'str_col_detail' => $s('col_detail'),
                'str_view_detail'   => $s('view_detail'),
                'str_no_students'   => $s('no_students'),
            ]);
 
        } else if ($this->subview === 'student_detail' && $this->cmid > 0 && $this->userid > 0) {
            $data = \local_smart_learning_mentor\application\teacher_report_application::get_student_detail_data(
                $this->cmid, $this->courseid, $this->userid
            );
            $backurl = (new moodle_url('/local/smart_learning_mentor/teacher.php', [
                'courseid' => $this->courseid, 'view' => 'general', 'subview' => 'vpl_detail', 'cmid' => $this->cmid,
            ]))->out(false);
            $base = array_merge($base, [
                'vpl' => $data['vpl'],
                'student' => $data['student'],
                'history' => self::normalize_history($data['history']),
                'has_history' => !empty($data['history']) ? 1 : 0,
                'back_url' => $backurl,
                'str_back' => $s('back_to_vpl'),
                'str_no_history' => $s('no_history'),
            ]);
 
        } else if ($this->subview === 'concepts') {
            $temas = \local_smart_learning_mentor\application\teacher_report_application::get_concepts_data(
                $this->courseid
            );
            $urlcatalog = (new \moodle_url('/local/smart_learning_mentor/teacher.php', [
                'courseid' => $this->courseid, 'view' => 'catalog', 'subview' => 'topics',
            ]))->out(false);
            $base = array_merge($base, [
                'temas'      => self::normalize_temas($temas),
                'has_temas'  => !empty($temas) ? 1 : 0,
                'url_catalog'=> $urlcatalog,
                'str_col_concept' => $s('col_concept'),
                'str_col_occurrences' => $s('col_occurrences'),
                'str_col_errors' => $s('col_errors'),
                'str_col_resources'   => $s('col_resources'),
                'str_col_detail' => $s('col_detail'),
                'str_view_detail' => $s('view_detail'),
                'str_concepts_no_catalog' => $s('concepts_no_catalog'),
                'str_concepts_no_catalog_body' => $s('concepts_no_catalog_body'),
                'str_concepts_go_catalog' => $s('concepts_go_catalog'),
            ]);
 
        } else if ($this->subview === 'concept_detail' && $this->conceptid > 0) {
            $conceptoid = $this->conceptid;
            $data = \local_smart_learning_mentor\application\teacher_report_application::get_concept_detail_data(
                $conceptoid, $this->courseid
            );
            $backurl = (new \moodle_url('/local/smart_learning_mentor/teacher.php', [
                'courseid' => $this->courseid,
                'view'     => 'general',
                'subview'  => 'concepts',
            ]))->out(false);
            $base = array_merge($base, [
                'concepto' => $data['concepto'],
                'errors' => $data['errors'],
                'resources' => $data['resources'],
                'has_errors' => $data['has_errors'],
                'has_resources' => $data['has_resources'],
                'back_url' => $backurl,
                'str_back' => $s('back_to_concepts'),
                'str_tema_label' => $s('tema_label'),
                'str_errors_title' => $s('errors_title'),
                'str_resources_title'  => $s('resources_title'),
                'str_recommendation' => $s('recommendation'),
                'str_ia_examples' => $s('ia_examples'),
                'str_occurrences' => $s('occurrences'),
                'str_no_errors' => $s('no_errors_for_concept'),
                'str_no_resources' => $s('no_resources_for_concept'),
            ]);
        }
 
        return $base;
    }
 
    // Normalizadores para Mustache (solo escalares en arrays)
 
    private static function normalize_activities(array $activities): array {
        return array_values(array_map(function($a) {
            return [
                'cmid' => (int)$a['cmid'],
                'name' => (string)$a['name'],
                'url' => (string)$a['url'],
                'detail_url' => (string)$a['detail_url'],
                'student_count' => (int)$a['student_count'],
                'has_data' => !empty($a['has_data']) ? 1 : 0,
                'top_errors' => array_values(array_map(fn($e) => [
                    'titulo' => (string)$e['titulo'],
                    'count'  => (int)$e['count'],
                ], $a['top_errors']  ?? [])),
                'top_concepts'  => array_values(array_map(fn($c) => [
                    'nombre' => (string)$c['nombre'],
                    'count'  => (int)$c['count'],
                ], $a['top_concepts'] ?? [])),
                'top_resources' => array_values(array_map(fn($r) => [
                    'titulo' => (string)$r['titulo'],
                    'count'  => (int)$r['count'],
                ], $a['top_resources'] ?? [])),
                'top_examples'  => array_values(array_map(fn($e) => [
                    'titulo' => (string)$e['titulo'],
                    'count'  => (int)$e['count'],
                ], $a['top_examples']  ?? [])),
            ];
        }, $activities));
    }
 
    private static function normalize_students(array $students): array {
        return array_values(array_map(function($s) {
            return [
                'userid' => (int)$s['userid'],
                'fullname' => (string)$s['fullname'],
                'solicitud_count' => (int)$s['solicitud_count'],
                'last_request'    => (string)$s['last_request'],
                'detail_url' => (string)$s['detail_url'],
                'top_errors' => array_values(array_map(fn($e) => ['titulo' => (string)$e['titulo']], $s['top_errors']    ?? [])),
                'top_concepts' => array_values(array_map(fn($c) => ['nombre' => (string)$c['nombre']], $s['top_concepts']  ?? [])),
                'top_resources' => array_values(array_map(fn($r) => ['titulo' => (string)$r['titulo']], $s['top_resources'] ?? [])),
                'top_examples' => array_values(array_map(fn($e) => ['titulo' => (string)$e['titulo']], $s['top_examples']  ?? [])),
            ];
        }, $students));
    }
 
    private static function normalize_temas(array $temas): array {
        return array_values(array_map(function($tema) {
            $count = count($tema['conceptos']);
            $conceptos = array_values(array_map(function($c) {
                return [
                    'conceptoid' => (int)$c['conceptoid'],
                    'nombre' => (string)$c['nombre'],
                    'occurrences' => (int)$c['occurrences'],
                    'has_data' => (int)$c['has_data'],
                    'has_errors' => (int)$c['has_errors'],
                    'has_resources' => (int)$c['has_resources'],
                    'detail_url' => (string)$c['detail_url'],
                    'top_errors' => array_values(array_map(fn($e) => [
                        'titulo' => (string)$e['titulo'],
                        'count'  => (int)$e['count'],
                    ], $c['top_errors'])),
                    'resources'     => array_values(array_map(fn($r) => [
                        'titulo' => (string)$r['titulo'],
                        'url'    => (string)$r['url'],
                    ], $c['resources'])),
                ];
            }, $tema['conceptos']));
            return [
                'temaid' => (int)$tema['temaid'],
                'nombre' => (string)$tema['nombre'],
                'count' => $count,
                'count_plural' => $count !== 1 ? 1 : 0,
                'conceptos' => $conceptos,
            ];
        }, $temas));
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
                            'titulo'  => (string)$r['titulo'],
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
                        'has_codigo' => !empty($ej['codigo'])             ? 1 : 0,
                        'has_resultado' => !empty($ej['resultado_esperado']) ? 1 : 0,
                    ], $e['examples'] ?? [])),
                ];
            }, $h['errors_enriched'] ?? []));
 
            return [
                'solicitudid'    => (int)$h['solicitudid'],
                'numero' => (int)$h['numero'],
                'fecha' => (string)$h['fecha'],
                'student_message'=> (string)($h['student_message'] ?? ''),
                'has_errors'     => !empty($errors_enriched) ? 1 : 0,
                'errors_enriched'=> $errors_enriched,
            ];
        }, $history));
    }
 
    /*public function get_template_name(renderer_base $renderer): string {
        return 'local_smart_learning_mentor/teacher/general';
    }*/
}
 