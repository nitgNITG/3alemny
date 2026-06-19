<?php
/**
 * Value object: result of attendance_engine::finalise_session().
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class finalise_result {

    public int    $sessionid;
    public bool   $skipped;
    public string $reason;
    public int    $total;
    public int    $attended;
    public int    $partial;
    public int    $absent;
    public float  $threshold;
    public int    $duration_sec;

    public function __construct(
        int    $sessionid,
        bool   $skipped      = false,
        string $reason       = '',
        int    $total        = 0,
        int    $attended     = 0,
        int    $partial      = 0,
        int    $absent       = 0,
        float  $threshold    = 70.0,
        int    $duration_sec = 0
    ) {
        $this->sessionid    = $sessionid;
        $this->skipped      = $skipped;
        $this->reason       = $reason;
        $this->total        = $total;
        $this->attended     = $attended;
        $this->partial      = $partial;
        $this->absent       = $absent;
        $this->threshold    = $threshold;
        $this->duration_sec = $duration_sec;
    }

    public function attendance_rate(): float {
        if ($this->total === 0) return 0.0;
        return round($this->attended / $this->total * 100, 1);
    }

    public function to_array(): array {
        return [
            'sessionid'       => $this->sessionid,
            'skipped'         => $this->skipped,
            'reason'          => $this->reason,
            'total'           => $this->total,
            'attended'        => $this->attended,
            'partial'         => $this->partial,
            'absent'          => $this->absent,
            'threshold'       => $this->threshold,
            'duration_sec'    => $this->duration_sec,
            'attendance_rate' => $this->attendance_rate(),
        ];
    }
}
