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
 * Quality Manager – central management of globally-hidden courses (enrollment mode).
 *
 * Courses hidden here are removed from every student's workload dashboard by
 * default. A per-student force-include override still wins for that student.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$action         = optional_param('action', '', PARAM_ALPHA);
$courseid       = optional_param('courseid', 0, PARAM_INT);
$catid          = optional_param('catid', 0, PARAM_INT);
$includesubcats = optional_param('includesubcats', 0, PARAM_BOOL);
$search         = optional_param('search', '', PARAM_TEXT);
$page           = optional_param('page', 0, PARAM_INT);
$perpage        = optional_param('perpage', 25, PARAM_INT);

$syscontext = context_system::instance();
require_login();
require_capability('block/workload:manage', $syscontext);

$coursemode = get_config('block_workload', 'coursemode') ?: 'enrollment';
if ($coursemode !== 'enrollment') {
    redirect(new moodle_url('/blocks/workload/manage.php'));
}

$baseurl = new moodle_url('/blocks/workload/manage_global.php');

$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_url($baseurl, array_filter(['catid' => $catid ?: null]));
$PAGE->navbar->add(
    get_string('managetitle', 'block_workload'),
    new moodle_url('/blocks/workload/manage.php')
);
$PAGE->navbar->add(
    get_string('enrollmentmodemanage', 'block_workload'),
    new moodle_url('/blocks/workload/manage_enrollment.php')
);
$PAGE->navbar->add(get_string('globalhiddencourses', 'block_workload'));
$PAGE->set_title(get_string('globalhiddencourses', 'block_workload'));
$PAGE->set_heading(get_string('globalhiddencourses', 'block_workload'));

// Remove a single course from the global hidden list.
if ($action === 'unhide' && $courseid && confirm_sesskey()) {
    $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);
    \block_workload\helper::remove_global_hidden_course($courseid);
    \block_workload\event\global_course_toggled::create([
        'objectid' => $courseid,
        'context'  => $syscontext,
        'other'    => ['hidden' => 0, 'coursename' => $course->fullname],
    ])->trigger();
    redirect(
        $baseurl,
        get_string('courseunhiddenglobally', 'block_workload'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Bulk hide selected courses globally (POST from the category adder).
$hidecourses = optional_param('hidecourses', 0, PARAM_BOOL);
if ($hidecourses && confirm_sesskey()) {
    $hidecourseids = optional_param_array('hidecourseids', [], PARAM_INT);
    $hiddencount = 0;
    foreach ($hidecourseids as $cid) {
        $cid = (int)$cid;
        if ($cid <= 0) {
            continue;
        }
        $course = $DB->get_record('course', ['id' => $cid], 'id, fullname');
        if ($course && \block_workload\helper::add_global_hidden_course($cid)) {
            \block_workload\event\global_course_toggled::create([
                'objectid' => $cid,
                'context'  => $syscontext,
                'other'    => ['hidden' => 1, 'coursename' => $course->fullname],
            ])->trigger();
            $hiddencount++;
        }
    }
    redirect(
        new moodle_url($baseurl, array_filter([
            'catid'          => $catid ?: null,
            'includesubcats' => $includesubcats ?: null,
            'search'         => ($search !== '') ? $search : null,
            'page'           => $page ?: null,
            'perpage'        => ($perpage != 25) ? $perpage : null,
        ])),
        get_string('courseshiddenglobally', 'block_workload', $hiddencount),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Build the view.

$hidden      = \block_workload\helper::get_global_hidden_courses();
$hiddenids   = array_keys($hidden);
$datedisabled = get_string('datedisabled', 'block_workload');

// Category selector options.
$rawcatopts = [0 => get_string('selectcategory', 'block_workload')]
            + \block_workload\helper::get_category_options();
$catopts = [];
foreach ($rawcatopts as $val => $lbl) {
    $catopts[] = ['value' => $val, 'label' => $lbl, 'selected' => ((int)$val === $catid)];
}

// Add-courses table (courses in the selected category).
$hascatresults  = false;
$nocoursesincat = false;
$addcourserows  = [];
$catresultinfo  = '';

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
        // The per-row "already hidden" badge reports this precisely; an aggregate
        // here would silently mean "on this page" once the list is paged.
        $catresultinfo = get_string('courseresultcount', 'block_workload', $total);

        foreach ($available as $c) {
            $already         = in_array((int)$c->id, $hiddenids, true);
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
                'alreadytitle' => get_string('alreadyhiddencount', 'block_workload', 1),
            ];
        }
    }
}

// Currently hidden courses table.
$hiddenrows = [];
foreach (array_values($hidden) as $course) {
    $hiddenrows[] = [
        'id'        => (int)$course->id,
        'name'      => format_string($course->fullname),
        'shortname' => $course->shortname,
        'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'startdate' => !empty($course->startdate)
            ? userdate($course->startdate, get_string('strftimedate', 'langconfig'))
            : $datedisabled,
        'enddate'   => !empty($course->enddate)
            ? userdate($course->enddate, get_string('strftimedate', 'langconfig'))
            : $datedisabled,
        'unhideurl' => (new moodle_url($baseurl, [
            'action' => 'unhide', 'courseid' => $course->id, 'sesskey' => sesskey(),
        ]))->out(false),
    ];
}

// Filter state carried by every paging/per-page link.
$filterparams = array_filter([
    'catid'          => $catid ?: null,
    'includesubcats' => $includesubcats ?: null,
    'search'         => ($search !== '') ? $search : null,
]);

$total      = $total ?? 0;
$pagingurl  = new moodle_url($baseurl, $filterparams + ['perpage' => $perpage]);
$showpaging = ($perpage > 0 && $total > $perpage);
$pagingbar  = $showpaging ? $OUTPUT->paging_bar($total, $page, $perpage, $pagingurl) : '';

// Per-page selector (mirrors manage_targets.php / manage_enrollment.php).
$perpageopts = \block_workload\helper::build_perpage_options($baseurl, $filterparams, $perpage, $total);

$templatecontext = [
    'backurl'   => (new moodle_url('/blocks/workload/manage_enrollment.php'))->out(false),
    'backlabel' => get_string('backstudentlist', 'block_workload'),
    'sesskey'   => sesskey(),
    'formaction' => $baseurl->out(false),
    'catformaction'  => $baseurl->out(false),
    'catopts'        => array_values($catopts),
    'selectedcatid'  => $catid,
    'includesubcats' => $includesubcats,
    'search'          => $search,
    'selectedperpage' => $perpage,
    'selectedpage'    => $page,
    'pagingbartop'    => $pagingbar,
    'pagingbarbottom' => $pagingbar,
    'perpagestr'      => get_string('perpage', 'block_workload'),
    'perpageopts'     => array_values($perpageopts),

    'hascatresults'  => $hascatresults,
    'nocoursesincat' => $nocoursesincat,
    'catresultinfo'  => $catresultinfo,
    'addcourserows'  => array_values($addcourserows),

    'hiddencount' => count($hidden),
    'hashidden'   => !empty($hidden),
    'nohidden'    => empty($hidden),
    'hiddenrows'  => array_values($hiddenrows),
    'unhidelabel' => get_string('unhidecourse', 'block_workload'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_workload/manage_global', $templatecontext);
$PAGE->requires->js_call_amd('block_workload/toggleall', 'init');
echo $OUTPUT->footer();
