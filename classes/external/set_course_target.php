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
 * External function: set or clear a course's workload target hours.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_workload\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use context_system;

/**
 * External API: set or clear a course's workload target (manager inline edit).
 */
class set_course_target extends external_api {
    /**
     * Return the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'    => new external_value(PARAM_INT, 'Course ID'),
            'targethours' => new external_value(PARAM_FLOAT, 'Target hours (0 or less clears the course target)'),
        ]);
    }

    /**
     * Set or clear the target for a course and report the effective value.
     *
     * @param int $courseid
     * @param float $targethours
     * @return array
     */
    public static function execute(int $courseid, float $targethours): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'targethours'));

        $syscontext = context_system::instance();
        self::validate_context($syscontext);
        require_capability('block/workload:manage', $syscontext);

        $course = $DB->get_record('course', ['id' => $params['courseid']], 'id, fullname', MUST_EXIST);

        if ($params['targethours'] > 0) {
            \block_workload\helper::set_course_target((int) $course->id, $params['targethours'], (int) $USER->id);
            $eventtarget = $params['targethours'];
        } else {
            \block_workload\helper::delete_course_target((int) $course->id);
            $eventtarget = null;
        }

        \block_workload\event\course_target_updated::create([
            'objectid' => (int) $course->id,
            'context'  => $syscontext,
            'other'    => ['coursename' => $course->fullname, 'targethours' => $eventtarget],
        ])->trigger();

        // Report the resulting effective target and where it comes from.
        $effective = \block_workload\helper::get_course_target((int) $course->id);
        if ($params['targethours'] > 0) {
            $source = 'course';
        } else {
            $source = ($effective !== null) ? 'default' : 'none';
        }

        return [
            'success'   => true,
            'effective' => $effective ?? 0,
            'source'    => $source,
        ];
    }

    /**
     * Return the structure of the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'   => new external_value(PARAM_BOOL, 'Whether the save succeeded'),
            'effective' => new external_value(PARAM_FLOAT, 'Effective target hours after the change (0 = none)'),
            'source'    => new external_value(PARAM_ALPHA, 'Where the effective value comes from: course, default or none'),
        ]);
    }
}
