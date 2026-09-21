---
harness:
  commits:
    - "fix(room): default omitted list status to active"
    - "test(room): cover omitted rooms status defaulting to active"
    - "fix(reservation): align list participants and show passed status"
    - "test(reservation): cover list participants alignment and passed status"
    - "chore(config): default app locale to pt-BR"
    - "chore(docs): document list alignment, passed status, and rooms default"
    - "chore(specs): record list alignment and passed status context"
    - "chore(specs): record list alignment and passed status spec"
    - "chore(specs): record list alignment and passed status tasks"
    - "chore(specs): record list alignment and passed status progress"
    - "chore(specs): record list alignment and passed status reviews"
    - "chore(specs): record list alignment and passed status checks"
    - "chore(specs): record list alignment and passed status validation"
  tests:
    unit:
      - "Reservation::listStatus returns cancelled when cancelledAt is set, including when endsAt is past"
      - "Reservation::listStatus returns passed when cancelledAt is null and endsAt is before now"
      - "Reservation::listStatus returns active when cancelledAt is null and endsAt is not before now, including in-progress and endsAt equal to now"
      - "ListRooms default status argument is active and still forwards all and inactive"
      - "Reservation/Index Participantes header and participant cells share text-center"
      - "Reservation/Index passed row shows Passada, hides Editar and Cancelar, and shows a dash"
      - "Room/Index Status select defaults to Ativas (active) when filters.status is omitted"
      - "Room/Index Ativas omits status from the visit; Todas visits with status=all; Inativas visits with status=inactive; page resets to 1"
      - "Room/Index pagination href omits status when Ativas and includes status=all when Todas"
    integration:
      - "GET /rooms with omitted status echoes filters.status active and excludes inactive rooms while still omitting soft-deleted rooms"
      - "GET /rooms?status=all still lists active and inactive rooms"
      - "GET /reservations with a non-cancelled row whose ends_at is before frozen now returns status passed and status_label Passada"
      - "GET /reservations with a non-cancelled row whose ends_at is after frozen now returns status active and status_label Ativa"
      - "GET /reservations with a cancelled row whose ends_at is before frozen now still returns cancelled and Cancelada"
      - "GET /reservations default/active still includes a past non-cancelled row (Passada), not only future Ativa rows"
      - "Existing Feature Ativa assertions whose fixtures end before frozen now are retargeted to future ends_at or to Passada"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Alignment, rooms default, and Passada labels are covered by Vitest plus Feature HTTP on MySQL. docs/test/e2e.md forbids repeating that matrix in the browser. Do not bootstrap Playwright in this task."
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

Three list fixes: (1) center reservations `Participantes` like rooms `Capacidade` (`text-center` on `th`/`td` only); (2) rooms omitted `status` defaults to `active` (Ativas) and the rooms URL omits `status` when Ativas, including `status=all` for Todas; (3) derive list status from `cancelledAt` then `endsAt` vs `Clock::now()` — `cancelled`/`Cancelada`, `passed`/`Passada`, `active`/`Ativa`. Do not persist Passada. Do not add a Passadas filter. Occupancy stays `cancelled_at`.

**Design (inline, no `design.md`):**

- `Reservation::listStatus(DateTimeImmutable $now): string` in Domain: cancelledAt set → `cancelled`; else endsAt < now → `passed`; else `active`. No Portuguese in Domain.
- `IndexReservationController` injects `Clock` (already bound). `toListItem` maps `cancelled`→`Cancelada`, `passed`→`Passada`, `active`→`Ativa`. Filter `active` still means `cancelled_at` null.
- Frozen Feature now stays `2026-09-21 12:00`. Any existing `status_label` `Ativa` assertion on a row ending at 09:30/10:30 must move that fixture after 12:00 (e.g. 13:00–13:30) or expect `Passada`. Add dedicated past / in-progress / cancelled-past cases.
- Rooms: `IndexRoomController` `$status = ... ?? 'active'`. `ListRooms` / `RoomRepository` / Eloquent / Fake default `'active'`. `IndexRoomRequest` rules stay `sometimes` + `in:all,active,inactive` (omitted still absent from `validated()`).
- `Room/Index.jsx`: default `filters = { status: 'active' }`; `value={filters.status ?? 'active'}`; `statusQuery`/`listingHref` omit when `active`, keep `status=all` and `status=inactive`.
- `Reservation/Index.jsx`: `text-center` on Participantes header and cells. `StatusBadge` already slates non-`active`. `RowActions` already hides non-`active`. Add a Passada fixture in Vitest.
- Screens: replace reservations “ended stays Ativa / do not invent completed”; document Passada; rooms default Ativas and omit-`status`-when-active.

## Affected Components

- `app` — `Reservation` entity + `IndexReservationController`; `ListRooms` + room repository defaults + `IndexRoomController`; `Reservation/Index.jsx`; `Room/Index.jsx`; PHP Unit/Feature tests; Vitest Index tests; `docs/screens/screen-reservations-list.md`; `docs/screens/screen-rooms-list.md`.
- Occupancy, cancel PATCH, reservation Status filter enum, and period/range stay as-is.

## Tasks

Execute T1 → T7 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. PHP Unit stays isolated (pure entity, fake repository, no HTTP). Vitest keeps `@inertiajs/react` mocked. Do not unit-test Eloquent SQL.

### Integration

See `harness.tests.integration`. Real GET `/rooms` and GET `/reservations` + MySQL 8. One omitted-rooms default case plus `status=all` membership. Passada/Ativa/Cancelada labels with `travelTo`. Do not repeat the full period-preset or rooms CRUD matrix.

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

- AC-001: Participantes header/cells share `text-center`.
- AC-002, AC-003, AC-004, AC-012: rooms omitted → Ativas; URL omit-when-active.
- AC-005…AC-011: Cancelada / Passada / Ativa derivation, Ativas still includes past non-cancelled, actions only on `active`.
- Existing Feature Ativa fixtures that end before frozen now are retargeted, not deleted.
- Screen docs match. Gates above pass. No product commit in PLAN.

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
| Reservation::listStatus | unit | cancelled / passed / active branches; cancelled wins; in-progress; endsAt == now | `tests/Unit/Reservation/ReservationListStatusTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| ListRooms default | unit | default listed status `active`; explicit `all`/`inactive` still work | `tests/Unit/Room/ListRoomsTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| IndexRoomController + Eloquent listPage | integration | GET omitted excludes inactive, echoes `active`; `status=all` includes inactive; soft-deleted stay hidden | `tests/Feature/Room/RoomIndexHttpTest.php` | `php artisan test --testsuite=Feature` |
| IndexReservationController mapping | integration | GET past non-cancelled → Passada; future → Ativa; cancelled past → Cancelada; default active still includes past row; retarget old Ativa fixtures | `tests/Feature/Reservation/ReservationIndexHttpTest.php` | `php artisan test --testsuite=Feature` |
| Reservation/Index.jsx | unit | Participantes `text-center`; Passada badge and hidden actions | `resources/js/Pages/Reservation/Index.test.jsx` | `npm run test:coverage` |
| Room/Index.jsx | unit | default Ativas; omit `status` when active; include `status=all`; pagination href | `resources/js/Pages/Room/Index.test.jsx` | `npm run test:coverage` |
| screen docs | none | Screen contract only | `docs/screens/screen-reservations-list.md`, `docs/screens/screen-rooms-list.md` | build / lint |
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

### Phase 1: Rooms default Ativas

```
T1 -> T2
```

### Phase 2: Passed list status

```
T3 -> T4
```

### Phase 3: Reservations list UI

```
T5
```

### Phase 4: Screen contracts

```
T6 -> T7
```

---

## Task Breakdown

### Phase 1: Rooms default Ativas

#### T1: Default omitted rooms status to active on the backend

**What**: Change `IndexRoomController` omitted default from `'all'` to `'active'`. Change `ListRooms::execute`, `RoomRepository::listPage`, Eloquent, and `FakeRoomRepository` default `$status` from `'all'` to `'active'`. Keep `IndexRoomRequest` as `sometimes` + `in:all,active,inactive` (omitted still absent from `validated()`). Update `ListRoomsTest` so `execute(0, 2)` without status lists `active`; keep an explicit `'all'` call for mixed active/inactive. Update `RoomIndexHttpTest`: omitted GET echoes `filters.status` `active` and excludes the inactive factory room; move “including inactive” to `GET /rooms?status=all`. Keep soft-delete exclusion. Do not change create/update/delete.
**Where**: `app/Modules/Room/Infra/Http/Controllers/IndexRoomController.php`
**Depends on**: None
**Reuses**: Reservations omitted → `active` in `IndexReservationController`
**Requirement**: ROOM-01, ROOM-02

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Omitted `GET /rooms` uses `active` and excludes `is_active = false`
- [x] `GET /rooms?status=all` still includes inactive rooms
- [x] `ListRooms` default argument is `active`
- [x] Soft-deleted rooms stay hidden
- [ ] Gate check passes: `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature`

**Tests**: integration
**Gate**: full

---

#### T2: Omit rooms status in the URL when Ativas

**What**: On `Room/Index.jsx`, default `filters` to `{ status: 'active' }`, bind the select with `filters.status ?? 'active'`, and change `statusQuery`/`listingHref` to omit `status` when `active` and to include `status=all` and `status=inactive`. Reset `page` to 1 on change (already). Replace Vitest cases that expect omitted `status` for Todas: Anterior href with `filters.status === 'all'` must include `status=all`. Add: default select Ativas; Ativas visit omits `status`; Todas visits `status=all`.
**Where**: `resources/js/Pages/Room/Index.jsx`
**Depends on**: T1
**Reuses**: `Reservation/Index.jsx` `statusQuery` omit-when-`active`
**Requirement**: ROOM-03, ROOM-04

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Status select defaults to `active` / Ativas
- [x] Visits omit `status` for Ativas and keep it for Todas/Inativas
- [x] Pagination href matches that omit rule
- [ ] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

### Phase 2: Passed list status

#### T3: Add Reservation listStatus

**What**: Add `listStatus(DateTimeImmutable $now): string` on the Domain entity: `cancelledAt !== null` → `cancelled`; else `endsAt < $now` → `passed`; else `active`. No labels, no Laravel. Cover cancelled-wins, past, future, in-progress (`startsAt < now < endsAt`), and `endsAt == now` in `tests/Unit/Reservation/ReservationListStatusTest.php` using `PHPUnit\Framework\TestCase`.
**Where**: `app/Modules/Reservation/Domain/Entities/Reservation.php`
**Depends on**: None
**Reuses**: Existing `Reservation` constructor fields; `Clock` stays out of the entity
**Requirement**: RSV-01, RSV-02, RSV-03, RSV-04, RSV-07

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `listStatus` returns `cancelled` / `passed` / `active` as specified
- [x] Cancelled past is still `cancelled`
- [x] `endsAt == now` is `active`
- [ ] Gate check passes: `php artisan test --testsuite=Unit --coverage --min=80`

**Tests**: unit
**Gate**: quick

---

#### T4: Map listStatus on the index controller and Feature flow

**What**: Inject `Clock` into `IndexReservationController::__invoke`. In `toListItem`, set `status` from `$reservation->listStatus($clock->now())` and `status_label` `Cancelada`/`Passada`/`Ativa`. Do not change filter predicates. Extend `ReservationIndexHttpTest` (frozen `2026-09-21 12:00`): past non-cancelled → `passed`/`Passada`; future non-cancelled → `active`/`Ativa`; cancelled past → `cancelled`/`Cancelada`; default/active still includes the past non-cancelled row. Retarget `test_index_filters_by_room_with_the_resolved_window_and_excludes_canceled_rows` and `test_index_status_all_active_and_cancelled_membership_and_labels` so `Ativa` fixtures have `ends_at` after 12:00 (e.g. 13:00–13:30). Keep occupancy/cancel Feature tests green.
**Where**: `app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php`
**Depends on**: T3
**Reuses**: `LaravelClock` via existing container bind; Feature `travelTo`
**Requirement**: RSV-01, RSV-02, RSV-03, RSV-04, RSV-05, RSV-07

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Inertia rows expose derived `status` / `status_label`
- [x] Feature covers Passada / Ativa / Cancelada and Ativas-includes-past
- [x] Old Ativa assertions on ended fixtures are retargeted, not dropped
- [ ] Gate check passes: `php artisan test --testsuite=Feature`

**Tests**: integration
**Gate**: full

---

### Phase 3: Reservations list UI

#### T5: Center Participantes and render Passada

**What**: On the desktop reservations table, add `text-center` to the `Participantes` header and the participant `td` (same shared class as rooms `Capacidade`; do not add `w-0` / `xl:w-[16%]`). Keep mobile card copy. Rely on existing `StatusBadge` (`active={status === 'active'}`) and `RowActions` (`status !== 'active'` → `—`) so Passada is slate and action-less. Add Vitest: Participantes header/cell share `text-center`; a `status: 'passed'`, `status_label: 'Passada'` row shows `Passada` and `—` without Editar/Cancelar for that row.
**Where**: `resources/js/Pages/Reservation/Index.jsx`
**Depends on**: T4
**Reuses**: Rooms `Capacidade` `text-center` Vitest; existing cancelled-row dash test
**Requirement**: ALIGN-01, RSV-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Participantes header and cells include `text-center`
- [x] Passada row shows the label and hides actions
- [x] Cancelled-row dash contract stays
- [ ] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

### Phase 4: Screen contracts

#### T6: Document Passada and centered Participantes

**What**: Update `docs/screens/screen-reservations-list.md`: `Participantes` display rule includes centering with the header (`text-center`). Replace “If an active reservation has already ended, it continues to use Ativa. Do not invent a completed status…” with the three-way badge table `Ativa` / `Passada` / `Cancelada` (`active` / `passed` / `cancelled`). Keep Status filter `all`/`active`/`cancelled` (default Ativas). Record that Ativas still lists `cancelled_at` null rows including Passada, and that Editar/Cancelar stay only on `active`.
**Where**: `docs/screens/screen-reservations-list.md`
**Depends on**: T5
**Reuses**: Existing status-badge table in the same screen
**Requirement**: ALIGN-01, RSV-01, RSV-02, RSV-03, RSV-05, RSV-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Screen doc centers Participantes
- [x] Passada is documented; the old “ended stays Ativa” sentence is gone
- [x] Filter enum is unchanged
- [ ] Gate check passes: `vendor/bin/pint --test` && `npm run lint`

**Tests**: none
**Gate**: build

---

#### T7: Document rooms omitted status as Ativas

**What**: Update `docs/screens/screen-rooms-list.md` Status filter: default `active` (`Ativas`); omitted `status` means Ativas; `Todas` is `status=all` (not omitted). Query omit when Ativas, same idea as reservations. Keep labels `Todas` / `Ativas` / `Inativas` and badges `Ativa` / `Inativa`. Update required-states “all-status filter” so the default populated list is Ativas.
**Where**: `docs/screens/screen-rooms-list.md`
**Depends on**: T6
**Reuses**: Reservations omit-when-Ativas wording from T6
**Requirement**: ROOM-01, ROOM-03, ROOM-04

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Rooms screen default is Ativas / omitted `status`
- [x] `Todas` is documented as `status=all`
- [ ] Gate check passes: `vendor/bin/pint --test` && `npm run lint`

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1 → Phase 2 → Phase 3 → Phase 4

Phase 1:  T1 -> T2
Phase 2:  T3 -> T4
Phase 3:  T5
Phase 4:  T6 -> T7
```

T3 has no intra-phase predecessor (Depends on: None). T5 depends on T4 (previous phase). T6 depends on T5. Execution is sequential. Seven tasks fit one Execute batch.

**How phase-based execution works:**

At Execute, pack phases into ~7-task batches. This feature is one batch (≤8 tasks): run T1–T7 inline. No sub-agents.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Rooms omitted default | 1 controller action (use case/repo defaults must match) | Cohesive |
| T2: Room/Index query omit | 1 page | Granular |
| T3: Reservation::listStatus | 1 entity method | Granular |
| T4: Index controller mapping + Feature | 1 controller action | Cohesive |
| T5: Reservation/Index alignment + Passada | 1 page | Cohesive |
| T6: reservations screen doc | 1 doc | Granular |
| T7: rooms screen doc | 1 doc | Granular |

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | Phase 1 start | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | None | Phase 2 start | Match |
| T4 | T3 | T3 -> T4 | Match |
| T5 | T4 | Cross-phase (no intra-phase arrow required) | Match |
| T6 | T5 | Cross-phase (no intra-phase arrow required) | Match |
| T7 | T6 | T6 -> T7 | Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | IndexRoomController + ListRooms + Eloquent | integration (+ ListRooms unit in same task) | integration | OK |
| T2 | Room/Index.jsx | unit | unit | OK |
| T3 | Reservation::listStatus | unit | unit | OK |
| T4 | IndexReservationController | integration | integration | OK |
| T5 | Reservation/Index.jsx | unit | unit | OK |
| T6 | screen-reservations-list.md | none | none | OK |
| T7 | screen-rooms-list.md | none | none | OK |
