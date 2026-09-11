# Database Documentation

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

This project uses Laravel migrations and seeders to build a MySQL-ready database for the RFID Attendance Monitoring, Borrowing, Inventory, Clinic, Registrar, Online Class, and Student/Parent Portal system.

## Setup Commands

Run migrations and seed demo data:

```bash
php artisan migrate --seed
```

For a clean installation with only essential records, run migrations first and then the minimal seeder:

```bash
php artisan migrate
php artisan db:seed --class=MinimalSeeder
```

`MinimalSeeder` creates one root administrator, the automatically calculated current academic year, and baseline system settings. It does not create demo accounts, students, instructors, rooms, schedules, messages, inventory, emergency records, or attendance data. The root administrator uses `root.admin@sample.com` with the temporary password `change-me-now` unless overridden by environment variables. The account is required to change its password on first login. The academic year follows the June-to-March cycle and can be overridden with `MINIMAL_ACADEMIC_YEAR`, `MINIMAL_ACADEMIC_YEAR_START`, and `MINIMAL_ACADEMIC_YEAR_END`.

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

- `academic_years` - lifecycle records for draft, active, closed, and archived academic years.
- `student_enrollments` - historical student placement per academic year and semester, including section, strand, grade level, and enrollment status.
- `strands` - senior high school strands/tracks.
- `sections` - class sections linked to strands.
- `subjects` - subjects linked to sections and optionally assigned users.
- `subject_offerings` - year- and semester-specific use of a catalog subject, linked to one section and optional instructor.
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

Emergency response metrics are stored on `emergency_alerts` as `acknowledged_at`, `dispatched_at`, and `response_seconds`. Multi-student alerts retain their selected students in metadata and create one linked `clinic_cases` row per student when dispatched.
- `patient_histories` - patient history records.

Messaging and portal tables:

- `messages` - public/student-to-instructor and instructor inbox messages.
- `parent_student_links` - parent account to student links.
- `student_excuse_letters` - student/parent excuse letter submissions with parent approval and signature metadata.
- `student_portal_messages` - authenticated Messenger conversations for students, parents, clinic, registrar, instructors, and admins; console users are excluded from Messenger.

Online Class tables:

- `online_classes` - online class sessions created by instructors/admins.
- `online_class_attachments` - uploaded online class attachments.
- `online_class_attendances` - student online class join attendance.

Online-class historical context:

- `online_classes.academic_year_id` and `subject_offering_id` preserve the class assignment.
- `online_class_attendances.student_enrollment_id` preserves the participating student's roster membership.
- online-class attendance and notifications also copy the class year and offering for direct historical filtering.
- automatic absence finalization uses the enrollment roster for the class year, not `students.section_id`.

Portal excuse-letter context:

- `student_excuse_letters.academic_year_id` identifies the covered academic year.
- `student_excuse_letters.student_enrollment_id` preserves the student's section/strand/year-level placement for that letter.
- legacy letters remain readable with nullable context when no deterministic enrollment can be resolved.

Reporting contract:

- year-aware reports default to the active `academic_years` row.
- historical selection filters tables through their `academic_year_id`; student population reports use `student_enrollments`.
- authorized staff can explicitly select all years, while student and parent reports require an enrollment in the selected year.
- CSV exports reuse the same filter payload as the visible report.

Rollover audit model:

- `academic_year_rollovers` records the source/destination years, operator, mode, status, preview/execution counts, errors, and completion timestamps.
- `academic_year_rollover_items` records each student decision and any resulting destination enrollment/section.
- the source/destination pair and per-rollover student are unique, making successful execution safe to retry.
- rollover copies only explicitly mapped sections, offerings, unassigned schedules, and approved enrollments; attendance, online classes, messages, letters, clinic data, borrowing, evidence, and audit history are excluded.

Legacy academic compatibility:

- legacy student placement and subject-assignment columns are nullable and deprecated.
- Student and Subject Management no longer write those assignment fields; normalized enrollments and offerings are authoritative.
- `legacy_academic_fallback_events` records any compatibility read so production usage can be measured before column removal.
- see [Legacy Academic Dependency Audit](LEGACY_ACADEMIC_DEPENDENCY_AUDIT.md) for the rollback plan and removal gate.
- `online_class_notifications` - per-student online class notifications and email delivery state.
- `online_class_audit_logs` - immutable audit trail for online class activity.

System and audit tables:

- `system_settings` - feature flags and configurable settings.
- `activity_logs` - general activity/audit log records.

Panel PIN behavior:

- `system_settings.panel.pin_hash` stores the global default panel PIN.
- `panel_devices.pin_hash` stores an override PIN for a registered or previously seen panel label.
- Panel login asks for the room first, uses that room to find the latest panel label, checks the matching `panel_devices` PIN when one exists, and otherwise falls back to the global default PIN.

## Key Relationships

`panel_devices.laboratory_id` links one managed device to one physical laboratory. See [Laboratories and Devices](LABORATORIES_AND_DEVICES.md) for CRUD and operational ownership.

- `users.role` controls dashboard access and major account behavior.
- `students.section_id` links students to `sections`.
- `student_enrollments.student_id` links every historical enrollment to the permanent student identity.
- `student_enrollments.academic_year_id`, `section_id`, and `strand_id` preserve placement for one academic year and semester.
- `students.strand_id` links students to `strands`.
- `sections.strand_id` links sections to strands.
- `sections.academic_year_id` links each migrated/new section to its academic year. Section names are unique within an academic year and semester, allowing the same name to be reused in another year.
- `subjects.section_id` links subjects to sections.
- `subject_offerings.subject_id` links an offering to its reusable catalog subject.
- `subject_offerings.academic_year_id`, `section_id`, and `instructor_id` preserve the year-specific class assignment.
- `schedules.section_id` links schedules to sections.
- `schedules.academic_year_id` and `schedules.subject_offering_id` preserve the schedule's year, semester, subject, section, and instructor context.
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
- Parent portal accounts are stored in `users` with `role = parent`; Admin Student Management creates or links these accounts and stores the relationship label on `parent_student_links.relationship`.
- `online_classes.schedule_id`, `instructor_id`, and `section_id` connect online classes to the academic setup.

Student excuse-letter approval fields:

- `parent_signature` stores the typed parent signature shown on generated PDFs.
- `parent_approval_notes` stores optional parent approval notes.
- `parent_approved_by_user_id` links to the parent `users` row that approved the letter.
- `parent_approved_at` stores the approval timestamp.
- `recipient_user_ids` optionally stores selected teacher recipients. When empty, approved letters are sent to all assigned teachers for the student's section.
- Student-created letters use `pending_parent_approval` until a linked parent approves them; parent-created letters are saved as `approved`.
- Excuse-letter attachments are downloaded through an authenticated student/parent route and remain scoped to the selected student.
- Student submission emails each linked parent with a valid email address and a portal link for review and signature.
- Approval generates an official signed PDF stored under `student-excuse-letters/generated`; the generated file metadata is copied to the instructor Messenger record and the PDF is also attached to the instructor email.

Messenger data behavior:

- `student_portal_messages.student_id` is nullable so staff-to-staff and staff-to-parent conversations do not require a student record.
- Student and parent messages may still include `student_id` for selected-student context.
- `sender_user_id` and `recipient_user_id` define conversation privacy.
- `attachment_path`, `attachment_name`, `attachment_mime`, and `attachment_size` store optional message attachment metadata.
- Messenger allows text messages, attachment-only messages, and text-plus-attachment messages.
- Messenger recipient search includes admin, instructor, clinic, registrar, student, and parent users, but excludes console accounts and the current user.
- Selecting a recipient clears the search query and closes the search results for every supported role.
- Messenger attachments are downloaded through an authorized route that allows only the sender or recipient.
- Image attachments can be served inline for chat preview while still using the same authorization check.
- Messenger email throttling uses an atomic cache key scoped to sender and recipient. The default five-minute cooldown can be changed with `MESSENGER_EMAIL_NOTIFICATION_COOLDOWN_MINUTES`.
- Failed email delivery removes the cache key so a subsequent message can retry notification delivery.

Reporting data behavior:

- Shared reports do not add a new database table.
- Admin reports aggregate existing `users`, `students`, `attendances`, `borrowings`, `inventory_items`, and `clinic_cases` rows.
- Clinic reports aggregate existing `clinic_cases`, `patient_histories`, and `emergency_alerts` rows.
- Registrar reports aggregate existing `students`, `sections`, `strands`, and `registrar_enrollment_logs` rows.
- Instructor reports aggregate existing schedules, attendance records, online classes, and online class attendance scoped to the signed-in instructor profile.
- `/reports/export` downloads the same filtered aggregate rows as CSV.

School-year entry behavior:

- Admin Student and Section add/edit forms use academic-year-backed and compatibility dropdown values. Creating a valid missing label creates a draft academic-year record during the transition.
- Closed or archived academic years reject normal section creation, editing, and deletion.
- Existing saved school-year values are merged into the dropdowns so older records remain editable.
- Subject catalog entries can have multiple offerings. Each offering inherits its academic year from the selected section and stores its semester and instructor assignment.
- New schedules select a subject offering, which supplies academic year, semester, section, subject code, and instructor. Legacy section and subject-code fields remain populated for compatibility.

## Attendance Data Model

`attendance_sessions` represents the live room session:

- `attendance_id`
- `subject_code`
- `schedule_id`
- `academic_year_id`
- `subject_offering_id`
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
- `academic_year_id`
- `subject_offering_id`
- `student_enrollment_id`
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
2. `AcademicYearSeeder`
3. `EmergencySeeder`
4. `ComlabUserSeeder`
5. `DemoSystemSeeder`
6. `StudentParentAccountSeeder`
7. `ClinicDashboardSeeder`
8. `MessageSeeder`

Other available seeders not called by default:

- `MinimalSeeder` - root administrator and baseline settings only.
- `AcademicYearSeeder` - active `2026-2027` academic year used by the demo records.
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
| Clinic responder | `clinic.responder@sample.com` | `sample` | Assignable Clinic responder |
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
