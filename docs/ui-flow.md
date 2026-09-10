# UI Flow

Implemented in `frontend/src/pages/` with routes in `frontend/src/routes/AppRoutes.tsx`
(auth endpoints behind them documented in [API](api.md)).

## Guest
- `/` → redirects to `/dashboard` → `ProtectedRoute` bounces logged-out users to `/login`.
- `/login`: validate email/password → `authService.login()` → role-based landing
  (representative → `/representative`, else saved `from` or `/dashboard`).
- `/register`: student-only signup (name, email, password ≥ 8, confirm match, batch) → auto-login → `/dashboard`.

## Student
- `/dashboard`: greeting + batch + progress ring (live `completed/total`) → urgent panel
  (incomplete, urgency ≠ normal, nearest first, max 4, "See More" → `/tasks`) → deadline
  calendar (Prev/Next/Today, live month) → upcoming list (toggle complete inline) → assistant placeholder.
- `/tasks`: search + status (all/pending/completed/overdue) + type + sort → `TaskList` →
  `TaskCard` (type/urgency badges, remaining time, attachment count, Open details, Complete toggle).
- `/tasks/:id`: full description, remaining time, attachments (image inline w/ broken fallback,
  PDF/file rows, long names truncated w/ full title on hover), student complete/uncomplete button.
- `/profile`: identity + personal completion bar. `/settings`: device prefs, dev role switch, demo reset.

## Representative
- `/representative`: stat cards (total/active/overdue/avg %) + recent activity + "New task".
- `/representative/tasks`: filterable grid; each card has Edit / Statistics / Delete (confirm dialog).
- `/representative/tasks/create`: validated form → success notice → back to list.
- `/representative/tasks/:id/edit`: same form prefilled; ownership never editable.
- `/representative/tasks/:id/statistics`: `38 / 50`, ring %, progress bar, total/completed/remaining,
  submissions-by-day; links to student view and edit page.

## Guards & states
- Logged out → any protected route → `/login` (remembers `from`).
- Student → `/representative*` → `/access-denied`.
- Every data page: skeleton/loading → content | empty ("No urgent tasks 🎉", "No tasks match…") |
  error with retry. Destructive actions always confirm. 401 anywhere → session cleared → `/login`.
