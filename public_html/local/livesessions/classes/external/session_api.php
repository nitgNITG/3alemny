<?php
/**
 * External (web service) functions for session management.
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
use external_multiple_structure;
use context_course;
use local_livesessions\session_manager;

class session_api extends external_api {

    // ---------------------------------------------------------------
    // create_session
    // ---------------------------------------------------------------

    public static function create_session_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'           => new external_value(PARAM_INT,  'Course ID'),
            'teacherid'          => new external_value(PARAM_INT,  'Teacher user ID', VALUE_DEFAULT, 0),
            'title'              => new external_value(PARAM_TEXT, 'Session title'),
            'description'        => new external_value(PARAM_RAW,  'Session description', VALUE_DEFAULT, ''),
            'provider'           => new external_value(PARAM_ALPHA,'Provider: zoom|100ms|agora|bigbluebutton', VALUE_DEFAULT, 'zoom'),
            'provider_meeting_id'=> new external_value(PARAM_RAW,  'External meeting ID', VALUE_DEFAULT, ''),
            'join_url'           => new external_value(PARAM_URL,  'Student join URL', VALUE_DEFAULT, ''),
            'host_url'           => new external_value(PARAM_URL,  'Host join URL', VALUE_DEFAULT, ''),
            'starttime'          => new external_value(PARAM_INT,  'Start time (Unix timestamp)'),
            'endtime'            => new external_value(PARAM_INT,  'End time (Unix timestamp)'),
            'capacity'           => new external_value(PARAM_INT,  'Max students (0=unlimited)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function create_session(int $courseid, int $teacherid, string $title, string $description,
            string $provider, string $provider_meeting_id, string $join_url, string $host_url,
            int $starttime, int $endtime, int $capacity): array {

        $params = self::validate_parameters(self::create_session_parameters(), compact(
            'courseid','teacherid','title','description','provider','provider_meeting_id',
            'join_url','host_url','starttime','endtime','capacity'
        ));

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/livesessions:createSession', $context);

        $id = session_manager::create_session($params);

        return ['id' => $id, 'success' => true, 'message' => get_string('sessioncreated', 'local_livesessions')];
    }

    public static function create_session_returns(): external_single_structure {
        return new external_single_structure([
            'id'      => new external_value(PARAM_INT,  'New session ID'),
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }

    // ---------------------------------------------------------------
    // update_session
    // ---------------------------------------------------------------

    public static function update_session_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid'          => new external_value(PARAM_INT,  'Session ID'),
            'title'              => new external_value(PARAM_TEXT, 'Session title',      VALUE_DEFAULT, ''),
            'description'        => new external_value(PARAM_RAW,  'Description',        VALUE_DEFAULT, ''),
            'provider'           => new external_value(PARAM_ALPHA,'Provider',           VALUE_DEFAULT, ''),
            'provider_meeting_id'=> new external_value(PARAM_RAW,  'External meeting ID',VALUE_DEFAULT, ''),
            'join_url'           => new external_value(PARAM_URL,  'Student join URL',   VALUE_DEFAULT, ''),
            'host_url'           => new external_value(PARAM_URL,  'Host join URL',      VALUE_DEFAULT, ''),
            'starttime'          => new external_value(PARAM_INT,  'Start time',         VALUE_DEFAULT, 0),
            'endtime'            => new external_value(PARAM_INT,  'End time',           VALUE_DEFAULT, 0),
            'capacity'           => new external_value(PARAM_INT,  'Capacity',           VALUE_DEFAULT, -1),
        ]);
    }

    public static function update_session(int $sessionid, string $title, string $description,
            string $provider, string $provider_meeting_id, string $join_url, string $host_url,
            int $starttime, int $endtime, int $capacity): array {

        $params = self::validate_parameters(self::update_session_parameters(), compact(
            'sessionid','title','description','provider','provider_meeting_id',
            'join_url','host_url','starttime','endtime','capacity'
        ));

        global $DB;
        $session = $DB->get_record('livesessions_sessions', ['id' => $params['sessionid']], '*', MUST_EXIST);
        $context = context_course::instance($session->courseid);
        self::validate_context($context);
        require_capability('local/livesessions:editSession', $context);

        // Strip empty optionals so they don't overwrite.
        $data = array_filter($params, fn($v) => $v !== '' && $v !== 0 && $v !== -1);
        unset($data['sessionid']);

        session_manager::update_session((int)$params['sessionid'], $data);

        return ['success' => true, 'message' => get_string('sessionupdated', 'local_livesessions')];
    }

    public static function update_session_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }

    // ---------------------------------------------------------------
    // cancel_session
    // ---------------------------------------------------------------

    public static function cancel_session_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session ID'),
        ]);
    }

    public static function cancel_session(int $sessionid): array {
        $params = self::validate_parameters(self::cancel_session_parameters(), ['sessionid' => $sessionid]);

        global $DB;
        $session = $DB->get_record('livesessions_sessions', ['id' => $params['sessionid']], '*', MUST_EXIST);
        $context = context_course::instance($session->courseid);
        self::validate_context($context);
        require_capability('local/livesessions:cancelSession', $context);

        session_manager::cancel_session((int)$params['sessionid']);

        return ['success' => true, 'message' => get_string('sessioncancelled', 'local_livesessions')];
    }

    public static function cancel_session_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }

    // ---------------------------------------------------------------
    // get_sessions
    // ---------------------------------------------------------------

    public static function get_sessions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'  => new external_value(PARAM_INT,  'Filter by course',   VALUE_DEFAULT, 0),
            'teacherid' => new external_value(PARAM_INT,  'Filter by teacher',  VALUE_DEFAULT, 0),
            'status'    => new external_value(PARAM_ALPHA,'Filter by status',   VALUE_DEFAULT, ''),
            'from_time' => new external_value(PARAM_INT,  'Start of date range',VALUE_DEFAULT, 0),
            'to_time'   => new external_value(PARAM_INT,  'End of date range',  VALUE_DEFAULT, 0),
            'limitfrom' => new external_value(PARAM_INT,  'Pagination offset',  VALUE_DEFAULT, 0),
            'limitnum'  => new external_value(PARAM_INT,  'Number of results',  VALUE_DEFAULT, 50),
        ]);
    }

    public static function get_sessions(int $courseid, int $teacherid, string $status,
            int $from_time, int $to_time, int $limitfrom, int $limitnum): array {

        $params = self::validate_parameters(self::get_sessions_parameters(), compact(
            'courseid','teacherid','status','from_time','to_time','limitfrom','limitnum'
        ));

        if ($params['courseid']) {
            $context = context_course::instance($params['courseid']);
            self::validate_context($context);
        } else {
            self::validate_context(\context_system::instance());
        }

        $filters = array_filter([
            'courseid'  => $params['courseid'] ?: null,
            'teacherid' => $params['teacherid'] ?: null,
            'status'    => $params['status'] ?: null,
            'from_time' => $params['from_time'] ?: null,
            'to_time'   => $params['to_time'] ?: null,
        ]);

        $sessions = session_manager::get_sessions($filters, $params['limitfrom'], $params['limitnum']);

        return array_map(fn($s) => (array) $s, $sessions);
    }

    public static function get_sessions_returns(): external_multiple_structure {
        return new external_multiple_structure(self::session_structure());
    }

    // ---------------------------------------------------------------
    // get_session
    // ---------------------------------------------------------------

    public static function get_session_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session ID'),
        ]);
    }

    public static function get_session(int $sessionid): array {
        $params = self::validate_parameters(self::get_session_parameters(), ['sessionid' => $sessionid]);
        $session = session_manager::get_session((int)$params['sessionid']);
        $context = context_course::instance($session->courseid);
        self::validate_context($context);
        return (array) $session;
    }

    public static function get_session_returns(): external_single_structure {
        return self::session_structure();
    }

    // ---------------------------------------------------------------
    // Shared return structure
    // ---------------------------------------------------------------

    private static function session_structure(): external_single_structure {
        return new external_single_structure([
            'id'                  => new external_value(PARAM_INT,   'Session ID'),
            'courseid'            => new external_value(PARAM_INT,   'Course ID'),
            'teacherid'           => new external_value(PARAM_INT,   'Teacher user ID'),
            'title'               => new external_value(PARAM_TEXT,  'Title'),
            'description'         => new external_value(PARAM_RAW,   'Description'),
            'provider'            => new external_value(PARAM_ALPHA, 'Provider'),
            'provider_meeting_id' => new external_value(PARAM_RAW,   'External meeting ID'),
            'join_url'            => new external_value(PARAM_URL,   'Student join URL'),
            'host_url'            => new external_value(PARAM_URL,   'Host join URL'),
            'starttime'           => new external_value(PARAM_INT,   'Start timestamp'),
            'endtime'             => new external_value(PARAM_INT,   'End timestamp'),
            'duration'            => new external_value(PARAM_INT,   'Duration (minutes)'),
            'capacity'            => new external_value(PARAM_INT,   'Capacity (0=unlimited)'),
            'status'              => new external_value(PARAM_ALPHA, 'Status'),
            'recording_status'    => new external_value(PARAM_ALPHA, 'Recording status'),
            'timecreated'         => new external_value(PARAM_INT,   'Created timestamp'),
            'timemodified'        => new external_value(PARAM_INT,   'Modified timestamp'),
            'createdby'           => new external_value(PARAM_INT,   'Created by user ID'),
        ]);
    }
}
