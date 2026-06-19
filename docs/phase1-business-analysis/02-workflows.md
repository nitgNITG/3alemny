# Business Workflows
**Document:** WF-001
**Phase:** 1 — Business Analysis
**Version:** 1.6 — WF-04 resolved; WF-02 package structure corrected
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
| Q-WF01-1 | Who creates student account? | **Both** — student self-registers OR admin creates | ✅ Resolved |
| Q-WF01-2 | Is course required before buying package? | **No** — buy package first, enrol later | ✅ Resolved |
| Q-AV-1 | Min/max slot duration? | **Fixed: 50 minutes** | ✅ Resolved |
| Q-AV-2 | How far ahead can teacher set availability? | Teacher defines weekly schedule (working hours/days); slots auto-generated from it | ✅ Resolved |
| Q-AV-2b | Recording retention before archive? | **30 days** (configurable in admin settings) | ✅ Resolved |
| Q-AV-3 | Buffer time between sessions? | **10 minutes** auto-enforced after each session | ✅ Resolved |
| Q-AV-4 | Slots for specific students only? | **Yes** — teacher restricts slot → student gets Accept/Reject → if rejected, slot reopens for all | ✅ Resolved |
| Q-AV-4b | Student reschedule window? | Student can reschedule up to **30 minutes before** session start | ✅ Resolved |
| Q-AV-5 | Timezone handling? | **Multi-timezone** — each user sees times in their own timezone; teacher sets in their TZ, student sees in theirs | ✅ Resolved |
| Q-BK-1 | Auto-accept per teacher or platform-wide? | **Teacher must approve** each booking manually | ✅ Resolved |
| Q-BK-2 | When is credit deducted? | **At booking time** (not after session) | ✅ Resolved |
| Q-BK-3 | Multiple sessions per day with same teacher? | **Yes** | ✅ Resolved |
| Q-BK-4 | Minimum advance booking notice (hours)? | **1 hour** minimum before session start | ✅ Resolved |
| Q-BK-5 | Can student add note when booking? | **Yes** | ✅ Resolved |
| Q-BK-6 | Max concurrent active bookings per student? | **12** | ✅ Resolved |
| Q-PKG-SIZES | Package session counts? | **8, 12, or 20 sessions** — tiered pricing (more = cheaper per session) | ✅ Resolved |
| Q-PKG-PREF | Session time preference? | Student selects **morning or evening** preference when booking | ✅ Resolved |
| Q-PKG-VALIDITY | Package validity period? | **One academic year** (not fixed days) — the year the student is enrolled in | ✅ Resolved |
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

## WF-01 — Student Registration & Onboarding ✅ RESOLVED

```
                    ┌─────────────────────────────────────┐
                    │  Account Creation (BOTH supported): │
                    │  A) Student self-registers          │
                    │  B) Admin creates account           │
                    └─────────────────────────────────────┘
                    │
              ┌─────┴──────────────────────┐
              ▼                            ▼
      [A] Self-registration           [B] Admin creates
      Student fills sign-up form      Admin opens Users area
      Moodle creates account          Creates user profile
              │                            │
              └──────────────┬─────────────┘
                             ▼
              Student profile exists in system
              Credits = 0 | No active package
                             │
                             ▼
              ┌─────────────────────────────────────┐
              │ NEXT STEPS (any order):             │
              │ 1) Buy a package → get credits      │
              │ 2) Enrol in a course (optional now) │
              │ 3) Browse teachers & book sessions  │
              └─────────────────────────────────────┘
                             │
                             ▼
              Packages are for LIVE SESSIONS only.
              No enrolment required before purchase.
              Student can enrol after buying and
              select different teachers per course.
```

**Business Rules confirmed for WF-01:**
- ✅ Student can self-register **or** admin creates the account
- ✅ No course enrolment required before buying a package
- ✅ Packages are scoped to **live sessions only** (not general Moodle content)
- ✅ Student can enrol in any course after purchasing and pick different teachers

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
- ✅ Package sizes: **8 sessions / 12 sessions / 20 sessions** — tiered pricing (more sessions = better price per session)
- ✅ Student selects **morning or evening** time preference when purchasing/booking
- ✅ Package validity: **one full academic year** (the year the student is currently enrolled in), NOT a fixed number of days

---

## WF-03 — Teacher Availability Management ✅ RESOLVED

```
[Teacher] ──► Opens Availability Settings
                    │
                    ▼
              Defines WEEKLY SCHEDULE
              (working days + working hours, per their timezone)
              e.g. Sun–Thu, 10:00–18:00 (Cairo)
                   Mon–Fri, 09:00–17:00 (Riyadh)
                    │
                    ▼
              System auto-generates 50-min bookable slots
              within those hours, with 10-min buffer between
              Effective block per slot = 60 minutes
                    │
                    ▼
              Teacher can also set BLOCKED DATES
              (holidays, leave, personal commitments)
              → blocked dates have no available slots
                    │
                    ▼
              For each slot, teacher chooses visibility:
              ┌────────────────────────────────────────┐
              │ A) OPEN — any enrolled student can     │
              │    see and book                        │
              │                                        │
              │ B) RESERVED — assigned to ONE specific │
              │    student only                        │
              └────────────────────────────────────────┘
                    │
                    ▼
         ┌──────────┴────────────────────────────┐
         ▼  (Open slots)                         ▼  (Reserved slots)
  Students see slot                    Target student gets notification
  in their own timezone                "Teacher reserved a slot for you"
  and can book directly                Student: ACCEPT or REJECT
  → go to WF-04                                │
                                    ┌───────────┴──────────┐
                                    ▼ Accept               ▼ Reject
                              Credit deducted         Slot reopens
                              Session confirmed       as OPEN for all
                              → go to WF-04 end       students

  RESCHEDULING:
  Student can reschedule any booked session
  → up to 30 minutes before session start time
  → picks a different open slot from same teacher
  → original slot is released back to Open pool
```

**Attendance & Recordings:**
```
  Session ends
       │
       ▼
  System records:
  • join_time (when participant joined)
  • leave_time (when participant left)
  • duration = leave_time − join_time
       │
       ▼
  Recording saved and available to student for 30 days
  (configurable in admin settings)
       │
       ▼
  After 30 days → recording moved to ARCHIVE
  (no longer visible on student dashboard)
```

**Business Rules confirmed for WF-03:**
- ✅ Session duration is **fixed at 50 minutes** per slot
- ✅ **10-minute buffer** auto-enforced after each session (effective block = 60 min)
- ✅ Teacher defines a **weekly schedule** (working hours/days); slots are auto-generated from it
- ✅ Teacher can mark **blocked dates** (holidays, leave)
- ✅ Teacher can **reserve a slot for one specific student** — student must Accept/Reject
- ✅ If student **rejects** a reserved slot → slot reopens as Open for all
- ✅ If student **accepts** → credit deducted from their wallet
- ✅ Student can **reschedule up to 30 minutes** before session start
- ✅ **Multi-timezone** — teacher sets schedule in their local timezone; students see all times in their own timezone
- ✅ Recordings available for **30 days** then archived (default configurable in admin settings)
- ✅ System records actual **join time and leave time** per participant

---

## WF-04 — Session Booking (Student) ✅ RESOLVED

```
[Student] ──► Opens teacher profile / available slots
                    │
                    ▼
              Filters by preference:
              ┌──────────────────────────┐
              │ • Morning slots          │
              │ • Evening slots          │
              └──────────────────────────┘
                    │
                    ▼
              Selects a 50-min slot (shown in student's timezone)
              Adds note/topic for the teacher (optional but available)
                    │
                    ▼
              ┌──────────────────────────────────────┐
              │ System validates:                    │
              │ ✓ Student has active package         │
              │ ✓ Student has ≥ 1 credit remaining   │
              │ ✓ Package not expired (within        │
              │   academic year)                     │
              │ ✓ Slot still available               │
              │ ✓ No overlapping booking for student │
              │ ✓ Session is ≥ 1 hour away           │
              │ ✓ Student has < 12 active bookings   │
              └──────────────────────────────────────┘
                    │
              ┌─────┴──────┐
              ▼            ▼
           VALID        INVALID ──► Error shown, booking blocked
              │
              ▼
         Credit DEDUCTED immediately from student wallet
         (1 credit per session, at booking time)
         Booking status: PENDING TEACHER APPROVAL
         Slot marked as RESERVED (not visible to other students)
              │
              ▼
         Teacher notified → go to WF-05 (Approval)
              │
              ▼
         Student sees booking in "My Sessions":
         Status: ⏳ Pending Approval
         Note: credit already deducted; refunded if teacher rejects
```

**Business Rules confirmed for WF-04:**
- ✅ **Teacher must manually approve** every booking (no auto-accept)
- ✅ **Credit deducted at booking time** — not at approval, not at session
- ✅ If teacher **rejects** → credit is refunded to student wallet
- ✅ Student can book **multiple sessions per day** with the same teacher
- ✅ Minimum booking notice: **1 hour** before session start
- ✅ Student can add a **note/topic** when booking
- ✅ Maximum **12 active (upcoming) bookings** per student at any time
- ✅ Student filters slots by **morning or evening** preference
- ✅ Package validity runs for **the full academic year** of enrolment

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
