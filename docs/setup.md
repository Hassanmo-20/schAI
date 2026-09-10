# Setup Guide

Covers a full local setup: backend + database + frontend. Estimated time: 10–15 minutes
with MySQL running, less with SQLite.

## 1. Prerequisites

Git · Node.js 18+ (`node -v`, `npm -v`) · PHP 8.3+ (`php -v`) with
`pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo` (`php -m`) ·
Composer 2 (`composer -V`) · MySQL 8 or MariaDB (`mysql --version`) — unless using SQLite below.

## 2. Backend

```bash
cd backend
composer install
copy .env.example .env        # macOS/Linux: cp .env.example .env
php artisan key:generate
```

### Database options

**Option A — MySQL (default).** Create the database, point `.env` at it, migrate + seed:

```bash
mysql -u root -p -e "CREATE DATABASE schai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# .env: DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE=schai, DB_USERNAME=root, DB_PASSWORD=…
php artisan migrate --seed
```

**Option B — SQLite (no server).** Good for trying the app; not the documented production path:

```bash
# .env:
DB_CONNECTION=sqlite
DB_DATABASE=C:\full\path\to\backend\database\database.sqlite   # any absolute path
# macOS/Linux: DB_DATABASE=/abs/path/backend/database/database.sqlite
php artisan migrate --seed
```

Seeding creates Batch CS-2026-A (50 students + rep), Batch CS-2026-B, and 7 tasks with
realistic completions (38/50, 20/50, 45/50, 5/50, 48/50, 1/50). Demo passwords: `password`.

### Serve

```bash
php artisan serve              # http://localhost:8000
php artisan route:list --path=api   # sanity check: 17 routes
```

## 3. Frontend

```bash
cd ../frontend
npm install
copy .env.example .env        # macOS/Linux: cp .env.example .env
npm run dev
```

`.env` values:

| Variable | Default | Notes |
|---|---|---|
| `VITE_API_BASE_URL` | `http://localhost:8000/api` | Must match the backend address. Restart `dev` after changes. |
| `VITE_USE_MOCK_DATA` | `true` | `false` switches to the real Laravel API. |

Open the printed URL and sign in (`student@schai.test` / `password`).

## 4. Verify the whole loop

1. Backend: `php artisan test` → 66 passed.
2. Frontend: `npm run build` → clean build.
3. Browser: log in as student (dashboard + complete a task), then as representative
   (create a task, open its statistics). No console errors.
4. API spot-check: [Manual test sequence](api.md#manual-test-sequence-curl).

## 5. CORS

The API only accepts cross-origin calls on `api/*` from origins in backend `FRONTEND_URL`
(comma-separated). Running Vite on a custom port? Add it there and restart `php artisan serve`.
Never use `*` in production.

## 6. Attachments & storage

Uploads use the configured filesystem disk (`FILESYSTEM_DISK=local` by default) under hashed paths
and are streamed through `GET /api/tasks/{task}/attachments/{attachment}` with policy checks.
`php artisan storage:link` is **not** required. Files are not covered by seeders — upload one via
`POST /api/tasks` to exercise the flow.
