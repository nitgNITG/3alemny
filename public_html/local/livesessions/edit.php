<?php
/**
 * Create / Edit session page.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_livesessions\form\session_form;
use local_livesessions\session_manager;

$id       = optional_param('id',       0, PARAM_INT); // session id for editing
$courseid = optional_param('courseid', 0, PARAM_INT);

if ($id) {
    // Editing existing session.
    $session  = session_manager::get_session($id);
    $courseid = $session->courseid;
} else {
    $session = null;
}

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
require_login($course);

if ($id) {
    require_capability('local/livesessions:editSession', $context);
} else {
    require_capability('local/livesessions:createSession', $context);
}

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/edit.php', ['id' => $id, 'courseid' => $courseid]);
$PAGE->set_title($id ? get_string('editsession', 'local_livesessions')
                     : get_string('createsession', 'local_livesessions'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$form = new session_form(null, ['session' => $session, 'courseid' => $courseid]);

// Pre-populate form when editing.
if ($session) {
    $formdata              = clone $session;
    $formdata->description_editor['text']   = $session->description;
    $formdata->description_editor['format'] = FORMAT_HTML;
    $form->set_data($formdata);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/livesessions/index.php', ['courseid' => $courseid]));
}

if ($data = $form->get_data()) {
    $data->description = $data->description_editor['text'];

    if ($id) {
        session_manager::update_session($id, (array) $data);
        \core\notification::success(get_string('sessionupdated', 'local_livesessions'));
    } else {
        $newid = session_manager::create_session((array) $data);
        \core\notification::success(get_string('sessioncreated', 'local_livesessions'));
    }
    redirect(new moodle_url('/local/livesessions/index.php', ['courseid' => $courseid]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($id ? get_string('editsession', 'local_livesessions')
                          : get_string('createsession', 'local_livesessions'));
$form->display();
echo $OUTPUT->footer();
