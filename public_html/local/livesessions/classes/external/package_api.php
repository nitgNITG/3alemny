<?php
/**
 * External API for package / credit management — Feature 3.1 / 3.2 / 3.3.
 *
 * Exposed Moodle web-service functions:
 *   local_livesessions_consume_credit   — deduct one credit (credit_mode = credit)
 *   local_livesessions_get_my_credits   — student's remaining credits
 *   local_livesessions_assign_package   — admin: assign a package to a student
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use local_livesessions\package_manager;

class package_api extends external_api {

    // ================================================================
    // consume_credit
    // ================================================================

    public static function consume_credit_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'    => new external_value(PARAM_INT, 'Student user ID'),
            'sessionid' => new external_value(PARAM_INT, 'Session ID'),
        ]);
    }

    /**
     * Deduct one session credit from the student's earliest-expiring active subscription.
     * Idempotent: safe to call multiple times for the same (userid, sessionid) pair.
     */
    public static function consume_credit(int $userid, int $sessionid): array {
        $params = self::validate_parameters(self::consume_credit_parameters(),
            compact('userid', 'sessionid'));

        self::validate_context(\context_system::instance());

        try {
            $log       = package_manager::consume_credit($params['userid'], $params['sessionid']);
            $remaining = package_manager::get_remaining_credits($params['userid']);

            return [
                'success'   => true,
                'remaining' => $remaining,
                'message'   => get_string('creditconsumed', 'local_livesessions'),
            ];
        } catch (\moodle_exception $e) {
            return [
                'success'   => false,
                'remaining' => 0,
                'message'   => $e->getMessage(),
            ];
        }
    }

    public static function consume_credit_returns(): external_single_structure {
        return new external_single_structure([
            'success'   => new external_value(PARAM_BOOL, 'Whether the credit was deducted'),
            'remaining' => new external_value(PARAM_INT,  'Remaining session credits across all packages'),
            'message'   => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }

    // ================================================================
    // get_my_credits
    // ================================================================

    public static function get_my_credits_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
        ]);
    }

    public static function get_my_credits(int $userid): array {
        $params = self::validate_parameters(self::get_my_credits_parameters(), compact('userid'));
        self::validate_context(\context_system::instance());

        $remaining = package_manager::get_remaining_credits($params['userid']);
        $subs      = package_manager::get_student_subscriptions($params['userid'], true);

        $sub_list = [];
        foreach ($subs as $s) {
            $sub_list[] = [
                'sub_id'      => (int)$s->id,
                'package_name'=> $s->package_name,
                'remaining'   => (int)$s->remaining_sessions,
                'total'       => (int)$s->total_sessions,
                'expiry_date' => $s->expiry_date ? (int)$s->expiry_date : 0,
            ];
        }

        return [
            'total_remaining' => $remaining,
            'subscriptions'   => $sub_list,
        ];
    }

    public static function get_my_credits_returns(): external_single_structure {
        return new external_single_structure([
            'total_remaining' => new external_value(PARAM_INT, 'Total credits across all active subscriptions'),
            'subscriptions'   => new external_multiple_structure(
                new external_single_structure([
                    'sub_id'       => new external_value(PARAM_INT,  'Subscription row ID'),
                    'package_name' => new external_value(PARAM_TEXT, 'Package name'),
                    'remaining'    => new external_value(PARAM_INT,  'Remaining sessions in this subscription'),
                    'total'        => new external_value(PARAM_INT,  'Total sessions in this subscription'),
                    'expiry_date'  => new external_value(PARAM_INT,  'Unix timestamp of expiry; 0 = no limit'),
                ])
            ),
        ]);
    }

    // ================================================================
    // assign_package
    // ================================================================

    public static function assign_package_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'    => new external_value(PARAM_INT, 'Target student user ID'),
            'packageid' => new external_value(PARAM_INT, 'Package ID to assign'),
        ]);
    }

    public static function assign_package(int $userid, int $packageid): array {
        global $USER;

        $params = self::validate_parameters(self::assign_package_parameters(),
            compact('userid', 'packageid'));

        self::validate_context(\context_system::instance());
        require_capability('local/livesessions:manageSessions', \context_system::instance());

        try {
            $sub_id = package_manager::assign_package(
                $params['userid'], $params['packageid'], $USER->id
            );
            return ['success' => true, 'sub_id' => $sub_id, 'message' => get_string('packageassigned', 'local_livesessions')];
        } catch (\moodle_exception $e) {
            return ['success' => false, 'sub_id' => 0, 'message' => $e->getMessage()];
        }
    }

    public static function assign_package_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'sub_id'  => new external_value(PARAM_INT,  'New subscription ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }
}
