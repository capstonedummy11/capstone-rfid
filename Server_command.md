```bash
cd /var/www/capstone-rfid
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
sudo chown -R ubuntu:www-data storage bootstrap/cache public/build
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
sudo chmod -R g+s storage bootstrap/cache
sudo systemctl restart php8.4-fpm
sudo systemctl restart nginx
```

```bash
cd /var/www/capstone-rfid
git checkout feature/2000-face-detection
git pull --ff-only origin feature/2000-face-detection
sudo chown -R ubuntu:www-data public/build
sudo systemctl restart php8.4-fpm
sudo systemctl restart nginx
```

```bash
cd /var/www/capstone-rfid
git pull --ff-only origin feature/2000-face-detection
sudo chown -R ubuntu:www-data public/build
sudo systemctl restart php8.4-fpm
sudo systemctl restart nginx

```

## Frontend build

This server has only about 416 MiB of RAM. Build the frontend on a local computer or CI server instead of running Vite here.

On the local computer:

```bash
npm install
npm run build
```

Upload the generated `public/build` directory to:

```text
/var/www/capstone-rfid/public/build
```

Do not use `NODE_OPTIONS=--max-old-space-size=2048` on this small server. That only permits Node to use up to 2 GB; it does not add RAM and can cause swapping or an out-of-memory crash.

If a build must be attempted on a Linux server, use the correct shell syntax:

```bash
export NODE_OPTIONS="--max-old-space-size=512"
npm run build
```
