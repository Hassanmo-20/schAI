# Backend Guide (`backend/`)

Laravel API (framework ^13.17) · PHP ^8.3 · Sanctum ^4.3 · Eloquent · MySQL (SQLite in tests).

## Layer responsibilities

| Layer | Lives in | Job | Examples |
|---|---|---|---|
| Routes | `routes/api.php` | URL → controller only (16 routes) | `POST /tasks/{task}/complete` |
| Form Requests | `app/Http/Requests/` | Shape validation → `422` JSON | `RegisterRequest` (no `role` rule — escalation impossible), `StoreTaskRequest` (type normalized, attachments ≤5, mime/size checked) |
| Policies | `app/Policies/TaskPolicy.php` (+ Gate registration) | **The security boundary**: batch match, role gates, inactive hidden from students | `complete` = student + own batch + active |
| Controllers | `app/Http/Controllers/Api/` | Thin orchestration, transactions, status codes | `TaskController` (scoped listing, server-derived ownership), `TaskStatisticsController` (DB aggregates) |
| Resources | `app/Http/Resources/` | Output shapes only | `TaskResource` (`data`-wrapped, ISO-8601, rep `statistics` when aggregates loaded) |
| Models/Enums | `app/Models/`, `app/Enums/` | Relations, casts, scopes | `Role`/`TaskType` backed enums, `active`/`forBatch` scopes |
| Storage | configured disk | Hashed upload paths, authorized streaming | `downloadAttachment` (binding mismatch → 404, cross-batch → 403) |

No service classes exist — logic lives in the layers above, which is sufficient at this size.

## Request lifecycle

`auth:sanctum` → Form Request (422) → `Gate::authorize` (403) → controller transaction →
Resource JSON. API exceptions render JSON (`bootstrap/app.php`); stack traces never leak
(`APP_DEBUG=false` in production).

## Performance notes

- Rep listings use `withCount('completions')` + one batch-student `COUNT` (no N+1); attachments/creator eager-loaded.
- Listing is SQL-ordered (incomplete-first, deadline) and paginated (`per_page` default 20, max 100).
- Indexes on `tasks(batch_id,is_active,deadline)`, `users.batch_id`, completions FKs + unique pair.

## Database & tests

Migrations/factories/seeders under `database/` (see [Database](database.md));
43 feature tests under `tests/Feature/` (`php artisan test`, sqlite `:memory:`) covering auth,
authz, CRUD, completion, statistics (0/partial/full/empty), attachments, and cross-batch security —
see [Testing](testing.md).
