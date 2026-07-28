# Full System Demonstration Script

Use this as the presenter script for demonstrating the complete system capabilities. It assumes the app is already open, users can log in, and demo data is available.

## 1. Admin Setup Demo

Login as admin.

Show these pages in order:

1. `/admin/dashboard`
2. `/admin/active-devices`
3. `/admin/strands`
4. `/admin/sections`
5. `/admin/subjects`
6. `/admin/instructors`
7. `/admin/students`
8. `/admin/schedules`
9. `/admin/inventory`
10. `/admin/settings`

Talk track:

- The admin prepares the school structure first.
- Laboratories, sections, subjects, instructors, and students must exist before schedules.
- Inventory records are maintained by admin for laboratory visibility.
- Settings control attendance threshold, face recognition, panel access, and online class defaults.

## 2. Registrar Enrollment Demo

Login as registrar.

Show:

1. `/registrar/dashboard`
2. `/registrar/biometric-enrollment`
3. `/registrar/instructor-face-enrollment`

Talk track:

- Registrar connects students and instructors to RFID cards.
- Registrar enrolls face images used for verification.
- Student RFID is needed for attendance taps.
- Instructor RFID is needed for class start, fallback approval, temporary movement approval, Dismiss Class, and Continue Class.

## 3. Instructor Login Verification Demo

Login as instructor.

Show:

```text
/instructor/verify
```

Demonstrate the three verification methods:

1. Face verification
2. OTP
3. Security question

Face verification talk track:

- The instructor centers their face in the camera.
- The system compares the capture to the enrolled instructor face image.
- If it matches, the instructor is verified and redirected to the dashboard.

OTP talk track:

- The instructor requests an OTP.
- The system stores a hashed OTP in the session and sends an email if mail is configured.
- The OTP expires after 10 minutes.
- A valid OTP verifies the instructor session.

Security question talk track:

- If questions are not yet saved, the instructor sets up three security questions.
- Later, the instructor can answer a saved question.
- Correct answer verifies the instructor session.

## 4. Console Attendance Demo

Login as console.

Show:

```text
/attendance-control-panel
```

Demo steps:

1. Select the laboratory.
2. Instructor taps RFID.
3. Panel starts the active class session.
4. Student completes face check or instructor-approved fallback.
5. Student taps RFID.
6. First valid tap records check-in.
7. Show late/on-time status.
8. Demonstrate temporary exit/return with instructor approval if useful.
9. Demonstrate official checkout or Dismiss Class mode, then tap the instructor RFID again and choose Continue Class to restore normal rules.

Talk track:

- The panel does not blindly save attendance. It checks active schedule, student identity, verification grant, and tap timing.
- Attendance logs keep tap sequence and evidence.

## 5. Attendance Logs Demo

Login as admin or instructor.

Show:

```text
/admin/attendance/logs
```

Talk track:

- Admin can review wider attendance records.
- Instructor sees assigned attendance scope.
- Logs show time in, time out, status, temporary movement, ignored taps, and evidence where available.

## 6. Student And Parent Portal Demo

Login as student.

Show:

1. `/student-parent/dashboard`
2. `/student-parent/attendance`
3. `/student-parent/online-classes`
4. `/student-parent/excuse-letters`
5. `/student-parent/messages`
6. `/student-parent/notifications`

Then login as parent.

Show:

1. Linked student context.
2. Attendance view.
3. Excuse letter approval.
4. Messages.

Talk track:

- Student can view attendance and join online classes.
- Student can create excuse letters.
- Parent approval is required for student-created excuse letters before download.

## 7. Messenger Demo

Show:

```text
/messages
```

Demo steps:

1. Login as student and send a message to instructor.
2. Login as instructor.
3. Open Messenger.
4. Reply to the student.
5. Search for another supported recipient by name, email, or role.
6. Attach an image and send it as a photo-style chat message.
7. Attach a document and send it as a downloadable file.
8. Show conversation history.

Talk track:

- Messenger supports authenticated role-to-role communication for admin, instructor, clinic, registrar, student, and parent users.
- Console users are excluded because they are limited to the attendance panel.
- Messenger can send text-only, attachment-only, or text-plus-attachment messages.
- Image attachments display inline like photos, while all attachment downloads remain protected so only the sender or recipient can open them.

## 8. Online Class Demo

Login as instructor or admin.

Show:

```text
/admin/online-classes
```

Demo steps:

1. Create an online class for an assigned schedule.
2. Add meeting link and instructions.
3. Enable face requirement only when available.
4. Login as student.
5. Join the online class from `/student-parent/online-classes`.
6. Show online class attendance/audit logs as admin.

## 9. Emergency And Clinic Demo

From console:

1. Open attendance panel.
2. Trigger emergency alert.

From clinic:

1. Open `/clinic/dashboard`.
2. Click anywhere or press any key once if the emergency-sound notice is visible.
3. Review emergency alert.
4. Point out that newly received alerts play `/sound/emergency-alert.mp3` on the clinic dashboard.
5. Update alert status.
6. Open `/clinic/case-logs`.
7. Create a clinic case.
8. Open `/clinic/patient-history`.
9. Create or review patient history.
10. Open `/clinic/reports`.

Talk track:

- Emergency alert records and hotline records are managed in the app.
- The clinic dashboard refreshes alert data and plays an MP3 sound for newly received emergency alerts after browser audio is enabled.
- Live SMS/text sending depends on external provider configuration and is not confirmed as fully implemented in the current source.

## 10. Reports And Audit Demo

Show:

1. `/reports`
2. `/reports/export`
3. `/admin/activity-logs`
4. `/admin/online-class-logs`

Talk track:

- Reports are role-aware.
- Exports are CSV.
- Activity logs and online class audit logs support accountability.

## Recommended Closing Statement

The system connects setup, enrollment, verified attendance, student/parent self-service, communication, emergency response, inventory visibility, reports, and audit logs into one role-based workflow.
