---
harness:
  commits:
    - "feat(database): switch catalog ids and firstOrCreate administrators"
    - "test(database): cover incrementing catalog ids and administrator seeder"
    - "feat(room): use incrementing ids and show them on the list"
    - "test(room): cover incrementing room ids in schema, HTTP, and list UI"
    - "feat(reservation): use incrementing ids and show them on the list"
    - "test(reservation): cover incrementing ids and integer room_id"
    - "chore(docs): record incrementing ids and list columns"
    - "chore(readme): document challenge section 05 decisions and AI use"
    - "chore(specs): record incrementing ids plan artifacts"
  tests:
    unit:
      - "StoreReservationRequest rejects a non-integer room_id with Selecione uma sala válida"
      - "IndexReservationRequest rejects a non-integer room_id with Selecione uma sala válida"
      - "StoreReservationRequest accepts an integer room_id in the isolated input contract"
      - "Room/Index renders columnheader ID and the persisted room id"
      - "Reservation/Index renders columnheader ID and the persisted reservation id"
      - "Room/Index table columnheader for situation is Situação"
      - "Reservation/Index table columnheader for situation is Situação"
    integration:
      - "rooms.id and reservations.id are incrementing integers; reservations.room_id is an integer foreign key"
      - "Factory-created room and reservation ids are incrementing integers, not UUID v7"
      - "Demo catalog rooms and reservations persist incrementing integer ids"
      - "POST /rooms persists an incrementing integer room id"
      - "POST /reservations persists an incrementing integer reservation id and accepts integer room_id"
      - "GET /rooms Inertia payload includes each room id"
      - "GET /reservations Inertia payload includes each reservation id"
      - "migrate then seed leaves exactly three users (Gertrudes, Marcelo, Emerson) and never test@example.com"
      - "DatabaseSeeder creates the three known administrators when those emails are missing"
      - "Unknown or malformed room or reservation route id returns 404 and leaves other rows unchanged"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. List columns, schema identity, and seeder behavior are covered by Vitest and Feature/MySQL tests. docs/test/e2e.md forbids repeating those matrices in the browser. Do not bootstrap Playwright in this task."
  gates:
    - id: unit
      command: "php artisan test --testsuite=Unit --coverage --min=80"
      required: true
    - id: integration
      command: "php artisan test --testsuite=Feature"
      required: true
    - id: frontend
      command: "npm run test:coverage"
      required: true
    - id: lint
      command: "vendor/bin/pint --test && npm run lint"
      required: true
    - id: build
      command: "composer run build && npm run build"
      required: true
    - id: test
      command: "php artisan test && npm run test"
      required: true
---

# Implementation Plan

## Summary

Close the three remaining Nano Incub gaps: incrementing ids on rooms/reservations with visible `ID` + `Situação` list columns; README section 05 (decisions, leftovers, Cursor AI); and a `UserSeeder` that `firstOrCreate`s the three known administrators. New ADR-008 supersedes only the UUID identity slice of ADR-004/005. Users stay UUID v7. Overlap stays Application rules + room `lockForUpdate`.

## Affected Components

- Docs: ADR-008, ADR-004/005 Status, `screen-rooms-list.md`, `screen-reservations-list.md`
- Database: create + demo migrations; `UserSeeder` + `DatabaseSeeder`
- Room / Reservation Infra models and reservation FormRequests
- Inertia `Room/Index` and `Reservation/Index`
- README
- Feature schema/HTTP/seeder tests and Vitest list tests; FormRequest unit tests

## Tasks

See Task Breakdown. Execute one task at a time; tests travel with the layer they protect.

## Planned Tests

Punctual behaviors (not suite commands). Classification from `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`.

### Unit

- StoreReservationRequest / IndexReservationRequest `room_id` integer contract (container, no route, no DB).
- Room/Index and Reservation/Index render `ID` + persisted id and header `Situação` (Inertia mocked).

### Integration

- MySQL schema and demo catalog: incrementing integer PKs and integer `room_id` FK.
- HTTP create/list/404 paths that use room or reservation ids.
- migrate+seed: exactly three admins; seeder recreates them if missing; no `test@example.com`.

### E2E

Not applicable — no Playwright project. Do not run `npx playwright test`.

## Required Gates

After Execute, before review (`harness/stack.yml` + `verify.required`):

| Gate | Command |
| ---- | ------- |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` |
| integration | `php artisan test --testsuite=Feature` |
| frontend | `npm run test:coverage` |
| lint | `vendor/bin/pint --test && npm run lint` |
| build | `composer run build && npm run build` |
| test | `php artisan test && npm run test` |

Stack verify also runs command IDs `test`, `lint`, `build` on `app`. Do not run `npx playwright test`.

## Definition of Done

- ADR-008 exists; ADR-004/005 Status only; users still UUID v7
- Rooms/reservations use `$table->id()` / `foreignId`; lists show ID and Situação
- README has Portuguese §05 (overlap trade-off, leftovers, Cursor)
- Seeder firstOrCreates the three admins; migrate+seed = 3 users
- Planned unit + integration tests pass; gates above are green

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec — confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `phpunit.xml`, `package.json`, `harness/stack.yml`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| FormRequest `room_id` | unit | Isolated type contract; reject non-integer; accept integer | `tests/Unit/Reservation/*RequestTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| React list pages | unit | ID column + persisted id + Situação header; invert hide-ID assertions | `resources/js/Pages/Room/Index.test.jsx`, `resources/js/Pages/Reservation/Index.test.jsx` | `npm run test:coverage` |
| Schema / demo / seeder / HTTP ids | integration | Incrementing PKs/FK; migrate+seed users; create/list/404 on MySQL 8 | `tests/Feature/Room/*`, `tests/Feature/Reservation/*`, `tests/Feature/Database/*` | `php artisan test --testsuite=Feature` |
| Eloquent models / ADR / README | none | Build + lint; docs review | `app/Modules/*/Infra/Database/Models`, `docs/adr`, `README.md` | build gate |
| E2E workflows | none | Not applicable | — | do not run Playwright |

## Gate Check Commands

> Generated from `harness/stack.yml` — confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After FormRequest or Vitest-only tasks | `php artisan test --testsuite=Unit --coverage --min=80` and/or `npm run test:coverage` |
| Full | After schema, HTTP, or seeder tasks | Unit command + `php artisan test --testsuite=Feature` + `npm run test:coverage` |
| Build | After docs/README or phase end | `vendor/bin/pint --test && npm run lint` and `composer run build && npm run build` |

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Contract

```
T1 -> T2 -> T3
```

### Phase 2: Persistence

```
T4
```

### Phase 3: List UI

```
T5 -> T6
```

### Phase 4: Seeder and README

```
T7 -> T8
```

---

## Task Breakdown

### Phase 1: Contract

### T1: Write ADR-008

**What**: Create MADR ADR-008 in Portuguese, date 2026-09-20: rooms/reservations use `$table->id()` / `foreignId`; users stay UUID v7. Supersedes the UUID identity slice of ADR-004 and ADR-005 only. Keep business columns, room soft delete, and `cancelled_at`.
**Where**: `docs/adr/008-identidade-autoincremento-rooms-e-reservations.md`
**Depends on**: None
**Reuses**: existing Portuguese ADR heading style; create-adr MADR; ADR-002 Status+supersede pattern
**Requirement**: IDENT-01, IDENT-02, IDENT-06

**Tools**:

- MCP: NONE
- Skill: create-adr

**Done when**:

- [x] File exists with Portuguese MADR sections and date 2026-09-20
- [x] Decision is incrementing ids for rooms/reservations only
- [x] Links supersede ADR-004/005 identity slice; ADR-002 unchanged

**Tests**: none
**Gate**: build

### T2: Supersede UUID identity on ADR-004 and ADR-005

**What**: On ADR-004 and ADR-005, change only the Status line (and Links supersede pointer) so the UUID/`HasUuids`/`foreignUuid` identity slice is superseded by ADR-008. Do not rewrite decision bodies, columns, soft delete, or `cancelled_at`.
**Where**: `docs/adr/004-modulo-rooms-ciclo-de-vida-minimo.md`
**Depends on**: T1
**Reuses**: ADR-002 Status clause pointing at ADR-007
**Requirement**: IDENT-06

**Tools**:

- MCP: NONE
- Skill: create-adr

**Done when**:

- [x] ADR-004 Status notes identity superseded by ADR-008
- [x] ADR-005 Status notes identity superseded by ADR-008
- [x] Decision bodies untouched

**Tests**: none
**Gate**: build

### T3: Require ID and Situação on list screens

**What**: `screen-rooms-list.md`: add column `ID` (persisted id); rename situation column `Status` → `Situação`; remove “identifier is not shown”. `screen-reservations-list.md`: keep `ID`; rename situation column `Status` → `Situação`. Filter label `Status` and room form stay.
**Where**: `docs/screens/screen-rooms-list.md`
**Depends on**: T2
**Reuses**: current screen table structure
**Requirement**: IDENT-03, IDENT-04, IDENT-05

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [x] Rooms list screen requires visible ID and Situação
- [x] Reservations list screen requires visible ID and Situação

**Tests**: none
**Gate**: build

### Phase 2: Persistence

### T4: Switch rooms and reservations to incrementing ids

**What**: Edit create migrations to `$table->id()` / `foreignId('room_id')->constrained('rooms')`. Demo migration inserts rooms/reservations without explicit ids (resolve FKs by name after insert). Remove `HasUuids` from Room and Reservation models. Change `StoreReservationRequest` and `IndexReservationRequest` `room_id` from `uuid` to `integer` (keep message `Selecione uma sala válida.` on the integer rule). Domain entities stay `string`; repos keep `(string) $model->getKey()`. Factories stay id-less. Update Feature/Unit PHP tests that assert UUID v7, insert a UUID `id`, or use UUID `room_id` (schema, demo catalog, store HTTP, destroy/guest/cancel 404, FormRequest unit). `UserSchemaTest` UUID assertions stay.
**Where**: `database/migrations/2026_09_18_120000_create_rooms_table.php`
**Depends on**: T3
**Reuses**: Eloquent incrementing defaults; existing Feature test names
**Requirement**: IDENT-01, IDENT-02

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [x] Fresh migrate: rooms/reservations incrementing bigint; `room_id` integer FK
- [x] Demo catalog has no `Str::uuid7()`
- [x] FormRequest unit + Feature identity tests listed in Planned Tests pass
- [x] Users remain UUID v7

**Tests**: unit, integration
**Gate**: full

### Phase 3: List UI

### T5: Show room id and Situação on Room/Index

**What**: Desktop table and mobile cards show persisted `room.id` under header `ID`. Rename table header `Status` → `Situação`. Invert Vitest hide-ID assertions; use numeric fixture ids (e.g. `1`, `2`) so the id is visible and edit hrefs stay `/rooms/{id}/edit`. Keep filter label `Status`.
**Where**: `resources/js/Pages/Room/Index.jsx`
**Depends on**: T4
**Reuses**: existing Inertia `id` prop from `IndexRoomController`
**Requirement**: IDENT-03, IDENT-05

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [x] Columnheader `ID` and persisted id are visible
- [x] Situation columnheader is `Situação`
- [x] Room/Index Vitest Planned Tests pass

**Tests**: unit
**Gate**: quick

### T6: Show reservation id and Situação on Reservation/Index

**What**: Desktop table and mobile cards show persisted `reservation.id` under header `ID`. Rename table header `Status` → `Situação`. Invert Vitest hide-ID assertions; use numeric fixture ids (e.g. `1`, `2`).
**Where**: `resources/js/Pages/Reservation/Index.jsx`
**Depends on**: T5
**Reuses**: existing Inertia `id` prop from `IndexReservationController`
**Requirement**: IDENT-04, IDENT-05

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [x] Columnheader `ID` and persisted id are visible
- [x] Situation columnheader is `Situação`
- [x] Reservation/Index Vitest Planned Tests pass

**Tests**: unit
**Gate**: quick

### Phase 4: Seeder and README

### T7: Seed administrators with firstOrCreate

**What**: Add `UserSeeder` that `firstOrCreate`s Gertrudes/Marcelo/Emerson by email, plaintext `Senha123` (Eloquent `hashed` → Argon), no `test@example.com`. Call it from `DatabaseSeeder` before room/reservation seeders. Keep those seeders idempotent. Extend Feature tests: seed still yields 3 users after migrate; seed recreates the three emails if deleted; still no fourth user. Keep `test_database_seeder_does_not_insert_additional_administrators` as the “no extra user” case.
**Where**: `database/seeders/UserSeeder.php`
**Depends on**: T4
**Reuses**: admin migration emails; `RoomSeeder` / `ReservationSeeder` `firstOrCreate` style
**Requirement**: SEED-01, SEED-02, SEED-03, SEED-04

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [x] `DatabaseSeeder` calls `UserSeeder`
- [x] migrate+seed = 3 users, Argon `Senha123`, no `test@example.com`
- [x] Missing emails are recreated by seed
- [x] Feature seeder tests pass

**Tests**: integration
**Gate**: full

### T8: Document challenge section 05 in README

**What**: Add Portuguese README sections (keep existing setup/credentials). Cover: technical decisions and why, including overlap = Application RF13–RF18 + `lockForUpdate` on the room (RNF09 / ADR-006) and why both — not app-only, not unique-index-only; incrementing room/reservation ids vs user UUID v7. Leftovers and more time (ADR-001: public signup, reservation edit, calendar, email, extra roles). AI: Cursor (agents) used to plan/implement/test; author owns the code; do not invent other tools. No secrets, no `CONTRIBUTING.md`.
**Where**: `README.md`
**Depends on**: T7
**Reuses**: current README setup; ADR-001/006 wording
**Requirement**: README-01, README-02, README-03, README-04

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [x] Portuguese §05 narratives exist
- [x] Overlap trade-off names both layers and rejects the two single-layer options
- [x] Cursor is the only AI tool named
- [x] No secrets / no CONTRIBUTING.md

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1 -> Phase 2 -> Phase 3 -> Phase 4

Phase 1:  T1 -> T2 -> T3
Phase 2:  T4
Phase 3:  T5 -> T6
Phase 4:  T7 -> T8
```

T4 depends on T3. T5 and T7 depend on T4 (cross-phase). Execution is sequential.

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1 | 1 ADR file | Granular |
| T2 | Status lines on two ADRs | Cohesive |
| T3 | Two list screens | Cohesive |
| T4 | Identity persistence + PHP tests | Cohesive (one identity change) |
| T5 | One React page + its Vitest | Granular |
| T6 | One React page + its Vitest | Granular |
| T7 | UserSeeder + DatabaseSeeder + Feature tests | Cohesive |
| T8 | README only | Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (start) | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | T2 | T2 -> T3 | Match |
| T4 | T3 | cross-phase (no intra-phase arrow) | Match |
| T5 | T4 | cross-phase | Match |
| T6 | T5 | T5 -> T6 | Match |
| T7 | T4 | cross-phase | Match |
| T8 | T7 | T7 -> T8 | Match |

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | ADR / docs | none | none | OK |
| T2 | ADR / docs | none | none | OK |
| T3 | screens / docs | none | none | OK |
| T4 | schema + models + FormRequest + HTTP/schema tests | unit + integration | unit, integration | OK |
| T5 | React list page | unit | unit | OK |
| T6 | React list page | unit | unit | OK |
| T7 | seeder + Feature seeder tests | integration | integration | OK |
| T8 | README | none | none | OK |
