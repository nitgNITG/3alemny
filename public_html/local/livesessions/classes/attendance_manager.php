<?php
/**
 * Attendance Manager — records join/leave events and computes attendance %.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class attendance_manager {

    const STATUS_PENDING  = 'pending';
    const STATUS_ATTENDED = 'attended';
    const STATUS_ABSENT   = 'absent';
    const STATUS_PARTIAL  = 'partial';

    // ---------------------------------------------------------------
    // Record a join event
    // ---------------------------------------------------------------

    /**
     * Called when a student enters the session.
     * Delegates to join_manager for full segment-based tracking.
     * This method is kept as a thin wrapper so external API calls still work.
     *
     * @param  int $sessionid
     * @param  int $userid
     * @param  int $timestamp   defaults to now
     * @return int   attendance record id
     */
    public static function record_join(int $sessionid, int $userid, int $timestamp = 0): int {
        global $DB;

        if ($timestamp === 0) {
            $timestamp = time();
        }

        $existing = $DB->get_record('livesessions_attendance',
            ['sessionid' => $sessionid, 'userid' => $userid]);

        if ($existing) {
            // Already have a record — update join_time only if currently null (re-join after disconnect).
            if (empty($existing->join_time)) {
                $DB->set_field('livesessions_attendance', 'join_time',    $timestamp, ['id' => $existing->id]);
                $DB->set_field('livesessions_attendance', 'timemodified', time(),     ['id' => $existing->id]);
            }
            return $existing->id;
        }

        $record               = new \stdClass();
        $record->sessionid    = $sessionid;
        $record->userid       = $userid;
        $record->join_time    = $timestamp;
        $record->leave_time   = null;
        $record->duration_attended  = 0;
        $record->attendance_percent = 0.00;
        $record->attendance_status  = self::STATUS_PENDING;
        $record->recording_access   = 0;
        $record->timecreated        = time();
        $record->timemodified       = time();

        return $DB->insert_record('livesessions_attendance', $record);
    }

    // ---------------------------------------------------------------
    // Record a leave event
    // ---------------------------------------------------------------

    /**
     * Called when a student leaves the session.
     *
     * @param  int $sessionid
     * @param  int $userid
     * @param  int $timestamp  defaults to now
     * @return bool
     */
    public static function record_leave(int $sessionid, int $userid, int $timestamp = 0): bool {
        global $DB;

        if ($timestamp === 0) {
            $timestamp = time();
        }

        $record = $DB->get_record('livesessions_attendance',
            ['sessionid' => $sessionid, 'userid' => $userid]);

        if (!$record) {
            // Student left without a join record — create one retroactively.
            self::record_join($sessionid, $userid, $timestamp - 60);
            $record = $DB->get_record('livesessions_attendance',
                ['sessionid' => $sessionid, 'userid' => $userid]);
        }

        // Accumulate duration.
        $segment = 0;
        if (!empty($record->join_time) && $timestamp > $record->join_time) {
            $segment = $timestamp - $record->join_time;
        }

        $record->leave_time          = $timestamp;
        $record->duration_attended  += $segment;
        $record->timemodified        = time();

        $DB->update_record('livesessions_attendance', $record);

        return true;
    }

    // ---------------------------------------------------------------
    // Finalise attendance at session end
    // ---------------------------------------------------------------

    /**
     * Called by session_manager::complete_session().
     * Calculates attendance % for all students and sets status + recording_access.
     *
     * @param  int $sessionid
     */
    public static function finalise_session_attendance(int $sessionid): void {
        global $DB;

        $session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $session_duration_sec = $session->duration * 60; // duration field is in minutes.

        if ($session_duration_sec <= 0) {
            return; // Cannot compute percentage with zero duration.
        }

        $threshold = (float) get_config('local_livesessions', 'attendance_threshold');
        if ($threshold <= 0) {
            $threshold = session_manager::ATTENDANCE_THRESHOLD;
        }

        $records = $DB->get_records('livesessions_attendance', ['sessionid' => $sessionid]);

        $cap_time = min(time(), $session->endtime ?: time());

        foreach ($records as $record) {
            // Close any open segment still in progress.
            $open = $DB->get_record_sql(
                "SELECT * FROM {livesessions_att_segments}
                  WHERE attendanceid = :aid AND leave_time IS NULL
               ORDER BY join_time DESC LIMIT 1",
                ['aid' => $record->id]
            );
            if ($open) {
                $seg_dur = max(0, $cap_time - $open->join_time);
                $DB->set_field('livesessions_att_segments', 'leave_time', $cap_time, ['id' => $open->id]);
                $DB->set_field('livesessions_att_segments', 'duration',   $seg_dur,  ['id' => $open->id]);
                $record->duration_attended += $seg_dur;
                $record->leave_time = $cap_time;
            }

            // Recompute total duration from ALL closed segments (most accurate).
            $total_sec = (int)$DB->get_field_sql(
                "SELECT COALESCE(SUM(duration),0) FROM {livesessions_att_segments}
                  WHERE attendanceid = :aid",
                ['aid' => $record->id]
            );
            if ($total_sec > 0) {
                $record->duration_attended = $total_sec;
            }

            $pct = min(100, ($record->duration_attended / $session_duration_sec) * 100);
            $record->attendance_percent = round($pct, 2);

            if ($pct >= $threshold) {
                $record->attendance_status = self::STATUS_ATTENDED;
                $record->recording_access  = 1;
            } elseif ($pct > 0) {
                $record->attendance_status = self::STATUS_PARTIAL;
                $record->recording_access  = 0;
            } else {
                $record->attendance_status = self::STATUS_ABSENT;
                $record->recording_access  = 0;
            }

            $record->timemodified = time();
            $DB->update_record('livesessions_attendance', $record);
        }
    }

    // ---------------------------------------------------------------
    // Read helpers
    // ---------------------------------------------------------------

    /**
     * Get all attendance records for a session.
     */
    public static function get_session_attendance(int $sessionid): array {
        global $DB;
        return array_values($DB->get_records('livesessions_attendance', ['sessionid' => $sessionid]));
    }

    /**
     * Get a specific student's attendance record for a session.
     */
    public static function get_student_attendance(int $sessionid, int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('livesessions_attendance',
            ['sessionid' => $sessionid, 'userid' => $userid]) ?: null;
    }

    /**
     * Does this student have recording access for this session?
     */
    public static function has_recording_access(int $sessionid, int $userid): bool {
        global $DB;
        $record = $DB->get_record('livesessions_attendance',
            ['sessionid' => $sessionid, 'userid' => $userid], 'recording_access');
        return $record && (bool) $record->recording_access;
    }

    /**
     * Manually grant recording access (admin override).
     */
    public static function grant_recording_access(int $sessionid, int $userid): bool {
        global $DB;
        $record = $DB->get_record('livesessions_attendance',
            ['sessionid' => $sessionid, 'userid' => $userid]);
        if (!$record) {
            return false;
        }
        $DB->set_field('livesessions_attendance', 'recording_access', 1, ['id' => $record->id]);
        $DB->set_field('livesessions_attendance', 'timemodified',  time(), ['id' => $record->id]);
        return true;
    }
}
