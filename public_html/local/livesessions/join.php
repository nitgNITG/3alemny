<?php
/**
 * Join Session page.
 *
 * Flow:
 *   1. Student visits /local/livesessions/join.php?id=SESSION_ID
 *   2. All pre-conditions are validated (enrollment, capacity, credits, etc.)
 *   3. Attendance record is created / updated
 *   4. Student is redirected to the provider join URL
 *   5. A "Return to Moodle" landing page records the leave on return
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\join_manager;
use local_livesessions\session_manager;

$sessionid = required_param('id', PARAM_INT);
$confirm   = optional_param('confirm', 0, PARAM_INT); // 1 = post-session return

$session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST);
$context = context_course::instance($session->courseid);

require_login($course);
require_capability('local/livesessions:joinSession', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/join.php', ['id' => $sessionid]);
$PAGE->set_title(get_string('joinsession', 'local_livesessions'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ---------------------------------------------------------------
// Return-from-session flow (student came back to Moodle tab)
// ---------------------------------------------------------------
if ($confirm) {
    // Record leave using "now" as the leave time.
    join_manager::record_leave($sessionid, $USER->id);

    \core\notification::success(get_string('attendancerecorded', 'local_livesessions'));
    redirect(new moodle_url('/local/livesessions/view.php', ['id' => $sessionid]));
}

// ---------------------------------------------------------------
// Normal join flow
// ---------------------------------------------------------------
try {
    $result = join_manager::initiate_join($sessionid, $USER->id);
} catch (\moodle_exception $e) {
    // Show friendly error and send back to session detail.
    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/livesessions/view.php', ['id' => $sessionid]),
            '← ' . get_string('backtosessions', 'local_livesessions'),
            ['class' => 'btn btn-secondary']
        ),
        'mt-3'
    );
    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------------
// Intermediate "Launch" page
// Opens the provider in a new tab and shows a "I've finished" button
// so we capture the return event (which records leave time).
// ---------------------------------------------------------------
echo $OUTPUT->header();

$return_url = new moodle_url('/local/livesessions/join.php', [
    'id'      => $sessionid,
    'confirm' => 1,
]);

$join_url_safe = s($result->join_url);

echo html_writer::start_div('card text-center p-5 mt-4');
echo html_writer::tag('h2', format_string($result->session->title));
echo html_writer::tag('p',
    $result->is_rejoin
        ? get_string('welcomeback', 'local_livesessions')
        : get_string('readytojoin', 'local_livesessions'),
    ['class' => 'lead']);

// Auto-open the provider link in a new tab.
echo html_writer::script(
    "window.open(" . json_encode($result->join_url) . ", '_blank');"
);

// Big join button.
echo html_writer::tag('a', get_string('openprovider', 'local_livesessions'),
    ['href' => $result->join_url, 'target' => '_blank',
     'class' => 'btn btn-success btn-lg m-2']);

echo html_writer::tag('p', get_string('joininstructions', 'local_livesessions'),
    ['class' => 'text-muted mt-4']);

// Return button — records leave when clicked.
echo html_writer::tag('a', get_string('sessionfinished', 'local_livesessions'),
    ['href' => $return_url->out(false),
     'class' => 'btn btn-outline-secondary btn-sm mt-3']);

echo html_writer::end_div();

echo $OUTPUT->footer();
