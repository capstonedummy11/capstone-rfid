# Clinic Dispatch Assignment

This is the canonical reference for assigning and receiving internal Clinic dispatches.

## Purpose

A Clinic user who reviews an emergency alert assigns a specific Clinic account to respond. The selected responder receives the location and available student context, while the system keeps the response linked to a Clinic Case.

Dispatch is an internal school workflow. It is not confirmation that an ambulance, police unit, or other external service was contacted.

## Dispatch Flow

1. An active emergency appears on the Clinic dashboard.
2. The reviewing Clinic user selects an active Clinic account from **Assign Clinic responder**.
3. The reviewing user selects **Dispatch**.
4. The emergency alert changes to `acknowledged`.
5. The system creates or updates a linked Clinic Case with status `monitoring`.
6. `handled_by_user_id` is set to the selected Clinic responder.
7. The selected responder receives an email containing the location, patient, emergency details, up to three recent Clinic history records, and up to three recent attendance records.
8. The selected responder sees the case under **My Dispatch Assignments**, including the student/patient and the name of the responder sent, and proceeds to the stated location.
9. The responder continues documentation in **Clinic Case Logs** and **Patient History**.

If the alert cannot be linked to a student, the notification states that no linked student history was found.

## Assignment Rules

- Only an active, non-deleted account whose role is `clinic` can be selected.
- Dispatch cannot be submitted until a Clinic responder is selected.
- The dispatcher and responder may be the same Clinic account, but assignment is always explicit.
- An email delivery failure is logged and does not undo the saved dispatch or Clinic Case.
- Reassigning the same alert updates the linked case handler.
- The dispatch action is recorded in the activity log with the selected responder's name.
- Clinic Case Logs display **Responder sent** beside the student/patient so staff can identify who was dispatched.

## Seeded Clinic Accounts

| Account | Email | Default password |
|---|---|---|
| Clinic Staff | `clinic@sample.com` | `sample` |
| Clinic Responder | `clinic.responder@sample.com` | `sample` |

These development passwords must be changed according to [Authentication and Password Rules](AUTHENTICATION_PASSWORD_RULES.md).

## Related References

- [Roles and Functionality](ROLES_AND_FUNCTIONALITY.md)
- [System Flow](SYSTEM_FLOW.md)
- [User Operations Tutorial](USER_OPERATIONS_TUTORIAL.md)
- [Default Account Passwords](DEFAULT_ACCOUNT_PASSWORDS.md)
