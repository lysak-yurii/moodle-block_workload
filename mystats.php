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
 * Student personal workload statistics page.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$weekfrom = optional_param('weekfrom', null, PARAM_INT);
$yearfrom = optional_param('yearfrom', null, PARAM_INT);
$weekto   = optional_param('weekto', null, PARAM_INT);
$yearto   = optional_param('yearto', null, PARAM_INT);
$export   = optional_param('export', 0, PARAM_BOOL);
$chartwk  = optional_param('chartwk', 0, PARAM_INT);
$viewas   = optional_param('viewas', 0, PARAM_INT);

$syscontext = context_system::instance();
require_login();

$targetuserid = $USER->id;
$viewingother = false;
if ($viewas > 0 && has_capability('block/workload:viewallstats', $syscontext)) {
    $targetuserid = $viewas;
    $viewingother = true;
}

require_capability('block/workload:viewownstats', $syscontext);

$targetuser = $viewingother
    ? $DB->get_record('user', ['id' => $targetuserid, 'deleted' => 0], '*', MUST_EXIST)
    : $USER;

$PAGE->set_context($syscontext);
$PAGE->set_url('/blocks/workload/mystats.php', array_filter([
    'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
    'weekto'   => $weekto, 'yearto'   => $yearto,
    'chartwk'  => $chartwk, 'viewas'   => $viewas,
]));
$PAGE->set_pagelayout($viewingother ? 'admin' : 'base');
$pagetitle = $viewingother
    ? get_string('viewingas', 'block_workload', fullname($targetuser))
    : get_string('mystats', 'block_workload');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

// No filter applied yet → default to the current ISO week.
$hasfilter     = ($weekfrom !== null || $yearfrom !== null || $weekto !== null || $yearto !== null);
$queryweekfrom = $weekfrom ?? (int)date('W');
$queryyearfrom = $yearfrom ?? (int)date('o');
$queryweekto = $weekto ?? (int)date('W');
$queryyearto = $yearto ?? (int)date('o');

// Fetch entries.
$entries = \block_workload\helper::get_student_entries(
    $targetuserid,
    $queryweekfrom,
    $queryyearfrom,
    $queryweekto,
    $queryyearto
);

// CSV Export.
if ($export) {
    $filename = get_string('exportfilename', 'block_workload')
              . '_' . fullname($targetuser) . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        get_string('year', 'block_workload'),
        get_string('week', 'block_workload'),
        get_string('course', 'block_workload'),
        get_string('hours', 'block_workload'),
    ]);
    foreach ($entries as $e) {
        fputcsv($out, [$e->year, $e->weeknumber, $e->coursename, number_format((float)$e->hours, 1)]);
    }
    fclose($out);
    exit;
}

// Aggregate.

$weektotals   = [];
$coursetotals = [];
$grandtotal   = 0.0;
$weekswithdata = [];

foreach ($entries as $e) {
    $wkey = sprintf('%04d-%02d', (int)$e->year, (int)$e->weeknumber);
    $weektotals[$wkey]            = ($weektotals[$wkey] ?? 0) + (float)$e->hours;
    $coursetotals[$e->coursename] = ($coursetotals[$e->coursename] ?? 0) + (float)$e->hours;
    $grandtotal                  += (float)$e->hours;
    $weekswithdata[$wkey]         = true;
}
ksort($weektotals);
arsort($coursetotals);

$avgperweek = count($weekswithdata) > 0
    ? round($grandtotal / count($weekswithdata), 1)
    : 0;

$weekcoursemap = [];
foreach ($entries as $e) {
    $wkey = sprintf('%04d-%02d', (int)$e->year, (int)$e->weeknumber);
    $weekcoursemap[$wkey][$e->coursename] =
        ($weekcoursemap[$wkey][$e->coursename] ?? 0) + (float)$e->hours;
}

// Resolve chart week.
$currentwkint  = (int)date('o') * 100 + (int)date('W');
$resolvedwkint = $chartwk ?: $currentwkint;
$chartweekkey  = sprintf('%04d-%02d', (int)($resolvedwkint / 100), $resolvedwkint % 100);
if (!isset($weekcoursemap[$chartweekkey])) {
    $chartweekkey  = !empty($weekcoursemap) ? array_key_last($weekcoursemap) : '';
    $resolvedwkint = $chartweekkey
        ? (int)str_replace('-', '', $chartweekkey)
        : 0;
}

// Build charts (only when there are entries).

$chartweekhtml       = '';
$chartcoursehtml     = '';
$chartweekdetailhtml = '';
$chartweekdetail     = null;

$piemaxslices      = 10;
$truncate          = fn($s) => mb_strlen($s) > 35 ? mb_substr($s, 0, 33) . "\xe2\x80\xa6" : $s;
$overflowcourses   = [];

if (!empty($entries)) {
    // Weekly trend chart.
    if (count($weektotals) > 12) {
        $chartweek = new \core\chart_line();
        $chartweek->set_smooth(true);
    } else {
        $chartweek = new \core\chart_bar();
    }
    $chartweek->set_title(get_string('statsbyweek', 'block_workload'));
    $chartweek->add_series(new \core\chart_series(
        get_string('hours', 'block_workload'),
        array_values($weektotals)
    ));
    $chartweek->set_labels(array_keys($weektotals));
    $chartweekhtml = $OUTPUT->render($chartweek);

    // Course pie chart.
    $chartcoursetotals = array_slice($coursetotals, 0, $piemaxslices, true);
    $overflowcourses   = array_slice($coursetotals, $piemaxslices, null, true);
    if (!empty($overflowcourses)) {
        $chartcoursetotals[get_string('othercourses', 'block_workload', count($overflowcourses))]
            = (float)array_sum($overflowcourses);
    }
    $chartcourse = new \core\chart_pie();
    $chartcourse->set_title(get_string('statsbycourse', 'block_workload'));
    $chartcourse->add_series(new \core\chart_series(
        get_string('hours', 'block_workload'),
        array_values($chartcoursetotals)
    ));
    $chartcourse->set_labels(array_map($truncate, array_keys($chartcoursetotals)));
    $chartcoursehtml = $OUTPUT->render($chartcourse);

    // Week-detail chart (only when more than one week of data).
    if (count($weekswithdata) > 1 && $chartweekkey !== '') {
        $wdslice  = $weekcoursemap[$chartweekkey];
        arsort($wdslice);
        $wdtotals   = array_slice($wdslice, 0, $piemaxslices, true);
        $wdoverflow = array_slice($wdslice, $piemaxslices, null, true);
        if (!empty($wdoverflow)) {
            $wdtotals[get_string('othercourses', 'block_workload', count($wdoverflow))]
                = (float)array_sum($wdoverflow);
        }
        [$wdyr, $wdwn] = explode('-', $chartweekkey);
        $wdtitle = get_string('statsbycourse', 'block_workload')
                 . ': ' . $wdyr . " \xe2\x80\x93 "
                 . get_string('weeknumber', 'block_workload', (int)$wdwn);
        $chartweekdetail = new \core\chart_pie();
        $chartweekdetail->set_title($wdtitle);
        $chartweekdetail->add_series(new \core\chart_series(
            get_string('hours', 'block_workload'),
            array_values($wdtotals)
        ));
        $chartweekdetail->set_labels(array_map($truncate, array_keys($wdtotals)));
        $chartweekdetailhtml = $OUTPUT->render($chartweekdetail);
    }
}

// Build template context.

// Back navigation.
$backurl = $viewingother
    ? (new moodle_url('/blocks/workload/statistics.php', array_filter([
        'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
        'weekto'   => $weekto, 'yearto'   => $yearto,
        'submitted' => 1,
      ])))->out(false)
    : (new moodle_url('/my'))->out(false);
$backlabel = $viewingother
    ? get_string('backtooverview', 'block_workload')
    : get_string('backtoblock', 'block_workload');

// Clear-filter URL preserves viewas context.
$clearurl = (new moodle_url(
    '/blocks/workload/mystats.php',
    $viewas ? ['viewas' => $viewas] : []
))->out(false);

// Export URL.
$exportparams = array_filter([
    'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
    'weekto'   => $weekto, 'yearto'   => $yearto,
    'export'   => 1,
    'viewas'   => $viewas ?: null,
]);
$exporturl = (new moodle_url('/blocks/workload/mystats.php', $exportparams))->out(false);

// KPI cards.
$kpis = [
    ['value' => number_format($grandtotal, 1) . ' ' . get_string('hrs', 'block_workload'),
     'label' => get_string('statstotal', 'block_workload')],
    ['value' => $avgperweek . ' ' . get_string('hrs', 'block_workload'),
     'label' => get_string('statsavg', 'block_workload')],
    ['value' => (string)count($weekswithdata),
     'label' => get_string('weeksactive', 'block_workload')],
    ['value' => (string)count($coursetotals),
     'label' => get_string('courses', 'block_workload')],
];

// Overflow courses list.
$overflowcoursesctx = [];
foreach ($overflowcourses as $cname => $chours) {
    $overflowcoursesctx[] = [
        'name'  => format_string($cname),
        'hours' => number_format($chours, 1),
    ];
}

// Week-detail chart week-selector options.
$weekoptions = [];
foreach (array_keys($weektotals) as $wkey) {
    [$optyr, $optwn] = explode('-', $wkey);
    $weekoptions[] = [
        'value'    => (int)$optyr * 100 + (int)$optwn,
        'label'    => $optyr . " \xe2\x80\x93 " . get_string('weeknumber', 'block_workload', (int)$optwn),
        'selected' => ($wkey === $chartweekkey),
    ];
}

// Hidden inputs that carry the active date-range filter into the week-detail form.
$chartweekfilter = [];
foreach (
    ['weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
          'weekto'   => $weekto, 'yearto'   => $yearto] as $n => $v
) {
    if ($v) {
        $chartweekfilter[] = ['name' => $n, 'value' => $v];
    }
}
if ($viewas) {
    $chartweekfilter[] = ['name' => 'viewas', 'value' => $viewas];
}

// Collapsible week table.
$coursestr  = get_string('course', 'block_workload');
$coursesstr = get_string('courses', 'block_workload');
$weekgroupsctx = [];
$weekgroups    = [];
foreach ($entries as $e) {
    $wkey = sprintf('%04d-%02d', (int)$e->year, (int)$e->weeknumber);
    if (!isset($weekgroups[$wkey])) {
        $weekgroups[$wkey] = [
            'label' => $e->year . " \xe2\x80\x93 "
                     . get_string('weeknumber', 'block_workload', (int)$e->weeknumber),
            'rows'  => [],
            'total' => 0.0,
        ];
    }
    $weekgroups[$wkey]['rows'][] = [format_string($e->coursename), (float)$e->hours];
    $weekgroups[$wkey]['total'] += (float)$e->hours;
}
ksort($weekgroups);

$idx = 0;
foreach ($weekgroups as $wd) {
    $idx++;
    $gid         = 'wlwg' . $idx;
    $coursecount = count($wd['rows']);
    $courses     = [];
    foreach ($wd['rows'] as [$cname, $chours]) {
        $courses[] = [
            'gid'   => $gid,
            'name'  => $cname,
            'hours' => number_format($chours, 1),
        ];
    }
    $weekgroupsctx[] = [
        'gid'         => $gid,
        'label'       => $wd['label'],
        'total'       => number_format($wd['total'], 1),
        'coursebadge' => $coursecount . "\xc2\xa0"
                       . ($coursecount === 1 ? $coursestr : $coursesstr),
        'courses'     => $courses,
    ];
}

$templatecontext = [
    'viewingother' => $viewingother,
    'hasviewas'    => $viewas > 0,
    'viewas'       => $viewas,
    'backurl'      => $backurl,
    'backlabel'    => $backlabel,
    'viewingas'    => $viewingother ? fullname($targetuser) : '',

    'filterform' => [
        'weekfrom'  => $queryweekfrom,
        'yearfrom'  => $queryyearfrom,
        'weekto'    => $queryweekto,
        'yearto'    => $queryyearto,
        'hasfilter' => $hasfilter,
        'clearurl'  => $clearurl,
    ],

    'hasdata'   => !empty($entries),
    'exporturl' => $exporturl,
    'kpis'      => $kpis,

    'chartweekhtml'   => $chartweekhtml,
    'chartcoursehtml' => $chartcoursehtml,
    'hasoverflow'     => !empty($overflowcourses),
    'overflowlabel'   => !empty($overflowcourses)
        ? get_string('othercourses', 'block_workload', count($overflowcourses))
        : '',
    'overflowcourses' => array_values($overflowcoursesctx),

    'hasweekdetail'       => $chartweekdetail !== null,
    'chartweekdetailhtml' => $chartweekdetailhtml,
    'weekoptions'         => array_values($weekoptions),
    'chartweekfilter'     => array_values($chartweekfilter),

    'weekgroups' => array_values($weekgroupsctx),
    'grandtotal' => number_format($grandtotal, 1),
];

// Output.

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_workload/mystats', $templatecontext);
if (!empty($entries)) {
    $PAGE->requires->js_call_amd('block_workload/mystats', 'init');
}
echo $OUTPUT->footer();
