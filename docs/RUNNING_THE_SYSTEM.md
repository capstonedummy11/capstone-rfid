# Running The RFID System (From Zero)

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

This guide is for a brand-new machine. Follow the steps in order.

## Quick Start (Do This First)

1. Install XAMPP (includes PHP and MySQL): [Go to XAMPP installation](#step-1-install-xampp)
2. Install Node.js and npm: [Go to Node.js installation](#step-2-install-nodejs-and-npm)
3. Install Composer: [Go to Composer installation](#step-3-install-composer)
4. Copy the repository to your own device: [Go to repository copy step](#step-4-copy-the-repository-to-your-device)
5. Install project dependencies: [Go to project setup](#step-5-project-setup)
6. Configure environment and database: [Go to environment setup](#step-6-environment-and-database)
7. Run the app (backend + frontend): [Go to run commands](#step-7-run-the-system)

## What You Will Run Daily

Open **2 terminals** inside the project folder:

- Terminal 1: `php artisan serve`
- Terminal 2: `npm run dev`

Then open: `http://127.0.0.1:8000`

---

## Step 1: Install XAMPP

1. Download XAMPP: https://www.apachefriends.org/index.html
2. Install it with default settings.
3. Open XAMPP Control Panel.
4. Start **MySQL**.

Notes:
- This project uses Laravel. PHP comes from XAMPP.
- Apache is optional for this workflow, because `php artisan serve` can run the app.

## Step 2: Install Node.js and npm

1. Download Node.js LTS: https://nodejs.org/
2. Install with default settings.
3. Verify in terminal:

```bash
node -v
npm -v
```

## Step 3: Install Composer

1. Download Composer installer: https://getcomposer.org/download/
2. Install Composer for Windows.
3. During setup, point Composer to your XAMPP PHP executable (example path):
   `C:\xampp\php\php.exe`
4. Verify:

```bash
composer -V
```

## Step 4: Copy the Repository to Your Device

You can copy the project in two ways.

### Option A: Clone with Git (recommended)

1. Open terminal where you want to save the project.
2. Run:

```bash
git clone https://github.com/capstonedummy11/capstone-rfid.git
cd capstone-rfid
```

### Option B: Download ZIP (no Git needed)

1. Open the repository in browser.
2. Click **Code** -> **Download ZIP**.
3. Extract the ZIP to your preferred folder.
4. Open terminal inside the extracted project folder.

## Step 5: Project Setup

1. Open terminal in project root.
2. Install PHP dependencies:

```bash
composer install
```

3. Install JavaScript dependencies:

```bash
npm install
```

## Step 6: Environment and Database

1. Create your environment file:

```bash
copy .env.example .env
```

2. Generate app key:

```bash
php artisan key:generate
```

3. Edit `.env` and set database values (example):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=capstone_rfid
DB_USERNAME=root
DB_PASSWORD=
```

4. Create the database (for example, in phpMyAdmin) with the same name as `DB_DATABASE`.
5. Run migrations and seeders:

```bash
php artisan migrate --seed
```

## Step 7: Run the System

Run both commands in separate terminals:

```bash
php artisan serve
```

```bash
npm run dev
```

Open:
- App: `http://127.0.0.1:8000`

## Step 8: Default Troubleshooting

### Problem: `php` command not found

- Add `C:\xampp\php` to your Windows PATH.
- Restart terminal.

### Problem: Composer cannot detect PHP

- Re-run Composer installer and point to `C:\xampp\php\php.exe`.

### Problem: DB connection error

- Ensure MySQL is running in XAMPP.
- Check `.env` DB values.
- Confirm database exists.

### Problem: Vite assets not loading

- Make sure `npm run dev` is running.
- Check if port 5173 is blocked by firewall.

## Optional: Build for Production

```bash
npm run build
php artisan config:cache
php artisan route:cache
```
