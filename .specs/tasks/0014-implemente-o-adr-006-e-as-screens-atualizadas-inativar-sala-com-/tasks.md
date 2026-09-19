---
harness:
  commits:
    - "feat(reservation): hide canceled rows and cap create participants"
    - "feat(resources): pass status filter through rooms inertia service"
    - "feat(room): add deactivation decision and lock use cases"
    - "feat(room): add room lock-by-id port"
    - "feat(room): wire room lifecycle HTTP and status filter"
    - "feat(room): add deactivate radios delete warning and status filter"
    - "test(reservation): cover create capacity and hidden canceled pages"
    - "test(reservation): cover reservation index and room-lifecycle races"
    - "test(reservation): cover canceled listing and room-lifecycle ports"
    - "test(room): cover room form radios filter and delete warning"
    - "test(room): cover deactivate delete filter and lifecycle HTTP"
    - "test(room): cover deactivate delete filter use cases"
    - "chore(specs): record adr-006 plan context"
    - "chore(specs): record adr-006 progress"
    - "chore(specs): record adr-006 reviews"
    - "chore(specs): record adr-006 spec"
    - "chore(specs): record adr-006 tasks"
    - "chore(specs): record adr-006 checks"
    - "chore(specs): record adr-006 validation"
  tests:
    unit:
      - "UpdateRoom deactivates with keep and leaves future actives unchanged"
      - "UpdateRoom deactivates with cancel and sets cancelled_at only on future actives"
      - "UpdateRoom without scheduled_meetings_action throws DeactivationDecisionRequired and writes nothing when futures exist"
      - "UpdateRoom deactivates without a dialog path when no future active exists"
      - "UpdateRoom cancel leaves past, in-progress, and already-canceled reservations unchanged"
      - "DeleteRoom cancels every active reservation then deletes the room"
      - "DeleteRoom throws RoomNotFound and writes nothing when the room is missing"
      - "ListRooms forwards status all/active/inactive, clamps page, and reports hasAny"
      - "Reservation list/hasAny exclude rows with cancelled_at set"
      - "UpdateRoomRequest requires is_active and accepts only keep|cancel for scheduled_meetings_action"
      - "IndexRoomRequest accepts all|active|inactive and rejects unknown status"
      - "Room/Edit opens the deactivation dialog with keep default and submits scheduled_meetings_action only after Desativar"
      - "Room/Edit shows the inline Inativa warning and hides it when Status is Ativa"
      - "Room/Index applies status through the Inertia service, resets page to 1, and warns on delete when has_reservations"
      - "Reservation/Create blocks a participants value above the selected room capacity"
      - "Reservation/Index does not render a Cancelada row or badge"
    integration:
      - "PUT /rooms/{id} with is_active false and keep persists inactive room, keeps future actives, flashes the keep message"
      - "PUT /rooms/{id} with is_active false and cancel persists inactive room, cancels only future actives, flashes the cancel message"
      - "PUT /rooms/{id} deactivate without action returns 422, future_active_count, and leaves room and reservations unchanged"
      - "DELETE /rooms/{id} cancels all actives, soft-deletes the room, and keeps reservation rows"
      - "GET /rooms?status=all|active|inactive filters on the server and omits soft-deleted rooms"
      - "GET /reservations returns only cancelled_at null rows after standalone cancel, deactivate-cancel, and delete-cancel"
      - "POST /reservations vs same-room deactivation: two PHP processes; create after inactive lock fails with inactive room and inserts no row"
      - "POST /reservations vs same-room deletion: two PHP processes; create after delete lock fails with room not found and inserts no row"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Deactivate, delete, filter, list exclusion, and the create capacity cap are covered by Vitest page tests and Feature HTTP tests, including two-process MySQL races. Do not bootstrap Playwright in this task."
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

Close ADR-006 on top of the existing Room and Reservation slices. Deactivate and delete lock the same `rooms` row CreateReservation already locks, then apply reservation effects in one transaction. Hide canceled rows. Add the rooms status URL filter. Align the create screen and cap participants by the selected room.

**Design (inline, no `design.md`):**

- `RoomRepository::lockById($id): ?Room` — Eloquent `lockForUpdate()`, soft-deleted → null.
- `UpdateRoom` / `DeleteRoom` constructor: `RoomRepository`, `ReservationRepository`, `Clock`, `Transaction`. Both `transaction->run`: lock room or `RoomNotFound`; then ADR-006; then update/delete.
- New error `DeactivationDecisionRequired` with `public int $futureActiveCount`.
- `ReservationRepository` additions: `countActiveFutureByRoom(string $roomId, DateTimeImmutable $now): int`; `countByRoomIds(array $ids): array<string,int>`; `cancelActiveFutureByRoom(...)`; `cancelAllActiveByRoom(...)`. Cancel methods must not overwrite an existing `cancelled_at`.
- `listPage` / `hasAny`: `whereNull('cancelled_at')`.
- HTTP: required `is_active`; optional `scheduled_meetings_action` in `keep,cancel`. `IndexRoomRequest` `status`. Edit prop `future_active_count`. Index row `has_reservations`. Map flashes per spec.
- React: Status select + warning + dialog; `/rooms?status=`; delete warning; create date/time fields + `max` on participants; drop canceled list UI.
- Concurrency: copy `concurrency_create_worker.php`; add deactivate and delete workers; Feature tests start POST + PUT/DELETE together.

## Affected Components

- `app` — `app/Modules/Room/**` (use cases, repository, HTTP), `app/Modules/Reservation/Domain/Repositories/ReservationRepository.php`, `EloquentReservationRepository`, `IndexReservationController` (no canceled status), `resources/js/Pages/Room/*`, `resources/js/Pages/Reservation/Create.jsx`, `resources/js/Pages/Reservation/Index.jsx`, `resources/js/Services/rooms.js`, Unit/Feature/Vitest tests, new Feature workers under `tests/Feature/Reservation/Support/`.
- Do not change occupancy math in `CreateReservation`. Do not add Playwright.

## Tasks

Execute T1 → T7 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. PHPUnit Unit + Vitest. Use cases use fakes only. FormRequests via container, not a route. React mocks Inertia.

### Integration

See `harness.tests.integration`. Feature suite on MySQL 8. Lifecycle writes assert rows + rollback. Concurrency must start two PHP processes (file barrier + `symfony/process`). Sequential double-calls do not satisfy ADR-006.

### E2E

Not applicable — no Playwright project. See `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Unit coverage remains Application-only (`phpunit.xml` already includes Room + Reservation Application). Frontend coverage via `npm run test:coverage` (≥80%).

## Definition of Done

- All P1 ACs have a unit and/or integration test as classified above; no duplicated scenario across levels.
- Existing 0013 occupancy and same-room create-vs-create concurrency tests still pass.
- The 0013 “index includes canceled rows” Feature/Vitest cases are replaced by exclusion.
- Pint, ESLint, PHP build, and Vite build pass.
- No product commit in PLAN.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `phpunit.xml` (Application coverage ≥80%), `package.json` (`npm run test:coverage`).

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Room / Reservation use cases | unit | Keep/cancel/delete/list/filter branches and listed edge cases | `tests/Unit/Room/*`, `tests/Unit/Reservation/*` | `php artisan test --testsuite=Unit --coverage --min=80` |
| FormRequest input contract | unit | Required/allowed values for `is_active`, `scheduled_meetings_action`, `status` | `tests/Unit/Room/*RequestTest.php` | `php artisan test --testsuite=Unit --filter=Request` |
| React pages / forms | unit | Dialog, filter, warning, participants max, no Cancelada | `resources/js/Pages/**/*.test.jsx` | `npm run test:coverage` |
| Controllers + MySQL | integration | HTTP + persistence + rollback + two-process races | `tests/Feature/Room/*`, `tests/Feature/Reservation/*` | `php artisan test --testsuite=Feature` |
| Eloquent models / migrations | none | Unchanged schema | — | build gate only |

## Gate Check Commands

> Generated from `composer.json` / `package.json` / task 0013.

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

### Phase 1: Ports and use cases

```
T1 -> T2
```

### Phase 2: HTTP

```
T3 -> T4
```

### Phase 3: Screens

```
T5 -> T6
```

### Phase 4: Races

```
T7
```

---

## Task Breakdown

### Phase 1: Ports and use cases

#### T1: Add reservation room-lifecycle ports and hide canceled rows

**What**: Extend `ReservationRepository` with `countActiveFutureByRoom`, `countByRoomIds`, `cancelActiveFutureByRoom`, `cancelAllActiveByRoom`. Eloquent: `whereNull('cancelled_at')` on `listPage` and `hasAny`; cancel helpers set `cancelled_at` only where it is null. Update `FakeReservationRepository`. Unit-test list/hasAny exclusion and the cancel/count helpers (in-memory).
**Where**: `app/Modules/Reservation/Domain/Repositories/ReservationRepository.php`
**Depends on**: None
**Reuses**: `EloquentReservationRepository`, `FakeReservationRepository`, `CancelReservation`
**Requirement**: RSV-01, ROOM-01, ROOM-02

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [ ] Port methods exist and fakes compile
- [ ] Unit tests prove canceled rows are excluded and cancel-by-room is selective
- [ ] Gate check passes: `php artisan test --testsuite=Unit --filter=Reservation`

**Tests**: unit
**Gate**: quick

---

#### T2: Serialize UpdateRoom and DeleteRoom; filter ListRooms

**What**: Add `RoomRepository::lockById`. Rewrite `UpdateRoom` / `DeleteRoom` to run inside `Transaction` with Clock + ReservationRepository per Selected Approach (`DeactivationDecisionRequired`). `ListRooms` takes `status` (`all` default) + returns `hasAny`. Update `FakeRoomRepository` listed payload. Unit-test every `harness.tests.unit` Update/Delete/ListRooms item.
**Where**: `app/Modules/Room/Application/UseCases/UpdateRoom.php`
**Depends on**: T1
**Reuses**: `FakeTransaction`, `FakeClock`, `CreateReservation` lock-then-decide shape
**Requirement**: ROOM-01, ROOM-02, ROOM-03

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [ ] Use cases have no Illuminate import
- [ ] All Room use-case unit items pass
- [ ] Gate check passes: `php artisan test --testsuite=Unit --coverage --min=80`

**Tests**: unit
**Gate**: quick

---

### Phase 2: HTTP

#### T3: Wire room lifecycle HTTP and status filter

**What**: `UpdateRoomRequest`: required boolean `is_active`; optional `scheduled_meetings_action` in `keep,cancel`. New `IndexRoomRequest` for `status`. Controllers: Update maps decision-required to 422 + count and flashes; Destroy stays 404 on missing; Edit passes `future_active_count`; Index applies filter, `hasAny`, per-row `has_reservations` via `countByRoomIds`, keeps query string. Eloquent `lockById` + `listPage($page,$perPage,$status)`. Unit FormRequest tests (no route). Feature tests for the non-concurrency room integration items.
**Where**: `app/Modules/Room/Infra/Http/Controllers/UpdateRoomController.php`
**Depends on**: T2
**Reuses**: `UpdateRoomRequestTest`, `RoomUpdateHttpTest`, `RoomIndexHttpTest`, `RoomDestroyHttpTest`
**Requirement**: HTTP-01, ROOM-03

**Tools**:

- MCP: `context7` (Laravel `lockForUpdate` + Inertia props)
- Skill: `tlc-spec-driven`

**Done when**:

- [ ] Edit/index props match the spec
- [ ] Feature deactivate keep/cancel/422, delete cascade, and status filter pass
- [ ] Gate check passes: `php artisan test --testsuite=Feature --filter=Room`

**Tests**: integration
**Gate**: full

---

#### T4: Stop returning canceled reservations over HTTP

**What**: `IndexReservationController` / Inertia mapper expose only active rows (drop canceled status/label). Replace `ReservationIndexHttpTest::test_index_filters_by_room_and_local_day_including_canceled_rows` with exclusion, plus a case that deactivate-cancel and delete-cancel also disappear. Keep 0013 create/cancel/overlap Feature tests green (update the create worker only if the POST body must change — it must not).
**Where**: `app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php`
**Depends on**: T1, T3
**Reuses**: `ReservationIndexHttpTest`, `ReservationCancelHttpTest`
**Requirement**: RSV-01

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [ ] GET `/reservations` never includes `cancelled_at` set rows
- [ ] Gate check passes: `php artisan test --testsuite=Feature --filter=ReservationIndex`

**Tests**: integration
**Gate**: full

---

### Phase 3: Screens

#### T5: Room form radios, status warning, list filter, delete warning

**What**: Edit: Status select (Ativa/Inativa), amber warning per screen, dialog on Salvar when switching to Inativa and `future_active_count` > 0; default keep; submit `is_active` + `scheduled_meetings_action`; handle 422 by reopening the dialog. Index: Status filter `Todas/Ativas/Inativas` via `visitIndex({ status })`, page=1; delete copy when `has_reservations`. Update `rooms.js`. Rewrite `Edit.test.jsx` / `Index.test.jsx` for the unit UI items.
**Where**: `resources/js/Pages/Room/Components/RoomForm.jsx`
**Depends on**: T3
**Reuses**: current dialog focus/Escape, `Services/rooms.js`
**Requirement**: UI-01, ROOM-01, ROOM-03

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [ ] Vitest room UI items pass
- [ ] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: frontend

---

#### T6: Create screen capacity cap and list without Cancelada

**What**: `Reservation/Create.jsx` follows `screen-reservation-create.md`: date + start + end (combine to `starts_at`/`ends_at` before `store`), `Título / finalidade`, `Criar reserva` / `Criando reserva...`, room option shows capacity, participants `min=1` `max=selected.capacity` (ignore typed values above max). Empty active-rooms state per screen. `Reservation/Index.jsx` + test: no canceled fixture, no `Cancelada` badge. Update `Create.test.jsx`.
**Where**: `resources/js/Pages/Reservation/Create.jsx`
**Depends on**: T4, T5
**Reuses**: `CreateReservationController` `rooms[].capacity`, `Services/reservations.js`
**Requirement**: RSV-03, UI-01

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [ ] Participants cannot exceed selected capacity in Vitest
- [ ] Index test no longer expects Cancelada
- [ ] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: frontend

---

### Phase 4: Races

#### T7: Two-process create vs deactivate and vs delete

**What**: Add Feature workers (same barrier as `concurrency_create_worker.php`) for PUT deactivate (`is_active=false`, `scheduled_meetings_action=cancel`) and DELETE. Tests: POST `/reservations` vs deactivate same room; POST vs delete same room. Disable the test-case transaction (`$connectionsToTransact = []`) like the existing concurrency test. Assert: after lifecycle wins the lock, create stores no row and the error is inactive-room or room-not-found. Different rooms must not be required here (already covered for create-vs-create).
**Where**: `tests/Feature/Reservation/ReservationRoomLifecycleConcurrencyHttpTest.php`
**Depends on**: T3, T4
**Reuses**: `ReservationConcurrencyHttpTest`, `concurrency_create_worker.php`
**Requirement**: RSV-02

**Tools**:

- MCP: `context7` (Laravel HTTP kernel in a worker process)
- Skill: `tlc-spec-driven`

**Done when**:

- [ ] Both two-process races pass on MySQL 8
- [ ] Existing create-vs-create concurrency still passes
- [ ] Gate check passes: `php artisan test --testsuite=Feature --filter=Concurrency`

**Tests**: integration
**Gate**: full

---

## Phase Execution Map

```
Phase 1 → Phase 2 → Phase 3 → Phase 4

Phase 1:  T1 ------→ T2
Phase 2:  T3 ------→ T4
Phase 3:  T5 ------→ T6
Phase 4:  T7
```

Execution is sequential. T4 depends on T1 and T3. T7 depends on T3 and T4.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Reservation ports + hide canceled | One port + Eloquent impl | ✅ Cohesive |
| T2: Room use cases | Update/Delete/List in Application | ✅ Cohesive |
| T3: Room HTTP | Controllers + requests + Feature | ✅ Cohesive |
| T4: Reservation index HTTP | One controller + Feature | ✅ Granular |
| T5: Room screens | Form + index + service | ✅ Cohesive |
| T6: Reservation screens | Create + index tests | ✅ Cohesive |
| T7: Concurrency Feature | One test class + workers | ✅ Granular |

**Granularity check**: no task spans unrelated modules except T7 (Feature that hits both HTTP surfaces, which is the race).

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | No inbound arrow | ✅ Match |
| T2 | T1 | T1 → T2 | ✅ Match |
| T3 | T2 | T2 → T3 (phase 2 starts after T2) | ✅ Match |
| T4 | T1, T3 | T1 (prior phase) and T3 → T4 | ✅ Match |
| T5 | T3 | T3 → T5 | ✅ Match |
| T6 | T4, T5 | T4 (prior phase) and T5 → T6 | ✅ Match |
| T7 | T3, T4 | T3/T4 → T7 | ✅ Match |

T4’s intra-phase arrow from T3 is sequencing only; T4’s body depends on T1, which is in an earlier phase (allowed). Execute T3 then T4 as drawn.

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | Reservation ports / use-case boundary | unit | unit | ✅ OK |
| T2 | Room use cases | unit | unit | ✅ OK |
| T3 | Controllers + MySQL + FormRequest | integration (HTTP) + unit FormRequest | integration | ✅ OK (FormRequest unit lives in this task) |
| T4 | Controller + MySQL | integration | integration | ✅ OK |
| T5 | React pages | unit | unit | ✅ OK |
| T6 | React pages | unit | unit | ✅ OK |
| T7 | Controller concurrency | integration | integration | ✅ OK |
