<?php
/**
 * Scheduled task: expire stale package subscriptions — Feature 3.2.
 *
 * Runs daily and marks any active subscriptions whose expiry_date has passed
 * as 'expired'. Credits on expired subscriptions are no longer usable.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions\task;

defined('MOODLE_INTERNAL') || die();

use local_livesessions\package_manager;

class expire_subscriptions extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_expiresubscriptions', 'local_livesessions');
    }

    public function execute(): void {
        $count = package_manager::expire_stale_subscriptions();
        if ($count > 0) {
            mtrace("expire_subscriptions: marked {$count} subscription(s) as expired.");
        }
    }
}
