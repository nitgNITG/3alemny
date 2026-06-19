<?php
/**
 * Recording Manager — status machine and persistence layer for recordings.
 *
 * Status flow:
 *
 *   detected  ──► queued  ──► downloading  ──► uploading  ──► ready
 *                   │              │               │
 *                   └──────────────┴───────────────┴──► failed
 *                                                           │
 *                                              (retry_count < MAX_RETRIES)
 *                                                           │
 *                                                        queued (retry)
 *
 * Idempotency:
 *   - Every recording is keyed by `idempotency_key` (provider + file ID).
 *   - A second webhook for the same recording is a no-op.
 *   - Re-running the queue task is safe at any status.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class recording_manager {

    // ---------------------------------------------------------------
    // Status constants
    // ---------------------------------------------------------------

    const STATUS_DETECTED    = 'detected';    // webhook received, not yet queued
    const STATUS_QUEUED      = 'queued';      // in Moodle adhoc queue
    const STATUS_DOWNLOADING = 'downloading'; // task is fetching the file
    const STATUS_UPLOADING   = 'uploading';   // task is pushing to Bunny/VdoCipher
    const STATUS_READY       = 'ready';       // fully available for playback
    const STATUS_FAILED      = 'failed';      // exhausted retries

    const MAX_RETRIES        = 5;
    const RETRY_BACKOFF      = [300, 900, 1800, 3600, 7200]; // seconds per attempt

    // ---------------------------------------------------------------
    // Public: ingest a detected recording (called by webhook.php)
    // ---------------------------------------------------------------

    /**
     * Accept a detected recording, persist it, and queue the processing task.
     * Idempotent — calling twice with the same idempotency_key is safe.
     *
     * @param  int                $sessionid
     * @param  detected_recording $detected
     * @return ingest_result
     */
    public static function ingest(int $sessionid, detected_recording $detected): ingest_result {
        global $DB;

        // ---- Idempotency check ----
        $existing = $DB->get_record('livesessions_recordings',
            ['idempotency_key' => $detected->idempotency_key]);

        if ($existing) {
            return new ingest_result(
                (int)$existing->id,
                true,
                $existing->status,
                'Recording already ingested (idempotency_key match)'
            );
        }

        // ---- Persist ----
        $rec = new \stdClass();
        $rec->sessionid        = $sessionid;
        $rec->provider         = 'pending';          // upload target set later by admin config
        $rec->source_provider  = $detected->provider;
        $rec->external_id      = $detected->external_id;
        $rec->idempotency_key  = $detected->idempotency_key;
        $rec->download_url     = $detected->download_url;
        $rec->download_token   = $detected->download_token;
        $rec->video_url        = $detected->play_url;
        $rec->raw_payload      = json_encode($detected->raw_payload);
        $rec->duration_seconds = $detected->duration_seconds;
        $rec->filesize_bytes   = $detected->filesize_bytes;
        $rec->status           = self::STATUS_DETECTED;
        $rec->retry_count      = 0;
        $rec->retry_after      = null;
        $rec->last_error       = null;
        $rec->timecreated      = time();
        $rec->timemodified     = time();

        $rec_id = $DB->insert_record('livesessions_recordings', $rec);

        // ---- Write status log ----
        self::write_log($rec_id, $sessionid, '', self::STATUS_DETECTED, 0, 'webhook_received',
            $detected->provider . ' meeting=' . $detected->meeting_id);

        // ---- Mark session recording_status = processing ----
        $DB->set_field('livesessions_sessions', 'recording_status', 'processing',
            ['id' => $sessionid]);
        $DB->set_field('livesessions_sessions', 'timemodified', time(),
            ['id' => $sessionid]);

        // ---- Queue processing task ----
        self::transition($rec_id, $sessionid, self::STATUS_QUEUED, 0, 'queued_after_detection');
        \local_livesessions\task\process_recording::queue($sessionid, $rec_id);

        return new ingest_result(
            $rec_id,
            false,
            self::STATUS_QUEUED,
            'Recording detected and queued for processing'
        );
    }

    // ---------------------------------------------------------------
    // Public: status transitions
    // ---------------------------------------------------------------

    /**
     * Move a recording to a new status, write audit log.
     *
     * @param  int    $recording_id
     * @param  int    $sessionid
     * @param  string $new_status
     * @param  int    $changed_by  0 = system
     * @param  string $reason
     * @param  string $detail
     */
    public static function transition(
        int    $recording_id,
        int    $sessionid,
        string $new_status,
        int    $changed_by = 0,
        string $reason     = '',
        string $detail     = ''
    ): void {
        global $DB;

        $rec = $DB->get_record('livesessions_recordings', ['id' => $recording_id]);
        $prev = $rec ? $rec->status : '';

        $DB->set_field('livesessions_recordings', 'status',       $new_status, ['id' => $recording_id]);
        $DB->set_field('livesessions_recordings', 'timemodified', time(),      ['id' => $recording_id]);

        self::write_log($recording_id, $sessionid, $prev, $new_status, $changed_by, $reason, $detail);
    }

    /**
     * Mark a recording as failed, schedule retry if budget allows.
     *
     * @param  int    $recording_id
     * @param  int    $sessionid
     * @param  string $error_message
     * @return bool   true = retry queued, false = exhausted
     */
    public static function fail_with_retry(int $recording_id, int $sessionid, string $error_message): bool {
        global $DB;

        $rec = $DB->get_record('livesessions_recordings', ['id' => $recording_id], '*', MUST_EXIST);
        $attempt = (int)$rec->retry_count;

        $update = new \stdClass();
        $update->id         = $recording_id;
        $update->last_error = $error_message;
        $update->timemodified = time();

        if ($attempt >= self::MAX_RETRIES) {
            // Exhausted — mark permanently failed.
            $update->status = self::STATUS_FAILED;
            $DB->update_record('livesessions_recordings', $update);

            self::write_log($recording_id, $sessionid, $rec->status, self::STATUS_FAILED,
                0, 'retry_exhausted', $error_message);

            // Escalate: mark session recording_status as 'failed'.
            $DB->set_field('livesessions_sessions', 'recording_status', 'failed',
                ['id' => $sessionid]);

            return false;
        }

        // Schedule retry with exponential backoff.
        $backoff = self::RETRY_BACKOFF[$attempt] ?? 7200;
        $update->status      = self::STATUS_QUEUED;
        $update->retry_count = $attempt + 1;
        $update->retry_after = time() + $backoff;
        $DB->update_record('livesessions_recordings', $update);

        self::write_log($recording_id, $sessionid, $rec->status, self::STATUS_QUEUED,
            0, 'retry_scheduled',
            "Attempt {$update->retry_count}/" . self::MAX_RETRIES . " — retry in {$backoff}s. Error: {$error_message}");

        // Re-queue the adhoc task.
        \local_livesessions\task\process_recording::queue($sessionid, $recording_id);

        return true;
    }

    /**
     * Mark a recording as successfully uploaded and ready.
     *
     * @param  int    $recording_id
     * @param  int    $sessionid
     * @param  string $upload_provider  bunny | vdocipher
     * @param  string $external_id     Provider video ID post-upload
     * @param  string $embed_url
     * @param  string $video_url
     * @param  string $thumbnail_url
     */
    public static function mark_ready(
        int    $recording_id,
        int    $sessionid,
        string $upload_provider,
        string $external_id,
        string $embed_url,
        string $video_url,
        string $thumbnail_url = ''
    ): void {
        global $DB;

        $rec = $DB->get_record('livesessions_recordings', ['id' => $recording_id], '*', MUST_EXIST);

        $update = new \stdClass();
        $update->id            = $recording_id;
        $update->provider      = $upload_provider;
        $update->external_id   = $external_id;
        $update->embed_url     = $embed_url;
        $update->video_url     = $video_url;
        $update->thumbnail_url = $thumbnail_url;
        $update->status        = self::STATUS_READY;
        $update->timemodified  = time();
        $DB->update_record('livesessions_recordings', $update);

        $DB->set_field('livesessions_sessions', 'recording_status', 'uploaded', ['id' => $sessionid]);
        $DB->set_field('livesessions_sessions', 'timemodified',      time(),     ['id' => $sessionid]);

        self::write_log($recording_id, $sessionid, $rec->status, self::STATUS_READY,
            0, 'upload_complete', "Uploaded to {$upload_provider}, external_id={$external_id}");
    }

    // ---------------------------------------------------------------
    // Public: read helpers
    // ---------------------------------------------------------------

    public static function get_recording(int $recording_id): ?\stdClass {
        global $DB;
        return $DB->get_record('livesessions_recordings', ['id' => $recording_id]) ?: null;
    }

    public static function get_session_recording(int $sessionid): ?\stdClass {
        global $DB;
        // Return the most recent non-failed recording for the session.
        return $DB->get_record_sql(
            "SELECT * FROM {livesessions_recordings}
              WHERE sessionid = :sid AND status != 'failed'
           ORDER BY timecreated DESC LIMIT 1",
            ['sid' => $sessionid]
        ) ?: null;
    }

    /**
     * Get all recordings that are queued and past their retry_after time.
     * Called by the retry scheduled task.
     */
    public static function get_queued_for_processing(): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT * FROM {livesessions_recordings}
              WHERE status = :queued
                AND (retry_after IS NULL OR retry_after <= :now)
           ORDER BY timecreated ASC",
            ['queued' => self::STATUS_QUEUED, 'now' => time()]
        ));
    }

    // ---------------------------------------------------------------
    // Private: write audit log
    // ---------------------------------------------------------------

    private static function write_log(
        int    $recording_id,
        int    $sessionid,
        string $prev_status,
        string $new_status,
        int    $changed_by,
        string $reason,
        string $detail = ''
    ): void {
        global $DB;
        $log = new \stdClass();
        $log->recordingid  = $recording_id;
        $log->sessionid    = $sessionid;
        $log->prev_status  = $prev_status;
        $log->new_status   = $new_status;
        $log->changed_by   = $changed_by;
        $log->reason       = $reason;
        $log->detail       = $detail;
        $log->timecreated  = time();
        $DB->insert_record('livesessions_rec_log', $log);
    }
}
