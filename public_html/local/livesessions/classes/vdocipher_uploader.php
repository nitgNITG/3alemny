<?php
/**
 * VdoCipher Uploader — Feature 2.3.
 *
 * VdoCipher upload flow:
 *   1. POST /videos                     → get uploadLink (S3 pre-signed URL) + videoId
 *   2. PUT  <uploadLink>  (S3 PUT)      → upload raw MP4 directly to S3
 *   3. Poll GET /videos/{id}            → wait until status = "ready"
 *   4. POST /videos/{id}/otp            → generate OTP+playbackInfo for each view
 *
 * Config keys (all under local_livesessions plugin):
 *   vdocipher_api_secret   — API secret from VdoCipher dashboard → API Keys
 *   vdocipher_folder_id    — Optional: move videos into this folder
 *   vdocipher_encode_timeout — Max seconds to wait for encoding (default 3600)
 *   vdocipher_otp_ttl      — OTP time-to-live in seconds (default 300 = 5 min)
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class vdocipher_uploader {

    const API_BASE = 'https://dev.vdocipher.com/api';

    // VdoCipher video status strings.
    const STATUS_QUEUED    = 'Queued';
    const STATUS_ENCODING  = 'Processing';
    const STATUS_READY     = 'ready';
    const STATUS_FAILED    = 'Failed';

    private string $api_secret;
    private string $folder_id;
    private int    $encode_timeout;
    private int    $otp_ttl;

    // ---------------------------------------------------------------
    // Constructor
    // ---------------------------------------------------------------

    public function __construct() {
        $this->api_secret      = (string)(get_config('local_livesessions', 'vdocipher_api_secret')      ?: '');
        $this->folder_id       = (string)(get_config('local_livesessions', 'vdocipher_folder_id')       ?: '');
        $this->encode_timeout  = (int)   (get_config('local_livesessions', 'vdocipher_encode_timeout')  ?: 3600);
        $this->otp_ttl         = (int)   (get_config('local_livesessions', 'vdocipher_otp_ttl')         ?: 300);
        $this->validate_config();
    }

    // ---------------------------------------------------------------
    // Public: main upload entry point
    // ---------------------------------------------------------------

    /**
     * Upload a local MP4 file to VdoCipher.
     *
     * @param  string    $local_path   Absolute path to the MP4 file.
     * @param  \stdClass $rec          livesessions_recordings row.
     * @param  int       $recordingid  DB row id (for logging).
     * @return vdocipher_upload_result
     */
    public function upload(string $local_path, \stdClass $rec, int $recordingid): vdocipher_upload_result {
        $title = $this->build_title($rec);

        // ---- 1. Create video object on VdoCipher ----
        mtrace("    [VdoCipher] Creating video: {$title}");
        [$video_id, $upload_link, $upload_fields] = $this->create_video($title);
        mtrace("    [VdoCipher] Video created: {$video_id}");

        // ---- 2. Upload to S3 via pre-signed POST ----
        mtrace("    [VdoCipher] Uploading to S3 ...");
        $this->upload_to_s3($upload_link, $upload_fields, $local_path);
        mtrace("    [VdoCipher] S3 upload complete");

        // ---- 3. Poll for encoding completion ----
        mtrace("    [VdoCipher] Waiting for encoding ...");
        $this->wait_for_encoding($video_id, $recordingid);
        mtrace("    [VdoCipher] Encoding complete");

        // ---- 4. Fetch metadata ----
        $meta = $this->fetch_video_metadata($video_id);

        // ---- 5. Build embed URL (OTP generated per-view; store placeholder) ----
        // The actual OTP is fetched fresh in vdocipher_player.php per student request.
        $embed_url     = $this->build_embed_url_placeholder($video_id);
        $video_url     = self::API_BASE . "/videos/{$video_id}";
        $thumbnail_url = $meta['poster'] ?? '';

        return new vdocipher_upload_result(
            $video_id,
            $embed_url,
            $video_url,
            $thumbnail_url,
            (int)($meta['length'] ?? 0),
            (int)($meta['size']   ?? 0)
        );
    }

    // ---------------------------------------------------------------
    // Step 1: Create video object
    // ---------------------------------------------------------------

    /**
     * POST /videos — register video with VdoCipher, receive upload credentials.
     * Returns [video_id, upload_link, upload_fields_array].
     */
    private function create_video(string $title): array {
        $body = ['title' => $title];
        if ($this->folder_id) {
            $body['folderId'] = $this->folder_id;
        }

        $response = $this->api_call('POST', '/videos', $body);

        $video_id    = $response['videoId']    ?? '';
        $upload_link = $response['uploadLink']  ?? '';
        $fields      = $response['uploadLinkFields'] ?? [];

        if (!$video_id || !$upload_link) {
            throw new \RuntimeException(
                'VdoCipher create_video failed: ' . json_encode($response)
            );
        }

        return [$video_id, $upload_link, $fields];
    }

    // ---------------------------------------------------------------
    // Step 2: Upload to S3 via multipart POST
    // ---------------------------------------------------------------

    /**
     * VdoCipher uses an S3 pre-signed POST (multipart/form-data) for the actual upload.
     * The uploadLink is the S3 bucket URL; uploadLinkFields are the form fields.
     */
    private function upload_to_s3(string $upload_link, array $fields, string $local_path): void {
        $size = filesize($local_path);
        if (!$size) {
            throw new \RuntimeException("File not found or empty: {$local_path}");
        }

        // Build multipart fields.
        $post_fields = $fields; // VdoCipher provides: key, policy, x-amz-*, etc.
        $post_fields['file'] = new \CURLFile($local_path, 'video/mp4', basename($local_path));

        $ch = curl_init($upload_link);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post_fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 7200, // 2 hours for large files
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        // S3 pre-signed POST returns 204 on success.
        if (!in_array($code, [200, 204])) {
            throw new \RuntimeException(
                "S3 upload failed: HTTP {$code}" . ($err ? " — {$err}" : '') .
                ($response ? " — " . substr($response, 0, 200) : '')
            );
        }
    }

    // ---------------------------------------------------------------
    // Step 3: Poll for encoding
    // ---------------------------------------------------------------

    /**
     * Poll GET /videos/{id} until status = "ready" or timeout.
     */
    private function wait_for_encoding(string $video_id, int $recordingid): void {
        global $DB;

        $deadline = time() + $this->encode_timeout;
        $interval = 15; // seconds between polls

        while (time() < $deadline) {
            sleep($interval);
            $interval = min($interval + 5, 60); // ramp up to 60s

            $meta   = $this->fetch_video_metadata($video_id);
            $status = $meta['status'] ?? 'unknown';

            // Log the poll.
            try {
                $poll = new \stdClass();
                $poll->recordingid  = $recordingid;
                $poll->video_id     = $video_id;
                $poll->enc_status   = $status;
                $poll->timecreated  = time();
                $DB->insert_record('livesessions_vdo_polls', $poll);
            } catch (\Throwable $e) {
                // Non-fatal — poll logging table may not exist in older installs.
            }

            // Update recording row with current status.
            try {
                $DB->set_field('livesessions_recordings', 'encoding_status',
                    $status, ['id' => $recordingid]);
            } catch (\Throwable $e) {}

            mtrace("    [VdoCipher] Encoding status: {$status}");

            if ($status === self::STATUS_READY) {
                return;
            }
            if ($status === self::STATUS_FAILED) {
                throw new \RuntimeException("VdoCipher encoding failed for video {$video_id}");
            }
        }

        throw new \RuntimeException(
            "VdoCipher encoding timed out after {$this->encode_timeout}s for video {$video_id}"
        );
    }

    // ---------------------------------------------------------------
    // Step 4 / helper: fetch video metadata
    // ---------------------------------------------------------------

    public function fetch_video_metadata(string $video_id): array {
        return $this->api_call('GET', "/videos/{$video_id}");
    }

    // ---------------------------------------------------------------
    // OTP generation (called per-student-view in player page)
    // ---------------------------------------------------------------

    /**
     * Generate a fresh OTP + playbackInfo for a given video.
     * Must be called at player-page load time — OTP expires after otp_ttl seconds.
     *
     * @param  string $video_id   VdoCipher video ID.
     * @param  int    $ttl        Override default TTL in seconds.
     * @param  array  $annotations Optional watermark annotations.
     * @return array  ['otp' => '...', 'playbackInfo' => '...']
     */
    public function generate_otp(string $video_id, int $ttl = 0, array $annotations = []): array {
        $ttl = $ttl ?: $this->otp_ttl;

        $body = ['ttl' => $ttl];
        if ($annotations) {
            $body['annotate'] = json_encode($annotations);
        }

        $response = $this->api_call('POST', "/videos/{$video_id}/otp", $body);

        if (empty($response['otp']) || empty($response['playbackInfo'])) {
            throw new \RuntimeException(
                "VdoCipher OTP generation failed: " . json_encode($response)
            );
        }

        return $response;
    }

    /**
     * Build the full VdoCipher iframe embed URL from otp + playbackInfo.
     */
    public function build_embed_url(string $otp, string $playback_info): string {
        return 'https://player.vdocipher.com/v2/?otp=' . urlencode($otp)
             . '&playbackInfo=' . urlencode($playback_info);
    }

    /**
     * Placeholder stored in DB — the real OTP is generated fresh per view.
     * Pattern: vdocipher:/{video_id} — player.php resolves this.
     */
    public function build_embed_url_placeholder(string $video_id): string {
        return 'vdocipher:/' . $video_id;
    }

    /**
     * True if the embed_url is a VdoCipher placeholder (not a real iframe URL).
     */
    public static function is_placeholder(string $embed_url): bool {
        return strpos($embed_url, 'vdocipher:/') === 0;
    }

    /**
     * Extract video_id from placeholder.
     */
    public static function video_id_from_placeholder(string $embed_url): string {
        return ltrim(substr($embed_url, strlen('vdocipher:/')), '/');
    }

    // ---------------------------------------------------------------
    // REST API helper
    // ---------------------------------------------------------------

    /**
     * Make a VdoCipher API call.
     * All requests use Bearer token auth (api_secret).
     *
     * @param  string $method  GET | POST | DELETE
     * @param  string $path    e.g. /videos or /videos/{id}/otp
     * @param  array  $body    JSON request body (for POST)
     * @return array  Decoded JSON response
     */
    private function api_call(string $method, string $path, array $body = []): array {
        $url = self::API_BASE . $path;

        $ch = curl_init($url);
        $headers = [
            'Authorization: Apisecret ' . $this->api_secret,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ? json_encode($body) : '{}');
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response_raw = curl_exec($ch);
        $code         = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err          = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException("VdoCipher API cURL error: {$err}");
        }
        if ($code >= 400) {
            throw new \RuntimeException(
                "VdoCipher API HTTP {$code}: " . substr($response_raw, 0, 300)
            );
        }

        return json_decode($response_raw, true) ?? [];
    }

    // ---------------------------------------------------------------
    // Config validation
    // ---------------------------------------------------------------

    private function validate_config(): void {
        if (empty($this->api_secret)) {
            throw new \RuntimeException(
                'VdoCipher API secret not configured (vdocipher_api_secret)'
            );
        }
    }

    private function build_title(\stdClass $rec): string {
        global $DB;
        try {
            $sess = $DB->get_record('livesessions_sessions', ['id' => $rec->sessionid], 'title');
            return $sess ? $sess->title : "Session Recording #{$rec->sessionid}";
        } catch (\Throwable $e) {
            return "Session Recording #{$rec->sessionid}";
        }
    }
}
