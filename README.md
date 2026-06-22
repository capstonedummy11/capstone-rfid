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
- `ClinicDashboardSeeder`

Common seeded demo accounts include:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@gmail.com` | `password` |
| Demo admin | `admin@sample.com` | `sample` |
| Instructor | `instructor@sample.com` | `sample` |
| Clinic | `clinic@sample.com` | `sample` |
| Registrar | `registrar@sample.com` | `sample` |
| Console | `console@sample.com` | `sample` |

## Important Routes

- `/` - public landing page or role-based redirect after login.
- `/dashboard` - role-based dashboard redirect.
- `/admin/dashboard` - admin/instructor dashboard.
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
  Services/               Face recognition integrations
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

## AI Prompt And Change Log

Use this section as a lightweight record of prompts and repository changes made with AI assistance. Add newest entries at the top.

| Date | Prompt / Request | Files Changed | Summary |
| --- | --- | --- | --- |
| 2026-06-22 | Allow registrar biometric enrollment to capture face images with a camera while keeping file upload. | `resources/js/pages/Registrar/BiometricEnrollment.vue`, `README.md` | Added optional camera capture to the Face Image form by converting snapshots into uploadable image files, while retaining the existing file picker and Upload Face submit button. |
| 2026-06-22 | Let instructors reply to messages with an attachment and rename the reply box. | `app/Http/Controllers/MessageController.php`, `app/Models/Message.php`, `database/migrations/2026_06_22_000001_add_reply_fields_to_messages_table.php`, `resources/js/pages/Messages/Index.vue`, `routes/web.php`, `tests/Feature/MessageReplyTest.php`, `README.md` | Added instructor-only message replies with optional file attachments, displayed the instructor reply in the message thread, and changed the bottom message box into a reply box without redesigning the page. |
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
