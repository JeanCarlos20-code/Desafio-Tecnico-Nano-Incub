---
harness:
  commits:
    - "feat(reservation): add period presets and optional range filter"
    - "test(reservation): cover reservation list period and range"
    - "chore(specs): record reservation list filter context"
    - "chore(specs): record reservation list filter progress"
    - "chore(specs): record reservation list filter reviews"
    - "chore(specs): record reservation list filter spec"
    - "chore(specs): record reservation list filter tasks"
    - "chore(specs): record reservation list filter checks"
    - "chore(specs): record reservation list filter validation"
  tests:
    unit:
      - "ListReservations with period=all and no range forwards null listPage bounds"
      - "ListReservations resolves today, tomorrow, and week half-open windows from Clock in the given timezone"
      - "ListReservations uses the inclusive starts_on/ends_on window and ignores period when both dates are present"
      - "ListReservations clamps page to 1 and still excludes cancelled_at rows"
      - "IndexReservationRequest accepts period all|today|tomorrow|week and a complete Y-m-d range"
      - "IndexReservationRequest rejects unknown period, one-sided range, inverted range, and malformed dates"
      - "Reservation/Index preset click writes period, omits range dates, and resets page to 1"
      - "Reservation/Index complete range writes starts_on and ends_on as Y-m-d with no time and resets page to 1"
      - "Reservation/Index range filter exposes Data inicial and Data final as date-only inputs with no time fields"
      - "Reservation/Index Limpar filtros requests period=all with no room_id or range"
    integration:
      - "GET /reservations with no query lists every cancelled_at-null row and echoes filters.period=all"
      - "GET /reservations?period=today|tomorrow|week returns only actives whose starts_at falls in that local window"
      - "GET /reservations with starts_on and ends_on returns that inclusive range even when period=today is also present"
      - "GET /reservations pagination links keep period, starts_on, ends_on, and room_id"
      - "GET /reservations with an unknown period or a one-sided range returns 422 through IndexReservationRequest"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Preset and range contracts are covered by ListReservations/FormRequest unit tests, Reservation/Index Vitest URL writes, and Feature GET /reservations on MySQL 8. Do not bootstrap Playwright in this task."
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

Replace the reservations list single-day `date` filter with URL presets and an optional inclusive **date-only** range. `ListReservations` plus `Clock` resolve a nullable half-open window. FormRequest validates `period` and the `Y-m-d` pair. The controller stops defaulting to today. React writes `period` / `starts_on` / `ends_on` (no times) and clears the range when a preset is chosen. The visible interval is `dd/mm/yyyy` via native `type="date"`; do not add hour/minute fields.

**Design (inline, no `design.md`):**

- `ListReservations` constructor: `ReservationRepository`, `Clock`.
- `execute(int $page, int $perPage, ?string $roomId, string $period, ?string $startsOn, ?string $endsOn, string $timezone)`.
- Complete range (`startsOn` and `endsOn` both non-null): window = `[startsOn 00:00 tz, endsOn+1 day 00:00 tz)`, ignore `$period`.
- Else `all` → both bounds null; `today` → `[today, +1 day)`; `tomorrow` → `[tomorrow, +1 day)`; `week` → `[today, +7 days)`. `today` = Clock `now()` in `$timezone` at 00:00:00.
- `listPage(..., ?DateTimeImmutable $rangeStart, ?DateTimeImmutable $rangeEndExclusive)`: apply `starts_at` constraints only when both bounds are set.
- Request: `period` sometimes `in:all,today,tomorrow,week`; `starts_on`/`ends_on` nullable `date_format:Y-m-d`, `required_with` each other, `ends_on` `after_or_equal:starts_on`. Remove `date`. Keep `room_id` uuid.
- Controller: `$period = validated('period') ?? 'all'`; do not invent a default day. `filters` = `{ room_id, period, starts_on, ends_on }`. Keep `withQueryString()`. `H:i` when the resolved window is one local day; `d/m/Y H:i` otherwise. Keep row `date` for the cancel dialog.
- React labels: Todos / Hoje / Amanhã / 1 semana; Data inicial / Data final as `type="date"` only (visible `dd/mm/yyyy`, query `Y-m-d`). No `datetime-local` and no time inputs. Preset click clears range. Visit only when both range dates are set. `Limpar filtros` → `{ period: 'all', page: 1 }`.

## Affected Components

- `app` — `ListReservations`, `ReservationRepository`, `EloquentReservationRepository`, `IndexReservationRequest`, `IndexReservationController`, `resources/js/Pages/Reservation/Index.jsx`, `FakeReservationRepository`, Unit/Feature/Vitest reservation index tests.
- Do not change create, cancel, occupancy, room lifecycle, or Playwright.

## Tasks

Execute T1 → T4 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. PHPUnit Unit + Vitest. Use cases use `FakeReservationRepository` + `FakeClock` only. FormRequest via container, not a route. React mocks Inertia.

### Integration

See `harness.tests.integration`. Feature suite on MySQL 8. One 422 case proves the FormRequest is on the route; do not repeat the unit validation matrix. Rewrite existing `?date=` assertions.

### E2E

Not applicable — no Playwright project. See `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Unit coverage remains Application-only. Frontend coverage via `npm run test:coverage` (≥80%).

## Definition of Done

- All P1 ACs have a unit and/or integration test as classified above; no duplicated scenario across levels.
- `GET /reservations` default is all actives; each preset and a range are Feature-tested.
- Existing cancel-exclusion and occupancy Feature tests still pass.
- Pint, ESLint, PHP build, and Vite build pass.
- No product commit in PLAN.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `phpunit.xml` (Application coverage ≥80%), `package.json` (`npm run test:coverage`).

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| ListReservations window | unit | all / today / tomorrow / week / range-override / page clamp / canceled exclusion | `tests/Unit/Reservation/ListReservationsTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| FormRequest input contract | unit | period enum, range pair, order, formats, room_id | `tests/Unit/Reservation/IndexReservationRequestTest.php` | `php artisan test --testsuite=Unit --filter=IndexReservationRequest` |
| React index filters | unit | presets, date-only range, clear, page reset | `resources/js/Pages/Reservation/Index.test.jsx` | `npm run test:coverage` |
| Index controller + MySQL | integration | GET each preset, range override, pagination query, one 422 | `tests/Feature/Reservation/ReservationIndexHttpTest.php` | `php artisan test --testsuite=Feature` |
| Eloquent models / migrations | none | Unchanged schema | — | build gate only |

## Gate Check Commands

> Generated from `harness/stack.yml`, `composer.json`, `package.json`.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After unit-only tasks | `php artisan test --testsuite=Unit --coverage --min=80` |
| Frontend | After React tasks | `npm run test:coverage` |
| Full | After Feature / page+HTTP tasks | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run test:coverage` |
| Build | Phase end / lint | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Use case and request

```
T1 -> T2
```

### Phase 2: HTTP

```
T3
```

### Phase 3: Screen

```
T4
```

---

## Task Breakdown

### Phase 1: Use case and request

#### T1: Resolve list window from period and range

**What**: Inject `Clock` into `ListReservations`. Replace the required `$date` argument with `$period`, `$startsOn`, `$endsOn`, `$timezone` and resolve nullable half-open bounds per Selected Approach. Change `ReservationRepository::listPage` (and Eloquent + `FakeReservationRepository`) so both bounds are nullable; skip `starts_at` constraints when they are null. Unit-test every `harness.tests.unit` ListReservations item.
**Where**: `app/Modules/Reservation/Application/UseCases/ListReservations.php`
**Depends on**: None
**Reuses**: `FakeClock`, `FakeReservationRepository`, existing canceled-exclusion assertions
**Requirement**: RSV-01, RSV-02

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Use case has no Illuminate import
- [x] Fake and Eloquent `listPage` signatures match
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=ListReservations`

**Tests**: unit
**Gate**: quick

---

#### T2: Validate period and range on IndexReservationRequest

**What**: Replace `date` with `period` (`sometimes`, `in:all,today,tomorrow,week`) and `starts_on` / `ends_on` (`nullable`, `date_format:Y-m-d`, `required_with` each other, `ends_on` `after_or_equal:starts_on`). Keep `room_id` uuid. Portuguese messages for invalid period/dates. Unit-test every `harness.tests.unit` IndexReservationRequest item (container, no route).
**Where**: `app/Modules/Reservation/Infra/Http/Requests/IndexReservationRequest.php`
**Depends on**: T1
**Reuses**: `IndexReservationRequestTest` create+`validateResolved` helper
**Requirement**: HTTP-01, RSV-02

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Valid period and complete range pass
- [x] Unknown period, one-sided range, inverted range, and `21/09/2026` fail
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=IndexReservationRequest`

**Tests**: unit
**Gate**: quick

---

### Phase 2: HTTP

#### T3: Wire GET /reservations presets and range

**What**: Controller reads `period` default `all` and the optional range; calls the new `ListReservations` signature; Inertia `filters` = `{ room_id, period, starts_on, ends_on }`; keep paginator `withQueryString()` and page size 15. Format `starts_at`/`ends_at` as `H:i` on a one-day window and `d/m/Y H:i` otherwise; keep row `date`. Rewrite `ReservationIndexHttpTest`: drop `filters.date` / `?date=`; add Feature cases for default all, each preset, range-over-preset, pagination query string, and one 422. Keep hide-canceled coverage on `period=all` or an explicit window.
**Where**: `app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php`
**Depends on**: T1, T2
**Reuses**: `ReservationIndexHttpTest` travelTo 2026-09-21, `withQueryString()`
**Requirement**: RSV-01, RSV-02, RSV-03, HTTP-01

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Default GET lists actives on more than one day
- [x] Feature items for each preset, range override, pagination, and 422 pass
- [x] Gate check passes: `php artisan test --testsuite=Feature --filter=ReservationIndex`

**Tests**: integration
**Gate**: full

---

### Phase 3: Screen

#### T4: Replace the day input with presets and range

**What**: `Reservation/Index.jsx`: remove the `date` input. Add Todos / Hoje / Amanhã / 1 semana (accessible group) writing `period=all|today|tomorrow|week`, clearing `starts_on`/`ends_on`, `page=1`, keeping `room_id`. Below, Data inicial / Data final as `type="date"` only (visible `dd/mm/yyyy`, query `Y-m-d`); no time fields. Visit only when both dates are set; keep last `period` and `room_id`. `Limpar filtros` → `period=all` only. Retry/load-error must send the new query shape. Rewrite `Index.test.jsx` for the React `harness.tests.unit` items including date-only range; keep cancel-dialog and empty-state cases.
**Where**: `resources/js/Pages/Reservation/Index.jsx`
**Depends on**: T3
**Reuses**: `visitIndex` in `Services/reservations.js`, rooms status-filter URL pattern
**Requirement**: UI-01, RSV-03

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] No `date` filter control remains
- [x] Data inicial / Data final are `type="date"` with no time fields
- [x] Vitest preset / range / clear items pass
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: frontend

---

## Phase Execution Map

```
Phase 1 → Phase 2 → Phase 3

Phase 1:  T1 ------→ T2
Phase 2:  T3
Phase 3:  T4
```

Execution is sequential. T3 depends on T1 and T2 (prior phase). T4 depends on T3.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: List window + listPage bounds | One use case + matching port | ✅ Cohesive |
| T2: IndexReservationRequest | One FormRequest | ✅ Granular |
| T3: Index controller + Feature | One controller + existing Feature class | ✅ Granular |
| T4: Reservation/Index filters | One page + its Vitest file | ✅ Granular |

**Granularity check**: each task is one deliverable; T1 also updates Eloquent/Fake so the new port compiles.

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | No inbound arrow | ✅ Match |
| T2 | T1 | T1 → T2 | ✅ Match |
| T3 | T1, T2 | Prior phase only (no intra-phase arrow) | ✅ Match |
| T4 | T3 | T3 prior phase (no intra-phase arrow) | ✅ Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | ListReservations window | unit | unit | ✅ OK |
| T2 | FormRequest input contract | unit | unit | ✅ OK |
| T3 | Index controller + MySQL | integration | integration | ✅ OK |
| T4 | React index filters | unit | unit | ✅ OK |
