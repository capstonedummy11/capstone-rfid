# Instructor Excuse-Letter Review

This document records the discovered behavior and implementation contract for the Instructor-side excuse-letter decision flow.

## Current submission and review flow

1. A Student or enabled Parent submits an excuse letter in the Student/Parent portal. Submission always creates it as `pending_instructor_approval`.
2. When **Require Parent Approval Before Instructor Review** is off, Student submission immediately creates an `Excuse Letter Submitted: ...` Messenger item for selected Instructors, or all Instructors assigned to the Student's current enrollment context. The Student sees the sent item on the dashboard.
3. When that setting is on, the letter starts as `pending_parent_approval`. Only linked Parents receive an `Excuse Letter Needs Parent Approval: ...` dashboard message. The Student does not receive this Parent-action reminder.
4. A linked Parent approves by entering their name. The letter then moves to `pending_instructor_approval`, and the assigned Instructors receive the `Excuse Letter Submitted: ...` Messenger item.
5. Delivery uses the unified `/messages` Messenger page. There is no separate Instructor excuse-letter inbox.
6. Each generated Instructor Messenger record is linked to its source letter using `student_portal_messages.student_excuse_letter_id`.

The explicit foreign key is important. Runtime authorization must not infer a letter from an attachment filename. The migration only uses the old `excuse-letter-{id}.pdf` naming convention as a one-time backfill for existing records.

## Instructor decision contract

The recipient Instructor sees Approve and Deny only when:

- the account role is `instructor`;
- the Instructor is the recipient of that exact Messenger record;
- the linked letter is pending (`pending_instructor_approval`); and
- that delivered message has not already been reviewed.

The decision is stored on the delivered Messenger record and updates the linked letter to `approved` or `denied`. The first Instructor decision closes review for that letter. Approval emails and dashboard-notifies the Student only; denial sends no notification.

The live letter template preview and generated PDF show a Parent Signature section only when **Require Parent Approval Before Instructor Review** is enabled. The downloaded PDF includes the actual Parent signature only after that approval is recorded. Letters submitted while the setting is off do not show the signature section.

Review fields are:

- `excuse_letter_review_decision`: `approved` or `denied`;
- `excuse_letter_reviewed_by_user_id` and `excuse_letter_reviewed_at`;
- final email subject and body; and
- resolved recipient details in `excuse_letter_review_recipients` JSON.

## Email behavior

The modal provides an editable template and a Student email recipient. After approval, the system emails and creates an `Approved Excuse Letter: ...` portal reminder for the Student only. Denial is recorded without sending a notification.

The Parent is not an Instructor-decision notification recipient. Parent notification occurs only at the separate Parent-approval stage when the setting is enabled.

The browser submits only the Student recipient role. The server resolves the Student email address, validates subject/body length, and attaches the generated PDF when the stored file still exists. A second review of the same delivered message is rejected.

Email delivery is synchronous because that matches the existing application mail flow. A queue or transactional outbox is the future alternative for high-volume production deployments; it would avoid holding the HTTP request while an SMTP provider responds.

## Validation and security findings

- UI controls are convenience only; the controller repeats role, recipient, linked-letter, status, and duplicate-review authorization.
- An Instructor cannot review an ordinary PDF attachment because it has no explicit letter foreign key.
- An Admin may view Messenger according to the existing role rules but cannot use the Instructor review endpoint.
- An invalid or missing Student email disables the checkbox and is rejected again server-side.
- Turning off Parent Portal also disables the Parent-approval setting; the default path then requires Instructor review only.
- The generated PDF remains protected by the existing authorized attachment route.

## Verification

`tests/Feature/ExcuseLetterAttendanceWorkflowTest.php` covers the complete path: current-enrollment Instructor lookup, pending submission delivery, linked Parent discovery, unauthorized Instructor rejection, empty-recipient validation, Instructor approval and denial, approval reminders, duplicate-review rejection, and captured outgoing emails.

Run the migration and checks with:

```powershell
C:\xampp\php8.5.8\php.exe artisan migrate
C:\xampp\php8.5.8\php.exe artisan test
npm run build
```

The latest verified snapshot, including academic-year date validation and server logging coverage, is 201 backend tests and 2,758 assertions, plus a successful Vite production build.
