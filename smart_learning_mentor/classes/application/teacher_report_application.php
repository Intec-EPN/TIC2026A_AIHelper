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
 * Caso de uso: preparar los datos del reporte del profesor.
 *
 * Descripción del flujo:
 *   1. teacher_controller llama a get_general_data() con courseid y subview.
 *   2. La application llama a report_storage para obtener los datos.
 *   3. Procesa y estructura los datos para el output.
 *   4. El output los adapta para Mustache.
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\application;

defined('MOODLE_INTERNAL') || die();

class teacher_report_application {

    /**
     * 1. Datos para la tabla resumen de actividades VPL.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_activities_data(int $courseid): array {
        $raw = \local_smart_learning_mentor\storage\report_storage::get_vpl_activities_report($courseid);

        return array_map(function($a) use ($courseid) {
            $detailurl = (new \moodle_url('/local/smart_learning_mentor/teacher.php', [
                'courseid' => $courseid,
                'view'     => 'general',
                'subview'  => 'vpl_detail',
                'cmid'     => $a['cmid'],
            ]))->out(false);

            return array_merge($a, ['detail_url' => $detailurl]);
        }, $raw);
    }

    /**
     * 2. Datos para la vista detalle de un VPL (lista de estudiantes).
     *
     * @param int $cmid
     * @param int $courseid
     * @return array
     */
    public static function get_vpl_detail_data(int $cmid, int $courseid): array {
        $vplinfo = \local_smart_learning_mentor\storage\report_storage::get_vpl_info($cmid, $courseid);
        if (!$vplinfo) {
            return ['vpl' => null, 'students' => []];
        }

        $students = \local_smart_learning_mentor\storage\report_storage::get_students_for_vpl(
            $vplinfo['vplid'], $courseid
        );

        // Agregar URL de detalle de cada estudiante.
        $students = array_map(function($s) use ($cmid, $courseid) {
            $s['detail_url'] = (new \moodle_url('/local/smart_learning_mentor/teacher.php', [
                'courseid' => $courseid,
                'view'     => 'general',
                'subview'  => 'student_detail',
                'cmid'     => $cmid,
                'userid'   => $s['userid'],
            ]))->out(false);
            return $s;
        }, $students);

        return ['vpl' => $vplinfo, 'students' => $students];
    }

    /**
     * 3. Datos para la vista detalle de un estudiante en un VPL.
     *
     * @param int $cmid
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function get_student_detail_data(int $cmid, int $courseid, int $userid): array {
        global $DB;

        $vplinfo = \local_smart_learning_mentor\storage\report_storage::get_vpl_info($cmid, $courseid);
        if (!$vplinfo) {
            return ['vpl' => null, 'student' => null, 'history' => []];
        }

        $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname', IGNORE_MISSING);
        $history = \local_smart_learning_mentor\storage\report_storage::get_student_history(
            $vplinfo['vplid'], $courseid, $userid
        );

        return [
            'vpl'     => $vplinfo,
            'student' => $user ? ['userid' => $userid, 'fullname' => fullname($user)] : null,
            'history' => $history,
        ];
    }

    /**
     * 4. Datos para la vista de Conceptos organizados por tema.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_concepts_data(int $courseid): array {
        $temas = \local_smart_learning_mentor\storage\report_storage::get_concepts_report($courseid);

        return array_map(function($tema) use ($courseid) {
            $tema['conceptos'] = array_map(function($c) {
                return [
                    'conceptoid'    => (int)$c['conceptoid'],
                    'nombre'        => (string)$c['nombre'],
                    'occurrences'   => (int)$c['occurrences'],
                    'has_data'      => $c['has_data'],
                    'detail_url'    => (string)$c['detail_url'],
                    'has_errors'    => $c['has_errors'],
                    'has_resources' => $c['has_resources'],
                    'top_errors'    => array_values(array_map(fn($e) => [
                        'titulo' => (string)$e['titulo'],
                        'count'  => (int)$e['count'],
                    ], $c['top_errors'])),
                    'resources'     => array_values(array_map(fn($r) => [
                        'titulo' => (string)$r['titulo'],
                        'url'    => (string)$r['url'],
                        'tipo'   => (string)$r['tipo'],
                    ], $c['resources'])),
                ];
            }, $tema['conceptos']);
            return $tema;
        }, $temas);
    }

    /**
     * 5. Datos para la vista detalle de un concepto específico.
     *
     * @param int $conceptoid
     * @param int $courseid
     * @return array
     */
    public static function get_concept_detail_data(int $conceptoid, int $courseid): array {
        $raw = \local_smart_learning_mentor\storage\report_storage::get_concept_detail($conceptoid, $courseid);
        if (empty($raw)) {
            return ['concepto' => null, 'errors' => [], 'resources' => []];
        }

        $errors = array_map(function($e) {
            return [
                'titulo'        => (string)$e['titulo'],
                'severidad'     => (string)$e['severidad'],
                'porcentaje'    => (float)$e['porcentaje'],
                'ocurrencias'   => (int)$e['ocurrencias'],
                'descripcion'   => (string)$e['descripcion'],
                'recomendacion' => (string)$e['recomendacion'],
                'has_ejemplos'  => !empty($e['ejemplos']) ? 1 : 0,
                'ejemplos'      => array_values(array_map(fn($ej) => [
                    'titulo' => (string)$ej['titulo'],
                    'codigo' => (string)$ej['codigo'],
                ], $e['ejemplos'])),
            ];
        }, $raw['errors']);

        $resources = array_values(array_map(fn($r) => [
            'titulo' => (string)$r['titulo'],
            'url'    => (string)$r['url'],
            'tipo'   => (string)$r['tipo'],
        ], $raw['resources']));

        return [
            'concepto'  => $raw['concepto'],
            'errors'    => $errors,
            'resources' => $resources,
            'has_errors'    => !empty($errors) ? 1 : 0,
            'has_resources' => !empty($resources) ? 1 : 0,
        ];
    }

}
