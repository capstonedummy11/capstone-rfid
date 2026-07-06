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
- Admin user management for clinic, registrar, and admin accounts, with root-admin-only admin creation/deletion.
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
| Root admin | `root.admin@sample.com` | `sample` |
| Admin | `admin@gmail.com` | `password` |
| Test admin | `test@example.com` | `password` |
| Demo admin | `admin@sample.com` | `sample` |
| Dev admin | `jeromebernante@gmail.com` | `1234` |
| Dev admin | `vallecera@gmail.com` | `sample` |
| Instructor | `instructor@sample.com` | `sample` |
| Clinic | `clinic@sample.com` | `sample` |
| Registrar | `registrar@sample.com` | `sample` |
| Console | `console@sample.com` | `sample` |
| Console | `comlab1@example.com` | `1234` |
| Console | `comlab2@example.com` | `1234` |
| Console | `comlab3@example.com` | `1234` |
| Console | `comlab4@example.com` | `1234` |
| Console | `comlab5@example.com` | `1234` |
| Student | `andrea.santos@student.sample.com` | `sample` |
| Student | `miguel.reyes@student.sample.com` | `sample` |
| Parent | `parent.andrea.santos@sample.com` | `sample` |

`StudentParentAccountSeeder` reuses demo students `SHS-ICT-1101` and `SHS-ICT-1102` when available. If no student exists yet, it creates fallback ICT student records. The parent demo account is linked to Andrea Santos through `parent_student_links` with relationship `mother`.

Current state: this branch now includes the StudentParent Vue pages, portal controller methods, demo student/parent accounts, and the parent-student database link.

## README Maintenance Rule

Whenever a meaningful project-facing discovery, limitation, setup step, account, schema change, route change, or implementation update is found while working on this project, update this `README.md` in the same change. Keep demo accounts, route notes, and public feature documentation current so the next work session starts from accurate project knowledge.

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
- `/admin/users` - admin user management for clinic, registrar, and admin accounts. Only root admins can create or delete admin accounts.
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
