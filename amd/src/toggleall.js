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
 * Select-all / toggle-all helper for block_workload tables.
 *
 * Self-contained replacement for core/checkbox-toggleall. The core module's
 * data-toggle="master"/"slave" values were renamed to "toggler"/"target"
 * (MDL-79753), and the old values stopped functioning in Moodle 5.x while the
 * new values are not understood by older 4.5.x builds. To work across both, we
 * own the toggling here instead of depending on the moving core contract.
 *
 * Markup contract (matches the templates):
 *   master: data-toggle="toggler" data-togglegroup="<group>"
 *   slaves: data-toggle="target"  data-togglegroup="<group>"
 *
 * @module    block_workload/toggleall
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    var registered = false;

    return {
        /**
         * Register a single delegated change listener for all toggle groups.
         */
        init: function() {
            if (registered) {
                return;
            }
            registered = true;

            document.addEventListener('change', function(e) {
                var toggler = e.target.closest('[data-toggle="toggler"]');
                if (!toggler) {
                    return;
                }
                var group = toggler.getAttribute('data-togglegroup');
                if (!group) {
                    return;
                }
                document.querySelectorAll(
                    '[data-toggle="target"][data-togglegroup="' + group + '"]'
                ).forEach(function(cb) {
                    if (cb.checked !== toggler.checked) {
                        cb.checked = toggler.checked;
                        // Bubble so per-form submit-button state handlers update.
                        cb.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                });
            });
        }
    };
});
