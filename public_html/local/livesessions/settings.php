<?php
/**
 * Admin settings for local_livesessions.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_livesessions',
        get_string('pluginname', 'local_livesessions')
    );

    $ADMIN->add('localplugins', $settings);

    // ---------------------------------------------------------------
    // Attendance threshold
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_configtext(
        'local_livesessions/attendance_threshold',
        get_string('attendance_threshold',      'local_livesessions'),
        get_string('attendance_threshold_desc', 'local_livesessions'),
        70,         // default: 70 %
        PARAM_INT
    ));

    // ---------------------------------------------------------------
    // Zoom Meeting SDK credentials (for embedded room)
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_livesessions/zoom_sdk_heading',
        get_string('zoom_sdk_heading', 'local_livesessions'),
        get_string('zoom_sdk_heading_desc', 'local_livesessions')
    ));

    $settings->add(new admin_setting_configtext(
        'local_livesessions/zoom_sdk_key',
        get_string('zoom_sdk_key',      'local_livesessions'),
        get_string('zoom_sdk_key_desc', 'local_livesessions'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_livesessions/zoom_sdk_secret',
        get_string('zoom_sdk_secret',      'local_livesessions'),
        get_string('zoom_sdk_secret_desc', 'local_livesessions'),
        ''
    ));

    // ---------------------------------------------------------------
    // Webhook secret
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_configpasswordunmask(
        'local_livesessions/webhook_secret',
        get_string('webhook_secret',      'local_livesessions'),
        get_string('webhook_secret_desc', 'local_livesessions'),
        ''
    ));

    // ---------------------------------------------------------------
    // Grace period before cron finalises attendance
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_configtext(
        'local_livesessions/finalise_grace_minutes',
        get_string('finalise_grace_minutes',      'local_livesessions'),
        get_string('finalise_grace_minutes_desc', 'local_livesessions'),
        5,
        PARAM_INT
    ));

    // ---------------------------------------------------------------
    // Credit mode (how recording access is granted)
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_configselect(
        'local_livesessions/credit_mode',
        get_string('credit_mode',      'local_livesessions'),
        get_string('credit_mode_desc', 'local_livesessions'),
        'attendance',
        [
            'attendance' => get_string('credit_mode_attendance', 'local_livesessions'),
            'credit'     => get_string('credit_mode_credit',     'local_livesessions'),
        ]
    ));

    // ---------------------------------------------------------------
    // Recording upload provider
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_configselect(
        'local_livesessions/upload_provider',
        get_string('upload_provider',      'local_livesessions'),
        get_string('upload_provider_desc', 'local_livesessions'),
        'bunny',
        ['bunny' => 'Bunny Stream', 'vdocipher' => 'VdoCipher']
    ));

    // ---------------------------------------------------------------
    // Bunny Stream settings (visible only when upload_provider = bunny)
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_configtext(
        'local_livesessions/bunny_library_id',
        get_string('bunny_library_id',      'local_livesessions'),
        get_string('bunny_library_id_desc', 'local_livesessions'),
        '',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_livesessions/bunny_library_api_key',
        get_string('bunny_library_api_key',      'local_livesessions'),
        get_string('bunny_library_api_key_desc', 'local_livesessions'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_livesessions/bunny_cdn_hostname',
        get_string('bunny_cdn_hostname',      'local_livesessions'),
        get_string('bunny_cdn_hostname_desc', 'local_livesessions'),
        '',
        PARAM_HOST
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_livesessions/bunny_token_auth_key',
        get_string('bunny_token_auth_key',      'local_livesessions'),
        get_string('bunny_token_auth_key_desc', 'local_livesessions'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_livesessions/bunny_token_ttl',
        get_string('bunny_token_ttl',      'local_livesessions'),
        get_string('bunny_token_ttl_desc', 'local_livesessions'),
        14400,   // 4 hours default
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_livesessions/bunny_collection_id',
        get_string('bunny_collection_id',      'local_livesessions'),
        get_string('bunny_collection_id_desc', 'local_livesessions'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_livesessions/bunny_allowed_referer',
        get_string('bunny_allowed_referer',      'local_livesessions'),
        get_string('bunny_allowed_referer_desc', 'local_livesessions'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_livesessions/bunny_encode_timeout',
        get_string('bunny_encode_timeout',      'local_livesessions'),
        get_string('bunny_encode_timeout_desc', 'local_livesessions'),
        3600,
        PARAM_INT
    ));

    // Bunny webhook secret (for encoding-complete callbacks from Bunny dashboard).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_livesessions/webhook_secret_bunny',
        get_string('webhook_secret_bunny',      'local_livesessions'),
        get_string('webhook_secret_bunny_desc', 'local_livesessions'),
        ''
    ));

    // ---------------------------------------------------------------
    // Feature 2.4 — Auto activity creation
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_configcheckbox(
        'local_livesessions/auto_create_activity',
        get_string('auto_create_activity',      'local_livesessions'),
        get_string('auto_create_activity_desc', 'local_livesessions'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_livesessions/auto_activity_section',
        get_string('auto_activity_section',      'local_livesessions'),
        get_string('auto_activity_section_desc', 'local_livesessions'),
        0,
        PARAM_INT
    ));

    // ---------------------------------------------------------------
    // VdoCipher settings (visible only when upload_provider = vdocipher)
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_configpasswordunmask(
        'local_livesessions/vdocipher_api_secret',
        get_string('vdocipher_api_secret',      'local_livesessions'),
        get_string('vdocipher_api_secret_desc', 'local_livesessions'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_livesessions/vdocipher_folder_id',
        get_string('vdocipher_folder_id',      'local_livesessions'),
        get_string('vdocipher_folder_id_desc', 'local_livesessions'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_livesessions/vdocipher_encode_timeout',
        get_string('vdocipher_encode_timeout',      'local_livesessions'),
        get_string('vdocipher_encode_timeout_desc', 'local_livesessions'),
        3600,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_livesessions/vdocipher_otp_ttl',
        get_string('vdocipher_otp_ttl',      'local_livesessions'),
        get_string('vdocipher_otp_ttl_desc', 'local_livesessions'),
        300,
        PARAM_INT
    ));

    // ---------------------------------------------------------------
    // Per-provider webhook secrets (session providers)
    // ---------------------------------------------------------------
    foreach (['zoom', '100ms', 'agora', 'bigbluebutton'] as $prov) {
        $key = str_replace('-', '_', $prov);
        $settings->add(new admin_setting_configpasswordunmask(
            "local_livesessions/webhook_secret_{$key}",
            get_string("webhook_secret_{$key}",      'local_livesessions'),
            get_string("webhook_secret_{$key}_desc", 'local_livesessions'),
            ''
        ));
    }

    $settings->add(new admin_setting_configtext(
        'local_livesessions/bbb_server_url',
        get_string('bbb_server_url',      'local_livesessions'),
        get_string('bbb_server_url_desc', 'local_livesessions'),
        '',
        PARAM_URL
    ));

    // ---------------------------------------------------------------
    // Default provider
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_configselect(
        'local_livesessions/default_provider',
        get_string('default_provider',      'local_livesessions'),
        get_string('default_provider_desc', 'local_livesessions'),
        'zoom',
        [
            'zoom'          => 'Zoom',
            '100ms'         => '100ms',
            'agora'         => 'Agora',
            'bigbluebutton' => 'BigBlueButton',
        ]
    ));

    // ---------------------------------------------------------------
    // Feature 6.1 — Device limits
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_configtext(
        'local_livesessions/max_devices_per_session',
        get_string('max_devices_per_session',      'local_livesessions'),
        get_string('max_devices_per_session_desc', 'local_livesessions'),
        2,
        PARAM_INT
    ));

    // ---------------------------------------------------------------
    // Feature 6.2 — Watermark mode
    // ---------------------------------------------------------------
    $settings->add(new admin_setting_configselect(
        'local_livesessions/watermark_mode',
        get_string('watermark_mode',      'local_livesessions'),
        get_string('watermark_mode_desc', 'local_livesessions'),
        'off',
        [
            'off'       => get_string('watermark_mode_off',       'local_livesessions'),
            'name'      => get_string('watermark_mode_name',      'local_livesessions'),
            'namephone' => get_string('watermark_mode_namephone',  'local_livesessions'),
        ]
    ));
}
