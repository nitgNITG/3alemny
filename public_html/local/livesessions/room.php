<?php
/**
 * Embedded Live Room — opens the meeting inside the Moodle page.
 *
 * Provider routing:
 *   zoom          → Zoom Meeting SDK (Component View) embedded in <div>
 *   100ms         → 100ms Prebuilt room in <iframe>
 *   bigbluebutton → BBB join URL in <iframe>
 *   agora         → Agora Web SDK embedded (or iframe fallback)
 *
 * Access rules:
 *   - User must be logged in and enrolled.
 *   - Session must be live (or within 15 min of start).
 *   - For teacher/admin: host mode (role=1 for Zoom).
 *   - For student: attendee mode (role=0 for Zoom).
 *   - Attendance join is recorded on page load.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\session_manager;
use local_livesessions\attendance_manager;

$id = required_param('id', PARAM_INT);

$session = session_manager::get_session($id);
$course  = $DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST);
$context = context_course::instance($session->courseid);
require_login($course);

$is_teacher = has_capability('local/livesessions:editSession', $context)
           || has_capability('local/livesessions:manageSessions', context_system::instance());
$is_student = has_capability('local/livesessions:joinSession', $context);

if (!$is_teacher && !$is_student) {
    throw new \moodle_exception('nopermissions', 'error', '', 'join session');
}

// Allow entry 15 minutes early.
$can_enter = in_array($session->status, ['live', 'scheduled'])
          && ($session->starttime - 900) <= time();

if (!$can_enter && !$is_teacher) {
    \core\notification::error(get_string('sessionnotlive', 'local_livesessions'));
    redirect(new moodle_url('/local/livesessions/view.php', ['id' => $id]));
}

// Record attendance join for students.
if ($is_student && !$is_teacher) {
    attendance_manager::record_join($id, $USER->id, time());
}

// ---------------------------------------------------------------
// Page setup
// ---------------------------------------------------------------
$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/room.php', ['id' => $id]);
$PAGE->set_title(format_string($session->title));
$PAGE->set_heading(format_string($session->title));
$PAGE->set_pagelayout('embedded'); // no sidebar, full width

// ---------------------------------------------------------------
// Provider-specific embed
// ---------------------------------------------------------------
$provider = $session->provider;

echo $OUTPUT->header();

// Room container styling.
echo html_writer::start_div('livesessions-room-wrap', [
    'style' => 'position:relative; width:100%; min-height:600px; background:#000;'
]);

if ($provider === 'zoom') {
    render_zoom_room($session, $is_teacher, $USER, $OUTPUT);
} elseif ($provider === '100ms') {
    render_100ms_room($session, $is_teacher, $USER);
} elseif ($provider === 'bigbluebutton') {
    render_bbb_room($session, $is_teacher, $USER);
} else {
    // Generic iframe fallback (Agora, etc.)
    render_iframe_room($session, $is_teacher);
}

echo html_writer::end_div(); // .livesessions-room-wrap

// Back button.
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/livesessions/view.php', ['id' => $id]),
        '← ' . get_string('backtosession', 'local_livesessions'),
        ['class' => 'btn btn-secondary mt-3']
    ),
    'mt-3'
);

echo $OUTPUT->footer();

// ---------------------------------------------------------------
// Zoom — Meeting SDK Component View
// ---------------------------------------------------------------

function render_zoom_room(\stdClass $session, bool $is_teacher, \stdClass $user, $OUTPUT): void {
    global $CFG;

    $sdk_key    = get_config('local_livesessions', 'zoom_sdk_key')    ?: '';
    $sdk_secret = get_config('local_livesessions', 'zoom_sdk_secret') ?: '';

    if (!$sdk_key || !$sdk_secret) {
        echo \html_writer::div(
            \html_writer::tag('p',
                '⚠️ Zoom Meeting SDK Key and Secret are not configured. ' .
                'Go to <a href="' . (new \moodle_url('/admin/settings.php',
                    ['section' => 'local_livesessions']))->out() .
                '">plugin settings</a> and add your Zoom SDK credentials.',
                ['style' => 'color:#fff; padding:40px; font-size:1.1em;']
            ),
            '',
            ['style' => 'background:#1a1a2e; min-height:600px; display:flex; align-items:center;']
        );
        return;
    }

    // Meeting number: strip spaces/dashes.
    $meeting_number = preg_replace('/[^0-9]/', '', $session->provider_meeting_id ?? '');
    $meeting_pwd    = $session->meeting_password ?? '';
    $role           = $is_teacher ? 1 : 0; // 1 = host, 0 = attendee
    $display_name   = fullname($user);
    $user_email     = $user->email;

    // Generate Zoom SDK signature (HS256 JWT).
    $signature = generate_zoom_sdk_signature($sdk_key, $sdk_secret, $meeting_number, $role);

    // Zoom Meeting SDK CDN (latest stable v3).
    $sdk_version = '3.1.5';
    $sdk_css_url = "https://source.zoom.us/{$sdk_version}/css/bootstrap.css";
    $sdk_css_react_url = "https://source.zoom.us/{$sdk_version}/css/react-select.css";
    $sdk_js_url  = "https://source.zoom.us/{$sdk_version}/VideoSDKZoomClient.min.js";

    echo \html_writer::tag('link', '', ['rel' => 'stylesheet', 'href' => $sdk_css_url]);
    echo \html_writer::tag('link', '', ['rel' => 'stylesheet', 'href' => $sdk_css_react_url]);

    // Extra CSS to make the SDK fill the page and look native.
    echo '<style>
    #zmmtg-root, .meeting-client, .meeting-client-inner { width:100% !important; }
    #zoom-meeting-container { width:100%; height:calc(100vh - 200px); min-height:600px;
        background:#1c1c1e; display:flex; align-items:center; justify-content:center; }
    #zoom-loading { color:#fff; font-size:1.2em; text-align:center; padding:40px; }
    </style>';

    echo '<div id="zoom-meeting-container">';
    echo '<div id="zoom-loading">⏳ Loading Zoom meeting, please wait...</div>';
    echo '</div>';

    // Config passed securely to JS.
    $config = json_encode([
        'sdkKey'        => $sdk_key,
        'signature'     => $signature,
        'meetingNumber' => $meeting_number,
        'password'      => $meeting_pwd,
        'userName'      => $display_name,
        'userEmail'     => $user_email,
        'role'          => $role,
    ]);

    echo "<script>
    var ZoomConfig = {$config};
    </script>";

    echo "<script src=\"{$sdk_js_url}\"></script>";

    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof ZoomMtgEmbedded === 'undefined') {
            document.getElementById('zoom-loading').innerHTML =
                '❌ Failed to load Zoom SDK. Check your internet connection.';
            return;
        }

        var container = document.getElementById('zoom-meeting-container');
        var client = ZoomMtgEmbedded.createClient();

        client.init({
            zoomAppRoot: container,
            language: 'en-US',
            customize: {
                video: {
                    isResizable: true,
                    viewSizes: { default: { width: Math.min(window.innerWidth - 40, 1200), height: 600 } }
                },
                meetingInfo: ['topic', 'host', 'mn', 'pwd', 'telPwd', 'invite', 'participant', 'dc', 'enctype'],
                toolbar: {
                    buttons: [{ text: 'Leave', className: 'CustomButton', event: 'leave' }]
                }
            }
        }).then(function() {
            document.getElementById('zoom-loading').style.display = 'none';
            return client.join({
                sdkKey:        ZoomConfig.sdkKey,
                signature:     ZoomConfig.signature,
                meetingNumber: ZoomConfig.meetingNumber,
                password:      ZoomConfig.password,
                userName:      ZoomConfig.userName,
                userEmail:     ZoomConfig.userEmail,
                tk:            '',
                zak:           ''
            });
        }).then(function() {
            console.log('Joined Zoom meeting successfully');
        }).catch(function(error) {
            console.error('Zoom join error:', error);
            document.getElementById('zoom-loading').innerHTML =
                '❌ Could not join meeting: ' + (error.reason || error.message || JSON.stringify(error));
        });
    });
    </script>";
}

// ---------------------------------------------------------------
// Zoom SDK Signature (HS256 JWT)
// ---------------------------------------------------------------

function generate_zoom_sdk_signature(string $sdk_key, string $sdk_secret,
        string $meeting_number, int $role): string {
    $iat = time() - 30;
    $exp = $iat + 60 * 60 * 2; // 2-hour token

    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode([
        'sdkKey'     => $sdk_key,
        'appKey'     => $sdk_key,
        'mn'         => $meeting_number,
        'role'       => $role,
        'iat'        => $iat,
        'exp'        => $exp,
        'tokenExp'   => $exp,
    ]));

    $sig = base64url_encode(hash_hmac('sha256', "{$header}.{$payload}", $sdk_secret, true));
    return "{$header}.{$payload}.{$sig}";
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// ---------------------------------------------------------------
// 100ms — Prebuilt iframe
// ---------------------------------------------------------------

function render_100ms_room(\stdClass $session, bool $is_teacher, \stdClass $user): void {
    // 100ms Prebuilt room URL is stored as join_url (for students) or host_url (for teacher).
    $room_url = $is_teacher
        ? ($session->host_url ?: $session->join_url)
        : $session->join_url;

    if (!$room_url) {
        echo \html_writer::tag('p', 'No 100ms room URL configured for this session.',
            ['style' => 'color:#fff; padding:40px;']);
        return;
    }

    // Append display name as query param if supported.
    $sep      = (strpos($room_url, '?') !== false) ? '&' : '?';
    $room_url .= $sep . 'name=' . urlencode(fullname($user));

    echo '<style>
    #ms100-frame { width:100%; height:calc(100vh - 200px); min-height:600px;
        border:none; background:#1c1c1e; display:block; }
    </style>';

    echo \html_writer::tag('iframe', '', [
        'id'              => 'ms100-frame',
        'src'             => $room_url,
        'allow'           => 'camera; microphone; display-capture; fullscreen; speaker-selection',
        'allowfullscreen' => 'true',
    ]);
}

// ---------------------------------------------------------------
// BigBlueButton — join API URL in iframe
// ---------------------------------------------------------------

function render_bbb_room(\stdClass $session, bool $is_teacher, \stdClass $user): void {
    // BBB join URL already includes the full API join link (with checksum).
    $join_url = $is_teacher
        ? ($session->host_url ?: $session->join_url)
        : $session->join_url;

    if (!$join_url) {
        echo \html_writer::tag('p', 'No BigBlueButton join URL configured.',
            ['style' => 'color:#fff; padding:40px;']);
        return;
    }

    // Append display name.
    $sep       = (strpos($join_url, '?') !== false) ? '&' : '?';
    $join_url .= $sep . 'fullName=' . urlencode(fullname($user));

    echo '<style>
    #bbb-frame { width:100%; height:calc(100vh - 200px); min-height:600px;
        border:none; background:#1c1c1e; display:block; }
    </style>';

    echo \html_writer::tag('iframe', '', [
        'id'              => 'bbb-frame',
        'src'             => $join_url,
        'allow'           => 'camera; microphone; display-capture; fullscreen; speaker-selection',
        'allowfullscreen' => 'true',
    ]);
}

// ---------------------------------------------------------------
// Generic iframe fallback (Agora, etc.)
// ---------------------------------------------------------------

function render_iframe_room(\stdClass $session, bool $is_teacher): void {
    $url = $is_teacher
        ? ($session->host_url ?: $session->join_url)
        : $session->join_url;

    if (!$url) {
        echo \html_writer::tag('p', 'No meeting URL configured for this session.',
            ['style' => 'color:#fff; padding:40px;']);
        return;
    }

    echo '<style>
    #generic-frame { width:100%; height:calc(100vh - 200px); min-height:600px;
        border:none; background:#1c1c1e; display:block; }
    </style>';

    echo \html_writer::tag('iframe', '', [
        'id'              => 'generic-frame',
        'src'             => $url,
        'allow'           => 'camera; microphone; display-capture; fullscreen; speaker-selection',
        'allowfullscreen' => 'true',
    ]);
}
