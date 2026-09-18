# Architecture and Feature Flows

## Technology and runtime

- Backend: PHP 8.2+ requirement, Laravel 12.52 in the audited lockfile, Laravel Fortify, Inertia Laravel, Eloquent, notifications/mail, scheduler, filesystem, cache, and sessions.
- Frontend: Vue 3.5, Inertia Vue 2.3, Vite 7, Tailwind CSS 4, Wayfinder, SweetAlert2, Lucide, and TypeScript tooling.
- Database: MySQL in `.env.example`; migrations also completed against a temporary SQLite audit database during documentation review.
- Documents/data: DOMPDF dependency for PDF support, PhpSpreadsheet for XLSX attendance export, native streamed CSV exports.
- External services: AWS Rekognition, optional CompreFace code path, Semaphore SMS, SMTP/Laravel Mail.
- State: database sessions/cache are the documented defaults; uploaded/generated files are split between `storage/app/private` and `storage/app/public` according to the calling code.

## Project structure

| Path | Responsibility |
| --- | --- |
| `routes/web.php` | Public, staff, Console, Admin/Instructor, Registrar, Clinic, Student/Parent, reports, messages, and file endpoints. |
| `routes/settings.php` | Profile, password, appearance, and two-factor settings. |
| `routes/console.php` | Scheduler registration plus diagnostic Artisan commands. |
| `app/Http/Controllers` | Request validation, scope checks, orchestration, Inertia/JSON/file/export responses. |
| `app/Http/Middleware` | Roles, Instructor verification, Parent Portal, first-password, activity audit, appearance, shared props. |
| `app/Models` | Eloquent records, relationships, encryption accessors, casts, and academic compatibility hooks. |
| `app/Services` | Academic year/rollover, enrollment, face providers, online attendance/notifications/audit, SMS, Messenger mail, PDF generation. |
| `resources/js/pages` | Route-level Vue pages and retained legacy/prototype pages. |
| `resources/js/layouts` | Shared authenticated navigation, role/feature visibility, unread polling, saved-profile behavior. |
| `database/migrations` | Final schema and compatibility evolution. |
| `database/seeders` | Minimal, reference, demo, and account datasets. |
| `tests/Feature` | Auth, roles, attendance, clinic, settings, academic year, portal, reports, devices, and audit regression tests. |

## Request pipeline

```text
Browser action
  -> Laravel web middleware (cookies/session/session-identity binding/shared Inertia props/audit/first-password)
  -> authentication and role/feature-specific middleware
  -> controller validation and record-scope authorization
  -> service/business logic and transaction where needed
  -> Eloquent/query-builder persistence and optional filesystem/external service
  -> Inertia page, redirect+flash, JSON, authorized file, CSV, or XLSX response
  -> Vue updates the page and shows success/error feedback
```

`RecordSystemActivity` observes all POST/PUT/PATCH/DELETE requests and selected GET exports/log views. It deliberately catches its own failures so audit storage cannot break the business request.

## Authentication and authorization

1. Each successful login regenerates the Laravel session ID and records a unique login instance plus its bound user in that server-side session. Different browser cookie jars remain independent; one browser profile keeps one active account.
2. `EnsureAuthenticatedSessionIdentity` validates the bound user on every authenticated web request and invalidates only the affected session if an identity mismatch is detected.
3. `StudentParentLoginController` accepts only `student`/`parent`; a disabled Parent Portal logs a Parent back out with a neutral failure.
4. `StaffLoginController` accepts only `admin`/`instructor`/`registrar`/`clinic`; Instructor is redirected to verification.
5. Console uses the public panel verification endpoint to check room/device PIN, then authenticates/maintains a role-restricted Console session.
6. `EnsurePasswordIsChanged` gates non-Console accounts with `must_change_password`.
7. `CheckRole` enforces route roles; queries add object-level scope checks.
8. `EnsureInstructorVerified` gates shared Admin/Instructor pages after each new Instructor login.
9. `EnsureParentPortalEnabled` blocks Parent access to portal, messages, reports, and evidence while leaving Student access intact.
10. Root Admin is a boolean privilege on an Admin account. `AdminUserController` enforces Root-only Admin management, prevents self-deletion, and protects the last Root Admin.
11. Fortify supplies password reset, email verification, password confirmation, and two-factor flows. Login is limited to five attempts/minute per normalized email and IP.

## Shared frontend behavior

`HandleInertiaRequests` shares current user, feature flags, flash data, title, and sidebar state. `AuthNavbar.vue` filters links by role and selected feature flags. This is presentation logic only; route middleware/controller checks remain the security boundary. `AuthLayout.vue` polls unread Messenger state every 10 seconds and processes the user's local saved-login preference after authentication.

## Academic setup flow

```text
Admin form -> Academic/Strand/Section/Subject/Schedule controller
  -> validate current year and semester plus FK/uniqueness rules
  -> normalize offering-derived year/section/subject/instructor data
  -> academic_years / strands / sections / subjects / subject_offerings / schedules
  -> redirect with success or field errors -> page reloads filtered records
```

The permanent `subjects` table is a catalog. `subject_offerings` owns year/semester/section/Instructor assignment. `schedules` reference the offering but retain compatibility columns. Closed/archived years are immutable through normal controllers. No schedule-overlap algorithm exists.

## Student and Parent provisioning flow

```text
Admin Student form -> StudentsController
  -> validate identity and current year/semester/section/strand match
  -> transaction creates/updates students + student_enrollments
  -> creates/updates Student user and hashes the deterministic temporary password
  -> optional Parent create/link writes users + parent_student_links
  -> current placement compatibility fields are synchronized
  -> redirect displays temporary password and updated history
```

Permanent identity stays in `students`; historical placement stays in `student_enrollments`. Parent ownership is a many-to-many link. Face-image methods exist in `StudentsController`, but their route names referenced by `Students.vue` are currently absent; the Registrar endpoints are the supported biometric route.

## Physical attendance flow

```text
Console selects room/PIN -> AttendanceController opens rfid_panel_sessions
Instructor RFID/verification -> active-year Schedule resolved -> attendance_sessions opened
Student RFID -> roster/year/section validation -> face or Instructor override grant
Student tap transaction -> attendances official row + attendance_logs event/evidence
Panel JSON response -> immediate card/toast/log snapshot
Admin/Instructor workspace -> AttendanceManagementController derives summaries/exports
```

The official row is unique by student/schedule/date at the business-logic level. Each tap has sequence, type, validation, verification, and optional evidence. Check-in basis is Present/Late. Checkout preserves Late. Before the checkout window the system requires scheduled-Instructor RFID for movement toggles. Dismiss, final 15 minutes, and accepted fallback methods lead to checkout. Ending attendance calls `finalizeCuttingStudents` for unresolved temporary exits.

## Manual attendance flow

Instructor edit -> ownership and edit-window check -> validate Present/Late/Absent/Excused -> require reason for Excused -> upsert official attendance -> insert `Manual Edit` log -> general audit -> refreshed session. Admin can inspect but the controller intentionally reserves correction authority for the assigned Instructor.

## Online-class flow

```text
Admin/Instructor form -> OnlineClassController
  -> authorize schedule ownership/writable year
  -> create/update/cancel class and attachments
  -> online_class_audit_logs
  -> OnlineClassNotificationService creates per-student notifications + mail attempts

Student join -> linked/current Student -> class enrollment/time/year checks
  -> optional Rekognition comparison
  -> idempotent online_class_attendances Present/Late
  -> redirect to meeting link/status

Scheduler/lazy page pass -> OnlineClassAttendanceFinalizer
  -> insert missing Absent rows after end
```

Known implementation mismatch: `student_enrollments.status` defaults to `enrolled`, while the join/finalizer/report paths contain `status = active` filters. This can exclude valid normalized enrollments until code/data conventions are aligned.

## Messenger flow

```text
Recipient selection + text/file -> MessageController
  -> validate role, recipient, content/file and optional student context
  -> student_portal_messages encrypted content + stored attachment metadata
  -> cache-controlled MessengerEmailNotificationService
  -> redirect; 10-second unread polling surfaces recipient toast
```

Only sender/recipient may download/preview an attachment. The older public `messages` table/form is still used for the legacy student-to-Instructor inbox and reply bridge.

## Excuse-letter flow

Student/Parent form -> resolve owned/linked Student and active enrollment -> validate dates, teachers, attachment, signature rules -> `student_excuse_letters`. Student letters wait for Parent approval only when Parent Portal is enabled. Approval calls `ExcuseLetterPdfService`, writes the PDF, creates teacher Messenger messages, and attempts email attachments. The letter is evidence/communication; attendance status changes only through attendance controls.

## Borrowing and inventory flow

Borrow/return JSON/form -> feature switch -> validate borrower/item/quantity/status -> transaction writes `borrowings` and `borrowing_items`, updates `inventory_items.status`, and returns calculated header/item status. Legacy `items`/`inventories`/`transactions` remain for compatibility but current borrowing uses `inventory_items`.

## Emergency and Clinic flow

```text
Console emergency wizard -> EmergencyController::storeAlert
  -> validate type/scope/people/location and suppress rapid duplicates
  -> emergency_alerts + metadata
  -> optional SemaphoreSmsService using selected active hotline
  -> JSON result

Clinic dashboard action -> acknowledge/resolve/cancel OR dispatch
  -> dispatch validates active Clinic responder
  -> one clinic_cases row per identified Student, otherwise generic case
  -> response timestamps/seconds + assignment
  -> ClinicDispatchAssigned notification email
  -> dashboard assignment and general audit
```

Emergency sound is a browser-side notification driven by polling/refresh data and a configured, authorized sound endpoint. It requires user audio permission. SMS errors do not roll back the stored alert.

## Academic rollover algorithm

1. Validate same-year First-to-Second semester mode or different-year active/closed source plus draft destination. Rollover does not change `active_semester`.
2. Load source-semester enrollments and recommend dropped, graduated, retain, promote, or review.
3. Reuse or create explicitly selected destination Sections with the reviewed grade while preserving the source semester for full-year rollover.
4. For promote/retain, create the destination enrollment if missing and update current student compatibility placement.
5. For graduated, mark current Student status graduated; skipped decisions create no enrollment.
6. Upsert per-student rollover item and complete the transaction/audit.
7. Reuse the global Subject catalog and create only the Subject Offerings selected in preview for mapped destination Sections. Do not copy Instructor assignments, Schedules, attendance, online classes, messages, files, Clinic, borrowing, or logs.

The unused private `copyOfferingsAndSchedules` helper remains in the service, but `execute` explicitly sets those copy counts to zero and does not call it. Documentation follows executed behavior.

## External services and failure behavior

| Integration | Trigger | Failure behavior |
| --- | --- | --- |
| AWS Rekognition | Attendance/online face comparison and availability checks. | Returns unavailable/no-match path; settings disable impossible combinations; documented Instructor fallbacks may apply. |
| CompreFace | Legacy/alternative face service code path. | Service catches/logs failures; not the primary settings availability provider. |
| SMTP/Laravel Mail | OTP, password reset, messages, class notices, dispatch, letters. | Most user operation remains stored; email error is caught/recorded where implemented. OTP send itself returns an error on mail failure. |
| Semaphore | Emergency hotline SMS. | Alert remains stored and JSON/metadata reports failed/disabled result. |
| Public filesystem | Faces, evidence, class/message/letter files, sounds. | Missing file returns 404; writes must have runtime permissions and public link where URL access is used. |

## Current implementation gaps requiring explicit treatment

- No schedule conflict detection.
- Online Classes feature switch is not a universal route-level kill switch.
- Enrollment status vocabulary is inconsistent (`enrolled` versus `active`) in online-class/report queries.
- Student Management face buttons reference unregistered Admin route names.
- Public registration and legacy public message-create/store routes remain enabled; production policy should confirm this.
- Database queue is the example default but migrations do not create `jobs`, `job_batches`, or `failed_jobs`; either add queue migrations or set `QUEUE_CONNECTION=sync` when no queued work is required.
- The health route is application-process health only, not dependency readiness.
