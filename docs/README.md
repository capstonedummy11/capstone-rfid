# RFID Borrowing and Attendance System

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

RFID Borrowing and Attendance System is a Laravel 12, Inertia, and Vue 3 capstone application for senior high school computer laboratory operations. The repository contains a working web application for attendance, borrowing, inventory, registrar biometric enrollment, student/parent self-service, online classes, clinic records, emergency alerts, messaging, reports, and audit logs.

This README is written from the current source code. It intentionally excludes private AI notes, secrets, real production passwords, software installation instructions, deployment steps, and infrastructure setup. Documented default and seeded development passwords are non-production fixtures.

## Current Status

| Area                                   | Status                                | Evidence                                                                                                                                             |
| -------------------------------------- | ------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| Merge conflicts                        | Resolved                              | No unresolved conflict markers were found in tracked source/docs checked during this pass.                                                           |
| Application stack                      | Implemented                           | Laravel backend, Inertia routes, Vue pages, migrations, seeders, tests, Vite build.                                                                  |
| Attendance panel                       | Implemented with complex rules        | `AttendanceController`, `AttendanceControlPanel.vue`, attendance panel feature tests.                                                                |
| Face verification                      | Implemented with provider dependency  | AWS Rekognition service, fallback/override paths, attendance evidence storage.                                                                       |
| Borrowing and inventory                | Implemented                           | Borrow, item, inventory, laboratory controllers/pages/models.                                                                                        |
| Student/parent portal                  | Implemented                           | Dashboard, profile, attendance, online classes, excuse letters, messages, notifications.                                                             |
| Unified Messenger                      | Implemented for all non-console roles | Admin, instructor, clinic, registrar, student, and parent users can search recipients, chat, send attachments, and preview image attachments inline. |
| Clinic and emergency                   | Implemented                           | Clinic dashboard/cases/patient histories/reports; emergency types/hotlines/alerts; explicit Clinic responder assignment with email and recent student context; clinic dashboard MP3 alert sound for newly received emergencies.  |
| Shared reports                         | Implemented as CSV exports            | Role-specific report payloads and stream downloads.                                                                                                  |
| Schedule conflict detection            | Missing                               | Schedule CRUD validates data but does not reject overlapping schedules.                                                                              |
| Full term/department/course management | Partial                               | Strands, sections, subjects, schedules, and school year fields exist; no dedicated term closing, department, curriculum, or course lifecycle module. |

Academic-year lifecycle foundation is available at `/admin/academic-years`. Student Management preserves yearly enrollment history, sections are year-scoped, and subjects use separate yearly offerings. Schedules now select an offering and store its academic year, semester, subject, section, and instructor context. Closed-year schedules are read-only, and the live attendance panel resolves schedules only from the active academic year. Attendance records, online classes, and reports still require later migration phases for complete historical isolation.

## Primary Roles

Canonical reference: [Roles and Functionality](ROLES_AND_FUNCTIONALITY.md). The list below is a short overview; the linked document explains each role's purpose, complete functionality, boundaries, login entry, and daily workflow.

- `admin`: manages the system, academics, laboratories, users, devices, borrowing, inventory, online class logs, reports, and settings.
- `root admin`: an admin flagged as root; can manage admin accounts that normal admins cannot manage.
- `instructor`: views scoped students/schedules, verifies attendance access, manages assigned online classes, replies to messages, and reviews attendance.
- `registrar`: enrolls and updates student/faculty RFID cards and face images.
- `clinic`: manages clinic cases, patient histories, emergency alerts, hotlines, emergency types, and clinic reports.
- `console`: runs the physical attendance control panel for a selected laboratory or room.
- `student`: uses the student/parent portal for attendance, online classes, excuse letters, messages, notifications, and profile updates.
- `parent`: uses linked student portal views and approves student-created excuse letters.

The standalone admin RFID navigation shortcut is intentionally hidden because RFID tags can be assigned from Student and Instructor Management. Registrar navigation separates **Student Biometric Enrollment**, which only lists students, from **Instructor Faces**, which only lists instructors. There is no separate registrar-only RFID navigation item.

## First Access Flow

The root route `/` is the student/parent login entry when the visitor is not authenticated. Authenticated users are redirected by role:

| Role                  | Destination                 |
| --------------------- | --------------------------- |
| `admin`, `instructor` | `/admin/dashboard`          |
| `clinic`              | `/clinic/dashboard`         |
| `console`             | `/attendance-control-panel` |
| `registrar`           | `/registrar/dashboard`      |
| `student`, `parent`   | `/student-parent/dashboard` |

Staff users authenticate through the configured staff login route. Student and parent users authenticate from the public portal login.

See [DEFAULT_ACCOUNT_PASSWORDS.md](./DEFAULT_ACCOUNT_PASSWORDS.md) for the initial-password rule used by every account-creation path and for development-only seeded credentials.

## Database Starting Point

The app can run from a minimal migrated database, but the repository seeders create a demo-rich environment. `DatabaseSeeder` calls:

- `UserSeeder`
- `EmergencySeeder`
- `ComlabUserSeeder`
- `DemoSystemSeeder`
- `StudentParentAccountSeeder`
- `ClinicDashboardSeeder`
- `MessageSeeder`

Because of those seeders, a seeded database contains sample users, console accounts, academic records, students, parent links, clinic data, emergency data, and messages. An unseeded database should be treated as empty except for schema-level defaults and whatever records an operator creates manually.

Seeder shortcuts:

- `php artisan db:seed` runs the normal full seed path.
- `php artisan db:seed --class=MinimalSeeder` creates only the root admin and baseline system settings for a clean installation.
- `php artisan db:seed --class=SystemSeeder` seeds system reference records and demo operational data.
- `php artisan db:seed --class=DataAccountSeeder` seeds login accounts, console accounts, and student/parent portal account links.

### Minimal Seeder

Use the minimal seeder when the database should start without demo or operational records:

```bash
php artisan db:seed --class=MinimalSeeder
```

It creates or preserves one root administrator and automatically creates the current academic year using the June-to-March school-year cycle:

| Field | Default value |
| --- | --- |
| Name | `Root Admin` |
| Email | `root.admin@sample.com` |
| Temporary password | `change-me-now` |
| Role | `admin` |
| Root administrator | Enabled |
| Must change password | Enabled on first login |

The current academic year is calculated automatically. For example, a run in September 2026 creates active year `2026-2027`, with dates `2026-06-01` through `2027-03-31`. A run from January through May uses the previous year's academic-year label. Existing active years from an older cycle are closed when a new current year is created.

It also creates baseline settings, preserving existing values when the seeder is run again:

- Borrowing and inventory disabled.
- Parent portal and parent excuse letters disabled.
- Face recognition enabled.
- Demo attendance panel disabled.
- Online-class face recognition enabled by default.
- Attendance late threshold set to 15 minutes.
- Attendance correction window set to 15 days.
- Default security questions.
- Attendance Console panel label with PIN `1234`.

The values can be customized through `MINIMAL_ROOT_ADMIN_NAME`, `MINIMAL_ROOT_ADMIN_EMAIL`, `MINIMAL_ROOT_ADMIN_PASSWORD`, `MINIMAL_ACADEMIC_YEAR`, `MINIMAL_ACADEMIC_YEAR_START`, `MINIMAL_ACADEMIC_YEAR_END`, and `PANEL_PIN` environment variables. The minimal seeder does not create students, instructors, rooms, schedules, messages, inventory, emergency records, or attendance data.

Default seeded login accounts:

| Role | Email | Password | Notes |
| --- | --- | --- | --- |
| Root admin | `root.admin@sample.com` | `sample` | Root administrator |
| Admin | `test@example.com` | `password` | Test admin |
| Admin | `jeromebernante@gmail.com` | `1234` | Demo/dev admin |
| Admin | `vallecera@gmail.com` | `sample` | Demo/dev admin |
| Admin | `admin@gmail.com` | `password` | Standard admin |
| Instructor | `instructor@sample.com` | `sample` | RFID `RFID-INSTRUCTOR-SAMPLE` |
| Clinic | `clinic@sample.com` | `sample` | Clinic staff |
| Clinic responder | `clinic.responder@sample.com` | `sample` | Assignable Clinic responder |
| Registrar | `registrar@sample.com` | `sample` | Registrar staff |
| Console | `comlab1@example.com` | `1234` | COMLAB 1 panel account |
| Console | `comlab2@example.com` | `1234` | COMLAB 2 panel account |
| Console | `comlab3@example.com` | `1234` | COMLAB 3 panel account |
| Console | `comlab4@example.com` | `1234` | COMLAB 4 panel account |
| Console | `comlab5@example.com` | `1234` | COMLAB 5 panel account |
| Student | `andrea.santos@student.sample.com` | `sample` | Andrea Santos |
| Student | `miguel.reyes@student.sample.com` | `sample` | Miguel Reyes |
| Parent | `parent.andrea.santos@sample.com` | `sample` | Linked to Andrea Santos |

## Initial Configuration Flow

After the app and database are already running, configure the operational data in this order:

1. Confirm system settings for panel access, attendance late threshold, face recognition, inventory availability, and online class defaults.
2. Create or review staff users and role assignments.
3. Define strands, sections, school year values, and semester values.
4. Define subjects and associate them with the intended academic structure.
5. Create instructor profiles and connect them to user accounts.
6. Create laboratories/rooms and panel device access settings.
7. Create schedules by section, subject, instructor, room/laboratory, weekday, start time, and end time.
8. Create student records and link them to sections, strands, and parent accounts as needed.
9. Use registrar enrollment to assign RFID tags and face images for students and instructors.
10. Open the attendance panel using a console account, select a room, and let the assigned instructor start a live session.
11. Review reports, activity logs, attendance logs, and clinic/online-class outputs during daily operation.

There is no dedicated setup wizard in the current codebase.

For a full click-by-click operating tutorial, see [USER_OPERATIONS_TUTORIAL.md](USER_OPERATIONS_TUTORIAL.md).

## Core Modules

### Authentication And Authorization

Canonical password behavior: [Authentication and Password Rules](AUTHENTICATION_PASSWORD_RULES.md). All roles except Console can request an emailed password-reset link. Newly created non-Console accounts must replace their temporary password before accessing any role dashboard.

Authentication screens use `resources/js/assets/images/logo-only.jpg` as the compact Pasay City South High School seal. Staff, student/parent, instructor verification, password recovery, first-login password change, and attendance-panel login share this identity mark while retaining their role-specific content.

The application uses Laravel authentication with role middleware and Inertia pages. Admin, instructor, clinic, registrar, console, student, and parent users are routed to separate dashboards. Instructor routes can require an extra verification step through face, OTP, or security questions.

### Admin Dashboard And Master Data

Admin routes under `/admin` cover:

- Dashboard.
- Student records and parent account links.
- Instructors.
- Users for admin, clinic, and registrar roles.
- Strands, sections, subjects, schedules, and laboratories.

Laboratory and panel ownership is defined in [Laboratories and Devices](LABORATORIES_AND_DEVICES.md): laboratories own physical room information, while assigned devices own their label, PIN, enabled state, live logout, and device lifecycle.
- Inventory, items, borrowing, returned items, and availability.
- Active devices and attendance panel access controls.
- System settings.
- Activity logs and CSV export.
- Online class management and online class audit logs.

Root admin protection is implemented for admin-account management.

### Academic Structure

The implemented academic structure is based on:

- `strands`
- `sections`
- `subjects`
- `schedules`
- `students`
- `instructors`
- `laboratories`

Sections store school year and semester information. Subjects also store semester information. The current code does not include separate department, course, curriculum, enrollment period, grading period, or term-closing modules.

### Schedule Management

Schedules connect sections, subjects, instructors, rooms/laboratories, weekdays, and time windows. Admins can create, update, delete, and filter schedules. Instructors can view their assigned schedules.

Admin forms use searchable autosuggestion fields for large relationship lists. Schedule instructor, subject, and section fields can be searched by their visible names or identifiers; subject assignment can search sections and instructors; online-class creation can search assigned schedules. Results are limited initially for responsiveness, while typing searches the complete list.

Current limitation: schedule CRUD validates required fields and foreign keys, but it does not currently block overlapping schedules for the same room, instructor, section, or time range.

### Attendance Control Panel

Canonical reference: [Attendance Control Panel Tapping Rules](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md). The following is only a summary; use the linked file for exact tap behavior.

The attendance panel is used by a `console` account in a selected room. A live attendance session is tied to the current schedule, subject, section, instructor, room, and date.

Student tap behavior:

- Face verification or instructor-approved fallback creates a short-lived one-use attendance grant.
- The first valid student tap records official check-in.
- Late status is based on `attendance.late_threshold_minutes`, defaulting to 15.
- Instructors can correct attendance for their own assigned sessions while the record remains inside the rolling `attendance.absent_default_days` window. Available manual statuses are Present, Late, Absent, and Excused; Excused requires a note. Each correction creates an attendance event log and a detailed system activity log containing the instructor and old/new status.
- A checked-in student is considered inside the room.
- Before the final checkout window, temporary exit/return taps require instructor RFID approval.
- The final checkout window begins 15 minutes before scheduled end.
- The first valid tap during that window records official checkout.
- Instructor Dismiss Class mode keeps class-wide checkout active: every checked-in student's tap becomes official checkout until the instructor taps again and chooses Continue Class.
- Taps after official checkout are ignored and logged without changing the completed record.
- When a live class ends, unfinished attendance is finalized according to the implemented session-ending rules.
- Demo attendance buttons are disabled by default and can be enabled from admin settings with configurable professor/student RFID values.

Attendance records keep the main state. Attendance logs keep per-tap evidence, sequence, room/location, validation result, verification method, and remarks.

### Attendance Analytics Workspace

Admin and Instructor users open Attendance from `/admin/attendance/logs`.

- Instructors see only subjects assigned through their schedules. If exactly one subject is assigned, its dashboard opens automatically; otherwise the system presents subject cards.
- Administrators see all scheduled subjects and can filter by school year, semester/term, department, section, and instructor.
- Each subject dashboard shows total students, sessions, and every status currently present in physical or online attendance data.
- The first dashboard card opens Student Attendance Summary with search, status filtering, sortable columns, attendance rates, and per-student session history.
- Present, Absent, Late, Excused, Unexcused, and Online Class columns always remain visible and show `0` when no matching record exists.
- Online attendance remains Pending until the scheduled class ends. A successful join is Present when it occurs within the configured attendance late threshold and Late after that threshold; either result also counts as Online Class participation. An eligible student who did not join is shown as Absent after class end.
- Admin and assigned Instructor users can correct online Present, Late, Absent, or Excused results within the same configured Attendance Days edit window used by physical attendance. Corrections are activity-logged.
- The student portal attendance history includes completed and pending online sessions, labels them separately from in-person classes, and applies the same Present/Absent rule.
- Online-class joining is closed after the configured end time.
- Online attendance also remains closed before the scheduled start time, so the valid join window is exactly the configured start through end time.
- Repeated Join requests are idempotent: the first successful join timestamp and Present/Late result are preserved.
- Ended online classes permanently receive `online_class_attendances` Absent rows for every active section student without a record. Finalization runs every minute through Laravel scheduling and is also triggered by relevant attendance/online-class pages for local operation.
- Online sessions appear as visually distinct violet/globe cards beside in-person attendance-session document cards.
- Each Instructor/Admin Online Classes row provides **View Attendance**, opening the live roster sheet so the assigned instructor can see Present, Late, Pending, Absent, and Excused students during and after the class.
- Subject colors are assigned from a stable subject identity. They vary across subjects but remain unchanged after logout, login, refresh, or device change.
- Document-style session cards open searchable and filterable individual attendance sheets.
- Admin and Instructor users can make audited corrections within the configured edit window and permitted role scope.
- Student summaries and attendance sheets export as professionally headed PDF or Excel `.xlsx` reports.
- Export generation uses `barryvdh/laravel-dompdf` and `phpoffice/phpspreadsheet`.

### Face Verification And Evidence

Student attendance can use AWS Rekognition against registrar-enrolled reference images. Successful camera captures are stored separately as attendance evidence and do not replace registrar reference images.

Fallback behavior exists for:

- Missing student face image.
- Face recognition disabled.
- Camera session override approved by the active instructor.
- AWS Rekognition unavailable with captured evidence.
- Instructor RFID override.

Evidence thumbnails are exposed to authorized users through protected routes.

### Borrowing And Inventory

Admin-facing inventory and borrowing workflows manage laboratory items, borrowing transactions, returned items, and status tracking. The attendance panel also exposes a borrowing-only action path for console workflows.

### Student And Parent Portal

The portal includes:

- Dashboard summary.
- Profile and account update views.
- Attendance history with evidence links when authorized.
- Online class list and join tracking.
- Excuse letter creation, attachments, parent approval, and downloadable generated letters.
- Messages.
- Notifications for online class events.

Parents can be linked to one or more students and can switch context where the portal supports linked student selection.

When an admin creates a student record from Student Management, the system also creates or syncs a matching student portal account using the student's email. The default student password is the student's first name plus last name with spaces removed, for example `JuanDelaCruz`. Admins can reset a student's portal password back to that default from the Student Management actions.

The excuse-letter form suggests teacher recipients from the instructors assigned to the student's section schedules. If no recipient is selected, the approved letter is sent to all assigned teachers. When a student submits a letter, linked parents with valid email addresses receive a Gmail SMTP notification and a link to review and sign it. After a parent signs or approves the letter, the system generates the complete signed PDF and sends it to each selected or assigned instructor through both email and Messenger. The Messenger attachment uses the protected attachment-download route.

### Online Classes

Instructors and admins can create, update, cancel, delete, and audit online classes for schedules. Students can join online classes, and attendance is recorded with late/face-verification metadata where required.

Online class notifications are stored in-app and can attempt email delivery. Email failures are preserved on notification records.

### Messages

Admin, instructor, clinic, registrar, student, and parent users can use the unified Messenger at `/messages` for role-to-role conversations. Console accounts are intentionally excluded because they are limited to physical attendance-panel operation.

Messenger supports:

- Recipient search by name, email, or role across message-capable users, excluding the current user and console accounts.
- Recipient search results close and the search input clears as soon as a recipient is selected, consistently across staff, student, and parent accounts.
- Existing conversation grouping, so both sent and received messages with the same person appear in one chat thread.
- Text messages, attachment-only messages, or text-plus-attachment messages.
- PDF, Word, image, GIF/WebP, and text file attachments within the configured upload limit.
- Inline image previews for image attachments, with protected download links for all attachment types.
- Read-state updates that only the recipient can apply.
- Gmail email notification when a new message is received. Rapid messages from the same sender to the same recipient are grouped by a configurable cooldown, which defaults to one email per five minutes.

Student/parent portal messages use `student_portal_messages`; staff, student, and parent conversations share the same Messenger page and protected attachment-download route.

### Reports

The shared Reports module serves all non-console roles with role-specific summary cards, bar/donut/trend/list charts, detail rows, filters, and CSV exports.

Report scope:

- Admin: users, students, attendance, borrowing, inventory, clinic cases.
- Clinic: cases, alerts, patient histories, response metrics.
- Registrar: students by academic grouping and enrollment logs.
- Instructor: assigned schedules, scoped attendance, online classes, and online attendance.
- Student: own attendance, online class participation, excuse letters, and messages.
- Parent: linked student attendance, online class participation, and excuse letters.

### Clinic And Emergency

Clinic routes support:

- Dashboard counts and emergency view.
- Automatic clinic dashboard refresh for newly received emergency alerts.
- MP3 emergency alert sound when a new emergency appears on the clinic dashboard.
- A small browser-audio notice that disappears after the clinic user clicks or presses a key once to enable alert sound.
- Case logs.
- Patient histories.
- Clinic reports and CSV export.
- Emergency hotlines.
- Emergency types.
- Emergency alert status updates and dispatch actions.

SMS/provider dispatch is not confirmed as a live external integration in the current source.

Clinic emergency sound usage:

1. Log in as an admin.
2. Open `Settings`.
3. Find `Clinic Emergency Sound`.
4. Enter an optional sound name and choose an audio file. Supported upload types are MP3, WAV, OGG, M4A, and AAC.
5. Click `Upload`. The uploaded sound is saved and selected immediately.
6. Use the radio button beside any stored sound to choose the one used by the clinic dashboard.
7. Use the audio preview controls to test a sound before selecting it.
8. Delete uploaded sounds that are no longer needed. The default emergency sound cannot be deleted.
9. On the clinic dashboard, click anywhere or press any key once so the browser allows alert audio playback.

Only the selected sound plays when the clinic dashboard receives a new emergency. If the selected uploaded sound is deleted, the system falls back to the default sound at `/sound/emergency-alert.mp3`.

### Audit Logs

The repository includes activity logging and expanded audit/event structures. Admins can review and export activity logs. The online class module has its own audit log table and export.

Audit records should avoid sensitive request bodies, secrets, tokens, passwords, and face image payloads.

## Important Routes

| Route                                           | Purpose                                                                    |
| ----------------------------------------------- | -------------------------------------------------------------------------- |
| `/`                                             | Student/parent login or role redirect.                                     |
| `/dashboard`                                    | Role-based dashboard redirect.                                             |
| `/messages`                                     | Unified authenticated Messenger for all non-console roles.                 |
| `/messages/new`                                 | Public message creation for inbound public/student-to-instructor messages. |
| `/reports`                                      | Shared role-aware reports.                                                 |
| `/reports/export`                               | Shared role-aware CSV export.                                              |
| `/attendance-control-panel/login`               | Console panel login.                                                       |
| `/attendance-control-panel`                     | Console attendance panel.                                                  |
| `/attendance-evidence/{attendanceLog}/{moment}` | Protected attendance evidence preview.                                     |
| `/admin/dashboard`                              | Admin/instructor dashboard.                                                |
| `/admin/students`                               | Student management and instructor-scoped student view.                     |
| `/admin/schedules`                              | Schedule management/viewing.                                               |
| `/admin/inventory`                              | Inventory management.                                                      |
| `/admin/borrow`                                 | Borrowing workflows.                                                       |
| `/admin/active-devices`                         | Laboratories, panel access, panel monitoring, and force logout.            |
| `/admin/activity-logs`                          | System activity log.                                                       |
| `/admin/online-classes`                         | Online class management.                                                   |
| `/admin/online-class-logs`                      | Online class audit log.                                                    |
| `/registrar/dashboard`                          | Registrar dashboard.                                                       |
| `/registrar/biometric-enrollment`               | Student RFID/face enrollment.                                              |
| `/registrar/instructor-face-enrollment`         | Faculty RFID/face enrollment.                                              |
| `/clinic/dashboard`                             | Clinic dashboard and emergency overview.                                   |
| `/clinic/case-logs`                             | Clinic cases.                                                              |
| `/clinic/patient-history`                       | Patient history records.                                                   |
| `/clinic/reports`                               | Clinic-specific reports.                                                   |
| `/clinic/emergency-hotlines`                    | Emergency hotline CRUD.                                                    |
| `/student-parent/dashboard`                     | Student/parent portal dashboard.                                           |
| `/student-parent/attendance`                    | Student attendance history.                                                |
| `/student-parent/online-classes`                | Student online classes.                                                    |
| `/student-parent/excuse-letters`                | Excuse letters.                                                            |
| `/student-parent/messages`                      | Portal messages.                                                           |
| `/student-parent/notifications`                 | Online class notifications.                                                |

## System Architecture

```mermaid
flowchart LR
    User[Browser User] --> Vue[Vue 3 Inertia Pages]
    Vue --> Routes[Laravel Web Routes]
    Routes --> Controllers[Controllers]
    Controllers --> Models[Eloquent Models]
    Models --> DB[(Database)]
    Controllers --> Storage[Public Storage]
    Controllers --> Services[Services]
    Services --> Rekognition[AWS Rekognition]
```

## Attendance Flow

```mermaid
flowchart TD
    A[Console selects room] --> B[Instructor RFID starts active session]
    B --> C[Student face check or instructor-approved fallback]
    C --> D[One-use attendance grant]
    D --> E[Student RFID tap]
    E --> F{Existing attendance?}
    F -- No --> G[Create check-in]
    F -- Yes, before checkout window --> H{Instructor temporary movement approved?}
    H -- Yes --> I[Temporary exit or return]
    H -- No --> J[Reject temporary movement]
    F -- Yes, checkout window or force logout --> K[Record official checkout]
    K --> L[Completed present or late]
```

## Student/Parent Portal Flow

```mermaid
flowchart TD
    A[Student or parent login] --> B[Portal dashboard]
    B --> C[Attendance history]
    B --> D[Online classes]
    B --> E[Excuse letters]
    B --> F[Messages]
    D --> G[Join class and record online attendance]
    E --> H{Created by student?}
    H -- Yes --> I[Parent approval required]
    H -- No --> J[Approved parent-created letter]
```

## Clinic/Emergency Flow

```mermaid
flowchart TD
    A[Emergency alert] --> B[Clinic dashboard]
    B --> S[Play emergency MP3 after browser audio is enabled]
    B --> C[Update alert status]
    B --> D[Create clinic case]
    D --> E[Create patient history]
    E --> F[Clinic reports]
```

## Project Structure

```text
app/
  Http/Controllers/       Laravel module controllers
  Models/                 Eloquent models
  Services/               Face recognition, online class audit, notification services
database/
  migrations/             Database schema
  seeders/                Demo/default data seeders
docs/                     Analysis reports, operating docs, slide content
resources/
  js/pages/               Inertia Vue pages
  js/components/          Shared Vue components
routes/
  web.php                 Main route map
tests/                    Pest/PHPUnit feature and unit tests
```

## Verification Performed

Use the canonical [Testing and Regression Guide](TESTING.md) for the focused checklist suite, full backend suite, frontend build, coverage map, and troubleshooting.

Current automated verification includes:

- Instructor email OTP and authentication lifecycle.
- Excuse-letter approval, notifications, and generated attachments.
- Messenger email cooldown behavior.
- Registrar navigation and enrollment separation.
- Instructor attendance correction windows and audit logs.
- Clinic responder dispatch and student/patient context.
- Full Laravel feature coverage and frontend production compilation.

See [REPOSITORY_ANALYSIS_AND_FLOW_REPORT.md](REPOSITORY_ANALYSIS_AND_FLOW_REPORT.md) for the deeper feature inventory, flow verification, limitations, and recommendations.

See [CAPSTONE_PRESENTATION.md](CAPSTONE_PRESENTATION.md) for editable presentation slide text. The generated PowerPoint is saved at `capstone-rfid-system-presentation.pptx`.

For a presenter-friendly full demonstration sequence, see [FULL_SYSTEM_DEMONSTRATION_SCRIPT.md](FULL_SYSTEM_DEMONSTRATION_SCRIPT.md).

## Known Limitations And Recommendations

- Add schedule overlap/conflict validation for room, instructor, section, day, and time.
- Add a formal academic year/semester closing workflow if the school needs archival term control.
- Add department/course/curriculum modules only if the institution needs them beyond strand/section/subject scheduling.
- Add a reviewed attendance correction workflow for late administrative corrections.
- Add live SMS or external emergency dispatch integration if required by clinic policy.
- Expand notification coverage to critical attendance events if required by school policy.
- Keep private notes, credentials, production secrets, and AI scratch files out of public documentation.
