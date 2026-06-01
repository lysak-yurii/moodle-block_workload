<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade steps for block_workload.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_block_workload_upgrade($oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024060101) {
        // Add block_workload_user_courses: per-student course overrides for enrollment mode.
        $table = new xmldb_table('block_workload_user_courses');

        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('active',       XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary',    XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_userid',  XMLDB_KEY_FOREIGN, ['userid'],   'user',   ['id']);
        $table->add_key('fk_course',  XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('user_course', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2024060101, 'workload');
    }

    return true;
}
