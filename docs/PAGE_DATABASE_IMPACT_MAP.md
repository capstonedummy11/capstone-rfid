# Page Files and Database Impact Map

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

This document maps every Vue page currently present under `resources/js/pages` to the database tables affected by the page's normal backend workflow.

## How to Read This Map

- Vue pages do not connect directly to the database. Database access happens through Laravel routes, controllers, services, Eloquent models, and jobs.
- **Read** means the page displays or uses data from the table.
- **Write** means an action available from the page can insert or update rows.
- **Delete** includes soft deletion when the model supports it.
- **Indirect** means a controller or service may write the table as a consequence of the main action, such as an audit log, notification, or generated attendance record.
- **None/static** means no repository-backed database operation was found for that page.
- **Legacy/unwired** means the file exists but is not the primary page rendered by the current routes. Its database impact applies only if the page is routed or reused.

## Public and General Pages

| Page file | Purpose/status | Database effect |
|---|---|---|
| `resources/js/pages/About.vue` | Public About page. | **None/static.** |
| `resources/js/pages/LandingPage.vue` | Public landing page. | **None/static.** |
| `resources/js/pages/ReusableAboutPage.vue` | Reusable public About page; not a primary current route. | **None/static.** |
| `resources/js/pages/ReusableLandingIndex.vue` | Reusable public landing page; not a primary current route. | **None/static.** |
| `resources/js/pages/Welcome.vue` | Framework/example welcome page; not the current public entry. | **None/static.** |
| `resources/js/pages/Classes.vue` | Legacy or prototype classes page; no active controller render found. | **Legacy/unwired; no confirmed database operation.** |
| `resources/js/pages/StudentsManagement.vue` | Legacy/admin placeholder reached by an Inertia-only route. | **No direct backend data binding in its current route; legacy/unwired for database CRUD.** |

## Authentication Pages

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/Auth/StaffLogin.vue` | Staff authentication. | **Read:** `users`; **write:** `sessions`, login timestamps/security state where implemented; **indirect:** `activity_logs`. |
| `resources/js/pages/Auth/StudentParentLogin.vue` | Student/parent authentication and public portal entry. | **Read:** `users`, `students`, `parent_student_links`; **write:** `sessions`; **indirect:** `activity_logs`. |
| `resources/js/pages/Auth/Login.vue` | Legacy/default login page; current staff and student/parent entry pages supersede it. | If routed: **read** `users`; **write** `sessions`. |
| `resources/js/pages/Auth/Register.vue` | Public account registration. | **Write:** `users`; **indirect:** `activity_logs`; authentication may write `sessions`. |
| `resources/js/pages/Auth/ForgotPassword.vue` | Requests a password-reset link. | **Read:** `users`; **write:** `password_reset_tokens`; mail is external, not a database table. |
| `resources/js/pages/Auth/ResetPassword.vue` | Completes password recovery. | **Read/write:** `users`, `password_reset_tokens`; **indirect:** `sessions`, `activity_logs`. |
| `resources/js/pages/Auth/FirstLoginPassword.vue` | Replaces a temporary password. | **Write:** `users` (`password`, `must_change_password`); **indirect:** `activity_logs`, session state. |
| `resources/js/pages/Auth/ConfirmPassword.vue` | Confirms password before a protected action. | **Read:** `users`; session confirmation state is stored through the configured session driver (`sessions` when database sessions are active). |
| `resources/js/pages/Auth/InstructorVerify.vue` | Instructor face, OTP, or security-question verification. | **Read/write:** `users`, `instructors`; session verification/OTP state may affect `sessions`; **indirect:** `activity_logs`; face provider calls and mail are external. |
| `resources/js/pages/Auth/TwoFactorChallenge.vue` | Two-factor authentication challenge. | **Read/write:** `users` two-factor fields/recovery codes as applicable; session state may affect `sessions`. |

## Admin Dashboard and Configuration

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/Auth/Admin/Dashboard.vue` | Role-aware admin/instructor dashboard. | **Read:** `users`, `students`, `instructors`, `sections`, `strands`, `subjects`, `schedules`, `laboratories`, `attendances`, `attendance_logs`, `online_classes`, `student_portal_messages`, inventory/borrowing tables, clinic/emergency tables, and `activity_logs`. No normal direct write. |
| `resources/js/pages/Auth/Admin/SystemSettings.vue` | System feature and behavior settings. | **Read/write:** `system_settings`; **indirect write:** `activity_logs`. Settings affect attendance, face recognition, panel, inventory, online-class, and security behavior without directly rewriting those tables. |
| `resources/js/pages/Auth/Admin/ActivityLogs.vue` | System audit-log browser. | **Read:** `activity_logs`, `users`; normally no write other than page-access logging if middleware records it. |
| `resources/js/pages/Auth/Admin/UserManagement.vue` | Admin, root-admin, clinic, and registrar account management. | **Read/write/delete:** `users`; **indirect:** `activity_logs`; related sessions may be invalidated by account changes. |

## Academic Structure and People

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/Auth/Admin/Strands.vue` | Strand management. | **Read/write/delete:** `strands`; **read:** related `sections`/`students` for constraints or display where applicable; **indirect:** `activity_logs`. |
| `resources/js/pages/Auth/Admin/Sections.vue` | Section, semester, and school-year management. | **Read/write/delete:** `sections`; **read:** `strands`; dependent records include `students`, `subjects`, `schedules`, `online_classes`; **indirect:** `activity_logs`. Foreign-key rules may block or cascade deletion. |
| `resources/js/pages/Auth/Admin/Subjects.vue` | Subject and section/instructor assignment management. | **Read/write/delete:** `subjects`; **read:** `sections`, `users`; dependent records include `schedules`, `attendance_sessions`, `attendances`, `online_classes`; **indirect:** `activity_logs`. |
| `resources/js/pages/Auth/Admin/Schedules.vue` | Physical class schedule management. | **Read/write/delete:** `schedules`; **read:** `laboratories`, `instructors`, `users`, `sections`, `subjects`; **indirect:** `activity_logs`. Deletion can null, block, or cascade related attendance/online-class relationships according to their foreign keys. |
| `resources/js/pages/Auth/Admin/Instructors.vue` | Instructor profile management. | **Read/write/delete:** `instructors`; **read/write:** linked `users`; **read:** `strands`, schedules/assignments; **indirect:** `activity_logs`. |
| `resources/js/pages/Auth/Admin/Students.vue` | Student identity, placement, account, password reset, and parent-link management. | **Read/write/soft-delete:** `students`; **read:** `sections`, `strands`; **read/write:** matching `users`; **read/write/delete:** `parent_student_links`; **indirect:** `activity_logs`. Related attendance, portal, borrowing, and clinic records may be read or affected by foreign-key behavior on deletion. |

## Laboratories, Devices, Inventory, and Borrowing

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/Auth/Admin/ActiveDevices.vue` | Combined laboratory/device management, PIN changes, monitoring, and remote panel logout. | **Read/write/delete:** `laboratories`, `panel_devices`; **read/write:** `rfid_panel_sessions`; **read/write:** panel keys in `system_settings`; **indirect:** `activity_logs`, console `users` where runtime panel accounts are managed. |
| `resources/js/pages/Auth/Admin/Laboratories.vue` | Separate/legacy laboratory management page. | **Read/write/delete:** `laboratories`; **read:** `schedules`, `panel_devices`; **indirect:** `activity_logs`. The combined Active Devices page is the main current UI. |
| `resources/js/pages/Auth/Admin/Inventory.vue` | Inventory item management. | **Read/write/soft-delete:** `inventory_items`; compatibility flows may also read `items`, `inventories`, and `transactions`; **indirect:** `activity_logs`. |
| `resources/js/pages/Borrow.vue` | Borrowing and return workflow. | **Read/write:** `borrowings`, `borrowing_items`; **read/write:** `inventory_items` availability/status or quantities; **read:** `students`, `users`, `instructors`; **indirect:** `activity_logs`. |

## Physical Attendance Pages

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/AttendancePanelLogin.vue` | Room/device selection and panel PIN authentication. | **Read:** `laboratories`, `panel_devices`, panel settings in `system_settings`; **read/write:** `rfid_panel_sessions`; runtime authentication may read/write console `users` and `sessions`; **indirect:** `activity_logs`. |
| `resources/js/pages/AttendanceControlPanel.vue` | Live instructor/student RFID attendance, face/fallback verification, movement, checkout, dismissal, borrowing shortcut, and emergencies. | **Read:** `rfid_panel_sessions`, `panel_devices`, `laboratories`, `schedules`, `sections`, `subjects`, `instructors`, `users`, `students`, `system_settings`, emergency types/hotlines, inventory; **write:** `attendance_sessions`, `attendances`, `attendance_logs`, `rfid_panel_sessions`, `emergency_alerts`; borrowing actions may write `borrowings`, `borrowing_items`, `inventory_items`; **indirect:** `activity_logs`; evidence files go to storage, not a database table. |
| `resources/js/pages/AttendanceScanner.vue` | Admin/instructor attendance scanner and recent activity view. | **Read:** `schedules`, `sections`, `subjects`, `instructors`, `students`, `attendance_sessions`, `attendances`, `attendance_logs`; scan actions can **write** attendance tables and `activity_logs`. |
| `resources/js/pages/AttendanceLogs.vue` | Legacy detailed attendance log view and correction UI. | **Read:** `attendance_sessions`, `attendances`, `attendance_logs`, `students`, `schedules`, `subjects`, `sections`, `instructors`, `users`; status correction **writes** `attendances`, `attendance_logs`, and `activity_logs`. |
| `resources/js/pages/Attendance/SubjectSelection.vue` | Subject-centered attendance entry and filtering. | **Read:** `subjects`, `sections`, `strands`, `schedules`, `instructors`, `users`, distinct school-year/semester metadata. No normal write. |
| `resources/js/pages/Attendance/Dashboard.vue` | Subject attendance dashboard and session list. | **Read:** `subjects`, `sections`, `schedules`, `attendance_sessions`, `attendances`, `online_classes`, `online_class_attendances`. No normal write. |
| `resources/js/pages/Attendance/Summary.vue` | Cumulative roster attendance summary and export entry. | **Read:** `students`, `subjects`, `sections`, `schedules`, `attendance_sessions`, `attendances`, `online_classes`, `online_class_attendances`. Export reads the same data; no normal write. |
| `resources/js/pages/Attendance/StudentHistory.vue` | One student's dated physical and online attendance history. | **Read:** `students`, `subjects`, `sections`, `schedules`, `attendance_sessions`, `attendances`, `attendance_logs`, `online_classes`, `online_class_attendances`. No normal write. |
| `resources/js/pages/Attendance/SessionDetails.vue` | Physical/online session roster, evidence, status editing, and exports. | **Read:** `students`, `subjects`, `sections`, `schedules`, `attendance_sessions`, `attendances`, `attendance_logs`, `online_classes`, `online_class_attendances`; manual corrections **write:** `attendances` or `online_class_attendances`, plus `attendance_logs`, `online_class_audit_logs`, and `activity_logs` as applicable. |

## Online Classes

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/Auth/Admin/OnlineClasses.vue` | Admin/instructor online-class CRUD and attendance access. | **Read/write/delete:** `online_classes`; **read:** `schedules`, `sections`, `subjects`, `instructors`, `students`, `system_settings`; **write:** `online_class_attachments`, `online_class_notifications`, `online_class_audit_logs`; cancellation/update may affect notification state; **indirect:** `activity_logs`. |
| `resources/js/pages/Auth/Admin/OnlineClassLogs.vue` | Online-class audit browser and export. | **Read:** `online_class_audit_logs`, `online_classes`, `users`, `sections`; no normal write. |
| `resources/js/pages/StudentParent/OnlineClasses.vue` | Student online-class listing and join workflow. | **Read:** `students`, `parent_student_links`, `sections`, `schedules`, `online_classes`, `online_class_attachments`, `online_class_attendances`, `online_class_notifications`, `system_settings`; joining **writes:** `online_class_attendances`, `online_class_audit_logs`, notification read/join state, and possibly stored face evidence metadata. |

## Registrar Pages

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/Registrar/Dashboard.vue` | Registrar enrollment overview. | **Read:** `students`, `sections`, `strands`, `users`, `instructors`, `registrar_enrollment_logs`. No normal direct write. |
| `resources/js/pages/Registrar/BiometricEnrollment.vue` | Student RFID and face enrollment. | **Read/write:** `students` RFID and face-image metadata; **read:** `sections`, `strands`; **write:** `registrar_enrollment_logs`, `activity_logs`; image files are written to storage. |
| `resources/js/pages/Registrar/InstructorFaceEnrollment.vue` | Instructor RFID and face enrollment. | **Read/write:** instructor-linked `users` and/or `instructors` RFID/face metadata; **write:** `registrar_enrollment_logs`, `activity_logs`; image files are written to storage. |
| `resources/js/pages/Rfid.vue` | Combined/legacy RFID management page. | **Read/write:** `students`, `users`; **read:** `instructors`; **indirect:** `activity_logs`. Current navigation favors Student and Instructor management/registrar enrollment. |
| `resources/js/pages/RFIDRegistration.vue` | Legacy or prototype RFID registration page; no active controller render found. | If wired, expected **read/write** `students` and/or `users`; currently **no confirmed active database workflow**. |

## Student and Parent Portal

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/StudentParent/Dashboard.vue` | Student/parent portal overview. | **Read:** `users`, `students`, `parent_student_links`, `attendances`, `online_classes`, `online_class_attendances`, `student_excuse_letters`, `student_portal_messages`, `online_class_notifications`. No normal direct write. |
| `resources/js/pages/StudentParent/Profile.vue` | Portal identity/profile and password information. | **Read/write:** permitted `users` and `students` profile fields; password actions write `users`; **read:** `sections`, `strands`, `parent_student_links`; **indirect:** `activity_logs`. |
| `resources/js/pages/StudentParent/Attendance.vue` | Own or linked-student attendance history. | **Read:** `students`, `parent_student_links`, `attendances`, `attendance_logs`, `attendance_sessions`, `schedules`, `subjects`; no normal write. |
| `resources/js/pages/StudentParent/ExcuseLetters.vue` | Student/parent excuse-letter creation, approval, attachment, generated PDF, and download. | **Read/write:** `student_excuse_letters`; **read:** `students`, `parent_student_links`, `schedules`, `instructors`, `users`; approval/delivery **writes:** `student_portal_messages`, possibly notification records, and `activity_logs`; files are written to protected storage; email is external. |
| `resources/js/pages/StudentParent/Messages.vue` | Older portal-specific messaging UI. | **Read/write:** `student_portal_messages`; **read:** `users`, `students`, `parent_student_links`, instructors; attachment files go to storage. The unified `Messages/Index.vue` is the main current Messenger. |
| `resources/js/pages/StudentParent/Notifications.vue` | Online-class/portal notifications. | **Read/write:** `online_class_notifications`; **read:** `online_classes`, `students`, `parent_student_links`; marking read updates the notification row. |
| `resources/js/pages/StudentParent/OnlineClasses.vue` | See Online Classes section above. | **Read/write:** online-class and online-attendance tables as described above. |

## Unified and Public Messaging

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/Messages/Index.vue` | Unified authenticated Messenger for non-console roles. | **Read/write:** `student_portal_messages`; **read:** `users`, optionally `students` and `parent_student_links`; read actions update message read state; attachments write metadata to the message row and files to storage; email cooldown uses cache, not necessarily a database table. |
| `resources/js/pages/Messages/Create.vue` | Public message-to-instructor form. | **Read:** `users`, `instructors`, `students`; **write:** `messages`; **indirect:** mail delivery and possibly `activity_logs`. |

## Clinic and Emergency Pages

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/Clinic/Dashboard.vue` | Clinic dashboard, emergency queue, dispatch, and assignments. | **Read:** `emergency_alerts`, `emergency_types`, `clinic_cases`, `patient_histories`, `students`, `attendances`, `users`; dispatch/status actions **write:** `emergency_alerts`, `clinic_cases`, `activity_logs`; email is external. |
| `resources/js/pages/Clinic/CaseLogs.vue` | Clinic case records. | **Read:** `clinic_cases`, `students`, `users`, `emergency_alerts`; current private notes identify this page as source-backed/read-only for manual CRUD, so no confirmed manual write from the page. |
| `resources/js/pages/Clinic/PatientHistory.vue` | Patient-history records. | **Read:** `patient_histories`, `students`, `users`, `clinic_cases`; current private notes identify this page as source-backed/read-only for manual CRUD. |
| `resources/js/pages/Clinic/EmergencyHotlines.vue` | Emergency hotline management. | **Read/write/delete:** `emergency_hotlines`; **indirect:** `activity_logs`. External SMS configuration/delivery is not a database table. |
| `resources/js/pages/Clinic/Reports.vue` | Clinic-specific reports. | **Read:** `clinic_cases`, `patient_histories`, `emergency_alerts`, `emergency_types`, `students`, `users`; exports are read-only. |

## Shared Reports

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/Reports/Index.vue` | Role-aware reports and CSV export. | **Read only, role-scoped:** `users`, `students`, `sections`, `strands`, `schedules`, `attendances`, `borrowings`, `inventory_items`, `clinic_cases`, `patient_histories`, `emergency_alerts`, `registrar_enrollment_logs`, `online_classes`, `online_class_attendances`, `student_excuse_letters`, and `student_portal_messages`. No reporting table is created or written. |

## Account Settings Pages

| Page file | Purpose | Database effect |
|---|---|---|
| `resources/js/pages/settings/Profile.vue` | Authenticated account profile editing and account deletion. | **Read/write/delete or soft-delete:** `users`; may invalidate/write `sessions`; **indirect:** `activity_logs`. |
| `resources/js/pages/settings/Password.vue` | Authenticated password change. | **Read/write:** `users`; **indirect:** `sessions`, `activity_logs`. |
| `resources/js/pages/settings/TwoFactor.vue` | Two-factor setup, confirmation, recovery codes, and disable action. | **Read/write:** two-factor fields on `users`; session confirmation state may use `sessions`; **indirect:** `activity_logs`. |
| `resources/js/pages/settings/Appearance.vue` | Client-side appearance preferences. | **None by default.** Theme preference is client-side unless a future backend preference field is added. |

## Database Tables Referenced by Pages

The page workflows above collectively affect these primary application tables:

- Authentication and accounts: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`
- Academic setup: `strands`, `sections`, `subjects`, `schedules`, `instructors`, `laboratories`
- Student identity and relationships: `students`, `parent_student_links`, `registrar_enrollment_logs`
- Physical attendance: `attendance_sessions`, `attendances`, `attendance_logs`, `rfid_panel_sessions`, `panel_devices`
- Online classes: `online_classes`, `online_class_attachments`, `online_class_attendances`, `online_class_notifications`, `online_class_audit_logs`
- Inventory and borrowing: `inventory_items`, `borrowings`, `borrowing_items`, plus legacy/compatibility `items`, `inventories`, and `transactions`
- Messaging and excuse letters: `messages`, `student_portal_messages`, `student_excuse_letters`
- Clinic and emergencies: `emergency_types`, `emergency_alerts`, `emergency_hotlines`, `clinic_cases`, `patient_histories`
- Configuration and accountability: `system_settings`, `activity_logs`

## Important Safety Notes

1. Deleting a master record can affect several pages because foreign keys may cascade, set references to null, or block deletion.
2. Student deletion is especially broad: attendance logs, portal records, parent links, online attendance, clinic links, and borrowing records use different foreign-key behaviors.
3. Schedule and subject changes can alter the labels and relationships displayed by historical attendance pages.
4. Files such as face images, attendance evidence, Messenger attachments, online-class attachments, and generated excuse-letter PDFs are stored in Laravel storage. Their metadata or paths are stored in database rows, but the file contents are not database tables.
5. External services such as AWS Rekognition, SMTP/Gmail, and an SMS provider can be triggered by page actions but are not database tables.
6. Before changing or deleting a table/relationship, verify every page listed for that table and run the focused regression suite, full backend tests, frontend build, and manual role checks.

## Coverage Statement

This map covers all 66 Vue page files found under `resources/js/pages` at the time it was created. It documents current repository behavior, including active, legacy, reusable, and currently unwired page files. The separate [Academic Year Implementation Impact Map](ACADEMIC_YEAR_IMPLEMENTATION_IMPACT.md) remains available and was not deleted or replaced.
