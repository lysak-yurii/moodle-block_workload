<?php
namespace block_workload\event;
defined('MOODLE_INTERNAL') || die();

class courses_assigned extends base {
    protected function init(): void {
        parent::init();
        $this->data['crud']        = 'u';
        $this->data['objecttable'] = 'block_workload_cohorts';
    }
    public static function get_name(): string {
        return get_string('event_courses_assigned', 'block_workload');
    }
    public function get_description(): string {
        return "User {$this->userid} assigned {$this->other['count']} course(s) to workload cohort "
            . "'{$this->other['cohortname']}' (id {$this->objectid}).";
    }
}
