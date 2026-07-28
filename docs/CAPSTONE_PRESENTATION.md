# Full System Capabilities Presentation And Demonstration

This file mirrors the generated editable PowerPoint at `capstone-rfid-system-presentation.pptx`.

## Slide 1: RFID Attendance And School Operations System

Full Capabilities Demonstration

- RFID attendance monitoring
- Face verification
- Inventory records
- Registrar biometric enrollment
- Student and parent portal
- Messenger
- Online classes
- Clinic and emergency workflows
- Reports and audit logs

## Slide 2: Presentation Goal

Show the complete system by role, then demonstrate the real operating flow from setup to daily use.

- What each role can do
- What must be configured first
- How instructor verification works
- How attendance is recorded
- How Messenger, excuse letters, emergency alerts, inventory, and reports fit together

## Slide 3: System Users

- Root Admin
- Admin
- Registrar
- Instructor
- Console Panel User
- Student
- Parent
- Clinic

Each role has a different dashboard and a different set of permissions.

## Slide 4: Recommended Demo Order

1. Admin setup
2. Registrar enrollment
3. Instructor login and verification
4. Console attendance panel
5. Student attendance tap
6. Student/parent portal
7. Messenger
8. Excuse letters
9. Emergency alert and clinic response
10. Inventory and reports

## Slide 5: Admin Capabilities

- Dashboard overview
- Laboratories and active devices
- Strands, sections, subjects, and schedules
- User management
- Instructor management
- Student and parent account management
- Inventory records
- Attendance logs
- Online class management and audit logs
- System settings
- Reports and activity logs
- Messenger

## Slide 6: Admin Setup Demonstration

Demo script:

1. Login as admin.
2. Open Laboratories/Active Devices.
3. Create or show a laboratory.
4. Open Strands and Sections.
5. Create or show an academic section.
6. Open Subjects.
7. Create or show a subject.
8. Open Instructors and Students.
9. Confirm that people exist before schedules.

## Slide 7: Schedule Setup Demonstration

Demo script:

1. Open Schedules.
2. Select laboratory or room.
3. Select instructor.
4. Select section.
5. Select subject.
6. Set weekday, start time, and end time.
7. Save the schedule.

Reminder: the current schedule page does not automatically block overlapping schedules, so the presenter should mention manual checking.

## Slide 8: Inventory Capabilities

- Create and review inventory records
- Track item names and categories
- Track quantity or availability count
- Track status
- Attach laboratory/location context when used
- Support admin dashboard/report visibility

This demo covers inventory records only.

## Slide 9: Registrar Capabilities

- Registrar dashboard
- Student RFID enrollment
- Student face image enrollment
- Instructor RFID enrollment
- Instructor face image enrollment
- Update or remove enrolled records
- Registrar reports
- Messenger

## Slide 10: Registrar Demonstration

Demo script:

1. Login as registrar.
2. Open Student Biometric Enrollment.
3. Select a student.
4. Assign or show RFID tag.
5. Capture or show face image records.
6. Open Instructor Face Enrollment.
7. Assign or show instructor RFID and face records.

Explain that attendance needs RFID enrollment before student taps can work.

## Slide 11: Instructor Capabilities

- Instructor dashboard
- Extra verification before protected instructor access
- Assigned schedules
- Assigned student visibility
- Online class management
- Attendance participation through RFID
- Temporary movement approval
- Dismiss Class mode
- Attendance logs
- Instructor reports
- Messenger

## Slide 12: Instructor Login Verification

When an instructor logs in, the system can require verification before continuing.

Available methods:

- Facial verification using enrolled instructor face image
- Email OTP
- Security questions

Routes used by the system:

- `/instructor/verify`
- `/instructor/verify/face`
- `/instructor/verify/otp/send`
- `/instructor/verify/otp`
- `/instructor/verify/security/setup`
- `/instructor/verify/security`

## Slide 13: Instructor Verification Demo

Demo script:

1. Login as instructor.
2. System opens Instructor Verification.
3. Show Face Verification option.
4. Explain that it compares the camera capture with enrolled instructor face image.
5. Switch to OTP.
6. Click send OTP and explain the code is emailed if mail is configured.
7. Switch to Security Question.
8. Show setup if questions are not yet saved.
9. Answer saved question to continue.
10. After success, instructor goes to dashboard.

## Slide 14: Console Panel Capabilities

- Attendance panel login
- Room/laboratory selection
- Live class session state
- Instructor RFID session start
- Student face verification flow
- Student RFID tap recording
- Instructor fallback approval
- Temporary exit and return handling
- Class-wide Dismiss Class mode support
- Emergency alert creation
- Live attendance log snapshot

## Slide 15: Attendance Panel Demonstration

Demo script:

1. Login as console.
2. Open Attendance Control Panel.
3. Select the correct laboratory.
4. Instructor taps RFID to start the class.
5. System matches the active schedule.
6. Student completes face check or approved fallback.
7. Student taps RFID.
8. Panel records check-in, late, temporary movement, or checkout.

## Slide 16: Attendance Rules Demonstration

- First valid tap becomes check-in.
- Late status uses admin late threshold.
- Temporary exit/return before checkout needs instructor approval.
- Final checkout window starts 15 minutes before class end.
- Instructor Dismiss Class mode makes every checked-in student's tap an official early checkout until Continue Class is selected.
- Extra taps after checkout are ignored but logged.
- Evidence images display when authorized and available.

## Slide 17: Student Capabilities

- Portal dashboard
- Attendance history
- Attendance evidence viewing when authorized
- Online classes
- Online class joining
- Excuse letter creation
- Attachment upload for excuse letters
- Messenger and portal messages
- Notifications
- Profile and password updates

## Slide 18: Parent Capabilities

- Parent portal dashboard
- Linked student switching
- Linked student attendance viewing
- Excuse letter approval
- Parent-created signed excuse letters
- Excuse letter downloads after approval
- Messenger and portal messages
- Notifications where available
- Profile and password updates

## Slide 19: Student And Parent Portal Demo

Demo script:

1. Login as student.
2. Open dashboard.
3. Show attendance history.
4. Show online classes.
5. Create an excuse letter.
6. Login as parent.
7. Select linked student.
8. Approve student-created excuse letter.
9. Download approved letter.

## Slide 20: Messenger Capabilities

- Unified Messenger for all authenticated non-console roles
- Search users by name, email, or role
- Start or open conversation
- Send text-only, attachment-only, or text-plus-attachment messages
- Add PDF, Word, image, GIF/WebP, or text attachments
- Show image attachments inline like photo messages
- Mark messages as read
- Download authorized attachments through protected links

Console accounts do not use Messenger because they are limited to attendance-panel operation.

Demo conversation:

- Student sends message to instructor.
- Instructor replies.
- Both users see the conversation history.
- Presenter sends an image attachment to show inline photo preview.
- Presenter sends a document attachment to show protected file download.

## Slide 21: Online Class Capabilities

- Instructor/admin create online classes
- Link class to schedule
- Meeting link and instructions
- Optional face verification requirement
- Student join tracking
- Late and attendance state tracking
- In-app notifications
- Email notification attempt when configured
- Admin online class audit logs

## Slide 22: Excuse Letter Capabilities

- Student creates excuse letter
- Optional attachment upload
- Parent approval required for student-created letters
- Parent can create signed excuse letters
- Approved letter download
- Student/parent history view

Key demo point: student-created letters are not downloadable until linked parent approval.

## Slide 23: Emergency And Clinic Capabilities

- Emergency alert creation from attendance panel
- Clinic dashboard alert monitoring
- Clinic dashboard MP3 sound for newly received emergency alerts
- Emergency type management
- Emergency hotline records
- Alert status updates
- Dispatch action where available
- Clinic case logs
- Patient histories
- Clinic reports

## Slide 24: Emergency Demonstration

Demo script:

1. Console opens attendance panel.
2. Trigger an emergency alert.
3. Login as clinic.
4. Open clinic dashboard.
5. Click or press any key once if the dashboard shows the alert-sound note.
6. Review alert details and explain that new alerts can play `/sound/emergency-alert.mp3`.
7. Update alert status.
8. Create a clinic case if needed.
9. Create patient history from the case.
10. Show clinic reports.

Important note: live SMS/text sending depends on provider configuration and is not confirmed as a complete external integration in the current source.

## Slide 25: Reports And Audit Capabilities

- Shared reports for admin, clinic, registrar, and instructor
- Role-aware report scope
- Summary cards
- Chart/table data
- CSV export
- Activity logs
- Online class audit logs
- Registrar enrollment logs

## Slide 26: Full Demo Closing Sequence

Recommended final demo flow:

1. Show admin setup records.
2. Show registrar RFID/face enrollment.
3. Login as instructor and verify by face, OTP, or security question.
4. Start attendance at the panel.
5. Record a student attendance tap.
6. Show attendance logs and evidence.
7. Show student/parent portal.
8. Show Messenger.
9. Show excuse letter approval.
10. Show emergency alert and clinic response.
11. Show inventory records.
12. Show reports and audit logs.

## Slide 27: Known Limitations

- Schedule overlap detection is not implemented.
- No dedicated setup wizard.
- No academic term closing/archive workflow.
- No full department/course/curriculum lifecycle module.
- Attendance correction approval workflow is not implemented.
- Live SMS/text dispatch depends on external provider readiness.
- Reports export CSV, not native spreadsheet files.

## Slide 28: Conclusion

The system demonstrates a complete role-based school operations workflow with a strong RFID attendance core, biometric verification, student/parent self-service, clinic response, inventory visibility, messaging, reports, and auditability.
