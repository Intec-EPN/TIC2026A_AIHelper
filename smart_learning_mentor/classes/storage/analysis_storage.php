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
* Acceso a BD para guardar la respuesta completa de la IA.
 *
 * Descripción del flujo:
 *   1. vpl_analysis_application recibe la respuesta de la IA ya validada.
 *   2. Llama a analysis_storage::save() con todos los datos necesarios.
 *   3. analysis_storage guarda en orden:
 *       a. local_slm_solicitud_ayuda ->registro de la petición del estudiante.
 *       b. local_slm_respuesta_ia -> JSON crudo + student_message.
 *       c. local_slm_error_detectado ->un registro por cada error de la IA.
 *       d. local_slm_error_concepto -> relación error - concepto del catálogo (origen='ia').
 *       e. local_slm_ia_concepto ->conceptos propuestos por la IA (prefijo "IA:").
 *       f. local_slm_ia_recurso -> recursos propuestos por la IA.
 *       g. local_slm_ia_ejemplo ->ejemplos de código de la IA.
 *   4. Todo va dentro de una transacción: si algo falla, no queda nada a medias.
 *   5. Retorna los IDs generados para que application los pueda usar si necesita.
 *
 * "Lectura y escritura en la base de datos":
 *   - Único lugar donde se usa $DB para guardar la respuesta de la IA.
 *
 * REGLAS:
 *   ✔ Toda inserción SQL de la respuesta IA vive aquí.
 *   X No contiene lógica del negocio.
 *   X No realiza cálculos ni análisis.
 *   X No llama a la IA.
 *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\storage;

defined('MOODLE_INTERNAL') || die();

class analysis_storage {

    /**
     * 1. Guarda la solicitud, la respuesta de la IA y todos sus datos derivados
     *    en las tablas correspondientes, dentro de una transacción atómica.
     *
     * @param int    $courseid        ID del curso
     * @param int    $cmid            ID del módulo del curso (actividad VPL)
     * @param int    $vplid           ID del VPL
     * @param int    $userid          ID del estudiante
     * @param array  $payload         JSON completo enviado a la IA
     * @param array  $airesponse      Respuesta ya parseada de la IA (student_message + errors[])
     * @param string $rawjson         JSON crudo completo devuelto por la IA (para auditoría)
     * @param array  $conceptmap      Mapa de conceptos del catálogo del profesor [nombre_normalizado => id]
     * @return array                  Resultado con success, solicitudid, respuestaid
     */
    public static function save(
        int    $courseid,
        int    $cmid,
        int    $vplid,
        int    $userid,
        array  $payload,
        array  $airesponse,
        string $rawjson,
        array  $conceptmap
    ): array {
        global $DB;

        $now = time();

        // 2. Iniciar transacción: si cualquier INSERT falla, nada queda guardado.
        $transaction = $DB->start_delegated_transaction();

        // 3. Guardar la solicitud de ayuda del estudiante.
        $solicitudid = self::save_solicitud($courseid, $cmid, $vplid, $userid, $payload, $now);

        // 4. Guardar la respuesta de la IA.
        $respuestaid = self::save_respuesta($solicitudid, $airesponse, $rawjson, $now);

        // 5. Guardar cada error detectado con todos sus datos asociados.
        foreach (($airesponse['errors'] ?? []) as $error) {
            $errorid = self::save_error($respuestaid, $error, $now);

            // 5a. Conceptos: separar catálogo del profesor vs propuestos por IA.
            self::save_error_concepts($errorid, $error['concepts'] ?? [], $conceptmap, $now);

            // 5b. Recursos propuestos por la IA.
            self::save_ia_recursos($errorid, $error['resources'] ?? [], $now);

            // 5c. Ejemplos generados por la IA.
            self::save_ia_ejemplos($errorid, $error['examples'] ?? [], $now);
        }

        // 6. Confirmar la transacción completa.
        $transaction->allow_commit();

        return [
            'success'     => true,
            'solicitudid' => $solicitudid,
            'respuestaid' => $respuestaid,
        ];
    }

    // =========================================================================
    // MÉTODOS PRIVADOS: cada uno guarda una tabla específica
    // =========================================================================

    /**
     * 2. Guarda el registro de la solicitud de ayuda del estudiante.
     *    Tabla: local_slm_solicitud_ayuda
     *
     * @param int   $courseid
     * @param int   $cmid
     * @param int   $vplid
     * @param int   $userid
     * @param array $payload  JSON enviado a la IA
     * @param int   $now      Timestamp actual
     * @return int  ID del registro insertado
     */
    private static function save_solicitud(
        int   $courseid,
        int   $cmid,
        int   $vplid,
        int   $userid,
        array $payload,
        int   $now
    ): int {
        global $DB;

        // Buscar si hay una configuración activa para este VPL.
        $configuracionid = (int)($DB->get_field('local_slm_configuracion', 'id', [
            'courseid' => $courseid,
            'vplid'    => $vplid,
        ]) ?: 0);

        return (int)$DB->insert_record('local_slm_solicitud_ayuda', (object)[
            'configuracionid' => $configuracionid,
            'courseid'        => $courseid,
            'cmid'            => $cmid,
            'vplid'           => $vplid,
            'userid'          => $userid,
            'json'            => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'fecha_peticion'  => $now,
        ]);
    }

    /**
     * 3. Guarda la respuesta de la IA.
     *    Tabla: local_slm_respuesta_ia
     *
     * @param int    $solicitudid
     * @param array  $airesponse  Respuesta parseada (student_message + errors)
     * @param string $rawjson     JSON crudo completo para auditoría
     * @param int    $now
     * @return int   ID del registro insertado
     */
    private static function save_respuesta(
        int    $solicitudid,
        array  $airesponse,
        string $rawjson,
        int    $now
    ): int {
        global $DB;

        return (int)$DB->insert_record('local_slm_respuesta_ia', (object)[
            'solicitudid'     => $solicitudid,
            'respuesta_json'  => $rawjson,
            'student_message' => (string)($airesponse['student_message'] ?? ''),
            'fecha_respuesta' => $now,
        ]);
    }

    /**
     * 4. Guarda un error detectado por la IA.
     *    Tabla: local_slm_error_detectado
     *
     * @param int   $respuestaid
     * @param array $error  Objeto error de la IA
     * @param int   $now
     * @return int  ID del error insertado
     */
    private static function save_error(int $respuestaid, array $error, int $now): int {
        global $DB;

        return (int)$DB->insert_record('local_slm_error_detectado', (object)[
            'respuestaid'          => $respuestaid,
            'titulo'               => self::truncate((string)($error['title']          ?? 'Error detectado'), 255),
            'descripcion_error'    => (string)($error['error']          ?? ''),
            'recomendacion'        => (string)($error['recommendation'] ?? ''),
            'severidad'            => self::truncate((string)($error['badge']          ?? 'low'), 20),
            'porcentaje'           => (float)($error['percentage']      ?? 0),
            'ocurrencias'          => (int)($error['detected']          ?? 0),
            'parte_codigo'         => (string)($error['parte_codigo']   ?? ''),
            'editado_por_profesor' => 0,
            'timecreated'          => $now,
            'timemodified'         => $now,
        ]);
    }

    /**
     * 5. Guarda las relaciones entre un error y los conceptos.
     *    - Conceptos del catálogo del profesor → local_slm_error_concepto (origen='ia')
     *    - Conceptos propuestos por la IA (prefijo "IA:") → local_slm_ia_concepto
     *
     * @param int   $errorid
     * @param array $concepts   Lista de strings de conceptos (mezclados)
     * @param array $conceptmap Mapa [nombre_normalizado => id] del catálogo del profesor
     * @param int   $now
     */
    private static function save_error_concepts(
        int   $errorid,
        array $concepts,
        array $conceptmap,
        int   $now
    ): void {
        global $DB;

        foreach ($concepts as $conceptname) {
            $conceptname = trim((string)$conceptname);

            if (empty($conceptname)) {
                continue;
            }

            if (self::is_ai_concept($conceptname)) {
                // 5a. Concepto propuesto por la IA (prefijo "IA:") → tabla ia_concepto.
                $cleanname = self::clean_ai_prefix($conceptname);

                $DB->insert_record('local_slm_ia_concepto', (object)[
                    'errorid'              => $errorid,
                    'nombre'               => self::truncate($cleanname, 255),
                    'descripcion'          => '',
                    'promovido'            => 0,
                    'conceptoid_promovido' => null,
                    'timecreated'          => $now,
                ]);
            } else {
                // 5b. Concepto del catálogo del profesor → buscar ID y guardar en error_concepto.
                $conceptid = self::find_concept_in_map($conceptname, $conceptmap);

                if ($conceptid > 0) {
                    // Evitar duplicados (unique: errorid + conceptoid + origen).
                    $exists = $DB->record_exists('local_slm_error_concepto', [
                        'errorid'   => $errorid,
                        'conceptoid' => $conceptid,
                        'origen'    => 'ia',
                    ]);

                    if (!$exists) {
                        $DB->insert_record('local_slm_error_concepto', (object)[
                            'errorid'      => $errorid,
                            'conceptoid'   => $conceptid,
                            'origen'       => 'ia',
                            'aceptado'     => 1,
                            'timecreated'  => $now,
                            'timemodified' => $now,
                        ]);
                    }
                } else {
                    // No coincidió con ningún concepto del catálogo.
                    // Lo guardamos igualmente como concepto IA sin prefijo,
                    // para no perder la información.
                    $DB->insert_record('local_slm_ia_concepto', (object)[
                        'errorid'              => $errorid,
                        'nombre'               => self::truncate($conceptname, 255),
                        'descripcion'          => '',
                        'promovido'            => 0,
                        'conceptoid_promovido' => null,
                        'timecreated'          => $now,
                    ]);
                }
            }
        }
    }

    /**
     * 6. Guarda los recursos propuestos por la IA.
     *    Tabla: local_slm_ia_recurso
     *
     * @param int   $errorid
     * @param array $resources Lista de strings de recursos
     * @param int   $now
     */
    private static function save_ia_recursos(int $errorid, array $resources, int $now): void {
        global $DB;

        foreach ($resources as $resource) {
            $title = self::clean_ai_prefix(trim((string)$resource));

            if (empty($title)) {
                continue;
            }

            $DB->insert_record('local_slm_ia_recurso', (object)[
                'errorid'             => $errorid,
                'titulo'              => self::truncate($title, 255),
                'tipo'                => '',
                'url'                 => '',
                'descripcion'         => '',
                'promovido'           => 0,
                'recursoid_promovido' => null,
                'timecreated'         => $now,
            ]);
        }
    }

    /**
     * 7. Guarda los ejemplos de código generados por la IA.
     *    Tabla: local_slm_ia_ejemplo
     *
     * @param int   $errorid
     * @param array $examples Lista de objetos ejemplo
     * @param int   $now
     */
    private static function save_ia_ejemplos(int $errorid, array $examples, int $now): void {
        global $DB;

        foreach ($examples as $example) {
            if (empty($example['title'])) {
                continue;
            }

            $DB->insert_record('local_slm_ia_ejemplo', (object)[
                'errorid'              => $errorid,
                'titulo'               => self::truncate((string)($example['title']              ?? ''), 255),
                'descripcion_ejemplo'  => (string)($example['descripcion']       ?? ''),
                'codigo'               => (string)($example['codigo']            ?? ''),
                'explicacion'          => (string)($example['explicacion']       ?? ''),
                'resultado_esperado'   => (string)($example['resultado_esperado'] ?? ''),
                'editado_por_profesor' => 0,
                'timecreated'          => $now,
                'timemodified'         => $now,
            ]);
        }
    }

    // =========================================================================
    // UTILIDADES PRIVADAS
    // =========================================================================

    /**
     * 8. Busca el ID de un concepto en el mapa del catálogo del profesor.
     *    Normaliza el texto antes de comparar para evitar diferencias de mayúsculas/tildes.
     *
     * @param string $conceptname Nombre del concepto a buscar
     * @param array  $conceptmap  Mapa [nombre_normalizado => id]
     * @return int   ID del concepto encontrado, 0 si no coincide
     */
    private static function find_concept_in_map(string $conceptname, array $conceptmap): int {
        $key = self::normalize($conceptname);
        return (int)($conceptmap[$key] ?? 0);
    }

    /**
     * 9. Verifica si un texto tiene el prefijo "IA:" (concepto propuesto por la IA).
     *
     * @param string $text
     * @return bool
     */
    private static function is_ai_concept(string $text): bool {
        return (bool)preg_match('/^IA:\s*/ui', trim($text));
    }

    /**
     * 10. Elimina el prefijo "IA:" de un texto.
     *
     * @param string $text
     * @return string
     */
    private static function clean_ai_prefix(string $text): string {
        return trim((string)preg_replace('/^IA:\s*/ui', '', trim($text)));
    }

    /**
     * 11. Normaliza texto para comparar: minúsculas, sin espacios extras.
     *
     * @param string $text
     * @return string
     */
    private static function normalize(string $text): string {
        $text = self::clean_ai_prefix($text);
        $text = \core_text::strtolower($text);
        $text = (string)preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /**
     * 12. Trunca un string a la longitud máxima indicada.
     *     Evita errores de BD por campos char con límite de longitud.
     *
     * @param string $text
     * @param int    $maxlength
     * @return string
     */
    private static function truncate(string $text, int $maxlength): string {
        if (\core_text::strlen($text) <= $maxlength) {
            return $text;
        }
        return \core_text::substr($text, 0, $maxlength - 3) . '...';
    }
}
