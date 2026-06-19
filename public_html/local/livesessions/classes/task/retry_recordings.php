<?php
/**
 * Scheduled task: re-queue stalled recordings that are overdue for retry.
 * Runs every 10 minutes.
 *
 * This catches cases where the adhoc task runner didn't fire (e.g., server
 * was down during backoff window) and recordings remain stuck in 'queued'
 * state past their retry_after timestamp.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions\task;

defined('MOODLE_INTERNAL') || die();

use local_livesessions\recording_manager;

class retry_recordings extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_retryrecordings', 'local_livesessions');
    }

    public function execute(): void {
        $pending = recording_manager::get_queued_for_processing();

        if (empty($pending)) {
            return;
        }

        mtrace("local_livesessions retry_recordings: found " . count($pending) . " recording(s) to retry.");

        foreach ($pending as $rec) {
            // Re-queue adhoc task.
            process_recording::queue((int)$rec->sessionid, (int)$rec->id);
            mtrace("  Queued recording {$rec->id} (session {$rec->sessionid}, attempt {$rec->retry_count})");
        }
    }
}
