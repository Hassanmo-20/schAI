# Frontend Guide (`frontend/`)

React 18 · Vite 6 · React Router 6 · TypeScript ~5.6 · plain organized CSS with tokens.
No UI framework, no test runner (verification is `npm run build` + manual QA).

## Architecture

- `src/routes/` — `AppRoutes.tsx` declares every route; `ProtectedRoute` redirects guests to
  `/login` (remembering `from`); `RoleProtectedRoute(['representative'])` sends students to
  `/access-denied`. Guards are UX only — the backend re-enforces everything.
- `src/pages/` — route-level composition: `auth/` (Login, Register), `student/` (Dashboard,
  Tasks, TaskDetails, Profile, Settings), `representative/` (RepDashboard, RepTasks, Create, Edit,
  Statistics), plus `NotFound`/`AccessDenied`.
- `src/components/` — presentation only, no `fetch`: `common/` (Button/PageHeader, Badge,
  Field/Input/Select/Textarea, Loading/Skeleton/Empty/Error/Modal/Confirm, ProgressBar/Ring/StatCard),
  `layout/` (`AppLayout`: sidebar + mobile drawer), `tasks/` (TaskCard, TaskList, TaskFilters,
  TaskForm, AttachmentPreview, DeadlineCalendar, AcademicAssistant placeholder), `statistics/`.
- `src/services/` — the **only HTTP layer**: `apiClient` (base URL from env, bearer token,
  centralized 401 handling, multipart-aware), `authService`, `taskService`, `statisticsService`,
  `batchService`, and `apiMappers` (the single Laravel↔UI translation point: `data` unwrapping,
  snake_case→camelCase, lowercase→display task types, multipart builders). Flip
  `VITE_USE_MOCK_DATA=false` to call Laravel with zero component changes.
- `src/context/` — `AuthContext` (`user/role/token/batch`, `login/logout/register/hasRole`) and
  `TaskContext` (task list, optimistic `toggleComplete` with rollback). Nothing reads raw
  `localStorage` outside services.
- `src/data/mockData.ts` — centralized demo users/tasks (tonight/tomorrow/2-day/7-day/overdue,
  varied completion). `src/hooks/useTaskFilters.ts` (memoized search/status/type/sort),
  `src/utils/` (urgency calc, urgent-list, progress), `src/types/`, `src/styles/`.

## Key behaviors

- **Urgency** (`utils/taskHelpers.ts`): incomplete + deadline-based (overdue / ≤24h / ≤72h / ≤7d /
  normal), nearest-first, max 4 on the dashboard with "See More" → `/tasks`.
- **Progress/statistics**: `completed/total*100`, guarded to `0%` on empty sets.
- **States**: skeletons/spinners while loading, retryable errors, intentional empty copy everywhere.
- **Responsive**: drawer nav ≤768px, auto-fill card grids, ellipsis/wrapping titles and filenames.
- **Attachments**: real file uploads (multipart) with client-side type/size/count checks mirroring
  the API rules; representatives can delete individual attachments.
- **Redirects**: post-login `from` targets pass through `utils/safeRedirect.ts`, which rejects
  external/protocol-relative paths (open-redirect guard).
- **Dev-only tools**: the role switcher and demo-task reset on `/settings` render only when
  `VITE_USE_MOCK_DATA=true` — against the real API the role comes from the token.

## Where to change things

| Task | Location |
|---|---|
| New page | `src/pages/<area>/` + route in `AppRoutes.tsx` + link in `AppLayout.tsx` |
| New component | `src/components/<domain>/` (`common/` if shared) |
| New API call | Method in matching `src/services/*` (map Laravel `data`-wrapped/snake_case shapes here) |
| New task feature | Component + `useTasks()` + service method + mock entry |
| New route | `AppRoutes.tsx` with the right guard |
| Styling | Tokens in `src/styles/variables.css`, feature CSS next to components |

## Environment

`frontend/.env` from `.env.example`: `VITE_API_BASE_URL` (default `http://localhost:8000/api`),
`VITE_USE_MOCK_DATA` (`true` = local mock). Restart `npm run dev` after edits.
Scripts: `dev` · `build` (`tsc -b` + Vite) · `typecheck` · `preview`.
