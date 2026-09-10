# SchAI API Reference

Base URL: `http://localhost:8000/api` (frontend: `VITE_API_BASE_URL`).
Verify the live table anytime: `php artisan route:list --path=api` in `backend/`.

Conventions (verified against `backend/routes/api.php`):

- Send `Accept: application/json`; authenticated calls add `Authorization: Bearer <token>`.
- Single resources render as `{ "data": { … } }`; collections as `{ "data": […], "meta": {…} }`.
- Auth responses are the exception: flat `{ "message", "token", "user" }`.
- Task `type` is canonical lowercase (`assignment|quiz|midterm|exam|project|other`);
  display casing (`Assignment`) is accepted on input and normalized server-side.
- All timestamps are ISO-8601. Batch scope comes from the token — no endpoint accepts
  `batch_id` or `student_id`.
- Status codes: `200` ok · `201` created · `401` unauthenticated · `403` unauthorized ·
  `404` not found · `409` duplicate completion · `422` validation error · `429` rate limited.
- Rate limits: auth endpoints 10 req/min per IP; all authenticated endpoints 120 req/min per user.

## Authentication

### `POST /api/auth/register` — public, throttled (10/min)

```json
{ "name": "Alex Mercer", "email": "alex@example.com", "password": "password123",
  "password_confirmation": "password123", "batch_id": 1 }
```

→ `201`:

```json
{ "message": "Registration successful", "token": "1|…",
  "user": { "id": 51, "name": "Alex Mercer", "email": "alex@example.com",
            "role": "student", "batch_id": 1, "batch": "Batch CS-2026-A" } }
```

`role` is forced to `student` server-side and cannot be escalated. Errors: `422` (duplicate/invalid fields).

### `POST /api/auth/login` — public, throttled (10/min)

```json
{ "email": "student@schai.test", "password": "password" }
```

→ `200` same shape as register (`"message": "Login successful"`).
→ `401` `{ "message": "The provided credentials are incorrect." }`

### `POST /api/auth/logout` — auth

Revokes the current token. → `200` `{ "message": "Logged out" }`

### `GET /api/auth/me` — auth

→ `200` `{ "data": { "id","name","email","role","batch_id","batch" } }` (never a password hash).

## Batches

### `GET /api/batches` — public, throttled (30/min)

Registration requires a valid `batch_id`, so the picker needs to discover them before an account
exists. Exposes descriptive fields only — no membership, counts, or user data.

→ `200` `{ "data": [ { "id": 1, "name": "Batch CS-2026-A", "department": "Computer Science", "academic_year": "2026" } ] }`

## Tasks

### `GET /api/tasks` — auth

Students get active tasks of their batch; representatives get the whole batch (incl. deactivated),
each row with a `statistics` object. SQL-ordered incomplete-first, then nearest deadline.
`?page=&per_page=` (default 20, max 100).

```bash
curl -H "Accept: application/json" -H "Authorization: Bearer <token>" \
  "http://localhost:8000/api/tasks?per_page=20"
```

→ `200` (representative row shown):

```json
{ "data": [ { "id": 1, "title": "Database Assignment 2", "description": "…",
  "type": "assignment", "deadline": "2026-09-12T23:59:00+00:00", "batch_id": 1,
  "is_active": true, "created_by": 2, "created_by_name": "Sarah Connor",
  "is_completed": false, "completed_at": null,
  "attachments": [ { "id": 5, "name": "rubric.pdf", "url": "http://localhost:8000/api/tasks/1/attachments/5",
                     "mime_type": "application/pdf", "file_size": 349184 } ],
  "statistics": { "total_students": 50, "completed_students": 38,
                  "remaining_students": 12, "completion_percentage": 76 } } ],
  "meta": { "current_page": 1, "per_page": 20, "total": 6 } }
```

### `GET /api/tasks/{task}` — auth, same batch (students: active tasks only)

→ `200` `{ "data": { …task… } }` · `403` other batch (or inactive, for students) · `404` unknown id.

### `POST /api/tasks` — representative, own batch, `multipart/form-data`

Fields: `title*` (≤180) · `description` · `type*` · `deadline*` (must be future) ·
`attachments[]` (≤5 files; `jpg/jpeg/png/gif/webp/pdf`; ≤5 MB each).
`batch_id`/`created_by` are derived from the token and silently ignored if sent.

→ `201` `{ "data": { … } }` · `403` student · `422` validation.

### `PUT /api/tasks/{task}` — representative, own batch

Updatable: `title, description, type, deadline, is_active` (+ appended `attachments[]`).
Ownership/completion fields are ignored. → `200` · `403` · `422`.

### `DELETE /api/tasks/{task}` — representative, own batch

Deactivates (`is_active=false`); row + completion history preserved, students stop seeing it.
→ `200` `{ "message": "Task deactivated" }` · `403`.

## Completion (students)

### `POST /api/tasks/{task}/complete` — student, own batch, active task

Records completion for the token owner (no `student_id` parameter exists).
→ `200` `{ "message": "Task marked as complete", "is_completed": true, "completed_at": "…" }`
· `409` already completed · `403` other batch / inactive / representative.

### `DELETE /api/tasks/{task}/complete` — student

Removes the caller's record.
→ `200` `{ "message": "Completion removed", "is_completed": false, "completed_at": null }`
· `404` nothing to remove.

## Statistics (representatives)

### `GET /api/tasks/{task}/statistics` — representative, own batch

Computed from database rows; an empty batch reports zeros (no division-by-zero).

→ `200`:

```json
{ "task_id": 20, "task_title": "Database Assignment 2", "total_students": 50,
  "completed_students": 38, "remaining_students": 12, "completion_percentage": 76 }
```

→ `403` student or other batch.

## Attachments

### `GET /api/tasks/{task}/attachments/{attachment}` — auth, same batch

Streams the file under its original name. Raw storage paths are never exposed; uploads live under
framework-hashed paths on the configured disk. Mismatched task/attachment or missing file → `404`;
cross-batch → `403`.

### `DELETE /api/tasks/{task}/attachments/{attachment}` — representative, own batch

Removes the attachment row and its stored file (updates only ever append files, so this is the
only way to remove one). → `200` `{ "message": "Attachment deleted" }` ·
`403` student/other batch · `404` attachment belongs to a different task.

## Health

### `GET /api/health` — public

→ `200` `{ "status": "ok" | "degraded", "timestamp": "…" }`.
Deliberately minimal: no environment, app name, or driver details are disclosed.

## Frontend integration

`frontend/src/services/apiMappers.ts` is the single translation point between this API and the
UI model: it unwraps `data` envelopes, converts snake_case → camelCase, maps lowercase `type`
→ display casing, derives attachment kind from `mime_type`, and builds multipart payloads.
Completion is create/delete (not a toggle), which `taskService.toggleComplete()` handles.
Set `VITE_USE_MOCK_DATA=false` to point the SPA at this API.

## Error shapes

```json
// 422
{ "message": "The title field is required. (and 1 more error)",
  "errors": { "title": ["The title field is required."], "type": ["…"] } }
// 401 / 403 / 404
{ "message": "Unauthenticated." }
```

## Manual test sequence (curl)

1. `POST /api/auth/login` as `student@schai.test` / `password` → copy `token`.
2. `GET /api/auth/me` with `Authorization: Bearer <token>` → `200` + your user.
3. `GET /api/tasks?per_page=5` → batch-scoped list with `is_completed` flags.
4. `POST /api/tasks/<id>/complete` → `is_completed: true`; repeat → `409`; `DELETE` it → uncompleted.
5. Login as `representative@schai.test` → `POST /api/tasks` (title/type/future deadline) → `201`;
   `GET /api/tasks/<id>/statistics` → counts + percentage.
6. Cross-checks: student `POST /api/tasks` → `403`; logged-out `GET /api/tasks` → `401`;
   other-batch id → `403`.

```bash
BASE=http://localhost:8000/api
TOKEN=<paste-token-here>
curl -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" $BASE/auth/me
curl -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" "$BASE/tasks?per_page=5"
```

A ready-made Postman collection mirrors these calls: [`schai-postman-collection.json`](schai-postman-collection.json).
