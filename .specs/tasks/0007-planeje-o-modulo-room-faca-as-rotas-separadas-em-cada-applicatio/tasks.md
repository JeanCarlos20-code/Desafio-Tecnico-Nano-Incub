---
harness:
  commits:
    - "chore(.env): force argon hash driver in phpunit env"
    - "feat(app): bind room repository"
    - "feat(database): add rooms table with uuidv7 and soft deletes"
    - "chore(harness): add build command to stack catalog"
    - "feat(http): share auth user and flash with inertia"
    - "chore(phpunit): include room application in coverage"
    - "feat(resources): add admin shell and rooms inertia service"
    - "feat(room): add room use cases"
    - "feat(room): add domain room entity and repository"
    - "feat(room): add eloquent room persistence"
    - "feat(room): add room http adapters"
    - "feat(room): add rooms list and form pages"
    - "feat(routes): register authenticated room routes"
    - "test(database): cover rooms schema"
    - "test(harness): align e2e fixture with specs chore commit"
    - "test(reservation): keep reservation page tests with admin shell"
    - "test(resources): cover admin shell and rooms service"
    - "test(room): cover rooms list and form pages"
    - "test(room): cover room http flows"
    - "test(room): cover room use cases and request validation"
    - "test(room): cover update room use case"
    - "test(tests): clear cached config before phpunit boot"
    - "chore(specs): record rooms module plan artifacts"
  tests:
    unit:
      - "CreateRoom persists name, capacity, and is_active through RoomRepository and returns a room with those values"
      - "CreateRoom treats omitted is_active as true"
      - "ListRooms returns non-deleted rooms in id ascending order for the requested page and includes inactive rooms"
      - "FindRoom returns the room when the port finds it"
      - "FindRoom throws RoomNotFound when the port returns null"
      - "UpdateRoom persists the new name, capacity, and is_active when the room exists"
      - "UpdateRoom throws RoomNotFound when the room is missing"
      - "DeleteRoom calls repository delete when the room exists"
      - "DeleteRoom throws RoomNotFound when the room is missing"
      - "Room Application, Domain, and RoomNotFound sources do not import Illuminate"
      - "StoreRoomRequest rejects missing name with Informe o nome da sala."
      - "StoreRoomRequest rejects missing or non-integer capacity"
      - "StoreRoomRequest rejects capacity less than 1"
      - "StoreRoomRequest trims name"
      - "UpdateRoomRequest applies the same field contract as store"
      - "Room/Index shows ID, name, capacity, Ativa/Inativa text, DD/MM/YYYY date, edit and delete actions"
      - "Room/Index shows the empty state and Nova sala when there are no rooms"
      - "Room/Index opens the delete dialog with the room name and does not call delete until confirm"
      - "Room/Index cancel/Escape does not call delete and returns focus"
      - "Room/Index confirm calls DELETE once and shows Excluindo... while processing"
      - "Room/Index shows the load-failure copy with retry"
      - "Room/Create and Room/Edit submit only name, capacity, and is_active"
      - "AppLayout marks Salas with aria-current on /rooms, shows the shared user name, and posts logout from the account menu"
    integration:
      - "Guest requests to GET/POST/PUT/DELETE room routes redirect to /login without writing rows"
      - "Authenticated GET /rooms renders Inertia Room/Index with non-deleted rooms including inactive ones and excluding soft-deleted ones, ordered by id"
      - "GET /rooms paginates by 15 and keeps query parameters on links when more than 15 rooms exist"
      - "POST /rooms with valid payload persists UUID v7 id, ADR-004 columns only, default is_active true when omitted, and redirects to /rooms with Sala criada com sucesso."
      - "POST /rooms with invalid name or capacity returns the Form Request errors and persists nothing"
      - "POST /rooms ignores extra fields such as location"
      - "Authenticated GET /rooms/create renders Inertia Room/Create"
      - "Authenticated GET /rooms/{room}/edit renders Inertia Room/Edit with that room's fields"
      - "PUT /rooms/{room} with valid payload updates name, capacity, and is_active and redirects with Sala atualizada com sucesso."
      - "GET/PUT/DELETE for unknown, malformed, or soft-deleted {room} return 404 and leave other rows unchanged"
      - "DELETE /rooms/{room} sets deleted_at, redirects with Sala excluída com sucesso., and omits the room from a later GET /rooms"
      - "rooms table columns are exactly id, name, capacity, is_active, created_at, updated_at, deleted_at"
      - "GET /rooms shares auth.user.name from the authenticated administrator and does not share a password"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in the repo. stack.yml lists npx playwright test but it is not runnable. Selected rooms flows are covered by Feature HTTP tests and Vitest page tests. Do not bootstrap Playwright in this task."
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

Add `app/Modules/Room` using the User module layout, not User's fat `UserController`. Domain `Room` + `RoomRepository`; Application `ListRooms`, `CreateRoom`, `FindRoom`, `UpdateRoom`, `DeleteRoom` + `RoomNotFound`; Infra Eloquent model (`HasUuids`, `SoftDeletes`) and six invokable controllers with `StoreRoomRequest`/`UpdateRoomRequest`. Persist only ADR-004 columns. Register authenticated `/rooms` routes. Upgrade `AppLayout` to the rooms-list admin shell, share `auth.user.name` and flash, and ship `Room/Index` plus minimal `Room/Create` and `Room/Edit`.

**Design (inline, no `design.md`):** see Selected Approach in `spec.md`.

## Affected Components

- `app` — `app/Modules/Room/**`, `database/migrations/*_create_rooms_table.php`, `database/factories/RoomFactory.php`, `app/Providers/AppServiceProvider.php`, `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `phpunit.xml`, `resources/js/Layouts/AppLayout.jsx`, `resources/js/Pages/Room/**`, `resources/js/Services/rooms.js`, `tests/Unit/Room/**`, `tests/Feature/Room/**`, `tests/Feature/Database/RoomsMigrationTest.php`, layout/reservation Vitest updates.

## Tasks

Execute T1 → T7 as in **Task Breakdown**.

## Planned Tests

### Unit

- CreateRoom persists name, capacity, and is_active through RoomRepository and returns a room with those values
- CreateRoom treats omitted is_active as true
- ListRooms returns non-deleted rooms in id ascending order for the requested page and includes inactive rooms
- FindRoom returns the room when the port finds it
- FindRoom throws RoomNotFound when the port returns null
- UpdateRoom persists the new name, capacity, and is_active when the room exists
- UpdateRoom throws RoomNotFound when the room is missing
- DeleteRoom calls repository delete when the room exists
- DeleteRoom throws RoomNotFound when the room is missing
- Room Application, Domain, and RoomNotFound sources do not import Illuminate
- StoreRoomRequest rejects missing name with Informe o nome da sala.
- StoreRoomRequest rejects missing or non-integer capacity
- StoreRoomRequest rejects capacity less than 1
- StoreRoomRequest trims name
- UpdateRoomRequest applies the same field contract as store
- Room/Index shows ID, name, capacity, Ativa/Inativa text, DD/MM/YYYY date, edit and delete actions
- Room/Index shows the empty state and Nova sala when there are no rooms
- Room/Index opens the delete dialog with the room name and does not call delete until confirm
- Room/Index cancel/Escape does not call delete and returns focus
- Room/Index confirm calls DELETE once and shows Excluindo... while processing
- Room/Index shows the load-failure copy with retry
- Room/Create and Room/Edit submit only name, capacity, and is_active
- AppLayout marks Salas with aria-current on /rooms, shows the shared user name, and posts logout from the account menu

### Integration

- Guest requests to GET/POST/PUT/DELETE room routes redirect to /login without writing rows
- Authenticated GET /rooms renders Inertia Room/Index with non-deleted rooms including inactive ones and excluding soft-deleted ones, ordered by id
- GET /rooms paginates by 15 and keeps query parameters on links when more than 15 rooms exist
- POST /rooms with valid payload persists UUID v7 id, ADR-004 columns only, default is_active true when omitted, and redirects to /rooms with Sala criada com sucesso.
- POST /rooms with invalid name or capacity returns the Form Request errors and persists nothing
- POST /rooms ignores extra fields such as location
- Authenticated GET /rooms/create renders Inertia Room/Create
- Authenticated GET /rooms/{room}/edit renders Inertia Room/Edit with that room's fields
- PUT /rooms/{room} with valid payload updates name, capacity, and is_active and redirects with Sala atualizada com sucesso.
- GET/PUT/DELETE for unknown, malformed, or soft-deleted {room} return 404 and leave other rows unchanged
- DELETE /rooms/{room} sets deleted_at, redirects with Sala excluída com sucesso., and omits the room from a later GET /rooms
- rooms table columns are exactly id, name, capacity, is_active, created_at, updated_at, deleted_at
- GET /rooms shares auth.user.name from the authenticated administrator and does not share a password

### E2E

- not applicable: No Playwright project, config, or dependency exists. Do not bootstrap Playwright. Feature HTTP + Vitest cover the rooms flows.

## Required Gates

| Gate | Command | Required |
| ---- | ------- | -------- |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` | yes |
| integration | `php artisan test --testsuite=Feature` | yes |
| frontend | `npm run test:coverage` | yes |
| lint | `vendor/bin/pint --test` | yes |
| frontend_lint | `npm run lint` | yes |
| php_build | `composer run build` | yes |
| frontend_build | `npm run build` | yes |

Do not require `npx playwright test`.

## Definition of Done

- ROOM-01…ROOM-07 have tests at the levels above.
- Gates pass. No silent test deletions. Existing User and login tests stay green.
- `rooms` has only ADR-004 columns. UUID v7 via `HasUuids`. Soft deletes work.
- Six invokable controllers, two Form Requests, five use cases. No fat controller. No Illuminate in Domain/Application.
- List screen matches `docs/screens/screen-rooms-list.md`. Create/edit only `name`, `capacity`, `is_active`.
- Guests redirected to `/login`. No reservation FK, no Playwright, no extra room fields.
- No product commit from this Plan phase.

---

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

---

**Design**: inline in Summary and `spec.md` Selected Approach (MVP; no `design.md`)
**Status**: Implemented

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `docs/reviews/review-tests.md`, `harness/stack.yml`, `phpunit.xml`, `vite.config.js`. Vitest is installed; Playwright is not.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Room use cases + `RoomNotFound` | unit | Success, not-found, default `is_active`, paging args, no Illuminate; 1:1 with ROOM-01…ROOM-05 application behavior | `tests/Unit/Room/*Test.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| Domain `Room` + `RoomRepository` | none | Contract only; exercised via use-case fakes | `app/Modules/Room/Domain/**` | lint / php_build |
| `StoreRoomRequest` / `UpdateRoomRequest` | unit | Exhaustive field contract (required, types, min capacity, trim). No HTTP | `tests/Unit/Room/StoreRoomRequestTest.php`, `tests/Unit/Room/UpdateRoomRequestTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| Eloquent Room model / migration / factory | integration | Columns match ADR-004; UUID; soft delete column present | `tests/Feature/Database/RoomsMigrationTest.php`, `tests/Feature/Room/RoomSchemaTest.php` | `php artisan test --testsuite=Feature` |
| Invokable room controllers + routes + middleware | integration | Guest redirect; each action happy path; representative validation on the route; 404 unknown/trashed; persist/soft-delete effects; flash; shared `auth.user.name` | `tests/Feature/Room/*HttpTest.php` | `php artisan test --testsuite=Feature` |
| `AppLayout` admin shell | unit | Sidebar, `aria-current`, shared name, account logout; empty/title regressions updated | `resources/js/Layouts/AppLayout.test.jsx` | `npm run test:coverage` |
| `Room/Index` + delete dialog + `rooms` service | unit | Table columns, empty, dialog confirm/cancel, processing, load failure, Nova sala href | `resources/js/Pages/Room/*.test.jsx`, `resources/js/Services/rooms.test.js` | `npm run test:coverage` |
| `Room/Create` + `Room/Edit` | unit | Only ADR-004 writable fields; submit goes through `rooms` service | `resources/js/Pages/Room/Create.test.jsx`, `resources/js/Pages/Room/Edit.test.jsx` | `npm run test:coverage` |
| Playwright e2e | none (deferred) | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After unit-only PHP tasks | `php artisan test --testsuite=Unit --coverage --min=80` |
| Full | After HTTP or persistence tasks | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` |
| Build | After frontend tasks or phase end | `vendor/bin/pint --test` && `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run lint` && `npm run test:coverage` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Persistence foundation

```
T1 → T2
```

### Phase 2: Application use cases

```
T3
```

### Phase 3: HTTP adapters

```
T4
```

### Phase 4: Admin shell and Inertia pages

```
T5 → T6 → T7
```

---

## Task Breakdown

### Phase 1: Persistence foundation

### T1: Add rooms migration matching ADR-004

**What**: Create a Laravel migration that builds `rooms` with `uuid` PK `id`, `string name`, `unsignedInteger capacity`, `boolean is_active` default true, `timestamps()`, `softDeletes()`. No other columns. Cover with a Feature schema listing test like `UsersMigrationTest`.
**Where**: `database/migrations/2026_09_18_120000_create_rooms_table.php`
**Depends on**: None
**Reuses**: `database/migrations/0001_01_01_000000_create_users_table.php`
**Requirement**: ROOM-07

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Migration creates exactly `id`, `name`, `capacity`, `is_active`, `created_at`, `updated_at`, `deleted_at`
- [x] `tests/Feature/Database/RoomsMigrationTest.php` asserts that column list (sorted) and no extra columns
- [x] Gate check passes: `php artisan test --testsuite=Feature`
- [x] Test count: existing Feature tests remain; no silent deletions

**Tests**: integration
**Gate**: full

---

### T2: Add Room domain, Eloquent model, factory, and repository

**What**: Add Domain `Room` entity and `RoomRepository` port (`listPage`, `findById`, `create`, `update`, `delete`). Add Infra model with `HasUuids` + `SoftDeletes` (no `newUniqueId` override), `RoomFactory`, `EloquentRoomRepository` mapping to Domain, bind the port in `AppServiceProvider`. Cover factory persistence (UUID, columns, soft delete) like `UserSchemaTest`.
**Where**: `app/Modules/Room/Domain/Entities/Room.php`
**Depends on**: T1
**Reuses**: `app/Modules/User/Domain/Entities/User.php`, `EloquentUserRepository`, `User` model, `UserFactory`, `AppServiceProvider`
**Requirement**: ROOM-07

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Domain types have no `Illuminate` import
- [x] Model uses `HasUuids` and `SoftDeletes`; fillable/casts limited to `name`, `capacity`, `is_active` (boolean)
- [x] `listPage` orders by `id` ASC, excludes soft-deleted, includes inactive, returns `{items: Room[], total: int}`
- [x] `AppServiceProvider` binds `RoomRepository` to `EloquentRoomRepository`
- [x] `tests/Feature/Room/RoomSchemaTest.php` asserts UUID, ADR-004 attributes only, timestamps
- [x] Gate check passes: `php artisan test --testsuite=Feature`
- [x] Test count: existing Feature tests remain; no silent deletions

**Tests**: integration
**Gate**: full

---

### Phase 2: Application use cases

### T3: Add room use cases

**What**: Add Application `ListRooms`, `CreateRoom`, `FindRoom`, `UpdateRoom`, `DeleteRoom` and `RoomNotFound`. `CreateRoom` defaults `is_active` to true. Find/Update/Delete throw `RoomNotFound` when `findById` is null. Include `app/Modules/Room/Application` in `phpunit.xml` `<source>`. Cover with PHPUnit fakes (no Laravel app), including a no-Illuminate source check.
**Where**: `app/Modules/Room/Application/UseCases/CreateRoom.php`
**Depends on**: T2
**Reuses**: `CreateUser` + `tests/Unit/User/CreateUserTest.php`
**Requirement**: ROOM-01, ROOM-02, ROOM-03, ROOM-04, ROOM-05

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Five use cases + `RoomNotFound` exist and do not import `Illuminate`
- [x] `phpunit.xml` includes `app/Modules/Room/Application`
- [x] `tests/Unit/Room/*` cover the Planned Tests Unit use-case behaviors
- [x] Gate check passes: `php artisan test --testsuite=Unit --coverage --min=80`
- [x] Test count: existing Unit tests plus the new files; no silent deletions

**Tests**: unit
**Gate**: quick

---

### Phase 3: HTTP adapters

### T4: Add invokable room controllers, form requests, and routes

**What**: Add six invokable controllers and `StoreRoomRequest`/`UpdateRoomRequest` (Portuguese messages, trim `name`, `capacity` integer min 1, `is_active` boolean, authorize true). Register the six `auth` routes in `routes/web.php` (no `Route::resource`). Controllers call use cases, map `RoomNotFound` to 404, flash the spec success strings, pass page size 15. Cover Form Request rules with `Validator` in Unit (no HTTP) and every action with Feature HTTP + MySQL.
**Where**: `app/Modules/Room/Infra/Http/Controllers/IndexRoomController.php`
**Depends on**: T3
**Reuses**: `StoreUserRequest`, `CreateUserHttpTest`, `LoginHttpTest` guest redirect, `actingAs` + `withoutVite`
**Requirement**: ROOM-01, ROOM-02, ROOM-03, ROOM-04, ROOM-05, ROOM-07

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Routes: `GET/POST /rooms`, `GET /rooms/create`, `GET /rooms/{room}/edit`, `PUT /rooms/{room}`, `DELETE /rooms/{room}` each point at a different invokable controller
- [x] Writes use `validated()` only; extra attributes are not mass-assigned
- [x] `tests/Unit/Room/StoreRoomRequestTest.php` and `UpdateRoomRequestTest.php` cover the validation Planned Tests Unit items
- [x] `tests/Feature/Room/*HttpTest.php` cover the Planned Tests Integration HTTP items except shared `auth.user.name` (T5)
- [x] Gate check passes: `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature`
- [x] Test count: existing tests remain; no silent deletions

**Tests**: integration
**Gate**: full

---

### Phase 4: Admin shell and Inertia pages

### T5: Upgrade AppLayout and share auth/flash

**What**: Share `auth.user.name` (no password) and `flash.success` / `flash.error` from `HandleInertiaRequests`. Rebuild `AppLayout` per `screen-rooms-list.md`: navy sidebar with ReservaSalas, Reservas → `/reservations`, Salas → `/rooms` (`aria-current="page"` on room URLs), account menu with administrator name/initials from shared props, logout via `session.logout`, dismissible flash live region. Update `AppLayout.test.jsx` and keep `Reservation/Index` green.
**Where**: `resources/js/Layouts/AppLayout.jsx`
**Depends on**: T4
**Reuses**: `resources/js/Services/session.js`, current `AppLayout.test.jsx`, `Reservation/Index.jsx`
**Requirement**: ROOM-05, ROOM-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `HandleInertiaRequests::share` exposes `auth.user.name` and flash keys only
- [x] Layout matches the list-screen shell rules (sidebar, account menu, flash `aria-live`)
- [x] Feature `GET /rooms` asserts shared `auth.user.name` and no password in Inertia props
- [x] `AppLayout.test.jsx` covers Planned Tests Unit layout behavior; Reservation stub tests still pass
- [x] Gate check passes: `php artisan test --testsuite=Feature` && `npm run test:coverage`
- [x] Test count: existing frontend tests remain; no silent deletions

**Tests**: integration
**Gate**: build

---

### T6: Add the rooms list Inertia page

**What**: Add `resources/js/Services/rooms.js` (create/update/delete URLs only; list is Inertia GET) and `Pages/Room/Index.jsx` per `docs/screens/screen-rooms-list.md`: table columns, status text+badge, `DD/MM/YYYY`, Nova sala → `/rooms/create`, edit → `/rooms/{id}/edit`, delete dialog (focus, cancel, `Excluindo...`, confirm once), empty state, load-failure+retry, flash from layout. Desktop table; mobile cards or horizontal scroll without dropping fields.
**Where**: `resources/js/Pages/Room/Index.jsx`
**Depends on**: T5
**Reuses**: `AppLayout`, `users.js` service style, `Login.test.jsx` RTL style
**Requirement**: ROOM-01, ROOM-04, ROOM-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `GET /rooms` Inertia component is `Room/Index` (already asserted in T4; stays green)
- [x] Index unit tests cover Planned Tests Unit list/dialog/empty/error behaviors
- [x] `rooms` service delete helper is the only place that issues `router.delete` / `form.delete` for rooms
- [x] Gate check passes: `npm run test:coverage` && `npm run lint`
- [x] Test count: existing frontend tests remain; no silent deletions

**Tests**: unit
**Gate**: build

---

### T7: Add minimal create and edit room pages

**What**: Add `Room/Create.jsx` and `Room/Edit.jsx` inside `AppLayout` with only `name`, `capacity`, `is_active`. Create posts via `rooms.store`; edit puts via `rooms.update`. Show Laravel field errors. Cancel returns to `/rooms`. No extra product fields or brand-panel register chrome.
**Where**: `resources/js/Pages/Room/Create.jsx`
**Depends on**: T6
**Reuses**: `Room/Index` layout, `users.js` + `Create.jsx` form error focus pattern (without BrandPanel)
**Requirement**: ROOM-02, ROOM-03, ROOM-07

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Pages render the three writable fields only and submit through `resources/js/Services/rooms.js`
- [x] Vitest covers Planned Tests Unit create/edit submit contract
- [x] Gate check passes: `vendor/bin/pint --test` && `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run lint` && `npm run test:coverage` && `composer run build` && `npm run build`
- [x] Test count: existing tests remain; no silent deletions

**Tests**: unit
**Gate**: build

---

## Phase Execution Map

```
Phase 1 → Phase 2 → Phase 3 → Phase 4

Phase 1:  T1 ------→ T2
Phase 2:  T3
Phase 3:  T4
Phase 4:  T5 ------→ T6 ------→ T7
```

Execution is strictly sequential. Seven tasks fit one batch; Execute inline, no sub-agents.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Add rooms migration matching ADR-004 | One migration + schema test | Granular |
| T2: Add Room domain, Eloquent model, factory, and repository | Cohesive persistence slice; `Where` is the entity | Granular (cohesive) |
| T3: Add room use cases | Application slice + unit tests | Granular (cohesive) |
| T4: Add invokable room controllers, form requests, and routes | HTTP slice; cannot prove adapters until routed | Granular (cohesive) |
| T5: Upgrade AppLayout and share auth/flash | Layout + Inertia share | Granular (cohesive) |
| T6: Add the rooms list Inertia page | One page + service | Granular |
| T7: Add minimal create and edit room pages | Two sibling pages, one service | Granular (cohesive) |

**Granularity check**: each task is one deliverable. Related files stay in `What` / `Done when` so `Where` names a single primary path.

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | no inbound arrow | Match |
| T2 | T1 | T1 → T2 | Match |
| T3 | T2 | cross-phase (no intra-phase arrow) | Match |
| T4 | T3 | cross-phase | Match |
| T5 | T4 | cross-phase | Match |
| T6 | T5 | T5 → T6 | Match |
| T7 | T6 | T6 → T7 | Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1: migration | migration / schema | integration | integration | OK |
| T2: domain + Eloquent repo | repository + schema | integration (highest; Domain none) | integration | OK |
| T3: use cases | Application | unit | unit | OK |
| T4: controllers + Form Requests | HTTP integration + validation unit | integration (highest) | integration | OK |
| T5: layout + Inertia share | layout unit + share via GET /rooms | integration (highest) | integration | OK |
| T6: Room/Index | React page | unit | unit | OK |
| T7: Create/Edit | React pages | unit | unit | OK |
