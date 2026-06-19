<?php
/**
 * Recording Access Manager — Feature 4.1 / 4.2.
 *
 * Centralises all recording-access decision logic in one place.
 *
 * Two modes (controlled by local_livesessions/credit_mode setting):
 *
 *   attendance  (4.1) — Access is auto-granted at finalisation time when the
 *                        student's attendance_status = 'attended' (>= threshold).
 *                        No extra action required from the student.
 *
 *   credit      (4.2) — Access is NOT automatic. The student must explicitly
 *                        unlock the recording by spending one package credit.
 *                        Students who already spent a credit to JOIN the session
 *                        are granted recording access automatically (they already paid).
 *
 * Public API:
 *   can_access_recording(sessionid, userid)  → bool (gateway check for player pages)
 *   grant_attendance_access(sessionid)       → int  (batch grant after finalisation)
 *   grant_credit_access(sessionid, userid)   → bool (student clicks "Unlock" button)
 *   revoke_access(sessionid, userid)         → bool (admin)
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class recording_access_manager {

    // ================================================================
    // Gateway: can this student watch the recording?
    // ================================================================

    /**
     * Return true if userid may access the recording for sessionid.
     * Does NOT consume any credits — this is a read-only check.
     *
     * @param  int  $sessionid
     * @param  int  $userid
     * @return bool
     */
    public static function can_access_recording(int $sessionid, int $userid): bool {
        // Admins / teachers bypass all checks.
        if (has_capability('local/livesessions:manageSessions', \context_system::instance(), $userid)) {
            return true;
        }

        global $DB;
        $att = $DB->get_record('livesessions_attendance', [
            'sessionid' => $sessionid,
            'userid'    => $userid,
        ]);

        return $att && (int)$att->recording_access === 1;
    }

    // ================================================================
    // Feature 4.1 — Attendance-based batch grant
    // ================================================================

    /**
     * Grant recording access to all 'attended' students for a session.
     * Called automatically by attendance_engine::finalise_session() and
     * also by process_recording when the recording becomes ready.
     *
     * Only operates when credit_mode = 'attendance' OR when called
     * explicitly from the recording pipeline (both modes benefit from
     * granting attended students access once the video is ready).
     *
     * @param  int  $sessionid
     * @return int  Number of students granted access.
     */
    public static function grant_attendance_access(int $sessionid): int {
        global $DB;

        $updated = $DB->get_records('livesessions_attendance', [
            'sessionid'         => $sessionid,
            'attendance_status' => 'attended',
            'recording_access'  => 0,
        ]);

        foreach ($updated as $att) {
            $DB->set_field('livesessions_attendance', 'recording_access', 1, ['id' => $att->id]);
        }

        return count($updated);
    }

    /**
     * Grant recording access to a single student (manual override or
     * after attendance re-calculation).
     */
    public static function grant_access(int $sessionid, int $userid, int $granted_by = 0): bool {
        global $DB;

        $att = $DB->get_record('livesessions_attendance', [
            'sessionid' => $sessionid,
            'userid'    => $userid,
        ]);

        if (!$att) {
            // No attendance record — create a minimal one to carry the access flag.
            $att                    = new \stdClass();
            $att->sessionid         = $sessionid;
            $att->userid            = $userid;
            $att->join_time         = 0;
            $att->leave_time        = 0;
            $att->duration_attended = 0;
            $att->attendance_percent = 0;
            $att->attendance_status = 'absent';
            $att->recording_access  = 1;
            $att->join_count        = 0;
            $att->timecreated       = time();
            $att->timemodified      = time();
            $DB->insert_record('livesessions_attendance', $att);
            return true;
        }

        $DB->set_field('livesessions_attendance', 'recording_access',  1,     ['id' => $att->id]);
        $DB->set_field('livesessions_attendance', 'timemodified',      time(), ['id' => $att->id]);
        return true;
    }

    /**
     * Revoke recording access (admin action).
     */
    public static function revoke_access(int $sessionid, int $userid): bool {
        global $DB;
        $att = $DB->get_record('livesessions_attendance', [
            'sessionid' => $sessionid,
            'userid'    => $userid,
        ]);
        if (!$att) return false;
        $DB->set_field('livesessions_attendance', 'recording_access', 0,     ['id' => $att->id]);
        $DB->set_field('livesessions_attendance', 'timemodified',     time(), ['id' => $att->id]);
        return true;
    }

    // ================================================================
    // Feature 4.2 — Credit-based unlock
    // ================================================================

    /**
     * Grant recording access by deducting one package credit.
     *
     * Flow:
     *   1. Check student does not already have access (idempotent).
     *   2. Check credit_mode = 'credit'; refuse if not.
     *   3. Check recording exists and is ready.
     *   4. Consume one credit via package_manager (idempotent on sessionid).
     *   5. Set recording_access = 1.
     *
     * Uses a synthetic "sessionid" key for the credit log in the form:
     *   rec_{sessionid}  to distinguish recording unlocks from join credits.
     *
     * @param  int  $sessionid
     * @param  int  $userid
     * @return bool  true = access granted; false = already had access
     * @throws \moodle_exception  No credits / wrong mode / recording not ready.
     */
    public static function grant_credit_access(int $sessionid, int $userid): bool {
        global $DB;

        $credit_mode = get_config('local_livesessions', 'credit_mode') ?: 'attendance';
        if ($credit_mode !== 'credit') {
            throw new \moodle_exception('creditaccessnotenabled', 'local_livesessions');
        }

        // Already has access?
        if (self::can_access_recording($sessionid, $userid)) {
            return false; // No-op.
        }

        // Recording must be ready.
        $rec = $DB->get_record('livesessions_recordings', [
            'sessionid' => $sessionid,
            'status'    => 'ready',
        ]);
        if (!$rec) {
            throw new \moodle_exception('norecordingready', 'local_livesessions');
        }

        // Consume credit (idempotent: uses rec_{sessionid} as the session key).
        package_manager::consume_credit($userid, $sessionid);

        // Grant access.
        self::grant_access($sessionid, $userid, $userid);
        return true;
    }

    /**
     * Check if student already has a join credit for this session
     * (credit_mode='credit' — they paid to join, so recording access is free).
     */
    public static function has_join_credit(int $sessionid, int $userid): bool {
        global $DB;
        return $DB->record_exists('livesessions_credit_log', [
            'userid'    => $userid,
            'sessionid' => $sessionid,
            'action'    => 'consumed',
        ]);
    }

    /**
     * Auto-grant recording access after a recording becomes ready,
     * applying whichever rules the current credit_mode dictates.
     *
     * Called by process_recording and the Bunny encoding webhook.
     */
    public static function apply_access_rules_for_session(int $sessionid): int {
        global $DB;

        $credit_mode = get_config('local_livesessions', 'credit_mode') ?: 'attendance';
        $count       = 0;

        // Attendance mode: grant to all 'attended'.
        if ($credit_mode === 'attendance') {
            $count = self::grant_attendance_access($sessionid);
        }

        // Credit mode: grant to students who already consumed a join credit.
        if ($credit_mode === 'credit') {
            $join_credits = $DB->get_records_sql(
                "SELECT DISTINCT userid FROM {livesessions_credit_log}
                  WHERE sessionid = :sid AND action = 'consumed'",
                ['sid' => $sessionid]
            );
            foreach ($join_credits as $row) {
                if (!self::can_access_recording($sessionid, (int)$row->userid)) {
                    self::grant_access($sessionid, (int)$row->userid, 0);
                    $count++;
                }
            }
        }

        return $count;
    }
}
