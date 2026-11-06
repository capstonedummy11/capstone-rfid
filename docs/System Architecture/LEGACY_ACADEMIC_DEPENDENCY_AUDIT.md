# Legacy Academic Dependency Audit

This file is retained as the migration and rollback record for the academic-year implementation.

## Authoritative structures

- Student placement: `student_enrollments`
- Subject assignment: `subject_offerings`
- Class assignment: year-aware `schedules`
- Physical and online attendance: stored year, offering, and enrollment foreign keys

## Deprecated compatibility columns

The following columns remain temporarily available but are no longer written by Student or Subject Management:

- `students.section_id`, `students.strand_id`, `students.year_level`, `students.semester`, `students.school_year`
- `subjects.section_id`, `subjects.user_id`, `subjects.semester`

They are intentionally nullable. Do not drop them until production monitoring shows no unexpected fallback access for a complete release cycle.

## Fallback monitoring

`legacy_academic_fallback_events` counts compatibility reads by context and stores the last-use timestamp and payload. The Academic Years admin page exposes these counters. A fully migrated database should show no events during ordinary operation.

Known compatibility paths are restricted to records created before academic-year foreign keys existed. New records must not rely on them.

## Remaining compatibility adapters

- Old attendance sessions or online classes with a null `academic_year_id` may validate against the legacy student section and record a monitored fallback.
- Portal profiles with no enrollment may render legacy placement and record a monitored fallback.
- The RFID directory records a fallback when a student has no enrollment.
- Historical display labels may still read stored legacy values when no normalized historical relationship exists.

## Rollback plan

1. Do not remove the deprecated columns in the same release that stops dual writes.
2. Before rollback, export normalized academic tables and fallback counters.
3. If necessary, repopulate student compatibility columns from the active/latest enrollment and subject compatibility fields from an explicitly selected offering.
4. Never overwrite attendance, online-class, rollover, or audit history during rollback.
5. Re-enable dual writes only temporarily and retain normalized records.

## Removal gate

Removal requires zero unexpected fallbacks for a full release cycle, successful Phase 12 reconciliation, a verified backup/restore test, explicit approval, and a tested rollback script.
