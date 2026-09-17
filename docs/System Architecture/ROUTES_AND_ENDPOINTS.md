# Routes and Endpoint Reference

## Endpoint style

All application endpoints use Laravel's `web` middleware and session/CSRF model. GET requests usually return Inertia pages; mutations return redirects with flash/errors or JSON for the Console; exports/files return streamed responses. This is not a stateless REST API.

## Public and authentication endpoints

| Methods and URI | Name/purpose | Main protection |
| --- | --- | --- |
| `GET /`, `GET /home`, `GET /dashboard` | Landing and role redirects. | Dashboard requires auth. |
| `GET /about`, `GET /up` | About placeholder and health response. | Public. |
| `GET/POST /{SECURE_LOGIN_ROUTE}` | Staff login page/submit. | Guest + login throttle on POST. |
| `GET /secure-login` | Compatibility redirect to configured staff path. | Public. |
| `POST /login` | Student/Parent login submit. | Guest + login throttle. |
| Fortify reset/verify/2FA routes | Password reset, confirmation, verification, two factor, logout. | Fortify/web middleware. |
| `GET/PUT /first-login/password` | Required temporary-password replacement. | Auth; update throttled. |
| `GET/POST /register` | Public registration UI/submit. | Public. |
| `GET /messages/new`, `POST /messages` | Legacy public message form/store. | Public in current routes. |

## Instructor verification

All use prefix `/instructor`, auth, and `role:instructor`: `GET /verify`; `POST /verify/face`; `POST /verify/otp/send`; `POST /verify/otp`; `POST /verify/security/setup`; `POST /verify/security`.

## Shared messages and reports

Authenticated roles Admin, Instructor, Clinic, Registrar, Student, Parent use `GET /messages`, unread status, conversation POST, read PUT, authorized attachment GET, and excuse-forward POST. `GET /reports` and `/reports/export` use the same roles. `EnsureParentPortalEnabled` additionally blocks Parent when disabled.

## Attendance Panel

Public bootstrap endpoints: `GET /attendance-control-panel/login`, `POST /panel-verify`, and legacy `POST /face-recognition/verify-student`.

Console-only `/attendance-control-panel` endpoints include page GET plus POST operations for room selection, logout, status, RFID lookup, session state, student/instructor face checks, student tap, attendance-log snapshot, borrow-only operation, generic face verification, and emergency alert. Authorized staff/portal roles access evidence at `GET /attendance-evidence/{attendanceLog}/{moment}` with ownership/scope checks in the controller.

## Admin and Instructor endpoints

Prefix `/admin`, auth:

- Admin or verified Instructor: dashboard; attendance scanner/logs/subject/summary/student/session/export/status; messages/reply; online-class CRUD/cancel; Student list; Schedule list.
- Admin only: Academic Year lifecycle/rollover; Laboratory CRUD; Borrowing/return; RFID update/clear; Section, Subject/Offering, Schedule, Inventory/Item, Strand, Student/Parent, Instructor, User CRUD; Activity and Online Class log exports; Active Device/PIN/session management; System Settings/sound library; prototype students-management route.

Endpoint names and controller methods are declared in `routes/web.php`; the runtime route list is authoritative when duplicate URIs exist. In particular, the final `GET /admin/inventory` closure is named `admin.inventory` and supersedes the earlier same-URI index route in the runtime list.

## Registrar endpoints

Prefix `/registrar`, auth + `role:registrar`: dashboard, student biometric page, Instructor face page, Student RFID PUT, Student face POST/DELETE, Instructor RFID PUT, and Instructor face POST/DELETE.

## Clinic endpoints

Prefix `/clinic`, auth + `role:clinic,admin`: dashboard; Case Log GET/POST/PUT and case-to-history POST; Patient History GET/POST/PUT/DELETE; Clinic Reports GET/export; Emergency Hotline GET/POST/PUT/DELETE; Emergency Type POST/PUT/DELETE; Alert status PUT and dispatch POST; authorized uploaded emergency-sound GET.

## Student/Parent endpoints

Prefix `/student-parent`, auth + `role:student,parent` + Parent Portal middleware: dashboard; profile GET/PUT; password PUT; attendance GET; excuse-letter GET/POST/approve/download/attachment; messages GET/POST; notifications GET/read; online classes GET/join.

## Settings endpoints

Under `/settings`: authenticated profile GET/PATCH; verified account deletion, password GET/PUT, appearance GET, and password-confirmed two-factor GET. Fortify registers the supporting two-factor mutation endpoints.

## Console commands and schedule

| Command | Purpose |
| --- | --- |
| `online-classes:finalize-attendance` | Persist Absent rows for eligible students who did not join ended online classes. |
| `academic-years:check-integrity` | Validate academic-year consistency; implemented as `CheckAcademicYearIntegrity`. |
| `db:schema-notes` | Print database tables/columns when a database connection is available. |
| `system:features` | Print a quick feature/role summary. |
| `schedule:run` | Run due scheduled tasks once. |
| `schedule:work` | Keep the scheduler running locally. |

The scheduler invokes `online-classes:finalize-attendance` every minute with overlap protection.

