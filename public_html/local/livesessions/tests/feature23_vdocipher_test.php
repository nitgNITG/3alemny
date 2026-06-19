<?php
/**
 * Feature 2.3 — VdoCipher Upload test suite.
 *
 * Tests:
 *   TC-2.3.01  vdocipher_upload_result provider = 'vdocipher'
 *   TC-2.3.02  vdocipher_upload_result external_id == video_id
 *   TC-2.3.03  vdocipher_upload_result to_array() has correct keys
 *   TC-2.3.04  vdocipher_uploader throws when api_secret missing
 *   TC-2.3.05  is_placeholder() true for vdocipher:/ prefix
 *   TC-2.3.06  is_placeholder() false for real URL
 *   TC-2.3.07  video_id_from_placeholder extracts id correctly
 *   TC-2.3.08  build_embed_url_placeholder produces vdocipher:/ prefix
 *   TC-2.3.09  build_embed_url returns player.vdocipher.com URL
 *   TC-2.3.10  upload_to_vdocipher method exists and is not a stub
 *   TC-2.3.11  process_recording references vdocipher_uploader
 *   TC-2.3.12  upgrade.php contains version 2024020202
 *   TC-2.3.13  upgrade.php creates livesessions_vdo_polls table
 *   TC-2.3.14  upgrade.php adds moodle_cmid column
 *   TC-2.3.15  settings.php has vdocipher_api_secret
 *   TC-2.3.16  settings.php has vdocipher_otp_ttl
 *   TC-2.3.17  vdocipher_player.php exists
 *   TC-2.3.18  vdocipher_player.php calls generate_otp
 *   TC-2.3.19  player.php routes vdocipher recordings to vdocipher_player.php
 *   TC-2.3.20  lang strings present for vdocipher settings
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
require_once($plugin_classes . '/vdocipher_uploader.php');
require_once($plugin_classes . '/vdocipher_upload_result.php');
require_once($plugin_classes . '/task/process_recording.php');

$tests  = [];
$passed = 0;
$failed = 0;

function t23(string $name, callable $fn): void {
    global $tests;
    $tests[] = ['name' => $name, 'fn' => $fn];
}

function run23(array $tests, int &$passed, int &$failed): void {
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

function a23(bool $cond, string $msg = ''): void {
    if (!$cond) throw new \RuntimeException("Assertion failed" . ($msg ? ": $msg" : ''));
}

function eq23($a, $b, string $msg = ''): void {
    if ($a !== $b) throw new \RuntimeException(
        ($msg ? "$msg — " : '') . "Expected " . var_export($b, true) . " got " . var_export($a, true)
    );
}

// ---------------------------------------------------------------
// Tests
// ---------------------------------------------------------------

t23('TC-2.3.01 vdocipher_upload_result provider = vdocipher', function () {
    $r = new \local_livesessions\vdocipher_upload_result('vid123', 'vdocipher:/vid123', 'https://api', '', 60, 1000);
    eq23($r->provider, 'vdocipher');
});

t23('TC-2.3.02 vdocipher_upload_result external_id == video_id', function () {
    $r = new \local_livesessions\vdocipher_upload_result('vid456', 'vdocipher:/vid456', '', '');
    eq23($r->external_id, $r->video_id, 'external_id should equal video_id');
});

t23('TC-2.3.03 vdocipher_upload_result to_array() has correct keys', function () {
    $r   = new \local_livesessions\vdocipher_upload_result('vid789', 'vdocipher:/vid789', 'https://v', 'https://t');
    $arr = $r->to_array();
    foreach (['provider', 'external_id', 'embed_url', 'video_url', 'thumbnail_url'] as $k) {
        a23(array_key_exists($k, $arr), "missing key: $k");
    }
    eq23($arr['provider'], 'vdocipher');
});

t23('TC-2.3.04 vdocipher_uploader throws when api_secret missing', function () {
    set_config('vdocipher_api_secret', '', 'local_livesessions');
    try {
        new \local_livesessions\vdocipher_uploader();
        throw new \LogicException('Expected exception not thrown');
    } catch (\LogicException $e) {
        throw $e;
    } catch (\Throwable $e) {
        a23(str_contains($e->getMessage(), 'secret') || str_contains($e->getMessage(), 'VdoCipher'),
            'Exception: ' . $e->getMessage());
    }
});

t23('TC-2.3.05 is_placeholder() true for vdocipher:/ prefix', function () {
    a23(\local_livesessions\vdocipher_uploader::is_placeholder('vdocipher:/some-video-id'));
    a23(\local_livesessions\vdocipher_uploader::is_placeholder('vdocipher://some-video-id'));
});

t23('TC-2.3.06 is_placeholder() false for real URL', function () {
    a23(!\local_livesessions\vdocipher_uploader::is_placeholder('https://player.vdocipher.com/v2/?otp=abc'));
    a23(!\local_livesessions\vdocipher_uploader::is_placeholder(''));
    a23(!\local_livesessions\vdocipher_uploader::is_placeholder('https://iframe.mediadelivery.net/embed/0/guid'));
});

t23('TC-2.3.07 video_id_from_placeholder extracts id correctly', function () {
    $id = \local_livesessions\vdocipher_uploader::video_id_from_placeholder('vdocipher:/abc-def-123');
    eq23($id, 'abc-def-123', 'extracted video id');
});

t23('TC-2.3.08 build_embed_url_placeholder produces vdocipher:/ prefix', function () {
    set_config('vdocipher_api_secret', 'test-secret', 'local_livesessions');
    $uploader = new \local_livesessions\vdocipher_uploader();
    $url      = $uploader->build_embed_url_placeholder('video-guid-001');
    a23(str_starts_with($url, 'vdocipher:/'), "URL: $url");
    a23(str_contains($url, 'video-guid-001'), "URL: $url");
});

t23('TC-2.3.09 build_embed_url returns player.vdocipher.com URL', function () {
    set_config('vdocipher_api_secret', 'test-secret', 'local_livesessions');
    $uploader = new \local_livesessions\vdocipher_uploader();
    $url      = $uploader->build_embed_url('my-otp-token', 'my-playback-info');
    a23(str_contains($url, 'player.vdocipher.com'), "URL: $url");
    a23(str_contains($url, 'otp='),          "URL: $url");
    a23(str_contains($url, 'playbackInfo='), "URL: $url");
});

t23('TC-2.3.10 upload_to_vdocipher method exists and is not a stub', function () {
    $rm  = new \ReflectionClass(\local_livesessions\task\process_recording::class);
    a23($rm->hasMethod('upload_to_vdocipher'), 'Method not found');
    $src = file_get_contents($rm->getFileName());
    a23(!str_contains($src, '[STUB] VdoCipher'), 'Should not contain stub code');
    a23(str_contains($src, 'vdocipher_uploader'), 'Should reference vdocipher_uploader');
});

t23('TC-2.3.11 process_recording references vdocipher_uploader', function () {
    $src = file_get_contents(dirname(__DIR__) . '/classes/task/process_recording.php');
    a23(str_contains($src, 'vdocipher_uploader'), 'process_recording should use vdocipher_uploader');
});

t23('TC-2.3.12 upgrade.php contains version 2024020202', function () {
    $src = file_get_contents(dirname(__DIR__) . '/db/upgrade.php');
    a23(str_contains($src, '2024020202'), 'upgrade.php should contain version 2024020202');
});

t23('TC-2.3.13 upgrade.php creates livesessions_vdo_polls', function () {
    $src = file_get_contents(dirname(__DIR__) . '/db/upgrade.php');
    a23(str_contains($src, 'livesessions_vdo_polls'), 'upgrade.php should create vdo_polls table');
});

t23('TC-2.3.14 upgrade.php adds moodle_cmid column', function () {
    $src = file_get_contents(dirname(__DIR__) . '/db/upgrade.php');
    a23(str_contains($src, 'moodle_cmid'), 'upgrade.php should add moodle_cmid column');
});

t23('TC-2.3.15 settings.php has vdocipher_api_secret', function () {
    $src = file_get_contents(dirname(__DIR__) . '/settings.php');
    a23(str_contains($src, 'vdocipher_api_secret'), 'settings.php should have vdocipher_api_secret');
});

t23('TC-2.3.16 settings.php has vdocipher_otp_ttl', function () {
    $src = file_get_contents(dirname(__DIR__) . '/settings.php');
    a23(str_contains($src, 'vdocipher_otp_ttl'), 'settings.php should have vdocipher_otp_ttl');
});

t23('TC-2.3.17 vdocipher_player.php exists', function () {
    $path = dirname(__DIR__) . '/vdocipher_player.php';
    a23(file_exists($path), "vdocipher_player.php not found at: $path");
});

t23('TC-2.3.18 vdocipher_player.php calls generate_otp', function () {
    $src = file_get_contents(dirname(__DIR__) . '/vdocipher_player.php');
    a23(str_contains($src, 'generate_otp'), 'vdocipher_player.php should call generate_otp');
});

t23('TC-2.3.19 player.php routes vdocipher to vdocipher_player.php', function () {
    $src = file_get_contents(dirname(__DIR__) . '/player.php');
    a23(str_contains($src, 'vdocipher_player.php'), 'player.php should route to vdocipher_player.php');
    a23(str_contains($src, 'redirect'),             'player.php should redirect for vdocipher');
});

t23('TC-2.3.20 lang strings present for vdocipher settings', function () {
    $src = file_get_contents(dirname(__DIR__) . '/lang/en/local_livesessions.php');
    foreach (['vdocipher_api_secret', 'vdocipher_otp_ttl', 'vdocipher_encode_timeout'] as $key) {
        a23(str_contains($src, $key), "Missing lang string: $key");
    }
});

// ---------------------------------------------------------------
// Run
// ---------------------------------------------------------------
echo "\n=== Feature 2.3 — VdoCipher Upload (" . count($tests) . " tests) ===\n\n";
run23($tests, $passed, $failed);
echo "\n--- Results: {$passed} passed, {$failed} failed ---\n\n";
exit($failed > 0 ? 1 : 0);
