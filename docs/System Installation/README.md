# System Installation

This folder contains the complete setup, configuration, build, run, deployment, scheduler, storage, and troubleshooting guide.

## Supported stack

| Requirement | Project evidence | Recommendation |
| --- | --- | --- |
| PHP | Composer requires `^8.2`; CI tests 8.4 and 8.5; local audit passed route/migrations on 8.5.8. | Use PHP 8.4 or 8.5 with matching CLI and web-server versions. |
| Composer | Composer 2. | Current Composer 2 release. |
| Node.js | CI uses Node 22. | Node 22 LTS and npm. |
| Database | `.env.example` uses MySQL. | MySQL 8+ or a compatible modern MariaDB; use an empty database for installation. |
| Web server | Laravel 12 application. | Apache/Nginx pointing document root to `public/`; HTTPS in production. |
| Browser | Camera/audio/file features use modern browser APIs. | Current Chromium/Edge/Chrome; allow camera/audio when used. |

Required PHP extensions normally include: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, and `xml`. Enable `gd` or Imagick where image/PDF tooling requires it. Composer reports exact missing extensions.

## Clean local installation

From the repository root:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
npm install
```

Create an empty MySQL database and update `.env`, then run one of these paths:

```powershell
# Clean operational start: Root Admin plus baseline settings
php artisan migrate --force
php artisan db:seed --class=MinimalSeeder

# Demonstration dataset instead
php artisan migrate --force
php artisan db:seed
```

Create the public storage link and build assets:

```powershell
php artisan storage:link
npm run build
```

Do not run `migrate:fresh` against a database that contains data you need; it drops all tables.

## Environment configuration

Never commit the real `.env`. Generate a unique `APP_KEY`, use production-only secrets, and do not reuse demonstration credentials.

### Core application

| Variable | Purpose | Notes |
| --- | --- | --- |
| `APP_NAME` | Display name. | Used by Inertia/mail. |
| `APP_ENV` | `local`, `staging`, or `production`. | Production changes password strength and destructive-command protection. |
| `APP_KEY` | Encryption key. | Required; changing it makes encrypted data unreadable, including encrypted message values. |
| `APP_DEBUG` | Detailed errors. | `false` in production. |
| `APP_URL` | Canonical URL. | Use HTTPS production URL; affects storage URLs. |
| `APP_TIMEZONE` | Server-side application time. | Example uses `Asia/Manila`; schedules/attendance depend on this. |
| `SECURE_LOGIN_ROUTE` | Private staff-login path. | Do not publish the production value; omit leading/trailing slash for clarity. |

### Database, session, cache, and queue

| Variable | Purpose | Recommended production value |
| --- | --- | --- |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection. | Least-privilege application user; no root account. |
| `SESSION_DRIVER` | Signed-in session storage. | `database` after migrations, or a configured Redis store. |
| `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE`, `SESSION_DOMAIN` | Session duration/cookie scope. | Secure cookies on HTTPS; set domain only when required. |
| `CACHE_STORE` | Cache and Messenger cooldown storage. | `database` or Redis; atomic store preferred on multiple servers. |
| `QUEUE_CONNECTION` | Async queue backend. | Use `sync` unless queue tables/backend are installed. Current migrations do not include `jobs`, `job_batches`, or `failed_jobs`. |
| `LOG_CHANNEL`, `LOG_SERVER_CHANNELS`, `LOG_LEVEL`, `LOG_DAILY_DAYS` | Laravel application and server error logging. | Use `LOG_CHANNEL=server`, `LOG_SERVER_CHANNELS=daily,errorlog`, `LOG_LEVEL=error`, and an appropriate retention period such as 14 days. |

If database queues are required, generate and commit the appropriate Laravel queue migrations before setting `QUEUE_CONNECTION=database`, migrate them, and run supervised workers.

### Production error logging

The `server` log channel writes reportable Laravel errors to both rotating files under `storage/logs` and the PHP/web-server error log. Configure the production `.env` with:

```dotenv
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=server
LOG_SERVER_CHANNELS=daily,errorlog
LOG_LEVEL=error
LOG_DAILY_DAYS=14
```

After changing server environment values, run `php artisan config:clear` or rebuild the production configuration cache. Ensure the web-server account can write to `storage/logs` and `bootstrap/cache`. Laravel automatically reports unexpected exceptions; expected form validation is returned to the page as field errors and is intentionally not treated as a server failure. Log context includes the route, request method/path, and authenticated user ID, but excludes request bodies and passwords.

### Attendance panel and face services

| Variable | Purpose |
| --- | --- |
| `PANEL_PIN` | Configuration fallback; normal runtime global/device hashes are stored in System Settings/Panel Devices. |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` | AWS Rekognition credentials/region. Define each once; `.env.example` currently contains duplicate AWS placeholders and should be cleaned when next edited. |
| `AWS_REKOGNITION_SIMILARITY_THRESHOLD` | Match threshold; example is 90. |
| `COMPREFACE_URL`, `COMPREFACE_API_KEY` | Alternative/legacy CompreFace service configuration. `COMPREFACE_URL` has a code default but is not shown in the example file. |

Use an IAM principal restricted to the required Rekognition actions. Do not expose cloud keys to frontend code.

### SMS and mail

| Variable | Purpose |
| --- | --- |
| `SEMAPHORE_ENABLED` | Enables/disables live SMS attempt. |
| `SEMAPHORE_API_KEY`, `SEMAPHORE_SENDER_NAME`, `SEMAPHORE_ENDPOINT` | Semaphore SMS configuration. |
| `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Laravel mail transport for reset, OTP, messages, class notices, dispatch, and letters. |
| `MESSENGER_EMAIL_NOTIFICATION_COOLDOWN_MINUTES` | Minimum interval for repeated sender-to-recipient Messenger email alerts; default 5. |

Use `MAIL_MAILER=log` during local development if no SMTP server is available. Live OTP/password-reset workflows require real mail delivery.

## Run locally

### Three-terminal method

```powershell
# Terminal 1
php artisan serve

# Terminal 2
npm run dev

# Terminal 3 - required for every-minute automatic online absence finalization
php artisan schedule:work
```

If the project adds real queued jobs and an installed queue backend, start another terminal:

```powershell
php artisan queue:work --tries=3
```

The Composer shortcut `composer dev` starts the Laravel server, a queue listener, and Vite, but it does **not** start `schedule:work`. With the repository's default `QUEUE_CONNECTION=database` and no queue tables, the queue listener may fail; configure `sync` or add queue tables first.

## Build and verify

```powershell
npm run build
composer test
```

Useful focused commands:

```powershell
composer test:requested-features
php artisan test --compact tests/Feature/AcademicYearManagementTest.php
php artisan route:list
php artisan schedule:list
php artisan academic-years:check-integrity
```

Frontend formatting/linting commands can modify files:

```powershell
npm run format:check
npm run lint
composer test:lint
```

Use the same PHP executable for Composer, Artisan, Wayfinder/Vite, and the web server. On this Windows checkout, `C:\xampp\php8.5.8\php.exe` is the known compatible executable.

## Production deployment

1. Back up the database and `storage/app` files.
2. Deploy the reviewed commit with the web-server document root set to `public/`.
3. Install optimized dependencies: `composer install --no-dev --classmap-authoritative` and `npm ci`.
4. Configure production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URL, unique key, database, mail, optional SMS/Rekognition, secure sessions, cache, and queue choice.
5. Build frontend: `npm run build`.
6. Put the app in maintenance mode when the migration risk requires it: `php artisan down`.
7. Run `php artisan migrate --force` and the deliberate production seeder only when required. Do not run demo seeders.
8. Run `php artisan storage:link` and ensure web/PHP user can write `storage/` and `bootstrap/cache/`.
9. Cache configuration/routes/views: `php artisan optimize` (after validating environment values).
10. Restart PHP-FPM/Apache and supervised workers, then `php artisan up`.
11. Run smoke tests for role login, storage files, attendance panel, scheduler, mail, SMS (if enabled), face provider (if enabled), and exports.

### Scheduler

On Linux, run Laravel's scheduler every minute as the application user:

```cron
* * * * * cd /path/to/capstone-rfid && php artisan schedule:run >> /dev/null 2>&1
```

On Windows Server, create a Task Scheduler job that runs the project PHP executable with `artisan schedule:run` every minute from the repository directory. Only one effective scheduler should run per deployment; the scheduled command already uses overlap protection.

### Queue worker

No current service implements `ShouldQueue`, so most mail/notifications execute during the request. If queued jobs are introduced, install a supported backend/tables and use Supervisor/systemd/Windows service management for `php artisan queue:work`; do not depend on an interactive terminal.

### Permissions and files

- PHP/web-server identity needs read access to application code and write access to `storage/` and `bootstrap/cache/`.
- Back up private/public stored files with the database.
- Restrict `.env`, logs, private storage, Composer files, and source directories from direct web access by serving only `public/`.
- Uploaded file endpoints still perform authorization, but public-disk URLs may be directly reachable when generated with `Storage::url`; assess privacy requirements for biometric/class/message files before production.

## Seeders

| Seeder | Use |
| --- | --- |
| `MinimalSeeder` | Root Admin plus baseline system settings for a clean installation. |
| `DatabaseSeeder` (`php artisan db:seed`) | Full development/demonstration dataset. |
| `SystemSeeder` | Reference and demo operational data. |
| `DataAccountSeeder` | Development account set and portal links. |
| `AcademicYearSeeder`, `EmergencySeeder`, `SeniorHighAcademicSeeder`, others | Focused development/test fixtures. |

Seeded credentials documented in [Default Account Passwords](../System%20Explanation/DEFAULT_ACCOUNT_PASSWORDS.md) are non-production fixtures. Replace/remove them before deployment.

## Troubleshooting

| Problem | Checks and solution |
| --- | --- |
| `php` is old or not found | Run `php -v` and `where.exe php`; invoke the compatible absolute PHP path or fix `PATH`. Vite Wayfinder also executes PHP. |
| Database connection refused | Start MySQL, confirm host/port/database/user/password, then run `php artisan migrate:status`. During this review MySQL at `127.0.0.1:3306` was not running, so live-data verification was unavailable. |
| `jobs` table missing | Set `QUEUE_CONNECTION=sync` or add Laravel queue migrations and migrate before running a database worker. |
| Assets/Wayfinder fail | Run `npm install`, ensure compatible PHP, clear stale caches with `php artisan optimize:clear`, then rebuild. |
| Uploaded images/files return 404 | Run `php artisan storage:link`, verify file exists and permissions/`APP_URL`, and confirm the requesting account owns/is allowed to access it. |
| Scheduler does not create online absences | Run `php artisan schedule:list`; start `schedule:work` locally or cron/Task Scheduler in production; inspect logs. Also verify enrollment status compatibility (`active` versus `enrolled`). |
| Face recognition unavailable | Verify feature flag, AWS credentials/region/network/IAM and stored image; System Settings reports provider availability and keeps invalid combinations off. |
| OTP/reset/message mail absent | Check mail transport, queue choice, logs, recipient email, and cooldown. With `MAIL_MAILER=log`, inspect Laravel logs rather than inbox. |
| Emergency SMS absent | Check switch, active hotline with SMS enabled, number format, API key/sender/endpoint, network, and alert metadata/result. Alert storage does not prove SMS delivery. |
| Parent cannot sign in | Confirm Parent Portal is on, account role is Parent, and the Parent is linked to a Student. Parent Excuse Letters is a separate switch. |
| Page hidden but URL works | Some switches are menu-visibility controls only (notably Online Classes). Use documented middleware/controller behavior and fix route enforcement if a hard shutdown is required. |

For a shorter Windows-first walkthrough, see [Running the System](RUNNING_THE_SYSTEM.md). Download sources are listed in [Installation Links](INSTALLATION_LINKS.md).
