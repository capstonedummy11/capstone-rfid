# Database Schema Reference

This data dictionary was reconciled from every migration by running the full migration chain in a temporary empty database on 2026-09-17. `created_at` and `updated_at` are omitted from field lists below unless a table has unusual timestamp behavior. `PK` means primary key; `FK` means foreign key; `?` means nullable. JSON fields are stored using the database driver's JSON/text representation.

## Accounts and framework tables

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `users` | `user_id PK`, `name`, `middle_name?`, `last_name?`, `email unique`, `password`, `phone?`, `gender?`, `role`, `rfid_tag? unique`, `remember_token?`, `deleted_at?`, `face_images? JSON`, `security_question?`, `security_answer_hash?`, `security_questions? JSON`, `is_root_admin`, `email_verified_at?`, `two_factor_secret?`, `two_factor_recovery_codes?`, `two_factor_confirmed_at?`, `must_change_password` | Central account for every role. Referenced by Instructor, Parent links, messages, clinic, audits, lifecycle actors, etc. Soft deleted. |
| `password_reset_tokens` | `email PK`, `token`, `created_at?` | Fortify password-reset tokens. |
| `sessions` | `id PK`, `user_id?`, `ip_address?`, `user_agent?`, `payload`, `last_activity` | Database-backed web sessions. `user_id` is indexed but not a migration FK. |
| `cache` | `key PK`, `value`, `expiration` | Database cache values, including notification cooldown when database cache is selected. |
| `cache_locks` | `key PK`, `owner`, `expiration` | Atomic cache locks. |
| `migrations` | `id PK`, `migration`, `batch` | Laravel migration history. |

The migration set does **not** create `jobs`, `job_batches`, or `failed_jobs`, even though the example queue connection is `database`.

## Academic and identity tables

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `academic_years` | `academic_year_id PK`, `name unique`, `starts_on`, `ends_on`, `status`, `active_semester?`, `activated_at?`, `activated_by_user_id? FK`, `closed_at?`, `closed_by_user_id? FK`, `reopened_at?`, `reopened_by_user_id? FK`, `reopen_reason?` | Year lifecycle. Actor FKs set null when account is deleted. |
| `strands` | `strand_id PK`, `strand_code unique`, `strand_name`, `department`, `status`, `deleted_at?` | Academic strand/track; parent of Sections, Instructors, and enrollments. |
| `sections` | `section_id PK`, `strand_id FK`, `section_name`, `year_level`, `semester`, `school_year`, `status`, `academic_year_id? FK` | Year/semester class group. Academic year deletion restricted. |
| `subjects` | `subject_id PK`, legacy `section_id? FK`, legacy `user_id? FK`, `subject_name`, `subject_code unique`, `year_level?`, `department?`, `unit`, `semester?`, `subject_description?` | Reusable subject catalog. Legacy assignment columns remain nullable for compatibility. |
| `subject_offerings` | `subject_offering_id PK`, `academic_year_id FK`, `subject_id FK`, `section_id FK`, `instructor_id? FK`, `semester`, `status` | Year-specific assignment. Year/subject/section deletion restricted; Instructor deletion sets null. |
| `schedules` | `scheduled_id PK`, `academic_year_id? FK`, `subject_offering_id? FK`, `section_id FK`, `subject_code`, `semester?`, `laboratory_id? FK`, `instructor_id? FK`, `weekdays`, `time_start`, `time_end`, `room` | Scheduled class. Academic/offering deletion restricted; lab/Instructor deletion sets null. |
| `students` | `student_id PK`, legacy/current `section_id? FK`, `strand_id? FK`, `student_number unique`, `first_name`, `last_name`, `middle_name?`, `gender`, `email? unique`, `phone?`, `year_level?`, `semester?`, `school_year?`, `rfid_tag? unique`, `status`, `face_images? JSON`, `deleted_at?` | Permanent Student identity plus compatibility current placement. Soft deleted. |
| `student_enrollments` | `student_enrollment_id PK`, `student_id FK`, `academic_year_id FK`, `section_id FK`, `strand_id FK`, `year_level`, `semester`, `status` default `enrolled`, `enrolled_at?`, `ended_at?` | Historical Student placement. Unique Student/year/semester; Student deletion cascades, academic references restrict. |
| `instructors` | `instructor_id PK`, `user_id FK`, `strand_id FK`, `instructor_number unique`, `status` | Instructor profile. User deletion cascades. |
| `registrar_enrollment_logs` | `id PK`, `registrar_user_id? FK`, `action`, `person_type`, `person_id`, `person_name`, `identifier?` | Immutable-style Registrar RFID/face audit. Registrar deletion sets null. |
| `legacy_academic_fallback_events` | `legacy_academic_fallback_event_id PK`, `context unique`, `use_count`, `last_used_at?`, `last_payload? JSON` | Counts runtime reads that fall back to deprecated academic columns. |

## Academic rollover tables

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `academic_year_rollovers` | `academic_year_rollover_id PK`, `source_academic_year_id FK`, `destination_academic_year_id FK`, `executed_by_user_id? FK`, `mode`, `status`, `preview_counts? JSON`, `execution_counts? JSON`, `errors? JSON`, `started_at?`, `completed_at?` | One audited source/destination rollover. Years are delete-restricted; actor sets null. |
| `academic_year_rollover_items` | `academic_year_rollover_item_id PK`, `academic_year_rollover_id FK`, `student_id? FK`, `source_student_enrollment_id? FK`, `destination_student_enrollment_id? FK`, `destination_section_id? FK`, `decision`, `status`, `message?`, `payload? JSON` | Per-Student decision/result. Parent rollover deletion cascades; academic identity references restrict. |

## Physical attendance and panel tables

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `attendance_sessions` | `attendance_id PK`, `subject_code?`, `schedule_id? FK`, `academic_year_id? FK`, `subject_offering_id? FK`, `date?`, `time_start?`, `time_end?`, `status`, `room?` | Room/class attendance session. Schedule deletion sets null; academic context restricts. |
| `attendances` | `attendance_id PK`, `student_id FK`, `schedule_id? FK`, `academic_year_id? FK`, `subject_offering_id? FK`, `student_enrollment_id? FK`, `subject_id? FK`, `date`, `time_start?`, `time_end?`, `time_in?`, `time_out?`, `check_in_status?`, `status`, `room_status`, `total_taps`, `remarks?`, `subject_code?`, `room?` | Official per-Student result. Schedule/subject delete set null; academic references restrict. |
| `attendance_logs` | `id PK`, `attendance_id FK`, `main_attendance_id? FK`, `student_id? FK`, `schedule_id? FK`, `academic_year_id? FK`, `subject_offering_id? FK`, `student_enrollment_id? FK`, `time_in?`, `time_out?`, `status?`, `verification_method?`, `time_in_face_path?`, `time_out_face_path?`, `is_late`, `completion_reason?`, `tap_datetime?`, `tap_type?`, `tap_sequence_number`, `device_scanner_id?`, `location?`, `validation_result`, `remarks?` | Per-tap/evidence audit. Attendance session/Student deletes cascade; official/schedule set null; academic context restricts. |
| `rfid_panel_sessions` | `panel_session_id PK`, `panel_id?`, `room`, `status`, `subject_code?`, `schedule_id? FK`, `academic_year_id? FK`, `subject_offering_id? FK`, `opened_by_user_id? FK`, `is_listening`, `listening_started_at?`, `paused_at?`, `ended_at?`, `meta? JSON` | Live room Console state. Deleting schedule/user sets null; academic context restricts. |
| `panel_devices` | `panel_device_id PK`, `label unique`, `pin_hash`, `is_active`, `laboratory_id? FK`, `description?` | Managed Console device/PIN. Laboratory deletion sets null. |

## Online classes

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `online_classes` | `online_class_id PK`, `schedule_id FK`, `academic_year_id? FK`, `subject_offering_id? FK`, `instructor_id FK`, `section_id FK`, `subject_code`, `title`, `description?`, `meeting_link`, `scheduled_date`, `start_time`, `end_time`, `require_face_recognition`, `status`, `created_by_user_id? FK`, `updated_by_user_id? FK`, `cancelled_at?`, `cancelled_by_user_id? FK`, `deleted_at?` | Soft-deleted class. Schedule/Instructor/Section delete cascade; actor deletes set null; academic context restricts. |
| `online_class_attachments` | `online_class_attachment_id PK`, `online_class_id FK`, `file_path`, `file_name`, `mime_type?`, `file_size?` | Stored class files; class deletion cascades. |
| `online_class_attendances` | `online_class_attendance_id PK`, `online_class_id FK`, `student_id FK`, `academic_year_id? FK`, `subject_offering_id? FK`, `student_enrollment_id? FK`, `joined_at?`, `status`, `is_late`, `face_required`, `face_verified?`, `face_verified_at?` | Unique class/Student result; class/Student deletion cascades, academic context restricts. |
| `online_class_notifications` | `online_class_notification_id PK`, `online_class_id FK`, `student_id FK`, `academic_year_id? FK`, `subject_offering_id? FK`, `student_enrollment_id? FK`, `event`, `title`, `body`, `read_at?`, `email_sent_at?`, `email_error?` | Per-Student portal/email notification. |
| `online_class_audit_logs` | `online_class_audit_log_id PK`, `online_class_id? FK`, `user_id? FK`, `user_role?`, `action`, `section_id? FK`, `ip_address?`, `previous_values? JSON`, `new_values? JSON`, `created_at` | Module-specific audit; referenced deletion sets null. |

## Inventory and borrowing

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `inventory_items` | `item_id PK`, `barcode unique`, `name`, `sku? unique`, `description?`, `status`, `deleted_at?` | Current inventory catalog; soft deleted. |
| `borrowings` | `borrowing_id PK`, `student_id? FK`, `user_id? FK`, legacy `item_id? FK`, legacy `quantity`, `borrower_type`, `borrowed_at`, `returned_at?`, `status`, `due_date?`, `remarks?` | Borrow transaction header. Borrower/item deletions set null. |
| `borrowing_items` | `id PK`, `borrowing_id FK`, `item_id FK`, `quantity`, `status` | Borrow lines. Header deletion cascades; item deletion restricts. |
| `items` | `item_id PK`, `item_name`, `item_description?`, `item_sku? unique`, `item_barcode? unique`, `status` | Legacy/simple item catalog. |
| `inventories` | `inventory_id PK`, `item_id FK`, `quantity` | Legacy quantity record; legacy item deletion cascades. |
| `transactions` | `transaction_id PK`, `inventory_id FK`, `item_id FK`, `quantity`, `transaction_type` | Legacy inventory movement; inventory deletion cascades, item deletion restricts. |

## Messages, parents, and excuse letters

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `parent_student_links` | `id PK`, `parent_user_id FK`, `student_id FK`, `relationship` | Unique Parent/Student pair; either deletion cascades. |
| `student_portal_messages` | `student_portal_message_id PK`, `student_id? FK`, `sender_user_id FK`, `recipient_user_id? FK`, legacy `instructor_user_id? FK`, `sender_role`, compatibility `subject`, compatibility `body`, `subject_ciphertext?`, `body_ciphertext?`, `attachment_path?`, `attachment_name?`, `attachment_mime?`, `attachment_size?`, `read_at?` | Unified private Messenger. Student/sender deletion cascades; recipient/Instructor deletion sets null. |
| `messages` | `message_id PK`, `instructor_user_id FK`, `sender_type`, `sender_name`, `sender_email?`, `student_number?`, compatibility `subject?`, compatibility `body`, `subject_ciphertext?`, `body_ciphertext?`, attachment fields, `read_at?` | Legacy public/student-to-Instructor inbox. Instructor deletion cascades. |
| `student_excuse_letters` | `student_excuse_letter_id PK`, `student_id FK`, `academic_year_id? FK`, `student_enrollment_id? FK`, `submitted_by_user_id FK`, `submitted_by_role`, `subject`, `from_date`, `to_date`, `reason`, `attachment_path?`, `attachment_name?`, `status`, `parent_signature?`, `parent_approval_notes?`, `parent_approved_by_user_id? FK`, `parent_approved_at?`, `recipient_user_ids? JSON` | Portal approval/PDF workflow. Student/submitter deletion cascades; Parent sets null; academic context restricts. |
| `excuse_letters` | `id PK`, `user_id FK`, `student_id? FK`, `submitted_by_role`, `subject`, `from_date`, `to_date`, `reason`, attachment metadata, `status`, `review_notes?`, `reviewed_by_user_id? FK`, `reviewed_at?` | Older excuse-letter schema retained for compatibility; current portal uses `student_excuse_letters`. |

## Clinic and emergency

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `emergency_types` | `emergency_type_id PK`, `name`, `category`, `default_message?`, `is_active`, `sort_order`, `deleted_at?` | Configurable panel buttons; soft deleted. |
| `emergency_hotlines` | `emergency_hotline_id PK`, `name`, `category`, `phone_number`, `contact_person?`, `sms_enabled`, `is_active`, `sort_order`, `notes?`, `deleted_at?` | SMS/contact routing; soft deleted. |
| `emergency_alerts` | `emergency_alert_id PK`, `emergency_type_id? FK`, `triggered_by_user_id? FK`, `schedule_id? FK`, `room?`, `subject_code?`, `triggered_by_name?`, `severity`, `status`, `message`, `metadata? JSON`, `sub_type?`, `acknowledged_at?`, `dispatched_at?`, `response_seconds?`, `resolved_at?` | Alert and response metrics. Related master/account/schedule deletion sets null. |
| `clinic_cases` | `clinic_case_id PK`, `emergency_alert_id? FK`, `student_id? FK`, `user_id? FK`, `handled_by_user_id? FK`, `patient_type?`, `patient_name?`, `case_type?`, `symptoms?`, `action_taken?`, `notes?`, `status`, `occurred_at?` | Treatment/dispatch case. All optional links set null on deletion. |
| `patient_histories` | `patient_history_id PK`, `student_id? FK`, `user_id? FK`, `recorded_by_user_id? FK`, `patient_type?`, `patient_name?`, `summary`, `notes?`, `occurred_at?` | Longer-term history; optional links set null. |

## Configuration and audit

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `system_settings` | `system_setting_id PK`, `key unique`, `value? JSON/text`, `type` | Feature flags, thresholds, PIN hash, questions, demo RFIDs, and sound library. |
| `activity_logs` | `logs_id PK`, `event_id? unique`, `user_id? FK`, `user_name?`, `user_role?`, `action`, `table_name`, `module?`, `outcome`, `severity`, `subject_type?`, `subject_id?`, `route_name?`, `http_method?`, `ip_address?`, `user_agent?`, `status_code?`, `description?`, `created_at` | General audit. Account deletion sets `user_id` null while snapshots remain. |

## Important uniqueness and lifecycle rules

- Academic year name is unique and should be consecutive `YYYY-YYYY`.
- Section identity is scoped by academic year/semester in the evolved schema rules.
- One Student enrollment is expected per Student/year/semester.
- One subject offering is expected per year/semester/subject/section combination.
- RFID tags are unique separately on `users` and `students`; controller checks must prevent cross-table collisions.
- Online attendance is unique per class/Student and finalizer inserts are idempotent.
- Rollover source/destination and rollover/Student pairs are unique.
- Soft-delete tables retain history and may release or continue to occupy unique values depending on query/controller behavior.

## File storage that is not in the database

The database stores paths and metadata, while bytes live in Laravel storage: Student/Instructor faces, attendance face evidence, Messenger files, online-class files, excuse-letter support files and generated PDFs, and uploaded emergency sounds. Backups must include both database and `storage/app` content.

