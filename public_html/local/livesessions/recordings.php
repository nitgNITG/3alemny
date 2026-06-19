<?php
/**
 * Recording queue monitor — admin/teacher view of all recording jobs.
 * Shows status, retry count, errors, audit log.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\recording_manager;

$courseid  = optional_param('courseid', 0, PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$action    = optional_param('action', '', PARAM_ALPHA);
$recid     = optional_param('recid', 0, PARAM_INT);

$context = $courseid
    ? context_course::instance($courseid)
    : context_system::instance();

require_login($courseid ? $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST) : null);
require_capability('local/livesessions:manageSessions', context_system::instance());

// ---- Actions ----
if ($action === 'retry' && $recid && confirm_sesskey()) {
    $rec = $DB->get_record('livesessions_recordings', ['id' => $recid], '*', MUST_EXIST);
    $DB->set_field('livesessions_recordings', 'status',      recording_manager::STATUS_QUEUED, ['id' => $recid]);
    $DB->set_field('livesessions_recordings', 'retry_after', null,                            ['id' => $recid]);
    $DB->set_field('livesessions_recordings', 'last_error',  null,                            ['id' => $recid]);
    \local_livesessions\task\process_recording::queue((int)$rec->sessionid, $recid);
    \core\notification::success(get_string('recordingretryqueued', 'local_livesessions'));
    redirect(new moodle_url('/local/livesessions/recordings.php',
        ['courseid' => $courseid, 'sessionid' => $sessionid]));
}

$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/recordings.php', ['courseid' => $courseid, 'sessionid' => $sessionid]);
$PAGE->set_title(get_string('recordingqueue', 'local_livesessions'));
$PAGE->set_heading(get_string('recordingqueue', 'local_livesessions'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('recordingqueue', 'local_livesessions'));

// ---- Stats banner ----
$stats = $DB->get_records_sql(
    "SELECT status, COUNT(*) AS cnt FROM {livesessions_recordings} GROUP BY status"
);
$stat_html = '';
$status_colors = [
    'detected'    => 'secondary',
    'queued'      => 'info',
    'downloading' => 'primary',
    'uploading'   => 'warning',
    'ready'       => 'success',
    'failed'      => 'danger',
];
foreach ($stats as $s) {
    $stat_html .= html_writer::span(
        $s->status . ': ' . $s->cnt,
        'badge badge-' . ($status_colors[$s->status] ?? 'light') . ' mr-2 p-2'
    );
}
if ($stat_html) {
    echo html_writer::div($stat_html, 'mb-4 p-3 bg-light rounded');
}

// ---- Recordings table ----
$where  = '1=1';
$params = [];
if ($sessionid) {
    $where         = 'r.sessionid = :sid';
    $params['sid'] = $sessionid;
}

$records = $DB->get_records_sql(
    "SELECT r.*, s.title AS session_title
       FROM {livesessions_recordings} r
       JOIN {livesessions_sessions} s ON s.id = r.sessionid
      WHERE {$where}
   ORDER BY r.timecreated DESC",
    $params, 0, 100
);

if (empty($records)) {
    echo $OUTPUT->notification(get_string('norecordingsjobs', 'local_livesessions'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        'ID',
        get_string('session',            'local_livesessions'),
        get_string('sourceprovider',     'local_livesessions'),
        get_string('status',             'local_livesessions'),
        get_string('retrycount',         'local_livesessions'),
        get_string('lasterror',          'local_livesessions'),
        get_string('timecreated',        'local_livesessions'),
        get_string('actions',            'local_livesessions'),
    ];
    $table->attributes['class'] = 'generaltable table table-striped table-sm';

    foreach ($records as $r) {
        $status_class = $status_colors[$r->status] ?? 'secondary';
        $retry_url    = new moodle_url('/local/livesessions/recordings.php', [
            'courseid'  => $courseid,
            'sessionid' => $sessionid,
            'action'    => 'retry',
            'recid'     => $r->id,
            'sesskey'   => sesskey(),
        ]);
        $log_url = new moodle_url('/local/livesessions/rec_log.php', ['recordingid' => $r->id]);

        $actions = [
            html_writer::link($log_url, get_string('viewlog', 'local_livesessions'),
                ['class' => 'btn btn-xs btn-outline-secondary']),
        ];
        if (in_array($r->status, ['failed', 'queued'])) {
            $actions[] = html_writer::link($retry_url,
                get_string('retry', 'local_livesessions'),
                ['class' => 'btn btn-xs btn-outline-warning']);
        }
        if ($r->status === 'ready' && !empty($r->embed_url)) {
            $actions[] = html_writer::link($r->embed_url,
                get_string('preview', 'local_livesessions'),
                ['class' => 'btn btn-xs btn-outline-success', 'target' => '_blank']);
        }

        $table->data[] = [
            $r->id,
            html_writer::link(
                new moodle_url('/local/livesessions/view.php', ['id' => $r->sessionid]),
                format_string($r->session_title)
            ),
            strtoupper($r->source_provider ?? $r->provider),
            html_writer::span($r->status, 'badge badge-' . $status_class),
            (int)$r->retry_count . ' / ' . recording_manager::MAX_RETRIES,
            $r->last_error
                ? html_writer::tag('small',
                    html_writer::tag('code', substr($r->last_error, 0, 120)),
                    ['class' => 'text-danger'])
                : '—',
            userdate($r->timecreated, '%d %b %H:%M'),
            implode(' ', $actions),
        ];
    }
    echo html_writer::table($table);
}

// ---- Recent webhook log ----
echo $OUTPUT->heading(get_string('recentwebhooks', 'local_livesessions'), 3);

$webhooks = $DB->get_records_sql(
    "SELECT * FROM {livesessions_webhook_log} ORDER BY timecreated DESC LIMIT 20"
);

if ($webhooks) {
    $wt = new html_table();
    $wt->head = ['Time', 'Provider', 'Event', 'Session', 'Sig', 'Processed'];
    $wt->attributes['class'] = 'generaltable table table-sm table-bordered';
    foreach ($webhooks as $w) {
        $wt->data[] = [
            userdate($w->timecreated, '%d %b %H:%M:%S'),
            strtoupper($w->provider),
            html_writer::tag('code', $w->event_type),
            $w->sessionid ?: '—',
            $w->sig_valid
                ? html_writer::span('✓', 'badge badge-success')
                : html_writer::span('✗', 'badge badge-danger'),
            $w->processed
                ? html_writer::span('yes', 'badge badge-success')
                : html_writer::span('pending', 'badge badge-secondary'),
        ];
    }
    echo html_writer::table($wt);
}

echo $OUTPUT->footer();
