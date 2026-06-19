<?php
/**
 * Value object: result of attendance_engine::calculate_one_student().
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class engine_result {

    public string $new_status;
    public float  $new_percent;
    public int    $new_duration;        // seconds
    public int    $new_recording_access; // 0 or 1
    public string $reason;

    public function __construct(
        string $new_status,
        float  $new_percent,
        int    $new_duration,
        int    $new_recording_access,
        string $reason = ''
    ) {
        $this->new_status           = $new_status;
        $this->new_percent          = $new_percent;
        $this->new_duration         = $new_duration;
        $this->new_recording_access = $new_recording_access;
        $this->reason               = $reason;
    }
}
