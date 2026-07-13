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
 * Punto de entrada principal del plugin local_smart_learning_mentor.
 *
 * Descripción del archivo:
 *   Recibe la petición del usuario, verifica permisos y redirige
 *   a teacher.php o student.php según el rol detectado.
 *   No contiene lógica de negocio ni consultas a la BD.
 *
 * Flujo:
 *   1. El usuario hace clic en el enlace del plugin en la navegación del curso.
 *   2. index.php recibe el parámetro courseid.
 *   3. Se verifica que el usuario esté autenticado y tenga permisos.
 *   4. Si tiene rol de profesor (managereport)-> redirige a teacher.php.
 *   5. Si tiene rol de estudiante (viewreport) -> redirige a student.php.
 *   6. Si no tiene ningún permiso -> muestra mensaje de error.
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Cargar el nucleo de Moodle
require_once('../../config.php');

// 1. Obtener el parámetro obligatorio del curso.
$courseid = required_param('courseid', PARAM_INT);

// 2. Cargar el curso y establecer el contexto.
$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

// 3. Verificar que el usuario esté autenticado y tenga acceso al curso.
require_login($course);

// 4. Verificar permisos y redirigir a la vista correspondiente.
if (has_capability('local/smart_learning_mentor:managereport', $context)) {
    // 4a. El usuario es profesor: redirigir al panel del profesor.
    redirect(new moodle_url('/local/smart_learning_mentor/teacher.php', [
        'courseid' => $courseid,
    ]));
} else if (has_capability('local/smart_learning_mentor:viewreport', $context)) {
    // 4b. El usuario es estudiante: redirigir al panel del estudiante.
    redirect(new moodle_url('/local/smart_learning_mentor/student.php', [
        'courseid' => $courseid,
    ]));
} else {
    // 5. Sin permiso: mostrar error de acceso.
    require_capability('local/smart_learning_mentor:viewreport', $context);
}