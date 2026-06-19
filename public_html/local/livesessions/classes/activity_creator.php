<?php
/**
 * Activity Creator — Feature 2.4.
 *
 * Automatically creates a Moodle "url" resource activity in the course after a
 * recording is marked ready. This makes the recording visible in the course
 * content tree alongside other activities without requiring manual teacher action.
 *
 * Behaviour:
 *   - Creates a mod_url pointing to player.php (Bunny) or vdocipher_player.php.
 *   - If a recording activity already exists (moodle_cmid set), skips silently.
 *   - Stores the created cmid in livesessions_recordings.moodle_cmid.
 *   - Respects a site-wide toggle: local_livesessions/auto_create_activity.
 *   - Places the activity in the first available course section (section 0 = general).
 *   - Activity name = "[Recording] {session title}".
 *
 * This class calls core Moodle functions (course_create_module) and MUST be run
 * from a task or server-side context (not from a webhook before require_once config).
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class activity_creator {

    /**
     * Create a Moodle URL activity for a completed recording (if not already created).
     *
     * @param  int $recordingid  Row id in livesessions_recordings.
     * @return int|null          The course module id (cmid) of the created activity,
     *                           or null if auto-creation is disabled / already exists.
     */
    public static function create_for_recording(int $recordingid): ?int {
        global $DB, $CFG;

        // ---- Feature toggle ----
        if (!get_config('local_livesessions', 'auto_create_activity')) {
            return null;
        }

        // ---- Load recording ----
        $rec = $DB->get_record('livesessions_recordings', ['id' => $recordingid]);
        if (!$rec || $rec->status !== 'ready') {
            return null;
        }

        // ---- Already created ----
        if (!empty($rec->moodle_cmid)) {
            return (int)$rec->moodle_cmid;
        }

        // ---- Load session ----
        $session = $DB->get_record('livesessions_sessions', ['id' => $rec->sessionid]);
        if (!$session) {
            return null;
        }

        // ---- Determine player URL ----
        $provider = $rec->source_provider ?? ($rec->provider ?? 'bunny');
        if ($provider === 'vdocipher') {
            $player_url = new \moodle_url('/local/livesessions/vdocipher_player.php',
                ['sessionid' => $rec->sessionid]);
        } else {
            $player_url = new \moodle_url('/local/livesessions/player.php',
                ['sessionid' => $rec->sessionid]);
        }

        // ---- Load mod_url module id ----
        $module = $DB->get_record('modules', ['name' => 'url'], 'id', MUST_EXIST);

        // ---- Determine section (default to section 0 = General) ----
        $section_num = (int)get_config('local_livesessions', 'auto_activity_section');
        $section = $DB->get_record('course_sections', [
            'course'  => $session->courseid,
            'section' => $section_num,
        ]);
        if (!$section) {
            // Section doesn't exist yet — use section 0.
            $section = $DB->get_record('course_sections', [
                'course'  => $session->courseid,
                'section' => 0,
            ]);
        }
        if (!$section) {
            debugging("activity_creator: no section found for course {$session->courseid}", DEBUG_DEVELOPER);
            return null;
        }

        $activity_name = '[Recording] ' . $session->title;

        // ---- Create the course module row ----
        $cm                = new \stdClass();
        $cm->course        = $session->courseid;
        $cm->module        = $module->id;
        $cm->instance      = 0;    // filled after mod instance created
        $cm->section       = $section->id;
        $cm->visible       = 1;
        $cm->groupmode     = 0;
        $cm->groupingid    = 0;
        $cm->completion    = 0;
        $cm->indent        = 0;
        $cm->added         = time();

        // ---- Create the mod_url instance ----
        require_once($CFG->dirroot . '/mod/url/lib.php');

        $url_instance              = new \stdClass();
        $url_instance->course      = $session->courseid;
        $url_instance->name        = $activity_name;
        $url_instance->intro       = '<p>' . get_string('recording', 'local_livesessions') . '</p>';
        $url_instance->introformat = FORMAT_HTML;
        $url_instance->externalurl = $player_url->out(false);
        $url_instance->display     = RESOURCELIB_DISPLAY_NEW; // open in new window
        $url_instance->displayoptions = serialize([]);
        $url_instance->parameters  = serialize([]);
        $url_instance->timemodified = time();

        try {
            $instance_id = url_add_instance($url_instance, null);
            if (!$instance_id) {
                throw new \RuntimeException('url_add_instance returned falsy');
            }

            // ---- Create the course_module row ----
            $cm->instance = $instance_id;
            $cmid = (int)$DB->insert_record('course_modules', $cm);

            // ---- Add to section sequence ----
            $sequence = $section->sequence ? explode(',', $section->sequence) : [];
            $sequence[] = $cmid;
            $DB->set_field('course_sections', 'sequence', implode(',', $sequence), ['id' => $section->id]);

            // ---- Rebuild course cache ----
            rebuild_course_cache($session->courseid, true);

            // ---- Persist cmid on recording row ----
            $DB->set_field('livesessions_recordings', 'moodle_cmid', $cmid, ['id' => $recordingid]);

            mtrace("  [activity_creator] Created URL activity cmid={$cmid} in course {$session->courseid}");
            return $cmid;

        } catch (\Throwable $e) {
            debugging("activity_creator: failed to create activity — " . $e->getMessage(), DEBUG_DEVELOPER);
            mtrace("  [activity_creator] ERROR: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete the auto-created activity when a recording is deleted or re-uploaded.
     * Safe to call even if no activity was created (cmid = 0 / null).
     */
    public static function delete_for_recording(int $recordingid): void {
        global $DB, $CFG;

        $rec = $DB->get_record('livesessions_recordings', ['id' => $recordingid]);
        if (!$rec || empty($rec->moodle_cmid)) {
            return;
        }

        $cmid = (int)$rec->moodle_cmid;

        try {
            require_once($CFG->dirroot . '/course/lib.php');
            $cm = get_coursemodule_from_id('url', $cmid);
            if ($cm) {
                course_delete_module($cmid);
            }
            $DB->set_field('livesessions_recordings', 'moodle_cmid', null, ['id' => $recordingid]);
            mtrace("  [activity_creator] Deleted URL activity cmid={$cmid}");
        } catch (\Throwable $e) {
            debugging("activity_creator: delete failed — " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
