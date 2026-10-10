# Authentication and Password Rules

Documentation home: [Documentation Index and Source-of-Truth Map](../DOCUMENTATION_INDEX.md).

This is the canonical reference for login entry points, password recovery, temporary passwords, and first-login enforcement.

## Role Login Entry Points

All role login and password-lifecycle screens display the shared `logo-only.jpg` school seal for consistent authentication branding.

- Student and Parent: public portal `/`
- Admin, Instructor, Registrar, and Clinic: configured secure staff login route
- Console: `/attendance-control-panel/login`

The compatibility path `/login` redirects to the public Student/Parent entry point. Protected Admin, Instructor, Registrar, and Clinic pages redirect unauthenticated users to the secure staff login instead of the public portal.

The public Student/Parent interface does not display the secure staff login. The shared Forgot Password page has one context-aware back button: when opened from the secure Staff login it returns there; otherwise it returns to the Student/Parent login. After a successful staff password reset, the system redirects that account to the secure staff login.

## Browser and Device Sessions

Authentication uses Laravel's server-side session guard and an HTTP-only session cookie, not bearer tokens. The session cookie has no persistent expiry and is discarded when the browser session closes. Both portal login forms offer **Remember my email** as an optional browser-only convenience; it saves normalized profile display information, never the password, and does not keep the user signed in. Login endpoints ignore submitted authentication `remember` values, and the authentication middleware rejects legacy persistent remember-cookie authentication, so closing and reopening the browser requires a new sign-in. Browser session restore features can preserve session cookies after a restart; users should still select **Log out** on shared devices for immediate server-side invalidation.

Each successful login regenerates the Laravel session ID and issues a unique login-instance ID inside that server-side session. The browser stores only its own encrypted session cookie; authenticated requests resolve the user from that session and verify that the session remains bound to the same user. No application-wide "current user" value is shared between requests or devices.

- One browser profile has one active account because it has one cookie jar. Sign out before changing accounts in that profile.
- A different browser, private window, browser profile, or device has an independent cookie and can remain signed in at the same time.
- A new login does not invalidate sessions in other browsers or devices.
- If stored session identity and authenticated identity ever disagree, the affected session is invalidated and receives a clear sign-in-again response instead of silently switching users.
- Production uses the database session driver, whose `sessions.id` primary key keeps each browser session separate. Do not key authenticated state by IP address, a static cache key, or a process-wide variable.

## Instructor Email OTP

After successful staff authentication, an Instructor is sent to the Instructor Verification screen. The Instructor can request a six-digit OTP through the registered email address. The OTP expires after 10 minutes and is stored only as a hash in the session. Face verification and configured security questions remain alternative Instructor verification methods.

If email delivery fails, no usable Instructor OTP remains in the session. The page asks the Instructor to retry or use face or security-question verification. If an Admin login code cannot be emailed, the Admin verification page says no code is available, offers Resend code, and advises contacting the system administrator if delivery continues to fail. Admin access remains blocked until a valid emailed code is verified.

## Forgot Password

Forgot Password is available to Admin, Instructor, Registrar, Clinic, Student, and Parent accounts.

1. Select **Forgot password?** from either login page.
2. Enter the email registered to the account.
3. The system sends a time-limited reset link using the configured mail service.
4. Open the link, enter and confirm a new password, and submit.
5. A completed recovery clears any first-login password-change requirement.

The New Password field shows the requirement before typing. New passwords must contain at least 12 characters. A shorter value is rejected in both the browser and backend with **Password must be at least 12 characters long.** Confirmation continues to enforce matching and does not define a separate complexity policy.

For privacy, the request screen returns the same generic result even when an email is unknown or belongs to a Console account.

Console accounts cannot use email password recovery. Their access is managed through the attendance-panel administration process.

Password-reset links and other application website links included in emails use the canonical `APP_URL` value. For local XAMPP operation this is `http://localhost`; production must replace it with the public HTTPS application URL and rebuild the configuration cache.

## New Account First Login

Accounts created for Admin, Instructor, Registrar, Clinic, Student, or Parent receive `must_change_password = true`.

Admin User Management uses the same live strength meter and checklist for its temporary password field: at least 12 characters, lowercase and uppercase letters, at least one number, and at least one symbol. The Create/Save action remains unavailable until the entered password and confirmation satisfy the displayed rules. When editing an existing managed user, leaving both password fields blank preserves the current password.

After successful authentication:

- The user is redirected to **Create your private password** before any dashboard or role feature.
- Admin and Root Admin accounts complete this password step before entering the email code sent for the current login. If the code expires during password setup, they can request a new code on the verification page.
- Directly entering another protected URL redirects back to the password-change page.
- The New Password field displays a live strength bar and checklist for at least 12 characters, lowercase and uppercase letters, at least one number, and at least one symbol.
- Each checklist item turns green when satisfied, and the first-login form remains disabled until every displayed requirement is met. Length, requirement, and confirmation messages update as the user types without a page reload. The password must also differ from the temporary password; that server-only check returns an inline password-field error after submission and clears when the user edits the field. Production additionally checks that it is not present in known compromised-password data.
- After saving, the flag is cleared and the user proceeds to the correct role dashboard.
- A cached login form cannot bypass the changed password while the browser still has the current account's session. Submitting that form starts a fresh same-account password check: the former temporary password is rejected and signs out that session; the new password starts a new login. Switching to another account in the same browser session remains blocked.
- Instructor verification occurs after the temporary password has been replaced.

Console accounts are excluded.

The authenticated Student/Parent Change Password form uses the same 12-character minimum and messages. Existing current-password and confirmation checks remain unchanged.

Reset, first-login, and Student/Parent password submissions accept only one in-flight request. Their submit buttons remain disabled and show one stable loading label until the request succeeds or fails, then become available again. Validation messages use the existing single field-error state rather than stacking separate alerts.

## Administrative Password Resets

When an administrator resets a Student, Instructor, Clinic, or Registrar account to its documented default password, the account is marked to require a new private password at the next login. Instructor, Clinic, and Registrar resets also revoke remember-me access and delete the account's active database sessions. Clinic and Registrar reset passwords use the lowercase account Name with spaces removed; Admin accounts are excluded from this action.

Default-password formulas and account provisioning details are documented in [Default Account Passwords](DEFAULT_ACCOUNT_PASSWORDS.md).

An administrator can also reset an Instructor's security questions without changing the password. This clears both the legacy single-question fields and the current three-question set, rotates remember-me access, and ends active Instructor sessions. At the next verification, the Instructor must create three new security questions before that verification method is available again.

## Security and Audit Notes

- Passwords are stored as hashes.
- Reset links use Laravel's password broker and `password_reset_tokens`.
- Password-reset requests do not reveal whether an account exists.
- Login and mutating password requests remain subject to throttling and system activity middleware.
- The first-login requirement is database-backed and enforced server-side.
