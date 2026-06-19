<?php
/**
 * Package subscriptions — Feature 3.2.
 *
 * Admin page: view all students subscribed to a package, assign new subscriptions,
 * cancel existing ones. Also shows credit log for the package.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\package_manager;

$packageid = required_param('packageid', PARAM_INT);
$action    = optional_param('action',    '', PARAM_ALPHA);
$subid     = optional_param('subid',     0,  PARAM_INT);
$userid    = optional_param('userid',    0,  PARAM_INT);

require_login();
require_capability('local/livesessions:manageSessions', context_system::instance());

$pkg = package_manager::get_package($packageid);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/livesessions/package_subs.php', ['packageid' => $packageid]);
$PAGE->set_title(get_string('packagesubscriptions', 'local_livesessions'));
$PAGE->set_heading(get_string('packagesubscriptions', 'local_livesessions') . ': ' . format_string($pkg->name));
$PAGE->set_pagelayout('admin');

// ---- Cancel subscription ----
if ($action === 'cancel' && $subid && confirm_sesskey()) {
    package_manager::cancel_subscription($subid, $USER->id);
    \core\notification::success(get_string('subscriptioncancelled', 'local_livesessions'));
    redirect(new moodle_url('/local/livesessions/package_subs.php', ['packageid' => $packageid]));
}

// ---- Assign package to a user ----
if ($action === 'assign' && $userid && confirm_sesskey()) {
    try {
        package_manager::assign_package($userid, $packageid, $USER->id);
        \core\notification::success(get_string('packageassigned', 'local_livesessions'));
    } catch (\moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
    redirect(new moodle_url('/local/livesessions/package_subs.php', ['packageid' => $packageid]));
}

// ---- Handle assign form POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('assign_user', 0, PARAM_INT)) {
    require_sesskey();
    $target_userid = required_param('target_userid', PARAM_INT);
    try {
        package_manager::assign_package($target_userid, $packageid, $USER->id);
        \core\notification::success(get_string('packageassigned', 'local_livesessions'));
    } catch (\moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
    redirect(new moodle_url('/local/livesessions/package_subs.php', ['packageid' => $packageid]));
}

// ---- Fetch data ----
$subs = $DB->get_records_sql(
    "SELECT sp.*, u.firstname, u.lastname, u.email
       FROM {livesessions_student_packages} sp
       JOIN {user} u ON u.id = sp.userid
      WHERE sp.packageid = :packageid
   ORDER BY sp.timecreated DESC",
    ['packageid' => $packageid]
);

$status_colors = [
    package_manager::SUB_ACTIVE    => 'success',
    package_manager::SUB_EXHAUSTED => 'warning',
    package_manager::SUB_EXPIRED   => 'secondary',
    package_manager::SUB_CANCELLED => 'danger',
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('packagesubscriptions', 'local_livesessions')
    . ': ' . format_string($pkg->name));

// Package summary.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body d-flex gap-4');
echo html_writer::tag('span', '<strong>' . get_string('sessions_count', 'local_livesessions') . ':</strong> ' . $pkg->sessions_count);
echo html_writer::tag('span', '<strong>' . get_string('validity_days',  'local_livesessions') . ':</strong> '
    . ($pkg->validity_days > 0 ? $pkg->validity_days . ' ' . get_string('days', 'local_livesessions') : get_string('nolimit', 'local_livesessions')));
echo html_writer::tag('span', '<strong>' . get_string('status', 'local_livesessions') . ':</strong> '
    . html_writer::span($pkg->status, 'badge badge-' . ($status_colors[$pkg->status] ?? 'secondary')));
echo html_writer::end_div();
echo html_writer::end_div();

// ---- Assign form ----
if ($pkg->status === package_manager::PKG_ACTIVE) {
    $assign_form = html_writer::start_tag('form', ['method' => 'post', 'class' => 'form-inline mb-4'])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',     'value' => sesskey()])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'assign_user', 'value' => 1])
        . html_writer::tag('label', get_string('assignuserid', 'local_livesessions'), ['class' => 'mr-2'])
        . html_writer::empty_tag('input', ['type' => 'number', 'name' => 'target_userid', 'class' => 'form-control mr-2', 'placeholder' => 'User ID', 'min' => '1', 'required' => 'required'])
        . html_writer::tag('button', get_string('assignpackage', 'local_livesessions'), ['type' => 'submit', 'class' => 'btn btn-success'])
        . html_writer::end_tag('form');
    echo $assign_form;
}

// ---- Subscriptions table ----
if (empty($subs)) {
    echo $OUTPUT->notification(get_string('nosubscriptions', 'local_livesessions'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('student',       'local_livesessions'),
        get_string('total',         'local_livesessions'),
        get_string('used',          'local_livesessions'),
        get_string('remaining',     'local_livesessions'),
        get_string('expiry',        'local_livesessions'),
        get_string('status',        'local_livesessions'),
        get_string('actions',       'local_livesessions'),
    ];
    $table->attributes['class'] = 'generaltable table table-sm table-striped';

    foreach ($subs as $s) {
        $cancel_url = new moodle_url('/local/livesessions/package_subs.php', [
            'packageid' => $packageid,
            'action'    => 'cancel',
            'subid'     => $s->id,
            'sesskey'   => sesskey(),
        ]);
        $actions = [];
        if ($s->status === package_manager::SUB_ACTIVE) {
            $actions[] = html_writer::link($cancel_url,
                get_string('cancel', 'local_livesessions'),
                ['class' => 'btn btn-xs btn-outline-danger',
                 'onclick' => "return confirm('" . get_string('confirmcancelsub', 'local_livesessions') . "')"]);
        }
        $credit_log_url = new moodle_url('/local/livesessions/credit_log.php', ['subid' => $s->id]);
        $actions[] = html_writer::link($credit_log_url,
            get_string('viewlog', 'local_livesessions'),
            ['class' => 'btn btn-xs btn-outline-secondary']);

        $table->data[] = [
            fullname($s) . html_writer::tag('small', ' ' . $s->email, ['class' => 'text-muted ml-1']),
            $s->total_sessions,
            $s->used_sessions,
            html_writer::tag('strong', $s->remaining_sessions),
            $s->expiry_date ? userdate($s->expiry_date, '%d %b %Y') : get_string('nolimit', 'local_livesessions'),
            html_writer::span($s->status, 'badge badge-' . ($status_colors[$s->status] ?? 'secondary')),
            implode(' ', $actions),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::link(
    new moodle_url('/local/livesessions/packages.php'),
    '← ' . get_string('packages', 'local_livesessions'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();
