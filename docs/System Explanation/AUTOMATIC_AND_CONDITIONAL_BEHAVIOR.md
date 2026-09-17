# Automatic and Conditional Behavior

This guide explains functions that are easy to miss because they happen in the background, appear only for certain people, or depend on a setting or record state.

## Feature switches

Admin controls the switches on **System Settings**. The application shares their values with every signed-in page.

| Switch | Visible effect | Server behavior and limits |
| --- | --- | --- |
| Borrowing | Shows Borrowing in the Admin menu and borrowing mode on the panel. | Disabled requests are rejected; the Admin page redirects to Settings. |
| Inventory | Shows Inventory in the Admin menu. | The main Inventory page redirects to Settings when disabled. |
| Parent Portal | Shows the Parent option on the public login. | Parent login and parent-protected routes are rejected when off. Students remain able to use their own portal. |
| Parent Excuse Letters | Shows parent creation/approval controls only while Parent Portal is also on. | A parent cannot submit or approve when disabled. Turning off Parent Portal also stores this setting as off. |
| Face Recognition | Enables attendance and online-class face checks when AWS Rekognition is configured. | Settings automatically keep it off when the provider is unavailable. Attendance can use documented instructor fallback paths. |
| Demo Attendance Panel | Shows configured demonstration tap buttons and sample RFID values. | Intended for demonstrations, not production. |
| Online Classes | Hides the staff Online Classes and Admin Online Class Logs menu links. | Current implementation does not consistently block all direct online-class routes; treat it as a visibility switch, not a complete shutdown control. |
| Online-class face default | Preselects face verification for new online classes. | Automatically turns off if the main face-recognition switch/provider is unavailable. |

## Sign-in and access automation

- The public root page is the Student/Parent sign-in screen for visitors. Signed-in users are redirected to the correct workspace by role.
- Staff use an environment-configured private login path. `/secure-login` is only a compatibility redirect; documentation must not expose a real production path.
- Five failed login attempts per email/IP combination are allowed per minute before throttling.
- New non-Console accounts marked for first-login replacement are forced to the password-change page before other work.
- Console password-reset requests deliberately return the normal neutral response but do not start a Console email reset.
- Every Instructor staff login clears the previous verification session. The Instructor must use face match, a 10-minute email code, or a saved security question before opening the shared Admin/Instructor workspace.
- Two-factor authentication is provided by Laravel Fortify for verified accounts and requires password confirmation for its settings page.

## Menu and page visibility

- Admin-only links include Academic Years, master data, User Management, activity logs, devices, settings, and privileged operations.
- Instructors see only their schedules, handled students, attendance workspace, online classes, messages, and reports.
- Registrar sees dashboard, Student Biometric Enrollment, Instructor Faces, messages, and reports.
- Clinic sees Clinic Dashboard, Case Logs, Patient History, Emergency Hotlines, messages, and reports.
- Student and Parent see the portal pages. Parent data is limited to explicitly linked students, selected through the student selector.
- The standalone Admin RFID route exists, but its navigation item is intentionally hidden because normal RFID enrollment is performed from student/instructor management or Registrar pages.
- Several Vue files are prototypes or legacy pages and have no active route. They are listed in [Pages and Features](PAGES_AND_FEATURES.md#legacy-reusable-and-unwired-page-files).

## Attendance automation

- The panel resolves live schedules only from the current academic year and active semester.
- A student must belong to the schedule's section through an active or enrolled yearly enrollment.
- A verification grant is short-lived and tied to one student and one attendance session. It is consumed after the tap.
- First valid tap creates a Pending attendance result and records Present or Late as the check-in basis. Late uses the configured number of minutes after class start.
- Before the last 15 minutes, another tap requires the scheduled Instructor's RFID and becomes Temporary Exit or Temporary Return.
- During the final 15 minutes, Dismiss Class, or an accepted fallback verification path, the next valid tap becomes official checkout.
- Once checkout exists, later taps are logged as ignored and do not change the official result.
- Leaving attendance mode finalizes students who left temporarily and did not return as Absent with a cutting reason.
- A checked-in student without checkout is displayed as Incomplete Attendance after the session ends.
- Missing eligible students are displayed as generated No Tap/Absent rows within the configured attendance-history window; these display rows are not necessarily stored attendance rows.
- Manual Present, Late, Absent, or Excused changes require an assigned Instructor, fall within the configured edit window, and create an audit/tap record. Excused requires a reason.
- Face evidence is stored on the public disk but served only through an authorized evidence route.

## Online-class automation

- Creating, updating, rescheduling, or cancelling a class creates per-student portal notifications and attempts email delivery.
- Students may join only from the scheduled start through the scheduled end. Joining again returns the existing success state instead of adding a duplicate.
- The normal late threshold also determines whether an online join is Present or Late.
- When face verification is required but unavailable, the system can remove the requirement and record/notify the bypass depending on the current provider state.
- `online-classes:finalize-attendance` runs every minute when Laravel's scheduler is running. It inserts missing Absent rows for ended, non-cancelled classes and is designed to be safe when repeated.
- Opening an online-class page also performs a lazy finalization pass, so records may still be completed when the scheduler was briefly unavailable.
- Current-code caution: some online-class enrollment queries require status `active`, while normal yearly enrollments default to `enrolled`. Verify this workflow in the deployed database before relying on automatic online attendance.

## Messages and notifications

- Signed-in non-Console roles can message permitted recipients. Conversation rows store sender and recipient IDs; only those users can open attachments.
- Message subject/body have encrypted columns and compatibility fallback columns.
- The layout checks unread messages every 10 seconds and shows an on-screen toast when a new message is detected.
- Email alerts for repeated messages from the same sender to the same recipient are limited by an atomic cache cooldown (five minutes by default). A failed email removes the cooldown key so a later message can retry.
- Online-class notifications have read timestamps and email delivery/error fields.

## Excuse-letter automation

- A Student letter becomes `pending_parent_approval` when the Parent Portal is on. Linked parents with valid email addresses receive a review link.
- If Parent Portal is off, a Student letter is approved immediately and sent to teachers without parent review.
- A permitted Parent-created letter is approved immediately and requires a typed signature.
- Parent approval stores the approving account, time, signature, and optional notes; generates a signed PDF; stores it; creates teacher Messenger records; and attempts teacher email with the PDF attached.
- Selected teacher recipients must be valid for the student's schedule. When none are selected, assigned teachers are determined from the student's class schedules.
- Parent approval never changes official attendance by itself.

## Emergency and clinic automation

- The panel suppresses rapid duplicate alert submissions and stores the selected scope, people, location, notes, and hotline/SMS result in alert metadata.
- An enabled matching hotline can trigger a Semaphore SMS attempt. Success or failure is returned and recorded; the alert itself remains saved even if SMS fails.
- Clinic Dashboard plays the selected sound only after browser audio is enabled and a newly received open alert appears.
- Acknowledge records the first acknowledgement time. Dispatch records dispatch time and response seconds.
- Dispatch requires an active Clinic responder, creates or updates one Clinic Case per identified student (or a general incident case), assigns the responder, emails available context, and shows the assignment in **My Dispatch Assignments**.
- Dispatch is internal and does not prove an external agency was contacted.

## Academic-year automation

- Only one academic year can be active. Activating one closes the previously active year.
- Closed and archived years are read-only for normal section, offering, schedule, online-class, and attendance changes. Reopening requires a reason and is audited.
- Semester rollover keeps students in the same academic year and grade, moving from First to Second Semester.
- Full rollover maps students into a different draft year while preserving the selected source semester: Grade 11 normally becomes Grade 12; Grade 12 normally becomes graduated; dropped/transferred/inactive records are skipped; operators may review decisions.
- Rollover creates/matches reviewed destination Sections, copies only the Subject Offerings selected in preview, creates enrollment records, updates current Student placement, and records a detailed audit. Instructor assignments and Schedules are intentionally not copied.
- Rollover never changes an academic year's active semester. The administrator changes the active semester explicitly through the Academic Years lifecycle controls.
- Attendance, messages, letters, clinic data, borrowing, files, and audit history are never copied during rollover.

## General auditing

- Mutating web requests and selected exports/log views are automatically written to `activity_logs` when possible.
- The audit middleware records user, role, route, HTTP method, subject, IP address, user agent, outcome, severity, and status code. Audit failure is deliberately prevented from breaking the user's operation.
- Attendance, online classes, registrar enrollment, rollover, and some emergency/message operations also write specialized records.
