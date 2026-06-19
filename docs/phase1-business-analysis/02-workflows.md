# Business Workflows
**Document:** WF-001
**Phase:** 1 — Business Analysis
**Version:** 1.2 — WF-02 answers incorporated
**Status:** 🟡 IN PROGRESS — remaining questions below

---

## Decision Log

| ID | Question | Answer | Status |
|----|----------|--------|--------|
| Q-PKG-1 | Online payment or admin-manual only? | **Both** — online gateway + admin manual | ✅ Resolved |
| Q-PKG-2 | Can student have multiple active packages? | **No** — only one active package at a time | ✅ Resolved |
| Q-PKG-3 | Credits per-subject or platform-wide? | **Platform-wide** — credits work across all courses | ✅ Resolved |
| Q-PKG-4 | Can packages be gifted / transferred? | **No** | ✅ Resolved |
| Q-PKG-5 | What currency? | **EGP (Egyptian Pound)** | ✅ Resolved |
| Q-WF01-1 | Who creates student account? | ⬜ Pending |
| Q-WF01-2 | Is course required before buying package? | ⬜ Pending |
| Q-AV-1 | Min/max slot duration? | ⬜ Pending |
| Q-AV-2 | How far ahead can teacher set availability? | ⬜ Pending |
| Q-AV-3 | Buffer time between sessions? | ⬜ Pending |
| Q-AV-4 | Slots for specific students only? | ⬜ Pending |
| Q-AV-5 | Timezone handling? | ⬜ Pending |
| Q-BK-1 | Auto-accept per teacher or platform-wide? | ⬜ Pending |
| Q-BK-2 | When is credit deducted? | ⬜ Pending |
| Q-BK-3 | Multiple sessions per day with same teacher? | ⬜ Pending |
| Q-BK-4 | Minimum advance booking notice (hours)? | ⬜ Pending |
| Q-BK-5 | Can student add note when booking? | ⬜ Pending |
| Q-BK-6 | Max concurrent active bookings per student? | ⬜ Pending |
| Q-AP-1 | Teacher response window (hours)? | ⬜ Pending |
| Q-AP-2 | Expired requests — auto-reject or stays pending? | ⬜ Pending |
| Q-AP-3 | Does student see rejection reason? | ⬜ Pending |
| Q-CN-1 | Cancellation window? | ⬜ Pending |
| Q-CN-2 | Partial credit refund supported? | ⬜ Pending |
| Q-CN-3 | Teacher paid on student no-show? | ⬜ Pending |
| Q-CN-4 | How many teacher cancellations = admin alert? | ⬜ Pending |
| Q-CN-5 | Can admin override any cancellation? | ⬜ Pending |
| Q-AT-1 | Attendance % threshold? | ⬜ Pending |
| Q-AT-2 | Teacher can manually override attendance? | ⬜ Pending |
| Q-AT-3 | Grace period for late joining? | ⬜ Pending |
| Q-RC-1 | Recording access costs a credit? | ⬜ Pending |
| Q-RC-2 | Recording retention days? | ⬜ Pending |
| Q-RC-3 | Download or stream only? | ⬜ Pending |
| Q-RC-4 | Teacher can disable recording per session? | ⬜ Pending |
| Q-RC-5 | Parent access automatic or teacher grants? | ⬜ Pending |

---

## WF-01 — Student Registration & Onboarding

```
[Student] ──► Registers on Moodle (or Admin creates account)
                    │
                    ▼
              Admin assigns student to Course(s)
                    │
                    ▼
              Student profile visible in system
              Credits = 0 | No active package
                    │
                    ▼
              Student directed to ──► [Package Purchase WF-02]
```

**Pending Decisions:**
- [ ] **Q-WF01-1:** Who creates the student account — student self-registers or admin only?
- [ ] **Q-WF01-2:** Is a course enrolment mandatory before buying a package?

---

## WF-02 — Package Purchase ✅ RESOLVED

```
[Student] ──► Views available packages
                    │
                    ▼
              Selects package
              (e.g., 10 sessions / 30-day validity)
              Currency: EGP
                    │
                    ▼
              ┌─────────────────────────────────────┐
              │  Payment Method (BOTH supported):   │
              │  A) Online payment gateway          │
              │  B) Admin manual assignment         │
              └─────────────────────────────────────┘
                    │
              ┌─────┴──────────────────────┐
              ▼                            ▼
         [A] Online Gateway           [B] Admin Manual
         Student pays online          Admin opens student profile
         Gateway confirms             Selects package
              │                       Clicks "Assign"
              └──────────┬────────────┘
                         ▼
              ┌─────────────────────────────────────┐
              │ RULES APPLIED:                      │
              │ • Student must NOT have active       │
              │   package (only 1 allowed at a time) │
              │ • Credits are platform-wide          │
              │   (not subject-locked)               │
              │ • No gifting or transfer             │
              │ • Validity: today → today + N days   │
              └─────────────────────────────────────┘
                         │
                         ▼
              Credits added to student wallet
              Package status: ACTIVE
              Expiry: today + package.validity_days
                         │
                         ▼
              Student notified (email + in-app):
              "X credits added. Valid until DD/MM/YYYY (EGP)"
                         │
                         ▼
              If student already has ACTIVE package:
              ──► Error: "You already have an active package.
                          It expires on DATE."
```

**Business Rules confirmed for WF-02:**
- ✅ Only **1 active package** per student at any time
- ✅ Credits are **platform-wide** (usable on any course/teacher)
- ✅ Currency: **EGP**
- ✅ No package gifting or transfer
- ✅ Both online payment and admin manual assignment supported

---

## WF-03 — Teacher Availability Management

```
[Teacher] ──► Opens Availability Calendar
                    │
                    ▼
              Sets weekly recurring slots
              Slot duration: [TBD — Q-AV-1]
                    │
                    ▼
              Sets blocked dates (holidays, leave)
                    │
                    ▼
              System generates bookable slots
              Visible to enrolled students
              Buffer between slots: [TBD — Q-AV-3]
                    │
                    ▼
              Teacher can:
              ├── Edit future unbooked slots ✓
              ├── Delete future unbooked slots ✓
              ├── Block already-booked slot ──► reschedule WF triggered
              └── Set slot for specific student only? [TBD — Q-AV-4]
```

**Pending Decisions:**
- [ ] **Q-AV-1:** Minimum and maximum slot duration (minutes)?
- [ ] **Q-AV-2:** How far ahead can teacher publish availability?
- [ ] **Q-AV-3:** Is a buffer time required between sessions?
- [ ] **Q-AV-4:** Can a teacher restrict a slot to a specific student?
- [ ] **Q-AV-5:** Timezone — is the platform Egypt-only (EGP currency suggests so) or multi-timezone?

---

## WF-04 — Session Booking (Student)

```
[Student] ──► Opens teacher calendar / available slots
                    │
                    ▼
              Views available slots
              (filtered: student enrolled, has credits, no overlap)
                    │
                    ▼
              Selects slot
              Adds note/topic: [TBD — Q-BK-5]
                    │
                    ▼
              ┌──────────────────────────────────┐
              │ System validates:                │
              │ ✓ Student has active package     │
              │ ✓ Student has ≥ 1 credit         │
              │ ✓ Package not expired            │
              │ ✓ Slot still available           │
              │ ✓ No overlapping booking         │
              │ ✓ Minimum notice met [TBD Q-BK-4]│
              └──────────────────────────────────┘
                    │
              ┌─────┴──────┐
              ▼            ▼
           VALID        INVALID → Error shown, booking blocked
              │
              ▼
         Credit RESERVED (held, not yet deducted)
         Deduction timing: [TBD — Q-BK-2]
              │
              ▼
         ┌──────────────────────────────┐
         │ Teacher approval mode?       │
         │ [TBD — Q-BK-1]              │
         └──────────────────────────────┘
              │
        ┌─────┴──────┐
        ▼            ▼
   Auto-Accept   Manual Review ──► [WF-05]
        │
        ▼
   Zoom created immediately
   Credit deducted [TBD timing]
   Both notified
```

**Pending Decisions:**
- [ ] **Q-BK-1:** Auto-accept toggle per teacher, or one platform-wide setting?
- [ ] **Q-BK-2:** Credit deducted at: (a) booking, (b) teacher approval, or (c) session start?
- [ ] **Q-BK-3:** Can student book more than 1 session per day with same teacher?
- [ ] **Q-BK-4:** Minimum advance booking notice in hours?
- [ ] **Q-BK-5:** Can student add a note/topic when booking?
- [ ] **Q-BK-6:** Maximum number of pending/upcoming bookings per student at once?

---

## WF-05 — Session Approval (Teacher)

```
[Teacher] ◄── Notification: "New session request from [Student]"
                    │
                    ▼
              Reviews: student name, date/time, note
              Must respond within [TBD — Q-AP-1] hours
                    │
              ┌─────┴──────┐
              ▼            ▼
          APPROVE       REJECT
              │            │
              ▼            ▼
         Zoom meeting  Optional reason entered
         auto-created  [visible to student? TBD Q-AP-3]
         via API            │
              │             ▼
              ▼        Credit reservation RELEASED
         Credit DEDUCTED   Student notified
         [timing TBD]      Can rebook a different slot
              │
              ▼
         Student receives:
         • Confirmation notification
         • Zoom join link (embedded room)
         • Calendar reminder set
```

**Pending Decisions:**
- [ ] **Q-AP-1:** How many hours does teacher have to respond before request expires?
- [ ] **Q-AP-2:** On expiry — auto-reject and notify student, or remain pending indefinitely?
- [ ] **Q-AP-3:** Does student see the teacher's rejection reason?

---

## WF-06 — Session Cancellation

```
WHO cancels?    WHEN?                    CREDIT OUTCOME             TEACHER IMPACT
──────────────────────────────────────────────────────────────────────────────────
Student         Before window [TBD]      Full credit refund         None
Student         Within window [TBD]      [TBD — full/partial/none]  [TBD — paid?]
Student         No-show                  [TBD — forfeited/refunded] [TBD — paid?]
Teacher         Any time                 Full refund to student     Strike logged
Teacher         Nth cancellation [TBD]   Full refund to student     Admin alerted
System/Tech     Any time                 Full refund                No penalty
──────────────────────────────────────────────────────────────────────────────────
```

**Pending Decisions:**
- [ ] **Q-CN-1:** What is the cancellation window? (hours before session, configurable?)
- [ ] **Q-CN-2:** If student cancels within window — full forfeit, partial refund, or full refund?
- [ ] **Q-CN-3:** On student no-show — does teacher get credited / compensated?
- [ ] **Q-CN-4:** After how many teacher cancellations does admin receive an alert?
- [ ] **Q-CN-5:** Can admin override any cancellation credit decision?

---

## WF-07 — Live Session (Attendance)

```
T-15 min: System sends reminder to both parties
                    │
[Teacher] ──► Clicks "Start Session"
              Zoom meeting opened in embedded room
                    │
[Student] ──► Clicks "Join Session"
              Zoom embedded in Moodle page
                    │
              Webhook: participant.joined ──► join_time logged
                    │
              [ session in progress ]
                    │
              Webhook: participant.left  ──► leave_time logged
                    │
              Webhook: meeting.ended
                    │
              Attendance Engine:
              total_attended_minutes / session_duration_minutes × 100
                    │
              ┌─────────┬──────────────┬────────────┐
              ▼         ▼              ▼             ▼
          ≥ threshold  < threshold  Never joined  Teacher absent
          "attended"   "partial"    "absent"      ──► admin alert
```

**Pending Decisions:**
- [ ] **Q-AT-1:** Minimum % to be marked "attended"? (e.g., 70%) — configurable per admin?
- [ ] **Q-AT-2:** Can teacher manually override a student's attendance status?
- [ ] **Q-AT-3:** Is there a grace period for late joining that doesn't penalise the student?

---

## WF-08 — Recording Access

```
Session ends
      │
      ▼
Zoom webhook: recording.completed
      │
      ▼
Background task: download from Zoom → upload to Bunny/VdoCipher
      │
      ▼
Recording status = 'ready'
Watermark applied: student name + date
      │
      ▼
Access granted to:
  ✓ Teacher — always
  ✓ Student — if attended OR teacher manually grants
  ✓ Parent  — automatic or teacher grants? [TBD — Q-RC-5]
  ✗ Others  — never
      │
      ▼
Stream only (no download) [TBD — Q-RC-3]
      │
      ▼
Auto-delete after [TBD — Q-RC-2] days
```

**Pending Decisions:**
- [ ] **Q-RC-1:** Does watching a recording cost a credit?
- [ ] **Q-RC-2:** How many days are recordings kept before auto-deletion?
- [ ] **Q-RC-3:** Stream only or can student download?
- [ ] **Q-RC-4:** Can teacher disable recording for a specific session?
- [ ] **Q-RC-5:** Is parent recording access automatic (same as student) or must teacher grant it separately?

---

## WF-09 — Parent Monitoring

```
[Admin/Support] ──► Links parent account → student account(s)
                    One parent can link to MULTIPLE students ✅
                          │
[Parent] ──► Logs in → Parent Dashboard
                          │
                    Per linked student shows:
                    • Upcoming sessions (date, teacher, status)
                    • Attendance history (attended/partial/absent)
                    • Credits remaining + expiry date
                    • Recording library (stream only)
                    • No ability to book or cancel sessions
```

---

## WF-10 — Refund Requests

```
[Student or Parent] ──► Submits refund request
                        Reason field required
                              │
                        Support Team / Admin reviews
                              │
                    ┌─────────┴─────────┐
                    ▼                   ▼
                APPROVE             DECLINE
                    │                   │
                    ▼                   ▼
              Credit restored      Student notified
              to student wallet    with decline reason
              Finance log entry
              created with actor,
              reason, timestamp
```

---

## WF-11 — Package Expiration

```
[System CRON — runs daily at midnight]
          │
          ▼
    Scans all ACTIVE packages
          │
          ▼
    ┌───────────────────────────────┐
    │ 7 days before expiry_date:   │
    │ Warning sent → student+parent│
    └───────────────────────────────┘
          │
          ▼
    ┌───────────────────────────────┐
    │ On expiry_date:               │
    │ Package status → EXPIRED      │
    │ Remaining credits → EXPIRED   │
    │ (visible in history, unusable)│
    │ Student + parent notified     │
    │ Finance log entry created     │
    └───────────────────────────────┘

RULE: Student with EXPIRED package must purchase
      a new one before booking any session.
      Expired credits are NOT carried over.
```

---

## Summary — Remaining Open Questions

**Please answer the following to complete this document:**

### WF-01 Registration
1. **Q-WF01-1:** Student self-registers OR admin creates account only?
2. **Q-WF01-2:** Must student be enrolled in a course before buying a package?

### WF-03 Teacher Availability
3. **Q-AV-1:** Min / max slot duration in minutes?
4. **Q-AV-2:** How many weeks/months ahead can teacher publish slots?
5. **Q-AV-3:** Required buffer between sessions (minutes)? Or none?
6. **Q-AV-4:** Can teacher restrict a slot to one specific student?
7. **Q-AV-5:** Is the platform Egypt-only (one timezone) or multi-timezone?

### WF-04 Booking
8. **Q-BK-1:** Auto-accept — per-teacher toggle or platform-wide switch?
9. **Q-BK-2:** Credit deducted at: booking / teacher approval / session start?
10. **Q-BK-3:** Can student book more than 1 session per day with same teacher?
11. **Q-BK-4:** Minimum hours in advance a student must book?
12. **Q-BK-5:** Can student add a topic/note when booking?
13. **Q-BK-6:** Max number of pending bookings a student can have at once?

### WF-05 Approval
14. **Q-AP-1:** Hours teacher has to approve/reject before request expires?
15. **Q-AP-2:** On expiry — auto-reject or stays pending?
16. **Q-AP-3:** Does student see rejection reason?

### WF-06 Cancellation
17. **Q-CN-1:** Cancellation window in hours?
18. **Q-CN-2:** Student cancels within window — forfeit / partial refund / full refund?
19. **Q-CN-3:** Student no-show — teacher compensated?
20. **Q-CN-4:** How many teacher cancellations before admin alert?
21. **Q-CN-5:** Can admin override any cancellation credit decision?

### WF-07 Attendance
22. **Q-AT-1:** Attendance threshold % to count as "attended"?
23. **Q-AT-2:** Can teacher manually override attendance status?
24. **Q-AT-3:** Late-join grace period in minutes?

### WF-08 Recording
25. **Q-RC-1:** Does watching a recording cost a credit?
26. **Q-RC-2:** Recording retention period in days?
27. **Q-RC-3:** Stream only or allow download?
28. **Q-RC-4:** Can teacher disable recording per session?
29. **Q-RC-5:** Parent recording access — automatic or teacher must grant?

---

**STATUS: 🟡 IN PROGRESS**
*WF-02 ✅ Resolved | All others pending answers above*
*Approved by: _________________ Date: _________________*
