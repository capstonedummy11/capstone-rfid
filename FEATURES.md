# Feature Index

## Table of Contents

- [How to find a feature](#how-to-find-a-feature)
- [Admin](#admin)
- [Root Admin](#root-admin)
- [Registrar](#registrar)
- [Clinic](#clinic)
- [Instructor](#instructor)
- [Student](#student)
- [Parent](#parent)
- [Shared / Core](#shared--core)
- [Flow Diagrams](#flow-diagrams)

## How to find a feature

Piliin ang actor at feature sa ibaba, buksan ang Main files, o gamitin ang `rg -n "FEATURE:<slug>" app routes resources` para makita ang connected code. Ang `@feature` block sa main entry point ang may flow, dependencies, at disable steps. Sundin ang current routes at code kung may lumang demo notes na iba ang sinasabi.

Sa bawat named function, ang `@function` ang maikling purpose at ang `@useIn` ang route, template event, caller, o framework lifecycle na gumagamit nito. Ang `TODO(verify)` ay nangangahulugang walang direktang caller na napatunayan sa static search; huwag itong ituring na siguradong unused nang walang runtime check.

## Admin

| Feature | Slug (grep tag) | What it does | Main files | How to disable |
| --- | --- | --- | --- | --- |
| User and Role Management | `FEATURE:user-management` | Dito minamanage ang staff accounts, roles, at password resets. | `app/Http/Controllers/AdminUserController.php`, `resources/js/pages/Auth/Admin/UserManagement.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/AdminUserController.php`. |
| Student and Parent Management | `FEATURE:student-management` | Dito ginagawa ang student records, enrollment, at linked Parent accounts. | `app/Http/Controllers/StudentsController.php`, `resources/js/pages/Auth/Admin/Students.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/StudentsController.php`. |
| Instructor Management | `FEATURE:instructor-management` | Dito minamanage ang Instructor records at account recovery. | `app/Http/Controllers/InstructorsController.php`, `resources/js/pages/Auth/Admin/Instructors.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/InstructorsController.php`. |
| Academic Year Lifecycle and Rollover | `FEATURE:academic-year-rollover` | Dito ina-activate, kino-close, at niro-rollover ang academic year. | `app/Http/Controllers/AcademicYearController.php`, `resources/js/pages/Auth/Admin/AcademicYears.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/AcademicYearController.php`. |
| Academic Structure and Scheduling | `FEATURE:academic-scheduling` | Dito binubuo ang strands, sections, subjects, offerings, at class schedules. | `app/Http/Controllers/ScheduleController.php`, `app/Http/Controllers/SectionController.php`, `app/Http/Controllers/SubjectController.php`, `app/Http/Controllers/StrandController.php`, `resources/js/pages/Auth/Admin/Schedules.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/ScheduleController.php`. |
| Laboratories and Devices | `FEATURE:device-management` | Dito kino-configure ang rooms, panel devices, PIN, at remote logout. | `app/Http/Controllers/ActiveDeviceController.php`, `app/Http/Controllers/LaboratoryController.php`, `resources/js/pages/Auth/Admin/ActiveDevices.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/ActiveDeviceController.php`. |
| System Settings | `FEATURE:system-settings` | Dito sine-set ang feature switches, attendance rules, SMS, at emergency sounds. | `app/Http/Controllers/SystemSettingsController.php`, `resources/js/pages/Auth/Admin/SystemSettings.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/SystemSettingsController.php`. |
| Inventory Management | `FEATURE:inventory-management` | Dito nililista at ina-update ang inventory records at item status. | `app/Http/Controllers/InventoryController.php`, `resources/js/pages/Auth/Admin/Inventory.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/InventoryController.php`. |
| Borrowing Oversight and Returns | `FEATURE:borrowing-management` | Dito tinitingnan ang borrowing history at pinoproseso ang returns. | `app/Http/Controllers/BorrowController.php`, `resources/js/pages/Borrow.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/BorrowController.php`. |
| Activity and Online Class Logs | `FEATURE:admin-logs` | Dito nire-review at ine-export ang system activity; may hiwalay ding online-class logs. | `app/Http/Controllers/ActivityLogController.php`, `app/Http/Controllers/OnlineClassController.php`, `resources/js/pages/Auth/Admin/ActivityLogs.vue`, `resources/js/pages/Auth/Admin/OnlineClassLogs.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/ActivityLogController.php`. |

## Root Admin

| Feature | Slug (grep tag) | What it does | Main files | How to disable |
| --- | --- | --- | --- | --- |
| Ownership Transfer and Override | `FEATURE:root-ownership` | Dito sinisimulan o kina-cancel ang Root ownership transfer at emergency override. | `app/Http/Controllers/RootOwnershipController.php`, `resources/js/components/Admin/RootOwnershipPanel.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/RootOwnershipController.php`. |

## Registrar

| Feature | Slug (grep tag) | What it does | Main files | How to disable |
| --- | --- | --- | --- | --- |
| Student RFID Enrollment | `FEATURE:student-rfid-enrollment` | Dito nililink ang scanned RFID tag sa student record. | `app/Http/Controllers/RegistrarController.php`, `resources/js/pages/Registrar/BiometricEnrollment.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/RegistrarController.php`. |
| Student Face Enrollment | `FEATURE:student-face-enrollment` | Dito sine-save at tinatanggal ang enrolled student face images. | `app/Http/Controllers/RegistrarController.php`, `resources/js/pages/Registrar/BiometricEnrollment.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/RegistrarController.php`. |
| Instructor RFID and Face Enrollment | `FEATURE:instructor-biometric-enrollment` | Dito nililink ang Instructor RFID at face images sa account. | `app/Http/Controllers/RegistrarController.php`, `resources/js/pages/Registrar/InstructorFaceEnrollment.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/RegistrarController.php`. |

## Clinic

| Feature | Slug (grep tag) | What it does | Main files | How to disable |
| --- | --- | --- | --- | --- |
| Emergency Alert Response and Dispatch | `FEATURE:clinic-dispatch` | Dito ina-assign ang Clinic responder at gumagawa ng linked case. | `app/Http/Controllers/EmergencyController.php`, `resources/js/pages/Clinic/Dashboard.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/EmergencyController.php`. |
| Emergency Types and Hotlines | `FEATURE:emergency-configuration` | Dito sine-set ang emergency types at matching hotlines. | `app/Http/Controllers/EmergencyController.php`, `resources/js/pages/Clinic/EmergencyHotlines.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/EmergencyController.php`. |
| Case Logs and Patient History | `FEATURE:clinic-records` | Dito ini-record ang clinic cases at patient history. | `app/Http/Controllers/ClinicController.php`, `resources/js/pages/Clinic/CaseLogs.vue`, `resources/js/pages/Clinic/PatientHistory.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/ClinicController.php`. |
| Clinic Reports | `FEATURE:clinic-reports` | Dito fina-filter at ine-export ang clinic activity. | `app/Http/Controllers/ClinicController.php`, `resources/js/pages/Clinic/Reports.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/ClinicController.php`. |

## Instructor

| Feature | Slug (grep tag) | What it does | Main files | How to disable |
| --- | --- | --- | --- | --- |
| Login Verification | `FEATURE:instructor-verification` | Pagkatapos ng login, dito kinukumpleto ang face, email OTP, o security-question check. | `app/Http/Controllers/InstructorVerificationController.php`, `resources/js/pages/Auth/InstructorVerify.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/InstructorVerificationController.php`. |
| Assigned Attendance and Corrections | `FEATURE:attendance-review` | Dito nire-review ang assigned attendance at nilolog ang allowed manual corrections. | `app/Http/Controllers/AttendanceManagementController.php`, `resources/js/pages/Attendance/SessionDetails.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/AttendanceManagementController.php`. |
| Online Class Management | `FEATURE:online-class-management` | Dito ginagawa, ina-update, at kina-cancel ang assigned online classes. | `app/Http/Controllers/OnlineClassController.php`, `resources/js/pages/Auth/Admin/OnlineClasses.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/OnlineClassController.php`. |
| Excuse Letter Review | `FEATURE:excuse-letter-review` | Dito nagde-decide ang recipient Instructor sa delivered letter at nagpapadala ng email. | `app/Http/Controllers/MessageController.php`, `resources/js/pages/Messages/Index.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/MessageController.php`. |

## Student

| Feature | Slug (grep tag) | What it does | Main files | How to disable |
| --- | --- | --- | --- | --- |
| Attendance History | `FEATURE:student-attendance-history` | Dito nakikita ng student ang sariling physical at online attendance. | `app/Http/Controllers/StudentsController.php`, `resources/js/pages/StudentParent/Attendance.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/StudentsController.php`. |
| Online Class Viewing and Joining | `FEATURE:online-class-join` | Dito sumasali ang eligible student sa class at nalolog ang attendance. | `app/Http/Controllers/OnlineClassController.php`, `resources/js/pages/StudentParent/OnlineClasses.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/OnlineClassController.php`. |
| Excuse Letter Submission | `FEATURE:excuse-letter-submission` | Dito nagsusubmit ng letter ang student; Parent approval muna kung enabled. | `app/Http/Controllers/StudentsController.php`, `resources/js/pages/StudentParent/ExcuseLetters.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/StudentsController.php`. |

## Parent

| Feature | Slug (grep tag) | What it does | Main files | How to disable |
| --- | --- | --- | --- | --- |
| Linked Student Dashboard and Attendance | `FEATURE:parent-student-view` | Dito nakikita ng Parent ang dashboard ng linked student. | `app/Http/Controllers/StudentsController.php`, `resources/js/pages/StudentParent/Dashboard.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/StudentsController.php`. |
| Excuse Letter Approval | `FEATURE:excuse-letter-approval` | Dito pinipirmahan at ina-approve ng Parent ang pending letter. | `app/Http/Controllers/StudentsController.php`, `resources/js/pages/StudentParent/ExcuseLetters.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/StudentsController.php`. |
| Online Class Notifications | `FEATURE:online-class-notifications` | Dito nakikita at minamark read ang class-change notices ng linked student. | `app/Http/Controllers/StudentsController.php`, `resources/js/pages/StudentParent/Notifications.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/StudentsController.php`. |

## Shared / Core

| Feature | Slug (grep tag) | What it does | Main files | How to disable |
| --- | --- | --- | --- | --- |
| Role-Based Login and Session Protection | `FEATURE:authentication` | Dito nilolog in ang staff at binabantayan ang role at browser session. | `app/Http/Controllers/StaffLoginController.php`, `resources/js/pages/Auth/StaffLogin.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/StaffLoginController.php`. |
| Admin Login Email OTP | `FEATURE:admin-login-otp` | Bawat tunay na Admin password login ay may bagong email OTP challenge. | `app/Http/Controllers/AdminLoginVerificationController.php`, `resources/js/pages/Auth/AdminLoginVerification.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/AdminLoginVerificationController.php`. |
| First-Login Password Setup | `FEATURE:first-login-password` | Dito pinapalitan ang temporary password bago buksan ang ibang page. | `app/Http/Controllers/FirstLoginPasswordController.php`, `resources/js/pages/Auth/FirstLoginPassword.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/FirstLoginPasswordController.php`. |
| Messenger and Attachments | `FEATURE:messenger` | Dito nagpapalitan ng private messages at authorized attachments ang users. | `app/Http/Controllers/MessageController.php`, `resources/js/pages/Messages/Index.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/MessageController.php`. |
| Reports and Exports | `FEATURE:reports` | Dito fina-filter at ine-export ang role-scoped reports. | `app/Http/Controllers/ReportController.php`, `resources/js/pages/Reports/Index.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/ReportController.php`. |
| RFID Attendance Console | `FEATURE:rfid-attendance` | Dito kino-convert ang verified RFID tap sa attendance event at official row. | `app/Http/Controllers/AttendanceController.php`, `resources/js/pages/AttendanceControlPanel.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/AttendanceController.php`. |
| Face Recognition | `FEATURE:face-recognition` | Dito kino-compare ang captured face at stored face gamit ang AWS similarity threshold. | `app/Services/AwsFaceRecognitionService.php`, `resources/js/components/CameraCapture.vue` | Tingnan ang `@disable` sa `app/Services/AwsFaceRecognitionService.php`. |
| Face Liveness | `FEATURE:face-liveness` | Dito chine-check ang AWS liveness confidence bago gamitin ang reference image. | `app/Services/AwsFaceLivenessService.php`, `resources/js/lib/faceLiveness.tsx` | Tingnan ang `@disable` sa `app/Services/AwsFaceLivenessService.php`. |
| Console Borrowing | `FEATURE:console-borrowing` | Dito nililink ang borrower RFID at scanned item sa borrowing records. | `app/Http/Controllers/BorrowController.php`, `resources/js/pages/AttendanceControlPanel.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/BorrowController.php`. |
| Emergency Alerts and Delivery | `FEATURE:emergency-alerts` | Dito sine-save ang emergency alert at ina-attempt ang hotline at Parent notifications. | `app/Http/Controllers/EmergencyController.php`, `resources/js/pages/AttendanceControlPanel.vue` | Tingnan ang `@disable` sa `app/Http/Controllers/EmergencyController.php`. |
| Audit Logging | `FEATURE:audit-logging` | Dito nilolog ang mutating requests at selected exports kahit may audit storage error. | `app/Http/Middleware/RecordSystemActivity.php`, `resources/js/pages/Auth/Admin/ActivityLogs.vue` | Tingnan ang `@disable` sa `app/Http/Middleware/RecordSystemActivity.php`. |

## Flow Diagrams

### Physical attendance

```mermaid
flowchart LR
  RFID[Student RFID tap] --> Lookup[Student and roster lookup]
  Lookup --> Verify{Verification required?}
  Verify -->|Yes| Live[Optional face liveness]
  Live --> Match[Face recognition against enrolled image]
  Verify -->|Instructor fallback| Grant[Short-lived verification grant]
  Match --> Grant
  Grant --> Tap[AttendanceController recordStudentTap]
  Tap --> Rows[Attendance and attendance_logs]
```

### Borrowing

```mermaid
flowchart LR
  Card[Borrower RFID] --> Lookup[Student or Instructor lookup]
  Lookup --> Items[Scan available item barcodes]
  Items --> Borrow[BorrowController borrowItemsOnly]
  Borrow --> Records[Borrowing and borrowing_items]
  Records --> Status[Update item status]
```

### Return

```mermaid
flowchart LR
  Card[Borrower RFID] --> Active[Find active borrowing]
  Active --> Items[Scan borrowed item barcodes]
  Items --> Return[BorrowController returnItems]
  Return --> Records[Update borrowing_items and item status]
```

### Excuse letter

```mermaid
flowchart LR
  Student[Student submits] --> Parent{Parent Portal enabled?}
  Parent -->|Yes| Approval[Linked Parent approves]
  Parent -->|No| Delivery[Generate PDF and deliver to Instructors]
  Approval --> Delivery
  Delivery --> Review[Each recipient Instructor reviews in Messenger]
  Review --> Email[Email selected Student or Parent recipients]
```

Excuse letter approval at Instructor review ay hindi awtomatikong nagpapalit ng official attendance status.

## Current implementation notes

- Ang Student at Parent portal ay walang sariling borrowing page o face enrollment action. Sa Console ginagawa ang borrowing; Registrar ang nag-e-enroll ng face.
- Ang `/admin/inventory` ay may duplicate GET declarations sa `routes/web.php`; gamitin ang runtime route list para malaman ang aktibong handler.
- May online-class enrollment queries na naghahanap ng `active` habang karaniwang `enrolled` ang normalized status. I-verify ang deployed data bago umasa sa automatic online attendance.
- Ang Online Classes switch ay nagtatago ng menu links pero hindi lahat ng direct routes. Suriin ang server routes bago ituring itong full disable control.
