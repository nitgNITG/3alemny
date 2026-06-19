# Business Requirements Document (BRD)
**Document:** BRD-001
**Project:** 3alemny — 1-to-1 Live Sessions Platform
**Version:** 1.0 DRAFT
**Date:** 2026-06-19
**Status:** 🟡 AWAITING APPROVAL

---

## 1. Executive Summary

3alemny is an online education platform built on Moodle where students book private 1-to-1 sessions with teachers. Sessions are delivered live via Zoom (embedded inside Moodle). Teachers manage their own availability. Students purchase session credits via packages. Sessions are recorded and accessible to students and parents post-session.

---

## 2. Business Objectives

| # | Objective | Success Metric |
|---|-----------|---------------|
| BO-1 | Students can independently book and attend private sessions | 100% of bookings handled without admin intervention |
| BO-2 | Teachers fully control their availability | Zero double-bookings |
| BO-3 | Credits are tracked accurately with full audit trail | Zero discrepancies in credit ledger |
| BO-4 | Recordings are secure and watermarked | Zero unauthorised access incidents |
| BO-5 | Parents have full visibility of their child's progress | Parent satisfaction ≥ 4/5 |
| BO-6 | Admin has complete reporting and control | All reports available without manual export |

---

## 3. Scope

### In Scope
- Teacher availability calendar management
- Student session booking with credit validation
- Teacher approval / rejection workflow
- Zoom meeting auto-creation via API
- Live session delivery via embedded Zoom (inside Moodle page)
- Webhook-based attendance tracking
- Recording upload to Bunny CDN / VdoCipher
- Secure watermarked recording playback
- Package management (admin creates, student purchases)
- Credit ledger with full audit trail
- Parent portal (read-only linked to student)
- Notification system (email + in-app)
- Admin reports and controls
- Mobile app access (Flutter — future phase)

### Out of Scope
- Payment gateway integration (Phase 1 = admin manual assignment)
- Live chat between teacher and student outside sessions
- AI-based teacher matching
- Multi-teacher group sessions (separate feature)
- Moodle core modifications

---

## 4. Platform Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    MOODLE (Core LMS)                    │
│                                                         │
│  ┌──────────────────────────────────────────────────┐   │
│  │           local_livesessions (Plugin)            │   │
│  │                                                  │   │
│  │  ┌──────────┐  ┌──────────┐  ┌───────────────┐  │   │
│  │  │ Booking  │  │Attendance│  │   Recording   │  │   │
│  │  │ Engine   │  │ Engine   │  │   Manager     │  │   │
│  │  └────┬─────┘  └────┬─────┘  └──────┬────────┘  │   │
│  │       │             │               │            │   │
│  └───────┼─────────────┼───────────────┼────────────┘   │
│          │             │               │                │
└──────────┼─────────────┼───────────────┼────────────────┘
           │             │               │
    ┌──────▼──────┐  ┌───▼────┐  ┌──────▼──────┐
    │  Zoom API   │  │ Zoom   │  │Bunny/VdoCipher│
    │ (S2S OAuth) │  │Webhooks│  │  (CDN/DRM)  │
    └─────────────┘  └────────┘  └─────────────┘
```

---

## 5. Stakeholder Sign-off

| Stakeholder | Role | Signature | Date |
|-------------|------|-----------|------|
| | Admin / Business Owner | | |
| | Finance Lead | | |
| | Lead Teacher | | |

---

## 6. Supporting Documents

| Document | Link |
|----------|------|
| Stakeholder Analysis | [stakeholders.md](stakeholders.md) |
| Business Workflows | [workflows.md](workflows.md) |
| Business Rules | [business-rules.md](business-rules.md) |

---

## 7. Open Items — MUST be resolved before Phase 2

> See [business-rules.md](business-rules.md) — Section: "Summary of All TBD Items"
> **23 questions require answers.**

---

**STATUS: 🟡 AWAITING APPROVAL**

Once all 23 questions in business-rules.md are answered and this document is signed off, Phase 2 (Functional Requirements) will begin.

*Approved by: _________________ Date: _________________*
