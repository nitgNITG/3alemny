<?php
/**
 * Feature 2.4 — Moodle Activity Auto-creation test suite.
 *
 * Tests:
 *   TC-2.4.01  activity_creator class exists
 *   TC-2.4.02  create_for_recording returns null when toggle disabled
 *   TC-2.4.03  create_for_recording returns null for non-ready recording
 *   TC-2.4.04  create_for_recording returns null when recording not found
 *   TC-2.4.05  create_for_recording returns existing cmid if already created
 *   TC-2.4.06  activity_creator uses player.php for bunny provider
 *   TC-2.4.07  activity_creator uses vdocipher_player.php for vdocipher provider
 *   TC-2.4.08  delete_for_recording is safe when cmid is null
 *   TC-2.4.09  process_recording calls activity_creator
 *   TC-2.4.10  settings.php has auto_create_activity toggle
 *   TC-2.4.11  settings.php has auto_activity_section setting
 *   TC-2.4.12  lang strings present for activity creator settings
 *   TC-2.4.13  upgrade.php adds moodle_cmid to recordings table
 *   TC-2.4.14  version.php bumped to 2024020202
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

$plugin_classes = dirname(__DIR__) . '/classes';
require_once($plugin_classes . '/activity_creator.php');
require_once($plugin_classes . '/task/process_recording.php');

$tests  = [];
$passed = 0;
$failed = 0;

function t24(string $name, callable $fn): void {
    global $tests;
    $tests[] = ['name' => $name, 'fn' => $fn];
}

function run24(array $tests, int &$passed, int &$failed): void {
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

function a24(bool $cond, string $msg = ''): void {
    if (!$cond) throw new \RuntimeException("Assertion failed" . ($msg ? ": $msg" : ''));
}

function eq24($a, $b, string $msg = ''): void {
    if ($a !== $b) throw new \RuntimeException(
        ($msg ? "$msg — " : '') . "Expected " . var_export($b, true) . " got " . var_export($a, true)
    );
}

// ---------------------------------------------------------------
// Tests
// ---------------------------------------------------------------

t24('TC-2.4.01 activity_creator class exists', function () {
    a24(class_exists(\local_livesessions\activity_creator::class), 'activity_creator class not found');
    a24(method_exists(\local_livesessions\activity_creator::class, 'create_for_recording'),
        'create_for_recording method not found');
    a24(method_exists(\local_livesessions\activity_creator::class, 'delete_for_recording'),
        'delete_for_recording method not found');
});

t24('TC-2.4.02 create_for_recording returns null when toggle disabled', function () {
    set_config('auto_create_activity', 0, 'local_livesessions');
    // Pass a fake recording id — should return null immediately due to toggle.
    $result = \local_livesessions\activity_creator::create_for_recording(99999);
    eq24($result, null, 'Should return null when toggle disabled');
});

t24('TC-2.4.03 create_for_recording returns null for non-ready recording', function () {
    global $DB;
    set_config('auto_create_activity', 1, 'local_livesessions');

    // Insert a fake session and recording.
    $sess_id = $DB->insert_record('livesessions_sessions', (object)[
        'courseid'    => 1,
        'teacherid'   => 2,
        'title'       => 'Test Session 2.4.03',
        'status'      => 'completed',
        'starttime'   => time() - 3600,
        'endtime'     => time() - 1800,
        'duration'    => 60,
        'capacity'    => 0,
        'timecreated' => time(),
        'timemodified'=> time(),
    ]);

    $rec_id = $DB->insert_record('livesessions_recordings', (object)[
        'sessionid'   => $sess_id,
        'provider'    => 'zoom',
        'status'      => 'uploading', // NOT ready
        'timecreated' => time(),
        'retry_count' => 0,
    ]);

    $result = \local_livesessions\activity_creator::create_for_recording($rec_id);
    eq24($result, null, 'Should return null for non-ready recording');

    // Cleanup.
    $DB->delete_records('livesessions_recordings', ['id' => $rec_id]);
    $DB->delete_records('livesessions_sessions',   ['id' => $sess_id]);
});

t24('TC-2.4.04 create_for_recording returns null for non-existent recording', function () {
    set_config('auto_create_activity', 1, 'local_livesessions');
    $result = \local_livesessions\activity_creator::create_for_recording(0);
    eq24($result, null, 'Should return null for id=0');
});

t24('TC-2.4.05 create_for_recording returns existing cmid if already set', function () {
    global $DB;
    set_config('auto_create_activity', 1, 'local_livesessions');

    $sess_id = $DB->insert_record('livesessions_sessions', (object)[
        'courseid'    => 1,
        'teacherid'   => 2,
        'title'       => 'Test Session 2.4.05',
        'status'      => 'completed',
        'starttime'   => time() - 3600,
        'endtime'     => time() - 1800,
        'duration'    => 60,
        'capacity'    => 0,
        'timecreated' => time(),
        'timemodified'=> time(),
    ]);

    $rec_id = $DB->insert_record('livesessions_recordings', (object)[
        'sessionid'    => $sess_id,
        'provider'     => 'zoom',
        'status'       => 'ready',
        'moodle_cmid'=> 9999, // Already has a cmid.
        'timecreated'  => time(),
        'retry_count'  => 0,
    ]);

    $result = \local_livesessions\activity_creator::create_for_recording($rec_id);
    eq24($result, 9999, 'Should return existing cmid without re-creating');

    // Cleanup.
    $DB->delete_records('livesessions_recordings', ['id' => $rec_id]);
    $DB->delete_records('livesessions_sessions',   ['id' => $sess_id]);
});

t24('TC-2.4.06 activity_creator uses player.php for bunny provider', function () {
    $src = file_get_contents(dirname(__DIR__) . '/classes/activity_creator.php');
    a24(str_contains($src, 'player.php'), 'activity_creator should reference player.php');
});

t24('TC-2.4.07 activity_creator uses vdocipher_player.php for vdocipher provider', function () {
    $src = file_get_contents(dirname(__DIR__) . '/classes/activity_creator.php');
    a24(str_contains($src, 'vdocipher_player.php'), 'activity_creator should reference vdocipher_player.php');
});

t24('TC-2.4.08 delete_for_recording is safe when cmid is null', function () {
    global $DB;

    $sess_id = $DB->insert_record('livesessions_sessions', (object)[
        'courseid'    => 1,
        'teacherid'   => 2,
        'title'       => 'Test Session 2.4.08',
        'status'      => 'completed',
        'starttime'   => time() - 3600,
        'endtime'     => time() - 1800,
        'duration'    => 60,
        'capacity'    => 0,
        'timecreated' => time(),
        'timemodified'=> time(),
    ]);

    $rec_id = $DB->insert_record('livesessions_recordings', (object)[
        'sessionid'    => $sess_id,
        'provider'     => 'zoom',
        'status'       => 'ready',
        'moodle_cmid'=> null, // No cmid.
        'timecreated'  => time(),
        'retry_count'  => 0,
    ]);

    // Should not throw.
    \local_livesessions\activity_creator::delete_for_recording($rec_id);
    a24(true, 'delete_for_recording should be safe when no activity exists');

    // Cleanup.
    $DB->delete_records('livesessions_recordings', ['id' => $rec_id]);
    $DB->delete_records('livesessions_sessions',   ['id' => $sess_id]);
});

t24('TC-2.4.09 process_recording calls activity_creator', function () {
    $src = file_get_contents(dirname(__DIR__) . '/classes/task/process_recording.php');
    a24(str_contains($src, 'activity_creator'), 'process_recording should call activity_creator');
    a24(str_contains($src, 'create_for_recording'), 'process_recording should call create_for_recording');
});

t24('TC-2.4.10 settings.php has auto_create_activity toggle', function () {
    $src = file_get_contents(dirname(__DIR__) . '/settings.php');
    a24(str_contains($src, 'auto_create_activity'), 'settings.php missing auto_create_activity');
});

t24('TC-2.4.11 settings.php has auto_activity_section setting', function () {
    $src = file_get_contents(dirname(__DIR__) . '/settings.php');
    a24(str_contains($src, 'auto_activity_section'), 'settings.php missing auto_activity_section');
});

t24('TC-2.4.12 lang strings present for activity creator', function () {
    $src = file_get_contents(dirname(__DIR__) . '/lang/en/local_livesessions.php');
    foreach (['auto_create_activity', 'auto_activity_section'] as $key) {
        a24(str_contains($src, $key), "Missing lang string: $key");
    }
});

t24('TC-2.4.13 upgrade.php adds moodle_cmid column', function () {
    $src = file_get_contents(dirname(__DIR__) . '/db/upgrade.php');
    a24(str_contains($src, 'moodle_cmid'), 'upgrade.php should add moodle_cmid');
});

t24('TC-2.4.14 version.php bumped to 2024070101 (final)', function () {
    $src = file_get_contents(dirname(__DIR__) . '/version.php');
    a24(str_contains($src, '2024070101'), 'version.php should contain 2024070101');
});

// ---------------------------------------------------------------
// Run
// ---------------------------------------------------------------
echo "\n=== Feature 2.4 — Activity Auto-creation (" . count($tests) . " tests) ===\n\n";
run24($tests, $passed, $failed);
echo "\n--- Results: {$passed} passed, {$failed} failed ---\n\n";
exit($failed > 0 ? 1 : 0);
