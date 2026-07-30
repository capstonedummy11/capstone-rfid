# Step-By-Step System Use Tutorial

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

This guide explains the practical order for using the system after it is already opened and you can log in. It does not cover software installation, server setup, or deployment.

## Big Picture Setup Order

Use this order for a new or fresh school setup:

1. Admin logs in.
2. Admin reviews system settings.
3. Admin creates laboratories/rooms.
4. Admin creates strands.
5. Admin creates sections.
6. Admin creates subjects.
7. Admin creates staff users.
8. Admin creates instructor profiles.
9. Admin creates student records.
10. Admin links parent accounts to students if needed.
11. Registrar enrolls RFID cards and face images.
12. Admin creates schedules.
13. Admin prepares inventory records.
14. Console account opens the attendance panel.
15. Instructor starts the class attendance session.
16. Students tap RFID for attendance.
17. Admin/instructor reviews attendance logs and reports.
18. Users use role-based features such as Messenger, excuse letters, online classes, clinic cases, emergency alerts, and reports.

The reason for this order is simple: schedules need sections, subjects, instructors, rooms, and students to already exist. Attendance needs schedules, student RFID records, and instructor RFID records to already exist.

## 1. Login As Admin

For all roles, see the canonical [Authentication and Password Rules](AUTHENTICATION_PASSWORD_RULES.md). Admin, Instructor, Registrar, Clinic, Student, and Parent users can select **Forgot password?** and recover through their registered email. Console accounts are excluded.

When an account is new or an administrator has restored a temporary/default password, login opens **Create your private password** first. Enter and confirm a different password before continuing to the dashboard.

Start with an admin or root admin account.

After login, admin users are redirected to:

```text
/admin/dashboard
```

Use the admin account first because most setup records are created from the admin area.

## 2. Review System Settings

Go to:

```text
/admin/settings
```

Review the settings before creating daily records.

Important settings:

- Attendance late threshold.
- Face recognition availability.
- Panel access behavior.
- Inventory availability behavior.
- Online class face recognition default.

Recommended first value:

- Keep the late threshold at 15 minutes unless the school has another rule.

## 3. Create Laboratories Or Rooms

Go to:

```text
/admin/active-devices
```

Create the computer laboratories or rooms used for classes.

Examples:

- Computer Laboratory 1
- Computer Laboratory 2
- ICT Laboratory
- Robotics Laboratory

Why this comes early:

- Schedules need a room/laboratory.
- Attendance panel sessions are tied to a selected room.

## 4. Create Strands

Go to:

```text
/admin/strands
```

Create the strands used by the school.

Examples:

- ICT
- STEM
- ABM
- HUMSS

Why this comes before sections:

- Sections can be grouped under strands.
- Student records can be connected to strands.

## 5. Create Sections

Go to:

```text
/admin/sections
```

Create the class sections.

For each section, prepare:

- Strand.
- Section name.
- Year level.
- Semester.
- School year.

Examples:

- ICT 11-A
- ICT 12-B
- STEM 11-A

Why this comes before students and schedules:

- Students belong to sections.
- Schedules are assigned to sections.
- Reports can group students by section.

## 6. Create Subjects

Go to:

```text
/admin/subjects
```

Create the subjects that will appear in schedules and attendance logs.

For each subject, prepare:

- Subject code.
- Subject name.
- Semester.
- Optional section or assigned user if your workflow uses it.

The Section and Instructor fields support autosuggestion. Click the field and type part of a section or instructor name to narrow a large list. Use the clear button to leave an optional assignment unassigned.

Examples:

- CP101 - Computer Programming
- CSS101 - Computer Systems Servicing
- MIL101 - Media and Information Literacy

Why this comes before schedules:

- Every class schedule needs a subject.
- Attendance records display the scheduled subject.

## 7. Create Staff Users

Go to:

```text
/admin/users
```

Create users for staff roles:

- Admin.
- Registrar.
- Clinic.

Root admin note:

- Only a root admin can create, update, delete, or promote admin accounts.
- Normal admins can manage clinic and registrar accounts, but admin-account management is restricted.

## 8. Create Instructor Profiles

Go to:

```text
/admin/instructors
```

Create instructor profiles and connect them to instructor user accounts.

For each instructor, prepare:

- Name/user account.
- Email or staff account information.
- Assigned strand or related profile details if used by the form.

Why this comes before schedules:

- Schedules require an instructor assignment.
- Attendance sessions are started by the active instructor.
- Instructor reports are scoped to assigned schedules.

## 9. Create Student Records

Go to:

```text
/admin/students
```

Create students after strands and sections exist.

For each student, prepare:

- Student number.
- First name and last name.
- Email or account information if used.
- Strand.
- Section.
- Year level or school year details.
- Status.

Why this comes before attendance:

- Attendance can only be recorded for known student records.
- Student portal pages depend on student records.
- RFID and face enrollment are attached to student records.

## 10. Link Parent Accounts

Still in:

```text
/admin/students
```

Use the parent management action for each student when parent portal access is needed.

Parent links allow:

- Parent dashboard access.
- Linked student attendance viewing.
- Excuse letter approval.
- Parent profile context.

You can create a new parent account or link an existing parent account, depending on the available admin action.

## 11. Enroll Student RFID And Face Records

Login as registrar, then go to:

```text
/registrar/biometric-enrollment (Student Biometric Enrollment)
```

For each student, enroll:

- RFID card/tag.
- Face image records if face verification will be used.

Why this is required:

- RFID identifies the student at the attendance panel.
- Face images allow AWS Rekognition comparison when face verification is enabled.
- Students without face records may require instructor approval during attendance.

## 12. Enroll Instructor RFID And Face Records

As registrar, go to:

```text
/registrar/instructor-face-enrollment
```

For each instructor, enroll:

- Instructor RFID card/tag.
- Instructor face image records if instructor face verification is used.

Why this matters:

- Instructor RFID starts attendance sessions.
- Instructor RFID can approve fallback attendance flows.
- Instructor RFID can approve temporary exits/returns.
- Instructor Dismiss Class and Continue Class actions use the active instructor flow.

## 13. Create Class Schedules

Login as admin, then go to:

```text
/admin/schedules
```

Create schedules only after laboratories, sections, subjects, and instructors exist.

For each schedule, select:

- Laboratory or room.
- Instructor.
- Section.
- Subject.
- Weekday.
- Start time.
- End time.

Instructor, Section, and Subject are searchable autosuggestion fields. Start typing a name, subject code, grade, or school year, then select the matching result. When more than 50 choices exist, the initial list stays short and typing searches all available choices.

Example setup:

```text
Room: Computer Laboratory 1
Instructor: Instructor A
Section: ICT 11-A
Subject: CP101 - Computer Programming
Day: Monday
Time: 08:00 AM to 10:00 AM
```

Important limitation:

- The current schedule form validates the fields, but it does not automatically block overlapping schedules. Manually check that the same room, instructor, or section is not double-booked.

## 14. Prepare Inventory Records

Go to:

```text
/admin/inventory
```

Use Inventory to record laboratory equipment and supplies that the school wants to monitor.

Examples:

- Keyboard.
- Mouse.
- Monitor.
- System unit.
- Projector.
- HDMI cable.
- Network cable.
- Toolkit.

For each inventory record, prepare:

- Item name.
- Category or item type if the form asks for it.
- Quantity or available count.
- Status.
- Laboratory or location if used by the page.
- Remarks or condition notes if needed.

Why this comes after laboratories and schedules:

- Inventory items are easier to organize when laboratories/rooms already exist.
- Admin can review which equipment belongs to which room.
- Reports and dashboard counts become more useful when inventory records are complete.

This step is only for inventory setup, item visibility, and status checking.

## 15. Open The Attendance Panel

Before operating the panel, read the canonical [Attendance Control Panel Tapping Rules](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md). The tutorial steps below are an operational summary.

Login using a console account, then open:

```text
/attendance-control-panel
```

First select the correct room or laboratory.

Example:

```text
Computer Laboratory 1
```

The panel should match the physical room where students will tap.

## 16. Start A Class Attendance Session

At class time:

1. The instructor goes to the selected room.
2. The instructor taps their RFID card.
3. The system finds the active schedule for that room, instructor, day, and time.
4. The panel enters attendance mode for that class.

If no schedule matches, check:

- The room selected on the panel.
- The schedule day.
- The schedule start and end time.
- The assigned instructor.
- The instructor RFID enrollment.

## 17. Record Student Attendance

For each student:

1. Student completes face verification when required and available.
2. Student taps RFID.
3. First valid tap becomes official check-in.
4. If the student is late beyond the configured threshold, the record is marked late.
5. Temporary exit or return before checkout needs instructor approval.
6. The final valid tap in the checkout window becomes official checkout.

Checkout window:

- The system treats the final 15 minutes of the scheduled class as the official checkout window.

After checkout:

- Extra taps are ignored but still logged.

## 18. Dismiss or Continue a Class

If the instructor dismisses the class before the normal checkout window:

1. Instructor taps RFID.
2. Instructor chooses Dismiss Class.
3. A confirmation modal explains that all checked-in student taps will be official checkout.
4. Instructor confirms Dismiss Class.
5. Every checked-in student taps RFID.
6. The system records official checkout for each checked-in student.
7. Students without check-in receive Invalid Tap; students already checked out receive Ignored Tap.

Dismiss Class remains active across student taps. If the instructor taps again, the instructor action menu opens. Choosing Continue Class disables Dismiss Class and restores normal check-in, temporary-exit/return, and checkout-window rules.

Use Dismiss Class only when the class is actually being released, not for a temporary student exit.

## 19. Review Attendance Logs

Admin or instructor can review:

```text
/admin/attendance/logs
```

Use this page to check:

- Time in.
- Time out.
- Late status.
- Instructors can use **Edit status** on their assigned Attendance Logs while the date is still within the configured **Absent Attendance Days** window.
- Select Present, Late, Absent, or Excused. Excused requires an explanation.
- Saving creates a manual attendance event and a system activity entry showing who changed the status and its previous and new values. Once the configured window expires, the row becomes read-only.
- Temporary exits and returns.
- Ignored or invalid taps.
- Face evidence thumbnails when available.
- Final attendance status.

Instructor view is scoped to instructor assignments. Admin view is broader.

## 20. Use Reports

Go to:

```text
/reports
```

Reports are role-aware:

- Admin sees wider system reports.
- Instructor sees assigned schedule and attendance reports.
- Registrar sees student/enrollment-related reports.
- Clinic sees clinic/emergency reports.

CSV export is available from:

```text
/reports/export
```

## 21. Use Messenger

Messenger is available to authenticated roles from:

```text
/messages
```

Roles that can use the shared Messenger:

- Admin.
- Instructor.
- Clinic.
- Registrar.
- Student.
- Parent.

Console accounts cannot use Messenger. Console access is intentionally limited to attendance-panel room operation, RFID scans, attendance session state, and emergency alerts.

Basic Messenger flow:

1. Login using any supported role.
2. Open `/messages`.
3. Search for the person you want to message by name, email, or role.
4. Select the user or existing conversation.
5. Type a message, attach a file, or do both.
6. Review the selected file name if an attachment was chosen.
7. Send the message.
8. The recipient opens `/messages` to read and reply.

Recipients with valid email addresses receive a Gmail notification containing the sender, a message preview, and a link to Messenger. To avoid email spam, a burst of messages from the same sender to the same recipient produces only one email during the default five-minute cooldown. Every message is still saved and visible in Messenger.

Attachment behavior:

- You can send text-only, attachment-only, or text-plus-attachment messages.
- Supported attachments include PDF, Word, JPG, PNG, WebP, GIF, and text files within the configured upload limit.
- Image attachments show inline inside the chat bubble like a photo message.
- All attachments still use a protected download route, so only the sender or recipient can open them.

Use Messenger for:

- Student asking an instructor about attendance or class concerns.
- Parent contacting staff.
- Clinic contacting admin or registrar.
- Registrar coordinating enrollment issues.
- Instructor replying to student concerns.
- Admin sending files or announcements to supported users.

There is also a public message form:

```text
/messages/new
```

Use the public form when the sender is not logged in.

## 22. Use Online Classes

Instructor or admin starts the online class workflow from:

```text
/admin/online-classes
```

Instructor/admin flow:

1. Login as instructor or admin.
2. Open Online Classes.
3. Create a class for an existing schedule.
4. Add the title, meeting link, date, time, and instructions.
5. Choose whether face recognition is required when the setting/provider allows it.
6. Save the online class.
7. Students assigned to the section can see the class in their portal.

Student flow:

1. Login as student.
2. Open:

```text
/student-parent/online-classes
```

3. Find the online class.
4. Open the meeting link.
5. Join the class.
6. Complete face verification if required.
7. The system records online class attendance.

Admin audit flow:

```text
/admin/online-class-logs
```

Use this page to review online class events such as creation, updates, cancellation, student joins, attendance recording, and notification activity.

## 23. Use Excuse Letters

Students and parents use excuse letters from:

```text
/student-parent/excuse-letters
```

Student-created excuse letter flow:

1. Student logs in.
2. Student opens Excuse Letters.
3. Student creates an excuse letter.
4. Student adds the reason, date details, and attachment if needed.
5. The letter is saved with parent approval required.
6. Linked parents receive an email asking them to review and sign the letter.
7. The parent follows the email link and logs in.
8. Parent opens Excuse Letters for the linked student.
9. Parent reviews the student-created letter.
10. Parent approves it with a typed parent signature.
11. The approved generated letter becomes downloadable.
12. Selected instructors, or all assigned instructors when none were selected, receive the signed PDF through Messenger and email.

Parent-created excuse letter flow:

1. Parent logs in.
2. Parent selects the linked student if more than one child is linked.
3. Parent opens Excuse Letters.
4. Parent creates the letter.
5. Parent signs it during creation.
6. The letter is immediately treated as parent-approved.
7. The generated letter can be downloaded.
8. Selected or assigned instructors receive the signed PDF through Messenger and email.

Important rule:

- Student-created letters need parent approval before download.
- Parent-created letters are already approved because the parent created and signed them.

## 24. Use Emergency Alerts And Emergency Text Area

Emergency alert creation is available from the attendance panel:

```text
/attendance-control-panel
```

Clinic users manage emergency records from:

```text
/clinic/dashboard
/clinic/emergency-hotlines
```

Emergency alert flow from the panel:

1. Console account opens the attendance panel.
2. User selects or enters the emergency type/details.
3. User submits the emergency alert.
4. Clinic dashboard receives the alert.
5. If the clinic dashboard is already open, it refreshes alert data automatically and plays the emergency alert sound for a newly received alert.
6. If the browser has not enabled audio yet, the clinic dashboard shows a small note asking the user to click anywhere or press any key once. After audio is enabled, the note disappears.
7. Clinic reviews the room, type, message, patient/student details when available, and status.
8. Clinic updates the alert status as it is handled.

Emergency alert sound:

- The sound file is stored at `public/sound/emergency-alert.mp3`.
- The browser loads it from `/sound/emergency-alert.mp3`.
- The sound is intended for newly received emergency alerts on the clinic dashboard, not for the initial page load.

Emergency hotline/text-management flow:

1. Login as clinic or admin with clinic access.
2. Open `/clinic/emergency-hotlines`.
3. Add hotline contact records.
4. Maintain names, contact details, and emergency categories.
5. Use emergency alert dispatch actions where available in the clinic workflow.

Important current limitation:

- The system stores emergency hotlines and alert details. Live external SMS/text sending is not confirmed as a completed provider integration in the current source, so treat the emergency text area as records and dispatch workflow unless your deployed environment has a working provider connected.

## 25. Use Clinic Case Logs And Patient History

Clinic users start from:

```text
/clinic/dashboard
```

Clinic case flow:

1. Clinic reviews emergency alerts or receives a walk-in case.
2. Clinic opens:

```text
/clinic/case-logs
```

3. Clinic creates a case log with patient name, patient type, symptoms, case type, action taken, status, and notes.
4. If the case should become part of medical history, clinic creates a patient history record from the case.
5. Clinic opens:

```text
/clinic/patient-history
```

6. Clinic reviews, updates, or creates patient history records.

Clinic reporting:

```text
/clinic/reports
```

Use clinic reports for case summaries, emergency alert counts, trends, and CSV export.

## 26. Role-Based Daily Use Guide

Use this section as the quick feature map for each role.

| Role | Main Features |
| --- | --- |
| Root Admin | Admin account control, system settings, full admin management, activity logs, reports. |
| Admin | Laboratories, academic records, users, instructors, students, parent links, schedules, inventory, attendance logs, online classes, reports, system settings, Messenger. |
| Registrar | Student RFID enrollment, student face enrollment, instructor RFID enrollment, instructor face enrollment, registrar reports, Messenger. |
| Instructor | Assigned schedules, instructor verification, attendance session participation, Dismiss Class/Continue Class control, temporary movement approval, attendance logs, online classes, reports, Messenger. |
| Console | Room selection, attendance panel operation, instructor session start support, student RFID tap recording, face/fallback attendance flow, emergency alert creation. |
| Student | Portal dashboard, attendance history, online classes, excuse letters, messages, notifications, profile updates. |
| Parent | Linked student dashboard, attendance viewing, excuse letter approval, parent-created excuse letters, messages, notifications, profile updates. |
| Clinic | Clinic dashboard, emergency alerts, case logs, patient histories, emergency hotlines, emergency types, clinic reports, Messenger. |

### Root Admin

Use root admin when the action affects system ownership or admin accounts.

Features:

- Access admin dashboard.
- Manage admin-level accounts when root permissions are required.
- Create, update, delete, or promote admin users.
- Review system activity logs.
- Review shared reports.
- Use Messenger.
- Review or update system settings when needed.

Common tasks:

1. Login as root admin.
2. Review `/admin/users`.
3. Create or update admin accounts when needed.
4. Promote or restrict admin users.
5. Review `/admin/activity-logs`.

### Admin

Use admin for school setup and daily management.

Features:

- Dashboard overview.
- Laboratory/room management.
- Active device and panel access monitoring.
- Strand management.
- Section management.
- Subject management.
- Instructor management.
- Student management.
- Parent account linking.
- Schedule management.
- Inventory records.
- Attendance logs.
- Online class management.
- Online class audit logs.
- User management for supported roles.
- System settings.
- Shared reports and CSV export.
- System activity logs.
- Messenger.

Common tasks:

1. Create laboratories.
2. Create strands, sections, and subjects.
3. Create instructors and students.
4. Link parent accounts.
5. Create schedules.
6. Prepare and review inventory records.
7. Review attendance logs.
8. Review reports.
9. Maintain system settings.

Admin routes to remember:

- `/admin/dashboard`
- `/admin/active-devices`
- `/admin/strands`
- `/admin/sections`
- `/admin/subjects`
- `/admin/instructors`
- `/admin/students`
- `/admin/schedules`
- `/admin/inventory`
- `/admin/attendance/logs`
- `/admin/activity-logs`
- `/reports`
- `/messages`

### Registrar

Use registrar for RFID and face enrollment.

Features:

- Registrar dashboard.
- Student RFID enrollment.
- Student face image enrollment.
- Instructor RFID enrollment.
- Instructor face image enrollment.
- Update or remove biometric records when needed.
- Registrar enrollment logs.
- Registrar-scoped reports.
- Messenger.

Common tasks:

1. Login as registrar.
2. Open `/registrar/biometric-enrollment`.
3. Assign student RFID cards.
4. Capture or upload student face images.
5. Open `/registrar/instructor-face-enrollment`.
6. Assign instructor RFID cards.
7. Capture or upload instructor face images.
8. Use `/reports` for registrar reports.
9. Use `/messages` to coordinate with admin, instructors, students, or parents.

### Instructor

Use instructor for teaching, online classes, attendance review, and student communication.

Features:

- Instructor/admin dashboard access.
- Instructor verification by supported methods.
- Assigned student viewing.
- Assigned schedule viewing.
- Attendance session start through instructor RFID at the panel.
- Instructor approval for temporary student exits and returns.
- Class-wide Dismiss Class mode for official early checkout.
- Attendance log review for assigned classes.
- Online class creation and management for assigned schedules.
- Online class attendance tracking.
- Instructor-scoped reports.
- Messenger.

Common tasks:

1. Login as instructor.
2. Complete instructor verification if required.
3. Review assigned dashboard/schedules.
4. Create or manage online classes at `/admin/online-classes`.
5. Start physical attendance by tapping RFID at the attendance panel.
6. Approve temporary student exits/returns when appropriate.
7. Use Dismiss Class for class-wide official early checkout; tap again and choose Continue Class to restore normal rules.
8. Review scoped attendance logs at `/admin/attendance/logs`.
9. Use `/reports` for instructor reports.
10. Use `/messages` for conversations.

### Console Panel User

Use console accounts only for the physical attendance panel.

Features:

- Attendance panel login.
- Room/laboratory selection.
- Live attendance panel operation.
- RFID lookup.
- Student face check flow.
- Instructor face check flow.
- Student tap recording.
- Live attendance log snapshot.
- Emergency alert creation from the panel.
- Panel logout/session state updates.

Common tasks:

1. Login as console.
2. Open `/attendance-control-panel`.
3. Select the correct room.
4. Wait for instructor RFID to start the active class.
5. Record student attendance taps.
6. Use emergency alert action when needed.
7. Logout or let admin force logout from active device tools if needed.

### Student

Use student portal for self-service.

Features:

- Student/parent portal dashboard.
- Attendance history.
- Protected attendance evidence viewing when authorized.
- Online class list.
- Online class joining and attendance recording.
- Excuse letter creation.
- Excuse letter attachment upload.
- Messenger and portal messages.
- Online class notifications.
- Profile update.
- Password update.

Common tasks:

1. Login from the student/parent portal.
2. Open `/student-parent/dashboard`.
3. Check attendance at `/student-parent/attendance`.
4. Join online classes at `/student-parent/online-classes`.
5. Create excuse letters at `/student-parent/excuse-letters`.
6. Message staff or instructors from `/student-parent/messages` or `/messages`.
7. Review notifications at `/student-parent/notifications`.

### Parent

Use parent portal for linked student monitoring and approvals.

Features:

- Parent portal dashboard.
- Linked student switching when multiple students are connected.
- Linked student attendance history.
- Protected attendance evidence viewing when authorized.
- Parent approval for student-created excuse letters.
- Parent-created signed excuse letters.
- Excuse letter downloads after approval.
- Messenger and portal messages.
- Online class notification viewing when available.
- Parent profile update.
- Password update.

Common tasks:

1. Login from the student/parent portal.
2. Select the linked student if more than one child is linked.
3. Review attendance.
4. Approve student-created excuse letters.
5. Create parent-signed excuse letters.
6. Use messages.
7. Review online class notifications when available.

### Clinic

Use clinic for health and emergency operations.

Features:

- Clinic dashboard.
- Emergency alert monitoring.
- Emergency alert sound for newly received clinic-dashboard alerts.
- Emergency alert status updates.
- Emergency dispatch action where available.
- Clinic case logs.
- Patient history records.
- Clinic reports and CSV export.
- Emergency hotline management.
- Emergency type management.
- Shared reports.
- Messenger.

Common tasks:

1. Login as clinic.
2. Open `/clinic/dashboard`.
3. Click anywhere or press any key once if the dashboard shows the alert-sound note.
4. Review emergency alerts.
5. Update alert status.
6. Create case logs.
7. Create or update patient histories.
8. Maintain emergency types and hotlines.
9. Use clinic reports.
10. Use Messenger for coordination.

## 27. Student And Parent Daily Use

Students and parents login from the public portal.

After login, they go to:

```text
/student-parent/dashboard
```

They can use:

- `/student-parent/attendance` for attendance history.
- `/student-parent/online-classes` for online class joining.
- `/student-parent/excuse-letters` for excuse letters.
- `/student-parent/messages` for messages.
- `/student-parent/notifications` for online class notifications.

Student-created excuse letters require linked parent approval before download.

## 28. Clinic Daily Use

Clinic users login and go to:

```text
/clinic/dashboard
```

Clinic workflow:

1. Open the clinic dashboard and enable alert sound when the note appears.
2. Review emergency alerts.
3. Update alert status when handled.
4. Create clinic cases when a patient is treated.
5. Create patient history from case records when needed.
6. Review clinic reports.
7. Maintain emergency types and hotlines.

## Recommended First Demo Script

Use this simple flow when presenting the system:

1. Login as admin.
2. Show laboratories.
3. Show strands and sections.
4. Show subjects.
5. Show instructors.
6. Show students and parent links.
7. Login as registrar.
8. Show RFID and face enrollment.
9. Login as admin again.
10. Show schedule creation.
11. Show inventory records.
12. Login as console.
13. Select a room.
14. Start attendance with instructor RFID.
15. Record a student check-in.
16. Show temporary exit/return or checkout.
17. Open attendance logs.
18. Open reports.
19. Show student/parent portal attendance history.
20. Show Messenger between student and instructor.
21. Show excuse-letter approval from parent.
22. Show emergency alert from attendance panel.
23. Show clinic dashboard receiving or managing the alert.

## Common Mistakes To Avoid

- Do not create schedules before creating rooms, sections, subjects, and instructors.
- Do not expect attendance to work before RFID enrollment.
- Do not expect face verification to work before face image enrollment.
- Do not double-book a room, instructor, or section; the current schedule module does not automatically prevent overlaps.
- Do not use Dismiss Class for temporary exits.
- Do not put private credentials or passwords in public documentation.
