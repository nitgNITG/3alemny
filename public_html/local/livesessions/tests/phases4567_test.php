<?php
/**
 * Tests for Phases 4–7:
 *   Phase 4 — Recording Access Rules (recording_access_manager)
 *   Phase 5 — Mobile API structure (mobile_api)
 *   Phase 6 — Device Manager (device_manager)
 *   Phase 7 — Dashboard / Reports (structural smoke tests)
 *
 * Run:
 *   php local/livesessions/tests/phases4567_test.php
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    $moodle_root = dirname(__FILE__, 4);
    define('MOODLE_INTERNAL', true);
    define('CLI_SCRIPT', true);
    require_once($moodle_root . '/config.php');
}

// ---- Autoload classes under test ----
require_once(dirname(__DIR__) . '/classes/recording_access_manager.php');
require_once(dirname(__DIR__) . '/classes/device_manager.php');
require_once(dirname(__DIR__) . '/classes/package_manager.php');
require_once(dirname(__DIR__) . '/classes/recording_manager.php');
require_once(dirname(__DIR__) . '/classes/external/mobile_api.php');

// =========================================================================
// Minimal test harness
// =========================================================================

$pass   = 0;
$fail   = 0;
$errors = [];

function ok(bool $cond, string $id, string $desc = ''): void {
    global $pass, $fail, $errors;
    if ($cond) {
        echo "  PASS  {$id}" . ($desc ? " — {$desc}" : '') . "\n";
        $pass++;
    } else {
        echo "  FAIL  {$id}" . ($desc ? " — {$desc}" : '') . "\n";
        $fail++;
        $errors[] = $id;
    }
}

function expect_exception(callable $fn, string $id, string $desc = ''): void {
    global $pass, $fail, $errors;
    $thrown = false;
    try {
        $fn();
    } catch (\Throwable $e) {
        $thrown = true;
    }
    if ($thrown) {
        echo "  PASS  {$id}" . ($desc ? " — {$desc}" : '') . "\n";
        $pass++;
    } else {
        echo "  FAIL  {$id} — expected exception not thrown" . ($desc ? " ({$desc})" : '') . "\n";
        $fail++;
        $errors[] = $id;
    }
}

// =========================================================================
// DB helpers: ensure test tables exist
// =========================================================================

function ensure_device_sessions_table(): void {
    global $DB;
    $dbman = $DB->get_manager();
    $table = new xmldb_table('livesessions_device_sessions');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sessionid',    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('device_token', XMLDB_TYPE_CHAR,    '128', null, XMLDB_NOTNULL, null, '');
        $table->add_field('joined_at',    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('left_at',      XMLDB_TYPE_INTEGER, '10',  null, null,          null, null);
        $table->add_field('ip',           XMLDB_TYPE_CHAR,    '45',  null, null,          null, null);
        $table->add_field('user_agent',   XMLDB_TYPE_CHAR,    '255', null, null,          null, null);
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
        echo "  [setup] Created livesessions_device_sessions table.\n";
    }
}

// ---- Seed helpers ----
function seed_session(int $courseid = 1): int {
    global $DB;
    return (int)$DB->insert_record('livesessions_sessions', (object)[
        'courseid'    => $courseid,
        'teacherid'   => 2,
        'title'       => 'Test session ' . uniqid(),
        'status'      => 'completed',
        'provider'    => 'zoom',
        'starttime'   => time() - 3600,
        'endtime'     => time() - 1800,
        'timecreated' => time(),
        'timemodified'=> time(),
    ]);
}

function seed_attendance(int $sessionid, int $userid, string $status, int $rec_access = 0): int {
    global $DB;
    $existing = $DB->get_record('livesessions_attendance', ['sessionid' => $sessionid, 'userid' => $userid]);
    if ($existing) {
        $existing->attendance_status = $status;
        $existing->recording_access  = $rec_access;
        $existing->timemodified      = time();
        $DB->update_record('livesessions_attendance', $existing);
        return (int)$existing->id;
    }
    return (int)$DB->insert_record('livesessions_attendance', (object)[
        'sessionid'          => $sessionid,
        'userid'             => $userid,
        'join_time'          => time() - 3600,
        'leave_time'         => time() - 1800,
        'duration_attended'  => 1800,
        'attendance_percent' => ($status === 'attended') ? 80.0 : 30.0,
        'attendance_status'  => $status,
        'recording_access'   => $rec_access,
        'join_count'         => 1,
        'timecreated'        => time(),
        'timemodified'       => time(),
    ]);
}

function seed_recording(int $sessionid, string $status = 'ready'): int {
    global $DB;
    return (int)$DB->insert_record('livesessions_recordings', (object)[
        'sessionid'       => $sessionid,
        'source_provider' => 'zoom',
        'status'          => $status,
        'download_url'    => 'https://example.com/rec.mp4',
        'embed_url'       => 'https://iframe.mediadelivery.net/embed/999/abc',
        'video_url'       => 'https://example.com/rec.mp4',
        'external_id'     => 'test_guid_' . uniqid(),
        'source_provider' => 'bunny',
        'retry_count'     => 0,
        'timecreated'     => time(),
        'timemodified'    => time(),
    ]);
}

// =========================================================================
// Phase 4 — Recording Access Manager
// =========================================================================

echo "\n========== Phase 4: Recording Access Manager ==========\n\n";

ensure_device_sessions_table(); // also called later for phase 6

$sid4 = seed_session();
$uid4 = 10001; // fake student ID

// TC-4.01: Student with no attendance record cannot access recording.
ok(
    !\local_livesessions\recording_access_manager::can_access_recording($sid4, $uid4),
    'TC-4.01', 'No attendance → no access'
);

// TC-4.02: Seed 'attended' student without recording_access = 0 → still no access.
seed_attendance($sid4, $uid4, 'attended', 0);
ok(
    !\local_livesessions\recording_access_manager::can_access_recording($sid4, $uid4),
    'TC-4.02', 'attended but recording_access=0 → no access'
);

// TC-4.03: grant_attendance_access sets recording_access for all attended.
$granted = \local_livesessions\recording_access_manager::grant_attendance_access($sid4);
ok($granted === 1, 'TC-4.03', "grant_attendance_access returned {$granted}, expected 1");

// TC-4.04: After batch grant, student can now access.
ok(
    \local_livesessions\recording_access_manager::can_access_recording($sid4, $uid4),
    'TC-4.04', 'After grant_attendance_access → can access'
);

// TC-4.05: grant_attendance_access is idempotent (won't double-count).
$granted2 = \local_livesessions\recording_access_manager::grant_attendance_access($sid4);
ok($granted2 === 0, 'TC-4.05', "Second call to grant_attendance_access returned {$granted2}, expected 0");

// TC-4.06: revoke_access works.
\local_livesessions\recording_access_manager::revoke_access($sid4, $uid4);
ok(
    !\local_livesessions\recording_access_manager::can_access_recording($sid4, $uid4),
    'TC-4.06', 'After revoke_access → no access'
);

// TC-4.07: grant_access re-grants.
\local_livesessions\recording_access_manager::grant_access($sid4, $uid4, 1);
ok(
    \local_livesessions\recording_access_manager::can_access_recording($sid4, $uid4),
    'TC-4.07', 'After grant_access → can access again'
);

// TC-4.08: grant_access for user with no attendance record creates one.
$uid_new = 10002;
$result = \local_livesessions\recording_access_manager::grant_access($sid4, $uid_new, 1);
ok($result === true, 'TC-4.08', 'grant_access returns true for new student');
ok(
    \local_livesessions\recording_access_manager::can_access_recording($sid4, $uid_new),
    'TC-4.09', 'New student granted access via grant_access'
);

// TC-4.10: apply_access_rules_for_session (attendance mode) grants all attended.
set_config('credit_mode', 'attendance', 'local_livesessions');
$sid4b  = seed_session();
$uid4b1 = 10003;
$uid4b2 = 10004;
seed_attendance($sid4b, $uid4b1, 'attended', 0);
seed_attendance($sid4b, $uid4b2, 'absent', 0);
$cnt = \local_livesessions\recording_access_manager::apply_access_rules_for_session($sid4b);
ok($cnt === 1, 'TC-4.10', "apply_access_rules_for_session granted {$cnt} (expected 1 attended)");

// TC-4.11: credit_mode='credit' + grant_credit_access fails when no recording ready.
set_config('credit_mode', 'credit', 'local_livesessions');
$sid4c  = seed_session();
$uid4c  = 10005;
expect_exception(
    fn() => \local_livesessions\recording_access_manager::grant_credit_access($sid4c, $uid4c),
    'TC-4.11', 'grant_credit_access throws when no recording ready'
);

// TC-4.12: grant_credit_access with ready recording but no credits → exception.
seed_recording($sid4c, 'ready');
expect_exception(
    fn() => \local_livesessions\recording_access_manager::grant_credit_access($sid4c, $uid4c),
    'TC-4.12', 'grant_credit_access throws when student has no credits'
);

// Restore attendance mode.
set_config('credit_mode', 'attendance', 'local_livesessions');

// =========================================================================
// Phase 5 — Mobile API structure
// =========================================================================

echo "\n========== Phase 5: Mobile API Structure ==========\n\n";

// TC-5.01: get_sessions_parameters returns external_function_parameters.
$params = \local_livesessions\external\mobile_api::get_sessions_parameters();
ok($params instanceof external_function_parameters, 'TC-5.01', 'get_sessions_parameters()');

// TC-5.02: get_sessions_returns returns external_single_structure.
$ret = \local_livesessions\external\mobile_api::get_sessions_returns();
ok($ret instanceof external_single_structure, 'TC-5.02', 'get_sessions_returns()');

// TC-5.03: get_session_parameters returns external_function_parameters.
$params3 = \local_livesessions\external\mobile_api::get_session_parameters();
ok($params3 instanceof external_function_parameters, 'TC-5.03', 'get_session_parameters()');

// TC-5.04: join_session_parameters returns external_function_parameters.
$params4 = \local_livesessions\external\mobile_api::join_session_parameters();
ok($params4 instanceof external_function_parameters, 'TC-5.04', 'join_session_parameters()');

// TC-5.05: leave_session_parameters returns external_function_parameters.
$params5 = \local_livesessions\external\mobile_api::leave_session_parameters();
ok($params5 instanceof external_function_parameters, 'TC-5.05', 'leave_session_parameters()');

// TC-5.06: get_recording_token_parameters returns external_function_parameters.
$params6 = \local_livesessions\external\mobile_api::get_recording_token_parameters();
ok($params6 instanceof external_function_parameters, 'TC-5.06', 'get_recording_token_parameters()');

// TC-5.07: get_my_packages_parameters returns external_function_parameters.
$params7 = \local_livesessions\external\mobile_api::get_my_packages_parameters();
ok($params7 instanceof external_function_parameters, 'TC-5.07', 'get_my_packages_parameters()');

// TC-5.08: get_my_packages_returns returns external_single_structure.
$ret8 = \local_livesessions\external\mobile_api::get_my_packages_returns();
ok($ret8 instanceof external_single_structure, 'TC-5.08', 'get_my_packages_returns()');

// TC-5.09: All 6 mobile functions registered in services.php.
$services_file = __DIR__ . '/../db/services.php';
$services_content = file_get_contents($services_file);
$mobile_fns = [
    'local_livesessions_mobile_get_sessions',
    'local_livesessions_mobile_get_session',
    'local_livesessions_mobile_join_session',
    'local_livesessions_mobile_leave_session',
    'local_livesessions_mobile_get_recording_token',
    'local_livesessions_mobile_get_my_packages',
];
$all_registered = true;
foreach ($mobile_fns as $fn) {
    if (strpos($services_content, $fn) === false) {
        $all_registered = false;
        echo "    Missing from services.php: {$fn}\n";
    }
}
ok($all_registered, 'TC-5.09', 'All 6 mobile functions registered in services.php');

// TC-5.10: mobile_api class extends external_api.
ok(
    is_subclass_of(\local_livesessions\external\mobile_api::class, external_api::class),
    'TC-5.10', 'mobile_api extends external_api'
);

// =========================================================================
// Phase 6 — Device Manager
// =========================================================================

echo "\n========== Phase 6: Device Manager ==========\n\n";

$sid6 = seed_session();
$uid6 = 20001;
set_config('max_devices_per_session', 2, 'local_livesessions');

// TC-6.01: Register first device → returns a row with id > 0.
$dev1 = \local_livesessions\device_manager::register_device($sid6, $uid6, 'device_token_A', '1.2.3.4', 'TestUA');
ok($dev1->id > 0, 'TC-6.01', "First device registered, id={$dev1->id}");

// TC-6.02: count_active_devices = 1 after one registration.
$count = \local_livesessions\device_manager::count_active_devices($sid6, $uid6);
ok($count === 1, 'TC-6.02', "Active devices after 1 registration: {$count}");

// TC-6.03: Register same token again → idempotent (still 1 active slot).
\local_livesessions\device_manager::register_device($sid6, $uid6, 'device_token_A');
$count2 = \local_livesessions\device_manager::count_active_devices($sid6, $uid6);
ok($count2 === 1, 'TC-6.03', "Idempotent registration: still {$count2} active device");

// TC-6.04: Register second device → succeeds, count = 2.
\local_livesessions\device_manager::register_device($sid6, $uid6, 'device_token_B');
$count3 = \local_livesessions\device_manager::count_active_devices($sid6, $uid6);
ok($count3 === 2, 'TC-6.04', "Two devices registered: count={$count3}");

// TC-6.05: Third device → moodle_exception (limit = 2).
expect_exception(
    fn() => \local_livesessions\device_manager::register_device($sid6, $uid6, 'device_token_C'),
    'TC-6.05', 'Third device throws moodle_exception when limit=2'
);

// TC-6.06: release_device reduces count to 1.
\local_livesessions\device_manager::release_device($sid6, $uid6, 'device_token_A');
$count4 = \local_livesessions\device_manager::count_active_devices($sid6, $uid6);
ok($count4 === 1, 'TC-6.06', "After release, active devices = {$count4}");

// TC-6.07: After release, can register device_token_C.
$dev3 = \local_livesessions\device_manager::register_device($sid6, $uid6, 'device_token_C');
ok($dev3->id > 0, 'TC-6.07', 'Can register new device after freeing a slot');

// TC-6.08: get_active_devices returns correct count.
$active = \local_livesessions\device_manager::get_active_devices($sid6, $uid6);
ok(count($active) === 2, 'TC-6.08', 'get_active_devices returns 2 rows');

// TC-6.09: release_all_for_user closes all slots.
$released = \local_livesessions\device_manager::release_all_for_user($sid6, $uid6);
ok($released === 2, 'TC-6.09', "release_all_for_user released {$released} slots");
$count5 = \local_livesessions\device_manager::count_active_devices($sid6, $uid6);
ok($count5 === 0, 'TC-6.10', "Active devices after release_all_for_user: {$count5}");

// TC-6.11: cleanup_stale marks old open slots as closed.
// Manually insert a stale open slot.
global $DB;
$stale_id = (int)$DB->insert_record('livesessions_device_sessions', (object)[
    'sessionid'    => $sid6,
    'userid'       => $uid6 + 1,
    'device_token' => 'stale_device',
    'joined_at'    => time() - 90000, // older than 24 h
    'left_at'      => null,
    'ip'           => '5.5.5.5',
    'user_agent'   => 'StaleUA',
    'timecreated'  => time() - 90000,
    'timemodified' => time() - 90000,
]);
$cleaned = \local_livesessions\device_manager::cleanup_stale();
ok($cleaned >= 1, 'TC-6.11', "cleanup_stale closed {$cleaned} stale slot(s)");
$stale_row = $DB->get_record('livesessions_device_sessions', ['id' => $stale_id]);
ok($stale_row->left_at !== null, 'TC-6.12', 'Stale slot now has left_at set');

// TC-6.13: Watermark annotations — mode=off → empty array.
set_config('watermark_mode', 'off', 'local_livesessions');
$annots = \local_livesessions\device_manager::build_watermark_annotations(2);
ok($annots === [], 'TC-6.13', 'watermark_mode=off returns empty annotations');

// TC-6.14: Watermark annotations — mode=name → non-empty array.
set_config('watermark_mode', 'name', 'local_livesessions');
$annots2 = \local_livesessions\device_manager::build_watermark_annotations(2); // userid=2 (admin)
ok(is_array($annots2), 'TC-6.14', 'watermark_mode=name returns array');

// Restore defaults.
set_config('watermark_mode', 'off', 'local_livesessions');
set_config('max_devices_per_session', 2, 'local_livesessions');

// =========================================================================
// Phase 7 — Dashboard / Reports (structural / file smoke tests)
// =========================================================================

echo "\n========== Phase 7: Dashboard & Reports (structural) ==========\n\n";

$base = __DIR__ . '/..';

// TC-7.01: dashboard.php exists.
ok(file_exists("{$base}/dashboard.php"), 'TC-7.01', 'dashboard.php exists');

// TC-7.02: reports.php exists.
ok(file_exists("{$base}/reports.php"), 'TC-7.02', 'reports.php exists');

// TC-7.03: dashboard.php requires manageSessions capability (check source).
$dash = file_get_contents("{$base}/dashboard.php");
ok(str_contains($dash, 'manageSessions'), 'TC-7.03', 'dashboard.php checks manageSessions');

// TC-7.04: reports.php requires manageSessions capability.
$rpt = file_get_contents("{$base}/reports.php");
ok(str_contains($rpt, 'manageSessions'), 'TC-7.04', 'reports.php checks manageSessions');

// TC-7.05: reports.php supports CSV export.
ok(str_contains($rpt, 'Content-Type: text/csv'), 'TC-7.05', 'reports.php outputs CSV header');

// TC-7.06: dashboard.php has KPI cards for sessions, live, students, attendance, recordings.
$kpis_present =
    str_contains($dash, 'totalsessions') &&
    str_contains($dash, 'livesessionscount') &&
    str_contains($dash, 'totalstudents') &&
    str_contains($dash, 'avgattendance') &&
    str_contains($dash, 'recordingsready');
ok($kpis_present, 'TC-7.06', 'dashboard.php references all 5 KPI strings');

// TC-7.07: dashboard.php links to reports.php and recordings.php.
ok(
    str_contains($dash, 'reports.php') && str_contains($dash, 'recordings.php'),
    'TC-7.07', 'dashboard.php links to reports and recording queue'
);

// TC-7.08: reports.php has per-package aggregate SQL.
ok(str_contains($rpt, 'livesessions_packages'), 'TC-7.08', 'reports.php queries livesessions_packages');

// TC-7.09: Device manager class file exists.
ok(file_exists("{$base}/classes/device_manager.php"), 'TC-7.09', 'device_manager.php exists');

// TC-7.10: Lang file has all Phase 4 strings.
$lang = file_get_contents("{$base}/lang/en/local_livesessions.php");
$p4_strings = ['unlockrecording', 'unlockrecordingconfirm', 'recordingaccessgranted',
                'creditaccessnotenabled', 'creditcost1'];
$p4_ok = true;
foreach ($p4_strings as $s) {
    if (!str_contains($lang, $s)) { $p4_ok = false; echo "    Missing lang string: {$s}\n"; }
}
ok($p4_ok, 'TC-7.10', 'Lang file has all Phase 4 strings');

// TC-7.11: Lang file has Phase 6 device-limit strings.
ok(
    str_contains($lang, 'devicelimitexceeded') && str_contains($lang, 'max_devices_per_session'),
    'TC-7.11', 'Lang file has Phase 6 device limit strings'
);

// TC-7.12: Lang file has Phase 7 dashboard strings.
ok(
    str_contains($lang, 'dashboard') && str_contains($lang, 'reports') && str_contains($lang, 'revenuebypackage'),
    'TC-7.12', 'Lang file has Phase 7 dashboard / report strings'
);

// TC-7.13: upgrade.php contains the 2024060101 step.
$upgrade = file_get_contents("{$base}/db/upgrade.php");
ok(str_contains($upgrade, '2024060101'), 'TC-7.13', 'upgrade.php has 2024060101 step');

// TC-7.14: upgrade.php creates livesessions_device_sessions table.
ok(str_contains($upgrade, 'livesessions_device_sessions'), 'TC-7.14', 'upgrade.php defines device_sessions table');

// TC-7.15: settings.php has watermark_mode and max_devices_per_session settings.
$settings = file_get_contents("{$base}/settings.php");
ok(
    str_contains($settings, 'watermark_mode') && str_contains($settings, 'max_devices_per_session'),
    'TC-7.15', 'settings.php has Phase 6 config settings'
);

// =========================================================================
// Summary
// =========================================================================

echo "\n";
echo str_repeat('=', 55) . "\n";
echo "  Phases 4–7 Results: {$pass} passed, {$fail} failed\n";
echo str_repeat('=', 55) . "\n";

if ($errors) {
    echo "\nFailed tests:\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
    exit(1);
}

exit(0);
