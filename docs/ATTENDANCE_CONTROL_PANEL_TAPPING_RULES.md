# Attendance Control Panel Tapping Rules

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

This is the canonical reference for RFID tapping behavior on the Attendance Control Panel. Other documentation may summarize the flow, but this file controls when descriptions differ.

## 1. Required Session Context

A student tap can become official attendance only when:

- A console account is signed in and a room/laboratory is selected.
- An assigned instructor has started the matching scheduled session.
- The current date, weekday, room, instructor, and time match the schedule.
- The student is active and belongs to the scheduled section.
- Required face verification or an allowed instructor-approved fallback succeeds.

A failed prerequisite produces a rejected or invalid event; it does not create valid attendance.

## 2. Status and Location State

The main attendance result and the student's room location are separate:

- `Pending`: valid check-in exists, but official checkout is not complete.
- `Present`: on-time check-in plus official checkout.
- `Late`: late check-in plus official checkout.
- `Incomplete Attendance`: check-in exists but official checkout was not completed.
- `Absent`: no valid check-in, or the panel explicitly finalizes the record under its absence/cutting rules.
- `Excused`: an instructor-authorized manual correction recorded through Attendance Logs.
- `Inside` / `Outside`: current room-location state.

Temporary Exit and Temporary Return change location and create audit events. They do not replace official check-in or checkout and do not independently make a student Present or Late.

## 3. First Valid Student Tap

The first accepted student tap is `Check-in`.

- At or before the configured late threshold after scheduled start: check-in classification is `present`.
- After that threshold: check-in classification is `late`.
- The displayed final state remains `Pending` until an official checkout occurs.
- The student location becomes `Inside`.

The late threshold comes from `attendance.late_threshold_minutes` and defaults to 15 minutes.

## 4. Taps Before the Checkout Window

The normal checkout window begins 15 minutes before scheduled class end.

After check-in and before that window:

- A further tap is a temporary-movement request.
- Valid assigned-instructor RFID approval is required.
- `Inside` becomes `Temporary Exit` and changes location to `Outside`.
- `Outside` becomes `Temporary Return` and changes location to `Inside`.
- Without valid approval, movement is rejected and state does not change.
- Approved early movement can alternate on later taps.

## 5. Official Checkout

During the final 15-minute checkout window:

- Temporary movement is disabled.
- The first accepted tap from a student with a valid check-in becomes official `Check-out`.
- An on-time check-in finalizes as `Present`.
- A late check-in finalizes as `Late`.
- Location becomes `Outside`.
- Later taps are audit-only `Ignored Tap` events and do not change the final result.

## 6. Dismiss Class and Continue Class

The active assigned instructor can tap their RFID and choose `Dismiss Class`. After confirmation:

- Dismiss Class remains active until explicitly stopped.
- Every student tap is treated as a checkout attempt, even before the checkout window.
- A student with a valid check-in receives official `Check-out`.
- A student without a valid check-in receives `Invalid Tap`; Dismiss Class never invents a check-in.
- A student already checked out receives `Ignored Tap`.
- The instructor can tap again and choose `Continue Class` to restore normal rules.

Dismiss Class is for releasing the entire class, not for one student's temporary exit.

## 7. Tap Sequence Reference

| Situation | Recorded event | Result |
|---|---|---|
| First accepted tap | Check-in | Pending; Inside; classified on-time or Late |
| Later tap before checkout, approved, currently Inside | Temporary Exit | Pending; Outside |
| Later tap before checkout, approved, currently Outside | Temporary Return | Pending; Inside |
| Later tap before checkout without approval | Rejected movement | No state change |
| Tap during checkout window with prior check-in | Check-out | Final Present or Late; Outside |
| Tap during Dismiss Class with prior check-in | Check-out | Final Present or Late; Outside |
| Tap during Dismiss Class without prior check-in | Invalid Tap | No valid attendance |
| Tap after official checkout | Ignored Tap | Final status unchanged |

Tap number alone does not determine the action. Every tap evaluates check-in and checkout existence, location, current time, Dismiss Class state, verification, and instructor authorization.

## 8. Missing, Duplicate, and Invalid Taps

- No valid check-in after the attendance period results in `Absent` under the configured absence rules.
- Check-in without official checkout becomes `Incomplete Attendance` after session end unless explicitly finalized under absence/cutting rules.
- Duplicate taps never create a second main row for the same student, schedule, and date.
- Invalid, rejected, ignored, and temporary events remain in attendance logs.

## 9. Manual Corrections

Manual correction is separate from RFID tapping:

- Instructors may edit only their assigned sessions.
- Editing is allowed only within `attendance.absent_default_days`.
- Available values are Present, Late, Absent, and Excused.
- Excused requires a note.
- Every correction creates attendance and system activity log entries with the instructor and old/new status.

Manual edits do not delete or rewrite original tap evidence.

## 10. Audit Data

The system keeps one main attendance row per student, schedule, and date. Individual event records include Check-in, Temporary Exit, Temporary Return, Check-out, Invalid Tap, Ignored Tap, and Manual Edit. Event details can include sequence, time, room/device, validation, verification method, evidence paths, and remarks.
