<?php
/**
 * Student: request a private 1-to-1 session with a teacher.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\private_session_manager;

$courseid = required_param('courseid', PARAM_INT);

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/livesessions:joinSession', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/request.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('request_session', 'local_livesessions'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ---- Get teachers enrolled in this course ----
$teacher_role = $DB->get_record('role', ['shortname' => 'editingteacher']);
$teachers     = [];
if ($teacher_role) {
    $teachers = get_role_users($teacher_role->id, $context, false,
        'u.id, u.firstname, u.lastname, u.email');
}

// Also check 'teacher' (non-editing).
$ne_role = $DB->get_record('role', ['shortname' => 'teacher']);
if ($ne_role) {
    $ne_teachers = get_role_users($ne_role->id, $context, false,
        'u.id, u.firstname, u.lastname, u.email');
    foreach ($ne_teachers as $t) {
        $teachers[$t->id] = $t;
    }
}

// ---- Handle submission ----
$error = '';
if (optional_param('submitted', 0, PARAM_INT) && confirm_sesskey()) {
    $teacherid   = required_param('teacherid', PARAM_INT);
    $sessiondate = required_param('sessiondate', PARAM_TEXT);   // YYYY-MM-DD
    $sessiontime = required_param('sessiontime', PARAM_TEXT);   // HH:MM
    $duration    = optional_param('duration', 60, PARAM_INT);
    $note        = optional_param('note', '', PARAM_TEXT);
    $note        = trim($note);

    // Validate teacher belongs to this course.
    if (!isset($teachers[$teacherid])) {
        $error = get_string('invalid_teacher', 'local_livesessions');
    } else {
        // Build timestamps.
        $starttime = strtotime($sessiondate . ' ' . $sessiontime . ':00');
        if (!$starttime || $starttime < time() + 300) {
            $error = get_string('invalid_time', 'local_livesessions');
        } else {
            $endtime = $starttime + ($duration * 60);
            private_session_manager::create_request(
                $courseid, $teacherid, $USER->id, $starttime, $endtime, $note
            );
            redirect(
                new moodle_url('/local/livesessions/my_sessions.php', ['courseid' => $courseid]),
                get_string('request_sent', 'local_livesessions'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }
}

// ---- Render page ----
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('request_session', 'local_livesessions'));

if ($error) {
    echo $OUTPUT->notification($error, 'notifyproblem');
}

if (empty($teachers)) {
    echo $OUTPUT->notification(get_string('no_teachers_in_course', 'local_livesessions'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

$min_date = date('Y-m-d', strtotime('+1 hour'));
?>
<div class="card" style="max-width:640px;">
  <div class="card-body">
    <form method="post" action="">
      <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
      <input type="hidden" name="submitted" value="1">
      <input type="hidden" name="courseid" value="<?php echo (int)$courseid; ?>">

      <div class="form-group mb-3">
        <label class="col-form-label font-weight-bold">
          <?php echo get_string('select_teacher', 'local_livesessions'); ?>
        </label>
        <select name="teacherid" class="form-control" required>
          <?php foreach ($teachers as $t): ?>
            <option value="<?php echo (int)$t->id; ?>"><?php echo s(fullname($t)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group mb-3">
        <label class="col-form-label font-weight-bold">
          <?php echo get_string('session_date', 'local_livesessions'); ?>
        </label>
        <input type="date" name="sessiondate" class="form-control" required
               min="<?php echo $min_date; ?>">
      </div>

      <div class="form-group mb-3">
        <label class="col-form-label font-weight-bold">
          <?php echo get_string('session_time', 'local_livesessions'); ?>
        </label>
        <input type="time" name="sessiontime" class="form-control" required>
      </div>

      <div class="form-group mb-3">
        <label class="col-form-label font-weight-bold">
          <?php echo get_string('duration', 'local_livesessions'); ?>
        </label>
        <select name="duration" class="form-control">
          <option value="30">30 <?php echo get_string('minutes', 'local_livesessions'); ?></option>
          <option value="60" selected>60 <?php echo get_string('minutes', 'local_livesessions'); ?></option>
          <option value="90">90 <?php echo get_string('minutes', 'local_livesessions'); ?></option>
          <option value="120">120 <?php echo get_string('minutes', 'local_livesessions'); ?></option>
        </select>
      </div>

      <div class="form-group mb-4">
        <label class="col-form-label font-weight-bold">
          <?php echo get_string('note_optional', 'local_livesessions'); ?>
        </label>
        <textarea name="note" class="form-control" rows="3" maxlength="1000"
          placeholder="<?php echo s(get_string('note_placeholder', 'local_livesessions')); ?>"></textarea>
        <small class="form-text text-muted"><?php echo get_string('note_help', 'local_livesessions'); ?></small>
      </div>

      <button type="submit" class="btn btn-primary btn-lg">
        <?php echo get_string('send_request', 'local_livesessions'); ?>
      </button>
      <?php echo html_writer::link(
          new moodle_url('/local/livesessions/index.php', ['courseid' => $courseid]),
          get_string('cancel', 'moodle'),
          ['class' => 'btn btn-secondary btn-lg ml-2']
      ); ?>
    </form>
  </div>
</div>
<?php
echo $OUTPUT->footer();
