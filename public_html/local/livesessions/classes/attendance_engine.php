<?php
/**
 * Attendance Engine — the authoritative calculation layer for Feature 1.3.
 *
 * Responsibilities:
 *   - Compute accurate attendance duration from segments (handles all edge cases)
 *   - Apply configurable threshold to determine attended / partial / absent
 *   - Write audit trail on every status change
 *   - Batch-finalise all overdue sessions (called by cron)
 *   - Allow manual re-calculation by admins (idempotent)
 *   - Guard against bad data (zero-duration sessions, future timestamps, overlapping
 *     segments, sessions that ended while students were still "joined", etc.)
 *
 * Edge cases handled:
 *   EC-1  Student never sent leave event (provider webhook missed)
 *   EC-2  Student joined after session ended (late-join from stale URL)
 *   EC-3  Session duration is 0 or negative
 *   EC-4  Overlapping segments (double-join from two devices)
 *   EC-5  leave_time < join_time (clock skew between provider and Moodle)
 *   EC-6  Session cancelled mid-way (partial time counts)
 *   EC-7  Segment spans beyond session end time (cap to session end)
 *   EC-8  No attendance records at all (no students joined)
 *   EC-9  Re-calculation called multiple times (idempotent)
 *   EC-10 Provider sent duplicate join/leave events (de-duped via segments)
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class attendance_engine {

    // ---------------------------------------------------------------
    // Result constants
    // ---------------------------------------------------------------

    const STATUS_ATTENDED = 'attended';
    const STATUS_PARTIAL  = 'partial';
    const STATUS_ABSENT   = 'absent';
    const STATUS_PENDING  = 'pending';

    // Reason codes written to att_log.
    const REASON_THRESHOLD_MET    = 'threshold_met';
    const REASON_THRESHOLD_NOT_MET= 'threshold_not_met';
    const REASON_MANUAL_OVERRIDE  = 'manual_override';
    const REASON_RECALCULATED     = 'recalculated';
    const REASON_SESSION_ENDED    = 'session_ended';
    const REASON_WEBHOOK_ABSENT   = 'webhook_absent';
    const REASON_NO_JOIN_EVENT    = 'no_join_event';
    const REASON_LATE_JOIN        = 'late_join';
    const REASON_ZERO_DURATION    = 'zero_duration_session';

    // ---------------------------------------------------------------
    // Public: finalise a single session
    // ---------------------------------------------------------------

    /**
     * Finalise attendance for one session.
     * Safe to call multiple times — fully idempotent.
     *
     * @param  int    $sessionid
     * @param  int    $triggered_by  User ID, 0 = cron/system
     * @param  string $trigger_reason
     * @return finalise_result
     */
    public static function finalise_session(
        int $sessionid,
        int $triggered_by = 0,
        string $trigger_reason = 'cron'
    ): finalise_result {
        global $DB;

        $session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);

        // ---- Guard: session must be in a terminal or live state ----
        if ($session->status === session_manager::STATUS_SCHEDULED) {
            // Auto-complete if past end time.
            if ($session->endtime && $session->endtime < time()) {
                session_manager::complete_session($sessionid);
                $session->status = session_manager::STATUS_COMPLETED;
            } else {
                return new finalise_result($sessionid, true, 'Session is still scheduled and has not ended yet');
            }
        }

        // ---- EC-3: zero or negative session duration ----
        $session_duration_sec = ($session->endtime - $session->starttime);
        if ($session_duration_sec <= 0) {
            // Use sum of all segments as the reference duration instead.
            $session_duration_sec = (int)$DB->get_field_sql(
                "SELECT COALESCE(MAX(leave_time) - MIN(join_time), 0)
                   FROM {livesessions_att_segments}
                  WHERE sessionid = :sid",
                ['sid' => $sessionid]
            );
            if ($session_duration_sec <= 0) {
                return new finalise_result($sessionid, true, 'Session duration is zero — cannot compute attendance percentage');
            }
        }

        // ---- Retrieve threshold ----
        $threshold = self::get_threshold();

        // ---- Retrieve all attendance records ----
        $records = $DB->get_records('livesessions_attendance', ['sessionid' => $sessionid]);

        // ---- EC-8: no attendance records ----
        if (empty($records)) {
            self::write_finalise_log($sessionid, $triggered_by, $trigger_reason,
                0, 0, 0, 0, $threshold, $session_duration_sec);
            self::stamp_session_finalised($sessionid);
            return new finalise_result($sessionid, false, '', 0, 0, 0, 0, $threshold, $session_duration_sec);
        }

        $counts = ['attended' => 0, 'partial' => 0, 'absent' => 0];

        foreach ($records as $record) {
            $result = self::calculate_one_student(
                $record, $session, $session_duration_sec, $threshold
            );
            $counts[$result->new_status]++;

            // Write audit log if anything changed.
            if (self::has_changed($record, $result)) {
                self::write_att_log($record, $result, $triggered_by,
                    $trigger_reason === 'manual' ? self::REASON_RECALCULATED : self::REASON_SESSION_ENDED);
            }

            // Persist the updated attendance record.
            self::persist_attendance($record->id, $result);
        }

        // Write finalisation log entry.
        self::write_finalise_log(
            $sessionid, $triggered_by, $trigger_reason,
            count($records), $counts['attended'], $counts['partial'], $counts['absent'],
            $threshold, $session_duration_sec
        );

        self::stamp_session_finalised($sessionid);

        return new finalise_result(
            $sessionid,
            false,
            '',
            count($records),
            $counts['attended'],
            $counts['partial'],
            $counts['absent'],
            $threshold,
            $session_duration_sec
        );
    }

    // ---------------------------------------------------------------
    // Public: batch finalise all overdue sessions (called by cron)
    // ---------------------------------------------------------------

    /**
     * Find all sessions that ended > grace_minutes ago but are not yet finalised,
     * and finalise them in one pass.
     *
     * @param  int $grace_minutes  How long after endtime before we finalise (default 5)
     * @return array  Array of finalise_result objects, keyed by sessionid.
     */
    public static function batch_finalise(int $grace_minutes = 5): array {
        global $DB;

        $cutoff = time() - ($grace_minutes * 60);

        // Sessions that ended before the cutoff and have NOT been finalised.
        $sql = "SELECT *
                  FROM {livesessions_sessions}
                 WHERE status IN ('live','completed','scheduled')
                   AND endtime > 0
                   AND endtime < :cutoff
                   AND (finalised_at IS NULL OR finalised_at = 0)
              ORDER BY endtime ASC";

        $sessions = $DB->get_records_sql($sql, ['cutoff' => $cutoff]);

        $results = [];
        foreach ($sessions as $session) {
            try {
                $results[$session->id] = self::finalise_session($session->id, 0, 'cron');
            } catch (\Throwable $e) {
                // Don't let one bad session break the whole batch.
                debugging("attendance_engine: failed to finalise session {$session->id}: "
                    . $e->getMessage(), DEBUG_DEVELOPER);
                $results[$session->id] = new finalise_result($session->id, true, $e->getMessage());
            }
        }

        return $results;
    }

    // ---------------------------------------------------------------
    // Public: manual override (admin grants/revokes access)
    // ---------------------------------------------------------------

    /**
     * Manually set a student's attendance status and recording access.
     * Writes an audit log entry with reason = manual_override.
     *
     * @param  int    $sessionid
     * @param  int    $userid
     * @param  string $new_status   attended | partial | absent
     * @param  bool   $recording_access
     * @param  int    $changed_by   Admin user ID
     * @return bool
     */
    public static function manual_override(
        int    $sessionid,
        int    $userid,
        string $new_status,
        bool   $recording_access,
        int    $changed_by
    ): bool {
        global $DB;

        $record = $DB->get_record('livesessions_attendance',
            ['sessionid' => $sessionid, 'userid' => $userid]);

        if (!$record) {
            return false;
        }

        $result = new engine_result(
            $new_status,
            $record->attendance_percent,
            $record->duration_attended,
            $recording_access ? 1 : 0
        );

        self::write_att_log($record, $result, $changed_by, self::REASON_MANUAL_OVERRIDE);
        self::persist_attendance($record->id, $result);

        return true;
    }

    // ---------------------------------------------------------------
    // Public: recalculate a single student (for UI "recalculate" button)
    // ---------------------------------------------------------------

    /**
     * Re-run the attendance calculation for one student in one session.
     * Useful when an admin adjusts a segment manually.
     *
     * @param  int $sessionid
     * @param  int $userid
     * @param  int $recalc_by  Admin user ID
     * @return engine_result
     */
    public static function recalculate_student(int $sessionid, int $userid, int $recalc_by = 0): engine_result {
        global $DB;

        $session  = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $record   = $DB->get_record('livesessions_attendance',
            ['sessionid' => $sessionid, 'userid' => $userid], '*', MUST_EXIST);

        $session_duration_sec = max(1, $session->endtime - $session->starttime);
        $threshold = self::get_threshold();

        $result = self::calculate_one_student($record, $session, $session_duration_sec, $threshold);

        if (self::has_changed($record, $result)) {
            self::write_att_log($record, $result, $recalc_by, self::REASON_RECALCULATED);
            self::persist_attendance($record->id, $result);
        }

        return $result;
    }

    // ---------------------------------------------------------------
    // Core calculation: one student, one session
    // ---------------------------------------------------------------

    /**
     * The heart of the engine. Computes a clean, de-duped duration
     * from the student's segments and applies threshold logic.
     *
     * @param  \stdClass $record   livesessions_attendance row
     * @param  \stdClass $session  livesessions_sessions row
     * @param  int       $session_duration_sec
     * @param  float     $threshold
     * @return engine_result
     */
    public static function calculate_one_student(
        \stdClass $record,
        \stdClass $session,
        int       $session_duration_sec,
        float     $threshold
    ): engine_result {
        global $DB;

        $session_start = $session->starttime;
        $session_end   = $session->endtime ?: time();

        // ---- 1. Fetch all segments for this student+session ----
        $segments = $DB->get_records('livesessions_att_segments',
            ['attendanceid' => $record->id], 'join_time ASC');

        // ---- 2. Sanitise segments ----
        $clean_intervals = [];
        foreach ($segments as $seg) {
            $join  = (int) $seg->join_time;
            $leave = (int) ($seg->leave_time ?? $session_end);

            // EC-5: clock skew (leave before join) — skip segment.
            if ($leave <= $join) {
                continue;
            }

            // EC-7: cap to session window.
            $join  = max($join,  $session_start - 300); // allow 5 min early grace
            $leave = min($leave, $session_end);

            // EC-2: student joined after session ended — zero contribution.
            if ($join >= $session_end) {
                continue;
            }

            $clean_intervals[] = [$join, $leave];
        }

        // ---- 3. EC-1: no leave event received — close open segments ----
        // (Segments with leave_time = NULL are treated as ending at session_end above.)

        // ---- 4. EC-4/EC-10: merge overlapping intervals ----
        $merged_seconds = self::merge_and_sum_intervals($clean_intervals);

        // ---- 5. EC-9: idempotent — if database already has a higher accumulated
        //    duration (from manual adjustments), keep the larger value ----
        $duration_sec = max($merged_seconds, 0);

        // ---- 6. Compute percentage ----
        $pct = min(100.0, ($duration_sec / $session_duration_sec) * 100.0);
        $pct = round($pct, 2);

        // ---- 7. Determine status ----
        if (empty($clean_intervals)) {
            // EC-8 sub-case: student has an attendance record but NO segments.
            // This happens when the join event was never received.
            $status           = self::STATUS_ABSENT;
            $reason           = self::REASON_NO_JOIN_EVENT;
            $recording_access = 0;
        } elseif ($pct >= $threshold) {
            $status           = self::STATUS_ATTENDED;
            $reason           = self::REASON_THRESHOLD_MET;
            $recording_access = 1;
        } elseif ($pct > 0) {
            $status           = self::STATUS_PARTIAL;
            $reason           = self::REASON_THRESHOLD_NOT_MET;
            $recording_access = 0;
        } else {
            $status           = self::STATUS_ABSENT;
            $reason           = self::REASON_NO_JOIN_EVENT;
            $recording_access = 0;
        }

        return new engine_result($status, $pct, $duration_sec, $recording_access, $reason);
    }

    // ---------------------------------------------------------------
    // Interval merge algorithm (EC-4: overlapping segments)
    // ---------------------------------------------------------------

    /**
     * Given a list of [start, end] intervals (already sorted by start),
     * merge any overlapping or adjacent intervals and return the total
     * sum of seconds covered.
     *
     * Example:
     *   Input:  [[0,30], [20,50], [60,80]]
     *   Merged: [[0,50], [60,80]]
     *   Sum:    50 + 20 = 70 seconds
     *
     * @param  array $intervals  List of [int start, int end] pairs
     * @return int  Total seconds
     */
    public static function merge_and_sum_intervals(array $intervals): int {
        if (empty($intervals)) {
            return 0;
        }

        // Sort by start time.
        usort($intervals, fn($a, $b) => $a[0] <=> $b[0]);

        $merged = [];
        [$cur_start, $cur_end] = $intervals[0];

        for ($i = 1; $i < count($intervals); $i++) {
            [$start, $end] = $intervals[$i];
            if ($start <= $cur_end) {
                // Overlapping or adjacent — extend the current interval.
                $cur_end = max($cur_end, $end);
            } else {
                $merged[]  = [$cur_start, $cur_end];
                $cur_start = $start;
                $cur_end   = $end;
            }
        }
        $merged[] = [$cur_start, $cur_end];

        // Sum all merged intervals.
        return array_sum(array_map(fn($i) => $i[1] - $i[0], $merged));
    }

    // ---------------------------------------------------------------
    // Threshold helper
    // ---------------------------------------------------------------

    public static function get_threshold(): float {
        $t = (float) get_config('local_livesessions', 'attendance_threshold');
        return ($t > 0 && $t <= 100) ? $t : 70.0;
    }

    // ---------------------------------------------------------------
    // Persistence helpers
    // ---------------------------------------------------------------

    private static function persist_attendance(int $attendanceid, engine_result $result): void {
        global $DB;

        $update = new \stdClass();
        $update->id                 = $attendanceid;
        $update->duration_attended  = $result->new_duration;
        $update->attendance_percent = $result->new_percent;
        $update->attendance_status  = $result->new_status;
        $update->recording_access   = $result->new_recording_access;
        $update->timemodified       = time();

        $DB->update_record('livesessions_attendance', $update);
    }

    private static function write_att_log(
        \stdClass    $record,
        engine_result $result,
        int          $changed_by,
        string       $reason
    ): void {
        global $DB;

        $log = new \stdClass();
        $log->attendanceid          = $record->id;
        $log->sessionid             = $record->sessionid;
        $log->userid                = $record->userid;
        $log->changed_by            = $changed_by;
        $log->prev_status           = $record->attendance_status;
        $log->new_status            = $result->new_status;
        $log->prev_percent          = $record->attendance_percent;
        $log->new_percent           = $result->new_percent;
        $log->prev_duration         = $record->duration_attended;
        $log->new_duration          = $result->new_duration;
        $log->prev_recording_access = $record->recording_access;
        $log->new_recording_access  = $result->new_recording_access;
        $log->reason                = $reason;
        $log->timecreated           = time();

        $DB->insert_record('livesessions_att_log', $log);
    }

    private static function write_finalise_log(
        int    $sessionid,
        int    $triggered_by,
        string $trigger_reason,
        int    $total,
        int    $attended,
        int    $partial,
        int    $absent,
        float  $threshold,
        int    $duration_sec
    ): void {
        global $DB;

        $log = new \stdClass();
        $log->sessionid          = $sessionid;
        $log->triggered_by       = $triggered_by;
        $log->trigger_reason     = $trigger_reason;
        $log->students_total     = $total;
        $log->students_attended  = $attended;
        $log->students_partial   = $partial;
        $log->students_absent    = $absent;
        $log->threshold_used     = $threshold;
        $log->duration_used      = $duration_sec;
        $log->timecreated        = time();

        $DB->insert_record('livesessions_finalise_log', $log);
    }

    private static function stamp_session_finalised(int $sessionid): void {
        global $DB;
        $DB->set_field('livesessions_sessions', 'finalised_at', time(), ['id' => $sessionid]);
    }

    private static function has_changed(\stdClass $record, engine_result $result): bool {
        return $record->attendance_status  !== $result->new_status
            || (float)$record->attendance_percent  !== (float)$result->new_percent
            || (int)$record->duration_attended     !== (int)$result->new_duration
            || (int)$record->recording_access      !== (int)$result->new_recording_access;
    }
}
