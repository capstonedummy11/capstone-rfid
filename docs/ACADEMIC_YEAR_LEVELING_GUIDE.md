# Academic Year Leveling Guide

## Purpose

Academic-year rollover moves the student placement structure forward without copying semester-specific teaching data. The source year remains available for historical attendance and subject review.

## Leveling rules

Semester-only rollover is available only from 1st Semester to 2nd Semester. Year rollover targets 2nd Semester in the destination year.

The source year’s `active_semester` determines the transition:

| Rollover type | Destination semester | Student level | Grade 12 |
|---|---|---|---|
| Semester-only | 2nd Semester | Remains in the same grade | Remains enrolled for 2nd Semester |
| Year rollover | 2nd Semester of the destination year | Grade 11 becomes Grade 12, unless retained | Graduated by default; may be retained or sent to review |

Only enrollments from the current semester are processed. Dropped, transferred, or inactive enrollments do not create a destination enrollment.

## What rollover creates

- Destination sections are created only for mapped source sections.
- Sections use the calculated destination semester.
- Grade progression is automatic; the operator cannot override it.
- Retained or promoted students receive a new destination `student_enrollments` record.
- Grade 12 students finishing 2nd Semester are marked `graduated` in the student master record and receive no destination enrollment.
- The rollover is transactional and safe to retry.

## What rollover does not create

Subjects, subject offerings, and schedules are not copied. A subject may belong to one semester, so the destination starts with:

- no destination subject offerings;
- no destination schedules; and
- no destination instructor assignments.

Administrators must add the subjects and offerings for the destination semester, assign instructors, and create schedules after rollover.

## Current-semester controls

- The Subjects page defaults to the current academic year and its active semester.
- The subject catalog does not display a stale legacy semester when no current offering exists.
- New subject offerings must use the current academic year and active semester. The semester is fixed by the current academic-year setting.
- Removing an instructor removes only the instructor assignment; the subject offering and section assignment remain.
- New schedules can use only subject offerings from the current academic year and active semester. Historical schedules remain viewable through filters but cannot be used to create a current schedule.

## Historical information

The following source-year records remain unchanged and can be viewed by administrators and instructors using the academic-year and semester filters:

- attendance and attendance sessions;
- subject and section history;
- schedules and instructor assignments;
- online classes and online attendance;
- messages, borrowing, clinic, evidence, and audit records.
- system settings and account configuration.

These records are not duplicated into the destination year. The destination’s new attendance history begins when new destination schedules and sessions are created.

System settings are global application configuration. Academic-year rollover does not copy, reset, or modify them; their values remain exactly as configured before rollover.

## Filters and defaults

Student, subject, schedule, and attendance pages expose academic-year and semester filters. On first load, the filters default to the current active academic year and its `active_semester`. Selecting a historical year and semester displays the records belonging to that academic context without changing the source data.

## Recommended operating sequence

1. Set the source academic year’s current semester.
2. Create or select a draft destination academic year.
3. Preview rollover and map the destination sections.
4. Execute rollover and review the enrollment results.
5. Add destination-semester subjects and subject offerings.
6. Assign instructors and create destination schedules.
7. Activate the destination academic year when setup is complete.
