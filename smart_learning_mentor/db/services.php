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
* Registro de funciones externas (endpoints AJAX/WebService) del plugin.
 *
 * Descripción:
 *   - Cada entrada registra un endpoint AJAX que puede ser llamado desde JavaScript.
 *   - Los nombres de funciones siguen el patrón: local_smart_learning_mentor_<accion>.
 *   - Cada función apunta a su clase en classes/external/.
 * *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$functions = [

    // 1. Solicitar análisis de la actividad VPL del estudiante a la IA.
    //    Llamado desde: amd/src/student_panel.js -> botón "Obtener ayuda"
    'local_smart_learning_mentor_request_vpl_analysis' => [
        'classname'   => 'local_smart_learning_mentor\\external\\request_vpl_analysis',
        'methodname'  => 'execute',
        'classpath'   => 'local/smart_learning_mentor/classes/external/request_vpl_analysis.php',
        'description' => 'Recopila los datos del estudiante en VPL y solicita análisis a la IA.',
        'type'        => 'write',
        'ajax'        => true,
    ],

    // 2. Guardar la configuracion de actividades VPL del curso.
    'local_smart_learning_mentor_save_configuration' => [
        'classname'   => 'local_smart_learning_mentor\\external\\save_configuration',
        'methodname'  => 'execute',
        'classpath'   => 'local/smart_learning_mentor/classes/external/save_configuration.php',
        'description' => 'Guarda la configuracion del plugin para multiples actividades VPL.',
        'type'        => 'write',
        'ajax'        => true,
    ],

    // 3. Gestionar temas del catalogo (crear, actualizar, eliminar)
    'local_smart_learning_mentor_manage_topic' => [
        'classname'   => 'local_smart_learning_mentor\\external\\manage_topic',
        'methodname'  => 'execute',
        'classpath'   => 'local/smart_learning_mentor/classes/external/manage_topic.php',
        'description' => 'Crea, actualiza o elimina un tema del catalogo pedagogico.',
        'type'        => 'write',
        'ajax'        => true,
    ],

    // 4. Gestionar conceptos del catalogo (crear, eliminar, promover desde IA)
    'local_smart_learning_mentor_manage_concept' => [
        'classname'   => 'local_smart_learning_mentor\\external\\manage_concept',
        'methodname'  => 'execute',
        'classpath'   => 'local/smart_learning_mentor/classes/external/manage_concept.php',
        'description' => 'Crea, elimina o promueve un concepto del catalogo pedagogico.',
        'type'        => 'write',
        'ajax'        => true,
    ],

    // 5. Gestionar recursos del catalogo (crear, eliminar, promover desde IA)
    'local_smart_learning_mentor_manage_resource' => [
        'classname'   => 'local_smart_learning_mentor\\external\\manage_resource',
        'methodname'  => 'execute',
        'classpath'   => 'local/smart_learning_mentor/classes/external/manage_resource.php',
        'description' => 'Crea, elimina o promueve un recurso.',
        'type'        => 'write',
        'ajax'        => true,
    ],

    // 6. Guardar la configuracion de ediciones del profesor
    'local_smart_learning_mentor_save_teacher_edit' => [
        'classname' => 'local_smart_learning_mentor\\external\\save_teacher_edit',
        'methodname' => 'execute',
        'classpath' => 'local/smart_learning_mentor/classes/external/save_teacher_edit.php',
        'description' => 'Guarda ediciones del profesor sobre errores y ejemplos IA',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

];

