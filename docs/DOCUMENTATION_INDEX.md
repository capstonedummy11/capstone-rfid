# Documentation Index and Source-of-Truth Map

This is the main entry point for all project documentation. It explains what every Markdown file is for, which documents are authoritative, and how overlapping documents relate to each other.

## Start Here

Choose the document that matches your task:

| Goal | Read this |
|---|---|
| Understand the whole project | [Project README](README.md) |
| Install and start the application | [Running the System](RUNNING_THE_SYSTEM.md) |
| Find required software downloads | [Installation Links](INSTALLATION_LINKS.md) |
| Operate the system step by step | [User Operations Tutorial](USER_OPERATIONS_TUTORIAL.md) |
| Understand backend and role flows | [System Flow](SYSTEM_FLOW.md) |
| Apply exact attendance tapping behavior | [Attendance Control Panel Tapping Rules](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md) |
| Understand database tables and migrations | [Database Documentation](DATABASE.md) |
| Find account-creation password rules | [Default Account Passwords](DEFAULT_ACCOUNT_PASSWORDS.md) |
| Understand login, recovery, and first-login enforcement | [Authentication and Password Rules](AUTHENTICATION_PASSWORD_RULES.md) |
| Prepare a capstone demonstration | [Demonstration Documentation Map](DEMONSTRATION_DOCUMENTATION.md) |

## Canonical Sources

When several files discuss the same subject, use these as the source of truth:

| Subject | Canonical document | Other files do this |
|---|---|---|
| Project scope, roles, modules, routes, and limitations | [Project README](README.md) | Present or summarize the implemented system |
| Exact end-to-end application behavior | [System Flow](SYSTEM_FLOW.md) | Convert the flow into tutorials, reports, or demonstrations |
| Exact RFID tapping and attendance-state rules | [Attendance Control Panel Tapping Rules](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md) | Provide abbreviated operator or presenter versions |
| Human operating procedure | [User Operations Tutorial](USER_OPERATIONS_TUTORIAL.md) | Reference selected steps for demonstrations |
| Schema, migrations, relationships, and seeding | [Database Documentation](DATABASE.md) | Mention only the data relevant to a feature |
| Installation and daily startup | [Running the System](RUNNING_THE_SYSTEM.md) | Provide links or short setup summaries |
| Login, forgot password, and first-login password changes | [Authentication and Password Rules](AUTHENTICATION_PASSWORD_RULES.md) | Summarize role-specific access where needed |
| Demonstration structure and artifact selection | [Demonstration Documentation Map](DEMONSTRATION_DOCUMENTATION.md) | Supply a script, slide outline, or detailed rehearsal |

If a summary conflicts with its canonical source, update the summary and follow the canonical source.

## Complete File Guide

### Core documentation

- [README.md](README.md) — Executive project overview: implementation status, roles, modules, important routes, architecture, verification, and known limitations. Use this for a new developer or evaluator's first technical introduction.
- [SYSTEM_FLOW.md](SYSTEM_FLOW.md) — Detailed end-to-end functional flow across authentication, admin setup, attendance, Messenger, excuse letters, reports, clinic, inventory, and audit behavior. Use this when implementing or verifying business logic.
- [USER_OPERATIONS_TUTORIAL.md](USER_OPERATIONS_TUTORIAL.md) — Screen-by-screen instructions from initial setup through daily use for every role. Use this for administrators, instructors, registrar staff, clinic staff, students, and parents.
- [REPOSITORY_ANALYSIS_AND_FLOW_REPORT.md](REPOSITORY_ANALYSIS_AND_FLOW_REPORT.md) — Evidence-oriented analysis of implemented repository capabilities and gaps. Use this for audits, reviews, and claims about what the source code currently supports.

### Attendance documentation

- [ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md) — Canonical tap-state reference covering eligibility, check-in, Late, temporary movement, checkout, Dismiss Class, invalid/ignored taps, manual correction, and audit data.
- [ATTENDANCE_SYSTEM_DEMONSTRATION_GUIDE.md](ATTENDANCE_SYSTEM_DEMONSTRATION_GUIDE.md) — Detailed blank-state setup and rehearsal guide focused on proving the attendance system. It is intentionally longer and presentation-oriented; exact tap decisions come from the canonical tapping-rules file.

### Demonstration and presentation documentation

- [DEMONSTRATION_DOCUMENTATION.md](DEMONSTRATION_DOCUMENTATION.md) — Canonical map for the overlapping demonstration files, their shared sequence, and which artifact to use.
- [FULL_SYSTEM_DEMONSTRATION_SCRIPT.md](FULL_SYSTEM_DEMONSTRATION_SCRIPT.md) — Concise live-demo actions and talking points. Use during the actual demonstration.
- [SYSTEM_FLOW_DEMONSTRATION.md](SYSTEM_FLOW_DEMONSTRATION.md) — Detailed presenter actions, explanations, expected results, rules, and recovery guidance. Use for rehearsal and evaluator questions.
- [CAPSTONE_PRESENTATION.md](CAPSTONE_PRESENTATION.md) — Slide-by-slide content outline for the full capstone presentation.
- `capstone-rfid-system-presentation.pptx` — Rendered presentation artifact. Keep its claims aligned with the Markdown presentation and canonical sources.

### Setup and technical reference

- [RUNNING_THE_SYSTEM.md](RUNNING_THE_SYSTEM.md) — Complete setup, environment, database, development server, production build, and troubleshooting instructions.
- [INSTALLATION_LINKS.md](INSTALLATION_LINKS.md) — Short list of external installation resources. Use together with Running the System.
- [DATABASE.md](DATABASE.md) — Database setup commands, migration groups, key relationships, attendance data model, system settings, seeder order, and caveats.
- [DEFAULT_ACCOUNT_PASSWORDS.md](DEFAULT_ACCOUNT_PASSWORDS.md) — Default-password rules for newly created accounts and seeded development accounts. Treat this as sensitive operational documentation and require password changes.
- [AUTHENTICATION_PASSWORD_RULES.md](AUTHENTICATION_PASSWORD_RULES.md) — Canonical role login, forgot-password, Console exclusion, and mandatory first-login password-change behavior.
- [REUSABLE_LANDING_ROUTES.md](REUSABLE_LANDING_ROUTES.md) — Developer reference for reusable landing-page routes and components.

## Recommended Reading Paths

### New developer

1. Project README
2. Running the System
3. System Flow
4. Database Documentation
5. Relevant canonical feature reference

### System operator

1. User Operations Tutorial
2. Attendance Control Panel Tapping Rules, if operating attendance
3. Default Account Passwords, if provisioning accounts

### Capstone presenter

1. Demonstration Documentation Map
2. Capstone Presentation
3. Full System Demonstration Script
4. System Flow Demonstration for rehearsal
5. Attendance Tapping Rules for exact panel answers

### Evaluator or auditor

1. Project README
2. Repository Analysis and Flow Report
3. System Flow
4. Database Documentation
5. Canonical feature references

## Documentation Maintenance Rule

For every feature update:

1. Update the canonical document for that subject.
2. Update the Project README if scope, routes, roles, limitations, or setup changed.
3. Update the User Operations Tutorial if user actions changed.
4. Update System Flow if business logic or data movement changed.
5. Update demonstration summaries only when the visible demonstration changes.
6. Add new documents to this index and state whether they are canonical, procedural, analytical, or presentation-oriented.
