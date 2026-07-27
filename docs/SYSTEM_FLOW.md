# System Flow

This document is the detailed operating flow for the RFID Borrowing and Attendance System. It connects the user roles, setup records, attendance panel, registrar enrollment, student/parent portal, clinic workflows, reports, and audit records into one readable sequence.

## Whole-System Flow Overview

The system works as a role-based Laravel and Inertia application. Users interact with Vue pages in the browser, requests pass through Laravel web routes and middleware, controllers validate permissions and data, Eloquent models write to the database, and supporting services handle face recognition, notifications, reports, file storage, and audit logging.

```mermaid
flowchart TD
    Browser[Browser / RFID Panel UI] --> Inertia[Vue 3 Inertia Pages]
    Inertia --> Routes[Laravel Web Routes]
    Routes --> Middleware[Auth, Role, Instructor Verification]
    Middleware --> Controllers[Controllers]
    Controllers --> Models[Eloquent Models]
    Models --> DB[(MySQL Database)]
    Controllers --> Storage[Public/Protected File Storage]
    Controllers --> Services[Application Services]
    Services --> Face[AWS Rekognition When Configured]
    Services --> Mail[Mail / Notification Attempts]
```

### Main User Journey

1. Admin prepares the school structure: laboratories, strands, sections, subjects, instructors, students, users, schedules, inventory, and settings.
2. Registrar enrolls RFID cards and face images for students and instructors.
3. Console user opens the attendance panel and selects the correct room.
4. Instructor taps RFID and starts the scheduled class session.
5. Student verifies face or receives instructor-approved fallback, then taps RFID.
6. Attendance records and tap logs are written with status, time, room, validation, and evidence details.
7. Students and parents view attendance, online classes, messages, notifications, and excuse letters from the portal.
8. Clinic monitors emergency alerts and manages case logs, patient histories, hotlines, and reports.
9. Admin, instructor, registrar, and clinic users review role-aware reports and CSV exports.
10. Activity logs, attendance logs, registrar logs, and online class audit logs preserve accountability.

## Role Entry And Redirect Flow

The public root route `/` is the student/parent login page for guests. Authenticated users are redirected by role.

```mermaid
flowchart TD
    Guest[Visitor opens /] --> Login[Student/Parent Login Page]
    Auth[Authenticated User opens / or /dashboard] --> Role{User role}
    Role -->|admin or instructor| AdminDash[/admin/dashboard]
    Role -->|clinic| ClinicDash[/clinic/dashboard]
    Role -->|console| Panel[/attendance-control-panel]
    Role -->|registrar| RegistrarDash[/registrar/dashboard]
    Role -->|student or parent| Portal[/student-parent/dashboard]
    Role -->|unknown| Login
```

Role access is controlled mostly through route middleware:

- `admin`: broad setup and management.
- `instructor`: scoped class, student, online-class, attendance-log, report, and messaging access after instructor verification.
- `registrar`: RFID and face enrollment.
- `console`: attendance panel only.
- `student` and `parent`: portal self-service.
- `clinic`: clinic dashboard, emergency, case, hotline, patient-history, and clinic report access.
- `root admin`: admin account management privileges on top of admin access.

## Setup Dependency Flow

The system has important setup dependencies. Creating records in this order prevents missing dropdowns, failed schedule creation, and failed attendance scans.

```mermaid
flowchart LR
    Settings[System Settings] --> Labs[Laboratories / Rooms]
    Settings --> Users[Staff Users]
    Labs --> Schedules[Class Schedules]
    Strands[Strands] --> Sections[Sections]
    Sections --> Students[Students]
    Sections --> Subjects[Subjects]
    Subjects --> Schedules
    Users --> Instructors[Instructor Profiles]
    Instructors --> Schedules
    Students --> Enrollment[Registrar RFID / Face Enrollment]
    Instructors --> Enrollment
    Schedules --> Attendance[Attendance Panel Operation]
    Enrollment --> Attendance
```

Recommended setup order:

1. Review `/admin/settings`.
2. Create laboratories or rooms from `/admin/active-devices` or `/admin/laboratories`.
3. Create strands from `/admin/strands`.
4. Create sections from `/admin/sections`.
5. Create subjects from `/admin/subjects`.
6. Create staff users from `/admin/users`.
7. Create instructor profiles from `/admin/instructors`.
8. Create student records from `/admin/students`.
9. Link parent accounts from the student management page.
10. Enroll student and instructor RFID/face records from registrar pages.
11. Create schedules from `/admin/schedules`.
12. Prepare inventory from `/admin/inventory`.
13. Open the attendance panel and start daily operation.

Important limitation: schedule creation validates fields, but the current source does not automatically block overlapping room, instructor, section, or time assignments.

## Data Flow By Layer

| Layer | Main Files Or Tables | Responsibility |
| --- | --- | --- |
| Browser UI | `resources/js/pages` | Role dashboards, forms, scanner/panel screens, portal pages, reports, messages. |
| Routing | `routes/web.php` | Public, authenticated, role-specific, and console endpoints. |
| Controllers | `app/Http/Controllers` | Validation, authorization checks, workflow decisions, database writes, response payloads. |
| Models | `app/Models` | Eloquent access to users, students, schedules, attendance, inventory, clinic, reports, and messages. |
| Database | `database/migrations` | Permanent records for operations and audit trails. |
| Seeders | `database/seeders` | Demo users, academic data, students, parent links, clinic data, emergency data, and messages. |
| Services | `app/Services` | Face recognition, online class audit, notifications, and related business services. |
| Storage | Laravel disks | Face images, attendance evidence, message attachments, excuse-letter attachments, and generated files. |

## Core Database Flow

The operational database centers on these record chains:

```mermaid
flowchart TD
    User[users] --> Instructor[instructors]
    User --> ParentLink[parent_student_links]
    Strand[strands] --> Section[sections]
    Section --> Student[students]
    Section --> Subject[subjects]
    Subject --> Schedule[schedules]
    Instructor --> Schedule
    Lab[laboratories] --> Schedule
    Student --> Attendance[attendances]
    Schedule --> Attendance
    Attendance --> Log[attendance_logs]
    PanelSession[attendance_sessions] --> Log
    Student --> Excuse[student_excuse_letters]
    User --> Messenger[student_portal_messages]
    Schedule --> Online[online_classes]
    Online --> OnlineAttendance[online_class_attendances]
    EmergencyType[emergency_types] --> Alert[emergency_alerts]
    Alert --> Case[clinic_cases]
    Case --> History[patient_histories]
```

The main attendance result is stored in `attendances`. Every RFID tap or validation event is stored separately in `attendance_logs`. This separation lets the system show one final attendance status while still preserving the full tap history.

## Daily Operations Flow

```mermaid
flowchart TD
    AdminSetup[Admin setup complete] --> RegistrarEnrollment[Registrar RFID and face enrollment]
    RegistrarEnrollment --> ConsoleRoom[Console selects room]
    ConsoleRoom --> InstructorStart[Instructor RFID starts schedule session]
    InstructorStart --> StudentVerify[Student face verification or fallback]
    StudentVerify --> StudentTap[Student RFID tap]
    StudentTap --> AttendanceWrite[Attendance and tap log saved]
    AttendanceWrite --> Review[Admin/Instructor attendance logs]
    AttendanceWrite --> Portal[Student/Parent attendance history]
    Review --> Reports[Role-aware reports and CSV export]
```

Daily success depends on four matching conditions:

- The panel room must match the scheduled laboratory or room.
- The instructor RFID must belong to the instructor assigned to the active schedule.
- The student RFID must belong to a student in the scheduled section.
- The tap must happen within the active schedule rules and verification requirements.

## Admin Student And Parent Account Flow

Page names:

- `Auth/Admin/Students.vue` - admin and instructor student management page.

Routes:

- `/admin/students` - opens Student Management.
- `POST /admin/students/{id}/parents` - creates or links a parent portal account to a student.
- `PUT /admin/students/{id}/parents/{parent}` - updates a linked parent account and relationship label.
- `DELETE /admin/students/{id}/parents/{parent}` - unlinks a parent account from the student.

### 1. Student List

1. Admins open `/admin/students`.
2. The page loads each student with section, strand, RFID, face image count, status, and linked parent accounts.
3. Instructors can view scoped student records only for handled sections.
4. Only admins can create, update, delete, or manage parent links.
5. Student add/edit uses a School Year dropdown with `2025-2026` through `2030-2031`, plus any existing saved school-year values.

### 2. Parent Account Management

1. An admin selects the `Parents` action on a student row.
2. The modal lists linked parent portal accounts with name, email, relationship, and phone.
3. When the submitted email is new, the system creates a `users` row with role `parent` and requires a password.
4. When the submitted email already belongs to a parent account, the system links that existing account to the selected student and can update the parent profile fields.
5. Emails that belong to non-parent users cannot be linked as parent accounts.
6. The parent-student relationship label is saved on `parent_student_links.relationship`.
7. Unlink removes only the student association. The parent user account remains available for other linked students.

### 3. Section And Schedule School-Year Selection

1. Admin Section add/edit uses the same School Year dropdown: `2025-2026` through `2030-2031`, plus existing saved values.
2. Subject and Schedule add/edit modals do not store school year directly.
3. Subject and Schedule modals display section choices with section name, grade, and school year so admins can select the intended academic year.

## Student/Parent Excuse Letter Flow

Page names:

- `StudentParent/ExcuseLetters.vue` - student and parent excuse letter submission, approval, and download page.

Routes:

- `/student-parent/excuse-letters` - opens the excuse letter page and stores new letters.
- `/student-parent/excuse-letters/{letter}/approve` - linked parent approval endpoint for student-created letters.
- `/student-parent/excuse-letters/{letter}/download` - downloads the approved generated PDF.

### 1. Student-Created Letter

1. A student submits an excuse letter for their own student record.
2. The letter is saved with status `pending_parent_approval`.
3. PDF download is blocked while parent approval is pending.
4. A linked parent opens the same student's Excuse Letter page.
5. The parent enters a typed parent signature and optional notes, then approves the letter.
6. The letter status becomes `approved`, and the parent signature, approver, and approval timestamp are stored.
7. The student or linked parent can download the generated `.pdf`.

### 2. Parent-Created Letter

1. A parent selects the linked student and submits an excuse letter.
2. Parent signature is required on submission.
3. The letter is saved immediately as `approved` with the parent signature and approval timestamp.
4. The generated `.pdf` includes the letter content and parent approval block.

### 3. Excuse Letter Attachments

1. A student or parent can upload an optional attachment when submitting an excuse letter.
2. The attachment path and original file name are saved on the letter record.
3. The attachment link uses an authenticated download route.
4. Only the student or linked parent for the selected student can download the attachment.

## Authenticated Messenger Flow

Page names:

- `Messages/Index.vue` - unified messenger for all authenticated roles.

Routes:

- `/messages` - unified messenger inbox.
- `/messages/conversation` - sends a new chat message.
- `/messages/{message}/read` - marks a received message as read.
- `/messages/{message}/attachment` - downloads a message attachment when the current user is sender or recipient.
- `/student-parent/messages` - compatibility route that opens the same messenger for student and parent accounts.
- `/admin/messages` - compatibility route that opens the same messenger for admin and instructor accounts.

### 1. User Search And Conversation Start

1. Any authenticated admin, instructor, clinic, registrar, student, or parent opens Messenger.
2. The page loads searchable recipient options from active user accounts except the current user.
3. The user searches by name, email, or role.
4. Selecting a user opens the existing conversation when previous messages exist, showing both sender and recipient history in the same chat room.
5. If no previous conversation exists, selecting a user starts a new conversation draft.
6. Parent accounts keep the selected-student context when a linked student is selected.

### 2. Chat Messages

1. The sender enters a message body and optional attachment.
2. The message is saved in `student_portal_messages` with sender, recipient, sender role, optional student context, encrypted subject/body, and attachment metadata.
3. Conversation lists group messages by the other user so both your sent messages and the other user's replies appear in one room.
4. The chat view displays messages as sender/recipient bubbles newest conversation first and thread messages oldest to newest.
5. A received message can be marked as read only by its recipient.

### 3. Message Attachments

1. Messenger accepts PDF, Word, image, text, and web image attachment types up to the configured upload limit.
2. Attachments are stored on the public disk path but exposed through an authorized download route.
3. Only the message sender or recipient can download the attachment.

## Shared Reporting Flow

Page names:

- `Reports/Index.vue` - shared report dashboard for admin, clinic, registrar, and instructor users.

Routes:

- `/reports` - opens the role-aware report dashboard.
- `/reports/export` - downloads the currently filtered report rows as CSV.
- `/clinic/reports` - keeps the existing clinic-specific report page for compatibility.

### 1. Report Access

1. Admin, clinic, registrar, and instructor users can open Reports from the authenticated sidebar.
2. The shared page resolves the current user's role and returns only the report groups for that role.
3. Student, parent, and console users do not have access to the shared reporting route.

### 2. Role-Specific Report Data

1. Admin reports show system-wide users, students, attendance, borrowing, inventory, and clinic case counts.
2. Clinic reports show clinic cases, patient histories, emergency alerts, statuses, severities, and case types.
3. Registrar reports show student totals, active students, sections, strands, and enrollment log activity.
4. Instructor reports are scoped to the signed-in instructor profile and show assigned schedules, handled sections, attendance, and online class activity.

### 3. Filters, Charts, And Download

1. The user can apply `date_from` and `date_to` filters.
2. Summary cards and chart rows refresh from the filtered dataset.
3. Charts are rendered on the page from real database counts.
4. The Download CSV button exports the same filtered report rows shown in the detail table.

## Attendance Panel Flow

Page names:

- `AttendancePanelLogin.vue` - attendance panel login page.
- `AttendanceControlPanel.vue` - live RFID attendance panel used by the console account.
- `AttendanceLogs.vue` - admin and instructor attendance log page.
- `AttendanceScanner.vue` - admin and instructor RFID attendance scanner/demo page.
- `Auth/Admin/ActiveDevices.vue` - combined admin Laboratories & Devices page with laboratory management, panel login access, and active panel monitoring.

Routes:

- `/attendance-control-panel/login` - opens the panel login page.
- `/attendance-control-panel` - opens the live attendance control panel.
- `/admin/attendance/logs` - opens the admin/instructor attendance logs.
- `/admin/attendance/scanner` - opens the admin/instructor scanner page.
- `/admin/active-devices` - opens the combined Laboratories & Devices page.

### 1. Panel Login

1. A console user opens `/attendance-control-panel/login`.
2. The user selects the active room or laboratory first.
3. The user enters the PIN for the selected room.
4. If the selected room maps to an existing panel with its own saved PIN, that panel-specific PIN is checked.
5. If no panel-specific PIN exists, the global default panel PIN is checked.
6. After successful verification, the system opens `/attendance-control-panel`.

### 2. Instructor Starts Attendance

1. The panel waits for an instructor RFID tap.
2. The instructor taps their RFID card.
3. The system looks up the instructor and checks the current schedule for the selected room.
4. If a valid schedule is found, the panel starts attendance mode.
5. The active instructor, subject, section, room, schedule start time, and schedule end time are stored in the panel session.

### 3. Student Check-in

1. A student taps their RFID card.
2. The system verifies that the RFID belongs to a student.
3. The system checks that the student belongs to the active class section.
4. If face recognition is enabled, the student face verification or instructor override flow must pass before attendance is recorded.
5. If the student has no attendance record yet for the same student, schedule, and date, the tap becomes the official check-in.
6. If the check-in happens within the scheduled start time plus 15 minutes, the check-in status is on time.
7. If the check-in happens more than 15 minutes after the scheduled start time, the check-in status is late.
8. The student room status becomes `Inside`.
9. The main attendance status displays as `Pending` until the student completes official check-out or the session ends without checkout.

### 4. Temporary Exit And Return

1. After check-in, a student may tap again before the checkout window.
2. The checkout window starts 15 minutes before the scheduled class end time.
3. Taps before that checkout window do not end attendance automatically.
4. The panel asks for the active instructor RFID before saving a temporary movement.
5. If the instructor RFID is valid and the student is currently `Inside`, the tap is recorded as `Temporary Exit`.
6. If the instructor RFID is valid and the student is currently `Outside`, the tap is recorded as `Temporary Return`.
7. Temporary taps continue alternating between exit and return.
8. Temporary taps are kept as audit records and do not change the final attendance status by themselves.
9. If the instructor RFID is not provided or does not match the active class instructor, the temporary movement is not saved.

### 5. Official Check-out

1. The official checkout window starts 15 minutes before the scheduled end time.
2. During this final 15-minute window, Temporary Exit and Temporary Return are disabled.
3. The first valid student tap inside the checkout window becomes the official logout/check-out.
4. The attendance record saves the check-out time.
5. The student room status becomes `Outside`.
6. If the student checked in on time and checked out, final status becomes `Present`.
7. If the student checked in late and checked out, final status becomes `Late`.

### 6. Instructor Student Logout Override

1. During a live attendance session, the active instructor may tap their RFID again.
2. The panel opens instructor session actions.
3. If the instructor chooses `Student Logout`, the panel enters Student Logout Mode.
4. The next student RFID tap is recorded as official `Check-out` even if the normal checkout window has not started.
5. This is used when the instructor wants to log the student out of the class instead of recording a `Temporary Exit`.
6. Student Logout Mode is one-shot: after the next student tap, the panel returns to normal attendance tap rules.

### 7. Ignored Taps

1. If a student taps again after official check-out, the tap is recorded as `Ignored Tap`.
2. Ignored taps do not change check-in, check-out, room status, or final attendance status.
3. This prevents duplicate checkout records for the same class.

### 8. Incomplete And Absent Attendance

1. If a student checked in but did not complete official check-out, the status remains `Pending` while the session is still active.
2. After the session or allowed attendance period ends, a checked-in student without official check-out is treated as `Incomplete Attendance`.
3. This applies even if the student has Temporary Exit or Temporary Return records.
4. If a student has no valid check-in tap for the scheduled class, the admin/instructor logs show the student as `Absent` after the attendance period ends.

### 9. Attendance Records And Tap Logs

Main attendance record:

- One main attendance row is kept per student, schedule, and date.
- It stores student ID, schedule ID, attendance date, scheduled start and end time, check-in time, check-out time, check-in status, final status, room status, total taps, remarks, created date, and updated date.

Tap log records:

- Each tap creates a separate tap log row.
- Tap logs store attendance ID, student ID, schedule ID, tap date/time, tap type, tap sequence number, device/scanner ID, room/location, validation result, and remarks.
- Tap types include `Check-in`, `Temporary Exit`, `Temporary Return`, `Check-out`, `Ignored Tap`, and `Invalid Tap`.

### 10. Admin And Instructor Attendance Views

`Auth/Admin/ActiveDevices.vue`:

- Admin users manage laboratory records and monitor active attendance panel devices from one Laboratories & Devices sidebar entry.
- The page includes the only sidebar-reachable Panel Login link, default panel device label/PIN settings, per-existing-panel PIN changes, active/waiting device counts, and forced panel logout controls.
- The global panel PIN remains the default/fallback PIN. When an existing panel has its own saved PIN, panel login verifies that selected room against the panel-specific PIN instead.

`AttendanceLogs.vue`:

- Admin users can view attendance logs across instructors.
- Instructor users can view attendance logs only for their assigned classes.
- The page displays subject, date, instructor, student, tap type, tap sequence, check-in, check-out, room status, and attendance status.
- Admin users can filter by instructor or scan instructor RFID to filter logs.

`AttendanceScanner.vue`:

- Admin and instructor users can view the current schedule context and recent attendance scans.
- Instructor users are scoped to their assigned schedules and handled sections.
- The scanner page is useful for checking recent RFID attendance activity outside the full live panel.

`AttendanceControlPanel.vue`:

- Console users use this page for live class attendance.
- The page displays the active instructor, student tap result, temporary movements, official check-out, current room status, and final attendance status.

### 11. Final Attendance Status Priority

1. No valid check-in after the attendance period ends: `Absent`.
2. Valid check-in but no official check-out after the session ends: `Incomplete Attendance`.
3. Valid check-in and valid check-out within the flow: `Present` or `Late`.
4. Active attendance with check-in but no check-out yet: `Pending`.
5. Temporary Exit and Temporary Return records do not override the final status.

### 12. Attendance Verification Grant Flow

The attendance panel uses a two-step student validation flow so a face check or fallback approval cannot be reused indefinitely.

```mermaid
sequenceDiagram
    participant S as Student
    participant P as Console Panel
    participant A as AttendanceController
    participant F as Face Service
    participant DB as Database
    S->>P: Camera capture / fallback request
    P->>A: Student face check
    A->>F: Compare captured face when enabled
    F-->>A: Match, no match, or provider unavailable
    A->>DB: Store evidence when available
    A-->>P: Create short-lived one-use grant if approved
    S->>P: RFID tap
    P->>A: Record student tap
    A->>A: Consume grant
    A->>DB: Save attendance and attendance_log
    A-->>P: Return status/result
```

Grant outcomes:

- `face_verified`: AWS Rekognition matched the captured face to an enrolled reference image.
- `instructor_override`: active instructor approved a fallback or exception.
- `face_disabled`: face recognition is disabled in settings, so RFID can continue through the allowed path.
- `missing_reference`: no student face reference exists, so instructor approval is needed when configured.
- `provider_unavailable`: captured evidence may be stored, but the final action depends on fallback rules.

The grant is consumed by the next valid student tap. This prevents one verification from being reused for multiple RFID scans.

### 13. Invalid Or Blocked Attendance Paths

The panel should reject or log taps without changing official attendance when the business rules are not satisfied.

| Situation | System behavior |
| --- | --- |
| No room selected | Panel asks the console user to select a room before attendance can start. |
| Instructor RFID not found | Session is not started; the tap is treated as invalid. |
| No active schedule | Panel reports that no matching class schedule is active for the selected room/instructor/time. |
| Student RFID not found | No attendance row is created; invalid tap can be logged. |
| Student not in scheduled section | Attendance is rejected because the student is outside the active class roster. |
| Student has no verification grant | Attendance write is blocked until face check or approved fallback succeeds. |
| Temporary exit without instructor approval | Temporary movement is rejected. |
| Duplicate tap after official checkout | Tap is logged as `Ignored Tap`; final attendance does not change. |

## Registrar Enrollment Flow

Registrar enrollment connects physical identifiers to system records.

```mermaid
flowchart TD
    Registrar[Registrar Login] --> StudentEnroll[/registrar/biometric-enrollment]
    Registrar --> FacultyEnroll[/registrar/instructor-face-enrollment]
    StudentEnroll --> StudentRfid[Assign student RFID]
    StudentEnroll --> StudentFace[Upload/capture student face images]
    FacultyEnroll --> FacultyRfid[Assign instructor RFID]
    FacultyEnroll --> FacultyFace[Upload/capture instructor face images]
    StudentRfid --> AttendanceReady[Student ready for panel scan]
    StudentFace --> FaceReady[Student ready for face verification]
    FacultyRfid --> SessionReady[Instructor ready to start/approve sessions]
    FacultyFace --> InstructorVerify[Instructor face verification available]
```

Enrollment outputs:

- Student RFID is used for attendance taps.
- Student face images are reference images for attendance verification.
- Instructor RFID starts attendance sessions and approves temporary movements/fallbacks.
- Instructor face images support instructor verification and face-based instructor checks.
- Registrar enrollment actions are available to reporting/audit flows through enrollment logs.

## Online Class Flow

```mermaid
flowchart TD
    Instructor[Instructor or Admin] --> CreateOnline[Create online class]
    CreateOnline --> ScheduleLink[Link to schedule, section, instructor]
    ScheduleLink --> Notify[Create in-app notifications / email attempts]
    Notify --> StudentPortal[Student sees class in portal]
    StudentPortal --> Join[Student joins meeting link]
    Join --> FaceRequired{Face required?}
    FaceRequired -->|Yes| OnlineFace[Verify face or handle fallback]
    FaceRequired -->|No| OnlineAttendance[Record online attendance]
    OnlineFace --> OnlineAttendance
    OnlineAttendance --> Audit[Online class audit log]
```

Online class records are connected to schedules, instructors, sections, attendance rows, notifications, attachments, and audit logs. The actual video meeting is external through the meeting link; the system records class metadata, join activity, attendance state, and notification/audit history.

## Student And Parent Portal Handoff

The portal reads official records created by admin, registrar, attendance, online-class, messenger, and excuse-letter workflows.

```mermaid
flowchart TD
    Attendance[Physical attendance records] --> PortalAttendance[Attendance history]
    Online[Online classes] --> PortalOnline[Online class list and join flow]
    StudentLetter[Student excuse letter] --> ParentApproval[Parent approval]
    ParentLetter[Parent-created excuse letter] --> ApprovedLetter[Approved generated letter]
    Messenger[Unified Messenger] --> PortalMessages[Portal messages]
    Notifications[Online class notifications] --> PortalNotifications[Notifications page]
```

Student portal visibility:

- Student users see their own linked student record.
- Parent users see records for linked students through `parent_student_links`.
- Attendance evidence routes require authorization before images are shown.
- Student-created excuse letters remain pending until a linked parent approves them.
- Parent-created excuse letters are saved as approved because the parent signs during creation.

## Clinic And Emergency Handoff

Emergency alerts can originate from the console attendance panel and are handled from the clinic module.

```mermaid
sequenceDiagram
    participant P as Attendance Panel
    participant E as EmergencyController
    participant DB as Database
    participant C as Clinic User
    P->>E: Submit emergency alert
    E->>DB: Save emergency_alert
    C->>DB: View alert on clinic dashboard
    C->>E: Update alert status / dispatch
    E->>DB: Save status and dispatch metadata
    C->>DB: Create clinic case if treatment occurs
    C->>DB: Create patient history when needed
```

Clinic outputs:

- `emergency_alerts` track alert details and status.
- `clinic_cases` track treatment or clinic response records.
- `patient_histories` preserve longer-term medical history.
- `emergency_hotlines` and `emergency_types` support clinic configuration.
- Clinic reports aggregate alerts, cases, patient histories, response states, and severity/case-type data.

Live SMS or external dispatch should be treated as provider-dependent unless the deployed environment confirms a working integration.

## Borrowing And Inventory Flow

```mermaid
flowchart LR
    Admin[Admin] --> Inventory[Maintain inventory items]
    Inventory --> Borrow[Create borrowing transaction]
    Borrow --> BorrowItems[Attach borrowed item lines]
    BorrowItems --> Return[Return borrowed items]
    Return --> Availability[Update availability/status]
    Borrow --> Reports[Borrowing and inventory reports]
```

Borrowing can involve student or instructor/user borrowers. The current inventory and borrowing flow is mainly administrative, while the attendance panel also exposes a borrowing-only action path for console-side workflows.

## Reporting And Audit Flow

```mermaid
flowchart TD
    Role[Admin / Clinic / Registrar / Instructor] --> Reports[/reports]
    Reports --> Scope{Role scope}
    Scope --> AdminData[System-wide admin aggregates]
    Scope --> ClinicData[Clinic and emergency aggregates]
    Scope --> RegistrarData[Enrollment and student aggregates]
    Scope --> InstructorData[Assigned schedule and attendance aggregates]
    Reports --> Csv[/reports/export CSV]
    Activity[Activity logs] --> Accountability[Accountability review]
    AttendanceLogs[Attendance logs] --> Accountability
    OnlineLogs[Online class audit logs] --> Accountability
    RegistrarLogs[Registrar enrollment logs] --> Accountability
```

Report data is read from existing operational tables. The shared report module does not create a separate reporting database table. CSV export returns the same filtered rows shown in the report view.

## End-To-End Demo Flow

For a complete demonstration, use this sequence:

1. Admin shows dashboard and settings.
2. Admin shows laboratories, strands, sections, subjects, instructors, students, and parent links.
3. Registrar shows student and instructor RFID/face enrollment.
4. Admin shows schedule setup and notes the overlap limitation.
5. Console selects a room and opens the attendance panel.
6. Instructor taps RFID and starts the active class.
7. Student completes face/fallback verification and taps RFID for check-in.
8. Instructor approves temporary exit/return or Student Logout when needed.
9. Student checks out during the final checkout window.
10. Admin or instructor opens attendance logs and evidence.
11. Student or parent opens portal attendance history.
12. Student creates an excuse letter and parent approves it.
13. Instructor/admin creates an online class and student joins it.
14. Student and instructor exchange a Messenger conversation.
15. Console submits an emergency alert and clinic handles it.
16. Admin/clinic/registrar/instructor opens reports and exports CSV.

## Operational Caveats

- RFID attendance depends on correct schedule, room, instructor, student section, and enrollment data.
- Face verification depends on enrolled face images and provider configuration.
- Attendance evidence images are separate from registrar reference images.
- Temporary movement records are audit records; they do not decide the final status.
- Schedule overlap prevention is recommended but not currently enforced.
- Attendance correction with approval/audit is recommended for real deployments but is not a dedicated workflow in the current source.
- Reports export CSV files, not native `.xlsx` files.
- Private notes, credentials, and `.env` secrets should stay outside public documentation.
