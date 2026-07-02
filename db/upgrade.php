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

/**
 * Upgrade steps for block_workload.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run upgrade steps for block_workload.
 *
 * @param int $oldversion the version we are upgrading from.
 * @return bool
 */
function xmldb_block_workload_upgrade(int $oldversion): bool {

    if ($oldversion < 2026061200) {
        // Allow regular users to export their own statistics as CSV.
        // Archetype defaults in db/access.php only apply on fresh installs,
        // so grant the capability to existing 'user' archetype roles here.
        $syscontext = context_system::instance();
        foreach (get_archetype_roles('user') as $role) {
            assign_capability('block/workload:export', CAP_ALLOW, $role->id, $syscontext->id, true);
        }

        upgrade_block_savepoint(true, 2026061200, 'workload');
    }

    if ($oldversion < 2026070200) {
        // Statistics anonymization: secret used to derive stable,
        // non-reversible pseudonyms.
        // The new block/workload:viewrealnames capability is installed by
        // update_capabilities(), which applies the 'manager' archetype
        // default to existing manager-archetype roles. The workload_manager
        // role has no archetype, so it is intentionally NOT granted.
        if (!get_config('block_workload', 'anonsalt')) {
            set_config('anonsalt', bin2hex(random_bytes(16)), 'block_workload');
        }

        upgrade_block_savepoint(true, 2026070200, 'workload');
    }

    return true;
}
