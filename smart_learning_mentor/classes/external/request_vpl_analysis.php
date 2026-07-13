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
* Endpoint AJAX: recibe la petición del botón "Obtener ayuda" y la delega al caso de uso.
 *
 * Descripción del flujo:
 *   1. El estudiante hace clic en "Obtener ayuda" en el panel flotante.
 *   2. student_panel.js llama a este endpoint mediante AJAX (core/ajax).
 *   3. request_vpl_analysis recibe y valida el parámetro cmid.
 *   4. Verifica que el usuario esté autenticado y tenga contexto válido.
 *   5. Delega toda la lógica a vpl_analysis_application::execute().
 *   6. Retorna el resultado al JS (status, message, datos de la IA).
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

class request_vpl_analysis extends \external_api {

    /**
     * 1. Define los parámetros de entrada del endpoint AJAX.
     *
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, 'ID del módulo del curso (actividad VPL)'),
        ]);
    }

    /**
     * 2. Procesa la petición AJAX: valida, verifica permisos y delega al caso de uso.

     */
    public static function execute(int $cmid): array {
        global $USER;

        // 3. Validar los parámetros de entrada.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
        ]);

        $cmid   = (int)$params['cmid'];
        $userid = (int)$USER->id;

        // 4. Verificar autenticación y contexto del módulo.
        require_login();

        $context = \context_module::instance($cmid);
        self::validate_context($context);

        // 5. Delegar completamente al caso de uso (application).
        $result = \local_smart_learning_mentor\application\vpl_analysis_application::execute($cmid, $userid);

        // 6. Retornar la respuesta estructurada al JS.
        return [
            'status' => (bool)($result['status'] ?? false),
            'message' => (string)($result['message'] ?? ''),
            'http_code' => (int)($result['http_code'] ?? 0),
            'data' => (string)($result['data'] ?? ''),
        ];
    }

    /**
     * 3. Define la estructura de retorno del endpoint AJAX.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_BOOL, 'true si el proceso fue exitoso'),
            'message' => new \external_value(PARAM_TEXT, 'Mensaje de resultado'),
            'http_code' => new \external_value(PARAM_INT,  'Código HTTP de la respuesta de la IA', VALUE_OPTIONAL),
            'data' => new \external_value(PARAM_RAW,  'JSON con payload y respuesta completa', VALUE_OPTIONAL),
        ]);
    }
}

