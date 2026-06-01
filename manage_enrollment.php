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
 * Quality Manager – enrollment-mode student course management.
 *
 * List view  (no userid): paginated list of all enrolled students.
 * Detail view (userid=X): enrolled courses for one student + manager overrides.
 *
 * State-changing GET actions (require sesskey):
 *   action=exclude    – manager excludes an enrolled course for this student
 *   action=restore    – removes a manager override (restores default)
 *   action=addcourse  – manager force-adds a course (not enrolled)
 *   action=removecourse – removes a force-added course override
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$userid   = optional_param('userid', 0, PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);

// A-Z filters for the student list.
$alphabet    = explode(',', get_string('alphabet', 'langconfig'));
$firstletter = optional_param('firstletter', '', PARAM_RAW);
$lastletter  = optional_param('lastletter', '', PARAM_RAW);
$firstletter = in_array($firstletter, $alphabet, true) ? $firstletter : '';
$lastletter  = in_array($lastletter, $alphabet, true) ? $lastletter : '';
$perpage     = optional_param('perpage', 25, PARAM_INT);
$page        = optional_param('page', 0, PARAM_INT);

// Category browser (for add-course panel in detail view).
$catid   = optional_param('catid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$syscontext = context_system::instance();
require_login();
require_capability('block/workload:manage', $syscontext);

$coursemode = get_config('block_workload', 'coursemode') ?: 'cohort';

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

// State-changing actions – Moodle confirm() pattern.
// Actions that are destructive (exclude, removecourse) show a Moodle.
// Confirmation page first; safe actions (restore, addcourse) execute directly.
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

            // No break needed: fall through.
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

// Bulk-add courses (POST form with checkboxes).
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
        new moodle_url('/blocks/workload/manage_enrollment.php', ['userid' => $userid, 'catid' => $catid]),
        get_string('coursesadded', 'block_workload', $added),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Unified bulk action handler (exclude / restore / remove).
//
// IDs are pre-filtered by course type at intercept time so that:.
// Exclude  → only enrolled courses that are currently included.
// Restore  → only enrolled courses that are currently excluded.
// - remove   → only manager-added courses (not enrolled)
// This means the confirm page always shows the true affected count, and.
// Selecting "all" can never accidentally touch the wrong course type.
$bulkaction = optional_param('bulkaction', '', PARAM_ALPHA);
$courseids  = optional_param_array('courseids', [], PARAM_INT);

if ($bulkaction && $userid && !empty($courseids)) {
    $ids        = array_filter(array_map('intval', $courseids));
    $allcourses = \block_workload\helper::get_user_courses_for_management($userid);
    $detailurl  = new moodle_url('/blocks/workload/manage_enrollment.php', ['userid' => $userid]);

    if ($bulkaction === 'restore') {
        // Safe, no confirm – but only restore enrolled courses that are currently excluded.
        if (confirm_sesskey()) {
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
    }

    if ($bulkaction === 'exclude') {
        // Only enrolled courses that are currently included.
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
        // Only manager-added courses (not enrolled).
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

// Unified bulk confirm + execute (IDs are already pre-filtered above).
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

    // Show Moodle confirm page.
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

// Detail view: one student's courses.
if ($userid) {
    render_student_detail($userid, $catid);
    exit;
}

// List view: all students.
render_student_list($firstletter, $lastletter, $perpage, $page, $alphabet);

// List view renderer.
/**
 * Render the enrollment mode student list page.
 *
 * @param string $firstletter
 * @param string $lastletter
 * @param int $perpage
 * @param int $page
 * @param array $alphabet
 */
function render_student_list(
    string $firstletter,
    string $lastletter,
    int $perpage,
    int $page,
    array $alphabet
): void {
    global $OUTPUT, $PAGE;

    $PAGE->set_title(get_string('enrollmentmodemanage', 'block_workload'));
    $PAGE->set_heading(get_string('enrollmentmodemanage', 'block_workload'));

    $total   = \block_workload\helper::get_enrollment_mode_students_count($firstletter, $lastletter);
    $perpageeff = ($perpage > 0) ? $perpage : 0;
    $offseteff  = ($perpage > 0) ? max(0, $page) * $perpage : 0;
    $students = \block_workload\helper::get_enrollment_mode_students(
        $perpageeff,
        $offseteff,
        $firstletter,
        $lastletter
    );

    echo $OUTPUT->header();

    echo html_writer::div(
        html_writer::link(
            new moodle_url('/blocks/workload/statistics.php'),
            get_string('statisticstitle', 'block_workload'),
            ['class' => 'btn btn-outline-primary']
        ),
        'mb-3 text-end'
    );

    // Live student search.
    $noresultsjson   = json_encode(get_string('nouserfound', 'block_workload'));
    $managecoursesjson = json_encode(get_string('managecourses', 'block_workload') . ' →');

    echo '<div class="card p-3 mb-3">';
    echo '<label class="form-label small fw-semibold mb-2" for="wl-enrol-search">'
        . get_string('searchusers', 'block_workload') . ':</label>';
    echo '<div class="d-flex align-items-center gap-2 flex-wrap">';
    echo '<div class="position-relative" style="flex:1;min-width:260px;max-width:480px;">';
    echo '<input type="text" id="wl-enrol-search" class="form-control"'
        . ' placeholder="' . s(get_string('searchusers', 'block_workload')) . '"'
        . ' autocomplete="off">';
    echo '<ul id="wl-enrol-results" class="list-unstyled mb-0 border rounded bg-white shadow-sm"'
        . ' style="display:none;position:absolute;top:100%;left:0;right:0;z-index:1050;'
        . 'max-height:260px;overflow-y:auto;margin-top:2px;"></ul>';
    echo '</div>';
    echo '<button type="button" id="wl-enrol-clear"'
        . ' class="btn btn-outline-secondary" style="display:none;" title="Clear">'
        . '&#x2715;</button>';
    echo '<a href="#" id="wl-enrol-btn" class="btn btn-primary" style="display:none;">'
        . get_string('managecourses', 'block_workload') . ' &rarr;</a>';
    echo '</div>';
    echo '</div>';

    echo html_writer::script(
        "(function(){" .
        "var inp=document.getElementById('wl-enrol-search')," .
        "list=document.getElementById('wl-enrol-results')," .
        "btn=document.getElementById('wl-enrol-btn')," .
        "clr=document.getElementById('wl-enrol-clear')," .
        "timer=null," .
        "noRes=" . $noresultsjson . ";" .

        "function esc(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');}" .

        "function selectUser(id,fn,em){" .
            "inp.value=fn+' ('+em+')';" .
            "inp.dataset.userid=id;" .
            "list.innerHTML='';list.style.display='none';" .
            "btn.href=M.cfg.wwwroot+'/blocks/workload/manage_enrollment.php?userid='+id;" .
            "btn.style.display='';" .
            "clr.style.display='';" .
        "}" .

        "function clearSearch(){" .
            "inp.value='';inp.dataset.userid='';" .
            "list.innerHTML='';list.style.display='none';" .
            "btn.style.display='none';clr.style.display='none';" .
            "inp.focus();" .
        "}" .

        "function doSearch(q){" .
            "var url=M.cfg.wwwroot+'/blocks/workload/ajax_usersearch.php'" .
                "+'?q='+encodeURIComponent(q);" .
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

    $filterbase = new moodle_url('/blocks/workload/manage_enrollment.php');

    // A-Z letter bars.
    foreach (
        [
        ['firstinitial', get_string('firstname'), 'firstletter', $firstletter, $lastletter],
        ['lastinitial', get_string('lastname'), 'lastletter', $lastletter, $firstletter],
        ] as [$bartype, $barlabel, $param, $current, $other]
    ) {
        $otherparam = ($param === 'firstletter') ? 'lastletter' : 'firstletter';

        echo '<div class="initialbar ' . $bartype
            . ' d-flex flex-wrap justify-content-center justify-content-md-start mb-2">';
        echo '<span class="initialbarlabel me-2">' . $barlabel . '</span>';
        echo '<nav class="initialbargroups d-flex flex-wrap">';

        $u = new moodle_url($filterbase, [$param => '', $otherparam => $other, 'perpage' => $perpage, 'page' => 0]);
        echo '<ul class="pagination pagination-sm"><li class="page-item' . ($current === '' ? ' active' : '') . '">';
        echo '<a class="page-link" href="' . $u->out() . '">' . get_string('all') . '</a></li></ul>';

        echo '<ul class="pagination pagination-sm">';
        foreach ($alphabet as $l) {
            $u2 = new moodle_url($filterbase, [$param => $l, $otherparam => $other, 'perpage' => $perpage, 'page' => 0]);
            $active = ($current === $l);
            echo '<li class="page-item' . ($active ? ' active' : '') . '">';
            echo '<a class="page-link" href="' . $u2->out() . '">' . $l . '</a></li>';
        }
        echo '</ul></nav></div>';
    }

    // Per-page selector.
    $pphtml = html_writer::tag(
        'span',
        get_string('perpage', 'block_workload') . ': ',
        ['class' => 'small fw-semibold me-1']
    );
    foreach ([25 => '25', 50 => '50', 100 => '100', 0 => get_string('showall', 'block_workload', $total)] as $opt => $lbl) {
        $active = ($opt == $perpage);
        $ppparams = ['perpage' => $opt, 'page' => 0, 'firstletter' => $firstletter, 'lastletter' => $lastletter];
        if ($active) {
            $pphtml .= html_writer::tag('strong', $lbl, ['class' => 'me-2']);
        } else {
            $pphtml .= html_writer::link(new moodle_url($filterbase, $ppparams), $lbl, ['class' => 'me-2 small']);
        }
    }
    echo html_writer::div($pphtml, 'mb-3 small');

    if (empty($students)) {
        echo $OUTPUT->notification(get_string('nostudentsfound', 'block_workload'), 'info');
    } else {
        $table             = new html_table();
        $colhead = function (string $colkey): string {
            return html_writer::tag(
                'span',
                get_string($colkey, 'block_workload'),
                ['title' => get_string($colkey . '_title', 'block_workload'),
                 'style' => 'cursor:help; border-bottom:1px dotted currentColor;']
            );
        };

        $table->head       = [
            get_string('student', 'block_workload'),
            get_string('email'),
            $colhead('colenrolled'),
            $colhead('colexcluded'),
            $colhead('coladded'),
            $colhead('coltotal'),
            '',
        ];
        $table->attributes = ['class' => 'generaltable table-sm table-striped table-hover'];

        foreach ($students as $s) {
            $enrolled = (int)$s->enrolledcount;
            $excluded = (int)$s->excludedcount;
            $added    = (int)$s->addedcount;
            $total    = max(0, $enrolled - $excluded + $added);

            $manageurl = new moodle_url('/blocks/workload/manage_enrollment.php', ['userid' => $s->id]);

            $table->data[] = [
                format_string($s->firstname . ' ' . $s->lastname),
                $s->email,
                $enrolled,
                $excluded,
                $added,
                $total,
                html_writer::link(
                    $manageurl,
                    get_string('managecourses', 'block_workload') . ' &rarr;',
                    ['class' => 'btn btn-outline-secondary btn-sm text-nowrap']
                ),
            ];
        }

        echo html_writer::table($table);

        if ($perpage > 0 && $total > $perpage) {
            $pageurl = new moodle_url($filterbase, [
                'firstletter' => $firstletter, 'lastletter' => $lastletter, 'perpage' => $perpage,
            ]);
            echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
        }
    }

    echo $OUTPUT->footer();
}

// Detail view renderer.

/**
 * Render the enrollment mode student detail page.
 *
 * @param int $userid
 * @param int $catid
 */
function render_student_detail(int $userid, int $catid): void {
    global $OUTPUT, $PAGE, $DB;

    $user     = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
    $fullname = fullname($user);

    $PAGE->set_title(get_string('coursestitle', 'block_workload', $fullname));
    $PAGE->set_heading(get_string('coursestitle', 'block_workload', $fullname));
    $PAGE->set_url('/blocks/workload/manage_enrollment.php', ['userid' => $userid]);

    $courses = \block_workload\helper::get_user_courses_for_management($userid);
    $baseurl = new moodle_url('/blocks/workload/manage_enrollment.php');

    echo $OUTPUT->header();
    $PAGE->requires->js_call_amd('core/checkbox-toggleall', 'init');

    // Top bar: back link left, statistics button right.
    echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
    echo html_writer::link(
        new moodle_url('/blocks/workload/manage_enrollment.php'),
        '&larr; ' . get_string('backstudentlist', 'block_workload'),
        ['class' => 'btn btn-outline-secondary']
    );
    echo html_writer::link(
        new moodle_url('/blocks/workload/statistics.php'),
        get_string('statisticstitle', 'block_workload'),
        ['class' => 'btn btn-outline-primary']
    );
    echo html_writer::end_div();

    // Add Course(s) panel first (matches cohort courses page layout).
    echo html_writer::tag('h5', get_string('addcoursesforstudent', 'block_workload'), ['class' => 'mt-2 mb-2']);

    // Category selector (GET form – just updates the page to show the category).
    $catopts = [0 => get_string('selectcategory', 'block_workload')]
             + \block_workload\helper::get_category_options();

    echo html_writer::start_tag('form', ['method' => 'get', 'action' => '', 'class' => 'mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
    echo html_writer::start_div('d-flex align-items-end gap-2 flex-wrap');
    echo html_writer::start_div();
    echo html_writer::tag(
        'label',
        get_string('filterbycategory', 'block_workload'),
        ['for' => 'wl-catid', 'class' => 'form-label small fw-semibold']
    );
    echo html_writer::select(
        $catopts,
        'catid',
        $catid,
        false,
        ['id' => 'wl-catid', 'class' => 'form-select', 'style' => 'min-width:260px;']
    );
    echo html_writer::end_div();
    echo html_writer::start_div('align-self-end');
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('filterresults', 'block_workload'),
        'class' => 'btn btn-secondary',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_tag('form');

    if ($catid > 0) {
        $available = \block_workload\helper::get_courses_in_category($catid);

        if (empty($available)) {
            echo $OUTPUT->notification(get_string('nocoursesincategory', 'block_workload'), 'info');
        } else {
            // Courses already shown (enrolled+not-excluded or force-added with active=1).
            $shownids = array_keys(array_filter(
                $courses,
                fn($c) => $c->enrolled
                    ? ($c->override_active === null || (int)$c->override_active === 1)
                    : (int)$c->override_active === 1
            ));

            $alreadycount = count(array_filter($available, fn($c) => in_array((int)$c->id, $shownids)));
            echo html_writer::tag(
                'p',
                get_string('courseresultcount', 'block_workload', count($available))
                . ($alreadycount ? ', ' . get_string('alreadyassignedcount', 'block_workload', $alreadycount) : ''),
                ['class' => 'small text-muted mb-2']
            );

            // POST form for bulk-add.
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => '']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'catid', 'value' => $catid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bulkaddcourses', 'value' => '1']);

            // Master checkbox header cell.
            $masterth = new html_table_cell();
            $masterth->header = true;
            $masterth->attributes['class'] = 'c0 shrink';
            $masterth->text = html_writer::empty_tag('input', [
                'type'             => 'checkbox',
                'id'               => 'wl-addcourses-master',
                'data-action'      => 'toggle',
                'data-togglegroup' => 'wl-addcourses-group',
                'data-toggle'      => 'master',
                'title'            => get_string('selectall'),
                'checked'          => 'checked',
            ]);

            $tableadd             = new html_table();
            $tableadd->head       = [
                $masterth,
                get_string('course', 'block_workload'),
                get_string('coursestartdate', 'block_workload'),
                get_string('courseenddate', 'block_workload'),
            ];
            $tableadd->attributes = ['class' => 'generaltable table-sm table-hover mb-3'];

            $disabled = html_writer::tag(
                'span',
                get_string('datedisabled', 'block_workload'),
                ['class' => 'text-muted small']
            );

            foreach ($available as $c) {
                $already = in_array((int)$c->id, $shownids);

                $cbcell = new html_table_cell();
                $cbcell->attributes['class'] = 'c0 shrink';
                $cbcell->text = $already
                    ? html_writer::tag(
                        'span',
                        '&#10003;',
                        ['class' => 'text-success fw-bold',
                        'title' => get_string(
                            'alreadyassignedcount',
                            'block_workload',
                            1
                        )]
                    )
                    : html_writer::empty_tag('input', [
                        'type'             => 'checkbox',
                        'name'             => 'addcourseids[]',
                        'value'            => $c->id,
                        'checked'          => 'checked',
                        'data-action'      => 'toggle',
                        'data-togglegroup' => 'wl-addcourses-group',
                        'data-toggle'      => 'slave',
                    ]);

                $courseurl = new moodle_url('/course/view.php', ['id' => $c->id]);
                $namecell  = new html_table_cell();
                $namecell->text = html_writer::link($courseurl, format_string($c->fullname), ['target' => '_blank'])
                    . html_writer::tag('span', ' (' . $c->shortname . ')', ['class' => 'text-muted small']);

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
                $tableadd->data[] = $row;
            }

            echo html_writer::table($tableadd);
            echo html_writer::empty_tag('input', [
                'type'  => 'submit',
                'value' => get_string('addcourses', 'block_workload'),
                'class' => 'btn btn-primary',
            ]);
            echo html_writer::end_tag('form');
        }
    }

    // Assigned courses: unified enrolled + manager-added.
    echo html_writer::tag(
        'h5',
        get_string('assignedcourses', 'block_workload') .
        (!empty($courses)
            ? html_writer::tag(
                'span',
                ' (' . count($courses) . ')',
                ['class' => 'text-muted fw-normal small ms-1']
            )
            : ''),
        ['class' => 'mt-4 mb-2']
    );

    if (empty($courses)) {
        echo html_writer::div(get_string('noenrolledcourses', 'block_workload'), 'text-muted small mb-3');
    } else {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => '', 'id' => 'wl-assigned-form']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

        $masterth = new html_table_cell();
        $masterth->header = true;
        $masterth->attributes['class'] = 'c0 shrink';
        $masterth->text = html_writer::empty_tag('input', [
            'type'             => 'checkbox',
            'id'               => 'wl-assigned-master',
            'data-action'      => 'toggle',
            'data-togglegroup' => 'wl-assigned-group',
            'data-toggle'      => 'master',
            'title'            => get_string('selectall'),
        ]);

        $table             = new html_table();
        $table->head       = [
            $masterth,
            get_string('course', 'block_workload'),
            get_string('coursestartdate', 'block_workload'),
            get_string('courseenddate', 'block_workload'),
            get_string('activecourse', 'block_workload'),
            get_string('actions', 'block_workload'),
        ];
        $table->attributes = ['class' => 'generaltable table-sm table-striped table-hover mb-2'];

        $disabled = html_writer::tag(
            'span',
            get_string('datedisabled', 'block_workload'),
            ['class' => 'text-muted small']
        );

        foreach ($courses as $course) {
            $isadded  = !$course->enrolled;
            $excluded = $course->enrolled
                && $course->override_active !== null
                && (int)$course->override_active === 0;

            $cbcell = new html_table_cell();
            $cbcell->attributes['class'] = 'c0 shrink';
            $cbcell->text = html_writer::empty_tag('input', [
                'type'             => 'checkbox',
                'name'             => 'courseids[]',
                'value'            => $course->id,
                'data-action'      => 'toggle',
                'data-togglegroup' => 'wl-assigned-group',
                'data-toggle'      => 'slave',
            ]);

            $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
            $namecell  = new html_table_cell();
            $namecell->text = html_writer::link($courseurl, format_string($course->fullname), ['target' => '_blank'])
                . html_writer::tag('span', ' (' . $course->shortname . ')', ['class' => 'text-muted small']);

            $startcell = new html_table_cell();
            $startcell->text = !empty($course->startdate)
                ? userdate($course->startdate, get_string('strftimedate', 'langconfig'))
                : $disabled;

            $endcell = new html_table_cell();
            $endcell->text = !empty($course->enddate)
                ? userdate($course->enddate, get_string('strftimedate', 'langconfig'))
                : $disabled;

            $activecell = new html_table_cell();
            if ($isadded) {
                $activecell->text = html_writer::tag(
                    'span',
                    get_string('statusadded', 'block_workload'),
                    ['class' => 'badge bg-primary text-white']
                );
            } else if ($excluded) {
                $activecell->text = html_writer::tag(
                    'span',
                    get_string('statusexcluded', 'block_workload'),
                    ['class' => 'badge bg-secondary text-white']
                );
            } else {
                $activecell->text = html_writer::tag(
                    'span',
                    get_string('statusincluded', 'block_workload'),
                    ['class' => 'badge bg-success text-white']
                );
            }

            $actioncell = new html_table_cell();
            if ($isadded) {
                $actioncell->text = html_writer::link(
                    new moodle_url($baseurl, ['userid' => $userid, 'courseid' => $course->id, 'action' => 'removecourse']),
                    get_string('removecourse', 'block_workload'),
                    ['class' => 'btn btn-outline-secondary btn-sm']
                );
            } else if ($excluded) {
                $actioncell->text = html_writer::link(
                    new moodle_url($baseurl, ['userid' => $userid, 'courseid' => $course->id,
                        'action' => 'restore', 'sesskey' => sesskey()]),
                    get_string('restorecourse', 'block_workload'),
                    ['class' => 'btn btn-outline-success btn-sm']
                );
            } else {
                $actioncell->text = html_writer::link(
                    new moodle_url($baseurl, ['userid' => $userid, 'courseid' => $course->id, 'action' => 'exclude']),
                    get_string('excludecourse', 'block_workload'),
                    ['class' => 'btn btn-outline-secondary btn-sm']
                );
            }

            $row = new html_table_row();
            if ($excluded) {
                $row->attributes['class'] = 'text-muted';
            }
            $row->cells    = [$cbcell, $namecell, $startcell, $endcell, $activecell, $actioncell];
            $table->data[] = $row;
        }

        echo html_writer::table($table);

        echo html_writer::tag(
            'button',
            get_string('excludeselected', 'block_workload'),
            ['type' => 'submit', 'name' => 'bulkaction', 'value' => 'exclude',
            'class' => 'btn btn-secondary me-2',
            'disabled' => 'disabled']
        );
        echo html_writer::tag(
            'button',
            get_string('restoreselected', 'block_workload'),
            ['type' => 'submit', 'name' => 'bulkaction', 'value' => 'restore',
            'class' => 'btn btn-secondary me-2',
            'disabled' => 'disabled']
        );
        echo html_writer::tag(
            'button',
            get_string('bulkremovecourses', 'block_workload'),
            ['type' => 'submit', 'name' => 'bulkaction', 'value' => 'remove',
            'class' => 'btn btn-secondary',
            'disabled' => 'disabled']
        );

        echo html_writer::end_tag('form');

        echo html_writer::script("
(function() {
    var form = document.getElementById('wl-assigned-form');
    if (!form) return;
    var btns = form.querySelectorAll('button[name=\"bulkaction\"]');
    function update() {
        var any = Array.prototype.some.call(
            form.querySelectorAll('input[name=\"courseids[]\"]'),
            function(cb) { return cb.checked; }
        );
        Array.prototype.forEach.call(btns, function(b) { b.disabled = !any; });
    }
    form.addEventListener('change', update);
    form.addEventListener('click', function() { setTimeout(update, 0); });
    update();
})();
        ");
    }

    echo $OUTPUT->footer();
}
