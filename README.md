# SchAI — Academic Task Hub

SchAI is a university academic task-management platform. **Batch representatives** post assignments,
quizzes, midterms, exams, and projects for their batch; **students** track deadlines, spot urgent work,
mark tasks complete, and monitor their progress.

The repository holds two separate applications that talk over a REST API:

| App | Stack | Folder |
|---|---|---|
| Frontend | React 18 · Vite 6 · React Router 6 · TypeScript | [`frontend/`](frontend/) |
| Backend | Laravel · PHP 8.3+ · Sanctum · Eloquent · MySQL | [`backend/`](backend/) |

Deep dives: [Architecture](docs/architecture.md) · [Setup](docs/setup.md) · [API](docs/api.md) ·
[Frontend](docs/frontend.md) · [Backend](docs/backend.md) · [Database](docs/database.md) ·
[Authentication](docs/authentication.md) · [Roles & Permissions](docs/roles-permissions.md) ·
[Testing](docs/testing.md) · [Development](docs/development.md) · [UI flow](docs/ui-flow.md)

## Features

**Students** — batch-scoped task list with search, status/type filters and sorting; dashboard with
live progress ring, urgent-task panel (overdue → due tonight → tomorrow → this week) and a deadline
calendar; task details with attachments; one-click complete/uncomplete; profile.

**Representatives** — everything above plus a batch dashboard (total/active/overdue/average
completion), task create & edit with file attachments, deactivate (history preserved), and per-task
completion statistics (`38 / 50 · 76%`).

**Both** — token authentication, role-based routing, loading/error/empty states throughout, and a
responsive layout from 320 px to desktop with a mobile navigation drawer.

## Architecture

```
React SPA (frontend/)
  └─ src/services/  ← the only HTTP layer; apiMappers translates Laravel ⇄ UI shapes
       │  Bearer token
       ▼
Laravel REST API (backend/)
  routes/api.php → Form Request (422) → TaskPolicy via Gate (403)
                 → Controller → API Resource → Eloquent → MySQL
```

Two independent apps. The frontend can run fully offline on mock data
(`VITE_USE_MOCK_DATA=true`), so the UI is usable before the API is available.
Frontend guards are UX only — **the backend is the security boundary**.

## Project structure

```
├── frontend/          React + Vite SPA
│   └── src/           components · pages · routes · services · context · hooks · utils · styles
├── backend/           Laravel API
│   ├── app/           Enums · Http (Controllers/Requests/Resources) · Models · Policies
│   ├── database/      migrations · factories · seeders
│   ├── routes/api.php 16 endpoints
│   └── tests/Feature/ 43 feature tests
└── docs/              architecture · setup · api · frontend · backend · database
                       authentication · roles-permissions · testing · development · ui-flow
                       + schai-postman-collection.json
```

## Prerequisites

| Tool | Why | Check |
|---|---|---|
| Git | clone the repo | `git --version` |
| Node.js 18+ and npm | frontend | `node -v` · `npm -v` |
| PHP 8.3+ with `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo` | backend | `php -v` · `php -m` |
| Composer 2 | backend dependencies | `composer -V` |
| MySQL 8 (or MariaDB) | backend database | `mysql --version` |

> No MySQL handy? The backend test suite runs on SQLite (`:memory:`, zero setup), and you can point
> the dev database at SQLite too — see [Setup](docs/setup.md#database-options). The documented default is MySQL.

## Quick Start

```bash
# 1. Clone and enter
git clone <repo-url> schai
cd schai

# 2. Backend: install deps, configure, key, database, seed
cd backend
composer install
copy .env.example .env        # macOS/Linux: cp .env.example .env
php artisan key:generate
# create an empty MySQL database named `schai` (or see SQLite option in docs/setup.md)
php artisan migrate --seed
php artisan serve              # → http://localhost:8000

# 3. Frontend: install deps, configure, run (new terminal)
cd ../frontend
npm install
copy .env.example .env        # macOS/Linux: cp .env.example .env
npm run dev                    # → URL printed in the terminal
```

Open the frontend URL, sign in with a demo account below, and the app talks to
`VITE_API_BASE_URL` (`http://localhost:8000/api` by default).

## Demo accounts (development only)

Seeded by `backend/database/seeders/SchAIDemoSeeder.php`, password **`password`** for all:

| Role | Email | Batch |
|---|---|---|
| Student | `student@schai.test` | Batch CS-2026-A (50 students, realistic completions) |
| Representative | `representative@schai.test` | Batch CS-2026-A |
| Representative (2nd batch) | `rep.b@schai.test` | Batch CS-2026-B (isolation checks) |

The frontend also runs in mock mode (`VITE_USE_MOCK_DATA=true`) without any backend.

## Running things

| Task | Command | Where |
|---|---|---|
| Frontend dev server | `npm run dev` | `frontend/` |
| Frontend production build | `npm run build` | `frontend/` |
| Frontend typecheck | `npm run typecheck` | `frontend/` |
| Backend server | `php artisan serve` | `backend/` |
| Backend tests | `php artisan test` | `backend/` |
| List API routes | `php artisan route:list --path=api` | `backend/` |
| Fresh DB + seed (**deletes data**) | `php artisan migrate:fresh --seed` | `backend/` |

## Environment variables

- Frontend (`frontend/.env`, from [`frontend/.env.example`](frontend/.env.example)):
  `VITE_API_BASE_URL` (default `http://localhost:8000/api`), `VITE_USE_MOCK_DATA` (`true` = local mock, `false` = real API).
- Backend (`backend/.env`, from [`backend/.env.example`](backend/.env.example)):
  `APP_KEY`, `APP_URL`, `FRONTEND_URL` (comma-separated CORS origins), `DB_*`, `FILESYSTEM_DISK`.
- Never commit `.env` files. They are gitignored at the root.

## Where to start changing code

- New student/rep page → `frontend/src/pages/`, route in `frontend/src/routes/AppRoutes.tsx` (+ guard).
- New reusable UI → `frontend/src/components/common/`; task UI → `frontend/src/components/tasks/`.
- New API call from frontend → add a method in `frontend/src/services/` (never `fetch()` in components).
- New API endpoint → route in `backend/routes/api.php` + controller in `backend/app/Http/Controllers/Api/` + Form Request + Resource + Policy rule + feature test in `backend/tests/Feature/`.

## API overview

16 REST endpoints under `/api` — full reference with request/response examples in
[docs/api.md](docs/api.md); importable [Postman collection](docs/schai-postman-collection.json).

| Area | Endpoints |
|---|---|
| Auth | `POST /auth/register` · `POST /auth/login` · `POST /auth/logout` · `GET /auth/me` |
| Batches | `GET /batches` (public — registration needs valid batch ids) |
| Tasks | `GET/POST /tasks` · `GET/PUT/DELETE /tasks/{task}` |
| Completion | `POST /tasks/{task}/complete` · `DELETE /tasks/{task}/complete` |
| Statistics | `GET /tasks/{task}/statistics` |
| Attachments | `GET`/`DELETE /tasks/{task}/attachments/{attachment}` |
| Health | `GET /health` |

Conventions: single resources return `{ "data": … }`, collections add `meta` pagination;
auth returns flat `{ message, token, user }`. Errors use standard codes
(`401` · `403` · `404` · `409` duplicate completion · `422` validation · `429` rate limited).

## Authentication & roles

Laravel Sanctum bearer tokens. Public registration always creates a **student** — the role field
does not exist on that endpoint, so it cannot be escalated. Representatives are provisioned by
seeder/administrator.

| Action | Student | Representative |
|---|---|---|
| View own-batch tasks | Yes | Yes (also sees deactivated) |
| Create / edit / deactivate tasks | No | Yes, own batch only |
| Complete / uncomplete own task | Yes | No |
| View task statistics | No | Yes, own batch only |
| Delete an attachment | No | Yes, own batch |
| Access another batch | No | No |

Full matrix: [docs/roles-permissions.md](docs/roles-permissions.md).

## File attachments

Representatives upload up to 5 files per task (JPG, PNG, GIF, WebP, PDF; ≤5 MB each), validated on
both sides. Files are stored on Laravel's filesystem disk under framework-hashed paths — client
filenames are display metadata only — and are served through an authorization-checked download
route, so `php artisan storage:link` is **not** required.

## Security notes

- The backend re-enforces every rule: batch isolation, role checks, and ownership are decided by
  `TaskPolicy`, never by the client. Frontend route guards are UX only.
- `batch_id`, `created_by` and `student_id` are always derived from the authenticated token; they
  are ignored if sent by a client.
- `UNIQUE(task_id, student_id)` makes duplicate completions impossible at the database level.
- Passwords are hashed and never serialized. Rate limits: 10/min on auth, 120/min authenticated.
- Never commit `.env` — only `.env.example` files (placeholders) are tracked. Set
  `APP_DEBUG=false` and a strict `FRONTEND_URL` allow-list in production.
- Known advisory: react-router 6 open-redirect (GHSA-wrjc-x8rr-h8h6). The reachable path is
  mitigated in `frontend/src/utils/safeRedirect.ts`; the upstream fix requires a v7 major upgrade.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `npm install` fails | Use Node 18+, clear cache (`npm cache clean --force`), delete `frontend/node_modules` and retry |
| `composer install` fails | Need PHP 8.3+ with required extensions (`php -m`); update Composer (`composer self-update`) |
| `No application encryption key` | `php artisan key:generate` in `backend/` |
| `SQLSTATE … Connection refused` | MySQL not running / wrong `DB_*` in `backend/.env`; or switch to SQLite per [Setup](docs/setup.md#database-options) |
| Migrations fail halfway | `php artisan migrate:status`, fix DB, then `php artisan migrate`; nuclear option: `migrate:fresh --seed` (destroys data) |
| Attachments 404 after upload | Files are streamed by the API (no `storage:link` needed); 404 means the file row/path is missing — check disk config |
| CORS error in browser | Add the frontend origin to `FRONTEND_URL` in `backend/.env`, restart `php artisan serve` |
| Frontend can't reach API | Check `VITE_API_BASE_URL` in `frontend/.env` (restart `npm run dev` after changing it); API must be on `:8000` |
| 401 Unauthorized | Log in again — token missing/expired/revoked; check `Authorization: Bearer <token>` header |
| 403 Forbidden | Correct login, wrong role/batch — see [Roles & Permissions](docs/roles-permissions.md) |
| Port already in use | `php artisan serve --port=8001` (then update `VITE_API_BASE_URL`) or stop the other process |

## Contributing & license

See [CONTRIBUTING.md](CONTRIBUTING.md). License: [MIT](LICENSE).
