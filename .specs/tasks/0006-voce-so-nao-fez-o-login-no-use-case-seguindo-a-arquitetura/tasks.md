---
harness:
  commits:
    - "feat(user): add authenticate-user use case"
    - "feat(app): bind user authenticator"
    - "test(user): cover authenticate-user use case"
    - "chore(specs): record authenticate-user plan artifacts"
  tests:
    unit:
      - "AuthenticateUser completes without throwing when UserAuthenticator::attempt returns true"
      - "AuthenticateUser throws InvalidCredentials when UserAuthenticator::attempt returns false"
      - "AuthenticateUser passes the given email and password to UserAuthenticator::attempt"
      - "AuthenticateUser, UserAuthenticator, and InvalidCredentials sources do not import Illuminate"
    integration:
      - "POST /login with valid credentials authenticates through AuthenticateUser, regenerates the session, and redirects to /reservations or the intended URL"
      - "Unknown email, wrong password, or a soft-deleted user returns E-mail ou senha inválidos. on credentials without authenticating"
      - "InvalidCredentials from the use case is mapped to the generic credentials error and counts as a rate-limit hit"
      - "A sixth failed attempt for the same email and IP is rejected by throttle without authenticating, even with the correct password"
      - "Trimmed and lowercased email Ada@Example.com authenticates the stored user"
      - "Password is absent from old input after a failed login"
      - "POST /logout still invalidates the session and redirects to /login"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project exists in the repo; Feature HTTP tests cover the login stack through the use case. Do not bootstrap Playwright."
  gates:
    - id: unit
      command: "php artisan test --testsuite=Unit"
      required: true
    - id: integration
      command: "php artisan test --testsuite=Feature"
      required: true
    - id: frontend
      command: "npm test"
      required: true
    - id: lint
      command: "vendor/bin/pint --test"
      required: true
---

# Implementation Plan

## Summary

Move credential verification out of `LoginRequest::authenticate()` into Application `AuthenticateUser`, which depends on Domain `UserAuthenticator`. Infra implements the port with `Auth::attempt`. `LoginController::store` validates via `LoginRequest`, enforces the existing RateLimiter, runs the use case, maps `InvalidCredentials` to `credentials` = `E-mail ou senha inválidos.`, then regenerates the session and redirects. Logout, React, and routes stay as they are.

**Design (inline, no `design.md`):** see Selected Approach in `spec.md`. No extra DTO, Result type, logout use case, or Domain rate-limiter port.

## Affected Components

- `app` — `Domain/UserAuthenticator.php`, `Application/UseCases/AuthenticateUser.php`, `Application/Errors/InvalidCredentials.php`, `Infra/Http/LaravelUserAuthenticator.php`, `LoginController`, `LoginRequest`, `AppServiceProvider`, `tests/Unit/User/AuthenticateUserTest.php`. Existing `LoginHttpTest` and Vitest login tests stay green.

## Tasks

Execute T1 → T2 as in **Task Breakdown**.

## Planned Tests

### Unit

- AuthenticateUser completes without throwing when UserAuthenticator::attempt returns true
- AuthenticateUser throws InvalidCredentials when UserAuthenticator::attempt returns false
- AuthenticateUser passes the given email and password to UserAuthenticator::attempt
- AuthenticateUser, UserAuthenticator, and InvalidCredentials sources do not import Illuminate

### Integration

- POST /login with valid credentials authenticates through AuthenticateUser, regenerates the session, and redirects to /reservations or the intended URL
- Unknown email, wrong password, or a soft-deleted user returns E-mail ou senha inválidos. on credentials without authenticating
- InvalidCredentials from the use case is mapped to the generic credentials error and counts as a rate-limit hit
- A sixth failed attempt for the same email and IP is rejected by throttle without authenticating, even with the correct password
- Trimmed and lowercased email Ada@Example.com authenticates the stored user
- Password is absent from old input after a failed login
- POST /logout still invalidates the session and redirects to /login

### E2E

- not applicable: No Playwright project exists in the repo; Feature HTTP tests cover the login stack through the use case. Do not bootstrap Playwright.

## Required Gates

| Gate | Command | Required |
| ---- | ------- | -------- |
| unit | `php artisan test --testsuite=Unit` | yes |
| integration | `php artisan test --testsuite=Feature` | yes |
| frontend | `npm test` (`vitest run`) | yes |
| lint | `vendor/bin/pint --test` | yes |

Do not require `npx playwright test` (no Playwright project). Do not require `npm run build` (no frontend production change).

## Definition of Done

- AUTH-01…AUTH-07 have tests at the levels above.
- Gates pass. No silent test deletions. Existing `LoginHttpTest` and Vitest login tests remain.
- `LoginController::store` calls `AuthenticateUser`. `LoginRequest` no longer calls `Auth::attempt`.
- Application and Domain login types have no `Illuminate` imports.
- Infra adapter uses `Auth::attempt`. Session regenerate, throttle, and logout remain at the HTTP edge.
- No password recovery, JWT, roles, remember-me, or logout use case.
- No product commit from this Plan phase.

---

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

---

**Design**: inline in Summary and `spec.md` Selected Approach (MVP; no `design.md`)
**Status**: Implemented

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `docs/reviews/review-tests.md`, `harness/stack.yml`, `phpunit.xml`. Vitest is installed; Playwright is not.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| `AuthenticateUser` use case + `InvalidCredentials` | unit | Success, invalid credentials, arguments forwarded to the port; sources do not import Illuminate; 1:1 with AUTH-01/AUTH-02/AUTH-03 | `tests/Unit/User/AuthenticateUserTest.php` | `php artisan test --testsuite=Unit` |
| Domain `UserAuthenticator` interface | none | Contract only; exercised via the use-case fake | `app/Modules/User/Domain/UserAuthenticator.php` | build / lint |
| Infra `LaravelUserAuthenticator` + `LoginController` + `LoginRequest` | integration | Happy path + generic credentials + soft-delete + throttle + session regenerate + email normalize + password not flashed + logout | `tests/Feature/User/LoginHttpTest.php` | `php artisan test --testsuite=Feature` |
| React login page / session service | unit (regression) | Unchanged; existing Vitest files must stay green | `resources/js/Pages/User/Login.test.jsx`, `resources/js/Services/session.test.js` | `npm test` |
| Playwright e2e | none (deferred) | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After the use-case unit task | `php artisan test --testsuite=Unit` |
| Full | After HTTP wiring | `php artisan test --testsuite=Unit` && `php artisan test --testsuite=Feature` |
| Build | Phase end | `vendor/bin/pint --test` && `php artisan test --testsuite=Unit` && `php artisan test --testsuite=Feature` && `npm test` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Move login into the application use case

```
T1 → T2
```

---

## Task Breakdown

### T1: Add AuthenticateUser against a Domain UserAuthenticator port

**What**: Add Domain `UserAuthenticator` (`attempt(string $email, string $password): bool`), Application `InvalidCredentials`, and Application `AuthenticateUser::execute` that calls the port and throws on false. Cover with a PHPUnit fake (no Laravel app).
**Where**: `app/Modules/User/Application/UseCases/AuthenticateUser.php`
**Depends on**: None
**Reuses**: `CreateUser` + `tests/Unit/User/CreateUserTest.php` fake-port style
**Requirement**: AUTH-01, AUTH-02, AUTH-03

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `app/Modules/User/Domain/UserAuthenticator.php` exists, is an interface, and has no `Illuminate` import
- [x] `app/Modules/User/Application/Errors/InvalidCredentials.php` exists and has no `Illuminate` import
- [x] `AuthenticateUser` constructor-injects `UserAuthenticator`, `execute` throws `InvalidCredentials` when `attempt` is false, and returns without throwing when `attempt` is true
- [x] `tests/Unit/User/AuthenticateUserTest.php` covers the Planned Tests Unit behaviors, including the no-Illuminate source check
- [x] Gate check passes: `php artisan test --testsuite=Unit`
- [x] Test count: existing Unit tests plus the new file; no silent deletions

**Tests**: unit
**Gate**: quick

---

### T2: Wire LaravelUserAuthenticator and thin the login HTTP adapter

**What**: Implement `LaravelUserAuthenticator` with `Auth::attempt` (email+password only, remember false). Bind the port in `AppServiceProvider`. Change `LoginController::store` to run throttle helpers, `AuthenticateUser`, map `InvalidCredentials` to the generic credentials `ValidationException`, then regenerate the session and redirect. Remove `authenticate()` and `Auth` from `LoginRequest`; keep rules, messages, email normalize, and public throttle helpers.
**Where**: `app/Modules/User/Infra/Http/Controllers/LoginController.php`
**Depends on**: T1
**Reuses**: current `LoginHttpTest` contract; `UserRepository` bind pattern in `AppServiceProvider`

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `app/Modules/User/Infra/Http/LaravelUserAuthenticator.php` implements `UserAuthenticator` via `Auth::attempt`
- [x] `AppServiceProvider` binds `UserAuthenticator` to `LaravelUserAuthenticator`
- [x] `LoginController::store` calls `AuthenticateUser`; `LoginRequest` no longer calls `Auth::attempt`
- [x] Throttle (5 / email+IP / 60s), session regenerate, intended redirect, and logout stay at the HTTP edge
- [x] `tests/Feature/User/LoginHttpTest.php` stays green without weakened assertions
- [x] Gate check passes: `php artisan test --testsuite=Unit` && `php artisan test --testsuite=Feature`
- [x] Test count: existing Feature tests remain; no silent deletions

**Tests**: integration
**Gate**: full

---

## Phase Execution Map

```
Phase 1:  T1 ------→ T2
```

Execution is strictly sequential. The feature fits one batch (2 tasks); Execute inline, no sub-agents.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Add AuthenticateUser against a Domain UserAuthenticator port | One use case + its port + its error + unit tests | Granular (cohesive Application slice) |
| T2: Wire LaravelUserAuthenticator and thin the login HTTP adapter | HTTP adapter + bind + existing Feature contract | Granular (cannot prove the adapter until the controller is wired) |

**Granularity check**: each task is one deliverable. Related files for T1/T2 stay in `What` / `Done when` so `Where` names a single primary path.

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | no inbound arrow | Match |
| T2 | T1 | T1 → T2 | Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1: Add AuthenticateUser | Use case / Application error / Domain port | unit (port itself none; use case unit) | unit | OK |
| T2: Wire HTTP adapter | Infra authenticator + controller + Form Request | integration | integration | OK |
