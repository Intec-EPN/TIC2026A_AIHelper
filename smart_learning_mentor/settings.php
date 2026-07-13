<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Configuración global del plugin Smart Learning Mentor (administración del sitio).
 *
 * Descripción del flujo:
 *   1. El administrador accede a Administración del sitio → Plugins locales → Smart Learning Mentor.
 *   2. Moodle carga este archivo para mostrar las opciones de configuración global.
 *   3. El administrador configura la URL del webhook y el token de seguridad.
 *   4. Estos valores se leen en ai_service.php al momento de enviar datos a la IA.
 *
 * 
 * @package     local_smart_learning_mentor
 * @category    admin
 * @copyright   2026 Estefania Martinez <joselyn.martinez@epn.edu.ec>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // 1. Crear la página de configuración del plugin.
    $settings = new admin_settingpage('local_smart_learning_mentor_settings', new lang_string('pluginname', 'local_smart_learning_mentor'));

    // 2. Campo: URL del webhook (n8n u otro servicio de IA).
    $settings->add(new admin_setting_configtext(
        'local_smart_learning_mentor/webhook_url',
        get_string('setting_webhook_url', 'local_smart_learning_mentor'),
        get_string('setting_webhook_url_desc', 'local_smart_learning_mentor'),
        '',
        PARAM_URL
    ));

    // 3. Campo: Token de seguridad para autenticar la petición al webhook.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_smart_learning_mentor/webhook_token',
        get_string('setting_webhook_token', 'local_smart_learning_mentor'),
        get_string('setting_webhook_token_desc', 'local_smart_learning_mentor'),
        '',
        PARAM_TEXT
    ));

    // 4. Registrar la página de configuración en la sección de plugins locales.
    $ADMIN->add('localplugins', $settings);

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    /*if ($ADMIN->fulltree) {
        // TO-DO: Define actual plugin settings page and add it to the tree - {@link https://docs.moodle.org/dev/Admin_settings}.
    }*/
}
