# Testing and Regression Guide

This is the canonical guide for running automated tests and verifying the application after a feature update.

## Requirements

Before running tests, install the project dependencies:

```bash
composer install
npm install
```

The automated Laravel tests use the testing settings in `phpunit.xml`:

- SQLite in-memory database;
- array cache and session drivers;
- synchronous queues; and
- array mail driver.

Tests do not modify the normal development database and do not send real emails. Email and notification behavior is inspected through Laravel fakes or saved workflow results.

## Requested-Feature Regression Suite

Run the focused suite for OTP, excuse letters, Messenger notifications, navigation cleanup, Student Biometric Enrollment, attendance editing, password lifecycle, autosuggestions, and Clinic dispatch:

```bash
composer test:requested-features
```

The command is defined in `composer.json` and explicitly runs the relevant feature-test files. It should never return “No tests found.”

### Coverage map

| Feature | Primary automated coverage |
|---|---|
| Instructor email OTP | `tests/Feature/Auth/AuthenticationTest.php` |
| Parent and Instructor excuse-letter notifications | `tests/Feature/StudentParentPortalTest.php` |
| Instructor Messenger text and generated PDF attachment | `tests/Feature/StudentParentPortalTest.php` |
| Five-minute Messenger email cooldown per sender–recipient pair | `tests/Feature/StudentParentPortalTest.php` |
| Standalone RFID navigation hidden and Forgot Password back-button context | `tests/Feature/RequestedFeatureUiWiringTest.php`, `tests/Feature/Auth/PasswordResetTest.php` |
| Student Biometric Enrollment naming and separation | `tests/Feature/RequestedFeatureUiWiringTest.php`, `tests/Feature/RegistrarPortalTest.php` |
| Instructor attendance status editing and configured date window | `tests/Feature/AttendancePanelVerificationTest.php`, `tests/Feature/SystemSettingsTest.php` |
| First-login password replacement | `tests/Feature/PasswordLifecycleTest.php` |
| Forgot Password and password reset, with Console exclusion | `tests/Feature/PasswordLifecycleTest.php`, `tests/Feature/Auth/AuthenticationTest.php` |
| Searchable autosuggestions in large Admin relationship fields | `tests/Feature/RequestedFeatureUiWiringTest.php` |
| Clinic responder assignment, notification, history, and case ownership | `tests/Feature/ClinicFlowTest.php` |

## Full Backend Suite

Run every Pest/PHPUnit test:

```bash
php artisan test --compact
```

Use the full suite before committing, deploying, or demonstrating a broad set of changes.

To run one file:

```bash
php artisan test --compact tests/Feature/ClinicFlowTest.php
```

To run a test by name:

```bash
php artisan test --compact --filter="clinic dispatch"
```

## Frontend Verification

Compile all Vue, TypeScript, Tailwind, Inertia, and generated route assets:

```bash
npm run build
```

A successful backend suite does not replace this build. The build catches missing imports, malformed Vue templates, invalid generated route usage, and bundling errors.

Optional formatting checks:

```bash
npm run format:check
composer test:lint
```

## Recommended Verification Sequence

1. Run `composer test:requested-features`.
2. Fix any focused regression.
3. Run `php artisan test --compact`.
4. Run `npm run build`.
5. Run `git diff --check`.
6. Review the changed screens manually when browser interaction, layout, real SMTP delivery, camera hardware, RFID hardware, or an external provider is involved.

## Interpreting Failures

- **Validation or status mismatch:** compare the expected business rule with the canonical feature document before changing the assertion.
- **Route not defined:** run `php artisan route:list` and confirm the component uses the active named route.
- **Database table missing:** ensure the test uses `RefreshDatabase` and that all migrations are committed.
- **No tests found:** use `composer test:requested-features`; do not replace it with a separate PHPUnit configuration.
- **Email not received in a test inbox:** automated tests use a non-delivering mail driver. Use a controlled manual SMTP test for real delivery.
- **Frontend build permission error on Windows:** retry from the repository root and ensure no process is locking the project or parent folder.

## Adding Coverage for Future Updates

1. Add or update a behavioral feature test near the affected module.
2. Add the test file to `test:requested-features` in `composer.json` if it protects this checklist.
3. Update the coverage map in this document.
4. Run the focused suite, full suite, and frontend build.
5. Update the relevant canonical business documentation.
