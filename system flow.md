# System Flow

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
3. Taps before that checkout window do not end attendance.
4. If the student is currently `Inside`, the tap is recorded as `Temporary Exit`.
5. If the student is currently `Outside`, the tap is recorded as `Temporary Return`.
6. Temporary taps continue alternating between exit and return.
7. Temporary taps are kept as audit records and do not change the final attendance status by themselves.

### 5. Official Check-out

1. The official checkout window starts 15 minutes before the scheduled end time.
2. The first valid student tap inside the checkout window becomes the official check-out.
3. The attendance record saves the check-out time.
4. The student room status becomes `Outside`.
5. If the student checked in on time and checked out, final status becomes `Present`.
6. If the student checked in late and checked out, final status becomes `Late`.

### 6. Ignored Taps

1. If a student taps again after official check-out, the tap is recorded as `Ignored Tap`.
2. Ignored taps do not change check-in, check-out, room status, or final attendance status.
3. This prevents duplicate checkout records for the same class.

### 7. Incomplete And Absent Attendance

1. If a student checked in but did not complete official check-out, the status remains `Pending` while the session is still active.
2. After the session or allowed attendance period ends, a checked-in student without official check-out is treated as `Incomplete Attendance`.
3. This applies even if the student has Temporary Exit or Temporary Return records.
4. If a student has no valid check-in tap for the scheduled class, the admin/instructor logs show the student as `Absent` after the attendance period ends.

### 8. Attendance Records And Tap Logs

Main attendance record:

- One main attendance row is kept per student, schedule, and date.
- It stores student ID, schedule ID, attendance date, scheduled start and end time, check-in time, check-out time, check-in status, final status, room status, total taps, remarks, created date, and updated date.

Tap log records:

- Each tap creates a separate tap log row.
- Tap logs store attendance ID, student ID, schedule ID, tap date/time, tap type, tap sequence number, device/scanner ID, room/location, validation result, and remarks.
- Tap types include `Check-in`, `Temporary Exit`, `Temporary Return`, `Check-out`, `Ignored Tap`, and `Invalid Tap`.

### 9. Admin And Instructor Attendance Views

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

### 10. Final Attendance Status Priority

1. No valid check-in after the attendance period ends: `Absent`.
2. Valid check-in but no official check-out after the session ends: `Incomplete Attendance`.
3. Valid check-in and valid check-out within the flow: `Present` or `Late`.
4. Active attendance with check-in but no check-out yet: `Pending`.
5. Temporary Exit and Temporary Return records do not override the final status.
