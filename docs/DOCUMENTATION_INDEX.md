# Documentation Index

Use this file as the map for the project documentation. It tells you which file to open first, which files are the source of truth, and which files are supporting references.

## Start Here

| Goal                                      | Read this                                                           |
| ----------------------------------------- | ------------------------------------------------------------------- |
| Understand the whole project              | [Project README](README.md)                                         |
| Install and run the system                | [Running the System](RUNNING_THE_SYSTEM.md)                         |
| Find required software downloads          | [Installation Links](INSTALLATION_LINKS.md)                         |
| Operate the system step by step           | [User Operations Tutorial](USER_OPERATIONS_TUTORIAL.md)             |
| Understand system behavior and role flows | [System Flow](SYSTEM_FLOW.md)                                       |
| Understand academic years and rollover    | [Academic Year and Rollover Guide](ACADEMIC_YEAR_LEVELING_GUIDE.md) |
| Understand database tables and migrations | [Database Documentation](DATABASE.md)                               |
| Run tests and regression checks           | [Testing and Regression Guide](TESTING.md)                          |
| Test data shared across connected pages   | [Cross-Page Workflow Testing](CROSS_PAGE_WORKFLOW_TESTING.md)       |
| Prepare a capstone demonstration          | [Demonstration Documentation Map](DEMONSTRATION_DOCUMENTATION.md)   |

## Source of Truth

When documents overlap, follow the source-of-truth file in this table.

| Subject                                   | Source of truth                                                                        | Supporting files                                                     |
| ----------------------------------------- | -------------------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| Project scope, roles, routes, limitations | [README.md](README.md)                                                                 | Presentation and analysis docs summarize it                          |
| End-to-end application behavior           | [SYSTEM_FLOW.md](SYSTEM_FLOW.md)                                                       | Tutorials and demo docs convert it into steps                        |
| Human operating procedure                 | [USER_OPERATIONS_TUTORIAL.md](USER_OPERATIONS_TUTORIAL.md)                             | Demo scripts use shorter versions                                    |
| Attendance tapping rules                  | [ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md) | Attendance demo guide rehearses those rules                          |
| Academic year and rollover behavior       | [ACADEMIC_YEAR_LEVELING_GUIDE.md](ACADEMIC_YEAR_LEVELING_GUIDE.md)                     | Implementation plan and final verification explain build details     |
| Database schema and relationships         | [DATABASE.md](DATABASE.md)                                                             | Impact maps summarize affected tables                                |
| Login and password rules                  | [AUTHENTICATION_PASSWORD_RULES.md](AUTHENTICATION_PASSWORD_RULES.md)                   | Default-password doc lists account defaults                          |
| Role responsibilities                     | [ROLES_AND_FUNCTIONALITY.md](ROLES_AND_FUNCTIONALITY.md)                               | System flow references role behavior                                 |
| Clinic dispatch rules                     | [CLINIC_DISPATCH.md](CLINIC_DISPATCH.md)                                               | Emergency flow references Clinic response                            |
| Emergency flow                            | [EMERGENCY_FLOW.md](EMERGENCY_FLOW.md)                                                 | Tutorial and demo docs provide shorter versions                      |
| Laboratory and device rules               | [LABORATORIES_AND_DEVICES.md](LABORATORIES_AND_DEVICES.md)                             | Attendance docs reference devices                                    |
| Test commands and expectations            | [TESTING.md](TESTING.md)                                                               | Cross-page workflow testing adds one testing pattern                 |
| Demonstration structure                   | [DEMONSTRATION_DOCUMENTATION.md](DEMONSTRATION_DOCUMENTATION.md)                       | Full script, system-flow demo, and slides are presentation artifacts |

## File Guide

### Core System Docs

- [README.md](README.md) - High-level project overview, implemented modules, routes, verification, and known limitations.
- [SYSTEM_FLOW.md](SYSTEM_FLOW.md) - Detailed business flow across authentication, admin setup, attendance, messages, reports, clinic, inventory, and audit behavior.
- [USER_OPERATIONS_TUTORIAL.md](USER_OPERATIONS_TUTORIAL.md) - Step-by-step operating instructions for administrators, instructors, registrar staff, clinic staff, students, and parents.
- [ROLES_AND_FUNCTIONALITY.md](ROLES_AND_FUNCTIONALITY.md) - Responsibilities and access boundaries for each role.
- [REPOSITORY_ANALYSIS_AND_FLOW_REPORT.md](REPOSITORY_ANALYSIS_AND_FLOW_REPORT.md) - Evidence-oriented repository analysis and implementation summary.

### Setup and Accounts

- [RUNNING_THE_SYSTEM.md](RUNNING_THE_SYSTEM.md) - Environment setup, database setup, development server, production build, and troubleshooting.
- [INSTALLATION_LINKS.md](INSTALLATION_LINKS.md) - Short list of external installer links.
- [DEFAULT_ACCOUNT_PASSWORDS.md](DEFAULT_ACCOUNT_PASSWORDS.md) - Default-password rules for seeded and newly created accounts.
- [AUTHENTICATION_PASSWORD_RULES.md](AUTHENTICATION_PASSWORD_RULES.md) - Login, recovery, first-login password change, and role access rules.

### Academic Year Docs

- [ACADEMIC_YEAR_LEVELING_GUIDE.md](ACADEMIC_YEAR_LEVELING_GUIDE.md) - Plain-language guide for academic years, active semester, full academic rollover, semester-only rollover, Grade 12 archiving, section mapping, and what data is not copied.
- [ACADEMIC_YEAR_IMPLEMENTATION_PLAN.md](ACADEMIC_YEAR_IMPLEMENTATION_PLAN.md) - Technical implementation plan and phased build notes for academic-year support.
- [ACADEMIC_YEAR_IMPLEMENTATION_IMPACT.md](ACADEMIC_YEAR_IMPLEMENTATION_IMPACT.md) - Impact map for pages, backend files, and database tables affected by academic-year support.
- [ACADEMIC_YEAR_FINAL_VERIFICATION.md](ACADEMIC_YEAR_FINAL_VERIFICATION.md) - Final reconciliation and release-check notes for academic-year behavior.
- [LEGACY_ACADEMIC_DEPENDENCY_AUDIT.md](LEGACY_ACADEMIC_DEPENDENCY_AUDIT.md) - Audit of older academic assignment fields and rollback safety.

### Attendance and Devices

- [ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md) - Canonical RFID tap-state rules for attendance.
- [ATTENDANCE_SYSTEM_DEMONSTRATION_GUIDE.md](ATTENDANCE_SYSTEM_DEMONSTRATION_GUIDE.md) - Longer rehearsal guide for demonstrating attendance behavior.
- [LABORATORIES_AND_DEVICES.md](LABORATORIES_AND_DEVICES.md) - Laboratory, device, PIN, ownership, and disable rules.

### Clinic and Emergency

- [CLINIC_DISPATCH.md](CLINIC_DISPATCH.md) - Clinic responder assignment, notification, and case ownership rules.
- [EMERGENCY_FLOW.md](EMERGENCY_FLOW.md) - Emergency panel flow, hotline behavior, Clinic Case lifecycle, timers, and status charts.

### Database and Impact Maps

- [DATABASE.md](DATABASE.md) - Database setup commands, migration groups, relationships, seeders, and caveats.
- [PAGE_DATABASE_IMPACT_MAP.md](PAGE_DATABASE_IMPACT_MAP.md) - Page-by-page database read/write/delete impact map.

### Testing

- [TESTING.md](TESTING.md) - Test commands, regression expectations, and troubleshooting.
- [CROSS_PAGE_WORKFLOW_TESTING.md](CROSS_PAGE_WORKFLOW_TESTING.md) - How to test data created on one page and reused on another page.

### Demonstration and Presentation

- [DEMONSTRATION_DOCUMENTATION.md](DEMONSTRATION_DOCUMENTATION.md) - Map of all demo and presentation files.
- [FULL_SYSTEM_DEMONSTRATION_SCRIPT.md](FULL_SYSTEM_DEMONSTRATION_SCRIPT.md) - Concise live-demo script.
- [SYSTEM_FLOW_DEMONSTRATION.md](SYSTEM_FLOW_DEMONSTRATION.md) - Detailed presenter actions, expected results, and recovery guidance.
- [CAPSTONE_PRESENTATION.md](CAPSTONE_PRESENTATION.md) - Slide-by-slide capstone presentation outline.
- `capstone-rfid-system-presentation.pptx` - Rendered presentation artifact. Keep it aligned with the Markdown presentation and canonical docs.

### Smaller Technical References

- [REUSABLE_LANDING_ROUTES.md](REUSABLE_LANDING_ROUTES.md) - Developer reference for reusable landing-page routes and components.

## Recommended Reading Paths

### New Developer

1. [README.md](README.md)
2. [RUNNING_THE_SYSTEM.md](RUNNING_THE_SYSTEM.md)
3. [SYSTEM_FLOW.md](SYSTEM_FLOW.md)
4. [DATABASE.md](DATABASE.md)
5. The source-of-truth file for the feature being changed

### System Operator

1. [USER_OPERATIONS_TUTORIAL.md](USER_OPERATIONS_TUTORIAL.md)
2. [ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md), if operating attendance
3. [ACADEMIC_YEAR_LEVELING_GUIDE.md](ACADEMIC_YEAR_LEVELING_GUIDE.md), if managing school years or rollover
4. [DEFAULT_ACCOUNT_PASSWORDS.md](DEFAULT_ACCOUNT_PASSWORDS.md), if provisioning accounts

### Capstone Presenter

1. [DEMONSTRATION_DOCUMENTATION.md](DEMONSTRATION_DOCUMENTATION.md)
2. [CAPSTONE_PRESENTATION.md](CAPSTONE_PRESENTATION.md)
3. [FULL_SYSTEM_DEMONSTRATION_SCRIPT.md](FULL_SYSTEM_DEMONSTRATION_SCRIPT.md)
4. [SYSTEM_FLOW_DEMONSTRATION.md](SYSTEM_FLOW_DEMONSTRATION.md)
5. [ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md)

### Evaluator or Auditor

1. [README.md](README.md)
2. [REPOSITORY_ANALYSIS_AND_FLOW_REPORT.md](REPOSITORY_ANALYSIS_AND_FLOW_REPORT.md)
3. [SYSTEM_FLOW.md](SYSTEM_FLOW.md)
4. [DATABASE.md](DATABASE.md)
5. [ACADEMIC_YEAR_FINAL_VERIFICATION.md](ACADEMIC_YEAR_FINAL_VERIFICATION.md)

## Combined or Removed Docs

- `ACADEMIC_ROLLOVER_EXPLANATION.md` was merged into [ACADEMIC_YEAR_LEVELING_GUIDE.md](ACADEMIC_YEAR_LEVELING_GUIDE.md) so academic-year and rollover behavior has one readable guide.

## Maintenance Rule

When a feature changes:

1. Update the source-of-truth document first.
2. Update [README.md](README.md) if scope, routes, roles, setup, or limitations changed.
3. Update [USER_OPERATIONS_TUTORIAL.md](USER_OPERATIONS_TUTORIAL.md) if user steps changed.
4. Update [SYSTEM_FLOW.md](SYSTEM_FLOW.md) if business logic or data movement changed.
5. Update demo files only when the visible demonstration changes.
6. Update this index when a document is added, removed, renamed, or combined.
