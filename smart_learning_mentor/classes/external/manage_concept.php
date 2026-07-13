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
 * Endpoint AJAX para gestionar conceptos (crear, eliminar, promover desde IA).
 *
 * Acciones: create | delete | promote_ai
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class manage_concept extends \external_api {

    public static function execute_parameters(): \external_function_parameters { // extra, para agregar conceptos IA al catalogo
        return new \external_function_parameters([
            'action' => new \external_value(PARAM_ALPHANUMEXT, 'create | delete | promote_ai'), 
            'courseid' => new \external_value(PARAM_INT, 'ID del curso'),
            'themeid' => new \external_value(PARAM_INT,   'ID del tema destino', VALUE_DEFAULT, 0),
            'conceptid' => new \external_value(PARAM_INT,   'ID del concepto (eliminar)', VALUE_DEFAULT, 0),
            'nombre' => new \external_value(PARAM_TEXT,  'Nombre del concepto', VALUE_DEFAULT, ''),
            'descripcion' => new \external_value(PARAM_TEXT,  'Descripcion', VALUE_DEFAULT, ''),
            'ia_ids' => new \external_value(PARAM_TEXT,  'JSON array de IDs IA para promote_ai', VALUE_DEFAULT, '[]'),
        ]);
    }

    public static function execute(
        string $action,
        int $courseid,
        int $themeid    = 0,
        int $conceptid  = 0,
        string $nombre     = '',
        string $descripcion = '',
        string $ia_ids     = '[]'
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'action', 'courseid', 'themeid', 'conceptid', 'nombre', 'descripcion', 'ia_ids'
        ));

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/smart_learning_mentor:managecatalog', $context);

        $app = \local_smart_learning_mentor\application\topic_application::class;

        switch ($params['action']) {
            case 'create':
                $result = $app::create_concept(
                    $params['themeid'],
                    $params['courseid'],
                    $params['nombre'],
                    $params['descripcion']
                );
                break;

            case 'delete':
                $result = $app::delete_concept($params['conceptid'], $params['courseid']);
                break;

            case 'promote_ai':
                $iaids = json_decode($params['ia_ids'], true);
                if (!is_array($iaids)) {
                    $iaids = [];
                }
                $result = $app::promote_ai_concept(
                    $params['themeid'],
                    $params['courseid'],
                    $params['nombre'],
                    array_map('intval', $iaids)
                );
                break;

            default:
                $result = ['success' => false, 'id' => 0, 'nombre' => '', 'message' => 'Accion no valida.'];
        }

        return [
            'success' => (bool)($result['success'] ?? false),
            'id' => (int)($result['id']      ?? 0),
            'nombre'  => (string)($result['nombre']  ?? $params['nombre']),
            'message' => (string)($result['message'] ?? ''),
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Resultado'),
            'id' => new \external_value(PARAM_INT,  'ID del concepto', VALUE_OPTIONAL),
            'nombre'  => new \external_value(PARAM_TEXT, 'Nombre del concepto', VALUE_OPTIONAL),
            'message' => new \external_value(PARAM_TEXT, 'Mensaje'),
        ]);
    }
}
