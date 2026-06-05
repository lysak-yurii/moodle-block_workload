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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Workload Assessment – my statistics page AMD module.
 *
 * Handles the collapsible week-table rows and the week-detail
 * chart week-selector auto-submit.
 *
 * @module    block_workload/mystats
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Attach event listeners for the statistics page.
     */
    function init() {

        // Collapsible week-table: click a header row to show / hide its detail rows.
        document.addEventListener('click', function(e) {
            var hdr = e.target.closest('[data-wl-weektoggle]');
            if (!hdr) {
                return;
            }
            var gid = hdr.getAttribute('data-wl-weektoggle');
            var rows = document.querySelectorAll('[data-wl-weekgroup="' + gid + '"]');
            var icon = document.getElementById(gid + '-icon');
            var open = rows.length > 0 && rows[0].style.display === 'none';
            rows.forEach(function(r) {
                r.style.display = open ? '' : 'none';
            });
            if (icon) {
                icon.innerHTML = open ? '&#9650;' : '&#9660;';
            }
        });

        // Week-detail chart selector: auto-submit the form on change.
        var sel = document.querySelector('[data-wl-chartwkselect]');
        if (sel) {
            sel.addEventListener('change', function() {
                this.form.submit();
            });
        }
    }

    return {init: init};
});
