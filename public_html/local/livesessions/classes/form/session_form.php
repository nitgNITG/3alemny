<?php
/**
 * Moodle form for creating/editing a live session.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class session_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $editing = !empty($this->_customdata['session']);

        // ------- Hidden -------
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        // ------- Basic Info -------
        $mform->addElement('header', 'basic', get_string('sessiondetails', 'local_livesessions'));

        $mform->addElement('text', 'title', get_string('title', 'local_livesessions'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('editor', 'description_editor', get_string('description', 'local_livesessions'));
        $mform->setType('description_editor', PARAM_RAW);

        // ------- Provider -------
        $mform->addElement('header', 'provider_header', get_string('provider', 'local_livesessions'));

        $providers = [
            'zoom'          => 'Zoom',
            '100ms'         => '100ms',
            'agora'         => 'Agora',
            'bigbluebutton' => 'BigBlueButton',
        ];
        $mform->addElement('select', 'provider', get_string('provider', 'local_livesessions'), $providers);
        $mform->setDefault('provider', 'zoom');

        $mform->addElement('text', 'provider_meeting_id',
            get_string('provider_meeting_id', 'local_livesessions'), ['size' => 60]);
        $mform->setType('provider_meeting_id', PARAM_RAW);

        $mform->addElement('text', 'join_url', get_string('join_url', 'local_livesessions'), ['size' => 80]);
        $mform->setType('join_url', PARAM_URL);

        $mform->addElement('text', 'host_url', get_string('host_url', 'local_livesessions'), ['size' => 80]);
        $mform->setType('host_url', PARAM_URL);

        // ------- Schedule -------
        $mform->addElement('header', 'schedule_header', get_string('schedule', 'local_livesessions'));

        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'local_livesessions'));
        $mform->addRule('starttime', null, 'required', null, 'client');

        $mform->addElement('date_time_selector', 'endtime', get_string('endtime', 'local_livesessions'));
        $mform->addRule('endtime', null, 'required', null, 'client');

        // ------- Capacity -------
        $mform->addElement('text', 'capacity', get_string('capacity', 'local_livesessions'), ['size' => 6]);
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 0);
        $mform->addHelpButton('capacity', 'capacity', 'local_livesessions');

        // ------- Teacher assignment (admin only) -------
        if (has_capability('local/livesessions:manageSessions', \context_system::instance())) {
            $mform->addElement('header', 'teacher_header', get_string('teacher', 'local_livesessions'));
            $mform->addElement('text', 'teacherid', get_string('teacherid', 'local_livesessions'), ['size' => 10]);
            $mform->setType('teacherid', PARAM_INT);
        }

        // ------- Buttons -------
        $this->add_action_buttons(true, $editing
            ? get_string('updatesession', 'local_livesessions')
            : get_string('createsession', 'local_livesessions'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['starttime']) && !empty($data['endtime'])) {
            if ($data['endtime'] <= $data['starttime']) {
                $errors['endtime'] = get_string('endbeforestart', 'local_livesessions');
            }
        }

        return $errors;
    }
}
