<?php
/**
 * Join Manager — handles the full student join flow:
 *
 *  1. Eligibility check   (enrolled? session live/scheduled? capacity?)
 *  2. Package credit check (if credit-based join is required by config)
 *  3. Token generation    (short-lived signed URL passed to provider)
 *  4. Attendance record   (created or updated on join)
 *  5. Segment tracking    (open segment in att_segments table)
 *  6. Leave recording     (closes the open segment, accumulates duration)
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class join_manager {

    /** Token validity window in seconds. */
    const TOKEN_TTL = 300; // 5 minutes

    // ---------------------------------------------------------------
    // Public: initiate join
    // ---------------------------------------------------------------

    /**
     * Main entry point — called when student clicks "Join Session".
     *
     * Validates all pre-conditions, deducts credit if required,
     * records attendance join, and returns the provider URL to redirect to.
     *
     * @param  int $sessionid
     * @param  int $userid
     * @return join_result
     * @throws \moodle_exception on any failure.
     */
    public static function initiate_join(int $sessionid, int $userid): join_result {
        global $DB;

        $session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);

        // ---- 1. Session state check ----
        self::assert_session_joinable($session);

        // ---- 2. Enrollment check ----
        self::assert_enrolled($session->courseid, $userid);

        // ---- 3. Capacity check ----
        self::assert_capacity($session);

        // ---- 4. Existing attendance (re-join scenario) ----
        $attendance = $DB->get_record('livesessions_attendance',
            ['sessionid' => $sessionid, 'userid' => $userid]);

        // ---- 5. Credit deduction (only on FIRST join for this session) ----
        $credit_mode = get_config('local_livesessions', 'credit_mode') ?: 'attendance';
        if ($credit_mode === 'credit' && !$attendance) {
            self::deduct_credit($userid, $sessionid);
        }

        // ---- 6. Create / update attendance record ----
        $now = time();
        if (!$attendance) {
            $attendance = self::create_attendance($sessionid, $userid, $now);
        } else {
            // Re-join: increment join_count, update last_join_time.
            $DB->set_field('livesessions_attendance', 'join_count',
                $attendance->join_count + 1, ['id' => $attendance->id]);
            $DB->set_field('livesessions_attendance', 'last_join_time',
                $now, ['id' => $attendance->id]);
            $DB->set_field('livesessions_attendance', 'timemodified',
                $now, ['id' => $attendance->id]);
            $attendance->join_count++;
            $attendance->last_join_time = $now;
        }

        // ---- 7. Open a new attendance segment ----
        self::open_segment($attendance->id, $sessionid, $userid, $now);

        // ---- 8. Auto-start session if it's the teacher joining ----
        if ($userid == $session->teacherid && $session->status === session_manager::STATUS_SCHEDULED) {
            session_manager::start_session($sessionid);
            $session->status = session_manager::STATUS_LIVE;
        }

        // ---- 9. Build provider join URL ----
        $join_url = self::build_join_url($session, $userid);

        // ---- 10. Fire event ----
        $event = \local_livesessions\event\student_joined::create([
            'objectid' => $sessionid,
            'relateduserid' => $userid,
            'context'  => \context_course::instance($session->courseid),
            'other'    => ['attendanceid' => $attendance->id],
        ]);
        $event->trigger();

        return new join_result(
            true,
            $session,
            $attendance,
            $join_url,
            ($attendance->join_count > 1)
        );
    }

    // ---------------------------------------------------------------
    // Public: record leave (called by provider webhook or heartbeat)
    // ---------------------------------------------------------------

    /**
     * Record a student leaving. Closes the open segment and updates totals.
     *
     * @param  int $sessionid
     * @param  int $userid
     * @param  int $timestamp   0 = now
     * @return bool
     */
    public static function record_leave(int $sessionid, int $userid, int $timestamp = 0): bool {
        global $DB;

        $now = $timestamp ?: time();

        $attendance = $DB->get_record('livesessions_attendance',
            ['sessionid' => $sessionid, 'userid' => $userid]);

        if (!$attendance) {
            return false; // No attendance record — nothing to close.
        }

        // Close open segment.
        $duration = self::close_open_segment($attendance->id, $userid, $now);

        // Accumulate duration.
        $new_duration = $attendance->duration_attended + $duration;
        $DB->set_field('livesessions_attendance', 'duration_attended', $new_duration, ['id' => $attendance->id]);
        $DB->set_field('livesessions_attendance', 'leave_time',        $now,          ['id' => $attendance->id]);
        $DB->set_field('livesessions_attendance', 'last_join_time',    null,          ['id' => $attendance->id]);
        $DB->set_field('livesessions_attendance', 'timemodified',      $now,          ['id' => $attendance->id]);

        return true;
    }

    // ---------------------------------------------------------------
    // Public: generate a single-use signed join token
    // ---------------------------------------------------------------

    /**
     * Generate a short-lived token for the student's join link.
     * The token is embedded in the redirect URL so the provider callback
     * can verify the request came from Moodle.
     *
     * @param  int $sessionid
     * @param  int $userid
     * @return string  hex token
     */
    public static function generate_join_token(int $sessionid, int $userid): string {
        global $DB;

        // Invalidate any previous unused token for this user+session.
        $DB->delete_records('livesessions_join_tokens',
            ['sessionid' => $sessionid, 'userid' => $userid, 'used' => 0]);

        $token = bin2hex(random_bytes(32));
        $record = new \stdClass();
        $record->sessionid   = $sessionid;
        $record->userid      = $userid;
        $record->token       = $token;
        $record->expires     = time() + self::TOKEN_TTL;
        $record->used        = 0;
        $record->timecreated = time();
        $DB->insert_record('livesessions_join_tokens', $record);

        return $token;
    }

    /**
     * Validate and consume a join token.
     *
     * @param  string $token
     * @return \stdClass  token record (contains sessionid, userid)
     * @throws \moodle_exception on invalid/expired token
     */
    public static function consume_join_token(string $token): \stdClass {
        global $DB;

        $record = $DB->get_record('livesessions_join_tokens', ['token' => $token]);

        if (!$record) {
            throw new \moodle_exception('invalidjointoken', 'local_livesessions');
        }
        if ($record->used) {
            throw new \moodle_exception('jointokenalreadyused', 'local_livesessions');
        }
        if ($record->expires < time()) {
            throw new \moodle_exception('jointokenexpired', 'local_livesessions');
        }

        $DB->set_field('livesessions_join_tokens', 'used', 1, ['id' => $record->id]);

        return $record;
    }

    // ---------------------------------------------------------------
    // Provider webhook: process a raw participant event
    // ---------------------------------------------------------------

    /**
     * Store and process a raw participant event from any provider.
     *
     * @param  string $provider    zoom | 100ms | agora | bigbluebutton
     * @param  string $event_type  participant.joined | participant.left | etc.
     * @param  int    $sessionid
     * @param  array  $payload     Decoded JSON from webhook
     * @return bool
     */
    public static function process_provider_event(string $provider, string $event_type,
            int $sessionid, array $payload): bool {
        global $DB;

        // 1. Persist raw event for replay / auditing.
        $event_record              = new \stdClass();
        $event_record->sessionid   = $sessionid;
        $event_record->provider    = $provider;
        $event_record->event_type  = $event_type;
        $event_record->external_uid= $payload['participant_id'] ?? $payload['uid'] ?? null;
        $event_record->userid      = null;
        $event_record->payload     = json_encode($payload);
        $event_record->processed   = 0;
        $event_record->timecreated = time();

        // 2. Try to match participant to a Moodle user.
        $userid = self::resolve_participant_userid($sessionid, $payload);
        $event_record->userid = $userid;

        $event_id = $DB->insert_record('livesessions_provider_events', $event_record);

        if (!$userid) {
            // Can't match to a Moodle user — log but don't fail.
            debugging("livesessions: unmatched participant in event {$event_type} for session {$sessionid}",
                DEBUG_DEVELOPER);
            return false;
        }

        // 3. Route to join or leave handler.
        $timestamp = self::extract_timestamp($payload);

        $join_events  = ['participant.joined', 'peer:joined',  'PEER_JOINED',  'USER_JOINED',  'join'];
        $leave_events = ['participant.left',   'peer:left',    'PEER_LEFT',    'USER_LEFT',    'leave'];

        if (in_array($event_type, $join_events)) {
            attendance_manager::record_join($sessionid, $userid, $timestamp);
        } elseif (in_array($event_type, $leave_events)) {
            self::record_leave($sessionid, $userid, $timestamp);
        }

        $DB->set_field('livesessions_provider_events', 'processed', 1, ['id' => $event_id]);

        return true;
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private static function assert_session_joinable(\stdClass $session): void {
        if ($session->status === session_manager::STATUS_CANCELLED) {
            throw new \moodle_exception('sessioncancelled', 'local_livesessions');
        }
        if ($session->status === session_manager::STATUS_COMPLETED) {
            throw new \moodle_exception('sessioncompleted', 'local_livesessions');
        }
        // Allow joining up to 15 minutes early.
        $early_window = 15 * 60;
        if ($session->starttime > time() + $early_window) {
            throw new \moodle_exception('sessionnotstarted', 'local_livesessions');
        }
    }

    private static function assert_enrolled(int $courseid, int $userid): void {
        $context = \context_course::instance($courseid);
        if (!is_enrolled($context, $userid)) {
            throw new \moodle_exception('notenrolled', 'local_livesessions');
        }
    }

    private static function assert_capacity(\stdClass $session): void {
        if ($session->capacity == 0) {
            return; // Unlimited.
        }
        global $DB;
        // Count DISTINCT users who have attendance records (joined at least once).
        $count = $DB->count_records('livesessions_attendance', ['sessionid' => $session->id]);
        if ($count >= $session->capacity) {
            throw new \moodle_exception('sessionfull', 'local_livesessions');
        }
    }

    private static function deduct_credit(int $userid, int $sessionid): void {
        global $DB;

        $pkg = $DB->get_record_sql(
            "SELECT * FROM {livesessions_student_packages}
              WHERE userid = :userid
                AND status = 'active'
                AND remaining_sessions > 0
                AND (expiry_date IS NULL OR expiry_date > :now)
           ORDER BY expiry_date ASC
              LIMIT 1",
            ['userid' => $userid, 'now' => time()]
        );

        if (!$pkg) {
            throw new \moodle_exception('nosessioncredits', 'local_livesessions');
        }

        $pkg->used_sessions++;
        $pkg->remaining_sessions--;
        $pkg->timemodified = time();
        if ($pkg->remaining_sessions <= 0) {
            $pkg->status = 'exhausted';
        }
        $DB->update_record('livesessions_student_packages', $pkg);
    }

    private static function create_attendance(int $sessionid, int $userid, int $now): \stdClass {
        global $DB;

        $record                    = new \stdClass();
        $record->sessionid         = $sessionid;
        $record->userid            = $userid;
        $record->join_time         = $now;
        $record->leave_time        = null;
        $record->duration_attended = 0;
        $record->join_count        = 1;
        $record->last_join_time    = $now;
        $record->attendance_percent= 0.00;
        $record->attendance_status = attendance_manager::STATUS_PENDING;
        $record->recording_access  = 0;
        $record->timecreated       = $now;
        $record->timemodified      = $now;

        $record->id = $DB->insert_record('livesessions_attendance', $record);
        return $record;
    }

    private static function open_segment(int $attendanceid, int $sessionid, int $userid, int $now): int {
        global $DB;

        // Close any accidentally-open segment from a previous crash.
        self::close_open_segment($attendanceid, $userid, $now);

        $seg               = new \stdClass();
        $seg->attendanceid = $attendanceid;
        $seg->sessionid    = $sessionid;
        $seg->userid       = $userid;
        $seg->join_time    = $now;
        $seg->leave_time   = null;
        $seg->duration     = 0;

        return $DB->insert_record('livesessions_att_segments', $seg);
    }

    /**
     * Close the most recent open segment and return its duration in seconds.
     */
    private static function close_open_segment(int $attendanceid, int $userid, int $now): int {
        global $DB;

        // Find the open segment (leave_time IS NULL).
        $open = $DB->get_record_sql(
            "SELECT * FROM {livesessions_att_segments}
              WHERE attendanceid = :aid AND leave_time IS NULL
           ORDER BY join_time DESC LIMIT 1",
            ['aid' => $attendanceid]
        );

        if (!$open) {
            return 0;
        }

        $duration = max(0, $now - $open->join_time);
        $open->leave_time = $now;
        $open->duration   = $duration;
        $DB->update_record('livesessions_att_segments', $open);

        return $duration;
    }

    /**
     * Build the final URL the student is redirected to when joining.
     * For most providers this is the join_url stored on the session.
     * For 100ms/Agora we can construct a token-enhanced URL.
     */
    private static function build_join_url(\stdClass $session, int $userid): string {
        if (!empty($session->join_url)) {
            // Append Moodle user info as URL params for providers that support it.
            global $DB;
            $user = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,email');
            $name = urlencode(fullname($user));

            return rtrim($session->join_url, '?&')
                . (strpos($session->join_url, '?') !== false ? '&' : '?')
                . "uname={$name}&user_id={$userid}";
        }

        // No join URL configured — redirect to a placeholder.
        return new \moodle_url('/local/livesessions/view.php', ['id' => $session->id]);
    }

    /**
     * Try to match a provider participant to a Moodle user.
     * Checks: email field, external_uid stored in attendance, display name.
     */
    private static function resolve_participant_userid(int $sessionid, array $payload): ?int {
        global $DB;

        // Common fields across providers.
        $email   = $payload['email']          ?? $payload['user_email']  ?? null;
        $extuid  = $payload['participant_id'] ?? $payload['uid']          ?? $payload['peer_id'] ?? null;
        $name    = $payload['user_name']      ?? $payload['display_name'] ?? null;

        if ($email) {
            $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0], 'id');
            if ($user) {
                return (int) $user->id;
            }
        }

        // Fallback: look up by Moodle user_id embedded in the join URL params
        // (we append ?user_id=X when building the join URL).
        if (!empty($payload['user_id'])) {
            $id = (int) $payload['user_id'];
            if ($DB->record_exists('user', ['id' => $id, 'deleted' => 0])) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Extract an event timestamp from various provider payload formats.
     */
    private static function extract_timestamp(array $payload): int {
        foreach (['timestamp', 'ts', 'joined_at', 'left_at', 'time'] as $key) {
            if (!empty($payload[$key]) && is_numeric($payload[$key])) {
                // Some providers use milliseconds.
                $val = (int) $payload[$key];
                return $val > 1e12 ? (int)($val / 1000) : $val;
            }
        }
        return time();
    }
}
