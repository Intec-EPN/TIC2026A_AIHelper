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
 * Output para la vista Configuracion del profesor.
 *
 * Descripcion del flujo:
 *   1. teacher_controller retorna este renderable.
 *   2. teacher.php llama a export_for_template().
 *   3. export_for_template() llama a configuration_application::get_page_data().
 *   4. Moodle pasa los datos a templates/teacher/configuration.mustache.
 *   5. amd/src/teacher_configuration.js controla la interaccion (guardar).
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

class configuration implements renderable, templatable {

    private int $courseid;

    public function __construct(int $courseid) {
        $this->courseid = $courseid;
    }

    /**
     * 1. Prepara todos los datos para la plantilla teacher/configuration.mustache.
     *

     */
    public function export_for_template(renderer_base $output): array {
        $s = fn(string $k) => get_string($k, 'local_smart_learning_mentor');

        // 2. URLs de navegacion principal.
        $navgeneral = (new moodle_url('/local/smart_learning_mentor/teacher.php', [
            'courseid' => $this->courseid, 'view' => 'general', 'subview' => 'activities',
        ]))->out(false);
        $navcatalog = (new moodle_url('/local/smart_learning_mentor/teacher.php', [
            'courseid' => $this->courseid, 'view' => 'catalog', 'subview' => 'topics',
        ]))->out(false);
        $navconfig  = (new moodle_url('/local/smart_learning_mentor/teacher.php', [
            'courseid' => $this->courseid, 'view' => 'config',
        ]))->out(false);

        // 3. Obtener datos de VPLs y configuraciones desde la application.
        $pagedata = \local_smart_learning_mentor\application\configuration_application::get_page_data(
            $this->courseid
        );

        return [
            // Navegacion.
            'nav_general_url' => $navgeneral,
            'nav_catalog_url' => $navcatalog,
            'nav_config_url' => $navconfig,
            'active_general' => false,
            'active_catalog'  => false,
            'active_config' => true,

            // Cadenas de idioma para el template.
            'str_nav_general' => $s('nav_general'),
            'str_nav_catalog' => $s('nav_catalog'),
            'str_nav_config' => $s('nav_config'),

            // Cadenas de configuracion.
            'str_config_title' => $s('config_title'),
            'str_config_subtitle' => $s('config_subtitle'),
            'str_config_save_btn' => $s('config_save_btn'),
            'str_config_default_info' => $s('config_default_info'),
            'str_config_default_minenvios'  => $s('config_default_minenvios'),
            'str_config_default_maxsolic' => $s('config_default_maxsolic'),
            'str_config_filters_title' => $s('config_filters_title'),
            'str_config_filters_show' => $s('config_filters_show'),
            'str_config_search_label' => $s('config_search_label'),
            'str_config_search_placeholder' => $s('config_search_placeholder'),
            'str_config_section_label'    => $s('config_section_label'),
            'str_config_section_all' => $s('config_section_all'),
            'str_config_reset_filters'    => $s('config_reset_filters'),
            'str_config_table_title' => $s('config_table_title'),
            'str_config_table_subtitle'   => $s('config_table_subtitle'),
            'str_config_col_activity' => $s('config_col_activity'),
            'str_config_col_help' => $s('config_col_help'),
            'str_config_col_resources'    => $s('config_col_resources'),
            'str_config_col_examples' => $s('config_col_examples'),
            'str_config_col_minenvios' => $s('config_col_minenvios'),
            'str_config_col_maxsolic' => $s('config_col_maxsolic'),
            'str_config_open_vpl' => $s('config_open_vpl'),
            'str_config_no_vpls' => $s('config_no_vpls'),
            'str_config_help_tooltip' => $s('config_help_tooltip'),
            'str_config_resources_tooltip' => $s('config_resources_tooltip'),
            'str_config_examples_tooltip' => $s('config_examples_tooltip'),
            'str_config_minenvios_tooltip' => $s('config_minenvios_tooltip'),
            'str_config_maxsolic_tooltip' => $s('config_maxsolic_tooltip'),
            'str_config_saving' => $s('config_saving'),
            'str_config_saved_ok' => $s('config_saved_ok'),
            'str_config_save_error' => $s('config_save_error'),

            // Datos de VPLs.
            'courseid' => $this->courseid,
            'vplactivities' => $pagedata['vplactivities'],
            'vplactivitycount' => $pagedata['vplactivitycount'],
            'section_options' => $pagedata['section_options'],
            'has_vpls' => $pagedata['has_vpls'],
        ];
    }

    /*public function get_template_name(renderer_base $renderer): string {
        return 'local_smart_learning_mentor/teacher/configuration';
    }*/
}
