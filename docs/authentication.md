# Authentication

## Backend (the security boundary): Sanctum bearer tokens

- **Register** (`POST /api/auth/register`): name, valid unique email, password ≥8 with confirmation,
  existing `batch_id`. Role is forced to `student` — there is no `role` input by design.
- **Login** (`POST /api/auth/login`): verifies hash, creates a token (`createToken('api')`), returns
  `{ message, token, user }`. Wrong credentials → `401`. Both auth routes are throttled (10/min).
- **Token use**: `Authorization: Bearer <token>` on every protected call (`auth:sanctum` group).
  Tokens are SHA-hashed in `personal_access_tokens`; an unknown/invalid token → `401`.
- **Logout** (`POST /api/auth/logout`): deletes the current token; it stops working immediately.
- **Me** (`GET /api/auth/me`): current user resource (password hash hidden at model + resource level).

## Frontend (UX conveniences, not security)

- `authService` (`frontend/src/services/`) owns login/register/logout and stores token+user;
  `AuthContext`/`useAuth()` exposes `user/role/token/batch`, `isAuthenticated()`, `hasRole()`.
  Components never touch `localStorage` keys directly. Mock mode (`VITE_USE_MOCK_DATA=true`)
  simulates the same interface locally.
- `ProtectedRoute` sends guests to `/login` (preserving the target path);
  `RoleProtectedRoute(['representative'])` sends students to `/access-denied`.
- Centralized 401 handling in `apiClient`: clears the session and returns to login.

## The distinction that matters

Frontend guards only hide UI. Every one of these is re-decided server-side by `TaskPolicy`
(batch match, role, active-state): task reads, create/update/deactivate, completion
(token ⇒ student id, no `student_id` parameter exists), statistics, and attachment downloads.
A test suite (`backend/tests/Feature/`) proves the boundary: cross-batch reads/writes,
student management attempts, escalation attempts, and invalid tokens are all rejected.
