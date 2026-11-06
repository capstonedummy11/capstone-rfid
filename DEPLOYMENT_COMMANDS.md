# Deployment commands

This project uses a **local-build deployment**. Run the complete frontend production build on the Windows development computer, commit the generated `public/build` files with the rest of the system changes, and push that commit to the deployment branch. The server only pulls and serves the prebuilt files; it must not run `npm install` or `npm run build`.

## 1. Local Windows computer: build the complete frontend

Run this block in PowerShell from the project root. `npm run build` creates the production bundle for the complete frontend, not only the page that was changed. The build must run locally because the Lightsail server does not have enough RAM for a reliable Vite build. PHP 8.5.8 is placed first on `PATH` because Wayfinder runs Artisan while Vite is building, and Node 20 is the verified runtime for this checkout.

```powershell
Set-Location "C:\Users\User 03\Desktop\capstone-rfid"
$env:Path = "C:\xampp\php8.5.8;C:\nvm\v20.19.6;$env:Path"
$env:NODE_OPTIONS = "--max-old-space-size=2048"

# Run npm install first only when package.json or package-lock.json changed.
npm run build

if (-not (Test-Path "public/build/manifest.json")) {
    throw "Frontend build failed: public/build/manifest.json was not created."
}

Write-Host "Complete frontend production build created locally." -ForegroundColor Green
```

Laravel/PHP source is not compiled by Vite. It is committed with the frontend source in the next section, while Composer installation, migrations, and Laravel cache generation happen on the server.

## 2. Local Windows computer: commit the complete system and push the current branch

This block stages the complete system state, including backend, frontend, migrations, documentation, and deleted files. Review `git status` first and remove any unrelated local work before running it. The compiled `public/build` directory is ignored by default, so it must be force-staged separately.

```powershell
Set-Location "C:\Users\User 03\Desktop\capstone-rfid"
git status --short

# Stage every system change in this checkout.
git add -A

# Include the complete frontend bundle produced by npm run build.
git add -f -A public/build

git diff --cached --check
git diff --cached --name-status

# Stop and unstage any secrets or unrelated files shown above before committing.
$forbiddenFiles = git diff --cached --name-only | Where-Object {
    $_ -match '(^|/)(\.env($|\.)|node_modules/)' -or
    $_ -match '\.(pem|key|p12|pfx)$'
}

if ($forbiddenFiles) {
    $forbiddenFiles | ForEach-Object { Write-Error "Do not commit: $_" }
    throw "Deployment stopped because sensitive or dependency files are staged."
}

$deploymentBranch = git branch --show-current
if (-not $deploymentBranch) {
    throw "Deployment stopped: Git is not currently on a branch."
}

git commit -m "Build complete system for deployment"
git push origin $deploymentBranch

Write-Host "Built locally and pushed to branch: $deploymentBranch" -ForegroundColor Green
```

`git add -A` is intentional here because this workflow deploys the complete system state. Run it only after reviewing or setting aside unrelated work. Never commit `.env`, `node_modules`, credentials, private keys, local database files, or temporary files.

## 3. AWS Lightsail server: pull and deploy the prebuilt frontend

After the local push succeeds, copy and paste this entire block into the Lightsail terminal:

```bash
set -e
cd /var/www/capstone-rfid

# This must match the branch pushed from the local computer.
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

The server block intentionally does not run `npm install` or `npm run build`. Nginx serves the compiled `public/build` files produced on the local computer and committed in section 2. Before running the server block, set `DEPLOY_BRANCH` to the exact branch printed by the local PowerShell block.

## 4. Frontend deployment verification

After deployment:

1. Open the website in a private/incognito window or perform a hard refresh.
2. Confirm that `public/build/manifest.json` on the server has the latest Git commit timestamp.
3. Open the browser developer tools and confirm that the loaded JavaScript filenames match the current entries in `public/build/manifest.json`.
4. Test the updated page and check the browser console plus Laravel/Nginx logs for errors.
