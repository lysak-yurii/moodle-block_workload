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
 * Teacher – workload statistics for the students of one course.
 *
 * Enrollment mode only. Access is per course via the
 * block/workload:viewcoursestats capability; identities are pseudonymized
 * unless the anonymizeteacherstats setting is off or the viewer holds
 * block/workload:viewrealnames in this course.
 *
 * @package   block_workload
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid    = optional_param('id', 0, PARAM_INT);
$weekfrom    = optional_param('weekfrom', (int)date('W'), PARAM_INT);
$yearfrom    = optional_param('yearfrom', (int)date('o'), PARAM_INT);
$weekto      = optional_param('weekto', (int)date('W'), PARAM_INT);
$yearto      = optional_param('yearto', (int)date('o'), PARAM_INT);
$export      = optional_param('export', 0, PARAM_BOOL);
$exporttype  = optional_param('exporttype', 'quick', PARAM_ALPHA);
$alphabet    = explode(',', get_string('alphabet', 'langconfig'));
$firstletter = optional_param('firstletter', '', PARAM_RAW);
$lastletter  = optional_param('lastletter', '', PARAM_RAW);
$firstletter = in_array($firstletter, $alphabet, true) ? $firstletter : '';
$lastletter  = in_array($lastletter, $alphabet, true) ? $lastletter : '';
$perpage     = optional_param('perpage', 25, PARAM_INT);
$page        = optional_param('page', 0, PARAM_INT);

$syscontext = context_system::instance();

if ($courseid) {
    $course = get_course($courseid);
    require_login($course);
    $coursecontext = context_course::instance($course->id);
    require_capability('block/workload:viewcoursestats', $coursecontext);
} else {
    $course = null;
    require_login();
}

// This view exists only in enrollment mode; in cohort mode statistics are
// the Quality Manager's cohort overview.
$coursemode = get_config('block_workload', 'coursemode') ?: 'cohort';
if ($coursemode !== 'enrollment') {
    redirect(
        new moodle_url('/my'),
        get_string('coursestatsdisabled', 'block_workload'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

// Courses where the viewer may see statistics. $doanything = false so site
// admins get their explicitly-taught courses here, not every course on the
// site; they can still open any course directly by URL (require_capability
// above honours doanything).
$mycourses = get_user_capability_course(
    'block/workload:viewcoursestats',
    null,
    false,
    'fullname,shortname',
    'fullname ASC'
) ?: [];

if (!$course && count($mycourses) === 1) {
    redirect(new moodle_url('/blocks/workload/coursestats.php', ['id' => reset($mycourses)->id]));
}

$PAGE->set_url('/blocks/workload/coursestats.php', array_filter([
    'id'          => $courseid,
    'weekfrom'    => $weekfrom, 'yearfrom' => $yearfrom,
    'weekto'      => $weekto, 'yearto' => $yearto,
    'firstletter' => $firstletter, 'lastletter' => $lastletter,
    'perpage'     => ($perpage !== 25) ? $perpage : 0,
    'page'        => $page,
]));
if (!$course) {
    $PAGE->set_context($syscontext);
}
$PAGE->set_pagelayout('report');
$pagetitle = get_string('coursestatstitle', 'block_workload');
if ($course) {
    $pagetitle .= ': ' . format_string($course->fullname);
}
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

global $DB, $OUTPUT, $USER;

$wf = $weekfrom ?: null;
$yf = $yearfrom ?: null;
$wt = $weekto ?: null;
$yt = $yearto ?: null;

$anon       = false;
$suppressed = false;
$kpisdata   = null;
$staffids   = [];
if ($course) {
    $anon = \block_workload\helper::is_teacher_anonymized($coursecontext);
    if ($anon) {
        // Name-initial filters would leak identity information, even when
        // crafted directly in the URL.
        $firstletter = '';
        $lastletter  = '';

        // Course staff (viewcoursestats holders) are not pseudonymized:
        // anonymization protects students from staff, not staff from each
        // other, and identified rows let teachers clean up accidentally
        // recorded staff hours.
        $staffids = \block_workload\helper::get_course_staff_userids($coursecontext);
    }

    $kpisdata = \block_workload\helper::get_course_overview_kpis($course->id, $wf, $yf, $wt, $yt);
    // A teacher knows the roster: with only 1-2 students recording,
    // pseudonyms are trivially reversible, so per-student data (table, pie
    // chart, exports) is withheld and only aggregates remain. Staff (and the
    // viewer, who may hold the page capability via doanything) are excluded
    // from the count — their identified rows must not mask how few
    // pseudonymized students remain.
    $studentrecorders = (int)$kpisdata->usercount;
    if ($anon) {
        $excludeids = array_unique(array_merge($staffids, [(int)$USER->id]));
        $studentrecorders -= \block_workload\helper::count_course_users_with_entries(
            $course->id,
            $excludeids,
            $wf,
            $yf,
            $wt,
            $yt
        );
    }
    $suppressed = $anon
        && $studentrecorders > 0
        && $studentrecorders < \block_workload\helper::ANON_MIN_GROUP;
}

// Row identification and export ordering: the viewer's own rows come first
// (real name — it is their own data), then other course staff (real names),
// then students (pseudonymized when anonymized).
$groupof = function ($userid) use ($USER, &$staffids): int {
    if ((int)$userid === (int)$USER->id) {
        return 0;
    }
    return in_array((int)$userid, $staffids, true) ? 1 : 2;
};
$showreal = function ($userid) use ($anon, $groupof): bool {
    return !$anon || $groupof($userid) < 2;
};

// CSV export. The suppression guard must live here, server-side: the export
// URL can be crafted directly.

if ($course && $export && !$suppressed && has_capability('block/workload:export', $syscontext)) {
    if ($exporttype === 'detailed') {
        $rows = array_values(
            \block_workload\helper::get_course_detailed_export($course->id, $wf, $yf, $wt, $yt)
        );
        // Own rows first, then other staff, then students. Under
        // anonymization student rows are re-sorted by pseudonym token so row
        // order does not leak alphabetical rank (the SQL orders by
        // lastname); usort() is stable, so identified rows keep their order.
        usort($rows, function ($a, $b) use ($anon, $groupof) {
            $cmp = $groupof($a->userid) <=> $groupof($b->userid);
            if ($cmp !== 0 || !$anon || $groupof($a->userid) < 2) {
                return $cmp;
            }
            return strcmp(
                \block_workload\helper::pseudonym_token($a->userid)
                    . sprintf('%06d', $a->year * 100 + $a->weeknumber),
                \block_workload\helper::pseudonym_token($b->userid)
                    . sprintf('%06d', $b->year * 100 + $b->weeknumber)
            );
        });
        $filename = get_string('exportfilenamedetailed', 'block_workload')
            . '_' . clean_filename($course->shortname) . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            get_string('student', 'block_workload'), get_string('email'),
            get_string('department'), get_string('institution'),
            get_string('week', 'block_workload'),
            get_string('hours', 'block_workload'),
        ]);
        foreach ($rows as $r) {
            $real = $showreal($r->userid);
            fputcsv($out, [
                $real ? $r->firstname . ' ' . $r->lastname : \block_workload\helper::pseudonym($r->userid),
                $real ? $r->email : '',
                $real ? ($r->department ?? '') : '',
                $real ? ($r->institution ?? '') : '',
                sprintf('%04d-W%02d', (int)$r->year, (int)$r->weeknumber),
                number_format((float)$r->hours, 1),
            ]);
        }
        fclose($out);
    } else {
        $rows = array_values(
            \block_workload\helper::get_course_top_users($course->id, 0, $wf, $yf, $wt, $yt)
        );
        // Own row first, then other staff; student rows keep the totalhours
        // order (usort() is stable), which leaks no name rank.
        usort($rows, fn($a, $b) => $groupof($a->userid) <=> $groupof($b->userid));
        $filename = get_string('exportfilename', 'block_workload')
            . '_' . clean_filename($course->shortname) . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            get_string('student', 'block_workload'), get_string('email'),
            get_string('department'), get_string('institution'),
            get_string('totalhours', 'block_workload'),
            get_string('weeksactive', 'block_workload'),
            get_string('averagehours', 'block_workload'),
        ]);
        foreach ($rows as $r) {
            $real = $showreal($r->userid);
            $avg  = $r->weeksactive > 0 ? round((float)$r->totalhours / (int)$r->weeksactive, 2) : 0;
            fputcsv($out, [
                $real ? $r->firstname . ' ' . $r->lastname : \block_workload\helper::pseudonym($r->userid),
                $real ? $r->email : '',
                $real ? ($r->department ?? '') : '',
                $real ? ($r->institution ?? '') : '',
                number_format((float)$r->totalhours, 1),
                (int)$r->weeksactive, number_format($avg, 2),
            ]);
        }
        fclose($out);
    }
    exit;
}

// Fetch display data.

$weeklytotals = [];
$topusers     = [];
$tableusers   = [];
$totalcount   = 0;
$enrolledcount = 0;
if ($course) {
    $weeklytotals  = \block_workload\helper::get_course_weekly_totals($course->id, $wf, $yf, $wt, $yt);
    $enrolledcount = count_enrolled_users($coursecontext, '', 0, true);

    if (!$suppressed) {
        $topusers   = \block_workload\helper::get_course_top_users($course->id, 10, $wf, $yf, $wt, $yt);
        $totalcount = \block_workload\helper::get_course_top_users_count(
            $course->id,
            $wf,
            $yf,
            $wt,
            $yt,
            $firstletter,
            $lastletter
        );
        $perpageeff = ($perpage > 0) ? $perpage : 0;
        $offseteff  = ($perpage > 0) ? max(0, $page) * $perpage : 0;
        $tableusers = \block_workload\helper::get_course_top_users(
            $course->id,
            $perpageeff,
            $wf,
            $yf,
            $wt,
            $yt,
            $firstletter,
            $lastletter,
            $offseteff
        );
    }
}

// Build charts.

$chartweekavghtml   = '';
$chartweekusershtml = '';
$charttopusershtml  = '';

$brandcolor = !empty($PAGE->theme->settings->brandcolor) ? $PAGE->theme->settings->brandcolor : '#1177d1';

if (!empty($weeklytotals)) {
    $weeklabels = [];
    $avgvalues  = [];
    $uservalues = [];
    foreach ($weeklytotals as $row) {
        $weeklabels[] = sprintf('%04d-%02d', (int)$row->year, (int)$row->weeknumber);
        $uc           = max(1, (int)$row->usercount);
        $avgvalues[]  = round((float)$row->totalhours / $uc, 2);
        $uservalues[] = $uc;
    }
    $multiweek = count($weeklabels) > 12;

    $chartweekavg = $multiweek ? new \core\chart_line() : new \core\chart_bar();
    if ($multiweek) {
        $chartweekavg->set_smooth(true);
    }
    $chartweekavg->set_title(get_string('avghrsperstudent', 'block_workload'));
    $seriesavg = new \core\chart_series(
        get_string('avghrsperstudent', 'block_workload'),
        $avgvalues
    );
    $seriesavg->set_color($brandcolor);
    $chartweekavg->add_series($seriesavg);
    $yavg = new \core\chart_axis();
    $yavg->set_min(0);
    $chartweekavg->set_labels($weeklabels);
    $chartweekavg->set_yaxis($yavg, 0);
    $chartweekavghtml = $OUTPUT->render($chartweekavg);

    $chartweekusers = $multiweek ? new \core\chart_line() : new \core\chart_bar();
    if ($multiweek) {
        $chartweekusers->set_smooth(true);
    }
    $chartweekusers->set_title(get_string('activestudents', 'block_workload'));
    $seriesusers = new \core\chart_series(
        get_string('activestudents', 'block_workload'),
        $uservalues
    );
    $seriesusers->set_color('#e66000');
    $chartweekusers->add_series($seriesusers);
    $yusers = new \core\chart_axis();
    $yusers->set_min(0);
    $chartweekusers->set_labels($weeklabels);
    $chartweekusers->set_yaxis($yusers, 0);
    $chartweekusershtml = $OUTPUT->render($chartweekusers);
}

if (!empty($topusers)) {
    $truncate  = fn($s) => mb_strlen($s) > 35 ? mb_substr($s, 0, 33) . "\xe2\x80\xa6" : $s;
    $pielabels = [];
    $pievalues = [];
    foreach ($topusers as $r) {
        $pielabels[] = $showreal($r->userid)
            ? $truncate($r->firstname . ' ' . $r->lastname)
            : \block_workload\helper::pseudonym($r->userid);
        $pievalues[] = (float)$r->totalhours;
    }
    $charttopusers = new \core\chart_pie();
    $charttopusers->set_title(get_string('topstudents', 'block_workload', count($topusers)));
    $piepalette = ['#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f',
                   '#edc948', '#b07aa1', '#ff9da7', '#9c755f', '#bab0ac', '#4e79a7'];
    $seriestop = new \core\chart_series(
        get_string('totalhours', 'block_workload'),
        $pievalues
    );
    $seriestop->set_colors(array_slice($piepalette, 0, count($pievalues)));
    $charttopusers->add_series($seriestop);
    $charttopusers->set_labels($pielabels);
    $charttopusershtml = $OUTPUT->render($charttopusers);
}

// Build template context.

// Course selector options. The currently viewed course is appended if it is
// not in the list (e.g. a site admin opening a course they do not teach).
$courseopts = [];
$inlist     = false;
foreach ($mycourses as $c) {
    $selected = $course && (int)$c->id === (int)$course->id;
    $inlist   = $inlist || $selected;
    $courseopts[] = [
        'value'    => (int)$c->id,
        'label'    => format_string($c->fullname),
        'selected' => $selected,
    ];
}
if ($course && !$inlist) {
    array_unshift($courseopts, [
        'value'    => (int)$course->id,
        'label'    => format_string($course->fullname),
        'selected' => true,
    ]);
}

// KPI cards. The enrolled count is a participation-gap indicator: it counts
// all active enrolled users (teachers included), so it may slightly exceed
// the true student count.
$kpis = $kpisdata ? [
    ['value' => (string)$enrolledcount,
     'label' => get_string('enrolledstudents', 'block_workload')],
    ['value' => (string)(int)$kpisdata->usercount,
     'label' => get_string('studentsrecording', 'block_workload')],
    ['value' => (string)(int)$kpisdata->weekcount,
     'label' => get_string('weeksactive', 'block_workload')],
    ['value' => number_format((float)$kpisdata->totalhours, 1),
     'label' => get_string('totalhours', 'block_workload')],
] : [];

// Export URLs and labels.
$canexport  = $course && !$suppressed && has_capability('block/workload:export', $syscontext);
$exportbase = [
    'id'       => $courseid,
    'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
    'weekto'   => $weekto, 'yearto' => $yearto,
    'export'   => 1,
];
$exportquickurl  = (new moodle_url(
    '/blocks/workload/coursestats.php',
    array_merge($exportbase, ['exporttype' => 'quick'])
))->out(false);
$exportdetailurl = (new moodle_url(
    '/blocks/workload/coursestats.php',
    array_merge($exportbase, ['exporttype' => 'detailed'])
))->out(false);

// Base URL for letter-filter and per-page links.
$filterbase = new moodle_url('/blocks/workload/coursestats.php', [
    'id'       => $courseid,
    'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
    'weekto'   => $weekto, 'yearto' => $yearto,
    'submitted' => 1,
]);

// A-Z initial filter bars. Hidden entirely for anonymized viewers:
// filtering by name initial would leak identity information.
$alphabars = [];
foreach (
    ($anon || !$course) ? [] : [
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
                [$param => $l, $otherparam => $other, 'perpage' => $perpage, 'page' => 0]
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

// Per-page selector.
$perpageopts = [];
foreach (
    [25 => '25', 50 => '50', 100 => '100',
          0  => get_string('showall', 'block_workload', $totalcount)] as $opt => $lbl
) {
    $active = ($opt == $perpage);
    $perpageopts[] = [
        'label'  => $lbl,
        'active' => $active,
        'islink' => !$active,
        'url'    => !$active
            ? (new moodle_url($filterbase, [
                'perpage' => $opt, 'page' => 0,
                'firstletter' => $firstletter, 'lastletter' => $lastletter,
              ]))->out(false)
            : '',
    ];
}

// Student table rows. No drill-down: teachers see aggregates only.
$tablerows = [];
foreach ($tableusers as $r) {
    $real = $showreal($r->userid);
    $avg  = $r->weeksactive > 0
        ? round((float)$r->totalhours / (int)$r->weeksactive, 1)
        : 0;
    $tablerows[] = [
        'name'       => $real
            ? $r->firstname . ' ' . $r->lastname
            : \block_workload\helper::pseudonym($r->userid),
        'profileurl' => !$real ? '' : (new moodle_url(
            '/user/view.php',
            ['id' => $r->userid, 'course' => $course->id]
        ))->out(false),
        'email'      => $real ? $r->email : '',
        'totalhours' => number_format((float)$r->totalhours, 1),
        'weeksactive' => (int)$r->weeksactive,
        'avghours'   => number_format($avg, 1),
    ];
}

// Table headings. The email column is dropped for anonymized viewers.
$tableheads = array_merge(
    [get_string('student', 'block_workload')],
    $anon ? [] : [get_string('email')],
    [
        get_string('totalhours', 'block_workload'),
        get_string('weeksactive', 'block_workload'),
        get_string('averagehours', 'block_workload'),
    ]
);

// Paging bar.
$pagingbar = '';
if ($perpage > 0 && $totalcount > $perpage) {
    $pageurl   = new moodle_url($filterbase, [
        'firstletter' => $firstletter, 'lastletter' => $lastletter,
        'perpage'     => $perpage,
    ]);
    $pagingbar = $OUTPUT->paging_bar($totalcount, $page, $perpage, $pageurl);
}

$hasresults = !empty($tableusers) || !empty($weeklytotals);

$templatecontext = [
    'backurl'   => $course
        ? (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false)
        : '',
    'backlabel' => get_string('backtocourse', 'block_workload'),

    'hascourse'    => (bool)$course,
    'hascourseopts' => !empty($courseopts),
    'courseopts'   => array_values($courseopts),
    'nocoursesstr' => get_string('coursestatsnocourses', 'block_workload'),

    'filterform' => [
        'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
        'weekto'   => $weekto, 'yearto' => $yearto,
    ],

    'hasresults' => $hasresults,
    'canexport'  => $canexport && $hasresults,

    'suppressed'    => $suppressed,
    'suppressedstr' => get_string('anonsmallgroup', 'block_workload', \block_workload\helper::ANON_MIN_GROUP),

    'exportquickurl'   => $exportquickurl,
    'exportdetailurl'  => $exportdetailurl,
    'exporttitle'      => get_string('exportchoice', 'block_workload'),
    'exportlabelquick' => get_string('exportquick', 'block_workload'),
    'exportlabeldetail' => get_string('exportdetailed', 'block_workload'),
    'exportdescquick'  => get_string(
        $anon ? 'exportquickcourseanon_desc' : 'exportquick_desc',
        'block_workload'
    ),
    'exportdescdetail' => get_string(
        $anon ? 'exportdetailedcourseanon_desc' : 'exportdetailedcourse_desc',
        'block_workload'
    ),

    'kpis' => $kpis,

    'hasweeklycharts'   => !empty($chartweekavghtml) || !empty($chartweekusershtml),
    'chartweekavghtml'  => $chartweekavghtml,
    'chartweekusershtml' => $chartweekusershtml,

    'hastopusers'      => !empty($charttopusershtml),
    'charttopusershtml' => $charttopusershtml,

    'tableheading' => get_string('students', 'block_workload')
                    . ($totalcount > 0 ? ' (' . $totalcount . ')' : ''),
    'alphabars'    => array_values($alphabars),
    'perpagestr'   => get_string('perpage', 'block_workload'),
    'perpageopts'  => array_values($perpageopts),
    'tableheads'   => array_values($tableheads),
    'tablerows'    => array_values($tablerows),
    'tableempty'   => empty($tableusers),
    'emptystr'     => get_string('nouserfound', 'block_workload'),
    'pagingbar'    => $pagingbar,

    'showemail'    => !$anon,
];

// Output.

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_workload/coursestats', $templatecontext);
$PAGE->requires->js_call_amd('block_workload/statistics', 'init', [[
    'showSearch' => false,
]]);
echo $OUTPUT->footer();
