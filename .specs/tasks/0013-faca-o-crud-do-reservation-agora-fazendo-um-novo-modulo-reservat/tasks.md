---
harness:
  commits:
    - "feat(app): bind reservation ports and use cases"
    - "feat(database): add reservations table and factory"
    - "chore(phpunit): include reservation application in unit coverage"
    - "feat(reservation): add reservation occupancy error types"
    - "feat(reservation): add reservation transaction port"
    - "feat(reservation): add reservation occupancy use cases"
    - "feat(reservation): add reservation domain entity and ports"
    - "feat(reservation): implement reservation persistence and room lock"
    - "feat(reservation): add reservation controllers and form requests"
    - "feat(reservation): add laravel transaction adapter"
    - "feat(reservation): add laravel clock adapter"
    - "feat(reservation): render reservation list and create screens"
    - "feat(resources): add reservations inertia service"
    - "feat(routes): register reservation create list and cancel routes"
    - "test(database): cover reservations schema"
    - "test(reservation): cover reservation list and create pages"
    - "test(reservation): cover reservation HTTP create list and cancel"
    - "test(reservation): cover reservation occupancy use cases"
    - "test(reservation): cover reservation form request contracts"
    - "test(resources): cover reservations inertia service"
    - "chore(specs): record reservation crud plan artifacts"
  tests:
    unit:
      - "CreateReservation persists a valid future 30-minute booking with cancelled_at null"
      - "CreateReservation rejects starts_at before Clock::now() with O horário inicial não pode estar no passado."
      - "CreateReservation rejects duration below 30 minutes or above 4 hours"
      - "CreateReservation rejects missing, inactive, or over-capacity rooms"
      - "CreateReservation rejects active overlap and accepts consecutive ends_at equals starts_at"
      - "CreateReservation ignores canceled rows when checking overlap"
      - "CancelReservation sets cancelled_at once and is idempotent on repeat"
      - "ListReservations clamps page and forwards room_id plus timezone day bounds"
      - "StoreReservationRequest rejects missing or malformed fields with the spec Portuguese messages"
      - "StoreReservationRequest trims responsible and title and excludes unknown keys"
      - "IndexReservationRequest rejects malformed date or room_id"
      - "Reservation Domain and Application sources do not import Illuminate"
      - "Reservation/Create shows required fields, posts to /reservations, shows errors, Salvando..., and Cancelar to /reservations"
      - "Reservation/Index shows columns, Ativa/Cancelada badges, Cancelar only on active rows, and no calendar icon"
      - "Reservation/Index applies room_id and date filters through the Inertia service and resets page to 1"
      - "Reservation/Index opens the cancel dialog, focuses Voltar, Escape closes, and patches cancel only on confirm"
      - "Reservation/Index distinguishes global empty vs filtered empty vs load error"
    integration:
      - "Authenticated GET /reservations renders Reservation/Index with default date today and starts_at ASC"
      - "GET /reservations?room_id=&date= returns only that room and local day, including canceled rows"
      - "POST /reservations with a valid payload persists UUID v7 ADR-005 columns and flashes Reserva criada com sucesso."
      - "POST /reservations with invalid FormRequest input returns the spec messages and persists nothing"
      - "POST /reservations rejects inactive room, over-capacity, past start, bad duration, and active overlap"
      - "POST /reservations accepts a consecutive slot and a slot that only overlaps a canceled row"
      - "Two concurrent POSTs for the same overlapping room persist exactly one active row"
      - "PATCH /reservations/{id}/cancel sets cancelled_at, keeps the row, and frees the interval"
      - "Repeated PATCH cancel leaves cancelled_at unchanged"
      - "Guest reservation routes redirect to /login without writing rows"
      - "reservations table has exactly the ADR-005 columns and no deleted_at"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Create, list/filter, and cancel are covered by Vitest page tests and Feature HTTP tests including a two-process MySQL concurrency test. Do not bootstrap Playwright in this task."
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

Add module `Reservation` as a Rooms-shaped vertical slice and replace the `GET /reservations` stub with the documented list. Include a minimal create page. Do not edit or delete reservations.

**Design (inline, no `design.md`):**

- Domain: `Reservation` entity; `ReservationRepository`; `OccupancyRoomCatalog` (`lockById`, `listActiveForCreate`, `listFilterOptions`); `Clock`.
- Application: `Transaction` port (`run(callable)`); `CreateReservation`, `ListReservations`, `CancelReservation`; typed Errors. No `Illuminate`.
- `CreateReservation`: validate time/duration against Clock (before DB); `transaction->run`: `catalog->lockById` (`rooms` `lockForUpdate()`), reject missing/inactive/over-capacity, `reservations->hasActiveOverlap` using ADR-005 inequality, then `create`.
- Infra: Eloquent model `HasUuids` (no `SoftDeletes`); `foreignUuid('room_id')->constrained('rooms')`; nullable `cancelled_at`; Laravel Clock/Transaction adapters.
- HTTP: `IndexReservationController`, `CreateReservationController`, `StoreReservationController`, `CancelReservationController`; `IndexReservationRequest`, `StoreReservationRequest`. Map use-case errors with `back()->withErrors`. Bind ports in `AppServiceProvider`. Replace the index closure; keep name `reservations.index`.
- React: `Services/reservations.js` (`visitIndex`, `store` via `form.post`, `cancel` via `form.patch`). `Reservation/Create.jsx` like Room create. Rewrite `Reservation/Index.jsx` from the list screen. Reuse `AppLayout` + `FlashToast`.
- Add `app/Modules/Reservation/Application` to `phpunit.xml` source include.

## Affected Components

- `app` — new `app/Modules/Reservation/**`, `database/migrations/*reservations*`, `database/factories/ReservationFactory.php`, `routes/web.php`, `app/Providers/AppServiceProvider.php`, `phpunit.xml`, `resources/js/Pages/Reservation/*`, `resources/js/Services/reservations.js`, Unit/Feature/Vitest tests. Do not change Room occupancy behavior.

## Tasks

Execute T1 → T8 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. PHPUnit Unit + Vitest; no real HTTP route for FormRequest; use cases use fakes only.

### Integration

See `harness.tests.integration`. Feature suite against MySQL 8. Concurrency must start two PHP processes that POST overlapping creates for the same room (file barrier + `symfony/process`, already a Laravel dependency). Sequential double-calls do not satisfy RNF09.

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

- AC-001…AC-022 have unit and/or integration coverage as classified above.
- Existing login/register tests still hit `reservations.index` / `Reservation/Index`.
- Gates above pass. No silent test deletions.
- `phpunit.xml` includes Reservation Application.
- Domain/Application have no `Illuminate` imports.
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
| CreateReservation / ListReservations / CancelReservation | unit | All occupancy ACs and listed edges; fake clock, catalog, repository, transaction; no real DB | `tests/Unit/Reservation/*` | `php artisan test --testsuite=Unit --coverage --min=80` |
| StoreReservationRequest / IndexReservationRequest | unit | Required/type/date/uuid/min/trim/messages; no `exists` rule | `tests/Unit/Reservation/*RequestTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| React Reservation pages + service | unit | List columns/filters/empty/error/dialog; create fields/errors/processing; mocked Inertia | `resources/js/Pages/Reservation/*.test.jsx`, `resources/js/Services/reservations.test.js` | `npm run test:coverage` |
| Reservation HTTP + persistence + locks | integration | Every new controller action: auth, validation wiring, persist/redirect/flash, overlap, cancel idempotency, schema, two-process concurrency on MySQL 8 | `tests/Feature/Reservation/*`, `tests/Feature/Database/ReservationsMigrationTest.php` | `php artisan test --testsuite=Feature` |
| Eloquent model / migration / bindings | none | Covered by schema Feature tests + build | — | build gate |
| Domain ports / interfaces | none | Illuminate-import unit test only | `tests/Unit/Reservation/ReservationIlluminateImportTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| Playwright e2e | none | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After unit-only tasks | `php artisan test --testsuite=Unit --coverage --min=80` && `npm run test:coverage` |
| Full | After HTTP/Feature or page+HTTP tasks | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run test:coverage` |
| Build | Phase end / lint | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Persistence

```
T1
```

### Phase 2: Application

```
T2 -> T3
```

### Phase 3: HTTP

```
T4 -> T5 -> T6
```

### Phase 4: Screens

```
T7 -> T8
```

---

## Task Breakdown

### Phase 1: Persistence

#### T1: Add reservations schema and model

**What**: Migration `reservations` with UUID `id` PK, `foreignUuid('room_id')->constrained('rooms')`, `responsible`, `title`, `starts_at`, `ends_at`, `participants` unsigned integer, nullable `cancelled_at`, `timestamps()`, no `softDeletes()`. Eloquent model `HasUuids` + `HasFactory`, fillable those attributes, datetime casts, `newFactory`. Factory for tests. Feature schema/migration tests: exact ADR-005 columns, no `deleted_at`, factory row is UUID v7 (`id[14] === '7'`), FK to `rooms`.
**Where**: `database/migrations/2026_09_18_180000_create_reservations_table.php`
**Depends on**: None
**Reuses**: `database/migrations/2026_09_18_120000_create_rooms_table.php`, `Room` model/`RoomSchemaTest`/`RoomsMigrationTest`
**Requirement**: RSV-01

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `reservations` columns match ADR-005 only
- [x] Feature schema + migration tests pass
- [x] Gate check passes: `php artisan test --testsuite=Feature --filter=Reservation`

**Tests**: integration
**Gate**: full

---

### Phase 2: Application

#### T2: Implement CreateReservation occupancy rules

**What**: Add Domain `Reservation` entity and ports (`ReservationRepository`, `OccupancyRoomCatalog`, `Clock`) plus Application `Transaction` and Errors. Implement `CreateReservation` per Selected Approach (Clock first, then transaction + room lock + overlap + persist). Fakes in `tests/Unit/Reservation`. Cover AC-001 (in memory), AC-003–AC-009, consecutive vs overlap, canceled ignored, start-equals-now accepted. Add `ReservationIlluminateImportTest` for Domain+Application. Add `app/Modules/Reservation/Application` to `phpunit.xml` source include.
**Where**: `app/Modules/Reservation/Application/UseCases/CreateReservation.php`
**Depends on**: T1
**Reuses**: `tests/Unit/Room/CreateRoomTest.php`, `FakeRoomRepository`, `RoomIlluminateImportTest`
**Requirement**: RSV-01, RSV-03

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Use case has no `Illuminate` import
- [x] Unit tests listed for CreateReservation pass
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=Reservation`

**Tests**: unit
**Gate**: quick

---

#### T3: Implement ListReservations and CancelReservation

**What**: `ListReservations` clamps `page` ≥ 1, default perPage 15, passes `room_id` (nullable) and inclusive/exclusive day bounds in app timezone to the repository; returns items + total + `hasAny`. `CancelReservation` loads the row, no-ops if already canceled, otherwise sets `cancelled_at` to Clock::now(). Unit tests for page clamp, filter forwarding, first cancel, repeat cancel.
**Where**: `app/Modules/Reservation/Application/UseCases/ListReservations.php`
**Depends on**: T2
**Reuses**: `ListRooms`, CreateReservation fakes
**Requirement**: RSV-05, RSV-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Cancel is idempotent in unit tests
- [x] List forwards day bounds and room filter
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=Reservation`

**Tests**: unit
**Gate**: quick

---

### Phase 3: HTTP

#### T4: Persist reservations with room lock

**What**: Eloquent `ReservationRepository` (list page + overlap + create + find + markCanceled) and `OccupancyRoomCatalog` (`Room` model `lockForUpdate()` inside the open transaction). Laravel `Clock` and `Transaction` (`DB::transaction`). Bind interfaces in `AppServiceProvider`. Do not add methods to `RoomRepository`.
**Where**: `app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php`
**Depends on**: T3
**Reuses**: `EloquentRoomRepository`, Laravel `lockForUpdate` inside `DB::transaction`
**Requirement**: RSV-01, RSV-04

**Tools**:

- MCP: `context7` (Laravel `lockForUpdate` + `DB::transaction`)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Room row is locked inside the transaction before overlap
- [x] Bindings resolve in the container
- [x] Gate check passes: `php artisan test --testsuite=Unit --coverage --min=80`

**Tests**: none
**Gate**: quick

---

#### T5: Add reservation FormRequests

**What**: `StoreReservationRequest` rules/messages/trim exactly as AC-002. No `exists:rooms`, no duration/capacity/overlap rules. `IndexReservationRequest`: optional `room_id` uuid, optional `date` `Y-m-d`. Unit tests via `StoreRoomRequestTest` helpers (request + container, no route).
**Where**: `app/Modules/Reservation/Infra/Http/Requests/StoreReservationRequest.php`
**Depends on**: T4
**Reuses**: `StoreRoomRequest`, `StoreRoomRequestTest`
**Requirement**: RSV-02

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Messages match AC-002
- [x] `validated()` create keys are only the six fields
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=ReservationRequest`

**Tests**: unit
**Gate**: quick

---

#### T6: Wire reservation controllers and HTTP tests

**What**: Replace the reservations closure. Routes (auth): `GET /reservations` index, `GET /reservations/create`, `POST /reservations`, `PATCH /reservations/{reservation}/cancel`. Thin controllers: index uses List + filter rooms + paginator 15 + default date; create passes active rooms; store maps errors; cancel maps 404/idempotent + flash. Feature tests for every integration item including guest matrix and two-process concurrency (`symfony/process` workers + barrier file). Keep `reservations.index` name so login/register tests stay green.
**Where**: `app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php`
**Depends on**: T5
**Reuses**: `IndexRoomController`, `StoreRoomController`, `RoomGuestHttpTest`, `RoomStoreHttpTest`
**Requirement**: RSV-04, RSV-05, RSV-06, RSV-07

**Tools**:

- MCP: `context7` (Inertia render/redirect/flash)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] All `harness.tests.integration` items pass
- [x] Login/register Feature tests still pass
- [x] Gate check passes: `php artisan test --testsuite=Feature --filter=Reservation`

**Tests**: integration
**Gate**: full

---

### Phase 4: Screens

#### T7: Build the create reservation page

**What**: `Services/reservations.js` with `store` (`form.post('/reservations')`), `visitIndex`, `cancel` (`form.patch`). `Reservation/Create.jsx` (+ optional form component): Room-form card, required asterisks, `datetime-local` start/end, room select from active `rooms` prop, processing `Salvando...`, field errors + first-field focus, general failure banner, Cancelar → `/reservations`. Vitest + `reservations.test.js`.
**Where**: `resources/js/Pages/Reservation/Create.jsx`
**Depends on**: T6
**Reuses**: `Room/Create.jsx`, `Services/rooms.js`, Inertia `useForm`
**Requirement**: RSV-08

**Tools**:

- MCP: `context7` (Inertia React `useForm` `processing` `errors`)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Vitest covers create Planned Tests
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

#### T8: Replace the reservations list stub

**What**: Rewrite `Reservation/Index.jsx` per the list screen: header, `Nova reserva`, room+date filters in the URL, table (ID, sala, responsável, título, início, fim, participantes, status, ações), badges, cancel modal (focus `Voltar`, Escape, `Cancelando...`), empty/filtered/error/loading, pagination, no calendar icon. Keep heading `Reservas` so existing stub assertions still match a real heading. Rewrite `Index.test.jsx` (drop “stub” cases).
**Where**: `resources/js/Pages/Reservation/Index.jsx`
**Depends on**: T7
**Reuses**: `Room/Index.jsx`, `Room/Index.test.jsx`, `FlashToast`, `AppLayout`
**Requirement**: RSV-05, RSV-06, RSV-08

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Vitest covers list Planned Tests
- [x] Existing Reservation/Index heading still exists
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

## Phase Execution Map

```
Phase 1 → Phase 2 → Phase 3 → Phase 4

Phase 1:  T1
Phase 2:  T2 ------→ T3
Phase 3:  T4 ------→ T5 ------→ T6
Phase 4:  T7 ------→ T8
```

Execution is strictly sequential.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: reservations migration/model | 1 schema slice | ✅ Granular |
| T2: CreateReservation + ports | 1 use case + required ports | ⚠️ Cohesive |
| T3: List + Cancel use cases | 2 use cases same module | ⚠️ Cohesive |
| T4: Eloquent lock adapters | 1 persistence slice | ✅ Granular |
| T5: FormRequests | 1 HTTP validation slice | ✅ Granular |
| T6: Controllers + Feature tests | 1 HTTP wiring slice | ⚠️ Cohesive |
| T7: Create page + service | 1 screen | ✅ Granular |
| T8: List page | 1 screen | ✅ Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (none) | ✅ Match |
| T2 | T1 | cross-phase (no intra-phase arrow) | ✅ Match |
| T3 | T2 | T2 → T3 | ✅ Match |
| T4 | T3 | cross-phase | ✅ Match |
| T5 | T4 | T4 → T5 | ✅ Match |
| T6 | T5 | T5 → T6 | ✅ Match |
| T7 | T6 | cross-phase | ✅ Match |
| T8 | T7 | T7 → T8 | ✅ Match |

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | migration / model | none + schema Feature | integration | ✅ OK |
| T2 | CreateReservation use case | unit | unit | ✅ OK |
| T3 | List/Cancel use cases | unit | unit | ✅ OK |
| T4 | Eloquent adapters / bindings | none | none | ✅ OK |
| T5 | FormRequests | unit | unit | ✅ OK |
| T6 | Controllers / routes | integration | integration | ✅ OK |
| T7 | React create + service | unit | unit | ✅ OK |
| T8 | React list page | unit | unit | ✅ OK |
