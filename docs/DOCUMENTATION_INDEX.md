# Documentation Index

## Start here

| Need | Source of truth |
| --- | --- |
| Plain-language system overview | [System Explanation](System%20Explanation/README.md) |
| Every page, action, condition, and result | [Pages and Features](System%20Explanation/PAGES_AND_FEATURES.md) |
| Role capabilities and boundaries | [Roles and Functionality](System%20Explanation/ROLES_AND_FUNCTIONALITY.md) |
| Human operating steps | [User Operations Tutorial](System%20Explanation/USER_OPERATIONS_TUTORIAL.md) |
| Hidden, automatic, notification, and scheduled behavior | [Automatic and Conditional Behavior](System%20Explanation/AUTOMATIC_AND_CONDITIONAL_BEHAVIOR.md) |
| Internal architecture and feature data flows | [Architecture and Feature Flows](System%20Architecture/ARCHITECTURE_AND_FEATURE_FLOWS.md) |
| Routes and endpoints | [Routes and Endpoints](System%20Architecture/ROUTES_AND_ENDPOINTS.md) |
| Tables, fields, and relationships | [Database Schema Reference](System%20Architecture/DATABASE_SCHEMA_REFERENCE.md) |
| Install, configure, run, build, and deploy | [System Installation](System%20Installation/README.md) |
| Tests and release checks | [Testing](System%20Architecture/TESTING.md) |

## Folder map

### System Explanation

- `README.md` — non-technical overview, roles, and setup order.
- `PAGES_AND_FEATURES.md` — complete active/unwired page catalog using the seven required page questions.
- `AUTOMATIC_AND_CONDITIONAL_BEHAVIOR.md` — feature flags, automation, background/scheduled work, notifications, and conditional UI.
- `ROLES_AND_FUNCTIONALITY.md` — detailed role reference.
- `USER_OPERATIONS_TUTORIAL.md` — step-by-step procedures.
- `AUTHENTICATION_PASSWORD_RULES.md` and `DEFAULT_ACCOUNT_PASSWORDS.md` — account lifecycle and non-production fixtures.
- `ACADEMIC_YEAR_LEVELING_GUIDE.md` — academic lifecycle and rollover.
- `ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md` — canonical tap-state behavior.
- `LABORATORIES_AND_DEVICES.md` — room/device/PIN rules.
- `EMERGENCY_FLOW.md` and `CLINIC_DISPATCH.md` — emergency and Clinic ownership behavior.
- `ATTENDANCE_SYSTEM_DEMONSTRATION_GUIDE.md`, `SYSTEM_FLOW_DEMONSTRATION.md`, `FULL_SYSTEM_DEMONSTRATION_SCRIPT.md`, `CAPSTONE_PRESENTATION.md`, and `DEMONSTRATION_DOCUMENTATION.md` — presentation/rehearsal material.

### System Architecture

- `README.md` — technical entry point.
- `ARCHITECTURE_AND_FEATURE_FLOWS.md` — stack, layers, authentication, services, data flow, external integrations, and known gaps.
- `ROUTES_AND_ENDPOINTS.md` — HTTP and command surface.
- `DATABASE_SCHEMA_REFERENCE.md` — final migrated tables, fields, foreign keys, and storage boundary.
- `DATABASE.md` — migration groups, seeders, attendance data, and compatibility notes.
- `SYSTEM_FLOW.md` — detailed business/data flow.
- `PAGE_DATABASE_IMPACT_MAP.md` — page-level read/write impact.
- `REPOSITORY_ANALYSIS_AND_FLOW_REPORT.md` — evidence-oriented source review.
- `ACADEMIC_YEAR_IMPLEMENTATION_PLAN.md`, `ACADEMIC_YEAR_IMPLEMENTATION_IMPACT.md`, `ACADEMIC_YEAR_FINAL_VERIFICATION.md`, and `LEGACY_ACADEMIC_DEPENDENCY_AUDIT.md` — academic-year design/history/release notes.
- `TESTING.md` and `CROSS_PAGE_WORKFLOW_TESTING.md` — verification guidance.
- `REUSABLE_LANDING_ROUTES.md` — retained reusable-page developer note.

### System Installation

- `README.md` — canonical setup/deployment/operations guide.
- `RUNNING_THE_SYSTEM.md` — shorter Windows/XAMPP walkthrough.
- `INSTALLATION_LINKS.md` — official software download entry points.

## Source-of-truth order

When text overlaps, use this order:

1. Current executable implementation and migrations.
2. The new canonical page, automation, architecture, route, schema, and installation references listed above.
3. Specialized behavior guide such as Attendance Tapping or Academic Year Leveling.
4. Tutorial and demonstration material.
5. Historical implementation plans and verification notes.

Historical plans describe intended phases and may contain statements that were later superseded. They remain useful evidence but are not a substitute for the current architecture and schema references.

## Maintenance rule

When code changes:

1. Update the page catalog for visible behavior/access/action changes.
2. Update automatic behavior for jobs, notifications, statuses, feature switches, or hidden conditions.
3. Update architecture/routes/schema for controller, service, endpoint, migration, relationship, or integration changes.
4. Update installation for dependencies, environment variables, workers, scheduler, storage, or deployment changes.
5. Update specialized tutorials/demonstrations only when their workflow changes.
6. Run the Markdown link checker described in the testing guide.
