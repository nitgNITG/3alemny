<?php
/**
 * Package & Revenue Report — Feature 7.
 *
 * Shows per-package credit consumption, subscriptions, and revenue for a
 * configurable date range. Includes a CSV export.
 *
 * Access: requires local/livesessions:manageSessions at system context.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$context = context_system::instance();
require_login();
require_capability('local/livesessions:manageSessions', $context);

$datefrom  = optional_param('datefrom',  strtotime('-30 days'), PARAM_INT);
$dateto    = optional_param('dateto',    time(),                PARAM_INT);
$export    = optional_param('export',    0,                     PARAM_INT);

// ---- Aggregate per-package stats ----
$rows = $DB->get_records_sql(
    "SELECT
          p.id                                AS packageid,
          p.name                              AS packagename,
          p.sessions_count                    AS pkg_credits,
          p.price                             AS price,
          p.currency                          AS currency,
          COUNT(DISTINCT sp.id)               AS total_subs,
          COUNT(DISTINCT CASE WHEN sp.status = 'active' THEN sp.id END) AS active_subs,
          COALESCE(SUM(CASE WHEN cl.action = 'consumed' THEN 1 ELSE 0 END), 0) AS credits_consumed,
          COALESCE(SUM(CASE WHEN cl.action = 'granted'  THEN 1 ELSE 0 END), 0) AS credits_granted,
          (COUNT(DISTINCT sp.id) * p.price)  AS total_revenue
     FROM {livesessions_packages} p
LEFT JOIN {livesessions_student_packages} sp ON sp.packageid = p.id
LEFT JOIN {livesessions_credit_log} cl
       ON cl.packageid = p.id
      AND cl.timecreated >= :df AND cl.timecreated <= :dt
    WHERE p.status != 'archived'
 GROUP BY p.id, p.name, p.sessions_count, p.price, p.currency
 ORDER BY total_subs DESC, p.name ASC",
    ['df' => $datefrom, 'dt' => $dateto]
);

// ---- CSV export ----
if ($export) {
    $filename = 'livesessions_report_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Package', 'Price', 'Currency', 'Credits/Package',
        'Total Subs', 'Active Subs', 'Credits Granted', 'Credits Consumed',
        'Estimated Revenue',
    ]);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r->packagename,
            $r->price,
            $r->currency,
            $r->pkg_credits,
            $r->total_subs,
            $r->active_subs,
            $r->credits_granted,
            $r->credits_consumed,
            number_format((float)$r->total_revenue, 2),
        ]);
    }
    fclose($out);
    exit;
}

// ================================================================
// Output
// ================================================================
$PAGE->set_context($context);
$PAGE->set_url('/local/livesessions/reports.php');
$PAGE->set_title(get_string('reports', 'local_livesessions'));
$PAGE->set_heading(get_string('reports', 'local_livesessions'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reports', 'local_livesessions'));

// ---- Date filter form ----
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline mb-4']);
echo html_writer::tag('label', get_string('datefrom', 'local_livesessions') . ':', ['class' => 'mr-2']);
echo html_writer::empty_tag('input', [
    'type'  => 'date',
    'name'  => 'datefrom',
    'value' => date('Y-m-d', $datefrom),
    'class' => 'form-control mr-3',
]);
echo html_writer::tag('label', get_string('dateto', 'local_livesessions') . ':', ['class' => 'mr-2']);
echo html_writer::empty_tag('input', [
    'type'  => 'date',
    'name'  => 'dateto',
    'value' => date('Y-m-d', $dateto),
    'class' => 'form-control mr-3',
]);
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('apply', 'local_livesessions'),
    'class' => 'btn btn-primary mr-3',
]);
// Export link (same filters, add export=1)
$export_url = new moodle_url('/local/livesessions/reports.php', [
    'datefrom' => $datefrom,
    'dateto'   => $dateto,
    'export'   => 1,
]);
echo html_writer::link($export_url, get_string('exportcsv', 'local_livesessions'),
    ['class' => 'btn btn-outline-secondary']);
echo html_writer::end_tag('form');

// ---- Summary KPIs ----
$total_subs_all     = array_sum(array_column((array)$rows, 'total_subs'));
$total_consumed_all = array_sum(array_column((array)$rows, 'credits_consumed'));
$total_revenue_all  = array_sum(array_column((array)$rows, 'total_revenue'));

echo html_writer::start_div('row mb-4');
foreach ([
    [get_string('totalsubscriptions', 'local_livesessions'), $total_subs_all,                    'primary'],
    [get_string('totalcreditsused',   'local_livesessions'), $total_consumed_all,                 'warning'],
    [get_string('reports',            'local_livesessions') . ' Revenue', number_format((float)$total_revenue_all, 2), 'success'],
] as [$label, $value, $color]) {
    echo html_writer::start_div('col-md-4 mb-3');
    echo html_writer::start_div("card border-{$color}");
    echo html_writer::start_div('card-body text-center');
    echo html_writer::tag('h2', $value, ['class' => "text-{$color}"]);
    echo html_writer::tag('p', $label, ['class' => 'card-text small mb-0']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo html_writer::end_div();

// ---- Per-package table ----
if (empty($rows)) {
    echo $OUTPUT->notification(get_string('noreportdata', 'local_livesessions'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('packagename',       'local_livesessions'),
        get_string('price',             'local_livesessions'),
        get_string('sessions_count',    'local_livesessions'),
        get_string('totalsubscriptions','local_livesessions'),
        get_string('subscribers',       'local_livesessions') . ' (active)',
        get_string('creditsgranted',    'local_livesessions'),
        get_string('creditsconsumed',   'local_livesessions'),
        'Revenue (est.)',
    ];
    $table->attributes['class'] = 'table table-bordered table-striped';

    foreach ($rows as $r) {
        $table->data[] = [
            html_writer::link(
                new moodle_url('/local/livesessions/package_subs.php', ['id' => $r->packageid]),
                format_string($r->packagename)
            ),
            number_format((float)$r->price, 2) . ' ' . $r->currency,
            (int)$r->pkg_credits,
            (int)$r->total_subs,
            (int)$r->active_subs,
            (int)$r->credits_granted,
            (int)$r->credits_consumed,
            number_format((float)$r->total_revenue, 2) . ' ' . $r->currency,
        ];
    }

    echo html_writer::table($table);
}

echo html_writer::link(
    new moodle_url('/local/livesessions/dashboard.php'),
    '← ' . get_string('backtodashboard', 'local_livesessions'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();
