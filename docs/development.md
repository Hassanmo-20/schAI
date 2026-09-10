# Development Guide

## Everyday loop

1. Branch from `main`: `feature/…`, `fix/…`, `docs/…` (one concern per branch).
2. Backend change? Add/adjust a feature test first when the behavior is access-related.
3. Implement (frontend: component + service method; backend: request → policy → controller → resource).
4. Verify: `php artisan test` (backend) · `npm run build` + manual click-through (frontend).
5. Migration changed? Test `migrate:fresh --seed` on a scratch DB, never on shared data.
6. Review your own diff, then open a PR (see [CONTRIBUTING](../CONTRIBUTING.md)).

## Where new files go

- Frontend page → `frontend/src/pages/<area>/` + route in `src/routes/AppRoutes.tsx`.
- Shared UI → `frontend/src/components/common/`; domain UI → matching folder.
- Frontend API access → `frontend/src/services/*Service.ts` (nowhere else).
- API endpoint → `backend/routes/api.php`, controller in `Api/`, Form Request, Resource,
  Policy rule, test in `backend/tests/Feature/`.
- Tables → migration + model relations + factory + seeder coverage.

## Useful commands

```bash
# backend/
php artisan migrate                # apply pending migrations
php artisan migrate:fresh --seed   # DESTROYS all data, rebuilds + seeds
php artisan db:seed                # demo data only (firstOrCreate-based)
php artisan test                   # full suite
php artisan route:list --path=api  # verify routing
php vendor/bin/pint --test         # style check (pint without --test fixes)
# frontend/
npm run dev | npm run build | npm run typecheck | npm run preview
```

## Mock vs real API

Frontend `VITE_USE_MOCK_DATA=true` runs fully offline (seeded `mockData.ts` + localStorage).
Set it to `false` with `VITE_API_BASE_URL=http://localhost:8000/api` to develop against Laravel.
Field mapping (snake_case, `data` envelopes, lowercase `type`) lives in `src/services/*` —
update it there if the API shape ever changes.
