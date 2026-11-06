PhilSMS is available alongside Semaphore and iProgSMS in Admin Settings. It uses the dashboard endpoint supplied for this account: `https://dashboard.philsms.com/api/v3/sms/send`, with a Bearer API token and a JSON payload. Philippine mobile numbers in local, international, and `+63` formats are normalized to `639XXXXXXXXX`. The service also supports comma-separated recipients.

Each provider has a password input for credentials and a Send Test SMS form. Tests use that provider's entered credentials, or saved credentials when the token field is blank, without changing the active provider. Tests do not save credentials. Saving settings encrypts tokens in the existing `system_settings` table using Laravel's application key; existing environment credentials remain the fallback. The server returns only a configured flag for saved tokens. Blank token fields preserve existing credentials. No migration or seed is needed.

Emergency recipients and message templates remain unchanged: the selected SMS-enabled hotline and linked parents for specifically selected students. The selected primary provider is tried first, followed by the other available providers in registry order. Semaphore and iProgSMS retain their existing API payloads and response interpretation. Emergency feedback shows success/failure counts; test feedback shows a short success/failure message. Provider names are displayed in configuration labels and recorded in audit details, but are not included in SMS result messages.

The existing `ActivityLog` model and `activity_logs` table are reused. SMS details are JSON in the existing description field. The existing action filter derives its choices from stored records, so no filter UI changes were needed. The SMS logger records the acting user, IP, route, outcome, and timestamp. Test recipients are masked; emergency logs contain counts, provider attempts, fallback status, schedule/section IDs, scope, and message length. Tokens, raw API responses, full phone numbers, and emergency message bodies are excluded from these SMS audit records. Provider errors are converted to short reasons before logging or displaying them. Audit storage failures are reported server-side and do not stop SMS delivery.

Audit actions: `sms_test_sent`, `sms_test_failed`, `emergency_text_sent`, `emergency_text_failed`, `sms_config_created`, `sms_config_updated`, and `sms_provider_changed`. A partially successful emergency send uses `emergency_text_failed` with both succeeded and failed counts. There is one SMS summary per emergency send; existing general request/alert activity records remain. A duplicate-suppressed emergency does not send again.

Changed files and purposes:

| File | Purpose |
| --- | --- |
| [.env.example](../.env.example) | Document PhilSMS token, sender, enabled flag, and endpoint environment keys. |
| [config/services.php](../config/services.php) | Supply PhilSMS configuration and the dashboard endpoint default. |
| [app/Models/SystemSetting.php](../app/Models/SystemSetting.php) | Add PhilSMS availability/selection, encrypted saved credentials, and safe settings props. |
| [app/Services/PhilSmsService.php](../app/Services/PhilSmsService.php) | Implement the existing provider interface, normalization, Bearer JSON sending, response status checks, and safe failures. |
| [app/Services/SemaphoreSmsService.php](../app/Services/SemaphoreSmsService.php) | Support saved/entered credentials while preserving the existing API behavior; avoid logging exception messages containing credentials. |
| [app/Services/IprogSmsService.php](../app/Services/IprogSmsService.php) | Support saved/entered credentials while preserving API behavior; restrict provider error logs to safe status values and exception types. |
| [app/Services/SmsProviderRegistry.php](../app/Services/SmsProviderRegistry.php) | Register PhilSMS and construct provider instances with temporary test credentials. |
| [app/Services/SmsService.php](../app/Services/SmsService.php) | Keep the existing fallback flow and aggregate emergency delivery counts, providers, and reasons. |
| [app/Services/SmsActivityLogger.php](../app/Services/SmsActivityLogger.php) | Write SMS events through the existing ActivityLog model with best-effort error handling. |
| [app/Http/Controllers/Shared/SystemSettings/SystemSettingsController.php](../app/Http/Controllers/Shared/SystemSettings/SystemSettingsController.php) | Validate/save credentials, test a specific provider, and audit tests/configuration/provider changes. |
| [app/Http/Controllers/Shared/Emergency/EmergencyController.php](../app/Http/Controllers/Shared/Emergency/EmergencyController.php) | Add one delivery summary audit entry and safe UI counts; tolerate audit outages before sending. |
| [routes/admin.php](../routes/admin.php) | Allow PhilSMS in existing provider routes and add an admin-only, rate-limited test route. |
| [resources/js/pages/Admin/SystemSettings/SystemSettingsPage.vue](../resources/js/pages/Admin/SystemSettings/SystemSettingsPage.vue) | Add PhilSMS settings and consistent credential/test forms, loading states, validation feedback, and token clearing after saving. |
| [resources/js/pages/AttendanceConsole/AttendanceControlPanel/AttendanceControlPanelPage.vue](../resources/js/pages/AttendanceConsole/AttendanceControlPanel/AttendanceControlPanelPage.vue) | Prevent concurrent emergency submissions, display sending progress, and show SMS counts without provider names. |
| [tests/Feature/SmsProvidersTest.php](../tests/Feature/SmsProvidersTest.php) | Verify providers, credentials, response failures, validation, encryption, audit privacy/filtering, fallback, partial delivery, and audit outages. |
| [resources/js/pages/Admin/__tests__/sms-settings.test.ts](../resources/js/pages/Admin/__tests__/sms-settings.test.ts) | Verify each provider's entered test credentials, button disabling, duplicate-click prevention, and result feedback. |
| [docs/sms-providers.md](sms-providers.md) | Record implementation choices, changed files, validation results, and manual test steps. |

Verification results:

- **60 backend tests passed, 470 assertions:** the new SMS tests plus all existing SystemSettingsTest and ClinicFlowTest cases. Existing Semaphore and iProgSMS request, account-check, primary-selection, and fallback tests passed.
- **4 Vue component tests passed:** independent test sends for all three providers and loading/failure feedback.
- The production frontend build passed. Both final edited Vue components also passed direct script/template compilation. PHP syntax and Git whitespace checks passed. Generated production assets were restored after verification to keep the change focused on source files; rebuild assets when deploying.
- A broader run including RequestedFeatureUiWiringTest had 79 passing tests and two failures in unchanged code: instructor liveness diagnostics and the first-login page path expectation. Those checks do not involve SMS and were left unchanged.
- The installed PHP configuration has SQLite disabled. Tests were run with the installed SQLite extensions enabled for that command only; no system PHP configuration was modified. Missing locked Composer development packages were installed to run the tests, without changing dependency versions.
- HTTP calls in tests were faked. No live SMS was sent, and handset delivery has not yet been verified.

To repeat the focused checks on this Windows PHP installation:

```powershell
php -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor/bin/pest --compact tests/Feature/SmsProvidersTest.php tests/Feature/SystemSettingsTest.php tests/Feature/ClinicFlowTest.php
npm.cmd test -- resources/js/pages/Admin/__tests__/sms-settings.test.ts
```

Manual PhilSMS test:

1. Open Admin Settings using the development frontend (`npm.cmd run dev`), or rebuild production assets with `npm.cmd run build`.
2. Enter your PhilSMS API token and the active sender ID `PhilSMS`. Keep the existing primary provider selected while testing.
3. Enter your own mobile number in the PhilSMS Send Test SMS form. Leave the message blank to use the default test message, or enter a short test message. Click once and wait for the result. This test sends a real SMS using your account credits.
4. Confirm receipt on your phone. Open System Activity Logs and filter by `sms_test_sent` or `sms_test_failed`. Confirm the provider and masked number; no token should appear.
5. After the test works, mark PhilSMS available, select it as primary if multiple providers are available, and save settings. Check `sms_config_created`/`sms_config_updated` and `sms_provider_changed` entries.
6. Verify emergency sending only during an agreed test with the intended hotline/parent recipients. It preserves the existing recipient selection and can send real notifications to those people. Check the summary counts and the corresponding emergency audit entry.

PhilSMS's balance/account-check endpoint was not verified, so no balance endpoint was guessed. Its settings card uses Send Test SMS to verify sending. A successful API result means the provider accepted the request; confirm actual delivery on the receiving phone. The existing synchronous emergency/fallback flow is retained, including its potential delay when several providers time out.
