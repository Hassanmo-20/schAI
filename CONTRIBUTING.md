# Contributing to SchAI

## Branches

- `main` is always runnable. Branch from it: `feature/<short-name>`, `fix/<short-name>`, `docs/<short-name>`.
- One concern per branch; keep frontend and backend changes in separate commits when both are touched.

## Conventions

- **Frontend** (`frontend/`): small components, `PascalCase.tsx` files, styles next to features, design tokens in
  `src/styles/variables.css`. HTTP only inside `src/services/` — never `fetch()` in components or pages.
  Mock data lives in `src/data/mockData.ts`, never inside components.
- **Backend** (`backend/`): controllers stay thin; validation in Form Requests, output in API Resources,
  access rules in `TaskPolicy`. New endpoints need a feature test in `backend/tests/Feature/`.
- Run Pint (`php vendor/bin/pint --test` in `backend/`) and `npm run build` in `frontend/` before pushing.

## Testing expectations

- Backend: `php artisan test` must stay green (38 tests). Cover new auth/authz paths and edge cases (0/full stats, duplicates, cross-batch).
- Frontend: no test runner is configured — verify manually (login both roles, CRUD, completion, urgent sorting, 320px mobile) with a clean console.

## Commits & PRs

- Commits: imperative mood, scope prefix — e.g. `backend: enforce batch scope on task show`, `docs: document SQLite option`.
- PRs: describe what changed and how it was verified (commands + results). Link related frontend/backend changes.
- Never commit: `.env` files, `node_modules/`, `vendor/`, `dist/`, `*.log`, screenshots, IDE settings, or real credentials.

## Reviews

PRs need one approval. Reviewers check: batch isolation, no `role`/`batch_id`/`student_id` accepted from clients,
validation on every input, no N+1 queries, docs updated for user-facing changes.
