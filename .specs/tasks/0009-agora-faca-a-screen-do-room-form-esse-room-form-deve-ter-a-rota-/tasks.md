---
harness:
  commits:
    - "chore(harness): restore frontend command id in stack catalog"
    - "feat(resources): show flash messages as a bottom-right toast"
    - "feat(room): persist created rooms as always active"
    - "feat(room): implement room form create and deactivate confirmation"
    - "test(resources): cover flash toast auto-dismiss and close"
    - "test(room): cover room form screen and deactivate radios"
    - "test(room): cover always-active create-room contract"
    - "chore(docs): hide room id from rooms list screen"
    - "chore(specs): record room form context"
    - "chore(specs): record room form progress"
    - "chore(specs): record room form reviews"
    - "chore(specs): record room form spec"
    - "chore(specs): record room form tasks"
    - "chore(specs): record room form checks"
    - "chore(specs): record room form validation"
  tests:
    unit:
      - "CreateRoom persists name and capacity with is_active true even when a caller would prefer inactive"
      - "StoreRoomRequest rejects missing name with Informe o nome da sala."
      - "StoreRoomRequest rejects missing capacity with Informe a capacidade da sala."
      - "StoreRoomRequest rejects non-integer capacity with A capacidade deve ser um número inteiro."
      - "StoreRoomRequest rejects capacity less than 1 with A capacidade deve ser de pelo menos 1 pessoa."
      - "StoreRoomRequest trims name and excludes is_active from validated data"
      - "UpdateRoom keeps the stored is_active when the caller omits status"
      - "UpdateRoom persists is_active false when the caller confirms deactivation"
      - "UpdateRoomRequest accepts name and capacity without is_active"
      - "UpdateRoomRequest still trims name and rejects invalid capacity with the same messages as store"
      - "Room/Create shows Nova sala copy, only Nome and Capacidade with required asterisks, and no Status field"
      - "Room/Create posts only name and capacity to /rooms and does not send is_active"
      - "Room/Create shows backend field errors with aria-invalid and focuses the first invalid field"
      - "Room/Create shows Salvando... and disables Salvar and Cancelar while processing"
      - "Room/Create Cancelar goes to /rooms without posting"
      - "Room/Create shows Não foi possível salvar a sala. Tente novamente. on unexpected failure"
      - "Room/Edit prefills name and capacity, shows read-only Ativa or Inativa, and reuses the shared form"
      - "Room/Edit shows Desativar sala while the room is active and opens confirmation before any PUT"
      - "Room/Edit keeps radio Desativar sala sem reunião selected and hides meeting questions when has_registered_meetings is false"
      - "Room/Edit shows keep/cancel meeting radios and hides Desativar sala sem reunião when has_registered_meetings is true"
      - "Room/Edit confirm deactivation puts name, capacity, and is_active false once"
      - "Room/Edit Salvar puts only name and capacity and does not send is_active"
      - "Room/Edit dialog Cancelar closes without putting"
      - "Room/Edit hides Desativar sala and shows Ativar sala when the room is already inactive"
    integration:
      - "Authenticated GET /rooms/create renders Inertia Room/Create"
      - "POST /rooms with valid name and capacity persists UUID v7 id, ADR-004 columns, is_active true, and redirects to /rooms with Sala criada com sucesso."
      - "POST /rooms with is_active false still persists is_active true"
      - "rooms.is_active MySQL column default is boolean true"
      - "POST /rooms with invalid name or capacity returns Form Request errors and persists nothing"
      - "Authenticated GET /rooms/{room}/edit renders Room/Edit with room fields and has_registered_meetings false"
      - "PUT /rooms/{room} with name and capacity and no is_active keeps the stored is_active"
      - "PUT /rooms/{room} with is_active false persists is_active false and redirects with Sala atualizada com sucesso."
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in the repo. stack.yml lists npx playwright test but it is not runnable. Create/edit flows are covered by Vitest page tests and Feature HTTP tests. Do not bootstrap Playwright in this task."
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

Replace the 0007 stub room form with the create/edit screen from `docs/screens/screen-room-form.md`, plus the human revision of deactivation. Keep `GET /rooms/create` and `POST /rooms`. Keep MySQL `is_active` boolean `default(true)`. `CreateRoom` always persists `is_active=true`. `StoreRoomRequest` validates only `name` and `capacity`. Shared `RoomForm` handles create (no Status) and edit (read-only status, `Desativar sala` button, confirmation radios). `Salvar` on edit does not change status. Confirmed deactivation persists `is_active` false. Skip reservation keep/cancel writes and any second-database transaction.

**Design (inline, no `design.md`):** `CreateRoom::execute(string $name, int $capacity)` calls `RoomRepository::create($name, $capacity, true)`. `StoreRoomController` passes only validated name/capacity. `UpdateRoom::execute(..., ?bool $isActive = null)` uses `$isActive ?? $existing->isActive`. `UpdateRoomController` passes null when `is_active` is absent from `validated()`. Extract `resources/js/Pages/Room/Components/RoomForm.jsx` with `mode: 'create' | 'edit'`. Create `useForm({ name: '', capacity: '' })` and `store(...)`. Edit `useForm({ name, capacity })`; `Salvar` calls `update(form, room.id)`; confirmed deactivation `PUT`s `{ name, capacity, is_active: false }`. `EditRoomController` adds `has_registered_meetings: false`. Dialog radios: no meetings → `Desativar sala sem reunião` selected; meetings → keep/cancel questions only. Confirming those radios still only writes `is_active` false.

## Affected Components

- `app` — `CreateRoom.php`, `StoreRoomRequest.php`, `StoreRoomController.php`, `UpdateRoom.php`, `UpdateRoomRequest.php`, `UpdateRoomController.php`, `EditRoomController.php`, `Pages/Room/Create.jsx`, `Pages/Room/Edit.jsx`, `Pages/Room/Components/RoomForm.jsx`, matching Unit/Feature/Vitest tests. Migration already has `boolean('is_active')->default(true)` — do not rewrite unless a test proves the default is missing.

## Tasks

Execute T1 → T6 as in **Task Breakdown**.

## Planned Tests

### Unit

- CreateRoom persists name and capacity with `is_active` true even when a caller would prefer inactive
- StoreRoomRequest rejects missing name/capacity and non-integer / min capacity with the screen Portuguese messages
- StoreRoomRequest trims name and excludes `is_active` from validated data
- UpdateRoom keeps stored `is_active` when status is omitted and persists false when deactivation is confirmed
- UpdateRoomRequest accepts name and capacity without `is_active`
- Room/Create: copy, required fields, no Status, post shape, errors, processing, cancel, unexpected failure
- Room/Edit: read-only status, `Desativar sala`, radios, confirm PUT `is_active` false, `Salvar` omits status, dialog cancel, `Ativar sala` when already inactive

### Integration

- Authenticated `GET /rooms/create` renders Inertia `Room/Create`
- `POST /rooms` valid create persists UUID v7, ADR-004 columns, `is_active` true, flash redirect
- `POST /rooms` with `is_active` false still persists true
- `rooms.is_active` MySQL column default is boolean true
- Invalid create payload returns Form Request errors and persists nothing
- `GET /rooms/{room}/edit` includes room fields and `has_registered_meetings` false
- `PUT` without `is_active` keeps stored status; `PUT` with `is_active` false persists false

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

- AC-001…AC-016 have tests at the levels above or are explicitly out of scope (AC-014 is protected by the absence of reservation writes plus Vitest asserting no reservation payload).
- Existing guest, 404, list, and schema tests stay green.
- Gates above pass. No silent test deletions.
- `harness.commits` matches dirty-path grouping (`room` production then `room` tests).
- No product commit from this Plan phase.

---

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

---

**Design**: inline in Summary (MVP; no `design.md`)
**Status**: Executed

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `docs/reviews/review-tests.md`, `harness/stack.yml`, `phpunit.xml`, `package.json`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| CreateRoom / UpdateRoom use cases | unit | Always-active create; omit-status keeps current; confirmed deactivate writes false; no real DB | `tests/Unit/Room/CreateRoomTest.php`, `tests/Unit/Room/UpdateRoomTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| StoreRoomRequest / UpdateRoomRequest | unit | Required/type/min/trim; store excludes `is_active`; update allows omitted `is_active`; Portuguese messages | `tests/Unit/Room/*RequestTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| React Room form pages | unit | Copy, fields, deactivate button, radios, processing, errors, cancel, mocked Inertia submit | `resources/js/Pages/Room/*.test.jsx` | `npm run test:coverage` |
| Create/update HTTP | integration | Happy path, ignored create status, column default, omitted update status, deactivate `is_active` false, invalid payload, redirect/flash, MySQL 8 | `tests/Feature/Room/RoomStoreHttpTest.php`, `tests/Feature/Room/RoomUpdateHttpTest.php`, `tests/Feature/Room/RoomSchemaTest.php` | `php artisan test --testsuite=Feature` |
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

### Phase 1: Create persistence contract

```
T1 -> T2 -> T3
```

### Phase 2: Create screen

```
T4
```

### Phase 3: Edit deactivation

```
T5 -> T6
```

---

## Task Breakdown

### Phase 1: Create persistence contract

#### T1: Force CreateRoom to persist active rooms

**What**: Change `CreateRoom` so it always calls `RoomRepository::create($name, $capacity, true)`. Drop caller-controlled status (keep a unused optional argument only if `StoreRoomController` still compiles until T3). Update `CreateRoomTest` so `execute(..., false)` still persists `is_active` true. Keep the repository port signature unchanged.
**Where**: `app/Modules/Room/Application/UseCases/CreateRoom.php`
**Depends on**: None
**Reuses**: `FakeRoomRepository`, existing `CreateRoomTest`
**Requirement**: ROOM-02

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `CreateRoom` has no caller-controlled status
- [x] Unit test asserts persisted `is_active` true
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=CreateRoomTest`

**Tests**: unit
**Gate**: quick

---

#### T2: Stop validating create status on StoreRoomRequest

**What**: Remove `is_active` from `StoreRoomRequest` rules so it never appears in `validated()`. Keep `name` `required|string|max:255` with trim. Set capacity messages to `Informe a capacidade da sala.` (required), `A capacidade deve ser um número inteiro.` (integer), `A capacidade deve ser de pelo menos 1 pessoa.` (min). Extend `StoreRoomRequestTest` for those messages and for `validated()` excluding posted `is_active`.
**Where**: `app/Modules/Room/Infra/Http/Requests/StoreRoomRequest.php`
**Depends on**: T1
**Reuses**: existing `StoreRoomRequestTest` helpers
**Requirement**: ROOM-03, ROOM-02

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `validated()` keys are only `name` and `capacity`
- [x] Message assertions match the spec
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=StoreRoomRequestTest`

**Tests**: unit
**Gate**: quick

---

#### T3: Wire StoreRoomController and create HTTP tests

**What**: Call `CreateRoom::execute($data['name'], (int) $data['capacity'])` only. Keep redirect/flash. Update `RoomStoreHttpTest`: valid create stays active; add `POST` with `is_active=false` still stores true; refresh invalid-capacity expected messages; keep ignore-`location` and duplicate-name cases. Assert `rooms.is_active` MySQL default is true (Schema/column default) without changing the migration unless that assertion fails.
**Where**: `app/Modules/Room/Infra/Http/Controllers/StoreRoomController.php`
**Depends on**: T2
**Reuses**: `RoomStoreHttpTest`, `CreateRoomController` (unchanged), `RoomSchemaTest` if the default assertion fits there
**Requirement**: ROOM-02, ROOM-03, ROOM-08

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Controller does not read `is_active`
- [x] Feature tests listed in Planned Tests / Integration for store and column default pass
- [x] Gate check passes: `php artisan test --testsuite=Feature --filter=RoomStoreHttpTest`

**Tests**: integration
**Gate**: full

---

### Phase 2: Create screen

#### T4: Build the create room form screen

**What**: Extract `Pages/Room/Components/RoomForm.jsx` with `mode` `create` | `edit` (edit deactivate UI mounted in T6). Rewrite `Room/Create.jsx` for create mode: copy, required Nome/Capacidade with red asterisks and `aria-required`, `inputMode="numeric"` on capacity, no Status, centered card, `Salvar`/`Cancelar` (Cancelar → `/rooms`, disabled while processing), `Salvando...`, field errors, focus first invalid field, general failure banner. `useForm({ name: '', capacity: '' })`. Submit via `store` from `Services/rooms.js`. Replace `Create.test.jsx` so it no longer expects `Situação`/`is_active`.
**Where**: `resources/js/Pages/Room/Create.jsx`
**Depends on**: T3
**Reuses**: `AppLayout`, `Services/rooms.js` `store`, User/Create error/processing patterns
**Requirement**: ROOM-01, ROOM-04, ROOM-05

**Tools**:

- MCP: `context7` (Inertia React `useForm` `processing` `errors`)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Create UI matches AC-001, AC-002, AC-006, AC-007, AC-015 for create mode
- [x] Vitest covers the Planned Tests / Unit create-page items
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

### Phase 3: Edit deactivation

#### T5: Keep current status unless update sends is_active

**What**: Change `UpdateRoom` so omitted/`null` status keeps `$existing->isActive` and an explicit false persists inactive. Keep `UpdateRoomRequest` `is_active` as `sometimes|boolean` (not required). Change `UpdateRoomController` to pass `null` when the key is absent — never default missing status to `true`. Align name/capacity messages with store. Split `UpdateRoomRequestTest` away from “same contract as store” only where messages change; add an assertion that omitting `is_active` still validates. Extend `UpdateRoomTest` and `RoomUpdateHttpTest`: `PUT` without `is_active` leaves an inactive room inactive; `PUT` with `is_active` false persists false.
**Where**: `app/Modules/Room/Application/UseCases/UpdateRoom.php`
**Depends on**: T4
**Reuses**: `UpdateRoomTest`, `FakeRoomRepository`, `UpdateRoomRequestTest`, `RoomUpdateHttpTest`
**Requirement**: ROOM-07

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Omitted `is_active` keeps the stored value
- [x] Confirmed false persists false
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=UpdateRoom` && `php artisan test --testsuite=Feature --filter=RoomUpdateHttpTest`

**Tests**: integration
**Gate**: full

---

#### T6: Add deactivate button, confirmation radios, and edit HTTP props

**What**: Point `Room/Edit.jsx` at shared `RoomForm` with `mode="edit"`: copy, prefill, read-only `Ativa`/`Inativa`, `Desativar sala` while active, confirmation dialog, radio `Desativar sala sem reunião` selected when `has_registered_meetings` is false, keep/cancel radios only when that prop is true, dialog `Cancelar`/Escape without PUT, confirm `PUT`s `is_active` false, `Salvar` omits status, `Ativar sala` when inactive, processing `Salvando...` / `Desativando...`. Pass `has_registered_meetings: false` from `EditRoomController`. Rewrite `Edit.test.jsx`. Extend `RoomUpdateHttpTest` GET edit for the new prop. Do not add reservation payloads or a reservations table.
**Where**: `resources/js/Pages/Room/Edit.jsx`
**Depends on**: T5
**Reuses**: `RoomForm` from T4, `Services/rooms.js` `update`, `EditRoomController`
**Requirement**: ROOM-06, ROOM-07

**Tools**:

- MCP: `context7` (Inertia React `useForm`)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Button, radios, and confirm behavior match AC-008, AC-009, AC-010, AC-011, AC-014
- [x] Feature GET edit exposes `has_registered_meetings` false
- [x] Gate check passes: `php artisan test --testsuite=Feature --filter=RoomUpdateHttpTest` && `npm run test:coverage`

**Tests**: integration
**Gate**: full

---

## Phase Execution Map

```
Phase 1 -> Phase 2 -> Phase 3

Phase 1:  T1 -> T2 -> T3
Phase 2:  T4
Phase 3:  T5 -> T6
```

Execution is strictly sequential. Six tasks fit one Execute batch (no sub-agent split).

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Force CreateRoom active | 1 use case | Granular |
| T2: StoreRoomRequest contract | 1 Form Request | Granular |
| T3: Store controller + HTTP tests | 1 controller | Granular |
| T4: Create screen | 1 page (+ extracted form) | Granular |
| T5: UpdateRoom keep-current status | 1 use case | Granular |
| T6: Edit deactivate UI | 1 page | Granular |

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | no inbound arrow | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | T2 | T2 -> T3 | Match |
| T4 | T3 | cross-phase (Phase 1 -> Phase 2) | Match |
| T5 | T4 | cross-phase (Phase 2 -> Phase 3) | Match |
| T6 | T5 | T5 -> T6 | Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | CreateRoom use case | unit | unit | OK |
| T2 | StoreRoomRequest | unit | unit | OK |
| T3 | Store HTTP controller | integration | integration | OK |
| T4 | React Room form pages | unit | unit | OK |
| T5 | UpdateRoom use case + update HTTP | integration (highest of unit+integration) | integration | OK |
| T6 | React edit page + edit HTTP props | integration (highest of unit+integration) | integration | OK |
