# Attendance System: Complete Demonstration Guide and Presentation Script

This guide is designed for a live capstone defense, client presentation, or user training session. It follows the behavior implemented in the current system.

## 1. Demonstration Objective

The attendance system records and validates student presence in a scheduled class using RFID, with face verification or an instructor-authorized fallback. It links each attendance event to the student, subject, section, instructor, schedule, room, and attendance session. It also preserves a tap-by-tap audit trail, supports emergency requests, and provides role-based attendance history, reports, and administration.

The normal attendance journey is:

1. A console user opens the attendance panel and selects a laboratory.
2. The assigned instructor starts the scheduled class using the instructor RFID.
3. The student verifies their identity.
4. The student's first valid RFID tap records check-in.
5. The student's later valid tap records check-out when the checkout rule is satisfied.
6. The system finalizes the record as Present or Late.

## 2. Attendance Rules and Tapping Guidelines

### 2.1 Required taps

Two official student taps are normally required:

- First official tap — **Check-in**: records `time_in`, places the student inside the room, and leaves the attendance record Pending until checkout.
- Second official tap — **Check-out**: records `time_out`, places the student outside the room, and finalizes the record as Present or Late.

Identity verification is required before a student tap is accepted. Depending on configuration, this is a face match or an instructor RFID/fallback authorization.

### 2.2 On-time and late check-in

The default late threshold is **15 minutes after the scheduled class start**. An administrator can change this under System Settings.

- At or before `class start + late threshold`: the check-in classification is Present.
- After `class start + late threshold`: the check-in classification is Late.
- A tap before the scheduled start is accepted as an on-time check-in. The exact timestamp is retained; there is no separate final “Early” status.

Example for an 8:00 AM class using the default 15-minute threshold:

- 7:55 AM — accepted early check-in; eligible for final Present.
- 8:00 AM to 8:15 AM — on-time check-in; eligible for final Present.
- After 8:15 AM — late check-in; final status remains Late after checkout.

The threshold is exclusive at its upper boundary: exactly 8:15 AM is within the grace period; later than 8:15 AM is Late.

### 2.3 Normal checkout window

The normal checkout window begins **15 minutes before the scheduled class end**.

Example for a class ending at 10:00 AM:

- From 9:45 AM onward, the next valid student tap may be the official check-out.
- Before 9:45 AM, a normal face-verified tap is treated as a request for temporary movement and requires the assigned instructor's RFID.

An official checkout may also be recorded earlier through:

- **Student Logout mode**, explicitly activated and authorized by the instructor; or
- An applicable verification fallback accepted by the implementation, such as instructor RFID, disabled face recognition, or an approved camera/recognition fallback.

### 2.4 Early taps and breaks

The system does not use “Early Tap/Break” as a final attendance status.

- An early **first** tap is stored as the actual check-in time and is treated as on time.
- A tap after check-in but before the checkout window is a **Temporary Exit** or **Temporary Return**, not an official checkout, when authorized with the assigned instructor RFID.
- Temporary Exit changes room status to Outside.
- Temporary Return changes room status back to Inside.
- The main attendance record remains Pending until an official checkout or session finalization.
- If instructor authorization is not provided, the movement is not recorded and the panel asks for the instructor RFID.

### 2.5 Third and later taps

A third tap is accepted as the official second attendance endpoint when no official checkout exists and the checkout rule is satisfied. A common sequence is:

1. Check-in.
2. Instructor-authorized Temporary Exit.
3. Tap during the checkout window, in Student Logout mode, or with an applicable authorized fallback — accepted as Check-out.

If tap 3 occurs before the checkout window under the ordinary flow, it may instead become Temporary Return after instructor authorization. The system therefore evaluates the tap's time, mode, verification method, and current room state—not only its sequence number.

After an official `time_out` exists, all further taps are stored as **Ignored Tap** audit events. They do not change the completed attendance record.

### 2.6 Missing taps

- One valid check-in while the class is active: **Pending**.
- Check-in without checkout after the class/session ends: displayed as **Incomplete Attendance** in attendance views.
- When the panel closes or leaves Attendance mode, the current implementation finalizes open check-in logs as `absent` with completion reason `cutting`; the main record is also marked absent. Consequently, the final logs may show **Absent** rather than Incomplete Attendance after panel/session finalization.
- No valid check-in for a student in the scheduled section after a completed session: a generated **Absent** record appears in the attendance log view.

Presenter note: “Incomplete Attendance” is the display rule for an ended session with `time_in` but no `time_out`. The panel's explicit finalization routine can mark those open records Absent. Mention both behaviors to describe the implementation accurately.

### 2.7 Invalid and duplicate taps

A tap is rejected or marked invalid when, for example:

- The RFID is not assigned to a student.
- No active attendance session exists in the selected room.
- The student is not part of the scheduled section.
- The student's year level does not match the subject, when that restriction is set.
- Face verification or instructor override is missing or expired.
- Student Logout is requested for a student who never checked in.
- A temporary movement tap is attempted without the assigned instructor's RFID.

A tap after completed checkout is an **Ignored Tap**, not a new check-in or checkout. It is still auditable.

### 2.8 Status and outcome reference

| Label shown or recorded | Type | Condition |
|---|---|---|
| Checked In | Immediate panel response | First valid tap creates `time_in`; record remains Pending |
| Pending | Attendance status | Valid check-in exists, no checkout exists, and session is still active |
| Present | Final attendance status | On-time check-in plus official checkout |
| Late | Final attendance status | Check-in after the configured grace period plus official checkout |
| Incomplete Attendance | Displayed attendance status | Check-in exists, no checkout exists, and the session is considered ended |
| Absent | Final/generated status | Open record finalized by the panel as cutting, or no valid check-in exists for an eligible student in a completed session |
| Temporary Exit | Tap event | Pre-checkout-window movement from Inside to Outside, authorized by instructor RFID |
| Temporary Return | Tap event | Pre-checkout-window movement from Outside to Inside, authorized by instructor RFID |
| Invalid Tap | Tap outcome | Validation fails, including logout without check-in |
| Ignored Tap | Tap outcome | Official checkout already exists; completed attendance is unchanged |

### 2.9 Decision table

| Current state | Tap condition | System action | Result |
|---|---|---|---|
| No record | Valid identity and correct active class | Create check-in | Pending; Present or Late classification retained |
| No record | Student Logout mode | Log Invalid Tap | No attendance record created |
| Checked in | At/after 15 minutes before class end | Record check-out | Present or Late |
| Checked in | Student Logout mode | Record check-out | Present or Late |
| Checked in | Applicable authorized fallback | Record check-out | Present or Late |
| Checked in, Inside | Before checkout window, instructor approves | Temporary Exit | Pending; room status Outside |
| Checked in, Outside | Before checkout window, instructor approves | Temporary Return | Pending; room status Inside |
| Checked in | Before checkout window, no instructor approval | Reject movement request | No new tap record; attendance unchanged |
| Checked out | Any later tap | Log Ignored Tap | Completed record unchanged |
| Checked in only | Session ends | Display Incomplete or finalize as Absent/cutting | No official checkout |
| No check-in | Completed eligible class session | Generate No Tap record | Absent |

### 2.10 Simple flowchart

```text
Student presents RFID
        |
        v
Active session + correct section/year + valid verification?
        | No
        +----> Reject / Invalid Tap; explain validation failure
        |
       Yes
        |
        v
Existing attendance record?
        | No
        +----> First tap: save check-in
        |              |
        |              +----> within grace = Present classification
        |              +----> after grace  = Late classification
        |                       (record remains Pending until checkout)
        |
       Yes
        |
        v
Official checkout already saved?
        | Yes
        +----> Log Ignored Tap; do not change attendance
        |
       No
        |
        v
Checkout window / Student Logout / accepted fallback?
        | Yes
        +----> Save official checkout -> final Present or Late
        |
       No
        |
        v
Assigned instructor RFID authorizes movement?
        | No
        +----> Ask for instructor RFID; no movement recorded
        |
       Yes
        +----> Inside -> Temporary Exit
               Outside -> Temporary Return
               Attendance remains Pending
```

## 3. Pre-Demonstration Checklist

Before the audience arrives:

1. Confirm the database has an admin, console, instructor, clinic, student, and optionally parent account.
2. Confirm the student belongs to the section used by today's schedule.
3. Confirm student and instructor RFID tags are enrolled.
4. Confirm the subject, room, instructor, and schedule times support the scenarios.
5. Confirm the attendance late threshold under `/admin/settings`.
6. Confirm face images are enrolled, or prepare the instructor RFID fallback.
7. Confirm the browser has camera permission if face verification will be shown.
8. Confirm emergency types and hotlines exist.
9. For live SMS, confirm Semaphore is enabled, the API key and endpoint are configured, and the chosen active hotline has SMS enabled.
10. Open a second browser or private window for the clinic dashboard.
11. Enable clinic alert sound by clicking or pressing a key once on the clinic page.
12. Use separate demo students or resettable demo data for conflicting scenarios.

Suggested example schedule:

- Subject: Computer Programming 1
- Section: ICT 11-A
- Room: Laboratory 1
- Time: 8:00 AM–10:00 AM
- Late threshold: 15 minutes
- Checkout window: 9:45 AM–10:00 AM

## 4. Complete Live Presentation Script

Each step below contains the visible screen, presenter action, suggested explanation, expected response, and important notes.

### Step 1 — Introduce the system

**On screen:** Landing page or title slide.

**Presenter action:** Point to the major system modules.

**Say:** “This system combines scheduled RFID attendance, identity verification, emergency response, role-based records, notifications, and reports. Each attendance event is validated against the active class rather than accepting an RFID value blindly.”

**Expected response:** No system action yet.

**Important note:** State that RFID identifies the card holder, while face verification or an instructor-authorized fallback strengthens identity validation.

### Step 2 — Log in as the attendance console user

**On screen:** Staff login or `/attendance-control-panel/login`.

**Presenter action:** Enter the console credentials. If panel PIN access is enabled, enter the configured PIN. Select Laboratory 1 when prompted.

**Say:** “The attendance station is a controlled console account. Room selection ties every scan to a physical laboratory and prevents records from being mixed across rooms.”

**Expected response:** The Attendance Control Panel opens and shows the selected room, panel status, schedule area, RFID input/listening state, and emergency controls.

**Important note:** An administrator can monitor active devices, change panel PIN access, or force a panel logout.

### Step 3 — Start the active class

**On screen:** Attendance Control Panel in Online or waiting state.

**Presenter action:** Tap or enter the assigned instructor RFID, then choose/start Attendance mode for the displayed schedule.

**Say:** “The instructor starts the class session. The system resolves today's schedule, subject, section, room, and instructor before accepting student attendance.”

**Expected response:** The panel changes to Attendance mode and displays class details. An attendance session is created or activated.

**Important note:** A student outside the scheduled section is rejected.

### Step 4 — Verify the student

**On screen:** Student lookup/face verification prompt.

**Presenter action:** Present the student's RFID when requested, center the student in the camera, and complete face verification. If the camera or recognition service is unavailable, demonstrate the assigned instructor RFID override.

**Say:** “Before attendance is saved, the student must pass face verification or receive an authorized instructor fallback. This verification grant is short-lived and is consumed by the attendance tap.”

**Expected response:** A verification-success message appears and the system permits the next attendance tap.

**Important note:** Do not claim that face recognition is always active; it is configurable and depends on the recognition service.

### Step 5 — Scenario A: on-time check-in and checkout

**On screen:** Active attendance panel.

**Presenter action:** Use Student A at a simulated time within 8:00–8:15 AM. Complete verification and tap once.

**Say:** “This is the first official tap. It records the actual check-in timestamp. Because it is within the configured 15-minute grace period, the internal check-in classification is Present, but the live record remains Pending until checkout.”

**Expected response:** “Check-in” or “Checked In”; `time_in` is populated, `time_out` is blank, room status is Inside, and attendance is Pending.

**Presenter action:** At or after 9:45 AM, verify Student A again and tap.

**Say:** “The checkout window begins 15 minutes before class ends. This valid tap records the official checkout.”

**Expected response:** “Check-out”; `time_out` is populated, room status becomes Outside, and final status is Present.

### Step 6 — Scenario B: late check-in

**On screen:** Active attendance panel.

**Presenter action:** Use Student B after 8:15 AM, verify, and tap.

**Say:** “The first tap occurred after the configured grace period, so the system marks this check-in as Late. Checkout is still required.”

**Expected response:** Check-in recorded; live status remains Checked In/Pending while the late classification is retained.

**Presenter action:** Complete Student B's valid checkout.

**Expected response:** Final status Late with both timestamps.

**Important note:** Checkout does not convert a Late check-in to Present.

### Step 7 — Scenario C: one tap only or missed checkout

**On screen:** Active panel and Student C's check-in.

**Presenter action:** Verify and check in Student C, then do not tap again.

**Say:** “A single tap is not complete attendance. While class is active the record is Pending. If the session ends without checkout, the system identifies the missing endpoint.”

**Expected response:** During class: Pending. After the session is ended: Incomplete Attendance may be displayed; panel finalization may mark the open record Absent with completion reason cutting.

**Important note:** Explain the precise implementation behavior rather than promising that all ended one-tap records retain one universal label.

### Step 8 — Scenario D: early second tap or break

**On screen:** Student D already checked in before 9:45 AM.

**Presenter action:** Verify Student D and tap before the checkout window.

**Say:** “This is too early for ordinary official checkout. The system treats it as temporary movement and asks for the assigned instructor's RFID.”

**Expected response:** Temporary Movement Authorization Required.

**Presenter action:** Present the assigned instructor RFID.

**Expected response:** Temporary Exit is recorded; room status becomes Outside; attendance stays Pending.

**Presenter action:** Repeat student verification/tap before the checkout window and approve with instructor RFID.

**Expected response:** Temporary Return is recorded; room status becomes Inside; attendance stays Pending.

**Important note:** Temporary Exit/Return are audit events, not final “Early Tap/Break” attendance statuses.

### Step 9 — Scenario E: third tap becomes official checkout

**On screen:** Student E has Check-in as tap 1 and Temporary Exit as tap 2, with no official checkout.

**Presenter action:** At or after the checkout window begins, verify Student E and tap.

**Say:** “Although this is the third physical tap, it is accepted as the official checkout because no checkout exists and the checkout rule is now satisfied. The system evaluates state and timing, not merely tap number.”

**Expected response:** Tap 3 is Check-out; the attendance record gains `time_out` and becomes Present or Late according to tap 1.

### Step 10 — Scenario F: additional taps after completion

**On screen:** Student A or E already has a completed record.

**Presenter action:** Verify and tap the same student again.

**Say:** “Once checkout exists, later taps cannot overwrite the official attendance.”

**Expected response:** Ignored Tap; message indicates attendance is already completed. The extra event is retained in the audit log without changing `time_in`, `time_out`, or final status.

### Step 11 — Scenario G: early first tap

**On screen:** Active session arranged before the scheduled start.

**Presenter action:** Verify Student F and tap at 7:55 AM for the 8:00 AM example class.

**Say:** “An early first tap is recorded with its actual timestamp and counts as an on-time check-in. The current implementation does not assign a separate Early status.”

**Expected response:** Check-in/Checked In; Pending until checkout and eligible for final Present.

### Step 12 — Scenario H: invalid and duplicate behavior

**On screen:** Attendance panel.

**Presenter action:** Demonstrate one or more safe validation failures:

1. Use an unassigned RFID.
2. Use a student from another section.
3. Attempt a student tap without completing verification.
4. Activate Student Logout for a student with no check-in.

**Say:** “Validation protects the integrity of attendance. Failed taps explain the reason and do not create a valid attendance endpoint.”

**Expected response:** Student not found, wrong class/section, verification required, or Invalid Tap as appropriate.

**Important note:** A repeated tap after completed checkout is Ignored, whereas a tap that fails validation is rejected or Invalid.

### Step 13 — Demonstrate Student Logout mode

**On screen:** Attendance Control Panel.

**Presenter action:** Select Student Logout, complete the instructor authorization shown by the panel, then verify and tap a student who has checked in.

**Say:** “Student Logout is an explicit override for official checkout. It is useful when an authorized instructor must release a student outside the normal checkout window.”

**Expected response:** Official Check-out with remarks stating it was recorded by the instructor Student Logout override.

## 5. Attendance Records and Reports Demonstration

### Step 14 — Open attendance logs

**On screen:** `/admin/attendance/logs` as admin, or the same attendance-log page as an instructor.

**Presenter action:** Open Attendance Logs and filter by attendance session, date, subject, instructor, or instructor RFID where allowed.

**Say:** “Attendance data is stored in the database in two complementary levels: the `attendances` table holds the consolidated student-class record, while `attendance_logs` preserves individual tap events. `attendance_sessions` identifies the room/class session.”

**Expected response:** Summary cards show Present, Late, Pending, Incomplete, Absent, and Total Records. Records are grouped by date, room, subject, session time, and instructor.

**Point out on screen:**

- Student and student number
- Subject, section, and school year
- Instructor and room
- Session date and scheduled time
- Tap timestamp and tap sequence
- Time in and time out
- Tap type
- Room status
- Validation result and remarks
- Attendance status
- Face evidence where access is available

**Important note:** Admin sees wider records; instructor access is scoped to assigned schedules.

### Step 15 — Show total attendance history

**On screen:** Student/Parent portal `/student-parent/attendance`.

**Presenter action:** Log in as a student, or as a parent and select a linked student.

**Say:** “Students and parents can review their permitted attendance history without access to other students' records.”

**Expected response:** The portal displays the selected student's attendance dates, subjects, timestamps, and statuses.

### Step 16 — Generate a report

**On screen:** `/reports`.

**Presenter action:** Select the available date range filters. Review summary cards, status breakdowns, subject breakdowns, attendance trends, and other role-aware charts. Click Export.

**Say:** “Reports are role-aware. Administrators receive system-wide analytics; instructors receive data for their assigned scope; students and parents receive their own or linked-student information.”

**Expected response:** Dashboard metrics update for the selected range. Export downloads a CSV report.

**Important note:** The attendance log page is the detailed audit view; the Reports page is the analytical summary and CSV export point.

## 6. Emergency Features Demonstration

### Step 17 — Emergency Call/Alert

**On screen:** Attendance Control Panel emergency control.

**Presenter action:** Select Emergency Call. Choose an emergency type and, when available, an active hotline. Confirm the request.

**Say:** “An emergency request is created directly from the active attendance panel. It is associated with the room and current class context so responders know where assistance is needed.”

**Expected response:** “Emergency Call Triggered” and a success alert. An `emergency_alerts` database record is created and an activity-log entry is written.

**Information stored or sent includes:**

- Emergency type and subtype
- Urgent or critical severity
- Room
- Subject code and schedule
- Triggering user/name
- Emergency message
- Selected hotline metadata
- Additional student/context metadata when supplied
- Timestamp and response status

### Step 18 — Emergency SMS

**On screen:** Same emergency request result.

**Presenter action:** Select a hotline with SMS enabled and submit an alert.

**Say:** “When Semaphore is enabled and correctly configured, the system sends an SMS to the selected active hotline. The text contains the emergency type, room, triggering person, message, and hotline name.”

**Expected response:** The alert itself is saved even if SMS cannot be delivered. The API response reports whether SMS was sent.

**Important notes:**

- SMS requires a selected active hotline, SMS enabled for that hotline, a valid phone number, Semaphore enabled, and a configured API key/endpoint.
- Do not claim successful external delivery during the defense unless the response confirms `sent: true` and the recipient device receives it.
- If configuration is missing, explain that the in-app emergency alert remains available to clinic staff.

### Step 19 — Clinic notification and response flow

**On screen:** `/clinic/dashboard` in a second signed-in browser.

**Presenter action:** Refresh or wait for the dashboard polling cycle. If prompted, click or press a key once to enable alert audio. Open the new alert, acknowledge it, dispatch a response, and later mark it resolved.

**Say:** “Clinic personnel receive the emergency alert on their dashboard. Newly received alerts can play the configured emergency sound after browser audio is enabled. Staff can acknowledge, dispatch, monitor, resolve, or cancel the case.”

**Expected response:** New emergency notification appears; sound plays when permitted. Dispatch creates or updates a linked clinic case with monitoring status. Status can move through Open, Acknowledged, Resolved, or Cancelled.

**Expected operational outcome:** Responders obtain the location and emergency details, begin the response, maintain a case record, and close the incident with an auditable status.

## 7. Additional Features Demonstration

### Step 20 — Notifications

**On screen:** `/student-parent/notifications`.

**Presenter action:** Open a notification and mark it read.

**Say:** “The portal centralizes user notifications and tracks read status.”

**Expected response:** The selected notification is displayed and its unread state is cleared.

### Step 21 — User profile management

**On screen:** `/settings/profile` for staff, or `/student-parent/profile` for a student/parent.

**Presenter action:** Edit a safe demonstration field and save it.

**Say:** “Users can maintain permitted profile information. Access and editable fields depend on role.”

**Expected response:** Validation runs and a success confirmation appears.

### Step 22 — Attendance history

**On screen:** `/student-parent/attendance`.

**Presenter action:** Review several dates and statuses.

**Say:** “This gives students and parents transparent access to attendance history, while administrative logs retain the deeper tap-level audit.”

**Expected response:** Historical attendance records appear only for the authorized student or linked students.

### Step 23 — Reports and analytics

**On screen:** `/reports`.

**Presenter action:** Change the date range and point to status distribution, attendance by subject, and trend views.

**Say:** “Analytics turn raw timestamps into decision-support information, such as attendance distributions and trends.”

**Expected response:** Charts, tables, and summary counts reflect the role and selected range.

### Step 24 — Administrator dashboard

**On screen:** `/admin/dashboard`.

**Presenter action:** Navigate briefly through Active Devices, Students, Schedules, RFID, Attendance Logs, Reports, Activity Logs, and System Settings.

**Say:** “The administrator manages the academic structure, accounts, schedules, RFID assignments, device access, thresholds, feature switches, emergency sound, records, and audits.”

**Expected response:** Each page displays the administrator's system-wide management scope.

### Step 25 — RFID, NFC, QR, and biometric capabilities

**On screen:** `/admin/rfid`, registrar enrollment pages, and student face-image management.

**Presenter action:** Show an RFID assignment and enrolled face images; do not alter production data unless planned.

**Say:** “The implemented contactless identifier is RFID. An NFC reader that outputs the enrolled tag value may work at the hardware/interface level, but NFC is not presented as a separately implemented workflow. QR attendance is not implemented in the current source. Biometric support is face recognition, subject to configuration and service availability.”

**Expected response:** RFID owners and tag assignments are visible; face enrollment shows stored student images.

**Important note:** Never claim QR, fingerprint, or generic biometric support unless a corresponding module is added and tested.

## 8. Scenario Results Summary

| Demonstration scenario | Expected tap/result | Final attendance |
|---|---|---|
| On-time check-in + valid checkout | Check-in, then Check-out | Present |
| Late check-in + valid checkout | Late-classified Check-in, then Check-out | Late |
| One tap while session remains active | Check-in only | Pending |
| Missed checkout after session ends | No `time_out` | Incomplete display or Absent/cutting after panel finalization |
| Early first tap | Check-in at actual early timestamp | Pending, then Present after valid checkout |
| Early second tap with instructor approval | Temporary Exit | Pending |
| Next early movement tap with approval | Temporary Return | Pending |
| Third tap during checkout window | Check-out if no prior official checkout | Present or Late |
| Tap after official checkout | Ignored Tap | Existing Present/Late unchanged |
| Logout without check-in | Invalid Tap | No valid attendance |
| Unassigned RFID | Rejected | No attendance |
| Wrong section/year | Rejected | No attendance |
| Missing identity verification | Verification required | No attendance endpoint |
| Eligible student never checks in | Generated No Tap record | Absent |

## 9. Suggested Closing Script

“This demonstration showed the complete attendance lifecycle: secure role-based access, scheduled class activation, RFID and face-assisted identity validation, on-time and late classification, temporary movement, official checkout, missing-tap handling, detailed audit logs, student and parent history, reports, emergency alerts, optional SMS dispatch, clinic response, notifications, profiles, and administrator controls.

The key strength of the system is that it does more than read a card. It validates the student against the active class, preserves every accepted, rejected, temporary, ignored, and completed event, and makes the resulting information available to the correct users for accountability and timely response.”

## 10. Presenter Recovery Notes

- If the camera fails, explain the configured fallback and use the assigned instructor RFID.
- If a tap is rejected, read the visible validation message; it usually identifies the missing session, wrong class, unknown RFID, or required verification.
- If checkout becomes Temporary Exit, the demonstration time is before the normal checkout window. Use Student Logout mode for an authorized demonstration or adjust the demo schedule.
- If the clinic alert has no sound, interact with the clinic page once because browsers block autoplay audio.
- If SMS is not delivered, show that the in-app alert was saved and state the returned SMS reason without claiming delivery.
- If a status remains Pending, confirm that official checkout—not merely a temporary movement—was recorded.
- Keep separate students for Present, Late, Pending, Temporary Movement, and Invalid scenarios to avoid one scenario affecting another.
