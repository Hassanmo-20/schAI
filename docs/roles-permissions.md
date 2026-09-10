# Roles & Permissions (as implemented in `TaskPolicy` + routes)

Roles: `student` (default at registration) · `representative` (seeded/provisioned only).
Batch rule: every check additionally requires the resource's `batch_id` to equal the user's —
cross-batch access is denied everywhere, including downloads and statistics.

| Action | Student | Representative |
|---|---|---|
| View own-batch active tasks | Yes | Yes (also sees deactivated) |
| View other batch's tasks | No (403) | No (403) |
| Create task | No (403) | Yes, own batch only |
| Edit task | No (403) | Yes, own batch only |
| Delete / deactivate task | No (403) | Yes, own batch only (deactivate; history kept) |
| Complete own task | Yes (own batch, active task) | No (403 — completion is student-only) |
| Uncomplete own task | Yes (404 when nothing recorded) | No |
| Alter another student's completion | Impossible (no such parameter; id from token) | No (cannot write completions at all) |
| View task statistics | No (403) | Yes, own batch only |
| Change own role to representative | Impossible (no `role` input) | — |
| Download attachments | Yes, own batch active tasks | Yes, own batch |
| Delete an attachment | No (403) | Yes, own batch |

Notes: representatives complete nothing (policy `complete` requires `student`); students never see
inactive tasks (list filter + `view` denial); duplicate completion is rejected (`409`) and barred by
`UNIQUE(task_id, student_id)`. Frontend route guards mirror this table for UX, but the table above
describes server enforcement.
