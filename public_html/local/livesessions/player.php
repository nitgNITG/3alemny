<?php
/**
 * Recording player page — Feature 2.2 (Bunny Stream).
 *
 * Generates a fresh per-request signed embed URL and renders it in an iframe.
 * URL is signed with the student's IP (if token auth + IP lock is enabled),
 * so the link cannot be shared or scraped from the page source.
 *
 * Access rules enforced here:
 *   1. User must be logged in and enrolled in the course.
 *   2. User must have recording_access = 1 in livesessions_attendance.
 *      (Admins / teachers with manageSession capability bypass this.)
 *   3. If credit_mode = 'credit', a package credit was deducted at join time;
 *      access is already recorded in recording_access column.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\bunny_uploader;
use local_livesessions\recording_manager;

$sessionid = required_param('sessionid', PARAM_INT);

require_login();

// ---- Load session and recording ----
$session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);
$context = context_course::instance($session->courseid);

require_login($DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST));

$rec = $DB->get_record('livesessions_recordings', [
    'sessionid' => $sessionid,
    'status'    => 'ready',
], '*');

// ---- Check access ----
$can_manage = has_capability('local/livesessions:manageSessions', context_system::instance());

if (!$can_manage) {
    // Student: must have recording_access granted.
    $att = $DB->get_record('livesessions_attendance', [
        'sessionid' => $sessionid,
        'userid'    => $USER->id,
    ]);

    if (!$att || !$att->recording_access) {
        throw new \moodle_exception('recordingaccessdenied', 'local_livesessions',
            new moodle_url('/local/livesessions/view.php', ['id' => $sessionid]));
    }
}

// ---- Set up page ----
$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/player.php', ['sessionid' => $sessionid]);
$PAGE->set_title(format_string($session->title) . ' — ' . get_string('recording', 'local_livesessions'));
$PAGE->set_heading(format_string($session->title));
$PAGE->set_pagelayout('incourse');

// ---- Generate signed embed URL ----
$embed_url = '';
$error     = '';

if (!$rec) {
    $error = get_string('norecordingready', 'local_livesessions');
} elseif ($rec->source_provider === 'bunny' || empty($rec->source_provider)) {
    // Bunny Stream — generate a fresh signed URL per request.
    if (!empty($rec->external_id)) {
        try {
            $client_ip = get_local_referer_ip();
            $bunny     = new bunny_uploader();
            $embed_url = $bunny->fresh_signed_url($rec->external_id, $client_ip);
        } catch (\Throwable $e) {
            // Fall back to stored embed_url (unsigned / long-TTL).
            $embed_url = $rec->embed_url ?? '';
            debugging('bunny_uploader::fresh_signed_url failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    } else {
        $embed_url = $rec->embed_url ?? '';
    }
} elseif ($rec->source_provider === 'vdocipher') {
    // VdoCipher — redirect to the dedicated OTP player page.
    redirect(new moodle_url('/local/livesessions/vdocipher_player.php', ['sessionid' => $sessionid]));
} else {
    // Other providers — use stored embed_url as-is.
    $embed_url = $rec->embed_url ?? '';
}

// ---- Output ----
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('recording', 'local_livesessions'));

if ($error) {
    echo $OUTPUT->notification($error, 'warning');
} elseif (!$embed_url) {
    echo $OUTPUT->notification(get_string('norecordingready', 'local_livesessions'), 'warning');
} else {
    // Bunny embed URL with recommended parameters:
    //   autoplay=false  — never force autoplay
    //   preload=false   — save bandwidth
    //   responsive=true — fills container width
    $iframe_src = $embed_url
        . (strpos($embed_url, '?') !== false ? '&' : '?')
        . 'autoplay=false&preload=false&responsive=true';

    echo html_writer::div(
        html_writer::tag('iframe', '', [
            'src'             => $iframe_src,
            'loading'         => 'lazy',
            'style'           => 'border:none; width:100%; aspect-ratio:16/9;',
            'allow'           => 'accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture',
            'allowfullscreen' => 'true',
        ]),
        'livesessions-player-wrap',
        ['style' => 'max-width:960px; margin:0 auto;']
    );
}

// Back link.
echo html_writer::link(
    new moodle_url('/local/livesessions/view.php', ['id' => $sessionid]),
    '← ' . get_string('backtosession', 'local_livesessions'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();

// ---------------------------------------------------------------
// Helper
// ---------------------------------------------------------------

/**
 * Return the client's best-guess real IP address for Bunny token locking.
 * Prefers X-Forwarded-For first real IP; falls back to REMOTE_ADDR.
 */
function get_local_referer_ip(): string {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded) {
        $parts = array_map('trim', explode(',', $forwarded));
        if (filter_var($parts[0], FILTER_VALIDATE_IP)) {
            return $parts[0];
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}
