<?php
/**
 * local_livesessions upgrade steps.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_livesessions_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Feature 1.2 — Join tracking tables.
    if ($oldversion < 2024010102) {

        // att_segments table.
        $table = new xmldb_table('livesessions_att_segments');
        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('attendanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sessionid',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('join_time',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('leave_time',   XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('duration',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_attendanceid', XMLDB_INDEX_NOTUNIQUE, ['attendanceid']);
        $table->add_index('idx_session_user', XMLDB_INDEX_NOTUNIQUE, ['sessionid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // join_tokens table.
        $table = new xmldb_table('livesessions_join_tokens');
        $table->add_field('id',          XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sessionid',   XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid',      XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('token',       XMLDB_TYPE_CHAR,    '64',  null, XMLDB_NOTNULL, null, '');
        $table->add_field('expires',     XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('used',        XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('uniq_token',      XMLDB_INDEX_UNIQUE,    ['token']);
        $table->add_index('idx_session_user',XMLDB_INDEX_NOTUNIQUE, ['sessionid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // provider_events table.
        $table = new xmldb_table('livesessions_provider_events');
        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sessionid',    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('provider',     XMLDB_TYPE_CHAR,    '50',  null, XMLDB_NOTNULL, null, '');
        $table->add_field('event_type',   XMLDB_TYPE_CHAR,    '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('external_uid', XMLDB_TYPE_CHAR,    '255', null, null, null, null);
        $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10',  null, null, null, null);
        $table->add_field('payload',      XMLDB_TYPE_TEXT,    null,  null, XMLDB_NOTNULL, null, null);
        $table->add_field('processed',    XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_sessionid',  XMLDB_INDEX_NOTUNIQUE, ['sessionid']);
        $table->add_index('idx_event_type', XMLDB_INDEX_NOTUNIQUE, ['event_type']);
        $table->add_index('idx_processed',  XMLDB_INDEX_NOTUNIQUE, ['processed']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add join_count and last_join_time columns to attendance.
        $table = new xmldb_table('livesessions_attendance');
        $field = new xmldb_field('join_count', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0', 'duration_attended');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('last_join_time', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'join_count');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010102, 'local', 'livesessions');
    }

    // Feature 1.3 — Attendance Engine audit tables.
    if ($oldversion < 2024010103) {

        // att_log table.
        $table = new xmldb_table('livesessions_att_log');
        $table->add_field('id',                   XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('attendanceid',          XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sessionid',             XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid',                XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('changed_by',            XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('prev_status',           XMLDB_TYPE_CHAR,    '20',  null, XMLDB_NOTNULL, null, '');
        $table->add_field('new_status',            XMLDB_TYPE_CHAR,    '20',  null, XMLDB_NOTNULL, null, '');
        $table->add_field('prev_percent',          XMLDB_TYPE_NUMBER,  '5',   null, XMLDB_NOTNULL, null, '0.00');
        $table->add_field('new_percent',           XMLDB_TYPE_NUMBER,  '5',   null, XMLDB_NOTNULL, null, '0.00');
        $table->add_field('prev_duration',         XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('new_duration',          XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('prev_recording_access', XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('new_recording_access',  XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reason',                XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('timecreated',           XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_attendanceid', XMLDB_INDEX_NOTUNIQUE, ['attendanceid']);
        $table->add_index('idx_sessionid',    XMLDB_INDEX_NOTUNIQUE, ['sessionid']);
        $table->add_index('idx_userid',       XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // finalise_log table.
        $table = new xmldb_table('livesessions_finalise_log');
        $table->add_field('id',                XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sessionid',         XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('triggered_by',      XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('trigger_reason',    XMLDB_TYPE_CHAR,    '50',  null, XMLDB_NOTNULL, null, 'cron');
        $table->add_field('students_total',    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('students_attended', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('students_partial',  XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('students_absent',   XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('threshold_used',    XMLDB_TYPE_NUMBER,  '5',   null, XMLDB_NOTNULL, null, '70.00');
        $table->add_field('duration_used',     XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated',       XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_sessionid', XMLDB_INDEX_NOTUNIQUE, ['sessionid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add finalised_at to sessions.
        $table = new xmldb_table('livesessions_sessions');
        $field = new xmldb_field('finalised_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'recording_status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010103, 'local', 'livesessions');
    }

    // Feature 2.2 — Bunny Stream upload integration.
    if ($oldversion < 2024020201) {

        // ---- New columns on livesessions_recordings ----
        $table = new xmldb_table('livesessions_recordings');

        $cols = [
            // Provider-level columns (already added in Feature 2.1 install.xml;
            // upgrade adds them for sites upgrading from 2024010103).
            new xmldb_field('source_provider',   XMLDB_TYPE_CHAR,    '50',  null, null, null, null, 'provider'),
            new xmldb_field('idempotency_key',   XMLDB_TYPE_CHAR,    '255', null, null, null, null, 'source_provider'),
            new xmldb_field('download_token',    XMLDB_TYPE_TEXT,    null,  null, null, null, null, 'download_url'),
            new xmldb_field('embed_url',         XMLDB_TYPE_TEXT,    null,  null, null, null, null, 'download_token'),
            new xmldb_field('video_url',         XMLDB_TYPE_TEXT,    null,  null, null, null, null, 'embed_url'),
            new xmldb_field('thumbnail_url',     XMLDB_TYPE_TEXT,    null,  null, null, null, null, 'video_url'),
            new xmldb_field('raw_payload',       XMLDB_TYPE_TEXT,    null,  null, null, null, null, 'thumbnail_url'),
            new xmldb_field('retry_count',       XMLDB_TYPE_INTEGER, '3',   null, XMLDB_NOTNULL, null, '0', 'raw_payload'),
            new xmldb_field('retry_after',       XMLDB_TYPE_INTEGER, '10',  null, null, null, null, 'retry_count'),
            new xmldb_field('last_error',        XMLDB_TYPE_TEXT,    null,  null, null, null, null, 'retry_after'),
            // Bunny-specific columns.
            new xmldb_field('encoding_status',   XMLDB_TYPE_INTEGER, '3',   null, null, null, null, 'last_error'),
            new xmldb_field('encoding_progress', XMLDB_TYPE_INTEGER, '3',   null, null, null, null, 'encoding_status'),
        ];

        foreach ($cols as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // UNIQUE index on idempotency_key.
        $index = new xmldb_index('uniq_idempotency', XMLDB_INDEX_UNIQUE, ['idempotency_key']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // ---- New table: livesessions_rec_log ----
        $rectable = new xmldb_table('livesessions_rec_log');
        $rectable->add_field('id',           XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $rectable->add_field('recordingid',  XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $rectable->add_field('sessionid',    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $rectable->add_field('prev_status',  XMLDB_TYPE_CHAR,    '30',  null, null, null, null);
        $rectable->add_field('new_status',   XMLDB_TYPE_CHAR,    '30',  null, XMLDB_NOTNULL, null, '');
        $rectable->add_field('changed_by',   XMLDB_TYPE_INTEGER, '10',  null, null, null, null);
        $rectable->add_field('reason',       XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
        $rectable->add_field('detail',       XMLDB_TYPE_TEXT,    null,  null, null, null, null);
        $rectable->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $rectable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $rectable->add_index('idx_recordingid', XMLDB_INDEX_NOTUNIQUE, ['recordingid']);
        $rectable->add_index('idx_sessionid',   XMLDB_INDEX_NOTUNIQUE, ['sessionid']);
        if (!$dbman->table_exists($rectable)) {
            $dbman->create_table($rectable);
        }

        // ---- New table: livesessions_webhook_log ----
        $whtable = new xmldb_table('livesessions_webhook_log');
        $whtable->add_field('id',           XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $whtable->add_field('provider',     XMLDB_TYPE_CHAR,    '50',  null, XMLDB_NOTNULL, null, '');
        $whtable->add_field('event_type',   XMLDB_TYPE_CHAR,    '100', null, XMLDB_NOTNULL, null, '');
        $whtable->add_field('sessionid',    XMLDB_TYPE_INTEGER, '10',  null, null, null, null);
        $whtable->add_field('recordingid',  XMLDB_TYPE_INTEGER, '10',  null, null, null, null);
        $whtable->add_field('raw_body',     XMLDB_TYPE_TEXT,    null,  null, null, null, null);
        $whtable->add_field('headers',      XMLDB_TYPE_TEXT,    null,  null, null, null, null);
        $whtable->add_field('sig_valid',    XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '0');
        $whtable->add_field('processed',    XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '0');
        $whtable->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $whtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $whtable->add_index('idx_provider',    XMLDB_INDEX_NOTUNIQUE, ['provider']);
        $whtable->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->table_exists($whtable)) {
            $dbman->create_table($whtable);
        }

        // ---- New table: livesessions_bunny_polls ----
        $bptable = new xmldb_table('livesessions_bunny_polls');
        $bptable->add_field('id',              XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $bptable->add_field('recordingid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $bptable->add_field('video_guid',      XMLDB_TYPE_CHAR,    '64', null, XMLDB_NOTNULL, null, '');
        $bptable->add_field('enc_status',      XMLDB_TYPE_INTEGER, '3',  null, null, null, null);
        $bptable->add_field('enc_progress',    XMLDB_TYPE_INTEGER, '3',  null, null, null, null);
        $bptable->add_field('timecreated',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $bptable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $bptable->add_index('idx_recordingid', XMLDB_INDEX_NOTUNIQUE, ['recordingid']);
        $bptable->add_index('idx_video_guid',  XMLDB_INDEX_NOTUNIQUE, ['video_guid']);
        if (!$dbman->table_exists($bptable)) {
            $dbman->create_table($bptable);
        }

        upgrade_plugin_savepoint(true, 2024020201, 'local', 'livesessions');
    }

    // Feature 2.3 — VdoCipher upload + Feature 2.4 — Moodle activity auto-creation.
    if ($oldversion < 2024020202) {

        // ---- vdo_polls: VdoCipher encoding poll history ----
        $vdotable = new xmldb_table('livesessions_vdo_polls');
        $vdotable->add_field('id',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $vdotable->add_field('recordingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $vdotable->add_field('video_id',    XMLDB_TYPE_CHAR,    '64', null, XMLDB_NOTNULL, null, '');
        $vdotable->add_field('enc_status',  XMLDB_TYPE_CHAR,    '30', null, null,          null, null);
        $vdotable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $vdotable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $vdotable->add_index('idx_recordingid', XMLDB_INDEX_NOTUNIQUE, ['recordingid']);
        if (!$dbman->table_exists($vdotable)) {
            $dbman->create_table($vdotable);
        }

        // ---- Add moodle_cmid to livesessions_recordings (Feature 2.4) ----
        $table = new xmldb_table('livesessions_recordings');
        $field = new xmldb_field('moodle_cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'thumbnail_url');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024020202, 'local', 'livesessions');
    }

    // Feature 3.1 / 3.2 / 3.3 — Package system.
    if ($oldversion < 2024030301) {

        // ---- Add granted_by to livesessions_student_packages ----
        $table = new xmldb_table('livesessions_student_packages');
        $field = new xmldb_field('granted_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // ---- Create livesessions_credit_log ----
        $cltable = new xmldb_table('livesessions_credit_log');
        $cltable->add_field('id',             XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $cltable->add_field('userid',         XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $cltable->add_field('packageid',      XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $cltable->add_field('sub_id',         XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $cltable->add_field('sessionid',      XMLDB_TYPE_INTEGER, '10',  null, null,          null, null);
        $cltable->add_field('action',         XMLDB_TYPE_CHAR,    '20',  null, XMLDB_NOTNULL, null, '');
        $cltable->add_field('credits_before', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $cltable->add_field('credits_after',  XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $cltable->add_field('changed_by',     XMLDB_TYPE_INTEGER, '10',  null, null,          null, null);
        $cltable->add_field('reason',         XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
        $cltable->add_field('timecreated',    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $cltable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $cltable->add_index('idx_userid',    XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $cltable->add_index('idx_sub_id',    XMLDB_INDEX_NOTUNIQUE, ['sub_id']);
        $cltable->add_index('idx_sessionid', XMLDB_INDEX_NOTUNIQUE, ['sessionid']);
        $cltable->add_index('idx_action',    XMLDB_INDEX_NOTUNIQUE, ['action']);
        if (!$dbman->table_exists($cltable)) {
            $dbman->create_table($cltable);
        }

        upgrade_plugin_savepoint(true, 2024030301, 'local', 'livesessions');
    }

    // Feature 6.1 — Device session tracking.
    if ($oldversion < 2024060101) {

        $table = new xmldb_table('livesessions_device_sessions');
        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sessionid',    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('device_token', XMLDB_TYPE_CHAR,    '128', null, XMLDB_NOTNULL, null, '');
        $table->add_field('joined_at',    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('left_at',      XMLDB_TYPE_INTEGER, '10',  null, null,          null, null);
        $table->add_field('ip',           XMLDB_TYPE_CHAR,    '45',  null, null,          null, null);
        $table->add_field('user_agent',   XMLDB_TYPE_CHAR,    '255', null, null,          null, null);
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_session_user',  XMLDB_INDEX_NOTUNIQUE, ['sessionid', 'userid']);
        $table->add_index('idx_device_token',  XMLDB_INDEX_NOTUNIQUE, ['device_token']);
        $table->add_index('idx_joined_at',     XMLDB_INDEX_NOTUNIQUE, ['joined_at']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2024060101, 'local', 'livesessions');
    }

    // 2024070201 — Private 1-to-1 session fields
    if ($oldversion < 2024070201) {
        $table = new xmldb_table('livesessions_sessions');
        $fields_to_add = [
            ['session_type',    XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'group'],
            ['requested_by',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'],
            ['request_status',  XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'none'],
            ['request_note',    XMLDB_TYPE_TEXT,    null,  null, null, null, null],
            ['reject_reason',   XMLDB_TYPE_TEXT,    null,  null, null, null, null],
            ['meeting_password', XMLDB_TYPE_CHAR,   '64', null, null, null, null],
        ];
        foreach ($fields_to_add as $fdef) {
            $field = new xmldb_field($fdef[0], $fdef[1], $fdef[2], $fdef[3], $fdef[4], $fdef[5], $fdef[6]);
            if (!$dbman->field_exists($table, $field)) { $dbman->add_field($table, $field); }
        }
        upgrade_plugin_savepoint(true, 2024070201, 'local', 'livesessions');
    }

    return true;
}
