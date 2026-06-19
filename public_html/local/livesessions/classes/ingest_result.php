<?php
/**
 * Value object: result of recording_manager::ingest().
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class ingest_result {

    public int    $recording_id;
    public bool   $duplicate;
    public string $status;
    public string $message;

    public function __construct(
        int    $recording_id,
        bool   $duplicate,
        string $status,
        string $message = ''
    ) {
        $this->recording_id = $recording_id;
        $this->duplicate    = $duplicate;
        $this->status       = $status;
        $this->message      = $message;
    }
}
