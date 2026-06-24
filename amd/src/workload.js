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
 * Handles the +/- hour buttons, debounced AJAX save, week navigation
 * (backfilling past weeks) and client-side course pagination (all rows are
 * rendered server-side; JS shows/hides the current page's slice).
 *
 * @module    block_workload/workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    /** Shared config set on init (weeknumber/year are mutable: they track the viewed week). */
    var cfg = {};

    /** Per-course debounce timers. */
    var timers = {};

    /** Per-course latest pending save {courseId: {hours, week, year}}, captured at edit time. */
    var pending = {};

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
     * Current hours stored on a row's input (the single source of truth).
     *
     * @param  {HTMLElement} input
     * @return {number}
     */
    function getHours(input) {
        return parseFloat(input.dataset.hours) || 0;
    }

    /**
     * Send a save to the server for an explicit week/year.
     *
     * @param {number} courseId
     * @param {number} hours
     * @param {number} week
     * @param {number} year
     */
    function doSave(courseId, hours, week, year) {
        Ajax.call([{
            methodname: 'block_workload_save_hours',
            args: {
                cohortid:   cfg.cohortid,
                courseid:   courseId,
                weeknumber: week,
                year:       year,
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
    }

    /**
     * Queue a debounced save (500 ms). The target week/year is captured now, so a
     * later week switch can never redirect a pending save to the wrong week.
     *
     * @param {number} courseId
     * @param {number} hours
     */
    function saveHours(courseId, hours) {
        pending[courseId] = {hours: hours, week: cfg.weeknumber, year: cfg.year};
        clearTimeout(timers[courseId]);
        timers[courseId] = setTimeout(function() {
            flushOne(courseId);
        }, 500);
    }

    /**
     * Flush a single course's pending save immediately.
     *
     * @param {number} courseId
     */
    function flushOne(courseId) {
        clearTimeout(timers[courseId]);
        delete timers[courseId];
        var p = pending[courseId];
        if (!p) {
            return;
        }
        delete pending[courseId];
        doSave(courseId, p.hours, p.week, p.year);
    }

    /**
     * Flush every pending save immediately (used before changing weeks).
     */
    function flushAll() {
        Object.keys(pending).forEach(function(courseId) {
            flushOne(courseId);
        });
    }

    /**
     * Toggle the red left-border indicator based on whether hours == 0.
     *
     * @param {number} courseId
     * @param {number} hours
     */
    function updateWarning(courseId, hours) {
        var row = document.querySelector('.workload-course-row[data-courseid="' + courseId + '"]');
        if (!row) {
            return;
        }
        row.classList.toggle('workload-not-entered', hours <= 0);
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
     * Reads/writes input.dataset.hours as the source of truth so repainting a
     * different week (see loadWeek) keeps the +/- buttons in sync.
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
            input.value = formatHHMM(getHours(input));

            var commit = function(newVal) {
                newVal = Math.max(0, Math.min(max, newVal));
                input.value = formatHHMM(newVal);
                input.dataset.hours = newVal;
                saveHours(courseId, newVal);
                updateWarning(courseId, newVal);
            };

            btnMinus.addEventListener('click', function() {
                commit(getHours(input) - step);
            });

            btnPlus.addEventListener('click', function() {
                commit(getHours(input) + step);
            });

            input.addEventListener('change', function() {
                var parsed = parseHHMM(this.value);
                if (isNaN(parsed)) {
                    this.value = formatHHMM(getHours(input));
                    return;
                }
                commit(parsed);
            });
        });
    }

    /**
     * Date of the Monday of the given ISO week/year.
     *
     * @param  {number} year
     * @param  {number} week
     * @return {Date}
     */
    function isoWeekToDate(year, week) {
        var simple = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7));
        var dow = simple.getUTCDay();
        if (dow <= 4) {
            simple.setUTCDate(simple.getUTCDate() - simple.getUTCDay() + 1);
        } else {
            simple.setUTCDate(simple.getUTCDate() + 8 - simple.getUTCDay());
        }
        return simple;
    }

    /**
     * ISO {year, week} for the given Date.
     *
     * @param  {Date} d
     * @return {Object}
     */
    function dateToIsoWeek(d) {
        var date = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()));
        var dayNum = (date.getUTCDay() + 6) % 7; // Monday = 0.
        date.setUTCDate(date.getUTCDate() - dayNum + 3); // Thursday of this week.
        var firstThursday = new Date(Date.UTC(date.getUTCFullYear(), 0, 4));
        var firstDayNum = (firstThursday.getUTCDay() + 6) % 7;
        firstThursday.setUTCDate(firstThursday.getUTCDate() - firstDayNum + 3);
        var week = 1 + Math.round((date - firstThursday) / (7 * 24 * 3600 * 1000));
        return {year: date.getUTCFullYear(), week: week};
    }

    /**
     * Update the week badge label and tooltip from the localized templates.
     */
    function updateBadge() {
        var badge = document.querySelector('.workload-week-badge');
        if (!badge) {
            return;
        }
        if (cfg.weeklabeltpl) {
            badge.textContent = cfg.weeklabeltpl.replace('{w}', cfg.weeknumber);
        }
        if (cfg.weektooltiptpl) {
            badge.title = cfg.weektooltiptpl.replace('{w}', cfg.weeknumber).replace('{y}', cfg.year);
        }
    }

    /**
     * Enable/disable the prev/next buttons at the editable-week bounds.
     */
    function updateNavButtons() {
        var weekint = cfg.year * 100 + cfg.weeknumber;
        var prev = document.querySelector('.workload-week-prev');
        var next = document.querySelector('.workload-week-next');
        if (prev) {
            var atMin = weekint <= cfg.minweekint;
            prev.disabled = atMin;
            prev.style.visibility = atMin ? 'hidden' : '';
        }
        if (next) {
            var atMax = weekint >= cfg.maxweekint;
            next.disabled = atMax;
            next.style.visibility = atMax ? 'hidden' : '';
        }
    }

    /**
     * Fetch entries for the currently selected week and repaint the rows.
     */
    function loadWeek() {
        Ajax.call([{
            methodname: 'block_workload_get_week_data',
            args: {weeknumber: cfg.weeknumber, year: cfg.year},
            done: function(response) {
                var map = {};
                (response.courses || []).forEach(function(c) {
                    map[c.courseid] = c.hours;
                });
                document.querySelectorAll('.workload-course-row').forEach(function(row) {
                    var courseId = parseInt(row.dataset.courseid, 10);
                    var input = row.querySelector('.workload-hours-input');
                    if (!input) {
                        return;
                    }
                    var hours = map[courseId] || 0;
                    input.dataset.hours = hours;
                    input.value = formatHHMM(hours);
                    updateWarning(courseId, hours);
                });
                updateBadge();
                updateNavButtons();
            },
            fail: function(ex) {
                Notification.exception(ex);
            },
        }]);
    }

    /**
     * Step the viewed week by the given number of weeks (clamped to the bounds).
     *
     * @param {number} delta  +1 (next) or -1 (previous).
     */
    function changeWeek(delta) {
        // Persist anything queued for the week we are leaving before we switch.
        flushAll();

        var monday = isoWeekToDate(cfg.year, cfg.weeknumber);
        monday.setUTCDate(monday.getUTCDate() + delta * 7);
        var iso = dateToIsoWeek(monday);
        var weekint = iso.year * 100 + iso.week;

        if (weekint < cfg.minweekint || weekint > cfg.maxweekint) {
            return;
        }

        cfg.year = iso.year;
        cfg.weeknumber = iso.week;
        loadWeek();
    }

    /**
     * Wire up the prev/next week-navigation buttons (no-op when absent).
     */
    function initWeekNav() {
        var prev = document.querySelector('.workload-week-prev');
        var next = document.querySelector('.workload-week-next');
        if (prev) {
            prev.addEventListener('click', function() {
                changeWeek(-1);
            });
        }
        if (next) {
            next.addEventListener('click', function() {
                changeWeek(1);
            });
        }
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
            info.textContent = (page + 1) + ' / ' + total;
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
         * @param {Object} config  {cohortid, weeknumber, year, coursesperpage,
         *                          minweekint, maxweekint, weeklabeltpl, weektooltiptpl}
         */
        init: function(config) {
            cfg = config || {};
            attachListeners();
            initWeekNav();
            initPagination();
        },
    };
});
