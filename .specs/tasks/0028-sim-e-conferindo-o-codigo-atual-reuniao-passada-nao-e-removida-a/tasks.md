---
harness:
  commits:
    - "feat(reservation): add listing status filter for cancelled history"
    - "test(reservation): cover listing status filter"
    - "chore(docs): document reservation list status filter"
    - "chore(specs): record reservation list status filter context"
    - "chore(specs): record reservation list status filter progress"
    - "chore(specs): record reservation list status filter reviews"
    - "chore(specs): record reservation list status filter spec"
    - "chore(specs): record reservation list status filter tasks"
    - "chore(specs): record reservation list status filter checks"
    - "chore(specs): record reservation list status filter validation"
  tests:
    unit:
      - "IndexReservationRequest accepts status all, active, and cancelled, omits status when absent, and rejects unknown status with Informe um status válido."
      - "IndexReservationRequest still accepts period all, today, tomorrow, week and a complete starts_on/ends_on range independently of status"
      - "ListReservations forwards status all, active, and cancelled to listPage, defaults status to active, and clamps page to 1"
      - "ListReservations default/active returns only active rows; all returns active and cancelled; cancelled returns only cancelled"
      - "ListReservations hasAny is true when only cancelled rows exist and false when the catalog is empty"
      - "Reservation/Index Status select shows Todas, Ativas, and Canceladas, defaulting the control to Ativas"
      - "Reservation/Index changing Status to Canceladas visits with status cancelled, page 1, and keeps room, period, range, and limit"
      - "Reservation/Index Ativas omits status from the visit; Todas visits with status=all; Limpar filtros omits status"
      - "Reservation/Index cancelled row shows Cancelada, hides Editar and Cancelar, and shows a dash"
      - "Reservation/Index Próxima keeps status=cancelled or status=all in the pagination href and omits status when Ativas"
      - "Reservation/Index keeps Período radios Todos, Hoje, Amanhã, and 1 semana plus Data inicial and Data final"
    integration:
      - "GET /reservations with omitted status lists only cancelled_at null rows and echoes filters.status active"
      - "GET /reservations?status=all lists active and cancelled rows as Ativa/Cancelada"
      - "GET /reservations?status=active returns only cancelled_at null rows"
      - "GET /reservations?status=cancelled returns only cancelled_at not-null rows"
      - "GET /reservations?period=today with omitted status still restricts starts_at to today and excludes a same-day cancelled row"
      - "GET /reservations?period=today&status=cancelled still restricts starts_at to today and returns the same-day cancelled row"
      - "GET /reservations?period=all after cancel, room deactivate-cancel, or room delete-cancel still hides those rows; the same request with status=all lists them as Cancelada"
      - "GET /reservations?status=weekend returns 422 with status Informe um status válido wired on the route"
      - "PATCH cancel still leaves cancelled_at set and allows a new reservation on the same interval"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Status filter, labels, default Ativas, and cancel-still-listed on status=all are covered by Vitest plus Feature HTTP on MySQL. Period/range stay covered by existing unit and Feature tests. docs/test/e2e.md forbids repeating that matrix in the browser. Do not bootstrap Playwright in this task."
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

Add listing `status` (`all` | `active` | `cancelled`) so cancelled history is visible when asked for. Remove the hardcoded `whereNull('cancelled_at')` from `listPage()` only. Default omitted status to **`active`**. Map Inertia row status from `cancelledAt`. Add a Status select on `Reservation/Index`. Occupancy SQL stays exclusive of cancelled rows. Keep the existing Período radiogroup (`Todos` / `Hoje` / `Amanhã` / `1 semana`) and `Data inicial` / `Data final`. Do not add a past-meetings radio, a `yesterday` preset, a `completed` state, or a rooms default change.

**Design (inline, no `design.md`):**

- `IndexReservationRequest`: `'status' => ['sometimes', 'in:all,active,cancelled']`, message `Informe um status válido.` Do not change `period`, `starts_on`, `ends_on`, `room_id`, `page`, or `limit` rules.
- Controller: `$status = $request->validated('status') ?? 'active'`; `$period = $request->validated('period') ?? 'today'`; pass into `ListReservations`; echo `filters.status` and `filters.period`. `toListItem`: `cancelledAt !== null` → `cancelled` / `Cancelada`, else `active` / `Ativa`.
- `ListReservations::execute(..., string $timezone, string $status = 'active')` forwards `$status` to `listPage`. `resolveWindow` stays as it is.
- `ReservationRepository::listPage(..., string $status = 'active')`. Eloquent: `active` → `whereNull('cancelled_at')`; `cancelled` → `whereNotNull('cancelled_at')`; `all` → no cancelled predicate. Keep `starts_at` window, `room_id`, and `starts_at,id` order. `hasAny()` → `exists()` with no cancelled predicate. Do not change `hasActiveOverlap` or room-lifecycle cancel queries.
- Fake repository: same filter + `hasAny` so unit tests are honest. Capture `status` in `$listed`.
- React: Status `<select id="status">` in the existing filter card, options Todas/Ativas/Canceladas, `value={filters.status ?? 'active'}`. Omit `status` from the query when `active`; include `status=all` and `status=cancelled` (do not copy rooms omit-when-`all`). Thread `status` through `visitFilters`, `retry`, `listingHref`. `Limpar filtros` omits `status` and visits `period=today`. Keep `PERIODS`, Período radiogroup, and date inputs. Keep existing `RowActions` / `StatusBadge`.
- Rewrite `screen-reservations-list.md` hide-on-cancel / Ativa-only paragraphs. Document Status as a server filter (default Ativas). Align the stale Filters `date` row with Período + `starts_on`/`ends_on`. Do not document a past-meetings radio.
- Keep Feature cases that omit `status` as Ativas (still hide cancelled). Add `status=all` / `cancelled` cases. Keep `ReservationCancelHttpTest` overlap reuse as the occupancy contract.

## Affected Components

- `app` — Reservation HTTP request/controller, `ListReservations`, repository interface + Eloquent `listPage`/`hasAny`, `Reservation/Index.jsx`, PHP Unit + Feature Reservation tests, `Index.test.jsx`, `docs/screens/screen-reservations-list.md`.
- `visitIndex` in `reservations.js` stays a pass-through. Occupancy, cancel PATCH, period/range window resolution, and the rooms module stay as-is.

## Tasks

Execute T1 → T5 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. PHP Unit stays isolated (fake repository, FormRequest without a route). Vitest keeps `@inertiajs/react` mocked. Do not unit-test Eloquent SQL. Existing period-enum, range, and Período-radiogroup tests stay; do not weaken them.

### Integration

See `harness.tests.integration`. Real GET `/reservations` + MySQL 8. Occupancy reuse stays in existing `ReservationCancelHttpTest` (do not duplicate the overlap matrix). One 422 for invalid `status`. Omitted status remains the hide-cancelled contract. `status=all` and `status=cancelled` prove history. One `period=today&status=cancelled` case proves the window still applies. Do not re-run the full period-preset matrix as new tests.

### E2E

Not applicable. See `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. PHP coverage remains Application-only (≥80%). Frontend coverage via `npm run test:coverage` (≥80%). Do not run `npx playwright test`.

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

- AC-001, AC-007, AC-015: omitted/`active` hide cancelled (default Ativas).
- AC-002, AC-003, AC-004, AC-005, AC-008, AC-013: `all`/`cancelled` list + labels + hasAny + cancel-still-listed on `status=all`.
- AC-006: occupancy still frees cancelled intervals (existing Feature cancel test green).
- AC-009…AC-012: 422, Status select, query preservation, cancelled row actions.
- AC-014: Período radios and Data inicial/Data final stay; no past-meetings radio.
- Screen doc matches the new filter. Gates above pass. No product commit in PLAN.

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
| IndexReservationRequest | unit | accept all/active/cancelled; omit; reject unknown with Portuguese message; period/range rules unchanged | `tests/Unit/Reservation/IndexReservationRequestTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| ListReservations + FakeReservationRepository | unit | forward status; default active; all/active/cancelled membership; page clamp; hasAny including cancelled-only | `tests/Unit/Reservation/ListReservationsTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| IndexReservationController + Eloquent listPage | integration | GET omitted/active hide cancelled; all/cancelled membership; today window still applies; cancel-still-listed on status=all; one 422 | `tests/Feature/Reservation/ReservationIndexHttpTest.php` | `php artisan test --testsuite=Feature` |
| Occupancy after cancel | integration | existing cancel-then-reuse interval (no new overlap matrix) | `tests/Feature/Reservation/ReservationCancelHttpTest.php` | `php artisan test --testsuite=Feature` |
| Reservation/Index.jsx | unit | Status select default Ativas; visit/omit/pagination; Cancelada badge and hidden actions; keep Período radios and date range | `resources/js/Pages/Reservation/Index.test.jsx` | `npm run test:coverage` |
| screen-reservations-list.md | none | Screen contract only | `docs/screens/screen-reservations-list.md` | build / lint |
| Playwright e2e | none | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After PHP unit tasks | `php artisan test --testsuite=Unit --coverage --min=80` |
| Quick | After React unit tasks | `npm run test:coverage` |
| Full | After controller / Eloquent / HTTP tasks | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run test:coverage` |
| Build | After docs / phase end | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Backend listing status

```
T1 -> T2 -> T3
```

### Phase 2: List UI and screen contract

```
T4 -> T5
```

---

## Task Breakdown

### Phase 1: Backend listing status

#### T1: Validate listing status on IndexReservationRequest

**What**: Add optional query `status` with `sometimes` + `in:all,active,cancelled` and Portuguese `status.in` message `Informe um status válido.` Do not change period (`all,today,tomorrow,week`), range, room, page, or limit rules. Do not add `yesterday` or `completed`.
**Where**: `app/Modules/Reservation/Infra/Http/Requests/IndexReservationRequest.php`
**Depends on**: None
**Reuses**: `IndexRoomRequest` status rule/message
**Requirement**: LIST-09, LIST-01, LIST-14

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `status` accepts `all`, `active`, `cancelled`
- [x] omitted `status` is absent from `validated()`
- [x] unknown `status` fails with `Informe um status válido.`
- [x] Existing period/range validation tests still pass
- [x] Gate check passes: `php artisan test --testsuite=Unit --coverage --min=80`

**Tests**: unit
**Gate**: quick

---

#### T2: Apply listing status in ListReservations and listPage

**What**: Add `string $status = 'active'` to `ListReservations::execute` (after timezone) and `ReservationRepository::listPage`. Eloquent `listPage`: `active` → `whereNull('cancelled_at')`; `cancelled` → `whereNotNull('cancelled_at')`; `all` → no cancelled predicate; keep `starts_at` window, `room_id`, and `starts_at,id` order. `hasAny()` counts any row. Fake repository matches that filter so unit tests are not lying. Do not change `resolveWindow`, `hasActiveOverlap`, or cancel-by-room methods. Replace `test_it_clamps_page_to_one_and_excludes_cancelled_at_rows` with forward + default-active membership + hasAny cases. Keep existing today/tomorrow/week/range unit tests.
**Where**: `app/Modules/Reservation/Application/UseCases/ListReservations.php`
**Depends on**: T1
**Reuses**: `ListRooms` / `EloquentRoomRepository` status split; `FakeRoomRepository` listed capture
**Requirement**: LIST-01, LIST-02, LIST-07, LIST-08, LIST-13, LIST-14

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Use case forwards `status` (default `active`)
- [x] Fake + Eloquent apply the three predicates
- [x] `hasAny` is true for cancelled-only catalogues
- [x] Occupancy methods still use `whereNull('cancelled_at')`
- [x] Period/range window resolution is unchanged
- [x] Gate check passes: `php artisan test --testsuite=Unit --coverage --min=80`

**Tests**: unit
**Gate**: quick

---

#### T3: Map cancelledAt on the index controller and Feature flow

**What**: Read `status` from the FormRequest (default `active`), pass it to `ListReservations`, echo `filters.status`. Map each item from `cancelledAt`. Extend `ReservationIndexHttpTest`: omitted/`active` hide cancelled and echo `active`; `status=all` includes a cancelled row as `Cancelada`; `status=cancelled` returns only it; `status=weekend` is 422. Keep `test_index_filters_by_room_with_the_resolved_window_and_excludes_canceled_rows` as omitted-status + `period=today` (still hides the same-day cancelled row); add `period=today&status=cancelled` for window composition. Keep `test_index_hides_rows_canceled_by_standalone_deactivate_and_delete` for omitted `period=all`; add `status=all` so Standalone/Deactivate/Delete remain as `Cancelada`. Keep existing period-preset and range Feature tests. Keep `ReservationCancelHttpTest` interval reuse green (AC-006). Do not add a completed label.
**Where**: `app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php`
**Depends on**: T2
**Reuses**: `IndexRoomController` `filters.status` echo (rooms default stays `all`); existing index Feature helpers
**Requirement**: LIST-01, LIST-02, LIST-03, LIST-04, LIST-05, LIST-06, LIST-07, LIST-08, LIST-09, LIST-13, LIST-14, LIST-15

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Inertia props include `filters.status` and per-row `status` / `status_label` from `cancelledAt`
- [x] Feature cases cover omitted/active hide, all/cancelled membership, today-window composition, cancel-still-listed on `status=all`, and one 422
- [x] Existing cancel-reuse Feature test still passes
- [x] Gate check passes: `php artisan test --testsuite=Feature`

**Tests**: integration
**Gate**: full

---

### Phase 2: List UI and screen contract

#### T4: Add Status select on Reservation/Index

**What**: Add Status `<select>` (Todas / Ativas / Canceladas) in the existing filter card, default `active`. Thread `filters.status` through `visitFilters`, `retry`, `clearFilters`, and `listingHref`. Omit `status` when `active`; include `status=all` and `status=cancelled`. Reset `page` to 1 on status change. Keep the existing Período radiogroup (`Todos` / `Hoje` / `Amanhã` / `1 semana`) and `Data inicial` / `Data final`. Do not add a past-meetings radio or a `yesterday` option. Replace the “does not render a Cancelada row or badge” Vitest with cancelled-row + select + query cases. Change the inactive/`Inativa` fixture to `cancelled` / `Cancelada`. Keep cancel dialog behavior on active rows. Keep existing period/range Vitest cases.
**Where**: `resources/js/Pages/Reservation/Index.jsx`
**Depends on**: T3
**Reuses**: `resources/js/Pages/Room/Index.jsx` select markup; existing `RowActions` / `StatusBadge`. Query omit is **when `active`**, not when `all`.
**Requirement**: LIST-10, LIST-11, LIST-12, LIST-03, LIST-05, LIST-14, LIST-15

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Status select is labelled `Status` with the three options, default Ativas
- [x] Visits omit `status` for Ativas and keep it for Todas/Canceladas across pagination
- [x] Cancelled row shows `Cancelada` and `—` without Editar/Cancelar
- [x] Período radios and date range remain; no extra past-meetings radio
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

#### T5: Document listing status on the reservations screen

**What**: Update `docs/screens/screen-reservations-list.md` so Status is a server filter (`all`/`active`/`cancelled`, default Ativas). Replace “canceled reservations do not appear”, “this list only contains active”, and “row disappears after cancel” with: default Ativas hides cancelled; cancelled rows stay when Todas/Canceladas; `Ativa` green / `Cancelada` slate; actions only on active; cancel on Todas keeps the row; occupancy still frees the slot. Align the stale Filters `date` row with Período presets Todos/Hoje/Amanhã/1 semana and Data inicial/Data final. Do not add `Concluída`, a past-meetings radio, a yesterday preset, or a rooms default change.
**Where**: `docs/screens/screen-reservations-list.md`
**Depends on**: T4
**Reuses**: `docs/screens/screen-rooms-list.md` status filter table (labels only; reservation default is Ativas)
**Requirement**: LIST-02, LIST-03, LIST-05, LIST-10, LIST-12, LIST-14, LIST-15

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Screen doc describes the Status filter (default Ativas) and Cancelada badge
- [x] Hide-on-cancel-without-filter language is gone
- [x] Period/range remain documented as the only time filters
- [x] Gate check passes: `vendor/bin/pint --test` && `npm run lint`

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1 → Phase 2

Phase 1:  T1 -> T2 -> T3
Phase 2:  T4 -> T5
```

T4 depends on T3 (previous phase). Execution is sequential. Five tasks fit one Execute batch.

**How phase-based execution works:**

At Execute, pack phases into ~7-task batches. This feature is one batch (≤8 tasks): run T1–T5 inline. No sub-agents.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: IndexReservationRequest status rule | 1 FormRequest | Granular |
| T2: ListReservations + listPage status | 1 use case (interface/Eloquent/fake must match signature) | Cohesive |
| T3: Index controller mapping + Feature | 1 controller action | Cohesive |
| T4: Index.jsx Status select | 1 page | Granular |
| T5: screen-reservations-list.md | 1 doc | Granular |

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | Phase 1 start | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | T2 | T2 -> T3 | Match |
| T4 | T3 | Cross-phase (no intra-phase arrow required) | Match |
| T5 | T4 | T4 -> T5 | Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | IndexReservationRequest | unit | unit | OK |
| T2 | ListReservations / repository | unit | unit | OK |
| T3 | Index controller + Eloquent HTTP | integration | integration | OK |
| T4 | Reservation/Index.jsx | unit | unit | OK |
| T5 | screen doc | none | none | OK |
