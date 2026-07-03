# RFID Borrowing and Attendance System

A comprehensive Laravel 12 + Vue 3 + Inertia application for managing laboratory attendance, RFID-based borrowing, inventory tracking, biometric enrollment, clinic operations, and emergency alert systems. This is a full-stack capstone project designed for educational institutions.

## 📋 Project Overview

This system integrates RFID technology with face recognition to create an intelligent facility management solution. It tracks student attendance in laboratories, manages borrowing of equipment, maintains inventory levels, handles patient cases in clinics, and processes emergency alerts—all through role-based user interfaces.

**Key Documentation:**

- [Setup Guide (Beginners)](docs/RUNNING_THE_SYSTEM.md) - Step-by-step Windows setup
- [Software Links](docs/INSTALLATION_LINKS.md) - Download required software

## 🎯 Core Modules & Features

### 1. **RFID Attendance Panel** (`RfidController`, `AttendanceController`)

- Room-based RFID card detection at physical panel devices
- Real-time student tap recording with timestamp
- Optional face verification integration via AWS or CompreFace
- Attendance log viewing and validation
- Customizable attendance behavior settings

**Models:** `Attendance`, `AttendanceLog`, `RfidPanelSession`, `PanelDevice`, `Device`
**Related Routes:** `/attendance/*`, `/rfid/*`, `/active-devices/*`

### 2. **Inventory & Borrowing System** (`BorrowController`, `InventoryController`, `ItemController`)

- Track laboratory items and stock levels
- Record item borrowing by students and instructors
- Set due dates, track returns, manage late returns
- Inventory threshold alerts
- Borrowing history and transaction logging

**Models:** `Borrowing`, `BorrowingItem`, `Inventory`, `Item`, `Transaction`
**Related Routes:** `/borrow/*`, `/inventory/*`, `/items/*`

### 3. **User & Role Management** (`AuthController`, `SystemSettingsController`)

- Multi-role system: Admin, Instructor, Clinic Staff, Registrar, Attendance Console
- Role-based dashboards and permissions
- Face recognition, OTP, and security question-based verification
- RFID tagging for users

**Models:** `User` (supports face_images, rfid_tag, security_questions)
**Key Attributes:** role, rfid_tag, face_images, security_question, security_answer_hash

### 4. **Academic Structure** (`StudentsController`, `InstructorsController`, `ScheduleController`, `SubjectController`, `SectionController`, `StrandController`, `LaboratoryController`)

- Students organized by strand (program) → section → schedules
- Instructors manage subjects and lab sessions
- Schedules define time slots for attendance tracking
- Laboratories as physical spaces for attendance

**Models:** `Students`, `Instructor`, `Schedule`, `Subject`, `Section`, `Strand`, `Laboratory`
**Relations:** Student → Section → Strand; Schedule → Subject → Instructor

### 5. **Registrar Biometric Enrollment** (`RegistrarController`)

- Capture and store student/faculty RFID tags
- Capture and store face images for biometric verification
- Enrollment logs and audit trail

**Models:** `RegistrarEnrollmentLog`
**Services:** `AwsFaceRecognitionService`, `CompreFaceService`

### 6. **Clinic & Emergency Management** (`ClinicController`, `EmergencyController`, `EmergencyController`)

- Record clinic cases with patient info, symptoms, actions taken
- Emergency type definitions (injury, illness, etc.)
- Emergency alert triggering and handling
- Patient history tracking
- Clinic dashboard with case reports

**Models:** `ClinicCase`, `EmergencyAlert`, `EmergencyType`, `PatientHistory`
**Related Routes:** `/clinic/*`, `/emergency/*`

### 7. **Student/Parent Portal** (`StudentsController`)

- Dedicated role-based dashboard for Students and Parents
- Shared authentication and unified portal interface
- Dashboard: Attendance summary, recent records, item borrowings
- Profile Management: View/edit name, phone, gender; change password
- Attendance: Full history with subject and status filters
- Excuse Letters: Submit and track excuse letter requests (with file attachments)
- Messages: View notifications and system messages
- Role-based access control via middleware (`auth`, `role:student,parent`)
- Auto-redirect from home/dashboard routes for student/parent users

**Models:** `Students`, `Attendance`, `Borrowing` (relationships)
**Controller:** `StudentsController` (8 portal methods)
**Related Routes:** `/student-parent/*`, `/student-parent-login`
**Layout:** `StudentParentLayout.vue` (custom layout without panel navigation)
**Pages:** Dashboard, Profile, Attendance, ExcuseLetters, Messages

### 8. **Instructor Verification** (`InstructorVerificationController`)

- Multi-factor instructor verification (face, OTP, security questions)
- Required before certain operations
- Audit logging of verification attempts

### 9. **Activity Logging & System Settings** (`ActivityLogController`, `SystemSettingsController`)

- Track all system activities for audit purposes
- Configurable system-wide settings
- Panel access controls, inventory availability flags, face recognition toggles

**Models:** `ActivityLog`, `SystemSetting`

## 🏗️ Project Structure

```
app/
  Http/Controllers/      # 21 controllers handling business logic
  Models/               # 26 models representing database entities
  Services/            # Face recognition integrations (AWS, CompreFace)
  Concerns/            # Shared validation concerns (PasswordValidationRules, ProfileValidationRules)
  Actions/             # Laravel Actions (Fortify authentication)
  Providers/           # Service providers (AppServiceProvider, FortifyServiceProvider)

resources/
  js/                  # Vue 3 components and pages (Inertia)
  css/                 # Tailwind CSS styles
  views/               # Blade templates (if any)

database/
  migrations/          # Database schema definitions
  seeders/            # Database seeding for development
  factories/          # Model factories for testing

routes/
  web.php              # Main route definitions
  console.php          # Artisan command definitions
  settings.php         # Settings routes (if separate)

config/
  panel.php            # Panel-specific configuration
  fortify.php          # Authentication configuration
  services.php         # Third-party service configs (AWS, CompreFace)

tests/                 # Pest/PHPUnit test suites
```

## 🗄️ Core Database Models & Relationships

| Model                      | Primary Key                 | Purpose                                                      |
| -------------------------- | --------------------------- | ------------------------------------------------------------ |
| **User**                   | user_id                     | System users (admin, instructor, clinic, registrar, console) |
| **Students**               | student_id                  | Student records linked to sections                           |
| **Attendance**             | attendance_id               | Attendance records per student per schedule                  |
| **AttendanceLog**          | attendance_log_id           | Detailed tap logs at RFID panels                             |
| **Borrowing**              | borrowing_id                | Item borrowing transactions                                  |
| **BorrowingItem**          | borrowing_item_id           | Individual items in a borrowing transaction                  |
| **Inventory**              | inventory_id                | Item stock levels                                            |
| **Item**                   | item_id                     | Laboratory item definitions                                  |
| **ClinicCase**             | clinic_case_id              | Patient case records                                         |
| **EmergencyAlert**         | emergency_alert_id          | Emergency events                                             |
| **RfidPanelSession**       | rfid_panel_session_id       | RFID panel session tracking                                  |
| **Schedule**               | schedule_id                 | Class/lab time slots                                         |
| **Subject**                | subject_id                  | Course/subject definitions                                   |
| **Section**                | section_id                  | Student groups within a strand                               |
| **Strand**                 | strand_id                   | Academic program/track                                       |
| **Laboratory**             | laboratory_id               | Physical lab spaces                                          |
| **RegistrarEnrollmentLog** | registrar_enrollment_log_id | Biometric enrollment audit trail                             |

**Key Relationships:**

- `Student` → `Section` → `Strand`
- `Schedule` → `Subject` → `Instructor (User)`
- `Attendance` → `Student`, `Schedule`, `Subject`
- `Borrowing` → `Student`, `User (Instructor)`
- `ClinicCase` → `EmergencyAlert`, `Student`, `User`

## 🚀 Main Features

## 🎨 Tech Stack

**Backend:**

- PHP 8.2+, Laravel 12 framework
- Laravel Fortify (authentication)
- Inertia Laravel (server-side rendering)
- Ziggy (frontend route generation)
- Laravel Wayfinder (URL generation)
- AWS SDK (face recognition integration)

**Frontend:**

- Vue 3 (progressive framework)
- Inertia Vue (server-driven UI)
- Vite 7 (build tool)
- Tailwind CSS 4 (utility-first styling)
- Reka UI (headless component library)
- Lucide Vue (icon library)
- SweetAlert2 (modals & alerts)
- Vue OTP Input (OTP components)
- AOS (scroll animations)

**Testing & Quality:**

- Pest & PHPUnit (PHP testing)
- Laravel Pint (PHP code style)
- ESLint (JavaScript linting)
- Prettier (code formatting)
- vue-tsc (TypeScript validation for Vue)

**Database:**

- MySQL 8.0+ (recommended)
- Laravel migrations for schema management
- Seeders for development data

## 💻 Daily Development

Open two terminals in the project root and run:

**Terminal 1 - Start Laravel Server:**

```bash
php artisan serve
```

Runs the backend on `http://127.0.0.1:8000`

**Terminal 2 - Start Vite Dev Server:**

```bash
npm run dev
```

Watches and hot-reloads Vue/frontend changes

Then open your browser to: `http://127.0.0.1:8000`

**All-in-one alternative:**

```bash
composer run dev
```

Starts Laravel server, queue listener, and Vite in one command.

## 🔧 First-Time Setup

### Prerequisites

Install via [docs/INSTALLATION_LINKS.md](docs/INSTALLATION_LINKS.md):

- PHP 8.2+ (via XAMPP recommended)
- MySQL 8.0+ (via XAMPP)
- Node.js 18+
- Composer
- Git

### Setup Steps

1. **Clone or navigate to project:**

    ```bash
    cd capstone-rfid
    ```

2. **Install PHP dependencies:**

    ```bash
    composer install
    ```

3. **Install Node dependencies:**

    ```bash
    npm install
    ```

4. **Setup environment file:**

    ```bash
    copy .env.example .env
    ```

5. **Configure database in `.env`:**

    ```env
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=capstone_rfid
    DB_USERNAME=root
    DB_PASSWORD=
    ```

    ⚠️ **Do not commit real `.env` secrets** — use `.env.example` for shared configuration.

6. **Generate application key:**

    ```bash
    php artisan key:generate
    ```

7. **Run migrations and seeders:**

    ```bash
    php artisan migrate --seed
    ```

    This creates all tables and populates with sample data.

8. **Build frontend (optional for production):**

    ```bash
    npm run build
    ```

9. **Start development:**
    - Terminal 1: `php artisan serve`
    - Terminal 2: `npm run dev`
    - Open: `http://127.0.0.1:8000`

## 📚 Useful Commands

### Laravel & PHP Commands

```bash
# Start development server
php artisan serve

# Run migrations
php artisan migrate
php artisan migrate:rollback
php artisan migrate:reset

# Seed database with sample data
php artisan db:seed
php artisan db:seed --class=SpecificSeeder

# Generate new model with migration and controller
php artisan make:model ModelName -mcr

# Tinker (interactive shell for testing)
php artisan tinker

# Clear all caches
php artisan optimize:clear
php artisan cache:clear
php artisan config:clear
```

### Frontend & Build Commands

```bash
# Development server with hot reload
npm run dev

# Build production assets
npm run build

# Format code with Prettier
npm run format

# Check formatting without modifying
npm run format:check

# Lint with ESLint
npm run lint
```

### Testing & Quality Commands

```bash
# Run all tests and checks
composer test

# Run PHP tests only
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/SomeTest.php

# Check PHP code style with Pint
./vendor/bin/pint

# Validate TypeScript in Vue files
vue-tsc --noEmit
```

### Database Commands

```bash
# Reset all migrations and reseed
php artisan migrate:fresh --seed

# Create a new migration
php artisan make:migration create_table_name

# Create a new seeder
php artisan make:seeder SeederName
```

npm run lint

````

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
- `/login` - main login page for admin, instructor, clinic, and registrar users.
- `/student-parent-login` - dedicated Student and Parent login page.
- `/register` - user registration page.
- `/dashboard` - role-based dashboard redirect after login.
- `/admin/dashboard` - admin/instructor dashboard.
- `/admin/attendance/scanner` and `/admin/attendance/logs` - attendance tools.
- `/admin/inventory` and `/admin/borrow` - inventory and borrowing.
- `/attendance-control-panel/login` - console panel login for RFID staff.
- `/attendance-control-panel` - console attendance panel (RFID control interface).
- `/registrar/dashboard` and `/registrar/biometric-enrollment` - registrar workflows.
- `/clinic/dashboard`, `/clinic/case-logs`, `/clinic/patient-history`, `/clinic/reports` - clinic workflows.
- `/student-parent/dashboard` - student/parent portal main dashboard with attendance summary.
- `/student-parent/profile` - student/parent profile viewing and editing.
- `/student-parent/attendance` - student/parent full attendance history.
- `/student-parent/excuse-letters` - student/parent excuse letter management.
- `/student-parent/messages` - student/parent messages and notifications.
- `/messages/new` - public message creation form.

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
````

## 🔑 Key Files & Where Things Are

### Frontend (Vue 3 + Inertia)

- **Pages:** `resources/js/pages/` — Each subdirectory is a module (Admin, Clinic, Registrar, etc.)
    - Authentication pages: `resources/js/pages/Auth/`
    - Role dashboards: `resources/js/pages/Admin/`, `resources/js/pages/Clinic/`, etc.
- **Components:** `resources/js/components/` — Reusable Vue components
    - UI components (buttons, modals, tables, forms)
    - Feature-specific components (RFID scanner UI, borrowing forms, etc.)

- **Layouts:** `resources/js/layouts/Layout.vue` — Main layout wrapper
- **Styles:** `resources/css/` — Tailwind CSS global styles
- **Entry:** `resources/js/app.js` — Inertia app bootstrap

### Backend (Laravel)

- **Routes:** `routes/web.php` — All HTTP routes and their endpoints
- **Controllers:** `app/Http/Controllers/` — Business logic for each module
    - Example: `AttendanceController` handles attendance-related requests
- **Models:** `app/Models/` — Database entity representations with relationships
    - Example: `Student` model has `belongsTo(Section)`, `hasMany(Attendance)`
- **Database:**
    - **Migrations:** `database/migrations/` — Schema definitions (run with `php artisan migrate`)
    - **Seeders:** `database/seeders/` — Sample data for development (run with `php artisan db:seed`)
    - **Factories:** `database/factories/` — Model factories for testing

- **Services:** `app/Services/` — External integrations
    - `AwsFaceRecognitionService` — AWS Rekognition integration
    - `CompreFaceService` — CompreFace biometric integration

- **Configuration:** `config/`
    - `panel.php` — RFID panel settings
    - `fortify.php` — Authentication routes/features
    - `services.php` — Third-party API credentials and configuration

### Testing

- **Test Files:** `tests/Feature/` — Feature tests (Pest syntax)
- **Example:** `tests/Feature/InstructorSecurityQuestionTest.php`

## 📝 Common Development Tasks

### Adding a New Feature/Module

1. **Create the Model:**

    ```bash
    php artisan make:model FeatureName -mcr
    ```

    This generates Model, Controller, Migration, and Seeder.

2. **Define Database Schema (in migration):**
    - File: `database/migrations/YYYY_MM_DD_create_feature_names_table.php`
    - Add table columns, relationships, and indexes

3. **Define Model Relationships:**
    - File: `app/Models/FeatureName.php`
    - Add `belongsTo()`, `hasMany()`, etc. methods

4. **Add Controller Logic:**
    - File: `app/Http/Controllers/FeatureNameController.php`
    - Implement `index`, `store`, `show`, `update`, `destroy` methods

5. **Add Routes:**
    - File: `routes/web.php`
    - Use `Route::resource('features', FeatureNameController)` or define custom routes

6. **Create Frontend Pages/Components:**
    - Pages: `resources/js/pages/Feature/Index.vue`, `resources/js/pages/Feature/Create.vue`
    - Components: `resources/js/components/FeatureForm.vue`, etc.

7. **Add Tests:**
    - File: `tests/Feature/FeatureTest.php`
    - Test the controller endpoints, validation, and business logic

8. **Seed Sample Data (optional):**
    - File: `database/seeders/FeatureSeeder.php`
    - Call from `database/seeders/DatabaseSeeder.php`

9. **Update README Feature Progress (required):**
    - Add or update the feature row in the Feature Progress Tracker.
    - Add a new entry in the AI Prompt And Change Log if the work was assisted by AI.
    - Include current status, files touched, verification done, and any remaining notes.

### Adding a New Role/User Type

1. **Update User Model:**
    - Modify validation rules and relationships
    - File: `app/Models/User.php`

2. **Create Role Dashboard:**
    - Create new page: `resources/js/pages/RoleName/Dashboard.vue`

3. **Add Role-Based Routes:**
    - In `routes/web.php`, wrap routes with `auth` and custom middleware if needed

4. **Update Navigation:**
    - Modify layout components to show role-specific menu items
    - File: `resources/js/layouts/Layout.vue`

5. **Add Tests for Role Permissions:**
    - Test that role can only access their routes
    - File: `tests/Feature/RoleAccessTest.php`

### Adding a New API Endpoint

1. **Update Controller:**
    - File: `app/Http/Controllers/SomeController.php`
    - Add method: `public function action(Request $request) { ... }`

2. **Add Route:**
    - File: `routes/web.php`
    - Add: `Route::post('/endpoint', [SomeController::class, 'action']);`

3. **Add Frontend Call:**
    - In Vue component, use Inertia/fetch to call the endpoint

4. **Add Tests:**
    - Test request validation, response data, and side effects
    - File: `tests/Feature/SomeFeatureTest.php`

### Modifying the Database Schema

1. **Create a Migration:**

    ```bash
    php artisan make:migration modify_table_name
    ```

2. **Edit Migration File:**
    - Add `up()` for the change
    - Add `down()` to revert it (for rollback)

3. **Run Migration:**

    ```bash
    php artisan migrate
    ```

    Or rollback and re-run:

    ```bash
    php artisan migrate:rollback
    php artisan migrate
    ```

4. **Update Model if Needed:**
    - If adding/renaming columns, update `$fillable` array in the Model

## 🧪 Testing

### Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/SomeTest.php

# Run tests matching a pattern
./vendor/bin/pest --filter=method_name

# Generate coverage report
./vendor/bin/pest --coverage
```

### Writing a Test

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SomeFeatureTest extends TestCase
{
    public function test_authenticated_user_can_access_feature(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->get('/admin/dashboard');

        $response->assertStatus(200);
    }
}
```

## 🚨 Troubleshooting

### "SQLSTATE[HY000]: General error"

- **Cause:** Database connection issue
- **Fix:** Check `.env` database credentials
    ```bash
    php artisan tinker
    DB::connection()->getPdo();  # Should not error
    ```

### "Module not found" or "Cannot find component"

- **Cause:** Frontend build out of sync
- **Fix:** Rebuild Vite:
    ```bash
    npm run dev
    ```
    Or kill the dev server and restart it.

### "Class not found" error

- **Cause:** Composer autoloader not updated
- **Fix:** Regenerate autoloader:
    ```bash
    composer dump-autoload
    ```

### "Target class [ControllerName] does not exist"

- **Cause:** Route points to non-existent controller
- **Fix:** Check `routes/web.php` and verify controller exists and is imported

### Tests fail with "No application instance"

- **Cause:** Test class not extending `TestCase` properly
- **Fix:** Ensure test extends `Tests\TestCase`

### Face recognition not working

- **Cause:** AWS or CompreFace not configured
- **Fix:** Check `.env` for service credentials:
    ```env
    COMPREFACE_API_KEY=...
    AWS_ACCESS_KEY_ID=...
    AWS_SECRET_ACCESS_KEY=...
    ```

## Feature Progress Tracker

Every feature change must update this README. Add a new row when a feature starts, and update the same row as it moves from planned to in progress, complete, blocked, or needs testing. Keep newest feature work at the top.

| Date       | Feature / Module          | Status      | Files / Areas                         | Progress Notes                                        | Verification              |
| ---------- | ------------------------- | ----------- | ------------------------------------- | ----------------------------------------------------- | ------------------------- |
| 2026-07-03 | README feature tracking   | Complete    | `README.md`                           | Added required README feature progress tracking rule. | Documentation-only change |
| 2026-07-03 | Student/Parent Portal     | Complete    | `routes/web.php`, `StudentsController`, `StudentParentLayout.vue`, portal pages | Dashboard, profile, attendance, excuse letters, messages, authentication, and role guards documented. | Needs full browser QA     |
| 2026-06-21 | Public message form       | Complete    | `resources/js/pages/Messages/Create.vue` | Added success feedback and attachment reset after send. | Needs form submission QA  |
| 2026-06-21 | Instructor verification   | Complete    | `InstructorVerificationController`, `InstructorVerify.vue`, feature test | Improved security-question validation, saved-question flow, and visible answer fields. | Feature test added        |

### Feature Progress Template

Copy this row for future feature work:

```markdown
| YYYY-MM-DD | Feature name | Planned/In Progress/Complete/Blocked/Needs Testing | `file-a`, `file-b` | Short progress note and remaining work. | Tests, build, manual QA, or not run. |
```

## AI Prompt And Change Log

Use this section as a lightweight record of prompts and repository changes made with AI assistance. Add newest entries at the top.

| Date       | Prompt / Request                                                                                                                                  | Files Changed                                                                                                                                         | Summary                                                                                                                                                                                                                                                                                                                                                                         |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-07-03 | Read the README to understand the system and add a requirement that each feature progress must be added to the README.                            | `README.md`                                                                                                                                           | Added a required README update step to the feature workflow plus a Feature Progress Tracker section with status, touched areas, notes, verification, and a reusable template for future feature work.                                                                                                                                                                            |
| 2026-07-03 | Create complete Student/Parent portal with Dashboard, Profile, Attendance, ExcuseLetters, Messages pages with authentication and role guards.     | `routes/web.php`, `app/Http/Controllers/StudentsController.php`, `app/Models/Students.php`, `resources/js/layouts/AuthNavbar.vue`, `README.md`        | Created StudentsController with 8 portal methods (dashboard, showProfile, updateProfile, updatePassword, attendance, excuseLetters, storeExcuseLetter, messages). Added student-parent route group with middleware auth + role:student,parent. Updated Students model with attendances() relationship. Added Student/Parent navigation section to AuthNavbar with portal links. |
| 2026-07-03 | Create a new shared login page for Students and Parents with same design as existing login pages, without Attendance Control Panel in navigation. | `resources/js/layouts/StudentParentLayout.vue`, `resources/js/pages/Auth/StudentParentLogin.vue`, `routes/web.php`, `README.md`                       | Created dedicated Student/Parent login page with custom layout that removes "Attendance Control Panel" navigation item. Added route `/student-parent-login` for shared student and parent access. Layout uses same styling and design as main landing page. Updated README routes documentation.                                                                                |
| 2026-07-03 | Study codebase and expand README with architecture guide and common tasks.                                                                        | `README.md`                                                                                                                                           | Added comprehensive documentation: core modules breakdown with controllers/models/routes, detailed project structure, database relationships, key files guide, common development tasks (adding features, roles, endpoints), testing guide, troubleshooting section. Now AI assistant can understand the system structure and implement features without re-reading code.       |
| 2026-06-21 | Fix message sent feedback on the public message form.                                                                                             | `resources/js/pages/Messages/Create.vue`, `README.md`                                                                                                 | Added local success feedback after message submission and reset the attachment picker so the sent confirmation reliably appears.                                                                                                                                                                                                                                                |
| 2026-06-21 | Fix security question validation labels and seed an example message with a file.                                                                  | `app/Http/Controllers/InstructorVerificationController.php`, `database/seeders/MessageSeeder.php`, `database/seeders/DatabaseSeeder.php`, `README.md` | Renamed setup validation fields to Question 1-3 and added an idempotent demo message with a PDF attachment for an existing seeded student.                                                                                                                                                                                                                                      |
| 2026-06-21 | Fix saving instructor security questions.                                                                                                         | `resources/js/pages/Auth/InstructorVerify.vue`, `tests/Feature/InstructorSecurityQuestionTest.php`, `README.md`                                       | Added setup-form validation feedback, prevented duplicate question choices, carried the saved question into verification, and covered the save endpoint with a feature test.                                                                                                                                                                                                    |
| 2026-06-20 | Make instructor security question answers visible instead of hidden.                                                                              | `resources/js/pages/Auth/InstructorVerify.vue`, `README.md`                                                                                           | Changed instructor security question setup and verification answer inputs from password fields to visible text fields.                                                                                                                                                                                                                                                          |
| 2026-06-20 | Remove the message link from the landing page for now.                                                                                            | `resources/js/layouts/Layout.vue`, `README.md`                                                                                                        | Removed the public `Messages` navigation item from both desktop and mobile landing-page navigation while leaving message routes/pages available for later work.                                                                                                                                                                                                                 |
| 2026-06-20 | Create a README file to use for each prompt and changes; first study the system and files.                                                        | `README.md`                                                                                                                                           | Expanded the README after reviewing project structure, routes, setup docs, package manifests, frontend bootstrap, styles, and seeders. Added stack, setup, routes, demo accounts, project structure, and this prompt/change log.                                                                                                                                                |

### Change Log Template

Copy this row for future work:

```markdown
| YYYY-MM-DD | Prompt summary | `file-a`, `file-b` | Short summary of what changed and why. |
```
