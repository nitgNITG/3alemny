# Features Per Role
**Document:** FPR-001
**Phase:** 1 — Business Analysis
**Version:** 1.0
**Status:** 🟡 UNDER REVIEW
**Based on:** STK-001, WF-001 (all workflows approved)

> This document defines exactly what each user role can do in the platform.
> Scope: **Live Sessions plugin only** (packages, booking, sessions, recordings).
> Review this document and flag any changes — workflows will be updated accordingly.

---

## Role Summary

| Role | Moodle Role | Can Book | Can Teach | Can Pay | Financial Access |
|------|-------------|----------|-----------|---------|-----------------|
| Student | `student` | ✅ | ❌ | ✅ | Own credits only |
| Parent | custom `parent` | ❌ | ❌ | ❌ | View child's credits |
| Teacher | `editingteacher` | ✅ (as student in other courses) | ✅ | ❌ | Own earnings only |
| Support | custom `support` | ✅ (on behalf) | ❌ | ❌ | None |
| Manager | `manager` | ✅ (on behalf) | ❌ | ✅ (assign) | Full reports |
| Admin | `admin` | ✅ (on behalf) | ❌ | ✅ (assign) | Full + override |

---

## 1. STUDENT

### 1.1 Account & Registration
| # | Feature | Notes |
|---|---------|-------|
| S-01 | Self-register on platform | Or admin creates account |
| S-02 | Log in with email + password | Standard Moodle auth |
| S-03 | Edit own profile (name, photo, timezone) | Timezone used for all time displays |
| S-04 | Enrol in courses | After registration — not required before buying package |

### 1.2 Packages & Credits
| # | Feature | Notes |
|---|---------|-------|
| S-05 | View available packages | Sizes: 8 / 12 / 20 sessions — tiered pricing in EGP |
| S-06 | Purchase package online | Via payment gateway |
| S-07 | View own credit balance | Remaining sessions + expiry (end of academic year) |
| S-08 | View credit transaction history | Deductions, refunds, purchases |
| S-09 | **Cannot** hold more than 1 active package at a time | Blocked by system |
| S-10 | **Cannot** gift or transfer credits | Not permitted |

### 1.3 Browsing & Booking
| # | Feature | Notes |
|---|---------|-------|
| S-11 | Browse teachers in enrolled courses | See teacher profile + availability |
| S-12 | View teacher's available slots | Filtered by morning / evening preference |
| S-13 | Book a session (50 min) | Min 1 hour notice required |
| S-14 | Add note/topic when booking | Visible to teacher on approval screen |
| S-15 | Credit deducted immediately on booking | Refunded if teacher rejects |
| S-16 | View booking status (Pending / Confirmed / Cancelled) | In "My Sessions" |
| S-17 | **Cannot** hold more than 12 upcoming active bookings | System enforced |
| S-18 | See reserved slot assigned by teacher | Receive notification → Accept or Reject |
| S-19 | Accept reserved slot → credit deducted | 1 credit per session |
| S-20 | Reject reserved slot → slot reopens for all | Credit not touched |

### 1.4 Rescheduling & Cancellation
| # | Feature | Notes |
|---|---------|-------|
| S-21 | Cancel session **more than 1 hour** before start | Full credit refund |
| S-22 | Cancel session **within 1 hour** of start | Credit forfeited — no refund |
| S-23 | Reschedule session to another slot | Up to 30 minutes before session start |
| S-24 | See teacher's rejection reason | Shown on My Sessions page |

### 1.5 Live Session
| # | Feature | Notes |
|---|---------|-------|
| S-25 | Join live session (embedded in Moodle) | No Zoom app or new tab required |
| S-26 | Join up to 10 minutes late (grace period) | Not penalised for attendance |
| S-27 | Session attendance tracked automatically | join_time + leave_time recorded |
| S-28 | **Cannot** start a session | Teacher starts; student joins |

### 1.6 Recordings
| # | Feature | Notes |
|---|---------|-------|
| S-29 | Stream own session recordings | No credit cost |
| S-30 | Max view count per recording | Configurable (default 5 views) |
| S-31 | **Cannot** download recordings | Stream only — DRM protected |
| S-32 | Access recordings for 30 days after session | After 30 days → archived (configurable) |
| S-33 | **Cannot** access recordings if marked absent | Absent = joined 0% of session |

---

## 2. PARENT

### 2.1 Account
| # | Feature | Notes |
|---|---------|-------|
| P-01 | Log in with own account | Admin or support links parent to student(s) |
| P-02 | One parent linked to multiple students | 1-to-many relationship |
| P-03 | Read-only access — cannot book, cancel, or join | View only |

### 2.2 Monitoring (per linked student)
| # | Feature | Notes |
|---|---------|-------|
| P-04 | View upcoming sessions | Date, teacher name, status |
| P-05 | View attendance history | Attended / Partial / Absent per session |
| P-06 | View credit balance + expiry | Remaining sessions in active package |
| P-07 | View session recordings | Same access as student — streams only, within 30-day window |
| P-08 | **Cannot** watch recordings if student is marked absent | Same rule as student |

---

## 3. TEACHER

### 3.1 Account
| # | Feature | Notes |
|---|---------|-------|
| T-01 | Log in with teacher account | `editingteacher` role |
| T-02 | Can also hold a second account as a student | Enrolled in other courses |
| T-03 | Edit own profile (name, bio, photo, timezone) | Timezone used for schedule display |

### 3.2 Availability & Schedule
| # | Feature | Notes |
|---|---------|-------|
| T-04 | Define weekly working schedule | Working days + hours in own timezone |
| T-05 | System auto-generates 50-min bookable slots | 10-min buffer enforced between slots |
| T-06 | Set blocked dates (holidays, leave) | No slots generated on blocked dates |
| T-07 | Create open slot (any enrolled student can book) | Visible to all eligible students |
| T-08 | Create reserved slot (one specific student) | Student gets Accept/Reject notification |
| T-09 | Edit or delete unbooked future slots | Cannot delete already-booked slots |

### 3.3 Session Requests & Approval
| # | Feature | Notes |
|---|---------|-------|
| T-10 | Receive notification of new booking request | Email + in-app |
| T-11 | View request details (student, date/time, note) | In "Pending Requests" page |
| T-12 | Approve booking | Zoom meeting auto-created via API; student notified |
| T-13 | Reject booking with optional reason | Reason visible to student; credit refunded |
| T-14 | Must respond before start of first session on that day | Auto-cancel if deadline passes |

### 3.4 Live Session
| # | Feature | Notes |
|---|---------|-------|
| T-15 | Start session (marks session as Live) | Zoom embedded room opens in Moodle |
| T-16 | Re-enter live session if disconnected | "Enter Room" button shown while live |
| T-17 | Accept last-minute booking during live session | Stays pending; teacher can approve mid-session |
| T-18 | End session | Webhook triggers attendance calculation |

### 3.5 Attendance & Override
| # | Feature | Notes |
|---|---------|-------|
| T-19 | View attendance report for own students | After each session |
| T-20 | Manually override attendance status | e.g. internet disconnection; changes attended/partial/absent |

### 3.6 Recordings
| # | Feature | Notes |
|---|---------|-------|
| T-21 | Stream own session recordings | Always — regardless of student attendance |
| T-22 | **Cannot** disable recording per session | All sessions always recorded |
| T-23 | View archived recordings | Beyond 30-day student window |

### 3.7 Cancellation
| # | Feature | Notes |
|---|---------|-------|
| T-24 | Cancel a confirmed session | Strike +1 logged; full credit refund to student |
| T-25 | 3rd cancellation triggers admin alert | Configurable threshold |

---

## 4. SUPPORT TEAM

| # | Feature | Notes |
|---|---------|-------|
| SP-01 | View all sessions (all courses, all teachers) | Read access |
| SP-02 | View attendance logs | join_time, leave_time per participant |
| SP-03 | Reschedule a session on behalf of student or teacher | With reason logged |
| SP-04 | Link a parent account to student account(s) | Admin can also do this |
| SP-05 | **Cannot** assign credits or access financial reports | Manager/Admin only |
| SP-06 | **Cannot** start or join a live session | View-only on live sessions |

---

## 5. MANAGER

| # | Feature | Notes |
|---|---------|-------|
| MG-01 | All Support Team features | Superset |
| MG-02 | Manually assign package to student | Admin manual payment method |
| MG-03 | View financial reports | Credit ledger, package sales, refunds |
| MG-04 | Export reports as CSV | For reconciliation |
| MG-05 | Manage packages (create, edit, set prices) | 8 / 12 / 20 session tiers |
| MG-06 | View teacher cancellation strikes | Monitor reliability |
| MG-07 | **Cannot** override credit decisions | Admin only |

---

## 6. ADMIN

| # | Feature | Notes |
|---|---------|-------|
| AD-01 | All Manager features | Full superset |
| AD-02 | Manually refund credit to any student | Override any credit decision |
| AD-03 | Manually deduct credit from any student | Override any credit decision |
| AD-04 | Reset teacher cancellation strike count | Manual action with audit log |
| AD-05 | Configure all platform settings | All numbers and yes/no policies |
| AD-06 | Create / edit / deactivate any user account | Full user management |
| AD-07 | View all recordings including archived | No time limit |
| AD-08 | Impersonate any user for support | Standard Moodle admin feature |
| AD-09 | Receive alert when teacher hits cancellation threshold | System notification |
| AD-10 | Receive alert when teacher is absent from session | System notification |

---

## 7. Configurable Settings Reference

All values below are defaults — Admin can change them from the plugin settings page.

| Setting | Default | Affects |
|---------|---------|---------|
| `session_duration_minutes` | `50` | All sessions fixed duration |
| `session_buffer_minutes` | `10` | Gap between consecutive teacher slots |
| `booking_min_notice_hours` | `1` | Earliest a student can book |
| `cancellation_window_hours` | `1` | Cutoff for credit refund on cancel |
| `late_cancel_forfeit` | `ON` | Credit lost if cancel inside window |
| `student_reschedule_window_minutes` | `30` | Latest student can reschedule |
| `max_active_bookings` | `12` | Upcoming bookings per student |
| `late_join_grace_minutes` | `10` | Grace period for late join |
| `attendance_threshold_pct` | `70` | Min % to mark as "attended" |
| `teacher_attendance_override` | `ON` | Teacher can edit attendance |
| `noshow_teacher_compensation` | `ON` | Teacher compensated on no-show |
| `teacher_cancel_alert_threshold` | `3` | Cancellations before admin alert |
| `teacher_cancel_strike_reset` | `ON` | Admin can clear strikes |
| `admin_credit_override` | `ON` | Admin can refund/deduct credits |
| `recording_retention_days` | `30` | Days before archiving |
| `recording_max_views` | `5` | Max streams per student per recording |
| `recording_allow_download` | `OFF` | Allow download (stream-only default) |

---

**STATUS: 🟡 UNDER REVIEW**
*Please review each section and confirm or flag changes.*
*Any change here will trigger a workflow update before Phase 2 begins.*
