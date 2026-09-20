---
harness:
  commits:
    - "feat(reservation): count future meetings that exceed room capacity"
    - "feat(room): reject capacity reduction when meetings exceed it"
    - "test(reservation): extend reservation fake for exceeding-capacity count"
    - "test(room): cover capacity reduction guard on update and edit"
    - "chore(docs): document capacity reduction block on room edit"
    - "chore(specs): record capacity-reduction plan artifacts"
  tests:
    unit:
      - "UpdateRoom throws CapacityReductionBlocked and writes nothing when a future active has more participants than the new capacity"
      - "UpdateRoom uses the singular capacity message when exactly one future meeting exceeds the new capacity"
      - "UpdateRoom uses the plural capacity message when two future meetings exceed the new capacity"
      - "UpdateRoom persists a lower capacity when every future active fits the new number"
      - "UpdateRoom persists an increase or same capacity even if a future meeting equals the current capacity"
      - "UpdateRoom ignores past, in-progress, canceled, and other-room meetings when reducing capacity"
      - "UpdateRoom prefers CapacityReductionBlocked over DeactivationDecisionRequired when both would apply"
      - "UpdateRoom evaluates the capacity rule after lockById"
      - "Room/Edit shows the singular capacity-reduction sentence on the Capacidade field"
    integration:
      - "PUT /rooms/{id} reducing capacity below a future meeting's participants returns the capacity error and leaves room and reservations unchanged"
      - "PUT /rooms/{id} reducing capacity below two future over-capacity meetings returns the plural capacity error and writes nothing"
      - "PUT /rooms/{id} reducing capacity succeeds when every future active has participants less than or equal to the new capacity"
      - "PUT /rooms/{id} with a capacity conflict and is_active false without scheduled_meetings_action returns only the capacity error"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml may list npx playwright test but it is not runnable. The administrator edit flow is covered by UpdateRoom unit tests, PUT /rooms/{id} Feature tests on MySQL 8, and Room/Edit Vitest for the capacity error. Do not bootstrap Playwright in this task."
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

Close the RF16 hole on room edit. After the existing `UpdateRoom` lock, if the proposed capacity is **lower** than the current one, count this room’s future actives with `participants >` that number. If the count is above zero, throw `CapacityReductionBlocked` and persist nothing. Map the error to `capacity` with the Portuguese singular/plural sentences. Room/Edit already shows `errors.capacity`. Document the rule on `screen-room-form.md`. No new ADR. No reservation edit. No Playwright.

**Design (inline, no `design.md`):**

- `ReservationRepository::countActiveFutureExceedingCapacity(string $roomId, DateTimeImmutable $now, int $capacity): int`
- Eloquent: `room_id`, `cancelled_at` null, `starts_at > $now`, `participants > $capacity`
- Same filter on `FakeReservationRepository`
- `CapacityReductionBlocked` (`Room\Application\Errors`) with `public readonly int $conflictingCount`
  - 1 → `Não é possível reduzir a capacidade. Existe 1 reunião marcada com mais participantes do que a nova capacidade. Altere essa reunião primeiro e depois volte.`
  - n>1 → `Não é possível reduzir a capacidade. Existem {n} reuniões marcadas com mais participantes do que a nova capacidade. Altere essas reuniões primeiro e depois volte.`
- `UpdateRoom` after `lockById` / `RoomNotFound`: if `$capacity < $existing->capacity`, count; if count > 0 throw. Then existing deactivate path. Then `rooms->update`.
- `UpdateRoomController`: `catch (CapacityReductionBlocked)` → `ValidationException::withMessages(['capacity' => $exception->getMessage()])`
- Do not change `UpdateRoomRequest`
- React: Vitest only unless the existing field is broken
- Screen: one Capacity / edit paragraph on `docs/screens/screen-room-form.md`

## Affected Components

- `app` — `ReservationRepository`, `EloquentReservationRepository`, `FakeReservationRepository`, `CapacityReductionBlocked`, `UpdateRoom`, `UpdateRoomController`, `tests/Unit/Room/UpdateRoomTest.php`, `tests/Feature/Room/RoomUpdateHttpTest.php`, `resources/js/Pages/Room/Edit.test.jsx`, `docs/screens/screen-room-form.md`
- Do not change `CreateReservation`, deactivate radios, delete-room, `UpdateRoomRequest`, or Playwright.

## Tasks

Execute T1 → T6 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. PHPUnit Unit uses `FakeRoomRepository`, `FakeReservationRepository`, `FakeClock`, `FakeTransaction` only. Seed `participants` explicitly. React mocks Inertia. Do not hit MySQL or HTTP.

### Integration

See `harness.tests.integration`. Feature suite on MySQL 8. Freeze time like `RoomUpdateHttpTest` (`2026-09-21 12:00`). Assert session `capacity` errors and unchanged `rooms` / `reservations` rows. One deactivate-overlap case proves AC-007. Do not repeat the FormRequest min/type matrix.

### E2E

Not applicable — no Playwright project. See `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Unit coverage remains Application-only. Frontend coverage via `npm run test:coverage` (≥80%).

## Definition of Done

- All P1 ACs have a unit and/or integration test as classified above; no duplicated scenario across levels.
- Lowering capacity below a future meeting’s `participants` cannot persist.
- Existing deactivate, delete, and create-reservation Feature tests still pass.
- Pint, ESLint, PHP build, and Vite build pass.
- No product commit in PLAN.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `phpunit.xml` (Application coverage ≥80%), `package.json` (`npm run test:coverage`).

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| --- | --- | --- | --- | --- |
| UpdateRoom capacity guard | unit | block / singular / plural / allow-fit / allow-increase-or-same / ignore non-future / beats deactivate / after lock | `tests/Unit/Room/UpdateRoomTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| UpdateRoomController mapping | integration | PUT conflict, plural, allow-fit, capacity-vs-deactivate; FormRequest already unit-tested | `tests/Feature/Room/RoomUpdateHttpTest.php` | `php artisan test --testsuite=Feature` |
| Room/Edit capacity error | unit | AC-002 sentence on Capacidade | `resources/js/Pages/Room/Edit.test.jsx` | `npm run test:coverage` |
| ReservationRepository query | integration | Exercised by the PUT Feature cases on MySQL 8 | `app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php` | `php artisan test --testsuite=Feature` |
| Screen markdown | none | Copy only | `docs/screens/screen-room-form.md` | build gate only |

## Gate Check Commands

> Generated from `composer.json`, `package.json`, `phpunit.xml`.

| Gate Level | When to Use | Command |
| --- | --- | --- |
| Quick | After unit-only tasks | `php artisan test --testsuite=Unit --coverage --min=80` |
| Frontend | After React tasks | `npm run test:coverage` |
| Full | After Feature / page+HTTP tasks | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run test:coverage` |
| Build | Phase end / lint | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Port

```
T1
```

### Phase 2: Application and HTTP

```
T2 -> T3
```

### Phase 3: Tests and screen

```
T4
T5
T6
```

---

## Task Breakdown

### T1: Count future meetings over a proposed capacity

**What**: Add `countActiveFutureExceedingCapacity` to the reservation port and implement it in Eloquent (`room_id`, `cancelled_at` null, `starts_at > $now`, `participants > $capacity`). Mirror the filter on `FakeReservationRepository` so later unit tests compile.
**Where**: `app/Modules/Reservation/Domain/Repositories/ReservationRepository.php`
**Depends on**: None
**Reuses**: `countActiveFutureByRoom` predicates
**Requirement**: CAP-01, CAP-06

**Done when**:

- [x] Interface method exists with the signature in the Summary
- [x] Eloquent and fake implement the same filter
- [x] No other reservation write behavior changes

**Tests**: unit
**Gate**: quick

---

### T2: Reject a capacity drop in UpdateRoom

**What**: Add `CapacityReductionBlocked` and, after `lockById`, refuse `$capacity < $existing->capacity` when the new count is greater than zero. Keep the ADR-006 deactivate path after this check.
**Where**: `app/Modules/Room/Application/UseCases/UpdateRoom.php`
**Depends on**: T1
**Reuses**: `DeactivationDecisionRequired` error shape; existing `Clock` and `Transaction`
**Requirement**: CAP-01, CAP-02, CAP-03, CAP-04, CAP-05, CAP-06, CAP-07, CAP-08, CAP-10

**Done when**:

- [x] Error exposes `conflictingCount` and the exact Portuguese singular/plural messages
- [x] Reduce + conflict writes nothing (no `rooms->update`, no cancel)
- [x] Increase, same capacity, and fitting futures still persist
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=UpdateRoom`

**Tests**: unit
**Gate**: quick

---

### T3: Map CapacityReductionBlocked to the capacity field

**What**: Catch `CapacityReductionBlocked` in `UpdateRoomController` and turn it into `ValidationException` on `capacity` using `$exception->getMessage()`.
**Where**: `app/Modules/Room/Infra/Http/Controllers/UpdateRoomController.php`
**Depends on**: T2
**Reuses**: Existing `DeactivationDecisionRequired` catch
**Requirement**: CAP-02, CAP-03, CAP-07, CAP-09

**Done when**:

- [x] Inertia/session validation errors use the application message on `capacity`
- [x] Deactivate-decision mapping is unchanged when there is no capacity conflict

**Tests**: integration
**Gate**: full

---

### T4: Cover UpdateRoom capacity cases in unit tests

**What**: Extend `UpdateRoomTest` (and the reservation fake if T1 left a gap) for every unit item in `harness.tests.unit` except the React one. Seed `participants` on the reservation helper.
**Where**: `tests/Unit/Room/UpdateRoomTest.php`
**Depends on**: T2
**Reuses**: Existing `room()` / `reservation()` helpers; freeze `2026-09-21 12:00:00`
**Requirement**: CAP-01, CAP-02, CAP-03, CAP-04, CAP-05, CAP-06, CAP-07, CAP-08

**Done when**:

- [x] Each listed PHP unit behavior has an assertion on flash/update/cancel side effects
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=UpdateRoom`

**Tests**: unit
**Gate**: quick

---

### T5: Cover PUT /rooms capacity reduction on MySQL

**What**: Add Feature cases on `RoomUpdateHttpTest` for the four `harness.tests.integration` items. Use `Reservation::factory` with explicit `participants` and `starts_at`.
**Where**: `tests/Feature/Room/RoomUpdateHttpTest.php`
**Depends on**: T3
**Reuses**: Existing `travelTo` freeze and `from(rooms.edit)` pattern
**Requirement**: CAP-01, CAP-02, CAP-03, CAP-04, CAP-07

**Done when**:

- [x] Conflict PUTs assert the exact `capacity` message and unchanged rows
- [x] Fitting reduction still redirects with the current success flash
- [x] Gate check passes: `php artisan test --testsuite=Feature --filter=RoomUpdateHttp`

**Tests**: integration
**Gate**: full

---

### T6: Show the capacity-reduction sentence on Room/Edit and document it

**What**: Add a Vitest that Room/Edit renders the AC-002 sentence on Capacidade (`capacity-error`). Add one paragraph under Capacity on `docs/screens/screen-room-form.md` describing the block and the two sentences. Change production JSX only if the existing error slot does not show the string.
**Where**: `resources/js/Pages/Room/Edit.test.jsx`
**Depends on**: T3
**Reuses**: `Create.test.jsx` capacity-error rerender pattern
**Requirement**: CAP-09, CAP-10

**Done when**:

- [x] Vitest finds the singular sentence with `id="capacity-error"`
- [x] Screen doc states the rule and both messages
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: frontend

---

## Phase Execution Map

```
Phase 1 -> Phase 2 -> Phase 3

Phase 1:  T1
Phase 2:  T2 -> T3
Phase 3:  T4    T5    T6
```

```
T1 -> T2 -> T3
T2 -> T4
T3 -> T5
T3 -> T6
```

Execution is sequential. One worker; six tasks fit a single batch.

## Task Granularity Check

| Task | Scope | Status |
| --- | --- | --- |
| T1: Port count method | 1 port + impl + fake | OK if cohesive |
| T2: UpdateRoom + error | 1 use case | Granular |
| T3: Controller mapping | 1 catch | Granular |
| T4: Unit tests | 1 test class | Granular |
| T5: Feature tests | 1 test class | Granular |
| T6: Vitest + screen copy | page test + docs | OK if cohesive |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| --- | --- | --- | --- |
| T1 | None | (root) | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | T2 | T2 -> T3 | Match |
| T4 | T2 | T2 -> T4 | Match |
| T5 | T3 | T3 -> T5 | Match |
| T6 | T3 | T3 -> T6 | Match |

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| --- | --- | --- | --- | --- |
| T1 | Reservation port / Eloquent | integration (via T5) + fake for unit | unit | OK — fake is the unit surface; Eloquent is proven on PUT |
| T2 | UpdateRoom | unit | unit | OK |
| T3 | Controller | integration | integration | OK |
| T4 | UpdateRoom tests | unit | unit | OK |
| T5 | HTTP + MySQL | integration | integration | OK |
| T6 | Room/Edit + screen | unit / none | unit | OK |
