# Stakeholder Analysis
**Document:** STK-001
**Phase:** 1 — Business Analysis
**Status:** 🟡 AWAITING APPROVAL

---

## 1. Stakeholder Map

```
                          ┌─────────────┐
                          │    ADMIN    │
                          │ (Platform   │
                          │  Owner)     │
                          └──────┬──────┘
                                 │ manages
              ┌──────────────────┼──────────────────┐
              ▼                  ▼                   ▼
       ┌────────────┐    ┌──────────────┐   ┌──────────────┐
       │  TEACHER   │    │   FINANCE    │   │   SUPPORT    │
       │            │◄───│    TEAM      │   │    TEAM      │
       └─────┬──────┘    └──────────────┘   └──────────────┘
             │ teaches
       ┌─────▼──────┐
       │  STUDENT   │◄──── linked to ────── PARENT
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
| **Access** | Moodle student role + custom plugin capabilities |

### 2.2 Parent
| Attribute | Detail |
|-----------|--------|
| **Who** | Guardian of a student (often the payer) |
| **Goals** | Monitor attendance, track spending, watch recordings |
| **Pain Points** | No visibility into what child is doing |
| **Tech Level** | Basic — needs simple dashboard |
| **Access** | Read-only view of linked student's data |

### 2.3 Teacher
| Attribute | Detail |
|-----------|--------|
| **Who** | Subject matter expert delivering 1-to-1 sessions |
| **Goals** | Manage calendar, run sessions, see student notes |
| **Pain Points** | No-shows, last-minute cancellations, calendar chaos |
| **Tech Level** | Intermediate |
| **Access** | Moodle teacher role + availability + session management |

### 2.4 Admin
| Attribute | Detail |
|-----------|--------|
| **Who** | Platform operator / business owner |
| **Goals** | Revenue reports, package config, dispute resolution |
| **Pain Points** | Manual credit assignment, no unified reporting |
| **Tech Level** | Advanced |
| **Access** | Full plugin admin + Moodle site admin |

### 2.5 Finance Team
| Attribute | Detail |
|-----------|--------|
| **Who** | Handles payments, refunds, package sales |
| **Goals** | Accurate credit ledger, refund processing, revenue reports |
| **Pain Points** | Manual reconciliation, no audit trail |
| **Tech Level** | Basic — uses export/reports |
| **Access** | Read-only reports + manual credit adjustment |

### 2.6 Support Team
| Attribute | Detail |
|-----------|--------|
| **Who** | First-line customer support |
| **Goals** | Resolve disputes, verify attendance, reschedule sessions |
| **Pain Points** | No proof of attendance, no session history |
| **Tech Level** | Intermediate |
| **Access** | Read-only session + attendance logs |

---

## 3. Open Questions

| ID | Question | Directed To | Priority |
|----|----------|-------------|----------|
| STK-Q1 | Can one parent account be linked to multiple students? | Admin | HIGH |
| STK-Q2 | Can a teacher also be a student on the platform? | Admin | MEDIUM |
| STK-Q3 | Does Finance Team need a separate Moodle login or a report export? | Admin | HIGH |
| STK-Q4 | Does Support Team need the ability to manually reschedule sessions? | Admin | HIGH |

---

**STATUS: 🟡 AWAITING APPROVAL**
*Approved by: _________________ Date: _________________*
