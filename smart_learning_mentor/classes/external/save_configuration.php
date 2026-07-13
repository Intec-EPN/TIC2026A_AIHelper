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
 * Endpoint AJAX: guarda la configuracion de multiples VPLs.
 *
 * Descripcion del flujo:
 *   1. El profesor hace clic en "Guardar configuracion" en la interfaz.
 *   2. configuration.js recopila los datos de la tabla y llama a este endpoint.
 *   3. save_configuration recibe y valida el JSON de configuraciones.
 *   4. Delega a configuration_application::save_bulk().
 *   5. Retorna el resultado al JS.
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class save_configuration extends \external_api {

    /*1. Define los parametros de entrada*/
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'ID del curso'),
            'configs'  => new \external_value(PARAM_RAW, 'JSON con array de configuraciones por VPL'),
        ]);
    }

    /* 2. Procesa la peticion AJAX: valida, verifica permisos y delega*/
    public static function execute(int $courseid, string $configs): array {
        // 3. Validar parametros.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'configs' => $configs,
        ]);

        $courseid = (int)$params['courseid'];

        // 4. Verificar permisos.
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/smart_learning_mentor:managereport', $context);

        // 5. Parsear el JSON de configuraciones.
        $items = json_decode($params['configs'], true);

        if (!is_array($items)) {
            return [
                'success' => false,
                'message' => get_string('error_invalid_config_data', 'local_smart_learning_mentor'),
                'saved' => 0,
            ];
        }

        // 6. Delegar al caso de uso.
        $result = \local_smart_learning_mentor\application\configuration_application::save_bulk(
            $courseid,
            $items
        );

        return [
            'success' => $result['success'],
            'message' => $result['success']
                ? get_string('success_config_saved', 'local_smart_learning_mentor')
                : get_string('error_config_save',    'local_smart_learning_mentor'),
            'saved'   => $result['saved'],
        ];
    }

    /* 3. Define la estructura de retorno */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'true si se guardo correctamente'),
            'message' => new \external_value(PARAM_TEXT, 'Mensaje de resultado'),
            'saved'   => new \external_value(PARAM_INT,  'Cantidad de registros guardados'),
        ]);
    }
}

