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
     * Show on the user dashboard, and on course pages when the in-course
     * widget feature is enabled (admin master switch).
     *
     * @return array
     */
    public function applicable_formats(): array {
        $formats = [
            'my'   => true,
            'site' => false,
        ];
        if (\block_workload\helper::course_widget_enabled()) {
            $formats['course-view'] = true;
        }
        return $formats;
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

        // In-course widget: a single-course logger + progress bar on course pages.
        if (strpos($this->page->pagetype, 'course-view') === 0 && $this->page->course->id != SITEID) {
            return $this->get_course_content();
        }

        $syscontext = context_system::instance();
        $coursemode = get_config('block_workload', 'coursemode') ?: 'enrollment';

        // Quality Manager footer links.
        $links = [];
        if (has_capability('block/workload:manage', $syscontext)) {
            $links[] = [
                'url'   => (new moodle_url('/blocks/workload/manage.php'))->out(false),
                'label' => get_string('managedashboard', 'block_workload'),
            ];
        }
        if (has_capability('block/workload:viewallstats', $syscontext)) {
            $links[] = [
                'url'   => (new moodle_url('/blocks/workload/statistics.php'))->out(false),
                'label' => get_string('statisticstitle', 'block_workload'),
            ];
        }

        // Teacher footer link (enrollment mode): statistics for the students
        // of their own courses. get_user_capability_course() is cheap here —
        // it derives the check from the already-loaded access data and
        // returns false without touching the course table when the user
        // holds the capability nowhere; otherwise a single LIMIT 1 query.
        if (
            $coursemode === 'enrollment'
                && get_user_capability_course('block/workload:viewcoursestats', null, false, '', '', 1)
        ) {
            $links[] = [
                'url'   => (new moodle_url('/blocks/workload/coursestats.php'))->out(false),
                'label' => get_string('coursestatstitle', 'block_workload'),
            ];
        }

        // Student stats link — shown based on viewownstats, independent of submit.
        if (has_capability('block/workload:viewownstats', $syscontext)) {
            array_unshift($links, [
                'url'   => (new moodle_url('/blocks/workload/mystats.php'))->out(false),
                'label' => get_string('viewmystats', 'block_workload'),
            ]);
        }

        if ($links) {
            foreach ($links as $i => $link) {
                $links[$i]['separator'] = ($i < count($links) - 1);
            }
            $this->content->footer = $OUTPUT->render_from_template(
                'block_workload/block_footer',
                ['links' => $links]
            );
        }

        // Student block content.
        if (!has_capability('block/workload:submit', $syscontext)) {
            return $this->content;
        }

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
            $activecohorts = \block_workload\helper::filter_cohorts_active_now($allcohorts);

            if (empty($allcohorts)) {
                return $this->content;
            }

            if (empty($activecohorts)) {
                $this->content->text = $OUTPUT->render_from_template('block_workload/block_inactive', []);
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

        $coursedata = [];
        $idx = 0;
        foreach ($courses as $course) {
            $hours          = (float) ($entries[$course->id] ?? 0);
            $coursedata[]   = [
                'courseid'        => (int) $course->id,
                'coursename'      => format_string($course->fullname),
                'courseurl'       => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'hours_display'   => self::format_hhmm($hours),
                'hours_raw'       => $hours,
                'not_entered'     => ($hours == 0),
                'maxhours'        => $maxhours,
                'hourstep'        => $hourstep,
                'initiallyhidden' => ($coursesperpage > 0 && $idx >= $coursesperpage),
            ];
            $idx++;
        }

        $haspagination = ($coursesperpage > 0 && count($coursedata) > $coursesperpage);

        // Editable-week bounds for the week navigation (year * 100 + week).
        $bounds  = \block_workload\helper::get_editable_week_bounds((int)$USER->id);
        $weekint = $year * 100 + $weeknumber;
        $hasnav  = ($bounds['min'] < $bounds['max']);

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
            'hasweeknav'         => $hasnav,
            'prevdisabled'       => ($weekint <= $bounds['min']),
            'nextdisabled'       => ($weekint >= $bounds['max']),
            'blocktitle_tooltip' => get_string($hasnav ? 'blocktitle_tooltip_nav' : 'blocktitle_tooltip', 'block_workload'),
        ];

        // Load AMD module for the +/- button interactivity, week navigation and pagination.
        $this->page->requires->js_call_amd('block_workload/workload', 'init', [[
            'weeknumber'     => $weeknumber,
            'year'           => $year,
            'coursesperpage' => $haspagination ? $coursesperpage : 0,
            'minweekint'     => (int) $bounds['min'],
            'maxweekint'     => (int) $bounds['max'],
            'weeklabeltpl'   => get_string('weeknumber', 'block_workload', '{w}'),
            'weektooltiptpl' => get_string('weeknumber_tooltip', 'block_workload', (object)['week' => '{w}', 'year' => '{y}']),
        ]]);

        $this->content->text = $OUTPUT->render_from_template('block_workload/block', $templatecontext);

        return $this->content;
    }

    /**
     * Format a decimal hour value as H:MM for display in an input.
     *
     * @param float $dec
     * @return string
     */
    private static function format_hhmm(float $dec): string {
        $h = (int) floor($dec);
        $m = (int) round(($dec - $h) * 60);
        if ($m >= 60) {
            $h++;
            $m = 0;
        }
        return sprintf('%d:%02d', $h, $m);
    }

    /**
     * Build the in-course widget content (course pages only).
     *
     * Self-hides (empty content) when the feature is disabled, the user cannot
     * submit hours, or the course is not an active workload course for them —
     * mirroring the dashboard block's self-hiding behaviour.
     *
     * @return stdClass
     */
    private function get_course_content(): stdClass {
        global $USER, $OUTPUT;

        if (!\block_workload\helper::course_widget_enabled()) {
            return $this->content;
        }

        $syscontext = context_system::instance();
        if (!has_capability('block/workload:submit', $syscontext)) {
            return $this->content;
        }

        $courseid = (int) $this->page->course->id;
        if (!\block_workload\helper::is_course_workload_active_for_user((int) $USER->id, $courseid)) {
            return $this->content;
        }

        // Current ISO week/year.
        $weeknumber = (int) date('W');
        $year       = (int) date('o');

        $entries  = \block_workload\helper::get_week_entries((int) $USER->id, $weeknumber, $year);
        $hours    = (float) ($entries[$courseid] ?? 0);
        $maxhours = (int) (get_config('block_workload', 'maxhours') ?: 40);
        $rawstep     = get_config('block_workload', 'hourstep');
        $hourstepmin = ($rawstep !== false) ? max(1, (int) $rawstep) : 60;
        $hourstep    = $hourstepmin / 60;

        // Cumulative progress against the course's target (null = no bar).
        $target  = \block_workload\helper::get_course_target($courseid);
        $logged  = \block_workload\helper::get_course_logged_total((int) $USER->id, $courseid);
        $percent = $target ? (int) round(min(100, $logged / $target * 100)) : 0;

        $bounds  = \block_workload\helper::get_editable_week_bounds((int) $USER->id);
        $weekint = $year * 100 + $weeknumber;
        $hasnav  = ($bounds['min'] < $bounds['max']);

        $progresstext = $target
            ? get_string('progress_text', 'block_workload', (object) [
                'logged'  => number_format($logged, 1),
                'target'  => number_format($target, 1),
                'percent' => $percent,
            ])
            : '';

        $templatecontext = [
            'courseid'           => $courseid,
            'hours_display'      => self::format_hhmm($hours),
            'hours_raw'          => $hours,
            'not_entered'        => ($hours == 0),
            'maxhours'           => $maxhours,
            'hourstep'           => $hourstep,
            'weeknumber'         => $weeknumber,
            'year'               => $year,
            'weeknumber_tooltip' => get_string(
                'weeknumber_tooltip',
                'block_workload',
                (object) ['week' => $weeknumber, 'year' => $year]
            ),
            'hasweeknav'         => $hasnav,
            'prevdisabled'       => ($weekint <= $bounds['min']),
            'nextdisabled'       => ($weekint >= $bounds['max']),
            'blocktitle_tooltip' => get_string($hasnav ? 'coursewidget_tooltip_nav' : 'coursewidget_tooltip', 'block_workload'),
            'hastarget'          => ($target !== null),
            'target_raw'         => $target,
            'percent'            => $percent,
            'progresstext'       => $progresstext,
        ];

        // Student stats footer link, as on the dashboard.
        if (has_capability('block/workload:viewownstats', $syscontext)) {
            $this->content->footer = $OUTPUT->render_from_template(
                'block_workload/block_footer',
                ['links' => [[
                    'url'       => (new moodle_url('/blocks/workload/mystats.php'))->out(false),
                    'label'     => get_string('viewmystats', 'block_workload'),
                    'separator' => false,
                ]]]
            );
        }

        $this->page->requires->js_call_amd('block_workload/workload', 'init', [[
            'weeknumber'      => $weeknumber,
            'year'            => $year,
            'coursesperpage'  => 0,
            'minweekint'      => (int) $bounds['min'],
            'maxweekint'      => (int) $bounds['max'],
            'weeklabeltpl'    => get_string('weeknumber', 'block_workload', '{w}'),
            'weektooltiptpl'  => get_string('weeknumber_tooltip', 'block_workload', (object) ['week' => '{w}', 'year' => '{y}']),
            'progresstexttpl' => get_string('progress_text', 'block_workload', (object) [
                'logged' => '{l}', 'target' => '{t}', 'percent' => '{p}',
            ]),
        ]]);

        $this->content->text = $OUTPUT->render_from_template('block_workload/block_course', $templatecontext);

        return $this->content;
    }
}
