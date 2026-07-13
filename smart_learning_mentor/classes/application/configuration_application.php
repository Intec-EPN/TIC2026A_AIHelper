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
 * Caso de uso: gestionar la configuracion del plugin por VPL.
 *
 */

namespace local_smart_learning_mentor\application;

defined('MOODLE_INTERNAL') || die();

class configuration_application {

    /**
     * 1. Obtiene todos los datos necesarios para renderizar la pagina de configuracion.
     *    Retorna VPLs del curso con su configuracion actual y datos auxiliares (secciones).
     *
     * @param int $courseid
     * @return array Datos listos para Mustache
     */
    public static function get_page_data(int $courseid): array {
        // 1a. Obtener VPLs con configuracion.
        $rawvpls = \local_smart_learning_mentor\storage\configuration_storage::get_course_vpl_configs($courseid);

        // 1b. Obtener secciones para el filtro.
        $sections = \local_smart_learning_mentor\storage\configuration_storage::get_course_sections_with_vpl($courseid);

        // 1c. Preparar datos de cada VPL para Mustache.
        $vpls = array_map(function($vpl) {
            return array_merge($vpl, [
                // Estado visual del badge.
                'statusclass' => $vpl['configured'] ? 'bg-success' : 'bg-secondary',
                'statuslabel' => $vpl['configured']
                    ? get_string('config_status_configured', 'local_smart_learning_mentor')
                    : get_string('config_status_default',    'local_smart_learning_mentor'),
            ]);
        }, $rawvpls);

        return [
            'vplactivities'    => $vpls,
            'vplactivitycount' => count($vpls),
            'section_options'  => $sections,
            'has_vpls'         => !empty($vpls),
        ];
    }

    /**
     * 2. Guarda la configuracion de multiples VPLs en una sola operacion.
     *   Aplica las reglas de negocio antes de guardar:
     *     - Si habilitar_ayuda = false: fuerza habilitar_recursos = false, habilitar_ejemplos = false.
     *     - Si habilitar_recursos = true o habilitar_ejemplos = true: fuerza habilitar_ayuda = true.
     *
     * @param int   $courseid
     * @param array $items    Lista de configuraciones a guardar.
     * @return array Resultado con success y cantidad de registros guardados
     */
    public static function save_bulk(int $courseid, array $items): array {
        $saved  = 0;
        $errors = [];

        foreach ($items as $item) {
            $vplid = (int)($item['vplid'] ?? 0);

            if ($vplid <= 0) {
                continue;
            }

            // 2a. Aplicar reglas de negocio.
            $validated = self::apply_business_rules($item);

            // 2b. Validar limites numericos.
            $validated['min_envios']      = max(1, min(100, (int)($validated['min_envios']      ?? 1)));
            $validated['max_solicitudes'] = max(1, min(20,  (int)($validated['max_solicitudes'] ?? 3)));

            try {
                \local_smart_learning_mentor\storage\configuration_storage::save_vpl_config(
                    $courseid,
                    $vplid,
                    $validated
                );
                $saved++;
            } catch (\Exception $e) {
                $errors[] = "VPL $vplid: " . $e->getMessage();
            }
        }

        return [
            'success' => empty($errors),
            'saved'   => $saved,
            'errors'  => $errors,
        ];
    }

    /**
     * 3. Aplica las reglas de negocio de configuracion.
     *    - habilitar_recursos y habilitar_ejemplos REQUIEREN habilitar_ayuda=true.
     *    - Si habilitar_ayuda=false: desactiva habilitar_recursos y habilitar_ejemplos.
     *    - Si habilitar_recursos=true o habilitar_ejemplos=true: activa habilitar_ayuda.
     *
     * @param array $item Datos del item a validar
     * @return array      Item con reglas aplicadas
     */
    private static function apply_business_rules(array $item): array {
        $habilitarayuda    = (bool)($item['habilitar_ayuda']    ?? false);
        $habilitarrecursos = (bool)($item['habilitar_recursos'] ?? false);
        $habilitarejemplos = (bool)($item['habilitar_ejemplos'] ?? false);

        // Si algun dependiente esta activo, habilitar_ayuda debe estar activo.
        if ($habilitarrecursos || $habilitarejemplos) {
            $habilitarayuda = true;
        }

        // Si habilitar_ayuda se desactivo, desactivar todos los dependientes.
        if (!$habilitarayuda) {
            $habilitarrecursos = false;
            $habilitarejemplos = false;
        }

        return array_merge($item, [
            'habilitar_ayuda'    => $habilitarayuda,
            'habilitar_recursos' => $habilitarrecursos,
            'habilitar_ejemplos' => $habilitarejemplos,
        ]);
    }
}

