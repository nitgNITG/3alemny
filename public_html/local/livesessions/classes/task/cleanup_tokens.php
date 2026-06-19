<?php
/**
 * Scheduled task: delete expired join tokens and old provider events.
 * Runs daily.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions\task;

defined('MOODLE_INTERNAL') || die();

class cleanup_tokens extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_cleanuptokens', 'local_livesessions');
    }

    public function execute(): void {
        global $DB;

        $now = time();

        // Delete expired or used join tokens older than 24 hours.
        $cutoff = $now - 86400;
        $deleted_tokens = $DB->count_records_select(
            'livesessions_join_tokens',
            'expires < :cutoff OR used = 1',
            ['cutoff' => $cutoff]
        );
        $DB->delete_records_select(
            'livesessions_join_tokens',
            'expires < :cutoff OR used = 1',
            ['cutoff' => $cutoff]
        );

        // Delete processed provider events older than 7 days.
        $week_ago = $now - (7 * 86400);
        $deleted_events = $DB->count_records_select(
            'livesessions_provider_events',
            'processed = 1 AND timecreated < :week_ago',
            ['week_ago' => $week_ago]
        );
        $DB->delete_records_select(
            'livesessions_provider_events',
            'processed = 1 AND timecreated < :week_ago',
            ['week_ago' => $week_ago]
        );

        mtrace("local_livesessions cleanup: {$deleted_tokens} expired tokens removed, "
             . "{$deleted_events} processed events removed.");
    }
}
