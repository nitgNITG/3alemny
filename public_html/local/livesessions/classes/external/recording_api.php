<?php
/**
 * External API for recording webhook + access grant.
 * Stub — full implementation in Feature 2.x.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_livesessions\attendance_manager;

class recording_api extends external_api {

    // ---------------------------------------------------------------
    // upload_recording  (called by provider webhook)
    // ---------------------------------------------------------------

    public static function upload_recording_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid'    => new external_value(PARAM_INT,  'Session ID'),
            'provider'     => new external_value(PARAM_ALPHA,'bunny or vdocipher'),
            'external_id'  => new external_value(PARAM_RAW,  'Video ID on the provider'),
            'video_url'    => new external_value(PARAM_URL,  'Direct video URL',  VALUE_DEFAULT, ''),
            'embed_url'    => new external_value(PARAM_URL,  'Embed URL',         VALUE_DEFAULT, ''),
            'webhook_token'=> new external_value(PARAM_RAW,  'Shared secret for webhook validation'),
        ]);
    }

    public static function upload_recording(int $sessionid, string $provider, string $external_id,
            string $video_url, string $embed_url, string $webhook_token): array {

        $params = self::validate_parameters(self::upload_recording_parameters(), compact(
            'sessionid','provider','external_id','video_url','embed_url','webhook_token'
        ));

        // Validate webhook token.
        $expected = get_config('local_livesessions', 'webhook_secret');
        if (empty($expected) || !hash_equals($expected, $params['webhook_token'])) {
            throw new \moodle_exception('invalidwebhooktoken', 'local_livesessions');
        }

        global $DB;
        $DB->set_field('livesessions_sessions', 'recording_status', 'processing',
            ['id' => $params['sessionid']]);

        // Insert recording record.
        $rec = new \stdClass();
        $rec->sessionid   = $params['sessionid'];
        $rec->provider    = $params['provider'];
        $rec->external_id = $params['external_id'];
        $rec->video_url   = $params['video_url'];
        $rec->embed_url   = $params['embed_url'];
        $rec->status      = 'processing';
        $rec->timecreated = time();
        $rec->timemodified = time();
        $recid = $DB->insert_record('livesessions_recordings', $rec);

        // Queue upload task (Feature 2.x will flesh this out).
        \local_livesessions\task\process_recording::queue($params['sessionid'], $recid);

        return ['success' => true, 'recordingid' => $recid];
    }

    public static function upload_recording_returns(): external_single_structure {
        return new external_single_structure([
            'success'     => new external_value(PARAM_BOOL, 'Success'),
            'recordingid' => new external_value(PARAM_INT,  'Recording record ID'),
        ]);
    }

    // ---------------------------------------------------------------
    // grant_access  (admin/teacher manual override)
    // ---------------------------------------------------------------

    public static function grant_access_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session ID'),
            'userid'    => new external_value(PARAM_INT, 'Student user ID'),
        ]);
    }

    public static function grant_access(int $sessionid, int $userid): array {
        $params = self::validate_parameters(self::grant_access_parameters(),
            compact('sessionid','userid'));

        global $DB;
        $session = $DB->get_record('livesessions_sessions', ['id' => $params['sessionid']], '*', MUST_EXIST);
        $context = \context_course::instance($session->courseid);
        self::validate_context($context);
        require_capability('local/livesessions:manageSessions', \context_system::instance());

        $result = attendance_manager::grant_recording_access($params['sessionid'], $params['userid']);

        return ['success' => $result];
    }

    public static function grant_access_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
