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
* Acceso a datos de VPL: entregas, archivos y resultados de ejecución.
 *
 * Descripción del flujo:
 *   1. vpl_analysis_application necesita las entregas del estudiante.
 *   2. Llama a vpl_storage::get_submission_history() con cmid y userid.
 *   3. vpl_storage consulta la BD (tabla vpl_submissions) y el sistema de archivos de VPL.
 *   4. Retorna el historial estructurado con archivos y resultados de ejecución.
 *   5. vpl_analysis_application usa esos datos para construir el payload de la IA.
 *
 * Lectura y escritura en la base de datos:
 *   - Único lugar donde se usa $DB para leer datos de VPL.
 *   - También lee archivos del sistema de archivos de VPL (dataroot).
 *
 * NOTAS: aqui consulta SQL, no tiene logica del negocio, no realiza calculos ni analisis, ni llamadas a la IA
 *
 * @package   repository_pluginname
 * @copyright 2026, Estefania Martinez <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smart_learning_mentor\storage;

defined('MOODLE_INTERNAL') || die();

class vpl_storage {

    /**
     * 1. Obtiene la información básica de la actividad VPL a partir del cmid.
     *
     * @param int $cmid ID del módulo del curso
     * @return array    Datos del VPL: id, name, intro, grade, courseid
     */
    public static function get_vpl_info(int $cmid): array {
        global $DB;

        // 1a. Obtener el registro del módulo del curso.
        $cm  = get_coursemodule_from_id('vpl', $cmid, 0, false, MUST_EXIST);
        $vpl = $DB->get_record('vpl', ['id' => $cm->instance], '*', MUST_EXIST);

        return [
            'id'       => (int)$vpl->id,
            'name'     => $vpl->name,
            'intro'    => strip_tags((string)$vpl->intro),
            'grade'    => isset($vpl->grade) ? (float)$vpl->grade : null,
            'courseid' => (int)$cm->course,
        ];
    }

    /**
     * 2. Obtiene todas las entregas del estudiante para un VPL específico.
     *    Cada entrega incluye: archivos de código, resultados de compilación y ejecución.
     *
     * @param int $cmid   ID del módulo del curso
     * @param int $userid ID del estudiante
     * @return array      Historial de entregas numeradas cronológicamente
     */
    public static function get_submission_history(int $cmid, int $userid): array {
        global $DB;

        // a. obtiene el ID de la activdad VPL desde el modulo del curso
        $cm = get_coursemodule_from_id('vpl', $cmid, 0, false, MUST_EXIST);
        $vplid = (int)$cm->instance;

        // b consulta todas las entregas del estudiante
        $sql = "SELECT *
                  FROM {vpl_submissions}
                 WHERE vpl = :vplid
                   AND userid = :userid
              ORDER BY datesubmitted ASC, id ASC";

        $records = $DB->get_records_sql($sql, [
            'vplid'  => $vplid,
            'userid' => $userid,
        ]);

        // c. contruye el historial entregas
        $history = [];
        $attemptnumber = 1;

        foreach ($records as $submission) {
            $history[] = self::build_submission_record($cmid, $userid, $vplid, $submission, $attemptnumber);
            $attemptnumber++;
        }

        return $history;
    }

    /**
     * 3. Construye el registro estructurado de una entrega individual.
     *    Incluye archivos de código, resultados de ejecución y calificación propuesta.
     *
     * @param int    $cmid          ID del módulo del curso
     * @param int    $userid        ID del estudiante
     * @param int    $vplid         ID del VPL
     * @param object $submission    Registro de la entrega desde la BD
     * @param int    $attemptnumber Número de intento (1, 2, 3...)
     * @return array                Registro estructurado de la entrega
     */
    private static function build_submission_record(
        int $cmid,
        int $userid,
        int $vplid,
        object $submission,
        int $attemptnumber
    ): array {
        // 3a. Obtener los archivos de código de esta entrega.
        $files     = self::get_submission_files($cmid, $submission, $userid, $vplid);

        // 3b. Obtener los resultados de compilación y ejecución.
        $execution = self::get_execution_results($vplid, $userid, (int)$submission->id);

        // 3c. Intentar extraer la nota propuesta por el evaluador automático.
        $proposedgrade = self::extract_proposed_grade($vplid, $userid, (int)$submission->id);

        return [
            'attempt_number' => $attemptnumber,
            'submission_id'  => (int)$submission->id,
            'datesubmitted'  => isset($submission->datesubmitted) ? (int)$submission->datesubmitted : null,
            'grade'          => $submission->grade ?? null,
            'proposed_grade' => $proposedgrade,
            'files'          => $files,
            'execution'      => $execution,
            'raw_submission' => [
                'save_count'  => $submission->savecount  ?? null,
                'run_count'   => $submission->runcount   ?? null,
                'debug_count' => $submission->debugcount ?? null,
            ],
        ];
    }

    /**
     * 4. Obtiene los archivos de código de una entrega.
     *    Primero intenta desde el file storage de Moodle, luego desde el sistema de archivos de VPL.
     *
     * @param int    $cmid       ID del módulo del curso
     * @param object $submission Registro de la entrega
     * @param int    $userid     ID del estudiante
     * @param int    $vplid      ID del VPL
     * @return array             Lista de archivos con nombre y contenido
     */
    private static function get_submission_files(int $cmid, object $submission, int $userid, int $vplid): array {
        global $CFG;

        $filesdata = [];

        // 4a. Intentar obtener archivos desde el file storage de Moodle.
        $fs      = get_file_storage();
        $context = \context_module::instance($cmid);
        $files   = $fs->get_area_files(
            $context->id,
            'mod_vpl',
            'submission_files',
            $submission->id,
            'filename',
            false
        );

        foreach ($files as $file) {
            if (!$file->is_directory()) {
                $filesdata[] = [
                    'filename' => $file->get_filename(),
                    'content'  => $file->get_content(),
                    'size'     => $file->get_filesize(),
                ];
            }
        }

        // 4b. Si no se encontraron archivos en file storage, leer desde el sistema de archivos de VPL.
        if (empty($filesdata)) {
            $path = $CFG->dataroot . "/vpl_data/$vplid/usersdata/$userid/{$submission->id}/submittedfiles/";

            if (is_dir($path)) {
                foreach (scandir($path) as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    $fullpath = $path . $file;
                    if (is_file($fullpath)) {
                        $filesdata[] = [
                            'filename' => $file,
                            'content'  => file_get_contents($fullpath),
                            'size'     => filesize($fullpath),
                        ];
                    }
                }
            }
        }

        return $filesdata;
    }

    /**
     * 5. Obtiene los resultados de compilación y ejecución de una entrega.
     *    Lee los archivos de texto generados por VPL en el dataroot.
     *
     * @param int $vplid        ID del VPL
     * @param int $userid       ID del estudiante
     * @param int $submissionid ID de la entrega
     * @return array            Resultados de compilación, ejecución, stdout y stderr
     */
    private static function get_execution_results(int $vplid, int $userid, int $submissionid): array {
        global $CFG;

        $basepath = $CFG->dataroot . "/vpl_data/$vplid/usersdata/$userid/$submissionid/";

        // 5a. Función auxiliar para leer un archivo si existe.
        $readfile = function(string $path): ?string {
            return file_exists($path) ? file_get_contents($path) : null;
        };

        $executioncontent  = $readfile($basepath . 'execution.txt');
        $compilationcontent = $readfile($basepath . 'compilation.txt');
        $gradecomments     = $readfile($basepath . 'grade_comments.txt');

        // 5b. Extraer stdout y stderr desde el contenido de ejecución.
        $stdout = null;
        $stderr = null;

        if ($executioncontent !== null) {
            if (strpos($executioncontent, '--- Program output ---') !== false) {
                $parts = explode('--- Program output ---', $executioncontent);
                if (count($parts) > 1) {
                    $outputpart = $parts[1];
                    $stdout = (strpos($outputpart, '--- Expected output') !== false)
                        ? trim(explode('--- Expected output', $outputpart)[0])
                        : trim($outputpart);
                }
            }

            if (
                strpos($executioncontent, 'Incorrect program output') !== false ||
                strpos($executioncontent, 'Runtime error') !== false
            ) {
                $stderr = $executioncontent;
            }
        }

        if (!empty(trim((string)$compilationcontent))) {
            $stderr = $compilationcontent;
        }

        return [
            'compilation_output' => $compilationcontent,
            'execution_output'   => $executioncontent,
            'grade_comments'     => $gradecomments,
            'stdout'             => $stdout,
            'stderr'             => $stderr,
        ];
    }

    /**
     * 6. Extrae la nota propuesta por el evaluador automático desde execution.txt.
     *    Busca el patrón "Grade :=> X" en el archivo de ejecución.
     *
     * @param int $vplid        ID del VPL
     * @param int $userid       ID del estudiante
     * @param int $submissionid ID de la entrega
     * @return float|null       Nota propuesta o null si no se encontró
     */
    private static function extract_proposed_grade(int $vplid, int $userid, int $submissionid): ?float {
        global $CFG;

        $filepath = $CFG->dataroot . "/vpl_data/$vplid/usersdata/$userid/$submissionid/execution.txt";

        if (!file_exists($filepath)) {
            return null;
        }

        $content = file_get_contents($filepath);

        if ($content === false || empty($content)) {
            return null;
        }

        if (preg_match('/Grade\s*:=>\s*([0-9]+(?:\.[0-9]+)?)/', $content, $matches)) {
            return (float)$matches[1];
        }

        return null;
    }
}

