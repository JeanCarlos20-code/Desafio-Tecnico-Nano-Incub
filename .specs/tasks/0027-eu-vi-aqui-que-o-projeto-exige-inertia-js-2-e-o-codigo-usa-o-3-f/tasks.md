---
harness:
  commits:
    - "fix(composer): pin inertia-laravel to v2"
    - "fix(package): pin inertiajs react adapter to v2"
    - "fix(package-lock): pin inertiajs react adapter lockfile"
    - "fix(resources): add inertia 2 visit failure handlers"
    - "test(resources): cover inertia 2 visit failure handlers"
    - "fix(user): map login failure callbacks to inertia 2 events"
    - "test(user): cover login inertia 2 failure callbacks"
    - "fix(room): map room failure callbacks to inertia 2 events"
    - "test(room): cover room inertia 2 failure callbacks"
    - "fix(reservation): map reservation failure callbacks to inertia 2 events"
    - "test(reservation): cover reservation inertia 2 failure callbacks"
    - "chore(specs): record inertia 2 alignment plan artifacts"
  tests:
    unit:
      - "Visit failure helper subscribes to router.on('invalid') and preventDefault when the handler returns false"
      - "Visit failure helper subscribes to router.on('exception') and preventDefault when the handler returns false"
      - "Visit failure helper unsubscribes on onFinish and still calls the original onFinish"
      - "session login treats omitted invalid/exception returns as false so a non-validation failure stays on the page"
      - "User/Login shows Não foi possível entrar. Tente novamente. on unexpected failure"
      - "Room/Create shows Não foi possível salvar a sala. Tente novamente. on unexpected failure"
      - "Reservation/Create shows Não foi possível salvar a reserva. Tente novamente. on unexpected failure"
      - "Reservation/Index cancel unexpected failure returns false, keeps the dialog, and shows the cancel error"
      - "Reservation/Index retry unexpected failure returns false and keeps the load-failure copy"
    integration: []
    e2e: []
  tests_not_applicable:
    integration: "Adapter pin plus client visit-callback remap. No new Laravel route, middleware, FormRequest, controller, or MySQL behavior. Existing Feature tests already cover Inertia::render. Do not add a React integration suite."
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Unexpected-failure banners are React/service contracts covered by Vitest. docs/test/e2e.md forbids repeating that matrix in the browser. Do not bootstrap Playwright in this task."
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

Pin both Inertia adapters to `^2.0` and remap the only v3-only product APIs (`onHttpException` / `onNetworkError`) onto Inertia 2 `router.on('invalid'|'exception')`. Leave PHP `Inertia::render`, shared props, `createInertiaApp`, and screen markup alone.

**Design (inline, no `design.md`):**

- `composer require inertiajs/inertia-laravel:^2.0` (updates `composer.json` + `composer.lock`).
- `npm install @inertiajs/react@^2.0` (updates `package.json` + `package-lock.json`). Do not add an explicit `@inertiajs/core` line.
- Add `resources/js/Services/inertiaVisit.js` exporting `withVisitFailureHandlers(options)`:
  - Read `onInvalid`, `onException`, `onFinish` from `options`.
  - If either handler is present, `router.on('invalid', …)` and/or `router.on('exception', …)`.
  - When the handler returns `false`, call `event.preventDefault()`.
  - Replace `onFinish` so it unsubscribes first, then calls the original `onFinish`.
  - Spread the rest of `options` through (`onError`, `onSuccess`, query visits, etc.).
- `session.js`, `rooms.js`, and `reservations.js` pass every form/visit options object through the helper. `session.login` keeps wrapping handlers so a missing return is `false`.
- Pages rename `onHttpException` → `onInvalid` and `onNetworkError` → `onException`. Same banner strings and `return false` as today.
- Vitest: helper file new; existing cases rename the option they invoke; service mocks add `router.on: vi.fn(() => vi.fn())`.
- Do not edit PHP, `app.jsx`, Blade, React/Laravel versions, or docs (they already say Inertia 2).

## Affected Components

- `app` — `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `resources/js/Services/inertiaVisit.js` (+ test), `Services/session.js`, `Services/rooms.js`, `Services/reservations.js`, `Pages/User/Login.jsx`, `Pages/Room/{Index,Create,Edit}.jsx`, `Pages/Reservation/{Index,Create,Edit}.jsx`, and the Vitest files that name the v3 callbacks.
- PHP modules, `HandleInertiaRequests`, `app.jsx`, and Playwright stay as-is.

## Tasks

Execute T1 → T6 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. Vitest keeps `@inertiajs/react` mocked. Do not add a unit test that only reads `package.json` / `composer.json` (`docs/test/unit.md` forbids configuration tests). Do not add PHP Unit cases; no use-case change.

### Integration

Not applicable — no Laravel/MySQL change. See `harness.tests_not_applicable.integration`. Existing Feature Inertia responses remain green.

### E2E

Not applicable — no Playwright project. See `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Unit coverage remains Application-only (≥80%). Frontend coverage via `npm run test:coverage` (≥80%). Do not run `npx playwright test`.

| Gate | Command | Required |
| ---- | ------- | -------- |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` | yes |
| frontend | `npm run test:coverage` | yes |
| integration | `php artisan test --testsuite=Feature` | yes |
| lint | `vendor/bin/pint --test` | yes |
| frontend_lint | `npm run lint` | yes |
| php_build | `composer run build` | yes |
| frontend_build | `npm run build` | yes |

## Definition of Done

- AC-001…AC-003: both adapters are `^2.0` and locks resolve 2.x only.
- AC-004…AC-007: no v3 visit-option names; helper + pages preserve banners and stay-on-page.
- PHP Inertia usage and `createInertiaApp` unchanged.
- Gates above pass. No product commit in PLAN.

---

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

---

**Design**: inline in Summary (MVP; no `design.md`)
**Status**: Implemented

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `docs/reviews/review-tests.md`, `harness/stack.yml`, `phpunit.xml`, `package.json`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Inertia visit-failure helper | unit | invalid + exception subscribe; preventDefault on `false`; unsubscribe + original onFinish; no listeners when both handlers omitted | `resources/js/Services/inertiaVisit.test.js` | `npm run test:coverage` |
| User session + Login failure UX | unit | default-false wrap; login general banner | `resources/js/Services/session.test.js`, `resources/js/Pages/User/Login.test.jsx` | `npm run test:coverage` |
| Room save / retry failure UX | unit | create banner; existing Index retry still visits `/rooms` | `resources/js/Pages/Room/Create.test.jsx`, `resources/js/Services/rooms.test.js` | `npm run test:coverage` |
| Reservation save / cancel / retry failure UX | unit | create banner; cancel dialog stays; retry returns false | `resources/js/Pages/Reservation/Create.test.jsx`, `resources/js/Pages/Reservation/Index.test.jsx` | `npm run test:coverage` |
| Composer / npm pins | none | Manifest + lockfile only (`docs/test/unit.md` forbids configuration tests) | `composer.json`, `package.json` | build / lint |
| PHP Inertia controllers | none | Unchanged; existing Feature suite remains | `tests/Feature/**` | `php artisan test --testsuite=Feature` |
| Playwright e2e | none | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After React unit tasks | `npm run test:coverage` |
| Full | After pins + existing HTTP suites | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run test:coverage` |
| Build | Phase end / lint / lockfile tasks | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Pin Inertia 2 adapters

```
T1 -> T2
```

### Phase 2: Remap v3 failure callbacks

```
T3 -> T4 -> T5 -> T6
```

---

## Task Breakdown

### Phase 1: Pin Inertia 2 adapters

#### T1: Pin inertia-laravel to v2

**What**: Set `inertiajs/inertia-laravel` to `^2.0` in `composer.json` and refresh `composer.lock` with `composer require inertiajs/inertia-laravel:^2.0` so the locked package is 2.x (not 3.x). Do not change Laravel, other Composer requires, or PHP Inertia usage.
**Where**: `composer.json`
**Depends on**: None
**Reuses**: current `composer.json` require block
**Requirement**: INERTIA-02, INERTIA-03

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `composer.json` requires `inertiajs/inertia-laravel` `^2.0`
- [x] `composer.lock` resolves `inertiajs/inertia-laravel` 2.x only
- [x] No PHP controller or middleware edit
- [x] Gate check passes: `composer run build`

**Tests**: none
**Gate**: build

---

#### T2: Pin @inertiajs/react to v2

**What**: Set `@inertiajs/react` to `^2.0` in `package.json` and refresh `package-lock.json` with `npm install @inertiajs/react@^2.0` so `@inertiajs/react` and `@inertiajs/core` resolve 2.x only. Keep React 19. Do not add `@inertiajs/core` as a direct dependency. Do not change `app.jsx`.
**Where**: `package.json`
**Depends on**: T1
**Reuses**: current `package.json` dependencies
**Requirement**: INERTIA-01, INERTIA-03

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `package.json` depends on `@inertiajs/react` `^2.0`
- [x] `package-lock.json` resolves `@inertiajs/react` and `@inertiajs/core` 2.x only
- [x] `react` remains `^19.3.0`
- [x] Gate check passes: `npm run build`

**Tests**: none
**Gate**: build

---

### Phase 2: Remap v3 failure callbacks

#### T3: Add Inertia 2 visit failure helper

**What**: Add `withVisitFailureHandlers(options)` in `resources/js/Services/inertiaVisit.js`. For a visit that supplies `onInvalid` and/or `onException`, register `router.on('invalid')` / `router.on('exception')`, `preventDefault` when the handler returns `false`, unsubscribe in `onFinish`, and still call the original `onFinish`. When both handlers are omitted, do not register listeners. Add `inertiaVisit.test.js` that mocks `@inertiajs/react` `router.on` and covers those four behaviors. Do not wire pages yet.
**Where**: `resources/js/Services/inertiaVisit.js`
**Depends on**: T2
**Reuses**: `@inertiajs/react` `router.on` (v2 events docs); existing Vitest mock style in `Services/*.test.js`
**Requirement**: INERTIA-04, INERTIA-05, INERTIA-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Helper registers `invalid` / `exception` only when the matching handler is passed
- [x] `false` from a handler calls `event.preventDefault()`
- [x] `onFinish` unsubscribes then forwards
- [x] Vitest covers the four helper behaviors (no silent deletions elsewhere)
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

#### T4: Map login failure callbacks to Inertia 2 events

**What**: In `session.js`, wrap `form.post` options with `withVisitFailureHandlers`. Rename the login wrap from `onHttpException` / `onNetworkError` to `onInvalid` / `onException` and keep the default-`false` return. In `Login.jsx`, pass `onInvalid` / `onException` instead of the v3 names. Update `session.test.js` and `Login.test.jsx` to invoke the new names (banner copy and `false` stay). Mock `router.on` on the session test so the helper can subscribe. Do not change validation `onError`, fields, or layout.
**Where**: `resources/js/Services/session.js`
**Depends on**: T3
**Reuses**: `withVisitFailureHandlers`; existing login banner case
**Requirement**: INERTIA-04, INERTIA-05, INERTIA-06, INERTIA-07

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `session.js` and `Login.jsx` have no `onHttpException` / `onNetworkError`
- [x] Default-false wrap and `Não foi possível entrar. Tente novamente.` remain
- [x] Existing Login Vitest cases still pass (no silent deletions)
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

#### T5: Map room failure callbacks to Inertia 2 events

**What**: Wrap every `rooms.js` form/visit options object with `withVisitFailureHandlers`. Rename `onHttpException` / `onNetworkError` to `onInvalid` / `onException` on `Room/Index.jsx` retry, `Room/Create.jsx`, and `Room/Edit.jsx`. Update `Room/Create.test.jsx` to invoke `onInvalid`. Add `router.on` to the `rooms.test.js` mock. Keep save/retry copy and `return false`. Do not change filters, delete `onSuccess`, or RoomForm markup.
**Where**: `resources/js/Services/rooms.js`
**Depends on**: T4
**Reuses**: `withVisitFailureHandlers`; existing Create unexpected-failure case
**Requirement**: INERTIA-04, INERTIA-05, INERTIA-06, INERTIA-07

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Room services/pages have no `onHttpException` / `onNetworkError`
- [x] Create banner and Index retry visit still behave as today
- [x] Existing Room Vitest cases still pass (no silent deletions)
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

#### T6: Map reservation failure callbacks to Inertia 2 events

**What**: Wrap every `reservations.js` form/visit options object with `withVisitFailureHandlers`. Rename `onHttpException` / `onNetworkError` to `onInvalid` / `onException` on `Reservation/Index.jsx` (cancel + retry), `Reservation/Create.jsx`, and `Reservation/Edit.jsx`. Update `Reservation/Create.test.jsx` and `Reservation/Index.test.jsx` to invoke the new names (banner, dialog stays, retry `false`). Add `router.on` to the `reservations.test.js` mock. Do not change filters, cancel `onSuccess`, or schedule fields.
**Where**: `resources/js/Services/reservations.js`
**Depends on**: T5
**Reuses**: `withVisitFailureHandlers`; existing Create / Index failure cases
**Requirement**: INERTIA-04, INERTIA-05, INERTIA-06, INERTIA-07

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Reservation services/pages have no `onHttpException` / `onNetworkError`
- [x] Create banner, cancel-dialog stay, and retry `false` remain
- [x] Existing Reservation Vitest cases still pass (no silent deletions)
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

## Phase Execution Map

```
Phase 1 -> Phase 2

Phase 1:  T1 -> T2
Phase 2:  T3 -> T4 -> T5 -> T6
```

Execution is strictly sequential.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Pin inertia-laravel to v2 | 1 manifest (+ its lock) | Granular |
| T2: Pin @inertiajs/react to v2 | 1 manifest (+ its lock) | Granular |
| T3: Add Inertia 2 visit failure helper | 1 helper + its Vitest | Granular |
| T4: Map login failure callbacks | 1 service + login page/tests | Granular (same module) |
| T5: Map room failure callbacks | 1 service + room pages/tests | Granular (same module) |
| T6: Map reservation failure callbacks | 1 service + reservation pages/tests | Granular (same module) |

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (start) | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | T2 | (phase 2 start; T2 is prior phase) | Match |
| T4 | T3 | T3 -> T4 | Match |
| T5 | T4 | T4 -> T5 | Match |
| T6 | T5 | T5 -> T6 | Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | Composer / npm pins | none | none | OK |
| T2 | Composer / npm pins | none | none | OK |
| T3 | Inertia visit-failure helper | unit | unit | OK |
| T4 | User session + Login failure UX | unit | unit | OK |
| T5 | Room save / retry failure UX | unit | unit | OK |
| T6 | Reservation save / cancel / retry failure UX | unit | unit | OK |