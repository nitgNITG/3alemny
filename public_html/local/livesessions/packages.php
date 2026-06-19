<?php
/**
 * Package management — Feature 3.1.
 *
 * Admin page: list all packages, with create/edit/archive actions.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\package_manager;

$action    = optional_param('action',    '',  PARAM_ALPHA);
$packageid = optional_param('packageid', 0,   PARAM_INT);

require_login();
require_capability('local/livesessions:manageSessions', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/livesessions/packages.php');
$PAGE->set_title(get_string('packages', 'local_livesessions'));
$PAGE->set_heading(get_string('packages', 'local_livesessions'));
$PAGE->set_pagelayout('admin');

// ---- Handle archive action ----
if ($action === 'archive' && $packageid && confirm_sesskey()) {
    try {
        package_manager::archive_package($packageid);
        \core\notification::success(get_string('packagearchived', 'local_livesessions'));
    } catch (\moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
    redirect(new moodle_url('/local/livesessions/packages.php'));
}

// ---- Handle activate/deactivate toggle ----
if (in_array($action, ['activate','deactivate']) && $packageid && confirm_sesskey()) {
    $new_status = ($action === 'activate') ? package_manager::PKG_ACTIVE : package_manager::PKG_INACTIVE;
    package_manager::update_package($packageid, ['status' => $new_status]);
    \core\notification::success(get_string('packageupdated', 'local_livesessions'));
    redirect(new moodle_url('/local/livesessions/packages.php'));
}

$packages = package_manager::list_packages();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('packages', 'local_livesessions'));

// Create button.
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/livesessions/edit_package.php'),
        get_string('createpackage', 'local_livesessions'),
        ['class' => 'btn btn-primary mb-3']
    )
);

if (empty($packages)) {
    echo $OUTPUT->notification(get_string('nopackages', 'local_livesessions'), 'info');
} else {
    $status_colors = [
        package_manager::PKG_ACTIVE   => 'success',
        package_manager::PKG_INACTIVE => 'secondary',
        package_manager::PKG_ARCHIVED => 'danger',
    ];

    $table              = new html_table();
    $table->head        = [
        get_string('packagename',     'local_livesessions'),
        get_string('sessions_count',  'local_livesessions'),
        get_string('price',           'local_livesessions'),
        get_string('validity_days',   'local_livesessions'),
        get_string('status',          'local_livesessions'),
        get_string('subscribers',     'local_livesessions'),
        get_string('actions',         'local_livesessions'),
    ];
    $table->attributes['class'] = 'generaltable table table-striped table-sm';

    foreach ($packages as $pkg) {
        $subs = $GLOBALS['DB']->count_records('livesessions_student_packages', ['packageid' => $pkg->id]);
        $badge = html_writer::span($pkg->status,
            'badge badge-' . ($status_colors[$pkg->status] ?? 'secondary'));

        $edit_url    = new moodle_url('/local/livesessions/edit_package.php',    ['id' => $pkg->id]);
        $subs_url    = new moodle_url('/local/livesessions/package_subs.php',    ['packageid' => $pkg->id]);
        $toggle_url  = new moodle_url('/local/livesessions/packages.php', [
            'action'    => ($pkg->status === package_manager::PKG_ACTIVE) ? 'deactivate' : 'activate',
            'packageid' => $pkg->id,
            'sesskey'   => sesskey(),
        ]);
        $archive_url = new moodle_url('/local/livesessions/packages.php', [
            'action'    => 'archive',
            'packageid' => $pkg->id,
            'sesskey'   => sesskey(),
        ]);

        $actions = [
            html_writer::link($edit_url,   get_string('edit',   'local_livesessions'), ['class' => 'btn btn-xs btn-outline-primary']),
            html_writer::link($subs_url,   get_string('subscribers', 'local_livesessions'), ['class' => 'btn btn-xs btn-outline-secondary']),
        ];
        if ($pkg->status !== package_manager::PKG_ARCHIVED) {
            $toggle_label = ($pkg->status === package_manager::PKG_ACTIVE)
                ? get_string('deactivate', 'local_livesessions')
                : get_string('activate',   'local_livesessions');
            $actions[] = html_writer::link($toggle_url, $toggle_label, ['class' => 'btn btn-xs btn-outline-warning']);
            $actions[] = html_writer::link($archive_url, get_string('archive', 'local_livesessions'),
                ['class' => 'btn btn-xs btn-outline-danger',
                 'onclick' => "return confirm('" . get_string('confirmarchivepackage', 'local_livesessions') . "')"]);
        }

        $table->data[] = [
            format_string($pkg->name),
            $pkg->sessions_count,
            number_format((float)$pkg->price, 2) . ' ' . $pkg->currency,
            $pkg->validity_days > 0 ? $pkg->validity_days . ' ' . get_string('days', 'local_livesessions') : get_string('nolimit', 'local_livesessions'),
            $badge,
            $subs,
            implode(' ', $actions),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
