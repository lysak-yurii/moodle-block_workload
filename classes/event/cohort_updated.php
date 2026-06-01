<?php
namespace block_workload\event;
defined('MOODLE_INTERNAL') || die();

class cohort_updated extends base {
    protected function init(): void {
        parent::init();
        $this->data['crud']        = 'u';
        $this->data['objecttable'] = 'block_workload_cohorts';
    }
    public static function get_name(): string {
        return get_string('event_cohort_updated', 'block_workload');
    }
    public function get_description(): string {
        return "User {$this->userid} updated workload cohort '{$this->other['name']}' (id {$this->objectid}).";
    }
}
