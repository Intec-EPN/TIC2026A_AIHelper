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
 * Acceso a BD para la configuración del plugin por curso y VPL.
 *
 * Descripción del flujo:
 *   1. configuration_application necesita leer o guardar configuraciones.
 *   2. Llama a configuration_storage::get_course_config() para leer.
 *   3. Llama a configuration_storage::save_vpl_config() para guardar/actualizar.
 *   4. configuration_storage es el único lugar donde se usa $DB para estas tablas.
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_smart_learning_mentor\storage;
 
defined('MOODLE_INTERNAL') || die();
 
class configuration_storage {
 
    /**
     * 1. Obtiene todas las actividades VPL del curso con su configuracion actual.
     *    Si un VPL no tiene registro en local_slm_configuracion, retorna valores por defecto.
     *
     * @param int $courseid ID del curso
     * @return array Lista de VPLs con su configuracion
     */
    public static function get_course_vpl_configs(int $courseid): array {
        global $DB;
 
        // 1a. Obtener todos los VPLs del curso con datos de seccion.
        $sql = "SELECT cm.id        AS cmid,
                       v.id         AS vplid,
                       v.name       AS vplname,
                       cs.section   AS sectionnum,
                       cs.name      AS sectionname
                  FROM {course_modules} cm
                  JOIN {modules} m      ON m.id   = cm.module
                  JOIN {vpl} v          ON v.id   = cm.instance
                  JOIN {course_sections} cs ON cs.id = cm.section
                 WHERE cm.course = :courseid
                   AND m.name = 'vpl'
                   AND cm.deletioninprogress = 0
              ORDER BY cs.section ASC, v.name ASC";
 
        $vpls = $DB->get_records_sql($sql, ['courseid' => $courseid]);
 
        if (empty($vpls)) {
            return [];
        }
 
        // 1b. Obtener todas las configuraciones existentes para este curso.
        $configs = $DB->get_records('local_slm_configuracion', ['courseid' => $courseid], '', '*');
 
        // Indexar por vplid para busqueda rapida.
        $configmap = [];
        foreach ($configs as $cfg) {
            $configmap[(int)$cfg->vplid] = $cfg;
        }
 
        // 1c. Combinar VPLs con su configuracion (o valores por defecto si no existe).
        $result = [];
        foreach ($vpls as $vpl) {
            $vplid = (int)$vpl->vplid;
            $cfg   = $configmap[$vplid] ?? null;
 
            $result[] = [
                'cmid'                    => (int)$vpl->cmid,
                'vplid'                   => $vplid,
                'courseid'                => $courseid,
                'activityname'            => $vpl->vplname,
                'sectionnum'              => (int)$vpl->sectionnum,
                'sectionname'             => !empty($vpl->sectionname)
                    ? $vpl->sectionname
                    : get_string('section') . ' ' . $vpl->sectionnum,
                'activityurl'             => (new \moodle_url('/mod/vpl/view.php', ['id' => (int)$vpl->cmid]))->out(false),
                // Valores de configuracion (o por defecto si no existe registro).
                'habilitar_ayuda'         => $cfg ? (bool)$cfg->habilitar_ayuda         : true,
                'habilitar_recursos'      => $cfg ? (bool)$cfg->habilitar_recursos       : true,
                'habilitar_ejemplos'      => $cfg ? (bool)$cfg->habilitar_ejemplos       : true,
                'min_envios'              => $cfg ? (int)$cfg->min_envios                : 1,
                'max_solicitudes'         => $cfg ? (int)$cfg->max_solicitudes           : 3,
                'configured'              => $cfg !== null,
            ];
        }
 
        return $result;
    }
 
    /**
     * 2. Guarda o actualiza la configuracion de un VPL especifico.
     *    Si ya existe un registro para courseid+vplid lo actualiza (UPDATE).
     *    Si no existe, crea uno nuevo (INSERT).
     *
     * @param int   $courseid
     * @param int   $vplid
     * @param array $data  Campos: habilitar_ayuda, habilitar_recursos, habilitar_ejemplos,
     *                             min_envios, max_solicitudes
     * @return int  ID del registro (insertado o actualizado)
     */
    public static function save_vpl_config(int $courseid, int $vplid, array $data): int {
        global $DB;
 
        $now = time();
 
        $existing = $DB->get_record('local_slm_configuracion', [
            'courseid' => $courseid,
            'vplid'    => $vplid,
        ]);
 
        if ($existing) {
            // 2a. Actualizar el registro existente.
            $existing->habilitar_ayuda          = (int)($data['habilitar_ayuda']    ?? 0);
            $existing->habilitar_temas_conceptos = (int)($data['habilitar_recursos'] ?? 0);
            $existing->habilitar_recursos        = (int)($data['habilitar_recursos'] ?? 0);
            $existing->habilitar_ejemplos        = (int)($data['habilitar_ejemplos'] ?? 0);
            $existing->min_envios                = (int)($data['min_envios']         ?? 1);
            $existing->max_solicitudes           = (int)($data['max_solicitudes']    ?? 3);
            $existing->timemodified              = $now;
 
            $DB->update_record('local_slm_configuracion', $existing);
            return (int)$existing->id;
        }
 
        // 2b. Crear nuevo registro.
        return (int)$DB->insert_record('local_slm_configuracion', (object)[
            'courseid'                => $courseid,
            'vplid'                   => $vplid,
            'habilitar_ayuda'         => (int)($data['habilitar_ayuda']    ?? 0),
            'habilitar_temas_conceptos' => (int)($data['habilitar_recursos'] ?? 0),
            'habilitar_recursos'      => (int)($data['habilitar_recursos']  ?? 0),
            'habilitar_ejemplos'      => (int)($data['habilitar_ejemplos']  ?? 0),
            'min_envios'              => (int)($data['min_envios']           ?? 1),
            'max_solicitudes'         => (int)($data['max_solicitudes']      ?? 3),
            'timecreated'             => $now,
            'timemodified'            => $now,
        ]);
    }
 
    /**
     * 3. Obtiene las secciones unicas del curso que tienen actividades VPL.
     *    Usado para el filtro de seccion en la interfaz de configuracion.
     *
     * @param int $courseid
     * @return array [['sectionnum' => int, 'label' => string], ...]
     */
    public static function get_course_sections_with_vpl(int $courseid): array {
        global $DB;
 
        $sql = "SELECT DISTINCT cs.section AS sectionnum, cs.name AS sectionname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {course_sections} cs ON cs.id = cm.section
                 WHERE cm.course = :courseid
                   AND m.name = 'vpl'
                   AND cm.deletioninprogress = 0
              ORDER BY cs.section ASC";
 
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);
 
        return array_values(array_map(function($r) {
            return [
                'sectionnum' => (int)$r->sectionnum,
                'label'      => !empty($r->sectionname)
                    ? $r->sectionname
                    : get_string('section') . ' ' . $r->sectionnum,
            ];
        }, $records));
    }
 
    /**
     * Obtiene la configuracion del panel para un VPL especifico,
     * junto con el conteo de entregas y solicitudes del estudiante.
     * Usado por before_footer_html_generation para pasar datos al panel JS.
     *
     * @param int $vplid
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function get_vpl_panel_config(int $vplid, int $courseid, int $userid): array {
        global $DB;
 
        // 1. config de la activdad VPL
        $cfg = $DB->get_record('local_slm_configuracion', [
            'vplid' => $vplid,
            'courseid' => $courseid,
        ]);
 
        $habilitar_ayuda = $cfg ? (bool)$cfg->habilitar_ayuda : true;
        $habilitar_temas_conceptos = $cfg ? (bool)$cfg->habilitar_temas_conceptos : true;
        $habilitar_recursos = $cfg ? (bool)$cfg->habilitar_recursos : true;
        $habilitar_ejemplos = $cfg ? (bool)$cfg->habilitar_ejemplos : true;
        $min_envios = $cfg ? (int)$cfg->min_envios : 1;
        $max_solicitudes = $cfg ? (int)$cfg->max_solicitudes: 3;
 
        // 2. cuentra entregas del estudainte
        $submission_count = 0;
        try {
            $submission_count = (int)$DB->count_records('vpl_submissions', [
                'vpl' => $vplid,
                'userid' => $userid,
            ]);
        } catch (\Exception $e) {

            // Si la tabla no existe o falla, asumimos 0.
            $submission_count = 0;
        }
 
        // 3. Contar solicitudes ya realizadas por el estudiante en este VPL.
        $solicitud_count = (int)$DB->count_records('local_slm_solicitud_ayuda', [
            'vplid'    => $vplid,
            'courseid' => $courseid,
            'userid'   => $userid,
        ]);
 
        // 4. Determinar si el boton esta activo y el motivo si no.
        $can_request = false;
        $blocked_reason = '';
 
        if (!$habilitar_ayuda) {
            $blocked_reason = 'disabled_by_teacher';
        } else if ($submission_count < $min_envios) {
            $blocked_reason = 'need_more_submissions';
        } else if ($solicitud_count >= $max_solicitudes) {
            $blocked_reason = 'max_requests_reached';
        } else {
            $can_request = true;
        }
 
        return [
            'habilitar_ayuda'           => $habilitar_ayuda          ? 1 : 0,
            'habilitar_temas_conceptos' => $habilitar_temas_conceptos ? 1 : 0,
            'habilitar_recursos'        => $habilitar_recursos        ? 1 : 0,
            'habilitar_ejemplos'        => $habilitar_ejemplos        ? 1 : 0,
            'min_envios'                => $min_envios,
            'max_solicitudes'           => $max_solicitudes,
            'submission_count'          => $submission_count,
            'solicitud_count'           => $solicitud_count,
            'can_request'               => $can_request ? 1 : 0,
            'blocked_reason'            => $blocked_reason,
        ];
    }
 
}