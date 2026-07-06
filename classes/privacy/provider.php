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

namespace block_workload\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for block_workload (GDPR compliance).
 *
 * @package   block_workload
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Return the metadata about the data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_workload_cohorts', [
            'createdby' => 'privacy:metadata:block_workload_cohorts:createdby',
        ], 'privacy:metadata:block_workload_cohorts');

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

        $collection->add_database_table('block_workload_user_courses', [
            'userid'    => 'privacy:metadata:block_workload_user_courses:userid',
            'courseid'  => 'privacy:metadata:block_workload_user_courses:courseid',
            'active'    => 'privacy:metadata:block_workload_user_courses:active',
            'sortorder' => 'privacy:metadata:block_workload_user_courses:sortorder',
        ], 'privacy:metadata:block_workload_user_courses');

        $collection->add_database_table('block_workload_user_settings', [
            'userid' => 'privacy:metadata:block_workload_user_settings:userid',
            'active' => 'privacy:metadata:block_workload_user_settings:active',
        ], 'privacy:metadata:block_workload_user_settings');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the given user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // All data lives at system context.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :ctxlevel
                   AND (
                         EXISTS (SELECT 1 FROM {block_workload_entries}     WHERE userid    = :uid1)
                      OR EXISTS (SELECT 1 FROM {block_workload_members}     WHERE userid    = :uid2)
                      OR EXISTS (SELECT 1 FROM {block_workload_user_courses} WHERE userid   = :uid3)
                      OR EXISTS (SELECT 1 FROM {block_workload_user_settings} WHERE userid  = :uid4)
                      OR EXISTS (SELECT 1 FROM {block_workload_cohorts}     WHERE createdby = :uid5)
                   )";
        $contextlist->add_from_sql($sql, [
            'ctxlevel' => CONTEXT_SYSTEM,
            'uid1'     => $userid,
            'uid2'     => $userid,
            'uid3'     => $userid,
            'uid4'     => $userid,
            'uid5'     => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT DISTINCT userid    FROM {block_workload_entries}', []);
        $userlist->add_from_sql('userid', 'SELECT DISTINCT userid    FROM {block_workload_members}', []);
        $userlist->add_from_sql('userid', 'SELECT DISTINCT userid    FROM {block_workload_user_courses}', []);
        $userlist->add_from_sql('userid', 'SELECT DISTINCT userid    FROM {block_workload_user_settings}', []);
        $userlist->add_from_sql('createdby', 'SELECT DISTINCT createdby FROM {block_workload_cohorts}', []);
    }

    /**
     * Export personal data for the given approved contextlist.
     *
     * @param approved_contextlist $contextlist
     */
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

        $courseoverrides = $DB->get_records('block_workload_user_courses', ['userid' => $userid]);
        if ($courseoverrides) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_workload'), 'course_overrides'],
                (object) ['course_overrides' => array_values($courseoverrides)]
            );
        }

        $settings = $DB->get_records('block_workload_user_settings', ['userid' => $userid]);
        if ($settings) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_workload'), 'user_settings'],
                (object) ['user_settings' => array_values($settings)]
            );
        }

        $cohorts = $DB->get_records('block_workload_cohorts', ['createdby' => $userid]);
        if ($cohorts) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_workload'), 'created_cohorts'],
                (object) ['cohorts' => array_values($cohorts)]
            );
        }
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        global $DB;
        $DB->delete_records('block_workload_entries');
        $DB->delete_records('block_workload_members');
        $DB->delete_records('block_workload_user_courses');
        $DB->delete_records('block_workload_user_settings');
        // Createdby is NOTNULL — anonymise rather than delete the cohort row.
        $DB->set_field('block_workload_cohorts', 'createdby', 0);
    }

    /**
     * Delete all user data for the approved contextlist.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $hassystemctx = false;
        foreach ($contextlist->get_contexts() as $ctx) {
            if ($ctx->contextlevel === CONTEXT_SYSTEM) {
                $hassystemctx = true;
                break;
            }
        }
        if (!$hassystemctx) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('block_workload_entries', ['userid' => $userid]);
        $DB->delete_records('block_workload_members', ['userid' => $userid]);
        $DB->delete_records('block_workload_user_courses', ['userid' => $userid]);
        $DB->delete_records('block_workload_user_settings', ['userid' => $userid]);
        $DB->set_field('block_workload_cohorts', 'createdby', 0, ['createdby' => $userid]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        global $DB;
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('block_workload_entries', "userid $insql", $params);
        $DB->delete_records_select('block_workload_members', "userid $insql", $params);
        $DB->delete_records_select('block_workload_user_courses', "userid $insql", $params);
        $DB->delete_records_select('block_workload_user_settings', "userid $insql", $params);
        $DB->set_field_select('block_workload_cohorts', 'createdby', 0, "createdby $insql", $params);
    }
}
