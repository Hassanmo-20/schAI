# SchAI Frontend (React + Vite)

Student/representative web app for the Academic Task Hub. Talks to the Laravel API in
[`../backend`](../backend); full contract in [`../docs/api.md`](../docs/api.md).

## Stack

React 18 · Vite 6 · React Router 6 · TypeScript ~5.6 · organized plain CSS with design tokens
(`src/styles/variables.css`). No UI framework, no test runner (see below).

## Setup

```bash
npm install
copy .env.example .env        # macOS/Linux: cp .env.example .env
npm run dev                    # prints the local URL
```

Env (`frontend/.env`):

| Variable | Default | Meaning |
|---|---|---|
| `VITE_API_BASE_URL` | `http://localhost:8000/api` | Laravel API base |
| `VITE_USE_MOCK_DATA` | `true` | `true` = in-browser mock (no backend needed); `false` = real API |

Restart `npm run dev` after changing `.env`. Commands: `npm run dev` · `npm run build` (`tsc -b` + Vite) ·
`npm run preview` · `npm run typecheck`.

## Layout

- `src/routes/` — `AppRoutes`, `ProtectedRoute` (login gate), `RoleProtectedRoute` (rep gate).
- `src/pages/` — `auth/` (login/register), `student/` (dashboard, tasks, details, profile, settings),
  `representative/` (dashboard, manage, create, edit, statistics).
- `src/components/` — `common/` (Button, Badge, forms, loading/empty/error/modal, progress),
  `layout/` (sidebar + mobile drawer), `tasks/`, `statistics/`.
- `src/services/` — **the only place that talks HTTP**: `apiClient`, `authService`, `taskService`,
  `statisticsService`. Flip `VITE_USE_MOCK_DATA=false` to go live without touching components.
- `src/context/` — `AuthContext` (`useAuth`), `TaskContext` (`useTasks`).
- `src/data/mockData.ts` — centralized demo tasks/users. `src/hooks/`, `src/utils/`, `src/types/`.

## Adding things

- New page → `src/pages/<area>/`, register route + guard in `AppRoutes.tsx`, add sidebar link in `AppLayout.tsx`.
- New UI piece → `src/components/<domain>/`; shared primitives go in `common/`.
- New API call → method in the matching `src/services/*Service.ts` (keeps the Laravel mapping in one place).
- New filter/sort → extend `src/hooks/useTaskFilters.ts` + `TaskFilterState` in `src/types/`.

## Verification (no test runner configured)

`npm run build` must pass, then click through: login as student and representative, task CRUD,
complete/uncomplete, urgent sorting, statistics pages, and 320px mobile — with zero browser console errors.
