<?php
namespace block_workload\event;
defined('MOODLE_INTERNAL') || die();

class cohort_deleted extends base {
    protected function init(): void {
        parent::init();
        $this->data['crud'] = 'd';
        // No objecttable: the record is already gone when the event fires.
    }
    public static function get_name(): string {
        return get_string('event_cohort_deleted', 'block_workload');
    }
    public function get_description(): string {
        return "User {$this->userid} deleted workload cohort '{$this->other['name']}' (id {$this->objectid}).";
    }
}
