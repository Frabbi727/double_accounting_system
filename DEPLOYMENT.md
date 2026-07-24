# Production Deployment Cheat Sheet

This file contains the terminal commands required to deploy updates to the cPanel production server.

---

## 0. Local Build (Run LOCALLY before pushing to Git)
Since the production server does not have Node/NPM installed, assets must be compiled locally:
```bash
# 1. Run local build
npm run build

# 2. Commit the changes in public/build and push to GitHub
git add public/build
git commit -m "build: compile assets for production"
git push origin production
```

---

## 1. Standard Update (Run every time)
Run this block whenever you push new changes to GitHub and want to update the production site:
```bash
# 1. Navigate to the project directory
cd /home/techreal/repositories/double_accounting_system

# 2. Pull the latest code from GitHub
git pull --rebase origin production

# 3. Clear old caches (config, routes, views, events)
php artisan optimize:clear

# 4. Cache configurations and routes again for production
php artisan optimize
```

---

## 2. Composer Packages Update
If you added or updated any composer packages locally (modified `composer.json`), run this command after pulling the code:
```bash
composer install --no-dev --optimize-autoloader
```

---

## 3. Database Migrations Update
If you added new database migrations (modified database schemas), run this command after pulling the code:
```bash
php artisan migrate --force
```

---

## 4. Manual Cache Clearance (If needed)
If changes do not reflect on the website due to persistence caching, run:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```
