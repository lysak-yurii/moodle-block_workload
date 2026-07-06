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
 * External function: user search for the statistics and enrollment pages.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_workload\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use context_system;
use required_capability_exception;

/**
 * External API: search users by name or email for the "view stats as" and
 * enrollment-management autocompletes.
 *
 * Returns up to 20 matching users. The query is matched case-insensitively
 * against firstname, lastname, the concatenated full name, and email.
 */
class search_stats_users extends external_api {
    /**
     * Return the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query'    => new external_value(PARAM_TEXT, 'Search query (name or email, min 2 chars)'),
            'cohortid' => new external_value(
                PARAM_INT,
                'Restrict results to members of this workload cohort (0 = no restriction)',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Search for users matching the given query.
     *
     * @param string $query
     * @param int $cohortid
     * @return array
     */
    public static function execute(string $query, int $cohortid = 0): array {
        global $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['query' => $query, 'cohortid' => $cohortid]
        );

        $syscontext = context_system::instance();
        self::validate_context($syscontext);

        $canmanage = has_capability('block/workload:manage', $syscontext);
        if (!$canmanage) {
            require_capability('block/workload:viewallstats', $syscontext);

            // Anonymized statistics viewers must not search by real name/email — a
            // match would reveal which pseudonym belongs to whom. Managers keep
            // search for the management UI, which is outside the anonymization scope.
            if (\block_workload\helper::is_anonymized()) {
                throw new required_capability_exception(
                    $syscontext,
                    'block/workload:viewrealnames',
                    'nopermissions',
                    ''
                );
            }
        }

        $q = trim($params['query']);
        if (\core_text::strlen($q) < 2) {
            return ['users' => []];
        }

        $like = '%' . $DB->sql_like_escape($q) . '%';

        // Optional cohort restriction.
        $join      = '';
        $sqlparams = [];
        if ($params['cohortid'] > 0) {
            $join = "JOIN {block_workload_members} wm ON wm.userid = u.id AND wm.cohortid = :cohortid";
            $sqlparams['cohortid'] = $params['cohortid'];
        }

        $fnlike = $DB->sql_like('u.firstname', ':fn', false);
        $lnlike = $DB->sql_like('u.lastname', ':ln', false);
        $emlike = $DB->sql_like('u.email', ':em', false);
        $fllike = $DB->sql_like(
            $DB->sql_concat('u.firstname', "' '", 'u.lastname'),
            ':fl',
            false
        );

        $sqlparams += ['fn' => $like, 'ln' => $like, 'em' => $like, 'fl' => $like];

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email
                  FROM {user} u
                  {$join}
                 WHERE u.deleted   = 0
                   AND u.confirmed = 1
                   AND ($fnlike OR $lnlike OR $emlike OR $fllike)
              ORDER BY u.lastname ASC, u.firstname ASC";

        $records = $DB->get_records_sql($sql, $sqlparams, 0, 20);

        $users = [];
        foreach ($records as $u) {
            $users[] = [
                'id'       => (int) $u->id,
                'fullname' => $u->firstname . ' ' . $u->lastname,
                'email'    => $u->email,
            ];
        }
        return ['users' => $users];
    }

    /**
     * Return the structure of the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'User ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                    'email'    => new external_value(PARAM_TEXT, 'Email address'),
                ])
            ),
        ]);
    }
}
