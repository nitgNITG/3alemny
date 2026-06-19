<?php
/**
 * Start a session — marks it as 'live' and redirects the teacher to the host URL.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\session_manager;

$id = required_param('id', PARAM_INT);
require_sesskey();

$session = session_manager::get_session($id);
$course  = $DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST);
$context = context_course::instance($session->courseid);
require_login($course);
require_capability('local/livesessions:editSession', $context);

if (!in_array($session->status, ['scheduled', 'live'])) {
    \core\notification::error(get_string('cannotstartcompleted', 'local_livesessions'));
    redirect(new moodle_url('/local/livesessions/index.php', ['courseid' => $session->courseid]));
}

// Mark the session as live.
if ($session->status === 'scheduled') {
    $DB->set_field('livesessions_sessions', 'status',       'live', ['id' => $id]);
    $DB->set_field('livesessions_sessions', 'timemodified', time(),  ['id' => $id]);
    \core\notification::success(get_string('sessionstarted', 'local_livesessions'));
}

// Redirect teacher into the embedded room page.
redirect(new moodle_url('/local/livesessions/room.php', ['id' => $id]));
