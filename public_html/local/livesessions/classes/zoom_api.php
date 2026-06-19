<?php
/**
 * Zoom Server-to-Server OAuth API client.
 *
 * Used to auto-create Zoom meetings when a teacher approves a private session request.
 *
 * Credentials required (set in plugin settings):
 *   zoom_api_account_id    — from Zoom Marketplace → Server-to-Server OAuth app
 *   zoom_api_client_id     — from the same app
 *   zoom_api_client_secret — from the same app
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class zoom_api {

    const TOKEN_URL   = 'https://zoom.us/oauth/token';
    const API_BASE    = 'https://api.zoom.us/v2';

    /** @var string */
    private $account_id;
    /** @var string */
    private $client_id;
    /** @var string */
    private $client_secret;
    /** @var string|null  cached for this request lifetime */
    private $access_token = null;

    public function __construct() {
        $this->account_id    = (string)(get_config('local_livesessions', 'zoom_api_account_id')    ?: '');
        $this->client_id     = (string)(get_config('local_livesessions', 'zoom_api_client_id')     ?: '');
        $this->client_secret = (string)(get_config('local_livesessions', 'zoom_api_client_secret') ?: '');

        if (!$this->account_id || !$this->client_id || !$this->client_secret) {
            throw new \moodle_exception('zoom_not_configured', 'local_livesessions');
        }
    }

    // ---------------------------------------------------------------
    // Public: create a Zoom meeting
    // ---------------------------------------------------------------

    /**
     * Create a scheduled Zoom meeting and return join/host URLs.
     *
     * @param  string $topic        Meeting title
     * @param  int    $start_unix   Start time as Unix timestamp (UTC)
     * @param  int    $duration     Duration in minutes
     * @return array  ['join_url'=>string, 'host_url'=>string, 'meeting_id'=>string, 'password'=>string]
     */
    public function create_meeting(string $topic, int $start_unix, int $duration = 60): array {
        $token = $this->get_access_token();

        $body = json_encode([
            'topic'      => $topic,
            'type'       => 2,
            'start_time' => gmdate('Y-m-d\TH:i:s\Z', $start_unix),
            'duration'   => $duration,
            'timezone'   => 'UTC',
            'settings'   => [
                'waiting_room'      => false,
                'join_before_host'  => false,
                'mute_upon_entry'   => false,
                'participant_video' => true,
                'host_video'        => true,
                'auto_recording'    => 'cloud',
            ],
        ]);

        $response = $this->api_post('/users/me/meetings', $body, $token);

        if (empty($response['join_url'])) {
            throw new \moodle_exception('zoom_create_meeting_error', 'local_livesessions',
                '', null, json_encode($response));
        }

        return [
            'join_url'   => $response['join_url'],
            'host_url'   => $response['start_url'],
            'meeting_id' => (string)($response['id'] ?? ''),
            'password'   => $response['password'] ?? '',
        ];
    }

    // ---------------------------------------------------------------
    // Private: OAuth token
    // ---------------------------------------------------------------

    private function get_access_token(): string {
        if ($this->access_token !== null) {
            return $this->access_token;
        }

        $url  = self::TOKEN_URL
              . '?grant_type=account_credentials'
              . '&account_id=' . urlencode($this->account_id);

        $basic = base64_encode($this->client_id . ':' . $this->client_secret);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $basic,
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \moodle_exception('zoom_token_error', 'local_livesessions', '', null, $err);
        }

        $data = json_decode($raw, true) ?? [];
        if (empty($data['access_token'])) {
            throw new \moodle_exception('zoom_token_error', 'local_livesessions', '', null, $raw);
        }

        $this->access_token = $data['access_token'];
        return $this->access_token;
    }

    // ---------------------------------------------------------------
    // Private: POST helper
    // ---------------------------------------------------------------

    private function api_post(string $path, string $body, string $token): array {
        $url = self::API_BASE . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException('Zoom API cURL error: ' . $err);
        }
        if ($code >= 400) {
            throw new \RuntimeException('Zoom API HTTP ' . $code . ': ' . substr($raw, 0, 300));
        }

        return json_decode($raw, true) ?? [];
    }
}
