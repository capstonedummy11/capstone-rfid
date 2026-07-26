# Database Documentation

This project uses Laravel migrations and seeders to build a MySQL-ready database for the RFID Attendance Monitoring, Borrowing, Inventory, Clinic, Registrar, Online Class, and Student/Parent Portal system.

## Setup Commands

Run migrations and seed demo data:

```bash
php artisan migrate --seed
```

Reset and rebuild the local database:

```bash
php artisan migrate:fresh --seed
```

Recommended `.env` database settings for local XAMPP/MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=capstone_rfid
DB_USERNAME=root
DB_PASSWORD=
```

## Migration Groups

Core Laravel tables:

- `users` - all account records for admin, instructor, clinic, registrar, console, student, and parent users.
- `password_reset_tokens` - password reset token records.
- `sessions` - Laravel session storage.
- `cache` and `cache_locks` - Laravel cache tables.

Academic and identity tables:

- `strands` - senior high school strands/tracks.
- `sections` - class sections linked to strands.
- `subjects` - subjects linked to sections and optionally assigned users.
- `schedules` - class schedules linked to sections, subjects, instructors, laboratories, rooms, weekdays, and time ranges.
- `students` - student profile records, RFID tags, section/strand, face images, and school-year details.
- `instructors` - instructor profile records linked to `users`.
- `laboratories` - laboratory/room records.
- `registrar_enrollment_logs` - registrar RFID/face enrollment activity.

Attendance tables:

- `attendance_sessions` - room-level live attendance sessions started from the attendance panel.
- `attendances` - one main attendance record for a student, schedule, and date.
- `attendance_logs` - per-tap attendance audit logs for check-in, temporary movement, checkout, ignored taps, and invalid taps.
- `rfid_panel_sessions` - panel device/session state for active attendance consoles.
- `panel_devices` - known attendance panel device definitions.

Inventory and borrowing tables:

- `items` - early/simple device or item records.
- `inventory_items` - main inventory records with name, SKU, barcode, description, status, and soft deletes.
- `borrowings` - borrowing headers for student or instructor borrowers.
- `borrowing_items` - borrowed item lines linked to `borrowings` and inventory items.
- `inventories` - inventory quantity records when the older inventory schema is present.
- `transactions` - inventory transaction records when the older inventory schema is present.

Clinic and emergency tables:

- `emergency_types` - configurable emergency button/types shown on the panel.
- `emergency_alerts` - alerts triggered from the attendance panel or clinic flows.
- `emergency_hotlines` - clinic-managed emergency hotline/contact records.
- `clinic_cases` - clinic case logs.
- `patient_histories` - patient history records.

Messaging and portal tables:

- `messages` - public/student-to-instructor and instructor inbox messages.
- `parent_student_links` - parent account to student links.
- `student_excuse_letters` - student/parent excuse letter submissions.
- `student_portal_messages` - student/parent private portal conversations.

Online Class tables:

- `online_classes` - online class sessions created by instructors/admins.
- `online_class_attachments` - uploaded online class attachments.
- `online_class_attendances` - student online class join attendance.
- `online_class_notifications` - per-student online class notifications and email delivery state.
- `online_class_audit_logs` - immutable audit trail for online class activity.

System and audit tables:

- `system_settings` - feature flags and configurable settings.
- `activity_logs` - general activity/audit log records.

## Key Relationships

- `users.role` controls dashboard access and major account behavior.
- `students.section_id` links students to `sections`.
- `students.strand_id` links students to `strands`.
- `sections.strand_id` links sections to strands.
- `subjects.section_id` links subjects to sections.
- `schedules.section_id` links schedules to sections.
- `schedules.subject_code` links schedules to subjects.
- `schedules.instructor_id` links schedules to instructor profiles.
- `schedules.laboratory_id` links schedules to laboratories.
- `instructors.user_id` links instructor profiles to user accounts.
- `attendances.student_id` links main attendance rows to students.
- `attendances.schedule_id` links main attendance rows to schedules.
- `attendance_logs.attendance_id` links tap logs to the room-level `attendance_sessions` row.
- `attendance_logs.main_attendance_id` links tap logs to the student-level `attendances` row.
- `attendance_logs.student_id` and `attendance_logs.schedule_id` identify the student and schedule for each tap.
- `borrowings.student_id` is used for student borrowers.
- `borrowings.user_id` is used for instructor/user borrowers.
- `borrowing_items.borrowing_id` links item lines to borrowing headers.
- `borrowing_items.item_id` links borrowed lines to `inventory_items`.
- `emergency_alerts.emergency_type_id` links alerts to emergency types.
- `clinic_cases.emergency_alert_id` can link clinic cases to alerts.
- `parent_student_links.parent_user_id` links parent users to students.
- `online_classes.schedule_id`, `instructor_id`, and `section_id` connect online classes to the academic setup.

## Attendance Data Model

`attendance_sessions` represents the live room session:

- `attendance_id`
- `subject_code`
- `schedule_id`
- `date`
- `time_start`
- `time_end`
- `status`
- `room`
- timestamps

`attendances` represents the official student attendance result:

- `attendance_id`
- `student_id`
- `schedule_id`
- `date`
- `time_start`
- `time_end`
- `time_in`
- `time_out`
- `check_in_status`
- `status`
- `room_status`
- `total_taps`
- `remarks`
- `subject_code`
- `room`
- timestamps

`attendance_logs` represents each RFID tap:

- `id`
- `attendance_id`
- `main_attendance_id`
- `student_id`
- `schedule_id`
- `time_in`
- `time_out`
- `status`
- `tap_datetime`
- `tap_type`
- `tap_sequence_number`
- `device_scanner_id`
- `location`
- `validation_result`
- `remarks`
- timestamps

Attendance statuses:

- `Pending` - checked in, waiting for official checkout.
- `Present` - on-time check-in and official checkout completed.
- `Late` - late check-in and official checkout completed.
- `Incomplete Attendance` - checked in but no official checkout after session end.
- `Absent` - no valid check-in after the attendance period.

Tap types:

- `Check-in`
- `Temporary Exit`
- `Temporary Return`
- `Check-out`
- `Ignored Tap`
- `Invalid Tap`

## System Settings

`system_settings` stores configurable behavior. Important keys include:

- module availability flags for attendance, borrowing, inventory, clinic, registrar, and online classes.
- face recognition enablement.
- attendance panel access settings.
- security question settings.
- absent-attendance default day range.
- online class face recognition default behavior.

## Seeder Order

`DatabaseSeeder` currently runs these seeders:

1. `UserSeeder`
2. `EmergencySeeder`
3. `ComlabUserSeeder`
4. `DemoSystemSeeder`
5. `StudentParentAccountSeeder`
6. `ClinicDashboardSeeder`
7. `MessageSeeder`

Other available seeders not called by default:

- `BorrowingSeeder`
- `SampleInstructorSeeder`
- `SeniorHighAcademicSeeder`

## Seeder Details

`UserSeeder` creates base staff accounts:

| Role | Email | Password | Notes |
| --- | --- | --- | --- |
| Root admin | `root.admin@sample.com` | `sample` | `is_root_admin = true` |
| Admin | `test@example.com` | `password` | Test admin |
| Admin | `jeromebernante@gmail.com` | `1234` | Dev admin |
| Admin | `vallecera@gmail.com` | `sample` | Dev admin |
| Admin | `admin@gmail.com` | `password` | Standard admin |
| Instructor | `instructor@sample.com` | `sample` | RFID `RFID-INSTRUCTOR-SAMPLE` |
| Clinic | `clinic@sample.com` | `sample` | Clinic staff |
| Registrar | `registrar@sample.com` | `sample` | Registrar staff |

`EmergencySeeder` creates:

- `clinic@sample.com` clinic account if missing.
- Emergency types: Seizure, Fainting, Severe Bleeding, Asthma/Allergy, General Distress, Fire Emergency, Earthquake, Other Emergency.
- Emergency hotlines: School Clinic, Security Office, Barangay Emergency.

`ComlabUserSeeder` creates console users:

| Role | Email | Password |
| --- | --- | --- |
| Console | `comlab1@example.com` | `1234` |
| Console | `comlab2@example.com` | `1234` |
| Console | `comlab3@example.com` | `1234` |
| Console | `comlab4@example.com` | `1234` |
| Console | `comlab5@example.com` | `1234` |

`DemoSystemSeeder` creates broad demo data:

- Demo users: `admin@sample.com`, `instructor@sample.com`, `clinic@sample.com`, `console@sample.com`.
- Strand: `TVL-ICT`.
- Instructor profile for Sample Instructor.
- Laboratories: Laboratory 1 and Laboratory 2.
- Sections: ICT 11-A and ICT 12-A for school year 2026-2027.
- Subjects: Computer Programming 1 and Computer Networking 2.
- Schedules for the demo subjects/laboratories.
- Students:

| Student Number | Name | RFID |
| --- | --- | --- |
| `SHS-ICT-1101` | Andrea Santos | `RFID-STUDENT-1101` |
| `SHS-ICT-1102` | Miguel Reyes | `RFID-STUDENT-1102` |
| `SHS-ICT-1103` | Lara Cruz | `RFID-STUDENT-1103` |
| `SHS-ICT-1201` | Rafael Garcia | `RFID-STUDENT-1201` |
| `SHS-ICT-1202` | Nina Dela Cruz | `RFID-STUDENT-1202` |

- Inventory items: USB Keyboard, Optical Mouse, HDMI Cable, Portable Projector, Laboratory Laptop, Web Camera, Ethernet Cable, RFID Reader.
- Borrowing records for active, returned, student, and instructor borrowing examples.
- Demo attendance session, attendance rows, and attendance logs.
- Demo emergency alert, clinic case, patient history, and activity logs.

`StudentParentAccountSeeder` creates portal accounts:

| Role | Email | Password | Link |
| --- | --- | --- | --- |
| Student | `andrea.santos@student.sample.com` | `sample` | Andrea Santos |
| Student | `miguel.reyes@student.sample.com` | `sample` | Miguel Reyes |
| Parent | `parent.andrea.santos@sample.com` | `sample` | Linked to Andrea Santos as mother |

If the demo students do not exist, it creates fallback ICT student records first.

`ClinicDashboardSeeder` creates:

- Clinic user if missing.
- Emergency types for clinic dashboard examples.
- Recent emergency alerts.
- Clinic cases.
- Patient histories.

`MessageSeeder` creates:

- Demo public/student message records.
- Instructor inbox message examples.
- Message data linked to existing demo students and instructors when available.

## Notes And Caveats

- Several migrations are compatibility or alignment migrations because the schema evolved during development.
- `attendance_logs.attendance_id` points to `attendance_sessions`, while `attendance_logs.main_attendance_id` points to `attendances`.
- Legacy attendance log rows may not have `main_attendance_id`, `tap_datetime`, or `tap_type`; admin/instructor pages keep fallback display behavior for those rows.
- `inventory_items` is the main inventory table used by the current borrowing flow.
- `items`, `inventories`, and `transactions` may exist for older inventory compatibility.
- Real `.env` values and production secrets should not be committed.
