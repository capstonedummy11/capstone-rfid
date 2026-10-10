# System Architecture

This folder is the technical source of truth for developers, reviewers, maintainers, and deployment operators.

## Recommended reading order

1. [Architecture and Feature Flows](ARCHITECTURE_AND_FEATURE_FLOWS.md)
2. [System Use-Case Model](USE_CASE_MODEL.md)
3. [Implementation Catalog](IMPLEMENTATION_CATALOG.md)
4. [Routes and Endpoint Reference](ROUTES_AND_ENDPOINTS.md)
5. [Error Handling Guide](ERROR_HANDLING.md)
6. [Database Schema Reference](DATABASE_SCHEMA_REFERENCE.md)
7. [Database Documentation](DATABASE.md)
8. [System Flow](SYSTEM_FLOW.md)
9. [Page and Database Impact Map](PAGE_DATABASE_IMPACT_MAP.md)
10. [Testing and Regression Guide](TESTING.md)

The implementation is authoritative when it conflicts with documentation. The review date for the generated catalog is 2026-09-17 on branch `development`.

## Architecture summary

The application is a Laravel 12 monolith using server-side session authentication. Laravel routes/controllers perform authorization, validation, business logic, persistence, external-service calls, and Inertia responses. Vue 3 page components render the interface and submit Inertia forms or `fetch` JSON requests. MySQL is the intended database; Laravel filesystem stores faces, evidence, message attachments, class files, sounds, and generated PDFs.

There is no separate versioned REST API. The endpoint surface is the `web` middleware stack: HTML/Inertia GET requests, form mutations, JSON panel operations, authorized file responses, and CSV/XLSX exports.
