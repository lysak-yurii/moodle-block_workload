<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Student personal workload statistics page.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$weekfrom = optional_param('weekfrom', 0, PARAM_INT);
$yearfrom = optional_param('yearfrom', 0, PARAM_INT);
$weekto   = optional_param('weekto',   0, PARAM_INT);
$yearto   = optional_param('yearto',   0, PARAM_INT);
$export   = optional_param('export',   0, PARAM_BOOL);
// chartwk encodes the selected week for the per-week detail chart as YYYYWW
// (e.g. 202622 = ISO year 2026, week 22). Zero means "use default".
$chartwk  = optional_param('chartwk',  0, PARAM_INT);
// viewas: managers can pass a userid to view another student's statistics.
$viewas   = optional_param('viewas',   0, PARAM_INT);

$syscontext = context_system::instance();
require_login();

// Determine whose stats to show.
// A user with viewallstats can view any student by passing viewas=<userid>.
$targetuserid = $USER->id;
$viewingother = false;
if ($viewas > 0 && has_capability('block/workload:viewallstats', $syscontext)) {
    $targetuserid = $viewas;
    $viewingother = true;
}

require_capability('block/workload:viewownstats', $syscontext);

// When viewing another student, fetch their record for the heading.
$targetuser = $viewingother
    ? $DB->get_record('user', ['id' => $targetuserid, 'deleted' => 0], '*', MUST_EXIST)
    : $USER;

$PAGE->set_context($syscontext);
$PAGE->set_url('/blocks/workload/mystats.php', array_filter([
    'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
    'weekto'   => $weekto,   'yearto'   => $yearto,
    'chartwk'  => $chartwk,  'viewas'   => $viewas,
]));
$PAGE->set_pagelayout($viewingother ? 'admin' : 'base');
$pageTitle = $viewingother
    ? get_string('viewingas', 'block_workload', fullname($targetuser))
    : get_string('mystats', 'block_workload');
$PAGE->set_title($pageTitle);
$PAGE->set_heading($pageTitle);

// ---- Fetch entries for this user (week range filter if supplied) ----------
$entries = \block_workload\helper::get_student_entries(
    $targetuserid,
    $weekfrom ?: null,
    $yearfrom ?: null,
    $weekto   ?: null,
    $yearto   ?: null
);

// ---- CSV Export -----------------------------------------------------------
if ($export) {
    $filename = get_string('exportfilename', 'block_workload')
              . '_' . fullname($targetuser) . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

    fputcsv($out, [
        get_string('year',   'block_workload'),
        get_string('week',   'block_workload'),
        get_string('course', 'block_workload'),
        get_string('hours',  'block_workload'),
    ]);

    foreach ($entries as $e) {
        fputcsv($out, [
            $e->year,
            $e->weeknumber,
            $e->coursename,
            number_format((float)$e->hours, 1),
        ]);
    }

    fclose($out);
    exit;
}

// ---- Aggregate data -------------------------------------------------------
$weekTotals    = []; // 'YYYY-WW' => total hours (sorted by key)
$courseTotals  = []; // coursename => total hours
$grandTotal    = 0.0;
$weeksWithData = [];

foreach ($entries as $e) {
    $wkey = sprintf('%04d-%02d', (int)$e->year, (int)$e->weeknumber);
    $weekTotals[$wkey]            = ($weekTotals[$wkey] ?? 0) + (float)$e->hours;
    $courseTotals[$e->coursename] = ($courseTotals[$e->coursename] ?? 0) + (float)$e->hours;
    $grandTotal                  += (float)$e->hours;
    $weeksWithData[$wkey]         = true;
}
ksort($weekTotals);
// Course chart: highest first.
arsort($courseTotals);

$avgPerWeek = count($weeksWithData) > 0
    ? round($grandTotal / count($weeksWithData), 1)
    : 0;

// Per-week per-course map for the week detail chart.
// 'YYYY-WW' => [coursename => hours]
$weekCourseMap = [];
foreach ($entries as $e) {
    $wkey = sprintf('%04d-%02d', (int)$e->year, (int)$e->weeknumber);
    $weekCourseMap[$wkey][$e->coursename] =
        ($weekCourseMap[$wkey][$e->coursename] ?? 0) + (float)$e->hours;
}

// Resolve which week the detail chart shows.
// Default: current ISO week; fall back to the most recent week with data.
$currentWkInt = (int)date('o') * 100 + (int)date('W'); // e.g. 202622
$resolvedWkInt = $chartwk ?: $currentWkInt;
$chartWeekKey  = sprintf('%04d-%02d', (int)($resolvedWkInt / 100), $resolvedWkInt % 100);
if (!isset($weekCourseMap[$chartWeekKey])) {
    // Requested week has no data – fall back to the most recent week available.
    $chartWeekKey  = !empty($weekCourseMap) ? array_key_last($weekCourseMap) : '';
    $resolvedWkInt = $chartWeekKey
        ? (int)str_replace('-', '', $chartWeekKey)
        : 0;
}

// ---- Chart: hours per week ------------------------------------------------
// Use a line chart when there are many weeks: lines are designed for time
// series with dense data points and Chart.js auto-skips crowded axis labels.
// Bar chart is fine for short ranges where individual weeks are distinct.
$weekCount = count($weekTotals);
if ($weekCount > 12) {
    $chartWeek = new \core\chart_line();
    $chartWeek->set_smooth(true);
} else {
    $chartWeek = new \core\chart_bar();
}
$chartWeek->set_title(get_string('statsbyweek', 'block_workload'));
$seriesWeek = new \core\chart_series(
    get_string('hours', 'block_workload'),
    array_values($weekTotals)
);
$chartWeek->add_series($seriesWeek);
$chartWeek->set_labels(array_keys($weekTotals));

// ---- Chart: hours per course (pie) ----------------------------------------
// Pie charts are readable up to ~6 slices. Show the top 6 courses; merge the
// rest into one "others" slice. Simple and predictable.
$truncate = fn($s) => mb_strlen($s) > 35 ? mb_substr($s, 0, 33) . "\xe2\x80\xa6" : $s;

$PIE_MAX_SLICES    = 10;
$chartCourseTotals = array_slice($courseTotals, 0, $PIE_MAX_SLICES, true);
$overflowCourses   = array_slice($courseTotals, $PIE_MAX_SLICES, null, true);

if (!empty($overflowCourses)) {
    $otherSum   = (float) array_sum($overflowCourses);
    $otherCount = count($overflowCourses);
    $chartCourseTotals[get_string('othercourses', 'block_workload', $otherCount)] = $otherSum;
}

$chartCourse = new \core\chart_pie();
$chartCourse->set_title(get_string('statsbycourse', 'block_workload'));
$seriesCourse = new \core\chart_series(
    get_string('hours', 'block_workload'),
    array_values($chartCourseTotals)
);
$chartCourse->add_series($seriesCourse);
$chartCourse->set_labels(array_map($truncate, array_keys($chartCourseTotals)));

// ---- Chart 3: hours per course for a single selected week -----------------
// Only built when there is more than one week of data (otherwise it would be
// identical to the overall course pie chart above).
$chartWeekDetail = null;
if (count($weeksWithData) > 1 && $chartWeekKey !== '') {
    $wdSlice = $weekCourseMap[$chartWeekKey];
    arsort($wdSlice);

    $wdTotals   = array_slice($wdSlice, 0, $PIE_MAX_SLICES, true);
    $wdOverflow = array_slice($wdSlice, $PIE_MAX_SLICES, null, true);
    if (!empty($wdOverflow)) {
        $wdTotals[get_string('othercourses', 'block_workload', count($wdOverflow))]
            = (float) array_sum($wdOverflow);
    }

    [$wdYr, $wdWn] = explode('-', $chartWeekKey);
    $wdTitle = get_string('statsbycourse', 'block_workload')
             . ': ' . $wdYr . " \xe2\x80\x93 " . get_string('weeknumber', 'block_workload', (int)$wdWn);

    $chartWeekDetail = new \core\chart_pie();
    $chartWeekDetail->set_title($wdTitle);
    $seriesWd = new \core\chart_series(
        get_string('hours', 'block_workload'),
        array_values($wdTotals)
    );
    $chartWeekDetail->add_series($seriesWd);
    $chartWeekDetail->set_labels(array_map($truncate, array_keys($wdTotals)));
}

// ==========================================================================
// HTML Output
// ==========================================================================
echo $OUTPUT->header();

// Back button – context-aware.
if ($viewingother) {
    // Manager viewing a student: back to the statistics overview.
    echo html_writer::link(
        new moodle_url('/blocks/workload/statistics.php', array_filter([
            'weekfrom'  => $weekfrom, 'yearfrom' => $yearfrom,
            'weekto'    => $weekto,   'yearto'   => $yearto,
            'submitted' => 1,
        ])),
        '&larr; ' . get_string('backtooverview', 'block_workload'),
        ['class' => 'btn btn-outline-secondary mb-3']
    );
    // Student name heading so the manager knows whose data they're viewing.
    echo html_writer::tag('h4',
        get_string('viewingas', 'block_workload', fullname($targetuser)),
        ['class' => 'mb-3']
    );
} else {
    echo html_writer::link(
        new moodle_url('/my'),
        '&larr; ' . get_string('backtoblock', 'block_workload'),
        ['class' => 'btn btn-outline-secondary mb-3']
    );
}

// ---- Week-range filter form – mirrors the manager statistics layout -------
echo html_writer::start_tag('form', [
    'method' => 'get', 'action' => '',
    'class'  => 'card p-3 mb-4',
]);
echo html_writer::start_div('row g-3 align-items-end');

// From week / year.
echo html_writer::start_div('col-auto');
echo html_writer::tag('label', get_string('datefrom', 'block_workload'),
    ['class' => 'form-label small fw-semibold d-block']);
echo html_writer::start_div('d-flex align-items-center gap-1');
echo html_writer::empty_tag('input', [
    'type' => 'number', 'name' => 'weekfrom', 'id' => 'weekfrom',
    'value' => ($weekfrom ?: (int)date('W')), 'min' => 1, 'max' => 53,
    'class' => 'form-control', 'style' => 'width:5rem;',
    'placeholder' => get_string('week', 'block_workload'),
]);
echo html_writer::empty_tag('input', [
    'type' => 'number', 'name' => 'yearfrom', 'id' => 'yearfrom',
    'value' => ($yearfrom ?: (int)date('o')), 'min' => 2025,
    'class' => 'form-control', 'style' => 'width:6rem;',
    'placeholder' => get_string('year', 'block_workload'),
]);
echo html_writer::end_div();
echo html_writer::end_div();

// To week / year.
echo html_writer::start_div('col-auto');
echo html_writer::tag('label', get_string('dateto', 'block_workload'),
    ['class' => 'form-label small fw-semibold d-block']);
echo html_writer::start_div('d-flex align-items-center gap-1');
echo html_writer::empty_tag('input', [
    'type' => 'number', 'name' => 'weekto', 'id' => 'weekto',
    'value' => ($weekto ?: (int)date('W')), 'min' => 1, 'max' => 53,
    'class' => 'form-control', 'style' => 'width:5rem;',
    'placeholder' => get_string('week', 'block_workload'),
]);
echo html_writer::empty_tag('input', [
    'type' => 'number', 'name' => 'yearto', 'id' => 'yearto',
    'value' => ($yearto ?: (int)date('o')), 'min' => 2025,
    'class' => 'form-control', 'style' => 'width:6rem;',
    'placeholder' => get_string('year', 'block_workload'),
]);
echo html_writer::end_div();
echo html_writer::end_div();

// Buttons.
echo html_writer::start_div('col-auto');
echo html_writer::tag('span', '&nbsp;', ['class' => 'form-label small d-block']); // alignment spacer
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('filterresults', 'block_workload'),
    'class' => 'btn btn-primary me-2',
]);
// "Clear" link resets to all-time view.
if ($weekfrom || $yearfrom || $weekto || $yearto) {
    echo html_writer::link(
        new moodle_url('/blocks/workload/mystats.php'),
        get_string('clearfilters', 'block_workload'),
        ['class' => 'btn btn-outline-secondary']
    );
}
echo html_writer::end_div();

echo html_writer::end_div(); // .row
echo html_writer::end_tag('form');

// ---- No data state --------------------------------------------------------
if (empty($entries)) {
    echo $OUTPUT->notification(get_string('noentriesfound', 'block_workload'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// ---- Export button --------------------------------------------------------
$exportparams = array_filter([
    'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
    'weekto'   => $weekto,   'yearto'   => $yearto,
    'export'   => 1,
]);
echo html_writer::div(
    html_writer::link(
        new moodle_url('/blocks/workload/mystats.php', $exportparams),
        '&#8595;&nbsp;' . get_string('exportcsv', 'block_workload'),
        ['class' => 'btn btn-outline-success']
    ),
    'mb-3 text-end'
);

// ---- KPI summary cards – matching manager statistics style ----------------
echo html_writer::start_div('row g-3 mb-4');
$kpis = [
    [number_format($grandTotal, 1) . ' ' . get_string('hrs', 'block_workload'), get_string('statstotal', 'block_workload')],
    [$avgPerWeek . ' ' . get_string('hrs', 'block_workload'),                   get_string('statsavg',   'block_workload')],
    [(string)count($weeksWithData),           get_string('weeksactive',  'block_workload')],
    [(string)count($courseTotals),            get_string('courses',      'block_workload')],
];
foreach ($kpis as [$val, $lbl]) {
    echo html_writer::start_div('col-6 col-md-3');
    echo '<div class="card text-center p-3 h-100 shadow-sm wl-kpi-card">';
    echo html_writer::tag('div', $val, ['class' => 'display-6 fw-bold text-primary lh-1 mb-1']);
    echo html_writer::tag('div', $lbl, ['class' => 'text-muted small']);
    echo '</div>';
    echo html_writer::end_div(); // col
}
echo html_writer::end_div(); // .row

// ---- Chart: weekly trend --------------------------------------------------
echo '<div class="wl-chart-card card shadow-sm mb-4" style="overflow:hidden;">';
echo '<div class="card-body p-3">';
echo $OUTPUT->render($chartWeek);
echo '</div></div>';

// ---- Chart: hours per course ----------------------------------------------
echo '<div class="wl-chart-card card shadow-sm mb-4" style="overflow:hidden;">';
echo '<div class="card-body p-3">';
echo $OUTPUT->render($chartCourse);

// Collapsible list of courses hidden behind the "+ N course(s) more" slice.
if (!empty($overflowCourses)) {
    $overflowList = '<ul class="mb-0 mt-1">';
    foreach ($overflowCourses as $cname => $chours) {
        $overflowList .= '<li>' . format_string($cname)
                       . ': <strong>' . number_format($chours, 1) . '</strong>'
                       . ' ' . get_string('hrs', 'block_workload') . '</li>';
    }
    $overflowList .= '</ul>';

    echo '<details class="mt-2 small text-muted">'
       . '<summary style="cursor:pointer;" class="text-muted">'
       . get_string('othercourses', 'block_workload', count($overflowCourses))
       . '</summary>'
       . $overflowList
       . '</details>';
}

echo '</div></div>'; // card-body / wl-chart-card

// ---- Chart 3: per-week course breakdown -----------------------------------
if ($chartWeekDetail !== null) {
    echo html_writer::tag('h5', get_string('statsbycourse', 'block_workload')
        . ' &ndash; ' . get_string('week', 'block_workload'),
        ['class' => 'mt-2 mb-2']);

    // Week selector: a <select> of all weeks that have data, auto-submits on change.
    // Hidden inputs carry the active period filter through the submission.
    echo html_writer::start_tag('form', [
        'method' => 'get', 'action' => '',
        'class'  => 'd-flex align-items-center gap-2 mb-3',
    ]);
    foreach ([
        'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
        'weekto'   => $weekto,   'yearto'   => $yearto,
    ] as $pname => $pval) {
        if ($pval) {
            echo html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => $pname, 'value' => $pval,
            ]);
        }
    }
    echo html_writer::tag('label',
        get_string('week', 'block_workload') . ':',
        ['for' => 'wl-chartwk', 'class' => 'form-label mb-0 small fw-semibold text-nowrap']
    );

    $selectHtml = '<select name="chartwk" id="wl-chartwk" '
                . 'class="form-select" style="width:auto;" '
                . 'onchange="this.form.submit()">';
    foreach (array_keys($weekTotals) as $wkey) {
        [$optYr, $optWn] = explode('-', $wkey);
        $optVal      = (int)$optYr * 100 + (int)$optWn; // YYYYWW integer
        $optLabel    = $optYr . " \xe2\x80\x93 " . get_string('weeknumber', 'block_workload', (int)$optWn);
        $optSelected = ($wkey === $chartWeekKey) ? ' selected' : '';
        $selectHtml .= '<option value="' . $optVal . '"' . $optSelected . '>'
                    . htmlspecialchars($optLabel, ENT_QUOTES) . '</option>';
    }
    $selectHtml .= '</select>';
    echo $selectHtml;
    echo html_writer::end_tag('form');

    echo '<div class="wl-chart-card card shadow-sm mb-4" style="overflow:hidden;">';
    echo '<div class="card-body p-3">';
    echo $OUTPUT->render($chartWeekDetail);
    echo '</div></div>';
}

// ---- Collapsible week table -----------------------------------------------
// One summary row per week (always visible) showing week label, course count
// badge, and total hours.  Clicking a row expands/collapses the per-course
// detail rows beneath it.  This stays compact regardless of how many courses
// or weeks are in the dataset.
echo html_writer::tag('h5', get_string('statsbyweek', 'block_workload'), ['class' => 'mt-2 mb-3']);

// Build week → courses structure from raw entries (already sorted by DB query).
$weekGroups = []; // 'YYYY-WW' => ['label'=>string, 'rows'=>[[coursename,hours],...], 'total'=>float]
foreach ($entries as $e) {
    $wkey = sprintf('%04d-%02d', (int)$e->year, (int)$e->weeknumber);
    if (!isset($weekGroups[$wkey])) {
        $weekGroups[$wkey] = [
            'label' => $e->year . " \xe2\x80\x93 " . get_string('weeknumber', 'block_workload', (int)$e->weeknumber),
            'rows'  => [],
            'total' => 0.0,
        ];
    }
    $weekGroups[$wkey]['rows'][]  = [format_string($e->coursename), (float)$e->hours];
    $weekGroups[$wkey]['total'] += (float)$e->hours;
}
ksort($weekGroups);

// ---- Render as raw HTML (html_table cannot produce toggleable row groups) --
$courseStr  = get_string('course',  'block_workload');
$coursesStr = get_string('courses', 'block_workload');
$weekStr    = get_string('week',    'block_workload');
$hoursStr   = get_string('hours',   'block_workload');

$html  = '<table class="generaltable table-sm table-hover">';
$html .= '<thead><tr>'
       . '<th>' . htmlspecialchars($weekStr, ENT_QUOTES) . '</th>'
       . '<th class="text-end" style="min-width:6rem;">'
       . htmlspecialchars($hoursStr, ENT_QUOTES) . '</th>'
       . '</tr></thead><tbody>';

$idx = 0;
foreach ($weekGroups as $wd) {
    $idx++;
    $gid          = 'wlwg' . $idx; // unique id for this week's detail group
    $courseCount  = count($wd['rows']);
    $badgeLabel   = $courseCount . "\xc2\xa0" . ($courseCount === 1 ? $courseStr : $coursesStr);

    // Summary row – always visible, clickable to expand.
    $html .= '<tr class="table-secondary" style="cursor:pointer;" '
           . 'onclick="wlWeekToggle(\'' . $gid . '\',this)" '
           . 'title="' . htmlspecialchars(get_string('viewdetailed', 'block_workload'), ENT_QUOTES) . '">'
           . '<td><strong>' . htmlspecialchars($wd['label'], ENT_QUOTES) . '</strong>'
           . ' <span class="badge text-bg-secondary fw-normal ms-1 small">'
           . htmlspecialchars($badgeLabel, ENT_QUOTES) . '</span>'
           . ' <span id="' . $gid . '-icon" class="text-muted ms-1 small">'
           . '&#9660;</span></td>'  // ▼ — collapsed indicator
           . '<td class="text-end"><strong>' . number_format($wd['total'], 1) . '</strong></td>'
           . '</tr>';

    // Per-course detail rows – hidden until the summary row is clicked.
    foreach ($wd['rows'] as [$cname, $chours]) {
        $html .= '<tr class="' . $gid . '" style="display:none;">'
               . '<td class="ps-4 small text-muted">' . $cname . '</td>'
               . '<td class="text-end small">' . number_format($chours, 1) . '</td>'
               . '</tr>';
    }
}

// Grand total row.
$html .= '<tr class="table-secondary fw-bold">'
       . '<td><strong>' . get_string('statstotal', 'block_workload') . '</strong></td>'
       . '<td class="text-end"><strong>' . number_format($grandTotal, 1) . '</strong></td>'
       . '</tr>';

$html .= '</tbody></table>';

echo html_writer::div($html, 'table-responsive');

// Toggle function: show/hide detail rows and flip the ▼/▲ indicator.
echo html_writer::script(
    "function wlWeekToggle(gid,hdr){" .
    "var rows=document.querySelectorAll('.' + gid);" .
    "var icon=document.getElementById(gid + '-icon');" .
    "var expand=rows.length>0 && rows[0].style.display==='none';" .
    "rows.forEach(function(r){r.style.display=expand?'':'none';});" .
    "if(icon)icon.innerHTML=expand?'&#9650;':'&#9660;';" . // ▲ when open, ▼ when closed
    "}"
);

echo $OUTPUT->footer();
