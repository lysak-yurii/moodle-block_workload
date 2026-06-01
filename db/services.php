<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External function declarations for block_workload.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'block_workload_save_hours' => [
        'classname'     => 'block_workload\external\save_hours',
        'methodname'    => 'execute',
        'description'   => 'Save workload hours for a course in a specific week',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/workload:submit',
    ],

    'block_workload_get_week_data' => [
        'classname'     => 'block_workload\external\get_week_data',
        'methodname'    => 'execute',
        'description'   => 'Get workload entries for the current week',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/workload:submit',
    ],

    'block_workload_search_users' => [
        'classname'     => 'block_workload\external\search_users',
        'methodname'    => 'execute',
        'description'   => 'Search users by name or email for the member-add autocomplete',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/workload:manage',
    ],
];
