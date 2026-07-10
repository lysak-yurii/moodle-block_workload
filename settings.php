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
 * Admin settings for block_workload.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (!class_exists('admin_setting_workload_posint')) {
    /**
     * Integer setting that rejects values below 1.
     */
    class admin_setting_workload_posint extends admin_setting_configtext {
        /**
         * Validate that the value is a positive integer.
         *
         * @param mixed $data
         * @return bool|string
         */
        public function validate($data) {
            if (!is_numeric($data) || (int) $data < 1) {
                return get_string('hourstep_invalid', 'block_workload');
            }
            return true;
        }
    }
}

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'block_workload/maxhours',
        get_string('maxhours', 'block_workload'),
        get_string('maxhours_desc', 'block_workload'),
        '40',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_workload/coursesperpage',
        get_string('coursesperpage', 'block_workload'),
        get_string('coursesperpage_desc', 'block_workload'),
        '6',
        PARAM_INT
    ));

    $settings->add(new admin_setting_workload_posint(
        'block_workload/hourstep',
        get_string('hourstep', 'block_workload'),
        get_string('hourstep_desc', 'block_workload'),
        '60',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_workload/enablebackfill',
        get_string('enablebackfill', 'block_workload'),
        get_string('enablebackfill_desc', 'block_workload'),
        1
    ));

    $settings->add(new admin_setting_workload_posint(
        'block_workload/backfillweeks',
        get_string('backfillweeks', 'block_workload'),
        get_string('backfillweeks_desc', 'block_workload'),
        '4',
        PARAM_INT
    ));
    $settings->hide_if('block_workload/backfillweeks', 'block_workload/enablebackfill', 'notchecked');

    $settings->add(new admin_setting_configselect(
        'block_workload/courseorder',
        get_string('courseorder', 'block_workload'),
        get_string('courseorder_desc', 'block_workload'),
        'recentaccess',
        [
            'sortorder'    => get_string('courseorder_sortorder', 'block_workload'),
            'recentaccess' => get_string('courseorder_recentaccess', 'block_workload'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'block_workload/coursemode',
        get_string('coursemode', 'block_workload'),
        get_string('coursemode_desc', 'block_workload'),
        'enrollment',
        [
            'cohort'     => get_string('coursemode_cohort', 'block_workload'),
            'enrollment' => get_string('coursemode_enrollment', 'block_workload'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_workload/enrollmentactiveonly',
        get_string('enrollmentactiveonly', 'block_workload'),
        get_string('enrollmentactiveonly_desc', 'block_workload'),
        1
    ));
    $settings->hide_if('block_workload/enrollmentactiveonly', 'block_workload/coursemode', 'neq', 'enrollment');

    $settings->add(new admin_setting_configcheckbox(
        'block_workload/enablemoodlecohortimport',
        get_string('enablemoodlecohortimport', 'block_workload'),
        get_string('enablemoodlecohortimport_desc', 'block_workload'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_workload/anonymizestats',
        get_string('anonymizestats', 'block_workload'),
        get_string('anonymizestats_desc', 'block_workload'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_workload/anonymizeteacherstats',
        get_string('anonymizeteacherstats', 'block_workload'),
        get_string('anonymizeteacherstats_desc', 'block_workload'),
        1
    ));
    $settings->hide_if('block_workload/anonymizeteacherstats', 'block_workload/coursemode', 'neq', 'enrollment');
}
