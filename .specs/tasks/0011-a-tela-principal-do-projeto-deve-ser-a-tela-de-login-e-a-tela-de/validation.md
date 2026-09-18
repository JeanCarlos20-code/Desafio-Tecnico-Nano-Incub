# Validation

## Acceptance Criteria

| AC | Spec-defined outcome | Evidence | Result |
| --- | --- | --- | --- |
| AC-001 | Guest `GET /` renders Inertia `User/Login` | `tests/Feature/User/LoginHttpTest.php:24-26` - `assertOk()`; `$page->component('User/Login')` | PASS |
| AC-002 | Authenticated `GET /` redirects to `route('reservations.index')` | `tests/Feature/User/LoginHttpTest.php:35` - `assertRedirect(route('reservations.index'))` | PASS |
| AC-003 | Guest `GET /login` renders Inertia `User/Login` | `tests/Feature/User/LoginHttpTest.php:40-42` - `get(route('login'))`; `$page->component('User/Login')` | PASS |
| AC-004 | Authenticated `GET /login` redirects to reservations | `tests/Feature/User/LoginHttpTest.php:50-52` - `get(route('login'))`; `assertRedirect(route('reservations.index'))` | PASS |
| AC-005 | Login shows `Não tem uma conta?` and `Ir para o cadastro` with `href="/register"` | `resources/js/Pages/User/Login.test.jsx:84-85` - `getByText('Não tem uma conta?')`; `toHaveAttribute('href', '/register')` | PASS |
| AC-006 | Login does not offer password recovery | `resources/js/Pages/User/Login.test.jsx:91-92` - `queryByText(/esqueci/i)` and `/recuper/i` are absent | PASS |
| AC-007 | Named route `login` remains `GET /login` | `tests/Feature/User/LoginHttpTest.php:40` - `get(route('login'))` still renders login; `routes/web.php:16` keeps `name('login')` on `/login` | PASS |
| AC-008 | Valid `POST /login` regenerates the session and redirects to `/reservations` | `tests/Feature/User/LoginHttpTest.php:69` - `assertRedirect(route('reservations.index'))`; `:72` - `assertNotSame($previousSessionId, session()->getId())` | PASS |

Register `Ir para o login` stays on `/login`: `resources/js/Pages/User/Create.test.jsx:97` - `toHaveAttribute('href', '/login')`.

Processing keeps the cadastro link visible: `resources/js/Pages/User/Login.test.jsx:146` - `getByRole('link', { name: 'Ir para o cadastro' })`.

E2E is not applicable: no Playwright project. Covered by Feature HTTP and Vitest page tests.

## Test Results

- Unit PHP: `php artisan test --testsuite=Unit --coverage --min=80` — 26 passed.
- Frontend unit: `npm run test:coverage` — 94 passed (17 in `Login.test.jsx`). Statements 95.52%.
- Integration: `php artisan test --testsuite=Feature --filter=LoginHttpTest` — 18 passed (108 assertions). Added guest/auth `GET /`. Removed `tests/Feature/ExampleTest.php` welcome smoke (spec edge case).
- E2E: not executed; no runner.

Replaced only the Vitest case that forbade a registration link. Existing login form and `POST /login` Feature cases remain.

## Required Gates

Local quick checks (harness re-runs the official gates):

| Gate | Command | Local result |
| ---- | ------- | ------------ |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` | passed |
| frontend | `npm run test:coverage` | passed |
| integration | `php artisan test --testsuite=Feature --filter=LoginHttpTest` | passed |
| lint | `vendor/bin/pint --test` | passed |
| frontend_lint | `npm run lint` | passed |

`composer run build` and `npm run build` were not declared green here. The harness runs those deterministic gates after this phase.

## Review Result

Pending independent harness review. Execute did not review its own diff.

## Final Status

Implementation complete for T1–T3. Guest home is Inertia login. Authenticated home redirects to reservations. Login exposes `Ir para o cadastro` → `/register`. Named `login` stays at `GET /login`. No product commit from Execute.

No extra product files beyond the plan. Local `vendor/` and `.env` in the worktree are environment only (gitignored) so PHPUnit `inferBasePath()` boots this tree.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `php artisan test --testsuite=Unit --coverage --min=80` — exit=0 (required)
- ✅ `npm run test:coverage` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run lint` — exit=0 (required)
- ✅ `composer run build` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
<!-- harness-checks:end -->
