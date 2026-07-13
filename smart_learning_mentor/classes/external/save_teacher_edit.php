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
 * External: guarda las ediciones del profesor sobre errores y ejemplos IA.
 *
 * LÓGICA:
 *  - tipo='error' -> edita local_slm_error_detectado, guarda original en local_slm_error_edicion.
 *  - tipo='ejemplo'-> edita local_slm_ia_ejemplo directamente, marca editado_por_profesor=1.
 * *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_function_parameters;
use external_single_structure;
use external_value;

class save_teacher_edit extends \external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'tipo'     => new external_value(PARAM_ALPHA,  'Tipo: error | ejemplo'),
            'id'       => new external_value(PARAM_INT,    'ID del error o ejemplo a editar'),
            'courseid' => new external_value(PARAM_INT,    'ID del curso (para verificar permisos)'),
            'campos'   => new external_single_structure([
                'titulo'            => new external_value(PARAM_TEXT, 'Título',             VALUE_OPTIONAL),
                'descripcion_error' => new external_value(PARAM_RAW,  'Descripción error',  VALUE_OPTIONAL),
                'recomendacion'     => new external_value(PARAM_RAW,  'Recomendación',      VALUE_OPTIONAL),
                'descripcion'       => new external_value(PARAM_RAW,  'Descripción ejemplo',VALUE_OPTIONAL),
                'codigo'            => new external_value(PARAM_RAW,  'Código ejemplo',     VALUE_OPTIONAL),
                'explicacion'       => new external_value(PARAM_RAW,  'Explicación ejemplo',VALUE_OPTIONAL),
                'resultado'         => new external_value(PARAM_RAW,  'Resultado esperado', VALUE_OPTIONAL),
            ]),
        ]);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'OK'),
            'message' => new external_value(PARAM_TEXT, 'Mensaje de resultado'),
        ]);
    }

    public static function execute(string $tipo, int $id, int $courseid, array $campos): array {
        global $DB, $USER;

        self::validate_parameters(self::execute_parameters(), [
            'tipo'     => $tipo,
            'id'       => $id,
            'courseid' => $courseid,
            'campos'   => $campos,
        ]);

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/smart_learning_mentor:managecatalog', $context);

        $now = time();

        if ($tipo === 'error') {
            return self::save_error_edit($id, $campos, (int)$USER->id, $now);
        } else if ($tipo === 'ejemplo') {
            return self::save_ejemplo_edit($id, $campos, (int)$USER->id, $now);
        }

        return ['success' => false, 'message' => 'Tipo inválido: ' . $tipo];
    }

    //   Error  

    private static function save_error_edit(int $errorid, array $campos, int $userid, int $now): array {
        global $DB;

        $error = $DB->get_record('local_slm_error_detectado', ['id' => $errorid], '*', IGNORE_MISSING);
        if (!$error) {
            return ['success' => false, 'message' => 'Error no encontrado (id=' . $errorid . ')'];
        }

        // Campos editables del error.
        $editable = ['titulo', 'descripcion_error', 'recomendacion'];

        // Obtener o crear el registro de edición (UNIQUE por errorid).
        $edicion = $DB->get_record('local_slm_error_edicion', ['errorid' => $errorid]);
        $esnueva = !$edicion;
        if ($esnueva) {
            $edicion             = new \stdClass();
            $edicion->errorid    = $errorid;
            $edicion->editado_por = $userid;
            $edicion->timecreated = $now;
        }

        $updated = false;

        foreach ($editable as $campo) {
            $nuevovalor = isset($campos[$campo]) ? trim($campos[$campo]) : null;
            if ($nuevovalor === null || $nuevovalor === (string)$error->$campo) {
                continue; // Sin cambio.
            }

            // Guardar el original de la IA (solo la primera vez que se edita ese campo).
            $campoedicion = ($campo === 'descripcion_error') ? 'descripcion' : $campo;
            if (!isset($edicion->$campoedicion) || $edicion->$campoedicion === null) {
                $edicion->$campoedicion = $error->$campo;
            }

            // Aplicar el nuevo valor en error_detectado.
            $error->$campo = $nuevovalor;
            $updated = true;
        }

        if (!$updated) {
            return ['success' => true, 'message' => 'Sin cambios'];
        }

        $error->editado_por_profesor = 1;
        $error->timemodified         = $now;
        $DB->update_record('local_slm_error_detectado', $error);

        if ($esnueva) {
            $DB->insert_record('local_slm_error_edicion', $edicion);
        } else {
            $DB->update_record('local_slm_error_edicion', $edicion);
        }

        return ['success' => true, 'message' => 'Error actualizado'];
    }

    //   Ejemplo  

    private static function save_ejemplo_edit(int $ejemploid, array $campos, int $userid, int $now): array {
        global $DB;

        $ejemplo = $DB->get_record('local_slm_ia_ejemplo', ['id' => $ejemploid], '*', IGNORE_MISSING);
        if (!$ejemplo) {
            return ['success' => false, 'message' => 'Ejemplo no encontrado (id=' . $ejemploid . ')'];
        }

        // Mapa campo_ajax → campo_bd
        // Nota: resultado_esperado NO existe en ia_ejemplo (la IA lo devuelve pero no se guarda).
        $mapacampos = [
            'titulo'      => 'titulo',
            'descripcion' => 'descripcion',
            'codigo'      => 'codigo',
            'explicacion' => 'explicacion',
            'resultado_esperado' => 'resultado_esperado'
        ];

        $updated = false;
        foreach ($mapacampos as $campoajax => $campobd) {
            $nuevovalor = isset($campos[$campoajax]) ? trim($campos[$campoajax]) : null;
            if ($nuevovalor === null) {
                continue;
            }
            if ((string)($ejemplo->$campobd ?? '') !== $nuevovalor) {
                $ejemplo->$campobd = $nuevovalor;
                $updated = true;
            }
        }

        if (!$updated) {
            return ['success' => true, 'message' => 'Sin cambios'];
        }

        $ejemplo->editado_por_profesor = 1;
        $ejemplo->timemodified         = $now;
        $DB->update_record('local_slm_ia_ejemplo', $ejemplo);

        return ['success' => true, 'message' => 'Ejemplo actualizado'];
    }
}
