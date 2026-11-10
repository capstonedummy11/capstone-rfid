# Server installation and AWS face demonstration

This is a presenter checklist for a **new AWS Lightsail Ubuntu/Nginx installation** with **MySQL on the same instance**, and for enabling face verification on an existing installation. The current deployment uses a frontend build made on the Windows development computer; the Lightsail server pulls the committed `public/build` bundle. Use the actual branch, domain, and PHP-FPM version of the target server. Do not overwrite an existing server `.env` or seed a live database.

## 1. Website installation

### A. Create and connect to the Lightsail instance

1. Sign in to the [Lightsail console](https://lightsail.aws.amazon.com/) and choose **Create instance**. Choose a nearby AWS Region and Availability Zone, **Linux/Unix > OS Only > Ubuntu**, an instance plan with enough RAM for PHP-FPM and MySQL together, and a descriptive name. Choose **Create instance**. This creates a billable server; check the current plan and estimate in the console. [AWS instance instructions](https://docs.aws.amazon.com/lightsail/latest/userguide/getting-started-with-amazon-lightsail.html).
2. Open **Instances > your instance > Networking**. Create and attach a **static IP**. In the instance's IPv4 firewall, allow HTTP/TCP 80 and HTTPS/TCP 443; restrict SSH/TCP 22 to your administrator IP when practical. Do **not** open MySQL/TCP 3306 to the internet because MySQL runs on this same server. [Static IP](https://docs.aws.amazon.com/lightsail/latest/userguide/lightsail-create-static-ip.html) · [Firewall](https://docs.aws.amazon.com/lightsail/latest/userguide/amazon-lightsail-editing-firewall-rules.html).
3. For the demonstration hostname `rbasystemDemo.duckdns.org`, follow the separate [DuckDNS on Lightsail tutorial](DUCKDNS_LIGHTSAIL_TUTORIAL.md) to point it to the static IP. If you already own a domain, create an **A record** for the desired hostname at its DNS provider instead. Wait for the hostname to resolve to that IP. [AWS DNS instructions](https://docs.aws.amazon.com/lightsail/latest/userguide/lightsail-how-to-create-dns-entry.html).
4. In **Instances > your instance**, choose the browser SSH icon (**Connect using SSH**). Ubuntu instances normally sign in as `ubuntu`. Record `lsb_release -a` and `php -v` so the package and PHP-FPM commands below match the actual image. [AWS browser SSH instructions](https://docs.aws.amazon.com/lightsail/latest/userguide/lightsail-how-to-connect-to-your-instance-virtual-private-server.html).

### B. Install Nginx and local MySQL

**First, run these commands** on a new Ubuntu 24.04 instance. Wait for each command to finish before starting the next:

```bash
sudo apt update
```

```bash
sudo apt upgrade -y
```

```bash
sudo apt install -y nginx mysql-server git unzip curl ca-certificates
```

**If a command fails, stop and use the matching fix below; then return to this step.**

#### Installation error table

| Error shown | What to do next |
| --- | --- |
| `503 Service Unavailable` from `ap-southeast-1.ec2.archive.ubuntu.com` | Follow [Fix A: change the Ubuntu mirror](#fix-a), then retry the failed command. |
| `mysql.service: Result: oom-kill` or `dpkg` says `mysql-server` is unconfigured | Follow [Fix B: MySQL runs out of memory](#fix-b). |
| `Command 'composer' not found` during `composer install` | Follow [Fix C: install Composer](#fix-c), then retry the project install. |
| A purple **Pending kernel upgrade** or **Daemons using outdated libraries** dialog | Follow [Package-upgrade screens](#package-upgrade-screens). |

When installation finishes, verify MySQL before continuing:

```bash
free -h
```

```bash
sudo dpkg --audit
```

```bash
sudo systemctl status mysql --no-pager
```

Continue only when `dpkg --audit` lists no unfinished packages and MySQL is active.

---

### C. Install PHP 8.4 and Composer

Ubuntu 24.04 supplies PHP 8.3 by default, so `php8.4-*` will say **Unable to locate package** until you add the [PHP project's documented Ubuntu PPA](https://www.php.net/downloads.php?os=linux&osvariant=linux-ubuntu&usage=web&version=8.4). This third-party source is needed for the PHP 8.4 version used by this project's CI and deployment script. It needs ongoing updates.

On Ubuntu 24.04, add the PHP 8.4 package source and check the candidate before installing:

```bash
sudo apt install -y software-properties-common
```

```bash
sudo LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php
```

```bash
sudo apt update
```

```bash
apt-cache policy php8.4-fpm
```

When the policy output shows a real **Candidate** version for PHP 8.4, install CLI, FPM, and extensions:

```bash
sudo apt install -y php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath
```

Check the installed PHP version:

```bash
php -v
```

Check the required PHP modules:

```bash
php -m | grep -E 'curl|mbstring|pdo_mysql|xml'
```

Start and enable the services:

```bash
sudo systemctl enable --now nginx mysql php8.4-fpm
```

Install Composer 2 before the project dependencies. If a later `composer install` still says the command is not found, follow [Fix C: install Composer](#fix-c) at the bottom of this guide.

```bash
sudo apt install -y composer
```

```bash
composer --version
```

```bash
php -v
```

Use PHP 8.4 for both CLI and FPM. Do not switch an existing production server's PHP runtime during a live demonstration.

### D. Create the database on this server

Open the MySQL console:

```bash
sudo mysql
```

Then run the following SQL, replacing the sample password with a unique value. Keep the database private to the machine (`127.0.0.1`); the application user is scoped to this database. Run this only for a **new empty installation**.

```sql
CREATE DATABASE capstone_rfid_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```sql
CREATE USER 'capstone_user'@'127.0.0.1' IDENTIFIED BY 'CapstoneStrongPass123!';
```

```sql
CREATE USER 'capstone_user'@'localhost' IDENTIFIED BY 'CapstoneStrongPass123!';
```

```sql
GRANT ALL PRIVILEGES ON capstone_rfid_db.* TO 'capstone_user'@'127.0.0.1';
```

```sql
GRANT ALL PRIVILEGES ON capstone_rfid_db.* TO 'capstone_user'@'localhost';
```

Leave the `mysql>` prompt before running the following Ubuntu shell commands:

```sql
exit
```

MySQL can identify a local connection as `localhost` even when the client uses `127.0.0.1`. Create both local account entries with the same password so either match works. On the server, check MySQL and test the exact TCP login before running migrations:

```bash
sudo systemctl status mysql --no-pager
```

```bash
mysql --protocol=TCP -h 127.0.0.1 -u capstone_user -p capstone_rfid_db
```

When `Enter password:` appears, paste this demonstration password and press **Enter**. The terminal will not display characters while you type or paste:

```text
CapstoneStrongPass123!
```

Paste the password only at `Enter password:`, never at the `ubuntu@...$` shell prompt. A `mysql>` prompt means authentication succeeded; type `exit` to return to the shell. If login fails, check the account's host and password in MySQL before continuing. Set these matching values in the server's `.env` for the disposable demonstration instance:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=capstone_rfid_db
DB_USERNAME=capstone_user
DB_PASSWORD=CapstoneStrongPass123!
```

This password is included here only for the disposable demonstration. Do not reuse it for production or another server. MySQL should listen on loopback only; confirm its bind address in the MySQL server configuration. Back up both MySQL and `storage/app` before updates.

### E. Put the project on the server

On the Windows development computer, use [Deployment Commands](../../DEPLOYMENT_COMMANDS.md) to run the complete frontend build and commit `public/build`. On the server, clone the actual repository URL and deployment branch, then verify the bundle. Use a deploy key or another authorized Git credential without embedding a token in the clone URL.

```bash
sudo mkdir -p /var/www
```

```bash
sudo chown ubuntu:www-data /var/www
```

```bash
cd /var/www
```

```bash
git clone --branch main YOUR_REPOSITORY_URL capstone-rfid
```

Replace `YOUR_REPOSITORY_URL` with the repository's HTTPS or SSH clone URL. This demonstration uses the `main` branch; verify that the local frontend build was committed and pushed to `main` before cloning. For a private repository, use a deploy key or another authorized Git credential without placing a token in the URL.

```bash
cd capstone-rfid
```

```bash
test -f public/build/manifest.json
```

### F. Configure Nginx and HTTPS

Before editing Nginx, complete [DuckDNS on Lightsail](DUCKDNS_LIGHTSAIL_TUTORIAL.md): create or open `rbasystemDemo.duckdns.org`, set its address to this instance's Lightsail static IP, and verify that the hostname resolves to that IP. Use the hostname you actually registered if it differs.

First, check the PHP-FPM socket that was installed:

```bash
ls /run/php/*.sock
```

Open a new Nginx site file with `nano`:

```bash
sudo nano /etc/nginx/sites-available/capstone-rfid
```

Paste the complete configuration below into `nano`. The example hostname is `rbasystemDemo.duckdns.org`; replace it if your registered DuckDNS hostname differs. Replace `php8.4-fpm.sock` only if the `ls` output shows a different PHP-FPM socket. The `public` web root prevents direct access to `.env`, source, and private storage.

```nginx
server {
    listen 80;
    server_name rbasystemDemo.duckdns.org;
    root /var/www/capstone-rfid/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    location ~ /\. { deny all; }
}
```

Save the file in `nano`: press **Ctrl+O**, then **Enter** to confirm the filename, then **Ctrl+X** to exit. Enable the site and check the syntax before reloading Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/capstone-rfid /etc/nginx/sites-enabled/capstone-rfid
```

```bash
sudo nginx -t
```

```bash
sudo systemctl reload nginx
```

### Configure and start Laravel

For a **new** installation only, copy `.env.example` to `.env` from the project directory. The file is named `.env.example` in this repository. Edit the new `.env` before running Composer, key generation, migrations, or seeding. **If `.env` already exists on the server, skip the copy and key-generation steps** so the deployment keeps its current secrets and encrypted data.

```bash
cd /var/www/capstone-rfid
```

```bash
test -f public/build/manifest.json
```

```bash
cp .env.example .env
```

New installation only. Skip this command if `.env` already exists.

Open the server environment file:

```bash
nano .env
```

Set the actual server values: `APP_ENV=production`, `APP_DEBUG=false`, the HTTPS `APP_URL`, `APP_TIMEZONE=Asia/Manila`, `SECURE_LOGIN_ROUTE`, the local MySQL database/user/password from step D, real SMTP settings for login OTP, `SESSION_SECURE_COOKIE=true`, and a strong `MINIMAL_ROOT_ADMIN_PASSWORD` for the first login. Use `QUEUE_CONNECTION=sync` until a queue backend and worker are installed. Leave `APP_KEY` empty on a new installation; `php artisan key:generate` fills it below. For AWS face features, set the server's real AWS values from sections 2 and 3. Press **Ctrl+O**, **Enter**, then **Ctrl+X** to save and close `nano`. Never commit `.env` or show its secrets in the recording.

```bash
sudo chown ubuntu:www-data .env
```

```bash
chmod 640 .env
```

The PHP-FPM `www-data` group must be able to read `.env`.

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

```bash
php artisan key:generate
```

New installation only. Do not change the application key on an existing deployment.

```bash
php artisan migrate --force
```

```bash
php artisan db:seed --class=MinimalSeeder
```

New empty database only. Do not run this seeder on an existing deployment.

```bash
php artisan storage:link
```

```bash
sudo chown -R ubuntu:www-data storage bootstrap/cache
```

```bash
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
```

```bash
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

```bash
php artisan optimize:clear
```

```bash
php artisan config:cache
```

```bash
php artisan view:cache
```

```bash
sudo nginx -t
```

```bash
sudo systemctl restart php8.4-fpm
```

Use the installed PHP-FPM service name if it differs from `php8.4-fpm`.

```bash
sudo systemctl restart nginx
```

### Verify DNS and HTTPS before the AWS face setup

You should have completed the [DuckDNS on Lightsail tutorial](DUCKDNS_LIGHTSAIL_TUTORIAL.md) before editing Nginx. The examples below use `rbasystemDemo.duckdns.org`; replace it everywhere if your registered hostname differs. Confirm that the hostname points to the Lightsail static IP before requesting HTTPS.

On the server, check which IPv4 address the hostname resolves to:

```bash
getent ahostsv4 rbasystemDemo.duckdns.org
```

The displayed IPv4 address must match the Lightsail static IP. DNS changes can take time to propagate. Check the hostname from your own computer or browser too, because the server's resolver alone does not prove public DNS is ready.

Check that Nginx serves the site over HTTP:

```bash
curl -I http://rbasystemDemo.duckdns.org
```

An HTTP response confirms the hostname reaches a web server; a Laravel error response means the site still needs troubleshooting. If DNS is wrong, correct the A record and wait before requesting a certificate. If DNS is right but the site cannot be reached, check Lightsail inbound port **80**, the Nginx `server_name`, and the Nginx service.

After DNS resolves to this instance and HTTP works, install Certbot. The certificate command will say `sudo: certbot: command not found` if Certbot has not been installed yet. On Ubuntu, follow the [official Certbot Nginx/snap instructions](https://certbot.eff.org/instructions?ws=nginx&os=snap). Install `snapd` if needed:

```bash
sudo apt install -y snapd
```

Install Certbot and make its command available:

```bash
sudo snap install --classic certbot
```

```bash
sudo ln -s /snap/bin/certbot /usr/local/bin/certbot
```

If the link already exists, skip the link command. Confirm Certbot is available:

```bash
certbot --version
```

Then request the certificate, replacing the hostname if yours differs:

```bash
sudo certbot --nginx -d rbasystemDemo.duckdns.org
```

Check HTTPS and certificate renewal:

```bash
curl -I https://rbasystemDemo.duckdns.org
```

```bash
sudo certbot renew --dry-run
```

Confirm the HTTPS page loads in a browser. Set `APP_URL=https://rbasystemDemo.duckdns.org` and `SESSION_SECURE_COOKIE=true` in `.env`, then rebuild Laravel's config cache:

```bash
php artisan config:cache
```

The camera challenge requires a secure browser context outside localhost. Continue to AWS face setup only after the HTTPS site loads.

The minimal seeder creates a Root Admin and baseline settings. Replace the bootstrap password immediately. Do not use the full demo seeder on a server containing real records. Run Laravel's scheduler every minute and supervise a queue worker **only if** the configured queue backend has its required tables/service; see [System Installation](README.md). Verify the HTTPS page, login and OTP delivery, uploaded image access, and `php artisan migrate:status`. For later updates, use the pull/deploy block in [Deployment Commands](../../DEPLOYMENT_COMMANDS.md), with a database and storage backup first.

## 2. Activate face comparison (AWS Rekognition)

The application compares a newly captured face to the enrolled face using AWS `CompareFaces`. It does not need a Rekognition collection. First enroll an Instructor and Student face through Registrar enrollment, and make sure the panel device, RFID, schedule, and feature access are configured as described in [User Operations Tutorial](../System%20Explanation/USER_OPERATIONS_TUTORIAL.md).

### A. Create the backend AWS identity

The current Laravel `AwsFaceRecognitionService` explicitly requires an access key and secret and passes them to the AWS SDK. Therefore, **this version cannot use an instance role by itself**. Do not try to attach an EC2 instance profile in the Lightsail console. Use a dedicated, limited IAM user for this deployment; plan a code change before moving to temporary role credentials.

1. Open the [AWS IAM console](https://console.aws.amazon.com/iam/) in the same AWS account that will pay for Rekognition. Go to **Access management > Policies > Create policy > JSON**. Paste this policy, choose **Next**, name it `CapstoneRekognitionBackend`, then **Create policy**. `CompareFaces` is the only action needed for still-image comparison; add the two liveness actions when completing section 3.

   ```json
   {
     "Version": "2012-10-17",
     "Statement": [{
       "Effect": "Allow",
       "Action": [
         "rekognition:CompareFaces",
         "rekognition:CreateFaceLivenessSession",
         "rekognition:GetFaceLivenessSessionResults"
       ],
       "Resource": "*"
     }]
   }
   ```

2. In IAM, go to **Access management > Users > Create user**. Give it a name such as `capstone-rekognition-server`. Leave console access off. In **Set permissions**, attach the new `CapstoneRekognitionBackend` policy, then create the user. Do not use the AWS account root user. [AWS IAM user guide](https://docs.aws.amazon.com/IAM/latest/UserGuide/id_users_create.html).
3. Open the new user, select **Security credentials > Access keys > Create access key**. On **Access key best practices & alternatives**, review AWS's role/temporary-credential recommendation. This Lightsail deployment's current PHP code needs an explicit key and secret, so if you are continuing with the documented IAM-user setup, select **Other**, then **Next**. Add a description such as `Capstone Lightsail Rekognition demo`, choose **Create access key**, and record the **Access key ID** and **Secret access key** in a private password manager immediately; the secret is shown only at creation. [AWS access key creation steps](https://docs.aws.amazon.com/IAM/latest/UserGuide/access-keys-admin-managed.html). Rotate or disable the key after the demonstration if it is no longer needed.

### B. Configure the Lightsail server

4. In Lightsail browser SSH, run `cd /var/www/capstone-rfid` and open `.env` with `nano .env`. Set the following names using the **actual** key values. Choose a Rekognition `CompareFaces` region available to this account. The similarity threshold of 90 is a starting value, not a guarantee of identity accuracy.

   ```dotenv
   AWS_ACCESS_KEY_ID=your-new-access-key-id
   AWS_SECRET_ACCESS_KEY=your-new-secret-access-key
   AWS_DEFAULT_REGION=us-east-1
   AWS_REKOGNITION_SIMILARITY_THRESHOLD=90
   AWS_REKOGNITION_LIVENESS_ENABLED=false
   ```

   In `nano`, press **Ctrl+O**, **Enter**, then **Ctrl+X** to save and exit. Do not paste real keys into a presentation, chat, terminal command history, or Git commit. The `.env` file should remain readable only to the deploy user and PHP-FPM group (`ubuntu:www-data`, mode `640`). `AWS_SESSION_TOKEN` is for temporary credentials only; leave it empty for this IAM user.

5. Apply the server configuration and confirm the installed SDK without printing secrets:

   ```bash
   composer show aws/aws-sdk-php
   php artisan optimize:clear
   php artisan config:cache
   ```

### C. Switch it on and prove it works

6. Log in as Admin. Open **Admin > System Settings** and read the Face Rekognition provider status. If it says available, turn on **Face Rekognition** and save. If your demo uses online classes, enable that separate online-class face setting too. An “available” label checks local configuration; it does not make a network request to prove IAM access.
7. Enroll clear, current face images for the demo Instructor and Student using the Registrar enrollment pages. Prepare their RFID records, active schedule, and panel device as described in the [User Operations Tutorial](../System%20Explanation/USER_OPERATIONS_TUTORIAL.md). Use a browser that permits camera access over HTTPS.
8. Perform an Instructor face verification or a Student RFID attendance tap, then complete the camera comparison. Show the result and Attendance Logs. Repeat with an intentionally nonmatching face to demonstrate rejection. If AWS returns an error, inspect `storage/logs/` and the browser console without exposing credentials or face images. Follow only the application's explicit Instructor fallback flow when comparison is unavailable.

The settings availability check confirms the SDK and configuration are present; a real camera/CompareFaces attempt is needed to prove AWS connectivity, permissions, image quality, and threshold behavior.

## 3. Activate Face Liveness

Face Liveness is an additional video challenge. For identity checks, Laravel accepts a successful AWS liveness result only when its confidence meets the Student or Instructor threshold, then compares AWS's reference frame against the enrolled image. A liveness pass alone does not establish identity. Temporary Exit/Return is a separate optional **Student Liveness** setting; it requires Instructor RFID authorization and does not compare the Student's enrolled face.

**Check which feature is actually running first.** `CompareFaces` in section 2 works without Cognito. If `AWS_REKOGNITION_LIVENESS_ENABLED=false`, the application uses its still-image face flow and no Identity Pool is needed. An empty Cognito **Identity pools** list in the selected AWS region is therefore consistent with working face comparison. It does not prove whether a pool exists in another region or AWS account. Only follow the pool steps below when you intend to enable the separate **Live face verification** video challenge. On the server, check the effective Laravel setting without printing keys:

```bash
cd /var/www/capstone-rfid
```

```bash
php artisan tinker --execute="dump(config('services.aws_rekognition.liveness.enabled'), config('services.aws_rekognition.liveness.region'));"
```

If this prints `false`, keep the current working face comparison setup as it is unless the demonstration specifically requires video liveness. If it prints `true`, verify a pool exists in that printed region and the **Live face verification** dialog actually completes; a successful still-image comparison alone does not test liveness.

The example below uses **Asia Pacific (Tokyo), `ap-northeast-1`**, matching the existing Cognito Identity Pool found for this demonstration. Copy the actual full pool ID from AWS; do not put its UUID in repository documentation.
### A. Choose a region and create the Cognito Identity Pool

1. Confirm `ap-northeast-1` remains supported in the [AWS Face Liveness regional FAQ](https://docs.aws.amazon.com/rekognition/latest/dg/face-liveness-faq.html) and this checkout's `AwsFaceLivenessService::SUPPORTED_REGIONS` list. The Lightsail server and still-image `CompareFaces` may be in different regions. Keep the Cognito pool and Face Liveness in **Tokyo**.
2. In the AWS Console, switch the region picker to **Asia Pacific (Tokyo)**. Open **Amazon Cognito > Identity pools** and select the existing pool for this demonstration. Copy its full **Identity pool ID**, beginning `ap-northeast-1:`. If no pool exists in this AWS account and region, choose **Create identity pool**, enable **Guest access**, and let Cognito create a guest IAM role. The app does not require a Cognito User Pool. [Cognito identity pool console guide](https://docs.aws.amazon.com/cognito/latest/developerguide/identity-pools.html).
3. In the pool, open **User access > Guest access** and confirm it is **Active**. Follow the linked guest IAM role into IAM, then use **Add permissions > Create inline policy > JSON** to attach this single-action permission:

   ```json
   {
     "Version": "2012-10-17",
     "Statement": [{
       "Effect": "Allow",
       "Action": "rekognition:StartFaceLivenessSession",
       "Resource": "*"
     }]
   }
   ```

   Name the policy `CapstoneStartFaceLiveness`. Review the role's other attached permissions and keep guest access limited to what this challenge requires. The pool ID is public, and guests can obtain temporary credentials for this role; narrow role permissions matter. [Amplify liveness IAM instructions](https://ui.docs.amplify.aws/react/connected-components/liveness).

### B. Configure the Laravel server

4. In **IAM > Users > `capstone-rekognition-server` > Permissions**, confirm the section 2 backend policy is attached and includes `rekognition:CreateFaceLivenessSession` and `rekognition:GetFaceLivenessSessionResults`. The browser's guest role only starts the video stream; Laravel creates the session and retrieves the result. [AWS Face Liveness API workflow](https://docs.aws.amazon.com/rekognition/latest/dg/face-liveness-programming-api.html).
5. In Lightsail SSH, change to `/var/www/capstone-rfid`, then open `.env` with `nano .env`. Set the following, replacing the pool ID with the one copied from Cognito. Keep the existing backend AWS key and secret from section 2. In `nano`, press **Ctrl+O**, **Enter**, **Ctrl+X**.

   ```dotenv
   AWS_REKOGNITION_LIVENESS_ENABLED=true
   AWS_REKOGNITION_LIVENESS_REGION=ap-northeast-1
   LIVENESS_MIN_CONFIDENCE_STUDENT=60
   LIVENESS_MIN_CONFIDENCE_INSTRUCTOR=70
   VITE_AWS_COGNITO_IDENTITY_POOL_ID=ap-northeast-1:replace-with-your-pool-uuid
   ```

6. Apply the configuration:

   ```bash
   php artisan optimize:clear
   php artisan config:cache
   ```

   Despite its `VITE_` name, this checkout reads the pool ID in Laravel config and returns it to the browser in the liveness session response. **Changing only this pool ID does not require rebuilding JavaScript.** Rebuild the frontend locally and deploy `public/build` when application code or frontend dependencies change, using [Deployment Commands](../../DEPLOYMENT_COMMANDS.md). Do not put the backend key or secret in any `VITE_*` setting.

### C. Turn on the feature and test it

7. Open the site over HTTPS, allow camera access, and refresh the page. In **Admin > System Settings**, confirm Face Rekognition remains enabled and Face Liveness reports configured. There is no separate Admin switch for the main liveness challenge; `AWS_REKOGNITION_LIVENESS_ENABLED=true` enables it for supported identity flows. The separate **Require Student Liveness for Temporary Exit / Return** switch controls only that movement flow.
8. With an enrolled Instructor or Student, start a face-protected verification, attendance tap, or online-class join. The browser should show **Live face verification** and AWS camera prompts. Complete the challenge, then confirm the identity comparison and application result. Repeat once with **Cancel** or a failed attempt: it must not grant verification. For a temporary movement demo, enable its separate setting and also perform the required Instructor RFID authorization.
9. If the challenge fails, check in order: HTTPS/camera permission, Laravel configuration cache, supported region and matching pool region, guest access status, guest role's `StartFaceLivenessSession` permission, backend user's create/get permissions, browser console, and Laravel logs. A successful local “configured” status is not a live AWS permission test. Review AWS usage and billing before repeated demonstrations.

## 4. Demo sequence and recovery

1. Show the server URL, HTTPS certificate, and logged-in Root Admin. Explain the `public/` document root, server `.env`, MySQL, Composer dependencies, and locally built frontend manifest.
2. Show **Admin > System Settings** with Face Rekognition available and enabled. Explain that `CompareFaces` checks identity against an enrolled image.
3. Show Face Liveness enabled and its regional Cognito setup. Explain the sequence: Laravel creates a session, browser streams the challenge, Laravel reads the AWS result, then compares the reference face with enrollment.
4. Perform an enrolled Instructor or Student flow using a real camera. Show Attendance Logs or the relevant verification result. Repeat with an unsuccessful/cancelled attempt to show rejection. For a temporary movement demo, enable its separate setting and show that Instructor RFID remains required.

If the setting says unavailable, check server config cache, installed AWS SDK, IAM user credentials, region, IAM actions, and outbound AWS access. If the challenge does not open, check the locally built pool ID in `public/build`, Cognito unauthenticated-role policy, browser camera permission, HTTPS, and browser console. If the challenge succeeds but identity fails, check enrollment image quality, role confidence threshold, similarity threshold, and Laravel logs. Never display keys, tokens, `.env`, or biometric images to the audience.

## Environment file comparison for this guide

The template was checked against the local `.env` **by key name only**; no secret values were copied. `.env.example` contains all keys present in the local file. It also includes newer optional settings absent from that local file, so exact key-set equality is neither required nor desirable. `SMS_PROVIDER`, `MAIL_ENCRYPTION`, and `AWS_REKOGNITION_LIVENESS_CONFIDENCE_THRESHOLD` are retained in the template only to make the older `.env` names visible; current code uses System Settings, `MAIL_SCHEME`, and the role-specific `LIVENESS_MIN_CONFIDENCE_*` keys instead. Compare the **actual server** `.env` separately before the presentation.

## Installation troubleshooting

<a id="package-upgrade-screens"></a>

### ℹ️ Package-upgrade screens in SSH

[Return to the installation error table](#installation-error-table)

`apt upgrade` may open text dialogs instead of returning immediately to the shell:

1. **Pending kernel upgrade** says the installed kernel is newer than the currently running one. Press **Enter** on `<Ok>`. Let the package command finish; do not reboot while `apt` or `dpkg` is still running.
2. **Daemons using outdated libraries** asks which services to restart. Leave the preselected `[*]` services selected. Press **Tab** until `<Ok>` is highlighted, then press **Enter**. SSH may pause or disconnect while services restart; reconnect if necessary.
3. When the normal `ubuntu@...$` prompt returns and the upgrade has completed, run `sudo reboot` to load the new kernel. Reconnect through SSH after the instance comes back and check `uname -r` against the kernel version shown in the dialog. A reboot is not performed automatically by the dialog.

---

<a id="fix-a"></a>

### ⚠️ Fix A: package downloads fail with HTTP 503

[Return to the installation error table](#installation-error-table)

During one Lightsail setup, `apt upgrade` and `apt install` downloaded some files but then reported `503 Service Unavailable` from `ap-southeast-1.ec2.archive.ubuntu.com`. The same error happened over IPv6 and IPv4. This means the configured regional package mirror could not serve those files at that time; it does **not** indicate a Laravel, MySQL, or Lightsail inbound-firewall problem. The exact cause inside the mirror is not established. Do not assume a command succeeded because it downloaded most packages.

First confirm the Ubuntu release and where the mirror is configured:

```bash
lsb_release -rs
```

```bash
grep -R 'ap-southeast-1.ec2.archive.ubuntu.com' /etc/apt/sources.list /etc/apt/sources.list.d/ 2>/dev/null
```

On Ubuntu 24.04 (`noble`), if the result names `/etc/apt/sources.list.d/ubuntu.sources`, use this exact block. The backup is stored **outside** `sources.list.d` so APT does not warn that it is ignoring a file with an invalid extension. The replacement is plain shell text, not a Markdown link:

```bash
sudo cp /etc/apt/sources.list.d/ubuntu.sources /etc/apt/ubuntu.sources.backup
```

```bash
sudo sed -i 's|ap-southeast-1.ec2.archive.ubuntu.com|archive.ubuntu.com|g' /etc/apt/sources.list.d/ubuntu.sources
```

```bash
grep -n 'archive.ubuntu.com' /etc/apt/sources.list.d/ubuntu.sources
```

```bash
sudo apt-get update
```

If the result instead names `/etc/apt/sources.list`, as on an Ubuntu 22.04 (`jammy`) instance, back up that file and replace only the failing mirror host with [Ubuntu's primary package archive](https://documentation.ubuntu.com/project/how-ubuntu-is-made/concepts/package-archive/):

```bash
sudo cp /etc/apt/sources.list /etc/apt/sources.list.backup
```

```bash
sudo sed -i 's|ap-southeast-1.ec2.archive.ubuntu.com|archive.ubuntu.com|g' /etc/apt/sources.list
```

```bash
sudo apt-get update
```

After `apt-get update` succeeds, check `free -h` and `sudo dpkg --audit` before retrying MySQL, because a previous interrupted install may have left its package unconfigured. If the source search names another file, inspect that file before editing. Do not copy a `jammy` source list onto a `noble` server or mix Ubuntu releases. This project requires PHP `^8.2`; choose a supported PHP repository and verify CLI/FPM versions before continuing on 22.04. The backup of the 22.04 list is `/etc/apt/sources.list.backup`.

---

<a id="fix-b"></a>

### ⚠️ Fix B: MySQL runs out of memory

[Return to the installation error table](#installation-error-table)

If `mysql.service` reports `Result: oom-kill`, the server killed MySQL during startup. Check actual RAM, swap, and free disk:

```bash
free -h
```

```bash
swapon --show
```

```bash
df -h /
```

Use a Lightsail plan with enough memory for MySQL, PHP-FPM, and Nginx together; 2 GB is the minimum recommended for this demonstration. Installing the packages one at a time does not fix MySQL's runtime memory use. After memory is available, finish the interrupted package setup and confirm MySQL is active:

```bash
sudo dpkg --configure -a
```

```bash
sudo systemctl status mysql --no-pager
```

If MySQL is still killed on a 2 GB instance, inspect the actual `free -h` output and largest processes before changing MySQL settings or adding swap. Do not keep repeating `dpkg --configure -a` without resolving the memory shortage.

---

<a id="fix-c"></a>

### ⚠️ Fix C: Composer command not found

[Return to the installation error table](#installation-error-table)

If `composer install` prints `Command 'composer' not found`, install Ubuntu 24.04's Composer 2 package:

```bash
sudo apt install -y composer
```

```bash
composer --version
```

```bash
php -v
```

If `php -v` no longer shows PHP 8.4 after installing Composer, use `php8.4 /usr/bin/composer` for the project install and check the CLI alternative before running Artisan. The [official Composer installer](https://getcomposer.org/download/) is an alternative when the Ubuntu package is unavailable. Then return to the project directory and retry the original `composer install` command without `sudo`.

---
