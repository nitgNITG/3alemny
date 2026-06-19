<?php
/**
 * Package Manager — Feature 3.1 / 3.2 / 3.3.
 *
 * Manages the full package lifecycle:
 *   3.1 — Package CRUD (create, update, activate, deactivate, delete)
 *   3.2 — Student subscription (assign, list, expire)
 *   3.3 — Session consumption (atomic credit deduction with idempotency)
 *
 * DB tables:
 *   livesessions_packages         — package definitions
 *   livesessions_student_packages — per-student subscriptions
 *   livesessions_credit_log       — credit usage audit trail (Feature 3.3)
 *
 * Package status values: active | inactive | archived
 * Subscription status values: active | exhausted | expired | cancelled
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_livesessions;

defined('MOODLE_INTERNAL') || die();

class package_manager {

    // Package status constants.
    const PKG_ACTIVE   = 'active';
    const PKG_INACTIVE = 'inactive';
    const PKG_ARCHIVED = 'archived';

    // Subscription status constants.
    const SUB_ACTIVE    = 'active';
    const SUB_EXHAUSTED = 'exhausted';
    const SUB_EXPIRED   = 'expired';
    const SUB_CANCELLED = 'cancelled';

    // ================================================================
    // Feature 3.1 — Package CRUD
    // ================================================================

    /**
     * Create a new session package.
     *
     * @param  string   $name            Display name.
     * @param  string   $description     Rich description.
     * @param  int      $sessions_count  Number of sessions included.
     * @param  float    $price           Price (informational only — no payment processing).
     * @param  string   $currency        3-letter ISO code, e.g. 'USD'.
     * @param  int      $validity_days   Days until subscription expires (0 = no expiry).
     * @param  int      $courseid        0 = all courses; >0 = restrict to one course.
     * @return int  New package id.
     */
    public static function create_package(
        string $name,
        string $description,
        int    $sessions_count,
        float  $price         = 0.0,
        string $currency      = 'USD',
        int    $validity_days = 0,
        int    $courseid      = 0
    ): int {
        global $DB;

        self::validate_package_fields($name, $sessions_count);

        $pkg                 = new \stdClass();
        $pkg->name           = $name;
        $pkg->description    = $description;
        $pkg->sessions_count = $sessions_count;
        $pkg->price          = round($price, 2);
        $pkg->currency       = strtoupper(substr($currency, 0, 3));
        $pkg->validity_days  = max(0, $validity_days);
        $pkg->courseid       = max(0, $courseid);
        $pkg->status         = self::PKG_ACTIVE;
        $pkg->timecreated    = time();
        $pkg->timemodified   = time();

        return (int) $DB->insert_record('livesessions_packages', $pkg);
    }

    /**
     * Update an existing package (non-destructive — existing subscriptions are unaffected).
     */
    public static function update_package(int $packageid, array $fields): bool {
        global $DB;

        $pkg = $DB->get_record('livesessions_packages', ['id' => $packageid], '*', MUST_EXIST);

        $allowed = ['name','description','sessions_count','price','currency',
                    'validity_days','courseid','status'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $fields)) {
                $pkg->$f = $fields[$f];
            }
        }

        if (isset($fields['name'])) {
            self::validate_package_fields($pkg->name, $pkg->sessions_count);
        }

        $pkg->timemodified = time();
        $DB->update_record('livesessions_packages', $pkg);
        return true;
    }

    /**
     * Soft-delete a package by archiving it. Cannot delete if active subscriptions exist.
     */
    public static function archive_package(int $packageid): bool {
        global $DB;

        $active_subs = $DB->count_records('livesessions_student_packages', [
            'packageid' => $packageid,
            'status'    => self::SUB_ACTIVE,
        ]);
        if ($active_subs > 0) {
            throw new \moodle_exception('cannotarchivepackagewithsubs', 'local_livesessions');
        }

        $DB->set_field('livesessions_packages', 'status',       self::PKG_ARCHIVED, ['id' => $packageid]);
        $DB->set_field('livesessions_packages', 'timemodified', time(),              ['id' => $packageid]);
        return true;
    }

    /**
     * Get a single package record.
     */
    public static function get_package(int $packageid): \stdClass {
        global $DB;
        return $DB->get_record('livesessions_packages', ['id' => $packageid], '*', MUST_EXIST);
    }

    /**
     * List all packages, optionally filtered by status and/or course.
     *
     * @param  string $status    'active'|'inactive'|'archived'|'' (empty = all)
     * @param  int    $courseid  Filter by course; 0 = all
     * @return array  of stdClass
     */
    public static function list_packages(string $status = '', int $courseid = 0): array {
        global $DB;

        $where  = '1=1';
        $params = [];

        if ($status !== '') {
            $where           .= ' AND status = :status';
            $params['status'] = $status;
        }
        if ($courseid > 0) {
            $where              .= ' AND (courseid = 0 OR courseid = :courseid)';
            $params['courseid']  = $courseid;
        }

        return array_values($DB->get_records_sql(
            "SELECT * FROM {livesessions_packages} WHERE {$where} ORDER BY timecreated DESC",
            $params
        ));
    }

    // ================================================================
    // Feature 3.2 — Student Subscription
    // ================================================================

    /**
     * Assign a package to a student.
     *
     * @param  int    $userid     Moodle user id.
     * @param  int    $packageid  Package id.
     * @param  int    $granted_by Admin/teacher user id who created this subscription.
     * @return int  New subscription id.
     */
    public static function assign_package(int $userid, int $packageid, int $granted_by = 0): int {
        global $DB;

        $pkg = $DB->get_record('livesessions_packages', ['id' => $packageid], '*', MUST_EXIST);

        if ($pkg->status !== self::PKG_ACTIVE) {
            throw new \moodle_exception('packagenotactive', 'local_livesessions');
        }

        // Calculate expiry.
        $expiry = null;
        if ($pkg->validity_days > 0) {
            $expiry = time() + ($pkg->validity_days * 86400);
        }

        $sub                    = new \stdClass();
        $sub->userid            = $userid;
        $sub->packageid         = $packageid;
        $sub->total_sessions    = $pkg->sessions_count;
        $sub->used_sessions     = 0;
        $sub->remaining_sessions = $pkg->sessions_count;
        $sub->expiry_date       = $expiry;
        $sub->status            = self::SUB_ACTIVE;
        $sub->granted_by        = $granted_by;
        $sub->timecreated       = time();
        $sub->timemodified      = time();

        $sub_id = (int) $DB->insert_record('livesessions_student_packages', $sub);

        // Log the assignment.
        self::log_credit($userid, $packageid, $sub_id, 0, 'assigned',
            $pkg->sessions_count, $pkg->sessions_count, $granted_by,
            "Package assigned: {$pkg->name} ({$pkg->sessions_count} sessions)");

        return $sub_id;
    }

    /**
     * Get all active subscriptions for a student (sorted by expiry ASC — use soonest first).
     *
     * @param  int  $userid
     * @param  bool $valid_only  Only return non-expired, non-exhausted subs.
     * @return array of stdClass
     */
    public static function get_student_subscriptions(int $userid, bool $valid_only = true): array {
        global $DB;

        $now    = time();
        $where  = 'sp.userid = :userid';
        $params = ['userid' => $userid];

        if ($valid_only) {
            $where .= " AND sp.status = 'active'
                        AND sp.remaining_sessions > 0
                        AND (sp.expiry_date IS NULL OR sp.expiry_date > :now)";
            $params['now'] = $now;
        }

        return array_values($DB->get_records_sql(
            "SELECT sp.*, p.name AS package_name, p.validity_days
               FROM {livesessions_student_packages} sp
               JOIN {livesessions_packages} p ON p.id = sp.packageid
              WHERE {$where}
           ORDER BY sp.expiry_date ASC, sp.timecreated ASC",
            $params
        ));
    }

    /**
     * Get subscription summary for a student — total remaining across all active subs.
     */
    public static function get_remaining_credits(int $userid): int {
        global $DB;

        $now = time();
        return (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(remaining_sessions), 0)
               FROM {livesessions_student_packages}
              WHERE userid = :userid
                AND status = 'active'
                AND remaining_sessions > 0
                AND (expiry_date IS NULL OR expiry_date > :now)",
            ['userid' => $userid, 'now' => $now]
        );
    }

    /**
     * Cancel a student subscription.
     */
    public static function cancel_subscription(int $sub_id, int $cancelled_by = 0): bool {
        global $DB;

        $sub = $DB->get_record('livesessions_student_packages', ['id' => $sub_id], '*', MUST_EXIST);
        $DB->set_field('livesessions_student_packages', 'status',       self::SUB_CANCELLED, ['id' => $sub_id]);
        $DB->set_field('livesessions_student_packages', 'timemodified', time(),               ['id' => $sub_id]);

        self::log_credit($sub->userid, $sub->packageid, $sub_id, 0, 'cancelled',
            $sub->remaining_sessions, 0, $cancelled_by, 'Subscription cancelled');
        return true;
    }

    /**
     * Run expiry check — mark subscriptions expired if past expiry_date.
     * Called by cleanup scheduled task.
     */
    public static function expire_stale_subscriptions(): int {
        global $DB;

        $now    = time();
        $stale  = $DB->get_records_sql(
            "SELECT * FROM {livesessions_student_packages}
              WHERE status = 'active'
                AND expiry_date IS NOT NULL
                AND expiry_date <= :now",
            ['now' => $now]
        );

        foreach ($stale as $sub) {
            $DB->set_field('livesessions_student_packages', 'status',       self::SUB_EXPIRED, ['id' => $sub->id]);
            $DB->set_field('livesessions_student_packages', 'timemodified', time(),             ['id' => $sub->id]);
            self::log_credit($sub->userid, $sub->packageid, $sub->id, 0, 'expired',
                $sub->remaining_sessions, $sub->remaining_sessions, 0,
                'Subscription expired automatically');
        }

        return count($stale);
    }

    // ================================================================
    // Feature 3.3 — Session Credit Consumption
    // ================================================================

    /**
     * Deduct one session credit from the student's earliest-expiring active subscription.
     *
     * Idempotent: if a credit has already been deducted for (userid, sessionid) in the
     * credit_log table, this call is a no-op and returns the existing log row.
     *
     * @param  int $userid     Student user id.
     * @param  int $sessionid  Session they are joining.
     * @return \stdClass  The credit log row created (or existing duplicate row).
     * @throws \moodle_exception  If no active credits available.
     */
    public static function consume_credit(int $userid, int $sessionid): \stdClass {
        global $DB;

        // ---- Idempotency: already deducted for this (user, session)? ----
        $existing = $DB->get_record('livesessions_credit_log', [
            'userid'    => $userid,
            'sessionid' => $sessionid,
            'action'    => 'consumed',
        ]);
        if ($existing) {
            return $existing; // No double-deduction.
        }

        // ---- Find the soonest-expiring active subscription ----
        $now = time();
        $sub = $DB->get_record_sql(
            "SELECT * FROM {livesessions_student_packages}
              WHERE userid            = :userid
                AND status            = 'active'
                AND remaining_sessions > 0
                AND (expiry_date IS NULL OR expiry_date > :now)
           ORDER BY expiry_date ASC
              LIMIT 1",
            ['userid' => $userid, 'now' => $now]
        );

        if (!$sub) {
            throw new \moodle_exception('nosessioncredits', 'local_livesessions',
                '', null, "userid={$userid} sessionid={$sessionid}");
        }

        // ---- Atomic deduction ----
        $new_remaining = $sub->remaining_sessions - 1;
        $new_used      = $sub->used_sessions + 1;
        $new_status    = ($new_remaining <= 0) ? self::SUB_EXHAUSTED : self::SUB_ACTIVE;

        $DB->set_field('livesessions_student_packages', 'remaining_sessions', $new_remaining, ['id' => $sub->id]);
        $DB->set_field('livesessions_student_packages', 'used_sessions',      $new_used,      ['id' => $sub->id]);
        $DB->set_field('livesessions_student_packages', 'status',             $new_status,    ['id' => $sub->id]);
        $DB->set_field('livesessions_student_packages', 'timemodified',       time(),          ['id' => $sub->id]);

        // ---- Write audit log ----
        $log = self::log_credit(
            $userid, $sub->packageid, $sub->id, $sessionid,
            'consumed',
            $sub->remaining_sessions,   // before
            $new_remaining,             // after
            $userid,
            "Session credit consumed for sessionid={$sessionid}"
        );

        return $log;
    }

    /**
     * Refund one session credit (e.g. when session is cancelled by teacher).
     * Only refunds if a 'consumed' log row exists for (userid, sessionid).
     * Returns false if nothing to refund.
     */
    public static function refund_credit(int $userid, int $sessionid, int $refunded_by = 0): bool {
        global $DB;

        $consumed_log = $DB->get_record('livesessions_credit_log', [
            'userid'    => $userid,
            'sessionid' => $sessionid,
            'action'    => 'consumed',
        ]);
        if (!$consumed_log) {
            return false; // Never consumed — nothing to refund.
        }

        // Check if already refunded.
        if ($DB->record_exists('livesessions_credit_log', [
            'userid'    => $userid,
            'sessionid' => $sessionid,
            'action'    => 'refunded',
        ])) {
            return false; // Already refunded.
        }

        $sub = $DB->get_record('livesessions_student_packages', ['id' => $consumed_log->sub_id]);
        if (!$sub) {
            return false; // Subscription deleted.
        }

        $new_remaining = $sub->remaining_sessions + 1;
        $new_used      = max(0, $sub->used_sessions - 1);
        $new_status    = ($sub->status === self::SUB_EXHAUSTED) ? self::SUB_ACTIVE : $sub->status;

        $DB->set_field('livesessions_student_packages', 'remaining_sessions', $new_remaining,  ['id' => $sub->id]);
        $DB->set_field('livesessions_student_packages', 'used_sessions',      $new_used,       ['id' => $sub->id]);
        $DB->set_field('livesessions_student_packages', 'status',             $new_status,     ['id' => $sub->id]);
        $DB->set_field('livesessions_student_packages', 'timemodified',       time(),           ['id' => $sub->id]);

        self::log_credit(
            $userid, $sub->packageid, $sub->id, $sessionid,
            'refunded',
            $sub->remaining_sessions, $new_remaining,
            $refunded_by,
            "Credit refunded for sessionid={$sessionid}"
        );

        return true;
    }

    /**
     * Check if a student has at least one valid credit (without consuming it).
     */
    public static function has_credits(int $userid): bool {
        return self::get_remaining_credits($userid) > 0;
    }

    /**
     * Refund credits to ALL students who joined a session (used when a session is cancelled).
     */
    public static function refund_session_credits(int $sessionid, int $refunded_by = 0): int {
        global $DB;

        $consumed = $DB->get_records('livesessions_credit_log', [
            'sessionid' => $sessionid,
            'action'    => 'consumed',
        ]);

        $count = 0;
        foreach ($consumed as $log) {
            if (self::refund_credit($log->userid, $sessionid, $refunded_by)) {
                $count++;
            }
        }
        return $count;
    }

    // ================================================================
    // Credit audit log (used internally by 3.2 and 3.3)
    // ================================================================

    private static function log_credit(
        int    $userid,
        int    $packageid,
        int    $sub_id,
        int    $sessionid,
        string $action,
        int    $credits_before,
        int    $credits_after,
        int    $changed_by,
        string $reason
    ): \stdClass {
        global $DB;

        $log               = new \stdClass();
        $log->userid       = $userid;
        $log->packageid    = $packageid;
        $log->sub_id       = $sub_id;
        $log->sessionid    = $sessionid;
        $log->action       = $action;        // assigned|consumed|refunded|expired|cancelled
        $log->credits_before = $credits_before;
        $log->credits_after  = $credits_after;
        $log->changed_by   = $changed_by;
        $log->reason       = $reason;
        $log->timecreated  = time();

        $log->id = (int) $DB->insert_record('livesessions_credit_log', $log);
        return $log;
    }

    // ================================================================
    // Validation helpers
    // ================================================================

    private static function validate_package_fields(string $name, int $sessions_count): void {
        if (trim($name) === '') {
            throw new \moodle_exception('missingpackagename', 'local_livesessions');
        }
        if ($sessions_count < 1) {
            throw new \moodle_exception('invalidsessionscount', 'local_livesessions');
        }
    }
}
