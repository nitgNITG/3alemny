<?php
/**
 * External API for attendance recording.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_livesessions\attendance_manager;

class attendance_api extends external_api {

    public static function record_attendance_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT,   'Session ID'),
            'userid'    => new external_value(PARAM_INT,   'Student user ID'),
            'action'    => new external_value(PARAM_ALPHA, 'join or leave'),
            'timestamp' => new external_value(PARAM_INT,   'Event timestamp (0=now)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function record_attendance(int $sessionid, int $userid, string $action, int $timestamp): array {
        $params = self::validate_parameters(self::record_attendance_parameters(),
            compact('sessionid', 'userid', 'action', 'timestamp'));

        global $DB;
        $session = $DB->get_record('livesessions_sessions', ['id' => $params['sessionid']], '*', MUST_EXIST);
        $context = \context_course::instance($session->courseid);
        self::validate_context($context);

        if ($params['action'] === 'join') {
            $id = attendance_manager::record_join($params['sessionid'], $params['userid'], $params['timestamp']);
        } else {
            attendance_manager::record_leave($params['sessionid'], $params['userid'], $params['timestamp']);
            $id = 0;
        }

        return ['success' => true, 'attendanceid' => $id];
    }

    public static function record_attendance_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, 'Success'),
            'attendanceid' => new external_value(PARAM_INT,  'Attendance record ID'),
        ]);
    }
}
