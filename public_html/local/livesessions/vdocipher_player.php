<?php
/**
 * VdoCipher recording player — Feature 2.3.
 *
 * Generates a fresh OTP + playbackInfo per request and renders the VdoCipher
 * iframe. The OTP expires after vdocipher_otp_ttl seconds (default 5 min),
 * so the embed URL cannot be replayed or shared.
 *
 * Access rules are identical to player.php (Bunny):
 *   - Must be logged in + enrolled.
 *   - recording_access = 1 required for students.
 *   - Admins / teachers bypass the access check.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\vdocipher_uploader;
use local_livesessions\device_manager;

$sessionid = required_param('sessionid', PARAM_INT);

require_login();

$session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST);
$context = context_course::instance($session->courseid);

require_login($course);

// ---- Load the ready recording ----
$rec = $DB->get_record('livesessions_recordings', [
    'sessionid' => $sessionid,
    'status'    => 'ready',
], '*');

// ---- Check access ----
$can_manage = has_capability('local/livesessions:manageSessions', context_system::instance());

if (!$can_manage) {
    $att = $DB->get_record('livesessions_attendance', [
        'sessionid' => $sessionid,
        'userid'    => $USER->id,
    ]);
    if (!$att || !$att->recording_access) {
        throw new \moodle_exception('recordingaccessdenied', 'local_livesessions',
            new moodle_url('/local/livesessions/view.php', ['id' => $sessionid]));
    }
}

// ---- Page setup ----
$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/vdocipher_player.php', ['sessionid' => $sessionid]);
$PAGE->set_title(format_string($session->title) . ' — ' . get_string('recording', 'local_livesessions'));
$PAGE->set_heading(format_string($session->title));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('recording', 'local_livesessions'));

if (!$rec) {
    echo $OUTPUT->notification(get_string('norecordingready', 'local_livesessions'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

// ---- Resolve video_id ----
$video_id = '';
if (!empty($rec->external_id)) {
    $video_id = $rec->external_id;
} elseif (!empty($rec->embed_url) && vdocipher_uploader::is_placeholder($rec->embed_url)) {
    $video_id = vdocipher_uploader::video_id_from_placeholder($rec->embed_url);
}

if (!$video_id) {
    echo $OUTPUT->notification(get_string('norecordingready', 'local_livesessions'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

// ---- Generate fresh OTP ----
$embed_url = '';
$error     = '';

try {
    $vdo         = new vdocipher_uploader();
    $annotations = device_manager::build_watermark_annotations($USER->id);
    $otp_data    = $vdo->generate_otp($video_id, 0, $annotations);
    $embed_url   = $vdo->build_embed_url($otp_data['otp'], $otp_data['playbackInfo']);
} catch (\Throwable $e) {
    $error = $e->getMessage();
    debugging('VdoCipher OTP error: ' . $error, DEBUG_DEVELOPER);
}

if ($error || !$embed_url) {
    echo $OUTPUT->notification(
        get_string('norecordingready', 'local_livesessions') . ' (OTP error)',
        'warning'
    );
} else {
    echo html_writer::div(
        html_writer::tag('iframe', '', [
            'src'             => $embed_url,
            'style'           => 'border:none; width:100%; aspect-ratio:16/9;',
            'allow'           => 'encrypted-media',
            'allowfullscreen' => 'true',
            'loading'         => 'lazy',
        ]),
        'livesessions-vdo-player-wrap',
        ['style' => 'max-width:960px; margin:0 auto;']
    );
}

echo html_writer::link(
    new moodle_url('/local/livesessions/view.php', ['id' => $sessionid]),
    '← ' . get_string('backtosession', 'local_livesessions'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();
