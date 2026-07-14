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
 * Quality Manager – per-course workload target hours ("time required").
 *
 * Targets drive the in-course widget's progress bar. A course without its own
 * target falls back to the site default (block_workload/defaulttargethours).
 * Works in both course modes; the course list follows the active mode.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$catid          = optional_param('catid', 0, PARAM_INT);
$includesubcats = optional_param('includesubcats', 0, PARAM_BOOL);
$action         = optional_param('action', '', PARAM_ALPHA);
$dataformat     = optional_param('dataformat', 'csv', PARAM_ALPHA);
$applyimport    = optional_param('applyimport', 0, PARAM_BOOL);
$search         = optional_param('search', '', PARAM_TEXT);
$page           = optional_param('page', 0, PARAM_INT);
$perpage        = optional_param('perpage', 25, PARAM_INT);

$syscontext = context_system::instance();
require_login();
require_capability('block/workload:manage', $syscontext);

$coursemode = get_config('block_workload', 'coursemode') ?: 'enrollment';

$baseurl = new moodle_url('/blocks/workload/manage_targets.php');

// Set the context before the pre-output handlers below: the export handler
// calls format_string(), which needs a context, and emitting a debugging
// notice there would corrupt the download stream. Setting the context emits
// no output, so download_data()/redirect() stay safe.
$PAGE->set_context($syscontext);
$PAGE->set_url($baseurl);

// Export the course list as a fill-in scaffold: current targets pre-filled,
// blank where none — QMs complete the target_hours column and re-upload.
if ($action === 'export' && confirm_sesskey()) {
    if (!in_array($dataformat, ['csv', 'excel'], true)) {
        $dataformat = 'csv';
    }
    // No limit/offset: the export must hold every course in the current filter,
    // never just the page being viewed.
    $universe = \block_workload\helper::get_targets_course_universe(
        ($coursemode === 'enrollment') ? ($catid ?: null) : null,
        $includesubcats,
        $search
    );
    $targets = \block_workload\helper::get_all_targets();

    $columns = ['course_id', 'shortname', 'fullname', 'target_hours'];
    $rows = array_map(function ($course) use ($targets) {
        $own = isset($targets[$course->id]) ? (float) $targets[$course->id] : 0.0;
        return [
            'course_id'    => (int) $course->id,
            'shortname'    => $course->shortname,
            'fullname'     => format_string($course->fullname),
            'target_hours' => ($own > 0) ? $own : '',
        ];
    }, array_values($universe));

    \core\dataformat::download_data(
        'workload_targets_' . date('Ymd_His'),
        $dataformat,
        $columns,
        $rows
    );
    die;
}

// Apply a confirmed import (payload built by the preview step).
if ($applyimport && confirm_sesskey()) {
    $payload = json_decode(required_param('payload', PARAM_RAW), true);
    if (!is_array($payload)) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    $counts = \block_workload\targets_importer::apply($payload, (int) $USER->id);
    \block_workload\event\course_targets_imported::create([
        'context' => $syscontext,
        'other'   => $counts,
    ])->trigger();
    redirect(
        $baseurl,
        get_string('importapplied', 'block_workload', (object) $counts),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$PAGE->set_pagelayout('admin');
$PAGE->set_url($baseurl, array_filter(['catid' => $catid ?: null]));
$PAGE->navbar->add(
    get_string('managetitle', 'block_workload'),
    new moodle_url('/blocks/workload/manage.php')
);
$PAGE->navbar->add(get_string('coursetargets', 'block_workload'));
$PAGE->set_title(get_string('coursetargets', 'block_workload'));
$PAGE->set_heading(get_string('coursetargets', 'block_workload'));

// Upload form: submitting it renders the validate-and-preview step instead of the list.
$importform = new \block_workload\form\targets_import_form($baseurl->out(false));
if ($importform->get_data()) {
    $content  = $importform->get_file_content('targetsfile');
    $filename = $importform->get_new_filename('targetsfile');
    $parsed   = \block_workload\targets_importer::parse_file((string) $content, (string) $filename);

    if ($parsed['error'] !== null) {
        redirect(
            $baseurl,
            get_string($parsed['error'], 'block_workload'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $result = \block_workload\targets_importer::categorise($parsed['rows']);

    $previewrows = [];
    foreach ($result['rows'] as $row) {
        $previewrows[] = $row + [
            'statuslabel' => get_string('importstatus_' . $row['status'], 'block_workload'),
            // Both class families: badge-* styles under Bootstrap 4 (Moodle 4.5),
            // text-bg-* keeps it correct on Bootstrap 5 themes.
            'badgeclass'  => [
                'new'       => 'badge-success text-bg-success',
                'changed'   => 'badge-primary text-bg-primary',
                'cleared'   => 'badge-warning text-bg-warning',
                'unchanged' => 'badge-secondary text-bg-secondary',
                'unmatched' => 'badge-danger text-bg-danger',
                'invalid'   => 'badge-danger text-bg-danger',
            ][$row['status']],
        ];
    }

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('block_workload/manage_targets_import', [
        'summary'    => get_string('importsummary', 'block_workload', (object) $result['counts']),
        'haschanges' => !empty($result['changes']),
        'nochanges'  => empty($result['changes']),
        'rows'       => $previewrows,
        'payload'    => json_encode($result['changes']),
        'formaction' => $baseurl->out(false),
        'sesskey'    => sesskey(),
        'cancelurl'  => $baseurl->out(false),
    ]);
    echo $OUTPUT->footer();
    exit;
}

// Build the view.

$featuredisabled = !\block_workload\helper::course_widget_enabled();
$defaulttarget   = (float) (get_config('block_workload', 'defaulttargethours') ?: 0);
$targets         = \block_workload\helper::get_all_targets();
$isenrollment    = ($coursemode === 'enrollment');

// Category selector (enrollment mode only — cohort mode lists cohort courses).
$catopts = [];
if ($isenrollment) {
    $rawcatopts = [0 => get_string('allcategories', 'block_workload')]
                + \block_workload\helper::get_category_options();
    foreach ($rawcatopts as $val => $lbl) {
        $catopts[] = ['value' => $val, 'label' => $lbl, 'selected' => ((int) $val === $catid)];
    }
}

$offset = ($perpage > 0) ? $page * $perpage : 0;
$total  = \block_workload\helper::get_targets_course_universe_count(
    $isenrollment ? ($catid ?: null) : null,
    $includesubcats,
    $search
);
$universe = \block_workload\helper::get_targets_course_universe(
    $isenrollment ? ($catid ?: null) : null,
    $includesubcats,
    $search,
    $perpage,
    $offset
);

$sourcecourse  = get_string('targetsource_course', 'block_workload');
$sourcedefault = get_string('targetsource_default', 'block_workload');
$sourcenone    = get_string('targetsource_none', 'block_workload');

$rows = [];
foreach ($universe as $course) {
    $own = isset($targets[$course->id]) ? (float) $targets[$course->id] : 0.0;
    if ($own > 0) {
        $effectivetext = number_format($own, 1) . ' h (' . $sourcecourse . ')';
    } else if ($defaulttarget > 0) {
        $effectivetext = number_format($defaulttarget, 1) . ' h (' . $sourcedefault . ')';
    } else {
        $effectivetext = $sourcenone;
    }
    $rows[] = [
        'courseid'      => (int) $course->id,
        'name'          => format_string($course->fullname),
        'shortname'     => $course->shortname,
        'courseurl'     => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'target'        => ($own > 0) ? rtrim(rtrim(number_format($own, 1, '.', ''), '0'), '.') : '',
        'effectivetext' => $effectivetext,
    ];
}

// Filter state carried by every paging/per-page link.
$filterparams = array_filter([
    'catid'          => $catid ?: null,
    'includesubcats' => $includesubcats ?: null,
    'search'         => ($search !== '') ? $search : null,
]);

$pagingurl  = new moodle_url($baseurl, $filterparams + ['perpage' => $perpage]);
$showpaging = ($perpage > 0 && $total > $perpage);
$pagingbar  = $showpaging ? $OUTPUT->paging_bar($total, $page, $perpage, $pagingurl) : '';

// Per-page selector (mirrors manage_enrollment.php).
$perpageopts = \block_workload\helper::build_perpage_options($baseurl, $filterparams, $perpage, $total);

$templatecontext = [
    'backurl'         => (new moodle_url(
        $isenrollment ? '/blocks/workload/manage_enrollment.php' : '/blocks/workload/manage.php'
    ))->out(false),
    'backlabel'       => get_string('backtomanage', 'block_workload'),
    'sesskey'         => sesskey(),
    'featuredisabled' => $featuredisabled,
    'hasdefault'      => ($defaulttarget > 0),
    'defaultinfo'     => ($defaulttarget > 0)
        ? get_string('defaulttargetinfo', 'block_workload', number_format($defaulttarget, 1))
        : get_string('defaulttargetnone', 'block_workload'),
    'isenrollment'    => $isenrollment,
    'catformaction'   => $baseurl->out(false),
    'catopts'         => array_values($catopts),
    'selectedcatid'   => $catid,
    'includesubcats'  => $includesubcats,
    'coursecount'     => $total,
    'search'          => $search,
    'pagingbartop'    => $pagingbar,
    'pagingbarbottom' => $pagingbar,
    'perpagestr'      => get_string('perpage', 'block_workload'),
    'perpageopts'     => array_values($perpageopts),
    'hascourses'      => !empty($rows),
    'rows'            => array_values($rows),
    'exportcsvurl'   => (new moodle_url($baseurl, [
        'action' => 'export', 'dataformat' => 'csv', 'catid' => $catid,
        'includesubcats' => $includesubcats, 'search' => $search, 'sesskey' => sesskey(),
    ]))->out(false),
    'exportxlsxurl'  => (new moodle_url($baseurl, [
        'action' => 'export', 'dataformat' => 'excel', 'catid' => $catid,
        'includesubcats' => $includesubcats, 'search' => $search, 'sesskey' => sesskey(),
    ]))->out(false),
    'importformhtml' => $importform->render(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_workload/manage_targets', $templatecontext);
$PAGE->requires->js_call_amd('block_workload/managetargets', 'init', [[
    'sourcelabels' => [
        'course'  => $sourcecourse,
        'default' => $sourcedefault,
        'none'    => $sourcenone,
    ],
    'nonelabel'    => $sourcenone,
]]);
echo $OUTPUT->footer();
