<?php
/**
 * Value object returned by join_manager::initiate_join().
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class join_result {

    public bool      $success;
    public \stdClass $session;
    public \stdClass $attendance;
    public string    $join_url;
    public bool      $is_rejoin;

    public function __construct(
        bool      $success,
        \stdClass $session,
        \stdClass $attendance,
        string    $join_url,
        bool      $is_rejoin = false
    ) {
        $this->success    = $success;
        $this->session    = $session;
        $this->attendance = $attendance;
        $this->join_url   = $join_url;
        $this->is_rejoin  = $is_rejoin;
    }
}
