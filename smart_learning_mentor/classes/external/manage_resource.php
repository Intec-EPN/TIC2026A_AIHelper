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
 * Version metadata for the repository_pluginname plugin.
 *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\external;
 
defined('MOODLE_INTERNAL') || die();
 
global $CFG;
require_once($CFG->libdir . '/externallib.php');
 
class manage_resource extends \external_api {
 
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid'   => new \external_value(PARAM_INT,  'ID del curso'),
            'cmid'       => new \external_value(PARAM_INT,  'ID del modulo del curso'),
            'titulo'     => new \external_value(PARAM_TEXT, 'Titulo del recurso'),
            'conceptids' => new \external_value(PARAM_TEXT, 'JSON array de IDs de conceptos a asociar'),
        ]);
    }
 
    public static function execute(
        int    $courseid,
        int    $cmid,
        string $titulo,
        string $conceptids
    ): array {
        global $DB;
 
        // 1. Validar parametros.
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'courseid', 'cmid', 'titulo', 'conceptids'
        ));
 
        // 2. Autenticacion y permisos.
        $course  = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($params['courseid']);
        require_login($course);
        require_capability('local/smart_learning_mentor:managereport', $context);
 
        // 3. Parsear los IDs de conceptos.
        $conceptidsarray = json_decode($params['conceptids'], true);
        if (!is_array($conceptidsarray)) {
            $conceptidsarray = [];
        }
 
        // 4. Delegar al caso de uso.
        $result = \local_smart_learning_mentor\application\resource_application::save_resource_concepts(
            $params['courseid'],
            $params['cmid'],
            $params['titulo'],
            array_map('intval', $conceptidsarray)
        );
 
        // 5. Serializar los conceptos para retornar al JS.
        return [
            'success'    => (bool)($result['success']   ?? false),
            'message'    => (string)($result['message'] ?? ''),
            'recursoid'  => (int)($result['recursoid']  ?? 0),
            'cmid'       => $params['cmid'],
            'concepts'   => json_encode($result['concepts'] ?? [], JSON_UNESCAPED_UNICODE),
        ];
    }
 
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'success'   => new \external_value(PARAM_BOOL, 'Resultado'),
            'message'   => new \external_value(PARAM_TEXT, 'Mensaje'),
            'recursoid' => new \external_value(PARAM_INT,  'ID del recurso guardado'),
            'cmid'      => new \external_value(PARAM_INT,  'ID del modulo del curso'),
            'concepts'  => new \external_value(PARAM_RAW,  'JSON array de conceptos asociados'),
        ]);
    }
}