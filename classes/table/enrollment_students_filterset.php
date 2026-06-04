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

declare(strict_types=1);

namespace block_workload\table;

use core_table\local\filter\filterset;
use core_table\local\filter\string_filter;

/**
 * Filterset for the enrollment-mode student list table.
 *
 * Optional filters carry the A-Z initial bars state so that sort/page AJAX
 * requests preserve the currently active letter filter.
 *
 * @package   block_workload
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollment_students_filterset extends filterset {
    /**
     * Get the optional filters: firstletter and lastletter for the A-Z bars.
     *
     * @return array
     */
    protected function get_optional_filters(): array {
        return [
            'firstletter' => string_filter::class,
            'lastletter'  => string_filter::class,
        ];
    }
}
