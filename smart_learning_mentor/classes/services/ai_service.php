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
 * Servicio de comunicación con la IA externa (n8n, OpenAI....)
 *
 * Descripción:
 *   Única clase responsable de enviar peticiones HTTP al webhook de IA
 *   y retornar la respuesta cruda.
 *   No procesa la respuesta ni guarda datos en la BD.
 *
 * Flujo:
 *   1. vpl_analysis_application construye el payload JSON del estudiante.
 *   2. Llama a ai_service::send() con el JSON y las credenciales del webhook.
 *   3. ai_service envía el POST al webhook configurado en settings.php.
 *   4. Recibe la respuesta HTTP y la retorna como array.
 *   5. vpl_analysis_application procesa y extrae la respuesta util.
 * 
 * NOTA: Solo se encarga de enviar y recibir, NO proces ni interpreta la respuesta
 * 
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\services;

defined('MOODLE_INTERNAL') || die();

class ai_service {

    // 1. se encarga de enviar el payload JSON al webhook configurado y retorna la respuesta HTTP
    public static function send(string $jsondata, string $webhookurl, string $token = ''): array {
        // a. inicializa cURL.
        $curl = curl_init();

        // b. preapara los headers HTTP del request
        $headers = [
            'Content-Type: application/json',
            'Authorization: ' . $token,
            'Content-Length: ' . strlen($jsondata),
        ];

        // c. agrega el token de autorizacion en caso que este configurado
        if (!empty($token)) {
            $headers[] = 'Authorization: ' . $token;
        }

        // d. configura las opciones de cURL
        curl_setopt_array($curl, [
            CURLOPT_URL => $webhookurl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsondata,
            CURLOPT_HTTPHEADER => $headers,
            // Timeout extendido a 60s para soportar respuestas de la IA
            CURLOPT_TIMEOUT => 120,
        ]);

        // e. ejecuta la peticion y obtiene la respuesta
        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        // f. retorna el resultado estructurado.
        return [
            'success' => ($error === '' && $httpcode >= 200 && $httpcode < 300),
            'http_code' => $httpcode,
            'response' => $response,
            'error' => $error,
        ];
    }
}
