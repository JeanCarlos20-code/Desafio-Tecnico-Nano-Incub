---
harness:
  commits:
    - "chore(package): add vitest and testing-library"
    - "chore(package-lock): lock frontend unit test dependencies"
    - "chore(vite.config): enable vitest jsdom environment"
    - "feat(reservation): add post-register placeholder page"
    - "feat(resources): post register form through users service"
    - "feat(routes): add register and reservations routes"
    - "feat(user): add create-user register screen and session"
    - "test(reservation): cover post-register placeholder page"
    - "test(resources): cover users service and inertia glob"
    - "test(user): cover create-user screen and register HTTP"
    - "chore(docs): add create-user screen specification"
    - "chore(readme): document administrator registration access policy"
    - "chore(specs): record create-user screen task artifacts"
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

Evolve the existing Inertia page `User/Create` into the create-user screen from `docs/screens/screen-create-user.md`. Serve it at `GET /register`, POST through `Services/users.js` to `/register`, keep `/users*` aliases, localize and normalize on `StoreUserRequest`, `Auth::login` after `CreateUser`, redirect to a `/reservations` stub, and document public access in README. Cover UI with Vitest (new `npm test`) and HTTP with Feature tests. Do not implement login or reservation CRUD. Do not add Playwright.

**Design (inline, no `design.md`):** Page composes `Pages/User/Components` (brand panel, icon text field, password field with toggle). `useForm` from `@inertiajs/react` owns data, `processing`, and `errors`. `store(form)` in `Services/users.js` is the only POST URL. Controller stays thin: validate → use case → `Auth::login(UserModel::findOrFail($user->id))` → `redirect()->route('reservations.index')`. No Domain/Application change unless email normalize is done in the Form Request (`prepareForValidation`), which it should be.

## Affected Components

- `app` — `resources/js/Pages/User/*`, `resources/js/Services/users.js`, `UserController`, `StoreUserRequest`, `routes/web.php`, `Pages/Reservation/Index.jsx`, `vite.config.js`, `package.json`, `README.md`, `tests/Feature/User/CreateUserHttpTest.php`, `resources/js/Pages/User/Create.test.jsx`

## Tasks

See **Task Breakdown**. Execute T1 → T2 → T3 → T4 sequentially.

## Planned Tests

- Vitest + RTL: empty form copy, field labels, login href `/login`, password toggle (keyboard), `processing` label `Criando usuário...` and disabled submit, field errors + `aria-invalid`, password cleared on errors, general banner, `store` called with `/register` (mock Inertia).
- Feature: `GET /register` 200 + Inertia `User/Create`; `GET /users/create` still 200; valid `POST /register` persists, `assertAuthenticated`, redirect `/reservations`; invalid/duplicate/soft-deleted/short password do not persist; email stored lowercase/trimmed; password absent from session old input; Portuguese messages on keys; aliases `POST /users` still persist (redirect target becomes `/reservations`).
- Keep existing Unit `CreateUserTest` and persistence tests unchanged in intent.

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

- All AC-001…AC-020 have tests or a documented README check (AC-017).
- Gates above pass. No silent test deletions.
- `/register` matches the screen behaviors; `/users*` aliases still hit the same controller.
- `harness.commits` still matches dirty-path grouping (`user` / `resources` / `routes` / `reservation` / `package` / `package-lock` / `vite` / `readme`).
- No product commit from this Plan phase.

---

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

---

**Design**: inline in Summary (MVP; no `design.md`)
**Status**: Execute complete

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `docs/reviews/review-tests.md`, `harness/stack.yml`, `phpunit.xml`. No Vitest/Playwright config exists yet.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| React page / feature components | unit | Copy, fields, toggle, loading, field errors, general banner, login href, mocked POST `/register`; all listed UI edge cases | `resources/js/Pages/User/*.test.jsx` | `npm test` |
| `Services/users.js` | unit | Asserts `form.post('/register')` (via page test mock of Inertia form) | same as above | `npm test` |
| HTTP controller + Form Request | integration | Happy path + every validation/duplicate/soft-delete/auth/redirect/email-normalize path in spec | `tests/Feature/User/CreateUserHttpTest.php` | `php artisan test --testsuite=Feature` |
| CreateUser use case | unit | Existing tests remain; no new rules | `tests/Unit/User/CreateUserTest.php` | `php artisan test --testsuite=Unit` |
| Vite / npm / README | none | Build/lint/docs only | `vite.config.js`, `package.json`, `README.md` | build / lint |
| Playwright e2e | none (deferred) | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After React unit tasks | `npm test` && `php artisan test --testsuite=Unit` |
| Full | After HTTP/Feature tasks | `npm test` && `php artisan test --testsuite=Feature` && `php artisan test --testsuite=Unit` |
| Build | Tooling, README, phase end | `npm run build` && `vendor/bin/pint --test` && `php artisan test` && `npm test` |

Cwd: worktree root. After T1, `package.json` script `"test": "vitest run"`.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Tooling

```
T1
```

### Phase 2: Screen

```
T2
```

### Phase 3: HTTP contract

```
T3
```

### Phase 4: Documentation

```
T4
```

```
T1 -> T2 -> T3 -> T4
```

---

## Task Breakdown

### Phase 1: Tooling

#### T1: Add Vitest and Testing Library

**What**: Add `vitest`, `jsdom`, `@testing-library/react`, `@testing-library/user-event`, `@testing-library/jest-dom` as devDependencies; script `"test": "vitest run"`; Vitest `test.environment = 'jsdom'` and React plugin in `vite.config.js` (`/// <reference types="vitest/config" />`). Run `npm install` so `package-lock.json` updates.
**Where**: `package.json`
**Depends on**: None
**Reuses**: existing `vite.config.js` (`defineConfig`, `@vitejs/plugin-react`)
**Requirement**: (tooling for AC-002…AC-016 UI tests)

**Tools**:

- MCP: `context7` (Vitest Vite `test` config)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `npm test` runs Vitest and exits 0 with zero or more tests (no hang)
- [x] `vite.config.js` includes `test.environment` `jsdom`
- [x] Gate check passes: `npm test` && `npm run build`

**Tests**: none
**Gate**: build

**Commit**: `chore(package): add vitest and testing-library` (plus `chore(package-lock)` and `chore(vite)` from the same dirty set)

---

### Phase 2: Screen

#### T2: Build the create-user Inertia screen

**What**: Replace the zinc `AppLayout` form with the split-card screen. Put brand panel, icon fields, and password toggle under `resources/js/Pages/User/Components/`. Portuguese copy from the screen spec; password placeholder `Mínimo de 8 caracteres`. Submit still goes through `store(form)` (URL updated in T3). Inline SVGs only. Add `Create.test.jsx` mocking `@inertiajs/react` (`useForm`, `Link`).
**Where**: `resources/js/Pages/User/Create.jsx`
**Depends on**: T1
**Reuses**: `@inertiajs/react` `useForm`; Tailwind 4; do not use `AppLayout`
**Requirement**: AC-002, AC-003, AC-004, AC-005, AC-009, AC-012, AC-013, AC-014, AC-015, AC-016, AC-019

**Tools**:

- MCP: `context7` (Inertia React `useForm` `processing` `errors` `reset` `Link`)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Empty, focus, visible/hidden password, validation-error, processing, and unexpected-failure states are implemented as specified
- [x] Labels associated with inputs; `aria-required`, `aria-invalid`, `aria-describedby`, `aria-live="polite"` on the general banner
- [x] `Create.test.jsx` covers those UI ACs (mock Inertia; no Laravel)
- [x] Gate check passes: `npm test` && `php artisan test --testsuite=Unit`
- [x] Test count: no silent deletions of PHPUnit tests

**Tests**: unit
**Gate**: quick

**Commit**: `feat(user): add create-user register screen and session` (same `user` code group as T3) and `test(user): cover create-user screen and register HTTP`

---

### Phase 3: HTTP contract

#### T3: Wire register POST, session, and validation copy

**What**: `StoreUserRequest`: `prepareForValidation` trim+lowercase email; Portuguese `messages()`; keep `Password::defaults()` and unique including trash. `UserController@store`: `CreateUser` then `Auth::login` on the Eloquent model (`$created->id`), redirect to named `reservations.index`. `users.js`: `form.post('/register')` with `onError` → `reset('password')` and `onException` (or non-422 failure) for the general banner. `routes/web.php`: named `register` GET+POST plus keep `users.create` / `users.store`; `GET /reservations` → Inertia `Reservation/Index` stub. Extend `CreateUserHttpTest.php` (do not drop existing scenarios).
**Where**: `app/Modules/User/Infra/Http/Controllers/UserController.php`
**Depends on**: T2
**Reuses**: `CreateUser` use case; `User` Eloquent model; existing Feature test style (`withoutVite`, `AssertableInertia`)
**Requirement**: AC-001, AC-006, AC-007, AC-008, AC-010, AC-011, AC-018, AC-020

**Tools**:

- MCP: `context7` (Laravel `Auth::login`, Form Request `messages` / `prepareForValidation`)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `GET /register` and `GET /users/create` both render `User/Create`
- [x] Valid POST authenticates and redirects to `/reservations`
- [x] Invalid/duplicate/soft-deleted/short password cases still fail without extra rows
- [x] Email ` Ada@Example.com ` persists as `ada@example.com`
- [x] Password not in old input; Portuguese messages asserted
- [x] Stub `Reservation/Index` exists so the redirect is not a missing component
- [x] Gate check passes: `npm test` && `php artisan test --testsuite=Feature` && `php artisan test --testsuite=Unit`
- [x] Test count: no silent deletions

**Tests**: integration
**Gate**: full

**Commit**: `feat(resources): post register form through users service`; `feat(routes): add register and reservations routes`; `feat(reservation): add post-register placeholder page`; remaining `user` files join `feat(user): add create-user register screen and session` and `test(user): cover create-user screen and register HTTP`

---

### Phase 4: Documentation

#### T4: Document registration access policy

**What**: Expand `README.md` so it states `/register` is public because administrator login is not implemented, this is a challenge convenience, and production must not let any visitor create an administrator. Do not claim a feature flag that does not exist.
**Where**: `README.md`
**Depends on**: T3
**Reuses**: current README sentence about cadastro
**Requirement**: AC-017

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Policy paragraph is present and accurate
- [x] Gate check passes: `npm run build` && `vendor/bin/pint --test` && `php artisan test` && `npm test`

**Tests**: none
**Gate**: build

**Commit**: `chore(readme): document administrator registration access policy`

---

## Phase Execution Map

```
Phase 1 → Phase 2 → Phase 3 → Phase 4

Phase 1:  T1
Phase 2:  T2
Phase 3:  T3
Phase 4:  T4
```

```
T1 -> T2 -> T3 -> T4
```

Execution is strictly sequential — no intra-phase parallelism. Four tasks fit one Execute batch (no sub-agent split).

**How phase-based execution works:** one worker runs T1–T4 in order (implement → gate → mark done). After the last task, a fresh Verifier runs (author ≠ verifier).

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Add Vitest and Testing Library | npm + vite test config | Granular |
| T2: Build the create-user Inertia screen | one page + colocated components + unit tests | OK cohesive (one screen) |
| T3: Wire register POST, session, and validation copy | one HTTP flow (request, controller, routes, service, stub, Feature tests) | OK cohesive (single contract) |
| T4: Document registration access policy | one file | Granular |

Granularity check: T2/T3 are multi-file but one deliverable each. Do not split the screen from its Vitest file. Do not split the HTTP contract from `CreateUserHttpTest.php`.

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (start) | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | T2 | T2 -> T3 | Match |
| T4 | T3 | T3 -> T4 | Match |

Dependencies point backward only.

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | Vite / npm config | none | none | OK |
| T2 | React page / components | unit | unit | OK |
| T3 | HTTP controller + Form Request + service + routes | integration (highest) | integration | OK |
| T4 | README | none | none | OK |

T3 also touches `users.js`; the POST URL is asserted from the page test (T2) once T3 updates the service — re-run `npm test` on T3. Feature tests in T3 cover controller/request/routes.

---

## Considered Approaches

| Option | Trade-off | Decision |
| ------ | --------- | -------- |
| A. Only restyle `/users/create` | Misses `/register`, auth, redirect | Rejected |
| B. Duplicate Register page | Two forms to maintain | Rejected |
| C. One `User/Create` screen + `/register` aliases + session + stub + Vitest | Matches screen + tree.md + existing module | **Selected** |
| D. Add Playwright now | Correct long-term; no config/CI browsers yet | Deferred |

## Selected Approach

Option C, as in `spec.md`. Commit grouping follows `group_commits.py`: `resources/js/Pages/User` and `app/Modules/User` share `user`; `Services/users.js` is `resources`; `routes/web.php` is `routes`; reservation stub is `reservation`.
