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

namespace block_workload\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Upload form for the course-targets bulk import (.csv / .xlsx).
 *
 * @package   block_workload
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class targets_import_form extends \moodleform {
    /**
     * Define the form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement(
            'filepicker',
            'targetsfile',
            get_string('importfile', 'block_workload'),
            null,
            ['accepted_types' => ['.csv', '.xlsx']]
        );
        $mform->addRule('targetsfile', null, 'required');

        $this->add_action_buttons(false, get_string('uploadandpreview', 'block_workload'));
    }
}
