# Database

MySQL 8 in dev/prod (SQLite `:memory:` for tests via `backend/phpunit.xml`).
Migrate: `php artisan migrate --seed` · rebuild: `php artisan migrate:fresh --seed` (destroys data).

## Tables

**`batches`** — `id` PK · `name` UNIQUE · `department` · `academic_year` · timestamps.
A batch is the isolation boundary: users and tasks belong to exactly one.

**`users`** (Laravel default +) — `role` (`student`, default) · `batch_id` FK → `batches` NULL ON DELETE,
indexed (`INDEX users.batch_id`; `email` UNIQUE kept). Passwords stored hashed (`hashed` cast);
hashes are hidden from serialization and never appear in API output.

**`tasks`** — `title(180)` · `description` TEXT nullable · `type(20)` indexed (lowercase enum) ·
`deadline` DATETIME indexed · `batch_id` FK → `batches` CASCADE · `created_by` FK → `users` CASCADE ·
`is_active` BOOL default true, indexed · composite `INDEX(batch_id, is_active, deadline)` serving the
student listing order. Deactivation flips `is_active`; rows and history are kept.

**`task_completions`** — `task_id` FK CASCADE · `student_id` FK → `users` CASCADE ·
`completed_at` · timestamps · **`UNIQUE(task_id, student_id)`** + both columns indexed.
The unique pair is what makes duplicate completion *impossible* rather than merely rejected:
the API returns `409`, and even a race lands on a constraint violation instead of a double row.
No `completed` flag exists on `tasks` — per-student state lives only here.

**`task_attachments`** — `task_id` FK CASCADE · `original_name` (display metadata only) ·
`file_path(512)` (opaque hashed storage path, never client input) · `mime_type` · `file_size` ·
`INDEX(task_id)`.

**`personal_access_tokens`** (Sanctum) — hashed API tokens; logout deletes the current row.

## Relationships

```
Batch 1—* User        (User belongsTo Batch; Batch hasMany users/students)
Batch 1—* Task        (Task belongsTo Batch; scoped listings filter here)
User  1—* Task        (as creator via created_by)
Task  1—* TaskAttachment / TaskCompletion (CASCADE on task delete)
User  1—* TaskCompletion (as student via student_id)
```

## Seed data

`SchAIDemoSeeder`: Batch CS-2026-A (50 students incl. `student@schai.test`, rep
`representative@schai.test`) + 6 tasks with 38/20/45/5/48/1 completions (tonight → 30-day horizons,
incl. one overdue); Batch CS-2026-B with `rep.b@schai.test` + one task for isolation checks.
All demo passwords: `password`.

The demo student's own completion state is set per task (`demo_done`) rather than falling out of
the "first N students" slice — otherwise the demo login would show a 100%-complete dashboard with
no urgent tasks. They currently have 1 completed, 1 overdue, and 4 pending, while the batch-level
figures above stay exact.
