<?php
/**
 * Recording status audit log for a single recording job.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$recordingid = required_param('recordingid', PARAM_INT);
require_login();
require_capability('local/livesessions:manageSessions', context_system::instance());

$rec  = $DB->get_record('livesessions_recordings', ['id' => $recordingid], '*', MUST_EXIST);
$sess = $DB->get_record('livesessions_sessions',   ['id' => $rec->sessionid]);
$logs = $DB->get_records('livesessions_rec_log', ['recordingid' => $recordingid], 'timecreated ASC');

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/livesessions/rec_log.php', ['recordingid' => $recordingid]);
$PAGE->set_title(get_string('recauditlog', 'local_livesessions'));
$PAGE->set_heading(get_string('recauditlog', 'local_livesessions'));
$PAGE->set_pagelayout('admin');

$status_colors = [
    'detected' => 'secondary', 'queued' => 'info', 'downloading' => 'primary',
    'uploading' => 'warning',  'ready'  => 'success', 'failed' => 'danger',
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('recauditlog', 'local_livesessions'));

// Recording summary.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('p', '<strong>Recording ID:</strong> ' . $rec->id);
echo html_writer::tag('p', '<strong>Session:</strong> ' . ($sess ? format_string($sess->title) : $rec->sessionid));
echo html_writer::tag('p', '<strong>Source Provider:</strong> ' . strtoupper($rec->source_provider ?? $rec->provider));
echo html_writer::tag('p', '<strong>Current Status:</strong> ' .
    html_writer::span($rec->status, 'badge badge-' . ($status_colors[$rec->status] ?? 'secondary')));
echo html_writer::tag('p', '<strong>Retry Count:</strong> ' . $rec->retry_count . ' / ' .
    \local_livesessions\recording_manager::MAX_RETRIES);
if ($rec->external_id) {
    echo html_writer::tag('p', '<strong>External ID:</strong> ' . s($rec->external_id));
}
if ($rec->last_error) {
    echo html_writer::tag('p', '<strong>Last Error:</strong> ' .
        html_writer::tag('code', s($rec->last_error), ['class' => 'text-danger']));
}
echo html_writer::end_div();
echo html_writer::end_div();

// Status timeline.
if (empty($logs)) {
    echo $OUTPUT->notification('No log entries.', 'info');
} else {
    $table = new html_table();
    $table->head = ['Time', 'From', 'To', 'Changed By', 'Reason', 'Detail'];
    $table->attributes['class'] = 'generaltable table table-sm table-striped';
    foreach ($logs as $l) {
        $by = $l->changed_by ? fullname($DB->get_record('user', ['id' => $l->changed_by])) : 'System';
        $table->data[] = [
            userdate($l->timecreated, '%d %b %H:%M:%S'),
            html_writer::span($l->prev_status ?: '(new)', 'badge badge-' . ($status_colors[$l->prev_status] ?? 'secondary')),
            html_writer::span($l->new_status, 'badge badge-' . ($status_colors[$l->new_status] ?? 'secondary')),
            $by,
            $l->reason,
            html_writer::tag('small', s(substr($l->detail ?? '', 0, 200))),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::link(
    new moodle_url('/local/livesessions/recordings.php'),
    '← ' . get_string('backtoqueue', 'local_livesessions'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();
