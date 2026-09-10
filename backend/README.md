# SchAI Backend (Laravel API)

REST API for the Academic Task Hub. Serves the React frontend in [`../frontend`](../frontend);
full contract in [`../docs/api.md`](../docs/api.md).

## Stack

Laravel (`laravel/framework ^13.17`, installed v13.31) · PHP ^8.3 · Sanctum ^4.3 (bearer tokens) ·
Eloquent · MySQL (dev/prod) · SQLite (tests) · PHPUnit 12.

## Setup

```bash
composer install
copy .env.example .env        # macOS/Linux: cp .env.example .env
php artisan key:generate
```

Database — pick one (details: [`../docs/setup.md`](../docs/setup.md)):

```bash
# MySQL (default .env): create empty database `schai`, set DB_* in .env, then:
php artisan migrate --seed
# SQLite (no server needed):
#   set DB_CONNECTION=sqlite and DB_DATABASE=/abs/path/database.sqlite in .env, then:
php artisan migrate --seed
```

Run:

```bash
php artisan serve              # http://localhost:8000, API at /api/*
php artisan test               # 66 feature tests, sqlite :memory:
php artisan route:list --path=api
```

Seeded dev accounts (password `password`): `student@schai.test`, `representative@schai.test`, `rep.b@schai.test`.

## Layout

- `routes/api.php` — every endpoint (auth, tasks, completion, statistics, attachment download).
- `app/Http/Controllers/Api/` — `AuthController`, `TaskController`, `TaskCompletionController`, `TaskStatisticsController`.
- `app/Http/Requests/` — validation (`Auth/`, `Tasks/`). No `role`/`batch_id`/`student_id` accepted from clients.
- `app/Http/Resources/` — JSON shapes (`data`-wrapped singles, paginated collections).
- `app/Policies/TaskPolicy.php` — the authorization boundary (batch isolation, role gates).
- `app/Models/`, `app/Enums/` (`Role`, `TaskType`) — domain.
- `database/migrations/`, `factories/`, `seeders/SchAIDemoSeeder.php`.
- `tests/Feature/` — auth, authz, CRUD, completion, statistics, attachments.

Attachments are stored on the configured filesystem disk under hashed paths and streamed through an
authorized route — raw paths are never exposed, so `php artisan storage:link` is **not** required.

## Useful commands

```bash
php artisan migrate:fresh --seed   # rebuild dev DB (DESTROYS data)
php artisan db:seed                # re-run demo seeder (idempotent-ish via firstOrCreate)
php vendor/bin/pint --test         # code style check
```
