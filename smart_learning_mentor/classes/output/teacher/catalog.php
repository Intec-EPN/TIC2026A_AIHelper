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
 * Output para la vista Catalogo del profesor (Temas y Conceptos | Recursos).
 *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\output\teacher;

defined('MOODLE_INTERNAL') || die();

use renderable; use templatable; use renderer_base; use moodle_url;

class catalog implements renderable, templatable {
    private int $courseid;
    private string $subview;

    public function __construct(int $courseid, string $subview = 'topics') {
        $this->courseid = $courseid;
        $this->subview  = $subview;
    }

    public function export_for_template(renderer_base $output): array {
        $s = fn(string $k) => get_string($k, 'local_smart_learning_mentor');

        $navgeneral = (new moodle_url('/local/smart_learning_mentor/teacher.php', ['courseid' => $this->courseid, 'view' => 'general', 'subview' => 'activities']))->out(false);
        $navcatalog = (new moodle_url('/local/smart_learning_mentor/teacher.php', ['courseid' => $this->courseid, 'view' => 'catalog', 'subview' => 'topics']))->out(false);
        $navconfig  = (new moodle_url('/local/smart_learning_mentor/teacher.php', ['courseid' => $this->courseid, 'view' => 'config']))->out(false);
        $urltopics = (new moodle_url('/local/smart_learning_mentor/teacher.php', ['courseid' => $this->courseid, 'view' => 'catalog', 'subview' => 'topics']))->out(false);
        $urlresources = (new moodle_url('/local/smart_learning_mentor/teacher.php', ['courseid' => $this->courseid, 'view' => 'catalog', 'subview' => 'resources']))->out(false);

        // Datos segun subvista activa.
        $topicsdata   = [];
        $resourcedata = [];

        if ($this->subview === 'topics') {
            $rawdata = \local_smart_learning_mentor\application\topic_application::get_page_data($this->courseid);
            $topicsdata = [
                'topics' => self::normalize_topics_for_mustache($rawdata['topics'] ?? []),
                'topics_json' => htmlspecialchars(json_encode($rawdata['topics'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'),
                'has_topics' => !empty($rawdata['topics']),
                'ai_concepts' => self::normalize_ai_concepts($rawdata['ai_concepts'] ?? []),
                'has_ai_concepts'  => !empty($rawdata['ai_concepts']),
                'ai_concept_count' => count($rawdata['ai_concepts'] ?? []),
            ];
        }

        if ($this->subview === 'resources') {
            $rawdata = \local_smart_learning_mentor\application\resource_application::get_page_data($this->courseid);
            $resourcedata = [
                'has_concepts' => $rawdata['has_concepts'],
                'has_modules' => $rawdata['has_modules'],
                'total_modules'  => $rawdata['total_modules'],
                'course_modules' => self::normalize_modules_for_mustache($rawdata['course_modules'] ?? []),
                'topics' => self::normalize_topics_simple($rawdata['topics'] ?? []),
                'url_topics' => $urltopics,
            ];
        }

        return array_merge([
            // Navegacion.
            'nav_general_url' => $navgeneral,
            'nav_catalog_url' => $navcatalog,
            'nav_config_url'  => $navconfig,
            'active_general'  => false,
            'active_catalog'  => true,
            'active_config' => false,
            'url_topics' => $urltopics,
            'url_resources' => $urlresources,
            'subview_label' => $this->subview === 'topics' ? $s('subview_topics') : $s('subview_resources'),
            'subview_topics_active'    => ($this->subview === 'topics'),
            'subview_resources_active' => ($this->subview === 'resources'),
            'show_topics' => ($this->subview === 'topics'),
            'show_resources' => ($this->subview === 'resources'),
            'courseid' => $this->courseid,

            // Cadenas navegacion.
            'str_nav_general'  => $s('nav_general'),
            'str_nav_catalog'  => $s('nav_catalog'),
            'str_nav_config' => $s('nav_config'),
            'str_subview_topics' => $s('subview_topics'),
            'str_subview_resources' => $s('subview_resources'),

            // Cadenas temas.
            'str_topics_title' => $s('topics_title'),
            'str_topics_subtitle' => $s('topics_subtitle'),
            'str_topics_edit_mode' => $s('topics_edit_mode'),
            'str_topics_add_theme' => $s('topics_add_theme'),
            'str_topics_no_topics' => $s('topics_no_topics'),
            'str_topics_ai_panel_title' => $s('topics_ai_panel_title'),
            'str_topics_ai_panel_sub' => $s('topics_ai_panel_sub'),
            'str_topics_ai_add_btn' => $s('topics_ai_add_btn'),
            'str_topics_ai_empty' => $s('topics_ai_empty'),
            'str_topics_theme_edit' => $s('topics_theme_edit'),
            'str_topics_theme_delete' => $s('topics_theme_delete'),
            'str_topics_add_concept' => $s('topics_add_concept'),
            'str_topics_concept_placeholder' => $s('topics_concept_placeholder'),
            'str_topics_save_concept' => $s('topics_save_concept'),
            'str_topics_concept_delete' => $s('topics_concept_delete'),
            'str_topics_theme_name_label' => $s('topics_theme_name_label'),
            'str_topics_theme_name_placeholder' => $s('topics_theme_name_placeholder'),
            'str_topics_save_theme' => $s('topics_save_theme'),
            'str_topics_cancel' => $s('topics_cancel'),
            'str_topics_modal_title' => $s('topics_modal_title'),
            'str_topics_modal_theme_label' => $s('topics_modal_theme_label'),
            'str_topics_modal_select_theme' => $s('topics_modal_select_theme'),
            'str_topics_modal_concept_label'    => $s('topics_modal_concept_label'),
            'str_topics_modal_concept_placeholder' => $s('topics_modal_concept_placeholder'),
            'str_topics_modal_save' => $s('topics_modal_save'),
            'str_topics_modal_cancel' => $s('topics_modal_cancel'),

            // Cadenas recursos.
            'str_resources_no_concepts_title' => $s('resources_no_concepts_title'),
            'str_resources_no_concepts_body'  => $s('resources_no_concepts_body'),
            'str_resources_go_to_topics' => $s('resources_go_to_topics'),
            'str_resources_toggle_sidebar' => $s('resources_toggle_sidebar'),
            'str_resources_sidebar_title' => $s('resources_sidebar_title'),
            'str_resources_sidebar_sub' => $s('resources_sidebar_sub'),
            'str_resources_main_title' => $s('resources_main_title'),
            'str_resources_main_sub' => $s('resources_main_sub'),
            'str_resources_elements' => $s('resources_elements'),
            'str_resources_no_modules' => $s('resources_no_modules'),
            'str_resources_associate_btn' => $s('resources_associate_btn'),
            'str_resources_modal_title' => $s('resources_modal_title'),
            'str_resources_modal_subtitle' => $s('resources_modal_subtitle'),
            'str_resources_selected_resource' => $s('resources_selected_resource'),
            'str_resources_modal_concepts' => $s('resources_modal_concepts'),
            'str_resources_modal_search' => $s('resources_modal_search'),
            'str_resources_modal_summary' => $s('resources_modal_summary'),
            'str_resources_modal_selected' => $s('resources_modal_selected'),
            'str_resources_modal_none' => $s('resources_modal_none'),
            'str_resources_modal_cancel' => $s('resources_modal_cancel'),
            'str_resources_modal_save' => $s('resources_modal_save'),
        ], $topicsdata, $resourcedata);
    }

    private static function normalize_topics_for_mustache(array $topics): array {
        return array_values(array_map(function($topic) {
            $concepts = array_values(array_map(fn($c) => [
                'id' => (int)($c['id'] ?? 0),
                'nombre' => (string)($c['name'] ?? $c['nombre'] ?? ''),
                'descripcion' => (string)($c['description'] ?? $c['descripcion'] ?? ''),
            ], $topic['concepts'] ?? []));
            $count = count($concepts);
            return [
                'id' => (int)($topic['id'] ?? 0),
                'name' => (string)($topic['name'] ?? ''),
                'description'   => (string)($topic['description'] ?? ''),
                'concept_count' => $count,
                'concept_label' => $count === 1 ? get_string('concept_singular', 'local_smart_learning_mentor') : get_string('concept_plural', 'local_smart_learning_mentor'),
                'concepts' => $concepts,
                'has_concepts'  => !empty($concepts),
            ];
        }, $topics));
    }

    private static function normalize_topics_simple(array $topics): array {
        return array_values(array_map(function($topic) {
            return [
                'id' => (int)($topic['id'] ?? 0),
                'name' => (string)($topic['name'] ?? ''),
                'concepts' => array_values(array_map(fn($c) => [
                    'id'   => (int)($c['id'] ?? 0),
                    'name' => (string)($c['name'] ?? ''),
                ], $topic['concepts'] ?? [])),
            ];
        }, $topics));
    }

    private static function normalize_modules_for_mustache(array $sections): array {
        return array_values(array_map(function($section) {

            // Normaliza un array de items de modulo.
            $normalizeitems = function(array $rawItems): array {
                return array_values(array_map(function($item) {
                    $count = (int)($item['concept_count'] ?? 0);
                    return [
                        'id' => (int)($item['id'] ?? 0),
                        'cmid' => (int)($item['cmid'] ?? 0),
                        'courseid' => (int)($item['courseid'] ?? 0),
                        'titulo' => (string)($item['titulo'] ?? ''),
                        'typelabel' => (string)($item['typelabel'] ?? ''),
                        'icon' => (string)($item['icon'] ?? ''),
                        'concept_count' => $count,
                        'concept_count_plural' => $count !== 1,
                        'conceptids_csv'   => (string)($item['conceptids_csv'] ?? ''),
                        'associated_concepts' => array_values(array_map(fn($c) => [
                            'id' => (int)($c['id'] ?? 0),
                            'nombre' => (string)($c['nombre'] ?? ''),
                        ], $item['associated_concepts'] ?? [])),
                    ];
                }, $rawItems));
            };

            // Items directos de la seccion (sin subseccion).
            $directitems = $normalizeitems($section['direct_items'] ?? []);

            // Subsecciones con sus items.
            $subsections = array_values(array_map(function($sub) use ($normalizeitems) {
                $items = $normalizeitems($sub['items'] ?? []);
                return [
                    'subsectionid'   => (int)($sub['subsectionid'] ?? 0),
                    'subsectionname' => (string)($sub['subsectionname'] ?? ''),
                    'items' => $items,
                    'item_count' => count($items),
                    'has_items' => !empty($items),
                ];
            }, $section['subsections'] ?? []));

            $totalitems = count($directitems) + array_sum(array_column($subsections, 'item_count'));

            return [
                'sectionid' => (int)($section['sectionid'] ?? 0),
                'sectionnum' => (int)($section['sectionnum'] ?? 0),
                'sectionname' => (string)($section['sectionname'] ?? ''),
                'item_count' => $totalitems,
                'has_direct' => !empty($directitems),
                'direct_items' => $directitems,
                'has_subsections' => !empty($subsections),
                'subsections' => $subsections,
            ];
        }, $sections));
    }

    private static function normalize_ai_concepts(array $aiconcepts): array {
        return array_values(array_map(fn($item) => [
            'nombre' => (string)($item['nombre'] ?? ''),
            'count'  => (int)($item['count'] ?? 0),
            'ids' => json_encode($item['ids'] ?? [], JSON_UNESCAPED_UNICODE),
        ], $aiconcepts));
    }

    /*public function get_template_name(renderer_base $renderer): string {
        return 'local_smart_learning_mentor/teacher/catalog';
    }*/
}

