# Implementation Catalog

This catalog maps implementation classes and frontend building blocks to their responsibilities. Use it with the route, schema, and feature-flow references when locating code.

## Controllers

| Controller | Responsibility |
| --- | --- |
| `AcademicYearController` | Year lifecycle, active semester, reopen reason, rollover preview/execute, fallback metrics. |
| `ActiveDeviceController` | Laboratories/devices, global and per-device PINs, active panel state, remote logout. |
| `ActivityLogController` | General audit filtering and CSV export. |
| `AdminUserController` | Admin/Root Admin/Registrar/Clinic account management and privilege invariants. |
| `AttendanceController` | Console sessions, schedules, RFID lookup, verification grants, tap state machine, evidence, legacy scanner/logs, manual status writes. |
| `AttendanceManagementController` | Subject/session/student attendance analytics, physical/online rosters, corrections, CSV/XLSX exports. |
| `AuthController` | Custom public registration submit. |
| `BorrowController` | Borrowing/return transactions, borrower/item catalogs, panel borrowing JSON. |
| `ClinicController` | Clinic dashboard, Case Logs, Patient History, Clinic reports/export. |
| `DashboardController` | Admin/Instructor role-scoped dashboard payload. |
| `EmergencyController` | Panel alert creation, hotline/type CRUD, alert status, dispatch, SMS, response metrics. |
| `FirstLoginPasswordController` | Required temporary-password replacement. |
| `InstructorsController` | Instructor account/profile CRUD and scope payload. |
| `InstructorVerificationController` | Face, OTP, and security-question Instructor session verification. |
| `InventoryController` | Main `inventory_items` CRUD page operations. |
| `ItemController` | Legacy/simple `items` CRUD. |
| `LaboratoryController` | Focused Laboratory CRUD. |
| `MessageController` | Legacy public inbox, unified Messenger, unread status, attachments, replies, parent forwarding. |
| `OnlineClassController` | Class CRUD, student view/join, face requirement, finalization, logs/export, notifications. |
| `RegistrarController` | Registrar dashboard and Student/Instructor RFID/face enrollment. |
| `ReportController` | Shared role-aware reports, academic-year/date filters, CSV export. |
| `RfidController` | Hidden legacy Admin combined RFID assignment/clear. |
| `ScheduleController` | Role-scoped schedule list and Admin CRUD using current offerings. |
| `SectionController` | Academic-year/semester Section CRUD and historical protections. |
| `StaffLoginController` | Staff-only login and role redirects. |
| `StrandController` | Strand CRUD and related counts/options. |
| `StudentParentLoginController` | Student/Parent-only login and Parent Portal enforcement. |
| `StudentsController` | Student/enrollment/account/Parent management plus all Student/Parent portal data, letters, notifications, and compatibility message methods. |
| `SubjectController` | Subject catalog and Subject Offering lifecycle. |
| `SystemSettingsController` | Feature/attendance/security settings and emergency-sound library. |
| `Settings/ProfileController` | Starter-kit profile update/delete. |
| `Settings/PasswordController` | Authenticated password update. |
| `Settings/TwoFactorAuthenticationController` | Two-factor settings page state. |

## Services

| Service | Responsibility and important behavior |
| --- | --- |
| `AcademicYearService` | Transactional activate/close/archive/reopen invariants; one active year. |
| `AcademicYearRolloverService` | Preview, editable Section mapping, selectable Subject Offering creation, Student decision execution, and idempotent audit items. Selected offerings reuse the Subject catalog but do not copy Instructors or Schedules. |
| `StudentEnrollmentService` | Creates/synchronizes normalized enrollments and legacy current-placement fields. |
| `AwsFaceRecognitionService` | Provider availability and captured-vs-stored image comparison. |
| `CompreFaceService` | Alternative/legacy enroll, recognize, and delete-subject client. |
| `OnlineClassAttendanceFinalizer` | Idempotently inserts missing Absent rows after class end. |
| `OnlineClassNotificationService` | Per-Student notification rows and email attempts on class changes. |
| `OnlineClassAuditLogger` | Before/after online-class action audit. |
| `MessengerEmailNotificationService` | Cache-throttled sender-to-recipient email notification. |
| `ExcuseLetterPdfService` | Generates approved letter PDF bytes with conditional Parent approval content. |
| `SemaphoreSmsService` | Formats/sends hotline emergency SMS and returns structured success/failure. |
| `LegacyAcademicFallbackMonitor` | Counts reads that use deprecated academic assignment data. |

## Support classes

| Class | Responsibility |
| --- | --- |
| `AuthenticatedSession` | Issues per-login UUID metadata, binds a server session to one authenticated user, and exposes identity checks used by login flows and middleware. |

## Models

| Domain | Models |
| --- | --- |
| Accounts/config/audit | `User`, `SystemSetting`, `ActivityLog` |
| Academic structure | `AcademicYear`, `Strand`, `Section`, `Subject`, `SubjectOffering`, `Schedule`, `Instructor`, `Students`, `StudentEnrollment` |
| Rollover | `AcademicYearRollover`, `AcademicYearRolloverItem` |
| Physical attendance/panel | `Attendance`, `AttendanceLog`, `RfidPanelSession`, `PanelDevice`, `Laboratory` |
| Online classes | `OnlineClass`, `OnlineClassAttachment`, `OnlineClassAttendance`, `OnlineClassNotification`, `OnlineClassAuditLog` |
| Inventory/borrowing | `Item`, `Inventory`, `Device` (legacy class mapped to item concepts), `Transaction`, `Borrowing`, `BorrowingItem` |
| Portal/messaging | `StudentExcuseLetter`, `ExcuseLetter` (legacy), `StudentPortalMessage`, `Message` (legacy), Parent links through `User::linkedStudents()`/`Students::parentUsers()` |
| Clinic/emergency | `EmergencyType`, `EmergencyAlert`, `EmergencyHotline`, `ClinicCase`, `PatientHistory` |
| Registrar | `RegistrarEnrollmentLog` |

Model responsibilities are deliberately small: fillable/casts/relationships and a few compatibility/encryption hooks. Complex workflows belong in controllers/services. `Message` and `StudentPortalMessage` transparently encrypt/decrypt subject/body with compatibility fallback values.

## Middleware

| Middleware | Responsibility |
| --- | --- |
| `CheckRole` | Normalizes role and enforces allowed-role route lists. |
| `EnsureAuthenticatedSessionIdentity` | Verifies each authenticated request against its session-bound user and invalidates only a mismatched browser session. |
| `EnsureInstructorVerified` | Redirects unverified Instructor sessions to verification. |
| `EnsureParentPortalEnabled` | Rejects Parent routes while the feature is off. |
| `EnsurePasswordIsChanged` | Forces temporary-password replacement except allow-listed auth routes. |
| `PreventConsolePasswordReset` | Returns neutral response without creating a Console email-reset workflow. |
| `RecordSystemActivity` | Best-effort automatic mutation/export/log-access audit. |
| `HandleInertiaRequests` | Shares user, feature settings, flash, title, and sidebar state. |
| `HandleAppearance` | Shares browser appearance preference. |

## Auth actions, responses, notifications, and providers

- Fortify actions: `CreateNewUser`, `ResetUserPassword`; shared `PasswordValidationRules` and `ProfileValidationRules` concerns.
- Custom Fortify responses: role-aware `LoginResponse`, `LogoutResponse`, `PasswordResetResponse`, and `EmailVerificationNotificationSentResponse`.
- Notifications: `MessengerMessageReceived` and `ClinicDispatchAssigned`.
- `FortifyServiceProvider` configures views, role-aware authentication, responses, and rate limits.
- `AppServiceProvider` uses immutable dates, production destructive-command protection, and stronger production password defaults.

## Commands and scheduled work

- `CheckAcademicYearIntegrity` implements `academic-years:check-integrity` and validates active-year, enrollment, attendance, online roster, and orphan invariants.
- Closure commands in `routes/console.php`: `online-classes:finalize-attendance`, `db:schema-notes`, and `system:features`.
- The online attendance command is scheduled every minute without overlapping.

## Frontend application structure

### Bootstrap and layouts

- `resources/js/app.js` boots Inertia/Vue and resolves page components.
- `resources/js/ssr.ts` is the SSR entry used only by the SSR build/runtime commands.
- `AuthLayout.vue` provides the authenticated shell, saved-profile processing, and Messenger polling.
- `AuthNavbar.vue` defines role- and feature-aware navigation.
- `Layout.vue` is the older public marketing shell.

### Shared components

| Group | Components and use |
| --- | --- |
| Authentication | `Login`, `Navlinks`, `PasswordField`, `RegistrationForm`, `RegistrationInput` provide reusable auth forms/navigation. |
| Buttons | `AddButton`, `Button`, `LoginButton`, `Menu` provide common controls. |
| Identity/input | `CameraCapture` handles browser camera capture; `SearchableSelect` provides large relationship search. |
| Student portal | `LinkedStudentSelector`, `StatCard`, `SuccessModal` provide linked-child context and feedback. |
| Admin cards | Barcode scanner, Dashboard cards/table, Inventory table, and Search bar support Admin UIs. |
| Landing | About/showcase/gallery/journal/call-to-action components build reusable marketing sections. |
| Icons | The `components/Icon` directory contains presentation-only SVG/Vue icons for navigation/cards/statuses. |
| Modal | `EditInventory` is a focused Inventory edit modal. |

### Composables

- `useAppearance` manages client theme choice.
- `useCurrentUrl` and `useInitials` provide display helpers.
- `useLogin` shares public login-modal state.
- `useSavedStudentParentProfiles` manages browser-local saved account profiles for both portal and staff login; it does not store passwords.
- `useTwoFactorAuth` wraps Fortify two-factor state/actions.

### Pages

All 66 Vue files, including active, child, wrapper, legacy, reusable, and unwired pages, are cataloged in [Pages and Features](../System%20Explanation/PAGES_AND_FEATURES.md) and [Page/Database Impact Map](PAGE_DATABASE_IMPACT_MAP.md).
