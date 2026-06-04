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

declare(strict_types=1);

namespace block_workload\table;

use context_system;
use core_table\dynamic as dynamic_table;
use core_table\local\filter\filter;
use core_table\local\filter\filterset;
use html_writer;
use moodle_url;
use pix_icon;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/tablelib.php");

/**
 * Dynamic flexible_table for the enrollment-mode student list.
 *
 * Implementing core_table\dynamic makes sort, column collapse, and page
 * navigation work via fetch-and-swap (no full page reload), exactly like
 * the Moodle participants list.
 *
 * @package   block_workload
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollment_students extends \flexible_table implements dynamic_table {
    /** @var string Active first-name initial filter (empty = all). */
    protected string $firstletter = '';

    /** @var string Active last-name initial filter (empty = all). */
    protected string $lastletter  = '';

    /**
     * Check capability for users accessing the dynamic table.
     *
     * @return bool
     */
    public function has_capability(): bool {
        return has_capability('block/workload:manage', context_system::instance());
    }

    /**
     * Get the context for this table.
     *
     * @return \context
     */
    public function get_context(): \context {
        return context_system::instance();
    }

    /**
     * Set the base URL for this table.
     */
    public function guess_base_url(): void {
        $this->baseurl = new moodle_url('/blocks/workload/manage_enrollment.php');
    }

    /**
     * Store the filterset and extract A-Z filter values from it.
     *
     * @param filterset $filterset
     */
    public function set_filterset(filterset $filterset): void {
        if ($filterset->has_filter('firstletter')) {
            $vals = $filterset->get_filter('firstletter')->get_filter_values();
            $this->firstletter = (string)($vals[0] ?? '');
        }
        if ($filterset->has_filter('lastletter')) {
            $vals = $filterset->get_filter('lastletter')->get_filter_values();
            $this->lastletter = (string)($vals[0] ?? '');
        }
        parent::set_filterset($filterset);
    }

    /**
     * Show our own "no results" message inside the dynamic wrapper so the
     * AJAX swap still replaces the div correctly.
     */
    public function print_nothing_to_display(): void {
        global $OUTPUT;
        echo $this->get_dynamic_table_html_start();
        echo $this->render_reset_button();
        echo $OUTPUT->notification(get_string('nostudentsfound', 'block_workload'), 'info', false);
        echo $this->get_dynamic_table_html_end();
    }

    /**
     * Build and render the table.  Called both on initial page load and by
     * the core_table/dynamic web service on every sort/hide/page action.
     *
     * @param int    $pagesize        Rows per page (0 = show all).
     * @param bool   $useinitialsbar  Unused – we have our own A-Z bar outside.
     * @param string $downloadhelpbutton
     */
    public function out(int $pagesize, bool $useinitialsbar, string $downloadhelpbutton = ''): void {
        global $OUTPUT;

        $colhead = static function (string $colkey): string {
            return html_writer::tag(
                'span',
                get_string($colkey, 'block_workload'),
                ['title' => get_string($colkey . '_title', 'block_workload'),
                 'style' => 'cursor:help; border-bottom:1px dotted currentColor;']
            );
        };

        $this->define_columns([
            'fullname', 'email', 'department', 'institution',
            'colenrolled', 'colexcluded', 'coladded', 'coltotal', 'actions',
        ]);
        $this->define_headers([
            get_string('student', 'block_workload'),
            get_string('email'),
            get_string('department'),
            get_string('institution'),
            $colhead('colenrolled'),
            $colhead('colexcluded'),
            $colhead('coladded'),
            $colhead('coltotal'),
            '',
        ]);

        if (empty($this->baseurl)) {
            $this->define_baseurl(new moodle_url('/blocks/workload/manage_enrollment.php'));
        }

        $this->sortable(true, 'fullname', SORT_ASC);
        foreach (['colenrolled', 'colexcluded', 'coladded', 'coltotal', 'actions'] as $nosort) {
            $this->no_sorting($nosort);
        }
        $this->collapsible(true);
        $this->set_attribute('class', 'generaltable table-sm table-striped table-hover');

        $total = \block_workload\helper::get_enrollment_mode_students_count(
            $this->firstletter,
            $this->lastletter
        );

        if ($pagesize > 0) {
            $this->pagesize($pagesize, $total);
        }

        $this->setup();

        // Map table column keys to SQL ORDER BY expressions.
        // flexible_table splits the 'fullname' column into separate 'firstname'
        // and 'lastname' sort links, so both sub-keys must be mapped here.
        $sortmap = [
            'fullname'    => ['u.lastname', 'u.firstname'],
            'firstname'   => ['u.firstname', 'u.lastname'],
            'lastname'    => ['u.lastname', 'u.firstname'],
            'email'       => ['u.email'],
            'department'  => ['u.department'],
            'institution' => ['u.institution'],
        ];
        $orderparts = [];
        foreach ($this->get_sort_columns() as $col => $dir) {
            $sqldir = ($dir === SORT_DESC) ? 'DESC' : 'ASC';
            foreach ($sortmap[$col] ?? [] as $field) {
                $orderparts[] = $field . ' ' . $sqldir;
            }
        }
        $orderby = $orderparts ? implode(', ', $orderparts) : 'u.lastname ASC, u.firstname ASC';

        $offset   = ($pagesize > 0) ? (int)$this->get_page_start() : 0;
        $limit    = ($pagesize > 0) ? $pagesize : 0;

        $students = \block_workload\helper::get_enrollment_mode_students(
            $limit,
            $offset,
            $this->firstletter,
            $this->lastletter,
            $orderby
        );

        foreach ($students as $s) {
            $enrolled  = (int)$s->enrolledcount;
            $excluded  = (int)$s->excludedcount;
            $added     = (int)$s->addedcount;
            $efftotal  = max(0, $enrolled - $excluded + $added);
            $manageurl = new moodle_url('/blocks/workload/manage_enrollment.php', ['userid' => $s->id]);

            $this->add_data_keyed([
                'fullname'    => format_string($s->firstname . ' ' . $s->lastname),
                'email'       => $s->email,
                'department'  => $s->department,
                'institution' => $s->institution,
                'colenrolled' => $enrolled,
                'colexcluded' => $excluded,
                'coladded'    => $added,
                'coltotal'    => $efftotal,
                'actions'     => $OUTPUT->action_icon(
                    $manageurl,
                    new pix_icon('i/course', get_string('managecourses', 'block_workload'))
                ),
            ]);
        }

        $this->finish_output();
    }
}
