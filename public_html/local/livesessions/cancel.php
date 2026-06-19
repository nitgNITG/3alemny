<?php
/**
 * Cancel session action page.
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
$context = context_course::instance($session->courseid);
require_login($DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST));
require_capability('local/livesessions:cancelSession', $context);

session_manager::cancel_session($id);
\core\notification::success(get_string('sessioncancelled', 'local_livesessions'));

redirect(new moodle_url('/local/livesessions/index.php', ['courseid' => $session->courseid]));
