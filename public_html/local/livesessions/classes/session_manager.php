<?php
/**
 * Session Manager — CRUD and business logic for live_sessions.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class session_manager {

    // ---------------------------------------------------------------
    // Constants
    // ---------------------------------------------------------------

    const STATUS_SCHEDULED  = 'scheduled';
    const STATUS_LIVE       = 'live';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_CANCELLED  = 'cancelled';

    const PROVIDER_ZOOM         = 'zoom';
    const PROVIDER_100MS        = '100ms';
    const PROVIDER_AGORA        = 'agora';
    const PROVIDER_BBB          = 'bigbluebutton';

    const ATTENDANCE_THRESHOLD = 70; // percent required to qualify as "attended"

    // ---------------------------------------------------------------
    // Create
    // ---------------------------------------------------------------

    /**
     * Create a new live session.
     *
     * @param  array $data  Associative array of session fields.
     * @return int          ID of the newly created session.
     * @throws \moodle_exception on validation failure.
     */
    public static function create_session(array $data): int {
        global $DB, $USER;

        self::validate_session_data($data);

        $record = new \stdClass();
        $record->courseid           = (int) $data['courseid'];
        $record->teacherid          = (int) ($data['teacherid'] ?? $USER->id);
        $record->title              = clean_param($data['title'], PARAM_TEXT);
        $record->description        = clean_param($data['description'] ?? '', PARAM_CLEANHTML);
        $record->provider           = clean_param($data['provider'] ?? self::PROVIDER_ZOOM, PARAM_ALPHA);
        $record->provider_meeting_id = clean_param($data['provider_meeting_id'] ?? '', PARAM_RAW);
        $record->join_url           = clean_param($data['join_url'] ?? '', PARAM_URL);
        $record->host_url           = clean_param($data['host_url'] ?? '', PARAM_URL);
        $record->starttime          = (int) $data['starttime'];
        $record->endtime            = (int) $data['endtime'];
        $record->duration           = (int) (($record->endtime - $record->starttime) / 60);
        $record->capacity           = (int) ($data['capacity'] ?? 0);
        $record->status             = self::STATUS_SCHEDULED;
        $record->recording_status   = 'none';
        $record->timecreated        = time();
        $record->timemodified       = time();
        $record->createdby          = $USER->id;

        $id = $DB->insert_record('livesessions_sessions', $record);

        // Fire event.
        $event = \local_livesessions\event\session_created::create([
            'objectid' => $id,
            'context'  => \context_course::instance($record->courseid),
            'other'    => ['title' => $record->title],
        ]);
        $event->trigger();

        return $id;
    }

    // ---------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------

    /**
     * Update an existing session.
     *
     * @param  int   $sessionid
     * @param  array $data  Fields to update.
     * @return bool
     * @throws \moodle_exception
     */
    public static function update_session(int $sessionid, array $data): bool {
        global $DB;

        $session = self::get_session_or_throw($sessionid);

        if ($session->status === self::STATUS_CANCELLED) {
            throw new \moodle_exception('cannotedircancelledsession', 'local_livesessions');
        }
        if ($session->status === self::STATUS_COMPLETED) {
            throw new \moodle_exception('cannotedicompletdsession', 'local_livesessions');
        }

        $record = new \stdClass();
        $record->id = $sessionid;
        $record->timemodified = time();

        $editable_fields = ['title', 'description', 'provider', 'provider_meeting_id',
                            'join_url', 'host_url', 'starttime', 'endtime', 'capacity'];

        foreach ($editable_fields as $field) {
            if (isset($data[$field])) {
                $record->$field = $data[$field];
            }
        }

        if (isset($record->starttime) && isset($record->endtime)) {
            $record->duration = (int) (($record->endtime - $record->starttime) / 60);
        }

        $DB->update_record('livesessions_sessions', $record);

        // Fire event.
        $event = \local_livesessions\event\session_updated::create([
            'objectid' => $sessionid,
            'context'  => \context_course::instance($session->courseid),
        ]);
        $event->trigger();

        return true;
    }

    // ---------------------------------------------------------------
    // Cancel
    // ---------------------------------------------------------------

    /**
     * Cancel a session.
     *
     * @param  int $sessionid
     * @return bool
     */
    public static function cancel_session(int $sessionid): bool {
        global $DB;

        $session = self::get_session_or_throw($sessionid);

        if ($session->status === self::STATUS_CANCELLED) {
            return true; // Already cancelled, idempotent.
        }
        if ($session->status === self::STATUS_COMPLETED) {
            throw new \moodle_exception('cannotcancelcompleted', 'local_livesessions');
        }

        $DB->set_field('livesessions_sessions', 'status',       self::STATUS_CANCELLED, ['id' => $sessionid]);
        $DB->set_field('livesessions_sessions', 'timemodified', time(),                 ['id' => $sessionid]);

        // Fire event.
        $event = \local_livesessions\event\session_cancelled::create([
            'objectid' => $sessionid,
            'context'  => \context_course::instance($session->courseid),
        ]);
        $event->trigger();

        return true;
    }

    // ---------------------------------------------------------------
    // Status transitions
    // ---------------------------------------------------------------

    /**
     * Mark a session as live (started).
     */
    public static function start_session(int $sessionid): bool {
        global $DB;
        $session = self::get_session_or_throw($sessionid);
        if ($session->status !== self::STATUS_SCHEDULED) {
            throw new \moodle_exception('cannotstartinvalidstatus', 'local_livesessions');
        }
        $DB->set_field('livesessions_sessions', 'status',       self::STATUS_LIVE, ['id' => $sessionid]);
        $DB->set_field('livesessions_sessions', 'timemodified', time(),            ['id' => $sessionid]);
        return true;
    }

    /**
     * Mark a session as completed (ended).
     */
    public static function complete_session(int $sessionid): bool {
        global $DB;
        $session = self::get_session_or_throw($sessionid);
        if (!in_array($session->status, [self::STATUS_LIVE, self::STATUS_SCHEDULED])) {
            throw new \moodle_exception('cannotcompleteinvalidstatus', 'local_livesessions');
        }
        $DB->set_field('livesessions_sessions', 'status',       self::STATUS_COMPLETED, ['id' => $sessionid]);
        $DB->set_field('livesessions_sessions', 'timemodified', time(),                 ['id' => $sessionid]);

        // Finalise all pending attendance records.
        attendance_manager::finalise_session_attendance($sessionid);

        return true;
    }

    // ---------------------------------------------------------------
    // Read
    // ---------------------------------------------------------------

    /**
     * Get a session by ID.
     *
     * @param  int $sessionid
     * @return \stdClass
     * @throws \moodle_exception if not found.
     */
    public static function get_session(int $sessionid): \stdClass {
        return self::get_session_or_throw($sessionid);
    }

    /**
     * Get list of sessions with optional filters.
     *
     * @param  array $filters  Keys: courseid, teacherid, status, from_time, to_time
     * @param  int   $limitfrom
     * @param  int   $limitnum  0 = no limit
     * @return array of \stdClass session records.
     */
    public static function get_sessions(array $filters = [], int $limitfrom = 0, int $limitnum = 50): array {
        global $DB;

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['courseid'])) {
            $where[]             = 'courseid = :courseid';
            $params['courseid']  = (int) $filters['courseid'];
        }
        if (!empty($filters['teacherid'])) {
            $where[]              = 'teacherid = :teacherid';
            $params['teacherid']  = (int) $filters['teacherid'];
        }
        if (!empty($filters['status'])) {
            $where[]           = 'status = :status';
            $params['status']  = $filters['status'];
        }
        if (!empty($filters['from_time'])) {
            $where[]              = 'starttime >= :from_time';
            $params['from_time']  = (int) $filters['from_time'];
        }
        if (!empty($filters['to_time'])) {
            $where[]            = 'starttime <= :to_time';
            $params['to_time']  = (int) $filters['to_time'];
        }

        $sql = 'SELECT * FROM {livesessions_sessions}
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY starttime ASC';

        return array_values($DB->get_records_sql($sql, $params, $limitfrom, $limitnum));
    }

    /**
     * Get upcoming sessions for a given student (enrolled courses, not cancelled/completed).
     */
    public static function get_upcoming_sessions_for_student(int $userid): array {
        global $DB;

        $now = time();

        $sql = "SELECT ls.*
                  FROM {livesessions_sessions} ls
                  JOIN {enrol} e      ON e.courseid = ls.courseid
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :userid
                 WHERE ls.status IN ('scheduled','live')
                   AND ls.starttime >= :now
              ORDER BY ls.starttime ASC";

        return array_values($DB->get_records_sql($sql, ['userid' => $userid, 'now' => $now]));
    }

    /**
     * Get sessions a specific teacher owns.
     */
    public static function get_teacher_sessions(int $teacherid, string $status = ''): array {
        $filters = ['teacherid' => $teacherid];
        if ($status !== '') {
            $filters['status'] = $status;
        }
        return self::get_sessions($filters);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Validate session creation/update data.
     * @throws \moodle_exception
     */
    private static function validate_session_data(array $data): void {
        if (empty($data['courseid'])) {
            throw new \moodle_exception('missingcourseid', 'local_livesessions');
        }
        if (empty($data['title'])) {
            throw new \moodle_exception('missingtitle', 'local_livesessions');
        }
        if (empty($data['starttime']) || empty($data['endtime'])) {
            throw new \moodle_exception('missingtimes', 'local_livesessions');
        }
        if ((int)$data['endtime'] <= (int)$data['starttime']) {
            throw new \moodle_exception('endbeforestart', 'local_livesessions');
        }
        $valid_providers = [self::PROVIDER_ZOOM, self::PROVIDER_100MS,
                            self::PROVIDER_AGORA, self::PROVIDER_BBB];
        if (!empty($data['provider']) && !in_array($data['provider'], $valid_providers)) {
            throw new \moodle_exception('invalidprovider', 'local_livesessions');
        }
    }

    /**
     * Fetch session record or throw.
     */
    private static function get_session_or_throw(int $sessionid): \stdClass {
        global $DB;
        $session = $DB->get_record('livesessions_sessions', ['id' => $sessionid]);
        if (!$session) {
            throw new \moodle_exception('sessionnotfound', 'local_livesessions');
        }
        return $session;
    }
}
