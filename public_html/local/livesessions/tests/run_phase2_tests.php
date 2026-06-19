#!/usr/bin/env php
<?php
/**
 * Phase 2 — Full test runner.
 *
 * Runs all Feature 2.x test suites in sequence and reports a combined summary.
 *
 * Usage:
 *   php tests/run_phase2_tests.php
 *
 * Exit code: 0 = all tests passed, 1 = one or more failures.
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

$suites = [
    '2.2 — Bunny Stream Upload'         => __DIR__ . '/feature22_bunny_upload_test.php',
    '2.3 — VdoCipher Upload'            => __DIR__ . '/feature23_vdocipher_test.php',
    '2.4 — Activity Auto-creation'      => __DIR__ . '/feature24_activity_creator_test.php',
];

$total_passed = 0;
$total_failed = 0;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         local_livesessions — Phase 2 Test Suite             ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";

foreach ($suites as $name => $path) {
    if (!file_exists($path)) {
        echo "\n  ⚠  MISSING: Feature {$name} test file not found at {$path}\n";
        $total_failed++;
        continue;
    }

    // Capture output from the sub-suite.
    ob_start();
    $exit = null;
    // Run in same process — functions may conflict across suites,
    // so we parse the results line from stdout instead of sharing state.
    system(PHP_BINARY . ' ' . escapeshellarg($path) . ' 2>&1', $exit);
    $output = ob_get_clean();

    // Re-print output (already printed by system() directly to stdout).
    // Extract pass/fail counts from the "--- Results:" line.
    if (preg_match('/Results: (\d+) passed, (\d+) failed/', $output, $m)) {
        $total_passed += (int)$m[1];
        $total_failed += (int)$m[2];
    }
}

echo "\n";
echo "══════════════════════════════════════════════════════════════\n";
$icon = $total_failed === 0 ? '✅' : '❌';
echo "  {$icon}  PHASE 2 TOTAL:  {$total_passed} passed,  {$total_failed} failed\n";
echo "══════════════════════════════════════════════════════════════\n\n";

exit($total_failed > 0 ? 1 : 0);
