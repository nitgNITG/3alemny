# Business Rules Document
**Document:** BR-001
**Phase:** 1 — Business Analysis
**Status:** 🟡 AWAITING APPROVAL

> ⚠️ Items marked **[TBD]** require stakeholder answers before Phase 2 can begin.

---

## BR-01 — User Accounts & Roles

| ID | Rule | Default | Configurable |
|----|------|---------|--------------|
| BR-01-1 | A student must be enrolled in a course to book sessions in that course | Mandatory | No |
| BR-01-2 | A teacher must be assigned to a course to receive bookings for it | Mandatory | No |
| BR-01-3 | A parent account is read-only and cannot book or join sessions | Mandatory | No |
| BR-01-4 | One parent can be linked to **[TBD: 1 or many?]** student accounts | **[TBD]** | **[TBD]** |
| BR-01-5 | An admin can impersonate any user for support purposes | Mandatory | No |

---

## BR-02 — Package & Credit Rules

| ID | Rule | Default | Configurable |
|----|------|---------|--------------|
| BR-02-1 | A package has: name, price, credit count, validity period (days), subject scope | Mandatory | Yes (admin) |
| BR-02-2 | Credits are per-student, per-package. **[TBD: cross-subject or subject-locked?]** | **[TBD]** | **[TBD]** |
| BR-02-3 | A student can have multiple active packages simultaneously | **[TBD]** | **[TBD]** |
| BR-02-4 | If multiple packages exist, credits are consumed FIFO (earliest expiry first) | Recommended | Yes |
| BR-02-5 | Expired credits cannot be used but are visible in history | Mandatory | No |
| BR-02-6 | Package validity starts on: **[TBD: purchase date or first use?]** | **[TBD]** | **[TBD]** |
| BR-02-7 | Admin can manually add/remove credits from any student with audit log | Mandatory | No |
| BR-02-8 | A package can be restricted to specific courses/subjects | Optional | Yes (admin) |

---

## BR-03 — Booking Rules

| ID | Rule | Default | Configurable |
|----|------|---------|--------------|
| BR-03-1 | Student must have ≥ 1 usable credit to book a session | Mandatory | No |
| BR-03-2 | Credit is **RESERVED** at booking time, **DEDUCTED** at **[TBD: approval / session start / session end]** | **[TBD]** | **[TBD]** |
| BR-03-3 | Minimum advance booking notice: **[TBD]** hours | **[TBD: 2h]** | Yes (admin) |
| BR-03-4 | Maximum bookings per student per day: **[TBD]** | **[TBD: 2]** | Yes (admin) |
| BR-03-5 | A slot cannot be double-booked | Mandatory | No |
| BR-03-6 | Student cannot book overlapping sessions | Mandatory | No |
| BR-03-7 | Teacher approval window: **[TBD]** hours to respond | **[TBD: 24h]** | Yes (per teacher) |
| BR-03-8 | If teacher does not respond within window → request auto-**[TBD: expires / auto-approves]** | **[TBD]** | **[TBD]** |
| BR-03-9 | Student can cancel a booking up to **[TBD]** hours before session | **[TBD: 24h]** | Yes (admin) |
| BR-03-10 | Teacher can set their account to auto-accept bookings | Optional | Yes (per teacher) |

---

## BR-04 — Cancellation & Refund Rules

| ID | Rule | Default | Configurable |
|----|------|---------|--------------|
| BR-04-1 | Student cancels BEFORE policy window → **Full credit refund** | Mandatory | No |
| BR-04-2 | Student cancels WITHIN policy window → **[TBD: credit forfeited / partial refund]** | **[TBD]** | Yes (admin) |
| BR-04-3 | Student no-show (never joined) → credit **[TBD: forfeited / refunded]** | **[TBD]** | Yes (admin) |
| BR-04-4 | Teacher cancels any time → **Full credit refund to student** | Mandatory | No |
| BR-04-5 | Teacher cancellation is logged as a strike | Mandatory | No |
| BR-04-6 | After **[TBD]** teacher cancellations in **[TBD]** days → Admin alert | **[TBD: 3 in 30 days]** | Yes (admin) |
| BR-04-7 | Technical failure (platform down) → Full credit refund | Mandatory | No |
| BR-04-8 | Admin can manually issue refund with reason at any time | Mandatory | No |

---

## BR-05 — Attendance Rules

| ID | Rule | Default | Configurable |
|----|------|---------|--------------|
| BR-05-1 | Attendance is tracked via provider webhooks (join/leave events) | Mandatory | No |
| BR-05-2 | Minimum attendance % to be marked "attended": **[TBD]** % | **[TBD: 70%]** | Yes (admin) |
| BR-05-3 | Student attended ≥ threshold → status: **attended** | Mandatory | No |
| BR-05-4 | Student attended < threshold → status: **partial** | Mandatory | No |
| BR-05-5 | Student never joined → status: **absent** | Mandatory | No |
| BR-05-6 | Teacher can manually override attendance status | Optional | Yes (admin) |
| BR-05-7 | Late join grace period: **[TBD]** minutes (still counted as on-time) | **[TBD: 5 min]** | Yes (admin) |
| BR-05-8 | Session must last minimum **[TBD]** minutes to count as valid | **[TBD: 10 min]** | Yes (admin) |

---

## BR-06 — Recording Rules

| ID | Rule | Default | Configurable |
|----|------|---------|--------------|
| BR-06-1 | Recording starts automatically when session starts | **[TBD]** | Yes (per teacher) |
| BR-06-2 | Teacher can disable recording for a specific session | Optional | Yes |
| BR-06-3 | Recording access granted to student if: status = 'attended' OR teacher grants manually | Mandatory | No |
| BR-06-4 | Student with 'absent' status cannot access recording unless teacher grants | Mandatory | No |
| BR-06-5 | Parent has same recording access as linked student | Mandatory | No |
| BR-06-6 | Recordings are streamed only. No download permitted. | Mandatory | No |
| BR-06-7 | Recordings are watermarked with student name + timestamp | Mandatory | No |
| BR-06-8 | Recording consumes an additional credit: **[TBD: yes / no]** | **[TBD]** | Yes (admin) |
| BR-06-9 | Recording retained for **[TBD]** days then auto-deleted | **[TBD: 90 days]** | Yes (admin) |
| BR-06-10 | Maximum concurrent recording viewers per session recording: **[TBD]** | **[TBD: 1 device]** | Yes |

---

## BR-07 — Device & Security Rules

| ID | Rule | Default | Configurable |
|----|------|---------|--------------|
| BR-07-1 | Maximum devices per student per live session: **[TBD]** | **[TBD: 1]** | Yes (admin) |
| BR-07-2 | Maximum devices for recording playback: **[TBD]** | **[TBD: 1]** | Yes (admin) |
| BR-07-3 | Session join link is single-use or time-limited | Mandatory | No |
| BR-07-4 | Recording URL is signed and expires after **[TBD]** hours | **[TBD: 4h]** | Yes |
| BR-07-5 | Sharing a session link with another user is prevented by token binding | Mandatory | No |

---

## BR-08 — Notification Rules

| ID | Rule | Trigger | Channel |
|----|------|---------|---------|
| BR-08-1 | Session booked | On booking created | Email + In-app |
| BR-08-2 | Booking approved/rejected | On teacher action | Email + In-app |
| BR-08-3 | Session reminder | 24h and 30min before | Email + In-app |
| BR-08-4 | Session cancelled | On cancellation | Email + In-app |
| BR-08-5 | Credit low warning | When credits ≤ **[TBD: 2]** | Email + In-app |
| BR-08-6 | Credits expiring | 7 days before expiry | Email + In-app |
| BR-08-7 | Credits expired | On expiry day | Email + In-app |
| BR-08-8 | Recording ready | When upload complete | Email + In-app |
| BR-08-9 | No-show detected | 15 min after session start, student absent | Email to student |
| BR-08-10 | Teacher no-show | 15 min after session start, teacher absent | Email to admin + student |

---

## BR-09 — Financial Rules

| ID | Rule | Default |
|----|------|---------|
| BR-09-1 | All credit transactions are logged with: timestamp, actor, reason, before/after balance | Mandatory |
| BR-09-2 | Credit log is immutable (no deletes, only corrections with audit entry) | Mandatory |
| BR-09-3 | Finance team can export credit ledger as CSV | Mandatory |
| BR-09-4 | Refunds restore credits to the original package (respecting expiry) | **[TBD]** |
| BR-09-5 | Package price is set in: **[TBD: currency]** | **[TBD]** |

---

## Summary of All [TBD] Items Requiring Answers

| # | Question | Category |
|---|----------|----------|
| 1 | Can one parent link to multiple students? | Accounts |
| 2 | Are credits per-subject or platform-wide? | Credits |
| 3 | Can a student have multiple active packages? | Credits |
| 4 | Does package validity start on purchase or first use? | Credits |
| 5 | When is credit deducted — reservation, approval, or session start? | Credits |
| 6 | Minimum advance booking notice (hours)? | Booking |
| 7 | Maximum bookings per student per day? | Booking |
| 8 | Teacher response window (hours)? | Booking |
| 9 | What happens if teacher doesn't respond — auto-expire or auto-approve? | Booking |
| 10 | Student cancellation window (hours)? | Cancellation |
| 11 | What happens to credit if student cancels within window? | Cancellation |
| 12 | What happens to credit on student no-show? | Cancellation |
| 13 | How many teacher cancellations trigger admin alert? | Cancellation |
| 14 | Minimum attendance % to count as attended? | Attendance |
| 15 | Late join grace period (minutes)? | Attendance |
| 16 | Does recording access cost a credit? | Recording |
| 17 | Recording retention period (days)? | Recording |
| 18 | Can student download recordings? | Recording |
| 19 | Can teacher disable recording per session? | Recording |
| 20 | Maximum devices per session (live)? | Security |
| 21 | Maximum devices for recording playback? | Security |
| 22 | Is payment online or manual? | Finance |
| 23 | What currency? | Finance |

---

**STATUS: 🟡 AWAITING APPROVAL**

> **Next Step:** Answer the 23 questions above. Once answered, we move to Phase 2 — Functional Requirements Document.

*Approved by: _________________ Date: _________________*
