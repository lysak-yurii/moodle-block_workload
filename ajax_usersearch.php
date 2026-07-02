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
 * AJAX user-search endpoint for the workload statistics page.
 *
 * Returns a JSON array of up to 20 matching users:
 *   [{"id": 42, "fullname": "Jane Doe", "email": "jane.doe@uni.de"}, ...]
 *
 * GET parameters:
 *   q        – search string (min 2 chars; matched against firstname, lastname,
 *              concatenated full name, and email)
 *   cohortid – optional; when > 0, restricts results to members of that cohort
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_login();

$syscontext = context_system::instance();
if (
    !has_capability('block/workload:viewallstats', $syscontext) &&
    !has_capability('block/workload:manage', $syscontext)
) {
    http_response_code(403);
    echo json_encode([]);
    die;
}

// Anonymized statistics viewers must not search by real name/email — a match
// would reveal which pseudonym belongs to whom. Managers keep search for the
// management UI, which is outside the anonymization scope.
if (
    !has_capability('block/workload:manage', $syscontext) &&
    \block_workload\helper::is_anonymized()
) {
    http_response_code(403);
    echo json_encode([]);
    die;
}

$q        = optional_param('q', '', PARAM_TEXT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);

header('Content-Type: application/json; charset=utf-8');

global $DB;

$q = trim($q);
if (core_text::strlen($q) < 2) {
    echo json_encode([]);
    die;
}

$like = '%' . $DB->sql_like_escape($q) . '%';

// Optional cohort restriction.
$join   = '';
$params = [];
if ($cohortid > 0) {
    $join             = "JOIN {block_workload_members} wm ON wm.userid = u.id AND wm.cohortid = :cohortid";
    $params['cohortid'] = $cohortid;
}

$fnlike = $DB->sql_like('u.firstname', ':fn', false);
$lnlike = $DB->sql_like('u.lastname', ':ln', false);
$emlike = $DB->sql_like('u.email', ':em', false);
$fllike = $DB->sql_like(
    $DB->sql_concat('u.firstname', "' '", 'u.lastname'),
    ':fl',
    false
);

$params += ['fn' => $like, 'ln' => $like, 'em' => $like, 'fl' => $like];

$sql = "SELECT u.id, u.firstname, u.lastname, u.email
          FROM {user} u
          {$join}
         WHERE u.deleted   = 0
           AND u.confirmed = 1
           AND ($fnlike OR $lnlike OR $emlike OR $fllike)
         ORDER BY u.lastname ASC, u.firstname ASC";

$users   = $DB->get_records_sql($sql, $params, 0, 20);
$results = [];
foreach ($users as $u) {
    $results[] = [
        'id'       => (int) $u->id,
        'fullname' => $u->firstname . ' ' . $u->lastname,
        'email'    => $u->email,
    ];
}

echo json_encode($results);
