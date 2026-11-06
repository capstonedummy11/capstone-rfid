# Error Handling Guide

This guide describes the current Laravel 12 web application. Requests use the `web` middleware stack and return Inertia pages, redirects, file responses, or JSON. The application does not have a separate versioned REST API.

## Choose the right response

| Situation | Handling | What the user sees |
| --- | --- | --- |
| Invalid input or an expected business rule | Validate at the request boundary. Use Laravel validation or `ValidationException::withMessages()` for field-specific errors. | An Inertia form receives errors for its fields; a JSON request receives a validation response (normally HTTP 422). |
| Unauthenticated, forbidden, missing, expired CSRF token, or rate-limited request | Let authentication, authorization, model binding, CSRF, and throttle middleware use their configured responses. | A redirect or an appropriate HTTP error. These are not automatically server faults. |
| Unexpected exception | Let it reach Laravel's exception handler unless this operation has a specific recovery path. | A safe error response; details belong in server logs, not in the page. |
| Recoverable failure inside a workflow | Catch the exception at that workflow boundary, call `report($exception)`, and return an actionable but safe error. Use `DB::transaction()` when related writes must succeed or fail together. | A form error or other explicit failure response. Never return a success message for a failed write. |

For example, [first-login password setup](../../app/Http/Controllers/Shared/Auth/FirstLoginPassword/FirstLoginPasswordController.php) rejects reuse of the temporary password with `ValidationException::withMessages()`. A plain `abort(422)` for an Inertia form produced a generic error overlay instead of a field message. The [Subject Offering instructor assignment](../../app/Http/Controllers/Admin/Subjects/SubjectController.php) has a bounded `try/catch`: it reports unexpected failures, rolls back the transactional write, and returns an offering form error.

## Where unexpected errors go

The exception configuration in [bootstrap/app.php](../../bootstrap/app.php) adds the route name, HTTP method, and actor ID to Laravel exception reports. Laravel handles uncaught exceptions and chooses the response for the request. The logging destination comes from [config/logging.php](../../config/logging.php) and the environment's `LOG_CHANNEL` and `LOG_LEVEL` settings. In production, keep `APP_DEBUG=false`; see the [installation guide](../System%20Installation/README.md) for the server logging configuration.

Do not put passwords, tokens, full request bodies, or exception traces in user-facing messages or activity descriptions. A local catch that intentionally turns an exception into a form response must call `report($exception)` if operators need the failure details: Laravel no longer sees that exception as uncaught.

## System Activity Logs versus application logs

[RecordSystemActivity](../../app/Http/Middleware/RecordSystemActivity.php) writes database activity records for `POST`, `PUT`, `PATCH`, and `DELETE` requests, the Activity Logs page, named `.export` GET routes, and other web responses with status 400 or higher that pass through it. It also catches exceptions thrown by later middleware or controllers, attempts to record a failure, and rethrows the exception so Laravel can handle it.

The activity record holds request metadata such as actor, role, route, method, subject, IP, user agent, status, outcome, and severity. A 400-level failure is a warning; a 500-level failure is an error. A form submission can return HTTP 302 yet still be marked as failed when that response newly flashes validation errors. The activity description is intentionally generic; inspect the application log for the exception and stack trace.

An activity row is **best effort**, not a guarantee that every failure was persisted. A request can fail before this middleware runs; console commands and queued jobs do not use this web middleware; or the audit database write can fail. If audit storage fails, the middleware tries `report($exception)` and preserves the original response or exception. If both audit storage and application logging fail, neither record can be guaranteed. A workflow-specific activity entry may also coexist with the middleware's request entry.

## Adding error handling to a workflow

1. Validate input and authorize the actor before changing data. Give expected rule failures field-specific messages.
2. Use a transaction for related database writes that must be atomic.
3. Catch only where the workflow can recover or provide a useful response. Report caught unexpected exceptions, then return a clear failure. Otherwise allow Laravel's exception handler to report and render them.
4. Test a valid request, validation or authorization failure, and any meaningful transaction rollback. For audited web paths, assert the failure outcome and remember that audit persistence itself is best effort.

Wrapping every controller action in `try/catch (Throwable)` can hide defects, duplicate reports, or convert meaningful 403/404/422 responses into 500s. Returning an error without reporting the caught unexpected exception loses the diagnostic trace. Conversely, treating the audit table as the only error log loses failures that happen before or outside web middleware.

## Investigating a reported failure

Record the approximate time, route, HTTP method, actor, and visible status. Check the Admin System Activity Logs for the request outcome, then check Laravel's configured application log and the host PHP/web-server error log for its cause. On a production server, leave `APP_DEBUG=false`; fix the underlying failure and verify the original workflow again. For the relevant regression commands and release checks, use the [testing guide](TESTING.md).

The centralized exception configuration and validation-to-Inertia behavior described here are specific to this project's Laravel 12 and Inertia setup. If those framework versions or response conventions change, verify the actual redirect, JSON, and reporting behavior before copying these patterns.
