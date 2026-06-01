<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Privacy provider for block_workload (GDPR compliance).
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_workload\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_workload_entries', [
            'userid'     => 'privacy:metadata:block_workload_entries:userid',
            'courseid'   => 'privacy:metadata:block_workload_entries:courseid',
            'weeknumber' => 'privacy:metadata:block_workload_entries:weeknumber',
            'year'       => 'privacy:metadata:block_workload_entries:year',
            'hours'      => 'privacy:metadata:block_workload_entries:hours',
        ], 'privacy:metadata:block_workload_entries');

        $collection->add_database_table('block_workload_members', [
            'userid'   => 'privacy:metadata:block_workload_members:userid',
            'cohortid' => 'privacy:metadata:block_workload_members:cohortid',
        ], 'privacy:metadata:block_workload_members');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // All data lives at system context.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :ctxlevel
                   AND (
                         EXISTS (SELECT 1 FROM {block_workload_entries} WHERE userid = :uid1)
                      OR EXISTS (SELECT 1 FROM {block_workload_members} WHERE userid = :uid2)
                   )";
        $contextlist->add_from_sql($sql, [
            'ctxlevel' => CONTEXT_SYSTEM,
            'uid1'     => $userid,
            'uid2'     => $userid,
        ]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_table('block_workload_entries', 'userid');
        $userlist->add_from_table('block_workload_members', 'userid');
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid  = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $entries = $DB->get_records('block_workload_entries', ['userid' => $userid]);
        if ($entries) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_workload'), 'entries'],
                (object) ['entries' => array_values($entries)]
            );
        }

        $memberships = $DB->get_records('block_workload_members', ['userid' => $userid]);
        if ($memberships) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_workload'), 'memberships'],
                (object) ['memberships' => array_values($memberships)]
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        global $DB;
        $DB->delete_records('block_workload_entries');
        $DB->delete_records('block_workload_members');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('block_workload_entries', ['userid' => $userid]);
        $DB->delete_records('block_workload_members', ['userid' => $userid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('block_workload_entries', "userid $insql", $params);
        $DB->delete_records_select('block_workload_members', "userid $insql", $params);
    }
}
