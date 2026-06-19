# Features Per Role / الخصائص حسب الدور
**Document:** FPR-001
**Phase:** 1 — Business Analysis / المرحلة الأولى — تحليل الأعمال
**Version:** 1.1
**Status:** 🟡 UNDER REVIEW / قيد المراجعة
**Based on / مبني على:** STK-001, WF-001

> **EN:** This document defines exactly what each user role can do. Scope: Live Sessions plugin only.
> **AR:** هذا المستند يحدد بالضبط ما يستطيع كل دور فعله. النطاق: إضافة الحصص اللايف فقط.

---

## Role Summary / ملخص الأدوار

| Role / الدور | Can Book / يحجز | Can Teach / يدرّس | Financial / مالي |
|---|---|---|---|
| Student / طالب | ✅ | ❌ | رصيده فقط |
| Parent / ولي أمر | ❌ | ❌ | رصيد الطالب (عرض) |
| Teacher / مدرس | ✅ (كطالب في كورسات أخرى) | ✅ | أرباحه فقط |
| Support / دعم | ✅ (نيابةً) | ❌ | لا شيء |
| Manager / مدير | ✅ (نيابةً) | ❌ | تقارير كاملة |
| Admin / أدمن | ✅ (نيابةً) | ❌ | كامل + تدخل يدوي |

---

## 1. STUDENT / الطالب

### 1.1 Account & Registration / الحساب والتسجيل

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| S-01 | Self-register on platform | يسجّل نفسه على المنصة — أو الأدمن يعمله حساب |
| S-02 | Log in with email + password | يدخل بالإيميل والباسورد |
| S-03 | Edit own profile (name, photo, timezone) | يعدّل بياناته: الاسم، الصورة، التوقيت |
| S-04 | Enrol in courses after registration | يشترك في الكورسات بعد التسجيل — مش شرط قبل الباقة |

### 1.2 Packages & Credits / الباقات والكريدت

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| S-05 | View available packages (8 / 12 / 20 sessions, tiered EGP pricing) | يشوف الباقات المتاحة: ٨ أو ١٢ أو ٢٠ حصة بأسعار تدريجية بالجنيه |
| S-06 | Purchase a package online via payment gateway | يشتري الباقة أونلاين عبر بوابة الدفع |
| S-07 | View own credit balance and expiry date | يشوف رصيده المتبقي وتاريخ انتهاء الباقة |
| S-08 | View full credit transaction history | يشوف سجل الكريدت: خصومات، استرداد، شراء |
| S-09 | ❌ Cannot hold more than 1 active package | ❌ مش يقدر يشتري باقة وعنده باقة نشطة |
| S-10 | ❌ Cannot gift or transfer credits to others | ❌ مش يقدر يهدي أو ينقل الكريدت لحد تاني |

### 1.3 Browsing & Booking / التصفح والحجز

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| S-11 | Browse teachers in enrolled courses | يتصفح المدرسين في الكورسات اللي مشترك فيها |
| S-12 | View teacher's available slots (morning / evening filter) | يشوف المواعيد المتاحة للمدرس — يفلتر صباحي أو مسائي |
| S-13 | Book a 50-min session (min 1 hour notice) | يحجز حصة ٥٠ دقيقة — الحجز قبل الموعد بساعة على الأقل |
| S-14 | Add a note or topic when booking | يكتب ملاحظة أو موضوع الحصة وقت الحجز |
| S-15 | Credit deducted immediately on booking | الكريدت بيتخصم فور الحجز |
| S-16 | View booking status: Pending / Confirmed / Cancelled | يشوف حالة الحجز: معلق / مؤكد / ملغي |
| S-17 | ❌ Cannot hold more than 12 active upcoming bookings | ❌ مش يقدر يكون عنده أكتر من ١٢ حجز نشط في نفس الوقت |
| S-18 | Receive notification for reserved slot from teacher | يستلم إشعار لو المدرس خصص له سلوت |
| S-19 | Accept reserved slot → credit deducted (1 credit) | يوافق على السلوت المخصص → كريدت يتخصم |
| S-20 | Reject reserved slot → slot reopens for all | يرفض السلوت المخصص → السلوت يرجع مفتوح للكل |

### 1.4 Rescheduling & Cancellation / التأجيل والإلغاء

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| S-21 | Cancel session more than 1 hour before start → full refund | يلغي الحصة قبلها بأكتر من ساعة → كريدت يرجع كامل |
| S-22 | Cancel session within 1 hour of start → credit forfeited | يلغي في الساعة الأخيرة → الكريدت بيتخسر |
| S-23 | Reschedule to another slot (up to 30 min before session) | يأجّل الحصة لسلوت تاني — لحد ٣٠ دقيقة قبل الموعد |
| S-24 | View teacher's rejection reason | يشوف سبب رفض المدرس لو كتبه |

### 1.5 Live Session / الحصة المباشرة

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| S-25 | Join live session embedded inside Moodle page | يدخل الحصة مباشرة جوه صفحة Moodle — من غير تطبيق أو تاب جديد |
| S-26 | Join up to 10 minutes late without attendance penalty | يقدر يتأخر ١٠ دقائق من غير ما يتأثر حضوره |
| S-27 | Attendance tracked automatically (join + leave time) | الحضور بيتسجل تلقائياً: وقت الدخول ووقت الخروج |
| S-28 | ❌ Cannot start a session | ❌ مش يقدر يبدأ الحصة — الحصة بيبدأها المدرس بس |

### 1.6 Recordings / التسجيلات

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| S-29 | Stream own session recordings (no credit cost) | يشوف تسجيلات حصصه بدون خصم كريدت |
| S-30 | Max view count per recording (default 5, configurable) | عدد مشاهدات محدود لكل تسجيل (الافتراضي ٥ مرات) |
| S-31 | ❌ Cannot download recordings (stream only, DRM protected) | ❌ مش يقدر يحمّل التسجيل — مشاهدة فقط |
| S-32 | Access recordings for 30 days after session (configurable) | يشوف التسجيل ٣٠ يوم من بعد الحصة ثم يتأرشف |
| S-33 | ❌ No recording access if marked absent (0% attendance) | ❌ لو مسجل غائب ما يشوفش التسجيل |

---

## 2. PARENT / ولي الأمر

### 2.1 Account / الحساب

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| P-01 | Log in with own account | يدخل بحسابه الخاص |
| P-02 | One parent linked to multiple student accounts | حساب واحد ممكن يكون مربوط بأكتر من طالب |
| P-03 | Read-only access — cannot book, cancel, or join sessions | وصول للعرض فقط — مش يقدر يحجز أو يلغي أو يدخل حصة |

### 2.2 Monitoring Per Linked Student / متابعة كل طالب مرتبط

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| P-04 | View upcoming sessions (date, teacher, status) | يشوف الحصص القادمة: التاريخ، المدرس، الحالة |
| P-05 | View attendance history (Attended / Partial / Absent) | يشوف سجل حضور الطالب: حضر / جزئي / غائب |
| P-06 | View credit balance and package expiry | يشوف رصيد الكريدت وتاريخ انتهاء الباقة |
| P-07 | Stream session recordings (same as student access) | يشوف تسجيلات الطالب — نفس الصلاحية بتاعت الطالب |
| P-08 | ❌ No recording access if student is marked absent | ❌ لو الطالب مسجل غائب ما يشوفش التسجيل |

---

## 3. TEACHER / المدرس

### 3.1 Account / الحساب

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| T-01 | Log in with teacher account | يدخل بحسابه كمدرس |
| T-02 | Can hold a second student account in other courses | يقدر يكون له حساب طالب في كورسات تانية |
| T-03 | Edit own profile (name, bio, photo, timezone) | يعدّل بياناته: الاسم، السيرة، الصورة، التوقيت |

### 3.2 Availability & Schedule / الجدول والمواعيد

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| T-04 | Define weekly working schedule (days + hours in own timezone) | يحدد أيام وساعات عمله الأسبوعية بتوقيته هو |
| T-05 | System auto-generates 50-min bookable slots with 10-min buffer | النظام يولّد سلوتات ٥٠ دقيقة تلقائياً مع بوفر ١٠ دقائق |
| T-06 | Set blocked dates (holidays, leave) | يحجب أيام معينة: إجازات، ظروف شخصية |
| T-07 | Create open slot (visible to all enrolled students) | يعمل سلوت مفتوح يشوفه كل الطلاب المشتركين |
| T-08 | Create reserved slot (assigned to one specific student) | يعمل سلوت مخصص لطالب معين — الطالب بيستلم إشعار |
| T-09 | Edit or delete unbooked future slots | يعدّل أو يمسح سلوتات جاية لسه محجوزة |

### 3.3 Booking Requests & Approval / طلبات الحجز والموافقة

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| T-10 | Receive notification for new booking request (email + in-app) | يستلم إشعار بطلب حجز جديد: إيميل + إشعار داخل المنصة |
| T-11 | View request details (student name, date/time, note) | يشوف تفاصيل الطلب: اسم الطالب، الموعد، الملاحظة |
| T-12 | Approve booking → Zoom meeting auto-created | يوافق على الحجز → الزووم بيتعمل تلقائياً والطالب بيتبلّغ |
| T-13 | Reject booking with optional reason (visible to student) | يرفض الحجز مع سبب اختياري — الطالب بيشوفه + كريدت يرجع |
| T-14 | Deadline: must respond before first session starts that day | لازم يرد قبل بدء أول حصة عنده في يوم الحجز |

### 3.4 Live Session / الحصة المباشرة

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| T-15 | Start session (marks as Live, opens embedded Zoom room) | يبدأ الحصة → تتفتح في صفحة Moodle مباشرة |
| T-16 | Re-enter live session if disconnected | يقدر يرجع للحصة لو اتقطع |
| T-17 | Accept last-minute booking while session is live | يقدر يقبل حجز طارئ وهو شغال في حصة |
| T-18 | End session → triggers attendance calculation | ينهي الحصة → النظام يحسب الحضور تلقائياً |

### 3.5 Attendance & Override / الحضور والتعديل

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| T-19 | View attendance report for own students | يشوف تقرير حضور طلابه بعد كل حصة |
| T-20 | Manually override student attendance status | يعدّل نتيجة حضور الطالب يدوياً (مثلاً انقطاع انترنت) |

### 3.6 Recordings / التسجيلات

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| T-21 | Stream own session recordings (always, no time limit) | يشوف تسجيلات حصصه في أي وقت — من غير قيد الـ ٣٠ يوم |
| T-22 | ❌ Cannot disable recording for any session | ❌ مش يقدر يوقف التسجيل — كل الحصص بتتسجل دايماً |
| T-23 | View archived recordings | يشوف التسجيلات المؤرشفة (بعد الـ ٣٠ يوم) |

### 3.7 Cancellation / الإلغاء

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| T-24 | Cancel a confirmed session → full credit refund to student + strike +1 | يلغي حصة → كريدت يرجع للطالب + تسجيل إنذار |
| T-25 | 3rd cancellation → automatic alert sent to Admin | الإلغاء الثالث → تنبيه تلقائي للأدمن |

---

## 4. SUPPORT TEAM / فريق الدعم

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| SP-01 | View all sessions across all courses and teachers | يشوف كل الحصص في كل الكورسات |
| SP-02 | View full attendance logs (join time, leave time per participant) | يشوف سجل الحضور الكامل: وقت الدخول والخروج لكل مشارك |
| SP-03 | Reschedule a session on behalf of student or teacher (with reason logged) | يأجّل حصة نيابة عن الطالب أو المدرس مع تسجيل السبب |
| SP-04 | Link a parent account to one or more student accounts | يربط حساب ولي الأمر بحسابات الطلاب |
| SP-05 | ❌ Cannot assign credits or access financial reports | ❌ مش يقدر يضيف كريدت أو يشوف التقارير المالية |
| SP-06 | ❌ Cannot start or join a live session | ❌ مش يقدر يبدأ أو يدخل حصة لايف |

---

## 5. MANAGER / المدير

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| MG-01 | All Support Team features | كل صلاحيات فريق الدعم |
| MG-02 | Manually assign a package to a student (admin payment method) | يضيف باقة لطالب يدوياً (الدفع نقداً أو خارج المنظومة) |
| MG-03 | View financial reports (credit ledger, package sales, refunds) | يشوف التقارير المالية: الكريدت، مبيعات الباقات، الاستردادات |
| MG-04 | Export reports as CSV | يصدّر التقارير بصيغة CSV للمراجعة |
| MG-05 | Manage packages (create, edit, pricing for 8/12/20 tiers) | يدير الباقات: ينشئها، يعدّلها، يحدد أسعارها |
| MG-06 | View teacher cancellation strike count | يشوف عدد إلغاءات كل مدرس |
| MG-07 | ❌ Cannot override credit decisions | ❌ مش يقدر يرجع أو يخصم كريدت يدوياً — الأدمن بس |

---

## 6. ADMIN / الأدمن

| # | EN — Feature | AR — الخاصية |
|---|---|---|
| AD-01 | All Manager features | كل صلاحيات المدير |
| AD-02 | Manually refund credit to any student | يرجع كريدت لأي طالب يدوياً |
| AD-03 | Manually deduct credit from any student | يخصم كريدت من أي طالب يدوياً |
| AD-04 | Reset teacher cancellation strike count | يمسح سجل الإنذارات لأي مدرس |
| AD-05 | Configure all platform settings (numbers + yes/no policies) | يعدّل كل إعدادات المنصة: الأرقام والسياسات |
| AD-06 | Create, edit, deactivate any user account | إدارة كاملة لكل الحسابات |
| AD-07 | View all recordings including archived (no time limit) | يشوف كل التسجيلات بما فيها المؤرشفة — من غير قيد وقت |
| AD-08 | Impersonate any user for support purposes | يدخل على أي حساب لأغراض الدعم |
| AD-09 | Receive alert when teacher hits cancellation threshold | يستلم تنبيه لما مدرس يوصل للحد المحدد من الإلغاءات |
| AD-10 | Receive alert when teacher is absent from a session | يستلم تنبيه لو مدرس غاب عن حصة |

---

## 7. Configurable Settings / الإعدادات القابلة للتخصيص

كل القيم دي هي الـ defaults — الأدمن يقدر يغيّرها من صفحة إعدادات الإضافة.
All values are defaults — Admin can change from the plugin settings page.

| Setting / الإعداد | Default / الافتراضي | EN Description | AR الوصف |
|---|---|---|---|
| `session_duration_minutes` | `50` | Fixed session length in minutes | مدة الحصة بالدقائق |
| `session_buffer_minutes` | `10` | Gap enforced between consecutive sessions | الفاصل بين الحصص المتتالية |
| `booking_min_notice_hours` | `1` | Earliest a student can book before session | أقل مدة إشعار قبل الحجز |
| `cancellation_window_hours` | `1` | Hours before session where cancel = full refund | حد الإلغاء مع استرداد كامل |
| `late_cancel_forfeit` | `ON` | Credit lost if student cancels inside window | خسارة الكريدت عند الإلغاء المتأخر |
| `student_reschedule_window_minutes` | `30` | Latest the student can reschedule | آخر وقت للتأجيل قبل الحصة |
| `max_active_bookings` | `12` | Max upcoming bookings per student | أقصى حجوزات نشطة للطالب |
| `late_join_grace_minutes` | `10` | Minutes after start where join is still penalty-free | فترة السماح للدخول المتأخر |
| `attendance_threshold_pct` | `70` | Min % duration = "attended" | نسبة الحضور الدنيا للاعتبار حاضر |
| `teacher_attendance_override` | `ON` | Teacher can edit attendance status post-session | المدرس يقدر يعدّل نتيجة الحضور |
| `noshow_teacher_compensation` | `ON` | Teacher compensated on student no-show | تعويض المدرس عند غياب الطالب |
| `teacher_cancel_alert_threshold` | `3` | Cancellations before admin alert | عدد الإلغاءات قبل تنبيه الأدمن |
| `teacher_cancel_strike_reset` | `ON` | Admin can clear teacher strike count | الأدمن يقدر يمسح سجل الإنذارات |
| `admin_credit_override` | `ON` | Admin can refund or deduct credits manually | الأدمن يتدخل يدوياً في الكريدت |
| `recording_retention_days` | `30` | Days before recording is archived | أيام الاحتفاظ بالتسجيل قبل الأرشفة |
| `recording_max_views` | `5` | Max times student can stream a recording | أقصى عدد مشاهدات للتسجيل |
| `recording_allow_download` | `OFF` | Allow students to download recordings | السماح بتحميل التسجيلات |

---

**STATUS: 🟡 UNDER REVIEW / قيد المراجعة**
*راجع كل خاصية وأبلغنا بأي تعديل — أي تغيير هيتم تحديث الـ workflows على أساسه.*
*Review each feature and flag any change — workflows will be updated accordingly.*
