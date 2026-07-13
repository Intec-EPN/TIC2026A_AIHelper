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

namespace local_smart_learning_mentor\application;

defined('MOODLE_INTERNAL') || die();

class resource_application {

    /**
     * 1. Obtiene todos los datos para la pagina de Recursos.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_page_data(int $courseid): array {
        $topics = \local_smart_learning_mentor\storage\topic_storage::get_course_topics($courseid);

        // Verificar si hay conceptos.
        $totalconcepts = 0;
        foreach ($topics as $topic) {
            $totalconcepts += count($topic['concepts'] ?? []);
        }
        $hasconcepts = $totalconcepts > 0;

        if (!$hasconcepts) {
            return [
                'has_concepts'   => false,
                'has_modules'    => false,
                'topics'         => [],
                'course_modules' => [],
                'total_modules'  => 0,
            ];
        }

        $modulestree = \local_smart_learning_mentor\storage\resource_storage::get_course_modules_tree($courseid);

        // Contar total de modulos.
        $totalmodules = 0;
        foreach ($modulestree as $section) {
            $totalmodules += count($section['direct_items'] ?? []);
            foreach ($section['subsections'] ?? [] as $sub) {
                $totalmodules += count($sub['items'] ?? []);
            }
        }

        $topicsformustache = array_map(fn($topic) => [
            'id'       => (int)($topic['id'] ?? 0),
            'name'     => (string)($topic['name'] ?? ''),
            'concepts' => array_map(fn($c) => [
                'id'   => (int)($c['id'] ?? 0),
                'name' => (string)($c['name'] ?? ''),
            ], $topic['concepts'] ?? []),
        ], $topics);

        return [
            'has_concepts'   => true,
            'has_modules'    => $totalmodules > 0,
            'topics'         => $topicsformustache,
            'course_modules' => $modulestree,
            'total_modules'  => $totalmodules,
        ];
    }

    /**
     * 2. Guarda las asociaciones entre un modulo del curso y conceptos.
     *
     * @param int    $courseid
     * @param int    $cmid
     * @param string $titulo
     * @param array  $conceptids
     * @return array
     */
    public static function save_resource_concepts(
        int    $courseid,
        int    $cmid,
        string $titulo,
        array  $conceptids
    ): array {
        $recursoid = \local_smart_learning_mentor\storage\resource_storage::upsert_course_resource(
            $courseid, $cmid, $titulo
        );

        if ($recursoid <= 0) {
            return [
                'success'   => false,
                'message'   => get_string('error_resource_save', 'local_smart_learning_mentor'),
                'concepts'  => [],
                'recursoid' => 0,
            ];
        }

        $conceptids = array_values(array_unique(array_filter(array_map('intval', $conceptids))));
        \local_smart_learning_mentor\storage\resource_storage::sync_resource_concepts($recursoid, $conceptids);

        $concepts = \local_smart_learning_mentor\storage\resource_storage::get_concepts_by_resource($recursoid);

        return [
            'success'   => true,
            'message'   => get_string('success_resource_saved', 'local_smart_learning_mentor'),
            'concepts'  => $concepts,
            'recursoid' => $recursoid,
        ];
    }
}

