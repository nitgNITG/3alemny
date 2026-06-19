<?php
/**
 * Phase 3 — Package System test suite (Features 3.1, 3.2, 3.3).
 *
 * Tests:
 *   ── Feature 3.1 — Package CRUD ──────────────────────────────────
 *   TC-3.1.01  create_package returns integer id
 *   TC-3.1.02  created package has correct fields
 *   TC-3.1.03  create_package defaults status to active
 *   TC-3.1.04  create_package throws on empty name
 *   TC-3.1.05  create_package throws on sessions_count < 1
 *   TC-3.1.06  update_package changes fields
 *   TC-3.1.07  archive_package sets status = archived
 *   TC-3.1.08  archive_package throws when active subs exist
 *   TC-3.1.09  list_packages filters by status
 *   TC-3.1.10  list_packages returns all when status empty
 *
 *   ── Feature 3.2 — Student Subscription ──────────────────────────
 *   TC-3.2.01  assign_package creates subscription with correct counts
 *   TC-3.2.02  assign_package calculates expiry correctly
 *   TC-3.2.03  assign_package no-expiry when validity_days = 0
 *   TC-3.2.04  assign_package throws on inactive package
 *   TC-3.2.05  get_student_subscriptions returns only valid subs
 *   TC-3.2.06  get_remaining_credits sums across multiple subs
 *   TC-3.2.07  cancel_subscription sets status = cancelled
 *   TC-3.2.08  cancel_subscription logs to credit_log
 *   TC-3.2.09  expire_stale_subscriptions marks overdue subs
 *   TC-3.2.10  expired sub not returned by get_student_subscriptions
 *
 *   ── Feature 3.3 — Session Consumption ───────────────────────────
 *   TC-3.3.01  consume_credit deducts one credit
 *   TC-3.3.02  consume_credit is idempotent (no double deduction)
 *   TC-3.3.03  consume_credit sets status = exhausted at zero
 *   TC-3.3.04  consume_credit throws when no credits available
 *   TC-3.3.05  consume_credit logs to credit_log with action=consumed
 *   TC-3.3.06  refund_credit adds one credit back
 *   TC-3.3.07  refund_credit is idempotent (no double refund)
 *   TC-3.3.08  refund_credit returns false when never consumed
 *   TC-3.3.09  refund_session_credits refunds all students for a session
 *   TC-3.3.10  has_credits returns true/false correctly
 *   TC-3.3.11  consume_credit prefers soonest-expiring sub
 *
 *   ── Structural checks ────────────────────────────────────────────
 *   TC-3.S.01  package_api::consume_credit delegates to package_manager
 *   TC-3.S.02  package_api::get_my_credits exists
 *   TC-3.S.03  package_api::assign_package exists
 *   TC-3.S.04  expire_subscriptions task class exists
 *   TC-3.S.05  upgrade.php contains version 2024030301
 *   TC-3.S.06  upgrade.php creates credit_log table
 *   TC-3.S.07  services.php registers get_my_credits
 *   TC-3.S.08  lang strings present for package system
 *   TC-3.S.09  version.php bumped to 2024070101
 *   TC-3.S.10  credit_log table exists in DB
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

require_once(dirname(__DIR__) . '/classes/package_manager.php');
require_once(dirname(__DIR__) . '/classes/external/package_api.php');
require_once(dirname(__DIR__) . '/classes/task/expire_subscriptions.php');

$tests  = [];
$passed = 0;
$failed = 0;

// ---- Helpers -------------------------------------------------------

function t3(string $name, callable $fn): void {
    global $tests;
    $tests[] = ['name' => $name, 'fn' => $fn];
}

function run3(array $tests, int &$passed, int &$failed): void {
    foreach ($tests as $t) {
        try {
            ($t['fn'])();
            echo "  PASS  {$t['name']}\n";
            $passed++;
        } catch (\Throwable $e) {
            echo "  FAIL  {$t['name']}: {$e->getMessage()}\n";
            $failed++;
        }
    }
}

function a3(bool $cond, string $msg = ''): void {
    if (!$cond) throw new \RuntimeException("Assertion failed" . ($msg ? ": $msg" : ''));
}

function eq3($a, $b, string $msg = ''): void {
    if ($a !== $b) throw new \RuntimeException(
        ($msg ? "$msg — " : '') . "Expected " . var_export($b, true) . " got " . var_export($a, true)
    );
}

/**
 * Create a test package and return its id. Cleans up automatically via teardown registry.
 */
function make_test_pkg(string $name = 'Test Pkg', int $count = 5, int $validity = 30): int {
    $id = \local_livesessions\package_manager::create_package($name, '', $count, 0, 'USD', $validity);
    register_cleanup_pkg($id);
    return $id;
}

$cleanup_pkgs = [];
$cleanup_subs = [];
$cleanup_logs = [];

function register_cleanup_pkg(int $id): void { global $cleanup_pkgs; $cleanup_pkgs[] = $id; }
function register_cleanup_sub(int $id): void { global $cleanup_subs; $cleanup_subs[] = $id; }

function cleanup_all(): void {
    global $DB, $cleanup_pkgs, $cleanup_subs;
    foreach ($cleanup_subs as $id) {
        $DB->delete_records('livesessions_credit_log',       ['sub_id'    => $id]);
        $DB->delete_records('livesessions_student_packages', ['id'        => $id]);
    }
    foreach ($cleanup_pkgs as $id) {
        $DB->delete_records('livesessions_student_packages', ['packageid' => $id]);
        $DB->delete_records('livesessions_packages',         ['id'        => $id]);
    }
}

// ================================================================
// Feature 3.1 — Package CRUD
// ================================================================

t3('TC-3.1.01 create_package returns integer id', function () {
    $id = make_test_pkg('PKG-3.1.01');
    a3(is_int($id) && $id > 0, "Expected positive int, got: $id");
});

t3('TC-3.1.02 created package has correct fields', function () {
    $id  = make_test_pkg('PKG-3.1.02', 10, 60);
    $pkg = \local_livesessions\package_manager::get_package($id);
    eq3($pkg->name,           'PKG-3.1.02', 'name');
    eq3((int)$pkg->sessions_count, 10,      'sessions_count');
    eq3((int)$pkg->validity_days,  60,      'validity_days');
    eq3($pkg->currency,       'USD',         'currency');
});

t3('TC-3.1.03 create_package defaults status to active', function () {
    $id  = make_test_pkg('PKG-3.1.03');
    $pkg = \local_livesessions\package_manager::get_package($id);
    eq3($pkg->status, \local_livesessions\package_manager::PKG_ACTIVE, 'status should be active');
});

t3('TC-3.1.04 create_package throws on empty name', function () {
    try {
        \local_livesessions\package_manager::create_package('', '', 5);
        throw new \LogicException('Expected exception not thrown');
    } catch (\LogicException $e) { throw $e; }
    catch (\Throwable $e) { a3(true); }
});

t3('TC-3.1.05 create_package throws on sessions_count < 1', function () {
    try {
        \local_livesessions\package_manager::create_package('Test', '', 0);
        throw new \LogicException('Expected exception not thrown');
    } catch (\LogicException $e) { throw $e; }
    catch (\Throwable $e) { a3(true); }
});

t3('TC-3.1.06 update_package changes fields', function () {
    $id = make_test_pkg('PKG-3.1.06', 5);
    \local_livesessions\package_manager::update_package($id, ['name' => 'Updated Name', 'sessions_count' => 20]);
    $pkg = \local_livesessions\package_manager::get_package($id);
    eq3($pkg->name, 'Updated Name', 'name after update');
    eq3((int)$pkg->sessions_count, 20, 'sessions_count after update');
});

t3('TC-3.1.07 archive_package sets status = archived', function () {
    $id = make_test_pkg('PKG-3.1.07');
    \local_livesessions\package_manager::archive_package($id);
    $pkg = \local_livesessions\package_manager::get_package($id);
    eq3($pkg->status, \local_livesessions\package_manager::PKG_ARCHIVED, 'status after archive');
});

t3('TC-3.1.08 archive_package throws when active subs exist', function () {
    global $DB;
    $id = make_test_pkg('PKG-3.1.08');
    // Insert a fake active sub.
    $sub_id = $DB->insert_record('livesessions_student_packages', (object)[
        'userid' => 2, 'packageid' => $id,
        'total_sessions' => 5, 'used_sessions' => 0, 'remaining_sessions' => 5,
        'status' => 'active', 'timecreated' => time(), 'timemodified' => time(),
    ]);
    register_cleanup_sub($sub_id);
    try {
        \local_livesessions\package_manager::archive_package($id);
        throw new \LogicException('Expected exception not thrown');
    } catch (\LogicException $e) { throw $e; }
    catch (\Throwable $e) { a3(true, 'Exception correctly thrown: ' . $e->getMessage()); }
});

t3('TC-3.1.09 list_packages filters by status', function () {
    $id1 = make_test_pkg('PKG-3.1.09a');
    $id2 = make_test_pkg('PKG-3.1.09b');
    \local_livesessions\package_manager::update_package($id2, ['status' => \local_livesessions\package_manager::PKG_INACTIVE]);
    $active = \local_livesessions\package_manager::list_packages('active');
    $ids    = array_column($active, 'id');
    a3(in_array($id1, $ids),  "Active pkg should be in active list");
    a3(!in_array($id2, $ids), "Inactive pkg should not be in active list");
});

t3('TC-3.1.10 list_packages returns all when status empty', function () {
    $id1 = make_test_pkg('PKG-3.1.10a');
    $id2 = make_test_pkg('PKG-3.1.10b');
    \local_livesessions\package_manager::update_package($id2, ['status' => \local_livesessions\package_manager::PKG_INACTIVE]);
    $all = \local_livesessions\package_manager::list_packages('');
    $ids = array_column($all, 'id');
    a3(in_array($id1, $ids) && in_array($id2, $ids), "Both pkgs should appear in unfiltered list");
});

// ================================================================
// Feature 3.2 — Student Subscription
// ================================================================

t3('TC-3.2.01 assign_package creates subscription with correct counts', function () {
    global $DB;
    $pkg_id = make_test_pkg('PKG-3.2.01', 8, 30);
    $sub_id = \local_livesessions\package_manager::assign_package(2, $pkg_id, 1);
    register_cleanup_sub($sub_id);
    $sub = $DB->get_record('livesessions_student_packages', ['id' => $sub_id]);
    eq3((int)$sub->total_sessions,     8, 'total_sessions');
    eq3((int)$sub->remaining_sessions, 8, 'remaining_sessions');
    eq3((int)$sub->used_sessions,      0, 'used_sessions');
    eq3($sub->status, \local_livesessions\package_manager::SUB_ACTIVE, 'status');
});

t3('TC-3.2.02 assign_package calculates expiry correctly', function () {
    global $DB;
    $pkg_id = make_test_pkg('PKG-3.2.02', 5, 7); // 7-day validity
    $before = time();
    $sub_id = \local_livesessions\package_manager::assign_package(2, $pkg_id, 1);
    register_cleanup_sub($sub_id);
    $sub    = $DB->get_record('livesessions_student_packages', ['id' => $sub_id]);
    $after  = time();
    a3($sub->expiry_date >= $before + 7 * 86400, 'expiry should be ~7 days from now');
    a3($sub->expiry_date <= $after  + 7 * 86400, 'expiry should not be more than 7 days');
});

t3('TC-3.2.03 assign_package no-expiry when validity_days = 0', function () {
    global $DB;
    $pkg_id = \local_livesessions\package_manager::create_package('PKG-3.2.03-noexp', '', 5, 0, 'USD', 0);
    register_cleanup_pkg($pkg_id);
    $sub_id = \local_livesessions\package_manager::assign_package(2, $pkg_id, 1);
    register_cleanup_sub($sub_id);
    $sub    = $DB->get_record('livesessions_student_packages', ['id' => $sub_id]);
    a3($sub->expiry_date === null || $sub->expiry_date == 0, "expiry_date should be null/0, got: {$sub->expiry_date}");
});

t3('TC-3.2.04 assign_package throws on inactive package', function () {
    $pkg_id = make_test_pkg('PKG-3.2.04');
    \local_livesessions\package_manager::update_package($pkg_id, ['status' => \local_livesessions\package_manager::PKG_INACTIVE]);
    try {
        \local_livesessions\package_manager::assign_package(2, $pkg_id, 1);
        throw new \LogicException('Expected exception not thrown');
    } catch (\LogicException $e) { throw $e; }
    catch (\Throwable $e) { a3(true); }
});

t3('TC-3.2.05 get_student_subscriptions returns only valid subs', function () {
    global $DB;
    $pkg_id = make_test_pkg('PKG-3.2.05', 5, 30);
    $sub_id = \local_livesessions\package_manager::assign_package(2, $pkg_id, 1);
    register_cleanup_sub($sub_id);
    $subs = \local_livesessions\package_manager::get_student_subscriptions(2, true);
    $ids  = array_column($subs, 'id');
    a3(in_array($sub_id, $ids), "Active sub should be in results");
    // Cancel and verify removal.
    \local_livesessions\package_manager::cancel_subscription($sub_id, 1);
    $subs2 = \local_livesessions\package_manager::get_student_subscriptions(2, true);
    $ids2  = array_column($subs2, 'id');
    a3(!in_array($sub_id, $ids2), "Cancelled sub should not be in valid_only results");
});

t3('TC-3.2.06 get_remaining_credits sums across multiple subs', function () {
    global $DB;
    // Use userid=9999 to avoid conflicts with real users.
    $uid    = 9999;
    $pkg1   = make_test_pkg('PKG-3.2.06a', 3, 30);
    $pkg2   = make_test_pkg('PKG-3.2.06b', 4, 30);
    $sub1   = $DB->insert_record('livesessions_student_packages', (object)[
        'userid' => $uid, 'packageid' => $pkg1,
        'total_sessions' => 3, 'used_sessions' => 0, 'remaining_sessions' => 3,
        'expiry_date' => time() + 86400 * 30,
        'status' => 'active', 'timecreated' => time(), 'timemodified' => time(),
    ]);
    $sub2   = $DB->insert_record('livesessions_student_packages', (object)[
        'userid' => $uid, 'packageid' => $pkg2,
        'total_sessions' => 4, 'used_sessions' => 0, 'remaining_sessions' => 4,
        'expiry_date' => time() + 86400 * 30,
        'status' => 'active', 'timecreated' => time(), 'timemodified' => time(),
    ]);
    register_cleanup_sub($sub1);
    register_cleanup_sub($sub2);
    $remaining = \local_livesessions\package_manager::get_remaining_credits($uid);
    eq3($remaining, 7, "Expected 3+4=7 credits, got: $remaining");
});

t3('TC-3.2.07 cancel_subscription sets status = cancelled', function () {
    global $DB;
    $pkg_id = make_test_pkg('PKG-3.2.07', 5, 30);
    $sub_id = \local_livesessions\package_manager::assign_package(2, $pkg_id, 1);
    register_cleanup_sub($sub_id);
    \local_livesessions\package_manager::cancel_subscription($sub_id, 1);
    $sub = $DB->get_record('livesessions_student_packages', ['id' => $sub_id]);
    eq3($sub->status, \local_livesessions\package_manager::SUB_CANCELLED, 'status after cancel');
});

t3('TC-3.2.08 cancel_subscription logs to credit_log', function () {
    global $DB;
    $pkg_id = make_test_pkg('PKG-3.2.08', 5, 30);
    $sub_id = \local_livesessions\package_manager::assign_package(2, $pkg_id, 1);
    register_cleanup_sub($sub_id);
    \local_livesessions\package_manager::cancel_subscription($sub_id, 1);
    $logs = $DB->get_records('livesessions_credit_log', ['sub_id' => $sub_id]);
    $actions = array_column($logs, 'action');
    a3(in_array('cancelled', $actions), "Should have a cancelled log entry");
});

t3('TC-3.2.09 expire_stale_subscriptions marks overdue subs', function () {
    global $DB;
    $pkg_id = make_test_pkg('PKG-3.2.09', 5, 1);
    $sub_id = $DB->insert_record('livesessions_student_packages', (object)[
        'userid' => 9998, 'packageid' => $pkg_id,
        'total_sessions' => 5, 'used_sessions' => 0, 'remaining_sessions' => 5,
        'expiry_date' => time() - 1, // already expired
        'status' => 'active',
        'timecreated' => time(), 'timemodified' => time(),
    ]);
    register_cleanup_sub($sub_id);
    $count = \local_livesessions\package_manager::expire_stale_subscriptions();
    a3($count >= 1, "Should have expired at least 1 subscription, got: $count");
    $sub = $DB->get_record('livesessions_student_packages', ['id' => $sub_id]);
    eq3($sub->status, \local_livesessions\package_manager::SUB_EXPIRED, 'status after expiry');
});

t3('TC-3.2.10 expired sub not returned by get_student_subscriptions', function () {
    global $DB;
    $pkg_id = make_test_pkg('PKG-3.2.10', 5, 1);
    $sub_id = $DB->insert_record('livesessions_student_packages', (object)[
        'userid' => 9997, 'packageid' => $pkg_id,
        'total_sessions' => 5, 'used_sessions' => 0, 'remaining_sessions' => 5,
        'expiry_date' => time() - 1,
        'status' => 'active',
        'timecreated' => time(), 'timemodified' => time(),
    ]);
    register_cleanup_sub($sub_id);
    $subs = \local_livesessions\package_manager::get_student_subscriptions(9997, true);
    $ids  = array_column($subs, 'id');
    a3(!in_array($sub_id, $ids), "Expired sub should not appear in valid-only list");
});

// ================================================================
// Feature 3.3 — Session Credit Consumption
// ================================================================

function make_sub_for_user(int $userid, int $sessions, int $validity_days = 30): int {
    global $DB;
    $pkg_id = \local_livesessions\package_manager::create_package("Sub-Pkg-{$userid}", '', $sessions, 0, 'USD', $validity_days);
    register_cleanup_pkg($pkg_id);
    $sub_id = \local_livesessions\package_manager::assign_package($userid, $pkg_id, 1);
    register_cleanup_sub($sub_id);
    return $sub_id;
}

t3('TC-3.3.01 consume_credit deducts one credit', function () {
    global $DB;
    $uid = 9996; $sess_id = 88801;
    make_sub_for_user($uid, 5);
    $before = \local_livesessions\package_manager::get_remaining_credits($uid);
    \local_livesessions\package_manager::consume_credit($uid, $sess_id);
    $after  = \local_livesessions\package_manager::get_remaining_credits($uid);
    eq3($after, $before - 1, "Remaining should decrease by 1: before=$before after=$after");
    // Cleanup credit log.
    $DB->delete_records('livesessions_credit_log', ['userid' => $uid, 'sessionid' => $sess_id]);
});

t3('TC-3.3.02 consume_credit is idempotent', function () {
    global $DB;
    $uid = 9995; $sess_id = 88802;
    make_sub_for_user($uid, 5);
    \local_livesessions\package_manager::consume_credit($uid, $sess_id);
    $after1 = \local_livesessions\package_manager::get_remaining_credits($uid);
    \local_livesessions\package_manager::consume_credit($uid, $sess_id); // second call
    $after2 = \local_livesessions\package_manager::get_remaining_credits($uid);
    eq3($after1, $after2, "Second consume_credit should be a no-op");
    $DB->delete_records('livesessions_credit_log', ['userid' => $uid, 'sessionid' => $sess_id]);
});

t3('TC-3.3.03 consume_credit sets status = exhausted at zero', function () {
    global $DB;
    $uid = 9994; $pkg_id = \local_livesessions\package_manager::create_package("Exhaust-Pkg", '', 1, 0, 'USD', 30);
    register_cleanup_pkg($pkg_id);
    $sub_id = \local_livesessions\package_manager::assign_package($uid, $pkg_id, 1);
    register_cleanup_sub($sub_id);
    \local_livesessions\package_manager::consume_credit($uid, 88803);
    $sub = $DB->get_record('livesessions_student_packages', ['id' => $sub_id]);
    eq3($sub->status, \local_livesessions\package_manager::SUB_EXHAUSTED, 'Should be exhausted after last credit');
    $DB->delete_records('livesessions_credit_log', ['userid' => $uid, 'sessionid' => 88803]);
});

t3('TC-3.3.04 consume_credit throws when no credits available', function () {
    $uid = 9993;
    // No subscription for this user.
    try {
        \local_livesessions\package_manager::consume_credit($uid, 88804);
        throw new \LogicException('Expected exception not thrown');
    } catch (\LogicException $e) { throw $e; }
    catch (\Throwable $e) { a3(true, 'Exception thrown: ' . $e->getMessage()); }
});

t3('TC-3.3.05 consume_credit logs action=consumed', function () {
    global $DB;
    $uid = 9992; $sess_id = 88805;
    make_sub_for_user($uid, 5);
    \local_livesessions\package_manager::consume_credit($uid, $sess_id);
    $log = $DB->get_record('livesessions_credit_log', ['userid' => $uid, 'sessionid' => $sess_id, 'action' => 'consumed']);
    a3($log !== false, 'Credit log entry with action=consumed not found');
    eq3($log->credits_before - $log->credits_after, 1, 'Delta should be 1');
    $DB->delete_records('livesessions_credit_log', ['userid' => $uid, 'sessionid' => $sess_id]);
});

t3('TC-3.3.06 refund_credit adds one credit back', function () {
    global $DB;
    $uid = 9991; $sess_id = 88806;
    make_sub_for_user($uid, 5);
    \local_livesessions\package_manager::consume_credit($uid, $sess_id);
    $after_consume = \local_livesessions\package_manager::get_remaining_credits($uid);
    \local_livesessions\package_manager::refund_credit($uid, $sess_id, 1);
    $after_refund  = \local_livesessions\package_manager::get_remaining_credits($uid);
    eq3($after_refund, $after_consume + 1, "Refund should add one credit");
    $DB->delete_records('livesessions_credit_log', ['userid' => $uid, 'sessionid' => $sess_id]);
});

t3('TC-3.3.07 refund_credit is idempotent', function () {
    global $DB;
    $uid = 9990; $sess_id = 88807;
    make_sub_for_user($uid, 5);
    \local_livesessions\package_manager::consume_credit($uid, $sess_id);
    \local_livesessions\package_manager::refund_credit($uid, $sess_id, 1);
    $after1 = \local_livesessions\package_manager::get_remaining_credits($uid);
    $result2 = \local_livesessions\package_manager::refund_credit($uid, $sess_id, 1); // second call
    $after2 = \local_livesessions\package_manager::get_remaining_credits($uid);
    a3(!$result2,         "Second refund should return false");
    eq3($after1, $after2, "Second refund should not change balance");
    $DB->delete_records('livesessions_credit_log', ['userid' => $uid, 'sessionid' => $sess_id]);
});

t3('TC-3.3.08 refund_credit returns false when never consumed', function () {
    $uid = 9989;
    $result = \local_livesessions\package_manager::refund_credit($uid, 88808, 1);
    a3(!$result, "Should return false when no consume log exists");
});

t3('TC-3.3.09 refund_session_credits refunds all students', function () {
    global $DB;
    $sess_id = 88809;
    $uids = [9988, 9987, 9986];
    foreach ($uids as $uid) {
        make_sub_for_user($uid, 2);
        \local_livesessions\package_manager::consume_credit($uid, $sess_id);
    }
    $count = \local_livesessions\package_manager::refund_session_credits($sess_id, 1);
    eq3($count, 3, "Should refund 3 students, got: $count");
    foreach ($uids as $uid) {
        $DB->delete_records('livesessions_credit_log', ['userid' => $uid, 'sessionid' => $sess_id]);
    }
});

t3('TC-3.3.10 has_credits returns true/false correctly', function () {
    $uid_with    = 9985;
    $uid_without = 9984;
    make_sub_for_user($uid_with, 3);
    a3( \local_livesessions\package_manager::has_credits($uid_with),    "Should have credits");
    a3(!\local_livesessions\package_manager::has_credits($uid_without), "Should not have credits");
});

t3('TC-3.3.11 consume_credit prefers soonest-expiring sub', function () {
    global $DB;
    $uid = 9983; $sess_id = 88810;
    // Two subs: one expiring soon, one later.
    $pkg_soon  = \local_livesessions\package_manager::create_package("Soon",  '', 2, 0, 'USD', 1);
    $pkg_later = \local_livesessions\package_manager::create_package("Later", '', 2, 0, 'USD', 90);
    register_cleanup_pkg($pkg_soon);
    register_cleanup_pkg($pkg_later);

    // Manually insert with explicit expiry to avoid time() race.
    $sub_soon = $DB->insert_record('livesessions_student_packages', (object)[
        'userid' => $uid, 'packageid' => $pkg_soon,
        'total_sessions' => 2, 'used_sessions' => 0, 'remaining_sessions' => 2,
        'expiry_date' => time() + 86400,    // expires in 1 day
        'status' => 'active', 'timecreated' => time(), 'timemodified' => time(),
    ]);
    $sub_later = $DB->insert_record('livesessions_student_packages', (object)[
        'userid' => $uid, 'packageid' => $pkg_later,
        'total_sessions' => 2, 'used_sessions' => 0, 'remaining_sessions' => 2,
        'expiry_date' => time() + 86400 * 90, // expires in 90 days
        'status' => 'active', 'timecreated' => time(), 'timemodified' => time(),
    ]);
    register_cleanup_sub($sub_soon);
    register_cleanup_sub($sub_later);

    \local_livesessions\package_manager::consume_credit($uid, $sess_id);

    $soon_after  = $DB->get_field('livesessions_student_packages', 'remaining_sessions', ['id' => $sub_soon]);
    $later_after = $DB->get_field('livesessions_student_packages', 'remaining_sessions', ['id' => $sub_later]);
    eq3((int)$soon_after,  1, "Soonest-expiring sub should be decremented");
    eq3((int)$later_after, 2, "Later sub should be untouched");

    $DB->delete_records('livesessions_credit_log', ['userid' => $uid, 'sessionid' => $sess_id]);
});

// ================================================================
// Structural checks
// ================================================================

t3('TC-3.S.01 package_api::consume_credit delegates to package_manager', function () {
    $src = file_get_contents(dirname(__DIR__) . '/classes/external/package_api.php');
    a3(str_contains($src, 'package_manager::consume_credit'), 'package_api should delegate to package_manager');
});

t3('TC-3.S.02 package_api::get_my_credits exists', function () {
    a3(method_exists(\local_livesessions\external\package_api::class, 'get_my_credits'),
        'get_my_credits method not found');
});

t3('TC-3.S.03 package_api::assign_package exists', function () {
    a3(method_exists(\local_livesessions\external\package_api::class, 'assign_package'),
        'assign_package method not found');
});

t3('TC-3.S.04 expire_subscriptions task class exists', function () {
    a3(class_exists(\local_livesessions\task\expire_subscriptions::class),
        'expire_subscriptions task not found');
    a3(method_exists(\local_livesessions\task\expire_subscriptions::class, 'execute'),
        'execute method not found');
});

t3('TC-3.S.05 upgrade.php contains version 2024030301', function () {
    $src = file_get_contents(dirname(__DIR__) . '/db/upgrade.php');
    a3(str_contains($src, '2024030301'), 'upgrade.php should contain 2024030301');
});

t3('TC-3.S.06 upgrade.php creates credit_log table', function () {
    $src = file_get_contents(dirname(__DIR__) . '/db/upgrade.php');
    a3(str_contains($src, 'livesessions_credit_log'), 'upgrade.php should create credit_log');
});

t3('TC-3.S.07 services.php registers get_my_credits', function () {
    $src = file_get_contents(dirname(__DIR__) . '/db/services.php');
    a3(str_contains($src, 'get_my_credits'), 'services.php should register get_my_credits');
    a3(str_contains($src, 'assign_package'), 'services.php should register assign_package');
});

t3('TC-3.S.08 lang strings present for package system', function () {
    $src = file_get_contents(dirname(__DIR__) . '/lang/en/local_livesessions.php');
    foreach (['packages', 'createpackage', 'packageassigned', 'creditlog', 'sessions_count'] as $key) {
        a3(str_contains($src, $key), "Missing lang string: $key");
    }
});

t3('TC-3.S.09 version.php bumped to 2024070101', function () {
    $src = file_get_contents(dirname(__DIR__) . '/version.php');
    a3(str_contains($src, '2024070101'), 'version.php should contain 2024070101');
});

t3('TC-3.S.10 credit_log table exists in DB', function () {
    global $DB;
    a3($DB->get_manager()->table_exists('livesessions_credit_log'),
        'livesessions_credit_log table should exist in DB');
});

// ================================================================
// Run
// ================================================================
echo "\n=== Phase 3 — Package System (" . count($tests) . " tests) ===\n\n";
run3($tests, $passed, $failed);
cleanup_all();
echo "\n--- Results: {$passed} passed, {$failed} failed ---\n\n";
exit($failed > 0 ? 1 : 0);
