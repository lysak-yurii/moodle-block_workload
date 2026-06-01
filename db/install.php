<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Post-install hook for block_workload.
 *
 * Creates the two plugin roles so the administrator only needs to
 * assign users — no manual role configuration required.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_block_workload_install(): void {
    global $DB;

    // Capabilities from db/access.php are not yet registered when install.php
    // runs. Force-register them now so assign_capability() can find them.
    update_capabilities('block_workload');

    $syscontext = context_system::instance();

    // -------------------------------------------------------------------------
    // Workload Manager (Quality Manager) role
    // -------------------------------------------------------------------------
    if (!$DB->record_exists('role', ['shortname' => 'workload_manager'])) {
        $managerid = create_role(
            get_string('role_manager_name', 'block_workload'),
            'workload_manager',
            get_string('role_manager_desc', 'block_workload'),
            ''  // no archetype
        );
        set_role_contextlevels($managerid, [CONTEXT_SYSTEM]);
        assign_capability('block/workload:addinstance',  CAP_ALLOW, $managerid, $syscontext->id);
        assign_capability('block/workload:manage',       CAP_ALLOW, $managerid, $syscontext->id);
        assign_capability('block/workload:viewallstats', CAP_ALLOW, $managerid, $syscontext->id);
        assign_capability('block/workload:export',       CAP_ALLOW, $managerid, $syscontext->id);
    }
}
