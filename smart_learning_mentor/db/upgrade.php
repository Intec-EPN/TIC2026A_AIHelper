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
 * Plugin upgrade steps are defined here.
 * Pasos de actualización de la BD del plugin Smart Learning Mentor
 *
 * @package     local_smart_learning_mentor
 * @category    upgrade
 * @copyright   2026 Estefania Martinez <joselyn.martinez@epn.edu.ec>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_smart_learning_mentor upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_smart_learning_mentor_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.

    // if ($oldversion < 2026070100) {
    //     $table = new xmldb_table('local_slm_nueva_tabla');
    //     $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    //     ...
    //     $dbman->create_table($table);
    //     upgrade_plugin_savepoint(true, 2026070100, 'local', 'smart_learning_mentor');
    // }

    if ($oldversion < 2026062709) {

        // 1. Eliminar la FK y el campo conceptoid de local_slm_recurso si existen.
        $tablerecurso = new xmldb_table('local_slm_recurso');

        // 1a. Eliminar FK recurso-conc-fk si existe.
        $keyconcfk = new xmldb_key('recurso-conc-fk', XMLDB_KEY_FOREIGN, ['conceptoid'], 'local_slm_conceptos', ['id']);
        if ($dbman->find_key_name($tablerecurso, $keyconcfk)) {
            $dbman->drop_key($tablerecurso, $keyconcfk);
        }

        // 1b. Eliminar unique key recurso-uk (conceptoid,titulo) si existe.
        $keyuk = new xmldb_key('recurso-uk', XMLDB_KEY_UNIQUE, ['conceptoid', 'titulo']);
        if ($dbman->find_key_name($tablerecurso, $keyuk)) {
            $dbman->drop_key($tablerecurso, $keyuk);
        }

        // 1c. Eliminar indice recurso-conc-idx si existe.
        $idxconc = new xmldb_index('recurso-conc-idx', XMLDB_INDEX_NOTUNIQUE, ['conceptoid']);
        if ($dbman->index_exists($tablerecurso, $idxconc)) {
            $dbman->drop_index($tablerecurso, $idxconc);
        }

        // 1d. Eliminar el campo conceptoid si existe.
        $fieldconceptoid = new xmldb_field('conceptoid');
        if ($dbman->field_exists($tablerecurso, $fieldconceptoid)) {
            $dbman->drop_field($tablerecurso, $fieldconceptoid);
        }

        // 1e. Agregar unique key (courseid, cmid) para evitar duplicados de recursos del curso.
        //     Solo si no existe ya.
        $keycmiduk = new xmldb_key('recurso-cmid-uk', XMLDB_KEY_UNIQUE, ['courseid', 'cmid']);
        if (!$dbman->find_key_name($tablerecurso, $keycmiduk)) {
            $dbman->add_key($tablerecurso, $keycmiduk);
        }

        // 2. Crear la tabla pivot local_slm_recurso_concepto si no existe.
        $tablepivot = new xmldb_table('local_slm_recurso_concepto');

        $tablepivot->add_field('id',          XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $tablepivot->add_field('recursoid',   XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');
        $tablepivot->add_field('conceptoid',  XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');
        $tablepivot->add_field('timecreated', XMLDB_TYPE_INTEGER, '10',   null, XMLDB_NOTNULL, null, '0');

        $tablepivot->add_key('primary',          XMLDB_KEY_PRIMARY, ['id']);
        $tablepivot->add_key('rc-recurso-fk',    XMLDB_KEY_FOREIGN, ['recursoid'],  'local_slm_recurso',    ['id']);
        $tablepivot->add_key('rc-concepto-fk',   XMLDB_KEY_FOREIGN, ['conceptoid'], 'local_slm_conceptos',  ['id']);
        $tablepivot->add_key('rc-uk',            XMLDB_KEY_UNIQUE,  ['recursoid', 'conceptoid']);

        if (!$dbman->table_exists($tablepivot)) {
            $dbman->create_table($tablepivot);
        }

        upgrade_plugin_savepoint(true, 2026062709, 'local', 'smart_learning_mentor');
    }



    return true;
}
