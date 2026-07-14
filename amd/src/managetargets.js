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
 * Course targets management – inline editing of per-course target hours.
 *
 * Listens for changes on the target inputs, saves via AJAX and repaints the
 * row's effective-value cell from the server's response.
 *
 * @module    block_workload/managetargets
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    /** Config from init: {sourcelabels: {course, default, none}, nonelabel}. */
    var cfg = {};

    /**
     * Briefly tint a row green to confirm the save visually.
     *
     * @param {HTMLElement} row
     */
    function flashRow(row) {
        row.style.transition = 'background-color 0.2s';
        row.style.backgroundColor = '#d4edda';
        setTimeout(function() {
            row.style.backgroundColor = '';
        }, 600);
    }

    /**
     * Repaint a row's effective-target cell.
     *
     * @param {HTMLElement} row
     * @param {number} effective  Effective hours (0 = none).
     * @param {string} source     'course' | 'default' | 'none'.
     */
    function paintEffective(row, effective, source) {
        var cell = row.querySelector('.wl-target-effective');
        if (!cell) {
            return;
        }
        if (source === 'none' || !effective) {
            cell.textContent = cfg.nonelabel || '';
            return;
        }
        var label = (cfg.sourcelabels && cfg.sourcelabels[source]) || source;
        cell.textContent = effective.toFixed(1) + ' h (' + label + ')';
    }

    /**
     * Parse a manager-entered value into non-negative hours.
     * Accepts decimal comma or point; blank counts as 0 (= clear).
     *
     * @param  {string} val
     * @return {number} Hours, or NaN when unparseable.
     */
    function parseHours(val) {
        val = String(val || '').trim();
        if (val === '') {
            return 0;
        }
        val = val.replace(',', '.');
        var parsed = parseFloat(val);
        return (isNaN(parsed) || parsed < 0) ? NaN : parsed;
    }

    /**
     * Save one course's target and repaint its row.
     *
     * @param {HTMLElement} row
     * @param {HTMLInputElement} input
     */
    function save(row, input) {
        var courseId = parseInt(input.dataset.courseid, 10);
        var hours = parseHours(input.value);
        if (isNaN(hours)) {
            input.classList.add('is-invalid');
            return;
        }
        input.classList.remove('is-invalid');
        Ajax.call([{
            methodname: 'block_workload_set_course_target',
            args: {courseid: courseId, targethours: hours},
            done: function(response) {
                if (response.success) {
                    input.value = hours > 0 ? String(hours) : '';
                    paintEffective(row, response.effective, response.source);
                    flashRow(row);
                }
            },
            fail: function(ex) {
                Notification.exception(ex);
            },
        }]);
    }

    return {
        /**
         * Initialise inline target editing.
         *
         * @param {Object} config  {sourcelabels: {course, default, none}, nonelabel}
         */
        init: function(config) {
            cfg = config || {};
            var table = document.getElementById('wl-targets-table');
            if (!table) {
                return;
            }
            table.querySelectorAll('.wl-target-input').forEach(function(input) {
                var row = input.closest('tr');
                input.addEventListener('change', function() {
                    save(row, input);
                });
            });
        },
    };
});
