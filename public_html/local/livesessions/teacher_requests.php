<?php
/**
 * Teacher: manage incoming private session requests.
 *
 * Approve → Zoom meeting auto-created, student notified.
 * Reject  → optional reason, student notified.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\private_session_manager;

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/livesessions/teacher_requests.php');
$PAGE->set_title(get_string('pending_requests', 'local_livesessions'));
$PAGE->set_heading(get_string('pending_requests', 'local_livesessions'));
$PAGE->set_pagelayout('admin');

// ---- Handle actions ----
$action    = optional_param('action', '', PARAM_ALPHA);
$sessionid = optional_param('sessionid', 0, PARAM_INT);

if ($action && $sessionid && confirm_sesskey()) {
    try {
        if ($action === 'approve') {
            private_session_manager::approve_request($sessionid, $USER->id);
            redirect(
                new moodle_url('/local/livesessions/teacher_requests.php'),
                get_string('request_approved', 'local_livesessions'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else if ($action === 'reject') {
            $reason = optional_param('reason', '', PARAM_TEXT);
            private_session_manager::reject_request($sessionid, $USER->id, trim($reason));
            redirect(
                new moodle_url('/local/livesessions/teacher_requests.php'),
                get_string('request_rejected', 'local_livesessions'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
    } catch (\Throwable $e) {
        \core\notification::error($e->getMessage());
    }
}

// ---- Load data ----
$pending = private_session_manager::get_pending_requests($USER->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pending_requests', 'local_livesessions'));

// ---- Badge count ----
$badge = html_writer::span(count($pending), 'badge badge-danger ml-2');
echo html_writer::tag('p',
    get_string('pending_count', 'local_livesessions', ['n' => count($pending)]),
    ['class' => 'lead']);

if (empty($pending)) {
    echo $OUTPUT->notification(get_string('no_pending', 'local_livesessions'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// ---- Request cards ----
foreach ($pending as $s) {
    $student = $DB->get_record('user', ['id' => $s->requested_by],
        'id,firstname,lastname,email,picture');

    $course = $DB->get_record('course', ['id' => $s->courseid], 'id,fullname');

    echo html_writer::start_div('card mb-4 shadow-sm');
    echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
    echo html_writer::tag('h5', s(fullname($student)), ['class' => 'mb-0']);
    echo html_writer::tag('small', s($course ? $course->fullname : ''), ['class' => 'text-muted']);
    echo html_writer::end_div(); // card-header

    echo html_writer::start_div('card-body');

    // Date/time.
    echo html_writer::tag('p',
        html_writer::tag('strong', '🗓 ') .
        userdate($s->starttime) . ' → ' . userdate($s->endtime) .
        ' (' . round(($s->endtime - $s->starttime) / 60) . ' ' .
        get_string('minutes', 'local_livesessions') . ')',
        ['class' => 'mb-2']);

    // Student email.
    echo html_writer::tag('p',
        html_writer::tag('strong', '✉ ') . s($student->email),
        ['class' => 'mb-2 text-muted small']);

    // Note.
    if (!empty($s->request_note)) {
        echo html_writer::tag('blockquote',
            '"' . s($s->request_note) . '"',
            ['class' => 'blockquote border-left pl-3 text-muted']);
    }

    echo html_writer::end_div(); // card-body

    echo html_writer::start_div('card-footer d-flex align-items-center gap-2');

    // ---- Approve button ----
    $approve_url = new moodle_url('/local/livesessions/teacher_requests.php', [
        'action'    => 'approve',
        'sessionid' => $s->id,
        'sesskey'   => sesskey(),
    ]);
    echo html_writer::link(
        $approve_url,
        '✔ ' . get_string('approve', 'local_livesessions'),
        ['class' => 'btn btn-success mr-3',
         'onclick' => "return confirm('" .
            s(get_string('confirm_approve', 'local_livesessions')) . "');"]
    );

    // ---- Reject form (inline with optional reason) ----
    $reject_url = (new moodle_url('/local/livesessions/teacher_requests.php'))->out(false);
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $reject_url,
        'class'  => 'd-inline-flex align-items-center',
        'style'  => 'gap:8px;',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',   'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',    'value' => 'reject']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sessionid', 'value' => $s->id]);
    echo html_writer::empty_tag('input', [
        'type'        => 'text',
        'name'        => 'reason',
        'class'       => 'form-control form-control-sm',
        'placeholder' => s(get_string('reject_reason_placeholder', 'local_livesessions')),
        'style'       => 'width:220px;',
    ]);
    echo html_writer::tag('button',
        '✗ ' . get_string('reject', 'local_livesessions'),
        ['type' => 'submit', 'class' => 'btn btn-danger btn-sm']);
    echo html_writer::end_tag('form');

    echo html_writer::end_div(); // card-footer
    echo html_writer::end_div(); // card
}

echo $OUTPUT->footer();
