<?php
/**
 * local_livesessions - Library functions hooked into Moodle core.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add links to the site navigation (admin dashboard + reports).
 */
function local_livesessions_extend_navigation(global_navigation $nav) {
    global $PAGE;

    if (!has_capability('local/livesessions:manageSessions', context_system::instance())) {
        return;
    }

    $node = $nav->add(
        get_string('pluginname', 'local_livesessions'),
        new moodle_url('/local/livesessions/dashboard.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'livesessions_admin',
        new pix_icon('i/report', '')
    );

    $node->add(
        get_string('dashboard', 'local_livesessions'),
        new moodle_url('/local/livesessions/dashboard.php'),
        navigation_node::TYPE_CUSTOM,
        null, 'livesessions_dashboard'
    );

    $node->add(
        get_string('reports', 'local_livesessions'),
        new moodle_url('/local/livesessions/reports.php'),
        navigation_node::TYPE_CUSTOM,
        null, 'livesessions_reports'
    );

    $node->add(
        get_string('packages', 'local_livesessions'),
        new moodle_url('/local/livesessions/packages.php'),
        navigation_node::TYPE_CUSTOM,
        null, 'livesessions_packages'
    );

    $node->add(
        get_string('recordingqueue', 'local_livesessions'),
        new moodle_url('/local/livesessions/recordings.php'),
        navigation_node::TYPE_CUSTOM,
        null, 'livesessions_recordings'
    );
}

/**
 * Add "Live Sessions" node to the course navigation.
 */
function local_livesessions_extend_navigation_course(navigation_node $parentnode, stdClass $course,
        context_course $context) {

    if (has_capability('local/livesessions:createSession', $context) ||
        has_capability('local/livesessions:joinSession', $context)) {

        $url = new moodle_url('/local/livesessions/index.php', ['courseid' => $course->id]);
        $parentnode->add(
            get_string('pluginname', 'local_livesessions'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'livesessions',
            new pix_icon('i/calendar', '')
        );
    }
}
