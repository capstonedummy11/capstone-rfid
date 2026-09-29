# Deployment commands

## 1. Local Windows computer: build, commit, and push

Copy and paste this entire block into PowerShell from any location. It builds the frontend locally because the Lightsail server does not have enough RAM for a reliable Vite build.

```powershell
Set-Location "C:\Users\User 03\Desktop\capstone-rfid"
$env:Path = "C:\xampp\php8.5.8;$env:Path"
$env:NODE_OPTIONS = "--max-old-space-size=2048"
npm install
npm run build
git add app/Services/AwsFaceLivenessService.php
git add resources/js/lib/faceLiveness.tsx
git add resources/js/pages/Auth/InstructorVerify.vue
git add tests/Feature/FaceLivenessTest.php
git add tests/Feature/RequestedFeatureUiWiringTest.php
git add -A Server_command.md DEPLOYMENT_COMMANDS.md
git add -f -A public/build
git commit -m "Show Instructor Face Liveness test diagnostics"
git push origin HEAD
```

Review `git status` before committing if other local work is present. Never commit `.env`, `node_modules`, or AWS credentials.

## 2. AWS Lightsail server: pull and deploy

After the local push succeeds, copy and paste this entire block into the Lightsail terminal:

```bash
set -e
cd /var/www/capstone-rfid
git pull --ff-only origin feature/2000-face-detection
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

The server block intentionally does not run `npm install` or `npm run build`. It deploys the compiled `public/build` files committed by the local block.
