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
 * Workload Assessment block capability definitions.
 *
 * The plugin role (workload_manager) is created automatically by
 * db/install.php — the administrator only needs to assign users to that
 * role after installation.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Student: add block to their own dashboard.
    'block/workload:myaddinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user' => CAP_ALLOW,
        ],
    ],

    // Admin: add block to any page.
    'block/workload:addinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Student: submit (save) workload hours.
    'block/workload:submit' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user' => CAP_ALLOW,
        ],
    ],

    // Student: view their own statistics.
    'block/workload:viewownstats' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user' => CAP_ALLOW,
        ],
    ],

    // Quality Manager: access management interface (cohorts, members, courses, activations).
    'block/workload:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Quality Manager: view statistics for all students.
    'block/workload:viewallstats' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Export statistics as CSV: students export their own stats,
    // Quality Managers export cohort statistics.
    'block/workload:export' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'    => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // See real user identities in workload statistics even when the
    // "Anonymise statistics" setting is enabled. Deliberately NOT granted
    // to the auto-created workload_manager role — anonymization applies to
    // Quality Managers unless an admin opts a role in. Declared at course
    // level so it can also be granted per course/category (de-anonymizing
    // the teacher course statistics selectively); system-level grants still
    // apply everywhere via context aggregation.
    'block/workload:viewrealnames' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Teacher: view aggregated workload statistics of the students in their
    // own courses (enrollment mode only). RISK_PERSONAL because it exposes
    // per-student workload behaviour, and real names/emails when the
    // "Anonymise teacher statistics" setting is disabled.
    'block/workload:viewcoursestats' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];
