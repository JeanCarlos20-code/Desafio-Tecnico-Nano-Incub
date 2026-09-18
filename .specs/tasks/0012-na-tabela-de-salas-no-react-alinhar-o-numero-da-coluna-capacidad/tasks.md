---
harness:
  commits:
    - "fix(room): align capacity and center actions column"
    - "test(room): cover rooms table column alignment"
    - "chore(docs): document rooms table wide-layout columns"
    - "chore(specs): record rooms table alignment context"
    - "chore(specs): record rooms table alignment progress"
    - "chore(specs): record rooms table alignment reviews"
    - "chore(specs): record rooms table alignment spec"
    - "chore(specs): record rooms table alignment tasks"
    - "chore(specs): record rooms table alignment checks"
    - "chore(specs): record rooms table alignment validation"
  tests:
    unit:
      - "Room/Index Capacidade header and capacity cells share text-center"
      - "Room/Index Nome keeps leftover-width priority via w-full and min-w-0 without xl:w-[16%]"
      - "Room/Index Capacidade, Status, Criada em, and Ações stay compact with w-0 and use xl:w-[16%] nowrap"
      - "Room/Index Ações header and cells share text-center and do not use text-right"
      - "Room/Index still uses table-auto, overflow-x-auto, md:block table, and md:hidden mobile cards"
    integration: []
    e2e: []
  tests_not_applicable:
    integration: "CSS-only change on Room/Index.jsx. No Laravel route, middleware, FormRequest, controller, use case, or MySQL behavior changes. Existing Feature RoomIndexHttpTest remains the list HTTP contract. Do not add a React integration suite."
    e2e: "No Playwright project, config, or dependency exists in the repo. stack.yml lists npx playwright test but it is not runnable. Column alignment is a React class contract covered by Vitest. Do not bootstrap Playwright in this task."
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

On the desktop rooms table, drop `w-0` from `Capacidade`, `Status`, `Criada em`, and `Ações`. Give `Capacidade` th/td explicit shared `text-left` so the integer sits under the header. Keep `Nome` as `min-w-0` with no max-width. At Tailwind `xl`, apply `xl:w-[16%]` to the four secondary columns so leftover is no longer exclusive to `Nome`. Replace `text-right` on `Ações` th/td with shared `text-center` so the header and `RowActions` (`inline-flex`) sit in the center of the column. Keep `table-auto`, `whitespace-nowrap` on secondary headers, `overflow-x-auto`, and mobile cards. Replace the Vitest `w-0` and `text-right` compact-column assertions. Document the desktop rule in `screen-rooms-list.md`, including the override of “actions aligned on the right”.

**Design (inline, no `design.md`):** Keep `table-auto`. Do not use `table-fixed`. Mirror the same width classes on each secondary th and its td. Do not change `Index.jsx` data, dialogs, or pagination. Do not rewrite `RowActions`.

## Affected Components

- `app` — `resources/js/Pages/Room/Index.jsx`, `resources/js/Pages/Room/Index.test.jsx`, `docs/screens/screen-rooms-list.md`. PHP rooms module stays as-is.

## Tasks

Execute T1 → T2 as in **Task Breakdown**.

## Planned Tests

### Unit

- `Capacidade` header and a capacity cell share `text-center`
- `Nome` keeps `w-full` + `min-w-0` and does not receive `xl:w-[16%]`
- `Capacidade`, `Status`, `Criada em`, and `Ações` headers/cells include `w-0` and `xl:w-[16%]`; secondary headers keep `whitespace-nowrap`
- `Ações` header and an actions cell share `text-center` and do not include `text-right`
- Table stays `w-full table-auto`; scroll parent `overflow-x-auto`; desktop surface `md:block`; mobile list `md:hidden`
- Existing Index cases stay (empty state, delete dialog, pagination, load failure, no room id)

### Integration

Not applicable — no Laravel/MySQL change. See `harness.tests_not_applicable.integration`. Existing Feature rooms tests remain green.

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

- AC-001…AC-007 have Vitest coverage at the unit level, or remain existing Index contracts (AC-006).
- Capacity number lines up with `Capacidade`; `Nome` still grows first; `xl` shares a little leftover with the other four columns; `Ações` is centered.
- Gates above pass. The old `w-0` and `text-right` assertions are replaced, not deleted without a successor.
- `harness.commits` matches dirty-path grouping (`room` production / `room` tests / `docs` / `specs`).
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

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `docs/reviews/review-tests.md`, `harness/stack.yml`, `phpunit.xml`, `package.json`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| React rooms list table | unit | Capacity/header alignment; Nome leftover priority; xl share on secondary columns; Ações text-center; keep scroll/mobile; existing Index cases remain | `resources/js/Pages/Room/Index.test.jsx` | `npm run test:coverage` |
| Room HTTP / use cases | none | Unchanged; existing Unit + Feature suites remain | `tests/Unit/Room/*`, `tests/Feature/Room/*` | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` |
| Screen doc | none | Contract text only | `docs/screens/screen-rooms-list.md` | build / lint |
| Playwright e2e | none | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After React unit tasks | `npm run test:coverage` |
| Full | After page + existing HTTP suites | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run test:coverage` |
| Build | Phase end / lint / docs | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Align, share leftover, and center actions

```
T1 -> T2
```

---

## Task Breakdown

### Phase 1: Align, share leftover, and center actions

#### T1: Align capacity, share xl leftover, center actions

**What**: On the desktop rooms table in `Index.jsx`, remove `w-0` from `Capacidade`, `Status`, `Criada em`, and `Ações` th/td. Add shared `text-left` on the `Capacidade` header and capacity cells. Keep `Nome` as `min-w-0` with no max-width and no `xl:w-[16%]`. Add `xl:w-[16%]` to the four secondary columns (header and cells). Keep `whitespace-nowrap` on those headers, `table-auto`, and `overflow-x-auto`. Replace `text-right` on the `Ações` header and cells with `text-center`. Do not rewrite `RowActions`. Do not change mobile cards, dialogs, or props. Replace the Vitest case that requires `w-0` and `Ações` `text-right` with assertions for capacity `text-left`, no `w-0` on the four secondary columns, `Nome` `min-w-0`, `xl:w-[16%]` on those four, `Ações` `text-center` (header and cell), plus the existing scroll/mobile/`table-auto` checks.
**Where**: `resources/js/Pages/Room/Index.jsx`
**Depends on**: None
**Reuses**: current Index table markup; `Index.test.jsx` compact-column case
**Requirement**: ROOM-01, ROOM-02, ROOM-03, ROOM-04, ROOM-05

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `Capacidade` header and capacity cells share `text-left` and do not use `w-0`
- [x] `Nome` keeps `min-w-0`; secondary columns use `xl:w-[16%]`
- [x] `Ações` header and cells share `text-center` and do not use `text-right`
- [x] Existing Index behaviors (empty, delete, pagination, failure, no id) still pass
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

#### T2: Document rooms table width sharing

**What**: Update `docs/screens/screen-rooms-list.md` desktop/responsiveness rules: capacity integers align with the `Capacidade` header (`text-left`); `Nome` keeps leftover-width priority; at `xl`, `Capacidade`, `Status`, `Criada em`, and `Ações` receive `xl:w-[16%]`; `Ações` header and row actions are centered (`text-center`), replacing “keep row actions aligned on the right”; horizontal scroll and mobile cards stay. Do not rewrite create/edit screen docs.
**Where**: `docs/screens/screen-rooms-list.md`
**Depends on**: T1
**Reuses**: existing Rooms List Screen responsiveness section
**Requirement**: ROOM-03, ROOM-01, ROOM-05

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Screen doc states capacity/header alignment, Nome priority, xl leftover sharing, and centered Ações
- [x] The old “aligned on the right” desktop rule is replaced
- [x] Mobile cards and no-id rule remain
- [x] Gate check passes: `vendor/bin/pint --test` && `npm run lint`

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1

Phase 1:  T1 -> T2
```

Execution is strictly sequential.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Align capacity, share xl leftover, center actions | 1 page + its Vitest file | Granular |
| T2: Document rooms table width sharing | 1 markdown file | Granular |

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (start) | Match |
| T2 | T1 | T1 -> T2 | Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | React rooms list table | unit | unit | OK |
| T2 | Screen doc | none | none | OK |
