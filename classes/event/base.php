<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Abstract base event for block_workload manager actions.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_workload\event;

defined('MOODLE_INTERNAL') || die();

abstract class base extends \core\event\base {

    protected function init(): void {
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/blocks/workload/manage.php');
    }
}
