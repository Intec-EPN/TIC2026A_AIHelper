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
 * Controlador del estudiante: recibe la petición de student.php y delega al caso de uso.
 *
 * flujo:
 *   1. student.php instancia este controlador con el courseid y contexto.
 *   2. student.php llama a handle().
 *   3. El controlador decide qué application ejecutar.
 *   4. Retorna un renderable (output class) listo para Mustache.
 *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_smart_learning_mentor\controllers;

defined('MOODLE_INTERNAL') || die();

class student_controller {

    private int $courseid;

    private \context_course $context;


    public function __construct(int $courseid, \context_course $context) {
        $this->courseid = $courseid;
        $this->context  = $context;
    }

    /**
     * 2. Maneja la petición del estudiante y retorna el renderable del dashboard.
     *
     */
    public function handle(): \renderable {
        global $USER;

        // 3. Retorna el renderable del dashboard del estudiante.
        return new \local_smart_learning_mentor\output\student\dashboard(
            $this->courseid,
            (int)$USER->id
        );
    }
}
