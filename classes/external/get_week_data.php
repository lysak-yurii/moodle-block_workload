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
 * External function: get workload entries for the current week.
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

/**
 * External API: retrieve a student's weekly hour entries.
 */
class get_week_data extends external_api {
    /**
     * Return the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'weeknumber' => new external_value(PARAM_INT, 'ISO week number'),
            'year'       => new external_value(PARAM_INT, 'ISO year'),
        ]);
    }

    /**
     * Return the student's entries for a given week.
     *
     * @param int $weeknumber
     * @param int $year
     * @return array
     */
    public static function execute(int $weeknumber, int $year): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), compact('weeknumber', 'year'));

        $syscontext = context_system::instance();
        self::validate_context($syscontext);
        require_capability('block/workload:submit', $syscontext);

        $coursemode = get_config('block_workload', 'coursemode') ?: 'enrollment';

        if ($coursemode === 'enrollment') {
            if (!\block_workload\helper::is_user_widget_active((int) $USER->id)) {
                return ['courses' => []];
            }
            $courses = \block_workload\helper::get_user_enrolled_courses((int) $USER->id);
        } else {
            $activecohorts = \block_workload\helper::filter_cohorts_active_now(
                \block_workload\helper::get_user_active_cohorts($USER->id)
            );
            if (empty($activecohorts)) {
                return ['courses' => []];
            }
            $courses = \block_workload\helper::get_merged_cohort_courses(array_keys($activecohorts));
        }

        if (empty($courses)) {
            return ['courses' => []];
        }

        $entries = \block_workload\helper::get_week_entries($USER->id, $params['weeknumber'], $params['year']);

        $result = [];
        foreach ($courses as $course) {
            $result[] = [
                'courseid' => (int) $course->id,
                'hours'    => (float) ($entries[$course->id] ?? 0),
            ];
        }
        return ['courses' => $result];
    }

    /**
     * Return the structure of the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'courseid' => new external_value(PARAM_INT, 'Course ID'),
                    'hours'    => new external_value(PARAM_FLOAT, 'Hours entered this week'),
                ])
            ),
        ]);
    }
}
