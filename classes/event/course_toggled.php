<?php
namespace block_workload\event;
defined('MOODLE_INTERNAL') || die();

class course_toggled extends base {
    protected function init(): void {
        parent::init();
        $this->data['crud']        = 'u';
        $this->data['objecttable'] = 'block_workload_cohorts';
    }
    public static function get_name(): string {
        return get_string('event_course_toggled', 'block_workload');
    }
    public function get_description(): string {
        $state = $this->other['active'] ? 'activated' : 'deactivated';
        return "User {$this->userid} {$state} course '{$this->other['coursename']}' "
            . "in workload cohort '{$this->other['cohortname']}' (id {$this->objectid}).";
    }
}
