<?php
namespace block_workload\event;
defined('MOODLE_INTERNAL') || die();

class cohort_created extends base {
    protected function init(): void {
        parent::init();
        $this->data['crud']        = 'c';
        $this->data['objecttable'] = 'block_workload_cohorts';
    }
    public static function get_name(): string {
        return get_string('event_cohort_created', 'block_workload');
    }
    public function get_description(): string {
        return "User {$this->userid} created workload cohort '{$this->other['name']}' (id {$this->objectid}).";
    }
}
