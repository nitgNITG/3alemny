<?php
/**
 * Webhook signature verifier — provider-specific HMAC logic.
 *
 * Each provider has its own signing scheme. All secrets are stored as
 * separate Moodle config entries so providers can be rotated independently:
 *
 *   local_livesessions/webhook_secret_zoom          SHA-256 HMAC of "v0:{ts}:{body}"
 *   local_livesessions/webhook_secret_100ms         SHA-256 HMAC of body
 *   local_livesessions/webhook_secret_agora         token in ?token= query param
 *   local_livesessions/webhook_secret_bigbluebutton SHA-1 of (action + secret)
 *
 * If no secret is configured for a provider, verification is SKIPPED and
 * the function returns true. This is intentional for local dev environments.
 * In production, all secrets MUST be set.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class webhook_verifier {

    /**
     * Verify the webhook signature for a given provider.
     *
     * @param  string $provider  zoom | 100ms | agora | bigbluebutton
     * @param  string $raw_body  Raw request body (NOT decoded)
     * @param  array  $headers   Lowercase header map
     * @param  array  $query     $_GET params
     * @return bool
     */
    public static function verify(
        string $provider,
        string $raw_body,
        array  $headers,
        array  $query
    ): bool {
        $secret = self::get_secret($provider);

        // No secret configured → skip verification (dev mode).
        if (empty($secret)) {
            debugging("webhook_verifier: no secret configured for {$provider} — skipping verification",
                DEBUG_DEVELOPER);
            return true;
        }

        if ($provider === 'zoom')          return self::verify_zoom($secret, $raw_body, $headers);
        if ($provider === '100ms')         return self::verify_100ms($secret, $raw_body, $headers);
        if ($provider === 'agora')         return self::verify_agora($secret, $query);
        if ($provider === 'bigbluebutton') return self::verify_bbb($secret, $query);
        if ($provider === 'bunny')         return self::verify_bunny($secret, $raw_body, $headers);
        return false;
    }

    // ---------------------------------------------------------------
    // Zoom
    // https://developers.zoom.us/docs/api/rest/webhook-reference/#validate-your-webhook-endpoint
    //
    // Header:  x-zm-signature: v0=<hex>
    //          x-zm-request-timestamp: <unix_ms>
    // Message: "v0:{timestamp}:{raw_body}"
    // ---------------------------------------------------------------

    private static function verify_zoom(string $secret, string $body, array $headers): bool {
        $ts  = $headers['x-zm-request-timestamp'] ?? '';
        $sig = $headers['x-zm-signature']         ?? '';

        if (!$ts || !$sig) {
            return false;
        }

        // Reject timestamps older than 5 minutes (replay protection).
        $ts_sec = $ts > 1e10 ? (int)($ts / 1000) : (int)$ts;
        if (abs(time() - $ts_sec) > 300) {
            return false;
        }

        $expected = 'v0=' . hash_hmac('sha256', "v0:{$ts}:{$body}", $secret);
        return hash_equals($expected, $sig);
    }

    // ---------------------------------------------------------------
    // 100ms
    // https://www.100ms.live/docs/server-side/v2/how-to-guides/configure-webhooks
    //
    // Header:  HMS-Signature: <hex>
    // Message: raw_body (no timestamp)
    // ---------------------------------------------------------------

    private static function verify_100ms(string $secret, string $body, array $headers): bool {
        $sig = $headers['hms-signature'] ?? $headers['hms_signature'] ?? '';
        if (!$sig) {
            return false;
        }
        $expected = hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $sig);
    }

    // ---------------------------------------------------------------
    // Agora
    // Agora sends a shared secret token as ?token=<value> in the callback URL.
    // ---------------------------------------------------------------

    private static function verify_agora(string $secret, array $query): bool {
        $token = $query['token'] ?? '';
        return hash_equals($secret, $token);
    }

    // ---------------------------------------------------------------
    // BigBlueButton
    // BBB callback URL must include a checksum: SHA-1(callbackAction + sharedSecret)
    //
    // Query param: ?checksum=<sha1>
    // ---------------------------------------------------------------

    private static function verify_bbb(string $secret, array $query): bool {
        $checksum = $query['checksum'] ?? '';
        if (!$checksum) {
            return false;
        }
        // BBB computes: SHA-1("meeting_ended" + secret)
        $expected = sha1('meeting_ended' . $secret);
        return hash_equals($expected, $checksum);
    }

    // ---------------------------------------------------------------
    // Bunny Stream
    // Bunny sends a SHA-256 HMAC of the raw body in the BunnyNet-Signature header.
    // Secret = Bunny webhook security key configured in the Bunny dashboard.
    // ---------------------------------------------------------------

    private static function verify_bunny(string $secret, string $body, array $headers): bool {
        $sig = $headers['bunnynet-signature'] ?? $headers['x-bunny-signature'] ?? '';
        if (!$sig) {
            return false;
        }
        $expected = hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $sig);
    }

    // ---------------------------------------------------------------
    // Helper: retrieve per-provider secret from Moodle config
    // ---------------------------------------------------------------

    private static function get_secret(string $provider): string {
        $key = 'webhook_secret_' . str_replace(['100ms', '-', ' '], ['100ms', '_', '_'], $provider);
        return (string)(get_config('local_livesessions', $key) ?? '');
    }
}
