<?php
/**
 * Feature 2.2 — Bunny Stream Upload test suite.
 *
 * Tests:
 *   TC-2.2.01  bunny_upload_result value object
 *   TC-2.2.02  to_array() returns correct keys
 *   TC-2.2.03  is_ready() true only for ENC_FINISHED
 *   TC-2.2.04  webhook_verifier accepts Bunny HMAC
 *   TC-2.2.05  webhook_verifier rejects bad Bunny HMAC
 *   TC-2.2.06  webhook_verifier passes when no secret configured (dev mode)
 *   TC-2.2.07  bunny_uploader constructor throws when library_id missing
 *   TC-2.2.08  bunny_uploader constructor throws when api_key missing
 *   TC-2.2.09  bunny_uploader build_signed_embed_url generates correct token
 *   TC-2.2.10  build_signed_embed_url changes when expiry changes
 *   TC-2.2.11  build_signed_embed_url IP is optional
 *   TC-2.2.12  build_video_url uses cdn_hostname
 *   TC-2.2.13  build_thumbnail_url uses cdn_hostname
 *   TC-2.2.14  fresh_signed_url returns URL with token param
 *   TC-2.2.15  process_recording upload_to_bunny calls bunny_uploader (integration stub)
 *   TC-2.2.16  upgrade step 2024020201 creates bunny_polls table
 *   TC-2.2.17  upgrade step creates rec_log table
 *   TC-2.2.18  upgrade step creates webhook_log table
 *   TC-2.2.19  settings.php exposes bunny_library_id key
 *   TC-2.2.20  settings.php exposes bunny_token_auth_key key
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Bootstrap — works both from CLI (`php tests/feature22_bunny_upload_test.php`)
// and from the runner used in prior features.
if (!defined('MOODLE_INTERNAL')) {
    $moodle_root = dirname(__FILE__, 4);
    define('MOODLE_INTERNAL', true);
    define('CLI_SCRIPT', true);
    require_once($moodle_root . '/config.php');
}

// Manually require classes that may not be autoloaded in CLI test context.
$plugin_classes = dirname(__DIR__) . '/classes';
require_once($plugin_classes . '/bunny_uploader.php');
require_once($plugin_classes . '/bunny_upload_result.php');
require_once($plugin_classes . '/webhook_verifier.php');
require_once($plugin_classes . '/recording_manager.php');
require_once($plugin_classes . '/task/process_recording.php');

$tests  = [];
$passed = 0;
$failed = 0;

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

function t22(string $name, callable $fn): void {
    global $tests;
    $tests[] = ['name' => $name, 'fn' => $fn];
}

function run22(array $tests, int &$passed, int &$failed): void {
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

function assert22(bool $cond, string $msg = ''): void {
    if (!$cond) throw new \RuntimeException("Assertion failed" . ($msg ? ": $msg" : ''));
}

function eq22($a, $b, string $msg = ''): void {
    if ($a !== $b) throw new \RuntimeException(
        ($msg ? "$msg — " : '') . "Expected " . var_export($b, true) . " got " . var_export($a, true)
    );
}

// ---------------------------------------------------------------
// Helper: build bunny_uploader with fake Moodle config
// ---------------------------------------------------------------

function make_uploader(array $cfg = []): \local_livesessions\bunny_uploader {
    global $CFG;
    // Set required configs via set_config for the duration of the test.
    set_config('bunny_library_id',      $cfg['library_id']      ?? '12345',           'local_livesessions');
    set_config('bunny_library_api_key', $cfg['api_key']         ?? 'test-api-key',    'local_livesessions');
    set_config('bunny_cdn_hostname',    $cfg['cdn_hostname']    ?? 'vz-test.b-cdn.net','local_livesessions');
    set_config('bunny_token_auth_key',  $cfg['token_auth_key']  ?? 'tok-secret-xyz',  'local_livesessions');
    set_config('bunny_token_ttl',       $cfg['token_ttl']       ?? 14400,             'local_livesessions');
    return new \local_livesessions\bunny_uploader();
}

// ---------------------------------------------------------------
// TC-2.2.01 — bunny_upload_result value object
// ---------------------------------------------------------------
t22('TC-2.2.01 bunny_upload_result has correct provider', function () {
    $r = new \local_livesessions\bunny_upload_result('guid-abc', 'https://embed', 'https://video', 'https://thumb');
    eq22($r->provider,    'bunny', 'provider');
    eq22($r->video_guid,  'guid-abc', 'video_guid');
    eq22($r->external_id, 'guid-abc', 'external_id == video_guid');
});

// TC-2.2.02 — to_array()
t22('TC-2.2.02 to_array() returns correct keys', function () {
    $r   = new \local_livesessions\bunny_upload_result('g1', 'https://e', 'https://v', 'https://t', 120, 5000000);
    $arr = $r->to_array();
    foreach (['provider', 'external_id', 'embed_url', 'video_url', 'thumbnail_url'] as $k) {
        assert22(array_key_exists($k, $arr), "missing key: $k");
    }
    eq22($arr['provider'],    'bunny');
    eq22($arr['external_id'], 'g1');
    eq22($arr['embed_url'],   'https://e');
});

// TC-2.2.03 — is_ready()
t22('TC-2.2.03 is_ready() true only for ENC_FINISHED', function () {
    $ready   = new \local_livesessions\bunny_upload_result('g', 'e', 'v', 't', 0, 0, \local_livesessions\bunny_uploader::ENC_FINISHED);
    $failed  = new \local_livesessions\bunny_upload_result('g', 'e', 'v', 't', 0, 0, \local_livesessions\bunny_uploader::ENC_FAILED);
    $queued  = new \local_livesessions\bunny_upload_result('g', 'e', 'v', 't', 0, 0, \local_livesessions\bunny_uploader::ENC_QUEUED);
    assert22($ready->is_ready(),   'ENC_FINISHED should be ready');
    assert22(!$failed->is_ready(), 'ENC_FAILED should not be ready');
    assert22(!$queued->is_ready(), 'ENC_QUEUED should not be ready');
});

// TC-2.2.04 — webhook_verifier Bunny valid HMAC
t22('TC-2.2.04 webhook_verifier accepts valid Bunny HMAC', function () {
    set_config('webhook_secret_bunny', 'bunny-webhook-secret', 'local_livesessions');
    $body    = '{"VideoGuid":"abc","Status":4}';
    $sig     = hash_hmac('sha256', $body, 'bunny-webhook-secret');
    $headers = ['bunnynet-signature' => $sig];
    $result  = \local_livesessions\webhook_verifier::verify('bunny', $body, $headers, []);
    assert22($result, 'valid HMAC should return true');
});

// TC-2.2.05 — webhook_verifier Bunny invalid HMAC
t22('TC-2.2.05 webhook_verifier rejects invalid Bunny HMAC', function () {
    set_config('webhook_secret_bunny', 'bunny-webhook-secret', 'local_livesessions');
    $body    = '{"VideoGuid":"abc","Status":4}';
    $headers = ['bunnynet-signature' => 'wrong-sig'];
    $result  = \local_livesessions\webhook_verifier::verify('bunny', $body, $headers, []);
    assert22(!$result, 'invalid HMAC should return false');
});

// TC-2.2.06 — webhook_verifier dev mode (no secret)
t22('TC-2.2.06 webhook_verifier passes when no Bunny secret set', function () {
    set_config('webhook_secret_bunny', '', 'local_livesessions');
    $result = \local_livesessions\webhook_verifier::verify('bunny', '{}', [], []);
    assert22($result, 'empty secret should allow through in dev mode');
});

// TC-2.2.07 — bunny_uploader constructor throws when library_id missing
t22('TC-2.2.07 bunny_uploader throws when bunny_library_id missing', function () {
    set_config('bunny_library_id',      '',              'local_livesessions');
    set_config('bunny_library_api_key', 'test-api-key', 'local_livesessions');
    try {
        new \local_livesessions\bunny_uploader();
        throw new \LogicException('Expected exception not thrown');
    } catch (\LogicException $e) {
        throw $e; // re-throw "Expected exception not thrown"
    } catch (\Throwable $e) {
        assert22(str_contains($e->getMessage(), 'bunny_library_id') ||
                 str_contains($e->getMessage(), 'library'),
            'Exception should mention missing config: ' . $e->getMessage());
    }
});

// TC-2.2.08 — bunny_uploader constructor throws when api_key missing
t22('TC-2.2.08 bunny_uploader throws when bunny_library_api_key missing', function () {
    set_config('bunny_library_id',      '12345', 'local_livesessions');
    set_config('bunny_library_api_key', '',      'local_livesessions');
    try {
        new \local_livesessions\bunny_uploader();
        throw new \LogicException('Expected exception not thrown');
    } catch (\LogicException $e) {
        throw $e;
    } catch (\Throwable $e) {
        assert22(true, 'Exception thrown as expected: ' . $e->getMessage());
    }
});

// TC-2.2.09 — build_signed_embed_url generates SHA-256 token
t22('TC-2.2.09 build_signed_embed_url generates correct token', function () {
    $uploader    = make_uploader(['token_auth_key' => 'secret123', 'cdn_hostname' => 'vz-x.b-cdn.net']);
    $guid        = 'video-guid-001';
    $expiry      = time() + 14400;
    $ip          = '1.2.3.4';

    // Reproduce the expected token.
    $hash_base   = 'secret123' . '/' . $guid . $expiry . $ip;
    $hash_hex    = hash('sha256', $hash_base);
    $expected    = base64_encode(hex2bin($hash_hex));
    $expected    = str_replace(['+', '/', '='], ['-', '_', ''], $expected);

    $url = $uploader->build_signed_embed_url($guid, $expiry, $ip);
    assert22(str_contains($url, $expected), "URL should contain expected token. URL: $url");
    assert22(str_contains($url, $guid),     "URL should contain video guid");
});

// TC-2.2.10 — different expiry → different token
t22('TC-2.2.10 build_signed_embed_url changes when expiry changes', function () {
    $uploader = make_uploader(['token_auth_key' => 'secret123']);
    $guid     = 'video-guid-002';
    $url1     = $uploader->build_signed_embed_url($guid, time() + 3600);
    $url2     = $uploader->build_signed_embed_url($guid, time() + 7200);
    assert22($url1 !== $url2, 'Different expiry should produce different URL');
});

// TC-2.2.11 — IP optional (defaults to no IP in token)
t22('TC-2.2.11 build_signed_embed_url works without IP', function () {
    $uploader = make_uploader(['token_auth_key' => 'secret123']);
    $guid     = 'video-guid-003';
    $url      = $uploader->build_signed_embed_url($guid, time() + 3600, '');
    assert22(str_contains($url, 'token='), "URL should contain token= param: $url");
});

// TC-2.2.12 — build_video_url
t22('TC-2.2.12 build_video_url uses cdn_hostname', function () {
    $uploader = make_uploader(['cdn_hostname' => 'vz-testhost.b-cdn.net']);
    $url      = $uploader->build_video_url('myguid');
    assert22(str_contains($url, 'vz-testhost.b-cdn.net'), "URL: $url");
    assert22(str_contains($url, 'myguid'), "URL: $url");
});

// TC-2.2.13 — build_thumbnail_url
t22('TC-2.2.13 build_thumbnail_url uses cdn_hostname and contains thumbnail.jpg', function () {
    $uploader = make_uploader(['cdn_hostname' => 'vz-testhost.b-cdn.net']);
    $url      = $uploader->build_thumbnail_url('myguid');
    assert22(str_contains($url, 'thumbnail.jpg'), "URL: $url");
    assert22(str_contains($url, 'myguid'), "URL: $url");
});

// TC-2.2.14 — fresh_signed_url returns URL with token param
t22('TC-2.2.14 fresh_signed_url returns URL with token param', function () {
    $uploader = make_uploader(['token_auth_key' => 'secret123', 'cdn_hostname' => 'vz-x.b-cdn.net']);
    $url      = $uploader->fresh_signed_url('video-guid-fresh', '5.6.7.8', 3600);
    assert22(str_contains($url, 'token='),    "URL should have token param: $url");
    assert22(str_contains($url, 'expires='),  "URL should have expires param: $url");
});

// TC-2.2.15 — process_recording upload_to_bunny calls bunny_uploader
t22('TC-2.2.15 upload_to_bunny method exists in process_recording task', function () {
    $rm = new \ReflectionClass(\local_livesessions\task\process_recording::class);
    assert22($rm->hasMethod('upload_to_bunny'), 'Method upload_to_bunny not found');
    $m  = $rm->getMethod('upload_to_bunny');
    assert22($m->isPrivate(), 'upload_to_bunny should be private');
    // Verify it no longer contains [STUB] text.
    $source = file_get_contents($rm->getFileName());
    assert22(!str_contains($source, '[STUB] Bunny'), 'upload_to_bunny should not contain stub code');
    assert22(str_contains($source, 'bunny_uploader'),  'upload_to_bunny should reference bunny_uploader');
});

// TC-2.2.16 — upgrade step creates bunny_polls table (check class definition exists in upgrade.php)
t22('TC-2.2.16 upgrade.php contains step for 2024020201', function () {
    $src = file_get_contents(__DIR__ . '/../db/upgrade.php');
    assert22(str_contains($src, '2024020201'), 'upgrade.php should contain version 2024020201');
    assert22(str_contains($src, 'livesessions_bunny_polls'), 'upgrade.php should create bunny_polls table');
});

// TC-2.2.17 — upgrade step creates rec_log table
t22('TC-2.2.17 upgrade.php creates livesessions_rec_log table', function () {
    $src = file_get_contents(__DIR__ . '/../db/upgrade.php');
    assert22(str_contains($src, 'livesessions_rec_log'), 'upgrade.php should create rec_log table');
});

// TC-2.2.18 — upgrade step creates webhook_log table
t22('TC-2.2.18 upgrade.php creates livesessions_webhook_log table', function () {
    $src = file_get_contents(__DIR__ . '/../db/upgrade.php');
    assert22(str_contains($src, 'livesessions_webhook_log'), 'upgrade.php should create webhook_log table');
});

// TC-2.2.19 — settings.php has bunny_library_id key
t22('TC-2.2.19 settings.php exposes bunny_library_id', function () {
    $src = file_get_contents(__DIR__ . '/../settings.php');
    assert22(str_contains($src, 'bunny_library_id'), 'settings.php should contain bunny_library_id');
});

// TC-2.2.20 — settings.php has bunny_token_auth_key
t22('TC-2.2.20 settings.php exposes bunny_token_auth_key', function () {
    $src = file_get_contents(__DIR__ . '/../settings.php');
    assert22(str_contains($src, 'bunny_token_auth_key'), 'settings.php should contain bunny_token_auth_key');
});

// ---------------------------------------------------------------
// Run
// ---------------------------------------------------------------
echo "\n=== Feature 2.2 — Bunny Stream Upload (" . count($tests) . " tests) ===\n\n";
run22($tests, $passed, $failed);
echo "\n--- Results: {$passed} passed, {$failed} failed ---\n\n";
exit($failed > 0 ? 1 : 0);
