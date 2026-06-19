<?php
/**
 * Mobile REST API — Feature 5.1 / 5.2 / 5.3.
 *
 * Endpoints optimised for the 3alemny mobile app. All return clean
 * flat structures (no nested objects requiring client-side unwrapping).
 *
 * Functions exposed:
 *   5.1  local_livesessions_mobile_get_sessions      — course session list
 *        local_livesessions_mobile_get_session        — single session detail
 *   5.2  local_livesessions_mobile_join_session       — join flow (returns provider URL)
 *        local_livesessions_mobile_leave_session      — record attendance leave
 *   5.3  local_livesessions_mobile_get_recording_token — signed playback URL
 *        local_livesessions_mobile_get_my_packages    — student credits summary
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
use local_livesessions\session_manager;
use local_livesessions\join_manager;
use local_livesessions\attendance_manager;
use local_livesessions\recording_access_manager;
use local_livesessions\package_manager;
use local_livesessions\bunny_uploader;
use local_livesessions\vdocipher_uploader;

class mobile_api extends external_api {

    // ================================================================
    // 5.1 — Session listing
    // ================================================================

    public static function get_sessions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT,   'Course ID'),
            'status'   => new external_value(PARAM_ALPHA, 'Filter: scheduled|live|completed|cancelled|all', VALUE_DEFAULT, 'all'),
            'limit'    => new external_value(PARAM_INT,   'Max results', VALUE_DEFAULT, 50),
            'offset'   => new external_value(PARAM_INT,   'Pagination offset', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_sessions(int $courseid, string $status = 'all',
                                        int $limit = 50, int $offset = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_sessions_parameters(),
            compact('courseid','status','limit','offset'));

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/livesessions:joinSession', $context);

        $filters = ['courseid' => $params['courseid']];
        if ($params['status'] !== 'all') {
            $filters['status'] = $params['status'];
        }

        $sessions = session_manager::get_sessions($filters);
        $now      = time();
        $result   = [];

        foreach (array_slice($sessions, $params['offset'], $params['limit']) as $s) {
            $att = $DB->get_record('livesessions_attendance',
                ['sessionid' => $s->id, 'userid' => $USER->id]);

            $result[] = [
                'id'              => (int)$s->id,
                'title'           => format_string($s->title),
                'description'     => format_text($s->description ?? ''),
                'provider'        => $s->provider,
                'status'          => $s->status,
                'starttime'       => (int)$s->starttime,
                'endtime'         => (int)$s->endtime,
                'duration'        => (int)$s->duration,
                'can_join'        => ($s->status === 'live' || ($s->status === 'scheduled' && $s->starttime - $now <= 900)),
                'join_url'        => $s->join_url ?? '',
                'attended'        => $att ? $att->attendance_status : '',
                'recording_ready' => $DB->record_exists('livesessions_recordings',
                    ['sessionid' => $s->id, 'status' => 'ready']),
                'recording_access'=> $att ? (bool)(int)$att->recording_access : false,
            ];
        }

        return ['sessions' => $result, 'total' => count($sessions)];
    }

    public static function get_sessions_returns(): external_single_structure {
        return new external_single_structure([
            'total'    => new external_value(PARAM_INT, 'Total sessions matching filter'),
            'sessions' => new external_multiple_structure(
                new external_single_structure([
                    'id'               => new external_value(PARAM_INT,  'Session ID'),
                    'title'            => new external_value(PARAM_TEXT, 'Session title'),
                    'description'      => new external_value(PARAM_RAW,  'Description HTML'),
                    'provider'         => new external_value(PARAM_TEXT, 'Provider name'),
                    'status'           => new external_value(PARAM_ALPHA,'Session status'),
                    'starttime'        => new external_value(PARAM_INT,  'Start unix timestamp'),
                    'endtime'          => new external_value(PARAM_INT,  'End unix timestamp'),
                    'duration'         => new external_value(PARAM_INT,  'Duration in minutes'),
                    'can_join'         => new external_value(PARAM_BOOL, 'Whether student can join now'),
                    'join_url'         => new external_value(PARAM_URL,  'Provider join URL'),
                    'attended'         => new external_value(PARAM_TEXT, 'Student attendance status'),
                    'recording_ready'  => new external_value(PARAM_BOOL, 'Recording available'),
                    'recording_access' => new external_value(PARAM_BOOL, 'Student has recording access'),
                ])
            ),
        ]);
    }

    // ================================================================
    // 5.1 — Single session detail
    // ================================================================

    public static function get_session_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session ID'),
        ]);
    }

    public static function get_session(int $sessionid): array {
        global $DB, $USER;

        $params  = self::validate_parameters(self::get_session_parameters(), compact('sessionid'));
        $session = $DB->get_record('livesessions_sessions', ['id' => $params['sessionid']], '*', MUST_EXIST);

        $context = \context_course::instance($session->courseid);
        self::validate_context($context);
        require_capability('local/livesessions:joinSession', $context);

        $now = time();
        $att = $DB->get_record('livesessions_attendance',
            ['sessionid' => $session->id, 'userid' => $USER->id]);
        $rec = $DB->get_record('livesessions_recordings',
            ['sessionid' => $session->id, 'status' => 'ready'], '*', IGNORE_MISSING);

        return [
            'id'               => (int)$session->id,
            'courseid'         => (int)$session->courseid,
            'title'            => format_string($session->title),
            'description'      => format_text($session->description ?? ''),
            'provider'         => $session->provider,
            'provider_meeting_id' => $session->provider_meeting_id ?? '',
            'status'           => $session->status,
            'starttime'        => (int)$session->starttime,
            'endtime'          => (int)$session->endtime,
            'duration'         => (int)$session->duration,
            'capacity'         => (int)$session->capacity,
            'can_join'         => ($session->status === 'live' ||
                ($session->status === 'scheduled' && $session->starttime - $now <= 900)),
            'join_url'         => $session->join_url ?? '',
            'attended'         => $att ? $att->attendance_status    : '',
            'duration_attended'=> $att ? (int)$att->duration_attended : 0,
            'attendance_percent' => $att ? (float)$att->attendance_percent : 0.0,
            'recording_ready'  => (bool)$rec,
            'recording_access' => $att ? (bool)(int)$att->recording_access : false,
            'finalised'        => !empty($session->finalised_at),
        ];
    }

    public static function get_session_returns(): external_single_structure {
        return new external_single_structure([
            'id'                  => new external_value(PARAM_INT,   'Session ID'),
            'courseid'            => new external_value(PARAM_INT,   'Course ID'),
            'title'               => new external_value(PARAM_TEXT,  'Title'),
            'description'         => new external_value(PARAM_RAW,   'Description HTML'),
            'provider'            => new external_value(PARAM_TEXT,  'Provider'),
            'provider_meeting_id' => new external_value(PARAM_TEXT,  'Provider meeting/room ID'),
            'status'              => new external_value(PARAM_ALPHA, 'Status'),
            'starttime'           => new external_value(PARAM_INT,   'Start timestamp'),
            'endtime'             => new external_value(PARAM_INT,   'End timestamp'),
            'duration'            => new external_value(PARAM_INT,   'Duration minutes'),
            'capacity'            => new external_value(PARAM_INT,   'Max capacity (0=unlimited)'),
            'can_join'            => new external_value(PARAM_BOOL,  'Can join now'),
            'join_url'            => new external_value(PARAM_URL,   'Provider join URL'),
            'attended'            => new external_value(PARAM_TEXT,  'Attendance status'),
            'duration_attended'   => new external_value(PARAM_INT,   'Seconds attended'),
            'attendance_percent'  => new external_value(PARAM_FLOAT, 'Attendance percentage'),
            'recording_ready'     => new external_value(PARAM_BOOL,  'Recording available'),
            'recording_access'    => new external_value(PARAM_BOOL,  'Student has recording access'),
            'finalised'           => new external_value(PARAM_BOOL,  'Attendance finalised'),
        ]);
    }

    // ================================================================
    // 5.2 — Join / Leave
    // ================================================================

    public static function join_session_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid'    => new external_value(PARAM_INT,  'Session ID'),
            'device_token' => new external_value(PARAM_TEXT, 'Unique device identifier', VALUE_DEFAULT, ''),
        ]);
    }

    public static function join_session(int $sessionid, string $device_token = ''): array {
        global $USER;

        $params = self::validate_parameters(self::join_session_parameters(),
            compact('sessionid','device_token'));

        try {
            $result = join_manager::initiate_join($params['sessionid'], $USER->id);

            // Register device if device_token provided (Feature 6.1 device limit).
            if ($params['device_token']) {
                \local_livesessions\device_manager::register_device(
                    $params['sessionid'], $USER->id, $params['device_token']
                );
            }

            return [
                'success'     => true,
                'join_url'    => $result->join_url ?? '',
                'is_rejoin'   => $result->is_rejoin,
                'credits_remaining' => package_manager::get_remaining_credits($USER->id),
                'error'       => '',
            ];
        } catch (\moodle_exception $e) {
            return [
                'success'           => false,
                'join_url'          => '',
                'is_rejoin'         => false,
                'credits_remaining' => 0,
                'error'             => $e->getMessage(),
            ];
        }
    }

    public static function join_session_returns(): external_single_structure {
        return new external_single_structure([
            'success'           => new external_value(PARAM_BOOL, 'Whether join was successful'),
            'join_url'          => new external_value(PARAM_URL,  'Provider URL to open'),
            'is_rejoin'         => new external_value(PARAM_BOOL, 'Re-joining an existing session'),
            'credits_remaining' => new external_value(PARAM_INT,  'Remaining credits after join'),
            'error'             => new external_value(PARAM_TEXT, 'Error message if not successful'),
        ]);
    }

    public static function leave_session_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid'    => new external_value(PARAM_INT,  'Session ID'),
            'device_token' => new external_value(PARAM_TEXT, 'Device token to release', VALUE_DEFAULT, ''),
        ]);
    }

    public static function leave_session(int $sessionid, string $device_token = ''): array {
        global $USER;

        $params = self::validate_parameters(self::leave_session_parameters(),
            compact('sessionid','device_token'));

        join_manager::record_leave($params['sessionid'], $USER->id, time());

        // Release device slot.
        if ($params['device_token']) {
            \local_livesessions\device_manager::release_device(
                $params['sessionid'], $USER->id, $params['device_token']
            );
        }

        $att = \local_livesessions\attendance_manager::get_student_attendance(
            $params['sessionid'], $USER->id
        );

        return [
            'success'           => true,
            'duration_attended' => $att ? (int)$att->duration_attended : 0,
            'attendance_percent'=> $att ? (float)$att->attendance_percent : 0.0,
        ];
    }

    public static function leave_session_returns(): external_single_structure {
        return new external_single_structure([
            'success'            => new external_value(PARAM_BOOL,  'Success'),
            'duration_attended'  => new external_value(PARAM_INT,   'Total seconds attended'),
            'attendance_percent' => new external_value(PARAM_FLOAT, 'Attendance percentage'),
        ]);
    }

    // ================================================================
    // 5.3 — Recording playback token
    // ================================================================

    public static function get_recording_token_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT,  'Session ID'),
            'client_ip' => new external_value(PARAM_TEXT, 'Client IP for token locking (optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function get_recording_token(int $sessionid, string $client_ip = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_recording_token_parameters(),
            compact('sessionid','client_ip'));

        $session = $DB->get_record('livesessions_sessions', ['id' => $params['sessionid']], '*', MUST_EXIST);
        $context = \context_course::instance($session->courseid);
        self::validate_context($context);

        if (!recording_access_manager::can_access_recording($params['sessionid'], $USER->id)) {
            return [
                'success'   => false,
                'url'       => '',
                'provider'  => '',
                'expires'   => 0,
                'error'     => get_string('recordingaccessdenied', 'local_livesessions'),
            ];
        }

        $rec = $DB->get_record('livesessions_recordings', [
            'sessionid' => $params['sessionid'],
            'status'    => 'ready',
        ]);
        if (!$rec) {
            return ['success' => false, 'url' => '', 'provider' => '', 'expires' => 0,
                    'error' => get_string('norecordingready', 'local_livesessions')];
        }

        $provider = $rec->source_provider ?? $rec->provider ?? 'bunny';
        $url      = '';
        $expires  = 0;

        try {
            if ($provider === 'bunny' && !empty($rec->external_id)) {
                $ttl      = (int)(get_config('local_livesessions', 'bunny_token_ttl') ?: 14400);
                $expires  = time() + $ttl;
                $bunny    = new bunny_uploader();
                $url      = $bunny->fresh_signed_url($rec->external_id, $params['client_ip'], $ttl);

            } elseif ($provider === 'vdocipher' && !empty($rec->external_id)) {
                $vdo      = new vdocipher_uploader();
                $otp_data = $vdo->generate_otp($rec->external_id);
                $url      = $vdo->build_embed_url($otp_data['otp'], $otp_data['playbackInfo']);
                $expires  = time() + (int)(get_config('local_livesessions', 'vdocipher_otp_ttl') ?: 300);

            } else {
                $url     = $rec->embed_url ?? '';
                $expires = time() + 14400;
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'url' => '', 'provider' => $provider,
                    'expires' => 0, 'error' => $e->getMessage()];
        }

        return [
            'success'  => true,
            'url'      => $url,
            'provider' => $provider,
            'expires'  => $expires,
            'error'    => '',
        ];
    }

    public static function get_recording_token_returns(): external_single_structure {
        return new external_single_structure([
            'success'  => new external_value(PARAM_BOOL, 'Whether a URL was generated'),
            'url'      => new external_value(PARAM_RAW,  'Signed playback URL (or OTP embed URL)'),
            'provider' => new external_value(PARAM_TEXT, 'Video provider: bunny|vdocipher|other'),
            'expires'  => new external_value(PARAM_INT,  'URL expiry timestamp'),
            'error'    => new external_value(PARAM_TEXT, 'Error message'),
        ]);
    }

    // ================================================================
    // 5.3 — Student credit summary (for mobile home screen)
    // ================================================================

    public static function get_my_packages_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function get_my_packages(): array {
        global $USER;
        self::validate_parameters(self::get_my_packages_parameters(), []);

        $remaining = package_manager::get_remaining_credits($USER->id);
        $subs      = package_manager::get_student_subscriptions($USER->id, true);

        $list = [];
        foreach ($subs as $s) {
            $list[] = [
                'sub_id'       => (int)$s->id,
                'package_name' => $s->package_name,
                'remaining'    => (int)$s->remaining_sessions,
                'total'        => (int)$s->total_sessions,
                'expiry_date'  => $s->expiry_date ? (int)$s->expiry_date : 0,
                'status'       => $s->status,
            ];
        }

        return ['total_remaining' => $remaining, 'packages' => $list];
    }

    public static function get_my_packages_returns(): external_single_structure {
        return new external_single_structure([
            'total_remaining' => new external_value(PARAM_INT, 'Total credits across all active packages'),
            'packages' => new external_multiple_structure(
                new external_single_structure([
                    'sub_id'       => new external_value(PARAM_INT,   'Subscription ID'),
                    'package_name' => new external_value(PARAM_TEXT,  'Package name'),
                    'remaining'    => new external_value(PARAM_INT,   'Remaining sessions'),
                    'total'        => new external_value(PARAM_INT,   'Total sessions in package'),
                    'expiry_date'  => new external_value(PARAM_INT,   'Expiry unix timestamp; 0 = none'),
                    'status'       => new external_value(PARAM_ALPHA, 'Subscription status'),
                ])
            ),
        ]);
    }
}
