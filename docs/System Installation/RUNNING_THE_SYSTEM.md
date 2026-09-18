# Set Up and Run the RFID System Locally from GitHub

Documentation home: [Documentation Index and Source-of-Truth Map](../DOCUMENTATION_INDEX.md).

This guide explains how to download the project from GitHub and run it on a Windows computer. It is written for someone who is not a developer. Follow the steps in order and do not skip a verification command.

## What You Are Setting Up

This project has four main parts:

- **Laravel and PHP** run the application and its business rules.
- **MySQL** stores users, academic records, attendance, and other system data.
- **Vue and Vite** build and display the pages in the browser.
- **Git and GitHub** download the project and make future updates easier.

You will use PowerShell or Windows Terminal to enter commands. A command is simply an instruction typed into a terminal and followed by Enter.

## Before You Begin

You need:

- A Windows computer with administrator access for installing software.
- A stable internet connection.
- At least several gigabytes of free disk space.
- Access to the project repository on GitHub if it is private.

Do not place the project inside OneDrive or another automatically synchronized folder. Syncing can lock files while Composer, npm, or Vite is using them.

## Software to Install

Install these tools before downloading the project.

| Software                                             | Why it is needed                                        | Recommended version                                                               |
| ---------------------------------------------------- | ------------------------------------------------------- | --------------------------------------------------------------------------------- |
| [Git for Windows](https://git-scm.com/downloads/win) | Downloads and updates the GitHub repository.            | Current stable release                                                            |
| [XAMPP](https://www.apachefriends.org/)              | Provides MySQL and a convenient Windows control panel.  | A release with compatible PHP, or use the separate compatible PHP described below |
| [PHP](https://windows.php.net/download/)             | Runs Laravel and Artisan commands.                      | PHP 8.4 or 8.5; this checkout is known to work with `C:\xampp\php8.5.8\php.exe`   |
| [Composer](https://getcomposer.org/download/)        | Installs the Laravel/PHP packages.                      | Composer 2                                                                        |
| [Node.js](https://nodejs.org/)                       | Includes npm, which installs and builds the frontend.   | Node.js 22 LTS                                                                    |
| [Visual Studio Code](https://code.visualstudio.com/) | Makes it easier to edit `.env` and inspect the project. | Optional, but recommended                                                         |

Laravel accepts PHP 8.2 or newer, but PHP 8.4 or 8.5 is recommended for this project. The PHP command used by Composer, Artisan, Vite/Wayfinder, and the web server must point to the same compatible PHP installation.

## Step 1: Install Git

1. Download **Git for Windows**.
2. Run the installer.
3. Keep the default options unless your school or organization requires different settings.
4. Close and reopen PowerShell after installation.
5. Verify Git:

```powershell
git --version
```

You should see a Git version number. If Windows says the command is not recognized, restart the computer and try again.

## Step 2: Install and Start MySQL

1. Download and install XAMPP.
2. Open **XAMPP Control Panel**.
3. Select **Start** beside MySQL.
4. Confirm that MySQL stays highlighted as running.
5. Select **Admin** beside MySQL to open phpMyAdmin.

Apache is optional for local development because Laravel will provide its own local web server.

## Step 3: Verify PHP

Open a new PowerShell window and run:

```powershell
php -v
where.exe php
```

The first command must show PHP 8.2 or newer. The second command shows which PHP executable Windows is using.

If `php` is missing or points to an older installation, use the compatible executable directly. For this checkout, the known compatible executable is:

```powershell
C:\xampp\php8.5.8\php.exe -v
```

You may add its folder to the Windows `Path`, or replace `php` in the commands below with the full executable path.

## Step 4: Install Composer

1. Download and run **Composer-Setup.exe**.
2. When the installer asks for PHP, select the same compatible `php.exe` verified in Step 3.
3. Complete the installation.
4. Close and reopen PowerShell.
5. Verify Composer:

```powershell
composer --version
```

## Step 5: Install Node.js and npm

1. Download the Node.js 22 LTS Windows installer.
2. Install it with the default options.
3. Close and reopen PowerShell.
4. Verify both tools:

```powershell
node --version
npm --version
```

Both commands should display version numbers.

## Step 6: Download the Project from GitHub

### Recommended method: clone with Git

1. Open the project's GitHub page.
2. Select the green **Code** button.
3. Select **HTTPS** and copy the repository address.
4. Open PowerShell in the folder where you want to keep the project.
5. Run:

```powershell
git clone https://github.com/capstonedummy11/capstone-rfid.git
cd capstone-rfid
```

If GitHub asks you to sign in, complete the browser sign-in. GitHub no longer accepts an account password directly for Git operations; use browser authentication, Git Credential Manager, or an approved personal access token.

This guide describes the current `development` branch. Switch to it and download its latest commits:

```powershell
git switch development
git pull
```

Verify the branch:

```powershell
git branch --show-current
```

The result should be `development`.

### Alternative method: download a ZIP

If Git cannot be used:

1. Open the project on GitHub.
2. Choose the correct branch from the branch selector.
3. Select **Code**, then **Download ZIP**.
4. Extract the ZIP to a normal local folder.
5. Open PowerShell inside the extracted `capstone-rfid` folder.

The ZIP method works, but it does not provide the normal `git pull` update workflow.

## Step 7: Confirm You Are in the Project Folder

Run:

```powershell
Get-ChildItem
```

The list should include `artisan`, `composer.json`, `package.json`, `app`, `database`, and `resources`. If those items are missing, use `cd` to enter the correct folder before continuing.

## Step 8: Install Project Dependencies

Install the PHP packages:

```powershell
composer install
```

Then install the frontend packages:

```powershell
npm install
```

These commands can take several minutes. Warnings are not always failures. Stop and troubleshoot if a command ends with an error or a nonzero exit code.

## Step 9: Create the Local Environment File

The `.env` file contains settings for this computer. It must not be uploaded to GitHub.

Create it from the safe example:

```powershell
Copy-Item .env.example .env
```

Generate the application's encryption key:

```powershell
php artisan key:generate
```

Open `.env` in Visual Studio Code:

```powershell
code .env
```

If the `code` command is unavailable, open Visual Studio Code normally and use **File > Open File**.

For a basic local installation, confirm or change these values:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=capstone_rfid_db
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync

MAIL_MAILER=log
SEMAPHORE_ENABLED=false
```

Important notes:

- Leave `APP_KEY` as the generated value. Do not copy another installation's key.
- `QUEUE_CONNECTION=sync` is the safe local value because this repository does not currently include database queue tables.
- `MAIL_MAILER=log` writes local email attempts to the Laravel log instead of sending real email.
- Keep Semaphore SMS and AWS Rekognition disabled or unconfigured until valid credentials and provider access are available.
- Never share or commit the completed `.env` file.

## Step 10: Create the MySQL Database

1. Confirm MySQL is running in XAMPP.
2. Open `http://localhost/phpmyadmin` in a browser.
3. Select **Databases**.
4. Under **Create database**, enter:

```text
capstone_rfid_db
```

5. Choose `utf8mb4_unicode_ci` if phpMyAdmin asks for a collation.
6. Select **Create**.

The database name must exactly match `DB_DATABASE` in `.env`.

## Step 11: Create the Database Tables and Starting Data

Choose one setup option.

### Option A: demonstration setup

Use this when learning, testing, or preparing a capstone demonstration:

```powershell
php artisan migrate --seed
```

This creates the database tables and development demonstration accounts/data. The non-production account list is documented in [Default Account Passwords](../System%20Explanation/DEFAULT_ACCOUNT_PASSWORDS.md).

### Option B: clean operational setup

Use this when you want only a Root Admin, the current academic year, and baseline settings:

```powershell
php artisan migrate
php artisan db:seed --class=MinimalSeeder
```

The minimal Root Admin uses `root.admin@sample.com` and temporary password `change-me-now` unless environment overrides are configured. Change the temporary password immediately.

Do not run `php artisan migrate:fresh` on a database containing information you need. That command deletes all tables and data before rebuilding them.

## Step 12: Create the Public Storage Link

The application uses Laravel storage for face images, evidence, attachments, generated documents, and other files. Create the required link:

```powershell
php artisan storage:link
```

If Windows reports that the link already exists, no additional action is normally required.

## Step 13: Build the Frontend Once

Run a production build to confirm that Vue, TypeScript, Vite, Tailwind, and generated Laravel routes compile correctly:

```powershell
npm run build
```

Do not continue until the build finishes successfully. If Wayfinder reports a PHP version error, return to Step 3 and correct the PHP executable used by the terminal.

## Step 14: Start the System

Keep MySQL running in XAMPP. Open two PowerShell windows in the project folder.

In the first window, start Laravel:

```powershell
php artisan serve
```

In the second window, start the frontend development server:

```powershell
npm run dev
```

For automatic finalization of attendance for completed online classes, open a third window and run:

```powershell
php artisan schedule:work
```

Open this address in the browser:

```text
http://127.0.0.1:8000
```

Do not close the terminal windows while using the system. Press `Ctrl+C` in each terminal when you want to stop its process.

## Step 15: Verify the Installation

Run these checks from another terminal in the project folder:

```powershell
php artisan about
php artisan migrate:status
php artisan route:list
php artisan schedule:list
```

Then verify in the browser:

1. The public Student/Parent page opens.
2. A seeded or minimal administrator can sign in.
3. The Admin dashboard loads without an error.
4. Pages display styling and icons correctly.
5. `storage/logs/laravel.log` does not show a new fatal error.

Camera, RFID hardware, real email, AWS Rekognition, and Semaphore SMS require separate credentials, devices, permissions, and deployment-specific testing. A successful local page load does not prove those integrations are working.

## Daily Startup

After the one-time setup, the normal startup process is:

1. Open XAMPP and start MySQL.
2. Open PowerShell in the project folder.
3. Run `php artisan serve`.
4. Open a second terminal and run `npm run dev`.
5. Optionally run `php artisan schedule:work` in a third terminal.
6. Open `http://127.0.0.1:8000`.

## Getting Future Updates from GitHub

Before updating, stop the running Laravel, Vite, and scheduler processes with `Ctrl+C`. Preserve any local work before pulling changes.

```powershell
git status
git pull
composer install
npm install
php artisan migrate
php artisan storage:link
npm run build
```

If `git status` lists files you intentionally changed, do not discard them. Ask a developer to commit or safely preserve the work before running `git pull`.

After an update, restart the local servers.

## Common Problems

### `php` is not recognized or is too old

Run:

```powershell
where.exe php
php -v
```

Correct the Windows `Path`, reopen PowerShell, or use the full compatible PHP executable path.

### Composer reports missing PHP extensions

Confirm Composer uses the intended PHP installation:

```powershell
composer diagnose
php --ini
```

Enable the extension Composer names in the active `php.ini`, then reopen the terminal.

### MySQL connection is refused

- Start MySQL in XAMPP.
- Confirm `DB_HOST`, `DB_PORT`, and the database name in `.env`.
- Confirm `capstone_rfid_db` exists in phpMyAdmin.
- Clear cached configuration after changing `.env`:

```powershell
php artisan config:clear
```

### `jobs` table is missing

Set this in `.env` for local use:

```env
QUEUE_CONNECTION=sync
```

Then run:

```powershell
php artisan config:clear
```

### The page has no styling or Vite reports an error

- Confirm `npm install` completed.
- Keep `npm run dev` running.
- Rebuild with `npm run build`.
- Confirm the browser is opening the Laravel address, not the Vite port.

### Uploaded images or files return 404

Run:

```powershell
php artisan storage:link
```

Then confirm the file exists and the requesting account is authorized to view it.

### Email does not arrive

The beginner setup uses `MAIL_MAILER=log`, so email is not sent. Inspect `storage/logs/laravel.log`. Real password reset and Instructor OTP delivery require valid SMTP settings.

### Face recognition or SMS is unavailable

These features require valid AWS Rekognition or Semaphore credentials and network access. Keep the feature disabled during initial setup. The rest of the system can be installed and tested without them.

## Safe Reset for Disposable Local Data

Only use this command when the local database contains nothing you need:

```powershell
php artisan migrate:fresh --seed
```

This permanently deletes the current local database tables and rebuilds the demonstration data. Never use it on production or on a database containing important records.

## Next Documentation

After installation:

- Use [User Operations Tutorial](../System%20Explanation/USER_OPERATIONS_TUTORIAL.md) to configure and operate the system.
- Use [Authentication and Password Rules](../System%20Explanation/AUTHENTICATION_PASSWORD_RULES.md) for login and temporary-password behavior.
- Use [Testing and Regression Guide](../System%20Architecture/TESTING.md) before submitting or deploying changes.
- Use the complete [System Installation](README.md) guide for production deployment, external providers, permissions, queues, and scheduler configuration.
