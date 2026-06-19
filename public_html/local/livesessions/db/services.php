<?php
/**
 * External service definitions for local_livesessions.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_livesessions_create_session' => [
        'classname'     => 'local_livesessions\external\session_api',
        'methodname'    => 'create_session',
        'description'   => 'Create a new live session',
        'type'          => 'write',
        'capabilities'  => 'local/livesessions:createSession',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_livesessions_update_session' => [
        'classname'     => 'local_livesessions\external\session_api',
        'methodname'    => 'update_session',
        'description'   => 'Update an existing live session',
        'type'          => 'write',
        'capabilities'  => 'local/livesessions:editSession',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_livesessions_cancel_session' => [
        'classname'     => 'local_livesessions\external\session_api',
        'methodname'    => 'cancel_session',
        'description'   => 'Cancel a live session',
        'type'          => 'write',
        'capabilities'  => 'local/livesessions:cancelSession',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_livesessions_get_sessions' => [
        'classname'     => 'local_livesessions\external\session_api',
        'methodname'    => 'get_sessions',
        'description'   => 'Get list of sessions (filtered by course, teacher, status)',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_livesessions_get_session' => [
        'classname'     => 'local_livesessions\external\session_api',
        'methodname'    => 'get_session',
        'description'   => 'Get a single session by id',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_livesessions_record_attendance' => [
        'classname'     => 'local_livesessions\external\attendance_api',
        'methodname'    => 'record_attendance',
        'description'   => 'Record student join/leave for attendance tracking',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_livesessions_upload_recording' => [
        'classname'     => 'local_livesessions\external\recording_api',
        'methodname'    => 'upload_recording',
        'description'   => 'Webhook endpoint — provider notifies recording is ready',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => false,
        'capabilities'  => '',
    ],

    'local_livesessions_grant_access' => [
        'classname'     => 'local_livesessions\external\recording_api',
        'methodname'    => 'grant_access',
        'description'   => 'Grant a student access to a recording',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_livesessions_consume_credit' => [
        'classname'     => 'local_livesessions\external\package_api',
        'methodname'    => 'consume_credit',
        'description'   => 'Deduct one session credit from a student package',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    // Feature 3.2 / 3.3
    'local_livesessions_get_my_credits' => [
        'classname'     => 'local_livesessions\external\package_api',
        'methodname'    => 'get_my_credits',
        'description'   => 'Get remaining session credits for a student',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_livesessions_assign_package' => [
        'classname'     => 'local_livesessions\external\package_api',
        'methodname'    => 'assign_package',
        'description'   => 'Admin: assign a session package to a student',
        'type'          => 'write',
        'capabilities'  => 'local/livesessions:manageSessions',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    // ---- Feature 5.1 — Mobile: Session listing ----
    'local_livesessions_mobile_get_sessions' => [
        'classname'     => 'local_livesessions\external\mobile_api',
        'methodname'    => 'get_sessions',
        'description'   => 'Mobile: get paginated list of sessions for a course',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_livesessions_mobile_get_session' => [
        'classname'     => 'local_livesessions\external\mobile_api',
        'methodname'    => 'get_session',
        'description'   => 'Mobile: get full session detail including student attendance stats',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    // ---- Feature 5.2 — Mobile: Join / Leave ----
    'local_livesessions_mobile_join_session' => [
        'classname'     => 'local_livesessions\external\mobile_api',
        'methodname'    => 'join_session',
        'description'   => 'Mobile: initiate session join, returns provider join URL',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_livesessions_mobile_leave_session' => [
        'classname'     => 'local_livesessions\external\mobile_api',
        'methodname'    => 'leave_session',
        'description'   => 'Mobile: record session leave and release device slot',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    // ---- Feature 5.3 — Mobile: Recording token + Credits ----
    'local_livesessions_mobile_get_recording_token' => [
        'classname'     => 'local_livesessions\external\mobile_api',
        'methodname'    => 'get_recording_token',
        'description'   => 'Mobile: get a short-lived signed playback URL or VdoCipher OTP',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_livesessions_mobile_get_my_packages' => [
        'classname'     => 'local_livesessions\external\mobile_api',
        'methodname'    => 'get_my_packages',
        'description'   => 'Mobile: get student credit summary across all active packages',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],

];

$services = [
    'Live Sessions Service' => [
        'functions'       => array_keys($functions),
        'restrictedusers' => 0,
        'enabled'         => 1,
        'shortname'       => 'livesessions',
    ],
];
