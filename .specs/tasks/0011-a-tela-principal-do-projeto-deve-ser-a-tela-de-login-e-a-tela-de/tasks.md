---
harness:
  commits:
    - "feat(routes): serve login as the application home"
    - "feat(user): add register navigation on the login screen"
    - "test(user): cover home login and register navigation"
    - "test(exampletest.php): drop laravel welcome home smoke test"
    - "chore(docs): document login home and register CTA"
    - "chore(specs): record login-home plan artifacts"
  tests:
    unit:
      - "User/Login shows divider Não tem uma conta? and link Ir para o cadastro with href /register"
      - "User/Login still does not render a password-recovery control"
    integration:
      - "Guest GET / renders Inertia User/Login"
      - "Authenticated GET / redirects to /reservations"
      - "Guest GET /login still renders Inertia User/Login"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in the repo. stack.yml lists npx playwright test but it is not runnable. Home-as-login and login-to-register navigation are covered by Feature HTTP tests and Vitest page tests. Do not bootstrap Playwright in this task."
  gates:
    - id: unit
      command: "php artisan test --testsuite=Unit --coverage --min=80"
      required: true
    - id: frontend
      command: "npm run test:coverage"
      required: true
    - id: integration
      command: "php artisan test --testsuite=Feature"
      required: true
    - id: lint
      command: "vendor/bin/pint --test"
      required: true
    - id: frontend_lint
      command: "npm run lint"
      required: true
    - id: php_build
      command: "composer run build"
      required: true
    - id: frontend_build
      command: "npm run build"
      required: true
---

# Implementation Plan

## Summary

Stop serving Blade `welcome` at `GET /`. Register `GET /` on the existing `guest` group with `LoginController::create` so guests see Inertia `User/Login` at the app home and authenticated users are redirected to `/reservations`. Keep named `GET /login`. On `Login.jsx`, mirror the create-user secondary navigation: divider `Não tem uma conta?` and Inertia `Link` `Ir para o cadastro` → `/register`. Replace the Vitest case that forbids a registration link. Cover `GET /` in `LoginHttpTest`. Remove `tests/Feature/ExampleTest.php` if it still assumes a non-Inertia home. Update `docs/screens/screen-login.md`. Do not touch `POST /login`, use cases, or the register page.

**Design (inline, no `design.md`):** `Route::get('/', [LoginController::class, 'create'])->name('home');` inside the current `guest` group, above `/login`. `Login.jsx` imports `Link` from `@inertiajs/react` like `Create.jsx`. Cadastro block sits under `Entrar`, same divider/button classes as register’s login block. `Login.test.jsx` mocks `Link` as an `<a>`.

## Affected Components

- `app` — `routes/web.php`, `resources/js/Pages/User/Login.jsx`, `resources/js/Pages/User/Login.test.jsx`, `tests/Feature/User/LoginHttpTest.php`, `tests/Feature/ExampleTest.php` (remove), `docs/screens/screen-login.md`. `LoginController` stays as-is.

## Tasks

Execute T1 → T3 as in **Task Breakdown**.

## Planned Tests

### Unit

- User/Login shows `Não tem uma conta?` and `Ir para o cadastro` with `href="/register"`
- User/Login still has no password-recovery control
- Existing login Vitest cases stay (fields, `POST /login`, loading, errors, credentials banner)

### Integration

- Guest `GET /` renders Inertia `User/Login`
- Authenticated `GET /` redirects to `/reservations`
- Guest `GET /login` still renders `User/Login`
- Existing `POST /login` Feature cases stay

### E2E

Not applicable — no Playwright project. See `harness.tests_not_applicable.e2e`.

## Required Gates

| Gate | Command | Required |
| ---- | ------- | -------- |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` | yes |
| frontend | `npm run test:coverage` | yes |
| integration | `php artisan test --testsuite=Feature` | yes |
| lint | `vendor/bin/pint --test` | yes |
| frontend_lint | `npm run lint` | yes |
| php_build | `composer run build` | yes |
| frontend_build | `npm run build` | yes |

Do not run `npx playwright test`.

## Definition of Done

- AC-001…AC-008 have tests at the levels above or remain existing Feature contracts (AC-008).
- `GET /` is login for guests; cadastro is reachable from login; `/login` still exists.
- Gates above pass. No silent test deletions except replacing the “no registration link” Vitest case and the welcome `ExampleTest`.
- `harness.commits` matches dirty-path grouping (`routes` / `user` / `example` / `docs` / `specs`).
- No product commit from this Plan phase.

---

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

---

**Design**: inline in Summary (MVP; no `design.md`)
**Status**: Draft

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `docs/reviews/review-tests.md`, `harness/stack.yml`, `phpunit.xml`, `package.json`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| React login page | unit | Cadastro divider/link href `/register`; no password recovery; existing form cases remain | `resources/js/Pages/User/Login.test.jsx` | `npm run test:coverage` |
| Home/login HTTP + guest middleware | integration | Guest `GET /` Inertia `User/Login`; auth `GET /` → `/reservations`; guest `GET /login` unchanged; MySQL 8 Feature suite | `tests/Feature/User/LoginHttpTest.php` | `php artisan test --testsuite=Feature` |
| AuthenticateUser / LoginRequest | none | Unchanged; existing unit tests remain | `tests/Unit/User/*` | `php artisan test --testsuite=Unit --coverage --min=80` |
| Screen doc | none | Contract text only | `docs/screens/screen-login.md` | build / lint |
| Playwright e2e | none | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After React unit tasks | `npm run test:coverage` && `php artisan test --testsuite=Unit --coverage --min=80` |
| Full | After HTTP/Feature or page+HTTP tasks | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run test:coverage` |
| Build | Phase end / lint | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Home login and cadastro link

```
T1 -> T2 -> T3
```

---

## Task Breakdown

### Phase 1: Home login and cadastro link

#### T1: Serve login at GET /

**What**: Move `GET /` into the `guest` middleware group and point it at `LoginController::create`. Keep `GET /login` named `login`. Optionally name `/` `home`. Add Feature tests: guest `GET /` is Inertia `User/Login`; authenticated `GET /` redirects to `reservations.index`. Delete `tests/Feature/ExampleTest.php` (welcome 200 without `withoutVite()`). Do not change `POST /login`.
**Where**: `routes/web.php`
**Depends on**: None
**Reuses**: `LoginController::create`, `LoginHttpTest` `withoutVite()` + `assertInertia`
**Requirement**: AUTH-01, AUTH-02, AUTH-03

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Guest `GET /` renders Inertia `User/Login`
- [x] Authenticated `GET /` redirects to `/reservations`
- [x] `ExampleTest` welcome smoke is gone
- [x] Gate check passes: `php artisan test --testsuite=Feature --filter=LoginHttpTest`

**Tests**: integration
**Gate**: full

---

#### T2: Add cadastro navigation on the login screen

**What**: Import Inertia `Link` in `Login.jsx`. Below `Entrar`, add the create-user divider/button pattern with text `Não tem uma conta?` and link `Ir para o cadastro` `href="/register"`. Mock `Link` in `Login.test.jsx` like `Create.test.jsx`. Replace `does not render a password-recovery control or a registration link` with: still no recovery copy, and the cadastro link is present with `/register`.
**Where**: `resources/js/Pages/User/Login.jsx`
**Depends on**: T1
**Reuses**: `Create.jsx` divider/`Link` classes; `Create.test.jsx` `Link` mock
**Requirement**: AUTH-04, AUTH-05, AUTH-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Login shows `Não tem uma conta?` and `Ir para o cadastro` → `/register`
- [x] No password-recovery control
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

#### T3: Document login home and cadastro CTA

**What**: Update `docs/screens/screen-login.md` so guests also reach login at `GET /`, the navigation table includes `Ir para o cadastro` → `/register`, and the sentence that omits a registration link is replaced with the enabled CTA. Do not rewrite create-user docs.
**Where**: `docs/screens/screen-login.md`
**Depends on**: T2
**Reuses**: existing screen-login structure; create-user navigation table as the mirror
**Requirement**: AUTH-04, AUTH-01

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Screen doc lists `GET /` as guest home login and the cadastro control
- [x] Password recovery remains excluded
- [x] Gate check passes: `vendor/bin/pint --test` && `npm run lint`

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1

Phase 1:  T1 -> T2 -> T3
```

Execution is strictly sequential.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Serve login at GET / | 1 route + its HTTP tests | Granular |
| T2: Add cadastro navigation | 1 page + its Vitest file | Granular |
| T3: Document screen contract | 1 markdown file | Granular |

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (start) | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | T2 | T2 -> T3 | Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | Home/login HTTP + guest middleware | integration | integration | OK |
| T2 | React login page | unit | unit | OK |
| T3 | Screen doc | none | none | OK |
