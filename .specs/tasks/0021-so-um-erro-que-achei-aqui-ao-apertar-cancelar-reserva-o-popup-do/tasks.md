---
harness:
  commits:
    - "fix(reservation): dismiss cancel dialog on voltar and success"
    - "test(reservation): cover cancel dialog dismiss"
    - "fix(room): dismiss delete dialog on cancelar and success"
    - "test(room): cover delete dialog dismiss"
    - "chore(specs): record cancel dialog context"
    - "chore(specs): record cancel dialog progress"
    - "chore(specs): record cancel dialog reviews"
    - "chore(specs): record cancel dialog spec"
    - "chore(specs): record cancel dialog tasks"
    - "chore(specs): record cancel dialog checks"
    - "chore(specs): record cancel dialog validation"
  tests:
    unit:
      - "Reservation/Index Voltar hides the cancel dialog and does not PATCH"
      - "Reservation/Index successful cancel onSuccess hides the cancel dialog"
      - "Reservation/Index failed cancel keeps the dialog and the retry alert"
      - "Reservation/Index processing keeps the dialog, disables both actions, and shows Cancelando..."
      - "Room/Index Cancelar hides the delete dialog and does not DELETE"
      - "Room/Index successful delete onSuccess hides the delete dialog"
    integration: []
    e2e: []
  tests_not_applicable:
    integration: "No controller, FormRequest, use-case, or MySQL change. PATCH /reservations/{id}/cancel and DELETE /rooms/{id} already have Feature coverage. Re-asserting those HTTP flows would overlap integration.md and would not prove dialog unmount."
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Dialog dismiss is React state with Inertia mocked — unit.md. Do not bootstrap Playwright in this task."
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

Close the reservations-list cancel dialog when **Voltar** is used (already wired; assert it) and when the cancel PATCH succeeds (missing `onSuccess`). Apply the same dismiss on the rooms-list delete dialog (`Cancelar` / successful `Excluir sala`). Keep dialogs open while processing and on reservation cancel failure. PHP is unchanged.

**Design (inline, no `design.md`):**

- `confirmCancel` passes `onSuccess` into `cancel(form, pending.id, options)` that `setPending(null)` and `setCancelError('')`.
- `confirmDelete` passes `onSuccess` into `destroy(form, pending.id, options)` that `setPending(null)`.
- Do not set `preserveState: false`.
- Do not close on confirm click before the request finishes.
- `closeCancel` / `closeDelete` stay the dismiss paths for `Voltar` / `Cancelar` / `Escape` and still no-op when `form.processing`.
- Extend `Reservation/Index.test.jsx` and `Room/Index.test.jsx`. Mock `form.patch` / `form.delete` and call `options.onSuccess()` for the success cases.

## Affected Components

- `app` — `resources/js/Pages/Reservation/Index.jsx`, `resources/js/Pages/Reservation/Index.test.jsx`, `resources/js/Pages/Room/Index.jsx`, `resources/js/Pages/Room/Index.test.jsx`.
- Do not change `Services/reservations.js` or `Services/rooms.js` unless options cannot be forwarded (they already can).
- Do not change PHP controllers or Feature cancel/delete tests.

## Tasks

Execute T1 then T2 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. Vitest + Testing Library. Inertia `useForm` / `router` mocked. Existing Escape-opens-and-patches case may be extended; do not drop it. PHP Unit suite stays green; no new PHP unit cases.

### Integration

Not applicable — see `harness.tests_not_applicable.integration`. Feature cancel tests remain a regression gate only.

### E2E

Not applicable — see `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Frontend coverage via `npm run test:coverage` (≥80%). PHP Unit coverage remains Application-only (≥80%).

## Definition of Done

- AC-001–AC-006 each have a React unit assertion; no duplicated scenario at integration or e2e.
- `Voltar` / rooms `Cancelar` and success hide `role="dialog"`; reservation failure and processing still keep it.
- Pint, ESLint, PHP build, and Vite build pass.
- No product commit in PLAN.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `phpunit.xml` (Application coverage ≥80%), `vite.config.js` / `package.json` (`npm run test:coverage`, thresholds 80%).

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Reservation Index cancel dialog | unit | Voltar dismiss, success dismiss, failure stays, processing stays | `resources/js/Pages/Reservation/Index.test.jsx` | `npm run test:coverage` |
| Room Index delete dialog | unit | Cancelar dismiss, success dismiss | `resources/js/Pages/Room/Index.test.jsx` | `npm run test:coverage` |
| CancelReservation use case | none | Unchanged; existing unit file is regression only | `tests/Unit/Reservation/CancelReservationTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| Cancel HTTP + MySQL | none | Unchanged; existing Feature file is regression only | `tests/Feature/Reservation/ReservationCancelHttpTest.php` | `php artisan test --testsuite=Feature` |
| Playwright E2E | none | No suite in repo | — | not applicable |

## Gate Check Commands

> Generated from `harness/stack.yml`, `composer.json`, `package.json`.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After the React unit task | `npm run test:coverage` |
| Full | After Execute, before review | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run test:coverage` |
| Build | Phase end / lint | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Dialog dismiss

```
T1
T2
```

---

## Task Breakdown

### Phase 1: Dialog dismiss

#### T1: Dismiss cancel dialog on Voltar and success

**What**: In `confirmCancel`, pass Inertia `onSuccess` that clears `pending` and `cancelError`. Keep `closeCancel` for `Voltar` / `Escape`. Extend `Index.test.jsx` so `Voltar` and `onSuccess` hide `role="dialog"`, and keep the existing failure + processing cases.
**Where**: `resources/js/Pages/Reservation/Index.jsx`
**Depends on**: None
**Reuses**: `closeCancel`, `cancel()` option forwarding, existing `Index.test.jsx` form mock
**Requirement**: CANCEL-01, CANCEL-02, CANCEL-03, CANCEL-04

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `onSuccess` clears the dialog after a successful confirm
- [x] `Voltar` still calls `closeCancel` when not processing
- [x] Vitest covers all four `harness.tests.unit` behaviors
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

#### T2: Dismiss room delete dialog on Cancelar and success

**What**: In `confirmDelete`, pass Inertia `onSuccess` that clears `pending`. Keep `closeDelete` for `Cancelar` / `Escape`. Extend `Room/Index.test.jsx` so `Cancelar` and `onSuccess` hide `role="dialog"`.
**Where**: `resources/js/Pages/Room/Index.jsx`
**Depends on**: None
**Reuses**: `closeDelete`, `destroy()` option forwarding, existing `Index.test.jsx` form mock
**Requirement**: ROOM-DEL-01, ROOM-DEL-02

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `onSuccess` clears the dialog after a successful delete
- [x] `Cancelar` still calls `closeDelete` when not processing
- [x] Vitest covers the two new `harness.tests.unit` room behaviors
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

## Phase Execution Map

```
Phase 1

Phase 1:  T1 → T2
```

Single batch (2 tasks). Execute inline.

## Task Granularity Check

| Task | Scope | Status |
| --- | --- | --- |
| T1: Dismiss cancel dialog on Voltar and success | 1 page callback + its Vitest file | Granular |
| T2: Dismiss room delete dialog on Cancelar and success | 1 page callback + its Vitest file | Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| --- | --- | --- | --- |
| T1 | None | No inbound arrow | Match |
| T2 | None | No inbound arrow | Match |

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| --- | --- | --- | --- | --- |
| T1: Dismiss cancel dialog | Reservation Index cancel dialog | unit | unit | OK |
| T2: Dismiss room delete dialog | Room Index delete dialog | unit | unit | OK |
