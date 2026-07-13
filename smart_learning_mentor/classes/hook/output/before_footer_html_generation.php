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
 * Hook que inyecta el panel flotante del estudiante antes del footer de Moodle.
 *
 * Descripción:
 *   Este hook es invocado automáticamente por Moodle antes de cerrar
 *   el pie de página HTML de cualquier página. Se encarga de inyectar
 *   el HTML del panel flotante del estudiante.
 *
 * Descripción del flujo:
 *   1. Moodle genera el footer HTML de cualquier página.
 *   2. Moodle dispara el hook before_footer_html_generation registrado en db/hooks.php.
 *   3. Este callback verifica si la página actual corresponde a una actividad VPL.
 *   4. Si corresponde, instancia el panel del estudiante (output/student/panel.php).
 *   5. Renderiza el panel y lo inyecta en el HTML del footer.
 *   6. El panel queda disponible en todas las páginas de actividades VPL.
 *
 * hooks/output y no en observers
 *   - Los OBSERVERS escuchan eventos de Moodle que ya ocurrieron (entregas, login, etc.).
 *   - Los HOOKS interceptan el ciclo de renderizado en curso (generación de HTML).
 *   - Este archivo intercepta la generación del HTML del footer → es un hook de output.
 *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\hook\output;

defined('MOODLE_INTERNAL') || die();

class before_footer_html_generation {

    /**
     * 1. Callback principal invocado por Moodle antes de generar el footer
     *    se encarga de inyectar el panel flotante del estudiante si la pagina es una actividad VPL
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @return void
     */
    public static function execute(\core\hook\output\before_footer_html_generation $hook): void {
        global $OUTPUT, $PAGE;

        // a. No muestra en pag de login ni de admin del sitio
        if (in_array($PAGE->pagelayout, ['login', 'admin', 'maintenance', 'popup', 'frametop'])) {
            return;
        }

        // b. solo muetsra el panel si la URL actual corresponde a una actividad VPL
        $pageurl = $PAGE->url->out(false);
        if (strpos($pageurl, '/mod/vpl/') === false) {
            return;
        }

        // 4. Obtener vplid del cmid actual.
        $cmid  = (int)$PAGE->cm->id;
        $vplid = 0;
        $config = [];
        try {
            $vplinfo = \local_smart_learning_mentor\storage\vpl_storage::get_vpl_info($cmid);
            $vplid   = (int)($vplinfo['id'] ?? 0);
            $courseid = (int)($vplinfo['courseid'] ?? $PAGE->course->id);
            global $USER;
            $config = \local_smart_learning_mentor\storage\configuration_storage::get_vpl_panel_config(
                $vplid, $courseid, (int)$USER->id
            );
        } catch (\Exception $e) {
            // Si falla la config, usar defaults permisivos.
            $config = [
                'habilitar_ayuda'           => 1,
                'habilitar_temas_conceptos' => 1,
                'habilitar_recursos' => 1,
                'habilitar_ejemplos' => 1,
                'min_envios' => 1,
                'max_solicitudes' => 3,
                'submission_count' => 0,
                'solicitud_count' => 0,
                'can_request' => 1,
                'blocked_reason' => '',
            ];
        }

        // 4. Instanciar el renderable del panel del estudiante.
        $panel = new \local_smart_learning_mentor\output\panel($config);
        $data  = $panel->export_for_template($OUTPUT);

        $html = $OUTPUT->render_from_template(
            'local_smart_learning_mentor/panel',
            $data
        );

        // 7. Inyectar el HTML del panel en el footer.
        $hook->add_html($html);
    }
}
