# Academic Year Implementation Impact Map

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

This document is the pre-implementation change map for adding safe multi-school-year support. It identifies the affected user-facing page files, backend files, and database tables before any schema or application behavior is changed.

## Objective

The implementation must allow the school to:

1. Prepare a new academic year without editing the previous year's structure.
2. Reuse section names and subject codes in different academic years.
3. Promote or re-enroll students without overwriting their historical enrollment.
4. Keep attendance, online classes, reports, and exports attached to the academic context in which they occurred.
5. Close an academic year and protect its records from normal editing or deletion.
6. Browse both the active academic year and historical years.

## Database Change Summary

| Table | Change type | Planned effect |
|---|---|---|
| `academic_years` | New | Stores year label, dates, status (`draft`, `active`, `closed`, `archived`), active semester, activation/closure metadata, and audit ownership. |
| `student_enrollments` | New | Stores one student's section, strand, year level, semester, and enrollment status for a specific academic year. This becomes the historical enrollment source. |
| `subject_offerings` | New | Connects a catalog subject to an academic year, semester, section, and instructor. Schedules and class records use the offering rather than treating a subject as a yearly record. |
| `academic_year_rollovers` | New | Records rollover execution, source and destination years, status, operator, timestamps, and summary counts. |
| `academic_year_rollover_items` | New | Records per-student promotion, retention, graduation, drop, skip, or error decisions made during rollover. |
| `sections` | Migrate | Add `academic_year_id`; replace global section-name uniqueness with year/semester-scoped uniqueness; protect closed-year rows. |
| `subjects` | Migrate | Retain as the reusable subject catalog. Remove yearly section/instructor ownership after offerings are populated. Permit stable subject-code reuse through offerings rather than duplicate catalog records. |
| `schedules` | Migrate | Add `academic_year_id`, `semester`, and `subject_offering_id`; keep the existing section/subject fields temporarily during migration. |
| `students` | Migrate/deprecate fields | Keep permanent identity, RFID, face, contact, and account linkage. Move mutable `section_id`, `strand_id`, `year_level`, `semester`, `school_year`, and enrollment status into `student_enrollments`. |
| `attendance_sessions` | Migrate | Add `academic_year_id` and `subject_offering_id`, preserving the class context even if current assignments later change. |
| `attendances` | Migrate | Add `academic_year_id`, `student_enrollment_id`, and `subject_offering_id`; preserve historical label snapshots used by exports. |
| `attendance_logs` | Migrate | Add academic-year/enrollment context where needed; retain immutable tap and manual-edit evidence. |
| `rfid_panel_sessions` | Migrate | Add active academic-year and offering context so the panel cannot start an old-year schedule accidentally. |
| `online_classes` | Migrate | Add `academic_year_id` and `subject_offering_id`; keep each class attached to its original roster context. |
| `online_class_attendances` | Migrate | Add `student_enrollment_id` so historical online attendance does not depend on the student's current section. |
| `online_class_notifications` | Review/migrate | Preserve the academic context of notifications generated for an online class. |
| `student_excuse_letters` | Review/migrate | Add optional academic-year and enrollment context so historical letters remain associated with the correct year. |
| `emergency_alerts` | Review/migrate | Preserve the academic year/offering context when an alert originates from a live class. |
| `activity_logs` | Existing/write | Record year creation, activation, closure, reopening, rollover, protected corrections, and rejected closed-year mutations. |
| `system_settings` | Existing/read/write | Optionally retain the active academic-year setting for compatibility; `academic_years.status` remains authoritative. |

## User-Facing Page Impact

### Academic-year administration

| Page file | Change | Database affected |
|---|---|---|
| `resources/js/pages/Auth/Admin/AcademicYears.vue` | **New page.** Create years, set dates/semester, activate, close, archive, reopen with a reason, and open rollover. | Read/write `academic_years`; write `activity_logs`; optionally read/write `system_settings`. |
| `resources/js/pages/Auth/Admin/AcademicYearRollover.vue` | **New page.** Select the source/destination year, copy structures, review student promotion decisions, validate conflicts, preview, and execute. | Read `academic_years`, `students`, `student_enrollments`, `sections`, `subjects`, `subject_offerings`, `schedules`; write `academic_year_rollovers`, `academic_year_rollover_items`, new-year `student_enrollments`, `sections`, `subject_offerings`, `schedules`, and `activity_logs`. |
| `resources/js/layouts/AuthNavbar.vue` | Add Academic Years navigation for authorized admins and expose the active year label. | Read `academic_years` through shared application data; no direct write. |

### Academic setup and student management

| Existing page file | Required change | Database affected |
|---|---|---|
| `resources/js/pages/Auth/Admin/Sections.vue` | Add academic-year selector; default to active year; permit the same section name in another year; make closed-year records read-only; prevent destructive deletion of referenced history. | Read/write `sections`; read `academic_years`, `strands`; write `activity_logs`. |
| `resources/js/pages/Auth/Admin/Subjects.vue` | Treat subjects as reusable catalog entries and manage their yearly section/instructor assignment through offerings; add year/semester filters and closed-year locking. | Read/write `subjects`, `subject_offerings`; read `academic_years`, `sections`, `instructors`, `users`; write `activity_logs`. |
| `resources/js/pages/Auth/Admin/Schedules.vue` | Filter by active/historical year; create schedules from subject offerings; show year and semester; prevent edits to closed-year schedules. | Read/write `schedules`; read `academic_years`, `subject_offerings`, `subjects`, `sections`, `instructors`, `laboratories`; write `activity_logs`. |
| `resources/js/pages/Auth/Admin/Students.vue` | Separate permanent student identity from yearly enrollment; add enrollment history, promotion/re-enrollment actions, active-year roster filters, and closed-year read-only behavior. | Read/write `students`, `student_enrollments`; read `academic_years`, `sections`, `strands`, `users`, `parent_student_links`; write `activity_logs`. |
| `resources/js/pages/Auth/Admin/Dashboard.vue` | Scope operational counts to the active year by default and clearly label historical/all-time metrics. | Read `academic_years`, `student_enrollments`, `sections`, `subject_offerings`, `schedules`, `attendances`, `online_classes`, and existing dashboard tables. |

### Registrar pages

| Existing page file | Required change | Database affected |
|---|---|---|
| `resources/js/pages/Registrar/Dashboard.vue` | Show active-year enrollment counts separately from permanent student identities and historical totals. | Read `academic_years`, `students`, `student_enrollments`, `registrar_enrollment_logs`. |
| `resources/js/pages/Registrar/BiometricEnrollment.vue` | Filter students using their active-year enrollment while keeping RFID and face identity attached to the permanent student record. Allow explicit historical-year viewing. | Read `academic_years`, `students`, `student_enrollments`, `sections`, `strands`; write `students`, `registrar_enrollment_logs`, `activity_logs`. |
| `resources/js/pages/Registrar/InstructorFaceEnrollment.vue` | Display active-year teaching assignments without duplicating instructor biometric identity. | Read `academic_years`, `subject_offerings`, `schedules`, `instructors`, `users`; write `users`/instructor identity fields and `registrar_enrollment_logs` as currently applicable. |

### Attendance pages and panel

| Existing page file | Required change | Database affected |
|---|---|---|
| `resources/js/pages/AttendancePanelLogin.vue` | Resolve only enabled devices as today; display the active academic year and reject operation if no year is active. | Read `academic_years`, `laboratories`, `panel_devices`, `system_settings`, `rfid_panel_sessions`. |
| `resources/js/pages/AttendanceControlPanel.vue` | Start sessions only from active-year schedules and active enrollments; persist year, offering, and enrollment context with every attendance event. | Read `academic_years`, `student_enrollments`, `subject_offerings`, `schedules`, `students`, `instructors`, `laboratories`; write `attendance_sessions`, `attendances`, `attendance_logs`, `rfid_panel_sessions`, `emergency_alerts`, `activity_logs`. |
| `resources/js/pages/AttendanceScanner.vue` | Show active-year schedule context and allow authorized historical inspection without writing to closed years. | Read `academic_years`, `subject_offerings`, `schedules`, `attendance_sessions`, `attendances`, `attendance_logs`. |
| `resources/js/pages/AttendanceLogs.vue` | Add academic-year filtering to the legacy log view and render stored historical labels rather than current student placement. | Read `academic_years`, `student_enrollments`, `subject_offerings`, `attendance_sessions`, `attendances`, `attendance_logs`. |
| `resources/js/pages/Attendance/SubjectSelection.vue` | Use explicit academic-year selection, default to active year, and keep closed years available. | Read `academic_years`, `subject_offerings`, `subjects`, `sections`, `schedules`. |
| `resources/js/pages/Attendance/Dashboard.vue` | Display offering/year metadata and isolate session cards to the selected offering. | Read `academic_years`, `subject_offerings`, `attendance_sessions`, `online_classes`. |
| `resources/js/pages/Attendance/Summary.vue` | Build the roster from `student_enrollments` for that offering/year instead of students' current section. | Read `student_enrollments`, `subject_offerings`, `attendance_sessions`, `attendances`, `online_classes`, `online_class_attendances`. |
| `resources/js/pages/Attendance/StudentHistory.vue` | Show the selected historical enrollment and preserve cross-year navigation without mixing rosters. | Read `academic_years`, `student_enrollments`, `subject_offerings`, `attendances`, `attendance_logs`, `online_class_attendances`. |
| `resources/js/pages/Attendance/SessionDetails.vue` | Render the session's stored enrollment roster and block ordinary corrections when the year is closed. | Read `academic_years`, `student_enrollments`, `subject_offerings`, `attendance_sessions`, `attendances`, `attendance_logs`; conditionally write audited attendance corrections. |

### Online classes

| Existing page file | Required change | Database affected |
|---|---|---|
| `resources/js/pages/Auth/Admin/OnlineClasses.vue` | Create classes only for active-year offerings; filter historical classes; lock closed years; use the offering enrollment roster. | Read/write `online_classes`; read `academic_years`, `subject_offerings`, `schedules`, `student_enrollments`; write `online_class_notifications`, `online_class_audit_logs`, `activity_logs`. |
| `resources/js/pages/Auth/Admin/OnlineClassLogs.vue` | Add academic-year filtering and display offering/year snapshots. | Read `academic_years`, `subject_offerings`, `online_classes`, `online_class_audit_logs`. |
| `resources/js/pages/StudentParent/OnlineClasses.vue` | Show classes belonging to the student's selected/current enrollment; keep prior-year history separate and non-joinable. | Read `academic_years`, `student_enrollments`, `online_classes`, `online_class_attendances`, `online_class_notifications`; write active-year join attendance and audit logs. |

### Student and parent portal

| Existing page file | Required change | Database affected |
|---|---|---|
| `resources/js/pages/StudentParent/Dashboard.vue` | Default to the active enrollment and provide clearly separated historical-year summaries. | Read `academic_years`, `student_enrollments`, `attendances`, `online_classes`, `online_class_attendances`, and existing portal summary tables. |
| `resources/js/pages/StudentParent/Attendance.vue` | Add academic-year selection and derive labels from historical enrollment/offering records. | Read `academic_years`, `student_enrollments`, `subject_offerings`, `attendances`, `attendance_logs`. |
| `resources/js/pages/StudentParent/Profile.vue` | Display permanent identity separately from current and past enrollments. | Read/write permitted `students` identity fields; read `student_enrollments`, `academic_years`, `sections`, `strands`. |
| `resources/js/pages/StudentParent/ExcuseLetters.vue` | If present in the branch/page routing, associate new letters with the active enrollment and show their school-year context. | Read `academic_years`, `student_enrollments`; read/write `student_excuse_letters`; write Messenger/email-related records as currently implemented. |
| `resources/js/pages/StudentParent/Notifications.vue` | If present in the branch/page routing, label online-class notifications by academic year and keep historical notifications viewable. | Read `academic_years`, `online_classes`, `online_class_notifications`, `student_enrollments`. |

### Reports

| Existing page file | Required change | Database affected |
|---|---|---|
| `resources/js/pages/Reports/Index.vue` | Add academic-year and semester filters; default operational reports to the active year; allow explicit all-year or historical exports. | Read `academic_years` plus all role-scoped reporting tables, especially `student_enrollments`, `subject_offerings`, `schedules`, `attendances`, `online_classes`, and `online_class_attendances`. No direct write. |
| `resources/js/pages/Clinic/Reports.vue` | Preserve current date filtering and optionally add academic-year context for student-linked cases and class-originated alerts. | Read `academic_years`, `student_enrollments`, `clinic_cases`, `patient_histories`, `emergency_alerts`. No direct write. |

## Backend File Impact

| Backend file | Responsibility after change | Primary database tables |
|---|---|---|
| `routes/web.php` | Register academic-year lifecycle, rollover, enrollment-history, and protected reopen routes. | No direct access. |
| `app/Models/AcademicYear.php` | **New model:** status, active-year resolution, close/reopen rules, relations. | `academic_years`. |
| `app/Models/StudentEnrollment.php` | **New model:** historical student placement and enrollment status. | `student_enrollments`, related master tables. |
| `app/Models/SubjectOffering.php` | **New model:** yearly subject/section/instructor assignment. | `subject_offerings`, `subjects`, `sections`, `instructors`, `academic_years`. |
| `app/Models/AcademicYearRollover.php` | **New model:** rollover header and execution state. | `academic_year_rollovers`. |
| `app/Models/AcademicYearRolloverItem.php` | **New model:** per-student rollover decisions/results. | `academic_year_rollover_items`. |
| `app/Models/Section.php` | Add academic-year relationship and closed-year mutation protection. | `sections`, `academic_years`. |
| `app/Models/Students.php` | Add enrollments/currentEnrollment relations; retain permanent identity fields. | `students`, `student_enrollments`. |
| `app/Models/Subject.php` | Become reusable catalog model; add offerings relation. | `subjects`, `subject_offerings`. |
| `app/Models/Schedule.php` | Link to academic year and subject offering. | `schedules`, `academic_years`, `subject_offerings`. |
| `app/Models/Attendance.php` | Link to the historical enrollment/offering/year. | `attendances`, `student_enrollments`, `subject_offerings`, `academic_years`. |
| `app/Models/OnlineClass.php` and attendance models | Link classes and joins to offering/enrollment/year. | `online_classes`, `online_class_attendances`, `student_enrollments`, `subject_offerings`, `academic_years`. |
| `app/Http/Controllers/AcademicYearController.php` | **New controller:** year CRUD, activation, closure, archive, and audited reopening. | `academic_years`, `activity_logs`, optionally `system_settings`. |
| `app/Http/Controllers/AcademicYearRolloverController.php` | **New controller:** preview, validation, execution, idempotency, and results. | All rollover and academic setup/enrollment tables. |
| `app/Http/Controllers/SectionController.php` | Year-scoped validation, filtering, closed-year protection. | `academic_years`, `sections`, `strands`, `activity_logs`. |
| `app/Http/Controllers/SubjectController.php` | Catalog/offering management and scoped uniqueness. | `academic_years`, `subjects`, `subject_offerings`, `sections`, `instructors`, `activity_logs`. |
| `app/Http/Controllers/ScheduleController.php` | Offering-based schedules, active-year defaults, closed-year protection. | `academic_years`, `subject_offerings`, `schedules`, `sections`, `laboratories`, `activity_logs`. |
| `app/Http/Controllers/StudentsController.php` | Permanent identity plus enrollment CRUD; portal current/historical enrollment selection. | `students`, `student_enrollments`, `academic_years`, `sections`, `strands`, portal-related tables. |
| `app/Http/Controllers/RegistrarController.php` | Resolve eligible students from active enrollments without duplicating identity records. | `students`, `student_enrollments`, `academic_years`, `registrar_enrollment_logs`. |
| `app/Http/Controllers/AttendanceController.php` | Resolve active-year sessions/enrollments and write immutable year-aware attendance context. | `academic_years`, `student_enrollments`, `subject_offerings`, `schedules`, all physical-attendance tables. |
| `app/Http/Controllers/AttendanceManagementController.php` | Build historical rosters from enrollments and enforce closed-year correction policy. | Academic-year, enrollment, offering, attendance, and online-class tables. |
| `app/Http/Controllers/OnlineClassController.php` | Use offering enrollment rosters and active/closed-year rules. | Academic-year, enrollment, offering, online-class, notification, and audit tables. |
| `app/Http/Controllers/ReportController.php` | Apply academic-year/semester filters consistently and export the same scoped dataset. | All role-reporting tables, especially the new year/enrollment/offering tables. |
| `app/Http/Controllers/DashboardController.php` | Separate active-year metrics from all-time metrics. | Academic setup, enrollment, attendance, online-class, and existing dashboard tables. |
| `app/Services/AcademicYearService.php` | **New service:** authoritative active-year lookup and lifecycle validation. | `academic_years`, optionally `system_settings`. |
| `app/Services/AcademicYearRolloverService.php` | **New service:** transactional copy/promotion logic with preview and idempotency. | Rollover, academic setup, and enrollment tables. |
| `app/Services/OnlineAttendanceFinalizer.php` | Finalize only students enrolled in the class offering for that year. | `student_enrollments`, `online_classes`, `online_class_attendances`. |
| `app/Http/Middleware/EnsureAcademicYearIsActive.php` | **New middleware:** protect daily-operation write routes when no year is active or the requested year is closed. | Read `academic_years`; rejected attempts may write `activity_logs`. |

## Migration and Compatibility Plan

1. Back up the database and record baseline row counts.
2. Create the new academic-year, enrollment, offering, and rollover tables.
3. Create one `academic_years` row for each distinct existing `sections.school_year` and `students.school_year` value.
4. Populate `sections.academic_year_id` from the section's existing school-year label.
5. Create one `student_enrollments` row from every current student placement.
6. Convert existing subjects into catalog subjects and create a matching `subject_offering` for each current section assignment.
7. Link schedules, attendance sessions, attendance results, logs, online classes, and related records to the inferred academic year/offering/enrollment.
8. Store snapshot labels where a historical record currently depends on a mutable relationship.
9. Run consistency checks for orphaned records, duplicate codes, mismatched student/section years, and schedules with no resolvable offering.
10. Keep legacy school-year and relationship columns during a compatibility period; switch application reads first, then remove or formally deprecate legacy fields in a later migration.

## Records That Must Never Be Copied During Rollover

- Physical attendance results, sessions, tap logs, evidence, and manual edits
- Online classes, joins, notifications, and audit events
- Excuse letters and generated documents
- Messenger conversations and attachments
- Clinic cases, patient histories, and emergency alerts
- Borrowing transactions
- Activity and registrar enrollment logs

Only reusable configuration and explicitly approved student enrollment decisions should be created for the destination year.

## Required Verification

- The same section name can exist in two academic years.
- The same catalog subject can be offered in two years without mixing schedules or attendance.
- Promoting a student does not change their previous-year section, roster, attendance, or exports.
- Active-year attendance cannot select a closed-year schedule or inactive enrollment.
- Closed-year academic records cannot be edited or deleted through normal routes.
- An audited privileged correction does not rewrite original tap evidence.
- Student, parent, instructor, registrar, admin, and clinic reports remain year-scoped.
- PDF, Excel, and CSV exports show the selected academic year and use its historical roster.
- Rollover preview makes no writes; execution is transactional and safe to retry.
- Existing installations migrate without losing row counts or relationships.

## Implementation Boundary

This file is an impact map only. No academic-year schema, lifecycle behavior, rollover action, or page behavior is implemented merely by adding this document.
