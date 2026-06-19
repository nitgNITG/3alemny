<?php
/**
 * Credit audit log — Feature 3.3.
 *
 * Shows all credit movements for a student subscription.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$subid  = required_param('subid',  PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT); // alt: view by user

require_login();
require_capability('local/livesessions:manageSessions', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/livesessions/credit_log.php', ['subid' => $subid]);
$PAGE->set_title(get_string('creditlog', 'local_livesessions'));
$PAGE->set_heading(get_string('creditlog', 'local_livesessions'));
$PAGE->set_pagelayout('admin');

$sub = $DB->get_record('livesessions_student_packages', ['id' => $subid], '*', MUST_EXIST);
$pkg = $DB->get_record('livesessions_packages',         ['id' => $sub->packageid]);
$u   = $DB->get_record('user',                          ['id' => $sub->userid]);

$logs = $DB->get_records('livesessions_credit_log', ['sub_id' => $subid], 'timecreated ASC');

$action_colors = [
    'assigned'  => 'success',
    'consumed'  => 'warning',
    'refunded'  => 'info',
    'expired'   => 'secondary',
    'cancelled' => 'danger',
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('creditlog', 'local_livesessions'));

// Subscription summary.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('p', '<strong>' . get_string('student', 'local_livesessions') . ':</strong> '
    . fullname($u) . ' (' . $u->email . ')');
echo html_writer::tag('p', '<strong>' . get_string('packagename', 'local_livesessions') . ':</strong> '
    . ($pkg ? format_string($pkg->name) : $sub->packageid));
echo html_writer::tag('p', '<strong>' . get_string('total', 'local_livesessions') . ':</strong> '
    . $sub->total_sessions . ' &nbsp; <strong>'
    . get_string('used', 'local_livesessions') . ':</strong> '
    . $sub->used_sessions . ' &nbsp; <strong>'
    . get_string('remaining', 'local_livesessions') . ':</strong> '
    . html_writer::tag('strong', $sub->remaining_sessions));
echo html_writer::tag('p', '<strong>' . get_string('status', 'local_livesessions') . ':</strong> '
    . html_writer::span($sub->status, 'badge badge-' . ($action_colors[$sub->status] ?? 'secondary')));
echo html_writer::end_div();
echo html_writer::end_div();

if (empty($logs)) {
    echo $OUTPUT->notification(get_string('nocreditlog', 'local_livesessions'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('time',          'local_livesessions'),
        get_string('action',        'local_livesessions'),
        get_string('creditsbefore', 'local_livesessions'),
        get_string('creditsafter',  'local_livesessions'),
        get_string('session',       'local_livesessions'),
        get_string('changedby',     'local_livesessions'),
        get_string('reason',        'local_livesessions'),
    ];
    $table->attributes['class'] = 'generaltable table table-sm table-striped';

    foreach ($logs as $l) {
        $by      = $l->changed_by ? fullname($DB->get_record('user', ['id' => $l->changed_by])) : get_string('system', 'local_livesessions');
        $sess    = $l->sessionid  ? html_writer::link(
            new moodle_url('/local/livesessions/view.php', ['id' => $l->sessionid]),
            '#' . $l->sessionid
        ) : '—';

        $delta   = $l->credits_after - $l->credits_before;
        $delta_s = ($delta >= 0 ? '+' : '') . $delta;

        $table->data[] = [
            userdate($l->timecreated, '%d %b %H:%M'),
            html_writer::span($l->action, 'badge badge-' . ($action_colors[$l->action] ?? 'secondary')),
            $l->credits_before,
            html_writer::tag('strong', $l->credits_after) . ' ' . html_writer::tag('small', "({$delta_s})", ['class' => $delta < 0 ? 'text-danger' : 'text-success']),
            $sess,
            $by,
            s($l->reason),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::link(
    new moodle_url('/local/livesessions/package_subs.php', ['packageid' => $sub->packageid]),
    '← ' . get_string('packagesubscriptions', 'local_livesessions'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();
