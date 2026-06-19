<?php
/**
 * Value object: a recording that has been detected from a provider webhook.
 * Canonical normalised representation regardless of which provider fired it.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class detected_recording {

    public string $provider;          // source provider: zoom|100ms|agora|bigbluebutton
    public string $meeting_id;        // provider-side meeting/room/channel ID
    public string $external_id;       // provider-side recording file ID
    public string $idempotency_key;   // unique per-event key for deduplication
    public string $download_url;      // direct download URL (may be time-limited)
    public string $download_token;    // bearer token for authenticated download
    public string $play_url;          // provider's own playback URL
    public int    $duration_seconds;
    public int    $filesize_bytes;
    public int    $recorded_at;       // unix timestamp
    public array  $raw_payload;       // original decoded JSON
    public array  $extra;             // provider-specific fields

    public function __construct(
        string $provider,
        string $meeting_id,
        string $external_id,
        string $idempotency_key,
        string $download_url,
        string $download_token,
        string $play_url,
        int    $duration_seconds,
        int    $filesize_bytes,
        int    $recorded_at,
        array  $raw_payload = [],
        array  $extra       = []
    ) {
        $this->provider         = $provider;
        $this->meeting_id       = $meeting_id;
        $this->external_id      = $external_id;
        $this->idempotency_key  = $idempotency_key;
        $this->download_url     = $download_url;
        $this->download_token   = $download_token;
        $this->play_url         = $play_url;
        $this->duration_seconds = $duration_seconds;
        $this->filesize_bytes   = $filesize_bytes;
        $this->recorded_at      = $recorded_at;
        $this->raw_payload      = $raw_payload;
        $this->extra            = $extra;
    }

    public function needs_api_poll(): bool {
        return !empty($this->extra['needs_bbb_api_poll']);
    }

    public function has_download_url(): bool {
        return !empty($this->download_url);
    }
}
