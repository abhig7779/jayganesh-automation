# Jay Ganesh CRM (Laravel app)

Plan: `../CRM-PLAN.md` · Architecture: `../ARCHITECTURE.md`

## Run locally (Mac)
PHP 8.4 + Composer live in `~/.config/herd-lite/bin` (Laravel's standalone build).

```bash
export PATH=$HOME/.config/herd-lite/bin:$PATH
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed     # local demo logins: <role>@crm.test (local only)
php artisan serve --port=8077
./vendor/bin/pest                     # tests
```

## First production setup (MilesWeb, over SSH)
```bash
php artisan migrate --force
php artisan crm:setup          # roles, main branch, default settings (safe to re-run)
php artisan crm:create-owner   # asks for name, mobile and password
```
Composer is pinned to PHP 8.2 (`config.platform.php`) to match the server.
