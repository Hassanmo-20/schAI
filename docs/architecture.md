# Architecture

```
Browser
  └─ React SPA (frontend/) ── services/ (fetch|mock switch) ──┐
                                                              ▼
                    Laravel REST API (backend/) ◄── Sanctum bearer token
                      ├─ routes/api.php (16 routes)
                      ├─ Form Requests (validation) ──► 422 JSON
                      ├─ TaskPolicy via Gate (authorization) ──► 403
                      ├─ Controllers (thin) + API Resources (data-wrapped JSON)
                      └─ Eloquent models ──► MySQL (SQLite for tests)
```

## Why separate frontend and backend

Either side can be deployed, scaled, and replaced independently; the service layer
(`frontend/src/services/*`) is the single integration seam, so the UI never embeds URLs or
auth logic. The API is fully usable without the SPA (curl/Postman).

## Why completion is a separate table

One task is completed by *some* students and not others, so a boolean on `tasks` cannot work.
`task_completions(task_id, student_id)` with `UNIQUE(task_id, student_id)` makes duplicates
impossible at the database level, keeps per-student timestamps, and lets statistics be pure
aggregates (`COUNT`) instead of stored counters that can drift.

## Why Sanctum

Token auth fits an SPA + curl/Postman consumers with zero session/CORS-cookie complexity.
Tokens are hashed in `personal_access_tokens`; logout deletes the current token.

## Why Storage + authorized download route

Client filenames are untrusted metadata; files land under framework-hashed paths on the
configured disk, and bytes only leave the server through a policy-checked download route —
so batch isolation and response codes apply to files exactly like tasks.

## Why public registration can't choose a role

`RegisterRequest` has no `role` rule and the controller forces `role=student`; only validated
data is mass-assigned. Representatives come from seeders/admin provisioning. The frontend mirrors
this (no role picker on the register page).

## Flows

- **Student:** login → `GET /tasks` (own batch, incomplete-first) → dashboard urgency from ISO
  deadlines → `POST /tasks/{id}/complete` → progress/statistics update.
- **Representative:** login → batch overview (aggregates, no N+1 via `withCount`) → create/edit
  (multipart) → deactivate (students stop seeing it, history kept) → per-task statistics.
- **Completion:** token ⇒ student id ⇒ `firstOrCreate`-style guarded insert (409 on retry) ⇒
  `DELETE` removes the row (404 when absent).
- **Statistics:** `total = COUNT(students in batch)`, `completed = COUNT(completions)`,
  `remaining = max(0, total-completed)`, `pct = total ? round(completed/total*100) : 0`.
- **Attachments:** validate (type/mime/size/≤5) → `store()` hashed path → DB row →
  authorized stream on download.

Component/layer details: [Frontend](frontend.md) · [Backend](backend.md) · [Database](database.md) ·
[Authentication](authentication.md) · [Roles & Permissions](roles-permissions.md).
