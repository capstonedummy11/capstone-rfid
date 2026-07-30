# Authentication and Password Rules

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

This is the canonical reference for login entry points, password recovery, temporary passwords, and first-login enforcement.

## Role Login Entry Points

- Student and Parent: public portal `/`
- Admin, Instructor, Registrar, and Clinic: configured secure staff login route
- Console: `/attendance-control-panel/login`

## Forgot Password

Forgot Password is available to Admin, Instructor, Registrar, Clinic, Student, and Parent accounts.

1. Select **Forgot password?** from either login page.
2. Enter the email registered to the account.
3. The system sends a time-limited reset link using the configured mail service.
4. Open the link, enter and confirm a new password, and submit.
5. A completed recovery clears any first-login password-change requirement.

For privacy, the request screen returns the same generic result even when an email is unknown or belongs to a Console account.

Console accounts cannot use email password recovery. Their access is managed through the attendance-panel administration process.

## New Account First Login

Accounts created for Admin, Instructor, Registrar, Clinic, Student, or Parent receive `must_change_password = true`.

After successful authentication:

- The user is redirected to **Create your private password** before any dashboard or role feature.
- Directly entering another protected URL redirects back to the password-change page.
- The new password must satisfy the configured password policy, must be confirmed, and must differ from the temporary password.
- After saving, the flag is cleared and the user proceeds to the correct role dashboard.
- Instructor verification occurs after the temporary password has been replaced.

Console accounts are excluded.

## Administrative Password Resets

When an administrator resets a student account to its default password, the account is marked to require a new private password at the next login.

Default-password formulas and account provisioning details are documented in [Default Account Passwords](DEFAULT_ACCOUNT_PASSWORDS.md).

## Security and Audit Notes

- Passwords are stored as hashes.
- Reset links use Laravel's password broker and `password_reset_tokens`.
- Password-reset requests do not reveal whether an account exists.
- Login and mutating password requests remain subject to throttling and system activity middleware.
- The first-login requirement is database-backed and enforced server-side.

