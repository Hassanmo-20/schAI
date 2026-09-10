# Testing Guide

## Backend — PHPUnit, 43 tests, all passing

```bash
cd backend
php artisan test                 # sqlite :memory:, no MySQL needed
```

| Area | File | Covers |
|---|---|---|
| Authentication | `tests/Feature/AuthTest.php` | register, duplicate email, escalation blocked, validation, login, wrong password, profile, logout + token revocation, guest/invalid-token 401s |
| Authorization | `tests/Feature/TaskAuthorizationTest.php` | student CRUD blocked, rep allowed, cross-batch blocked (read/write/stats/complete), list isolation, student stats blocked |
| Task CRUD | `tests/Feature/TaskCrudTest.php` | server-derived ownership, validation, incomplete-first ordering, pagination, ownership-immutable update, deactivate-hides-from-students, ISO-8601 |
| Completion | `tests/Feature/TaskCompletionTest.php` | complete/uncomplete round-trip, `409` duplicate, race-window insert still yields `409` (never 500), 404 uncomplete, token-bound student, inactive blocked |
| Statistics | `tests/Feature/TaskStatisticsTest.php` | 0 / partial / 50-of-50 / empty-batch (no division error), rep-list aggregates |
| Attachments | `tests/Feature/TaskAttachmentTest.php` | image+PDF upload, hashed paths, bad extension, oversize, authorized + cross-batch download, delete (row + file), student delete blocked, cross-task IDOR guard |
| Batches | `tests/Feature/BatchTest.php` | public listing for registration, no user data exposed, id round-trips into a successful registration |

Conventions: `RefreshDatabase`, `Sanctum::actingAs` for authenticated calls, real bearer tokens where
revocation is asserted (with `forgetGuards()` to force re-resolution), `Storage::fake` + real PNG/PDF
byte fixtures (no GD needed).

## Frontend — no runner configured

Verification is explicit and manual:

1. `npm run build` (includes `tsc -b`) must pass with zero errors.
2. Click through as **student** (`student@schai.test`) and **representative**
   (`representative@schai.test`, password `password`): login, dashboard, tasks + filters,
   details, complete/uncomplete, create/edit/delete + statistics, logout.
3. Check urgent sorting (overdue → tonight → tomorrow → days), `0/50`–`50/50` stats, empty states.
4. Resize to 320px mobile: drawer nav, no horizontal overflow. Keep the browser console clean.

Do not claim frontend test coverage that does not exist; if you add Vitest/Playwright later,
document it here.
