# Deployment commands

## 1. Local Windows computer: build the frontend

Run this block in PowerShell. The frontend must be built locally because the Lightsail server does not have enough RAM for a reliable Vite build. PHP 8.5.8 is placed first on `PATH` because Wayfinder runs Artisan while Vite is building, and Node 20 is used because it is the verified runtime for this checkout.

```powershell
Set-Location "C:\Users\User 03\Desktop\capstone-rfid"
$env:Path = "C:\xampp\php8.5.8;C:\nvm\v20.19.6;$env:Path"
$env:NODE_OPTIONS = "--max-old-space-size=2048"

# Run npm install first only when package.json or package-lock.json changed.
npm run build

if (-not (Test-Path "public/build/manifest.json")) {
    throw "Frontend build failed: public/build/manifest.json was not created."
}

Write-Host "Frontend production build completed." -ForegroundColor Green
```

## 2. Local Windows computer: review, commit, and push the frontend

Review the working tree before staging. Stage the intended source files explicitly so unrelated local work is not accidentally deployed. The compiled `public/build` directory is ignored by default, so `-f` is required.

```powershell
Set-Location "C:\Users\User 03\Desktop\capstone-rfid"
git status --short

# Add only the frontend source and documentation intended for this deployment.
git add -A resources/js resources/css DEPLOYMENT_COMMANDS.md
git add -f -A public/build

git diff --cached --check
git diff --cached --stat

git commit -m "Build frontend for deployment"
$deploymentBranch = git branch --show-current
git push origin $deploymentBranch
```

If the deployment also contains Laravel changes, explicitly add their exact `app`, `routes`, `database`, `config`, or test files before committing. Do not replace the explicit staging commands with `git add -A` when unrelated local work exists. Never commit `.env`, `node_modules`, credentials, or private keys.

## 3. AWS Lightsail server: pull and deploy the prebuilt frontend

After the local push succeeds, copy and paste this entire block into the Lightsail terminal:

```bash
set -e
cd /var/www/capstone-rfid

# Change this only when deploying a different branch.
DEPLOY_BRANCH="development"
git pull --ff-only origin "$DEPLOY_BRANCH"

test -f public/build/manifest.json
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
sudo chown -R ubuntu:www-data storage bootstrap/cache public/build
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
sudo find public/build -type d -exec chmod 755 {} \;
sudo find public/build -type f -exec chmod 644 {} \;
sudo nginx -t
sudo systemctl restart php8.4-fpm
sudo systemctl restart nginx
echo "Deployment completed successfully."
```

The server block intentionally does not run `npm install` or `npm run build`. Nginx serves the compiled `public/build` files that were produced locally and committed in section 2.

## 4. Frontend deployment verification

After deployment:

1. Open the website in a private/incognito window or perform a hard refresh.
2. Confirm that `public/build/manifest.json` on the server has the latest Git commit timestamp.
3. Open the browser developer tools and confirm that the loaded JavaScript filenames match the current entries in `public/build/manifest.json`.
4. Test the updated page and check the browser console plus Laravel/Nginx logs for errors.
