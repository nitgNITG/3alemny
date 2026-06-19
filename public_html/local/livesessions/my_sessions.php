<?php
/**
 * Student: view all their private session requests.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\private_session_manager;

$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid) {
    $course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
    require_login($course);
} else {
    require_login();
    $context = context_system::instance();
}

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/my_sessions.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('my_sessions', 'local_livesessions'));
$PAGE->set_heading($courseid ? $course->fullname : get_string('my_sessions', 'local_livesessions'));
$PAGE->set_pagelayout('incourse');

$sessions = private_session_manager::get_student_requests($USER->id, $courseid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('my_sessions', 'local_livesessions'));

// ---- Quick action button ----
if ($courseid) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/livesessions/request.php', ['courseid' => $courseid]),
            '+ ' . get_string('new_request', 'local_livesessions'),
            ['class' => 'btn btn-primary mb-4']
        ) . ' ' .
        html_writer::link(
            new moodle_url('/local/livesessions/index.php', ['courseid' => $courseid]),
            '← ' . get_string('backtosessions', 'local_livesessions'),
            ['class' => 'btn btn-secondary mb-4']
        ),
        'mb-4'
    );
}

if (empty($sessions)) {
    echo $OUTPUT->notification(get_string('no_sessions', 'local_livesessions'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// ---- Session cards ----
$now = time();
foreach ($sessions as $s) {
    $teacher = $DB->get_record('user', ['id' => $s->teacherid], 'id,firstname,lastname');

    // Status badge.
    $status_colors = [
        'pending'  => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
    ];
    $badge_color  = $status_colors[$s->request_status] ?? 'secondary';
    $status_label = get_string('status_' . $s->request_status, 'local_livesessions');
    $badge = html_writer::span($status_label, 'badge badge-' . $badge_color . ' ml-2');

    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');

    // Title row.
    echo html_writer::tag('h5',
        s(fullname($teacher)) . $badge,
        ['class' => 'card-title']);

    // Time.
    echo html_writer::tag('p',
        html_writer::tag('strong', get_string('starttime', 'local_livesessions') . ': ') .
        userdate($s->starttime) . ' → ' . userdate($s->endtime),
        ['class' => 'mb-1']);

    // Note.
    if (!empty($s->request_note)) {
        echo html_writer::tag('p',
            html_writer::tag('em', '"' . s($s->request_note) . '"'),
            ['class' => 'text-muted mb-2']);
    }

    // Rejection reason.
    if ($s->request_status === 'rejected' && !empty($s->reject_reason)) {
        echo html_writer::tag('p',
            html_writer::tag('strong', get_string('reject_reason', 'local_livesessions') . ': ') .
            s($s->reject_reason),
            ['class' => 'text-danger mb-2']);
    }

    // Action buttons.
    if ($s->request_status === 'approved') {
        // Show Join button 15 min before start and until 30 min after endtime.
        $can_join = ($s->starttime - 900) <= $now && $now <= ($s->endtime + 1800);
        if ($can_join) {
            echo html_writer::link(
                new moodle_url('/local/livesessions/room.php', ['id' => $s->id]),
                get_string('joinsession', 'local_livesessions'),
                ['class' => 'btn btn-success btn-sm']
            );
        } else if ($s->starttime > $now) {
            $diff = $s->starttime - $now;
            $hrs  = floor($diff / 3600);
            $mins = floor(($diff % 3600) / 60);
            echo html_writer::tag('span',
                get_string('starts_in', 'local_livesessions',
                    ['h' => $hrs, 'm' => $mins]),
                ['class' => 'text-muted small']);
        }
    }

    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card
}

echo $OUTPUT->footer();
