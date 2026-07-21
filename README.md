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
- Attendance-panel student taps require active-class enrollment plus AWS face verification against the student's registered face images. The exact successful camera capture is stored as attendance evidence for both time-in and time-out without changing registrar-enrolled reference images. Students without a registered face require the active instructor's RFID approval. Verification creates a short-lived, one-use server-side grant required by the final attendance-write endpoint.
- If the camera is unavailable, the active instructor can scan their RFID once to enable a camera bypass for the current scheduled class. The bypass is stored server-side, applies only to that attendance session, and ends when the class or panel session ends.
- If the camera works but AWS Rekognition is unavailable, each time-in/time-out capture is stored as attendance evidence and the console shows a warning for that attendance event.
- Admin and instructor attendance logs and the student/linked-parent attendance table show authorized time-in and time-out evidence thumbnails. Selecting a thumbnail opens a larger preview; RFID-only overrides display `Not captured`.
- A first student tap creates a checked-in record. The second verified tap records time-out and finalizes the student as `present`, or preserves `late` when applicable. Ending the class with no student time-out marks the open record `absent` with completion reason `cutting`.
- `attendance.late_threshold_minutes` controls how many minutes after scheduled start count as late. Admin Settings exposes this value and defaults it to 15 minutes.
- Inventory and borrowing workflows for laboratory items.
- Student, instructor, section, strand, subject, schedule, and laboratory management.
- Admin user management for clinic, registrar, and admin accounts, with root-admin-only admin creation/deletion.
- Registrar biometric enrollment for student and faculty RFID or face records.
- Registrar student and instructor biometric enrollment supports RFID assignment plus either image-file upload or direct webcam capture, with capture preview/retake controls and the existing five-image limit.
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

| Role       | Email                              | Password   |
| ---------- | ---------------------------------- | ---------- |
| Root admin | `root.admin@sample.com`            | `sample`   |
| Admin      | `admin@gmail.com`                  | `password` |
| Test admin | `test@example.com`                 | `password` |
| Demo admin | `admin@sample.com`                 | `sample`   |
| Dev admin  | `jeromebernante@gmail.com`         | `1234`     |
| Dev admin  | `vallecera@gmail.com`              | `sample`   |
| Instructor | `instructor@sample.com`            | `sample`   |
| Clinic     | `clinic@sample.com`                | `sample`   |
| Registrar  | `registrar@sample.com`             | `sample`   |
| Console    | `console@sample.com`               | `sample`   |
| Console    | `comlab1@example.com`              | `1234`     |
| Console    | `comlab2@example.com`              | `1234`     |
| Console    | `comlab3@example.com`              | `1234`     |
| Console    | `comlab4@example.com`              | `1234`     |
| Console    | `comlab5@example.com`              | `1234`     |
| Student    | `andrea.santos@student.sample.com` | `sample`   |
| Student    | `miguel.reyes@student.sample.com`  | `sample`   |
| Parent     | `parent.andrea.santos@sample.com`  | `sample`   |

`StudentParentAccountSeeder` reuses demo students `SHS-ICT-1101` and `SHS-ICT-1102` when available. If no student exists yet, it creates fallback ICT student records. The parent demo account is linked to Andrea Santos through `parent_student_links` with relationship `mother`.

Current state: this branch now includes the StudentParent Vue pages, portal controller methods, demo student/parent accounts, and the parent-student database link.

## README Maintenance Rule

Whenever a meaningful project-facing discovery, limitation, setup step, account, schema change, route change, or implementation update is found while working on this project, update this `README.md` in the same change. Keep demo accounts, route notes, and public feature documentation current so the next work session starts from accurate project knowledge.

When adding a new feature, review its audit requirements as part of the implementation. Record security-relevant and state-changing actions in the system activity log, including the actor, module, action, outcome, affected record, and request context where applicable. Add or reuse meaningful admin filters when the feature introduces a new module, action, role, or affected record type. Never place passwords, tokens, face images, request bodies, or other sensitive payloads in audit records. Add automated tests confirming that the feature's important actions create the expected logs.

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

System-wide audit logs:

- Admins can review the immutable system activity trail at `/admin/activity-logs` and export the current filtered result as CSV from `/admin/activity-logs/export`.
- Successful and failed state-changing web requests are recorded across all roles and modules with timestamp, actor snapshot, role, module, action, outcome, severity, affected record, route, HTTP method/status, IP address, and user agent. Viewing the audit log and using export endpoints are also audited.
- Filters include free-text search, date range, module, action, actor user ID, role, outcome, severity, affected record type/ID, and IP address. Request bodies, passwords, tokens, face images, and other sensitive payloads are not stored.

Each system activity log can contain:

- Timestamp and a unique event ID for identifying and tracing the event.
- Actor name, user ID, and role. Events without an authenticated actor are identified as System or Guest events.
- Module and action, such as users, inventory, attendance, settings, messages, or online classes together with create, update, delete, export, cancel, or join actions.
- Outcome (`success` or `failure`) and severity (`info`, `warning`, or `error`).
- Affected record type and record ID when one can be determined from the request route.
- A short event description and the Laravel route that handled the request.
- HTTP method and response status code.
- Originating IP address and browser/device user-agent information.

Available system activity-log filters:

- Free-text search across descriptions, actions, modules, actors, emails, event IDs, and routes.
- From/to date range.
- Module and action.
- Actor user ID and role.
- Outcome and severity.
- Affected record type and record ID.
- Exact IP address.

For privacy and security, the system activity log does not store request bodies, passwords, access tokens, face images, or other sensitive payloads. Older activity-log records created before the expanded audit schema may contain fewer details than newly recorded events.

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
- Student/parent and instructor inbox messages work as private Messenger-style conversations with a searchable conversation list, complete chronological incoming/outgoing reply history, unread indicators, attachment links, and a reply composer. Stored message subjects are generated internally for compatibility, student portal messages remain encrypted, and public/student messages are mirrored into the instructor inbox.
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

- `/` - Student/Parent login page or role-based redirect after login. Saved profile tiles are stored per browser only when the user checks "Save this account on this device", expire after 30 days without another authenticated saved-login visit, and never store passwords in the tile data. The staff login link is intentionally hidden from public Student/Parent navigation.
- `/student-parent-login` - redirects to `/`.
- `/secure-route` - default env-configured secured staff login page for admin, instructor, registrar, and clinic accounts only (`SECURE_LOGIN_ROUTE`). Staff accounts use the same 30-day saved profile tile behavior as the Student/Parent login, with password-only re-entry for saved accounts and no stored passwords. Student and parent accounts are rejected here. This page includes a direct Attendance Panel button.
- `/secure-login` - legacy compatibility redirect to the configured `SECURE_LOGIN_ROUTE` when that env value is different.
- `/dashboard` - role-based dashboard redirect.
- `/admin/dashboard` - admin/instructor dashboard.
- `/admin/users` - admin user management for clinic, registrar, and admin accounts. Only root admins can create or delete admin accounts.
- `/admin/online-classes` - instructor/admin online class management.
- `/admin/online-class-logs` - admin-only online class audit logs.
- `/admin/online-class-logs/export` - admin-only online class audit log CSV export.
- `/admin/activity-logs` - admin-only system-wide activity log with advanced filters.
- `/admin/activity-logs/export` - CSV export of the current filtered system activity log.
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
- `/attendance-control-panel/login` - direct console panel login route. It is intentionally hidden from authenticated role navigation and remains available to dedicated attendance-panel devices by URL.
- `/attendance-control-panel` - console attendance panel.
- `/registrar/dashboard`, `/registrar/biometric-enrollment`, and `/registrar/instructor-face-enrollment` - registrar workflows.
- `/clinic/dashboard` - clinic dashboard with alert response tools, emergency type management, and real clinic calendar events.
- `/clinic/case-logs` - clinic case creation, follow-up updates, status tracking, and patient-history creation from a case.
- `/clinic/patient-history` - patient history creation, editing, deletion, and prefill from recent clinic cases.
- `/clinic/reports` and `/clinic/reports/export` - clinic report filters, summaries, trends, recent case breakdowns, and CSV export.
- `/clinic/emergency-hotlines` - clinic emergency hotline CRUD.
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

## Known Limitations

- Online Class facial recognition depends on AWS Rekognition configuration and saved face images.
- Generated excuse-letter downloads are Word-compatible `.doc` files, not native PDF files.
- Registrar face enrollment is limited to 5 stored face images per student/instructor.
- Emergency hotline SMS provider integration is pending; hotline records and intended contact metadata are stored, but no live SMS is sent.
- Legacy portal message rows with no inferable recipient may be hidden by the sender/recipient privacy filter until a recipient is assigned.
