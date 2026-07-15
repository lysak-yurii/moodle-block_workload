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
 * Quality Manager – enrollment-mode student course management.
 *
 * List view  (no userid): paginated list of all enrolled students.
 * Detail view (userid=X): enrolled courses for one student + manager overrides.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$userid   = optional_param('userid', 0, PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$moveup   = optional_param('moveup', 0, PARAM_INT);
$movedown = optional_param('movedown', 0, PARAM_INT);

$alphabet    = explode(',', get_string('alphabet', 'langconfig'));
$firstletter = optional_param('firstletter', '', PARAM_RAW);
$lastletter  = optional_param('lastletter', '', PARAM_RAW);
$firstletter = in_array($firstletter, $alphabet, true) ? $firstletter : '';
$lastletter  = in_array($lastletter, $alphabet, true) ? $lastletter : '';
$perpage     = optional_param('perpage', 25, PARAM_INT);
$page        = optional_param('page', 0, PARAM_INT);

$catid          = optional_param('catid', 0, PARAM_INT);
$includesubcats = optional_param('includesubcats', 0, PARAM_BOOL);
// Course search on the per-student "Manage Courses" adder. Shares page/perpage
// with the student list above: the two views are separate URLs (the detail view
// always carries userid), so they never contend.
$search         = optional_param('search', '', PARAM_TEXT);
$confirm        = optional_param('confirm', 0, PARAM_BOOL);

$syscontext = context_system::instance();
require_login();
require_capability('block/workload:manage', $syscontext);

$coursemode = get_config('block_workload', 'coursemode') ?: 'enrollment';
if ($coursemode !== 'enrollment') {
    redirect(new moodle_url('/blocks/workload/manage.php'));
}

$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/blocks/workload/manage_enrollment.php', array_filter([
    'userid'      => $userid,
    'firstletter' => $firstletter,
    'lastletter'  => $lastletter,
    'perpage'     => ($perpage !== 25) ? $perpage : null,
    'page'        => $page ?: null,
]));
$PAGE->navbar->add(
    get_string('managetitle', 'block_workload'),
    new moodle_url('/blocks/workload/manage.php')
);
$PAGE->navbar->add(get_string('enrollmentmodemanage', 'block_workload'));

// Toggle per-student widget enable/disable (from the student list).

$togglewidget = optional_param('togglewidget', 0, PARAM_INT);
if ($togglewidget && confirm_sesskey()) {
    $student  = $DB->get_record('user', ['id' => $togglewidget, 'deleted' => 0], '*', MUST_EXIST);
    $newstate = \block_workload\helper::toggle_user_widget_active($togglewidget);
    \block_workload\event\widget_toggled::create([
        'objectid'      => $togglewidget,
        'context'       => $syscontext,
        'relateduserid' => $togglewidget,
        'other'         => ['active' => $newstate ? 1 : 0],
    ])->trigger();
    redirect(
        $PAGE->url,
        $newstate
            ? get_string('widgetenabled', 'block_workload', fullname($student))
            : get_string('widgetdisabled', 'block_workload', fullname($student)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// State-changing single-course actions.

if ($action && $userid && $courseid) {
    global $OUTPUT, $DB;

    $detailurl = new moodle_url('/blocks/workload/manage_enrollment.php', ['userid' => $userid]);
    $baseurl   = new moodle_url('/blocks/workload/manage_enrollment.php');
    $course    = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);

    switch ($action) {
        case 'exclude':
            if ($confirm && confirm_sesskey()) {
                \block_workload\helper::save_user_course_override($userid, $courseid, 0);
                redirect(
                    $detailurl,
                    get_string('courseexcluded', 'block_workload'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
            $PAGE->set_title(get_string('excludecourse', 'block_workload'));
            $PAGE->set_heading(get_string('excludecourse', 'block_workload'));
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('confirmexclude', 'block_workload', format_string($course->fullname)),
                new moodle_url($baseurl, [
                    'userid' => $userid, 'courseid' => $courseid,
                    'action' => 'exclude', 'confirm' => 1, 'sesskey' => sesskey(),
                ]),
                $detailurl
            );
            echo $OUTPUT->footer();
            exit;

        case 'removecourse':
            if ($confirm && confirm_sesskey()) {
                \block_workload\helper::remove_user_course_override($userid, $courseid);
                redirect(
                    $detailurl,
                    get_string('courseremoved', 'block_workload'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
            $PAGE->set_title(get_string('removecourse', 'block_workload'));
            $PAGE->set_heading(get_string('removecourse', 'block_workload'));
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('confirmremoveadded', 'block_workload', format_string($course->fullname)),
                new moodle_url($baseurl, [
                    'userid' => $userid, 'courseid' => $courseid,
                    'action' => 'removecourse', 'confirm' => 1, 'sesskey' => sesskey(),
                ]),
                $detailurl
            );
            echo $OUTPUT->footer();
            exit;

        case 'restore':
            require_sesskey();
            \block_workload\helper::remove_user_course_override($userid, $courseid);
            redirect(
                $detailurl,
                get_string('courserestored', 'block_workload'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            // No break needed: redirect exits.
        case 'addcourse':
            require_sesskey();
            \block_workload\helper::save_user_course_override($userid, $courseid, 1);
            redirect(
                new moodle_url($detailurl, ['catid' => $catid]),
                get_string('courseadded', 'block_workload'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    }
}

// Bulk add courses (POST).

$bulkaddcourses = optional_param('bulkaddcourses', 0, PARAM_BOOL);
if ($bulkaddcourses && $userid && confirm_sesskey()) {
    $addcourseids = optional_param_array('addcourseids', [], PARAM_INT);
    $added = 0;
    foreach ($addcourseids as $cid) {
        $cid = (int)$cid;
        if ($cid > 0) {
            \block_workload\helper::save_user_course_override($userid, $cid, 1);
            $added++;
        }
    }
    redirect(
        new moodle_url('/blocks/workload/manage_enrollment.php', array_filter([
            'userid'         => $userid,
            'catid'          => $catid ?: null,
            'includesubcats' => $includesubcats ?: null,
            'search'         => ($search !== '') ? $search : null,
            'page'           => $page ?: null,
            'perpage'        => ($perpage != 25) ? $perpage : null,
        ])),
        get_string('coursesadded', 'block_workload', $added),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Unified bulk action handler.

$bulkaction = optional_param('bulkaction', '', PARAM_ALPHA);
$courseids  = optional_param_array('courseids', [], PARAM_INT);

if ($bulkaction && $userid && !empty($courseids)) {
    $ids        = array_filter(array_map('intval', $courseids));
    $allcourses = \block_workload\helper::get_user_courses_for_management($userid);
    $detailurl  = new moodle_url('/blocks/workload/manage_enrollment.php', ['userid' => $userid]);

    if ($bulkaction === 'restore' && confirm_sesskey()) {
        $affected = 0;
        foreach ($ids as $cid) {
            $c = $allcourses[$cid] ?? null;
            if ($c && $c->enrolled && $c->override_active !== null && (int)$c->override_active === 0) {
                \block_workload\helper::remove_user_course_override($userid, $cid);
                $affected++;
            }
        }
        redirect(
            $detailurl,
            get_string('coursesrestored', 'block_workload', $affected),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($bulkaction === 'exclude') {
        $filtered = array_values(array_filter($ids, function ($cid) use ($allcourses) {
            $c = $allcourses[$cid] ?? null;
            return $c && $c->enrolled
                && ($c->override_active === null || (int)$c->override_active === 1);
        }));
        if (!empty($filtered)) {
            redirect(new moodle_url('/blocks/workload/manage_enrollment.php', [
                'userid'         => $userid,
                'confirmbulkids' => implode(',', $filtered),
                'confirmbulkact' => 'exclude',
            ]));
        }
        redirect($detailurl);
    }

    if ($bulkaction === 'remove') {
        $filtered = array_values(array_filter($ids, function ($cid) use ($allcourses) {
            $c = $allcourses[$cid] ?? null;
            return $c && !$c->enrolled;
        }));
        if (!empty($filtered)) {
            redirect(new moodle_url('/blocks/workload/manage_enrollment.php', [
                'userid'         => $userid,
                'confirmbulkids' => implode(',', $filtered),
                'confirmbulkact' => 'remove',
            ]));
        }
        redirect($detailurl);
    }
}

// Bulk confirm + execute.

$confirmbulkids = optional_param('confirmbulkids', '', PARAM_SEQUENCE);
$confirmbulkact = optional_param('confirmbulkact', '', PARAM_ALPHA);
$dobulk         = optional_param('dobulk', 0, PARAM_BOOL);

if ($confirmbulkids !== '' && $confirmbulkact) {
    $ids       = array_filter(array_map('intval', explode(',', $confirmbulkids)));
    $detailurl = new moodle_url('/blocks/workload/manage_enrollment.php', ['userid' => $userid]);

    if ($dobulk && confirm_sesskey()) {
        if ($confirmbulkact === 'exclude') {
            foreach ($ids as $cid) {
                \block_workload\helper::save_user_course_override($userid, $cid, 0);
            }
            redirect(
                $detailurl,
                get_string('coursesexcluded', 'block_workload', count($ids)),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        if ($confirmbulkact === 'remove') {
            foreach ($ids as $cid) {
                \block_workload\helper::remove_user_course_override($userid, $cid);
            }
            redirect(
                $detailurl,
                get_string('coursesremoved', 'block_workload', count($ids)),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }

    $confirmstr = ($confirmbulkact === 'exclude')
        ? get_string('confirmbulkexclude', 'block_workload', count($ids))
        : get_string('confirmbulkremoveadded', 'block_workload', count($ids));

    $PAGE->set_title(get_string('enrollmenttitle_student', 'block_workload', ''));
    $PAGE->set_heading(get_string('enrollmenttitle_student', 'block_workload', ''));
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        $confirmstr,
        new moodle_url('/blocks/workload/manage_enrollment.php', [
            'userid'         => $userid,
            'confirmbulkids' => $confirmbulkids,
            'confirmbulkact' => $confirmbulkact,
            'dobulk'         => 1,
            'sesskey'        => sesskey(),
        ]),
        $detailurl
    );
    echo $OUTPUT->footer();
    exit;
}

// Course reorder.

if ($userid && ($moveup || $movedown) && confirm_sesskey()) {
    $moveid = $moveup ?: $movedown;
    \block_workload\helper::swap_user_course_sortorder($userid, $moveid, (bool)$moveup);
    redirect(new moodle_url('/blocks/workload/manage_enrollment.php', ['userid' => $userid]));
}

// Route to view.

if ($userid) {
    block_workload_render_student_detail($userid, $catid, (bool)$includesubcats, $search, $page, $perpage);
    exit;
}
block_workload_render_student_list($firstletter, $lastletter, $perpage, $alphabet);

// List view.

/**
 * Render the enrollment mode student list page.
 *
 * @param string $firstletter
 * @param string $lastletter
 * @param int    $perpage
 * @param array  $alphabet
 */
function block_workload_render_student_list(
    string $firstletter,
    string $lastletter,
    int $perpage,
    array $alphabet
): void {
    global $OUTPUT, $PAGE;

    $PAGE->set_title(get_string('enrollmentmodemanage', 'block_workload'));
    $PAGE->set_heading(get_string('enrollmentmodemanage', 'block_workload'));

    $total      = \block_workload\helper::get_enrollment_mode_students_count($firstletter, $lastletter);
    $filterbase = new moodle_url('/blocks/workload/manage_enrollment.php');

    // A-Z initial filter bars.
    $alphabars = [];
    foreach (
        [
        ['firstinitial', get_string('firstname'), 'firstletter', $firstletter, $lastletter],
        ['lastinitial', get_string('lastname'), 'lastletter', $lastletter, $firstletter],
        ] as [$bartype, $barlabel, $param, $current, $other]
    ) {
        $otherparam = ($param === 'firstletter') ? 'lastletter' : 'firstletter';
        $allurl = (new moodle_url(
            $filterbase,
            [$param => '', $otherparam => $other, 'perpage' => $perpage, 'page' => 0]
        ))->out(false);
        $letters = [];
        foreach ($alphabet as $l) {
            $letters[] = [
                'barid'  => $bartype,
                'letter' => $l,
                'url'    => (new moodle_url(
                    $filterbase,
                    [$param => $l, $otherparam => $other,
                    'perpage' => $perpage,
                    'page' => 0]
                ))->out(false),
                'active' => ($current === $l),
            ];
        }
        $alphabars[] = [
            'bartype'   => $bartype,
            'barlabel'  => $barlabel,
            'allstr'    => get_string('all'),
            'allurl'    => $allurl,
            'allactive' => ($current === ''),
            'letters'   => $letters,
        ];
    }

    // Per-page selector. The alphabet filter is the state to preserve here.
    $perpageopts = \block_workload\helper::build_perpage_options(
        $filterbase,
        ['firstletter' => $firstletter, 'lastletter' => $lastletter],
        $perpage,
        $total
    );

    // Student table via Moodle table class (captures output).
    $filterset = new \block_workload\table\enrollment_students_filterset();
    $filterset->set_join_type(\core_table\local\filter\filterset::JOINTYPE_ALL);
    if ($firstletter !== '') {
        $filterset->add_filter_from_params(
            'firstletter',
            \core_table\local\filter\filter::JOINTYPE_ANY,
            [$firstletter]
        );
    }
    if ($lastletter !== '') {
        $filterset->add_filter_from_params(
            'lastletter',
            \core_table\local\filter\filter::JOINTYPE_ANY,
            [$lastletter]
        );
    }
    $enrollmenttable = new \block_workload\table\enrollment_students('block-workload-enrollment-students');
    $enrollmenttable->set_filterset($filterset);
    ob_start();
    $enrollmenttable->out($perpage, false);
    $tablehtml = ob_get_clean();

    $templatecontext = [
        'statsurl'    => (new moodle_url('/blocks/workload/statistics.php'))->out(false),
        'globalurl'   => (new moodle_url('/blocks/workload/manage_global.php'))->out(false),
        'targetsurl'  => (new moodle_url('/blocks/workload/manage_targets.php'))->out(false),
        'alphabars'   => array_values($alphabars),
        'perpagestr'  => get_string('perpage', 'block_workload'),
        'perpageopts' => array_values($perpageopts),
        'tablehtml'   => $tablehtml,
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('block_workload/enrollment_list', $templatecontext);
    $PAGE->requires->js_call_amd('block_workload/enrollment', 'init', [[
        'noResultsStr' => get_string('nouserfound', 'block_workload'),
    ]]);
    echo $OUTPUT->footer();
}

// Detail view.

/**
 * Render the enrollment mode student detail page.
 *
 * @param int $userid
 * @param int  $catid
 * @param bool $includesubcats
 * @param string $search
 * @param int $page
 * @param int $perpage
 */
function block_workload_render_student_detail(
    int $userid,
    int $catid,
    bool $includesubcats = false,
    string $search = '',
    int $page = 0,
    int $perpage = 25
): void {
    global $OUTPUT, $PAGE, $DB;

    $user     = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
    $fullname = fullname($user);

    $PAGE->set_title(get_string('coursestitle', 'block_workload', $fullname));
    $PAGE->set_heading(get_string('coursestitle', 'block_workload', $fullname));
    $PAGE->set_url('/blocks/workload/manage_enrollment.php', ['userid' => $userid]);

    $courses         = \block_workload\helper::get_user_courses_for_management($userid);
    $baseurl         = new moodle_url('/blocks/workload/manage_enrollment.php');
    $showordercolumn = (get_config('block_workload', 'courseorder') ?: 'sortorder') === 'sortorder';
    $datedisabled    = get_string('datedisabled', 'block_workload');

    // Category selector options.

    $rawcatopts = [0 => get_string('selectcategory', 'block_workload')]
                + \block_workload\helper::get_category_options();
    $catopts = [];
    foreach ($rawcatopts as $val => $lbl) {
        $catopts[] = ['value' => $val, 'label' => $lbl, 'selected' => ((int)$val === $catid)];
    }

    // Add-courses table data.

    $hascatresults  = false;
    $nocoursesincat = false;
    $addcourserows  = [];
    $catresultinfo  = '';

    $total = 0;
    if ($catid > 0 || $search !== '') {
        $offset    = ($perpage > 0) ? $page * $perpage : 0;
        $total     = \block_workload\helper::get_courses_in_category_count($catid ?: null, $includesubcats, $search);
        $available = \block_workload\helper::get_courses_in_category(
            $catid ?: null,
            $includesubcats,
            $search,
            $perpage,
            $offset
        );
        if (empty($available)) {
            $nocoursesincat = true;
        } else {
            $hascatresults = true;
            $shownids      = array_keys(array_filter($courses, function ($c) {
                return $c->enrolled
                    ? ($c->override_active === null || (int)$c->override_active === 1)
                    : (int)$c->override_active === 1;
            }));
            // The per-row "already assigned" badge reports this precisely; an aggregate
            // here would silently mean "on this page" once the list is paged.
            $catresultinfo = get_string('courseresultcount', 'block_workload', $total);

            foreach ($available as $c) {
                $already         = in_array((int)$c->id, $shownids);
                $addcourserows[] = [
                    'id'         => (int)$c->id,
                    'name'       => format_string($c->fullname),
                    'shortname'  => $c->shortname,
                    'courseurl'  => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
                    'startdate'  => !empty($c->startdate)
                        ? userdate($c->startdate, get_string('strftimedate', 'langconfig'))
                        : $datedisabled,
                    'enddate'    => !empty($c->enddate)
                        ? userdate($c->enddate, get_string('strftimedate', 'langconfig'))
                        : $datedisabled,
                    'already'      => $already,
                    'alreadytitle' => get_string('alreadyassignedcount', 'block_workload', 1),
                ];
            }
        }
    }

    // Assigned courses table data.

    $courserows   = [];
    $allcoursesl  = array_values($courses);
    $coursecount  = count($allcoursesl);
    $enforcedates = \block_workload\helper::enrollment_dates_enforced();

    foreach ($allcoursesl as $idx => $course) {
        $isadded  = !$course->enrolled;
        $excluded = $course->enrolled
            && $course->override_active !== null
            && (int)$course->override_active === 0;
        $ghidden  = !empty($course->globally_hidden);
        // Enrolled, hidden globally, and force-shown for this student (override=1).
        $forceshown = $course->enrolled && $ghidden
            && $course->override_active !== null && (int)$course->override_active === 1;
        // Enrolled, hidden globally, no per-student override: not on the dashboard.
        $hiddenonly = $course->enrolled && $ghidden && $course->override_active === null;

        // Status badge.
        if ($isadded) {
            // Manager-added (not enrolled). If it is also hidden globally, flag
            // that it is shown for this student as an individual exception.
            $statuslabel      = $ghidden
                ? get_string('statushiddengloballyshown', 'block_workload')
                : get_string('statusadded', 'block_workload');
            $statusbadgeclass = 'bg-primary text-white';
        } else if ($excluded) {
            $statuslabel      = get_string('statusexcluded', 'block_workload');
            $statusbadgeclass = 'bg-secondary text-white';
        } else if ($hiddenonly) {
            $statuslabel      = get_string('statushiddenglobally', 'block_workload');
            $statusbadgeclass = 'bg-secondary text-white';
        } else if ($forceshown) {
            $statuslabel      = get_string('statushiddengloballyshown', 'block_workload');
            $statusbadgeclass = 'bg-success text-white';
        } else {
            $statuslabel      = get_string('statusincluded', 'block_workload');
            $statusbadgeclass = 'bg-success text-white';
        }

        // Date window: for courses that would otherwise be shown, flag when the
        // dashboard's active-only filter hides them (ended / not yet started).
        $datestatus = ($enforcedates && !$excluded && !$hiddenonly)
            ? \block_workload\helper::course_date_status((int)$course->startdate, (int)$course->enddate)
            : 'active';
        $dateended      = $datestatus === 'ended';
        $datenotstarted = $datestatus === 'notstarted';

        // Action link.
        if ($isadded) {
            $actionurl = new moodle_url($baseurl, [
                'userid' => $userid, 'courseid' => $course->id, 'action' => 'removecourse',
            ]);
            $actionlabel    = get_string('removecourse', 'block_workload');
            $actionbtnclass = 'btn-outline-secondary';
        } else if ($excluded) {
            $actionurl = new moodle_url($baseurl, [
                'userid' => $userid, 'courseid' => $course->id,
                'action' => 'restore', 'sesskey' => sesskey(),
            ]);
            $actionlabel    = get_string('restorecourse', 'block_workload');
            $actionbtnclass = 'btn-outline-success';
        } else if ($hiddenonly) {
            // Force-include this globally-hidden course for this one student.
            $actionurl = new moodle_url($baseurl, [
                'userid' => $userid, 'courseid' => $course->id,
                'action' => 'addcourse', 'sesskey' => sesskey(),
            ]);
            $actionlabel    = get_string('showforstudent', 'block_workload');
            $actionbtnclass = 'btn-outline-success';
        } else if ($forceshown) {
            // Drop the force-include so the course follows the global hide again.
            $actionurl = new moodle_url($baseurl, [
                'userid' => $userid, 'courseid' => $course->id,
                'action' => 'restore', 'sesskey' => sesskey(),
            ]);
            $actionlabel    = get_string('followglobalhide', 'block_workload');
            $actionbtnclass = 'btn-outline-secondary';
        } else {
            $actionurl = new moodle_url($baseurl, [
                'userid' => $userid, 'courseid' => $course->id, 'action' => 'exclude',
            ]);
            $actionlabel    = get_string('excludecourse', 'block_workload');
            $actionbtnclass = 'btn-outline-secondary';
        }

        $courserows[] = [
            'id'              => (int)$course->id,
            'name'            => format_string($course->fullname),
            'shortname'       => $course->shortname,
            'courseurl'       => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'startdate'       => !empty($course->startdate)
                ? userdate($course->startdate, get_string('strftimedate', 'langconfig'))
                : $datedisabled,
            'enddate'         => !empty($course->enddate)
                ? userdate($course->enddate, get_string('strftimedate', 'langconfig'))
                : $datedisabled,
            'statuslabel'      => $statuslabel,
            'statusbadgeclass' => $statusbadgeclass,
            'datehidden'       => $dateended || $datenotstarted,
            'datewarnlabel'    => $dateended
                ? get_string('statusdateended', 'block_workload')
                : ($datenotstarted ? get_string('statusdatenotstarted', 'block_workload') : ''),
            'datewarntitle'    => get_string('datehiddentooltip', 'block_workload'),
            'enddatealert'     => $dateended,
            'startdatealert'   => $datenotstarted,
            'actionurl'        => $actionurl->out(false),
            'actionlabel'      => $actionlabel,
            'actionbtnclass'   => $actionbtnclass,
            'showup'          => $showordercolumn && $idx > 0,
            'showdown'        => $showordercolumn && $idx < $coursecount - 1,
            'moveupurl'       => (new moodle_url('/blocks/workload/manage_enrollment.php', [
                'userid' => $userid, 'moveup' => $course->id, 'sesskey' => sesskey(),
            ]))->out(false),
            'movedownurl'     => (new moodle_url('/blocks/workload/manage_enrollment.php', [
                'userid' => $userid, 'movedown' => $course->id, 'sesskey' => sesskey(),
            ]))->out(false),
            'moveuplabel'     => get_string('moveup', 'moodle'),
            'movedownlabel'   => get_string('movedown', 'moodle'),
            'showordercolumn' => $showordercolumn,
            'excluded'        => $excluded,
        ];
    }

    // Filter state carried by every paging/per-page link on the add-courses table.
    $detailbase   = new moodle_url('/blocks/workload/manage_enrollment.php');
    $filterparams = array_filter([
        'userid'         => $userid,
        'catid'          => $catid ?: null,
        'includesubcats' => $includesubcats ?: null,
        'search'         => ($search !== '') ? $search : null,
    ]);

    $pagingurl  = new moodle_url($detailbase, $filterparams + ['perpage' => $perpage]);
    $showpaging = ($perpage > 0 && $total > $perpage);
    $pagingbar  = $showpaging ? $OUTPUT->paging_bar($total, $page, $perpage, $pagingurl) : '';

    $perpageopts = \block_workload\helper::build_perpage_options($detailbase, $filterparams, $perpage, $total);

    $templatecontext = [
        'backurl'    => (new moodle_url('/blocks/workload/manage_enrollment.php'))->out(false),
        'backlabel'  => get_string('backstudentlist', 'block_workload'),
        'statsurl'   => (new moodle_url('/blocks/workload/statistics.php'))->out(false),
        'userid'     => $userid,
        'sesskey'    => sesskey(),
        'search'          => $search,
        'selectedpage'    => $page,
        'selectedperpage' => $perpage,
        'pagingbartop'    => $pagingbar,
        'pagingbarbottom' => $pagingbar,
        'perpagestr'      => get_string('perpage', 'block_workload'),
        'perpageopts'     => array_values($perpageopts),
        'catformaction' => (new moodle_url('/blocks/workload/manage_enrollment.php'))->out(false),
        'catopts'    => array_values($catopts),
        'selectedcatid'  => $catid,
        'includesubcats' => $includesubcats,

        'hascatresults'  => $hascatresults,
        'nocoursesincat' => $nocoursesincat,
        'catresultinfo'  => $catresultinfo,
        'addcourserows'  => array_values($addcourserows),

        'assignedcount'   => count($courses),
        'hascourses'      => !empty($courses),
        'nocourses'       => empty($courses),
        'showordercolumn' => $showordercolumn,
        'courserows'      => array_values($courserows),

        'excludeselected'  => get_string('excludeselected', 'block_workload'),
        'restoreselected'  => get_string('restoreselected', 'block_workload'),
        'bulkremovecourses' => get_string('bulkremovecourses', 'block_workload'),
    ];

    echo $OUTPUT->header();

    if (!\block_workload\helper::is_user_widget_active($userid)) {
        echo $OUTPUT->notification(
            get_string('widgetdisablednotice', 'block_workload', $fullname),
            \core\output\notification::NOTIFY_WARNING
        );
    }

    $PAGE->requires->js_call_amd('block_workload/toggleall', 'init');
    echo $OUTPUT->render_from_template('block_workload/enrollment_detail', $templatecontext);
    $PAGE->requires->js_call_amd('block_workload/enrollment', 'init', [[
        'noResultsStr' => get_string('nouserfound', 'block_workload'),
    ]]);
    echo $OUTPUT->footer();
}
