<?php
/**
 * Value object returned by vdocipher_uploader::upload().
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class vdocipher_upload_result {

    public string $provider;
    public string $video_id;
    public string $external_id;   // alias for video_id — matches recording_manager contract
    public string $embed_url;     // vdocipher:/{video_id} placeholder
    public string $video_url;     // API URL for management
    public string $thumbnail_url;
    public int    $duration;      // seconds
    public int    $size_bytes;

    public function __construct(
        string $video_id,
        string $embed_url,
        string $video_url,
        string $thumbnail_url,
        int    $duration   = 0,
        int    $size_bytes = 0
    ) {
        $this->provider      = 'vdocipher';
        $this->video_id      = $video_id;
        $this->external_id   = $video_id;
        $this->embed_url     = $embed_url;
        $this->video_url     = $video_url;
        $this->thumbnail_url = $thumbnail_url;
        $this->duration      = $duration;
        $this->size_bytes    = $size_bytes;
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
}
