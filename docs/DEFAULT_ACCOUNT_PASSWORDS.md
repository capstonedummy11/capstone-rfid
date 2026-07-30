# Default Account Passwords

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

Login recovery and mandatory first-login behavior are defined in [Authentication and Password Rules](AUTHENTICATION_PASSWORD_RULES.md).

This document describes how the application assigns an initial password when each type of account is created.

> Security notice: Default and seeded passwords are intended only for first access or local demonstration. Change them immediately, never reuse them in production, and do not add real production credentials to this repository.

## Operational Account-Creation Rules

| Account type | Creation path | Initial password |
| --- | --- | --- |
| Root admin | Admin User Management, created by an existing root admin | No default. The creator must enter a password of at least 8 characters. |
| Admin | Admin User Management, created by a root admin | No default. The creator must enter a password of at least 8 characters. |
| Clinic | Admin User Management | No default. The creator must enter a password of at least 8 characters. |
| Registrar | Admin User Management | No default. The creator must enter a password of at least 8 characters. |
| Instructor | Admin Instructor Management | `password` |
| Student | Admin Student Management | Student's first name plus last name with all spaces removed. |
| Parent | Parent account creation/linking in Student Management | No default. The creator must enter a password of at least 8 characters for a new parent account. |
| Self-registered student/user | Public registration | No default. The user chooses and confirms their password. |
| Runtime attendance console | Attendance panel access | No reusable account password. The system generates a random 40-character password and users authenticate with the configured panel PIN. |

## Student Password Formula

When Admin Student Management creates a student record, the application creates or synchronizes the matching student portal account.

The initial password is:

```text
FirstName + LastName, with every space removed
```

Examples:

| Student name | Initial password |
| --- | --- |
| Juan Dela Cruz | `JuanDelaCruz` |
| Andrea Santos | `AndreaSantos` |
| Nina Dela Cruz | `NinaDelaCruz` |

Capitalization is preserved from the saved first and last names. If both names are unexpectedly empty, the student number is used as a fallback.

Updating a student record does not automatically replace the existing password. The **Reset Default Password** action explicitly resets it using the current first-name-plus-last-name formula.

## Existing Parent Accounts

When an existing parent account is linked to another student:

- Leaving the password field empty preserves the parent's current password.
- Entering a new password replaces the existing password.
- Unlinking a parent from one student does not delete the parent account or change its password.

## Seeded Development Accounts

The standard database seeders create these demonstration accounts:

| Role | Email | Seeded password |
| --- | --- | --- |
| Root admin | `root.admin@sample.com` | `sample` |
| Admin | `test@example.com` | `password` |
| Admin | `jeromebernante@gmail.com` | `1234` |
| Admin | `vallecera@gmail.com` | `sample` |
| Admin | `admin@gmail.com` | `password` |
| Instructor | `instructor@sample.com` | `sample` |
| Clinic | `clinic@sample.com` | `sample` |
| Registrar | `registrar@sample.com` | `sample` |
| Console | `comlab1@example.com` through `comlab5@example.com` | `1234` |
| Student | `andrea.santos@student.sample.com` | `sample` |
| Student | `miguel.reyes@student.sample.com` | `sample` |
| Parent | `parent.andrea.santos@sample.com` | `sample` |

Seeder passwords are development fixtures and do not override the operational account-creation rules above.

## Password Changes

- Students and parents can change their password from the portal after supplying their current password.
- Staff accounts can update passwords through the applicable account-management or password-reset flow.
- Instructor email OTP is a temporary verification code, not an account password. It expires after 10 minutes.
- The attendance panel PIN is separate from the generated runtime console-account password.
