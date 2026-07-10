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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Upgrade steps for block_workload.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run upgrade steps for block_workload.
 *
 * @param int $oldversion the version we are upgrading from.
 * @return bool
 */
function xmldb_block_workload_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061200) {
        // Allow regular users to export their own statistics as CSV.
        // Archetype defaults in db/access.php only apply on fresh installs,
        // so grant the capability to existing 'user' archetype roles here.
        $syscontext = context_system::instance();
        foreach (get_archetype_roles('user') as $role) {
            assign_capability('block/workload:export', CAP_ALLOW, $role->id, $syscontext->id, true);
        }

        upgrade_block_savepoint(true, 2026061200, 'workload');
    }

    if ($oldversion < 2026070200) {
        // Statistics anonymization: secret used to derive stable,
        // non-reversible pseudonyms.
        // The new block/workload:viewrealnames capability is installed by
        // update_capabilities(), which applies the 'manager' archetype
        // default to existing manager-archetype roles. The workload_manager
        // role has no archetype, so it is intentionally NOT granted.
        if (!get_config('block_workload', 'anonsalt')) {
            set_config('anonsalt', bin2hex(random_bytes(16)), 'block_workload');
        }

        upgrade_block_savepoint(true, 2026070200, 'workload');
    }

    if ($oldversion < 2026070300) {
        // Teacher course statistics. The new block/workload:viewcoursestats
        // capability is installed by update_capabilities(), which applies
        // the editingteacher/teacher/manager archetype defaults to existing
        // roles. Intentionally NOT granted to the workload_manager role — it
        // already holds the site-wide viewallstats.
        // Seed the anonymizeteacherstats default explicitly so get_config()
        // never returns false-meaning-unset (the privacy default is ON).
        if (get_config('block_workload', 'anonymizeteacherstats') === false) {
            set_config('anonymizeteacherstats', 1, 'block_workload');
        }

        upgrade_block_savepoint(true, 2026070300, 'workload');
    }

    if ($oldversion < 2026071000) {
        // Global course hiding for enrollment mode: courses listed here are
        // hidden from every student's workload dashboard by default (a
        // per-student force-include override still wins).
        $table = new xmldb_table('block_workload_global_courses');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);
        $table->add_key('fk_createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026071000, 'workload');
    }

    if ($oldversion < 2026071001) {
        // Make block_workload_user_courses.active nullable so a row can hold a
        // sort position without asserting an include/exclude opinion. This
        // disentangles the sort-order tracking rows (previously written as
        // active=1 by the reorder feature) from deliberate force-includes, so
        // the global-hide override can trust active=1 to mean "force-shown".
        $table = new xmldb_table('block_workload_user_courses');
        $field = new xmldb_field('active', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'courseid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
            $dbman->change_field_default($table, $field);
        }

        // Migrate existing sort-order tracking rows to NULL. Any active=1 row on
        // a course the student is currently enrolled in was a reorder side-effect
        // (there was no reason to force-include an already-enrolled course before
        // global hide existed). Genuine force-adds (active=1 on a NOT-enrolled
        // course) are left untouched.
        $DB->execute("
            UPDATE {block_workload_user_courses}
               SET active = NULL
             WHERE active = 1
               AND EXISTS (
                   SELECT 1
                     FROM {user_enrolments} ue
                     JOIN {enrol} e ON e.id = ue.enrolid
                    WHERE e.courseid = {block_workload_user_courses}.courseid
                      AND ue.userid = {block_workload_user_courses}.userid
                      AND e.status = 0
                      AND ue.status = 0
               )
        ");

        upgrade_block_savepoint(true, 2026071001, 'workload');
    }

    return true;
}
