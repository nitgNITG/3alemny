<?php
/**
 * Private (1-to-1) session manager.
 *
 * Handles the full lifecycle of a student-initiated private session:
 *   create_request()  → student submits a request
 *   approve_request() → teacher approves → Zoom meeting auto-created → student notified
 *   reject_request()  → teacher declines  → student notified
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class private_session_manager {

    // ---------------------------------------------------------------
    // Student: create request
    // ---------------------------------------------------------------

    /**
     * Create a pending private session request.
     *
     * @param  int    $courseid
     * @param  int    $teacherid
     * @param  int    $studentid
     * @param  int    $starttime  Unix timestamp
     * @param  int    $endtime    Unix timestamp
     * @param  string $note       Optional student note
     * @return int    New session id
     */
    public static function create_request(
        int $courseid,
        int $teacherid,
        int $studentid,
        int $starttime,
        int $endtime,
        string $note = ''
    ): int {
        global $DB;

        $rec = new \stdClass();
        $rec->courseid            = $courseid;
        $rec->teacherid           = $teacherid;
        $rec->title               = '';        // filled on approval
        $rec->description         = $note;
        $rec->provider            = 'zoom';
        $rec->provider_meeting_id = '';
        $rec->join_url            = '';
        $rec->host_url            = '';
        $rec->starttime           = $starttime;
        $rec->endtime             = $endtime;
        $rec->capacity            = 2;
        $rec->status              = 'scheduled';
        $rec->recording_status    = 'none';
        $rec->session_type        = 'private';
        $rec->requested_by        = $studentid;
        $rec->request_status      = 'pending';
        $rec->request_note        = $note;
        $rec->reject_reason       = '';
        $rec->timecreated         = time();
        $rec->timemodified        = time();

        $id = $DB->insert_record('livesessions_sessions', $rec);

        // Notify teacher.
        self::notify_teacher($teacherid, $studentid, $id, $starttime, $note, $courseid);

        return $id;
    }

    // ---------------------------------------------------------------
    // Teacher: approve
    // ---------------------------------------------------------------

    /**
     * Approve a request: auto-create Zoom meeting, save URLs, notify student.
     *
     * @param  int $sessionid
     * @param  int $acting_userid   Must match session.teacherid (or be admin)
     */
    public static function approve_request(int $sessionid, int $acting_userid): void {
        global $DB;

        $session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);

        // Permission check.
        $is_admin = has_capability('local/livesessions:manageSessions', \context_system::instance(),
            $acting_userid);
        if ((int)$session->teacherid !== $acting_userid && !$is_admin) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        if ($session->request_status !== 'pending') {
            throw new \moodle_exception('invalidrequest', 'local_livesessions');
        }

        // Build meeting topic.
        $student = $DB->get_record('user', ['id' => $session->requested_by],
            'id,firstname,lastname', MUST_EXIST);
        $teacher = $DB->get_record('user', ['id' => $session->teacherid],
            'id,firstname,lastname', MUST_EXIST);
        $topic   = fullname($teacher) . ' & ' . fullname($student);

        $duration_min = (int)max(30, round(($session->endtime - $session->starttime) / 60));

        // Create Zoom meeting via API.
        $zoom    = new zoom_api();
        $meeting = $zoom->create_meeting($topic, $session->starttime, $duration_min);

        // Persist.
        $upd = new \stdClass();
        $upd->id                  = $sessionid;
        $upd->title               = $topic;
        $upd->provider_meeting_id = $meeting['meeting_id'];
        $upd->join_url            = $meeting['join_url'];
        $upd->host_url            = $meeting['host_url'];
        $upd->meeting_password    = $meeting['password'];
        $upd->request_status      = 'approved';
        $upd->timemodified        = time();
        $DB->update_record('livesessions_sessions', $upd);

        // Re-read session so notification has fresh data.
        $session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        self::notify_student_approved($session, $teacher);
    }

    // ---------------------------------------------------------------
    // Teacher: reject
    // ---------------------------------------------------------------

    /**
     * Reject a request and notify the student.
     *
     * @param  int    $sessionid
     * @param  int    $acting_userid
     * @param  string $reason   Optional reason shown to the student
     */
    public static function reject_request(int $sessionid, int $acting_userid, string $reason = ''): void {
        global $DB;

        $session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);

        $is_admin = has_capability('local/livesessions:manageSessions', \context_system::instance(),
            $acting_userid);
        if ((int)$session->teacherid !== $acting_userid && !$is_admin) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        if ($session->request_status !== 'pending') {
            throw new \moodle_exception('invalidrequest', 'local_livesessions');
        }

        $upd = new \stdClass();
        $upd->id             = $sessionid;
        $upd->request_status = 'rejected';
        $upd->reject_reason  = $reason;
        $upd->status         = 'cancelled';
        $upd->timemodified   = time();
        $DB->update_record('livesessions_sessions', $upd);

        $teacher = $DB->get_record('user', ['id' => $session->teacherid],
            'id,firstname,lastname', MUST_EXIST);
        self::notify_student_rejected($session, $teacher, $reason);
    }

    // ---------------------------------------------------------------
    // Queries
    // ---------------------------------------------------------------

    /** Pending requests assigned to a teacher. */
    public static function get_pending_requests(int $teacherid): array {
        global $DB;
        return $DB->get_records_select(
            'livesessions_sessions',
            "teacherid = ? AND session_type = 'private' AND request_status = 'pending'",
            [$teacherid],
            'timecreated ASC'
        );
    }

    /** All private requests made by a student (optionally filtered by course). */
    public static function get_student_requests(int $studentid, int $courseid = 0): array {
        global $DB;

        $sql    = "requested_by = ? AND session_type = 'private'";
        $params = [$studentid];

        if ($courseid) {
            $sql    .= ' AND courseid = ?';
            $params[] = $courseid;
        }

        return $DB->get_records_select('livesessions_sessions', $sql, $params, 'timecreated DESC');
    }

    // ---------------------------------------------------------------
    // Notifications (Moodle internal messaging)
    // ---------------------------------------------------------------

    private static function notify_teacher(
        int $teacherid,
        int $studentid,
        int $sessionid,
        int $starttime,
        string $note,
        int $courseid
    ): void {
        global $DB;

        $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
        $teacher = $DB->get_record('user', ['id' => $teacherid], '*', MUST_EXIST);

        $a          = new \stdClass();
        $a->student = fullname($student);
        $a->time    = userdate($starttime);
        $a->note    = $note ?: '—';

        $msg = new \core\message\message();
        $msg->component         = 'local_livesessions';
        $msg->name              = 'session_request_update';
        $msg->userfrom          = $student;
        $msg->userto            = $teacher;
        $msg->notification      = 1;
        $msg->courseid          = $courseid;
        $msg->subject           = get_string('notify_new_request_subject', 'local_livesessions');
        $msg->fullmessage       = get_string('notify_new_request_body', 'local_livesessions', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = '<p>' . nl2br(s($msg->fullmessage)) . '</p>';
        $msg->smallmessage      = $msg->subject;
        $msg->contexturl        = (new \moodle_url('/local/livesessions/teacher_requests.php'))->out(false);
        $msg->contexturlname    = get_string('pending_requests', 'local_livesessions');

        message_send($msg);
    }

    private static function notify_student_approved(\stdClass $session, \stdClass $teacher): void {
        global $DB;

        $student = $DB->get_record('user', ['id' => $session->requested_by], '*', MUST_EXIST);

        $a           = new \stdClass();
        $a->teacher  = fullname($teacher);
        $a->time     = userdate($session->starttime);
        $a->join_url = $session->join_url;

        $msg = new \core\message\message();
        $msg->component         = 'local_livesessions';
        $msg->name              = 'session_request_update';
        $msg->userfrom          = $teacher;
        $msg->userto            = $student;
        $msg->notification      = 1;
        $msg->courseid          = $session->courseid;
        $msg->subject           = get_string('notify_approved_subject', 'local_livesessions');
        $msg->fullmessage       = get_string('notify_approved_body', 'local_livesessions', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = '<p>' . nl2br(s($msg->fullmessage)) . '</p>';
        $msg->smallmessage      = $msg->subject;
        $msg->contexturl        = (new \moodle_url('/local/livesessions/my_sessions.php',
            ['courseid' => $session->courseid]))->out(false);
        $msg->contexturlname    = get_string('my_sessions', 'local_livesessions');

        message_send($msg);
    }

    private static function notify_student_rejected(
        \stdClass $session,
        \stdClass $teacher,
        string $reason
    ): void {
        global $DB;

        $student = $DB->get_record('user', ['id' => $session->requested_by], '*', MUST_EXIST);

        $a          = new \stdClass();
        $a->teacher = fullname($teacher);
        $a->reason  = $reason ?: get_string('no_reason_given', 'local_livesessions');

        $msg = new \core\message\message();
        $msg->component         = 'local_livesessions';
        $msg->name              = 'session_request_update';
        $msg->userfrom          = $teacher;
        $msg->userto            = $student;
        $msg->notification      = 1;
        $msg->courseid          = $session->courseid;
        $msg->subject           = get_string('notify_rejected_subject', 'local_livesessions');
        $msg->fullmessage       = get_string('notify_rejected_body', 'local_livesessions', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = '<p>' . nl2br(s($msg->fullmessage)) . '</p>';
        $msg->smallmessage      = $msg->subject;
        $msg->contexturl        = (new \moodle_url('/local/livesessions/my_sessions.php',
            ['courseid' => $session->courseid]))->out(false);
        $msg->contexturlname    = get_string('my_sessions', 'local_livesessions');

        message_send($msg);
    }
}
