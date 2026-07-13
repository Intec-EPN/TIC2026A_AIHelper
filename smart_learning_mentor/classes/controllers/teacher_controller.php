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
 * Controlador del profesor - local_smart_learning_mentor.
 *
 * Descripción:
 *   Recibe la petición desde teacher.php, decide qué caso de uso (application)
 *   ejecutar y qué plantilla Mustache usar. Actúa como intermediario entre
 *   la capa de presentación (teacher.php) y la lógica de negocio (application).
 * 
 * Flujo:
 *   1. teacher.php instancia este controlador con courseid, userid y view.
 *   2. teacher.php llama a handle($view) indicando la vista solicitada.
 *   3. El controlador decide qué application (caso de uso) ejecutar según la vista.
 *   4. La application retorna datos procesados.
 *   5. El output convierte esos datos al formato de Mustache.
 *   6. handle() retorna [nombre_plantilla, datos_para_mustache].
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\controllers;
 
defined('MOODLE_INTERNAL') || die();
 
class teacher_controller {
 
    private int $courseid;
 
    private \context_course $context;
 
    public function __construct(int $courseid, \context_course $context) {
        $this->courseid = $courseid;
        $this->context  = $context;
    }
 
    /**
     * 1. Retorna el renderable correspondiente a la vista y subvista solicitadas.
     *
     */
    public function handle(string $view, string $subview, int $cmid = 0, int $userid = 0, int $conceptid = 0): \renderable {
        switch ($view) {
            case 'catalog':
                return new \local_smart_learning_mentor\output\teacher\catalog(
                    $this->courseid, $subview
                );
 
            case 'config':
                return new \local_smart_learning_mentor\output\teacher\configuration(
                    $this->courseid
                );
 
            case 'general':
            default:
                return new \local_smart_learning_mentor\output\teacher\general(
                    $this->courseid, $subview, $cmid, $userid, $conceptid
                );
        }
    }
}
