# System Explanation

This folder explains the RFID Attendance, Laboratory, and School Operations System in plain language. It is written for school administrators, instructors, registrar staff, clinic staff, console operators, students, parents, presenters, and evaluators.

## What the system does

The system combines school operations that depend on a shared student, class, and laboratory record:

- academic-year, semester, strand, section, subject, subject-offering, and schedule setup;
- student, parent, instructor, registrar, clinic, administrator, and console accounts;
- student and instructor RFID and face enrollment;
- room-based RFID attendance with face or instructor confirmation;
- attendance review, correction, evidence, export, and reports;
- online classes and automatic online absence recording;
- inventory and borrowing, when enabled;
- student/parent attendance, messages, notifications, and excuse letters;
- emergency requests, SMS attempts, clinic dispatch, cases, patient history, and reports;
- system settings, device access, feature switches, and audit records.

The application is not a complete student-information system. It has no grades, tuition, curriculum, or enrollment-admissions module. Schedule overlap detection is also not implemented.

## Read this folder in this order

1. [Pages and Features](PAGES_AND_FEATURES.md) — every active page, who can open it, what it shows, and what its actions do.
2. [Roles and Functionality](ROLES_AND_FUNCTIONALITY.md) — responsibilities and access limits for all roles.
3. [User Operations Tutorial](USER_OPERATIONS_TUTORIAL.md) — step-by-step operating procedures.
4. [Automatic and Conditional Behavior](AUTOMATIC_AND_CONDITIONAL_BEHAVIOR.md) — notifications, scheduled work, hidden features, automatic status changes, and feature switches.
5. [System Flow Demonstration](SYSTEM_FLOW_DEMONSTRATION.md) — a complete presentation-ready walkthrough.

Specialized guides are also available for [attendance tapping](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md), [academic years and rollover](ACADEMIC_YEAR_LEVELING_GUIDE.md), [emergencies](EMERGENCY_FLOW.md), [clinic dispatch](CLINIC_DISPATCH.md), [laboratories and devices](LABORATORIES_AND_DEVICES.md), [passwords](AUTHENTICATION_PASSWORD_RULES.md), and [development-only default accounts](DEFAULT_ACCOUNT_PASSWORDS.md).

## Roles at a glance

| Role | Main responsibility | Important limit |
| --- | --- | --- |
| Root Admin | Owns the installation and privileged administrator accounts. | Still follows authentication, validation, device-PIN, and audit rules. |
| Admin | Configures school data and supervises operations. | Only a Root Admin can manage Admin accounts. |
| Instructor | Handles assigned classes, attendance, online classes, and messages. | Must complete an extra verification step and is restricted to assigned data. |
| Registrar | Enrolls student and instructor RFID/face identity. | Does not decide attendance or manage academic setup. |
| Clinic | Responds to alerts and maintains clinic records. | Dispatch is internal; it does not automatically contact an ambulance or police. |
| Console | Operates the room attendance panel. | Cannot enter the staff or student portals. |
| Student | Reviews personal data, attendance, classes, letters, and messages. | Cannot change official attendance. |
| Parent | Reviews explicitly linked students and may approve letters. | Parent access can be disabled system-wide. |

## Normal setup order

1. Create or confirm the Root Admin.
2. Configure system settings and feature switches.
3. Create the active academic year and semester.
4. Create laboratories, devices, strands, sections, subjects, and offerings.
5. Create staff, instructor, student, and parent records.
6. Create schedules.
7. Enroll RFID cards and face images.
8. Configure emergency types, hotlines, sounds, and external services.
9. Test attendance, online classes, messages, reports, and clinic response before live use.

## Important operating truth

The menu is a convenience, not the security boundary. The server checks sign-in, role, instructor verification, parent availability, selected-student ownership, academic-year scope, device status, and other rules even when a person manually enters a URL.

