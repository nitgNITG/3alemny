<?php
/**
 * Unlock recording with a package credit — Feature 4.2.
 *
 * Shown when credit_mode = 'credit' and the student does not yet have
 * recording_access. Spending one credit grants permanent access.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\recording_access_manager;
use local_livesessions\package_manager;

$sessionid = required_param('sessionid', PARAM_INT);
$confirm   = optional_param('confirm',   0,   PARAM_INT);

require_login();

$session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST);
$context = context_course::instance($session->courseid);

require_login($course);

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/unlock_recording.php', ['sessionid' => $sessionid]);
$PAGE->set_title(get_string('unlockrecording', 'local_livesessions'));
$PAGE->set_heading(format_string($session->title));
$PAGE->set_pagelayout('incourse');

// Already has access → redirect to player.
if (recording_access_manager::can_access_recording($sessionid, $USER->id)) {
    redirect(new moodle_url('/local/livesessions/player.php', ['sessionid' => $sessionid]));
}

// Handle confirmation.
if ($confirm && confirm_sesskey()) {
    try {
        recording_access_manager::grant_credit_access($sessionid, $USER->id);
        \core\notification::success(get_string('recordingaccessgranted', 'local_livesessions'));
        redirect(new moodle_url('/local/livesessions/player.php', ['sessionid' => $sessionid]));
    } catch (\moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unlockrecording', 'local_livesessions'));

$remaining = package_manager::get_remaining_credits($USER->id);

if ($remaining < 1) {
    echo $OUTPUT->notification(get_string('nosessioncredits', 'local_livesessions'), 'warning');
} else {
    // Confirmation form.
    echo html_writer::start_div('card card-body text-center p-4', ['style' => 'max-width:480px;margin:0 auto']);
    echo html_writer::tag('p', get_string('unlockrecordingconfirm', 'local_livesessions',
        (object)['title' => format_string($session->title), 'remaining' => $remaining]));
    echo html_writer::tag('p',
        html_writer::tag('strong', get_string('creditcost1', 'local_livesessions')),
        ['class' => 'text-info']
    );
    $confirm_url = new moodle_url('/local/livesessions/unlock_recording.php', [
        'sessionid' => $sessionid,
        'confirm'   => 1,
        'sesskey'   => sesskey(),
    ]);
    echo html_writer::link($confirm_url,
        get_string('unlockrecordingbtn', 'local_livesessions'),
        ['class' => 'btn btn-primary btn-lg mr-2']
    );
    echo html_writer::link(
        new moodle_url('/local/livesessions/view.php', ['id' => $sessionid]),
        get_string('cancel', 'local_livesessions'),
        ['class' => 'btn btn-secondary btn-lg']
    );
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
