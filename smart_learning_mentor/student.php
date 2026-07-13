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
 * Pagina principal del estudiante
 *
 * Descripcion
 *   Punto de entrada para las vistas del estudiante. Recibe param de la URL,
 *   verifica permisos y delega al controlador del estudiante
 *
 * Flujo:
 *   1. El estudiante llega desde index.php (o por URL).
 *   2. Se leen los parámetros de la URL (courseid, view...).
 *   3. Se verifica autenticación y capacidad viewreport.
 *   4. Se delega al student_controller para obtener los datos y la plantilla.
 *   5. Se renderiza la vista con $OUTPUT->render_from_template().
 *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Cargar el nucleo de Moodle
require_once('../../config.php');

// 1. Recibir param de la URL
$courseid = required_param('courseid', PARAM_INT);

$subview  = optional_param('subview', 'concepts', PARAM_ALPHANUMEXT);
$vplid    = optional_param('vplid', 0, PARAM_INT);
$conceptid = optional_param('conceptid', 0, PARAM_INT);

/*var_dump($_GET['subview'] ?? null);
var_dump($subview, $conceptid);
die();
*/

if (!in_array($subview, ['concepts', 'activities', 'history', 'concept_detail'])) {
    $subview = 'concepts';
}
if ($subview === 'history' && $vplid === 0) {
    $subview = 'activities';
}
if ($subview === 'concept_detail' && $conceptid === 0) {
    $subview = 'concepts';
}

// 2. cargar el curso y establecer el contexto
$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

// 3. verificar autenticaicon y permiso de vista
require_login($course);
require_capability('local/smart_learning_mentor:viewreport', $context);

// 4. configurar la pag de Moodle
$PAGE->requires->css('/local/smart_learning_mentor/styles.css');

$PAGE->set_context($context);
$PAGE->set_url('/local/smart_learning_mentor/student.php', [
    'courseid' => $courseid,
    'subview'  => $subview,
    'vplid'    => $vplid,
    'conceptid' => $conceptid
]);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('pluginname', 'local_smart_learning_mentor'));
$PAGE->set_heading(format_string($course->fullname));

// 5. delegar al controlador del estudiante para obtener los datos
// $controller = new \local_smart_learning_mentor\controllers\student_controller($courseid, $context);
// $renderable  = $controller->handle();

$renderable = new \local_smart_learning_mentor\output\student\dashboard(
    $courseid, (int)$USER->id, $subview, $vplid, $conceptid
);

// 6. renderizar la plantilla del estudiante
echo $OUTPUT->header();

$data = $renderable->export_for_template($OUTPUT);

echo $OUTPUT->render_from_template(
    'local_smart_learning_mentor/student/dashboard',
    $data
);

// echo $OUTPUT->render($renderable);
echo $OUTPUT->footer();

