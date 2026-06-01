<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * External function: user search autocomplete for the member-add panel.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_workload\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_system;

class search_users extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search query (name or email)'),
        ]);
    }

    public static function execute(string $query): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['query' => $query]);

        $syscontext = context_system::instance();
        self::validate_context($syscontext);
        require_capability('block/workload:manage', $syscontext);

        $q = trim($params['query']);
        if (strlen($q) < 2) {
            return ['users' => []];
        }

        $sqlparams = [
            'deleted' => 0,
            'notsite' => SITEID,
            'name'    => '%' . $DB->sql_like_escape($q) . '%',
            'email'   => '%' . $DB->sql_like_escape($q) . '%',
        ];
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, u.institution
                  FROM {user} u
                 WHERE u.deleted = :deleted
                   AND u.id <> :notsite
                   AND (" . $DB->sql_like($DB->sql_concat('u.firstname', "' '", 'u.lastname'), ':name', false)
                   . " OR " . $DB->sql_like('u.email', ':email', false) . ")
              ORDER BY u.lastname ASC, u.firstname ASC";

        $records = $DB->get_records_sql($sql, $sqlparams, 0, 10);

        $users = [];
        foreach ($records as $u) {
            $users[] = [
                'id'         => (int) $u->id,
                'fullname'   => $u->firstname . ' ' . $u->lastname,
                'email'      => $u->email,
                'department' => (string) ($u->department ?? ''),
            ];
        }
        return ['users' => $users];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'id'         => new external_value(PARAM_INT,  'User ID'),
                    'fullname'   => new external_value(PARAM_TEXT, 'Full name'),
                    'email'      => new external_value(PARAM_TEXT, 'Email address'),
                    'department' => new external_value(PARAM_TEXT, 'Department'),
                ])
            ),
        ]);
    }
}
