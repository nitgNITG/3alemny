<?php
/**
 * Adhoc task: process a queued recording.
 *
 * Flow executed by this task:
 *   queued → downloading → uploading → ready
 *                 └─ on error ─→ fail_with_retry (re-queues with backoff)
 *
 * The actual upload to Bunny/VdoCipher is implemented in Feature 2.2 / 2.3.
 * This task handles the orchestration, retry budget, and status transitions.
 * Feature 2.2/2.3 will inject concrete uploader implementations.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions\task;

defined('MOODLE_INTERNAL') || die();

use local_livesessions\recording_manager;
use local_livesessions\attendance_manager;
use local_livesessions\recording_access_manager;
use local_livesessions\package_manager;

class process_recording extends \core\task\adhoc_task {

    public function get_name(): string {
        return get_string('task_processrecording', 'local_livesessions');
    }

    /**
     * Queue this task for a given session + recording.
     * Safe to call multiple times — Moodle deduplicates identical adhoc tasks.
     */
    public static function queue(int $sessionid, int $recordingid): void {
        $task = new self();
        $task->set_custom_data([
            'sessionid'   => $sessionid,
            'recordingid' => $recordingid,
        ]);
        \core\task\manager::queue_adhoc_task($task, true); // true = allow duplicates suppression
    }

    public function execute(): void {
        $data        = $this->get_custom_data();
        $sessionid   = (int)$data->sessionid;
        $recordingid = (int)$data->recordingid;

        mtrace("process_recording: sessionid={$sessionid} recordingid={$recordingid}");

        $rec = recording_manager::get_recording($recordingid);
        if (!$rec) {
            mtrace("  Recording {$recordingid} not found — aborting.");
            return;
        }

        // ---- Respect retry_after backoff ----
        if (!empty($rec->retry_after) && $rec->retry_after > time()) {
            $wait = $rec->retry_after - time();
            mtrace("  Backoff active — retry in {$wait}s. Task will be re-queued.");
            // Re-queue with delay (Moodle doesn't natively delay adhoc tasks, so
            // we simply exit and the scheduled retry task will re-queue it).
            return;
        }

        // ---- Skip if already in a terminal state ----
        if (in_array($rec->status, [recording_manager::STATUS_READY, recording_manager::STATUS_FAILED])) {
            mtrace("  Recording already in terminal state: {$rec->status} — nothing to do.");
            return;
        }

        try {
            // ---- Step 1: Downloading ----
            recording_manager::transition($recordingid, $sessionid,
                recording_manager::STATUS_DOWNLOADING, 0, 'task_started');
            mtrace("  Status → downloading");

            $temp_file = $this->download_recording($rec);
            mtrace("  Downloaded to: {$temp_file}");

            // ---- Step 2: Uploading ----
            recording_manager::transition($recordingid, $sessionid,
                recording_manager::STATUS_UPLOADING, 0, 'download_complete');
            mtrace("  Status → uploading");

            $upload_result = $this->upload_recording($rec, $temp_file);

            // ---- Step 3: Mark ready ----
            recording_manager::mark_ready(
                $recordingid,
                $sessionid,
                $upload_result['provider'],
                $upload_result['external_id'],
                $upload_result['embed_url'],
                $upload_result['video_url'],
                $upload_result['thumbnail_url'] ?? ''
            );
            mtrace("  Status → ready. Provider={$upload_result['provider']} id={$upload_result['external_id']}");

            // ---- Step 4: Apply recording access rules (Feature 4.1 / 4.2) ----
            $granted = \local_livesessions\recording_access_manager::apply_access_rules_for_session($sessionid);
            mtrace("  Granted recording access to {$granted} student(s) via access rules.");
            $this->grant_access_to_attendees($sessionid); // legacy fallback

            // ---- Step 5: Auto-create Moodle course activity (Feature 2.4) ----
            \local_livesessions\activity_creator::create_for_recording($recordingid);

            // ---- Step 6: Clean up temp file ----
            if ($temp_file && file_exists($temp_file)) {
                @unlink($temp_file);
            }

        } catch (\Throwable $e) {
            $error = $e->getMessage();
            mtrace("  ERROR: {$error}");

            // Clean up temp file on failure.
            if (!empty($temp_file) && file_exists($temp_file)) {
                @unlink($temp_file);
            }

            $retrying = recording_manager::fail_with_retry($recordingid, $sessionid, $error);
            mtrace($retrying
                ? "  Scheduled for retry (attempt " . ((int)$rec->retry_count + 1) . "/" . recording_manager::MAX_RETRIES . ")"
                : "  Max retries exhausted — recording marked FAILED."
            );

            throw $e; // Let Moodle task system log the exception too.
        }
    }

    // ---------------------------------------------------------------
    // Step 1: Download
    // ---------------------------------------------------------------

    /**
     * Download the recording from the source provider to a local temp file.
     * Returns the path to the temp file.
     *
     * For BBB (needs_api_poll = true): polls the BBB API to get the download URL first.
     * For others: downloads directly from download_url using download_token if present.
     *
     * Feature 2.2 / 2.3 replace this with streaming upload (no local temp file).
     * For now, download-then-upload is the safe approach.
     */
    private function download_recording(\stdClass $rec): string {
        global $CFG;

        // ---- BBB: poll API first to get actual download URL ----
        $extra = $rec->raw_payload ? (json_decode($rec->raw_payload, true)['extra'] ?? []) : [];
        if (!empty($extra['needs_bbb_api_poll'])) {
            $rec->download_url = $this->poll_bbb_recording($rec, $extra);
        }

        if (empty($rec->download_url)) {
            throw new \moodle_exception('nodownloadurl', 'local_livesessions',
                '', null, "Recording {$rec->id} has no download_url");
        }

        // ---- Build temp file path ----
        $temp_dir  = make_temp_directory('livesessions');
        $temp_file = $temp_dir . '/recording_' . $rec->id . '_' . time() . '.mp4';

        // ---- Download with curl ----
        $ch = curl_init($rec->download_url);
        $fp = fopen($temp_file, 'wb');

        $headers = ['Accept: video/mp4, */*'];
        if (!empty($rec->download_token)) {
            $headers[] = 'Authorization: Bearer ' . $rec->download_token;
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 3600, // 1 hour max
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $ok   = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $code !== 200) {
            @unlink($temp_file);
            throw new \RuntimeException(
                "Download failed: HTTP {$code}" . ($err ? " — {$err}" : '')
            );
        }

        $size = filesize($temp_file);
        if ($size < 1024) { // Less than 1 KB is suspicious.
            @unlink($temp_file);
            throw new \RuntimeException("Downloaded file is too small ({$size} bytes) — likely an error page");
        }

        return $temp_file;
    }

    /**
     * Poll BigBlueButton's /api/getRecordings to get the download URL for a recording.
     */
    private function poll_bbb_recording(\stdClass $rec, array $extra): string {
        $bbb_url    = get_config('local_livesessions', 'bbb_server_url');
        $bbb_secret = get_config('local_livesessions', 'webhook_secret_bigbluebutton');

        if (!$bbb_url || !$bbb_secret) {
            throw new \RuntimeException('BBB server URL or secret not configured');
        }

        $record_id  = $extra['record_id'] ?? '';
        $meeting_id = $extra['meeting_id'] ?? '';

        $params  = $record_id ? "recordID={$record_id}" : "meetingID=" . urlencode($meeting_id);
        $checksum = sha1('getRecordings' . $params . $bbb_secret);
        $url      = rtrim($bbb_url, '/') . "/api/getRecordings?{$params}&checksum={$checksum}";

        // Retry polling up to 3 times (BBB may still be processing).
        for ($i = 0; $i < 3; $i++) {
            $xml_str = file_get_contents($url);
            if (!$xml_str) {
                sleep(10);
                continue;
            }
            $xml = simplexml_load_string($xml_str);
            if (!$xml || (string)$xml->returncode !== 'SUCCESS') {
                sleep(10);
                continue;
            }
            foreach ($xml->recordings->recording as $r) {
                foreach ($r->playback->format as $fmt) {
                    if ((string)$fmt->type === 'video' || (string)$fmt->type === 'presentation') {
                        return (string)$fmt->url;
                    }
                }
            }
            sleep(30);
        }

        throw new \RuntimeException('BBB recording not yet available after polling');
    }

    // ---------------------------------------------------------------
    // Step 2: Upload (stub — Feature 2.2 / 2.3 provide real implementation)
    // ---------------------------------------------------------------

    /**
     * Upload the local temp file to the configured video CDN.
     * Returns array with provider, external_id, embed_url, video_url.
     *
     * Feature 2.2 → Bunny Stream uploader
     * Feature 2.3 → VdoCipher uploader
     * Both will be injected here via config 'upload_provider' = bunny|vdocipher.
     */
    private function upload_recording(\stdClass $rec, string $temp_file): array {
        $upload_provider = get_config('local_livesessions', 'upload_provider') ?: 'bunny';

        if ($upload_provider === 'bunny') {
            return $this->upload_to_bunny($rec, $temp_file);
        } elseif ($upload_provider === 'vdocipher') {
            return $this->upload_to_vdocipher($rec, $temp_file);
        } else {
            throw new \RuntimeException("Unknown upload provider: {$upload_provider}");
        }
    }

    /**
     * Bunny Stream upload — Feature 2.2 real implementation.
     *
     * Delegates to bunny_uploader which handles:
     *   create video → PUT upload (streaming) → poll encoding → fetch metadata → sign URL
     *
     * Returns the array contract expected by upload_recording().
     */
    private function upload_to_bunny(\stdClass $rec, string $temp_file): array {
        $uploader = new \local_livesessions\bunny_uploader();
        $result   = $uploader->upload($temp_file, $rec, $rec->id);
        return $result->to_array();
    }

    /**
     * VdoCipher upload — Feature 2.3 real implementation.
     *
     * Delegates to vdocipher_uploader which handles:
     *   create video → S3 multipart POST → poll encoding → store placeholder embed URL
     *
     * The actual OTP for playback is generated per-view in vdocipher_player.php.
     */
    private function upload_to_vdocipher(\stdClass $rec, string $temp_file): array {
        $uploader = new \local_livesessions\vdocipher_uploader();
        $result   = $uploader->upload($temp_file, $rec, $rec->id);
        return $result->to_array();
    }

    // ---------------------------------------------------------------
    // Step 4: Grant recording access to attendees
    // ---------------------------------------------------------------

    private function grant_access_to_attendees(int $sessionid): void {
        global $DB;

        $attendees = $DB->get_records('livesessions_attendance', [
            'sessionid'         => $sessionid,
            'attendance_status' => 'attended',
            'recording_access'  => 0, // not yet granted
        ]);

        foreach ($attendees as $a) {
            $DB->set_field('livesessions_attendance', 'recording_access', 1, ['id' => $a->id]);
        }

        if ($attendees) {
            mtrace("  Granted recording access to " . count($attendees) . " attended student(s).");
        }
    }
}
