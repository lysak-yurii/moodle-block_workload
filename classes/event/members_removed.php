<?php
namespace block_workload\event;
defined('MOODLE_INTERNAL') || die();

class members_removed extends base {
    protected function init(): void {
        parent::init();
        $this->data['crud']        = 'u';
        $this->data['objecttable'] = 'block_workload_cohorts';
    }
    public static function get_name(): string {
        return get_string('event_members_removed', 'block_workload');
    }
    public function get_description(): string {
        return "User {$this->userid} removed {$this->other['count']} member(s) from workload cohort "
            . "'{$this->other['cohortname']}' (id {$this->objectid}).";
    }
}
