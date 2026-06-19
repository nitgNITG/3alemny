<?php
/**
 * Device Manager — Feature 6.1.
 *
 * Tracks which devices (identified by a device_token string) are actively
 * logged in to a session for a given student, and enforces the configurable
 * maximum concurrent-device limit.
 *
 * DB table: livesessions_device_sessions
 *   id, sessionid, userid, device_token, joined_at, left_at (nullable),
 *   ip, user_agent, timecreated, timemodified
 *
 * A device slot is "active" when left_at IS NULL.
 *
 * Public API:
 *   register_device(sessionid, userid, device_token, ip, ua) — join; throws if limit hit
 *   release_device(sessionid, userid, device_token)           — leave
 *   count_active_devices(sessionid, userid)                   — current slot count
 *   get_active_devices(sessionid, userid)                     — full records
 *   release_all_for_user(sessionid, userid)                   — force-release (admin)
 *   cleanup_stale(max_age_seconds)                            — cron helper
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class device_manager {

    /** Default max concurrent devices when config not set. */
    const DEFAULT_MAX_DEVICES = 2;

    /** Stale-device cutoff: sessions open longer than this are auto-closed by cron (seconds). */
    const DEFAULT_STALE_AGE = 86400; // 24 hours

    // ================================================================
    // Core: register / release
    // ================================================================

    /**
     * Register a device for an active session slot.
     *
     * Idempotent: if the same device_token is already active for this
     * (sessionid, userid) pair, the existing row is refreshed (timemodified)
     * and returned without counting as a new slot.
     *
     * @param  int    $sessionid
     * @param  int    $userid
     * @param  string $device_token  Stable device identifier sent by the mobile app.
     * @param  string $ip            Client IP address (for audit).
     * @param  string $user_agent    Client User-Agent string (for audit).
     * @return \stdClass             The device_sessions row.
     * @throws \moodle_exception     When the slot limit is already reached.
     */
    public static function register_device(int $sessionid, int $userid,
                                           string $device_token,
                                           string $ip = '', string $user_agent = ''): \stdClass {
        global $DB;

        // Idempotency: device already active for this session?
        $existing = $DB->get_record('livesessions_device_sessions', [
            'sessionid'    => $sessionid,
            'userid'       => $userid,
            'device_token' => $device_token,
            'left_at'      => null,
        ]);

        if ($existing) {
            // Refresh heartbeat.
            $DB->set_field('livesessions_device_sessions', 'timemodified', time(), ['id' => $existing->id]);
            $existing->timemodified = time();
            return $existing;
        }

        // Enforce concurrent-device limit.
        $max = (int)(get_config('local_livesessions', 'max_devices_per_session') ?? self::DEFAULT_MAX_DEVICES);
        if ($max > 0) {
            $active = self::count_active_devices($sessionid, $userid);
            if ($active >= $max) {
                throw new \moodle_exception('devicelimitexceeded', 'local_livesessions', '', $max);
            }
        }

        // Insert new slot.
        $row                = new \stdClass();
        $row->sessionid     = $sessionid;
        $row->userid        = $userid;
        $row->device_token  = $device_token;
        $row->joined_at     = time();
        $row->left_at       = null;
        $row->ip            = $ip;
        $row->user_agent    = substr($user_agent, 0, 255);
        $row->timecreated   = time();
        $row->timemodified  = time();

        $row->id = $DB->insert_record('livesessions_device_sessions', $row);
        return $row;
    }

    /**
     * Mark a device slot as released (left_at = now).
     *
     * @param  int    $sessionid
     * @param  int    $userid
     * @param  string $device_token
     * @return bool   true if a row was updated, false if not found.
     */
    public static function release_device(int $sessionid, int $userid, string $device_token): bool {
        global $DB;

        $row = $DB->get_record('livesessions_device_sessions', [
            'sessionid'    => $sessionid,
            'userid'       => $userid,
            'device_token' => $device_token,
            'left_at'      => null,
        ]);

        if (!$row) {
            return false;
        }

        $DB->set_field('livesessions_device_sessions', 'left_at',      time(), ['id' => $row->id]);
        $DB->set_field('livesessions_device_sessions', 'timemodified',  time(), ['id' => $row->id]);
        return true;
    }

    // ================================================================
    // Query helpers
    // ================================================================

    /**
     * Count active (left_at IS NULL) device slots for a student in a session.
     */
    public static function count_active_devices(int $sessionid, int $userid): int {
        global $DB;
        return (int)$DB->count_records_select(
            'livesessions_device_sessions',
            'sessionid = :sid AND userid = :uid AND left_at IS NULL',
            ['sid' => $sessionid, 'uid' => $userid]
        );
    }

    /**
     * Return all active device rows for a student in a session.
     *
     * @return \stdClass[]
     */
    public static function get_active_devices(int $sessionid, int $userid): array {
        global $DB;
        return array_values($DB->get_records_select(
            'livesessions_device_sessions',
            'sessionid = :sid AND userid = :uid AND left_at IS NULL',
            ['sid' => $sessionid, 'uid' => $userid],
            'joined_at ASC'
        ));
    }

    // ================================================================
    // Admin helpers
    // ================================================================

    /**
     * Force-release all active device slots for a student in a session.
     * Called by admin override or when a session is finalised.
     *
     * @return int  Number of rows updated.
     */
    public static function release_all_for_user(int $sessionid, int $userid): int {
        global $DB;

        $rows = $DB->get_records_select(
            'livesessions_device_sessions',
            'sessionid = :sid AND userid = :uid AND left_at IS NULL',
            ['sid' => $sessionid, 'uid' => $userid]
        );

        $count = 0;
        foreach ($rows as $row) {
            $DB->set_field('livesessions_device_sessions', 'left_at',     time(), ['id' => $row->id]);
            $DB->set_field('livesessions_device_sessions', 'timemodified', time(), ['id' => $row->id]);
            $count++;
        }
        return $count;
    }

    /**
     * Force-release all active slots for every student in a session.
     * Called when a session is marked completed/cancelled.
     *
     * @return int  Total rows updated.
     */
    public static function release_all_for_session(int $sessionid): int {
        global $DB;

        $rows = $DB->get_records_select(
            'livesessions_device_sessions',
            'sessionid = :sid AND left_at IS NULL',
            ['sid' => $sessionid]
        );

        $count = 0;
        foreach ($rows as $row) {
            $DB->set_field('livesessions_device_sessions', 'left_at',     time(), ['id' => $row->id]);
            $DB->set_field('livesessions_device_sessions', 'timemodified', time(), ['id' => $row->id]);
            $count++;
        }
        return $count;
    }

    // ================================================================
    // Cron: clean up ghost sessions
    // ================================================================

    /**
     * Auto-close device slots that have been open longer than $max_age_seconds.
     * Runs as part of the cleanup_tokens scheduled task.
     *
     * @param  int $max_age_seconds  Default: DEFAULT_STALE_AGE (24 h).
     * @return int  Number of rows closed.
     */
    public static function cleanup_stale(int $max_age_seconds = self::DEFAULT_STALE_AGE): int {
        global $DB;

        $cutoff = time() - $max_age_seconds;
        $rows   = $DB->get_records_select(
            'livesessions_device_sessions',
            'left_at IS NULL AND joined_at < :cutoff',
            ['cutoff' => $cutoff]
        );

        $count = 0;
        foreach ($rows as $row) {
            $DB->set_field('livesessions_device_sessions', 'left_at',     time(), ['id' => $row->id]);
            $DB->set_field('livesessions_device_sessions', 'timemodified', time(), ['id' => $row->id]);
            $count++;
        }
        return $count;
    }

    // ================================================================
    // Feature 6.2 — Watermark annotation builder
    // ================================================================

    /**
     * Build the VdoCipher annotation array for a student.
     *
     * Controlled by the 'watermark_mode' config:
     *   off         → []  (no annotations)
     *   name        → student's full name
     *   namephone   → full name + phone number (profile field 'phone1')
     *
     * The annotation is passed directly to vdocipher_uploader::generate_otp().
     *
     * @param  int $userid
     * @return array  VdoCipher annotation array or empty array.
     */
    public static function build_watermark_annotations(int $userid): array {
        $mode = get_config('local_livesessions', 'watermark_mode') ?: 'off';

        if ($mode === 'off') {
            return [];
        }

        global $DB;
        $user = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,phone1');
        if (!$user) {
            return [];
        }

        $name  = trim($user->firstname . ' ' . $user->lastname);
        $phone = $user->phone1 ?? '';

        $text  = ($mode === 'namephone' && $phone) ? "{$name} | {$phone}" : $name;

        return [
            [
                'type'     => 'rtext',
                'text'     => $text,
                'alpha'    => '0.60',
                'color'    => '0xFF0000',
                'size'     => 15,
                'interval' => 5000,
            ],
        ];
    }
}
