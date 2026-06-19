<?php
/**
 * Provider Webhook Endpoint — Feature 2.1 (complete implementation).
 *
 * Handles inbound HTTP POST from:
 *   Zoom        → /local/livesessions/webhook.php?provider=zoom
 *   100ms       → /local/livesessions/webhook.php?provider=100ms
 *   Agora       → /local/livesessions/webhook.php?provider=agora
 *   BigBlueButton→ /local/livesessions/webhook.php?provider=bigbluebutton&meetingID=X
 *
 * Security model:
 *   - Each provider has its own HMAC/signature method (see webhook_verifier).
 *   - Zoom also requires a URL-verification handshake (handled first).
 *   - All raw payloads logged to webhook_log regardless of outcome.
 *   - Invalid signatures return 401 and are NOT processed but ARE logged.
 *
 * This file intentionally does NO Moodle session work (NO_MOODLE_COOKIES).
 * All DB access goes through $DB directly.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require_once(dirname(__FILE__) . '/../../config.php');

use local_livesessions\recording_detector;
use local_livesessions\recording_manager;
use local_livesessions\join_manager;
use local_livesessions\session_manager;
use local_livesessions\webhook_verifier;
use local_livesessions\bunny_uploader;

// ---------------------------------------------------------------
// 1. Read raw input
// ---------------------------------------------------------------
$provider  = required_param('provider', PARAM_ALPHANUMEXT);
$raw_body  = file_get_contents('php://input');
$headers   = self_headers();
$query     = $_GET;

// ---------------------------------------------------------------
// 2. Zoom URL-verification handshake (must respond before signature check)
//    https://developers.zoom.us/docs/api/rest/webhook-reference/#validate-your-webhook-endpoint
// ---------------------------------------------------------------
if ($provider === 'zoom') {
    $payload_early = json_decode($raw_body, true) ?? [];
    if (($payload_early['event'] ?? '') === 'endpoint.url_validation') {
        $plain_token = $payload_early['payload']['plainToken'] ?? '';
        $secret      = get_config('local_livesessions', 'webhook_secret_zoom') ?: '';
        $hash        = hash_hmac('sha256', $plain_token, $secret);
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['plainToken' => $plain_token, 'encryptedToken' => $hash]);
        exit;
    }
}

// ---------------------------------------------------------------
// 3. Verify signature
// ---------------------------------------------------------------
$sig_valid = webhook_verifier::verify($provider, $raw_body, $headers, $query);

// ---------------------------------------------------------------
// 4. Parse payload
// ---------------------------------------------------------------
$payload = json_decode($raw_body, true) ?? [];

// ---------------------------------------------------------------
// 5. Determine event type
// ---------------------------------------------------------------
$event_type = extract_event_type($provider, $payload, $query);

// ---------------------------------------------------------------
// 6. Log the raw webhook (always, even if sig invalid)
// ---------------------------------------------------------------
$log_id = log_webhook($provider, $event_type, $payload, $headers, $raw_body, $sig_valid);

// ---------------------------------------------------------------
// 7. Reject bad signatures (after logging)
// ---------------------------------------------------------------
if (!$sig_valid) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid webhook signature']);
    exit;
}

// ---------------------------------------------------------------
// 8. Resolve sessionid
// ---------------------------------------------------------------
$sessionid = (int)($query['sessionid'] ?? 0);
if (!$sessionid) {
    $meeting_id = extract_meeting_id($provider, $payload, $query);
    if ($meeting_id) {
        $sess = $DB->get_record('livesessions_sessions', [
            'provider_meeting_id' => $meeting_id,
            'provider'            => $provider,
        ]);
        $sessionid = $sess ? (int)$sess->id : 0;
    }
}

// Update log with resolved sessionid.
if ($log_id && $sessionid) {
    $DB->set_field('livesessions_webhook_log', 'sessionid', $sessionid, ['id' => $log_id]);
}

// ---------------------------------------------------------------
// 9. Route event
// ---------------------------------------------------------------
$response = ['status' => 'ok', 'event' => $event_type, 'sessionid' => $sessionid];

// --- Participant joined ---
$join_events = ['participant.joined', 'peer.joined', 'peer:joined', 'PEER_JOINED',
                'USER_JOINED', 'user-joined', 'join'];
if (in_array($event_type, $join_events) && $sessionid) {
    $userid = resolve_user($provider, $payload);
    if ($userid) {
        $ts = extract_timestamp($provider, $payload);
        join_manager::record_leave($sessionid, $userid); // close any stale segment first
        \local_livesessions\attendance_manager::record_join($sessionid, $userid, $ts);
        $response['participant'] = 'join_recorded';
    } else {
        $response['participant'] = 'user_unmatched';
    }
}

// --- Participant left ---
$leave_events = ['participant.left', 'peer.left', 'peer:left', 'PEER_LEFT',
                 'USER_LEFT', 'user-left', 'leave'];
if (in_array($event_type, $leave_events) && $sessionid) {
    $userid = resolve_user($provider, $payload);
    if ($userid) {
        $ts = extract_timestamp($provider, $payload);
        join_manager::record_leave($sessionid, $userid, $ts);
        $response['participant'] = 'leave_recorded';
    }
}

// --- Meeting/session ended ---
$end_events = ['meeting.ended', 'room.ended', 'ROOM_ENDED', 'meeting_ended',
               'meeting-ended', 'session_ended'];
if (in_array($event_type, $end_events) && $sessionid) {
    try {
        session_manager::complete_session($sessionid);
        $response['session'] = 'completed';
    } catch (\moodle_exception $e) {
        $response['session'] = 'already_completed';
    }
}

// --- Recording ready ---
if (recording_detector::is_recording_event($provider, $event_type)) {
    $detected = recording_detector::parse($provider, $payload, $query);

    if ($detected && $sessionid) {
        $result = recording_manager::ingest($sessionid, $detected);

        // Update webhook_log with recording_id.
        if ($log_id) {
            $DB->set_field('livesessions_webhook_log', 'recordingid', $result->recording_id, ['id' => $log_id]);
        }

        $response['recording'] = [
            'recording_id' => $result->recording_id,
            'duplicate'    => $result->duplicate,
            'status'       => $result->status,
            'message'      => $result->message,
        ];
    } elseif (!$sessionid) {
        $response['recording'] = 'no_session_match';
    } else {
        $response['recording'] = 'parse_failed';
    }
}

// --- Bunny Stream encoding-complete callback ---
// Bunny fires a POST to /webhook.php?provider=bunny when encoding finishes.
// Payload: {"VideoGuid":"...","Status":4,"VideoLibraryId":...}
// We look up the recording by external_id (= video_guid) and mark it ready
// with fresh CDN URLs (no temp file needed — video is already on Bunny CDN).
if ($provider === 'bunny') {
    $video_guid     = $payload['VideoGuid']       ?? '';
    $bunny_status   = (int)($payload['Status']    ?? -1);
    $library_id     = (int)($payload['VideoLibraryId'] ?? 0);

    if ($video_guid) {
        $rec = $DB->get_record('livesessions_recordings', ['external_id' => $video_guid]);

        if ($rec) {
            try {
                $bunny = new bunny_uploader();
                // Update encoding_status and encoding_progress columns from this callback.
                $DB->set_field('livesessions_recordings', 'encoding_status',   $bunny_status, ['id' => $rec->id]);
                $DB->set_field('livesessions_recordings', 'encoding_progress', 100,           ['id' => $rec->id]);

                if ($bunny_status === bunny_uploader::ENC_FINISHED) {
                    // Fetch authoritative metadata from Bunny API.
                    $meta = $bunny->fetch_video_metadata($video_guid);

                    // Build fresh signed embed URL (4-hour TTL, no IP lock for webhook).
                    $embed_url = $bunny->build_signed_embed_url($video_guid);

                    $video_url     = $bunny->build_video_url($video_guid);
                    $thumbnail_url = $bunny->build_thumbnail_url($video_guid);

                    recording_manager::mark_ready(
                        (int)$rec->id,
                        (int)$rec->sessionid,
                        'bunny',
                        $video_guid,
                        $embed_url,
                        $video_url,
                        $thumbnail_url
                    );

                    // Grant access to attended students (if not already done by process_recording task).
                    $attendees = $DB->get_records('livesessions_attendance', [
                        'sessionid'         => $rec->sessionid,
                        'attendance_status' => 'attended',
                        'recording_access'  => 0,
                    ]);
                    foreach ($attendees as $a) {
                        $DB->set_field('livesessions_attendance', 'recording_access', 1, ['id' => $a->id]);
                    }

                    $response['bunny'] = [
                        'video_guid' => $video_guid,
                        'action'     => 'marked_ready',
                        'attendees_granted' => count($attendees),
                    ];

                } elseif ($bunny_status === bunny_uploader::ENC_FAILED) {
                    recording_manager::fail_with_retry(
                        (int)$rec->id, (int)$rec->sessionid, 'Bunny encoding failed (status=3)'
                    );
                    $response['bunny'] = ['video_guid' => $video_guid, 'action' => 'encoding_failed'];
                } else {
                    // Encoding still in progress — just update the DB status column.
                    $response['bunny'] = ['video_guid' => $video_guid, 'action' => 'progress_updated', 'status' => $bunny_status];
                }
            } catch (\Throwable $e) {
                $response['bunny'] = ['video_guid' => $video_guid, 'error' => $e->getMessage()];
            }
        } else {
            $response['bunny'] = ['video_guid' => $video_guid, 'action' => 'recording_not_found'];
        }
    } else {
        $response['bunny'] = ['action' => 'no_guid'];
    }
}

// Mark webhook as processed.
if ($log_id) {
    $DB->set_field('livesessions_webhook_log', 'processed', 1, ['id' => $log_id]);
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode($response);

// ---------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------

function extract_event_type(string $provider, array $p, array $q): string {
    if ($provider === 'zoom')          return $p['event']     ?? 'unknown';
    if ($provider === '100ms')         return $p['type']      ?? 'unknown';
    if ($provider === 'agora')         return $p['eventType'] ?? 'unknown';
    if ($provider === 'bigbluebutton') return $q['event']     ?? $p['event'] ?? 'meeting_ended';
    return $p['event'] ?? $p['type'] ?? 'unknown';
}

function extract_meeting_id(string $provider, array $p, array $q): ?string {
    if ($provider === 'zoom')          return (string)($p['payload']['object']['id'] ?? '') ?: null;
    if ($provider === '100ms')         return $p['data']['room_id'] ?? null;
    if ($provider === 'agora')         return $p['payload']['cname'] ?? null;
    if ($provider === 'bigbluebutton') return $q['meetingID'] ?? null;
    return null;
}

function resolve_user(string $provider, array $p): ?int {
    global $DB;

    $email    = null;
    $user_id  = null;
    $ext_name = null;

    switch ($provider) {
        case 'zoom':
            $part     = $p['payload']['object']['participant'] ?? [];
            $email    = $part['email']   ?? null;
            $ext_name = $part['user_name'] ?? null;
            // Zoom embeds Moodle user_id in the user_name if we built the join URL right.
            if (preg_match('/uid:(\d+)/', $ext_name ?? '', $m)) $user_id = (int)$m[1];
            break;
        case '100ms':
            $peer     = $p['data']   ?? [];
            $ext_name = $peer['peer_name'] ?? null;
            if (preg_match('/uid:(\d+)/', $ext_name ?? '', $m)) $user_id = (int)$m[1];
            break;
        case 'agora':
            $user_id  = (int)($p['payload']['uid'] ?? 0) ?: null;
            break;
        case 'bigbluebutton':
            $ext_name = $p['attendeeName'] ?? null;
            break;
    }

    if ($user_id && $DB->record_exists('user', ['id' => $user_id, 'deleted' => 0])) {
        return $user_id;
    }
    if ($email) {
        $u = $DB->get_record('user', ['email' => $email, 'deleted' => 0], 'id');
        if ($u) return (int)$u->id;
    }
    return null;
}

function extract_timestamp(string $provider, array $p): int {
    $candidates = [
        $p['payload']['object']['start_time'] ?? null,
        $p['data']['joined_at']               ?? null,
        $p['ts']                              ?? null,
        $p['timestamp']                       ?? null,
    ];
    foreach ($candidates as $v) {
        if (!$v) continue;
        $t = is_numeric($v) ? (int)$v : strtotime($v);
        if ($t > 1000000000) {
            return $t > 1e12 ? (int)($t / 1000) : $t;
        }
    }
    return time();
}

function log_webhook(string $provider, string $event_type, array $payload,
        array $headers, string $raw_body, bool $sig_valid): int {
    global $DB;
    $log = new stdClass();
    $log->provider    = $provider;
    $log->event_type  = $event_type;
    $log->sessionid   = null;
    $log->recordingid = null;
    $log->raw_body    = $raw_body;
    $log->headers     = json_encode($headers);
    $log->sig_valid   = $sig_valid ? 1 : 0;
    $log->processed   = 0;
    $log->timecreated = time();
    return (int)$DB->insert_record('livesessions_webhook_log', $log);
}

function self_headers(): array {
    $headers = [];
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = str_replace('_', '-', substr($k, 5));
            $headers[strtolower($name)] = $v;
        }
    }
    return $headers;
}
