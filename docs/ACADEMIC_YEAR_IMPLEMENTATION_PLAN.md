# Academic Year Implementation Plan

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

Related planning references:

- [Academic Year Implementation Impact Map](ACADEMIC_YEAR_IMPLEMENTATION_IMPACT.md)
- [Page Files and Database Impact Map](PAGE_DATABASE_IMPACT_MAP.md)

This runbook defines the recommended implementation order for safe academic-year management. Each phase has a completion gate. Do not proceed to the next phase until its gate passes.

## Current Implementation Progress

- Phase 1 foundation is implemented: academic-year CRUD/lifecycle, one active year, closure, archive, audited reopen, admin page, navigation, and tests.
- Phase 2 foundation is implemented: `student_enrollments`, existing-data backfill, dual-write compatibility from Student Management, enrollment-history relationships/UI, and preservation tests.
- Phase 3 is implemented: sections link to academic years, section-name uniqueness is scoped by year and semester, the page supports year filtering, and closed/archived sections are locked against normal edits and deletion.
- Phase 4 is implemented: reusable subject catalog records now have year/semester/section/instructor-specific `subject_offerings`, existing assignments are backfilled, additional-year offerings can be added, and closed offerings are protected.
- Phase 5 is implemented: schedules store academic-year and subject-offering context, new schedule forms derive academic fields from offerings, closed-year schedules are locked, and live attendance schedule resolution uses only the active year.
- Phase 6 is implemented: physical sessions/results/tap logs/panel sessions persist year, offering, and enrollment context; existing records are backfilled; live eligibility and historical rosters use enrollment records; and closed-year physical attendance is read-only.
- Phase 7 is implemented: online classes, participation, finalization, and notifications persist year/offering/enrollment context; rosters come from historical enrollments; and closed-year classes are visible but read-only and cannot be joined.
- Phase 8 is implemented: registrar student lists default to enrollment-backed active-year placement; portal identity/attendance/classes/notifications use enrollment context; attendance and online classes support historical-year selection; and new excuse letters retain year/enrollment context.
- Phase 9 is implemented: shared reports default to the active year, authorize historical selection, support staff-level all-years views, apply identical filters to CSV exports, and label/filter admin and instructor dashboards by academic year.
- Phase 10 is implemented: admins can preview rollovers without writes, explicitly map destination sections, transactionally copy approved configuration and enrollment decisions, review stored per-student outcomes, and safely retry completed rollovers without duplicates.
- Phase 11 transition is implemented: new student placement and subject assignment writes use normalized tables only, legacy columns are nullable/deprecated, compatibility reads are counted in an admin-visible monitor, and a rollback/removal gate is documented. Physical column removal is intentionally deferred until production monitoring completes.
- Phase 12 automated verification is implemented: lifecycle and rollover tests cover activation and authorization, a repeatable integrity command checks normalized data, and the retained release checklist separates automated evidence from hardware/provider/manual deployment checks.

## Target Result

The finished system must:

1. Maintain one permanent student identity while storing a separate enrollment for each academic year and semester.
2. Allow section names and subject offerings to be reused in later years.
3. Keep schedules, physical attendance, online attendance, and reports attached to their original academic year.
4. Let administrators prepare a draft year, activate it, close it, and review historical years.
5. Promote or re-enroll students through a controlled rollover process.
6. Prevent ordinary changes to closed-year records.
7. Preserve all existing records during migration.

## Non-Negotiable Safety Rules

- Back up the database and uploaded files before applying migration changes.
- Never use `migrate:fresh`, destructive resets, or production seeding during this implementation.
- Add new schema and compatibility reads before removing legacy columns.
- Do not copy attendance, messages, clinic data, excuse letters, borrowing transactions, or audit logs during rollover.
- Run data migrations inside transactions where supported.
- Make backfill commands idempotent so they can be inspected and safely retried.
- Compare row counts and orphan counts before and after every data migration.
- Keep closed-year data readable but read-only through normal application routes.

## Phase 0 — Establish the Baseline

### Step 0.1: Create a working branch and database backup

1. Create a dedicated implementation branch.
2. Export the current database.
3. Back up Laravel storage containing faces, evidence, attachments, and generated documents.
4. Record the current application commit and migration status.

### Step 0.2: Record baseline counts

Capture counts for at least:

- `students`
- `sections`
- `subjects`
- `schedules`
- `attendance_sessions`
- `attendances`
- `attendance_logs`
- `online_classes`
- `online_class_attendances`
- `student_excuse_letters`
- `parent_student_links`
- `clinic_cases`
- `borrowings`

Also record:

- distinct `sections.school_year` values;
- distinct `students.school_year` values;
- students whose school year differs from their section;
- schedules with missing sections or subjects;
- attendance rows with missing students or schedules;
- duplicate section names and subject codes; and
- null or malformed school-year labels.

### Step 0.3: Run the existing verification suite

Run:

```bash
composer test:requested-features
php artisan test --compact
npm run build
git diff --check
```

Record existing failures separately so they are not confused with academic-year regressions.

### Phase 0 gate

- Database and storage backups exist.
- Baseline counts and anomalies are recorded.
- Existing test/build status is known.
- No production data has been changed.

## Phase 1 — Add Academic Year Records

### Step 1.1: Create the `academic_years` table

Recommended fields:

| Field | Purpose |
|---|---|
| `academic_year_id` | Primary key |
| `name` | Unique label such as `2026-2027` |
| `starts_on` | First calendar date |
| `ends_on` | Last calendar date |
| `status` | `draft`, `active`, `closed`, or `archived` |
| `active_semester` | Optional current semester |
| `activated_at` / `activated_by_user_id` | Activation audit |
| `closed_at` / `closed_by_user_id` | Closure audit |
| `reopened_at` / `reopened_by_user_id` | Exceptional reopening audit |
| timestamps | Creation/update audit |

Enforce only one active year. Because database engines differ in partial-index support, enforce this in a transaction and application service even if a database constraint is also available.

### Step 1.2: Create the model and service

Add:

- `app/Models/AcademicYear.php`
- `app/Services/AcademicYearService.php`

The service should provide:

- `active()` and `requireActive()`;
- draft creation and validation;
- atomic activation;
- closure checks;
- audited reopen behavior; and
- `isWritable()` for mutation protection.

### Step 1.3: Backfill academic-year rows

1. Collect distinct non-empty school-year labels from sections and students.
2. Normalize only formatting differences that are unquestionably equivalent.
3. Create one academic-year row per distinct valid label.
4. Mark the intended current year active only after administrator review.
5. Leave older years closed or archived according to the agreed policy.

Do not guess an active year solely from the lexicographically greatest label when real data is available for review.

### Step 1.4: Add automated tests

Test that:

- duplicate year names are rejected;
- invalid date ranges are rejected;
- only one year can be active;
- activating a draft deactivates or closes the previous active year according to policy;
- closed years are not writable; and
- reopen requires a reason and creates an activity log.

### Phase 1 gate

- Every existing school-year label maps to an `academic_years` row.
- Exactly one intended year is active, or the system explicitly has no active year.
- Lifecycle tests pass.
- Existing pages still work using legacy fields.

## Phase 2 — Introduce Student Enrollment History

### Step 2.1: Create `student_enrollments`

Recommended fields:

| Field | Purpose |
|---|---|
| `student_enrollment_id` | Primary key |
| `student_id` | Permanent student identity |
| `academic_year_id` | Academic year |
| `section_id` | Placement for this enrollment |
| `strand_id` | Strand at this time |
| `year_level` | Grade level at this time |
| `semester` | Semester/term |
| `status` | `enrolled`, `promoted`, `retained`, `graduated`, `dropped`, `transferred`, etc. |
| `enrolled_at` / `ended_at` | Enrollment period timestamps |
| timestamps | Audit fields |

Start with a uniqueness rule such as:

```text
student_id + academic_year_id + semester
```

If the school permits section transfers during one semester, add effective-date history or a placement-history table rather than allowing silent overwrites.

### Step 2.2: Create model relationships

Add `StudentEnrollment` and relationships:

- Student has many enrollments.
- Student has one current enrollment resolved through the active year/semester.
- Enrollment belongs to student, academic year, section, and strand.
- Section has many enrollments.

### Step 2.3: Backfill current student placement

For every student:

1. Resolve the academic-year row from `students.school_year`.
2. Validate the student's section and its school year.
3. Create an enrollment using existing section, strand, year level, semester, and status.
4. Flag mismatches for manual review instead of silently choosing one value.

The command must support a dry-run mode that reports planned inserts and anomalies without writing.

### Step 2.4: Add compatibility accessors

During transition:

- read current placement from `student_enrollments` when available;
- fall back to legacy student columns when no enrollment exists;
- keep legacy writes synchronized temporarily; and
- log or test every fallback so incomplete migration remains visible.

### Step 2.5: Test historical isolation

Create a test student with:

- Grade 11 enrollment in Year A;
- Grade 12 enrollment in Year B; and
- attendance in both years.

Verify that creating Year B enrollment does not change Year A section, year level, roster, attendance, or exports.

### Phase 2 gate

- Every applicable student has a valid enrollment.
- All placement mismatches are resolved or explicitly quarantined.
- Student promotion no longer requires overwriting the prior enrollment.
- Existing identity, RFID, face, account, and parent links remain unchanged.

## Phase 3 — Make Sections Year-Specific

### Step 3.1: Add `academic_year_id` to sections

1. Add it as nullable initially.
2. Backfill it from `sections.school_year`.
3. Validate that every section resolves to a year.
4. Make the foreign key required after successful validation.

### Step 3.2: Replace global uniqueness

Replace controller-level global section-name uniqueness with a scoped rule such as:

```text
academic_year_id + semester + section_name
```

This permits `ICT 11-A` in multiple years but prevents duplicate `ICT 11-A` records within the same year and semester.

### Step 3.3: Protect historical sections

- Normal users cannot edit or delete a section belonging to a closed year.
- A section with historical attendance or online-class references cannot be hard-deleted.
- Prefer inactive/archive state over deletion.
- Reopening or exceptional correction must be audited.

### Step 3.4: Update the Sections page

Update `resources/js/pages/Auth/Admin/Sections.vue` to:

- display and filter by academic year;
- default to the active year;
- allow viewing closed years;
- disable mutation controls for closed years; and
- show why a record is locked.

### Phase 3 gate

- The same section name can exist in different academic years.
- Same-year duplicates remain blocked.
- Closed-year sections cannot be normally changed or deleted.
- Existing sections and relationships remain intact.

## Phase 4 — Separate Subject Catalog from Subject Offerings

### Step 4.1: Keep `subjects` as the catalog

The catalog should store stable subject identity:

- subject code;
- subject name;
- description;
- default units; and
- department/category where applicable.

It should not represent one specific yearly section assignment.

### Step 4.2: Create `subject_offerings`

Recommended fields:

- `subject_offering_id`;
- `academic_year_id`;
- `subject_id`;
- `section_id`;
- `instructor_id`;
- `semester`;
- status; and
- timestamps.

Recommended uniqueness:

```text
academic_year_id + semester + section_id + subject_id
```

If team teaching or multiple offerings of the same subject are required, add an offering code or group key rather than weakening history guarantees.

### Step 4.3: Backfill offerings

For every existing subject/section assignment:

1. Resolve the section's academic year.
2. Resolve the catalog subject.
3. Resolve the instructor from the existing subject or schedule data.
4. Create the offering.
5. Flag inconsistent instructor assignments for review.

### Step 4.4: Update subject management

Update `Auth/Admin/Subjects.vue` and its controller so administrators can:

- manage reusable catalog subjects;
- create yearly offerings;
- filter offerings by year/semester;
- reuse a catalog subject in a later year; and
- view but not normally edit closed-year offerings.

### Phase 4 gate

- Existing subjects have a valid catalog entry and offering.
- One catalog subject can be offered in multiple years.
- Historical offering assignments remain unchanged when a new offering is created.

## Phase 5 — Make Schedules Year- and Offering-Aware

### Step 5.1: Extend schedules

Add:

- `academic_year_id`;
- `subject_offering_id`; and
- explicit semester if required for snapshots/filtering.

Retain `section_id` and `subject_code` temporarily for compatibility.

### Step 5.2: Backfill schedules

For each schedule:

1. Resolve its section.
2. Resolve the section's academic year.
3. Match the subject offering using section, subject, semester, and instructor.
4. Report ambiguous or missing matches.
5. Populate the new foreign keys only when the match is deterministic.

### Step 5.3: Update schedule CRUD

The schedule form must:

- default to the active year;
- choose a subject offering rather than unrelated section/subject/instructor values;
- show only writable-year offerings;
- validate start time before end time;
- eventually detect conflicts for room, instructor, section, and time; and
- prohibit normal edits/deletes for closed-year schedules.

### Step 5.4: Update active-schedule resolution

Attendance-panel schedule lookup must include:

- active academic year;
- active semester where used;
- correct room;
- correct weekday/date/time;
- assigned instructor; and
- active offering/section.

### Phase 5 gate

- Every active schedule has a year and offering.
- The panel cannot resolve a closed-year schedule.
- Creating a new-year schedule does not modify an old schedule.
- Schedule CRUD and role scoping tests pass.

## Phase 6 — Attach Physical Attendance to Historical Context

### Step 6.1: Extend attendance tables

Add academic context to:

- `attendance_sessions`;
- `attendances`;
- `attendance_logs`; and
- `rfid_panel_sessions`.

At minimum, persist:

- `academic_year_id`;
- `subject_offering_id`;
- `student_enrollment_id` on student results/events; and
- historical label snapshots needed for stable exports.

### Step 6.2: Backfill attendance

Resolve context in this order:

1. attendance schedule;
2. schedule offering/year;
3. attendance date;
4. the student's enrollment for that year/semester.

Do not use the student's current section as the sole source for historical attendance. Ambiguous records must be reported for manual review.

### Step 6.3: Update live attendance writes

When a session starts:

- capture the active year and offering;
- resolve the student against enrollment in that offering's section;
- reject students without eligible enrollment; and
- write the same context to attendance and tap logs.

Keep the existing check-in, Late, temporary movement, checkout, Dismiss Class, ignored-tap, invalid-tap, face verification, and audit rules unchanged except for year-aware eligibility.

### Step 6.4: Update attendance management

Change subject dashboards, summaries, histories, session sheets, corrections, and exports to use:

- the session's offering;
- enrollment roster for that academic year; and
- stored historical labels.

Do not construct an old roster from `students.section_id`.

### Step 6.5: Enforce closed-year correction policy

Recommended rule:

- ordinary instructor/admin corrections are blocked after year closure;
- a privileged reopen/correction path requires a reason;
- original tap evidence is never modified or deleted; and
- every exceptional correction is logged.

### Phase 6 gate

- Old attendance reports still show their original roster and labels after student promotion.
- Active attendance uses only active-year enrollment and schedules.
- Closed-year attendance is read-only by default.
- PDF and Excel exports include the correct academic year.

## Phase 7 — Make Online Classes Year-Aware

### Step 7.1: Extend online-class tables

Add:

- `academic_year_id` and `subject_offering_id` to `online_classes`;
- `student_enrollment_id` to `online_class_attendances`; and
- academic context to notifications where it improves traceability.

### Step 7.2: Backfill existing online classes

Resolve year/offering from each online class's schedule, section, subject, and instructor. Resolve student enrollment from the class date and year.

### Step 7.3: Update class creation and joining

- Admin/instructor can create classes only for writable-year offerings.
- Student eligibility comes from the offering's enrollment roster.
- Historical classes remain visible but cannot be joined.
- Attendance finalization creates Absent rows only for students enrolled in that offering.

### Phase 7 gate

- Online attendance is isolated across years.
- Promoted students do not appear retroactively in last year's class roster.
- Closed-year classes cannot be created, edited, cancelled, deleted, or joined normally.

## Phase 8 — Update Registrar and Portal Workflows

### Step 8.1: Registrar

Update registrar pages to:

- list students through active-year enrollment by default;
- retain RFID and face data on the permanent student identity;
- allow historical enrollment filtering; and
- avoid creating duplicate student identities during yearly enrollment.

### Step 8.2: Student and parent portal

Update dashboard, attendance, online classes, profile, notifications, and excuse letters to:

- default to current enrollment;
- allow explicit historical-year selection where useful;
- separate permanent identity from enrollment details; and
- prevent actions against closed/historical offerings where inappropriate.

### Step 8.3: Excuse-letter context

Associate new excuse letters with the applicable enrollment/year when possible. Existing letters remain readable even if they cannot be deterministically backfilled.

### Phase 8 gate

- RFID/face identity persists across years without duplication.
- Students and parents see the correct current placement.
- Historical attendance/classes remain separate and accessible.

## Phase 9 — Add Reports and Year Selectors

### Step 9.1: Shared selector behavior

Use one consistent selector contract:

- default: active academic year;
- option: selected historical year;
- optional explicit `All years` for authorized aggregate reports; and
- selected semester where applicable.

### Step 9.2: Update report queries

Apply year filtering to:

- admin reports;
- instructor reports;
- registrar reports;
- student reports;
- parent reports;
- clinic/student-linked reports; and
- CSV, PDF, and Excel exports.

The export must use exactly the same filters as the visible report.

### Step 9.3: Update dashboards

Label every metric as either:

- active-year;
- selected-year; or
- all-time.

Avoid presenting an all-time student count as “this school year.”

### Phase 9 gate

- Every applicable report can be isolated to one academic year.
- Exports match visible filtered data.
- No role gains access to another role's or unrelated student's data.

## Phase 10 — Implement Closure and Rollover

### Step 10.1: Create rollover tables

Create:

- `academic_year_rollovers`; and
- `academic_year_rollover_items`.

Record source/destination year, operator, mode, status, preview/execution counts, errors, timestamps, and per-student decisions.

### Step 10.2: Build rollover preview

Preview must make no writes. It should show:

- sections to copy;
- offerings to copy;
- schedules to copy or leave unassigned;
- students eligible for promotion;
- students retained, graduated, dropped, transferred, or requiring review;
- uniqueness conflicts;
- missing destination sections; and
- invalid/incomplete source data.

### Step 10.3: Define promotion rules

Recommended defaults:

- Grade 11 enrolled → Grade 12 destination enrollment after admin confirmation.
- Grade 12 enrolled → graduated, with no destination enrollment unless explicitly configured.
- Retained → same year level in an explicitly selected destination section.
- Dropped/transferred → no automatic destination enrollment.
- Students with unresolved data → skipped and listed for manual review.

Do not infer destination sections only from text names when an explicit mapping can be required.

### Step 10.4: Execute transactionally

Execution should:

1. lock the rollover header;
2. verify source/destination status again;
3. create destination configuration selected in preview;
4. create destination enrollments;
5. write per-item results;
6. write activity logs;
7. commit only if required operations succeed; and
8. be idempotent so retrying does not create duplicates.

### Step 10.5: Close and activate

Recommended sequence:

1. Finish and finalize pending source-year operational work.
2. Run rollover preview.
3. Create destination-year draft data.
4. Review destination rosters, offerings, schedules, and conflicts.
5. Close the source year.
6. Activate the destination year atomically.
7. Confirm all daily-operation pages resolve the new active year.

### Records excluded from rollover

Never copy:

- attendance sessions, results, logs, evidence, or corrections;
- online classes, joins, notifications, or audit logs;
- messages or attachments;
- excuse letters or generated documents;
- clinic cases, histories, or emergency alerts;
- borrowing transactions; or
- activity/registrar audit logs.

### Phase 10 gate

- Preview is read-only.
- Rollover execution is transactional and safe to retry.
- Source-year operational history remains unchanged.
- Destination year contains only approved configuration and enrollments.
- Activation leaves exactly one active year.

## Phase 11 — Remove Legacy Dependencies Carefully

Do this only after all application reads and writes use the new structure.

### Step 11.1: Measure remaining legacy usage

Search code and query logs for reads/writes of:

- `students.section_id`;
- `students.strand_id`;
- `students.year_level`;
- `students.semester`;
- `students.school_year`;
- `subjects.section_id`;
- `subjects.user_id`; and
- schedule `subject_code` lookups that should use offerings.

### Step 11.2: Stop dual writes

After compatibility tests prove the new source is authoritative:

1. stop writing legacy placement fields;
2. keep read-only compatibility accessors temporarily;
3. monitor for fallback use; and
4. remove or deprecate columns only in a later release.

### Step 11.3: Do not rush column removal

It is acceptable to leave documented deprecated columns for one release. Immediate removal increases rollback risk and makes historical-data reconciliation harder.

### Phase 11 gate

- No active workflow depends on legacy placement/assignment columns.
- Fallback-use monitoring reports zero unexpected access.
- A rollback plan exists before any column removal.

## Phase 12 — Final Verification

### Required automated scenarios

1. Create Year A and activate it.
2. Create `ICT 11-A`, a subject offering, schedule, and enrolled student.
3. Record physical and online attendance.
4. Create Year B as draft.
5. Reuse the same section name and subject catalog entry.
6. Promote the student into Year B.
7. Confirm Year A roster, attendance, reports, and exports are unchanged.
8. Close Year A and confirm normal writes are rejected.
9. Activate Year B and confirm panel/class operations use only Year B.
10. Confirm student and parent can view both years without mixed records.
11. Confirm instructor scope is based on the selected offering/year.
12. Confirm rollover retry creates no duplicate structures or enrollments.
13. Confirm unauthorized users cannot reopen or mutate closed years.

### Required data-integrity checks

- No orphaned academic-year foreign keys.
- No student without a resolvable intended enrollment.
- No attendance row assigned to an incompatible enrollment/year.
- No active schedule linked to a closed year.
- No online attendance for a student outside the offering roster.
- Baseline historical row counts remain unchanged except for planned new foreign-key/backfill data.
- File metadata still points to existing stored files.

### Required commands

```bash
composer test:requested-features
php artisan test --compact
npm run build
git diff --check
```

Also perform manual tests for:

- every user role;
- RFID hardware and attendance panel;
- face verification/fallback;
- PDF, Excel, and CSV exports;
- real browser navigation and closed-year controls; and
- email/SMS behavior where configured.

### Phase 12 gate

- Automated and manual verification passes.
- Data-integrity comparison is approved.
- Documentation matches implemented behavior.
- Backup restoration procedure has been tested or verified.

## Recommended Pull Request Sequence

Keep changes reviewable instead of implementing everything in one pull request:

1. Academic-year schema, model, service, and lifecycle tests.
2. Student enrollments and backfill command.
3. Year-scoped sections.
4. Subject catalog and offerings.
5. Year-aware schedules.
6. Physical attendance migration and queries.
7. Online-class migration and queries.
8. Registrar and portal updates.
9. Reports and exports.
10. Closure, rollover preview, and execution.
11. Legacy dependency cleanup and final documentation.

Each pull request should be deployable with compatibility preserved and should include its own migration validation and rollback notes.

## Documentation Updates Required During Implementation

Update these canonical documents as behavior becomes real:

- `docs/DATABASE.md`
- `docs/SYSTEM_FLOW.md`
- `docs/USER_OPERATIONS_TUTORIAL.md`
- `docs/ROLES_AND_FUNCTIONALITY.md`
- `docs/TESTING.md`
- `docs/README.md`
- `docs/DOCUMENTATION_INDEX.md`

Do not describe a phase as implemented until its code, migration, tests, and UI behavior are complete.
