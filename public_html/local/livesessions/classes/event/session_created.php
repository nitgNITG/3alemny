<?php
namespace local_livesessions\event;
defined('MOODLE_INTERNAL') || die();

class session_created extends \core\event\base {
    protected function init() {
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'livesessions_sessions';
    }
    public static function get_name(): string {
        return get_string('event_sessioncreated', 'local_livesessions');
    }
    public function get_description(): string {
        return "User {$this->userid} created live session {$this->objectid}.";
    }
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/livesessions/view.php', ['id' => $this->objectid]);
    }
}
