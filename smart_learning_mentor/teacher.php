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
 * Página principal del profesor local_smart_learning_mentor.
 *
 * Descripción:
 *   Punto de entrada para las vistas del profesor. Recibe parámetros de la URL,
 *   verifica permisos y delega al controlador del profesor.
 *   No contiene lógica de negocio ni consultas a la BD.
 *
 * Flujo:
 *   1. El profesor llega desde index.php (o por URL).
 *   2. Se leen los parámetros de la URL (courseid, view, filtros...).
 *   3. Se verifica autenticación y capacidad managereport.
 *   4. Se delega al teacher_controller para obtener los datos y la plantilla.
 *   5. Se renderiza la vista con $OUTPUT->render_from_template().
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Cargar el núcleo de Moodle
require_once('../../config.php');

// 1. Recibir parametros de la URL.
$courseid = required_param('courseid', PARAM_INT);
$view     = optional_param('view', 'dashboard', PARAM_ALPHA);
$subview  = optional_param('subview', 'activities', PARAM_ALPHANUMEXT);
$cmid     = optional_param('cmid', 0, PARAM_INT);
$userid   = optional_param('userid', 0, PARAM_INT);
$conceptid = optional_param('conceptid', 0, PARAM_INT);


// Validar valores permitidos.
$validviews    = ['general', 'catalog', 'config'];
$validsubviews = ['activities', 'concepts', 'topics', 'resources', 'vpl_detail', 'student_detail', 'concept_detail'];
if (!in_array($view,    $validviews))    { $view    = 'general';    }
if (!in_array($subview, $validsubviews)) { $subview = 'activities'; }

/*var_dump($_GET['subview'] ?? null);
var_dump($subview, $cmid, $conceptid);
die();*/

// 2. Cargar el curso y establecer el contexto.
$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

// 3. Verificar que el usuario sea profesor.
require_login($course);
require_capability('local/smart_learning_mentor:managereport', $context);

// 4. Configurar la página de Moodle.
$PAGE->set_context($context);
$PAGE->set_url('/local/smart_learning_mentor/teacher.php', [
    'courseid' => $courseid,
    'view'     => $view,
    'subview'  => $subview,
    'cmid'     => $cmid,
    'userid'   => $userid,
    'conceptid' => $conceptid,
]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_smart_learning_mentor'));
$PAGE->set_heading(format_string($course->fullname));

// 5. Delegar al controlador del profesor para obtener los datos de la vista.
$controller = new \local_smart_learning_mentor\controllers\teacher_controller($courseid, $context);

echo $OUTPUT->header();

$renderable  = $controller->handle($view, $subview, $cmid, $userid, $conceptid);
// 6. Obtener datos y determinar el template.
$data = $renderable->export_for_template($OUTPUT);

$templates = [
    'general' => 'local_smart_learning_mentor/teacher/general',
    'catalog' => 'local_smart_learning_mentor/teacher/catalog',
    'config'  => 'local_smart_learning_mentor/teacher/configuration',
];

$template = $templates[$view] ?? $templates['general'];

// 7. Renderizar.
echo $OUTPUT->render_from_template($template, $data);
// echo $OUTPUT->render($renderable);
echo $OUTPUT->footer();
