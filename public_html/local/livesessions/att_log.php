<?php
/**
 * Attendance audit log viewer — shows every status change for one student in one session.
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

$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/att_log.php', ['sessionid' => $sessionid, 'userid' => $userid]);
$PAGE->set_title(get_string('attauditlog', 'local_livesessions'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$logs = $DB->get_records('livesessions_att_log',
    ['sessionid' => $sessionid, 'userid' => $userid], 'timecreated DESC');

echo $OUTPUT->header();
echo $OUTPUT->heading(
    fullname($student) . ' — ' . format_string($session->title) . ' — ' .
    get_string('attauditlog', 'local_livesessions'), 3
);

if (empty($logs)) {
    echo $OUTPUT->notification(get_string('nologentries', 'local_livesessions'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('time',         'local_livesessions'),
        get_string('changedby',    'local_livesessions'),
        get_string('reason',       'local_livesessions'),
        get_string('prevstatus',   'local_livesessions'),
        get_string('newstatus',    'local_livesessions'),
        get_string('prevpercent',  'local_livesessions'),
        get_string('newpercent',   'local_livesessions'),
        get_string('prevduration', 'local_livesessions'),
        get_string('newduration',  'local_livesessions'),
        get_string('recaccess',    'local_livesessions'),
    ];
    $table->attributes['class'] = 'generaltable table table-sm table-striped';

    foreach ($logs as $l) {
        $by = $l->changed_by
            ? fullname($DB->get_record('user', ['id' => $l->changed_by]))
            : get_string('system', 'local_livesessions');

        $status_color_map = ['attended' => 'success', 'partial' => 'warning', 'absent' => 'danger'];
        $status_badge = function($s) use ($status_color_map) {
            $color = $status_color_map[$s] ?? 'secondary';
            return html_writer::span(
                get_string($s ?: 'pending', 'local_livesessions'),
                'badge badge-' . $color
            );
        };

        $table->data[] = [
            userdate($l->timecreated, '%d %b %Y %H:%M:%S'),
            $by,
            $l->reason,
            $status_badge($l->prev_status),
            $status_badge($l->new_status),
            number_format($l->prev_percent, 1) . '%',
            number_format($l->new_percent, 1) . '%',
            round($l->prev_duration / 60, 1) . ' min',
            round($l->new_duration / 60, 1) . ' min',
            ($l->prev_recording_access ? '✓' : '✗') . ' → ' . ($l->new_recording_access ? '✓' : '✗'),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::link(
    new moodle_url('/local/livesessions/finalise.php', ['id' => $sessionid]),
    '← ' . get_string('backtofinalisepage', 'local_livesessions'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();
