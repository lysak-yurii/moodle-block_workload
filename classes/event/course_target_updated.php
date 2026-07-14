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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_workload\event;

/**
 * Course workload target set, changed or cleared event.
 *
 * @package   block_workload
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_target_updated extends base {
    /**
     * Initialise the event data.
     */
    protected function init(): void {
        parent::init();
        $this->data['crud']        = 'u';
        $this->data['objecttable'] = 'block_workload_targets';
    }
    /**
     * Return the event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_course_target_updated', 'block_workload');
    }
    /**
     * Return the event description.
     *
     * @return string
     */
    public function get_description(): string {
        $target = $this->other['targethours'];
        $state  = ($target === null)
            ? 'cleared the workload target for'
            : "set the workload target to {$target} h for";
        return "User {$this->userid} {$state} course '{$this->other['coursename']}' (id {$this->objectid}).";
    }
    /**
     * Return the URL associated with this event.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/blocks/workload/manage_targets.php');
    }
}
