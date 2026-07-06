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
 * Workload Assessment – enrollment management page AMD module.
 *
 * List view:   wires up the live user-search autocomplete.
 * Detail view: enables bulk-action buttons when at least one checkbox is checked.
 *
 * @module    block_workload/enrollment
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['block_workload/usersearch'], function(UserSearch) {

    /**
     * @param {Object} cfg  PHP-supplied config: noResultsStr
     */
    function init(cfg) {

        // List view: live student search.
        UserSearch.init({
            inputId: 'wl-enrol-search',
            listId: 'wl-enrol-results',
            btnId: 'wl-enrol-btn',
            clearId: 'wl-enrol-clear',
            methodname: 'block_workload_search_stats_users',
            noResultsStr: cfg.noResultsStr,
            buildTargetUrl: function(uid) {
                return M.cfg.wwwroot + '/blocks/workload/manage_enrollment.php?userid=' + uid;
            },
        });

        // Detail view: enable bulk buttons when any checkbox is checked.
        var form = document.getElementById('wl-assigned-form');
        if (!form) {
            return;
        }
        var btns = form.querySelectorAll('button[name="bulkaction"]');

        /** Update disabled state of all bulk buttons. */
        function update() {
            var any = Array.prototype.some.call(
                form.querySelectorAll('input[name="courseids[]"]'),
                function(cb) {
                    return cb.checked;
                }
            );
            Array.prototype.forEach.call(btns, function(b) {
                b.disabled = !any;
            });
        }

        form.addEventListener('change', update);
        form.addEventListener('click', function() {
            setTimeout(update, 0);
        });
        update();
    }

    return {init: init};
});
