# Instructor Excuse-Letter Review

This document records the discovered behavior and implementation contract for the Instructor-side excuse-letter decision flow.

## Existing flow discovered

1. A Student or Parent creates an excuse letter in the Student/Parent portal.
2. Student-created letters wait in `pending_parent_approval`; Parent-created letters are immediately `approved`.
3. Parent approval generates the official PDF and sends it to selected Instructors, or all Instructors assigned to the Student's current enrollment context.
4. Delivery uses the unified `/messages` Messenger page. There is no separate Instructor excuse-letter inbox.
5. Each generated Instructor Messenger record is linked to its source letter using `student_portal_messages.student_excuse_letter_id`.

The explicit foreign key is important. Runtime authorization must not infer a letter from an attachment filename. The migration only uses the old `excuse-letter-{id}.pdf` naming convention as a one-time backfill for existing records.

## Instructor decision contract

The recipient Instructor sees Approve and Deny only when:

- the account role is `instructor`;
- the Instructor is the recipient of that exact Messenger record;
- the linked letter is Parent-approved (`approved`); and
- that delivered message has not already been reviewed.

The decision is stored on the delivered Messenger record, not globally on the letter. This allows several Instructors who received the same letter to make independent decisions.

Review fields are:

- `excuse_letter_review_decision`: `approved` or `denied`;
- `excuse_letter_reviewed_by_user_id` and `excuse_letter_reviewed_at`;
- final email subject and body; and
- resolved recipient details in `excuse_letter_review_recipients` JSON.

## Email behavior

The modal provides an editable template and two recipient checkboxes:

- Student: the Student record's valid email address;
- Parent: every linked Parent account with a valid email address.

The browser submits recipient roles only. The server resolves addresses, removes duplicates, rejects an empty recipient set, validates subject/body length, and attaches the generated PDF when the stored file still exists. A second review of the same delivered message is rejected.

Email delivery is synchronous because that matches the existing application mail flow. A queue or transactional outbox is the future alternative for high-volume production deployments; it would avoid holding the HTTP request while an SMTP provider responds.

## Validation and security findings

- UI controls are convenience only; the controller repeats role, recipient, linked-letter, status, and duplicate-review authorization.
- An Instructor cannot review an ordinary PDF attachment because it has no explicit letter foreign key.
- An Admin may view Messenger according to the existing role rules but cannot use the Instructor review endpoint.
- Invalid or missing Student/Parent email addresses disable the corresponding checkbox and are rejected again server-side.
- The generated PDF remains protected by the existing authorized attachment route.

## Verification

`tests/Feature/ExcuseLetterAttendanceWorkflowTest.php` covers the complete path: real attendance scan, current-enrollment Instructor lookup, generated attachment delivery, linked Parent discovery, unauthorized Instructor rejection, empty-recipient validation, approval, denial, duplicate-review rejection, and captured outgoing emails.

Run the migration and checks with:

```powershell
C:\xampp\php8.5.8\php.exe artisan migrate
C:\xampp\php8.5.8\php.exe artisan test
npm run build
```

The verified snapshot for this implementation is 200 backend tests and 2,743 assertions, plus a successful Vite production build.
