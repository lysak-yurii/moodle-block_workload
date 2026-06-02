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
 * Moodleform for creating and editing workload cohorts.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_workload\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating or editing a workload cohort.
 */
class cohort_form extends \moodleform {
    /**
     * Define the form fields.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('cohortname', 'block_workload'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'degree_program', get_string('degreeprogram', 'block_workload'), ['size' => 60]);
        $mform->setType('degree_program', PARAM_TEXT);
        $mform->addRule('degree_program', null, 'required', null, 'client');
        $mform->addRule('degree_program', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Autocomplete for department — shows existing cohort + user-profile departments as.
        // Suggestions while still allowing free-text entry (tags => true).
        // Empty values are intentionally excluded; any blank <li data-value=""> entries.
        // Injected internally by Moodle's form framework are hidden via styles.css.
        $departments = $this->_customdata['departments'] ?? [];
        $deptoptions = [];
        foreach ($departments as $dept) {
            if ($dept !== '') {
                $deptoptions[$dept] = $dept;
            }
        }
        $mform->addElement(
            'autocomplete',
            'department',
            get_string('department'),
            $deptoptions,
            ['tags' => true, 'multiple' => false, 'id' => 'workload_department']
        );
        $mform->setType('department', PARAM_TEXT);

        $mform->addElement(
            'textarea',
            'description',
            get_string('description', 'block_workload'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'active', get_string('active', 'block_workload'));
        $mform->setDefault('active', 1);

        if (!empty($this->_customdata['cohort'])) {
            $mform->addElement('hidden', 'id', (int) $this->_customdata['cohort']->id);
            $mform->setType('id', PARAM_INT);
        }

        $this->add_action_buttons(true, get_string('save', 'block_workload'));
    }

    /**
     * Validate form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty(trim($data['name']))) {
            $errors['name'] = get_string('required');
        }
        if (empty(trim($data['degree_program']))) {
            $errors['degree_program'] = get_string('required');
        }
        return $errors;
    }
}
