<?php
/**
 * Session list page — adapts view based on role:
 *   Admin/Teacher → all sessions in course
 *   Student       → upcoming sessions in enrolled courses
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/tablelib.php');

use local_livesessions\session_manager;

$courseid = optional_param('courseid', 0, PARAM_INT);
$status   = optional_param('status',   '', PARAM_ALPHA);
$page     = optional_param('page',      0, PARAM_INT);

if ($courseid) {
    $course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
    require_login($course);
} else {
    require_login();
    $context = context_system::instance();
}

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/index.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('pluginname', 'local_livesessions'));
$PAGE->set_heading(get_string('pluginname', 'local_livesessions'));
$PAGE->set_pagelayout('incourse');

$is_admin_or_teacher = has_capability('local/livesessions:viewAllSessions', $context)
                    || has_capability('local/livesessions:createSession',    $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_livesessions'));

// ---------------------------------------------------------------
// Create button (teachers/admins)
// ---------------------------------------------------------------
if ($courseid && has_capability('local/livesessions:createSession', $context)) {
    $createurl = new moodle_url('/local/livesessions/edit.php', ['courseid' => $courseid]);
    echo html_writer::div(
        $OUTPUT->single_button($createurl, get_string('createsession', 'local_livesessions'), 'get'),
        'mb-3'
    );
}

// ---------------------------------------------------------------
// Status filter tabs
// ---------------------------------------------------------------
$statuses = [
    ''           => get_string('all',       'local_livesessions'),
    'scheduled'  => get_string('scheduled', 'local_livesessions'),
    'live'       => get_string('live',      'local_livesessions'),
    'completed'  => get_string('completed', 'local_livesessions'),
    'cancelled'  => get_string('cancelled', 'local_livesessions'),
];

$tabs = [];
foreach ($statuses as $s => $label) {
    $url   = new moodle_url('/local/livesessions/index.php', ['courseid' => $courseid, 'status' => $s]);
    $tabs[] = new tabobject($s ?: 'all', $url, $label);
}
echo $OUTPUT->tabtree($tabs, $status ?: 'all');

// ---------------------------------------------------------------
// Fetch sessions
// ---------------------------------------------------------------
$perpage = 20;
$filters = [];
if ($courseid) {
    $filters['courseid'] = $courseid;
}
if ($status) {
    $filters['status'] = $status;
}

if (!$is_admin_or_teacher) {
    // Students only see upcoming sessions from their enrolled courses.
    $sessions = session_manager::get_upcoming_sessions_for_student($USER->id);
} else {
    $sessions = session_manager::get_sessions($filters, $page * $perpage, $perpage);
}

// ---------------------------------------------------------------
// Render table
// ---------------------------------------------------------------
if (empty($sessions)) {
    echo $OUTPUT->notification(get_string('nosessions', 'local_livesessions'), 'info');
} else {
    $table            = new html_table();
    $table->head      = [
        get_string('title',     'local_livesessions'),
        get_string('teacher',   'local_livesessions'),
        get_string('starttime', 'local_livesessions'),
        get_string('endtime',   'local_livesessions'),
        get_string('provider',  'local_livesessions'),
        get_string('status',    'local_livesessions'),
        get_string('actions',   'local_livesessions'),
    ];
    $table->attributes['class'] = 'generaltable table table-striped';

    foreach ($sessions as $s) {
        $teacher = $DB->get_record('user', ['id' => $s->teacherid]);
        $teacher_name = $teacher ? fullname($teacher) : '—';

        $status_badge = html_writer::span(
            get_string($s->status, 'local_livesessions'),
            'badge badge-' . status_badge_class($s->status)
        );

        $actions = [];

        // Start button (teacher/admin on scheduled sessions).
        if ($s->status === 'scheduled' &&
                has_capability('local/livesessions:editSession', $context)) {
            $starturl = new moodle_url('/local/livesessions/start.php',
                ['id' => $s->id, 'sesskey' => sesskey()]);
            $actions[] = html_writer::link(
                $starturl,
                get_string('startsession', 'local_livesessions'),
                ['class' => 'btn btn-primary btn-sm',
                 'onclick' => "return confirm('" . get_string('confirmstartsession', 'local_livesessions') . "');"]
            );
        }

        // Host button (teacher/admin on live sessions — re-opens host URL).
        if ($s->status === 'live' && !empty($s->host_url) &&
                has_capability('local/livesessions:editSession', $context)) {
            $actions[] = html_writer::link(
                $s->host_url,
                get_string('hostroom', 'local_livesessions'),
                ['class' => 'btn btn-primary btn-sm', 'target' => '_blank']
            );
        }

        // Join button (students — if live or within 15 min of start).
        $can_join_now = in_array($s->status, ['live', 'scheduled'])
            && ($s->starttime - 900) <= time()
            && !empty($s->join_url);
        if ($can_join_now && has_capability('local/livesessions:joinSession', $context)) {
            $join_url = new moodle_url('/local/livesessions/join.php', ['id' => $s->id]);
            $actions[] = html_writer::link(
                $join_url,
                get_string('join', 'local_livesessions'),
                ['class' => 'btn btn-success btn-sm']
            );
        }

        // View details.
        $viewurl  = new moodle_url('/local/livesessions/view.php', ['id' => $s->id]);
        $actions[] = html_writer::link($viewurl,
            get_string('view', 'local_livesessions'), ['class' => 'btn btn-info btn-sm']);

        // Edit (teachers/admins).
        if (has_capability('local/livesessions:editSession', $context) &&
                !in_array($s->status, ['completed', 'cancelled'])) {
            $editurl  = new moodle_url('/local/livesessions/edit.php', ['id' => $s->id]);
            $actions[] = html_writer::link($editurl,
                get_string('edit', 'local_livesessions'), ['class' => 'btn btn-warning btn-sm']);
        }

        // Cancel (teachers/admins).
        if (has_capability('local/livesessions:cancelSession', $context) &&
                !in_array($s->status, ['completed', 'cancelled'])) {
            $cancelurl = new moodle_url('/local/livesessions/cancel.php',
                ['id' => $s->id, 'sesskey' => sesskey()]);
            $actions[] = html_writer::link($cancelurl,
                get_string('cancel', 'local_livesessions'),
                ['class' => 'btn btn-danger btn-sm',
                 'onclick' => "return confirm('" . get_string('confirmsessioncancel', 'local_livesessions') . "');"]);
        }

        $table->data[] = [
            format_string($s->title),
            $teacher_name,
            userdate($s->starttime),
            userdate($s->endtime),
            strtoupper($s->provider),
            $status_badge,
            implode(' ', $actions),
        ];
    }
    echo html_writer::table($table);

    if ($is_admin_or_teacher) {
        echo $OUTPUT->paging_bar(count($sessions), $page, $perpage,
            new moodle_url('/local/livesessions/index.php', ['courseid' => $courseid, 'status' => $status]));
    }
}

echo $OUTPUT->footer();

// ---------------------------------------------------------------
// Helper
// ---------------------------------------------------------------
function status_badge_class(string $status): string {
    $map = [
        'scheduled' => 'secondary',
        'live'      => 'success',
        'completed' => 'primary',
        'cancelled' => 'danger',
    ];
    return $map[$status] ?? 'light';
}
