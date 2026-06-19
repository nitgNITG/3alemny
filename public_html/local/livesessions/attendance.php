<?php
/**
 * Detailed attendance report for a single session.
 * Shows each student's join/leave segments with duration breakdown.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\attendance_manager;
use local_livesessions\session_manager;

$sessionid = required_param('id', PARAM_INT);
$download  = optional_param('download', '', PARAM_ALPHA); // csv

$session = session_manager::get_session($sessionid);
$course  = $DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST);
$context = context_course::instance($session->courseid);

require_login($course);
require_capability('local/livesessions:viewAttendance', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/attendance.php', ['id' => $sessionid]);
$PAGE->set_title(get_string('attendancereport', 'local_livesessions'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ---------------------------------------------------------------
// Data: attendance records + segments
// ---------------------------------------------------------------
$records = $DB->get_records_sql(
    "SELECT a.*,
            u.firstname, u.lastname, u.email,
            u.idnumber
       FROM {livesessions_attendance} a
       JOIN {user} u ON u.id = a.userid
      WHERE a.sessionid = :sid
   ORDER BY u.lastname, u.firstname",
    ['sid' => $sessionid]
);

// ---------------------------------------------------------------
// CSV download
// ---------------------------------------------------------------
if ($download === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_session_' . $sessionid . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Student Name', 'Email', 'ID Number',
        'First Join', 'Last Leave', 'Duration (min)', 'Attendance %',
        'Status', 'Recording Access'
    ]);

    foreach ($records as $r) {
        fputcsv($out, [
            $r->firstname . ' ' . $r->lastname,
            $r->email,
            $r->idnumber,
            $r->join_time  ? userdate($r->join_time)  : '',
            $r->leave_time ? userdate($r->leave_time) : '',
            round($r->duration_attended / 60, 1),
            number_format($r->attendance_percent, 1),
            $r->attendance_status,
            $r->recording_access ? 'Yes' : 'No',
        ]);
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------
// HTML output
// ---------------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($session->title) . ' — ' . get_string('attendancereport', 'local_livesessions'));

// Session summary bar.
$total    = count($records);
$attended = array_reduce((array)$records, fn($c, $r) => $c + ($r->attendance_status === 'attended' ? 1 : 0), 0);
$absent   = array_reduce((array)$records, fn($c, $r) => $c + ($r->attendance_status === 'absent'   ? 1 : 0), 0);
$partial  = $total - $attended - $absent;

echo html_writer::div(
    html_writer::div(
        html_writer::tag('span', $total,    ['class' => 'badge badge-secondary mx-2']) . get_string('total',    'local_livesessions') .
        html_writer::tag('span', $attended, ['class' => 'badge badge-success  mx-2']) . get_string('attended', 'local_livesessions') .
        html_writer::tag('span', $partial,  ['class' => 'badge badge-warning  mx-2']) . get_string('partial',  'local_livesessions') .
        html_writer::tag('span', $absent,   ['class' => 'badge badge-danger   mx-2']) . get_string('absent',   'local_livesessions'),
        'p-3 bg-light rounded mb-3'
    ),
    'mb-3'
);

// Download button.
$csvurl = new moodle_url('/local/livesessions/attendance.php', ['id' => $sessionid, 'download' => 'csv']);
echo html_writer::div(
    html_writer::link($csvurl, '↓ ' . get_string('downloadcsv', 'local_livesessions'),
        ['class' => 'btn btn-outline-primary btn-sm mb-3']),
    'text-right'
);

// Threshold notice.
$threshold = (int)(get_config('local_livesessions', 'attendance_threshold') ?: 70);
echo html_writer::tag('p',
    get_string('attendancethresholdnotice', 'local_livesessions', $threshold),
    ['class' => 'text-muted small']);

if (empty($records)) {
    echo $OUTPUT->notification(get_string('noattendance', 'local_livesessions'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        '#',
        get_string('student',           'local_livesessions'),
        get_string('joincount',         'local_livesessions'),
        get_string('jointime',          'local_livesessions'),
        get_string('leavetime',         'local_livesessions'),
        get_string('durationattended',  'local_livesessions'),
        get_string('attendancepercent', 'local_livesessions'),
        get_string('attendancestatus',  'local_livesessions'),
        get_string('recordingaccess',   'local_livesessions'),
        get_string('actions',           'local_livesessions'),
    ];
    $table->attributes['class'] = 'generaltable table table-striped table-sm';

    $i = 1;
    foreach ($records as $r) {
        $status_map = ['attended' => 'badge-success', 'absent' => 'badge-danger', 'partial' => 'badge-warning'];
        $status_class = $status_map[$r->attendance_status] ?? 'badge-secondary';

        // Grant access override button.
        if (!$r->recording_access && $r->attendance_status !== 'attended') {
            $grant_url = new moodle_url('/local/livesessions/grant_access.php', [
                'sessionid' => $sessionid,
                'userid'    => $r->userid,
                'sesskey'   => sesskey(),
            ]);
            $grant_btn = html_writer::link($grant_url,
                get_string('grantaccess', 'local_livesessions'),
                ['class' => 'btn btn-xs btn-outline-success']);
        } else {
            $grant_btn = '—';
        }

        // Segments expand link.
        $segs_url = new moodle_url('/local/livesessions/segments.php', [
            'sessionid' => $sessionid,
            'userid'    => $r->userid,
        ]);

        $table->data[] = [
            $i++,
            html_writer::tag('strong', $r->firstname . ' ' . $r->lastname)
                . html_writer::tag('br', '')
                . html_writer::tag('small', $r->email, ['class' => 'text-muted']),
            (int) $r->join_count,
            $r->join_time  ? userdate($r->join_time)  : '—',
            $r->leave_time ? userdate($r->leave_time) : '—',
            round($r->duration_attended / 60, 1) . ' min',
            html_writer::tag('strong', number_format($r->attendance_percent, 1) . '%'),
            html_writer::span(
                get_string($r->attendance_status, 'local_livesessions'),
                'badge ' . $status_class
            ),
            $r->recording_access
                ? html_writer::span(get_string('yes', 'moodle'), 'badge badge-success')
                : html_writer::span(get_string('no', 'moodle'),  'badge badge-danger'),
            implode(' ', [
                html_writer::link($segs_url, get_string('viewsegments', 'local_livesessions'),
                    ['class' => 'btn btn-xs btn-outline-info']),
                $grant_btn,
            ]),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/livesessions/view.php', ['id' => $sessionid]),
        '← ' . get_string('backtosession', 'local_livesessions'),
        ['class' => 'btn btn-secondary mt-3']
    )
);

echo $OUTPUT->footer();
