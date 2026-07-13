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
 * Registro de hooks (callbacks de ciclo de renderizado) del plugin.
 *
 * Descripción:
 *   - before_footer_html_generation: inyecta el panel flotante del estudiante
 *     antes del footer en páginas de actividades VPL.
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [

    // 1. Inyectar el panel flotante del estudiante en el footer de páginas VPL.
    [
        'hook'     => \core\hook\output\before_footer_html_generation::class,
        'callback' => \local_smart_learning_mentor\hook\output\before_footer_html_generation::class . '::execute',
    ],

];

