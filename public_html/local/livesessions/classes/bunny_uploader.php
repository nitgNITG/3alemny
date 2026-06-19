<?php
/**
 * Bunny Stream Uploader — complete Bunny Stream v1 API integration.
 *
 * API docs: https://docs.bunny.net/reference/video_createvideo
 *
 * Upload flow:
 *   1. POST /library/{libraryId}/videos          → create video object, get videoId
 *   2. PUT  /library/{libraryId}/videos/{id}     → upload raw MP4 bytes (TUS or direct)
 *   3. PATCH /library/{libraryId}/videos/{id}    → set title, chapters, captions (optional)
 *   4. Poll GET /library/{libraryId}/videos/{id} → wait for encoding (status 4 = finished)
 *   5. Build signed embed URL via token auth
 *
 * Security features implemented:
 *   - Token-authenticated embed URLs (time-limited, IP-locked optional)
 *   - Referrer restriction via library settings (set once at library level)
 *   - DRM-lite: token expires after configured window (default 4 hours)
 *
 * Config keys (all under local_livesessions plugin):
 *   bunny_api_key          — Bunny.net account-level API key (for management)
 *   bunny_library_id       — Stream library ID
 *   bunny_library_api_key  — Library-specific API key (for video upload)
 *   bunny_cdn_hostname     — Pull zone hostname: vz-xxx.b-cdn.net
 *   bunny_token_auth_key   — Token authentication key (from library settings)
 *   bunny_token_ttl        — Signed URL TTL in seconds (default 14400 = 4 h)
 *   bunny_allowed_referer  — Optional allowed referer domain
 *   bunny_encode_timeout   — Max seconds to wait for encoding (default 3600)
 *   bunny_collection_id    — Optional collection to place videos into
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class bunny_uploader {

    // ---------------------------------------------------------------
    // Bunny API base URLs
    // ---------------------------------------------------------------

    const VIDEO_API_BASE = 'https://video.bunnycdn.com/library';
    const STREAM_BASE    = 'https://iframe.mediadelivery.net/embed';

    // Bunny encoding status codes returned by GET /videos/{id}
    const ENC_QUEUED   = 0;
    const ENC_MOVING   = 1;
    const ENC_ENCODING = 2;
    const ENC_FAILED   = 3;
    const ENC_FINISHED = 4;
    const ENC_CAPTIONS = 5;
    const ENC_DELETED  = 6;

    private string $library_id;
    private string $library_api_key;
    private string $cdn_hostname;
    private string $token_auth_key;
    private int    $token_ttl;
    private int    $encode_timeout;
    private string $collection_id;

    // ---------------------------------------------------------------
    // Constructor
    // ---------------------------------------------------------------

    public function __construct() {
        $this->library_id      = (string) get_config('local_livesessions', 'bunny_library_id');
        $this->library_api_key = (string) get_config('local_livesessions', 'bunny_library_api_key');
        $this->cdn_hostname    = (string) get_config('local_livesessions', 'bunny_cdn_hostname');
        $this->token_auth_key  = (string) get_config('local_livesessions', 'bunny_token_auth_key');
        $this->token_ttl       = (int)   (get_config('local_livesessions', 'bunny_token_ttl')       ?: 14400);
        $this->encode_timeout  = (int)   (get_config('local_livesessions', 'bunny_encode_timeout')  ?: 3600);
        $this->collection_id   = (string)(get_config('local_livesessions', 'bunny_collection_id')   ?: '');

        $this->validate_config();
    }

    // ---------------------------------------------------------------
    // Public: main upload entry point
    // ---------------------------------------------------------------

    /**
     * Upload a local MP4 file to Bunny Stream.
     *
     * @param  string    $local_path   Absolute path to the MP4 file on disk.
     * @param  \stdClass $rec          livesessions_recordings row (for metadata).
     * @param  int       $recordingid  DB row id (for poll logging).
     * @return bunny_upload_result
     * @throws \RuntimeException on any unrecoverable failure.
     */
    public function upload(string $local_path, \stdClass $rec, int $recordingid): bunny_upload_result {

        // ---- 1. Build video title from session ----
        $title = $this->build_title($rec);

        // ---- 2. Create the video object on Bunny ----
        $video_guid = $this->create_video($title);

        // ---- 3. Upload the file bytes ----
        $this->upload_file($video_guid, $local_path);

        // ---- 4. Set collection if configured ----
        if ($this->collection_id) {
            $this->set_collection($video_guid, $this->collection_id);
        }

        // ---- 5. Poll until encoding finishes ----
        $this->wait_for_encoding($video_guid, $recordingid);

        // ---- 6. Fetch final metadata (thumbnail, duration) ----
        $meta = $this->fetch_video_metadata($video_guid);

        // ---- 7. Build signed embed URL ----
        $embed_url     = $this->build_signed_embed_url($video_guid);
        $video_url     = $this->build_video_url($video_guid);
        $thumbnail_url = $this->build_thumbnail_url($video_guid);

        return new bunny_upload_result(
            $video_guid,
            $embed_url,
            $video_url,
            $thumbnail_url,
            (int)($meta['length']      ?? 0),
            (int)($meta['storageSize'] ?? 0)
        );
    }

    // ---------------------------------------------------------------
    // Step 2: Create video object
    // ---------------------------------------------------------------

    /**
     * POST /library/{libraryId}/videos
     * Returns the new videoId (GUID string).
     */
    private function create_video(string $title): string {
        $body = ['title' => $title];
        if ($this->collection_id) {
            $body['collectionId'] = $this->collection_id;
        }

        $resp = $this->api_call('POST',
            self::VIDEO_API_BASE . "/{$this->library_id}/videos",
            $body
        );

        $guid = $resp['guid'] ?? $resp['videoId'] ?? null;
        if (!$guid) {
            throw new \RuntimeException(
                'Bunny create_video: no guid in response — ' . json_encode($resp)
            );
        }

        return $guid;
    }

    // ---------------------------------------------------------------
    // Step 3: Upload file bytes
    // ---------------------------------------------------------------

    /**
     * PUT /library/{libraryId}/videos/{videoId}
     * Streams the local file directly to Bunny using chunked transfer.
     * No temp copy on server — streams straight from disk.
     */
    private function upload_file(string $video_guid, string $local_path): void {
        $filesize = filesize($local_path);
        if ($filesize === false || $filesize === 0) {
            throw new \RuntimeException("Cannot read file or file is empty: {$local_path}");
        }

        $url = self::VIDEO_API_BASE . "/{$this->library_id}/videos/{$video_guid}";
        $fp  = fopen($local_path, 'rb');
        if (!$fp) {
            throw new \RuntimeException("Cannot open file for reading: {$local_path}");
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_INFILE         => $fp,
            CURLOPT_INFILESIZE     => $filesize,
            CURLOPT_UPLOAD         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => max($this->encode_timeout, 7200),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'AccessKey: ' . $this->library_api_key,
                'Content-Type: application/octet-stream',
                'Content-Length: ' . $filesize,
            ],
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($curl_err) {
            throw new \RuntimeException("Bunny upload_file curl error: {$curl_err}");
        }

        // Bunny returns 200 or 201 on success.
        if (!in_array($http_code, [200, 201])) {
            throw new \RuntimeException(
                "Bunny upload_file failed: HTTP {$http_code} — " . substr($response, 0, 500)
            );
        }
    }

    // ---------------------------------------------------------------
    // Step 4 (optional): Assign to a collection
    // ---------------------------------------------------------------

    private function set_collection(string $video_guid, string $collection_id): void {
        try {
            $this->api_call('POST',
                self::VIDEO_API_BASE . "/{$this->library_id}/videos/{$video_guid}",
                ['collectionId' => $collection_id]
            );
        } catch (\Throwable $e) {
            // Non-fatal — log and continue.
            debugging("bunny_uploader: set_collection failed for {$video_guid}: " . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // Step 5: Poll encoding status
    // ---------------------------------------------------------------

    /**
     * Polls GET /library/{libraryId}/videos/{videoId} every 10 seconds
     * until encoding finishes (status 4) or the timeout elapses.
     *
     * Bunny encoding statuses:
     *   0 = Queued, 1 = Processing/Moving, 2 = Encoding,
     *   3 = Failed, 4 = Finished, 5 = Uploading captions, 6 = Deleted
     *
     * @throws \RuntimeException if encoding fails or timeout exceeded.
     */
    private function wait_for_encoding(string $video_guid, int $recordingid): void {
        global $DB;

        $deadline  = time() + $this->encode_timeout;
        $poll_interval = 10; // seconds between polls
        $last_status   = -1;

        mtrace("  Bunny: waiting for encoding of {$video_guid} (timeout={$this->encode_timeout}s)");

        while (time() < $deadline) {
            sleep($poll_interval);

            $meta = $this->fetch_video_metadata($video_guid);
            $status   = (int)($meta['status']          ?? -1);
            $progress = (int)($meta['encodeProgress']  ?? 0);
            $http     = (int)($meta['__http_code']     ?? 200);

            // Log every poll to bunny_polls table.
            $poll = new \stdClass();
            $poll->recordingid   = $recordingid;
            $poll->video_guid    = $video_guid;
            $poll->enc_status    = $this->status_name($status);
            $poll->enc_progress  = $progress;
            $poll->response_code = $http;
            $poll->timecreated   = time();
            $DB->insert_record('livesessions_bunny_polls', $poll);

            // Update recording row with current encoding progress.
            $DB->set_field('livesessions_recordings', 'encoding_status',
                $this->status_name($status), ['id' => $recordingid]);
            $DB->set_field('livesessions_recordings', 'encoding_progress',
                $progress, ['id' => $recordingid]);

            if ($status !== $last_status) {
                mtrace("  Bunny encoding: status={$this->status_name($status)} progress={$progress}%");
                $last_status = $status;
            }

            if ($status === self::ENC_FINISHED) {
                mtrace("  Bunny encoding complete.");
                return;
            }

            if ($status === self::ENC_FAILED || $status === self::ENC_DELETED) {
                throw new \RuntimeException(
                    "Bunny encoding failed for video {$video_guid} (status={$status})"
                );
            }

            // Increase poll interval for long-running videos (avoid hammering API).
            if ($progress < 10)  $poll_interval = 10;
            elseif ($progress < 50) $poll_interval = 15;
            else                    $poll_interval = 20;
        }

        throw new \RuntimeException(
            "Bunny encoding timeout after {$this->encode_timeout}s for video {$video_guid}"
        );
    }

    // ---------------------------------------------------------------
    // Step 6: Fetch final metadata
    // ---------------------------------------------------------------

    /**
     * GET /library/{libraryId}/videos/{videoId}
     * Returns decoded JSON array; adds '__http_code' key.
     */
    public function fetch_video_metadata(string $video_guid): array {
        $url = self::VIDEO_API_BASE . "/{$this->library_id}/videos/{$video_guid}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['AccessKey: ' . $this->library_api_key],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException("Bunny fetch_video_metadata curl error: {$err}");
        }
        if ($code !== 200) {
            throw new \RuntimeException("Bunny fetch_video_metadata HTTP {$code}: " . substr($body, 0, 300));
        }

        $data = json_decode($body, true) ?? [];
        $data['__http_code'] = $code;
        return $data;
    }

    // ---------------------------------------------------------------
    // Step 7: Build URLs
    // ---------------------------------------------------------------

    /**
     * Build a signed (token-authenticated) embed URL.
     *
     * Token format (Bunny Token Auth v1):
     *   token  = SHA-256( tokenAuthKey + videoGuid + expiry + [clientIp] )
     *   URL    = https://iframe.mediadelivery.net/embed/{libraryId}/{guid}
     *              ?token={token}&expires={expiry}
     *
     * This makes each embed URL valid for $this->token_ttl seconds only.
     * Students cannot share the URL — it expires quickly.
     */
    public function build_signed_embed_url(
        string $video_guid,
        int    $expiry    = 0,
        string $client_ip = ''
    ): string {
        if ($expiry === 0) {
            $expiry = time() + $this->token_ttl;
        }

        if (empty($this->token_auth_key)) {
            // No token auth configured — return unsigned URL (dev mode).
            return self::STREAM_BASE . "/{$this->library_id}/{$video_guid}?autoplay=false";
        }

        // Compute token: SHA256( key + "/" + guid + expiry + [ip] )
        $hash_base = $this->token_auth_key . '/' . $video_guid . $expiry . $client_ip;
        $token     = base64_encode(hex2bin(hash('sha256', $hash_base)));
        // Bunny expects URL-safe base64.
        $token = str_replace(['+', '/', '='], ['-', '_', ''], $token);

        $params = http_build_query([
            'token'   => $token,
            'expires' => $expiry,
        ]);

        return self::STREAM_BASE . "/{$this->library_id}/{$video_guid}?{$params}";
    }

    /**
     * Build the direct MP4 URL via the pull-zone CDN.
     * Requires CDN hostname to be configured.
     */
    public function build_video_url(string $video_guid): string {
        if (empty($this->cdn_hostname)) {
            return '';
        }
        return "https://{$this->cdn_hostname}/{$video_guid}/play.mp4";
    }

    /**
     * Build thumbnail URL (Bunny auto-generates at /thumbnail.jpg).
     */
    public function build_thumbnail_url(string $video_guid): string {
        if (empty($this->cdn_hostname)) {
            return '';
        }
        return "https://{$this->cdn_hostname}/{$video_guid}/thumbnail.jpg";
    }

    // ---------------------------------------------------------------
    // Delete a video (for cleanup / re-upload)
    // ---------------------------------------------------------------

    /**
     * DELETE /library/{libraryId}/videos/{videoId}
     */
    public function delete_video(string $video_guid): bool {
        try {
            $this->api_call('DELETE',
                self::VIDEO_API_BASE . "/{$this->library_id}/videos/{$video_guid}"
            );
            return true;
        } catch (\Throwable $e) {
            debugging("bunny_uploader: delete_video {$video_guid} failed: " . $e->getMessage());
            return false;
        }
    }

    // ---------------------------------------------------------------
    // Regenerate a fresh signed embed URL (called per-request for students)
    // ---------------------------------------------------------------

    /**
     * Called by the player page to issue a short-lived signed URL per student.
     * Optionally locks URL to student's IP address.
     *
     * @param  string $video_guid
     * @param  string $client_ip   Optional — lock URL to this IP
     * @param  int    $ttl_seconds Override default TTL
     * @return string
     */
    public function fresh_signed_url(string $video_guid, string $client_ip = '', int $ttl_seconds = 0): string {
        $ttl    = $ttl_seconds > 0 ? $ttl_seconds : $this->token_ttl;
        $expiry = time() + $ttl;
        return $this->build_signed_embed_url($video_guid, $expiry, $client_ip);
    }

    // ---------------------------------------------------------------
    // Private: generic API call helper
    // ---------------------------------------------------------------

    /**
     * Make a REST call to the Bunny Stream API.
     * Returns decoded JSON body.
     *
     * @param  string $method  GET | POST | PUT | PATCH | DELETE
     * @param  string $url
     * @param  array  $body    Will be JSON-encoded for non-GET requests.
     * @return array
     * @throws \RuntimeException on HTTP error.
     */
    private function api_call(string $method, string $url, array $body = []): array {
        $ch = curl_init($url);

        $headers = [
            'AccessKey: ' . $this->library_api_key,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ($body && $method !== 'GET') {
            $json = json_encode($body);
            $opts[CURLOPT_POSTFIELDS]  = $json;
        }

        curl_setopt_array($ch, $opts);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            throw new \RuntimeException("Bunny API curl error ({$method} {$url}): {$curl_err}");
        }

        if ($http_code >= 400) {
            throw new \RuntimeException(
                "Bunny API error: {$method} {$url} → HTTP {$http_code}: " . substr($response, 0, 500)
            );
        }

        return $response ? (json_decode($response, true) ?? []) : [];
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function validate_config(): void {
        if (empty($this->library_id)) {
            throw new \RuntimeException('Bunny Stream library ID not configured (bunny_library_id)');
        }
        if (empty($this->library_api_key)) {
            throw new \RuntimeException('Bunny Stream library API key not configured (bunny_library_api_key)');
        }
    }

    private function build_title(\stdClass $rec): string {
        global $DB;
        $session = $DB->get_record('livesessions_sessions', ['id' => $rec->sessionid]);
        $title   = $session ? format_string($session->title) : 'Session ' . $rec->sessionid;
        // Append date to make it uniquely identifiable in Bunny dashboard.
        return $title . ' — ' . userdate($rec->timecreated, '%Y-%m-%d');
    }

    private function status_name(int $code): string {
        $map = [
            self::ENC_QUEUED   => 'queued',
            self::ENC_MOVING   => 'moving',
            self::ENC_ENCODING => 'encoding',
            self::ENC_FAILED   => 'failed',
            self::ENC_FINISHED => 'finished',
            self::ENC_CAPTIONS => 'captions',
            self::ENC_DELETED  => 'deleted',
        ];
        return $map[$code] ?? 'unknown';
    }
}
