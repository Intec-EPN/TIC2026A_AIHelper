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
 * Análisis de la actividad VPL con IA.
 *
 * Descripción:
 *   Contiene la inteligencia principal del plugin para la fase de análisis.
 *   Orquesta la recolección de datos del VPL, el envío a la IA y el procesamiento
 *   de la respuesta. Es el "director de orquesta" entre storage y services.
 *
 * flujo:
 *   1. El external (endpoint AJAX) llama a este application con cmid y userid.
 *   2. El application orquesta: llama a vpl_storage para obtener datos de VPL.
 *   3. Llama a topic_storage para obtener el catálogo de temas y conceptos del profesor.
 *   4. Construye el payload completo (contexto del estudiante + catálogo pedagógico).
 *   5. Llama a ai_service para enviar el payload a la IA (webhook n8n).
 *   6. Recibe y retorna la respuesta de la IA al external, que la devuelve al JS.
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



namespace local_smart_learning_mentor\application;
 
defined('MOODLE_INTERNAL') || die();
 
class vpl_analysis_application {
 
    /**
     * 1. Ejecuta el caso de uso completo:
     *    recopilar datos VPL → enviar a IA → guardar en BD → retornar respuesta.
     *
     * @param int $cmid   ID del módulo del curso (actividad VPL)
     * @param int $userid ID del estudiante
     * @return array      Resultado con status, message y datos para el JS
     */
    public static function execute(int $cmid, int $userid): array {
 
        // 2. Verificar que exista al menos una entrega del estudiante.
        $submissions = \local_smart_learning_mentor\storage\vpl_storage::get_submission_history($cmid, $userid);
 
        if (empty($submissions)) {
            return [
                'status'  => false,
                'message' => get_string('error_no_submissions', 'local_smart_learning_mentor'),
                'data'    => null,
            ];
        }
 
        // 3. Obtener datos del VPL para conocer el courseid y el vplid.
        $vpldata  = \local_smart_learning_mentor\storage\vpl_storage::get_vpl_info($cmid);
        $courseid = (int)$vpldata['courseid'];
        $vplid    = (int)$vpldata['id'];
 
        // 4. Obtener el catálogo de temas y conceptos del profesor.
        //    Se usa para: a) enviar a la IA como referencia pedagógica,
        //                  b) construir el mapa para cruzar con la respuesta IA.
        $topics     = \local_smart_learning_mentor\storage\topic_storage::get_course_topics($courseid);
        $conceptmap = self::build_concept_map($topics);
 
        // 5. Construir el payload completo para la IA.
        $payload = self::build_payload($cmid, $userid, $vpldata, $submissions, $topics);
 
        // 6. Verificar que el webhook esté configurado por el administrador.
        $webhookurl = get_config('local_smart_learning_mentor', 'webhook_url');
        $token      = get_config('local_smart_learning_mentor', 'webhook_token');
 
        if (empty($webhookurl)) {
            return [
                'status'  => false,
                'message' => get_string('error_no_webhook', 'local_smart_learning_mentor'),
                'data'    => json_encode(['payload' => $payload, 'sent' => false], JSON_UNESCAPED_UNICODE),
            ];
        }
 
        // 7. Enviar el payload a la IA.
        $jsonpayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $airesult    = \local_smart_learning_mentor\services\ai_service::send($jsonpayload, $webhookurl, $token);
 
        // 8. Extraer la respuesta útil de la IA desde el JSON del webhook.
        $rawjson    = $airesult['response'] ?? '';
        $airesponse = self::extract_ai_response($rawjson);
 
        // 9. Si la IA respondió correctamente, guardar todo en la BD.
        //    analysis_storage se encarga de insertar en todas las tablas correspondientes.
        if ($airesult['success'] && is_array($airesponse) && !empty($airesponse['errors'])) {
            try {
                \local_smart_learning_mentor\storage\analysis_storage::save(
                    $courseid,
                    $cmid,
                    $vplid,
                    $userid,
                    $payload,
                    $airesponse,
                    $rawjson,
                    $conceptmap
                );
            } catch (\Exception $e) {
                // El guardado falló pero no interrumpimos el flujo:
                // el estudiante ya recibió la respuesta de la IA y la ve en pantalla.
                // El error queda en los logs para que el administrador lo revise.
                debugging(
                    'smart_learning_mentor: error guardando respuesta IA en BD: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }
 
        // 10. Enriquecer $airesponse (el output ya extraído) con recursos del profesor.
        //     Los recursos no se mandan a la IA pero se buscan en BD:
        //     error_concepto → conceptoid → recurso_concepto → recurso.
        //     Se modifica $airesponse directamente (tiene la clave 'errors').
        $enrichedairesponse = self::enrich_with_teacher_resources($airesponse, $courseid);
 
        // 11. Retornar el resultado al external → JS.
        //     El JS lee data.n8n_response.output, así que empaquetamos enriched en output.
        return [
            'status'    => $airesult['success'],
            'message'   => $airesult['success']
                ? get_string('success_analysis_sent', 'local_smart_learning_mentor')
                : get_string('error_sending_data',    'local_smart_learning_mentor'),
            'http_code' => $airesult['http_code'],
            'data'      => json_encode([
                'payload'      => $payload,
                'sent'         => $airesult['success'],
                'n8n_response' => ['output' => $enrichedairesponse],
                'error'        => $airesult['error'],
            ], JSON_UNESCAPED_UNICODE),
        ];
    }
 
    // MÉTODOS PRIVADOS
 
    /**
     * 2. Construye el payload completo para enviar a la IA.
     *    Incluye el contexto del estudiante y el catálogo pedagógico del profesor.
     *
     * @param int   $cmid        ID del módulo del curso
     * @param int   $userid      ID del estudiante
     * @param array $vpldata     Datos del VPL obtenidos de vpl_storage
     * @param array $submissions Historial de entregas ya obtenido
     * @param array $topics      Catálogo de temas y conceptos del profesor
     * @return array             Payload completo para la IA
     */
    private static function build_payload(
        int   $cmid,
        int   $userid,
        array $vpldata,
        array $submissions,
        array $topics
    ): array {
        return [
            'student_context' => [
                'userid' => $userid,
                'courseid' => (int)$vpldata['courseid'],
                'actividadid' => $cmid,
                'vpl' => [
                    'id' => (int)$vpldata['id'],
                    'name' => $vpldata['name'],
                    'context' => $vpldata['intro'],
                    'grade' => $vpldata['grade'],
                ],
                'submission_count' => count($submissions),
                'latest_submission' => !empty($submissions) ? $submissions[array_key_last($submissions)] : null,
                'submissions' => $submissions,
            ],
            'pedagogical_catalog' => [
                'themes' => $topics,
            ],
        ];
    }
 
    /**
     * 3. Construye el mapa de conceptos del catálogo del profesor.
     *    El mapa se usa en analysis_storage para cruzar los conceptos
     *    que devuelve la IA con los conceptos reales del catálogo.
     *
     *    Estructura: ['nombre_normalizado' => id_concepto]
     *    Ejemplo:    ['ciclo for' => 5, 'condicional if/else' => 7]
     *
     * @param array $topics Catálogo de temas con sus conceptos
     * @return array        Mapa [nombre_normalizado => id]
     */
    private static function build_concept_map(array $topics): array {
        $map = [];
 
        foreach ($topics as $topic) {
            foreach (($topic['concepts'] ?? []) as $concept) {
                $id   = (int)($concept['id'] ?? 0);
                $name = trim((string)($concept['name'] ?? ''));
 
                // Solo incluir conceptos que tienen ID real en la BD (no el catálogo estático).
                if ($id > 0 && $name !== '') {
                    $key       = self::normalize_concept($name);
                    $map[$key] = $id;
                }
            }
        }
 
        return $map;
    }
 
    /**
     * 4. Extrae la respuesta útil de la IA desde el JSON del webhook.
     *    Soporta distintas estructuras de respuesta que puede devolver n8n.
     *
     * @param string $rawresponse JSON crudo del webhook
     * @return array|null         Respuesta parseada o null si no se encontró
     */
    private static function extract_ai_response(string $rawresponse): ?array {
        if (empty($rawresponse)) {
            return null;
        }
 
        $decoded = json_decode($rawresponse, true);
 
        if (!is_array($decoded)) {
            return null;
        }
 
        // Variante 1: { response: { output: {...} } }
        if (!empty($decoded['response']['output']) && is_array($decoded['response']['output'])) {
            return $decoded['response']['output'];
        }
 
        // Variante 2: { output: {...} }
        if (!empty($decoded['output']) && is_array($decoded['output'])) {
            return $decoded['output'];
        }
 
        // Variante 3: la respuesta ya es el objeto directamente.
        return $decoded;
    }
 
    /**
     * 5. Normaliza un nombre de concepto para comparación.
     *    Minúsculas + sin espacios extras.
     *
     * @param string $text
     * @return string
     */
    private static function normalize_concept(string $text): string {
        $text = \core_text::strtolower(trim($text));
        $text = (string)preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }
 
    /**
     * 3. Enriquece la respuesta de la IA con los recursos del profesor.
     *    Para cada error, busca los conceptos del catálogo vinculados (error_concepto)
     *    y luego los recursos asociados a esos conceptos (recurso_concepto → recurso).
     *    Agrega "teacher_resources" a cada entrada de concepts en la respuesta.
     *
     * @param array|null $n8nresponse  Respuesta decodificada de la IA
     * @param int        $courseid
     * @return array|null
     */
    private static function enrich_with_teacher_resources(?array $n8nresponse, int $courseid): ?array {
        global $DB;
 
        if (empty($n8nresponse) || empty($n8nresponse['errors'])) {
            return $n8nresponse;
        }
 
        // Nombres de temas del curso (para excluirlos de los conceptos del profesor).
        $themenames = [];
        foreach ($DB->get_records('local_slm_temas', ['courseid' => $courseid], '', 'id, nombre') as $t) {
            $themenames[] = mb_strtolower(trim($t->nombre));
        }
 
        // Busqueda por NOMBRE directamente: si la IA da "FOR" y el catalogo tiene "FOR",
        // los recursos aparecen en todos los errores que mencionen "FOR",
        // sin depender de que ese concepto este en error_concepto para ese errorid concreto.
        $sql = "SELECT c.id AS cid, c.nombre AS cnombre,
                       rc.id AS rcid, r.titulo, r.cmid, r.url, r.tipo
                  FROM {local_slm_conceptos} c
                  JOIN {local_slm_temas} t ON t.id = c.temaid
                  LEFT JOIN {local_slm_recurso_concepto} rc ON rc.conceptoid = c.id
                  LEFT JOIN {local_slm_recurso} r ON r.id = rc.recursoid
                 WHERE t.courseid = :courseid
              ORDER BY c.nombre ASC, r.titulo ASC";
 
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid]);
 
        // Indexar: nombre_minusculas => [recursos...]
        $resourcesbynombre = [];
        foreach ($rows as $row) {
            $key = mb_strtolower(trim($row->cnombre));
            if (!isset($resourcesbynombre[$key])) {
                $resourcesbynombre[$key] = [];
            }
            if (!empty($row->rcid)) {
                $url = '';
                if (!empty($row->url)) {
                    $url = $row->url;
                } else if (!empty($row->cmid)) {
                    // Obtener el modname real del cmid para generar la URL correcta.
                    // r.tipo='curso' no indica el tipo de modulo, solo que viene del curso.
                    try {
                        $cm = get_coursemodule_from_id('', $row->cmid);
                        $realmodname = $cm ? $cm->modname : 'resource';
                    } catch (\Exception $e) {
                        $realmodname = 'resource';
                    }
                    $url = (new \moodle_url("/mod/{$realmodname}/view.php", ['id' => $row->cmid]))->out(false);
                }
                $already = array_column($resourcesbynombre[$key], 'titulo');
                if (!in_array($row->titulo, $already)) {
                    $resourcesbynombre[$key][] = ['titulo' => $row->titulo, 'url' => $url];
                }
            }
        }
 
        // Enriquecer cada error en la respuesta de la IA.
        foreach ($n8nresponse['errors'] as &$error) {
            $enrichedconcepts = [];
            $seennames        = [];
 
            foreach ($error['concepts'] ?? [] as $conceptname) {
                $name = trim((string)$conceptname);
                if (in_array($name, $seennames)) {
                    continue;
                }
                $seennames[] = $name;
 
                // Conceptos IA (prefijo "IA:") => omitir (deshabilitado temporalmente).
                // TODO: habilitar cuando el ingeniero lo pida.
                if (stripos($name, 'IA:') === 0) {
                    continue;
                }
 
                // Excluir si es un tema (no un concepto del catalogo).
                if (in_array(mb_strtolower($name), $themenames)) {
                    continue;
                }
 
                // Buscar recursos directamente por nombre del concepto.
                $normalizedname   = mb_strtolower($name);
                $conceptresources = $resourcesbynombre[$normalizedname] ?? [];
                // DEBUG: log para diagnóstico.
                debugging(
                    "slm enrich: concepto='{$name}' key='{$normalizedname}' recursos=" . count($conceptresources) .
                    " keys_disponibles=" . implode('|', array_keys($resourcesbynombre)),
                    DEBUG_DEVELOPER
                );
 
                $enrichedconcepts[] = [
                    'name'      => $name,
                    'resources' => $conceptresources,
                ];
            }
 
            $error['concepts'] = $enrichedconcepts;
        }
        unset($error);
 
        return $n8nresponse;
    }
 
}
 