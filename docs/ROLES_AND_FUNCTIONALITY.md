# Roles and Functionality

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

This is the canonical reference for system roles, their responsibilities, available functionality, and access boundaries.

## Role Summary

| Role | Main purpose | Login entry |
|---|---|---|
| Root Admin | Owns the system and manages privileged administrator accounts | Secure staff login |
| Admin | Configures and supervises school operations | Secure staff login |
| Instructor | Operates assigned classes and reviews assigned students | Secure staff login |
| Registrar | Enrolls student RFID and biometric identity records | Secure staff login |
| Clinic | Responds to emergencies and maintains clinic records | Secure staff login |
| Console | Runs the physical attendance control panel | Attendance Panel login |
| Student | Uses the student self-service portal | Student/Parent login |
| Parent | Reviews and acts for linked students | Student/Parent login |

All roles except Console support email password recovery. Newly created non-Console accounts must replace their temporary password before proceeding. See [Authentication and Password Rules](AUTHENTICATION_PASSWORD_RULES.md).

## 1. Root Admin

### Purpose

The Root Admin is the highest-privilege administrator and controls system ownership.

### Functionality

- Access the complete Admin workspace.
- Create, edit, and delete Admin accounts.
- Grant or remove Root Admin status where permitted.
- Create and manage Registrar and Clinic accounts.
- Manage laboratories, devices, academic records, schedules, instructors, students, and parent links.
- Manage system settings, activity logs, reports, online-class logs, attendance records, inventory, Messenger, and emergency configuration.
- Review system-wide audit activity.

### Boundaries

- Root Admin is still subject to authentication, password, audit, and validation rules.
- The system prevents unsafe actions such as deleting the currently signed-in account.
- Root Admin does not operate the Console by bypassing its room/device PIN workflow.

### Typical workflow

1. Sign in through the secure staff login.
2. Review dashboard and activity logs.
3. Configure system-wide settings.
4. Manage privileged accounts and operational records.
5. Review reports and audit trails.

## 2. Admin

### Purpose

Admin prepares, maintains, and supervises the school data required by the other roles.

### Functionality

- View the Admin dashboard.
- Manage laboratories and their assigned attendance devices.
- Create and update strands, sections, subjects, school years, and schedules.
- Create and maintain Instructor and Student records.
- Link Parent accounts to students.
- Manage Registrar and Clinic users where authorized.
- Manage inventory, borrowing records, and item returns.
- View attendance logs and attendance evidence.
- Manage online classes and view online-class audit logs.
- Use Messenger and receive message notifications.
- Configure emergency types, hotlines, sounds, and applicable system settings.
- View and export role-appropriate reports.
- Review system activity logs.

### Boundaries

- Standard Admin cannot perform Root-Admin-only account operations.
- Admin attendance access is primarily supervisory; instructor manual attendance correction is restricted to the assigned Instructor and configured edit window.
- Laboratory information and Device access are separate. Devices own PIN, enabled/disabled state, and live panel management. See [Laboratories and Devices](LABORATORIES_AND_DEVICES.md).

### Typical workflow

1. Configure settings and school structure.
2. Create rooms, devices, users, sections, subjects, and schedules.
3. Monitor attendance, inventory, online classes, emergency activity, and reports.
4. Review logs and correct configuration problems.

## 3. Instructor

### Purpose

Instructor operates and reviews only the classes, schedules, students, and attendance assigned to their instructor profile.

### Login and verification

After staff login and any required first-password change, the Instructor completes identity verification using an available method:

- Enrolled face verification
- Email OTP
- Saved security question

### Functionality

- View assigned schedules and dashboard information.
- Start or participate in scheduled attendance sessions using Instructor RFID.
- Authorize permitted temporary student exit and return.
- Activate Dismiss Class for class-wide early checkout.
- Select Continue Class to restore normal attendance rules.
- View attendance logs and evidence for assigned sessions.
- Change assigned attendance to Present, Late, Absent, or Excused within the configured edit window.
- Add a required explanation for Excused attendance.
- Create and manage assigned online classes.
- View instructor-scoped reports.
- Use Messenger and receive approved signed excuse letters with their generated PDF attachments.
- Receive applicable email and Messenger notifications.

### Boundaries

- Instructor cannot edit attendance outside the configured `attendance.absent_default_days` period.
- Instructor cannot edit another instructor's session.
- Every manual attendance correction is audited.
- Instructor sees only assigned/scoped academic and attendance information.
- Instructor is not responsible for student biometric enrollment or system-wide master data.

Exact attendance behavior is defined in [Attendance Control Panel Tapping Rules](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md).

### Typical workflow

1. Sign in and complete Instructor verification.
2. Review assigned schedules.
3. Start or authorize the scheduled attendance session.
4. Handle temporary movement, checkout, or Dismiss Class as necessary.
5. Review attendance and make permitted logged corrections.
6. Manage assigned online classes and respond through Messenger.

## 4. Registrar

### Purpose

Registrar manages student identity enrollment used by RFID and biometric attendance verification.

### Functionality

- View the Registrar dashboard.
- Open Student Biometric Enrollment.
- Search and select Student records.
- Enroll or update Student RFID tags.
- Capture and upload Student face images.
- Remove incorrect Student face images.
- Update applicable Student enrollment status.
- Produce registrar enrollment audit records.
- Use shared Messenger and reports where permitted.

### Boundaries

- Biometric Enrollment is student-only.
- Registrar does not enroll Instructor biometrics from the student enrollment page.
- Registrar does not manage schedules, attendance outcomes, system settings, or Device PINs.
- Enrollment actions are audited.

### Typical workflow

1. Sign in through the secure staff login.
2. Search for the Student.
3. Verify the Student identity and active record.
4. Enroll RFID and required face images.
5. Confirm the enrollment result and audit entry.

## 5. Clinic

### Purpose

Clinic receives school emergency alerts, dispatches internal clinic responses, and maintains health-related case and patient records.

### Functionality

- View Clinic dashboard counts and active emergency alerts.
- Receive a configured emergency sound for newly received alerts after browser audio is enabled.
- Acknowledge, resolve, or cancel emergency alerts.
- Dispatch an internal clinic response.
- Automatically create or update a monitoring Clinic Case during dispatch.
- Identify a Student from alert metadata or RFID when available.
- Manage Clinic Case Logs.
- Create and maintain Patient History.
- Manage emergency hotlines and emergency types where permitted.
- View Clinic reports and export CSV data.
- Use Messenger.

### Dispatch behavior

Dispatch:

1. Changes the alert from Open to Acknowledged.
2. Identifies the patient when possible.
3. Creates or updates a linked Clinic Case.
4. Requires the dispatcher to select an active Clinic responder.
5. Assigns the selected Clinic account as the case handler.
6. Emails the responder the dispatch location, patient details, and available recent Clinic and attendance history.
7. Shows the case in the responder's **My Dispatch Assignments** panel.
8. Records the assignment and sets the case to Monitoring.

The exact assignment and notification rules are defined in [Clinic Dispatch Assignment](CLINIC_DISPATCH.md).
6. Writes an activity log.
7. Removes the alert from the active Open queue.

Dispatch is an internal workflow. It does not automatically call emergency services. Live SMS depends on a configured hotline and working external SMS provider.

### Boundaries

- Clinic should not treat Dispatch as confirmation that an ambulance, police unit, or external responder was contacted.
- Clinic does not manage academic master data, Student biometric enrollment, or attendance Device PINs.
- Sensitive clinic information remains role-protected.

### Typical workflow

1. Sign in and enable browser audio.
2. Review new emergency details.
3. Dispatch, ignore, or update the alert appropriately.
4. Continue treatment documentation in Case Logs.
5. Create Patient History when required.
6. Resolve the alert and review reports.

## 6. Console

### Purpose

Console is the restricted account used by the physical Attendance Control Panel.

### Functionality

- Select a Laboratory/room.
- Authenticate using the assigned Device PIN.
- Open and maintain the room's live panel session.
- Receive Instructor RFID and Student RFID taps.
- Perform face verification and permitted fallbacks.
- Record Check-in, Temporary Exit, Temporary Return, Check-out, Invalid Tap, and Ignored Tap events.
- Support Dismiss Class and Continue Class.
- Submit room-aware emergency alerts.
- Operate enabled borrowing functions where configured.

### Boundaries

- Console does not use email password recovery.
- Console does not access Admin, Instructor, Registrar, Clinic, Student, or Parent pages.
- A disabled assigned Device blocks panel login.
- Console cannot change Device PINs; Admin manages them.
- Tap behavior must follow the canonical attendance rules.

See [Attendance Control Panel Tapping Rules](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md) and [Laboratories and Devices](LABORATORIES_AND_DEVICES.md).

### Typical workflow

1. Open Attendance Panel login.
2. Select the Laboratory.
3. Enter the assigned Device PIN.
4. Wait for the assigned Instructor to start the scheduled session.
5. Process verified Instructor and Student taps.
6. Close or remotely end the panel session when finished.

## 7. Student

### Purpose

Student uses the self-service portal to view personal school information and communicate with authorized users.

### Functionality

- View personal dashboard and attendance history.
- Filter personal attendance records.
- View and join available online classes.
- Submit an excuse letter.
- Upload an optional excuse-letter PDF, DOCX, or supporting file.
- Select suggested assigned Instructor recipients.
- Wait for linked Parent review and signature.
- Download approved generated excuse-letter documents.
- Use Messenger and receive message notifications.
- Update permitted profile and password information.

### Boundaries

- Student sees only their own records.
- Student-created excuse letters require linked Parent approval before final Instructor delivery.
- Student cannot change official attendance.
- Student cannot access staff, Clinic, Registrar, Admin, or Console functions.

### Typical workflow

1. Sign in through the Student/Parent portal.
2. Review dashboard, attendance, and online classes.
3. Submit excuse letters when needed.
4. Communicate through Messenger.
5. Monitor notifications and approved documents.

## 8. Parent

### Purpose

Parent reviews authorized information and performs approval actions for linked Students.

### Functionality

- View linked Student dashboards and attendance.
- Switch between linked Students when more than one child is assigned.
- Review Student-created excuse letters.
- Add a signature and approval notes.
- Approve the excuse letter for Instructor delivery.
- Create a Parent-signed excuse letter directly.
- Upload supporting files.
- Download approved generated excuse-letter PDFs.
- Use Messenger and receive notifications.
- Update permitted profile and password information.

### Boundaries

- Parent sees only explicitly linked Students.
- Parent approval does not directly change attendance status.
- Parent cannot access Admin, Instructor, Registrar, Clinic, or Console tools.
- Approved letters are sent only to selected or schedule-assigned Instructors.

### Typical workflow

1. Sign in through the Student/Parent portal.
2. Select the linked Student.
3. Review attendance and pending excuse letters.
4. Sign and approve a Student-created letter or create a Parent letter.
5. Communicate with staff through Messenger.

## Shared Functionality

### Messenger

Admin, Instructor, Registrar, Clinic, Student, and Parent can use the authenticated Messenger. Recipient search supports autosuggestion and closes after selection. Messages can include attachments. Email notification is rate-limited so repeated messages from the same sender do not generate an email for every spam-like message.

Console does not use Messenger.

### Reports

Reports are role-scoped:

- Admin: system-wide operational reporting.
- Instructor: assigned schedules, students, attendance, and online classes.
- Clinic: alerts, cases, patient-related operational summaries.
- Student: personal attendance and portal activity.
- Parent: linked Student data.
- Registrar: permitted enrollment/reporting views.

### Audit logging

Important mutating operations are recorded through system activity logs or module-specific logs. Attendance, online classes, registrar enrollment, emergency dispatch, Messenger attachments, and excuse-letter actions retain accountability data appropriate to the workflow.

## Access-Denied Principle

A user must never receive a feature merely because they know its URL. Authentication middleware, role middleware, scope queries, first-login password enforcement, Instructor verification, and Device PIN validation enforce access on the server.
