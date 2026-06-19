<?php
/**
 * Recording Detector — normalises raw webhook payloads from all providers
 * into a single canonical DetectedRecording value object.
 *
 * Each provider sends different field names, date formats, and nesting.
 * This class is the single source of truth for "how do we read provider X".
 *
 * Supported providers:
 *   - zoom          (recording.completed webhook)
 *   - 100ms         (beam.recording.success / recording.success)
 *   - agora         (cloud recording callback)
 *   - bigbluebutton (meeting-ended + /api/v1/recordings)
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class recording_detector {

    // ---------------------------------------------------------------
    // Recording event names per provider
    // ---------------------------------------------------------------

    const RECORDING_EVENTS = [
        'zoom'          => ['recording.completed'],
        '100ms'         => ['beam.recording.success', 'recording.success', 'recording.enabled'],
        'agora'         => ['cloud_recording_upload_complete', 'cloud_recording_backup_complete'],
        'bigbluebutton' => ['meeting_ended', 'recording_ready'],
    ];

    // ---------------------------------------------------------------
    // Public: is this event a recording event?
    // ---------------------------------------------------------------

    public static function is_recording_event(string $provider, string $event_type): bool {
        return in_array($event_type, self::RECORDING_EVENTS[$provider] ?? []);
    }

    // ---------------------------------------------------------------
    // Public: parse and normalise
    // ---------------------------------------------------------------

    /**
     * Parse a raw provider webhook payload into a canonical DetectedRecording.
     * Returns null if this payload does not contain usable recording data.
     *
     * @param  string $provider
     * @param  array  $payload    Decoded JSON
     * @param  array  $query      $_GET params (used by BBB)
     * @return detected_recording|null
     */
    public static function parse(string $provider, array $payload, array $query = []): ?detected_recording {
        if ($provider === 'zoom')          return self::parse_zoom($payload);
        if ($provider === '100ms')         return self::parse_100ms($payload);
        if ($provider === 'agora')         return self::parse_agora($payload);
        if ($provider === 'bigbluebutton') return self::parse_bbb($payload, $query);
        return null;
    }

    // ---------------------------------------------------------------
    // Zoom
    // ---------------------------------------------------------------

    /**
     * Zoom recording.completed payload structure:
     * {
     *   "event": "recording.completed",
     *   "payload": {
     *     "object": {
     *       "id": "MEETING_ID",
     *       "uuid": "MEETING_UUID",
     *       "duration": 60,
     *       "recording_files": [
     *         {
     *           "id": "FILE_UUID",
     *           "recording_type": "shared_screen_with_speaker_view",
     *           "file_type": "MP4",
     *           "file_size": 123456789,
     *           "play_url": "https://zoom.us/rec/play/...",
     *           "download_url": "https://zoom.us/rec/download/...",
     *           "recording_start": "2024-01-15T10:00:00Z",
     *           "recording_end": "2024-01-15T11:00:00Z",
     *           "status": "completed"
     *         }
     *       ],
     *       "download_access_token": "TOKEN"
     *     }
     *   }
     * }
     */
    private static function parse_zoom(array $p): ?detected_recording {
        $obj   = $p['payload']['object'] ?? null;
        if (!$obj) return null;

        $files = $obj['recording_files'] ?? [];

        // Prefer the main MP4 with speaker view; fall back to first MP4.
        $file = self::pick_zoom_file($files, 'shared_screen_with_speaker_view')
             ?? self::pick_zoom_file($files, 'active_speaker')
             ?? self::pick_zoom_file($files, null); // first MP4

        if (!$file) return null;

        $duration = (int)($obj['duration'] ?? 0) * 60; // Zoom gives minutes
        if ($duration === 0 && !empty($file['recording_start']) && !empty($file['recording_end'])) {
            $duration = strtotime($file['recording_end']) - strtotime($file['recording_start']);
        }

        return new detected_recording(
            'zoom',
            (string)($obj['id'] ?? ''),
            $file['id'] ?? '',
            'zoom_' . ($file['id'] ?? md5(json_encode($file))),
            $file['download_url'] ?? '',
            $obj['download_access_token'] ?? '',
            $file['play_url'] ?? '',
            $duration,
            (int)($file['file_size'] ?? 0),
            $file['recording_start'] ? strtotime($file['recording_start']) : time(),
            $p,
            [
                'file_type'        => $file['file_type']       ?? 'MP4',
                'recording_type'   => $file['recording_type']  ?? '',
                'meeting_uuid'     => $obj['uuid']             ?? '',
                'topic'            => $obj['topic']            ?? '',
            ]
        );
    }

    private static function pick_zoom_file(array $files, ?string $type): ?array {
        foreach ($files as $f) {
            if (($f['file_type'] ?? '') !== 'MP4') continue;
            if ($f['status'] !== 'completed') continue;
            if ($type === null || ($f['recording_type'] ?? '') === $type) return $f;
        }
        return null;
    }

    // ---------------------------------------------------------------
    // 100ms
    // ---------------------------------------------------------------

    /**
     * 100ms beam.recording.success payload:
     * {
     *   "type": "beam.recording.success",
     *   "data": {
     *     "room_id": "ROOM_ID",
     *     "room_name": "...",
     *     "peer_id": "...",
     *     "recording_id": "REC_ID",
     *     "duration": 3540,
     *     "size": 524288000,
     *     "location": "s3://bucket/path/recording.mp4",
     *     "session_id": "...",
     *     "recording_presigned_url": "https://..."
     *   }
     * }
     */
    private static function parse_100ms(array $p): ?detected_recording {
        $data = $p['data'] ?? null;
        if (!$data) return null;

        $rec_id = $data['recording_id'] ?? $data['session_id'] ?? null;
        if (!$rec_id) return null;

        return new detected_recording(
            '100ms',
            (string)($data['room_id'] ?? ''),
            $rec_id,
            '100ms_' . $rec_id,
            $data['recording_presigned_url'] ?? $data['location'] ?? '',
            '',
            $data['recording_presigned_url'] ?? '',
            (int)($data['duration'] ?? 0),
            (int)($data['size'] ?? 0),
            time(),
            $p,
            [
                'room_name'   => $data['room_name']  ?? '',
                's3_location' => $data['location']   ?? '',
                'session_id'  => $data['session_id'] ?? '',
            ]
        );
    }

    // ---------------------------------------------------------------
    // Agora
    // ---------------------------------------------------------------

    /**
     * Agora cloud recording callback:
     * {
     *   "noticeLevel": 1,
     *   "eventType": "cloud_recording_upload_complete",
     *   "notifyMs": 1705312800000,
     *   "payload": {
     *     "cname": "CHANNEL_NAME",
     *     "uid": "123",
     *     "sid": "SESSION_ID",
     *     "resourceId": "RESOURCE_ID",
     *     "fileList": [
     *       {
     *         "filename": "recording.mp4",
     *         "trackType": "audio_and_video",
     *         "vid": "VIDEO_ID",
     *         "uid": "123",
     *         "mixedAllUser": true,
     *         "isPlayable": true,
     *         "sliceStartTime": 1705312200000
     *       }
     *     ],
     *     "uploadingStatus": "uploaded"
     *   }
     * }
     */
    private static function parse_agora(array $p): ?detected_recording {
        $payload = $p['payload'] ?? null;
        if (!$payload) return null;

        $files = $payload['fileList'] ?? [];
        // Pick the first playable video.
        $file  = null;
        foreach ($files as $f) {
            if (($f['isPlayable'] ?? false) && ($f['trackType'] ?? '') === 'audio_and_video') {
                $file = $f;
                break;
            }
        }
        if (!$file) $file = $files[0] ?? null;
        if (!$file) return null;

        $sid       = $payload['sid']        ?? $payload['resourceId'] ?? md5(json_encode($payload));
        $channel   = $payload['cname']      ?? '';

        return new detected_recording(
            'agora',
            $channel,
            $sid,
            'agora_' . $sid . '_' . ($file['vid'] ?? ''),
            '',
            '',
            '',
            0,
            0,
            isset($file['sliceStartTime']) ? (int)($file['sliceStartTime'] / 1000) : time(),
            $p,
            [
                'channel'     => $channel,
                'resource_id' => $payload['resourceId'] ?? '',
                'filename'    => $file['filename']      ?? '',
                'uid'         => $payload['uid']        ?? '',
                'vid'         => $file['vid']           ?? '',
            ]
        );
    }

    // ---------------------------------------------------------------
    // BigBlueButton
    // ---------------------------------------------------------------

    /**
     * BBB doesn't have a standard push webhook. Instead it calls a
     * meeting-ended callback URL, and you then poll /api/getRecordings.
     *
     * We support two modes:
     *   a) BBB calls our webhook with ?meetingID=X&event=meeting_ended
     *   b) BBB sends a recording_ready_callback with recordID
     */
    private static function parse_bbb(array $payload, array $query): ?detected_recording {
        // Mode B: recording_ready_callback
        if (!empty($payload['signed_parameters'])) {
            parse_str($payload['signed_parameters'], $params);
            $record_id = $params['record_id'] ?? null;
            if ($record_id) {
                return new detected_recording(
                    'bigbluebutton',
                    $params['meeting_id'] ?? '',
                    $record_id,
                    'bbb_' . $record_id,
                    '',
                    '',
                    '',
                    0,
                    0,
                    time(),
                    $payload,
                    ['needs_bbb_api_poll' => true, 'record_id' => $record_id]
                );
            }
        }

        // Mode A: meeting-ended GET callback
        $meeting_id = $query['meetingID'] ?? $payload['meetingID'] ?? null;
        if (!$meeting_id) return null;

        return new detected_recording(
            'bigbluebutton',
            $meeting_id,
            'bbb_meet_' . md5($meeting_id . time()),
            'bbb_meetend_' . $meeting_id,
            '',
            '',
            '',
            0,
            0,
            time(),
            $payload,
            ['needs_bbb_api_poll' => true, 'meeting_id' => $meeting_id]
        );
    }
}
