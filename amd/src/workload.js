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
 * Workload Assessment block – AMD module.
 *
 * Handles the +/- hour buttons, debounced AJAX save, and client-side
 * course pagination (all rows are rendered server-side; JS shows/hides
 * the current page's slice).
 *
 * @module    block_workload/workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    /** Shared config set on init. */
    var cfg = {};

    /** Per-course debounce timers. */
    var timers = {};

    /** Current pagination page (0-based). */
    var currentPage = 0;

    /**
     * Parse a user-entered value into decimal hours.
     * Accepts "H:MM" / "H:M" (colon format) or a plain decimal number.
     * Returns NaN for anything that cannot be interpreted.
     *
     * @param  {string} val
     * @return {number}
     */
    function parseHHMM(val) {
        val = String(val || '').trim();
        var colon = val.match(/^(\d+):(\d{1,2})$/);
        if (colon) {
            var mins = parseInt(colon[2], 10);
            if (mins > 59) {
                return NaN;
            }
            return parseInt(colon[1], 10) + mins / 60;
        }
        return parseFloat(val); // Plain decimal or NaN.
    }

    /**
     * Format decimal hours as H:MM.
     *
     * @param  {number} hours
     * @return {string}
     */
    function formatHHMM(hours) {
        hours = Math.max(0, hours || 0);
        var h = Math.floor(hours);
        var m = Math.round((hours - h) * 60);
        if (m >= 60) {
            h++;
            m = 0;
        }
        return h + ':' + (m < 10 ? '0' : '') + m;
    }

    /**
     * Persist hours for one course via AJAX (debounced by 500 ms).
     *
     * @param {number} courseId
     * @param {number} hours
     */
    function saveHours(courseId, hours) {
        clearTimeout(timers[courseId]);
        timers[courseId] = setTimeout(function() {
            Ajax.call([{
                methodname: 'block_workload_save_hours',
                args: {
                    cohortid:   cfg.cohortid,
                    courseid:   courseId,
                    weeknumber: cfg.weeknumber,
                    year:       cfg.year,
                    hours:      hours,
                },
                done: function(response) {
                    if (response.success) {
                        updateWarning(courseId, response.hours);
                        flashRow(courseId);
                    }
                },
                fail: function(ex) {
                    Notification.exception(ex);
                },
            }]);
        }, 500);
    }

    /**
     * Show/hide the "!" warning badge based on whether hours == 0.
     *
     * @param {number} courseId
     * @param {number} hours
     */
    function updateWarning(courseId, hours) {
        var row = document.querySelector('.workload-course-row[data-courseid="' + courseId + '"]');
        if (!row) {
            return;
        }
        var icon = row.querySelector('.workload-warning');
        if (!icon) {
            return;
        }
        if (hours > 0) {
            icon.classList.add('d-none');
        } else {
            icon.classList.remove('d-none');
        }
    }

    /**
     * Briefly tint the row green to confirm the save visually.
     *
     * @param {number} courseId
     */
    function flashRow(courseId) {
        var row = document.querySelector('.workload-course-row[data-courseid="' + courseId + '"]');
        if (!row) {
            return;
        }
        row.style.transition = 'background-color 0.2s';
        row.style.backgroundColor = '#d4edda';
        setTimeout(function() {
            row.style.backgroundColor = '';
        }, 600);
    }

    /**
     * Attach click / change listeners to all course rows in the block.
     */
    function attachListeners() {
        var rows = document.querySelectorAll('.workload-course-row');
        rows.forEach(function(row) {
            var courseId = parseInt(row.dataset.courseid, 10);
            if (!courseId) {
                return;
            }

            var input = row.querySelector('.workload-hours-input');
            var btnMinus = row.querySelector('.workload-btn-minus');
            var btnPlus = row.querySelector('.workload-btn-plus');

            if (!input || !btnMinus || !btnPlus) {
                return;
            }

            var step = parseFloat(input.dataset.step) || 1;
            var max = parseFloat(input.dataset.max) || 40;
            var lastValid = parseFloat(input.dataset.hours) || 0;
            input.value = formatHHMM(lastValid);

            btnMinus.addEventListener('click', function() {
                var newVal = Math.max(0, lastValid - step);
                input.value = formatHHMM(newVal);
                lastValid = newVal;
                saveHours(courseId, newVal);
                updateWarning(courseId, newVal);
            });

            btnPlus.addEventListener('click', function() {
                var newVal = Math.min(max, lastValid + step);
                input.value = formatHHMM(newVal);
                lastValid = newVal;
                saveHours(courseId, newVal);
                updateWarning(courseId, newVal);
            });

            input.addEventListener('change', function() {
                var parsed = parseHHMM(this.value);
                if (isNaN(parsed)) {
                    this.value = formatHHMM(lastValid);
                    return;
                }
                var newVal = Math.max(0, Math.min(max, parsed));
                this.value = formatHHMM(newVal);
                lastValid = newVal;
                saveHours(courseId, newVal);
                updateWarning(courseId, newVal);
            });
        });
    }

    /**
     * Show the given page of courses and update the pagination controls.
     * Does nothing when cfg.coursesperpage is 0 (pagination disabled).
     *
     * @param {number} page  0-based page index
     */
    function showPage(page) {
        var pageSize = cfg.coursesperpage || 0;
        if (!pageSize) {
            return;
        }
        var rows = document.querySelectorAll('.workload-course-row');
        var total = Math.ceil(rows.length / pageSize);
        currentPage = page;

        rows.forEach(function(row, idx) {
            if (idx >= page * pageSize && idx < (page + 1) * pageSize) {
                row.style.removeProperty('display');
            } else {
                row.style.setProperty('display', 'none', 'important');
            }
        });

        var prevBtn = document.querySelector('.workload-btn-prev');
        var nextBtn = document.querySelector('.workload-btn-next');
        var info = document.querySelector('.workload-page-info');

        if (prevBtn) {
            prevBtn.disabled = (page <= 0);
        }
        if (nextBtn) {
            nextBtn.disabled = (page >= total - 1);
        }
        if (info) {
            info.textContent = (page + 1) + ' / ' + total;
        }
    }

    /**
     * Wire up the prev / next pagination buttons.
     * Called only when cfg.coursesperpage > 0 and there are more rows than fit
     * on one page (guaranteed by PHP passing coursesperpage = 0 otherwise).
     */
    function initPagination() {
        var pageSize = cfg.coursesperpage || 0;
        if (!pageSize) {
            return;
        }

        showPage(0);

        var prevBtn = document.querySelector('.workload-btn-prev');
        var nextBtn = document.querySelector('.workload-btn-next');

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (currentPage > 0) {
                    showPage(currentPage - 1);
                }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                var rows = document.querySelectorAll('.workload-course-row');
                var total = Math.ceil(rows.length / pageSize);
                if (currentPage < total - 1) {
                    showPage(currentPage + 1);
                }
            });
        }
    }

    return {
        /**
         * Initialise the block.
         *
         * @param {Object} config  {cohortid, weeknumber, year, coursesperpage}
         */
        init: function(config) {
            cfg = config || {};
            attachListeners();
            initPagination();
        },
    };
});
