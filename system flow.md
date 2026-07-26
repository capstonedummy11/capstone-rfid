# System Flow

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

## Attendance Panel Flow

Page names:

- `AttendancePanelLogin.vue` - attendance panel login page.
- `AttendanceControlPanel.vue` - live RFID attendance panel used by the console account.
- `AttendanceLogs.vue` - admin and instructor attendance log page.
- `AttendanceScanner.vue` - admin and instructor RFID attendance scanner/demo page.

Routes:

- `/attendance-control-panel/login` - opens the panel login page.
- `/attendance-control-panel` - opens the live attendance control panel.
- `/admin/attendance/logs` - opens the admin/instructor attendance logs.
- `/admin/attendance/scanner` - opens the admin/instructor scanner page.

### 1. Panel Login

1. A console user opens `/attendance-control-panel/login`.
2. The user enters the panel PIN or uses the configured panel access flow.
3. After successful verification, the system opens `/attendance-control-panel`.
4. The user selects the active room or laboratory.

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
