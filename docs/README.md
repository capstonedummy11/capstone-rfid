# RFID Attendance and School Operations System Documentation

This documentation is organized by audience and purpose. It was cross-checked against the repository's routes, 66 Vue page files, controllers, services, models, migrations, middleware, scheduler, configuration, seeders, and automated tests on 2026-09-17.

## 1. System Explanation

Plain-language documentation for operators, users, presenters, and non-developers.

- [Start with the System Explanation](System%20Explanation/README.md)
- [Every page and feature](System%20Explanation/PAGES_AND_FEATURES.md)
- [Automatic, scheduled, hidden, and conditional behavior](System%20Explanation/AUTOMATIC_AND_CONDITIONAL_BEHAVIOR.md)
- [Step-by-step user operations](System%20Explanation/USER_OPERATIONS_TUTORIAL.md)

## 2. System Architecture

Technical documentation for developers, reviewers, testers, and maintainers.

- [Start with System Architecture](System%20Architecture/README.md)
- [Architecture and end-to-end feature flows](System%20Architecture/ARCHITECTURE_AND_FEATURE_FLOWS.md)
- [Routes and endpoints](System%20Architecture/ROUTES_AND_ENDPOINTS.md)
- [Instructor excuse-letter review contract](System%20Architecture/EXCUSE_LETTER_INSTRUCTOR_REVIEW.md)
- [Complete database schema reference](System%20Architecture/DATABASE_SCHEMA_REFERENCE.md)
- [Testing and regression guide](System%20Architecture/TESTING.md)

## 3. System Installation

Setup, configuration, local runtime, build, production deployment, scheduler, permissions, and troubleshooting.

- [Complete Installation and Deployment Guide](System%20Installation/README.md)
- [Short Windows Setup Guide](System%20Installation/RUNNING_THE_SYSTEM.md)
- [Installer Links](System%20Installation/INSTALLATION_LINKS.md)

## Scope and current limitations

The system implements academic setup, accounts/roles, Registrar biometric enrollment, RFID/face attendance, online classes, Student/Parent portal, excuse letters, Messenger, reports, inventory/borrowing, Clinic cases, emergency alerts/SMS attempts, devices/settings, and audit logs.

Important current-code limitations are documented rather than hidden: some enrollment queries use `active` while normalized records default to `enrolled`; the Online Classes switch does not block every direct route; Student Management face buttons reference missing Admin route names; public registration and a legacy public message form remain enabled; and database queue tables are absent from migrations.

Use [Documentation Index](DOCUMENTATION_INDEX.md) for the full file map and source-of-truth rules.
