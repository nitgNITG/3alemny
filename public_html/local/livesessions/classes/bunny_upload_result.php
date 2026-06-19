<?php
/**
 * Value object returned by bunny_uploader::upload().
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

/**
 * Immutable result of a Bunny Stream upload operation.
 *
 * Properties:
 *   provider       — always 'bunny'
 *   video_guid     — Bunny Stream video GUID (UUID)
 *   external_id    — alias for video_guid (matches recording_manager::mark_ready() contract)
 *   embed_url      — signed iframe embed URL (token-authenticated, expires in ttl seconds)
 *   video_url      — direct MP4 CDN URL
 *   thumbnail_url  — CDN thumbnail URL
 *   duration       — video duration in seconds (from Bunny metadata)
 *   size_bytes     — uploaded file size in bytes
 *   encoding_status— final Bunny encoding status code (4 = finished)
 */
class bunny_upload_result {

    public string $provider;
    public string $video_guid;
    public string $external_id;
    public string $embed_url;
    public string $video_url;
    public string $thumbnail_url;
    public int    $duration;
    public int    $size_bytes;
    public int    $encoding_status;

    public function __construct(
        string $video_guid,
        string $embed_url,
        string $video_url,
        string $thumbnail_url,
        int    $duration        = 0,
        int    $size_bytes      = 0,
        int    $encoding_status = bunny_uploader::ENC_FINISHED
    ) {
        $this->provider        = 'bunny';
        $this->video_guid      = $video_guid;
        $this->external_id     = $video_guid;
        $this->embed_url       = $embed_url;
        $this->video_url       = $video_url;
        $this->thumbnail_url   = $thumbnail_url;
        $this->duration        = $duration;
        $this->size_bytes      = $size_bytes;
        $this->encoding_status = $encoding_status;
    }

    /**
     * Convert to the plain array expected by process_recording::upload_recording().
     */
    public function to_array(): array {
        return [
            'provider'      => $this->provider,
            'external_id'   => $this->external_id,
            'embed_url'     => $this->embed_url,
            'video_url'     => $this->video_url,
            'thumbnail_url' => $this->thumbnail_url,
        ];
    }

    /**
     * True when Bunny finished encoding successfully.
     */
    public function is_ready(): bool {
        return $this->encoding_status === bunny_uploader::ENC_FINISHED;
    }
}
