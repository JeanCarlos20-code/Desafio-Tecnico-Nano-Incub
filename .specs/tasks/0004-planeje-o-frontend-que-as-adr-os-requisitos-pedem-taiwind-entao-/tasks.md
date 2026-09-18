---
harness:
  commits:
    - "feat(user): center register card in the viewport"
    - "test(user): cover register viewport centering"
    - "feat(resources): add responsive app shell without overflow"
    - "test(resources): cover app shell responsive padding"
    - "feat(reservation): wrap reservations stub in app layout"
    - "test(reservation): cover reservations stub layout shell"
    - "chore(specs): record register layout centering task artifacts"
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

Keep Tailwind CSS v4 (`@tailwindcss/vite` + `@import 'tailwindcss'`). Do not reinstall or switch to v3. Center the `/register` card with a full-viewport flex shell (`min-h-dvh` or `min-h-screen`, `items-center`, `justify-center-safe` or equivalent), keep the existing `lg` two-column card, and wrap `Reservation/Index` in a responsive `AppLayout`. Cover the new layout contracts with Vitest. Do not change register POST, validation, or session.

**Design (inline, no `design.md`):** `User/Create` stays a dedicated split-card page (not `AppLayout`). The outer wrapper is the centering container; `[data-layout="register-card"]` keeps `mx-auto max-w-5xl` and `lg:grid-cols-[minmax(0,44%)_minmax(0,56%)]`. `AppLayout` is the admin shell for `Reservation/Index` only. Optional `@theme` tokens in `app.css` are allowed if they stay inside the existing CSS entry; they are not a separate product feature.

## Affected Components

- `app` — `resources/js/Pages/User/Create.jsx`, `resources/js/Pages/User/Create.test.jsx`, `resources/js/Layouts/AppLayout.jsx`, new `resources/js/Layouts/AppLayout.test.jsx`, `resources/js/Pages/Reservation/Index.jsx`, `resources/js/Pages/Reservation/Index.test.jsx`. Optional: `resources/css/app.css` `@theme` only if Execute needs brand tokens.

## Tasks

See **Task Breakdown**. Execute T1, then T2 → T3. Three tasks fit one Execute batch.

## Planned Tests

- Vitest + RTL on `Create`: page shell uses flex + full viewport height + axis centering classes; `overflow-x-hidden`; horizontal padding (`px-6` = 24px); keep existing two-column / hero / `w-full` assertions; keep all existing form tests.
- Vitest on `AppLayout`: renders `children`; with `title` shows heading; shell has `min-h-screen`, padding, `overflow-x-hidden`, max width on `main`.
- Vitest on `Reservation/Index`: heading `Reservas` still present; page is wrapped so the layout shell exists (render Index and assert the heading lives inside a `min-h-screen` ancestor from `AppLayout`).
- PHPUnit Unit + Feature: run as gates; **no new HTTP cases** unless a change breaks Inertia (it must not). Do not delete existing tests.
- Do not add Playwright.

## Required Gates

| Gate | Command | Required |
| ---- | ------- | -------- |
| frontend | `npm test` | yes |
| unit | `php artisan test --testsuite=Unit` | yes |
| integration | `php artisan test --testsuite=Feature` | yes |
| lint | `vendor/bin/pint --test` | yes |
| build | `npm run build` | yes |

Do not require `npx playwright test` (no Playwright project).

## Definition of Done

- UI-01…UI-10 have tests or an explicit config check (UI-09 pipeline files).
- Gates above pass. No silent test deletions.
- Register card is centered in the viewport; both existing React screens are overflow-safe and responsive.
- `harness.commits` matches dirty-path grouping (`user` / `layouts` / `reservation`; tests separate).
- No product commit from this Plan phase.

---

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

---

**Design**: inline in Summary (MVP; no `design.md`)
**Status**: Implemented

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `docs/reviews/review-tests.md`, `harness/stack.yml`, `vite.config.js` Vitest include `resources/js/**/*.test.{js,jsx}`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| React page `User/Create` layout | unit | Centering, padding, overflow-x, two-column vs one-column, hero visibility; all listed layout edge cases; existing form tests remain | `resources/js/Pages/User/Create.test.jsx` | `npm test` |
| React layout `AppLayout` | unit | Renders children; title optional; min-h-screen, padding, overflow-x-hidden, max width | `resources/js/Layouts/AppLayout.test.jsx` | `npm test` |
| React page `Reservation/Index` | unit | Heading `Reservas`; wrapped in AppLayout shell | `resources/js/Pages/Reservation/Index.test.jsx` | `npm test` |
| Vite / Tailwind entry | none | Build gate proves CSS compiles; do not add a second design system | `vite.config.js`, `resources/css/app.css`, `package.json` | `npm run build` |
| HTTP controller / Form Request | none (unchanged) | Existing Feature tests must still pass | `tests/Feature/User/CreateUserHttpTest.php` | `php artisan test --testsuite=Feature` |
| Playwright e2e | none | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After React unit tasks | `npm test` && `php artisan test --testsuite=Unit` |
| Full | After page wiring that could affect Inertia HTML | `npm test` && `php artisan test --testsuite=Feature` && `php artisan test --testsuite=Unit` |
| Build | Phase end / CSS entry changes | `npm run build` && `vendor/bin/pint --test` && `php artisan test` && `npm test` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Register centering

```
T1
```

### Phase 2: Shared responsive shell

```
T2 -> T3
```

---

## Task Breakdown

### Phase 1: Register centering

#### T1: Center the register card in the viewport

**What**: Change the `User/Create` page shell to a Tailwind flex container that fills the viewport and centers `[data-layout="register-card"]` on both axes; keep ≥24px horizontal padding (`px-6`), `overflow-x-hidden`, and the existing `lg` two-column / mobile hero-hidden classes. Extend `Create.test.jsx` with centering assertions. Do not change form submit logic.
**Where**: `resources/js/Pages/User/Create.jsx`
**Depends on**: None
**Reuses**: existing `data-layout="register-card"` card, `BrandPanel`, Tailwind v4 utilities already on the page
**Requirement**: UI-01, UI-02, UI-03, UI-04, UI-05, UI-06, UI-09, UI-10

**Tools**:

- MCP: `context7` (Tailwind v4 `min-h-dvh` / `min-h-screen`, `items-center`, `justify-center-safe`)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Page shell uses Tailwind flex + full viewport height + centering (`items-center` and `justify-center` or `justify-center-safe`)
- [x] Card remains `max-w-5xl` and `lg:grid-cols-[minmax(0,44%)_minmax(0,56%)]`; hero stays `hidden lg:flex`
- [x] `Create.test.jsx` asserts centering / padding / overflow-x and keeps prior layout and form tests
- [x] Gate check passes: `npm test` && `php artisan test --testsuite=Unit`
- [x] Test count: no silent deletions

**Tests**: unit
**Gate**: quick

**Commit**: `feat(user): center register card in the viewport` then `test(user): cover register viewport centering`

---

### Phase 2: Shared responsive shell

#### T2: Make AppLayout a responsive admin shell

**What**: Update `AppLayout` so it fills the viewport, applies horizontal padding, sets `overflow-x-hidden`, and constrains the main column with a max width. Add `AppLayout.test.jsx` (children always render; optional title; layout classes).
**Where**: `resources/js/Layouts/AppLayout.jsx`
**Depends on**: None
**Reuses**: current `AppLayout` props `{ children, title }`
**Requirement**: UI-08, UI-09, UI-10

**Tools**:

- MCP: `context7` (Tailwind v4 spacing / overflow / max-width utilities)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Shell uses Tailwind `min-h-screen`, horizontal padding, `overflow-x-hidden`, and a max width on `main`
- [x] `AppLayout.test.jsx` covers children, optional title, and those layout classes
- [x] Gate check passes: `npm test` && `php artisan test --testsuite=Unit`
- [x] Test count: no silent deletions

**Tests**: unit
**Gate**: quick

**Commit**: `feat(layouts): add responsive app shell without overflow` then `test(layouts): cover app shell responsive padding`

---

#### T3: Wrap the reservations stub in AppLayout

**What**: Render `Reservation/Index` inside `AppLayout` (title `Reservas` or keep the inner heading). Update `Index.test.jsx` so the stub heading still appears and the AppLayout shell is present. Do not add reservation CRUD.
**Where**: `resources/js/Pages/Reservation/Index.jsx`
**Depends on**: T2
**Reuses**: `resources/js/Layouts/AppLayout.jsx` from T2
**Requirement**: UI-07, UI-08

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `Index.jsx` imports and wraps content with `AppLayout`
- [x] Heading `Reservas` remains
- [x] `Index.test.jsx` asserts the heading and a `min-h-screen` ancestor
- [x] Gate check passes: `npm test` && `php artisan test --testsuite=Feature` && `php artisan test --testsuite=Unit`
- [x] Test count: no silent deletions

**Tests**: unit
**Gate**: full

**Commit**: `feat(reservation): wrap reservations stub in app layout` then `test(reservation): cover reservations stub layout shell`

---

## Phase Execution Map

```
Phase 1 → Phase 2

Phase 1:  T1
Phase 2:  T2 -> T3
```

Execution is strictly sequential — no intra-phase parallelism. Three tasks fit one Execute batch (no sub-agent split).

**How phase-based execution works:** one worker runs T1–T3 in order (implement → gate → mark done). After the last task, a fresh Verifier runs (author ≠ verifier).

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Center the register card in the viewport | one page + colocated Vitest file | OK cohesive (one screen) |
| T2: Make AppLayout a responsive admin shell | one layout + colocated test | Granular |
| T3: Wrap the reservations stub in AppLayout | one page + colocated test | Granular |

Granularity check: do not split a page from its Vitest file. `Where` names the production file; tests are written in the same task.

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (start of phase 1) | Match |
| T2 | None | (start of phase 2) | Match |
| T3 | T2 | T2 -> T3 | Match |

Dependencies point backward or within the same phase only. T1 is independent of T2/T3.

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1: Center the register card | React page `User/Create` layout | unit | unit | OK |
| T2: AppLayout shell | React layout `AppLayout` | unit | unit | OK |
| T3: Reservations stub wrap | React page `Reservation/Index` | unit | unit | OK |

No `Tests: none` on a layer that the matrix requires tests for.
