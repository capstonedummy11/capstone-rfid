# Database Schema Reference

This data dictionary was reconciled against every migration through 2026-10-11. See the exhaustive [Database Relationship Reference](DATABASE_RELATIONSHIPS.md) for enforced foreign keys, delete behavior, and application-level links. `created_at` and `updated_at` are omitted from field lists below unless a table has unusual timestamp behavior. `PK` means primary key; `FK` means a database-enforced foreign key; `?` means nullable. JSON fields are stored using the database driver's JSON/text representation.

## Accounts and framework tables

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `users` | `user_id PK`, `name`, `middle_name?`, `last_name?`, `email unique`, `password`, `phone?`, `gender?`, `profile_photo_path?`, `role`, `rfid_tag? unique`, `remember_token?`, `deleted_at?`, `face_images? JSON`, `security_question?`, `security_answer_hash?`, `security_questions? JSON`, `is_root_admin`, `email_verified_at?`, `two_factor_secret?`, `two_factor_recovery_codes?`, `two_factor_confirmed_at?`, `must_change_password` | Central account for every role. `profile_photo_path` identifies an optional public account avatar and is separate from biometric `face_images`. Referenced by Instructor, Parent links, messages, clinic, audits, lifecycle actors, etc. Soft deleted. |
| `password_reset_tokens` | `email PK`, `token`, `created_at?` | Fortify password-reset tokens. |
| `sessions` | `id PK`, `user_id?`, `ip_address?`, `user_agent?`, `payload`, `last_activity` | Database-backed web sessions. `user_id` is indexed but not a migration FK. |
| `cache` | `key PK`, `value`, `expiration` | Database cache values, including notification cooldown when database cache is selected. |
| `cache_locks` | `key PK`, `owner`, `expiration` | Atomic cache locks. |
| `migrations` | `id PK`, `migration`, `batch` | Laravel migration history. |

The migration set creates `jobs` and `failed_jobs` for the database queue. It does not create `job_batches`.

## Academic and identity tables

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `academic_years` | `academic_year_id PK`, `name unique`, `starts_on`, `ends_on`, `status`, `active_semester?`, `activated_at?`, `activated_by_user_id? FK`, `closed_at?`, `closed_by_user_id? FK`, `reopened_at?`, `reopened_by_user_id? FK`, `reopen_reason?` | Year lifecycle. Actor FKs set null when account is deleted. |
| `laboratories` | `laboratory_id PK`, `name`, `description?`, `location`, `status` | Physical rooms/labs referenced by schedules and assigned panel devices. |
| `strands` | `strand_id PK`, `strand_code unique`, `strand_name`, `department`, `status`, `deleted_at?` | Academic strand/track; parent of Sections, Instructors, and enrollments. |
| `sections` | `section_id PK`, `strand_id FK`, `section_name`, `year_level`, `semester`, `school_year`, `status`, `academic_year_id?`, `deleted_at?` | Year/semester class group. `academic_year_id` is a logical link without an FK. Soft deleted when it has no Student, Subject, Schedule, or enrollment history. |
| `subjects` | `subject_id PK`, legacy `section_id? FK`, legacy `user_id? FK`, `subject_name`, active `subject_code` unique, `year_level?`, `department?`, `unit`, `semester?`, `subject_description?`, `deleted_at?` | Reusable subject catalog. Legacy assignment columns remain nullable for compatibility. Soft deleted. |
| `subject_offerings` | `subject_offering_id PK`, `academic_year_id FK`, `subject_id FK`, `section_id FK`, `instructor_id? FK`, `semester`, `status` | Year-specific assignment. Year/subject/section deletion restricted; Instructor deletion sets null. |
| `schedules` | `scheduled_id PK`, `academic_year_id?`, `subject_offering_id?`, `section_id FK`, `subject_code`, `semester?`, `laboratory_id?`, `instructor_id?`, `weekdays`, `time_start`, `time_end`, `room`, `deleted_at?` | Soft-deleted scheduled class. Days use canonical `Sun` through `Sat` tokens. Linked offering Instructor changes synchronize `instructor_id`; the original schedule migration does not declare database foreign keys for year/offering/laboratory/Instructor IDs. |
| `students` | `student_id PK`, legacy/current `section_id? FK`, `strand_id? FK`, `student_number unique`, `first_name`, `last_name`, `middle_name?`, `gender`, `email? unique`, `phone?`, `year_level?`, `semester?`, `school_year?`, `rfid_tag? unique`, `status`, `face_images? JSON`, `deleted_at?` | Permanent Student identity plus compatibility current placement. Soft deleted. |
| `student_enrollments` | `student_enrollment_id PK`, `student_id FK`, `academic_year_id FK`, `section_id FK`, `strand_id FK`, `year_level`, `semester`, `status` default `enrolled`, `enrolled_at?`, `ended_at?` | Historical Student placement. Unique Student/year/semester; Student deletion cascades, academic references restrict. |
| `instructors` | `instructor_id PK`, `user_id FK`, `strand_id FK`, active `instructor_number` unique, `status`, `deleted_at?` | Instructor profile. Instructor and linked User records are soft deleted together. |
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
| `attendance_sessions` | `attendance_id PK`, `subject_code?`, `schedule_id? FK`, `academic_year_id?`, `subject_offering_id?`, `date?`, `time_start?`, `time_end?`, `status`, `room?` | Room/class attendance session. Schedule deletion sets null. Academic year/offering values are logical links without FKs. |
| `attendances` | `attendance_id PK`, `student_id FK`, `schedule_id? FK`, `academic_year_id?`, `subject_offering_id?`, `student_enrollment_id?`, `subject_id?`, `date`, `time_start?`, `time_end?`, `time_in?`, `time_out?`, `check_in_status?`, `status`, `room_status`, `total_taps`, `remarks?`, `subject_code?`, `room?`, `deleted_at?` | Soft-deletable official per-Student result. Removed rows are excluded from panels, summaries, reports, and exports while tap/audit evidence remains stored. Schedule hard deletion sets null; academic and Subject context columns are logical links without FKs. |
| `attendance_logs` | `id PK`, `attendance_id FK`, `main_attendance_id? FK`, `student_id? FK`, `schedule_id? FK`, `academic_year_id?`, `subject_offering_id?`, `student_enrollment_id?`, `time_in?`, `time_out?`, `status?`, `verification_method?`, `time_in_face_path?`, `time_out_face_path?`, `is_late`, `completion_reason?`, `tap_datetime?`, `tap_type?`, `tap_sequence_number`, `device_scanner_id?`, `location?`, `validation_result`, `remarks?` | Per-tap/evidence audit. Attendance-session deletion cascades; official attendance, Student, and Schedule deletion set null. Academic context columns are logical links without FKs. |
| `rfid_panel_sessions` | `panel_session_id PK`, `panel_id?`, `room`, `status`, `subject_code?`, `schedule_id? FK`, `academic_year_id?`, `subject_offering_id?`, `opened_by_user_id? FK`, `is_listening`, `listening_started_at?`, `paused_at?`, `ended_at?`, `meta? JSON` | Live room Console state. Deleting Schedule/User sets null; academic context columns are logical links without FKs. |
| `panel_devices` | `panel_device_id PK`, `label unique`, `pin_hash`, `is_active`, `laboratory_id? FK`, `description?` | Managed Console device/PIN. Laboratory deletion sets null. |

## Online classes

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `online_classes` | `online_class_id PK`, `schedule_id`, `academic_year_id?`, `subject_offering_id?`, `instructor_id?`, `section_id`, `subject_code`, `title`, `description?`, `meeting_link`, `scheduled_date`, `start_time`, `end_time`, `require_face_recognition`, `status`, `created_by_user_id? FK`, `updated_by_user_id? FK`, `cancelled_at?`, `cancelled_by_user_id? FK`, `deleted_at?` | Soft-deleted class. Upcoming Instructor assignment can be null; completed/cancelled attribution remains. Actor deletes set null. Schedule, academic year, offering, Instructor, and Section IDs are logical links without database FKs. |
| `online_class_attachments` | `online_class_attachment_id PK`, `online_class_id FK`, `file_path`, `file_name`, `mime_type?`, `file_size?` | Stored class files; class deletion cascades. |
| `online_class_attendances` | `online_class_attendance_id PK`, `online_class_id FK`, `student_id FK`, `academic_year_id?`, `subject_offering_id?`, `student_enrollment_id?`, `joined_at?`, `status`, `is_late`, `face_required`, `face_verified?`, `face_verified_at?` | Unique class/Student result; class/Student deletion cascades. Academic context columns are logical links without FKs. |
| `online_class_notifications` | `online_class_notification_id PK`, `online_class_id FK`, `student_id FK`, `academic_year_id?`, `subject_offering_id?`, `student_enrollment_id?`, `event`, `title`, `body`, `read_at?`, `email_sent_at?`, `email_error?` | Per-Student portal/email notification. Academic context columns are logical links without FKs. Incremental polling uses the `(student_id, read_at, online_class_notification_id)` index and omits notifications whose Online Class has been soft-deleted. |
| `online_class_audit_logs` | `online_class_audit_log_id PK`, `online_class_id? FK`, `user_id? FK`, `user_role?`, `action`, `section_id? FK`, `ip_address?`, `previous_values? JSON`, `new_values? JSON`, `created_at` | Module-specific audit; referenced deletion sets null. |

## Inventory and borrowing

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `inventory_items` | `item_id PK`, `barcode unique`, `name`, `sku?`, `description?`, `status`, `deleted_at?` | Current inventory catalog; soft deleted. |
| `borrowings` | `borrowing_id PK`, `student_id? FK`, `user_id? FK`, legacy `item_id?`, legacy `quantity`, `borrower_type`, `borrowed_at`, `returned_at?`, `status`, `due_date?`, `remarks?` | Borrow transaction header. Borrower hard deletion sets null. `item_id` is a logical link without an FK. |
| `borrowing_items` | `id PK`, `borrowing_id FK`, `item_id`, `quantity`, `status` | Borrow lines. Header deletion cascades. `item_id` logically targets `inventory_items.item_id` but has no FK. |
| `items` | `item_id PK`, `item_name`, `item_description?`, `item_sku? unique`, `item_barcode? unique`, `status` | Legacy/simple item catalog. |
| `inventories` | `inventory_id PK`, `item_id FK`, `quantity` | Legacy quantity record; legacy item deletion cascades. |
| `transactions` | `transaction_id PK`, `inventory_id FK`, `item_id FK`, `quantity`, `transaction_type` | Legacy inventory movement; inventory deletion cascades, item deletion restricts. |

## Messages, parents, and excuse letters

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `parent_student_links` | `id PK`, `parent_user_id FK`, `student_id FK`, `relationship` | Unique Parent/Student pair; either deletion cascades. |
| `student_portal_messages` | `student_portal_message_id PK`, `student_id? FK`, `student_excuse_letter_id? FK`, sender/recipient fields, compatibility/encrypted message fields, attachment metadata, `excuse_letter_review_decision?`, `excuse_letter_reviewed_by_user_id? FK`, `excuse_letter_reviewed_at?`, review email subject/body/recipients, `read_at?` | Direct Messenger conversations and linked automated excuse-letter workflow notices. Student/Parent Notifications can display recipient-owned excuse notices independently of Messenger access. The optional letter FK identifies generated notices; per-message review fields allow each recipient Instructor to decide independently and preserve the resolved email audit. |
| `messages` | `message_id PK`, `instructor_user_id FK`, `sender_type`, `sender_name`, `sender_email?`, `student_number?`, compatibility `subject?`, compatibility `body`, `subject_ciphertext?`, `body_ciphertext?`, attachment fields, `read_at?` | Legacy public/student-to-Instructor inbox. Instructor deletion cascades. |
| `student_excuse_letters` | `student_excuse_letter_id PK`, `student_id FK`, academic/submission fields, dates/reason/attachment, `status`, Parent signature/approval fields, `recipient_user_ids? JSON` | Portal Parent-approval/PDF workflow. Instructor decisions are stored per delivered `student_portal_messages` row so multiple recipient Instructors remain independent. |
| `excuse_letters` | `id PK`, `user_id FK`, `student_id? FK`, `submitted_by_role`, `subject`, `from_date`, `to_date`, `reason`, attachment metadata, `status`, `review_notes?`, `reviewed_by_user_id? FK`, `reviewed_at?` | Older excuse-letter schema retained for compatibility; current portal uses `student_excuse_letters`. |

## Clinic and emergency

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `emergency_types` | `emergency_type_id PK`, `name`, `category`, `default_message?`, `is_active`, `sort_order`, `deleted_at?` | Configurable panel buttons; soft deleted. |
| `emergency_hotlines` | `emergency_hotline_id PK`, `name`, `category`, `phone_number`, `contact_person?`, `sms_enabled`, `is_active`, `sort_order`, `notes?`, `deleted_at?` | SMS/contact routing; soft deleted. |
| `emergency_alerts` | `emergency_alert_id PK`, `emergency_type_id? FK`, `triggered_by_user_id? FK`, `schedule_id? FK`, `room?`, `subject_code?`, `triggered_by_name?`, `severity`, `status`, `message`, `metadata? JSON`, `sub_type?`, `acknowledged_at?`, `dispatched_at?`, `response_seconds?`, `resolved_at?` | Alert and response metrics. Related master/account/schedule deletion sets null. |
| `clinic_cases` | `clinic_case_id PK`, `emergency_alert_id? FK`, `student_id? FK`, `user_id? FK`, `handled_by_user_id? FK`, `patient_type?`, `patient_name?`, `case_type?`, `symptoms?`, `action_taken?`, `notes?`, `status` (`open`, `monitoring`, `resolved`, `referred`, `cancelled`), `occurred_at?` | Treatment/dispatch case. Linked status synchronizes with its Emergency Notification; all optional links set null on deletion. |
| `patient_histories` | `patient_history_id PK`, `student_id? FK`, `user_id? FK`, `recorded_by_user_id? FK`, `clinic_case_id? FK unique`, `patient_type?`, `patient_name?`, `summary`, `notes?`, `occurred_at?` | Longer-term history; optional links set null. The unique Clinic Case link permits at most one linked history row per case. |

## Configuration and audit

| Table | Fields | Relationships and purpose |
| --- | --- | --- |
| `system_settings` | `system_setting_id PK`, `key unique`, `value? JSON/text`, `type` | Feature flags, including independent `feature.messenger_{role}_enabled` booleans for Admin, Instructor, Clinic, Registrar, Student, and Parent; thresholds, PIN hash, questions, demo RFIDs, sound library, and SMS provider availability/primary-provider keys. |
| `activity_logs` | `logs_id PK`, `event_id? unique`, `user_id? FK`, `user_name?`, `user_role?`, `action`, `table_name`, `module?`, `outcome`, `severity`, `subject_type?`, `subject_id?`, `route_name?`, `http_method?`, `ip_address?`, `user_agent?`, `status_code?`, `description?`, `created_at` | General audit. Account deletion sets `user_id` null while snapshots remain. |
| `root_transfer_requests` | Old/new owner FKs, requester, status, unique pending guard, hashed cancellation token, effective/expiry/acceptance/completion/reminder timestamps, request IP/user agent | Normal accepted and delayed Root Admin ownership lifecycle. |
| `root_override_requests` / `root_override_approvals` | Requester/current/proposed owner, written reason, required approvals, delay/expiry/status; unique approver decision per request | Multi-person delayed emergency ownership recovery. |
| `root_audit_logs` | Actor/target/request FKs, action, timestamp, IP, user agent, metadata JSON | Append-only ownership security log; application models reject update/delete operations. |
| `jobs` / `failed_jobs` | Laravel database queue payload, reservation, attempt, availability, and failure fields | Supports queued ownership mail and other queued work. |

## Important uniqueness and lifecycle rules

- Academic year name is unique and should be consecutive `YYYY-YYYY`.
- Section identity is scoped by academic year/semester in the evolved schema rules.
- One Student enrollment is expected per Student/year/semester.
- One subject offering is expected per year/semester/subject/section combination.
- Email, RFID, Student/Instructor numbers, Subject codes, Strand codes, and inventory barcodes are unique among active rows. Soft-deleted rows retain their original values for audit history but do not block replacement records. SQLite/PostgreSQL enforce this with partial unique indexes; MySQL uses generated active-value columns with unique indexes.
- RFID tags are unique separately on active `users` and active `students`; controller checks also prevent cross-table collisions.
- Online attendance is unique per class/Student and finalizer inserts are idempotent.
- Rollover source/destination and rollover/Student pairs are unique.
- Soft-delete validation must use `Rule::unique(...)->whereNull('deleted_at')`; update rules must additionally ignore the current primary key. Database indexes remain the final concurrency-safe enforcement layer.

## File storage that is not in the database

The database stores paths and metadata, while bytes live in Laravel storage: Student/Instructor faces, attendance face evidence, Messenger files, online-class files, excuse-letter support files and generated PDFs, and uploaded emergency sounds. Backups must include both database and `storage/app` content.
