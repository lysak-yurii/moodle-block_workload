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

namespace block_workload\local;

/**
 * Admin text setting that rejects numeric values below 0.
 *
 * Autoloaded so settings.php keeps a single inline class (PSR1), while the
 * plugin can still validate the default-target-hours field as a number >= 0.
 *
 * @package   block_workload
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_nonnegnum extends \admin_setting_configtext {
    /**
     * Validate that the value is a number greater than or equal to 0.
     *
     * @param mixed $data
     * @return bool|string true when valid, otherwise an error message.
     */
    public function validate($data) {
        if (!is_numeric($data) || (float) $data < 0) {
            return get_string('targethours_invalid', 'block_workload');
        }
        return true;
    }
}
