# Business Workflows
**Document:** WF-001
**Phase:** 1 — Business Analysis
**Status:** 🟡 AWAITING APPROVAL

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
              Credits = 0 | No packages yet
                    │
                    ▼
              Student directed to ──► [Package Purchase WF-02]
```

**Decision Points:**
- [ ] Who creates the student account — student self-registers or admin only?
- [ ] Is a course mandatory before buying a package?

---

## WF-02 — Package Purchase

```
[Student] ──► Views available packages
                    │
                    ▼
              Selects package
              (e.g., 10 sessions / 30-day validity / Subject: Math)
                    │
                    ▼
              ┌─────────────────────────────┐
              │  Payment Method?            │
              │  A) Online (gateway)        │
              │  B) Manual (admin assigns)  │
              └─────────────────────────────┘
                    │
              ┌─────┴──────┐
              ▼            ▼
         [Online]      [Manual]
         Gateway       Admin confirms
         confirms      in dashboard
              │            │
              └─────┬──────┘
                    ▼
              Credits added to student wallet
              Validity start date = today
              Expiry date = today + package days
                    │
                    ▼
              Student notified:
              "10 credits added. Valid until DD/MM/YYYY"
```

**Decision Points:**
- [ ] Q-PKG-1: Online payment or admin-manual only?
- [ ] Q-PKG-2: Can student have multiple active packages?
- [ ] Q-PKG-3: Credits per-subject or platform-wide?
- [ ] Q-PKG-4: Can packages be gifted / transferred?
- [ ] Q-PKG-5: What currency?

---

## WF-03 — Teacher Availability Management

```
[Teacher] ──► Opens Availability Calendar
                    │
                    ▼
              Sets weekly recurring slots
              Example: Mon/Wed/Fri → 4pm–8pm
              Slot duration: 60 min (or configurable)
                    │
                    ▼
              Sets blocked dates
              (holidays, personal leave)
                    │
                    ▼
              System generates bookable slots
              Visible to enrolled students
                    │
                    ▼
              Teacher can:
              ├── Edit future unbooked slots ✓
              ├── Delete future unbooked slots ✓
              ├── Block already-booked slot? ──► triggers reschedule WF
              └── Set slot as "private" (specific student only)?
```

**Decision Points:**
- [ ] Q-AV-1: Minimum/maximum slot duration?
- [ ] Q-AV-2: How far ahead can teacher publish availability? (1 week / 1 month / custom)
- [ ] Q-AV-3: Buffer time required between sessions? (e.g., 15 min break)
- [ ] Q-AV-4: Can a teacher offer slots to specific students only?
- [ ] Q-AV-5: Timezone handling — teacher's timezone vs student's timezone?

---

## WF-04 — Session Booking (Student)

```
[Student] ──► Opens teacher profile or calendar
                    │
                    ▼
              Views available slots
              (filtered: course match, has credits, no overlap)
                    │
                    ▼
              Selects slot + adds optional note
                    │
                    ▼
              ┌──────────────────────────────────┐
              │ System validates:                │
              │ ✓ Student has ≥1 credit          │
              │ ✓ Slot still available           │
              │ ✓ No overlapping booking         │
              │ ✓ Package not expired            │
              └──────────────────────────────────┘
                    │
              ┌─────┴──────┐
              ▼            ▼
           VALID        INVALID
              │            │
              ▼            ▼
         Credit         Error shown
         RESERVED       (reason given)
         (not yet        Student cannot
         deducted)       proceed
              │
              ▼
         ┌────────────────────────┐
         │ Teacher approval mode? │
         └────────────────────────┘
              │
        ┌─────┴──────┐
        ▼            ▼
   Auto-Accept   Manual Review
        │            │
        ▼            ▼
   Zoom created   Request sent
   immediately    to teacher
   Both notified  ──► [WF-05]
```

**Decision Points:**
- [ ] Q-BK-1: Auto-accept per teacher (toggle) or platform-wide setting?
- [ ] Q-BK-2: When exactly is credit deducted — reservation, approval, or session start?
- [ ] Q-BK-3: Can student book multiple sessions per day with same teacher?
- [ ] Q-BK-4: Minimum notice period for booking? (e.g., must book 2h in advance)
- [ ] Q-BK-5: Can student add a note/topic when booking?
- [ ] Q-BK-6: Maximum concurrent active bookings per student?

---

## WF-05 — Session Approval (Teacher)

```
[Teacher] ◄── Notification: new booking request
                    │
                    ▼
              Reviews request:
              • Student name
              • Requested date/time
              • Note/topic
              • Student's credit balance (visible?)
                    │
              ┌─────┴──────┐
              ▼            ▼
          APPROVE        REJECT
              │            │
              ▼            ▼
         Must respond   Teacher enters
         within X hours optional reason
         or auto-expire      │
              │              ▼
              ▼         Credit reservation
         Zoom meeting   RELEASED
         auto-created   Student notified
         via API        with reason
              │         Can rebook
              ▼
         Credit DEDUCTED
         (or at session start?)
              │
              ▼
         Both parties get:
         • Zoom join link
         • Calendar invite
         • Reminder notification
```

**Decision Points:**
- [ ] Q-AP-1: How many hours does teacher have to respond before request expires?
- [ ] Q-AP-2: What happens to expired requests — auto-reject or stays pending?
- [ ] Q-AP-3: Does student see teacher's reason for rejection?

---

## WF-06 — Session Cancellation

```
WHO cancels?    WHEN?                   CREDIT OUTCOME        TEACHER IMPACT
─────────────────────────────────────────────────────────────────────────────
Student         > cancellation window   Full refund           None
Student         ≤ cancellation window   Credit forfeited      Teacher paid?
Student         No-show (never joined)  Credit forfeited      Teacher paid?
Teacher         Any time                Full refund           Penalty flag
Teacher         3rd cancellation        Full refund           Admin alert
System/Tech     Any time                Full refund           No penalty
─────────────────────────────────────────────────────────────────────────────
```

```
[User] ──► Requests cancellation
                │
                ▼
         System checks cancellation window
                │
         ┌──────┴───────┐
         ▼              ▼
    Within window   Outside window
         │              │
         ▼              ▼
    Credit policy   Full refund
    applied         processed
    (see table)
                │
                ▼
         Zoom meeting deleted
         Both parties notified
         Slot reopened (if teacher cancelled)
         Cancellation logged for audit
```

**Decision Points:**
- [ ] Q-CN-1: What is the cancellation window? (configurable per admin?)
- [ ] Q-CN-2: Is partial credit refund supported? (e.g., 50% if cancelled 12h before)
- [ ] Q-CN-3: Does teacher receive compensation for student no-shows?
- [ ] Q-CN-4: After how many teacher cancellations does admin get alerted?
- [ ] Q-CN-5: Can admin override any cancellation decision?

---

## WF-07 — Live Session (Attendance)

```
[System] — 15 min before session:
              Reminder sent to both parties
                    │
[Teacher] ──► Clicks "Start Session"
                    │
                    ▼
              Zoom meeting confirmed/created
              Teacher enters embedded room
                    │
[Student] ──► Joins session (within open window)
                    │
                    ▼
              Webhook: participant.joined
              → Attendance record: join_time saved
                    │
                    ▼
              Session in progress...
                    │
              Webhook: participant.left
              → Attendance record: leave_time saved
              → Duration segment calculated
                    │
                    ▼
              Session ends (teacher ends OR endtime passed)
              Webhook: meeting.ended
                    │
                    ▼
              Attendance Engine runs:
              attended_duration / session_duration × 100 = %
                    │
              ┌─────┴──────────────────┐
              ▼          ▼             ▼
          ≥ threshold  < threshold   Never joined
          "attended"   "partial"      "absent"
                    │
                    ▼
              Credit handling:
              (see Business Rules BR-05)
```

**Decision Points:**
- [ ] Q-AT-1: What % attendance = "attended"? (configurable per course?)
- [ ] Q-AT-2: Can teacher manually mark attendance override?
- [ ] Q-AT-3: Grace period for late joining? (e.g., first 10 min = on time)

---

## WF-08 — Recording Access

```
Session ends
      │
      ▼
Zoom sends: recording.completed webhook
      │
      ▼
Background task downloads recording from Zoom
      │
      ▼
Uploads to Bunny CDN / VdoCipher
      │
      ▼
Recording status → 'ready'
      │
      ▼
Access rules applied:
  ✓ Teacher         — always
  ✓ Student         — if status='attended' OR teacher grants manually
  ✓ Parent          — same as student (read-only)
  ✗ Other students  — never
  ✗ Public          — never
      │
      ▼
Student watches via:
  Embedded player (in Moodle page)
  Watermarked (name + timestamp overlay)
  No download option (stream only)
      │
      ▼
Access expiry (if configured):
  Recording deleted after X days
```

**Decision Points:**
- [ ] Q-RC-1: Does recording access cost a credit?
- [ ] Q-RC-2: How many days are recordings retained?
- [ ] Q-RC-3: Can student download or stream only?
- [ ] Q-RC-4: Can teacher disable recording for a session?
- [ ] Q-RC-5: Is parent access automatic or must teacher grant it?

---

## WF-09 — Parent Monitoring

```
[Admin] ──► Links parent account to student account(s)
                    │
                    ▼
[Parent] ──► Logs in → sees Parent Dashboard
                    │
                    ▼
              Views per linked student:
              • Upcoming sessions
              • Attendance history (attended/absent/partial)
              • Credits remaining / expiry date
              • Recordings (watch only)
              • Teacher notes (if teacher chooses to share)
```

---

## WF-10 — Refund Requests

```
[Student/Parent] ──► Submits refund request (reason required)
                            │
                            ▼
                     Support Team reviews
                            │
                     ┌──────┴──────┐
                     ▼             ▼
                  APPROVE       DECLINE
                     │             │
                     ▼             ▼
               Credits returned  Student notified
               to student wallet with reason
               Finance log entry
               created
```

---

## WF-11 — Package Expiration

```
[System CRON — daily] ──► Scans all active packages
                                  │
                                  ▼
                    Finds packages where expiry_date < today
                                  │
                                  ▼
                    Remaining credits → EXPIRED (not usable)
                                  │
                                  ▼
                    Student notified: "X credits expired"
                    Finance log entry created
                    ─────────────────────────
                    7 days BEFORE expiry:
                    Warning email sent to student + parent
```

---

**STATUS: 🟡 AWAITING APPROVAL**
*Answer the Decision Point questions above before Phase 2 begins.*
*Approved by: _________________ Date: _________________*
