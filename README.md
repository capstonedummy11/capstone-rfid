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
- Clinic dashboard, case logs, patient history, reports, emergency types, and emergency alert handling.
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
| Parent | `parent.andrea.santos@sample.com` | `sample` |

`StudentParentAccountSeeder` reuses the demo student `SHS-ICT-1101` when available. If no student exists yet, it creates a fallback ICT strand, section, and Andrea Santos student record. The parent demo account is linked to that student through `parent_student_links` with relationship `mother`.

Discovery: this branch has the student/parent demo accounts and database link, but the source files do not currently include the StudentParent Vue page folder or the matching portal methods in `StudentsController`. `php artisan route:list --path=student-parent` may still show cached StudentParent routes from `bootstrap/cache/routes-v7.php`; clear/regenerate route cache after restoring or changing the portal source files.

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

- Students can view online classes for their section at `/student-parent/online-classes`.
- Students can open the meeting link and record join attendance.
- Join attendance records joined time, attendance status, late flag, face-required flag, face verification result, and face verification timestamp when required.

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

Known limitations:

- The student join page currently uses a confirmation flow to submit `face_verified` when facial recognition is required. A camera capture UI should be wired to the existing `/face-recognition/verify-student` endpoint in a later pass.
- The log export is CSV, which Excel can open. A native `.xlsx` export is not implemented.
- In-app notifications are stored but do not yet have a dedicated student notification center page.

## Important Routes

- `/` - public landing page or role-based redirect after login.
- `/dashboard` - role-based dashboard redirect.
- `/admin/dashboard` - admin/instructor dashboard.
- `/admin/online-classes` - instructor/admin online class management.
- `/admin/online-class-logs` - admin-only online class audit logs.
- `/admin/online-class-logs/export` - admin-only online class audit log CSV export.
- `/student-parent/online-classes` - student online class list and join page.
- `/admin/attendance/scanner` and `/admin/attendance/logs` - attendance tools.
- `/admin/inventory` and `/admin/borrow` - inventory and borrowing.
- `/attendance-control-panel/login` - console panel login.
- `/attendance-control-panel` - console attendance panel.
- `/registrar/dashboard` and `/registrar/biometric-enrollment` - registrar workflows.
- `/clinic/dashboard`, `/clinic/case-logs`, `/clinic/patient-history`, `/clinic/reports` - clinic workflows.
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

- Online Class facial recognition currently records the verification result supplied by the student page. The actual camera-based verification UI still needs to be connected to the existing face verification endpoint.

### Remaining Tasks

- Wire student Online Class join flow to a real camera capture/face verification UI.
- Add a student notification center for `online_class_notifications`.
- Add automated feature tests for instructor scoping, student section scoping, audit logs, and notification creation.
- Add optional native Excel export if `.xlsx` output is required instead of CSV.
- Run migrations and seeders against a live MySQL database.

### Next Steps

- Start MySQL/XAMPP.
- Run `php artisan migrate`.
- Log in as `instructor@sample.com` / `sample`, open `/admin/online-classes`, and create a class.
- Log in as `andrea.santos@student.sample.com` / `sample`, open `/student-parent/online-classes`, and test joining.
- Confirm `online_class_audit_logs`, `online_class_notifications`, and `online_class_attendances` rows are created.

### Architectural Decisions

- Online Class uses separate purpose-built tables instead of overloading RFID attendance tables.
- Instructor authorization is based on `schedules.instructor_id`.
- Student access is based on matching the authenticated student user's email to a `students.email` row and then checking `section_id`.
- Online Class logs are append-only; no update/delete route is provided.
- Notifications are dedicated Online Class records because the existing `messages` feature is student/parent-to-instructor communication, not system notifications.

### Known Issues

- Local MySQL was not available earlier in this branch, so migration execution was not verified against a live database.
- The existing StudentParent portal source was missing in this branch before this change; only the Online Classes student page was added.

### Testing Status

- Passed: PHP syntax checks for new controller, services, models, and migration.
- Passed: `npm run build`.
- Not run: full PHP test suite and live database migration.

### Documentation Status

- `README.md` has been updated for the Online Class module, routes, schema, system setting, known limitations, and this handoff.

## AI Prompt And Change Log

Use this section as a lightweight record of prompts and repository changes made with AI assistance. Add newest entries at the top.

| Date | Prompt / Request | Files Changed | Summary |
| --- | --- | --- | --- |
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
