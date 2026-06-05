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
 * Workload Assessment – shared live user-search autocomplete.
 *
 * Callers supply a config object:
 *   inputId        {string}   – ID of the text input
 *   listId         {string}   – ID of the <ul> results list
 *   btnId          {string}   – ID of the "go" anchor/button
 *   clearId        {string}   – ID of the clear button
 *   ajaxPath       {string}   – server path for the search endpoint
 *   cohortFieldId  {string}   – optional ID of a cohort <select> whose value is
 *                               appended to the search request
 *   noResultsStr   {string}   – localised "no results" message
 *   buildTargetUrl {function} – receives userid, returns the navigation href
 *
 * @module    block_workload/usersearch
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Initialise one autocomplete instance.
     *
     * @param {Object} cfg
     */
    function init(cfg) {
        var inp = document.getElementById(cfg.inputId);
        var list = document.getElementById(cfg.listId);
        var btn = cfg.btnId ? document.getElementById(cfg.btnId) : null;
        var clr = cfg.clearId ? document.getElementById(cfg.clearId) : null;

        if (!inp || !list) {
            return;
        }

        var timer = null;

        /**
         * @param {string} s Raw string
         * @returns {string} HTML-escaped string
         */
        function esc(s) {
            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        /**
         * @param {number} id       User id
         * @param {string} fullname User full name
         * @param {string} email    User email
         */
        function selectUser(id, fullname, email) {
            inp.value = fullname + ' (' + email + ')';
            inp.dataset.userid = id;
            list.innerHTML = '';
            list.style.display = 'none';
            if (btn) {
                btn.href = cfg.buildTargetUrl(id);
                btn.style.display = '';
            }
            if (clr) {
                clr.style.display = '';
            }
        }

        /** Reset the search input and hide results. */
        function clearSearch() {
            inp.value = '';
            inp.dataset.userid = '';
            list.innerHTML = '';
            list.style.display = 'none';
            if (btn) {
                btn.style.display = 'none';
            }
            if (clr) {
                clr.style.display = 'none';
            }
            inp.focus();
        }

        /**
         * @param {string} q Search query
         */
        function doSearch(q) {
            var url = M.cfg.wwwroot + cfg.ajaxPath + '?q=' + encodeURIComponent(q);
            if (cfg.cohortFieldId) {
                var cel = document.getElementById(cfg.cohortFieldId);
                if (cel) {
                    url += '&cohortid=' + encodeURIComponent(cel.value);
                }
            }
            fetch(url, {credentials: 'same-origin'})
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    list.innerHTML = '';
                    if (!data || !data.length) {
                        var li = document.createElement('li');
                        li.className = 'px-3 py-2 text-muted small';
                        li.textContent = cfg.noResultsStr;
                        list.appendChild(li);
                        list.style.display = 'block';
                        return;
                    }
                    data.forEach(function(u) {
                        var li = document.createElement('li');
                        li.className = 'px-3 py-2';
                        li.style.cursor = 'pointer';
                        li.innerHTML = '<strong>' + esc(u.fullname) + '</strong>'
                                     + ' <small class="text-muted">' + esc(u.email) + '</small>';
                        li.addEventListener('mouseenter', function() {
                            this.style.background = '#f8f9fa';
                        });
                        li.addEventListener('mouseleave', function() {
                            this.style.background = '';
                        });
                        li.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            selectUser(u.id, u.fullname, u.email);
                        });
                        list.appendChild(li);
                    });
                    list.style.display = 'block';
                    return;
                })
                .catch(function() {
                    list.innerHTML = '';
                    list.style.display = 'none';
                });
        }

        inp.addEventListener('input', function() {
            clearTimeout(timer);
            inp.dataset.userid = '';
            if (btn) {
                btn.style.display = 'none';
            }
            if (clr) {
                clr.style.display = 'none';
            }
            var q = this.value.trim();
            if (q.length < 2) {
                list.innerHTML = '';
                list.style.display = 'none';
                return;
            }
            timer = setTimeout(function() {
                doSearch(q);
            }, 250);
        });

        inp.addEventListener('blur', function() {
            setTimeout(function() {
                list.style.display = 'none';
            }, 200);
        });

        inp.addEventListener('focus', function() {
            var q = this.value.trim();
            if (q.length >= 2 && !this.dataset.userid) {
                doSearch(q);
            }
        });

        if (clr) {
            clr.addEventListener('click', clearSearch);
        }

        document.addEventListener('click', function(e) {
            if (!inp.contains(e.target) && !list.contains(e.target)) {
                list.style.display = 'none';
            }
        });
    }

    return {init: init};
});
