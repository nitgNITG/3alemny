<?php
/**
 * Show the individual join/leave segments for one student in one session.
 * Used by teachers to drill into why a student got a certain attendance %.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$sessionid = required_param('sessionid', PARAM_INT);
$userid    = required_param('userid',    PARAM_INT);

$session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST);
$context = context_course::instance($session->courseid);

require_login($course);
require_capability('local/livesessions:viewAttendance', $context);

$student    = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
$attendance = $DB->get_record('livesessions_attendance',
    ['sessionid' => $sessionid, 'userid' => $userid], '*', MUST_EXIST);

$segments = $DB->get_records('livesessions_att_segments',
    ['sessionid' => $sessionid, 'userid' => $userid], 'join_time ASC');

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/segments.php', ['sessionid' => $sessionid, 'userid' => $userid]);
$PAGE->set_title(get_string('attendancesegments', 'local_livesessions'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(
    fullname($student) . ' — ' . format_string($session->title), 3
);

// Summary card.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('p', '<strong>' . get_string('joincount', 'local_livesessions') . ':</strong> '
    . (int)$attendance->join_count);
echo html_writer::tag('p', '<strong>' . get_string('durationattended', 'local_livesessions') . ':</strong> '
    . round($attendance->duration_attended / 60, 1) . ' min');
echo html_writer::tag('p', '<strong>' . get_string('attendancepercent', 'local_livesessions') . ':</strong> '
    . number_format($attendance->attendance_percent, 1) . '%');
echo html_writer::tag('p', '<strong>' . get_string('attendancestatus', 'local_livesessions') . ':</strong> '
    . get_string($attendance->attendance_status, 'local_livesessions'));
echo html_writer::end_div();
echo html_writer::end_div();

// Segments table.
if (empty($segments)) {
    echo $OUTPUT->notification(get_string('nosegments', 'local_livesessions'), 'info');
} else {
    $table = new html_table();
    $table->head = ['#', get_string('jointime', 'local_livesessions'),
                    get_string('leavetime', 'local_livesessions'),
                    get_string('durationattended', 'local_livesessions')];
    $table->attributes['class'] = 'generaltable table table-striped table-sm';

    $i = 1;
    foreach ($segments as $s) {
        $dur = $s->duration > 0 ? round($s->duration / 60, 1) . ' min' : '—';
        $table->data[] = [
            $i++,
            userdate($s->join_time),
            $s->leave_time ? userdate($s->leave_time) : html_writer::span('In session', 'badge badge-success'),
            $dur,
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::link(
    new moodle_url('/local/livesessions/attendance.php', ['id' => $sessionid]),
    '← ' . get_string('backtoattendance', 'local_livesessions'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();
