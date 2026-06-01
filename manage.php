<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

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

$action  = optional_param('action',  'list', PARAM_ALPHA);
$id      = optional_param('id',      0,      PARAM_INT);
$confirm = optional_param('confirm', 0,      PARAM_BOOL);

$syscontext = context_system::instance();
require_login();
require_capability('block/workload:manage', $syscontext);

// In enrollment mode the entire cohort management is disabled.
if ((get_config('block_workload', 'coursemode') ?: 'cohort') === 'enrollment') {
    redirect(new moodle_url('/blocks/workload/manage_enrollment.php'));
}

// Modal-done: tiny response that tells the parent frame to close the modal and reload.
// Must be handled before PAGE setup so we can bypass Moodle's full page output.
if ($action === 'modaldone') {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
    echo '<script>window.parent.postMessage("wl_modal_done","*");</script>';
    echo '</body></html>';
    exit;
}

$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managetitle', 'block_workload'));
$PAGE->set_heading(get_string('managetitle', 'block_workload'));
$PAGE->set_url('/blocks/workload/manage.php', ['action' => $action, 'id' => $id]);

// Navigation breadcrumb.
$PAGE->navbar->add(get_string('managetitle', 'block_workload'), new moodle_url('/blocks/workload/manage.php'));

// =========================================================================
// Router.
// =========================================================================
switch ($action) {

    // -----------------------------------------------------------------
    case 'add':
    case 'edit':
        action_cohort_form($action, $id);
        break;

    // -----------------------------------------------------------------
    case 'delete':
        action_delete($id, $confirm);
        break;

    // -----------------------------------------------------------------
    case 'members':
        action_members($id);
        break;

    // -----------------------------------------------------------------
    case 'courses':
        action_courses($id);
        break;

    // -----------------------------------------------------------------
    case 'activation':
        action_activation($id);
        break;

    // -----------------------------------------------------------------
    case 'togglecohort':
        action_toggle_cohort($id);
        break;

    // -----------------------------------------------------------------
    default:
        action_list();
        break;
}

// =========================================================================
// Action: toggle cohort active flag directly from the list.
// =========================================================================
function action_toggle_cohort(int $id): void {
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

// =========================================================================
// Action: list cohorts.
// =========================================================================
function action_list(): void {
    global $DB, $OUTPUT, $PAGE;

    $cohorts = $DB->get_records('block_workload_cohorts', null, 'timecreated DESC');

    // ---- Use Moodle's core/modal_factory for a native-looking modal with an iframe inside ----
    $PAGE->requires->js_amd_inline("
require(['core/modal_factory', 'core/modal_events', 'jquery'], function(ModalFactory, ModalEvents, \$) {

    \$(document).on('click', '[data-modal-url]', function(e) {
        e.preventDefault();
        var url   = \$(this).data('modal-url');
        var title = \$(this).data('modal-title') || '';

        var isLarge = \$(this).data('modal-large') ? true : false;
        var maxw    = \$(this).data('modal-maxwidth') || null;
        ModalFactory.create({
            type:  ModalFactory.types.DEFAULT,
            title: title,
            body:  '<iframe src=\"' + url + '\" style=\"width:100%;min-height:160px;border:none;display:block;\"></iframe>',
            large: isLarge,
        }).then(function(modal) {

            modal.show();
            if (maxw) { modal.getModal().css('max-width', maxw + 'px'); }

            // Auto-resize the iframe once its embedded page has loaded.
            modal.getRoot().find('iframe').on('load', function() {
                try {
                    var h = this.contentDocument.documentElement.scrollHeight;
                    if (h > 80) { this.style.minHeight = (h + 16) + 'px'; }
                } catch(ex) { /* cross-origin guard */ }
            });

            // Form saved inside iframe posts wl_modal_done → close + reload list.
            var msgHandler = function(ev) {
                if (ev.data !== 'wl_modal_done') { return; }
                window.removeEventListener('message', msgHandler);
                modal.destroy();
                location.reload();
            };
            window.addEventListener('message', msgHandler);

            // If the user closes the modal via X, clean up the listener.
            modal.getRoot().on(ModalEvents.hidden, function() {
                window.removeEventListener('message', msgHandler);
                modal.destroy();
            });

            return modal;
        }).catch(function(err) {
            window.console.error('WL modal error:', err);
        });
    });

});
");

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('cohorts', 'block_workload'));

    $addurl = (new moodle_url('/blocks/workload/manage.php', ['action' => 'add', 'modal' => 1]))->out(false);
    echo html_writer::div(
        html_writer::tag(
            'button',
            get_string('addcohort', 'block_workload'),
            [
                'type'              => 'button',
                'class'             => 'btn btn-primary me-2',
                'data-modal-url'    => $addurl,
                'data-modal-title'  => get_string('addcohort', 'block_workload'),
                'data-modal-large'  => '1',
            ]
        ) .
        html_writer::link(
            new moodle_url('/blocks/workload/statistics.php'),
            get_string('statisticstitle', 'block_workload'),
            ['class' => 'btn btn-outline-primary']
        ),
        'mb-3'
    );

    if (empty($cohorts)) {
        echo $OUTPUT->notification(get_string('nocohortsfound', 'block_workload'), 'info');
    } else {
        $table             = new html_table();
        $table->head       = [
            get_string('cohortname',    'block_workload'),
            get_string('degreeprogram', 'block_workload'),
            get_string('department'),
            get_string('active',        'block_workload'),
            get_string('students',      'block_workload'),
            get_string('courses',       'block_workload'),
            get_string('actions',       'block_workload'),
        ];
        $table->attributes = ['class' => 'table table-striped table-hover table-sm'];

        foreach ($cohorts as $cohort) {
            $membercount = $DB->count_records('block_workload_members', ['cohortid' => $cohort->id]);
            $coursecount = $DB->count_records('block_workload_courses', ['cohortid' => $cohort->id, 'active' => 1]);

            $memberurl = new moodle_url('/blocks/workload/manage.php', ['action' => 'members',    'id' => $cohort->id]);
            $courseurl = new moodle_url('/blocks/workload/manage.php', ['action' => 'courses',    'id' => $cohort->id]);
            $delurl    = new moodle_url('/blocks/workload/manage.php', ['action' => 'delete',     'id' => $cohort->id]);

            $editurl  = (new moodle_url('/blocks/workload/manage.php', ['action' => 'edit',       'id' => $cohort->id, 'modal' => 1]))->out(false);
            $activurl = (new moodle_url('/blocks/workload/manage.php', ['action' => 'activation', 'id' => $cohort->id, 'modal' => 1]))->out(false);

            // Edit + Activation as modal triggers; rest navigate normally.
            $editicon  = html_writer::link('#', $OUTPUT->pix_icon('t/edit',     get_string('editcohort',       'block_workload')),
                ['class' => 'action-icon', 'title' => get_string('editcohort',       'block_workload'),
                 'data-modal-url' => $editurl,  'data-modal-title' => get_string('editcohort',       'block_workload'),
                 'data-modal-large' => '1']);
            $activicon = html_writer::link('#', $OUTPUT->pix_icon('i/calendar', get_string('cohortactivation', 'block_workload')),
                ['class' => 'action-icon', 'title' => get_string('cohortactivation', 'block_workload'),
                 'data-modal-url'      => $activurl,
                 'data-modal-title'    => get_string('cohortactivation', 'block_workload'),
                 'data-modal-maxwidth' => '460']);

            $actions = implode('', [
                $editicon,
                $OUTPUT->action_icon($memberurl, new pix_icon('i/users',  get_string('cohortmembers', 'block_workload'))),
                $OUTPUT->action_icon($courseurl, new pix_icon('i/course', get_string('cohortcourses', 'block_workload'))),
                $activicon,
                $OUTPUT->action_icon($delurl,    new pix_icon('t/delete', get_string('deletecohort',  'block_workload'))),
            ]);

            $toggleurl   = new moodle_url('/blocks/workload/manage.php', [
                'action'  => 'togglecohort',
                'id'      => $cohort->id,
                'sesskey' => sesskey(),
            ]);
            $inputattrs = [
                'type'  => 'checkbox',
                'class' => 'custom-control-input',
                // No id needed — label has no "for", so there is no implicit
                // label→input click binding that could swallow the <a> navigation.
            ];
            if ($cohort->active) {
                $inputattrs['checked'] = 'checked';
            }
            $activebadge = html_writer::link(
                $toggleurl,
                html_writer::tag('div',
                    html_writer::empty_tag('input', $inputattrs) .
                    html_writer::tag('label', '', [
                        // No "for" attribute — Bootstrap uses CSS sibling selectors
                        // (input:checked ~ label) so the visual toggle still works,
                        // but without "for" the label has no implicit checkbox-toggle
                        // behaviour that would compete with the surrounding <a>.
                        'class' => 'custom-control-label',
                        'title' => $cohort->active
                            ? get_string('active',   'block_workload')
                            : get_string('inactive', 'block_workload'),
                    ]),
                    ['class' => 'custom-control custom-switch', 'style' => 'pointer-events:none;']
                ),
                ['style' => 'text-decoration:none;']
            );

            $table->data[] = [
                format_string($cohort->name),
                format_string($cohort->degree_program),
                format_string($cohort->department),
                $activebadge,
                $membercount,
                $coursecount,
                $actions,
            ];
        }
        echo html_writer::table($table);
    }

    echo $OUTPUT->footer();
}

// =========================================================================
// Action: create / edit cohort form.
// =========================================================================
function action_cohort_form(string $action, int $id): void {
    global $DB, $OUTPUT, $PAGE;

    $modal  = optional_param('modal', 0, PARAM_BOOL);
    $cohort = ($action === 'edit' && $id) ? $DB->get_record('block_workload_cohorts', ['id' => $id], '*', MUST_EXIST) : null;

    if ($modal) {
        $PAGE->set_pagelayout('embedded');
    }

    $formurl = new moodle_url('/blocks/workload/manage.php',
        ['action' => $action, 'id' => $id, 'modal' => (int)$modal]);
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
            // Note: date range is managed exclusively via the Activation page.
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
            // New cohorts start with no date restriction (always active when active=1).
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
            // Store notification for the parent page after reload, then signal done.
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
            ? get_string('editcohort',  'block_workload')
            : get_string('addcohort',   'block_workload'));
    }
    $form->display();
    echo $OUTPUT->footer();
}

// =========================================================================
// Action: delete cohort.
// =========================================================================
function action_delete(int $id, bool $confirmed): void {
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
        new moodle_url('/blocks/workload/manage.php', ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
        new moodle_url('/blocks/workload/manage.php')
    );
    echo $OUTPUT->footer();
}

// =========================================================================
// Action: manage members.
// =========================================================================
function action_members(int $id): void {
    global $DB, $OUTPUT, $PAGE;

    $cohort  = $DB->get_record('block_workload_cohorts', ['id' => $id], '*', MUST_EXIST);
    $baseurl = new moodle_url('/blocks/workload/manage.php', ['action' => 'members', 'id' => $id]);

    // ---- Paging & filter params (read early — needed for confirmation URLs) --
    $page    = optional_param('page',    0,  PARAM_INT);
    $perpage = optional_param('perpage', 25, PARAM_INT); // 0 = show all

    $alphabet    = explode(',', get_string('alphabet', 'langconfig'));
    $firstletter = optional_param('firstletter', '', PARAM_RAW);
    $lastletter  = optional_param('lastletter',  '', PARAM_RAW);
    // Accept only letters that are actually in the current language alphabet.
    $firstletter = (in_array($firstletter, $alphabet, true)) ? $firstletter : '';
    $lastletter  = (in_array($lastletter,  $alphabet, true)) ? $lastletter  : '';

    $search            = optional_param('search',            '', PARAM_TEXT);
    $filterdepartment  = optional_param('filterdepartment',  '', PARAM_TEXT);
    $filterinstitution = optional_param('filterinstitution', '', PARAM_TEXT);
    $filtercategory    = optional_param('filtercategory',    0,  PARAM_INT);
    $moodlecohortid    = optional_param('moodlecohortid',    0,  PARAM_INT);
    $importenabled     = (bool) get_config('block_workload', 'enablemoodlecohortimport');
    // Panel is "open" when any filter param was submitted (keeps state after filter).
    $hassearch  = ($search !== '' || $filterdepartment !== '' || $filterinstitution !== '' || $filtercategory > 0);
    $panelopen  = $hassearch || ($importenabled && $moodlecohortid > 0); // JS will also be able to open it client-side.

    // ---- Single-remove: show Moodle confirmation page --------------------
    $confirmremoveid = optional_param('confirmremoveid', 0, PARAM_INT);
    if ($confirmremoveid) {
        $member = $DB->get_record('user', ['id' => $confirmremoveid],
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename',
            MUST_EXIST);
        $yesurl = new moodle_url('/blocks/workload/manage.php', [
            'action'      => 'members',
            'id'          => $id,
            'removeid'    => $confirmremoveid,
            'sesskey'     => sesskey(),
            'firstletter' => $firstletter,
            'lastletter'  => $lastletter,
            'page'        => $page,
            'perpage'     => $perpage,
        ]);
        $nourl = new moodle_url('/blocks/workload/manage.php', ['action' => 'members', 'id' => $id]);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('memberstitle', 'block_workload', format_string($cohort->name)));
        echo $OUTPUT->confirm(
            get_string('confirmsingleremove', 'block_workload', fullname($member)),
            $yesurl,
            $nourl
        );
        echo $OUTPUT->footer();
        return;
    }

    // ---- Single-remove: execute (reached via "Continue" in confirmation) ----
    $removeid = optional_param('removeid', 0, PARAM_INT);
    if ($removeid && confirm_sesskey()) {
        $DB->delete_records('block_workload_members', ['cohortid' => $id, 'userid' => (int)$removeid]);

        \block_workload\event\members_removed::create([
            'objectid' => $id,
            'context'  => context_system::instance(),
            'other'    => ['cohortname' => $cohort->name, 'count' => 1],
        ])->trigger();

        redirect($baseurl, get_string('membersremoved', 'block_workload', 1), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // ---- Bulk-remove: execute confirmed (GET, reached via "Continue") ----
    $confirmbulkids = optional_param('confirmbulkids', '', PARAM_SEQUENCE);
    $dobulkremove   = optional_param('dobulkremove',   0,  PARAM_BOOL);
    if ($confirmbulkids !== '' && $dobulkremove && confirm_sesskey()) {
        $ids = array_filter(array_map('intval', explode(',', $confirmbulkids)));
        $removed = 0;
        foreach ($ids as $uid) {
            $DB->delete_records('block_workload_members', ['cohortid' => $id, 'userid' => $uid]);
            $removed++;
        }

        \block_workload\event\members_removed::create([
            'objectid' => $id,
            'context'  => context_system::instance(),
            'other'    => ['cohortname' => $cohort->name, 'count' => $removed],
        ])->trigger();

        redirect($baseurl,
            get_string('membersremoved', 'block_workload', $removed),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // ---- Bulk-remove: show Moodle confirmation page ----------------------
    if ($confirmbulkids !== '' && !$dobulkremove) {
        $ids = array_filter(array_map('intval', explode(',', $confirmbulkids)));
        $yesurl = new moodle_url('/blocks/workload/manage.php', [
            'action'         => 'members',
            'id'             => $id,
            'confirmbulkids' => $confirmbulkids,
            'dobulkremove'   => 1,
            'sesskey'        => sesskey(),
        ]);
        $nourl = new moodle_url('/blocks/workload/manage.php', ['action' => 'members', 'id' => $id]);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('memberstitle', 'block_workload', format_string($cohort->name)));
        echo $OUTPUT->confirm(
            get_string('confirmbulkremove', 'block_workload', count($ids)),
            $yesurl,
            $nourl
        );
        echo $OUTPUT->footer();
        return;
    }

    // ---- Bulk-remove: intercept POST → redirect to confirmation ----------
    $removeusers = optional_param_array('removeusers', [], PARAM_INT);
    if (!empty($removeusers)) {
        $idsstring = implode(',', array_filter(array_map('intval', $removeusers)));
        redirect(new moodle_url('/blocks/workload/manage.php', [
            'action'         => 'members',
            'id'             => $id,
            'confirmbulkids' => $idsstring,
        ]));
    }

    // ---- Handle add members (POST) --------------------------------------
    $addusers = optional_param_array('addusers', [], PARAM_INT);
    if (!empty($addusers) && confirm_sesskey()) {
        $added = 0;
        foreach ($addusers as $uid) {
            if (!$DB->record_exists('block_workload_members', ['cohortid' => $id, 'userid' => (int)$uid])) {
                $r              = new stdClass();
                $r->cohortid    = $id;
                $r->userid      = (int)$uid;
                $r->timecreated = time();
                $DB->insert_record('block_workload_members', $r);
                $added++;
            }
        }

        if ($added > 0) {
            \block_workload\event\members_added::create([
                'objectid' => $id,
                'context'  => context_system::instance(),
                'other'    => ['cohortname' => $cohort->name, 'count' => $added],
            ])->trigger();
        }

        redirect($baseurl,
            get_string('membersadded', 'block_workload', $added),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // ---- Preload filter option lists ------------------------------------
    $deptOpts = ['' => get_string('selectdepartment', 'block_workload')];
    foreach (\block_workload\helper::get_distinct_departments() as $d) {
        $deptOpts[$d] = $d;
    }
    $instOpts = ['' => get_string('selectinstitution', 'block_workload')];
    foreach (\block_workload\helper::get_distinct_institutions() as $i) {
        $instOpts[$i] = $i;
    }
    $catOpts = [0 => get_string('selectcategory', 'block_workload')]
             + \block_workload\helper::get_category_options();

    // ---- Members data ---------------------------------------------------
    $totalcount   = \block_workload\helper::get_cohort_members_count($id, $firstletter, $lastletter);
    $limit        = ($perpage > 0) ? $perpage : 0;
    $offset       = ($perpage > 0) ? $page * $perpage : 0;
    $members      = \block_workload\helper::get_cohort_members($id, $limit, $offset, $firstletter, $lastletter);
    $allmemberids = \block_workload\helper::get_all_cohort_member_ids($id);

    // ---- AMD modules ----------------------------------------------------
    $PAGE->requires->js_call_amd('core/checkbox-toggleall', 'init');

    $PAGE->requires->js_amd_inline("
require(['core/ajax', 'jquery'], function(Ajax, \$) {

    // ---- Panel toggle (instant, no animation) ----
    var panel    = \$('#workload-addpanel');
    var btn      = \$('#workload-addpanel-toggle');
    var openLbl  = btn.data('label-open');
    var closeLbl = btn.data('label-close');
    btn.on('click', function(e) {
        e.preventDefault();
        if (panel.is(':visible')) {
            panel.hide();
            btn.html(openLbl);
        } else {
            panel.show();
            btn.html(closeLbl);
            \$('#wl-search').trigger('focus');
        }
    });

    // ---- User search autocomplete ----
    var input    = \$('#wl-search');
    var form     = \$('#workload-filter-form');
    if (input.length) {
        // Wrapper needs position:relative so dropdown aligns under the input.
        var wrap = input.parent();
        wrap.css('position', 'relative');
        var dropdown = \$('<ul class=\"workload-suggest\"></ul>').appendTo(wrap);

        var timer;
        var activeIdx = -1;

        function setWidth() {
            dropdown.css('width', input.outerWidth() + 'px');
        }

        function hideDrop() {
            dropdown.hide().empty();
            activeIdx = -1;
        }

        function selectItem(li) {
            input.val(li.data('name'));
            hideDrop();
            form.trigger('submit');
        }

        input.on('input', function() {
            clearTimeout(timer);
            hideDrop();
            var q = \$.trim(input.val());
            if (q.length < 2) { return; }
            timer = setTimeout(function() {
                Ajax.call([{
                    methodname: 'block_workload_search_users',
                    args: { query: q },
                    done: function(data) {
                        hideDrop();
                        if (!data.users || !data.users.length) { return; }
                        \$.each(data.users, function(_, u) {
                            var dept = u.department ? ' \u2014 ' + u.department : '';
                            \$('<li></li>')
                                .text(u.fullname + ' (' + u.email + ')' + dept)
                                .data('name', u.fullname)
                                .appendTo(dropdown);
                        });
                        setWidth();
                        dropdown.show();
                        activeIdx = -1;
                    },
                    fail: function() { hideDrop(); }
                }]);
            }, 300);
        });

        // Keyboard navigation within dropdown.
        input.on('keydown', function(e) {
            var items = dropdown.find('li');
            if (!dropdown.is(':visible') || !items.length) { return; }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = Math.min(activeIdx + 1, items.length - 1);
                items.removeClass('active').eq(activeIdx).addClass('active');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                items.removeClass('active').eq(activeIdx).addClass('active');
            } else if (e.key === 'Enter' && activeIdx >= 0) {
                e.preventDefault();
                selectItem(\$(items.get(activeIdx)));
            } else if (e.key === 'Escape') {
                hideDrop();
            }
        });

        // Click on suggestion.
        dropdown.on('mousedown', 'li', function(e) {
            e.preventDefault();
            selectItem(\$(this));
        });

        // Hide on outside click.
        \$(document).on('click.wlsuggest', function(e) {
            if (!\$(e.target).closest('#wl-search, .workload-suggest').length) {
                hideDrop();
            }
        });
    }
});
    ");

    // =========================================================================
    // OUTPUT
    // =========================================================================
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('memberstitle', 'block_workload', format_string($cohort->name)));

    // Top action bar — explicit me-2 on back button avoids theme gap-class issues.
    echo html_writer::link(
        new moodle_url('/blocks/workload/manage.php'),
        '&larr; ' . get_string('cohorts', 'block_workload'),
        ['class' => 'btn btn-secondary me-2 mb-4']
    );
    $lblopen  = '+ '        . get_string('addmembers',      'block_workload');
    $lblclose = "\xc3\x97 " . get_string('closeaddpanel', 'block_workload');
    echo html_writer::tag('button',
        $panelopen ? $lblclose : $lblopen,
        [
            'type'             => 'button',
            'id'               => 'workload-addpanel-toggle',
            'class'            => 'btn btn-primary mb-4',
            'data-label-open'  => $lblopen,
            'data-label-close' => $lblclose,
        ]
    );

    // =========================================================================
    // ADD MEMBERS PANEL – always in DOM, shown/hidden by JS.
    // PHP opens it when filter results are already present ($panelopen).
    // =========================================================================
    $panelStyle = $panelopen ? 'display:block;' : 'display:none;';
    // Card-style panel matching Moodle's own filter panels (e.g. course participants).
    echo '<div id="workload-addpanel" style="' . $panelStyle . 'margin-bottom:1.5rem;">';
    echo '<div class="card">';
    echo '<div class="card-body">';
    echo html_writer::tag('h5', get_string('addmembers', 'block_workload'), ['class' => 'card-title mt-0 mb-3']);

    // Filter form — flex row, all inputs same size class, no description text on category.
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => '', 'id' => 'workload-filter-form']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'members']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id',      'value' => $id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'page',    'value' => $page]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'perpage', 'value' => $perpage]);

    $f = 'display:inline-block;width:200px;margin-right:16px;vertical-align:bottom;overflow:hidden;';
    echo '<div style="margin-bottom:1rem;">';

    echo '<div style="' . $f . 'position:relative;">';
    echo html_writer::tag('label', get_string('searchusers', 'block_workload'),
        ['for' => 'wl-search', 'class' => 'form-label fw-semibold mb-1 d-block']);
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'search', 'id' => 'wl-search',
        'value' => s($search), 'class' => 'form-control', 'style' => 'width:100%;',
        'placeholder' => get_string('searchusers', 'block_workload'), 'autocomplete' => 'off',
    ]);
    echo '</div>';

    echo '<div style="' . $f . '">';
    echo html_writer::tag('label', get_string('department'),
        ['for' => 'wl-dept', 'class' => 'form-label fw-semibold mb-1 d-block']);
    echo html_writer::select($deptOpts, 'filterdepartment', $filterdepartment, false,
        ['id' => 'wl-dept', 'class' => 'form-select', 'style' => 'width:100%;']);
    echo '</div>';

    echo '<div style="' . $f . '">';
    echo html_writer::tag('label', get_string('institution'),
        ['for' => 'wl-inst', 'class' => 'form-label fw-semibold mb-1 d-block']);
    echo html_writer::select($instOpts, 'filterinstitution', $filterinstitution, false,
        ['id' => 'wl-inst', 'class' => 'form-select', 'style' => 'width:100%;']);
    echo '</div>';

    echo '<div style="' . $f . '">';
    echo html_writer::tag('label', get_string('filterbycategory', 'block_workload'),
        ['for' => 'wl-cat', 'class' => 'form-label fw-semibold mb-1 d-block']);
    echo html_writer::select($catOpts, 'filtercategory', $filtercategory, false,
        ['id' => 'wl-cat', 'class' => 'form-select', 'style' => 'width:100%;']);
    echo '</div>';

    echo '</div>';

    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('filterresults', 'block_workload'),
        'class' => 'btn btn-primary me-2',
    ]);
    if ($hassearch) {
        echo html_writer::link(
            new moodle_url('/blocks/workload/manage.php', ['action' => 'members', 'id' => $id]),
            get_string('clearfilters', 'block_workload'),
            ['class' => 'btn btn-outline-secondary']
        );
    }
    echo html_writer::end_tag('form');

    // ---- Search results -------------------------------------------
    if ($hassearch) {
        $params = ['deleted' => 0, 'notsite' => SITEID];
        $where  = ['u.deleted = :deleted', 'u.id <> :notsite'];

        if ($search !== '') {
            $where[]         = '(' . $DB->sql_like($DB->sql_concat('u.firstname', "' '", 'u.lastname'), ':name', false)
                             . ' OR ' . $DB->sql_like('u.email', ':email', false) . ')';
            $params['name']  = '%' . $DB->sql_like_escape($search) . '%';
            $params['email'] = '%' . $DB->sql_like_escape($search) . '%';
        }
        if ($filterdepartment !== '') {
            $where[]        = 'u.department = :dept';
            $params['dept'] = $filterdepartment;
        }
        if ($filterinstitution !== '') {
            $where[]        = 'u.institution = :inst';
            $params['inst'] = $filterinstitution;
        }
        if ($filtercategory > 0) {
            $catids = \block_workload\helper::get_all_subcategory_ids($filtercategory);
            [$catsql, $catparams] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'ccat');
            $where[]  = "EXISTS (
                SELECT 1 FROM {user_enrolments} ue
                  JOIN {enrol} en ON en.id = ue.enrolid
                  JOIN {course} co ON co.id = en.courseid
                 WHERE ue.userid = u.id AND co.category $catsql
            )";
            $params = array_merge($params, $catparams);
        }

        // Select all name fields fullname() requires to avoid debug warnings.
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, u.institution,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                  FROM {user} u
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY u.lastname ASC, u.firstname ASC";
        $found = $DB->get_records_sql($sql, $params, 0, 200);

        echo html_writer::tag('hr', '');

        if (empty($found)) {
            echo $OUTPUT->notification(get_string('nouserfound', 'block_workload'), 'info');
        } else {
            $alreadycount = count(array_filter($found, fn($u) => in_array((int)$u->id, $allmemberids)));
            echo html_writer::tag('p',
                get_string('searchresultcount', 'block_workload', count($found))
                . ($alreadycount ? ', ' . get_string('alreadymembercount', 'block_workload', $alreadycount) : '')
                . (count($found) >= 200 ? ' ' . get_string('searchresultlimit', 'block_workload') : ''),
                ['class' => 'text-muted small mb-2']
            );

            echo html_writer::start_tag('form', ['method' => 'post', 'action' => '']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'members']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id',      'value' => $id]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

            $table             = new html_table();
            $table->head       = [
                html_writer::empty_tag('input', [
                    'type'             => 'checkbox',
                    'data-action'      => 'toggle',
                    'data-togglegroup' => 'addmembers-group',
                    'data-toggle'      => 'master',
                    'title'            => get_string('selectall'),
                    'id'               => 'wl-add-master',
                ]),
                get_string('fullnameuser', 'moodle'),
                get_string('email'),
                get_string('department'),
                get_string('institution'),
            ];
            $table->colclasses = ['c0 shrink', '', '', '', ''];
            $table->attributes = ['class' => 'generaltable table-sm'];

            foreach ($found as $u) {
                $already = in_array((int)$u->id, $allmemberids);

                $cbcell = new html_table_cell();
                $cbcell->attributes['class'] = 'c0 shrink';
                $cbcell->text = $already
                    ? html_writer::tag('span', '&#10003;',
                        ['class' => 'text-success fw-bold', 'title' => get_string('alreadymember', 'block_workload')])
                    : html_writer::empty_tag('input', [
                        'type'             => 'checkbox',
                        'name'             => 'addusers[]',
                        'value'            => $u->id,
                        'checked'          => 'checked',
                        'data-action'      => 'toggle',
                        'data-togglegroup' => 'addmembers-group',
                        'data-toggle'      => 'slave',
                    ]);

                $row = new html_table_row();
                if ($already) {
                    $row->attributes['class'] = 'text-muted';
                }
                $row->cells = [$cbcell, fullname($u), $u->email, s($u->department), s($u->institution)];
                $table->data[] = $row;
            }
            echo html_writer::table($table);

            echo html_writer::empty_tag('input', [
                'type'  => 'submit',
                'value' => get_string('addselected', 'block_workload'),
                'class' => 'btn btn-primary mt-2',
            ]);
            echo html_writer::end_tag('form');
        }
    }

    // ---- Import from Moodle system cohort (optional, admin-controlled) ------
    if ($importenabled) {
        echo html_writer::tag('hr', '');
        echo html_writer::tag('h5',
            get_string('importfrommoodlecohort', 'block_workload'),
            ['class' => 'card-title mt-0 mb-3']
        );

        $moodlecohorts = $DB->get_records('cohort', null, 'name ASC', 'id, name, idnumber');

        if (empty($moodlecohorts)) {
            echo $OUTPUT->notification(get_string('nomoodlecohorts', 'block_workload'), 'info');
        } else {
            // Cohort picker form (GET so the selection survives page load).
            echo html_writer::start_tag('form', [
                'method' => 'get', 'action' => '',
                'class'  => 'd-flex align-items-end gap-2 flex-wrap mb-3',
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'members']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id',      'value' => $id]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'page',    'value' => $page]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'perpage', 'value' => $perpage]);

            $cohortOpts = [0 => get_string('selectmoodlecohort', 'block_workload')];
            foreach ($moodlecohorts as $mc) {
                $label = format_string($mc->name);
                if ($mc->idnumber !== '') {
                    $label .= ' [' . s($mc->idnumber) . ']';
                }
                $cohortOpts[$mc->id] = $label;
            }

            echo html_writer::start_div('d-flex flex-column');
            echo html_writer::tag('label',
                get_string('moodlecohortlabel', 'block_workload'),
                ['for' => 'wl-moodlecohort', 'class' => 'form-label fw-semibold mb-1 d-block']
            );
            echo html_writer::select($cohortOpts, 'moodlecohortid', $moodlecohortid, false,
                ['id' => 'wl-moodlecohort', 'class' => 'form-select', 'style' => 'min-width:260px;']
            );
            echo html_writer::end_div();

            echo html_writer::empty_tag('input', [
                'type'  => 'submit',
                'value' => get_string('filterresults', 'block_workload'),
                'class' => 'btn btn-secondary align-self-end',
            ]);
            echo html_writer::end_tag('form');

            // Show members of the selected Moodle cohort.
            if ($moodlecohortid > 0 && array_key_exists($moodlecohortid, $moodlecohorts)) {
                $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, u.institution,
                               u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                          FROM {user} u
                          JOIN {cohort_members} cm ON cm.userid = u.id
                         WHERE cm.cohortid = :cohortid
                           AND u.deleted = 0
                           AND u.id <> :siteid
                      ORDER BY u.lastname ASC, u.firstname ASC";
                $moodlemembers = $DB->get_records_sql($sql, [
                    'cohortid' => $moodlecohortid,
                    'siteid'   => SITEID,
                ]);

                if (empty($moodlemembers)) {
                    echo $OUTPUT->notification(get_string('nomoodlecohortmembers', 'block_workload'), 'info');
                } else {
                    $alreadycount = count(array_filter($moodlemembers,
                        fn($u) => in_array((int)$u->id, $allmemberids)
                    ));

                    echo html_writer::tag('p',
                        get_string('moodlecohortmembers', 'block_workload', count($moodlemembers))
                        . ($alreadycount
                            ? ', ' . get_string('alreadymembercount', 'block_workload', $alreadycount)
                            : ''),
                        ['class' => 'text-muted small mb-2']
                    );

                    echo html_writer::start_tag('form', ['method' => 'post', 'action' => '', 'id' => 'wl-mcimport-form']);
                    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'members']);
                    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id',      'value' => $id]);
                    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

                    $mctable             = new html_table();
                    $mctable->head       = [
                        html_writer::empty_tag('input', [
                            'type'             => 'checkbox',
                            'data-action'      => 'toggle',
                            'data-togglegroup' => 'mcimport-group',
                            'data-toggle'      => 'master',
                            'title'            => get_string('selectall'),
                            'id'               => 'wl-mc-master',
                        ]),
                        get_string('fullnameuser', 'moodle'),
                        get_string('email'),
                        get_string('department'),
                        get_string('institution'),
                    ];
                    $mctable->colclasses = ['c0 shrink', '', '', '', ''];
                    $mctable->attributes = ['class' => 'generaltable table-sm'];

                    foreach ($moodlemembers as $u) {
                        $already = in_array((int)$u->id, $allmemberids);

                        $cbcell = new html_table_cell();
                        $cbcell->attributes['class'] = 'c0 shrink';
                        $cbcell->text = $already
                            ? html_writer::tag('span', '&#10003;',
                                ['class' => 'text-success fw-bold',
                                 'title' => get_string('alreadymember', 'block_workload')])
                            : html_writer::empty_tag('input', [
                                'type'             => 'checkbox',
                                'name'             => 'addusers[]',
                                'value'            => $u->id,
                                'checked'          => 'checked',
                                'data-action'      => 'toggle',
                                'data-togglegroup' => 'mcimport-group',
                                'data-toggle'      => 'slave',
                            ]);

                        $row = new html_table_row();
                        if ($already) {
                            $row->attributes['class'] = 'text-muted';
                        }
                        $row->cells = [$cbcell, fullname($u), $u->email, s($u->department), s($u->institution)];
                        $mctable->data[] = $row;
                    }
                    echo html_writer::table($mctable);

                    echo html_writer::tag('button', get_string('importselected', 'block_workload'), [
                        'type'  => 'submit',
                        'class' => 'btn btn-primary mt-2',
                    ]);
                    echo html_writer::end_tag('form');

                    echo html_writer::script("
(function() {
    var form = document.getElementById('wl-mcimport-form');
    if (!form) return;
    var btn = form.querySelector('button[type=\"submit\"]');
    function update() {
        var any = Array.prototype.some.call(
            form.querySelectorAll('input[name=\"addusers[]\"]'),
            function(cb) { return cb.checked; }
        );
        btn.disabled = !any;
    }
    form.addEventListener('change', update);
    form.addEventListener('click', function() { setTimeout(update, 0); });
    update();
})();
                    ");
                }
            }
        }
    }

    echo '</div>'; // card-body
    echo '</div>'; // card
    echo '</div>'; // #workload-addpanel

    // =========================================================================
    // CURRENT MEMBERS TABLE
    // =========================================================================
    echo html_writer::tag('h5', get_string('currentmembers', 'block_workload'));

    // A-Z first/last name filter bars (matching Moodle course participants style).
    $basealphaurl = new moodle_url('/blocks/workload/manage.php', [
        'action'  => 'members',
        'id'      => $id,
        'perpage' => $perpage,
        'page'    => 0,
    ]);

    // A-Z bars using exact Moodle participants pagination markup.
    foreach ([
        ['firstinitial', get_string('firstname'), 'firstletter', $firstletter, $lastletter],
        ['lastinitial',  get_string('lastname'),  'lastletter',  $lastletter,  $firstletter],
    ] as [$bartype, $barlabel, $param, $current, $other]) {
        $otherparam = ($param === 'firstletter') ? 'lastletter' : 'firstletter';
        echo '<div class="initialbar ' . $bartype . ' d-flex flex-wrap justify-content-center justify-content-md-start mb-2">';
        echo '<span class="initialbarlabel me-2">' . $barlabel . '</span>';
        echo '<nav class="initialbargroups d-flex flex-wrap justify-content-center justify-content-md-start">';

        // "All" in its own UL.
        $u = clone $basealphaurl; $u->param($param, ''); $u->param($otherparam, $other);
        echo '<ul class="pagination pagination-sm">';
        echo '<li id="' . $bartype . '_page-item_All" class="initialbarall page-item' . ($current === '' ? ' active' : '') . '">';
        echo '<a data-initial="" class="page-link" href="' . $u->out() . '"' . ($current === '' ? ' aria-current="true"' : '') . '>' . get_string('all') . '</a>';
        echo '</li></ul>';

        // Letters from the current language alphabet in a second UL.
        echo '<ul class="pagination pagination-sm">';
        foreach ($alphabet as $l) {
            $u2 = clone $basealphaurl; $u2->param($param, $l); $u2->param($otherparam, $other);
            $active = ($current === $l);
            echo '<li id="' . $bartype . '_page-item_' . $l . '" data-initial="' . $l . '" class="page-item ' . $l . ($active ? ' active' : '') . '">';
            echo '<a class="page-link" href="' . $u2->out() . '"' . ($active ? ' aria-current="true"' : '') . '>' . $l . '</a>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</nav></div>';
    }

    // Per-page switcher – matches Moodle's standard "X per page" pattern.
    $pplinks = [];
    foreach ([25, 50, 100] as $pp) {
        $pplinks[] = ($pp == $perpage)
            ? html_writer::tag('strong', $pp)
            : html_writer::link(
                new moodle_url('/blocks/workload/manage.php',
                    ['action' => 'members', 'id' => $id, 'perpage' => $pp, 'page' => 0,
                     'firstletter' => $firstletter, 'lastletter' => $lastletter]),
                $pp
              );
    }
    $pplinks[] = ($perpage === 0)
        ? html_writer::tag('strong', get_string('showall', 'block_workload', $totalcount))
        : html_writer::link(
            new moodle_url('/blocks/workload/manage.php',
                ['action' => 'members', 'id' => $id, 'perpage' => 0, 'page' => 0,
                 'firstletter' => $firstletter, 'lastletter' => $lastletter]),
            get_string('showall', 'block_workload', $totalcount)
          );

    echo html_writer::div(
        get_string('perpage', 'block_workload') . ': ' . implode(' | ', $pplinks),
        'text-muted small mb-2'
    );

    if ($totalcount === 0) {
        echo $OUTPUT->notification(get_string('nouserfound', 'block_workload'), 'info');
        echo $OUTPUT->footer();
        return;
    }

    // Paging bar (top).
    $pagingurl = new moodle_url('/blocks/workload/manage.php',
        ['action' => 'members', 'id' => $id, 'perpage' => $perpage,
         'firstletter' => $firstletter, 'lastletter' => $lastletter]);
    if ($perpage > 0 && $totalcount > $perpage) {
        echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $pagingurl);
    }

    // Fetch department/institution for the displayed page.
    if (!empty($members)) {
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($members), SQL_PARAMS_NAMED, 'uid');
        $profiles = $DB->get_records_sql(
            "SELECT id, department, institution FROM {user} WHERE id $insql", $inparams
        );
    } else {
        $profiles = [];
    }

    // Bulk-remove form.
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => '', 'id' => 'wl-members-form']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'members']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id',      'value' => $id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    $table             = new html_table();
    $table->head       = [
        html_writer::empty_tag('input', [
            'type'             => 'checkbox',
            'data-action'      => 'toggle',
            'data-togglegroup' => 'removemembers-group',
            'data-toggle'      => 'master',
            'title'            => get_string('selectall'),
            'id'               => 'wl-remove-master',
        ]),
        get_string('fullnameuser', 'moodle'),
        get_string('email'),
        get_string('department'),
        get_string('institution'),
        '',
    ];
    $table->colclasses = ['c0 shrink', '', '', '', '', 'shrink'];
    $table->attributes = ['class' => 'generaltable table-sm'];

    foreach ($members as $m) {
        $profile = $profiles[$m->id] ?? null;

        $cbcell = new html_table_cell();
        $cbcell->attributes['class'] = 'c0 shrink';
        $cbcell->text = html_writer::empty_tag('input', [
            'type'             => 'checkbox',
            'name'             => 'removeusers[]',
            'value'            => $m->id,
            'data-action'      => 'toggle',
            'data-togglegroup' => 'removemembers-group',
            'data-toggle'      => 'slave',
        ]);

        $removeurl = new moodle_url('/blocks/workload/manage.php', [
            'action'          => 'members',
            'id'              => $id,
            'confirmremoveid' => $m->id,
            'firstletter'     => $firstletter,
            'lastletter'      => $lastletter,
            'page'            => $page,
            'perpage'         => $perpage,
        ]);
        $removecell = new html_table_cell();
        $removecell->attributes['class'] = 'shrink';
        $removecell->text = html_writer::link(
            $removeurl,
            $OUTPUT->pix_icon('t/delete', get_string('delete')),
            ['title' => get_string('delete')]
        );

        $row = new html_table_row();
        $row->cells = [
            $cbcell,
            fullname($m),
            $m->email,
            $profile ? s($profile->department)  : '',
            $profile ? s($profile->institution) : '',
            $removecell,
        ];
        $table->data[] = $row;
    }
    echo html_writer::table($table);

    echo html_writer::tag('button', get_string('bulkremove', 'block_workload'), [
        'type'     => 'submit',
        'class'    => 'btn btn-secondary',
        'disabled' => 'disabled',
    ]);
    echo html_writer::end_tag('form');

    echo html_writer::script("
(function() {
    var form = document.getElementById('wl-members-form');
    if (!form) return;
    var btn = form.querySelector('button[type=\"submit\"]');
    function update() {
        var any = Array.prototype.some.call(
            form.querySelectorAll('input[name=\"removeusers[]\"]'),
            function(cb) { return cb.checked; }
        );
        btn.disabled = !any;
    }
    form.addEventListener('change', update);
    form.addEventListener('click', function() { setTimeout(update, 0); });
    update();
})();
    ");

    // Paging bar (bottom).
    if ($perpage > 0 && $totalcount > $perpage) {
        echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $pagingurl);
    }

    echo $OUTPUT->footer();
}

// =========================================================================
// Action: manage courses.
// =========================================================================
function action_courses(int $id): void {
    global $DB, $OUTPUT, $PAGE;

    $cohort = $DB->get_record('block_workload_cohorts', ['id' => $id], '*', MUST_EXIST);

    // Single remove: show Moodle confirmation page.
    $confirmremoveid = optional_param('confirmremoveid', 0, PARAM_INT);
    if ($confirmremoveid) {
        $rec    = $DB->get_record('block_workload_courses', ['id' => $confirmremoveid, 'cohortid' => $id], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $rec->courseid], 'id, fullname', MUST_EXIST);
        $yesurl = new moodle_url('/blocks/workload/manage.php', [
            'action'   => 'courses', 'id' => $id,
            'removeid' => $confirmremoveid, 'sesskey' => sesskey(),
        ]);
        $nourl  = new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('coursestitle', 'block_workload', format_string($cohort->name)));
        echo $OUTPUT->confirm(
            get_string('confirmsingleremovecourse', 'block_workload', format_string($course->fullname)),
            $yesurl, $nourl
        );
        echo $OUTPUT->footer();
        return;
    }

    // Single remove: execute (reached via "Continue" on confirmation page).
    $removeid = optional_param('removeid', 0, PARAM_INT);
    if ($removeid && confirm_sesskey()) {
        $DB->delete_records('block_workload_courses', ['id' => $removeid, 'cohortid' => $id]);

        \block_workload\event\courses_removed::create([
            'objectid' => $id,
            'context'  => context_system::instance(),
            'other'    => ['cohortname' => $cohort->name, 'count' => 1],
        ])->trigger();

        redirect(
            new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]),
            get_string('courseremoved', 'block_workload'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Bulk remove: execute confirmed (reached via "Continue" on confirmation page).
    $confirmbulkids = optional_param('confirmbulkids', '', PARAM_SEQUENCE);
    $dobulkremove   = optional_param('dobulkremove',   0,  PARAM_BOOL);
    if ($confirmbulkids !== '' && $dobulkremove && confirm_sesskey()) {
        $ids     = array_filter(array_map('intval', explode(',', $confirmbulkids)));
        $removed = 0;
        foreach ($ids as $aid) {
            $DB->delete_records('block_workload_courses', ['id' => $aid, 'cohortid' => $id]);
            $removed++;
        }

        \block_workload\event\courses_removed::create([
            'objectid' => $id,
            'context'  => context_system::instance(),
            'other'    => ['cohortname' => $cohort->name, 'count' => $removed],
        ])->trigger();

        redirect(
            new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]),
            get_string('coursesremoved', 'block_workload', $removed),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Bulk remove: show Moodle confirmation page.
    if ($confirmbulkids !== '' && !$dobulkremove) {
        $ids    = array_filter(array_map('intval', explode(',', $confirmbulkids)));
        $yesurl = new moodle_url('/blocks/workload/manage.php', [
            'action'         => 'courses', 'id' => $id,
            'confirmbulkids' => $confirmbulkids,
            'dobulkremove'   => 1, 'sesskey' => sesskey(),
        ]);
        $nourl  = new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('coursestitle', 'block_workload', format_string($cohort->name)));
        echo $OUTPUT->confirm(
            get_string('confirmbulkremovecourses', 'block_workload', count($ids)),
            $yesurl, $nourl
        );
        echo $OUTPUT->footer();
        return;
    }

    // Bulk remove: intercept POST → redirect to confirmation page.
    $removecourseids = optional_param_array('removecourseids', [], PARAM_INT);
    if (!empty($removecourseids)) {
        $idsstring = implode(',', array_filter(array_map('intval', $removecourseids)));
        redirect(new moodle_url('/blocks/workload/manage.php', [
            'action'         => 'courses',
            'id'             => $id,
            'confirmbulkids' => $idsstring,
        ]));
    }

    // Toggle active flag on a course assignment.
    $toggleid = optional_param('toggleid', 0, PARAM_INT);
    if ($toggleid && confirm_sesskey()) {
        $rec = $DB->get_record('block_workload_courses', ['id' => $toggleid, 'cohortid' => $id], '*', MUST_EXIST);
        $rec->active = $rec->active ? 0 : 1;
        $DB->update_record('block_workload_courses', $rec);

        $coursename = $DB->get_field('course', 'fullname', ['id' => $rec->courseid]);
        \block_workload\event\course_toggled::create([
            'objectid' => $id,
            'context'  => context_system::instance(),
            'other'    => ['cohortname' => $cohort->name, 'coursename' => $coursename, 'active' => $rec->active],
        ])->trigger();

        redirect(new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]),
            get_string('courseupdated', 'block_workload'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // Move a course up or down in the sort order.
    $moveup   = optional_param('moveup',   0, PARAM_INT);
    $movedown = optional_param('movedown', 0, PARAM_INT);
    $moveid   = $moveup ?: $movedown;
    if ($moveid && confirm_sesskey()) {
        // Load all assignments for this cohort in current order.
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
                // Normalise all positions first, then swap the two.
                foreach ($ordered as $j => $r) {
                    $DB->set_field('block_workload_courses', 'sortorder', $j, ['id' => $r->id]);
                }
                $DB->set_field('block_workload_courses', 'sortorder', $swapidx, ['id' => $ordered[$i]->id]);
                $DB->set_field('block_workload_courses', 'sortorder', $i,       ['id' => $ordered[$swapidx]->id]);
            }
            break;
        }
        redirect(new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]));
    }

    // Add selected courses from a category.
    $addcourses = optional_param_array('addcourseids', [], PARAM_INT);
    if (!empty($addcourses) && confirm_sesskey()) {
        // Append new courses after the current last position.
        $nextsort = 1 + (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), -1) FROM {block_workload_courses} WHERE cohortid = :cohortid',
            ['cohortid' => $id]
        );
        $added = 0;
        foreach ($addcourses as $cid) {
            if (!$DB->record_exists('block_workload_courses', ['cohortid' => $id, 'courseid' => $cid])) {
                $r              = new stdClass();
                $r->cohortid    = $id;
                $r->courseid    = $cid;
                $r->active      = 1;
                $r->sortorder   = $nextsort++;
                $r->timecreated = time();
                $DB->insert_record('block_workload_courses', $r);
                $added++;
            }
        }

        if ($added > 0) {
            \block_workload\event\courses_assigned::create([
                'objectid' => $id,
                'context'  => context_system::instance(),
                'other'    => ['cohortname' => $cohort->name, 'count' => $added],
            ])->trigger();
        }

        redirect(
            new moodle_url('/blocks/workload/manage.php', ['action' => 'courses', 'id' => $id]),
            get_string('coursesadded', 'block_workload', $added),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $selectedcategory = optional_param('categoryid', 0, PARAM_INT);
    $assignedcourses  = \block_workload\helper::get_cohort_courses_all($id);
    $assignedids      = array_map(fn($c) => (int)$c->id, $assignedcourses);
    $catOptions       = \block_workload\helper::get_category_options();

    // Hide sortorder controls when the block is set to "recently accessed" ordering,
    // since the display order is then user-specific and manual ordering has no effect.
    $showordercolumn = (get_config('block_workload', 'courseorder') ?: 'sortorder') === 'sortorder';

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('coursestitle', 'block_workload', format_string($cohort->name)));
    echo html_writer::link(
        new moodle_url('/blocks/workload/manage.php'),
        '&larr; ' . get_string('cohorts', 'block_workload'),
        ['class' => 'btn btn-outline-secondary mb-3']
    );

    // Add courses: category browser.
    echo html_writer::tag('h5', get_string('addcourses', 'block_workload'));

    echo html_writer::start_tag('form', ['method' => 'get', 'action' => '', 'class' => 'd-flex gap-2 align-items-end flex-wrap mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'courses']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id',     'value' => $id]);
    echo html_writer::start_div('d-flex flex-column');
    echo html_writer::tag('label', get_string('filterbycategory', 'block_workload'), ['for' => 'catsel', 'class' => 'form-label small']);
    $catopts = [0 => get_string('selectcategory', 'block_workload')] + $catOptions;
    echo html_writer::select($catopts, 'categoryid', $selectedcategory, false, ['id' => 'catsel', 'class' => 'form-select form-select-sm', 'style' => 'min-width:250px;']);
    echo html_writer::end_div();
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filterresults', 'block_workload'), 'class' => 'btn btn-secondary align-self-end']);
    echo html_writer::end_tag('form');

    if ($selectedcategory) {
        $catcourses = \block_workload\helper::get_courses_in_category($selectedcategory);
        if (empty($catcourses)) {
            echo html_writer::tag('p', get_string('nocoursesincategory', 'block_workload'), ['class' => 'text-muted']);
        } else {
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => '', 'id' => 'wl-addcourses-form']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'courses']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id',      'value' => $id]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

            // Summary line: "X course(s) found, Y already assigned".
            $alreadycount = count(array_filter($catcourses, fn($c) => in_array((int)$c->id, $assignedids)));
            echo html_writer::tag('p',
                get_string('courseresultcount', 'block_workload', count($catcourses))
                . ($alreadycount ? ', ' . get_string('alreadyassignedcount', 'block_workload', $alreadycount) : ''),
                ['class' => 'text-muted small mb-2']
            );

            $PAGE->requires->js_call_amd('core/checkbox-toggleall', 'init');

            // Header: master checkbox – use attributes['class'] (never replace the whole array).
            $masterth = new html_table_cell();
            $masterth->header = true;
            $masterth->attributes['class'] = 'c0 shrink';
            $masterth->text = html_writer::empty_tag('input', [
                'type'             => 'checkbox',
                'id'               => 'wl-addcourses-master',
                'data-action'      => 'toggle',
                'data-togglegroup' => 'addcourses-group',
                'data-toggle'      => 'master',
                'title'            => get_string('selectall'),
                'checked'          => 'checked',
            ]);

            $table = new html_table();
            $table->head       = [
                $masterth,
                get_string('course',          'block_workload'),
                get_string('coursestartdate', 'block_workload'),
                get_string('courseenddate',   'block_workload'),
            ];
            $table->attributes = ['class' => 'generaltable table-sm table-hover'];

            foreach ($catcourses as $c) {
                $already = in_array((int)$c->id, $assignedids);

                // Checkbox / tick cell – match members panel exactly: class only, no inline style,
                // no form-check-input on the <input> (that adds Bootstrap margins that break alignment).
                $cbcell = new html_table_cell();
                $cbcell->attributes['class'] = 'c0 shrink';
                $cbcell->text = $already
                    ? html_writer::tag('span', '&#10003;',
                        ['class' => 'text-success fw-bold', 'title' => get_string('alreadymember', 'block_workload')])
                    : html_writer::empty_tag('input', [
                        'type'             => 'checkbox',
                        'name'             => 'addcourseids[]',
                        'value'            => $c->id,
                        'checked'          => 'checked',
                        'data-action'      => 'toggle',
                        'data-togglegroup' => 'addcourses-group',
                        'data-toggle'      => 'slave',
                    ]);

                // Course name cell with link.
                $courseurl = new moodle_url('/course/view.php', ['id' => $c->id]);
                $namecell  = new html_table_cell();
                $namecell->text = html_writer::link($courseurl, format_string($c->fullname), ['target' => '_blank'])
                    . html_writer::tag('span', ' (' . $c->shortname . ')', ['class' => 'text-muted small']);

                $disabled = html_writer::tag('span',
                    get_string('datedisabled', 'block_workload'),
                    ['class' => 'text-muted small']
                );
                $startcell = new html_table_cell();
                $startcell->text = !empty($c->startdate)
                    ? userdate($c->startdate, get_string('strftimedate', 'langconfig'))
                    : $disabled;
                $endcell = new html_table_cell();
                $endcell->text = !empty($c->enddate)
                    ? userdate($c->enddate, get_string('strftimedate', 'langconfig'))
                    : $disabled;

                $row = new html_table_row();
                if ($already) {
                    $row->attributes['class'] = 'text-muted';
                }
                $row->cells    = [$cbcell, $namecell, $startcell, $endcell];
                $table->data[] = $row;
            }
            echo html_writer::table($table);
            echo html_writer::tag('button', get_string('addcourses', 'block_workload'), [
                'type'  => 'submit',
                'class' => 'btn btn-primary',
            ]);
            echo html_writer::end_tag('form');

            echo html_writer::script("
(function() {
    var form = document.getElementById('wl-addcourses-form');
    if (!form) return;
    var btn = form.querySelector('button[type=\"submit\"]');
    function update() {
        var any = Array.prototype.some.call(
            form.querySelectorAll('input[name=\"addcourseids[]\"]'),
            function(cb) { return cb.checked; }
        );
        btn.disabled = !any;
    }
    form.addEventListener('change', update);
    form.addEventListener('click', function() { setTimeout(update, 0); });
    update();
})();
            ");
        }
    }

    // Assigned courses table.
    echo html_writer::tag('h5',
        get_string('assignedcourses', 'block_workload') .
        (!empty($assignedcourses)
            ? html_writer::tag('span', ' (' . count($assignedcourses) . ')',
                ['class' => 'text-muted fw-normal small ms-1'])
            : ''),
        ['class' => 'mt-4']);
    if (empty($assignedcourses)) {
        echo html_writer::tag('p', get_string('nocoursesincategory', 'block_workload'), ['class' => 'text-muted']);
    } else {
        $PAGE->requires->js_call_amd('core/checkbox-toggleall', 'init');

        // Master checkbox header cell.
        $masterth = new html_table_cell();
        $masterth->header = true;
        $masterth->attributes['class'] = 'c0 shrink';
        $masterth->text = html_writer::empty_tag('input', [
            'type'             => 'checkbox',
            'id'               => 'wl-courses-remove-master',
            'data-action'      => 'toggle',
            'data-togglegroup' => 'removecourses-group',
            'data-toggle'      => 'master',
            'title'            => get_string('selectall'),
        ]);

        $table = new html_table();
        $tableheads = [
            $masterth,
            get_string('course',          'block_workload'),
            get_string('coursestartdate', 'block_workload'),
            get_string('courseenddate',   'block_workload'),
            get_string('activecourse',    'block_workload'),
        ];
        if ($showordercolumn) {
            $tableheads[] = get_string('order', 'block_workload');
        }
        $tableheads[]      = get_string('actions', 'block_workload');
        $table->head       = $tableheads;
        $table->attributes = ['class' => 'generaltable table-sm table-striped'];

        $assignedlist = array_values($assignedcourses);
        $lastidx      = count($assignedlist) - 1;
        $spacer = html_writer::tag('span', '', ['style' => 'display:inline-block;width:1.4rem;']);

        foreach ($assignedlist as $idx => $c) {
            $confirmremoveurl = new moodle_url('/blocks/workload/manage.php', [
                'action'          => 'courses', 'id' => $id,
                'confirmremoveid' => $c->assignid,
            ]);
            $toggleurl   = new moodle_url('/blocks/workload/manage.php', [
                'action'   => 'courses', 'id' => $id,
                'toggleid' => $c->assignid, 'sesskey' => sesskey(),
            ]);
            $moveupurl   = new moodle_url('/blocks/workload/manage.php', [
                'action'  => 'courses', 'id' => $id,
                'moveup'  => $c->assignid, 'sesskey' => sesskey(),
            ]);
            $movedownurl = new moodle_url('/blocks/workload/manage.php', [
                'action'    => 'courses', 'id' => $id,
                'movedown'  => $c->assignid, 'sesskey' => sesskey(),
            ]);

            $cbcell = new html_table_cell();
            $cbcell->attributes['class'] = 'c0 shrink';
            $cbcell->text = html_writer::empty_tag('input', [
                'type'             => 'checkbox',
                'name'             => 'removecourseids[]',
                'value'            => $c->assignid,
                'data-action'      => 'toggle',
                'data-togglegroup' => 'removecourses-group',
                'data-toggle'      => 'slave',
            ]);

            $disabled = html_writer::tag('span',
                get_string('datedisabled', 'block_workload'),
                ['class' => 'text-muted small']
            );
            $startdate = !empty($c->startdate)
                ? userdate($c->startdate, get_string('strftimedate', 'langconfig'))
                : $disabled;
            $enddate = !empty($c->enddate)
                ? userdate($c->enddate, get_string('strftimedate', 'langconfig'))
                : $disabled;

            $cells = [
                $cbcell,
                html_writer::link(
                    new moodle_url('/course/view.php', ['id' => $c->id]),
                    format_string($c->fullname),
                    ['target' => '_blank']
                ) . html_writer::tag('span', ' (' . $c->shortname . ')', ['class' => 'text-muted small']),
                $startdate,
                $enddate,
                $c->active
                    ? $OUTPUT->action_icon($toggleurl, new pix_icon('t/hide', get_string('deactivate', 'block_workload')))
                    : $OUTPUT->action_icon($toggleurl, new pix_icon('t/show', get_string('activate',   'block_workload'))),
            ];

            if ($showordercolumn) {
                $upicon   = ($idx > 0)
                    ? $OUTPUT->action_icon($moveupurl,   new pix_icon('t/up',   get_string('moveup',   'moodle')))
                    : $spacer;
                $downicon = ($idx < $lastidx)
                    ? $OUTPUT->action_icon($movedownurl, new pix_icon('t/down', get_string('movedown', 'moodle')))
                    : $spacer;
                $cells[] = $upicon . $downicon;
            }

            $cells[] = $OUTPUT->action_icon($confirmremoveurl, new pix_icon('t/delete', get_string('removecourse', 'block_workload')));

            $row = new html_table_row();
            $row->cells    = $cells;
            $table->data[] = $row;
        }

        echo html_writer::start_tag('form', ['method' => 'post', 'action' => '', 'id' => 'wl-removecourses-form']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'courses']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id',      'value' => $id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::table($table);
        echo html_writer::tag('button', get_string('bulkremovecourses', 'block_workload'), [
            'type'     => 'submit',
            'class'    => 'btn btn-secondary mt-2',
            'disabled' => 'disabled',
        ]);
        echo html_writer::end_tag('form');

        echo html_writer::script("
(function() {
    var form = document.getElementById('wl-removecourses-form');
    if (!form) return;
    var btn = form.querySelector('button[type=\"submit\"]');
    function update() {
        var any = Array.prototype.some.call(
            form.querySelectorAll('input[name=\"removecourseids[]\"]'),
            function(cb) { return cb.checked; }
        );
        btn.disabled = !any;
    }
    form.addEventListener('change', update);
    form.addEventListener('click', function() { setTimeout(update, 0); });
    update();
})();
        ");
    }

    echo $OUTPUT->footer();
}

// =========================================================================
// Action: activation settings.
// =========================================================================
// Action: activation settings.
// =========================================================================
function action_activation(int $id): void {
    global $DB, $OUTPUT, $PAGE;

    $modal  = optional_param('modal', 0, PARAM_BOOL);
    $cohort = $DB->get_record('block_workload_cohorts', ['id' => $id], '*', MUST_EXIST);

    if ($modal) {
        $PAGE->set_pagelayout('embedded');
    }

    // Derive the current mode from stored values.
    // 'inactive' | 'always' | 'period'
    $currentMode = !$cohort->active ? 'inactive'
        : (!$cohort->week_from && !$cohort->week_to ? 'always' : 'period');

    // Handle save.
    if (optional_param('saveactivation', 0, PARAM_BOOL) && confirm_sesskey()) {
        $mode     = optional_param('activation_mode', 'always', PARAM_ALPHA);
        $weekfrom = optional_param('week_from', 0, PARAM_INT);
        $yearfrom = optional_param('year_from', 0, PARAM_INT);
        $weekto   = optional_param('week_to',   0, PARAM_INT);
        $yearto   = optional_param('year_to',   0, PARAM_INT);

        $cohort->active    = ($mode !== 'inactive') ? 1 : 0;
        $cohort->week_from = ($mode === 'period') ? ($weekfrom ?: null) : null;
        $cohort->year_from = ($mode === 'period') ? ($yearfrom ?: null) : null;
        $cohort->week_to   = ($mode === 'period') ? ($weekto   ?: null) : null;
        $cohort->year_to   = ($mode === 'period') ? ($yearto   ?: null) : null;
        $cohort->timemodified = time();
        $DB->update_record('block_workload_cohorts', $cohort);

        \block_workload\event\activation_updated::create([
            'objectid' => $id,
            'context'  => context_system::instance(),
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

    $isActive    = \block_workload\helper::is_workload_active($id);
    $currentWeek = (int) date('W');
    $currentYear = (int) date('o');

    echo $OUTPUT->header();
    if (!$modal) {
        echo $OUTPUT->heading(get_string('activationtitle', 'block_workload', format_string($cohort->name)));
        echo html_writer::link(
            new moodle_url('/blocks/workload/manage.php'),
            '&larr; ' . get_string('cohorts', 'block_workload'),
            ['class' => 'btn btn-outline-secondary mb-3']
        );
    } else {
        echo html_writer::tag('p',
            html_writer::tag('strong', format_string($cohort->name)) .
            html_writer::tag('span',
                ' &ndash; ' . format_string($cohort->degree_program),
                ['class' => 'text-muted']
            ),
            ['class' => 'mb-3']
        );
    }

    // Current status badge.
    echo html_writer::div(
        get_string('currentstatus', 'block_workload') . ': ' .
        html_writer::tag('span',
            $isActive ? get_string('statusactive', 'block_workload') : get_string('statusinactive', 'block_workload'),
            ['class' => 'badge rounded-pill ' . ($isActive ? 'bg-success text-white' : 'bg-secondary text-white')]
        ),
        'mb-3'
    );

    $formclass = $modal ? 'p-3' : 'card p-3';
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => '', 'class' => $formclass, 'style' => 'max-width:480px;']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',         'value' => 'activation']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id',             'value' => $id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'modal',          'value' => (int)$modal]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',        'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'saveactivation', 'value' => '1']);

    // --- Radio buttons: three explicit states ---
    $radios = [
        'inactive' => get_string('statusinactive',  'block_workload'),
        'always'   => get_string('alwaysactive',    'block_workload'),
        'period'   => get_string('activationperiod','block_workload'),
    ];
    echo html_writer::tag('p', get_string('activationperiod', 'block_workload') . ':', ['class' => 'fw-semibold mb-2']);
    foreach ($radios as $val => $label) {
        $attrs = [
            'type'  => 'radio',
            'name'  => 'activation_mode',
            'value' => $val,
            'id'    => 'mode_' . $val,
            'class' => 'form-check-input',
        ];
        if ($currentMode === $val) {
            $attrs['checked'] = 'checked';
        }
        echo html_writer::start_div('form-check mb-2');
        echo html_writer::empty_tag('input', $attrs);
        echo html_writer::tag('label', $label, ['for' => 'mode_' . $val, 'class' => 'form-check-label']);
        echo html_writer::end_div();
    }

    // --- Period fields (shown only when 'period' radio is selected) ---
    $periodStyle = ($currentMode === 'period') ? '' : 'display:none;';
    echo html_writer::start_div('mt-3 ps-4 border-start border-2', ['id' => 'wl-period-fields', 'style' => $periodStyle]);
    echo html_writer::start_div('row g-2 mb-2');
    foreach ([
        ['week_from', 'weekfrom', $cohort->week_from ?? $currentWeek],
        ['year_from', 'yearfrom', $cohort->year_from ?? $currentYear],
    ] as [$name, $strkey, $val]) {
        echo html_writer::start_div('col-auto');
        echo html_writer::tag('label', get_string($strkey, 'block_workload'), ['for' => $name, 'class' => 'form-label small mb-1']);
        echo html_writer::empty_tag('input', [
            'type' => 'number', 'name' => $name, 'id' => $name,
            'value' => (int)$val, 'min' => 1,
            'class' => 'form-control', 'style' => 'width:7rem;',
        ]);
        echo html_writer::end_div();
    }
    echo html_writer::end_div(); // row

    echo html_writer::start_div('row g-2');
    foreach ([
        ['week_to', 'weekto', $cohort->week_to ?? $currentWeek],
        ['year_to', 'yearto', $cohort->year_to ?? $currentYear],
    ] as [$name, $strkey, $val]) {
        echo html_writer::start_div('col-auto');
        echo html_writer::tag('label', get_string($strkey, 'block_workload'), ['for' => $name, 'class' => 'form-label small mb-1']);
        echo html_writer::empty_tag('input', [
            'type' => 'number', 'name' => $name, 'id' => $name,
            'value' => (int)$val, 'min' => 1,
            'class' => 'form-control', 'style' => 'width:7rem;',
        ]);
        echo html_writer::end_div();
    }
    echo html_writer::end_div(); // row
    echo html_writer::end_div(); // period fields

    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('save', 'block_workload'), 'class' => 'btn btn-primary mt-3']);
    echo html_writer::end_tag('form');

    // Show/hide period fields based on radio selection.
    echo html_writer::script(
        "document.querySelectorAll('input[name=\"activation_mode\"]').forEach(function(r) {
            r.addEventListener('change', function() {
                document.getElementById('wl-period-fields').style.display =
                    (this.value === 'period') ? '' : 'none';
            });
        });"
    );

    echo $OUTPUT->footer();
}
