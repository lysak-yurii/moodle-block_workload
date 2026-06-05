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
 * Workload Assessment – QM statistics page AMD module.
 *
 * Wires up the live user-search autocomplete and the CSV-export modal.
 *
 * @module    block_workload/statistics
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['block_workload/usersearch'], function(UserSearch) {

    /**
     * @param {Object} cfg  PHP-supplied config: noResultsStr
     */
    function init(cfg) {

        // Live user search – navigates to mystats.php?viewas=ID with current filter params.
        UserSearch.init({
            inputId: 'wl-usersearch',
            listId: 'wl-usersearch-results',
            btnId: 'wl-usersearch-btn',
            clearId: 'wl-usersearch-clear',
            ajaxPath: '/blocks/workload/ajax_usersearch.php',
            cohortFieldId: 'wl-cohortid',
            noResultsStr: cfg.noResultsStr,
            buildTargetUrl: function(uid) {
                var p = '?viewas=' + uid;
                ['weekfrom', 'yearfrom', 'weekto', 'yearto'].forEach(function(n) {
                    var el = document.getElementById(n);
                    if (el && el.value) {
                        p += '&' + n + '=' + encodeURIComponent(el.value);
                    }
                });
                return M.cfg.wwwroot + '/blocks/workload/mystats.php' + p;
            },
        });

        // CSV export modal – opens on click of [data-wl-exportmodal].
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-wl-exportmodal]');
            if (!btn) {
                return;
            }
            require(['core/modal_factory'], function(ModalFactory) {
                var body = '<div class="d-grid gap-3">'
                    + '<a href="' + btn.dataset.quickUrl
                    + '" class="btn btn-outline-primary text-start p-3">'
                    + '<div class="fw-semibold">' + btn.dataset.labelQuick + '</div>'
                    + '<div class="small mt-1" style="opacity:0.8">' + btn.dataset.descQuick + '</div>'
                    + '</a>'
                    + '<a href="' + btn.dataset.detailUrl
                    + '" class="btn btn-outline-secondary text-start p-3">'
                    + '<div class="fw-semibold">' + btn.dataset.labelDetail + '</div>'
                    + '<div class="small mt-1" style="opacity:0.8">' + btn.dataset.descDetail + '</div>'
                    + '</a></div>';
                ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    title: btn.dataset.title,
                    body: body,
                }).then(function(modal) {
                    modal.show();
                    return modal;
                }).catch(function() {
                    return;
                });
            });
        });
    }

    return {init: init};
});
