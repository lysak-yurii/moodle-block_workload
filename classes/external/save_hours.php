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
 * External function: save workload hours.
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
use context_system;

/**
 * External API: save a student's workload hours for a course and week.
 */
class save_hours extends external_api {
    /**
     * Return the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cohortid'   => new external_value(PARAM_INT, 'Cohort ID (ignored; kept for client compatibility)', VALUE_DEFAULT, 0),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'weeknumber' => new external_value(PARAM_INT, 'ISO week number (1-53)'),
            'year'       => new external_value(PARAM_INT, 'ISO year'),
            'hours'      => new external_value(PARAM_FLOAT, 'Hours spent (0 – max configured)'),
        ]);
    }

    /**
     * Save the given number of hours for a course and week.
     *
     * @param int $cohortid
     * @param int $courseid
     * @param int $weeknumber
     * @param int $year
     * @param float $hours
     * @return array
     */
    public static function execute(int $cohortid, int $courseid, int $weeknumber, int $year, float $hours): array {
        global $USER, $DB, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), compact(
            'cohortid',
            'courseid',
            'weeknumber',
            'year',
            'hours'
        ));

        $syscontext = context_system::instance();
        self::validate_context($syscontext);
        require_capability('block/workload:submit', $syscontext);

        // Clamp hours to configured maximum.
        $max   = (float)(get_config('block_workload', 'maxhours') ?: 40);
        $hours = max(0, min($max, $params['hours']));

        $coursemode = get_config('block_workload', 'coursemode') ?: 'cohort';

        // Loaded in cohort mode below and reused by is_week_editable(); stays
        // null in enrollment mode, which never consults cohort windows.
        $allcohorts = null;

        if ($coursemode === 'enrollment') {
            // Enrollment mode: validate via Moodle enrollment + manager overrides.
            require_once($CFG->libdir . '/enrollib.php');
            $coursecontext = \context_course::instance($params['courseid'], IGNORE_MISSING);
            if (!$coursecontext) {
                throw new \moodle_exception('invalidcourse', 'block_workload');
            }
            $override = $DB->get_record('block_workload_user_courses', [
                'userid'   => $USER->id,
                'courseid' => $params['courseid'],
            ]);
            $forceexcluded = $override && (int)$override->active === 0;
            $forceincluded = $override && (int)$override->active === 1;
            $enrolled      = is_enrolled($coursecontext, $USER->id);

            if ($forceexcluded || (!$enrolled && !$forceincluded)) {
                throw new \moodle_exception('invalidcourse', 'block_workload');
            }
        } else {
            // Cohort mode: verify the user has at least one currently-active cohort.
            $allcohorts    = \block_workload\helper::get_user_active_cohorts($USER->id);
            $activecohorts = \block_workload\helper::filter_cohorts_active_now($allcohorts);
            if (empty($activecohorts)) {
                throw new \moodle_exception('workloadinactive', 'block_workload');
            }

            // Verify the course is assigned and active in at least one active cohort.
            $cohortids = array_keys($activecohorts);
            [$insql, $inparams] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED, 'coh');
            $inparams['courseid'] = $params['courseid'];
            if (
                !$DB->record_exists_sql(
                    "SELECT 1 FROM {block_workload_courses} WHERE courseid = :courseid AND active = 1 AND cohortid $insql",
                    $inparams
                )
            ) {
                throw new \moodle_exception('invalidcourse', 'block_workload');
            }
        }

        // Enforce the editable-week window (future weeks, rolling backfill floor, cohort start).
        if (!\block_workload\helper::is_week_editable($USER->id, $params['year'], $params['weeknumber'], $allcohorts)) {
            throw new \moodle_exception('weeknoteditable', 'block_workload');
        }

        // Save without tying to a specific cohort (student may be in multiple, or enrollment mode).
        \block_workload\helper::save_entry(
            $USER->id,
            null,
            $params['courseid'],
            $params['weeknumber'],
            $params['year'],
            $hours
        );

        return ['success' => true, 'hours' => $hours];
    }

    /**
     * Return the structure of the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the save succeeded'),
            'hours'   => new external_value(PARAM_FLOAT, 'The saved hours value (after clamping)'),
        ]);
    }
}
