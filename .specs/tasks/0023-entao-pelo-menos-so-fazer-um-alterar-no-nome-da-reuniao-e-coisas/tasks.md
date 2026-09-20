---
harness:
  commits:
    - "feat(reservation): add UpdateReservation use case HTTP and edit page"
    - "feat(resources): add reservation update helper"
    - "feat(routes): add reservation edit and update routes"
    - "test(reservation): cover reservation title and responsible edit"
    - "test(resources): cover reservation update helper"
    - "chore(docs): document partial reservation title and responsible edit"
    - "chore(readme): document partial reservation edit leftover"
    - "chore(specs): record partial reservation-edit plan artifacts"
  tests:
    unit:
      - "UpdateReservation persists trimmed title and responsible and does not change starts_at, ends_at, room_id, participants, or cancelled_at"
      - "UpdateReservation throws ReservationNotFound for a missing id and writes nothing"
      - "UpdateReservation throws ReservationNotFound for a cancelled reservation and writes nothing"
      - "UpdateReservation does not call overlap, duration, capacity, or lock collaborators"
      - "UpdateReservationRequest requires title and responsible with the store Portuguese messages and trims both"
      - "UpdateReservationRequest rejects a payload that includes starts_at, ends_at, room_id, participants, or cancelled_at"
      - "Reservation/Edit exposes title and responsible inputs and keeps room, date, start time, end time, and participants disabled and out of the PUT body"
      - "Reservation/Index shows an Editar link to /reservations/{id}/edit beside Cancelar on an active row"
    integration:
      - "Authenticated PUT /reservations/{id} with title and responsible updates only those columns on MySQL; occupancy columns stay equal to the pre-request row"
      - "Guest GET /reservations/{id}/edit and PUT /reservations/{id} redirect 302 to login and write no reservation rows"
      - "Authenticated PUT or GET edit for a cancelled reservation returns 404 and leaves the row unchanged"
      - "Authenticated GET /reservations/{id}/edit for an active reservation returns Inertia Reservation/Edit with current title, responsible, and occupancy display values"
      - "Authenticated GET /reservations lists an active id for which GET /reservations/{id}/edit is reachable"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Partial reservation edit is covered by UpdateReservation and FormRequest unit tests, Feature HTTP on MySQL 8, and Reservation/Edit plus Index Vitest. docs/test/e2e.md forbids repeating those matrices in the browser. Do not bootstrap Playwright in this task."
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

Add a partial reservation edit: title and responsible only. Occupancy (`starts_at`, `ends_at`, `room_id`, `participants`) and `cancelled_at` stay immutable. The FormRequest prohibits those keys. Cancelled or missing ids return 404. No overlap, duration, or lock on update. No schema change.

**Design (inline, no `design.md`):**

- `ReservationRepository::updateTitleAndResponsible(string $id, string $title, string $responsible): Reservation` — SQL updates only `title` and `responsible` (`updated_at` via Eloquent timestamps). Eager-load `room` on `findById` so edit can show `roomName`.
- Fake: replace those two fields on the seeded entity; leave occupancy fields as seeded.
- `UpdateReservation::execute(string $id, string $title, string $responsible): Reservation`
  - trim title/responsible
  - `findById`; null or `cancelledAt !== null` → `ReservationNotFound`
  - `updateTitleAndResponsible`
  - no `Transaction`, no room lock, no occupancy collaborators
- `UpdateReservationRequest`: `title` and `responsible` `required|string|max:255`; trim like store; `starts_at`, `ends_at`, `room_id`, `participants`, `cancelled_at` → `prohibited` with Portuguese messages.
- Routes (auth group, after create): `GET /reservations/{reservation}/edit` → `EditReservationController` (`reservations.edit`); `PUT /reservations/{reservation}` → `UpdateReservationController` (`reservations.update`).
- GET: `findById`; 404 if null or cancelled; Inertia `Reservation/Edit` props: `id`, `title`, `responsible`, `room_id`, `room_name`, `date` (`Y-m-d` in `app.timezone`), `start_time`/`end_time` (`H:i`), `participants`.
- PUT: `validated()` only into the use case; `ReservationNotFound` → 404; success redirect index + `Reserva atualizada com sucesso.`
- React: `Pages/Reservation/Edit.jsx` (create card layout); `update(form, id)` in `reservations.js`; Index `RowActions` adds `Editar` link.
- Docs: ADR-009 MADR PT; ADR-005 status pointer only; ADR-001 extra note only; new `screen-reservation-edit.md`; list + room-form + README leftover.

## Affected Components

- `app` — Reservation Application, repository port + Eloquent, HTTP edit/update, `routes/web.php`, `Reservation/Edit.jsx`, `Reservation/Index.jsx`, `Services/reservations.js`, unit/Feature/Vitest tests, `docs/adr/009-*`, status note on ADR-005, extra note on ADR-001, screens, README.
- Do not change create occupancy rules, cancel semantics (cancelled cancel stays idempotent), schema, or Playwright.

## Tasks

Execute T1 → T7 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. PHPUnit Unit uses `FakeReservationRepository` only. Assert occupancy fields on the fake entity after execute. FormRequest tests follow `StoreReservationRequestTest` (no HTTP). React mocks Inertia; assert disabled occupancy controls and PUT body keys.

### Integration

See `harness.tests.integration`. Feature suite on MySQL 8. Compare occupancy columns before/after PUT. Guest cases extend `ReservationGuestHttpTest`. Cancelled 404 on GET and PUT. GET edit asserts Inertia component and props. List test: pick an active listed id and GET its edit route. One prohibited-key PUT may prove FormRequest wiring; do not repeat the unit validation matrix.

### E2E

Not applicable — no Playwright project. See `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Unit coverage remains Application-only (≥80%). Frontend coverage via `npm run test:coverage` (≥80%). Do not run `npx playwright test`.

## Definition of Done

- All P1 ACs have a unit and/or integration test as classified above; no duplicated scenario across levels.
- An active reservation can change title/responsible only; occupancy columns cannot change through this screen or PUT.
- Cancelled/missing → 404. Guest → 302.
- ADR-009, README, and screens state the cut, including the capacity-reduction leftover.
- Pint, ESLint, PHP build, and Vite build pass.
- No product commit in PLAN.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `phpunit.xml` (Application coverage ≥80%), `package.json` (`npm run test:coverage`).

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| --- | --- | --- | --- | --- |
| UpdateReservation | unit | persist metadata / missing / cancelled / no occupancy collaborators | `tests/Unit/Reservation/UpdateReservationTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| UpdateReservationRequest | unit | required title/responsible, trim, prohibited occupancy keys | `tests/Unit/Reservation/UpdateReservationRequestTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| Edit/Update controllers + routes | integration | PUT persist, guest 302, cancelled 404, GET edit Inertia, list id → edit reachable; FormRequest wiring only | `tests/Feature/Reservation/ReservationUpdateHttpTest.php` | `php artisan test --testsuite=Feature` |
| Reservation/Edit | unit | editable title/responsible; occupancy disabled and not submitted | `resources/js/Pages/Reservation/Edit.test.jsx` | `npm run test:coverage` |
| Reservation/Index edit action | unit | Editar link beside Cancelar | `resources/js/Pages/Reservation/Index.test.jsx` | `npm run test:coverage` |
| Eloquent metadata write | integration | Exercised by PUT Feature on MySQL 8 | `app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php` | `php artisan test --testsuite=Feature` |
| ADR / screens / README | none | Copy only | `docs/adr/009-*.md`, `docs/screens/*`, `README.md` | build gate only |

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

### Phase 1: Application

```
T1 -> T2
```

### Phase 2: HTTP

```
T3 -> T4
```

### Phase 3: React

```
T5 -> T6
```

### Phase 4: Documentation

```
T7
```

---

## Task Breakdown

### T1: Add title and responsible write on the reservation port

**What**: Add `updateTitleAndResponsible` to `ReservationRepository`, implement it in Eloquent (only those two columns), eager-load `room` on `findById`, and mirror the write on `FakeReservationRepository` so later unit tests compile.
**Where**: `app/Modules/Reservation/Domain/Repositories/ReservationRepository.php`
**Depends on**: None
**Reuses**: `markCanceled` column-limited update style; `toDomain` roomName mapping
**Requirement**: EDIT-01, EDIT-10

**Done when**:

- [x] Interface method exists and Eloquent updates only `title` and `responsible`
- [x] Fake replaces those two fields and leaves occupancy as seeded
- [x] `findById` loads `room` so `roomName` is available for GET edit

**Tests**: unit
**Gate**: quick

---

### T2: Implement UpdateReservation

**What**: Add `UpdateReservation` that trims inputs, throws `ReservationNotFound` when missing or cancelled, writes via the new port method, and never uses Transaction, Clock occupancy rules, or room lock. Cover the four PHP use-case items in `harness.tests.unit`.
**Where**: `app/Modules/Reservation/Application/UseCases/UpdateReservation.php`
**Depends on**: T1
**Reuses**: `ReservationNotFound`; `CancelReservation` find-or-fail shape (cancelled is failure here, not idempotent)
**Requirement**: EDIT-01, EDIT-02, EDIT-03, EDIT-10

**Done when**:

- [x] Active update changes only title/responsible on the fake
- [x] Missing and cancelled throw `ReservationNotFound` and do not call the write (or leave occupancy untouched)
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=UpdateReservation`

**Tests**: unit
**Gate**: quick

---

### T3: Add UpdateReservationRequest

**What**: Add the FormRequest with required trimmed `title`/`responsible` (store messages + `max:255`) and `prohibited` on `starts_at`, `ends_at`, `room_id`, `participants`, `cancelled_at`. Cover the two FormRequest items in `harness.tests.unit`.
**Where**: `app/Modules/Reservation/Infra/Http/Requests/UpdateReservationRequest.php`
**Depends on**: None
**Reuses**: `StoreReservationRequest` trim + Portuguese required messages; `StoreReservationRequestTest` helper style
**Requirement**: EDIT-04, EDIT-05

**Done when**:

- [x] Isolated validator accepts a metadata-only payload
- [x] Required and prohibited cases match the unit list
- [x] Gate check passes: `php artisan test --testsuite=Unit --filter=UpdateReservationRequest`

**Tests**: unit
**Gate**: quick

---

### T4: Wire edit and update HTTP

**What**: Add `EditReservationController` and `UpdateReservationController`, register the two auth routes, and cover every `harness.tests.integration` item (PUT persist, guest 302, cancelled 404, GET edit Inertia, listed id → edit reachable). Extend `ReservationGuestHttpTest` and add Feature tests under `tests/Feature/Reservation/`.
**Where**: `app/Modules/Reservation/Infra/Http/Controllers/UpdateReservationController.php`
**Depends on**: T2, T3
**Reuses**: `CancelReservationController` 404 mapping; `EditRoomController` Inertia prop shape; `ReservationCancelHttpTest` / `ReservationIndexHttpTest` factories
**Requirement**: EDIT-01, EDIT-02, EDIT-03, EDIT-06, EDIT-07, EDIT-08, EDIT-09

**Done when**:

- [x] Named routes `reservations.edit` and `reservations.update` exist inside `auth`
- [x] GET edit 404s cancelled/missing; PUT writes only metadata on MySQL
- [x] Guest data provider includes GET edit and PUT
- [x] Gate check passes: `php artisan test --testsuite=Feature --filter=Reservation`

**Tests**: integration
**Gate**: full

---

### T5: Build Reservation/Edit and the update service

**What**: Add `Reservation/Edit.jsx` in the create-card style with editable title/responsible and disabled occupancy fields. Add `update(form, id)` that `put`s `/reservations/{id}` with only those two keys. Cover the Edit Vitest item and the service put.
**Where**: `resources/js/Pages/Reservation/Edit.jsx`
**Depends on**: T4
**Reuses**: `Reservation/Create.jsx` Field layout and Portuguese labels; `rooms.update` put helper
**Requirement**: EDIT-06, EDIT-11

**Done when**:

- [x] Submit payload has only `title` and `responsible`
- [x] Room, date, start time, end time, participants are disabled (or equivalent not submitted)
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: frontend

---

### T6: Add the list Editar action

**What**: Show `Editar` next to `Cancelar` on active rows (table and mobile card). Link to `/reservations/{id}/edit`. Cover the Index Vitest item.
**Where**: `resources/js/Pages/Reservation/Index.jsx`
**Depends on**: T5
**Reuses**: Existing `RowActions`; `Link` like `Nova reserva`
**Requirement**: EDIT-09

**Done when**:

- [x] Active row has visible `Editar` with the edit href and still has `Cancelar`
- [x] Inactive/empty actions stay an em dash
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: frontend

---

### T7: Record ADR-009 and leftover docs

**What**: Write ADR-009 (MADR, Portuguese) that supersedes only ADR-005’s “no reservation edit” slice and keeps occupancy immutable. Add a status/link line on ADR-005 without rewriting its decision. Add an extra note on ADR-001 (do not rewrite the RF catalog). Add `docs/screens/screen-reservation-edit.md`, extend the list screen (`Editar` + drop the “no edit action” note), update the room-form capacity paragraph and README leftover so “Altere essa reunião” still cannot shrink `participants` here.
**Where**: `docs/adr/009-edicao-parcial-de-reserva-titulo-e-responsavel.md`
**Depends on**: T6
**Reuses**: ADR-005/008 status-pointer style; `create-adr` MADR template in Portuguese
**Requirement**: EDIT-12

**Done when**:

- [x] ADR-009 exists and links the superseded slice
- [x] README and screens state partial edit vs locked occupancy vs capacity leftover
- [x] No product behavior change in this task

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1 -> Phase 2 -> Phase 3 -> Phase 4

Phase 1:  T1 -> T2
Phase 2:  T3 -> T4
Phase 3:  T5 -> T6
Phase 4:  T7
```

```
T1 -> T2
T3 -> T4
T2 -> T4
T4 -> T5
T5 -> T6
T6 -> T7
```

Execution is sequential. One worker; seven tasks fit a single batch.

## Task Granularity Check

| Task | Scope | Status |
| --- | --- | --- |
| T1: Port write method | 1 port + impl + fake | OK if cohesive |
| T2: UpdateReservation + unit | 1 use case | Granular |
| T3: FormRequest + unit | 1 request | Granular |
| T4: HTTP + Feature | routes + 2 controllers + Feature | OK if cohesive |
| T5: Edit page + service | 1 page + put helper | OK if cohesive |
| T6: Index Editar | 1 component action | Granular |
| T7: ADR + screens + README | docs only | OK if cohesive |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| --- | --- | --- | --- |
| T1 | None | (root) | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | None | (root of phase 2) | Match |
| T4 | T2, T3 | T3 -> T4 (T2 is prior phase) | Match |
| T5 | T4 | (T4 prior phase) | Match |
| T6 | T5 | T5 -> T6 | Match |
| T7 | T6 | (T6 prior phase) | Match |

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| --- | --- | --- | --- | --- |
| T1 | Reservation port / Eloquent / fake | unit fake + integration via T4 | unit | OK |
| T2 | UpdateReservation | unit | unit | OK |
| T3 | UpdateReservationRequest | unit | unit | OK |
| T4 | Controllers / routes | integration | integration | OK |
| T5 | Reservation/Edit | unit | unit | OK |
| T6 | Reservation/Index | unit | unit | OK |
| T7 | ADR / screens / README | none | none | OK |
