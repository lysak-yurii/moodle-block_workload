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
 * Quality Manager – workload statistics overview.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$cohortid    = optional_param('cohortid', 0, PARAM_INT);
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
require_login();
require_capability('block/workload:viewallstats', $syscontext);

$anon = \block_workload\helper::is_anonymized();
if ($anon) {
    // Name-initial filters would leak identity information, even when
    // crafted directly in the URL.
    $firstletter = '';
    $lastletter  = '';
}

$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('statisticstitle', 'block_workload'));
$PAGE->set_heading(get_string('statisticstitle', 'block_workload'));
$PAGE->set_url('/blocks/workload/statistics.php', array_filter([
    'cohortid'    => $cohortid,
    'weekfrom'    => $weekfrom, 'yearfrom'    => $yearfrom,
    'weekto'      => $weekto, 'yearto'      => $yearto,
    'firstletter' => $firstletter, 'lastletter' => $lastletter,
    'perpage'     => ($perpage !== 25) ? $perpage : 0,
    'page'        => $page,
]));

global $DB, $OUTPUT;

$coursemode = get_config('block_workload', 'coursemode') ?: 'enrollment';
if ($coursemode === 'enrollment') {
    $cohortid = 0;
}

// CSV export.

$wf = $weekfrom ?: null;
$yf = $yearfrom ?: null;
$wt = $weekto ?: null;
$yt = $yearto ?: null;

if ($export && has_capability('block/workload:export', $syscontext)) {
    if ($exporttype === 'detailed') {
        $rows     = \block_workload\helper::get_cohort_detailed_export($cohortid, $wf, $yf, $wt, $yt);
        if ($anon) {
            // The SQL orders by lastname; re-sort so row order does not leak
            // alphabetical rank.
            $rows = array_values($rows);
            usort($rows, fn($a, $b) => strcmp(
                \block_workload\helper::pseudonym_token($a->userid) . $a->coursename,
                \block_workload\helper::pseudonym_token($b->userid) . $b->coursename
            ));
        }
        $filename = get_string('exportfilenamedetailed', 'block_workload') . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            get_string('student', 'block_workload'), get_string('email'),
            get_string('department'), get_string('institution'),
            get_string('course', 'block_workload'), get_string('role', 'block_workload'),
            get_string('coursehours', 'block_workload'),
        ]);
        foreach ($rows as $r) {
            fputcsv($out, [
                $anon ? \block_workload\helper::pseudonym($r->userid) : $r->firstname . ' ' . $r->lastname,
                $anon ? '' : $r->email,
                $anon ? '' : ($r->department ?? ''),
                $anon ? '' : ($r->institution ?? ''),
                $r->coursename, $r->roles,
                number_format((float)$r->coursehours, 1),
            ]);
        }
        fclose($out);
    } else {
        $rows     = \block_workload\helper::get_cohort_top_users($cohortid, 0, $wf, $yf, $wt, $yt);
        $filename = get_string('exportfilename', 'block_workload') . '_' . date('Ymd_His') . '.csv';
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
            $avg = $r->weeksactive > 0 ? round((float)$r->totalhours / (int)$r->weeksactive, 2) : 0;
            fputcsv($out, [
                $anon ? \block_workload\helper::pseudonym($r->userid) : $r->firstname . ' ' . $r->lastname,
                $anon ? '' : $r->email,
                $anon ? '' : ($r->department ?? ''),
                $anon ? '' : ($r->institution ?? ''),
                number_format((float)$r->totalhours, 1),
                (int)$r->weeksactive, number_format($avg, 2),
            ]);
        }
        fclose($out);
    }
    exit;
}

// Fetch display data.

$weeklytotals = \block_workload\helper::get_cohort_weekly_totals($cohortid, $wf, $yf, $wt, $yt);
$topusers     = \block_workload\helper::get_cohort_top_users($cohortid, 10, $wf, $yf, $wt, $yt);
$kpisdata     = \block_workload\helper::get_cohort_overview_kpis($cohortid, $wf, $yf, $wt, $yt);

$totalcount = \block_workload\helper::get_cohort_top_users_count(
    $cohortid,
    $wf,
    $yf,
    $wt,
    $yt,
    $firstletter,
    $lastletter
);
$perpageeff = ($perpage > 0) ? $perpage : 0;
$offseteff  = ($perpage > 0) ? max(0, $page) * $perpage : 0;
$tableusers = \block_workload\helper::get_cohort_top_users(
    $cohortid,
    $perpageeff,
    $wf,
    $yf,
    $wt,
    $yt,
    $firstletter,
    $lastletter,
    $offseteff
);

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
        $pielabels[] = $anon
            ? \block_workload\helper::pseudonym($r->userid)
            : $truncate($r->firstname . ' ' . $r->lastname);
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

// Cohort selector options.
$allcohorts = ($coursemode === 'cohort')
    ? $DB->get_records('block_workload_cohorts', null, 'name ASC')
    : [];
$cohortopts = [];
$cohortopts[] = ['value' => 0, 'label' => get_string('allcohorts', 'block_workload'),
                 'selected' => $cohortid === 0];
foreach ($allcohorts as $c) {
    $cohortopts[] = [
        'value'    => (int)$c->id,
        'label'    => format_string($c->name) . ' – ' . format_string($c->degree_program),
        'selected' => (int)$c->id === $cohortid,
    ];
}

// KPI cards.
$kpis = $kpisdata ? [
    ['value' => (string)(int)$kpisdata->usercount,
     'label' => get_string('students', 'block_workload')],
    ['value' => (string)(int)$kpisdata->coursecount,
     'label' => get_string('courses', 'block_workload')],
    ['value' => (string)(int)$kpisdata->weekcount,
     'label' => get_string('weeksactive', 'block_workload')],
    ['value' => number_format((float)$kpisdata->totalhours, 1),
     'label' => get_string('totalhours', 'block_workload')],
] : [];

// Export URLs and labels.
$canexport  = has_capability('block/workload:export', $syscontext);
$exportbase = [
    'cohortid' => $cohortid,
    'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
    'weekto'   => $weekto, 'yearto'   => $yearto,
    'export'   => 1,
];
$exportquickurl  = (new moodle_url(
    '/blocks/workload/statistics.php',
    array_merge($exportbase, ['exporttype' => 'quick'])
))->out(false);
$exportdetailurl = (new moodle_url(
    '/blocks/workload/statistics.php',
    array_merge($exportbase, ['exporttype' => 'detailed'])
))->out(false);

// Base URL for letter-filter and per-page links.
$filterbase = new moodle_url('/blocks/workload/statistics.php', [
    'cohortid' => $cohortid,
    'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
    'weekto'   => $weekto, 'yearto'   => $yearto,
    'submitted' => 1,
]);

// A-Z initial filter bars. Hidden entirely for anonymized viewers:
// filtering by name initial would leak identity information.
$alphabars = [];
foreach (
    $anon ? [] : [
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

// Student table rows.
$viewbase  = new moodle_url('/blocks/workload/mystats.php', array_filter([
    'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
    'weekto'   => $weekto, 'yearto'   => $yearto,
]));
$tablerows = [];
foreach ($tableusers as $r) {
    $avg = $r->weeksactive > 0
        ? round((float)$r->totalhours / (int)$r->weeksactive, 1)
        : 0;
    $tablerows[] = [
        'name'       => $anon
            ? \block_workload\helper::pseudonym($r->userid)
            : $r->firstname . ' ' . $r->lastname,
        'profileurl' => $anon ? '' : (new moodle_url(
            '/user/view.php',
            ['id' => $r->userid, 'course' => SITEID]
        ))->out(false),
        'email'      => $anon ? '' : $r->email,
        'totalhours' => number_format((float)$r->totalhours, 1),
        'weeksactive' => (int)$r->weeksactive,
        'avghours'   => number_format($avg, 1),
        'viewurl'    => (new moodle_url($viewbase, $anon
            ? ['viewalias' => \block_workload\helper::pseudonym_token($r->userid)]
            : ['viewas' => $r->userid]))->out(false),
        'viewlabel'  => get_string('viewstudent', 'block_workload') . ' →',
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
        '',
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
    'backurl'   => (new moodle_url('/blocks/workload/manage.php'))->out(false),
    'backlabel' => get_string('managetitle', 'block_workload'),

    'cohortmode' => $coursemode === 'cohort',
    'cohortopts' => array_values($cohortopts),

    'filterform' => [
        'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
        'weekto'   => $weekto, 'yearto'   => $yearto,
    ],

    'hasresults' => $hasresults,
    'canexport'  => $canexport && $hasresults,

    'exportquickurl'   => $exportquickurl,
    'exportdetailurl'  => $exportdetailurl,
    'exporttitle'      => get_string('exportchoice', 'block_workload'),
    'exportlabelquick' => get_string('exportquick', 'block_workload'),
    'exportlabeldetail' => get_string('exportdetailed', 'block_workload'),
    'exportdescquick'  => get_string(
        $anon ? 'exportquickanon_desc' : 'exportquick_desc',
        'block_workload'
    ),
    'exportdescdetail' => get_string(
        $anon ? 'exportdetailedanon_desc' : 'exportdetailed_desc',
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

    'showsearch'   => !$anon,
    'showemail'    => !$anon,
];

// Output.

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_workload/statistics', $templatecontext);
$PAGE->requires->js_call_amd('block_workload/statistics', 'init', [[
    'noResultsStr' => get_string('nouserfound', 'block_workload'),
    'showSearch'   => !$anon,
]]);
echo $OUTPUT->footer();
