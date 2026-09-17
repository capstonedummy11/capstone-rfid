# Academic Year and Rollover Guide

This guide explains how academic years, semesters, sections, students, and subject offerings move through the system.

Use this document when you need to understand what **Academic Rollover** does, what it does not copy, and what the admin should do before and after rollover.

## Quick Summary

Academic Rollover moves student placement forward without copying historical teaching or attendance records.

- **Full academic rollover** moves completed 2nd Semester records into 1st Semester of the next academic year.
- **Semester-only rollover** moves 1st Semester records into 2nd Semester inside the same academic year.
- Grade 11 students move to Grade 12 during full academic rollover.
- Grade 12 students are archived as graduated by default during full academic rollover.
- Subjects, subject offerings, instructors, schedules, attendance, messages, clinic records, borrowing, and audit history are not copied.

The feature is called **Academic Rollover**, not Year Rollover, because it handles both full academic-year movement and semester-only movement.

## Core Ideas

### Academic Year

An academic year is the school-year container, such as `2026-2027`.

Each academic year can be:

- `draft` - prepared before it is used for live operations;
- `active` - used for current daily operations;
- `closed` - finished but still available for historical records;
- `archived` - locked for long-term reference.

Only one academic year should be active for normal daily operations.

### Active Semester

Each academic year can have an active semester.

The active semester helps pages default to the correct current context. On the Academic Years page, the admin can change the active semester for the active academic year.

Active semester is not the same as rollover:

- active semester controls the current operating semester;
- academic rollover creates the next placement records.

### Student Enrollment

The student master record identifies the student. The `student_enrollments` record stores the student's placement for a specific academic year and semester.

That is why a student can have historical placements without overwriting the previous year.

## Rollover Modes

### Full Academic Rollover

Use full academic rollover when the school year is finished and students need to move into a draft destination academic year.

Current behavior:

- Source 2nd Semester moves to destination 1st Semester.
- Grade 11 students move to Grade 12 unless retained.
- Grade 12 students are archived as graduated by default.
- Grade 12 students do not need a destination section unless the admin changes the decision to retain or review.
- Destination sections must match the correct grade level and destination semester.

Example:

| Source                            | Destination                       |
| --------------------------------- | --------------------------------- |
| 2026-2027, Grade 11, 2nd Semester | 2027-2028, Grade 12, 1st Semester |
| 2026-2027, Grade 12, 2nd Semester | Archived as graduated             |

### Semester-Only Rollover

Use semester-only rollover when students need to move from 1st Semester to 2nd Semester inside the same academic year.

Current behavior:

- 1st Semester moves to 2nd Semester.
- Students stay in the same academic year.
- Students stay in the same grade level.
- Sections can keep the same branch or section name.

Example:

| Source                            | Destination                       |
| --------------------------------- | --------------------------------- |
| 2026-2027, Grade 11, 1st Semester | 2026-2027, Grade 11, 2nd Semester |

Semester-only rollover is unavailable when the selected academic year is already on 2nd Semester.

## Leveling Rules

Only enrollments from the source current semester are processed. Dropped, transferred, or inactive enrollments do not create a destination enrollment.

| Rollover type     | Destination semester                 | Student level                              | Grade 12                                                |
| ----------------- | ------------------------------------ | ------------------------------------------ | ------------------------------------------------------- |
| Semester-only     | 2nd Semester                         | Remains in the same grade                  | Remains enrolled for 2nd Semester                       |
| Academic rollover | 1st Semester of the destination year | Grade 11 becomes Grade 12, unless retained | Graduated by default; may be retained or sent to review |

## How the Admin Uses Rollover

1. Go to **Academic Years**.
2. Create the next academic year as a draft if doing a full academic rollover.
3. Select the rollover mode.
4. Select the source academic year.
5. Select the destination academic year for full academic rollover.
6. Click **Preview**.
7. Click **Review and edit sections & subjects**.
8. Map each source Section to an existing destination Section, or edit the name and grade of a new destination Section.
9. Review the Subject Offerings and clear any offering that should not continue into the destination semester.
10. Confirm Grade 12 students that should be archived.
11. Save the preview changes and execute the reviewed academic rollover.

The preview step is important because it lets the admin check the movement before anything is written.

## What the Preview Shows

The preview shows:

- source sections;
- destination sections;
- active Subject Offerings from the source semester;
- which Subject Offerings are selected for the destination semester;
- student counts;
- promotion, retention, archive, drop, and review counts;
- each student's recommended decision;
- section mappings by grade and section.

For Grade 12 during full academic rollover, the preview shows that the student is archived unless the admin changes the decision.

## Why Sections Are Mapped

Sections are mapped because the source section and destination section are not always the same.

For example:

- Grade 11 ICT-A may become Grade 12 ICT-A.
- Grade 11 ICT-B may become Grade 12 ICT-B.
- A student may need to be retained in the same grade level.
- A draft destination year may already have sections created.

The map tells the system exactly where each promoted or retained student should be enrolled.

## Grade 12 Archive Rule

During full academic rollover, Grade 12 students have no normal next grade level.

Because of that:

- Grade 12 sections do not require a destination section.
- Grade 12 students are marked as archived or graduated by default.
- The UI should display them as archived instead of asking for a destination section.

If a Grade 12 student failed, was retained, or needs review, the admin can change the decision and assign a valid destination section.

## What Rollover Creates

Academic rollover can create:

- destination sections when the admin provides a new destination section name;
- selected destination Subject Offerings linked to the mapped Sections;
- destination student enrollment records;
- rollover history records;
- per-student rollover result records.

The system records the rollover so the admin can review what happened later.

The rollover process is transactional and safe to retry. Retrying a completed rollover should not create duplicate student enrollment records.

## What Rollover Does Not Copy

Academic rollover does not copy historical operational records.

It does not copy:

- attendance logs;
- attendance sessions;
- schedules;
- instructor assignments;
- online classes;
- online attendance;
- messages;
- excuse letters;
- clinic records;
- borrowing records;
- inventory activity;
- evidence records;
- audit logs;
- system settings;
- account configuration.

The permanent Subject catalog is reused rather than duplicated. The admin can include or exclude each active source Subject Offering during preview. Selected offerings are created for the mapped destination Section and semester without copying the old Instructor or Schedule. This keeps historical teaching assignments clean while avoiding repetitive Subject setup.

## Subject Offering and Schedule Setup

After rollover, administrators should complete the teaching setup for the destination semester.

- The Subjects page can filter sections by academic year and grade level before selecting a section.
- Subject offerings inherit their academic year and semester from the selected section.
- Rollover-created Subject Offerings have no Instructor assignment until an administrator assigns one.
- Removing an instructor removes only the instructor assignment; the subject offering and section assignment remain.
- New Schedules should use the reviewed rollover Subject Offerings from the intended academic year and semester.
- Historical schedules remain viewable through filters but should not be reused as current schedules.

## Historical Information

Historical records stay attached to their original academic year and semester.

Admins and instructors can view historical information by using academic-year and semester filters on supported pages, including:

- dashboards;
- sections;
- students;
- subjects;
- schedules;
- attendance;
- reports;
- online-class logs;
- activity logs.

Student and parent portal dashboards remain student-scoped and do not expose the full administrative academic-context filter.

## Online Classes Setting

Online Classes has its own feature setting.

When **Online Classes Enabled** is off:

- Online Classes is hidden from navigation;
- Online Class Logs is hidden from navigation;
- direct log or export access returns not found.

## Recommended Operating Sequence

1. Set the source academic year's active semester.
2. Create or select a draft destination academic year.
3. Preview rollover and map destination sections.
4. Execute rollover and review enrollment results.
5. Add destination-semester subjects and subject offerings.
6. Assign instructors.
7. Create destination schedules.
8. Activate the destination academic year when setup is complete.
