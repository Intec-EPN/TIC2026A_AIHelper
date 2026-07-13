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
 * Endpoint AJAX para gestionar temas (crear, actualizar, eliminar).
 *
 * Descripcion del flujo:
 *   1. teacher_topics.js llama a este endpoint con accion y datos.
 *   2. manage_topic valida parametros y permisos.
 *   3. Delega a topic_application segun la accion.
 *   4. Retorna el resultado al JS.
 *
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class manage_topic extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'action' => new \external_value(PARAM_ALPHA, 'create | update | delete'),
            'courseid' => new \external_value(PARAM_INT, 'ID del curso'),
            'themeid' => new \external_value(PARAM_INT, 'ID del tema (0 para crear)', VALUE_DEFAULT, 0),
            'nombre' => new \external_value(PARAM_TEXT,  'Nombre del tema', VALUE_DEFAULT, ''),
            'descripcion' => new \external_value(PARAM_TEXT,  'Descripcion del tema', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(
        string $action,
        int    $courseid,
        int    $themeid    = 0,
        string $nombre     = '',
        string $descripcion = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'action' => $action,
            'courseid' => $courseid,
            'themeid' => $themeid,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/smart_learning_mentor:managecatalog', $context);

        $app = \local_smart_learning_mentor\application\topic_application::class;

        switch ($params['action']) {
            case 'create':
                $result = $app::create_theme($params['courseid'], $params['nombre'], $params['descripcion']);
                break;
            case 'update':
                $result = $app::update_theme($params['themeid'], $params['courseid'], $params['nombre'], $params['descripcion']);
                break;
            case 'delete':
                $result = $app::delete_theme($params['themeid'], $params['courseid']);
                break;
            default:
                $result = ['success' => false, 'id' => 0, 'message' => 'Accion no valida.'];
        }

        return [
            'success' => (bool)($result['success'] ?? false),
            'id' => (int)($result['id']      ?? 0),
            'message' => (string)($result['message'] ?? ''),
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Resultado'),
            'id' => new \external_value(PARAM_INT,  'ID del registro', VALUE_OPTIONAL),
            'message' => new \external_value(PARAM_TEXT, 'Mensaje'),
        ]);
    }
}
