# Attendance System: Blank-State Setup and Complete Demonstration Guide

This guide assumes the application and database migrations are already installed, but the database contains only one account: **`root.admin`**. There are no laboratories, strands, sections, subjects, instructors, students, schedules, RFID assignments, face images, emergency types, hotlines, attendance sessions, or reports.

The goal is to build a fully usable system from that blank state and then demonstrate the complete attendance lifecycle.

## 1. What “Fully Running” Means

The system is ready for attendance only when all of the following exist:

1. Role accounts for administration, registrar, instructor, console, clinic, student, and optionally parent use.
2. At least one laboratory.
3. At least one strand, section, and subject.
4. An instructor record linked to an instructor user.
5. At least one active student in the scheduled section.
6. A schedule linking the subject, section, instructor, laboratory, weekday, start time, and end time.
7. RFID tags assigned to the instructor and students.
8. Face images enrolled, or a configured instructor-authorized fallback.
9. Attendance rules and panel access configured.
10. Emergency types and hotlines configured.
11. A console logged into the selected room.
12. An active class started by the assigned instructor.

The setup order matters. A schedule cannot be created correctly until its laboratory, section, subject, and instructor already exist.

## 2. Recommended Blank-State Build Order

```text
root.admin login
      |
      v
Create role accounts
      |
      v
Configure system rules and panel access
      |
      v
Create laboratories
      |
      v
Create strands -> sections -> subjects
      |
      v
Create instructor profiles and student records
      |
      v
Create schedules
      |
      v
Assign RFID and enroll faces
      |
      v
Configure emergency types and hotlines
      |
      v
Open console -> select room -> start class
      |
      v
Verify students -> check in -> check out
      |
      v
Review logs, portals, reports, and emergency response
```

## 3. Phase 1 — First Root Administrator Login

### Step 1. Log in

**On screen:** Staff login page.

**Presenter action:** Enter the credentials of `root.admin`.

**Presenter explanation:** “The system starts with one protected root administrator. This account establishes ownership and creates the first operational users.”

**Expected response:** The administrator dashboard opens. Most counts and tables are empty.

**Important rules:**

- Keep at least one root administrator.
- Only a root administrator can create, promote, update, or delete administrator accounts.
- Do not use the root account as the daily console or clinic account.
- Change the initial password and configure profile/security settings before production use.

### Step 2. Show the empty state

**On screen:** `/admin/dashboard`.

**Presenter action:** Open Students, Schedules, Attendance Logs, and Reports briefly.

**Presenter explanation:** “These pages are empty because the system has no operational data yet. We will create data in dependency order.”

**Expected response:** Zero totals, empty tables, or no-record messages.

## 4. Phase 2 — Create Required User Accounts

Open **Admin > User Management** at `/admin/users`.

Create these minimum accounts:

| Account | Role | Purpose |
|---|---|---|
| Secondary administrator | Admin | Daily configuration and management |
| Registrar | Registrar | RFID and face enrollment |
| Instructor | Instructor | Starts class, authorizes fallbacks and movement |
| Attendance station | Console | Operates the room attendance panel |
| Clinic responder | Clinic | Receives and manages emergency alerts |
| Student portal user | Student | Views personal attendance and notifications |
| Parent user, optional | Parent | Views linked-student information |

For each account:

1. Click **Add User**.
2. Enter name, email, password, and role.
3. Enable root administrator only for an intentionally authorized admin.
4. Save.
5. Confirm the account appears with the correct role.

**Presenter explanation:** “Role separation prevents the attendance station, registrar, instructor, clinic, and students from receiving unnecessary administrative access.”

**Expected response:** User list contains all operational roles.

**Important note:** Creating an instructor user is not the same as completing the instructor's academic profile. The instructor record is configured in the instructor management flow.

## 5. Phase 3 — Configure Core System Settings

Open `/admin/settings`.

### Attendance configuration

1. Set the **late threshold**. The implementation defaults to 15 minutes.
2. Set the default number of days used when generating absent attendance views.
3. Enable face recognition only when its service is available and configured.
4. Configure demo attendance controls only for a controlled presentation environment.
5. Save settings.

### Panel and device access

Open `/admin/active-devices`.

1. Review panel access settings.
2. Configure the panel PIN if required.
3. Confirm the attendance console role can open the panel.
4. Note that administrators can force-log out a panel later.

### Recommended production values

- Late threshold: institution-approved value, commonly 15 minutes.
- Face recognition: enabled only after enrollment and service testing.
- Panel PIN: enabled and protected.
- Demo RFID controls: disabled outside demonstrations.
- Emergency sound: select the default or upload an approved sound.

**Expected response:** Settings persist and control the later attendance workflow.

## 6. Phase 4 — Build the Academic Structure

### Step 1. Create a laboratory

Open `/admin/laboratories`.

1. Click **Add Laboratory**.
2. Enter a name such as `Laboratory 1`.
3. Enter its building/location and description.
4. Set it Active.
5. Save.

**Why first:** The schedule and attendance panel need a valid room.

### Step 2. Create a strand

Open `/admin/strands`.

1. Click **Add Strand**.
2. Enter a code such as `TVL-ICT`.
3. Enter the full strand name and department.
4. Set it Active.
5. Save.

### Step 3. Create a section

Open `/admin/sections`.

1. Click **Add Section**.
2. Select the strand.
3. Enter a section such as `ICT 11-A`.
4. Set year level, semester, school year, and Active status.
5. Save.

### Step 4. Create a subject

Open `/admin/subjects`.

1. Click **Add Subject**.
2. Enter subject code and name.
3. Select the section and relevant instructor fields if shown.
4. Set the year level and description.
5. Save.

**Presenter explanation:** “The hierarchy is strand, section, subject, and schedule. Attendance validation later checks whether the tapped student belongs to the active section and, when configured, the correct year level.”

**Expected response:** Each record becomes available as a selection in downstream forms.

## 7. Phase 5 — Create Instructors, Students, and Parents

### Step 1. Complete the instructor record

Open `/admin/instructors`.

1. Select or create the instructor connected to the instructor user.
2. Assign an instructor number.
3. Assign the relevant strand or department.
4. Set status to Active.
5. Save.

### Step 2. Create students

Open `/admin/students`.

For each student:

1. Click **Add Student**.
2. Enter student number and full name.
3. Select strand and section.
4. Set year level, school year, and Active status.
5. Enter contact or portal information supported by the form.
6. Save.

Create several students for a complete demonstration:

- Student A — on-time scenario
- Student B — late scenario
- Student C — missing checkout
- Student D — temporary exit/return
- Student E — invalid/wrong-section scenario

### Step 3. Create or link parent accounts

From the student management page:

1. Open the student's parent/guardian controls.
2. Add or link a parent account.
3. Confirm the parent is connected to the correct student.

**Expected response:** Student and linked-parent records appear in their authorized portals.

## 8. Phase 6 — Create the Schedule

Open `/admin/schedules`.

1. Click **Add Schedule**.
2. Select the laboratory.
3. Select the section.
4. Select the subject.
5. Select the assigned instructor.
6. Choose the active weekdays.
7. Enter class start and end times.
8. Save.

Example:

| Field | Demo value |
|---|---|
| Laboratory | Laboratory 1 |
| Section | ICT 11-A |
| Subject | Computer Programming 1 |
| Instructor | Assigned demo instructor |
| Weekday | Current day |
| Start | 8:00 AM |
| End | 10:00 AM |
| Late boundary | 8:15 AM with a 15-minute threshold |
| Normal checkout start | 9:45 AM |

**Critical checks:**

- The current date's weekday must match the schedule.
- The room must match the console's selected room.
- Student section must match schedule section.
- Subject year level must match the student when year-level validation is set.
- Instructor RFID must belong to the instructor assigned to this schedule.

## 9. Phase 7 — Enroll RFID and Face Identity

Log out as root admin and log in as the registrar, or use the relevant admin enrollment screens.

### Student RFID and face enrollment

Open `/registrar/biometric-enrollment`.

1. Search for the student.
2. Scan or enter a unique student RFID.
3. Save the assignment.
4. Capture or upload clear face images.
5. Confirm the student shows an RFID and enrolled face images.

### Instructor RFID and face enrollment

Open `/registrar/instructor-face-enrollment`.

1. Select the instructor user.
2. Assign a unique instructor RFID.
3. Capture or upload instructor face images.
4. Confirm the assignments.

### Enrollment rules

- Never assign one RFID to multiple people.
- Test the physical reader before the live defense.
- Capture well-lit, forward-facing images.
- Instructor RFID is essential: it starts class, authorizes permitted fallbacks, approves temporary movement, activates Dismiss Class, and restores Continue Class.
- Face recognition depends on configuration and recognition-service availability.
- RFID is implemented. QR attendance is not implemented.
- NFC is not a separate application workflow; compatible hardware may only act as a tag reader if it outputs the enrolled identifier.

## 10. Phase 8 — Configure Emergency Operations

Log in as the clinic account.

### Create emergency types

Open `/clinic/dashboard`.

Create types such as:

- Medical Emergency
- Injury
- Fire
- Earthquake
- Security Incident

For each type, enter:

1. Name
2. Category
3. Default message
4. Active status
5. Sort order

Disaster-category alerts are recorded as Critical; other types are generally Urgent.

### Create emergency hotlines

Open `/clinic/emergency-hotlines`.

1. Add the hotline name and category.
2. Enter phone number and contact person.
3. Enable SMS only when the recipient has approved it.
4. Set the hotline Active.
5. Save.

### Configure live SMS

Live SMS requires:

1. Semaphore enabled in application configuration.
2. A valid Semaphore API key and endpoint.
3. An active selected hotline.
4. SMS enabled for that hotline.
5. A valid recipient number.

The in-app emergency record is still saved if SMS is unavailable or fails.

## 11. Phase 9 — Open the Attendance Console

Log in as the console account.

1. Open `/attendance-control-panel/login`.
2. Enter the panel PIN if enabled.
3. Select `Laboratory 1`.
4. Confirm the panel is Online and listening.
5. Present the assigned instructor RFID.
6. Start Attendance mode for the displayed class.

**Expected response:** The subject, section, instructor, room, and schedule are displayed. An attendance session is active.

If no schedule appears, check the room, weekday, time, instructor assignment, and schedule status.

## 12. Complete Student Tap Rules

### 12.1 Two official attendance endpoints

A complete normal attendance record needs:

1. **Check-in** — first accepted student tap.
2. **Check-out** — later accepted student tap when a checkout condition is met.

Physical tap count and official endpoint count are not always identical because temporary movement taps can occur between check-in and check-out.

### 12.2 First student tap

Before the tap is recorded, the system validates:

- Active attendance session exists.
- RFID belongs to a student.
- Student belongs to the active schedule section.
- Year level matches when the subject restricts it.
- Face verification or an accepted instructor fallback is current.

If valid:

- Saves actual `time_in`.
- Sets room state to Inside.
- Sets tap sequence to 1.
- Leaves main status Pending until official checkout.
- Stores check-in classification as Present or Late.

### 12.3 On-time, late, and early first tap

With an 8:00 AM start and 15-minute threshold:

- Before 8:00 AM: early timestamp is stored; treated as on-time.
- 8:00 AM through exactly 8:15 AM: on time.
- Later than 8:15 AM: Late.

There is no separate final **Early Tap** status in the implementation.

### 12.4 Second student tap

The second physical tap can have different results.

#### A. At or after the checkout window

The normal checkout window starts 15 minutes before scheduled class end.

- Second tap is accepted as official Check-out.
- Saves `time_out`.
- Sets room state Outside.
- Final status becomes Present or Late based on the first tap.

#### B. Before the checkout window

Under ordinary face verification:

- It is not immediately accepted as official checkout.
- Panel requests the assigned instructor RFID.
- With instructor approval, it becomes Temporary Exit if the student is Inside.
- Without instructor approval, no movement tap is recorded.
- Attendance remains Pending.

#### C. Dismiss Class mode

When the authorized instructor activates Dismiss Class:

- A confirmation modal states that all student taps will be treated as checkout attempts.
- Every checked-in student's next tap becomes official Check-out, even before the normal checkout window.
- A student who never checked in does not receive a check-in; the system records Invalid Tap.
- A student who already checked out receives Ignored Tap and the completed attendance remains unchanged.
- The mode stays active across student taps.
- When the instructor taps again, the action menu appears. Choosing Continue Class disables Dismiss Class and restores normal check-in, temporary-movement, and timed-checkout rules.
- Remarks identify the instructor logout override.

#### D. Accepted verification fallback

Certain implemented fallback methods can cause a later tap to be accepted as official checkout, including:

- Instructor RFID verification
- Face recognition disabled
- Approved camera-session override
- Captured image with recognition service unavailable

Use these only according to institutional policy.

### 12.5 Third student tap

A third physical tap is not automatically ignored. The system evaluates the attendance state.

Example 1:

1. Tap 1 — Check-in.
2. Tap 2 — Temporary Exit before checkout window, instructor approved.
3. Tap 3 — During checkout window.
4. Result — Tap 3 becomes official Check-out.

Example 2:

1. Tap 1 — Check-in.
2. Tap 2 — Temporary Exit.
3. Tap 3 — Still before checkout window, instructor approved.
4. Result — Temporary Return; attendance remains Pending.

Example 3:

1. Tap 1 — Check-in.
2. Tap 2 — Official Check-out.
3. Tap 3 — Any later time.
4. Result — Ignored Tap; completed attendance remains unchanged.

### 12.6 Fourth and later taps

Before official checkout:

- Every pre-window, instructor-approved movement alternates room state:
  - Inside -> Temporary Exit -> Outside
  - Outside -> Temporary Return -> Inside
- A tap becomes official checkout once checkout timing, Dismiss Class, or an accepted fallback satisfies checkout rules.

After official checkout:

- Every additional tap is logged as Ignored Tap.
- `time_in`, `time_out`, and final Present/Late status do not change.
- A later tap never starts a second attendance record for the same student, schedule, and date.

### 12.7 Missed checkout

- While the session is active: Check-in without checkout displays Pending.
- After an ended session: the display logic can show Incomplete Attendance.
- When the panel explicitly finalizes open attendance while leaving Attendance mode or logging out, open records are marked Absent with completion reason `cutting`.
- An eligible student with no valid check-in in a completed session appears as Absent/No Tap.

### 12.8 Invalid, rejected, and ignored taps

| Situation | Result |
|---|---|
| Unknown/unassigned RFID | Rejected; student not found |
| No active attendance session | Rejected |
| Student is in another section | Rejected |
| Year level mismatch | Rejected |
| Missing/expired identity verification | Verification required |
| Temporary movement without assigned instructor RFID | Authorization requested; no movement saved |
| Dismiss Class tap without prior check-in | Invalid Tap |
| Any tap after official checkout | Ignored Tap |

### 12.9 Complete status and event reference

| Label | Category | Exact meaning |
|---|---|---|
| Checked In | Panel response | First valid tap saved |
| Pending | Attendance status | Check-in exists, active session, no official checkout |
| Present | Final status | On-time check-in and official checkout |
| Late | Final status | Late check-in and official checkout |
| Incomplete Attendance | Display status | Ended session has check-in but no checkout |
| Absent | Final/generated status | No check-in, or open record finalized as cutting |
| Temporary Exit | Tap event | Authorized early movement from Inside to Outside |
| Temporary Return | Tap event | Authorized early movement from Outside to Inside |
| Invalid Tap | Tap outcome | Validation or logout-state rule failed |
| Ignored Tap | Tap outcome | Attendance already has official checkout |

### 12.10 Tap decision flowchart

```text
Student RFID presented
        |
        v
Active session + correct class + valid identity?
        | No
        +----> Reject / Invalid; do not create valid endpoint
        |
       Yes
        |
        v
Does attendance already exist?
        | No
        +----> Check-in -> Pending
        |       | within grace -> Present classification
        |       + after grace  -> Late classification
        |
       Yes
        |
        v
Official checkout already exists?
        | Yes
        +----> Ignored Tap; completed record unchanged
        |
       No
        |
        v
Checkout window, Dismiss Class, or accepted fallback?
        | Yes
        +----> Official Check-out -> Present or Late
        |
       No
        |
        v
Assigned instructor authorizes temporary movement?
        | No
        +----> No movement saved
        |
       Yes
        +----> Inside: Temporary Exit
               Outside: Temporary Return
               Status remains Pending
```

## 13. Live Attendance Demonstration Scenarios

For every student tap, complete the face check or authorized fallback first.

### Scenario A — Normal Present

1. Student A taps within the grace period.
2. Panel shows Check-in/Checked In and Pending.
3. Student A taps during checkout window.
4. Panel records Check-out.
5. Final status: Present.

### Scenario B — Late

1. Student B first taps after the late boundary.
2. Panel records late-classified check-in.
3. Student B completes official checkout.
4. Final status: Late.

### Scenario C — One tap only

1. Student C checks in.
2. Do not perform checkout.
3. While active: Pending.
4. After session end: Incomplete display or Absent/cutting after explicit panel finalization.

### Scenario D — Second and third movement taps

1. Student D checks in.
2. Before checkout window, Student D taps again.
3. Instructor approves: Temporary Exit.
4. Student D taps a third time before checkout window.
5. Instructor approves: Temporary Return.
6. Student D later taps during checkout window.
7. Official Check-out; final Present or Late.

### Scenario E — Third tap becomes checkout

1. Tap 1: Check-in.
2. Tap 2: Temporary Exit with instructor approval.
3. Tap 3: occurs during checkout window.
4. Tap 3 is official Check-out.

### Scenario F — More taps after completion

1. Complete check-in and checkout.
2. Tap a third, fourth, or later time.
3. Each becomes Ignored Tap.
4. Original official timestamps and final status remain unchanged.

### Scenario G — Invalid and rejected scans

Demonstrate:

- Unassigned RFID
- Wrong-section student
- Missing face/instructor verification
- Dismiss Class checkout without check-in

Explain that failure messages protect record integrity.

## 14. Attendance Records, History, and Reports

### Administrator or instructor logs

Open `/admin/attendance/logs`.

Show:

- Present, Late, Pending, Incomplete, Absent, and Total summaries
- Student and student number
- Subject, section, school year, instructor, and room
- Session date and time
- Tap timestamp and sequence
- Time in and time out
- Tap type and room status
- Validation result and remarks
- Face evidence when available

Data is stored at three levels:

- `attendance_sessions` — active/completed room and class session
- `attendances` — consolidated student/schedule/date result
- `attendance_logs` — individual tap audit events

Admin sees broad records. Instructor records are scoped to assigned schedules.

### Student and parent history

Open `/student-parent/attendance`.

- Student sees personal attendance.
- Parent sees linked-student attendance.
- Neither role receives system-wide student records.

### Reports

Open `/reports`.

1. Select a date range.
2. Review summary totals and attendance distribution.
3. Review subject and attendance-trend views.
4. Click Export.

**Expected response:** Role-aware charts/tables and a CSV export.

## 15. Emergency Demonstration

### Send an emergency request

From the Attendance Control Panel:

1. Select Emergency Call.
2. Choose an emergency type.
3. Choose an active hotline when available.
4. Confirm.

The saved alert can include:

- Type, subtype, severity, and message
- Room, subject, and schedule
- Triggering user/name
- Hotline metadata
- Student/context metadata when supplied
- Timestamp and response status

### SMS behavior

If Semaphore and the selected hotline are fully configured, the SMS contains:

- Emergency type
- Room
- Triggering person
- Emergency message
- Hotline name

Do not claim delivery unless the service response says `sent: true` and the recipient confirms receipt.

### Clinic response

On `/clinic/dashboard`:

1. Click or press a key once to allow browser audio.
2. Wait for or refresh the new alert.
3. Acknowledge it.
4. Dispatch a response.
5. Monitor the linked clinic case.
6. Resolve or cancel it as appropriate.

The alert can move through Open, Acknowledged, Resolved, or Cancelled.

## 16. Additional Feature Walkthrough

| Feature | Demonstration |
|---|---|
| Notifications | Open `/student-parent/notifications`; read and mark a message as read |
| Profile | Open staff `/settings/profile` or student/parent profile; update a permitted field |
| Attendance history | Open student/parent attendance and review dates/statuses |
| Reports | Filter `/reports` and export CSV |
| Administrator dashboard | Review users, devices, academic setup, RFID, attendance, audit logs, and settings |
| Activity logs | Show that sensitive operational actions are auditable |
| RFID | Show enrolled student and instructor tags |
| Face recognition | Demonstrate enrolled images and live verification when configured |
| QR | State accurately that QR attendance is not implemented |
| NFC | State accurately that no separate NFC workflow is implemented |

## 17. Presenter-Ready End-to-End Script

Use this short sequence during a capstone defense:

1. **Empty dashboard:** “Only root.admin exists. We will build every dependency from scratch.”
2. **Users:** “We create separate administrator, registrar, instructor, console, clinic, student, and parent roles.”
3. **Settings:** “We establish the late threshold, verification mode, panel access, and emergency sound.”
4. **Academic setup:** “We create the laboratory, strand, section, subject, and then the schedule.”
5. **People:** “We complete the instructor profile, enroll students, and link parents.”
6. **Identity:** “The registrar assigns unique RFID tags and face images.”
7. **Emergency setup:** “Clinic staff define response types and active hotlines.”
8. **Console:** “The console selects the room; the assigned instructor starts the scheduled attendance session.”
9. **First tap:** “The first valid student tap is check-in. It retains Present or Late classification but remains Pending.”
10. **Second tap:** “During the checkout window it completes attendance. Before that window it needs instructor authorization and normally records Temporary Exit.”
11. **Third or later tap:** “Before checkout, taps can alternate Temporary Return and Exit. Once checkout is allowed, the next valid tap becomes official checkout. All taps after completion are ignored but audited.”
12. **Records:** “Logs preserve official timestamps, every tap event, validation, room state, and status.”
13. **Emergency:** “The panel creates a location-aware alert, optionally sends SMS, and notifies clinic responders.”
14. **Reports:** “Students, parents, instructors, and administrators receive appropriately scoped history and analytics.”

## 18. Closing Statement

“Starting with only the root administrator, we configured users, security rules, academic structure, instructors, students, schedules, identity enrollment, emergency response, and the attendance console. The final system validates who tapped, whether the student belongs to the active class, when the tap occurred, whether movement or checkout is allowed, and how every event should be recorded. It then makes those results available through controlled logs, portals, reports, notifications, and emergency workflows.”

## 19. Live-Demo Recovery Notes

- No schedule shown: verify room, weekday, time, section, subject, and instructor.
- Student rejected: verify RFID assignment, section/year, active status, and identity verification.
- Second tap becomes Temporary Exit: the current time is before checkout window; use authorized Dismiss Class for class-wide early checkout.
- Status remains Pending: no official checkout exists.
- Face recognition fails: use the institution-approved instructor fallback.
- No emergency sound: interact with the clinic page once to satisfy browser autoplay restrictions.
- SMS fails: show the saved in-app alert and report the returned reason accurately.
- Use different students for Present, Late, Missing Checkout, Movement, and Invalid scenarios.
