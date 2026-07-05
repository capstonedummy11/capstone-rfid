# RFID Borrowing and Attendance System

RFID - Attendance Monitoring, Borrowing, and Inventory System is a Laravel 12, Inertia, and Vue 3 application for managing laboratory attendance, RFID-based borrowing, inventory, registrar enrollment, instructor verification, clinic cases, and emergency alerts.

## Project Status

This repository already contains the Laravel backend, Vue/Inertia frontend, migrations, seeders, tests, and beginner setup documents.

Start with these docs when setting up a new machine:

- Full beginner guide: [docs/RUNNING_THE_SYSTEM.md](docs/RUNNING_THE_SYSTEM.md)
- Official software download links: [docs/INSTALLATION_LINKS.md](docs/INSTALLATION_LINKS.md)

## Main Features

- Public landing page and message form.
- Role-based dashboards for admin, instructor, clinic, registrar, and attendance console users.
- RFID attendance control panel with room selection, RFID lookup, student tap recording, attendance logs, and optional face verification.
- Inventory and borrowing workflows for laboratory items.
- Student, instructor, section, strand, subject, schedule, and laboratory management.
- Registrar biometric enrollment for student and faculty RFID or face records.
- Clinic dashboard, case logs, patient history, reports, emergency types, emergency hotline CRUD, and emergency alert handling.
- Instructor verification by face, OTP, or security questions.
- System settings for panel access, inventory availability, face recognition, security questions, and attendance behavior.
- Online Class management for instructors, student online-class joining, attendance recording, notifications, and admin audit logs.

## Tech Stack

- Backend: PHP 8.2+, Laravel 12, Laravel Fortify, Inertia Laravel, Ziggy, Laravel Wayfinder.
- Frontend: Vue 3, Inertia Vue, Vite 7, Tailwind CSS 4, Reka UI, Lucide Vue, SweetAlert2.
- Testing and quality: Pest, PHPUnit, Laravel Pint, ESLint, Prettier, vue-tsc.
- Database: MySQL is recommended in the setup guide.

## Daily Development

Open two terminals in the project root:

```bash
php artisan serve
```

```bash
npm run dev
```

Then open:

```text
http://127.0.0.1:8000
```

## First-Time Setup

Install PHP/MySQL through XAMPP, Node.js, Composer, and Git if needed. See [docs/RUNNING_THE_SYSTEM.md](docs/RUNNING_THE_SYSTEM.md) for the detailed Windows beginner flow.

Short version:

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Set the database values in `.env` before running migrations:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=capstone_rfid
DB_USERNAME=root
DB_PASSWORD=
```

Do not commit real `.env` secrets.

## Useful Commands

```bash
composer run dev
```

Runs the Laravel server, queue listener, and Vite dev server together.

```bash
npm run build
```

Builds frontend production assets.

```bash
composer test
```

Runs Laravel Pint checks and the PHP test suite.

```bash
npm run format
npm run format:check
npm run lint
```

Formats and lints frontend resources.

## Demo Seed Data

The main database seeder calls:

- `UserSeeder`
- `EmergencySeeder`
- `ComlabUserSeeder`
- `DemoSystemSeeder`
- `StudentParentAccountSeeder`
- `ClinicDashboardSeeder`
- `MessageSeeder`

Common seeded demo accounts include:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@gmail.com` | `password` |
| Demo admin | `admin@sample.com` | `sample` |
| Instructor | `instructor@sample.com` | `sample` |
| Clinic | `clinic@sample.com` | `sample` |
| Registrar | `registrar@sample.com` | `sample` |
| Console | `console@sample.com` | `sample` |
| Student | `andrea.santos@student.sample.com` | `sample` |
| Student | `miguel.reyes@student.sample.com` | `sample` |
| Parent | `parent.andrea.santos@sample.com` | `sample` |

`StudentParentAccountSeeder` reuses demo students `SHS-ICT-1101` and `SHS-ICT-1102` when available. If no student exists yet, it creates fallback ICT student records. The parent demo account is linked to Andrea Santos through `parent_student_links` with relationship `mother`.

Current state: this branch now includes the StudentParent Vue pages, portal controller methods, demo student/parent accounts, and the parent-student database link.

## README Maintenance Rule

Whenever a meaningful discovery, limitation, setup step, account, schema change, route change, or implementation update is found while working on this project, update this `README.md` in the same change. Keep the demo accounts, route notes, and AI prompt/change log current so the next work session starts from accurate project knowledge.

## Online Class Module

The Online Class module lets instructors create and manage online class sessions for their assigned schedules. Admin users can also access the management page and have a dedicated immutable audit-log page.

Implemented instructor/admin capabilities:

- Create, edit, cancel, delete, and list online classes.
- Capture schedule/class, section, subject, title, description/instructions, meeting link, scheduled date, start/end time, file attachments, and facial-recognition requirement.
- Instructor access is scoped to schedules assigned to their instructor profile.
- Admins can view/manage all online classes and inspect/export audit logs.

Implemented student capabilities:

- Students now land on `/student-parent/dashboard` after login.
- Student/Parent portal navigation includes My Dashboard, My Profile, My Attendance, Online Classes, Excuse Letters, Messages, and Notifications.
- Students can view online classes for their section at `/student-parent/online-classes`.
- Students can open the meeting link and record join attendance.
- Join attendance records joined time, attendance status, late flag, face-required flag, face verification result, and face verification timestamp when required.
- Students and linked parents can submit excuse letters with optional attachments.
- Students and linked parents can send portal messages after searching/selecting a recipient. Message history is shown as private conversations, and each message is visible only to its sender and recipient.

Notifications:

- Creating, updating, rescheduling, and cancelling online classes creates in-app notification records in `online_class_notifications`.
- The notification service attempts to send email notifications to enrolled students with class, subject, instructor, schedule, meeting link, and facial-recognition requirement.
- Email failures are stored on the notification row in `email_error`.

Audit logs:

- `online_class_audit_logs` stores immutable online class events newest-first.
- Logged events include create, update, reschedule, cancel, delete, facial-recognition requirement changes, student join, face pass/fail, attendance recorded, in-app notifications, and email notifications.
- Admin log filters support search, date range, instructor id, user id, user role, section id, and action. CSV export is available at `/admin/online-class-logs/export`.

System setting:

- `online_class.face_recognition_enabled_by_default` controls the default Require Facial Recognition toggle for new online classes.
- The setting is exposed in Admin Settings as "Online Class Facial Recognition Enabled by Default".
- Default behavior is `true` when no database row exists.
- Parents have no route or control for this setting.

Online Class database tables:

- `online_classes`
- `online_class_attachments`
- `online_class_attendances`
- `online_class_notifications`
- `online_class_audit_logs`
- `student_excuse_letters`
- `student_portal_messages`

Known limitations:

- Online Class join now uses the shared camera capture component and verifies the captured image through the existing student face-verification flow before recording required-face attendance.
- The Online Class join endpoint also validates the submitted face image server-side before recording attendance, so a plain `face_verified` flag is not accepted for required-face classes.
- The log export is CSV, which Excel can open. A native `.xlsx` export is not implemented.
- Student/parent notification center exists at `/student-parent/notifications` and supports marking notifications as read.
- Student/parent messages require an instructor recipient, are mirrored into that instructor inbox, and are stored with encrypted subject/body ciphertext.
- Parent accounts can switch between linked students on portal pages when more than one child is linked.
- Online Class facial recognition can only be required when Face Rekognition is enabled and AWS Rekognition appears configured. Settings and Online Class forms warn and keep the toggle off when unavailable.
- If an older required-face online class is joined while AWS Rekognition is unavailable, the student is allowed to join and the instructor receives one system inbox message per student/class.

Student portal unfinished items:

- Excuse Letter now generates a Word-compatible `.doc` download from the saved letter record. Native PDF generation is still not implemented.
- Excuse Letter has no instructor/admin review workflow yet; submitted letters stay in the student portal with their stored status.
- Portal Messages now use a conversation-style student/parent UI with the conversation list on the left, an empty-state prompt when there are no conversations, and recipient search before starting a new conversation.
- Portal Messages can receive instructor replies from the instructor inbox. Replies are written back to the portal conversation for the original sender only.
- Attendance page now has client-side search, status filtering, reset, class time, duration, and pagination. It remains read-only.
- Notifications currently cover Online Class events only; excuse-letter status changes and portal message replies do not create student notifications yet.
- Parent profile updates now save only the parent user profile; student accounts still sync their own phone/gender to their student record.
- Online Class face verification depends on existing AWS Rekognition credentials and saved student face images. Missing AWS setup prevents enabling required face recognition; missing student face images can still block required-face verification when the provider is available.

## Important Routes

- `/` - public landing page or role-based redirect after login.
- `/student-parent-login` - separate Student/Parent login page with saved demo profiles.
- `/dashboard` - role-based dashboard redirect.
- `/admin/dashboard` - admin/instructor dashboard.
- `/admin/online-classes` - instructor/admin online class management.
- `/admin/online-class-logs` - admin-only online class audit logs.
- `/admin/online-class-logs/export` - admin-only online class audit log CSV export.
- `/student-parent/dashboard` - student portal dashboard summary.
- `/student-parent/profile` - student profile and password page.
- `/student-parent/attendance` - student attendance history.
- `/student-parent/online-classes` - student online class list and join page.
- `/student-parent/excuse-letters` - student/parent excuse letter submission and history.
- `/student-parent/excuse-letters/{letter}/download` - generated Word-compatible excuse-letter download.
- `/student-parent/messages` - student/parent portal messages.
- `/student-parent/notifications` - student online class notifications.
- `/admin/messages/{message}/reply` - assigned instructor reply back to the linked student portal thread.
- `/admin/attendance/scanner` and `/admin/attendance/logs` - attendance tools.
- `/admin/inventory` and `/admin/borrow` - inventory and borrowing.
- `/attendance-control-panel/login` - console panel login.
- `/attendance-control-panel` - console attendance panel.
- `/registrar/dashboard`, `/registrar/biometric-enrollment`, and `/registrar/instructor-face-enrollment` - registrar workflows.
- `/clinic/dashboard`, `/clinic/case-logs`, `/clinic/patient-history`, `/clinic/reports`, and `/clinic/emergency-hotlines` - clinic workflows.
- `/messages/new` - public message creation.

## Project Structure

```text
app/
  Http/Controllers/       Laravel controllers for each module
  Models/                 Eloquent models
  Services/               Face recognition integrations, online class audit/notification services
database/
  migrations/             Database schema changes
  seeders/                Demo and default data
docs/                     Setup and installation documentation
resources/
  css/                    Tailwind and global styles
  js/app.js               Inertia/Vue app bootstrap
  js/pages/               Inertia page components
  js/components/          Shared Vue components and icons
routes/
  web.php                 Main web routes
tests/                    Pest/PHPUnit tests
```

## AI Development Handoff

### Completed Work

- Added student/parent portal navigation pages: Dashboard, Profile, Attendance, Excuse Letters, Messages, Notifications, and Online Classes.
- Added source-backed student portal routes and controller methods in `StudentsController`.
- Reviewed `README.md`, `docs/RUNNING_THE_SYSTEM.md`, and `docs/INSTALLATION_LINKS.md` before implementation.
- Added Online Class schema migration: `database/migrations/2026_07_04_000002_create_online_class_tables.php`.
- Added Online Class models: `OnlineClass`, `OnlineClassAttachment`, `OnlineClassAttendance`, `OnlineClassNotification`, and `OnlineClassAuditLog`.
- Added `OnlineClassController` with instructor/admin management, student join, admin logs, and CSV export.
- Added `OnlineClassAuditLogger` and `OnlineClassNotificationService`.
- Added admin setting `online_class.face_recognition_enabled_by_default`.
- Added Vue pages for online class management, admin logs, and student online classes.
- Added sidebar navigation links for Online Classes and Online Class Logs.
- Cleared stale Laravel route cache with `php artisan optimize:clear`.
- Verified `npm run build` passes.

### Work In Progress

- No active Online Class implementation work is pending from the latest pass.

### Remaining Tasks

- Add automated feature tests for instructor scoping, student section scoping, audit logs, and notification creation.
- Add optional native Excel export if `.xlsx` output is required instead of CSV.
- Add native PDF output for submitted excuse letters if `.pdf` is required instead of the current Word-compatible `.doc`.

### Next Steps

- Start MySQL/XAMPP.
- Run `php artisan migrate` if a fresh database is used. On this branch, all migrations through `2026_07_04_000003_create_student_portal_letters_and_messages` were verified as `Ran`.
- Log in as `instructor@sample.com` / `sample`, open `/admin/online-classes`, and create a class.
- Log in as `andrea.santos@student.sample.com` / `sample`, open `/student-parent/online-classes`, and test joining.
- Confirm `online_class_audit_logs`, `online_class_notifications`, and `online_class_attendances` rows are created.

### Architectural Decisions

- Online Class uses separate purpose-built tables instead of overloading RFID attendance tables.
- Instructor authorization is based on `schedules.instructor_id`.
- Student access is based on matching the authenticated student user's email to a `students.email` row and then checking `section_id`.
- Parent portal access uses `parent_student_links`; the selected child is passed as `student_id` and scoped to linked students only.
- Online Class logs are append-only; no update/delete route is provided.
- Notifications are dedicated Online Class records because the existing `messages` feature is student/parent-to-instructor communication, not system notifications.
- Portal messages are stored in `student_portal_messages` and mirrored into `messages` for the selected instructor recipient.
- Message subjects and bodies are encrypted into `subject_ciphertext` and `body_ciphertext` on both `messages` and `student_portal_messages`; legacy `subject` and `body` columns now keep placeholder text so raw database reads do not expose content.
- `student_portal_messages.recipient_user_id` is the portal privacy boundary. Student/parent message queries return only rows where the authenticated user is the sender or recipient.
- Instructor inbox messages are scoped to `messages.instructor_user_id`; admin users no longer get broad read/reply access to private instructor inbox messages.
- Instructor replies are written back into `student_portal_messages` with `sender_role = instructor` and `recipient_user_id` set to the original student/parent sender.
- Parent profile edits are kept separate from selected student profile data.
- Student/parent portal actions now write activity logs for profile updates, password changes, excuse-letter submission/download, portal message submission, and notification read status.
- Instructor inbox replies and public/inbox message reads now write activity logs.
- System settings updates write activity logs, including face-recognition forced-off warnings.
- Online Class audit logs now distinguish provider-unavailable face-recognition bypasses from normal face verification passes.
- Registrar RFID, face upload, and face removal actions write both `registrar_enrollment_logs` rows for registrar dashboards and shared `activity_logs` rows for admin activity review.
- Student facial-recognition images are registrar-owned: the student portal has no face-image upload route, and the admin Students modal no longer exposes face-image maintenance. Registrar users upload/remove student face images from Biometric Enrollment.
- Instructor login already supports facial recognition through `/instructor/verify`; it compares the camera capture against `users.face_images`. Registrar users now maintain instructor face images from the dedicated Instructor Face Enrollment page.
- Clinic emergency types describe the emergency scenario; emergency hotlines are separate contact records stored in `emergency_hotlines` and selected during the instructor re-tap emergency flow on the attendance panel.
- Clinic emergency hotline create/update/delete, emergency type create/update/delete, emergency alert status changes, panel emergency alert creation, and clinic dispatch actions write shared `activity_logs` entries visible to admin.
- Attendance Control Panel emergency alerts can include selected hotline metadata in `emergency_alerts.metadata`; actual SMS sending is not implemented yet.
- SMS API recommendation: use Semaphore first for Philippines-only school SMS because it is PH-focused, supports local networks, and advertises sender names and low local SMS pricing (`https://semaphore.co/`). Twilio (`https://www.twilio.com/en-us/sms/pricing/ph`) or Vonage (`https://www.vonage.com/communications-apis/sms/pricing/`) are stronger alternatives if the deployment needs broader global CPaaS tooling, dashboards, and multi-channel messaging.

### Known Issues

- Online Class facial recognition depends on AWS Rekognition configuration and saved student face images. If AWS setup is unavailable, required facial recognition is kept off for new/edited classes and older required-face joins are allowed with a one-time instructor warning.
- Generated excuse-letter downloads are Word-compatible `.doc` files, not native PDF files.
- Registrar face enrollment is limited to 5 stored face images per student/instructor. Registrar users can remove an existing face image and upload a replacement.
- Clinic Case Logs and Patient History are source-backed read-only pages. Dedicated clinic create/edit forms for manual case/history entry are still not implemented.
- Emergency hotline SMS provider integration is pending; the current implementation stores hotline records, lets the instructor select one during the emergency flow, and records intended hotline metadata in emergency alerts.
- Legacy portal message rows with no inferable recipient may be hidden by the new sender/recipient privacy filter until a recipient is assigned.

### Testing Status

- Passed: PHP syntax checks for new controller, services, models, and migration.
- Passed: `npm run build`.
- Passed: `php artisan migrate`; all student/parent portal migrations are now marked `Ran`.
- Passed: `php artisan test tests/Feature/StudentParentPortalTest.php` with 5 tests and 69 assertions covering student/parent portal access, parent-linked attendance access, student/parent message visibility, instructor replies back into the portal, parent profile separation, and generated excuse-letter downloads.
- Passed: `php artisan test tests/Feature/StudentParentPortalTest.php` again with 5 tests and 72 assertions after adding activity-log assertions.
- Passed: `php artisan test tests/Feature/RegistrarPortalTest.php` with 5 tests and 47 assertions covering registrar dashboard/enrollment page access, student RFID assignment logs, duplicate RFID rejection across students/faculty, student face upload logs, and the 5-image face upload limit.
- Passed: `php artisan test tests/Feature/RegistrarPortalTest.php` with 8 tests and 75 assertions after adding the instructor face-enrollment page, registrar face-image removal, shared activity-log checks, and the removed admin face endpoint check.
- Passed: Registrar flow retest on 2026-07-05: `php artisan route:list --path=registrar`, `php artisan test tests/Feature/RegistrarPortalTest.php` with 8 tests and 75 assertions, and `npm run build`.
- Passed: Clinic flow retest on 2026-07-05: PHP syntax checks for `EmergencyController` and `EmergencyHotline`, `php artisan route:list --path=clinic` showing 13 clinic routes, `php artisan test tests/Feature/ClinicFlowTest.php` with 4 tests and 85 assertions, `vendor\bin\pint` for touched PHP files, and `npm run build`.
- Passed: local database update for clinic hotline work on 2026-07-05: `php artisan migrate` ran `2026_07_05_000004_create_emergency_hotlines_table`, then `php artisan db:seed --class=EmergencySeeder` seeded starter emergency hotline rows.
- Passed: corrected attendance-panel emergency flow retest on 2026-07-06: `php artisan test tests/Feature/ClinicFlowTest.php` with 4 tests and 85 assertions, `npm run build`, and no remaining `Emergency Table`/`Use Hotline` visible-section references in `AttendanceControlPanel.vue`.
- Passed: message privacy/encryption retest on 2026-07-06: `php artisan migrate` ran `2026_07_06_000001_encrypt_message_content_and_add_recipients`, `vendor\bin\pint` formatted touched PHP files, `php artisan test tests/Feature/StudentParentPortalTest.php` passed with 5 tests and 80 assertions, and `npm run build` passed.
- Note: `php artisan optimize:clear` cleared config but failed while clearing the database-backed cache because local MySQL on `127.0.0.1:3306` was unavailable. After config was cleared, the focused clinic test used the SQLite testing configuration and passed.
- Passed: `php artisan test tests/Feature/StudentParentPortalTest.php tests/Feature/InstructorSecurityQuestionTest.php tests/Feature/Auth/AuthenticationTest.php` for StudentParent and instructor-security tests, but the existing two-factor auth test fails because the SQLite testing `users` table has no `two_factor_secret` column.
- Not run: full PHP test suite.

### Documentation Status

- `README.md` has been updated for the Online Class module, route/schema changes, parent child selector, notification read handling, instructor inbox mirroring/replies, encrypted recipient-only message conversations, searchable message recipients, generated excuse-letter downloads, attendance filters, parent profile separation, face-recognition availability guards, migration status, and this handoff.
- Continuity instruction: when discovering a missing feature, behavior change, migration status, testing result, blocker, or implementation update, add it to this README and add a row to the AI Prompt And Change Log before handing off.

## AI Prompt And Change Log

Use this section as a lightweight record of prompts and repository changes made with AI assistance. Add newest entries at the top.

| Date | Prompt / Request | Files Changed | Summary |
| --- | --- | --- | --- |
| 2026-07-06 | Encrypt messages and make every role's messages visible only to sender/recipient, with student/parent conversations and recipient search. | `database/migrations/2026_07_06_000001_encrypt_message_content_and_add_recipients.php`, `app/Models/Message.php`, `app/Models/StudentPortalMessage.php`, `app/Http/Controllers/StudentsController.php`, `app/Http/Controllers/MessageController.php`, `resources/js/pages/StudentParent/Messages.vue`, `tests/Feature/StudentParentPortalTest.php`, `README.md` | Added encrypted message subject/body storage, added portal `recipient_user_id`, backfilled existing message ciphertext where possible, scoped message visibility to sender/recipient or assigned instructor, required recipient selection before student/parent send, rebuilt the student/parent messages page as a conversation list plus recipient search, updated focused tests, ran the migration locally, and documented the README continuity rule. |
| 2026-07-06 | Correct Attendance Control Panel emergency flow after clarification. | `resources/js/pages/AttendanceControlPanel.vue`, `README.md` | Removed the always-visible emergency table from the attendance panel. Emergency now follows the intended instructor flow: instructor taps once to start attendance, taps again to open action choices, clicks Emergency Call, selects emergency type, optionally selects a hotline, and then the alert is saved with hotline metadata. |
| 2026-07-05 | Test the Clinic flow, find unfinished/missing features, add emergency hotline CRUD for attendance panel, suggest text API, and add admin-visible logs. | `database/migrations/2026_07_05_000004_create_emergency_hotlines_table.php`, `app/Models/EmergencyHotline.php`, `app/Http/Controllers/EmergencyController.php`, `app/Http/Controllers/AttendanceController.php`, `database/seeders/EmergencySeeder.php`, `routes/web.php`, `resources/js/layouts/AuthNavbar.vue`, `resources/js/pages/Clinic/EmergencyHotlines.vue`, `resources/js/pages/AttendanceControlPanel.vue`, `tests/Feature/ClinicFlowTest.php`, `README.md` | Added `emergency_hotlines` CRUD with clinic nav, seeded default hotline records, added hotline selection to panel-created emergency alerts, added shared activity logs for hotline CRUD, emergency type CRUD, alert updates, panel alert creation, and clinic dispatch, documented read-only clinic gaps, and recommended Semaphore first for PH-only SMS with Twilio/Vonage as broader CPaaS alternatives. |
| 2026-07-05 | Test the Registrar flow. | `README.md` | Retested registrar routes, registrar feature flow, and the production frontend build. Registrar routes showed 9 registrar endpoints, `RegistrarPortalTest` passed with 8 tests and 75 assertions, and `npm run build` passed with the new Instructor Face Enrollment page in the Vite manifest. |
| 2026-07-05 | Add shared admin activity logs, registrar-only face image ownership, instructor login facial recognition check, separate instructor face upload nav, and registrar remove/replace workflow. | `routes/web.php`, `app/Http/Controllers/RegistrarController.php`, `resources/js/layouts/AuthNavbar.vue`, `resources/js/pages/Registrar/Dashboard.vue`, `resources/js/pages/Registrar/BiometricEnrollment.vue`, `resources/js/pages/Registrar/InstructorFaceEnrollment.vue`, `resources/js/pages/Auth/Admin/Students.vue`, `tests/Feature/RegistrarPortalTest.php`, `README.md` | Confirmed instructor login already has facial recognition through `/instructor/verify`; added a registrar Instructor Faces nav/page, added registrar student/instructor face-image removal so images can be replaced, mirrored face-removal events into shared `activity_logs`, removed admin student face-image maintenance routes from the admin Students modal path, and added focused registrar tests. |
| 2026-07-05 | Test Registrar role, find missing features, and add logs. | `app/Http/Controllers/RegistrarController.php`, `tests/Feature/RegistrarPortalTest.php`, `README.md` | Added shared `activity_logs` mirroring for Registrar RFID/face enrollment actions, added a 5-image cap to Registrar face uploads to match student face image limits, and added focused Registrar feature tests for dashboard/enrollment access, RFID assignment logs, duplicate RFID rejection, face upload logs, and the face image cap. |
| 2026-07-05 | Check for missing student/parent feature coverage and add logs. | `app/Http/Controllers/StudentsController.php`, `app/Http/Controllers/MessageController.php`, `app/Http/Controllers/SystemSettingsController.php`, `app/Http/Controllers/OnlineClassController.php`, `tests/Feature/StudentParentPortalTest.php`, `README.md` | Added activity logs for portal profile/password changes, excuse-letter submit/download, portal messages, notification reads, inbox replies/message reads, system settings updates, and explicit Online Class face-recognition bypass audit events. Updated student/parent tests to assert new logs. |
| 2026-07-05 | Test student and parent role behavior. | `tests/Feature/StudentParentPortalTest.php`, `README.md` | Added and ran focused student/parent portal feature tests for access, parent linked-student attendance, student/parent message visibility, instructor replies, parent profile separation, and generated excuse-letter downloads. Targeted StudentParent tests passed; an adjacent existing two-factor auth test still fails because the testing users table lacks two-factor columns. |
| 2026-07-05 | Finish selected student portal gaps: generated excuse letters, instructor replies, searchable message recipients, attendance filters, parent profile separation, and facial-recognition availability guards. | `app/Http/Controllers/StudentsController.php`, `app/Http/Controllers/MessageController.php`, `app/Http/Controllers/OnlineClassController.php`, `app/Http/Controllers/SystemSettingsController.php`, `app/Services/AwsFaceRecognitionService.php`, `routes/web.php`, `resources/views/documents/excuse-letter.blade.php`, `resources/js/pages/StudentParent/Attendance.vue`, `resources/js/pages/StudentParent/ExcuseLetters.vue`, `resources/js/pages/StudentParent/Messages.vue`, `resources/js/pages/StudentParent/OnlineClasses.vue`, `resources/js/pages/Auth/Admin/OnlineClasses.vue`, `resources/js/pages/Auth/Admin/SystemSettings.vue`, `resources/js/pages/Messages/Index.vue`, `README.md` | Added generated Word-compatible excuse-letter downloads, instructor replies back to the student portal, searchable instructor selection, full attendance search/filter/pagination, parent-only profile updates, AWS Rekognition availability checks, settings warnings/forced-off toggles, Online Class require-face safeguards, and one-time instructor warnings when old required-face joins bypass unavailable AWS. |
| 2026-07-05 | Implement audit findings 3-6: dashboard controls, instructor message connection, real online-class face verification, and parent child selector. | `app/Http/Controllers/StudentsController.php`, `app/Http/Controllers/OnlineClassController.php`, `routes/web.php`, `resources/js/components/StudentPortal/LinkedStudentSelector.vue`, `resources/js/pages/StudentParent/*.vue`, `README.md` | Replaced dummy dashboard search/reset/pagination/class-time fields with working controls, mirrored portal messages into the instructor inbox when an instructor is selected, added notification mark-read support, added parent linked-student switching, connected Online Class join to camera capture and existing face verification, and verified the pending student portal migration ran. |
| 2026-07-04 | Check student-side Online Class and add it if missing. | `resources/js/pages/StudentParent/OnlineClasses.vue`, `README.md` | Confirmed the student-side Online Class route/page/navigation already exist and cleaned a visible encoding artifact in the Online Classes page. |
| 2026-07-04 | Add Account Ready 3 behavior to Student/Parent login. | `resources/js/pages/Auth/StudentParentLogin.vue`, `database/seeders/StudentParentAccountSeeder.php`, `README.md` | Updated the separate Student/Parent login page to show one ready profile at a time with three selectable account dots and seeded a third ready demo account for Miguel Reyes. |
| 2026-07-04 | Change only the Student/Parent login page. | `routes/web.php`, `resources/js/app.js`, `app/Http/Responses/LoginResponse.php`, `resources/js/pages/Auth/StudentParentLogin.vue`, `README.md` | Added a separate `/student-parent-login` page with a split school-photo layout and saved Student/Parent demo profiles while leaving the normal admin/instructor login unchanged. Student and parent users now redirect to the Student Portal after login. |
| 2026-07-04 | Replace student/parent Borrowings with Excuse Letters and add Messages with parent/student visibility rules. | `database/migrations/2026_07_04_000003_create_student_portal_letters_and_messages.php`, `app/Models/StudentExcuseLetter.php`, `app/Models/StudentPortalMessage.php`, `app/Http/Controllers/StudentsController.php`, `app/Models/Students.php`, `app/Http/Controllers/OnlineClassController.php`, `routes/web.php`, `resources/js/layouts/AuthNavbar.vue`, `resources/js/pages/StudentParent/ExcuseLetters.vue`, `resources/js/pages/StudentParent/Messages.vue`, `resources/js/pages/StudentParent/Dashboard.vue`, `resources/js/pages/StudentParent/Borrowings.vue`, `README.md` | Removed the student/parent Borrowings page, added excuse-letter submission with live preview, added portal messages, opened student-parent routes to linked parents, and enforced that parents can see linked student messages while students cannot see parent-authored messages. |
| 2026-07-04 | Redesign student dashboard and excuse-letter success modal using reusable components. | `resources/js/pages/StudentParent/Dashboard.vue`, `resources/js/pages/StudentParent/ExcuseLetters.vue`, `resources/js/components/StudentPortal/StatCard.vue`, `resources/js/components/StudentPortal/SuccessModal.vue`, `app/Http/Controllers/StudentsController.php`, `README.md` | Updated the student dashboard toward the provided compact card/calendar/reminder/attendance-table design and moved repeated stat-card and success-modal UI into reusable StudentPortal components. |
| 2026-07-04 | Create student portal navigation pages. | `routes/web.php`, `app/Http/Controllers/StudentsController.php`, `app/Models/Students.php`, `resources/js/layouts/AuthNavbar.vue`, `resources/js/pages/StudentParent/Dashboard.vue`, `resources/js/pages/StudentParent/Profile.vue`, `resources/js/pages/StudentParent/Attendance.vue`, `resources/js/pages/StudentParent/Borrowings.vue`, `resources/js/pages/StudentParent/Notifications.vue`, `README.md` | Added source-backed student dashboard, profile, attendance, borrowings, notifications, and navigation entries. Students now redirect to the dashboard. |
| 2026-07-04 | Implement Online Class module with facial-recognition default setting, notifications, attendance integration, audit logs, admin log page, and README handoff rules. | `database/migrations/2026_07_04_000002_create_online_class_tables.php`, `app/Models/OnlineClass*.php`, `app/Http/Controllers/OnlineClassController.php`, `app/Services/OnlineClassAuditLogger.php`, `app/Services/OnlineClassNotificationService.php`, `app/Models/SystemSetting.php`, `app/Http/Controllers/SystemSettingsController.php`, `routes/web.php`, `resources/js/pages/Auth/Admin/OnlineClasses.vue`, `resources/js/pages/Auth/Admin/OnlineClassLogs.vue`, `resources/js/pages/StudentParent/OnlineClasses.vue`, `resources/js/pages/Auth/Admin/SystemSettings.vue`, `resources/js/layouts/AuthNavbar.vue`, `README.md` | Added a modular Online Class implementation with instructor/admin management, student join attendance, notifications, audit logs, CSV export, system setting, navigation, and AI Development Handoff documentation. |
| 2026-07-04 | Create dummy student and parent accounts and link the parent to student info. | `database/migrations/2026_07_04_000001_create_parent_student_links_table.php`, `database/seeders/StudentParentAccountSeeder.php`, `database/seeders/DatabaseSeeder.php`, `app/Models/User.php`, `app/Models/Students.php`, `README.md` | Added a parent-student link table, model relationships, a seeded student login, a seeded parent login, and documentation for the accounts plus the README maintenance rule. Discovered this branch has cached StudentParent routes but is missing the matching portal source files/methods. |
| 2026-06-21 | Fix message sent feedback on the public message form. | `resources/js/pages/Messages/Create.vue`, `README.md` | Added local success feedback after message submission and reset the attachment picker so the sent confirmation reliably appears. |
| 2026-06-21 | Fix security question validation labels and seed an example message with a file. | `app/Http/Controllers/InstructorVerificationController.php`, `database/seeders/MessageSeeder.php`, `database/seeders/DatabaseSeeder.php`, `README.md` | Renamed setup validation fields to Question 1-3 and added an idempotent demo message with a PDF attachment for an existing seeded student. |
| 2026-06-21 | Fix saving instructor security questions. | `resources/js/pages/Auth/InstructorVerify.vue`, `tests/Feature/InstructorSecurityQuestionTest.php`, `README.md` | Added setup-form validation feedback, prevented duplicate question choices, carried the saved question into verification, and covered the save endpoint with a feature test. |
| 2026-06-20 | Make instructor security question answers visible instead of hidden. | `resources/js/pages/Auth/InstructorVerify.vue`, `README.md` | Changed instructor security question setup and verification answer inputs from password fields to visible text fields. |
| 2026-06-20 | Remove the message link from the landing page for now. | `resources/js/layouts/Layout.vue`, `README.md` | Removed the public `Messages` navigation item from both desktop and mobile landing-page navigation while leaving message routes/pages available for later work. |
| 2026-06-20 | Create a README file to use for each prompt and changes; first study the system and files. | `README.md` | Expanded the README after reviewing project structure, routes, setup docs, package manifests, frontend bootstrap, styles, and seeders. Added stack, setup, routes, demo accounts, project structure, and this prompt/change log. |

### Change Log Template

Copy this row for future work:

```markdown
| YYYY-MM-DD | Prompt summary | `file-a`, `file-b` | Short summary of what changed and why. |
```
