<?php
/**
 * Admin/teacher override: manually grant recording access to a student.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\attendance_manager;

$sessionid = required_param('sessionid', PARAM_INT);
$userid    = required_param('userid',    PARAM_INT);
require_sesskey();

$session = $DB->get_record('livesessions_sessions', ['id' => $sessionid], '*', MUST_EXIST);
$context = context_course::instance($session->courseid);
require_login($DB->get_record('course', ['id' => $session->courseid], '*', MUST_EXIST));
require_capability('local/livesessions:viewAttendance', $context);

attendance_manager::grant_recording_access($sessionid, $userid);
\core\notification::success(get_string('accessgranted', 'local_livesessions'));

redirect(new moodle_url('/local/livesessions/attendance.php', ['id' => $sessionid]));
