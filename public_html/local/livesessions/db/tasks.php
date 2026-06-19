<?php
/**
 * Scheduled task definitions for local_livesessions.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [

    // ---------------------------------------------------------------
    // Auto-start sessions (scheduled → live) and auto-complete (live → completed)
    // Runs every minute.
    // ---------------------------------------------------------------
    [
        'classname' => '\local_livesessions\task\auto_start_sessions',
        'blocking'  => 0,
        'minute'    => '*',
        'hour'      => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],

    // ---------------------------------------------------------------
    // Finalise attendance for sessions that ended > 5 min ago.
    // Runs every 5 minutes.
    // ---------------------------------------------------------------
    [
        'classname' => '\local_livesessions\task\finalise_attendance',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour'      => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],

    // ---------------------------------------------------------------
    // Clean up expired tokens and old provider events.
    // Runs once per day at 02:00.
    // ---------------------------------------------------------------
    [
        'classname' => '\local_livesessions\task\cleanup_tokens',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '2',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],

    // ---------------------------------------------------------------
    // Retry stalled recordings (queued past retry_after timestamp).
    // Runs every 10 minutes.
    // ---------------------------------------------------------------
    [
        'classname' => '\local_livesessions\task\retry_recordings',
        'blocking'  => 0,
        'minute'    => '*/10',
        'hour'      => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],

    // ---------------------------------------------------------------
    // Expire stale package subscriptions (Feature 3.2).
    // Runs once per day at 03:00.
    // ---------------------------------------------------------------
    [
        'classname' => '\local_livesessions\task\expire_subscriptions',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '3',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
