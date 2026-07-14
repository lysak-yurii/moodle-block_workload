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
 * Bulk import of course workload targets applied event.
 *
 * @package   block_workload
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_targets_imported extends base {
    /**
     * Initialise the event data.
     */
    protected function init(): void {
        parent::init();
        // Bulk/summary event: no single object, so objecttable/objectid are
        // deliberately omitted (Moodle requires objectid whenever objecttable
        // is set). Counts travel in $other instead.
        $this->data['crud'] = 'u';
    }
    /**
     * Return the event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_course_targets_imported', 'block_workload');
    }
    /**
     * Return the event description.
     *
     * @return string
     */
    public function get_description(): string {
        return "User {$this->userid} imported course workload targets: "
            . "{$this->other['created']} created, {$this->other['updated']} updated, "
            . "{$this->other['cleared']} cleared.";
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
