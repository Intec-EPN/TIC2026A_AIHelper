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
 * Callbacks requeridos por Moodle para el plugin local_smart_learning_mentor.
 *
 * Descripción:
 *   Este archivo contiene únicamente los hooks y callbacks que Moodle
 *   llama automáticamente. No debe contener lógica de negocio.
 *
 * Descripción del flujo:
 *   1. Moodle carga cualquier página del sistema.
 *   2. Moodle llama a local_smart_learning_mentor_extend_settings_navigation()
 *      para inyectar el enlace de reportes en el menú de configuración del curso.
 *   3. El hook before_footer_html_generation inyecta el panel flotante del estudiante
 *      en todas las páginas (excepto login), lo gestiona hooks/output/before_footer_html_generation.php.
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

use core\navigation\settings_navigation;
use navigation_node;
use moodle_url;
use pix_icon;
use context;
use context_course;

/**
 * 1. Carga el JS y CSS segun la pagina actual.
 *
 * @param global_navigation $nav
 */
function local_smart_learning_mentor_extend_navigation(global_navigation $nav) {
    global $PAGE;

    $pageurl = $PAGE->url->out(false);

    // 2. Paginas de actividades VPL: cargar panel flotante.
    if (strpos($pageurl, '/mod/vpl/') !== false) {
        $PAGE->requires->css('/local/smart_learning_mentor/styles.css');
        $PAGE->requires->js_call_amd('local_smart_learning_mentor/student_panel', 'init');
        return;
    }

    // 3. Paginas del plugin del profesor.
    if (strpos($pageurl, '/local/smart_learning_mentor/teacher.php') !== false) {
        $view    = optional_param('view',    'general',    PARAM_ALPHA);
        $subview = optional_param('subview', 'activities', PARAM_ALPHA);

        if ($view === 'config') {
            $PAGE->requires->js_call_amd('local_smart_learning_mentor/teacher_configuration', 'init');
        }

        if ($view === 'catalog' && $subview === 'topics') {
            $PAGE->requires->js_call_amd('local_smart_learning_mentor/teacher_topics', 'init');
        }

        if ($view === 'catalog' && $subview === 'resources') {
            $PAGE->requires->js_call_amd('local_smart_learning_mentor/teacher_resources', 'init');
        }
        // Colocar siguiendo la estructura definida (futuro)
        /*if ($view === 'general' && $subview === 'student_detail') {
            $PAGE->requires->js_call_amd('local_smart_learning_mentor/teacher_student_detail', 'init');
        }*/
    }
}

/**
 * 2. Agrega el enlace del plugin al menu de configuracion del curso.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 */
function local_smart_learning_mentor_extend_settings_navigation(
    settings_navigation $settingsnav,
    context $context
) {
    global $COURSE;

    if (empty($COURSE) || $COURSE->id == SITEID) {
        return;
    }

    $coursecontext = context_course::instance($COURSE->id);

    if (!has_capability('local/smart_learning_mentor:viewreport', $coursecontext)) {
        return;
    }

    $url = new moodle_url('/local/smart_learning_mentor/index.php', [
        'courseid' => $COURSE->id,
    ]);

    $coursenode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE);

    if ($coursenode) {
        $coursenode->add(
            get_string('pluginname', 'local_smart_learning_mentor'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'smart_learning_mentor_report',
            new pix_icon('i/report', '')
        );
    }
}
