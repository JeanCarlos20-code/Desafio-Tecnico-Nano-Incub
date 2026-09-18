---
harness:
  commits:
    - "feat(user): add session login and logout"
    - "feat(resources): add login session service and panel logout"
    - "feat(routes): add login logout and protect panel routes"
    - "feat(bootstrap): redirect guests to login"
    - "test(user): cover login HTTP and login screen"
    - "test(resources): cover session service and layout logout"
    - "test(reservation): mock layout logout form on reservations stub"
    - "chore(readme): document login and public register policy"
    - "chore(docs): add login screen specification"
    - "chore(specs): record login task artifacts"
  tests:
    unit:
      - "Login screen shows specified copy and required email/password fields without recovery or register links"
      - "Login screen disables Entrar, shows Entrando..., and ignores further submits while processing"
      - "Login screen shows Laravel field errors with aria-invalid and aria-describedby"
      - "Login screen shows generic credentials error in an aria-live region and focuses the password field"
      - "Login screen shows unexpected-failure banner Não foi possível entrar. Tente novamente."
      - "session.js posts the Inertia form to /login and /logout"
      - "BrandPanel login footer shows Mais produtividade para o seu time."
      - "AppLayout Sair posts logout"
      - "Existing CreateUser unit tests remain green"
    integration:
      - "Guest GET /login returns 200 and Inertia User/Login"
      - "Authenticated GET /login redirects to /reservations"
      - "Valid POST /login authenticates, regenerates the session id, and redirects to /reservations or the intended URL"
      - "Missing email returns Informe seu e-mail. without authenticating"
      - "Missing password returns Informe sua senha. without authenticating"
      - "Malformed email returns Informe um endereço de e-mail válido. without authenticating"
      - "Unknown email, wrong password, or a soft-deleted user returns E-mail ou senha inválidos. on credentials without authenticating"
      - "Email Ada@Example.com authenticates the stored lowercase user"
      - "Password is absent from old input after a failed login"
      - "A sixth failed attempt for the same email and IP is rejected by throttle without authenticating"
      - "Unauthenticated GET /reservations redirects to /login"
      - "POST /logout logs out, invalidates the session, and redirects to /login"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project exists in the repo; Feature HTTP and Vitest cover login. Do not bootstrap Playwright."
  gates:
    - id: frontend
      command: "npm test"
      required: true
    - id: unit
      command: "php artisan test --testsuite=Unit"
      required: true
    - id: integration
      command: "php artisan test --testsuite=Feature"
      required: true
    - id: lint
      command: "vendor/bin/pint --test"
      required: true
    - id: build
      command: "npm run build"
      required: true
---

# Implementation Plan

## Summary

Implement RF01/RF02 with native Laravel session auth inside the User module. Add `LoginController` + `LoginRequest` (`Auth::attempt`, session regenerate, `RateLimiter` 5/email+IP), guest `/login` and `POST /logout`, and `auth` on `/reservations`. Render Inertia `User/Login` from `docs/screens/screen-login.md`, post through `Services/session.js`, reuse register field components, parameterize BrandPanel footer, and add AppLayout `Sair`. Cover UI with Vitest and HTTP with Feature tests. Do not install Breeze/Fortify. Do not add password recovery, JWT, roles, or Playwright.

**Design (inline, no `design.md`):** Controller stays thin: Form Request validates and throttles → `Auth::attempt` → `session()->regenerate()` → `redirect()->intended('reservations.index')`. Failure throws/returns `credentials` = `E-mail ou senha inválidos.` with `onlyInput('email')`. `useForm` owns data, `processing`, and `errors`. `login(form)` in `session.js` is the only POST URL. Logout: `Auth::logout()`, `invalidate()`, `regenerateToken()`, redirect `login`.

## Affected Components

- `app` — `LoginController`, `LoginRequest`, `routes/web.php`, `bootstrap/app.php`, `Pages/User/Login.jsx`, `BrandPanel`, `Services/session.js`, `Layouts/AppLayout.jsx`, `README.md`, `tests/Feature/User/LoginHttpTest.php`, colocated Vitest files. Existing `CreateUser` / `CreateUserHttpTest` stay green.

## Tasks

See **Task Breakdown**. Execute T1 → T2 → T3 sequentially.

## Planned Tests

### Unit

- Login screen shows specified copy and required email/password fields without recovery or register links
- Login screen disables Entrar, shows Entrando..., and ignores further submits while processing
- Login screen shows Laravel field errors with aria-invalid and aria-describedby
- Login screen shows generic credentials error in an aria-live region and focuses the password field
- Login screen shows unexpected-failure banner Não foi possível entrar. Tente novamente.
- session.js posts the Inertia form to /login and /logout
- BrandPanel login footer shows Mais produtividade para o seu time.
- AppLayout Sair posts logout
- Existing CreateUser unit tests remain green

### Integration

- Guest GET /login returns 200 and Inertia User/Login
- Authenticated GET /login redirects to /reservations
- Valid POST /login authenticates, regenerates the session id, and redirects to /reservations or the intended URL
- Missing email returns Informe seu e-mail. without authenticating
- Missing password returns Informe sua senha. without authenticating
- Malformed email returns Informe um endereço de e-mail válido. without authenticating
- Unknown email, wrong password, or a soft-deleted user returns E-mail ou senha inválidos. on credentials without authenticating
- Email Ada@Example.com authenticates the stored lowercase user
- Password is absent from old input after a failed login
- A sixth failed attempt for the same email and IP is rejected by throttle without authenticating
- Unauthenticated GET /reservations redirects to /login
- POST /logout logs out, invalidates the session, and redirects to /login

### E2E

- not applicable: No Playwright project exists in the repo; Feature HTTP and Vitest cover login. Do not bootstrap Playwright.

## Required Gates

| Gate | Command | Required |
| ---- | ------- | -------- |
| frontend | `npm test` (`vitest run`) | yes |
| unit | `php artisan test --testsuite=Unit` | yes |
| integration | `php artisan test --testsuite=Feature` | yes |
| lint | `vendor/bin/pint --test` | yes |
| build | `npm run build` | yes |

Do not require `npx playwright test` (no Playwright project yet).

## Definition of Done

- All AC-001…AC-024 have tests or a documented README check (AC-023).
- Gates above pass. No silent test deletions.
- `/login` matches screen-login behaviors; `/register` still creates and signs in a user; `/reservations` requires a session.
- `harness.commits` still matches dirty-path grouping (`user` / `resources` / `routes` / `bootstrap` / `readme` / `docs` / `specs`).
- No product commit from this Plan phase.

---

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

---

**Design**: inline in Summary (MVP; no `design.md`)
**Status**: Complete

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `docs/reviews/review-tests.md`, `harness/stack.yml`, `phpunit.xml`. Vitest is installed; Playwright is not.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| React login page / BrandPanel footer / AppLayout logout | unit | Copy, fields, loading, field errors, credentials banner, general banner, mocked POST `/login` and `/logout`; listed UI edge cases | `resources/js/Pages/User/Login.test.jsx`, `BrandPanel.test.jsx`, `Layouts/AppLayout.test.jsx` | `npm test` |
| `Services/session.js` | unit | Asserts `form.post('/login')` and logout POST; password reset on error | `resources/js/Services/session.test.js` | `npm test` |
| HTTP login/logout + Form Request + middleware | integration | Happy path + validation + invalid/soft-deleted credentials + throttle + session regenerate + guest/auth redirects + logout | `tests/Feature/User/LoginHttpTest.php` | `php artisan test --testsuite=Feature` |
| CreateUser use case | unit | Existing tests remain; no new rules | `tests/Unit/User/CreateUserTest.php` | `php artisan test --testsuite=Unit` |
| README / screen markdown | none | Docs only | `README.md`, `docs/screens/screen-login.md` | build / lint |
| Playwright e2e | none (deferred) | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After React unit tasks | `npm test` && `php artisan test --testsuite=Unit` |
| Full | After HTTP/Feature tasks | `npm test` && `php artisan test --testsuite=Feature` && `php artisan test --testsuite=Unit` |
| Build | Docs, phase end | `npm run build` && `vendor/bin/pint --test` && `php artisan test` && `npm test` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: HTTP contract

```
T1
```

### Phase 2: Screen

```
T2
```

### Phase 3: Documentation

```
T3
```

```
T1 -> T2 -> T3
```

---

## Task Breakdown

### Phase 1: HTTP contract

#### T1: Add Laravel session login, logout, and route guards

**What**: Add `LoginController` (`create`/`store`/`destroy`) and `LoginRequest` (required email max 255, required password, trim+lowercase email, Portuguese `messages()`, `RateLimiter` 5/email+IP). `store` uses `Auth::attempt`, regenerates the session, `redirect()->intended(route('reservations.index'))`. Failures use `credentials` = `E-mail ou senha inválidos.` and `onlyInput('email')`. Wire `GET/POST /login` (`guest`), `POST /logout` (`auth`), `auth` on `/reservations`, `guest` on register aliases. Set `redirectGuestsTo` / `redirectUsersTo` in `bootstrap/app.php`. Add `LoginHttpTest.php`.
**Where**: `app/Modules/User/Infra/Http/Controllers/LoginController.php`
**Depends on**: None
**Reuses**: `User` Eloquent model; `CreateUserHttpTest` style (`withoutVite`, `AssertableInertia`, `RefreshDatabase`); Laravel `Auth`, `RateLimiter`
**Requirement**: AC-001, AC-007, AC-008, AC-009, AC-010, AC-011, AC-013, AC-017, AC-018, AC-019, AC-020, AC-021, AC-024

**Tools**:

- MCP: `context7` (Laravel `Auth::attempt`, session regenerate, `RateLimiter`, `redirectGuestsTo`)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Guest `GET /login` renders `User/Login`; authenticated guest-middleware redirects to reservations
- [x] Valid POST authenticates, session id changes, redirect reservations or intended URL
- [x] Missing/malformed fields, invalid/soft-deleted credentials, and throttle paths do not authenticate
- [x] Email is matched trimmed/lowercased; password not in old input
- [x] Unauthenticated `GET /reservations` redirects to login; `POST /logout` clears the session
- [x] Existing register Feature tests still pass
- [x] Gate check passes: `npm test` && `php artisan test --testsuite=Feature` && `php artisan test --testsuite=Unit`
- [x] Test count: no silent deletions

**Tests**: integration
**Gate**: full

**Commit**: `feat(user): add session login and logout`; `feat(routes): add login logout and protect panel routes`; `feat(bootstrap): redirect guests to login`; `test(user): cover login HTTP and login screen`

---

### Phase 2: Screen

#### T2: Build the login Inertia screen and session service

**What**: Add `Pages/User/Login.jsx` split-card (login copy, no recovery/register link, `autoFocus` on email when empty). Reuse `IconTextField` and `PasswordField`. Add `footer` prop to `BrandPanel` (login: `Mais produtividade para o seu time.`). Submit via `login(form)` in `Services/session.js` (`POST /login`, `onError` reset password, credentials banner + password focus, `onHttpException`/`onNetworkError` general banner). Add AppLayout `Sair` that posts `/logout` through the same service. Vitest for Login, session, BrandPanel footer, AppLayout logout. Mock `@inertiajs/react`.
**Where**: `resources/js/Pages/User/Login.jsx`
**Depends on**: T1
**Reuses**: `Create.jsx` card/spinner patterns; `users.js` Inertia option shape; do not wrap login in `AppLayout`
**Requirement**: AC-002, AC-003, AC-004, AC-005, AC-006, AC-012, AC-014, AC-015, AC-016, AC-022

**Tools**:

- MCP: `context7` (Inertia React `useForm` `processing` `errors` `reset`)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Empty, focus, validation-error, invalid-credentials, processing, and unexpected-failure states match the screen spec
- [x] Labels associated; `aria-required`, `aria-invalid`, `aria-describedby`, `aria-live="polite"` on banners
- [x] No recovery or register link; primary label `Entrando...` while processing
- [x] `Login.test.jsx` and `session.test.js` cover those UI ACs (mock Inertia; no Laravel)
- [x] Gate check passes: `npm test` && `php artisan test --testsuite=Unit`
- [x] Test count: no silent deletions of PHPUnit tests

**Tests**: unit
**Gate**: quick

**Commit**: `feat(user): add session login and logout` (Login.jsx / BrandPanel with T1 user files); `feat(resources): add login session service and panel logout`; remaining `test(user)` / `test(resources)` files

---

### Phase 3: Documentation

#### T3: Document login and remaining public register

**What**: Replace the README sentence that says login is unimplemented. State that administrators sign in at `/login`, panel routes require a session, and `/register` stays publicly reachable as a challenge convenience (not recommended in production). Mention the existing seeder user `test@example.com` / `password` only as a local way to try login. Keep `docs/screens/screen-login.md` in the tree.
**Where**: `README.md`
**Depends on**: T2
**Reuses**: current README register-policy paragraph
**Requirement**: AC-023

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Policy paragraph is present and accurate
- [x] Gate check passes: `npm run build` && `vendor/bin/pint --test` && `php artisan test` && `npm test`

**Tests**: none
**Gate**: build

**Commit**: `chore(readme): document login and public register policy`; `chore(docs): add login screen specification`; `chore(specs): record login task artifacts`

---

## Phase Execution Map

```
Phase 1 → Phase 2 → Phase 3

Phase 1:  T1
Phase 2:  T2
Phase 3:  T3
```

```
T1 -> T2 -> T3
```

Execution is strictly sequential. Three tasks fit one Execute batch (no sub-agent split).

**How phase-based execution works:** one worker runs T1–T3 in order (implement → gate → mark done). After the last task, a fresh Verifier runs (author ≠ verifier).

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Add Laravel session login, logout, and route guards | one HTTP auth contract | OK cohesive (single contract) |
| T2: Build the login Inertia screen and session service | one page + service + shell logout + unit tests | OK cohesive (one screen) |
| T3: Document login and remaining public register | one file | Granular |

Granularity check: T1/T2 are multi-file but one deliverable each. Do not split the controller from `LoginHttpTest.php`. Do not split the screen from its Vitest file.

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (start) | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | T2 | T2 -> T3 | Match |

Dependencies point backward only.

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | HTTP login/logout + Form Request + middleware | integration | integration | OK |
| T2 | React page / session service / AppLayout | unit (highest UI type) | unit | OK |
| T3 | README / screen markdown | none | none | OK |

T2 also touches `session.js`; POST URL is asserted in `session.test.js` and the page test. Feature tests in T1 cover controller/request/routes. Re-run `npm test` on T2.

---

## Considered Approaches

| Option | Trade-off | Decision |
| ------ | --------- | -------- |
| A. Install Breeze/Fortify | Conflicts with custom User module | Rejected |
| B. Application `AuthenticateUser` use case | Illuminate in Application | Rejected |
| C. Infra login + `User/Login` + `session.js` + Feature/Vitest | Matches ADRs, tree, and existing register screen | **Selected** |
| D. Add Playwright now | Correct long-term; no config yet | Deferred |

## Selected Approach

Option C, as in `spec.md`. Commit grouping follows `group_commits.py`: User HTTP + `Pages/User` share `user`; `Services/session.js` and `Layouts/AppLayout.jsx` share `resources`; `routes/web.php` is `routes`; `bootstrap/app.php` is `bootstrap`.
