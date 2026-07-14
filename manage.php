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
 * Quality Manager – cohort management page.
 *
 * Actions:
 *   list       – overview of all cohorts
 *   add/edit   – create or update a cohort
 *   delete     – delete a cohort (with confirmation)
 *   members    – manage students in a cohort
 *   courses    – manage courses assigned to a cohort
 *   activation – set active period for a cohort
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');

$action  = optional_param('action', 'list', PARAM_ALPHA);
$id      = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$syscontext = context_system::instance();
require_login();
require_capability('block/workload:manage', $syscontext);

// In enrollment mode the entire cohort management is disabled.
if ((get_config('block_workload', 'coursemode') ?: 'enrollment') === 'enrollment') {
    redirect(new moodle_url('/blocks/workload/manage_enrollment.php'));
}

$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managetitle', 'block_workload'));
$PAGE->set_heading(get_string('managetitle', 'block_workload'));
$PAGE->set_url('/blocks/workload/manage.php', ['action' => $action, 'id' => $id]);

// Modal-done: minimal response telling the parent frame to close the modal and
// reload. Rendered without header/footer — see templates/modal_done.mustache.
if ($action === 'modaldone') {
    echo $OUTPUT->render_from_template('block_workload/modal_done', []);
    exit;
}

$PAGE->navbar->add(get_string('managetitle', 'block_workload'), new moodle_url('/blocks/workload/manage.php'));

// Router.
switch ($action) {
    case 'add':
    case 'edit':
        block_workload_action_cohort_form($action, $id);
        break;

    case 'delete':
        block_workload_action_delete($id, $confirm);
        break;

    case 'members':
        block_workload_action_members($id);
        break;

    case 'courses':
        block_workload_action_courses($id);
        break;

    case 'activation':
        block_workload_action_activation($id);
        break;

    case 'togglecohort':
        block_workload_action_toggle_cohort($id);
        break;

    default:
        block_workload_action_list();
        break;
}

// Helper: build select-option arrays for Mustache templates.

/**
 * Convert a flat associative array (value => label) into the option-array
 * shape used by the Mustache templates: [{value, text, selected}].
 *
 * @param array  $opts    value => label map
 * @param mixed  $current currently selected value
 * @return array
 */
function block_workload_build_select_opts(array $opts, $current): array {
    $out = [];
    foreach ($opts as $val => $label) {
        $out[] = [
            'value'    => (string)$val,
            'text'     => $label,
            'selected' => ((string)$val === (string)$current),
        ];
    }
    return $out;
}

/**
 * Convert a list of users (stdClass with fullname fields) into the row
 * shape used by both the search-results and Moodle-import user tables.
 *
 * @param array $users         Indexed by user id.
 * @param array $allmemberids  Already-enrolled user ids (for the ✓ indicator).
 * @return array
 */
function block_workload_build_user_rows(array $users, array $allmemberids): array {
    $rows = [];
    foreach ($users as $u) {
        $rows[] = [
            'id'         => $u->id,
            'fullname'   => fullname($u),
            'email'      => $u->email,
            'department' => $u->department ?? '',
            'institution' => $u->institution ?? '',
            'already'    => in_array((int)$u->id, $allmemberids, true),
        ];
    }
    return $rows;
}

// Action: toggle cohort active flag (no output – redirect only).

/**
 * Toggle a cohort's active flag.
 *
 * @param int $id
 */
function block_workload_action_toggle_cohort(int $id): void {
    global $DB;
    require_sesskey();
    $cohort = $DB->get_record('block_workload_cohorts', ['id' => $id], '*', MUST_EXIST);
    $cohort->active       = $cohort->active ? 0 : 1;
    $cohort->timemodified = time();
    $DB->update_record('block_workload_cohorts', $cohort);

    \block_workload\event\cohort_updated::create([
        'objectid' => $cohort->id,
        'context'  => context_system::instance(),
        'other'    => ['name' => $cohort->name],
    ])->trigger();

    redirect(new moodle_url('/blocks/workload/manage.php'));
}

// Action: cohort list.

/**
 * Render the cohort list page.
 */
function block_workload_action_list(): void {
    global $DB, $OUTPUT, $PAGE;

    $PAGE->requires->js_call_amd('block_workload/manage', 'initList', []);

    $cohorts = $DB->get_records('block_workload_cohorts', null, 'timecreated DESC');

    // Preload per-cohort counts in two grouped queries instead of two queries per cohort.
    $membercounts = $DB->get_records_sql_menu(
        'SELECT cohortid, COUNT(1) FROM {block_workload_members} GROUP BY cohortid'
    );
    $coursecounts = $DB->get_records_sql_menu(
        'SELECT cohortid, COUNT(1) FROM {block_workload_courses} WHERE active = 1 GROUP BY cohortid'
    );

    $cohortrows = [];
    foreach ($cohorts as $cohort) {
        $membercount = (int)($membercounts[$cohort->id] ?? 0);
        $coursecount = (int)($coursecounts[$cohort->id] ?? 0);

        $cohortrows[] = [
            'id'             => $cohort->id,
            'name'           => format_string($cohort->name),
            'degree_program' => format_string($cohort->degree_program),
            'department'     => format_string($cohort->department),
            'active'         => (bool)$cohort->active,
            'activelabel'    => $cohort->active
                ? get_string('active', 'block_workload')
                : get_string('inactive', 'block_workload'),
            'membercount'    => $membercount,
            'coursecount'    => $coursecount,
            'memberurl'      => (new moodle_url(
                '/blocks/workload/manage.php',
                ['action' => 'members', 'id' => $cohort->id]
            ))->out(false),
            'courseurl'      => (new moodle_url(
                '/blocks/workload/manage.php',
                ['action' => 'courses', 'id' => $cohort->id]
            ))->out(false),
            'delurl'         => (new moodle_url(
                '/blocks/workload/manage.php',
                ['action' => 'delete', 'id' => $cohort->id]
            ))->out(false),
            'editurl'        => (new moodle_url(
                '/blocks/workload/manage.php',
                ['action' => 'edit', 'id' => $cohort->id, 'modal' => 1]
            ))->out(false),
            'activurl'       => (new moodle_url(
                '/blocks/workload/manage.php',
                ['action' => 'activation', 'id' => $cohort->id, 'modal' => 1]
            ))->out(false),
            'toggleurl'      => (new moodle_url(
                '/blocks/workload/manage.php',
                ['action' => 'togglecohort', 'id' => $cohort->id,
                'sesskey' => sesskey()]
            ))->out(false),
        ];
    }

    $ctx = [
        'addurl'        => (new moodle_url(
            '/blocks/workload/manage.php',
            ['action' => 'add', 'modal' => 1]
        ))->out(false),
        'statisticsurl' => (new moodle_url('/blocks/workload/statistics.php'))->out(false),
        'targetsurl'    => (new moodle_url('/blocks/workload/manage_targets.php'))->out(false),
        'manageurl'     => (new moodle_url('/blocks/workload/manage.php'))->out(false),
        'sesskey'       => sesskey(),
        'hascohorts'    => !empty($cohorts),
        'cohorts'       => $cohortrows,
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('cohorts', 'block_workload'));
    echo $OUTPUT->render_from_template('block_workload/manage_list', $ctx);
    echo $OUTPUT->footer();
}

// Action: create / edit cohort form (already uses Output API – unchanged).

/**
 * Handle the create/edit cohort form.
 *
 * @param string $action
 * @param int    $id
 */
function block_workload_action_cohort_form(string $action, int $id): void {
    global $DB, $OUTPUT, $PAGE;

    $modal  = optional_param('modal', 0, PARAM_BOOL);
    $cohort = ($action === 'edit' && $id)
        ? $DB->get_record('block_workload_cohorts', ['id' => $id], '*', MUST_EXIST)
        : null;

    if ($modal) {
        $PAGE->set_pagelayout('embedded');
    }

    $formurl = new moodle_url(
        '/blocks/workload/manage.php',
        ['action' => $action, 'id' => $id, 'modal' => (int)$modal]
    );
    $form = new \block_workload\form\cohort_form(
        $formurl,
        ['cohort' => $cohort, 'departments' => \block_workload\helper::get_distinct_cohort_departments()]
    );

    if ($cohort) {
        $form->set_data($cohort);
    }

    if ($form->is_cancelled()) {
        if ($modal) {
            redirect(new moodle_url('/blocks/workload/manage.php', ['action' => 'modaldone']));
        }
        redirect(new moodle_url('/blocks/workload/manage.php'));
    }

    if ($data = $form->get_data()) {
        $now = time();
        if ($cohort) {
            $cohort->name           = trim($data->name);
            $cohort->degree_program = trim($data->degree_program);
            $cohort->department     = trim($data->department ?? '');
            $cohort->description    = trim($data->description ?? '');
            $cohort->active         = (int) $data->active;
            $cohort->timemodified   = $now;
            $DB->update_record('block_workload_cohorts', $cohort);

            \block_workload\event\cohort_updated::create([
                'objectid' => $cohort->id,
                'context'  => context_system::instance(),
                'other'    => ['name' => $cohort->name],
            ])->trigger();
        } else {
            $new                 = new stdClass();
            $new->name           = trim($data->name);
            $new->degree_program = trim($data->degree_program);
            $new->department     = trim($data->department ?? '');
            $new->description    = trim($data->description ?? '');
            $new->active         = (int) $data->active;
            $new->week_from      = null;
            $new->year_from      = null;
            $new->week_to        = null;
            $new->year_to        = null;
            $new->createdby      = $GLOBALS['USER']->id;
            $new->timecreated    = $now;
            $new->timemodified   = $now;
            $newid = $DB->insert_record('block_workload_cohorts', $new);

            \block_workload\event\cohort_created::create([
                'objectid' => $newid,
                'context'  => context_system::instance(),
                'other'    => ['name' => $new->name],
            ])->trigger();
        }

        if ($modal) {
            \core\notification::success(get_string('cohortsaved', 'block_workload'));
            redirect(new moodle_url('/blocks/workload/manage.php', ['action' => 'modaldone']));
        }
        redirect(
            new moodle_url('/blocks/workload/manage.php'),
            get_string('cohortsaved', 'block_workload'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    if (!$modal) {
        echo $OUTPUT->heading($action === 'edit'
            ? get_string('editcohort', 'block_workload')
            : get_string('addcohort', 'block_workload'));
    }
    $form->display();
    echo $OUTPUT->footer();
}

// Action: delete cohort (already uses Output API – unchanged).

/**
 * Handle cohort deletion.
 *
 * @param int  $id
 * @param bool $confirmed
 */
function block_workload_action_delete(int $id, bool $confirmed): void {
    global $DB, $OUTPUT;

    $cohort = $DB->get_record('block_workload_cohorts', ['id' => $id], '*', MUST_EXIST);

    if ($confirmed && confirm_sesskey()) {
        $DB->delete_records('block_workload_members', ['cohortid' => $id]);
        $DB->delete_records('block_workload_courses', ['cohortid' => $id]);
        $DB->delete_records('block_workload_entries', ['cohortid' => $id]);
        $DB->delete_records('block_workload_cohorts', ['id'       => $id]);

        \block_workload\event\cohort_deleted::create([
            'objectid' => $id,
            'context'  => context_system::instance(),
            'other'    => ['name' => $cohort->name],
        ])->trigger();

        redirect(
            new moodle_url('/blocks/workload/manage.php'),
            get_string('cohortdeleted', 'block_workload', format_string($cohort->name)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deletecohort', 'block_workload'));
    echo $OUTPUT->confirm(
        get_string('confirmdelete', 'block_workload', format_string($cohort->name)),
        new moodle_url(
            '/blocks/workload/manage.php',
            ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]
        ),
        new moodle_url('/blocks/workload/manage.php')
    );
    echo $OUTPUT->footer();
}

// Action: manage members.

/**
 * Render the manage-members page.
 *
 * @param int $id Cohort id.
 */
function block_workload_action_members(int $id): void {
    global $DB, $OUTPUT, $PAGE;

    $cohort  = $DB->get_record('block_workload_cohorts', ['id' => $id], '*', MUST_EXIST);
    $baseurl = new moodle_url('/blocks/workload/manage.php', ['action' => 'members', 'id' => $id]);

    // Paging & filter params.
    $page    = optional_param('page', 0, PARAM_INT);
    $perpage = optional_param('perpage', 25, PARAM_INT);

    $alphabet    = explode(',', get_string('alphabet', 'langconfig'));
    $firstletter = optional_param('firstletter', '', PARAM_RAW);
    $lastletter  = optional_param('lastletter', '', PARAM_RAW);
    $firstletter = (in_array($firstletter, $alphabet, true)) ? $firstletter : '';
    $lastletter  = (in_array($lastletter, $alphabet, true)) ? $lastletter : '';

    // Current-members table column sorting.
    $sortmap = [
        'fullname'    => ['u.lastname', 'u.firstname'],
        'email'       => ['u.email'],
        'department'  => ['u.department'],
        'institution' => ['u.institution'],
    ];
    $sort = optional_param('sort', 'fullname', PARAM_ALPHA);
    $sort = array_key_exists($sort, $sortmap) ? $sort : 'fullname';
    $dir  = optional_param('dir', 'asc', PARAM_ALPHA);
    $dir  = ($dir === 'desc') ? 'desc' : 'asc';
    $sqldir  = ($dir === 'desc') ? 'DESC' : 'ASC';
    $orderby = implode(', ', array_map(fn($field) => "$field $sqldir", $sortmap[$sort]));

    $search            = optional_param('search', '', PARAM_TEXT);
    $filterdepartment  = optional_param('filterdepartment', '', PARAM_TEXT);
    $filterinstitution = optional_param('filterinstitution', '', PARAM_TEXT);
    $filtercategory    = optional_param('filtercategory', 0, PARAM_INT);
    $moodlecohortid    = optional_param('moodlecohortid', 0, PARAM_INT);
    $importenabled     = (bool) get_config('block_workload', 'enablemoodlecohortimport');

    $hassearch = ($search !== '' || $filterdepartment !== '' || $filterinstitution !== '' || $filtercategory > 0);
    $panelopen = $hassearch || ($importenabled && $moodlecohortid > 0);

    // Confirmation / mutation handlers (unchanged logic).

    // Single-remove: show confirmation page.
    $confirmremoveid = optional_param('confirmremoveid', 0, PARAM_INT);
    if ($confirmremoveid) {
        $member = $DB->get_record(
            'user',
            ['id' => $confirmremoveid],
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename',
            MUST_EXIST
        );
        $yesurl = new moodle_url('/blocks/workload/manage.php', [
            'action'      => 'members', 'id' => $id,
            'removeid'    => $confirmremoveid, 'sesskey' => sesskey(),
            'firstletter' => $firstletter, 'lastletter' => $lastletter,
            'page'        => $page, 'perpage' => $perpage,
            'sort'        => $sort, 'dir' => $dir,
        ]);
        $nourl = new moodle_url('/blocks/workload/manage.php', ['action' => 'members', 'id' => $id]);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('memberstitle', 'block_workload', format_string($cohort->name)));
        echo $OUTPUT->confirm(get_string('confirmsingleremove', 'block_workload', fullname($member)), $yesurl, $nourl);
        echo $OUTPUT->footer();
        return;
    }

    // Single-remove: execute.
    $removeid = optional_param('removeid', 0, PARAM_INT);
    if ($removeid && confirm_sesskey()) {
        $DB->delete_records('block_workload_members', ['cohortid' => $id, 'userid' => (int)$removeid]);
        \block_workload\event\members_removed::create([
            'objectid' => $id, 'context' => context_system::instance(),
            'other'    => ['cohortname' => $cohort->name, 'count' => 1],
        ])->trigger();
        redirect(
            $baseurl,
            get_string('membersremoved', 'block_workload', 1),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Bulk-remove: execute confirmed.
    $confirmbulkids = optional_param('confirmbulkids', '', PARAM_SEQUENCE);
    $dobulkremove   = optional_param('dobulkremove', 0, PARAM_BOOL);
    if ($confirmbulkids !== '' && $dobulkremove && confirm_sesskey()) {
        $ids = array_filter(array_map('intval', explode(',', $confirmbulkids)));
        $removed = count($ids);
        if ($removed > 0) {
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $DB->delete_records_select(
                'block_workload_members',
                "cohortid = :cohortid AND userid $insql",
                $inparams + ['cohortid' => $id]
            );
        }
        \block_workload\event\members_removed::create([
            'objectid' => $id, 'context' => context_system::instance(),
            'other'    => ['cohortname' => $cohort->name, 'count' => $removed],
        ])->trigger();
        redirect(
            $baseurl,
            get_string('membersremoved', 'block_workload', $removed),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Bulk-remove: show confirmation page.
    if ($confirmbulkids !== '' && !$dobulkremove) {
        $ids    = array_filter(array_map('intval', explode(',', $confirmbulkids)));
        $yesurl = new moodle_url('/blocks/workload/manage.php', [
            'action'         => 'members', 'id' => $id,
            'confirmbulkids' => $confirmbulkids, 'dobulkremove' => 1, 'sesskey' => sesskey(),
        ]);
        $nourl = new moodle_url('/blocks/workload/manage.php', ['action' => 'members', 'id' => $id]);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('memberstitle', 'block_workload', format_string($cohort->name)));
        echo $OUTPUT->confirm(get_string('confirmbulkremove', 'block_workload', count($ids)), $yesurl, $nourl);
        echo $OUTPUT->footer();
        return;
    }

    // Bulk-remove: intercept POST → redirect to confirmation.
    $removeusers = optional_param_array('removeusers', [], PARAM_INT);
    if (!empty($removeusers)) {
        $idsstring = implode(',', array_filter(array_map('intval', $removeusers)));
        redirect(new moodle_url(
            '/blocks/workload/manage.php',
            ['action' => 'members', 'id' => $id, 'confirmbulkids' => $idsstring]
        ));
    }

    // Add members (POST).
    $addusers = optional_param_array('addusers', [], PARAM_INT);
    if (!empty($addusers) && confirm_sesskey()) {
        $adduserids = array_unique(array_map('intval', $addusers));

        // One query to find users already in the cohort, then a single batch insert.
        [$insql, $inparams] = $DB->get_in_or_equal($adduserids, SQL_PARAMS_NAMED);
        $existing = $DB->get_records_select_menu(
            'block_workload_members',
            "cohortid = :cohortid AND userid $insql",
            $inparams + ['cohortid' => $id],
            '',
            'userid, id'
        );
        $now     = time();
        $records = [];
        foreach ($adduserids as $uid) {
            if (!isset($existing[$uid])) {
                $records[] = (object)[
                    'cohortid'    => $id,
                    'userid'      => $uid,
                    'timecreated' => $now,
                ];
            }
        }
        $added = count($records);
        if ($added > 0) {
            $transaction = $DB->start_delegated_transaction();
            $DB->insert_records('block_workload_members', $records);
            $transaction->allow_commit();

            \block_workload\event\members_added::create([
                'objectid' => $id, 'context' => context_system::instance(),
                'other'    => ['cohortname' => $cohort->name, 'count' => $added],
            ])->trigger();
        }
        redirect(
            $baseurl,
            get_string('membersadded', 'block_workload', $added),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Build template context.

    $PAGE->requires->js_call_amd('block_workload/manage', 'initMembers', []);
    $PAGE->requires->js_call_amd('block_workload/toggleall', 'init');

    // Filter option lists.
    $deptopts = ['' => get_string('selectdepartment', 'block_workload')]
              + array_combine(
                  \block_workload\helper::get_distinct_departments(),
                  \block_workload\helper::get_distinct_departments()
              );
    $instopts = ['' => get_string('selectinstitution', 'block_workload')]
              + array_combine(
                  \block_workload\helper::get_distinct_institutions(),
                  \block_workload\helper::get_distinct_institutions()
              );
    $catopts  = [0 => get_string('selectcategory', 'block_workload')]
              + \block_workload\helper::get_category_options();

    // Members data.
    $totalcount   = \block_workload\helper::get_cohort_members_count($id, $firstletter, $lastletter);
    $limit        = ($perpage > 0) ? $perpage : 0;
    $offset       = ($perpage > 0) ? $page * $perpage : 0;
    $members      = \block_workload\helper::get_cohort_members($id, $limit, $offset, $firstletter, $lastletter, $orderby);
    $allmemberids = \block_workload\helper::get_all_cohort_member_ids($id);

    // Search results.
    $found      = [];
    $nouserfound = false;
    if ($hassearch) {
        $found       = \block_workload\helper::search_users_for_member_add(
            $search,
            $filterdepartment,
            $filterinstitution,
            $filtercategory
        );
        $nouserfound = empty($found);
    }

    // Moodle cohort members.
    $moodlecohorts = $importenabled
        ? $DB->get_records('cohort', null, 'name ASC', 'id, name, idnumber')
        : [];
    $moodlemembers = [];
    $hasmoodlecohortsection = false;
    if ($importenabled && $moodlecohortid > 0 && array_key_exists($moodlecohortid, $moodlecohorts)) {
        $moodlemembers          = \block_workload\helper::get_moodle_cohort_members($moodlecohortid);
        $hasmoodlecohortsection = true;
    }

    // A-Z initial bars.
    $basealphaurl = new moodle_url(
        '/blocks/workload/manage.php',
        ['action' => 'members', 'id' => $id, 'perpage' => $perpage, 'page' => 0,
         'sort' => $sort, 'dir' => $dir]
    );
    $alphabars = [];
    foreach (
        [
            ['firstinitial', get_string('firstname'), 'firstletter', $firstletter, $lastletter],
            ['lastinitial', get_string('lastname'), 'lastletter', $lastletter, $firstletter],
        ] as [$bartype, $barlabel, $param, $current, $other]
    ) {
        $otherparam = ($param === 'firstletter') ? 'lastletter' : 'firstletter';
        $allurl     = clone $basealphaurl;
        $allurl->param($param, '');
        $allurl->param($otherparam, $other);

        $letters = [];
        foreach ($alphabet as $l) {
            $lu = clone $basealphaurl;
            $lu->param($param, $l);
            $lu->param($otherparam, $other);
            $letters[] = [
                'letter'  => $l,
                'url'     => $lu->out(false),
                'active'  => ($current === $l),
                'liclass' => 'page-item ' . $l . ($current === $l ? ' active' : ''),
            ];
        }

        $alphabars[] = [
            'bartype'   => $bartype,
            'barlabel'  => $barlabel,
            'allurl'    => $allurl->out(false),
            'allactive' => ($current === ''),
            'letters'   => $letters,
        ];
    }

    // Per-page switcher links.
    $pplinks   = [];
    $ppvalues  = [25, 50, 100, 0]; // 0 = show all
    $lastppidx = count($ppvalues) - 1;
    foreach ($ppvalues as $i => $pp) {
        $isall      = ($pp === 0);
        $iscurrent  = ($pp == $perpage);
        $label      = $isall
            ? get_string('showall', 'block_workload', $totalcount)
            : (string)$pp;
        $pplinks[] = [
            'label'     => $label,
            'isbold'    => $iscurrent,
            'islink'    => !$iscurrent,
            'url'       => !$iscurrent
                ? (new moodle_url('/blocks/workload/manage.php', [
                    'action' => 'members', 'id' => $id, 'perpage' => $pp, 'page' => 0,
                    'firstletter' => $firstletter, 'lastletter' => $lastletter,
                    'sort' => $sort, 'dir' => $dir,
                  ]))->out(false)
                : null,
            'separator' => ($i < $lastppidx),
        ];
    }

    // Paging bars (rendered HTML passed as raw).
    $pagingurl = new moodle_url(
        '/blocks/workload/manage.php',
        ['action' => 'members', 'id' => $id, 'perpage' => $perpage,
        'firstletter' => $firstletter,
        'lastletter' => $lastletter,
        'sort' => $sort, 'dir' => $dir]
    );
    $showpaging      = ($perpage > 0 && $totalcount > $perpage);
    $pagingbartop   = $showpaging ? $OUTPUT->paging_bar($totalcount, $page, $perpage, $pagingurl) : '';
    $pagingbarbottom = $showpaging ? $OUTPUT->paging_bar($totalcount, $page, $perpage, $pagingurl) : '';

    // Sortable headers for the current-members table.
    $sortlabels = [
        'fullname'    => get_string('fullnameuser'),
        'email'       => get_string('email'),
        'department'  => get_string('department'),
        'institution' => get_string('institution'),
    ];
    $sortheaders = [];
    foreach ($sortlabels as $sortkey => $sortlabel) {
        $active  = ($sort === $sortkey);
        $nextdir = ($active && $dir === 'asc') ? 'desc' : 'asc';
        $sorturl = new moodle_url('/blocks/workload/manage.php', [
            'action' => 'members', 'id' => $id,
            'firstletter' => $firstletter, 'lastletter' => $lastletter,
            'page' => $page, 'perpage' => $perpage,
            'sort' => $sortkey, 'dir' => $nextdir,
        ]);
        $sortheaders[] = [
            'label'    => $sortlabel,
            'url'      => $sorturl->out(false),
            'sortedby' => get_accesshide(get_string('sortby') . ' ' . $sortlabel . ' ' . get_string($nextdir)),
            'iconhtml' => $active
                ? $OUTPUT->pix_icon($dir === 'asc' ? 't/sort_asc' : 't/sort_desc', get_string($dir))
                : '',
        ];
    }

    // Member rows for the current-members table.
    $memberrows = [];
    foreach ($members as $m) {
        $removeurl  = new moodle_url('/blocks/workload/manage.php', [
            'action' => 'members', 'id' => $id, 'confirmremoveid' => $m->id,
            'firstletter' => $firstletter, 'lastletter' => $lastletter,
            'page' => $page, 'perpage' => $perpage,
            'sort' => $sort, 'dir' => $dir,
        ]);
        $memberrows[] = [
            'id'          => $m->id,
            'fullname'    => fullname($m),
            'email'       => $m->email,
            'department'  => $m->department,
            'institution' => $m->institution,
            'removeurl'   => $removeurl->out(false),
        ];
    }

    // Search results info string.
    $searchresultinfo = '';
    if ($hassearch && !$nouserfound) {
        $alreadycount     = count(array_filter($found, fn($u) => in_array((int)$u->id, $allmemberids, true)));
        $searchresultinfo = get_string('searchresultcount', 'block_workload', count($found));
        if ($alreadycount) {
            $searchresultinfo .= ', ' . get_string('alreadymembercount', 'block_workload', $alreadycount);
        }
        if (count($found) >= 200) {
            $searchresultinfo .= ' ' . get_string('searchresultlimit', 'block_workload');
        }
    }

    // Moodle cohort import info string.
    $moodleimportinfo = '';
    if ($hasmoodlecohortsection && !empty($moodlemembers)) {
        $alreadycount     = count(array_filter($moodlemembers, fn($u) => in_array((int)$u->id, $allmemberids, true)));
        $moodleimportinfo = get_string('moodlecohortmembers', 'block_workload', count($moodlemembers));
        if ($alreadycount) {
            $moodleimportinfo .= ', ' . get_string('alreadymembercount', 'block_workload', $alreadycount);
        }
    }

    // Moodle cohort picker options.
    $moodlecohortopts = [['value' => '0', 'text' => get_string('selectmoodlecohort', 'block_workload'),
                           'selected' => ($moodlecohortid === 0)]];
    foreach ($moodlecohorts as $mc) {
        $label = format_string($mc->name);
        if ($mc->idnumber !== '') {
            $label .= ' [' . s($mc->idnumber) . ']';
        }
        $moodlecohortopts[] = [
            'value'    => (string)$mc->id,
            'text'     => $label,
            'selected' => ($mc->id == $moodlecohortid),
        ];
    }

    // Panel button label.
    $lblopen  = '+ '        . get_string('addmembers', 'block_workload');
    $lblclose = "\xc3\x97 " . get_string('closeaddpanel', 'block_workload');

    $ctx = [
        'backurl'         => (new moodle_url('/blocks/workload/manage.php'))->out(false),
        'cohortid'        => $id,
        'sesskey'         => sesskey(),
        'page'            => $page,
        'perpage'         => $perpage,
        'panelopen'       => $panelopen,
        'panelstyle'      => $panelopen ? 'display:block;' : 'display:none;',
        'panelopen_btnlabel' => $panelopen ? $lblclose : $lblopen,
        'lblopen'         => $lblopen,
        'lblclose'        => $lblclose,

        // Filter form.
        'search'           => $search,
        'filterdepartment' => $filterdepartment,
        'filterinstitution' => $filterinstitution,
        'filtercategory'   => $filtercategory,
        'clearfiltersurl'  => (new moodle_url(
            '/blocks/workload/manage.php',
            ['action' => 'members', 'id' => $id]
        ))->out(false),
        'deptopts'         => block_workload_build_select_opts($deptopts, $filterdepartment),
        'instopts'         => block_workload_build_select_opts($instopts, $filterinstitution),
        'catopts'          => block_workload_build_select_opts($catopts, $filtercategory),
        'filtercategoryhelp' => $OUTPUT->help_icon('filterbycategory', 'block_workload'),

        // Search results.
        'hassearch'        => $hassearch,
        'nouserfound'      => $nouserfound,
        'searchresultinfo' => $searchresultinfo,
        'searchrows'       => $hassearch ? block_workload_build_user_rows($found, $allmemberids) : [],

        // Moodle cohort import.
        'importenabled'          => $importenabled,
        'nomoodlecohorts'        => $importenabled && empty($moodlecohorts),
        'moodlecohortopts'       => $moodlecohortopts,
        'hasmoodlecohortsection' => $hasmoodlecohortsection,
        'nomoodlemembers'        => $hasmoodlecohortsection && empty($moodlemembers),
        'moodleimportinfo'       => $moodleimportinfo,
        'moodlerows'             => $hasmoodlecohortsection
                                        ? block_workload_build_user_rows($moodlemembers, $allmemberids)
                                        : [],

        // A-Z bars + per-page switcher.
        'alphabars'        => $alphabars,
        'perpagetext'      => get_string('perpage', 'block_workload'),
        'pplinks'          => $pplinks,

        // Paging bars (pre-rendered HTML).
        'pagingbartop'    => $pagingbartop,
        'pagingbarbottom' => $pagingbarbottom,

        // Current-members table.
        'hasmembers'   => ($totalcount > 0),
        'sortheaders'  => $sortheaders,
        'memberrows'   => $memberrows,
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('memberstitle', 'block_workload', format_string($cohort->name)));
    echo $OUTPUT->render_from_template('block_workload/manage_members', $ctx);
    echo $OUTPUT->footer();
}

// Action: manage courses.

/**
 * Render the manage-courses page.
 *
 * @param int $id Cohort id.
 */
function block_workload_action_courses(int $id): void {
    global $DB, $OUTPUT, $PAGE;

    $cohort = $DB->get_record('block_workload_cohorts', ['id' => $id], '*', MUST_EXIST);

    // Mutation handlers (unchanged logic).

    // Add-courses browser state. Read up front so the action handlers below can
    // preserve it when they redirect. The assigned table is deliberately unpaged —
    // it is curated per cohort, whereas this browser can match every course on the
    // site once a search is used.
    $selectedcategory = optional_param('categoryid', 0, PARAM_INT);
    $includesubcats   = optional_param('includesubcats', 0, PARAM_BOOL);
    $search           = optional_param('search', '', PARAM_TEXT);
    $page             = optional_param('page', 0, PARAM_INT);
    $perpage          = optional_param('perpage', 25, PARAM_INT);

    // Single remove: show confirmation.
    $confirmremoveid = optional_param('confirmremoveid', 0, PARAM_INT);
    if ($confirmremoveid) {
        $rec    = $DB->get_record(
            'block_workload_courses',
            ['id' => $confirmremoveid, 'cohortid' => $id],
            '*',
            MUST_EXIST
        );
        $course = $DB->get_record('course', ['id' => $rec->courseid], 'id, fullname', MUST_EXIST);
        $yesurl = new moodle_url(
            '/blocks/workload/manage.php',
            ['action' => 'courses', 'id' => $id, 'removeid' => $confirmremoveid, 'sesskey' => sesskey()]
        );
        $nourl  = new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('coursestitle', 'block_workload', format_string($cohort->name)));
        echo $OUTPUT->confirm(
            get_string('confirmsingleremovecourse', 'block_workload', format_string($course->fullname)),
            $yesurl,
            $nourl
        );
        echo $OUTPUT->footer();
        return;
    }

    // Single remove: execute.
    $removeid = optional_param('removeid', 0, PARAM_INT);
    if ($removeid && confirm_sesskey()) {
        $DB->delete_records('block_workload_courses', ['id' => $removeid, 'cohortid' => $id]);
        \block_workload\event\courses_removed::create([
            'objectid' => $id, 'context' => context_system::instance(),
            'other'    => ['cohortname' => $cohort->name, 'count' => 1],
        ])->trigger();
        redirect(
            new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]),
            get_string('courseremoved', 'block_workload'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Bulk remove: execute confirmed.
    $confirmbulkids = optional_param('confirmbulkids', '', PARAM_SEQUENCE);
    $dobulkremove   = optional_param('dobulkremove', 0, PARAM_BOOL);
    if ($confirmbulkids !== '' && $dobulkremove && confirm_sesskey()) {
        $ids = array_filter(array_map('intval', explode(',', $confirmbulkids)));
        $removed = count($ids);
        if ($removed > 0) {
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $DB->delete_records_select(
                'block_workload_courses',
                "cohortid = :cohortid AND id $insql",
                $inparams + ['cohortid' => $id]
            );
        }
        \block_workload\event\courses_removed::create([
            'objectid' => $id, 'context' => context_system::instance(),
            'other'    => ['cohortname' => $cohort->name, 'count' => $removed],
        ])->trigger();
        redirect(
            new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]),
            get_string('coursesremoved', 'block_workload', $removed),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Bulk remove: show confirmation.
    if ($confirmbulkids !== '' && !$dobulkremove) {
        $ids    = array_filter(array_map('intval', explode(',', $confirmbulkids)));
        $yesurl = new moodle_url('/blocks/workload/manage.php', [
            'action' => 'courses', 'id' => $id,
            'confirmbulkids' => $confirmbulkids, 'dobulkremove' => 1, 'sesskey' => sesskey(),
        ]);
        $nourl  = new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('coursestitle', 'block_workload', format_string($cohort->name)));
        echo $OUTPUT->confirm(get_string('confirmbulkremovecourses', 'block_workload', count($ids)), $yesurl, $nourl);
        echo $OUTPUT->footer();
        return;
    }

    // Bulk remove: intercept POST → redirect.
    $removecourseids = optional_param_array('removecourseids', [], PARAM_INT);
    if (!empty($removecourseids)) {
        $idsstring = implode(',', array_filter(array_map('intval', $removecourseids)));
        redirect(new moodle_url(
            '/blocks/workload/manage.php',
            ['action' => 'courses', 'id' => $id, 'confirmbulkids' => $idsstring]
        ));
    }

    // Toggle active flag.
    $toggleid = optional_param('toggleid', 0, PARAM_INT);
    if ($toggleid && confirm_sesskey()) {
        $rec = $DB->get_record('block_workload_courses', ['id' => $toggleid, 'cohortid' => $id], '*', MUST_EXIST);
        $rec->active = $rec->active ? 0 : 1;
        $DB->update_record('block_workload_courses', $rec);
        $coursename = $DB->get_field('course', 'fullname', ['id' => $rec->courseid]);
        \block_workload\event\course_toggled::create([
            'objectid' => $id, 'context' => context_system::instance(),
            'other'    => ['cohortname' => $cohort->name, 'coursename' => $coursename, 'active' => $rec->active],
        ])->trigger();
        redirect(
            new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]),
            get_string('courseupdated', 'block_workload'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Move course up/down.
    $moveup   = optional_param('moveup', 0, PARAM_INT);
    $movedown = optional_param('movedown', 0, PARAM_INT);
    $moveid   = $moveup ?: $movedown;
    if ($moveid && confirm_sesskey()) {
        $ordered = array_values(
            $DB->get_records('block_workload_courses', ['cohortid' => $id], 'sortorder ASC, id ASC')
        );
        $count = count($ordered);
        foreach ($ordered as $i => $c) {
            if ((int)$c->id !== $moveid) {
                continue;
            }
            $swapidx = $moveup ? $i - 1 : $i + 1;
            if ($swapidx >= 0 && $swapidx < $count) {
                $transaction = $DB->start_delegated_transaction();
                // Normalise to sequential positions, writing only rows that changed.
                foreach ($ordered as $j => $r) {
                    if ($j !== $i && $j !== $swapidx && (int)$r->sortorder !== $j) {
                        $DB->set_field('block_workload_courses', 'sortorder', $j, ['id' => $r->id]);
                    }
                }
                $DB->set_field('block_workload_courses', 'sortorder', $swapidx, ['id' => $ordered[$i]->id]);
                $DB->set_field('block_workload_courses', 'sortorder', $i, ['id' => $ordered[$swapidx]->id]);
                $transaction->allow_commit();
            }
            break;
        }
        redirect(new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]));
    }

    // Add courses (POST).
    $addcourses = optional_param_array('addcourseids', [], PARAM_INT);
    if (!empty($addcourses) && confirm_sesskey()) {
        $nextsort = 1 + (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), -1) FROM {block_workload_courses} WHERE cohortid = :cohortid',
            ['cohortid' => $id]
        );
        $addcourseids = array_unique(array_map('intval', $addcourses));

        // One query to find courses already assigned, then a single batch insert.
        [$insql, $inparams] = $DB->get_in_or_equal($addcourseids, SQL_PARAMS_NAMED);
        $existing = $DB->get_records_select_menu(
            'block_workload_courses',
            "cohortid = :cohortid AND courseid $insql",
            $inparams + ['cohortid' => $id],
            '',
            'courseid, id'
        );
        $now     = time();
        $records = [];
        foreach ($addcourseids as $cid) {
            if (!isset($existing[$cid])) {
                $records[] = (object)[
                    'cohortid'    => $id,
                    'courseid'    => $cid,
                    'active'      => 1,
                    'sortorder'   => $nextsort++,
                    'timecreated' => $now,
                ];
            }
        }
        $added = count($records);
        if ($added > 0) {
            $transaction = $DB->start_delegated_transaction();
            $DB->insert_records('block_workload_courses', $records);
            $transaction->allow_commit();

            \block_workload\event\courses_assigned::create([
                'objectid' => $id, 'context' => context_system::instance(),
                'other'    => ['cohortname' => $cohort->name, 'count' => $added],
            ])->trigger();
        }
        redirect(
            new moodle_url('/blocks/workload/manage.php', array_filter([
                'action'         => 'courses',
                'id'             => $id,
                'categoryid'     => $selectedcategory ?: null,
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

    // Build template context.

    $PAGE->requires->js_call_amd('block_workload/manage', 'initCourses', []);
    $PAGE->requires->js_call_amd('block_workload/toggleall', 'init');

    $assignedcourses  = \block_workload\helper::get_cohort_courses_all($id);
    $assignedids      = array_map(fn($c) => (int)$c->id, $assignedcourses);
    $catoptions       = \block_workload\helper::get_category_options();
    $showordercolumn  = (get_config('block_workload', 'courseorder') ?: 'sortorder') === 'sortorder';
    $datedisabled     = get_string('datedisabled', 'block_workload');

    // Category browser courses.
    $catrows      = [];
    $hascatcourses = false;
    $nocatcourses  = false;
    $catresultinfo = '';
    $cattotal = 0;
    if ($selectedcategory || $search !== '') {
        $catoffset     = ($perpage > 0) ? $page * $perpage : 0;
        $cattotal      = \block_workload\helper::get_courses_in_category_count(
            $selectedcategory ?: null,
            (bool)$includesubcats,
            $search
        );
        $catcourses    = \block_workload\helper::get_courses_in_category(
            $selectedcategory ?: null,
            (bool)$includesubcats,
            $search,
            $perpage,
            $catoffset
        );
        $hascatcourses = !empty($catcourses);
        $nocatcourses  = empty($catcourses);
        if ($hascatcourses) {
            // The per-row "already assigned" badge reports this precisely; an aggregate
            // here would silently mean "on this page" once the list is paged.
            $catresultinfo = get_string('courseresultcount', 'block_workload', $cattotal);
            foreach ($catcourses as $c) {
                $already    = in_array((int)$c->id, $assignedids, true);
                $catrows[] = [
                    'id'               => $c->id,
                    'courseurl'        => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
                    'fullname'         => format_string($c->fullname),
                    'shortname'        => $c->shortname,
                    'startdate'        => !empty($c->startdate)
                        ? userdate($c->startdate, get_string('strftimedate', 'langconfig'))
                        : '',
                    'startdate_disabled' => empty($c->startdate),
                    'enddate'          => !empty($c->enddate)
                        ? userdate($c->enddate, get_string('strftimedate', 'langconfig'))
                        : '',
                    'enddate_disabled' => empty($c->enddate),
                    'datedisabled'     => $datedisabled,
                    'already'          => $already,
                ];
            }
        }
    }

    // Assigned courses rows.
    $assignedlist = array_values($assignedcourses);
    $lastidx      = count($assignedlist) - 1;
    $assignedrows = [];
    foreach ($assignedlist as $idx => $c) {
        $toggleurl      = new moodle_url(
            '/blocks/workload/manage.php',
            ['action' => 'courses', 'id' => $id, 'toggleid' => $c->assignid, 'sesskey' => sesskey()]
        );
        $confirmremoveurl = new moodle_url(
            '/blocks/workload/manage.php',
            ['action' => 'courses', 'id' => $id, 'confirmremoveid' => $c->assignid]
        );
        $moveupurl      = new moodle_url(
            '/blocks/workload/manage.php',
            ['action' => 'courses', 'id' => $id, 'moveup' => $c->assignid, 'sesskey' => sesskey()]
        );
        $movedownurl    = new moodle_url(
            '/blocks/workload/manage.php',
            ['action' => 'courses', 'id' => $id, 'movedown' => $c->assignid, 'sesskey' => sesskey()]
        );

        $assignedrows[] = [
            'assignid'          => $c->assignid,
            'courseurl'         => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            'fullname'          => format_string($c->fullname),
            'shortname'         => $c->shortname,
            'startdate'         => !empty($c->startdate)
                ? userdate($c->startdate, get_string('strftimedate', 'langconfig'))
                : '',
            'startdate_disabled' => empty($c->startdate),
            'enddate'           => !empty($c->enddate)
                ? userdate($c->enddate, get_string('strftimedate', 'langconfig'))
                : '',
            'enddate_disabled'  => empty($c->enddate),
            'datedisabled'      => $datedisabled,
            'active'            => (bool)$c->active,
            'toggleurl'         => $toggleurl->out(false),
            'togglelabel'       => $c->active
                ? get_string('deactivate', 'block_workload')
                : get_string('activate', 'block_workload'),
            'confirmremoveurl'  => $confirmremoveurl->out(false),
            'showordercolumn'   => $showordercolumn,
            'showup'            => ($showordercolumn && $idx > 0),
            'showdown'          => ($showordercolumn && $idx < $lastidx),
            'moveupurl'         => $moveupurl->out(false),
            'movedownurl'       => $movedownurl->out(false),
            'moveuplabel'       => get_string('moveup', 'moodle'),
            'movedownlabel'     => get_string('movedown', 'moodle'),
        ];
    }

    $catopts = [0 => get_string('selectcategory', 'block_workload')] + $catoptions;

    // Filter state carried by every paging/per-page link on the add-courses table.
    $coursesbase  = new moodle_url('/blocks/workload/manage.php');
    $filterparams = array_filter([
        'action'         => 'courses',
        'id'             => $id,
        'categoryid'     => $selectedcategory ?: null,
        'includesubcats' => $includesubcats ?: null,
        'search'         => ($search !== '') ? $search : null,
    ]);

    $pagingurl     = new moodle_url($coursesbase, $filterparams + ['perpage' => $perpage]);
    $showcatpaging = ($perpage > 0 && $cattotal > $perpage);
    $catpagingbar  = $showcatpaging ? $OUTPUT->paging_bar($cattotal, $page, $perpage, $pagingurl) : '';

    $ctx = [
        'backurl'           => (new moodle_url('/blocks/workload/manage.php'))->out(false),
        'cohortid'          => $id,
        'sesskey'           => sesskey(),
        'selectedcategory'  => $selectedcategory,
        'includesubcats'    => (bool)$includesubcats,
        'search'            => $search,
        'selectedpage'      => $page,
        'selectedperpage'   => $perpage,
        'pagingbartop'      => $catpagingbar,
        'pagingbarbottom'   => $catpagingbar,
        'perpagestr'        => get_string('perpage', 'block_workload'),
        'perpageopts'       => \block_workload\helper::build_perpage_options(
            $coursesbase,
            $filterparams,
            $perpage,
            $cattotal
        ),
        'catopts'           => block_workload_build_select_opts($catopts, $selectedcategory),
        'hascatcourses'     => $hascatcourses,
        'nocatcourses'      => $nocatcourses,
        'catresultinfo'     => $catresultinfo,
        'catrows'           => $catrows,
        'showordercolumn'   => $showordercolumn,
        'hasassigned'       => !empty($assignedcourses),
        'assignedcount'     => count($assignedcourses),
        'assignedrows'      => $assignedrows,
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('coursestitle', 'block_workload', format_string($cohort->name)));
    echo $OUTPUT->render_from_template('block_workload/manage_courses', $ctx);
    echo $OUTPUT->footer();
}

// Action: activation settings.

/**
 * Render the activation settings page.
 *
 * @param int $id Cohort id.
 */
function block_workload_action_activation(int $id): void {
    global $DB, $OUTPUT, $PAGE;

    $modal  = optional_param('modal', 0, PARAM_BOOL);
    $cohort = $DB->get_record('block_workload_cohorts', ['id' => $id], '*', MUST_EXIST);

    if ($modal) {
        $PAGE->set_pagelayout('embedded');
    }

    $currentmode = !$cohort->active ? 'inactive'
        : (!$cohort->week_from && !$cohort->week_to ? 'always' : 'period');

    // Handle save.
    if (optional_param('saveactivation', 0, PARAM_BOOL) && confirm_sesskey()) {
        $mode     = optional_param('activation_mode', 'always', PARAM_ALPHA);
        $weekfrom = optional_param('week_from', 0, PARAM_INT);
        $yearfrom = optional_param('year_from', 0, PARAM_INT);
        $weekto   = optional_param('week_to', 0, PARAM_INT);
        $yearto   = optional_param('year_to', 0, PARAM_INT);

        $cohort->active    = ($mode !== 'inactive') ? 1 : 0;
        $cohort->week_from = ($mode === 'period') ? ($weekfrom ?: null) : null;
        $cohort->year_from = ($mode === 'period') ? ($yearfrom ?: null) : null;
        $cohort->week_to   = ($mode === 'period') ? ($weekto ?: null) : null;
        $cohort->year_to   = ($mode === 'period') ? ($yearto ?: null) : null;
        $cohort->timemodified = time();
        $DB->update_record('block_workload_cohorts', $cohort);

        \block_workload\event\activation_updated::create([
            'objectid' => $id, 'context' => context_system::instance(),
            'other'    => ['name' => $cohort->name, 'mode' => $mode],
        ])->trigger();

        if ($modal) {
            \core\notification::success(get_string('activationsaved', 'block_workload'));
            redirect(new moodle_url('/blocks/workload/manage.php', ['action' => 'modaldone']));
        }
        redirect(
            new moodle_url('/blocks/workload/manage.php', ['action' => 'activation', 'id' => $id]),
            get_string('activationsaved', 'block_workload'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $PAGE->requires->js_call_amd('block_workload/manage', 'initActivation', []);

    $isactive    = \block_workload\helper::is_workload_active($id);
    $currentweek = (int) date('W');
    $currentyear = (int) date('o');

    $ctx = [
        'ismodal'          => (bool)$modal,
        'backurl'          => (new moodle_url('/blocks/workload/manage.php'))->out(false),
        'cohortname'       => format_string($cohort->name),
        'degree_program'   => format_string($cohort->degree_program),
        'isactive'         => $isactive,
        'statuslabel'      => $isactive
            ? get_string('statusactive', 'block_workload')
            : get_string('statusinactive', 'block_workload'),
        'statusbadgeclass' => $isactive ? 'bg-success text-white' : 'bg-secondary text-white',
        'cohortid'         => $id,
        'modal'            => (int)$modal,
        'sesskey'          => sesskey(),
        'formclass'        => $modal ? 'p-3' : 'card p-3',
        'mode_inactive'    => ($currentmode === 'inactive'),
        'mode_always'      => ($currentmode === 'always'),
        'mode_period'      => ($currentmode === 'period'),
        'periodstyle'      => ($currentmode === 'period') ? '' : 'display:none;',
        'week_from'        => (int)($cohort->week_from ?? $currentweek),
        'year_from'        => (int)($cohort->year_from ?? $currentyear),
        'week_to'          => (int)($cohort->week_to ?? $currentweek),
        'year_to'          => (int)($cohort->year_to ?? $currentyear),
    ];

    echo $OUTPUT->header();
    if (!$modal) {
        echo $OUTPUT->heading(get_string('activationtitle', 'block_workload', format_string($cohort->name)));
    }
    echo $OUTPUT->render_from_template('block_workload/manage_activation', $ctx);
    echo $OUTPUT->footer();
}
