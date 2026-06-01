<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Quality Manager – workload statistics overview.
 *
 * Aggregate view (no student selected):
 *   KPI cards · hours-by-week chart · top-10 students pie · paginated student table
 *   with A-Z name filters and per-page selector.
 *
 * Individual view: "View →" in the student table opens mystats.php in manager
 *   context (viewas=userid), reusing all individual-stats logic.
 *
 * Append &export=1 to download a CSV of the full student totals (unfiltered).
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Parameters.
$cohortid    = optional_param('cohortid', 0, PARAM_INT);
$weekfrom    = optional_param('weekfrom', (int)date('W'), PARAM_INT);
$yearfrom    = optional_param('yearfrom', (int)date('o'), PARAM_INT);
$weekto      = optional_param('weekto', (int)date('W'), PARAM_INT);
$yearto      = optional_param('yearto', (int)date('o'), PARAM_INT);
$export      = optional_param('export', 0, PARAM_BOOL);
$exporttype  = optional_param('exporttype', 'quick', PARAM_ALPHA);
$submitted   = 1; // Data always loads; defaults to current week.
// Table filters.
$alphabet    = explode(',', get_string('alphabet', 'langconfig'));
$firstletter = optional_param('firstletter', '', PARAM_RAW);
$lastletter  = optional_param('lastletter', '', PARAM_RAW);
$firstletter = (in_array($firstletter, $alphabet, true)) ? $firstletter : '';
$lastletter  = (in_array($lastletter, $alphabet, true)) ? $lastletter : '';
$perpage     = optional_param('perpage', 25, PARAM_INT);  // 0 = show all
$page        = optional_param('page', 0, PARAM_INT);

$syscontext = context_system::instance();
require_login();
require_capability('block/workload:viewallstats', $syscontext);

$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('statisticstitle', 'block_workload'));
$PAGE->set_heading(get_string('statisticstitle', 'block_workload'));
$PAGE->set_url('/blocks/workload/statistics.php', array_filter([
    'cohortid'    => $cohortid,
    'weekfrom'    => $weekfrom, 'yearfrom'    => $yearfrom,
    'weekto'      => $weekto, 'yearto'      => $yearto,
    'firstletter' => $firstletter, 'lastletter'  => $lastletter,
    'perpage'     => ($perpage !== 25) ? $perpage : 0, // Omit default.
    'page'        => $page,
]));

global $DB, $OUTPUT;

$coursemode = get_config('block_workload', 'coursemode') ?: 'cohort';

// In enrollment mode always query all entries (cohortid = 0).
if ($coursemode === 'enrollment') {
    $cohortid = 0;
}

// Cohort options (cohort mode only).
$allcohorts = ($coursemode === 'cohort') ? $DB->get_records('block_workload_cohorts', null, 'name ASC') : [];
$cohortopts = [0 => get_string('allcohorts', 'block_workload')];
foreach ($allcohorts as $c) {
    $cohortopts[$c->id] = format_string($c->name) . ' – ' . format_string($c->degree_program);
}

// Shared filter shortcuts.
$wf = $weekfrom ?: null;
$yf = $yearfrom ?: null;
$wt = $weekto ?: null;
$yt = $yearto ?: null;

// CSV Export (fetch all, ignore letter/page filters).
if ($export && has_capability('block/workload:export', $syscontext)) {
    if ($exporttype === 'detailed') {
        $exportrows = \block_workload\helper::get_cohort_detailed_export($cohortid, $wf, $yf, $wt, $yt);

        $filename = get_string('exportfilenamedetailed', 'block_workload') . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel.
        fputcsv($out, [
            get_string('student', 'block_workload'),
            get_string('email'),
            get_string('department'),
            get_string('institution'),
            get_string('course', 'block_workload'),
            get_string('role', 'block_workload'),
            get_string('coursehours', 'block_workload'),
        ]);
        foreach ($exportrows as $r) {
            fputcsv($out, [
                $r->firstname . ' ' . $r->lastname,
                $r->email,
                $r->department ?? '',
                $r->institution ?? '',
                $r->coursename,
                $r->roles,
                number_format((float)$r->coursehours, 1),
            ]);
        }
        fclose($out);
    } else {
        $exportusers = \block_workload\helper::get_cohort_top_users($cohortid, 0, $wf, $yf, $wt, $yt);

        $filename = get_string('exportfilename', 'block_workload') . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel.
        fputcsv($out, [
            get_string('student', 'block_workload'),
            get_string('email'),
            get_string('department'),
            get_string('institution'),
            get_string('totalhours', 'block_workload'),
            get_string('weeksactive', 'block_workload'),
            get_string('averagehours', 'block_workload'),
        ]);
        foreach ($exportusers as $r) {
            $avg = $r->weeksactive > 0 ? round((float)$r->totalhours / (int)$r->weeksactive, 2) : 0;
            fputcsv($out, [
                $r->firstname . ' ' . $r->lastname,
                $r->email,
                $r->department ?? '',
                $r->institution ?? '',
                number_format((float)$r->totalhours, 1),
                (int)$r->weeksactive,
                number_format($avg, 2),
            ]);
        }
        fclose($out);
    }
    exit;
}

// Fetch display data (only after filter form submitted).
$weeklytotals = [];
$topusers     = [];
$tableusers   = [];
$totalcount   = 0;
$kpis         = null;

if ($submitted) {
    // Charts & KPIs: always full/unfiltered aggregate.
    $weeklytotals = \block_workload\helper::get_cohort_weekly_totals($cohortid, $wf, $yf, $wt, $yt);
    $topusers     = \block_workload\helper::get_cohort_top_users($cohortid, 10, $wf, $yf, $wt, $yt);
    $kpis         = \block_workload\helper::get_cohort_overview_kpis($cohortid, $wf, $yf, $wt, $yt);

    // Table: letter-filtered, paginated – only load what we actually display.
    $totalcount   = \block_workload\helper::get_cohort_top_users_count(
        $cohortid,
        $wf,
        $yf,
        $wt,
        $yt,
        $firstletter,
        $lastletter
    );
    $perpageeff   = ($perpage > 0) ? $perpage : 0;
    $offseteff    = ($perpage > 0) ? max(0, $page) * $perpage : 0;
    $tableusers   = \block_workload\helper::get_cohort_top_users(
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
}

// Build charts.
$chartweekavg   = null;
$chartweekusers = null;
$charttopusers  = null;

if ($submitted && !empty($weeklytotals)) {
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

    // Chart 1 – avg hrs / student.
    if ($multiweek) {
        $chartweekavg = new \core\chart_line();
        $chartweekavg->set_smooth(true);
    } else {
        $chartweekavg = new \core\chart_bar();
    }
    $chartweekavg->set_title(get_string('avghrsperstudent', 'block_workload'));
    $seriesavg = new \core\chart_series(get_string('avghrsperstudent', 'block_workload'), $avgvalues);
    $yavg = new \core\chart_axis();
    $yavg->set_min(0);
    $chartweekavg->add_series($seriesavg);
    $chartweekavg->set_labels($weeklabels);
    $chartweekavg->set_yaxis($yavg, 0);

    // Chart 2 – active students.
    if ($multiweek) {
        $chartweekusers = new \core\chart_line();
        $chartweekusers->set_smooth(true);
    } else {
        $chartweekusers = new \core\chart_bar();
    }
    $chartweekusers->set_title(get_string('activestudents', 'block_workload'));
    $seriesusers = new \core\chart_series(get_string('activestudents', 'block_workload'), $uservalues);
    $yusers = new \core\chart_axis();
    $yusers->set_min(0);
    $chartweekusers->add_series($seriesusers);
    $chartweekusers->set_labels($weeklabels);
    $chartweekusers->set_yaxis($yusers, 0);
}

if ($submitted && !empty($topusers)) {
    $truncate  = fn($s) => mb_strlen($s) > 35 ? mb_substr($s, 0, 33) . "\xe2\x80\xa6" : $s;
    $pielabels = [];
    $pievalues = [];
    foreach ($topusers as $r) {
        $pielabels[] = $truncate($r->firstname . ' ' . $r->lastname);
        $pievalues[] = (float) $r->totalhours;
    }
    $charttopusers = new \core\chart_pie();
    $charttopusers->set_title(get_string('topstudents', 'block_workload', count($topusers)));
    $seriestop = new \core\chart_series(get_string('totalhours', 'block_workload'), $pievalues);
    $charttopusers->add_series($seriestop);
    $charttopusers->set_labels($pielabels);
}

// HTML Output.
echo $OUTPUT->header();

echo html_writer::link(
    new moodle_url('/blocks/workload/manage.php'),
    '&larr; ' . get_string('managetitle', 'block_workload'),
    ['class' => 'btn btn-outline-secondary mb-3']
);


// Live student search (above filter panel).
$noresultsjson = json_encode(get_string('nouserfound', 'block_workload'));
$viewstudentjson = json_encode(get_string('viewstudent', 'block_workload') . ' →');

echo '<div class="card p-3 mb-3">';
echo '<label class="form-label small fw-semibold mb-2" for="wl-usersearch">'
   . get_string('viewstudent', 'block_workload') . ':</label>';
echo '<div class="d-flex align-items-center gap-2 flex-wrap">';
echo '<div class="position-relative" style="flex:1;min-width:260px;max-width:480px;">';
echo '<input type="text" id="wl-usersearch" class="form-control"'
   . ' placeholder="' . s(get_string('searchusers', 'block_workload')) . '"'
   . ' autocomplete="off">';
echo '<ul id="wl-usersearch-results" class="list-unstyled mb-0 border rounded bg-white shadow-sm"'
   . ' style="display:none;position:absolute;top:100%;left:0;right:0;z-index:1050;'
   . 'max-height:260px;overflow-y:auto;margin-top:2px;"></ul>';
echo '</div>';
echo '<button type="button" id="wl-usersearch-clear"'
   . ' class="btn btn-outline-secondary" style="display:none;" title="Clear">'
   . '&#x2715;</button>';
echo '<a href="#" id="wl-usersearch-btn" class="btn btn-outline-primary" style="display:none;">'
   . get_string('viewstudent', 'block_workload') . ' &rarr;</a>';
echo '</div>';
echo '</div>';

// Live-search JavaScript.
echo html_writer::script(
    "(function(){" .
    "var inp=document.getElementById('wl-usersearch')," .
    "list=document.getElementById('wl-usersearch-results')," .
    "btn=document.getElementById('wl-usersearch-btn')," .
    "clr=document.getElementById('wl-usersearch-clear')," .
    "timer=null," .
    "noRes=" . $noresultsjson . ";" .

    "function esc(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');}" .

    "function buildUrl(uid){" .
        "var p='?viewas='+uid;" .
        "['weekfrom','yearfrom','weekto','yearto'].forEach(function(n){" .
            "var el=document.getElementById(n);" .
            "if(el&&el.value)p+='&'+n+'='+encodeURIComponent(el.value);" .
        "});" .
        "return M.cfg.wwwroot+'/blocks/workload/mystats.php'+p;" .
    "}" .

    "function selectUser(id,fn,em){" .
        "inp.value=fn+' ('+em+')';" .
        "inp.dataset.userid=id;" .
        "list.innerHTML='';list.style.display='none';" .
        "btn.href=buildUrl(id);btn.style.display='';" .
        "clr.style.display='';" .
    "}" .

    "function clearSearch(){" .
        "inp.value='';inp.dataset.userid='';" .
        "list.innerHTML='';list.style.display='none';" .
        "btn.style.display='none';clr.style.display='none';" .
        "inp.focus();" .
    "}" .

    "function doSearch(q){" .
        "var cohortEl=document.getElementById('wl-cohortid')," .
        "cid=cohortEl?cohortEl.value:'0'," .
        "url=M.cfg.wwwroot+'/blocks/workload/ajax_usersearch.php'" .
            "+'?q='+encodeURIComponent(q)+'&cohortid='+encodeURIComponent(cid);" .
        "fetch(url,{credentials:'same-origin'})" .
            ".then(function(r){return r.json();})" .
            ".then(function(data){" .
                "list.innerHTML='';" .
                "if(!data||!data.length){" .
                    "var li=document.createElement('li');" .
                    "li.className='px-3 py-2 text-muted small';" .
                    "li.textContent=noRes;" .
                    "list.appendChild(li);" .
                    "list.style.display='block';return;" .
                "}" .
                "data.forEach(function(u){" .
                    "var li=document.createElement('li');" .
                    "li.className='px-3 py-2';" .
                    "li.style.cursor='pointer';" .
                    "li.innerHTML='<strong>'+esc(u.fullname)+'</strong>" .
                        " <small class=\"text-muted\">'+esc(u.email)+'</small>';" .
                    "li.addEventListener('mouseenter',function(){this.style.background='#f8f9fa';});" .
                    "li.addEventListener('mouseleave',function(){this.style.background='';});" .
                    "li.addEventListener('mousedown',function(e){" .
                        "e.preventDefault();" .
                        "selectUser(u.id,u.fullname,u.email);" .
                    "});" .
                    "list.appendChild(li);" .
                "});" .
                "list.style.display='block';" .
            "})" .
            ".catch(function(){list.innerHTML='';list.style.display='none';});" .
    "}" .

    "inp.addEventListener('input',function(){" .
        "clearTimeout(timer);" .
        "inp.dataset.userid='';" .
        "btn.style.display='none';" .
        "clr.style.display='none';" .
        "var q=this.value.trim();" .
        "if(q.length<2){list.innerHTML='';list.style.display='none';return;}" .
        "timer=setTimeout(function(){doSearch(q);},250);" .
    "});" .

    "inp.addEventListener('blur',function(){" .
        "setTimeout(function(){list.style.display='none';},200);" .
    "});" .

    "inp.addEventListener('focus',function(){" .
        "var q=this.value.trim();" .
        "if(q.length>=2&&!this.dataset.userid)doSearch(q);" .
    "});" .

    "clr.addEventListener('click',clearSearch);" .

    "document.addEventListener('click',function(e){" .
        "if(!inp.contains(e.target)&&!list.contains(e.target))" .
            "list.style.display='none';" .
    "});" .

    "})()"
);

// Filter form.
echo html_writer::start_tag('form', [
    'method' => 'get', 'action' => '',
    'class'  => 'card p-3 mb-4',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'submitted', 'value' => '1']);
echo html_writer::start_div('row g-3 align-items-end');

// Cohort selector (cohort mode only).
if ($coursemode === 'cohort') {
    echo html_writer::start_div('col-12 col-sm-auto');
    echo html_writer::tag(
        'label',
        get_string('selectcohort', 'block_workload'),
        ['for' => 'wl-cohortid', 'class' => 'form-label small fw-semibold']
    );
    echo html_writer::select(
        $cohortopts,
        'cohortid',
        $cohortid,
        false,
        ['id' => 'wl-cohortid', 'class' => 'form-select', 'style' => 'min-width:220px;']
    );
    echo html_writer::end_div();
} else {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'id' => 'wl-cohortid', 'value' => '0']);
}

// From week / year.
echo html_writer::start_div('col-auto');
echo html_writer::tag(
    'label',
    get_string('datefrom', 'block_workload'),
    ['class' => 'form-label small fw-semibold d-block']
);
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
echo html_writer::tag(
    'label',
    get_string('dateto', 'block_workload'),
    ['class' => 'form-label small fw-semibold d-block']
);
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

// Apply Filter button.
echo html_writer::start_div('col-auto');
echo html_writer::tag('span', '&nbsp;', ['class' => 'form-label small d-block']);
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('filterresults', 'block_workload'),
    'class' => 'btn btn-primary',
]);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_tag('form');

// Results area.
if (empty($tableusers) && empty($weeklytotals)) {
    echo $OUTPUT->notification(get_string('nostatsfound', 'block_workload'), 'info');
} else {
    // Export button – opens a native Moodle modal (same as Edit Cohort) to choose export type.
    if (has_capability('block/workload:export', $syscontext)) {
        $exportbase = [
            'cohortid' => $cohortid,
            'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
            'weekto'   => $weekto, 'yearto'   => $yearto,
            'export'   => 1,
        ];
        $quickurl    = (new moodle_url(
            '/blocks/workload/statistics.php',
            array_merge($exportbase, ['exporttype' => 'quick'])
        ))->out(false);
        $detailedurl = (new moodle_url(
            '/blocks/workload/statistics.php',
            array_merge($exportbase, ['exporttype' => 'detailed'])
        ))->out(false);

        $PAGE->requires->js_amd_inline("
require(['core/modal_factory', 'jquery'], function(ModalFactory, \$) {
    \$(document).on('click', '[data-wl-exportmodal]', function() {
        var t    = \$(this);
        var body = '<div class=\"d-grid gap-3\">' +
            '<a href=\"'  + t.data('quick-url')  + '\" class=\"btn btn-outline-primary text-start p-3\">' +
            '<div class=\"fw-semibold\">'                                   + t.data('label-quick')  + '</div>' +
            '<div class=\"small mt-1\" style=\"opacity:0.8\">'              + t.data('desc-quick')   + '</div>' +
            '</a>' +
            '<a href=\"'  + t.data('detail-url') + '\" class=\"btn btn-outline-secondary text-start p-3\">' +
            '<div class=\"fw-semibold\">'                                   + t.data('label-detail') + '</div>' +
            '<div class=\"small mt-1\" style=\"opacity:0.8\">'              + t.data('desc-detail')  + '</div>' +
            '</a></div>';
        ModalFactory.create({
            type:  ModalFactory.types.DEFAULT,
            title: t.data('title'),
            body:  body,
        }).then(function(modal) { modal.show(); return modal; });
    });
});
        ");

        echo html_writer::div(
            html_writer::tag(
                'button',
                '&#8595;&nbsp;' . get_string('exportcsv', 'block_workload'),
                [
                    'type'                => 'button',
                    'class'               => 'btn btn-outline-success btn-sm',
                    'data-wl-exportmodal' => '1',
                    'data-quick-url'      => $quickurl,
                    'data-detail-url'     => $detailedurl,
                    'data-title'          => get_string('exportchoice', 'block_workload'),
                    'data-label-quick'    => get_string('exportquick', 'block_workload'),
                    'data-label-detail'   => get_string('exportdetailed', 'block_workload'),
                    'data-desc-quick'     => get_string('exportquick_desc', 'block_workload'),
                    'data-desc-detail'    => get_string('exportdetailed_desc', 'block_workload'),
                ]
            ),
            'mb-3 text-end'
        );
    }

    // KPI cards.
    if ($kpis) {
        echo html_writer::start_div('row g-3 mb-4');
        foreach (
            [
            [(string)(int)$kpis->usercount, get_string('students', 'block_workload')],
            [(string)(int)$kpis->coursecount, get_string('courses', 'block_workload')],
            [(string)(int)$kpis->weekcount, get_string('weeksactive', 'block_workload')],
            [number_format((float)$kpis->totalhours, 1), get_string('totalhours', 'block_workload')],
            ] as [$val, $lbl]
        ) {
            echo html_writer::start_div('col-6 col-md-3');
            echo '<div class="card text-center p-3 h-100 shadow-sm wl-kpi-card">';
            echo html_writer::tag(
                'div',
                $val,
                ['class' => 'display-6 fw-bold text-primary lh-1 mb-1']
            );
            echo html_writer::tag('div', $lbl, ['class' => 'text-muted small']);
            echo '</div>';
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }

    // Weekly charts (side by side).
    if ($chartweekavg !== null || $chartweekusers !== null) {
        echo html_writer::start_div('row g-4 mb-4');
        foreach ([$chartweekavg, $chartweekusers] as $wchart) {
            if ($wchart === null) {
                continue;
            }
            echo html_writer::start_div('col-12 col-md-6');
            echo '<div class="wl-chart-card card shadow-sm" style="overflow:hidden;">';
            echo '<div class="card-body p-3">';
            echo $OUTPUT->render($wchart);
            echo '</div></div>';
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }

    // Top students pie chart.
    if ($charttopusers !== null) {
        echo '<div class="wl-chart-card card shadow-sm mb-4" style="overflow:hidden;">';
        echo '<div class="card-body p-3">';
        echo $OUTPUT->render($charttopusers);
        echo '</div></div>';
    }

    // Students table with A-Z filters and pagination.
    $tableheading = get_string('students', 'block_workload');
    if ($totalcount > 0) {
        $tableheading .= ' (' . $totalcount . ')';
    }
    echo html_writer::tag('h5', $tableheading, ['class' => 'mt-2 mb-3']);

    // Base URL for letter-filter and per-page links.
    // These preserve cohort + date range + submitted flag but reset page to 0.
    $filterbase = new moodle_url('/blocks/workload/statistics.php', [
        'cohortid'  => $cohortid,
        'weekfrom'  => $weekfrom, 'yearfrom' => $yearfrom,
        'weekto'    => $weekto, 'yearto'   => $yearto,
        'submitted' => 1,
    ]);

    // A-Z letter bars – same initialbar / pagination markup as manage.php members.
    foreach (
        [
        ['firstinitial', get_string('firstname'), 'firstletter', $firstletter, $lastletter],
        ['lastinitial', get_string('lastname'), 'lastletter', $lastletter, $firstletter],
        ] as [$bartype, $barlabel, $param, $current, $other]
    ) {
        $otherparam = ($param === 'firstletter') ? 'lastletter' : 'firstletter';

        echo '<div class="initialbar ' . $bartype . ' d-flex flex-wrap justify-content-center justify-content-md-start mb-2">';
        echo '<span class="initialbarlabel me-2">' . $barlabel . '</span>';
        echo '<nav class="initialbargroups d-flex flex-wrap justify-content-center justify-content-md-start">';

        // The "All" link in its own UL.
        $u = new moodle_url($filterbase, [$param => '', $otherparam => $other, 'perpage' => $perpage, 'page' => 0]);
        echo '<ul class="pagination pagination-sm">';
        echo '<li id="' . $bartype . '_page-item_All" class="initialbarall page-item' . ($current === '' ? ' active' : '') . '">';
        $ariacurrent = ($current === '') ? ' aria-current="true"' : '';
        echo '<a data-initial="" class="page-link" href="' . $u->out() . '"'
            . $ariacurrent . '>' . get_string('all') . '</a>';
        echo '</li></ul>';

        // Letters from the current language alphabet in a second UL.
        echo '<ul class="pagination pagination-sm">';
        foreach ($alphabet as $l) {
            $u2     = new moodle_url($filterbase, [$param => $l, $otherparam => $other, 'perpage' => $perpage, 'page' => 0]);
            $active = ($current === $l);
            $liclass = 'page-item ' . $l . ($active ? ' active' : '');
            echo '<li id="' . $bartype . '_page-item_' . $l . '" data-initial="' . $l . '"'
                . ' class="' . $liclass . '">';
            $ariacurrentl = $active ? ' aria-current="true"' : '';
            echo '<a class="page-link" href="' . $u2->out() . '"' . $ariacurrentl . '>' . $l . '</a>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</nav></div>';
    }

    // Per-page selector.
    $perpagehtml = html_writer::tag(
        'span',
        get_string('perpage', 'block_workload') . ': ',
        ['class' => 'small fw-semibold me-1']
    );
    foreach ([25 => '25', 50 => '50', 100 => '100', 0 => get_string('showall', 'block_workload', $totalcount)] as $opt => $lbl) {
        $active = ($opt == $perpage);
        $ppparams = [
            'perpage'     => $opt,
            'page'        => 0,
            'firstletter' => $firstletter,
            'lastletter'  => $lastletter,
        ];
        if ($active) {
            $perpagehtml .= html_writer::tag('strong', $lbl, ['class' => 'me-2']);
        } else {
            $perpagehtml .= html_writer::link(
                new moodle_url($filterbase, $ppparams),
                $lbl,
                ['class' => 'me-2 small']
            );
        }
    }
    echo html_writer::div($perpagehtml, 'mb-3 small');

    // Table.
    $viewbase = new moodle_url('/blocks/workload/mystats.php', array_filter([
        'weekfrom' => $weekfrom, 'yearfrom' => $yearfrom,
        'weekto'   => $weekto, 'yearto'   => $yearto,
    ]));

    $table             = new html_table();
    $table->head       = [
        get_string('student', 'block_workload'),
        get_string('email'),
        get_string('totalhours', 'block_workload'),
        get_string('weeksactive', 'block_workload'),
        get_string('averagehours', 'block_workload'),
        '',
    ];
    $table->attributes = ['class' => 'generaltable table-sm table-striped table-hover'];

    if (empty($tableusers)) {
        $emptyrow = new html_table_row();
        $emptycel = new html_table_cell();
        $emptycel->colspan = 6;
        $emptycel->text    = get_string('nouserfound', 'block_workload');
        $emptycel->attributes['class'] = 'text-muted text-center';
        $emptyrow->cells   = [$emptycel];
        $table->data[]     = $emptyrow;
    } else {
        foreach ($tableusers as $r) {
            $avg      = $r->weeksactive > 0
                ? round((float)$r->totalhours / (int)$r->weeksactive, 1)
                : 0;
            $viewurl  = new moodle_url($viewbase, ['viewas' => $r->userid]);
            $profurl  = new moodle_url('/user/view.php', ['id' => $r->userid, 'course' => SITEID]);
            $table->data[] = [
                html_writer::link($profurl, $r->firstname . ' ' . $r->lastname),
                $r->email,
                number_format((float)$r->totalhours, 1),
                (int)$r->weeksactive,
                number_format($avg, 1),
                html_writer::link(
                    $viewurl,
                    get_string('viewstudent', 'block_workload') . ' &rarr;',
                    ['class' => 'btn btn-outline-secondary btn-sm text-nowrap']
                ),
            ];
        }
    }

    echo html_writer::table($table);

    // Paging bar (Moodle built-in, only shown when perpage > 0).
    if ($perpage > 0 && $totalcount > $perpage) {
        $pageurl = new moodle_url($filterbase, [
            'firstletter' => $firstletter,
            'lastletter'  => $lastletter,
            'perpage'     => $perpage,
        ]);
        echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $pageurl);
    }
}

echo $OUTPUT->footer();
