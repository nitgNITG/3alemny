<?php
/**
 * Manual session finalisation page.
 * Teachers/admins can trigger attendance calculation on demand.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\attendance_engine;
use local_livesessions\session_manager;

$sessionid = required_param('id',      PARAM_INT);
$action    = optional_param('action',  '', PARAM_ALPHA); // finalise | recalc_student
$userid    = optional_param('userid',  0,  PARAM_INT);

$session = session_manager::get_session($sessionid);
$course  = $DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST);
$context = context_course::instance($session->courseid);

require_login($course);
require_capability('local/livesessions:viewAttendance', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/finalise.php', ['id' => $sessionid]);
$PAGE->set_title(get_string('finaliseattendance', 'local_livesessions'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ---------------------------------------------------------------
// Action handlers (POST only with sesskey)
// ---------------------------------------------------------------
if ($action === 'finalise' && confirm_sesskey()) {
    $result = attendance_engine::finalise_session($sessionid, $USER->id, 'manual');
    if ($result->skipped) {
        \core\notification::warning($result->reason);
    } else {
        \core\notification::success(get_string('finalisedok', 'local_livesessions',
            $result->to_array()));
    }
    redirect(new moodle_url('/local/livesessions/finalise.php', ['id' => $sessionid]));
}

if ($action === 'recalc' && $userid && confirm_sesskey()) {
    $result = attendance_engine::recalculate_student($sessionid, $userid, $USER->id);
    \core\notification::success(get_string('recalcok', 'local_livesessions'));
    redirect(new moodle_url('/local/livesessions/finalise.php', ['id' => $sessionid]));
}

// ---------------------------------------------------------------
// Page output
// ---------------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($session->title) . ' — ' . get_string('finaliseattendance', 'local_livesessions'));

// Session info.
$threshold = attendance_engine::get_threshold();
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body row');
echo html_writer::div(
    html_writer::tag('dt', get_string('status',    'local_livesessions')) .
    html_writer::tag('dd', get_string($session->status, 'local_livesessions')) .
    html_writer::tag('dt', get_string('starttime', 'local_livesessions')) .
    html_writer::tag('dd', userdate($session->starttime)) .
    html_writer::tag('dt', get_string('endtime',   'local_livesessions')) .
    html_writer::tag('dd', userdate($session->endtime)) .
    html_writer::tag('dt', get_string('attendancethreshold', 'local_livesessions')) .
    html_writer::tag('dd', $threshold . '%') .
    html_writer::tag('dt', get_string('finalisedat', 'local_livesessions')) .
    html_writer::tag('dd', $session->finalised_at ? userdate($session->finalised_at)
                                                  : get_string('notfinalised', 'local_livesessions')),
    'col-md-6'
);
echo html_writer::end_div();
echo html_writer::end_div();

// Finalise now button.
$finalise_url = new moodle_url('/local/livesessions/finalise.php',
    ['id' => $sessionid, 'action' => 'finalise', 'sesskey' => sesskey()]);
echo html_writer::div(
    $OUTPUT->single_button($finalise_url,
        get_string('finalisenow', 'local_livesessions'), 'get',
        ['class' => 'mb-4']),
    'mb-4'
);

// ---------------------------------------------------------------
// Attendance table with per-student recalculate button
// ---------------------------------------------------------------
$records = $DB->get_records_sql(
    "SELECT a.*, u.firstname, u.lastname, u.email
       FROM {livesessions_attendance} a
       JOIN {user} u ON u.id = a.userid
      WHERE a.sessionid = :sid
   ORDER BY u.lastname, u.firstname",
    ['sid' => $sessionid]
);

if ($records) {
    echo $OUTPUT->heading(get_string('studentattendance', 'local_livesessions'), 3);

    $table = new html_table();
    $table->head = [
        get_string('student',           'local_livesessions'),
        get_string('joincount',         'local_livesessions'),
        get_string('durationattended',  'local_livesessions'),
        get_string('attendancepercent', 'local_livesessions'),
        get_string('attendancestatus',  'local_livesessions'),
        get_string('recordingaccess',   'local_livesessions'),
        get_string('actions',           'local_livesessions'),
    ];
    $table->attributes['class'] = 'generaltable table table-striped table-sm';

    foreach ($records as $r) {
        $status_map = ['attended' => 'badge-success', 'partial' => 'badge-warning', 'absent' => 'badge-danger'];
        $status_class = $status_map[$r->attendance_status] ?? 'badge-secondary';

        $recalc_url = new moodle_url('/local/livesessions/finalise.php', [
            'id'      => $sessionid,
            'action'  => 'recalc',
            'userid'  => $r->userid,
            'sesskey' => sesskey(),
        ]);
        $seg_url = new moodle_url('/local/livesessions/segments.php',
            ['sessionid' => $sessionid, 'userid' => $r->userid]);
        $log_url = new moodle_url('/local/livesessions/att_log.php',
            ['sessionid' => $sessionid, 'userid' => $r->userid]);

        $table->data[] = [
            $r->firstname . ' ' . $r->lastname . html_writer::tag('br', '') .
                html_writer::tag('small', $r->email, ['class' => 'text-muted']),
            (int)$r->join_count,
            round($r->duration_attended / 60, 1) . ' min',
            html_writer::tag('strong', number_format($r->attendance_percent, 1) . '%'),
            html_writer::span(
                get_string($r->attendance_status, 'local_livesessions'),
                'badge ' . $status_class
            ),
            $r->recording_access
                ? html_writer::span('✓', 'badge badge-success')
                : html_writer::span('✗', 'badge badge-danger'),
            implode(' ', [
                html_writer::link($recalc_url,
                    get_string('recalculate', 'local_livesessions'),
                    ['class' => 'btn btn-xs btn-outline-warning']),
                html_writer::link($seg_url,
                    get_string('viewsegments', 'local_livesessions'),
                    ['class' => 'btn btn-xs btn-outline-info']),
                html_writer::link($log_url,
                    get_string('viewlog', 'local_livesessions'),
                    ['class' => 'btn btn-xs btn-outline-secondary']),
            ]),
        ];
    }
    echo html_writer::table($table);
}

// ---------------------------------------------------------------
// Finalisation history
// ---------------------------------------------------------------
$finalise_logs = $DB->get_records('livesessions_finalise_log',
    ['sessionid' => $sessionid], 'timecreated DESC', '*', 0, 5);

if ($finalise_logs) {
    echo $OUTPUT->heading(get_string('finalisationhistory', 'local_livesessions'), 3);
    $ftable = new html_table();
    $ftable->head = [
        get_string('time', 'local_livesessions'),
        get_string('triggeredby', 'local_livesessions'),
        get_string('total', 'local_livesessions'),
        get_string('attended', 'local_livesessions'),
        get_string('partial', 'local_livesessions'),
        get_string('absent', 'local_livesessions'),
        get_string('attendancethreshold', 'local_livesessions'),
    ];
    $ftable->attributes['class'] = 'generaltable table table-sm table-bordered';

    foreach ($finalise_logs as $fl) {
        $by = $fl->triggered_by
            ? fullname($DB->get_record('user', ['id' => $fl->triggered_by]))
            : get_string('cron', 'local_livesessions');
        $ftable->data[] = [
            userdate($fl->timecreated),
            $by . ' (' . $fl->trigger_reason . ')',
            $fl->students_total,
            html_writer::span($fl->students_attended, 'badge badge-success'),
            html_writer::span($fl->students_partial,  'badge badge-warning'),
            html_writer::span($fl->students_absent,   'badge badge-danger'),
            number_format($fl->threshold_used, 0) . '%',
        ];
    }
    echo html_writer::table($ftable);
}

echo html_writer::link(
    new moodle_url('/local/livesessions/view.php', ['id' => $sessionid]),
    '← ' . get_string('backtosession', 'local_livesessions'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();
