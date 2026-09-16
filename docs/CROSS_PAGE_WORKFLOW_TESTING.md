# Cross-Page Workflow Testing

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

This procedural guide defines how to test features where data created on one page must be consumed by another page. An example is creating a student in Admin Student Management and then using the generated account on the Student Login page.

## Required Testing Rule

When a change connects two or more pages, create a Laravel feature test that exercises the complete workflow through the real HTTP routes.

The test must:

1. Create only prerequisite records directly, such as an active academic year, strand, section, and authorized administrator.
2. Submit the producer page's real route, such as `admin.students.store`.
3. Read the record produced by that request from the database.
4. Assert the important persisted values and relationships.
5. Continue through the consumer page's real route using those same values, such as the student's email and generated password.
6. Assert authentication, authorization, redirects, middleware behavior, and the final page payload.

Do not create the result record directly when the purpose of the test is to prove that the producer page creates it correctly.

## Test Isolation

Separate `test()` blocks must not depend on data created by another test. Tests may run in any order or in parallel, and `RefreshDatabase` resets database state between tests.

Use one of these patterns:

- Keep a connected multi-page journey in one test when later requests must consume the exact record created by an earlier request.
- Use a helper function or factory to build identical prerequisite data independently for several tests.
- Use `beforeEach()` only for independent setup, never to preserve mutable state from a previous test.

Reuse data across multiple requests inside one workflow test, but recreate fixtures independently across multiple tests.

## Student Provisioning Example

The student provisioning workflow should prove this sequence:

```text
Admin Student Management POST
    -> students record
    -> student_enrollments record
    -> users record with role=student
    -> admin logout
    -> Student Login POST with submitted email and generated password
    -> mandatory first-login password change
    -> Student Dashboard displays the created student
```

The implementation is covered by `tests/Feature/StudentAccountProvisioningTest.php`.

## Assertions to Include

For a cross-page workflow, assert behavior at each boundary:

- The producer request redirects or returns the expected status.
- Database records contain the submitted values and correct foreign keys.
- Passwords are checked with `Hash::check`; never compare password hashes directly.
- The previous role is logged out before testing another role's login.
- The consumer request authenticates the expected account.
- Security middleware still runs, including mandatory first-login password replacement.
- The final Inertia component receives the created record's identifying values.

## Middleware Guidance

Do not disable all middleware for cross-page workflow tests. Authentication, role authorization, and password-lifecycle middleware are part of the behavior being verified.

Laravel's HTTP testing environment handles CSRF protection for test requests, so a feature test normally does not need to call `withoutMiddleware()`. Disable a specific middleware only when the test explicitly requires it and the Laravel test case supports that helper.

## Running the Test

Run the focused workflow:

```bash
php artisan test --compact tests/Feature/StudentAccountProvisioningTest.php
```

Then run the related password tests:

```bash
php artisan test --compact tests/Feature/PasswordLifecycleTest.php tests/Feature/StudentAccountProvisioningTest.php
```

## Why This Is the Standard Approach

Laravel feature tests verify routing, validation, controllers, transactions, database relationships, authentication, middleware, and Inertia responses together. They provide stronger workflow confidence than unit tests while remaining faster and less fragile than browser tests.

Browser tests remain useful when the risk is specifically JavaScript interaction, form rendering, or visual behavior. Unit tests remain appropriate for isolated password formulas or domain services. Neither replaces the connected feature test when multiple server-rendered pages share persisted data.

Common mistakes include bypassing the producer route, sharing state between separate tests, disabling all middleware, omitting logout between roles, comparing password hashes as strings, or checking only the final redirect without verifying the database linkage.
