# System Context Memo

This compact memo synthesizes the 44 Markdown files in this repository. It is
an orientation aid, not a replacement for the canonical references in the
[Documentation Index](DOCUMENTATION_INDEX.md). When sources disagree, current
code and migrations take priority, followed by the canonical documentation.

## Product boundary

The system is a Laravel 12, Vue 3, Inertia, Tailwind, and MySQL school-operations
application. It covers academic setup, role-based accounts, RFID/face enrollment,
physical and online attendance, Student/Parent services, excuse letters,
Messenger, reports, inventory/borrowing, emergencies, Clinic cases, settings,
devices, audit records, and academic rollover.

It is not a complete student-information system. Grades, tuition, admissions,
and curriculum management are outside scope. Schedule-overlap detection is not
implemented.

## Actors

| Actor | Responsibility | Key boundary |
| --- | --- | --- |
| Root Admin | Owns the installation and privileged Admin accounts | Still follows authentication, validation, device, and audit rules |
| Admin | Configures and supervises school operations | Root-only account actions remain restricted |
| Instructor | Operates assigned classes, attendance, and online classes | Requires extra verification and sees assigned data only |
| Registrar | Enrolls Student/Instructor RFID and face identity | Does not decide attendance or manage academic setup |
| Clinic | Responds to alerts and maintains health records | Dispatch is internal, not proof of external-service contact |
| Console | Operates a room-bound attendance panel | Cannot enter other workspaces or change device PINs |
| Student | Uses personal attendance, classes, letters, and messages | Cannot change official attendance |
| Parent | Acts for explicitly linked Students | Access can be disabled globally |

Supporting actors include RFID readers, browser cameras, AWS face services,
mail, SMS providers, the scheduler, file storage, and the database.

## Operating lifecycle

1. Root Admin establishes ownership and operational accounts.
2. Admin configures settings, academic context, rooms, devices, people,
   offerings, and schedules.
3. Registrar enrolls RFID and face identity.
4. Console authenticates a room device; Instructor starts the scheduled class.
5. Verified taps create auditable events and one official attendance result per
   Student, schedule, and date.
6. Instructor reviews assigned attendance and makes time-bounded corrections.
7. Portal, online-class, Messenger, reporting, inventory, and Clinic workflows
   consume the same scoped academic and identity records.
8. Admin previews and executes academic rollover while preserving history.

## Core controls and rules

- Server-side role, ownership, academic-context, and record-state checks are the
  security boundary; knowing a URL or seeing a menu is not authorization.
- Authentication uses server-side sessions and CSRF protection.
- New non-Console users replace temporary passwords. Admin/Root Admin uses a
  per-login email OTP; Instructor uses face, email OTP, or security questions.
- Parent access requires an explicit Student link. Console access is bound to a
  laboratory and enabled device PIN.
- Live attendance uses the active academic year/semester. Early movement needs
  Instructor authorization; official checkout follows the final-15-minute,
  Dismiss Class, or accepted-fallback rules. Later taps are logged but ignored.
- Manual attendance correction never deletes original tap evidence.
- Emergency alerts remain saved when SMS/email fails. Specific-person alerts
  notify linked parents; area-wide alerts do not notify every parent.
- Clinic Dispatch requires an active Clinic responder and creates/updates a
  monitoring case. It does not automatically contact ambulance or police.
- Parent approval of an excuse letter does not directly change attendance.
- Rollover never copies attendance, schedules, Instructor assignments, online
  classes, messages, Clinic records, borrowing, evidence, audit logs, or settings.

## Known constraints

- Some enrollment queries use `active` while normalized records default to
  `enrolled`.
- The Online Classes switch does not block every direct route.
- Student Management references missing Admin face routes.
- Public registration and a legacy public message form remain enabled.
- Database queue tables are absent from the current migration set.
- RFID, cameras, SMTP, SMS, AWS, and browser audio need environment acceptance
  testing beyond automated tests.

## Canonical sources

- [Pages and Features](System%20Explanation/PAGES_AND_FEATURES.md)
- [Roles and Functionality](System%20Explanation/ROLES_AND_FUNCTIONALITY.md)
- [Automatic and Conditional Behavior](System%20Explanation/AUTOMATIC_AND_CONDITIONAL_BEHAVIOR.md)
- [Architecture and Feature Flows](System%20Architecture/ARCHITECTURE_AND_FEATURE_FLOWS.md)
- [Routes and Endpoints](System%20Architecture/ROUTES_AND_ENDPOINTS.md)
- [Database Schema](System%20Architecture/DATABASE_SCHEMA_REFERENCE.md)
- [Attendance Tapping Rules](System%20Explanation/ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md)
- [Emergency Flow](System%20Explanation/EMERGENCY_FLOW.md)
