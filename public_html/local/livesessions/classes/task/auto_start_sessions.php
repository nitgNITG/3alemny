<?php
/**
 * Scheduled task: auto-transition scheduled→live and live→completed
 * based on starttime/endtime. Runs every minute.
 *
 * This handles the case where the teacher never manually starts/ends
 * the session — cron takes over.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions\task;

defined('MOODLE_INTERNAL') || die();

class auto_start_sessions extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_autosstartsessions', 'local_livesessions');
    }

    public function execute(): void {
        global $DB;

        $now = time();

        // ---- scheduled → live (starttime passed) ----
        $to_start = $DB->get_records_select(
            'livesessions_sessions',
            "status = 'scheduled' AND starttime <= :now AND endtime > :now2",
            ['now' => $now, 'now2' => $now]
        );
        foreach ($to_start as $s) {
            try {
                \local_livesessions\session_manager::start_session($s->id);
                mtrace("  Session {$s->id} ({$s->title}): scheduled → live");
            } catch (\Throwable $e) {
                mtrace("  Session {$s->id}: could not start — " . $e->getMessage());
            }
        }

        // ---- live → completed (endtime passed) ----
        $to_complete = $DB->get_records_select(
            'livesessions_sessions',
            "status = 'live' AND endtime > 0 AND endtime <= :now",
            ['now' => $now]
        );
        foreach ($to_complete as $s) {
            try {
                \local_livesessions\session_manager::complete_session($s->id);
                mtrace("  Session {$s->id} ({$s->title}): live → completed");
            } catch (\Throwable $e) {
                mtrace("  Session {$s->id}: could not complete — " . $e->getMessage());
            }
        }

        if (empty($to_start) && empty($to_complete)) {
            // Silent when nothing to do — avoids log noise.
        }
    }
}
