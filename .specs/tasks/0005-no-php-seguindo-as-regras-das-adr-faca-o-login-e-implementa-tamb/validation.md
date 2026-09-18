# Validation

Worker execute for task 0005. Native Laravel session login ships in the User module with Inertia `User/Login`. No commit, merge, push, or deploy from this phase.

## Acceptance Criteria

| AC | Spec-defined outcome | `file:line` + assertion | Covered? |
| -- | --------------------- | ----------------------- | -------- |
| AC-001 | Guest `GET /login` is 200 and Inertia `User/Login` | `tests/Feature/User/LoginHttpTest.php:25-26` `assertOk()`; `assertInertia(...component('User/Login'))` | Yes |
| AC-002 | Heading `Acesse sua conta`, supporting text, primary `Entrar` | `resources/js/Pages/User/Login.test.jsx:46-48` `getByRole('heading', { name: 'Acesse sua conta' })`; supporting text; `getByRole('button', { name: 'Entrar' })` | Yes |
| AC-003 | Required E-mail and Senha; placeholder `seu@email.com`; autocomplete `email` / `current-password`; `aria-required` | `Login.test.jsx:57-64` placeholder, autocomplete, `aria-required`, password `type=password` and no placeholder | Yes |
| AC-004 | Brand panel wordmark, copy, footer `Mais produtividade para o seu time.` | `Login.test.jsx:70-75`; `BrandPanel.test.jsx:27` `getByText('Mais produtividade para o seu time.')` | Yes |
| AC-005 | No password recovery or registration link | `Login.test.jsx:81-86` `queryByRole('link')` is null; recovery/register copy absent | Yes |
| AC-006 | Inertia POST `/login` through `session.js` | `session.test.js:18-19` `form.post` called with `'/login'`; `Login.test.jsx:100-101` same | Yes |
| AC-007 | Valid credentials authenticate, regenerate session id, redirect `/reservations` or intended URL | `LoginHttpTest.php:53-56` `assertRedirect(reservations.index)`, `assertAuthenticatedAs`, `assertNotSame` session id; `:67-75` intended URL from reservations | Yes |
| AC-008 | Missing email → `Informe seu e-mail.` without authenticating | `LoginHttpTest.php:85-87` `assertSessionHasErrors(['email' => 'Informe seu e-mail.'])`; `assertGuest()` | Yes |
| AC-009 | Missing password → `Informe sua senha.` without authenticating | `LoginHttpTest.php:97-99` `assertSessionHasErrors(['password' => 'Informe sua senha.'])`; `assertGuest()` | Yes |
| AC-010 | Malformed email → `Informe um endereço de e-mail válido.` without authenticating | `LoginHttpTest.php:110-112` `assertSessionHasErrors(['email' => 'Informe um endereço de e-mail válido.'])`; `assertGuest()` | Yes |
| AC-011 | Unknown email, wrong password, or soft-deleted user → `E-mail ou senha inválidos.` on `credentials` | `LoginHttpTest.php:125-127` data provider; `assertSessionHasErrors(['credentials' => 'E-mail ou senha inválidos.'])`; `assertGuest()` | Yes |
| AC-012 | While processing: disable `Entrar`, label `Entrando...`, loading indicator, ignore further submits | `Login.test.jsx:137-139` disabled + svg; `:148` `form.post` not called | Yes |
| AC-013 | Keep email, clear password, no raw password in old input | `LoginHttpTest.php:161-163` password absent from `_old_input` and response; email kept; `Login.test.jsx:185` email value; `:216` `form.reset('password')` | Yes |
| AC-014 | Credentials banner `aria-live="polite"` and focus password | `Login.test.jsx:183` `aria-live`; `:198` password `toHaveFocus()`; email not marked invalid `:184` | Yes |
| AC-015 | Field errors with `aria-invalid` and `aria-describedby` | `Login.test.jsx:162-168` email/password error ids and aria | Yes |
| AC-016 | Unexpected failure banner `Não foi possível entrar. Tente novamente.` | `Login.test.jsx:250` banner `aria-live="polite"`; form still visible `:251-252` | Yes |
| AC-017 | Unauthenticated `GET /reservations` redirects to `/login` | `LoginHttpTest.php:199` `assertRedirect(route('login'))` | Yes |
| AC-018 | Authenticated `GET /login` redirects to `/reservations` | `LoginHttpTest.php:35` `assertRedirect(route('reservations.index'))` | Yes |
| AC-019 | `POST /logout` logs out, invalidates session, regenerates CSRF, redirects `/login` | `LoginHttpTest.php:213-217` redirect login, `assertGuest()`, session id and token changed | Yes |
| AC-020 | Sixth failed attempt for same email+IP is throttled without authenticating (even with correct password) | `LoginHttpTest.php:188-191` `assertSessionHasErrors('credentials')`; `assertGuest()` | Yes |
| AC-021 | Email trimmed and lowercased before authentication | `LoginHttpTest.php:142-144` `Ada@Example.com` authenticates stored `ada@example.com` | Yes |
| AC-022 | Wide: two-column card; narrow: one column, hero hidden, full-width controls, no horizontal scroll | `Login.test.jsx:261-265` `lg:grid-cols-`, hero `hidden`/`lg:flex`, button `w-full`, `overflow-x-hidden` | Yes |
| AC-023 | README documents `/login` and public `/register` as challenge convenience | `README.md:3-5` login + session requirement; public register not recommended in production | Yes |
| AC-024 | Raw password not flashed or returned | `LoginHttpTest.php:161-163` password missing from old input and response body | Yes |

Edge cases: extra name fields ignored (`LoginHttpTest.php:233-237`); throttle with correct password (`:188-191`); keyboard password toggle (`Login.test.jsx:129-130`).

## Test Results

Punctual tests from `harness.tests` (unit + integration). E2E not applicable; Playwright was not bootstrapped.

**Unit (Vitest `npm test`)**: 57 passed / 0 failed. New: Login screen (copy, fields, loading, field errors, credentials banner, unexpected-failure banner), `session.js` POST `/login` and `/logout`, BrandPanel login footer, AppLayout `Sair`. Existing CreateUser React tests remain green (17 cases).

**Integration (PHPUnit Feature)**: 38 Feature tests passed, including 16 new `LoginHttpTest` cases covering the integration list in `tasks.md`. Existing `CreateUserHttpTest` (15) still passed after `auth` on `/reservations`.

**PHP Unit**: 2 passed. Existing `CreateUserTest` unchanged.

No tests weakened, skipped, or deleted. PHPUnit assertion count 238 (was 4 unit + previous Feature; Login added 16 tests). Vitest 57 (previous files plus Login 16, session 4, BrandPanel +1, AppLayout +1).

## Required Gates

Worker-run results (harness re-runs these deterministically; this is not the official final gate):

| Gate | Command | Worker result |
| ---- | ------- | ------------- |
| frontend | `npm test` | 57 passed |
| unit | `php artisan test --testsuite=Unit` | 2 passed |
| integration | `php artisan test --testsuite=Feature` | 38 passed (suite total with unit: 40) |
| lint | `vendor/bin/pint --test` | passed |
| build | `npm run build` | passed (vite client build) |
| e2e | `npx playwright test` | not run (no Playwright project; `tests_not_applicable.e2e`) |

## Review Result

Not run. Execute does not own review. Independent Verifier / harness-review runs after this phase.

## Final Status

EXECUTE complete. Ready for harness complete-phase checks and human/review gates.

Extra files required by real dependency (not opportunistic):

- `resources/js/Pages/User/Login.jsx` during T1: Inertia Feature `assertInertia` fails unless the page file exists (`inertia.testing.ensure_pages_exist`).
- `resources/js/Pages/User/Components/IconTextField.jsx`: optional `autoFocus` so empty-email login can focus the field per `screen-login.md`.
- `resources/js/Pages/Reservation/Index.test.jsx`: AppLayout now calls Inertia `useForm` for `Sair`; Index renders AppLayout, so the test must mock `@inertiajs/react`.

Leftover risks (out of scope, documented): public `/register` remains a challenge convenience; Playwright E2E still deferred; pixel-identical mock dimensions were not required.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `npm test` — exit=0 (required)
- ✅ `php artisan test --testsuite=Unit` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
- ✅ `php artisan test` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
<!-- harness-checks:end -->
