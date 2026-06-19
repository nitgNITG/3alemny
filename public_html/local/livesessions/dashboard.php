<?php
/**
 * Admin Dashboard — Feature 7.
 *
 * High-level overview of all live sessions across the site:
 *   • KPI cards: total sessions, live now, unique students, avg attendance %, recordings ready
 *   • Per-course breakdown table (sessions, attended students, avg %)
 *   • Quick links to reports.php and the recording queue
 *
 * Access: requires local/livesessions:manageSessions at system context.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$context = context_system::instance();
require_login();
require_capability('local/livesessions:manageSessions', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/dashboard.php');
$PAGE->set_title(get_string('dashboard', 'local_livesessions'));
$PAGE->set_heading(get_string('dashboard', 'local_livesessions'));
$PAGE->set_pagelayout('admin');

// ---- Date range filter ----
$datefrom = optional_param('datefrom', strtotime('-30 days'), PARAM_INT);
$dateto   = optional_param('dateto',   time(),                PARAM_INT);

// ---- KPI: Total sessions ----
$total_sessions = $DB->count_records_select(
    'livesessions_sessions',
    'timecreated >= :df AND timecreated <= :dt',
    ['df' => $datefrom, 'dt' => $dateto]
);

// ---- KPI: Live now ----
$live_now = $DB->count_records('livesessions_sessions', ['status' => 'live']);

// ---- KPI: Unique students who attended at least one session ----
$total_students = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT userid) FROM {livesessions_attendance}
      WHERE attendance_status = 'attended'"
);

// ---- KPI: Average attendance % across all finalised sessions ----
$avg_att = $DB->get_field_sql(
    "SELECT COALESCE(AVG(attendance_percent), 0)
       FROM {livesessions_attendance}
      WHERE attendance_status IN ('attended','partial')"
);

// ---- KPI: Recordings ready ----
$recordings_ready = $DB->count_records('livesessions_recordings', ['status' => 'ready']);

// ---- Per-course breakdown ----
$course_stats = $DB->get_records_sql(
    "SELECT s.courseid,
            c.fullname                                      AS coursename,
            COUNT(DISTINCT s.id)                            AS sessioncount,
            COUNT(DISTINCT a.userid)                        AS student_count,
            COALESCE(AVG(a.attendance_percent), 0)         AS avg_attendance
       FROM {livesessions_sessions} s
       JOIN {course} c ON c.id = s.courseid
  LEFT JOIN {livesessions_attendance} a
         ON a.sessionid = s.id AND a.attendance_status IN ('attended','partial')
      WHERE s.timecreated >= :df AND s.timecreated <= :dt
   GROUP BY s.courseid, c.fullname
   ORDER BY sessioncount DESC",
    ['df' => $datefrom, 'dt' => $dateto]
);

// ---- Recent session activity (last 10 completed) ----
$recent = $DB->get_records_sql(
    "SELECT s.id, s.title, s.courseid, c.fullname AS coursename,
            s.status, s.starttime, s.endtime
       FROM {livesessions_sessions} s
       JOIN {course} c ON c.id = s.courseid
      WHERE s.status IN ('completed','live')
   ORDER BY s.starttime DESC
      LIMIT 10"
);

// ================================================================
// Output
// ================================================================
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard', 'local_livesessions'));

// ---- Date filter form ----
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline mb-4']);
echo html_writer::tag('label', get_string('datefrom', 'local_livesessions') . ':', ['class' => 'mr-2']);
echo html_writer::empty_tag('input', [
    'type'  => 'date',
    'name'  => 'datefrom',
    'value' => date('Y-m-d', $datefrom),
    'class' => 'form-control mr-3',
]);
echo html_writer::tag('label', get_string('dateto', 'local_livesessions') . ':', ['class' => 'mr-2']);
echo html_writer::empty_tag('input', [
    'type'  => 'date',
    'name'  => 'dateto',
    'value' => date('Y-m-d', $dateto),
    'class' => 'form-control mr-3',
]);
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('apply', 'local_livesessions'),
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

// ---- KPI cards ----
$kpis = [
    ['label' => get_string('totalsessions',    'local_livesessions'), 'value' => $total_sessions,           'color' => 'primary'],
    ['label' => get_string('livesessionscount','local_livesessions'), 'value' => $live_now,                 'color' => 'danger'],
    ['label' => get_string('totalstudents',    'local_livesessions'), 'value' => $total_students,           'color' => 'success'],
    ['label' => get_string('avgattendance',    'local_livesessions'), 'value' => round($avg_att, 1) . '%',  'color' => 'info'],
    ['label' => get_string('recordingsready',  'local_livesessions'), 'value' => $recordings_ready,         'color' => 'secondary'],
];

echo html_writer::start_div('row mb-4');
foreach ($kpis as $kpi) {
    echo html_writer::start_div('col-md-2 mb-3');
    echo html_writer::start_div("card border-{$kpi['color']}");
    echo html_writer::start_div('card-body text-center');
    echo html_writer::tag('h2', $kpi['value'], ['class' => "text-{$kpi['color']}"]);
    echo html_writer::tag('p', $kpi['label'], ['class' => 'card-text small mb-0']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo html_writer::end_div();

// ---- Quick links ----
echo html_writer::start_div('mb-4');
echo html_writer::link(
    new moodle_url('/local/livesessions/reports.php'),
    get_string('reports', 'local_livesessions'),
    ['class' => 'btn btn-outline-primary mr-2']
);
echo html_writer::link(
    new moodle_url('/local/livesessions/recordings.php'),
    get_string('recordingqueue', 'local_livesessions'),
    ['class' => 'btn btn-outline-secondary mr-2']
);
echo html_writer::link(
    new moodle_url('/local/livesessions/packages.php'),
    get_string('packages', 'local_livesessions'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::end_div();

// ---- Per-course table ----
echo $OUTPUT->heading(get_string('recentactivity', 'local_livesessions'), 3);

if (empty($course_stats)) {
    echo $OUTPUT->notification(get_string('nodashboarddata', 'local_livesessions'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('coursename',    'local_livesessions'),
        get_string('sessioncount',  'local_livesessions'),
        get_string('totalstudents', 'local_livesessions'),
        get_string('attendancerate','local_livesessions'),
    ];
    $table->attributes['class'] = 'table table-bordered table-striped';

    foreach ($course_stats as $row) {
        $table->data[] = [
            html_writer::link(
                new moodle_url('/local/livesessions/index.php', ['id' => $row->courseid]),
                format_string($row->coursename)
            ),
            (int)$row->sessioncount,
            (int)$row->student_count,
            round((float)$row->avg_attendance, 1) . '%',
        ];
    }

    echo html_writer::table($table);
}

// ---- Recent sessions ----
echo $OUTPUT->heading(get_string('recentactivity', 'local_livesessions') . ' — Sessions', 3);

if (empty($recent)) {
    echo $OUTPUT->notification(get_string('nodashboarddata', 'local_livesessions'), 'info');
} else {
    $rtable = new html_table();
    $rtable->head = [
        get_string('title',      'local_livesessions'),
        get_string('coursename', 'local_livesessions'),
        get_string('status',     'local_livesessions'),
        get_string('starttime',  'local_livesessions'),
    ];
    $rtable->attributes['class'] = 'table table-sm table-hover';

    foreach ($recent as $s) {
        $rtable->data[] = [
            html_writer::link(
                new moodle_url('/local/livesessions/view.php', ['id' => $s->id]),
                format_string($s->title)
            ),
            format_string($s->coursename),
            html_writer::tag('span', $s->status,
                ['class' => 'badge badge-' . ($s->status === 'live' ? 'danger' : 'secondary')]),
            userdate($s->starttime),
        ];
    }

    echo html_writer::table($rtable);
}

echo $OUTPUT->footer();
