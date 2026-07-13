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
 * Datos para el panel flotante del estudiante (Mustache)
 *
 * Descripción:
 *   Convierte los datos del plugin al formato que espera la plantilla Mustache
 *   templates/student/panel.mustache. Implementa las interfaces renderable
 *   y templatable de Moodle.
 *
 * Flujo:
 *   1. Moodle carga una actividad VPL.
 *   2. El hook before_footer_html_generation se dispara.
 *   3. hook/output/before_footer_html_generation instancia esta clase.
 *   4. $OUTPUT->render($panel) llama a export_for_template().
 *   5. export_for_template() prepara los datos para Mustache (icono, strings, etc.)
 *   6. Moodle pasa los datos a templates/student/panel.mustache
 *   7. Mustache reemplaza las variables en panel.mustache
 *   8. El HTML resultante se inyecta antes del </body>
 *   9. amd/src/student_panel.js controla la interacción del usuario
 *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\output;

defined('MOODLE_INTERNAL') || die();

// use renderable;
// use templatable;
use renderer_base;

// class panel implements renderable, templatable {
class panel {

    private array $config;

    public function __construct(array $config = []) {
        // Defaults permisivos si no se pasa configuración.
        $this->config = array_merge([
            'habilitar_ayuda' => 1,
            'habilitar_temas_conceptos' => 1,
            'habilitar_recursos' => 1,
            'habilitar_ejemplos' => 1,
            'min_envios' => 1,
            'max_solicitudes' => 3,
            'submission_count' => 0,
            'solicitud_count' => 0,
            'can_request'  => 1,
            'blocked_reason' => '',
        ], $config);
    }


    /**
     * 1. Prepara los datos necesarios para renderizar el panel en Mustache.
     *    Incluye la URL del ícono del tutor y las cadenas de idioma necesarias.
     *
     * @param renderer_base $output
     * @return array Datos para la plantilla Mustache
     */
    public function export_for_template(renderer_base $output) {

        $cfg = $this->config;

        // Mensajes según el motivo de bloqueo.
        $blocked_msg = '';
        switch ($cfg['blocked_reason']) {
            case 'disabled_by_teacher':
                $blocked_msg = get_string('panel_blocked_teacher',      'local_smart_learning_mentor');
                break;
            case 'need_more_submissions':
                $blocked_msg = get_string('panel_blocked_submissions',  'local_smart_learning_mentor',
                    ['min' => $cfg['min_envios'], 'current' => $cfg['submission_count']]);
                break;
            case 'max_requests_reached':
                $blocked_msg = get_string('panel_blocked_max_requests', 'local_smart_learning_mentor',
                    ['max' => $cfg['max_solicitudes']]);
                break;
        }


        return [
            // 2. URL del ícono flotante del tutor.
            'icon_mentor' => $output->image_url('mentor_icon', 'local_smart_learning_mentor')->out(false),

            // 3. Cadenas de idioma para el panel.
            'str_panel_title' => get_string('panel_title', 'local_smart_learning_mentor'),
            'str_panel_subtitle' => get_string('panel_subtitle', 'local_smart_learning_mentor'),
            'str_welcome_title' => get_string('panel_welcome_title', 'local_smart_learning_mentor'),
            'str_welcome_body' => get_string('panel_welcome_body', 'local_smart_learning_mentor'),
            'str_get_help' => get_string('panel_get_help', 'local_smart_learning_mentor'),
            'str_processing' => get_string('panel_processing', 'local_smart_learning_mentor'),
            'str_analysis_label' => get_string('panel_analysis_label', 'local_smart_learning_mentor'),
            'str_errors_title' => get_string('panel_errors_title', 'local_smart_learning_mentor'),
            'str_errors_desc' => get_string('panel_errors_desc', 'local_smart_learning_mentor'),

            // Configuracion del VPL (se pasa al JS via data-* en el botón o div).
            'can_request' => (int)$cfg['can_request'],
            'blocked_msg' => $blocked_msg,
            'habilitar_ayuda' => (int)$cfg['habilitar_ayuda'],
            'habilitar_temas_conceptos' => (int)$cfg['habilitar_temas_conceptos'],
            'habilitar_recursos' => (int)$cfg['habilitar_recursos'],
            'habilitar_ejemplos' => (int)$cfg['habilitar_ejemplos'],
            'solicitudes_usadas' => (int)$cfg['solicitud_count'],
            'max_solicitudes' => (int)$cfg['max_solicitudes'],


        ];
    }

    /**
     * 2. Indica a Moodle qué plantilla Mustache usar para renderizar este renderable.
     *
     * @param renderer_base $renderer
     * @return string Nombre de la plantilla
     */
    /*public function get_template_name(renderer_base $renderer) {
        return 'local_smart_learning_mentor/student/panel';
    }*/
}
