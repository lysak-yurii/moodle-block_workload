<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * External function: get workload entries for the current week.
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

class get_week_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'weeknumber' => new external_value(PARAM_INT, 'ISO week number'),
            'year'       => new external_value(PARAM_INT, 'ISO year'),
        ]);
    }

    public static function execute(int $weeknumber, int $year): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), compact('weeknumber', 'year'));

        $syscontext = context_system::instance();
        self::validate_context($syscontext);
        require_capability('block/workload:submit', $syscontext);

        $allcohorts    = \block_workload\helper::get_user_active_cohorts($USER->id);
        $activecohorts = array_filter($allcohorts, fn($c) => \block_workload\helper::is_workload_active($c->id));
        if (empty($activecohorts)) {
            return ['courses' => []];
        }

        $courses = \block_workload\helper::get_merged_cohort_courses(array_keys($activecohorts));
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

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'courseid' => new external_value(PARAM_INT,   'Course ID'),
                    'hours'    => new external_value(PARAM_FLOAT, 'Hours entered this week'),
                ])
            ),
        ]);
    }
}
