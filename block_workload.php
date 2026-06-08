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

/**
 * Workload Assessment block main class.
 *
 * Students see a weekly hour-entry form for each course in their cohort.
 * Quality Managers see links to the management dashboard and statistics.
 *
 * @package   block_workload
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_workload extends block_base {
    /**
     * Initialise the block title.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_workload');
    }

    /**
     * Only show on the user dashboard.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'my'   => true,
            'site' => false,
        ];
    }

    /**
     * Return true if the block has a config form.
     *
     * @return bool
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Return false to prevent multiple instances per page.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Return the block content.
     *
     * @return stdClass
     */
    public function get_content(): stdClass {
        global $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->text   = '';
        $this->content->footer = '';

        $syscontext = context_system::instance();

        // Quality Manager footer links.
        if (has_capability('block/workload:manage', $syscontext)) {
            $links = [];
            $links[] = html_writer::link(
                new moodle_url('/blocks/workload/manage.php'),
                get_string('managedashboard', 'block_workload')
            );
            $links[] = html_writer::link(
                new moodle_url('/blocks/workload/statistics.php'),
                get_string('statisticstitle', 'block_workload')
            );
            $this->content->footer = implode(' &middot; ', $links);
        }

        // Student block content.
        if (!has_capability('block/workload:submit', $syscontext)) {
            return $this->content;
        }

        $coursemode     = get_config('block_workload', 'coursemode') ?: 'cohort';
        $courseorder    = get_config('block_workload', 'courseorder') ?: 'sortorder';
        $raw            = get_config('block_workload', 'coursesperpage');
        $coursesperpage = max(0, (int)($raw !== false ? $raw : 6));

        // Current ISO week/year.
        $weeknumber = (int) date('W');
        $year       = (int) date('o'); // ISO year: differs from calendar year in edge weeks.

        if ($coursemode === 'enrollment') {
            // Manager may have disabled the widget for this individual student —
            // hide the block entirely, mirroring cohort mode's "not in any cohort" behaviour.
            if (!\block_workload\helper::is_user_widget_active((int)$USER->id)) {
                return $this->content;
            }

            // Enrollment mode: show courses the student is enrolled in (+ manager overrides).
            $courses = \block_workload\helper::get_user_enrolled_courses(
                (int)$USER->id,
                ($courseorder === 'recentaccess') ? 'recentaccess' : 'sortorder'
            );

            if (empty($courses)) {
                return $this->content;
            }
        } else {
            // Cohort mode (default).
            $allcohorts    = \block_workload\helper::get_user_active_cohorts($USER->id);
            $activecohorts = array_filter($allcohorts, fn($c) => \block_workload\helper::is_workload_active($c->id));

            if (empty($allcohorts)) {
                return $this->content;
            }

            if (empty($activecohorts)) {
                $this->content->text = html_writer::div(
                    get_string('workloadinactive', 'block_workload'),
                    'text-muted small p-2'
                );
                return $this->content;
            }

            $cohortids = array_keys($activecohorts);
            $courses   = \block_workload\helper::get_merged_cohort_courses($cohortids, $courseorder, (int)$USER->id);
        }

        // No courses assigned: render nothing so the block is hidden entirely.
        if (empty($courses)) {
            return $this->content;
        }

        $entries      = \block_workload\helper::get_week_entries($USER->id, $weeknumber, $year);
        $maxhours     = (int)(get_config('block_workload', 'maxhours') ?: 40);
        // Hourstep: stored as whole minutes (1-60), default 60 (= 1 h).
        $rawstep     = get_config('block_workload', 'hourstep');
        $hourstepmin = ($rawstep !== false) ? max(1, (int) $rawstep) : 60;
        $hourstep    = $hourstepmin / 60;

        // Format a decimal hour value as H:MM for display in the input.
        $hhmm = static function (float $dec): string {
            $h = (int) floor($dec);
            $m = (int) round(($dec - $h) * 60);
            if ($m >= 60) {
                $h++;
                $m = 0;
            }
            return sprintf('%d:%02d', $h, $m);
        };

        $coursedata = [];
        $idx = 0;
        foreach ($courses as $course) {
            $hours          = (float) ($entries[$course->id] ?? 0);
            $coursedata[]   = [
                'courseid'        => (int) $course->id,
                'coursename'      => format_string($course->fullname),
                'courseurl'       => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'hours_display'   => $hhmm($hours),
                'hours_raw'       => $hours,
                'not_entered'     => ($hours == 0),
                'maxhours'        => $maxhours,
                'hourstep'        => $hourstep,
                'initiallyhidden' => ($coursesperpage > 0 && $idx >= $coursesperpage),
            ];
            $idx++;
        }

        $haspagination = ($coursesperpage > 0 && count($coursedata) > $coursesperpage);

        $templatecontext = [
            'courses'            => array_values($coursedata),
            'weeknumber'         => $weeknumber,
            'year'               => $year,
            'weeknumber_tooltip' => get_string(
                'weeknumber_tooltip',
                'block_workload',
                (object)['week' => $weeknumber, 'year' => $year]
            ),
            'mystatsurl'         => (new moodle_url('/blocks/workload/mystats.php'))->out(false),
            'hascourses'         => !empty($coursedata),
            'haspagination'      => $haspagination,
            'hourstep'           => $hourstep,
        ];

        // Load AMD module for the +/- button interactivity and pagination.
        $this->page->requires->js_call_amd('block_workload/workload', 'init', [[
            'weeknumber'     => $weeknumber,
            'year'           => $year,
            'coursesperpage' => $haspagination ? $coursesperpage : 0,
        ]]);

        $this->content->text = $OUTPUT->render_from_template('block_workload/block', $templatecontext);

        // Student stats link in footer.
        if (has_capability('block/workload:viewownstats', $syscontext)) {
            $statslink = html_writer::link(
                new moodle_url('/blocks/workload/mystats.php'),
                get_string('viewmystats', 'block_workload')
            );
            if ($this->content->footer) {
                $this->content->footer = $statslink . ' &middot; ' . $this->content->footer;
            } else {
                $this->content->footer = $statslink;
            }
        }

        return $this->content;
    }
}
