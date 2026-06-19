# Stakeholder Analysis
**Document:** STK-001
**Phase:** 1 — Business Analysis
**Version:** 1.1 — Answers incorporated
**Status:** 🟢 APPROVED
**Approved:** 2026-06-19

---

## 1. Stakeholder Map

```
                          ┌─────────────┐
                          │    ADMIN    │
                          │  (owns all  │
                          │  reports)   │
                          └──────┬──────┘
                                 │ manages
              ┌──────────────────┼──────────────────┐
              ▼                  ▼                   ▼
       ┌────────────┐    ┌──────────────┐   ┌──────────────┐
       │  TEACHER   │    │   MANAGER    │   │   SUPPORT    │
       │  (can also │    │  (finance    │   │   TEAM       │
       │  be student│    │  via admin   │   │  (can re-    │
       │  in other  │    │  reports)    │   │  schedule)   │
       │  courses)  │    └──────────────┘   └──────────────┘
       └─────┬──────┘
             │ teaches
       ┌─────▼──────┐
       │  STUDENT   │◄──── linked to ────── PARENT (1 parent → many students)
       └────────────┘
```

---

## 2. Stakeholder Profiles

### 2.1 Student
| Attribute | Detail |
|-----------|--------|
| **Who** | Learner enrolled in one or more courses |
| **Goals** | Book sessions, join live, access recordings, track progress |
| **Pain Points** | Complex booking, credits expiring, no recording access |
| **Tech Level** | Basic — uses web browser and mobile app |
| **Moodle Role** | `student` |
| **Plugin Capabilities** | joinSession, requestSession, viewRecording |
| **Special Rule** | A teacher user can also enrol as a student in other courses ✅ |

### 2.2 Parent
| Attribute | Detail |
|-----------|--------|
| **Who** | Guardian of one or more students |
| **Goals** | Monitor attendance, track spending, watch recordings |
| **Pain Points** | No visibility into what child is doing |
| **Tech Level** | Basic — needs simple read-only dashboard |
| **Moodle Role** | Custom `parent` role (no editing rights) |
| **Plugin Capabilities** | viewAttendance (linked students only), viewRecording (linked students only) |
| **Special Rule** | One parent account can be linked to **multiple student accounts** ✅ |

### 2.3 Teacher
| Attribute | Detail |
|-----------|--------|
| **Who** | Subject matter expert delivering 1-to-1 sessions |
| **Goals** | Manage calendar, run sessions, view student notes |
| **Pain Points** | No-shows, last-minute cancellations, calendar chaos |
| **Tech Level** | Intermediate |
| **Moodle Role** | `editingteacher` |
| **Plugin Capabilities** | createSession, editSession, viewAttendance, manageAvailability |
| **Special Rule** | Can hold a second Moodle account enrolled as `student` in other courses ✅ |

### 2.4 Admin
| Attribute | Detail |
|-----------|--------|
| **Who** | Platform operator / business owner |
| **Goals** | Revenue reports, package config, dispute resolution, full financial visibility |
| **Pain Points** | Manual credit assignment, no unified reporting |
| **Tech Level** | Advanced |
| **Moodle Role** | `manager` or `admin` (site admin) |
| **Plugin Capabilities** | All capabilities |
| **Special Rule** | All financial data (credit ledger, package sales, refunds) is visible to Admins and Managers — no separate Finance login needed ✅ |

### 2.5 Finance Team
| Attribute | Detail |
|-----------|--------|
| **Who** | Reviews revenue, reconciles payments, processes refunds |
| **Goals** | Accurate credit ledger, revenue reports, refund processing |
| **Pain Points** | Manual reconciliation, no audit trail |
| **Tech Level** | Basic |
| **Moodle Role** | `manager` (uses Admin/Manager reports area) |
| **Plugin Capabilities** | viewReports, exportReports |
| **Special Rule** | **No separate system needed.** Finance team uses the Admin/Manager role in Moodle to access all financial reports. Reports are exportable as CSV. ✅ |

### 2.6 Support Team
| Attribute | Detail |
|-----------|--------|
| **Who** | First-line customer support |
| **Goals** | Resolve disputes, verify attendance, reschedule sessions |
| **Pain Points** | No proof of attendance, no session history |
| **Tech Level** | Intermediate |
| **Moodle Role** | Custom `support` role |
| **Plugin Capabilities** | viewAttendance, viewSessionLogs, rescheduleSession |
| **Special Rule** | Support Team **can manually reschedule sessions** on behalf of students or teachers ✅ |

---

## 3. Decisions Recorded

| ID | Question | Answer | Impact |
|----|----------|--------|--------|
| STK-Q1 | Can one parent account be linked to multiple students? | **YES** | Parent–student link table must be 1-to-many |
| STK-Q2 | Can a teacher also be a student on the platform? | **YES** | No system restriction — role is per-course enrolment |
| STK-Q3 | Finance Team needs separate login or report export? | **Report only — all finance visible to Admin/Manager role** | No separate Finance module needed; reports built into admin area |
| STK-Q4 | Can Support Team manually reschedule sessions? | **YES** | Support role needs rescheduleSession capability |

---

## 4. Capability Matrix

| Capability | Student | Parent | Teacher | Support | Manager | Admin |
|-----------|---------|--------|---------|---------|---------|-------|
| Book session | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Approve/reject booking | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| Reschedule session | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| Join live session | ✅ | ❌ | ✅ | ❌ | ❌ | ✅ |
| View attendance | own only | linked students | own students | all | all | all |
| View recordings | own only | linked students | own sessions | all | all | all |
| Manage packages | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Assign credits | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| View financial reports | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Export reports (CSV) | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Manage teachers | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Link parent to student | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |

---

**STATUS: 🟢 APPROVED**
*Approved by: Stakeholder — 2026-06-19*
