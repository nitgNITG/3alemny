<?php
/**
 * Scheduled task: batch-finalise attendance for all sessions that ended
 * more than 5 minutes ago but have not yet been finalised.
 *
 * Runs every 5 minutes via Moodle cron.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions\task;

defined('MOODLE_INTERNAL') || die();

class finalise_attendance extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_finaliseattendance', 'local_livesessions');
    }

    public function execute(): void {
        $grace = (int)(get_config('local_livesessions', 'finalise_grace_minutes') ?: 5);

        mtrace("local_livesessions: running attendance finalisation (grace={$grace} min)...");

        $results = \local_livesessions\attendance_engine::batch_finalise($grace);

        if (empty($results)) {
            mtrace("  No sessions pending finalisation.");
            return;
        }

        foreach ($results as $r) {
            if ($r->skipped) {
                mtrace("  Session {$r->sessionid}: SKIPPED — {$r->reason}");
            } else {
                mtrace(sprintf(
                    "  Session %d: %d students — %d attended, %d partial, %d absent (threshold %.0f%%, rate %.1f%%)",
                    $r->sessionid, $r->total, $r->attended, $r->partial, $r->absent,
                    $r->threshold, $r->attendance_rate()
                ));
            }
        }

        mtrace("local_livesessions: finalisation complete. " . count($results) . " session(s) processed.");
    }
}
