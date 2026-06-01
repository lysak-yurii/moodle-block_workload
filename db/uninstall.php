<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Post-uninstall hook for block_workload.
 *
 * Removes the Workload Manager role created during installation.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_block_workload_uninstall(): void {
    global $DB;

    $role = $DB->get_record('role', ['shortname' => 'workload_manager']);
    if ($role) {
        delete_role($role->id);
    }
}
