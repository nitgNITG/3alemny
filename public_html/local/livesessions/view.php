<?php
/**
 * View session detail page.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\session_manager;
use local_livesessions\attendance_manager;

$id = required_param('id', PARAM_INT);
$session = session_manager::get_session($id);

$course  = $DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST);
$context = context_course::instance($session->courseid);
require_login($course);

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/view.php', ['id' => $id]);
$PAGE->set_title(format_string($session->title));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$is_teacher = has_capability('local/livesessions:viewAttendance', $context);
$is_student = has_capability('local/livesessions:joinSession', $context);
$teacher    = $DB->get_record('user', ['id' => $session->teacherid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($session->title));

// ---------------------------------------------------------------
// Session info card
// ---------------------------------------------------------------
$info = html_writer::start_div('card mb-4');
$info .= html_writer::start_div('card-body');
$info .= html_writer::tag('p', '<strong>' . get_string('teacher', 'local_livesessions') . ':</strong> '
    . ($teacher ? fullname($teacher) : '—'));
$info .= html_writer::tag('p', '<strong>' . get_string('starttime', 'local_livesessions') . ':</strong> '
    . userdate($session->starttime));
$info .= html_writer::tag('p', '<strong>' . get_string('endtime', 'local_livesessions') . ':</strong> '
    . userdate($session->endtime));
$info .= html_writer::tag('p', '<strong>' . get_string('duration', 'local_livesessions') . ':</strong> '
    . $session->duration . ' ' . get_string('minutes', 'local_livesessions'));
$info .= html_writer::tag('p', '<strong>' . get_string('provider', 'local_livesessions') . ':</strong> '
    . strtoupper($session->provider));
$info .= html_writer::tag('p', '<strong>' . get_string('status', 'local_livesessions') . ':</strong> '
    . get_string($session->status, 'local_livesessions'));

if (!empty($session->description)) {
    $info .= html_writer::tag('p', format_text($session->description, FORMAT_HTML));
}
$info .= html_writer::end_div();
$info .= html_writer::end_div();
echo $info;

// ---------------------------------------------------------------
// Start button (teacher/admin on scheduled sessions)
// ---------------------------------------------------------------
if ($is_teacher && $session->status === 'scheduled') {
    $starturl = new moodle_url('/local/livesessions/start.php',
        ['id' => $id, 'sesskey' => sesskey()]);
    echo html_writer::div(
        html_writer::link($starturl, get_string('startsession', 'local_livesessions'),
            ['class' => 'btn btn-primary btn-lg mr-2',
             'onclick' => "return confirm('" . get_string('confirmstartsession', 'local_livesessions') . "');"]),
        'mb-4'
    );
}

// Host button (teacher/admin re-entering a live session)
if ($is_teacher && $session->status === 'live' && !empty($session->host_url)) {
    echo html_writer::div(
        html_writer::link($session->host_url, get_string('hostroom', 'local_livesessions'),
            ['class' => 'btn btn-primary btn-lg mr-2', 'target' => '_blank']),
        'mb-4'
    );
}

// ---------------------------------------------------------------
// Join button (students, when live)
// ---------------------------------------------------------------
// Students can join if session is live OR up to 15 min early (scheduled).
$can_join = $is_student
    && in_array($session->status, ['live', 'scheduled'])
    && ($session->starttime - 900) <= time()
    && !empty($session->join_url);

if ($can_join) {
    $join_url = new moodle_url('/local/livesessions/join.php', ['id' => $session->id]);
    echo html_writer::div(
        html_writer::link($join_url->out(false), get_string('joinsession', 'local_livesessions'),
            ['class' => 'btn btn-success btn-lg']),
        'mb-4'
    );
}

// ---------------------------------------------------------------
// Recording section (if available and student attended)
// ---------------------------------------------------------------
$recording = $DB->get_record('livesessions_recordings',
    ['sessionid' => $id, 'status' => 'ready'], '*', IGNORE_MISSING);

if ($recording) {
    $has_access = $is_teacher
               || attendance_manager::has_recording_access($id, $USER->id)
               || has_capability('local/livesessions:manageSessions', context_system::instance());

    if ($has_access) {
        echo $OUTPUT->heading(get_string('recording', 'local_livesessions'), 3);

        // Feature 2.2: Link to player.php which generates a fresh per-request
        // signed URL (Bunny token auth). Prevents embed URL scraping.
        $player_url = new moodle_url('/local/livesessions/player.php', ['sessionid' => $id]);
        echo html_writer::div(
            html_writer::link(
                $player_url,
                '▶ ' . get_string('recording', 'local_livesessions'),
                ['class' => 'btn btn-primary btn-lg']
            ),
            'mb-3'
        );
    } elseif (!$has_access) {
        echo $OUTPUT->notification(
            get_string('recordingaccessdenied', 'local_livesessions'), 'warning');
    }
}

// ---------------------------------------------------------------
// Attendance (teachers/admins) — link to full report
// ---------------------------------------------------------------
if ($is_teacher) {
    echo $OUTPUT->heading(get_string('attendance', 'local_livesessions'), 3);

    $att_url = new moodle_url('/local/livesessions/attendance.php', ['id' => $id]);
    echo html_writer::div(
        $OUTPUT->single_button($att_url, get_string('attendancereport', 'local_livesessions'), 'get',
            ['class' => 'mb-3']),
        'mb-3'
    );

    // Quick summary count.
    $att_count = $DB->count_records('livesessions_attendance', ['sessionid' => $id]);
    $attended  = $DB->count_records('livesessions_attendance',
        ['sessionid' => $id, 'attendance_status' => 'attended']);
    if ($att_count) {
        echo html_writer::tag('p',
            get_string('attendancesummary', 'local_livesessions',
                ['attended' => $attended, 'total' => $att_count]),
            ['class' => 'text-muted']);
    }
}

// ---------------------------------------------------------------
// Back button
// ---------------------------------------------------------------
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/livesessions/index.php', ['courseid' => $session->courseid]),
        '← ' . get_string('backtosessions', 'local_livesessions'),
        ['class' => 'btn btn-secondary']
    ),
    'mt-3'
);

echo $OUTPUT->footer();
