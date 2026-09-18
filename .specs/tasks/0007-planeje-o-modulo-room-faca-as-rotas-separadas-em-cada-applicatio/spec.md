# Specification

## Context

ReservaSalas already authenticates administrators (tasks 0005–0006) and can render a stub `/reservations` page. ADR-004 defines a Rooms module with a minimal persistence contract. `docs/screens/screen-rooms-list.md` defines the authenticated rooms list. There is no `app/Modules/Room` tree, no `/rooms` routes, and `AppLayout` is a logout-only stub.

## Problem

Administrators cannot list, create, edit, or delete meeting rooms. User CRUD is a two-action controller; this module must not copy that. Fields beyond ADR-004 must not appear.

## Problem Statement

An authenticated administrator must manage rooms (RF03–RF06) through Inertia pages backed by a `Room` module that follows `docs/architecture.md` and `docs/tree.md`: one use case per action, one invokable controller per route, Form Request validation on writes, and only ADR-004 columns persisted.

## Goal

- Persist `rooms` with exactly `id`, `name`, `capacity`, `is_active`, `created_at`, `updated_at`, `deleted_at`.
- Expose separate Application use cases and Infra HTTP adapters for list, create-form, store, edit-form, update, and destroy.
- Ship the rooms list screen per `docs/screens/screen-rooms-list.md`, plus minimal create/edit forms that use the same admin layout and ADR-004 writable fields.
- Protect the module with unit (use cases, validation, React) and integration (HTTP + MySQL) tests. Do not bootstrap Playwright.

## User Stories

### P1: List rooms ⭐ MVP

**User Story**: As an authenticated administrator, I want to open `/rooms` and see every non-deleted room so that I can manage meeting rooms.

**Why P1**: RF03 and the documented rooms list screen.

**Acceptance Criteria**:

1. WHEN an authenticated administrator requests `GET /rooms` THEN the system SHALL render Inertia `Room/Index` with one row per non-deleted room showing `id`, `name`, `capacity`, status, `created_at`, and row actions
2. WHEN the list is rendered THEN the system SHALL include inactive rooms (`is_active` false) and SHALL exclude soft-deleted rooms
3. WHEN `created_at` is shown THEN the system SHALL format it as `DD/MM/YYYY` in `config('app.timezone')`
4. WHEN `is_active` is true THEN the system SHALL show the text `Ativa`; WHEN `is_active` is false THEN the system SHALL show the text `Inativa`
5. WHEN no non-deleted rooms exist THEN the system SHALL show `Nenhuma sala cadastrada.` with a `Nova sala` action to `/rooms/create`
6. WHEN more than 15 non-deleted rooms exist THEN the system SHALL paginate server-side in `id` ascending order with page size 15 and SHALL preserve query parameters on pagination links
7. The sidebar item `Salas` SHALL have `aria-current="page"` on `/rooms` and nested room routes
8. The top bar SHALL display the authenticated administrator `name` from Inertia shared data and SHALL NOT hardcode that name

**Independent Test**: Feature `GET /rooms` Inertia assertions + Vitest list states.

---

### P1: Create a room ⭐ MVP

**User Story**: As an authenticated administrator, I want to register a room with name, capacity, and situation so that it appears on the list.

**Why P1**: RF04 and `GET /rooms/create` from the list screen.

**Acceptance Criteria**:

1. WHEN an authenticated administrator selects `Nova sala` THEN the system SHALL navigate to `GET /rooms/create` and render Inertia `Room/Create`
2. WHEN `POST /rooms` receives valid `name`, `capacity`, and `is_active` THEN `CreateRoom` SHALL persist a room whose `id` is a UUID v7, whose timestamps are set, whose `deleted_at` is null, and the HTTP adapter SHALL redirect to `/rooms` flashing `Sala criada com sucesso.`
3. IF `POST /rooms` omits `name` THEN the system SHALL return `Informe o nome da sala.` on `name` and SHALL NOT persist a row
4. IF `POST /rooms` omits `capacity` or sends a non-integer THEN the system SHALL return a capacity error and SHALL NOT persist a row
5. IF `POST /rooms` sends `capacity` less than 1 THEN the system SHALL reject it and SHALL NOT persist a row
6. WHEN `is_active` is omitted on create THEN the system SHALL persist `is_active` as true
7. The create form SHALL contain only `name`, `capacity`, and `is_active` as writable fields

**Independent Test**: `CreateRoom` unit fake + Feature `POST /rooms` + Vitest create form.

---

### P1: Edit a room ⭐ MVP

**User Story**: As an authenticated administrator, I want to change a room's name, capacity, and situation so that the list stays current.

**Why P1**: RF05 and `GET /rooms/{room}/edit`.

**Acceptance Criteria**:

1. WHEN an authenticated administrator selects edit for a room THEN the system SHALL navigate to `GET /rooms/{room}/edit` and render Inertia `Room/Edit` prefilled with that room's `name`, `capacity`, and `is_active`
2. WHEN `PUT /rooms/{room}` receives valid fields THEN `UpdateRoom` SHALL persist the new `name`, `capacity`, and `is_active` and the HTTP adapter SHALL redirect to `/rooms` flashing `Sala atualizada com sucesso.`
3. IF `{room}` is unknown, malformed, or soft-deleted THEN `GET .../edit` and `PUT /rooms/{room}` SHALL respond 404 and SHALL NOT update rows
4. IF update validation fails THEN the system SHALL return to the edit form with field errors and SHALL leave persisted data unchanged
5. The edit form SHALL contain only `name`, `capacity`, and `is_active` as writable fields

**Independent Test**: `UpdateRoom`/`FindRoom` unit + Feature edit/update HTTP + Vitest edit form.

---

### P1: Delete a room after confirmation ⭐ MVP

**User Story**: As an authenticated administrator, I want to delete a room only after confirming so that I do not remove a room by accident.

**Why P1**: RF06 and the list-screen modal.

**Acceptance Criteria**:

1. WHEN the administrator selects delete on a row THEN the React list SHALL open a confirmation dialog that includes the room name and SHALL NOT send HTTP yet
2. WHEN the administrator cancels, presses Escape, or closes the dialog THEN the system SHALL leave the room persisted and SHALL return focus to the delete control
3. WHEN the administrator confirms THEN the client SHALL send `DELETE /rooms/{room}` once; `DeleteRoom` SHALL soft-delete the room; the HTTP adapter SHALL redirect to `/rooms` flashing `Sala excluída com sucesso.`
4. WHEN a room is soft-deleted THEN subsequent list loads SHALL omit it and `GET /rooms/{room}/edit` SHALL respond 404
5. IF `{room}` is unknown or already soft-deleted THEN `DELETE /rooms/{room}` SHALL respond 404

**Independent Test**: `DeleteRoom` unit + Feature delete HTTP + Vitest dialog.

---

### P1: Protect room routes ⭐ MVP

**User Story**: As the system, I want room routes reachable only with a valid administrator session so that guests cannot manage rooms.

**Why P1**: RF02.

**Acceptance Criteria**:

1. IF a guest requests any room route (`GET /rooms`, `GET /rooms/create`, `POST /rooms`, `GET /rooms/{room}/edit`, `PUT /rooms/{room}`, `DELETE /rooms/{room}`) THEN the system SHALL redirect to `/login` and SHALL NOT persist changes
2. WHILE the administrator is authenticated the system SHALL allow those routes through `auth` middleware
3. The Application use cases, Domain entity, Domain `RoomRepository`, and `RoomNotFound` SHALL NOT import `Illuminate`

**Independent Test**: Feature guest redirects + unit source check for Illuminate.

---

## Acceptance Criteria

1. WHEN an authenticated administrator requests `GET /rooms` THEN the system SHALL render Inertia `Room/Index` with non-deleted rooms showing id, name, capacity, status text, created date, and actions
2. WHEN the list is rendered THEN the system SHALL include inactive rooms and SHALL exclude soft-deleted rooms
3. WHEN `created_at` is shown THEN the system SHALL format it as `DD/MM/YYYY` in the application timezone
4. WHEN `POST /rooms` receives valid `name`, `capacity`, and `is_active` THEN the system SHALL persist a UUID v7 room and redirect to `/rooms` with `Sala criada com sucesso.`
5. IF create or update input is invalid THEN the system SHALL return Portuguese field errors from the Form Request and SHALL NOT persist the invalid write
6. WHEN `PUT /rooms/{room}` receives valid fields for an existing room THEN the system SHALL persist the changes and redirect to `/rooms` with `Sala atualizada com sucesso.`
7. WHEN `DELETE /rooms/{room}` succeeds THEN the system SHALL set `deleted_at` and redirect to `/rooms` with `Sala excluída com sucesso.`
8. IF `{room}` is unknown, malformed, or soft-deleted THEN edit, update, and delete SHALL respond 404
9. IF a guest hits a room route THEN the system SHALL redirect to `/login`
10. The system SHALL persist only ADR-004 columns on `rooms`
11. Each room HTTP action SHALL use its own invokable controller and each write SHALL use its own Form Request; each mutating/list/find action SHALL use its own Application use case
12. Domain and Application Room types SHALL NOT import `Illuminate`
13. The rooms list UI SHALL follow `docs/screens/screen-rooms-list.md` (sidebar, table or mobile cards, empty state, delete dialog, flash live region, `Nova sala`)
14. Create and edit pages SHALL collect only `name`, `capacity`, and `is_active`
15. The system SHALL NOT add reservation integrity rules, extra room fields, public REST, or a Playwright runner

Traceability aliases: AC-001 … AC-015 map to items 1 … 15 above.

## Edge Cases

- IF extra fields such as `location` are posted THEN the system SHALL ignore them and persist only `name`, `capacity`, and `is_active`
- IF `name` is sent with surrounding whitespace THEN the system SHALL trim it before persist
- IF `page` is missing or less than 1 THEN the system SHALL list page 1
- IF confirm-delete is in progress THEN the dialog actions SHALL be disabled and the destructive label SHALL read `Excluindo...`
- IF the list client visit fails THEN the system SHALL show `Não foi possível carregar as salas.` with a retry action
- IF the session expires on a room route THEN the backend SHALL redirect to `/login`
- IF two rooms share the same `name` THEN the system SHALL allow both (no unique on `name`)

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Reservation module, overlap, duration, participants | ADR-001 RF07–RF18; ADR-004 |
| Block delete when room has reservations | No `reservations` table yet; ADR-001 gap |
| RF19 combined seeder | Needs rooms and reservations together |
| Extra room fields (location, photo, equipment, floor) | ADR-001 / ADR-004 |
| Unique room name | ADR-004 defers it |
| Public REST API | ADR-003 |
| Roles / guest room access | ADR-001 |
| Dedicated create/edit screen markdown / visual invention | Packet: only enough for list routes + RF04/RF05 |
| Playwright bootstrap | No runner in repo; do not add one |
| Fat `RoomController` or `Route::resource` | User asked for separate controllers/use cases |
| DTOs / API Resources / module ServiceProvider | architecture.md: no extra layers without need |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Update HTTP verb | `PUT /rooms/{room}` via Inertia `_method` | Idiomatic Laravel (RNF14); packet “POST” is the Inertia transport | n |
| List order | `id` ASC, page size 15 | Screen allows id ASC; UUID v7 is time-ordered; Laravel default page size | n |
| Delete vs reservations | Soft-delete always; no reservation check | No reservations table; simplest coherent path (ADR-004) | n |
| Create default situation | `is_active=true` when omitted | RF04 does not specify; active is the useful default for RF17 later | n |
| Name uniqueness | Not unique | ADR-004 says unique needs a later explicit rule | n |
| Timezone for dates | `config('app.timezone')` currently `UTC` | Do not invent `America/Sao_Paulo` | n |
| UUID generation | `HasUuids` default UUIDv7, no `newUniqueId` override | Laravel 12 docs + existing User model | n |
| GET create use case | None; controller only renders Inertia | No domain decision on an empty form | n |
| Pagination type | Port returns `{items, total}`; HTTP maps Inertia props | Application cannot depend on Illuminate paginator | n |
| Shared Inertia auth | `{ auth: { user: { name } }, flash: { success, error } }` | Screen forbids hardcoded name; share password-free | n |
| Create/edit chrome | Same admin `AppLayout`; fields name, capacity, is_active only | No screen markdown; honor routes + RF04/RF05 | n |
| E2E | Not in this task | No Playwright project; human may refuse | n |

**Open questions:** none - all resolved or logged above.

## Considered Approaches

| Approach | Summary | Trade-off |
| -------- | ------- | --------- |
| A. Fat `RoomController` + one `ManageRoom` use case | Fast, matches current `UserController` | Rejected: user asked for separate use cases/controllers; architecture wants focused adapters |
| B. `Route::resource` + resource controller | Idiomatic Laravel resource | Rejected: dumps CRUD into one class; hides per-action validation |
| C. Invokable controller + use case per action, Form Request per write, ADR-004 schema, list screen + minimal create/edit | Matches packet, ADR-004, tree, architecture | Selected: slightly more files, clear boundaries |
| D. Embed rooms in reservations | One less module | Rejected by ADR-004 |

## Selected Approach

Approach C.

```text
GET    /rooms                  IndexRoomController     → ListRooms
GET    /rooms/create           CreateRoomController    → Inertia Room/Create
POST   /rooms                  StoreRoomController     → StoreRoomRequest → CreateRoom
GET    /rooms/{room}/edit      EditRoomController      → FindRoom → Inertia Room/Edit
PUT    /rooms/{room}           UpdateRoomController    → UpdateRoomRequest → UpdateRoom
DELETE /rooms/{room}           DestroyRoomController   → DeleteRoom
```

Dependency direction: Infra (controllers, Form Requests, Eloquent) → Application (use cases, `RoomNotFound`) → Domain (`Room`, `RoomRepository`).

Writable HTTP fields: `name`, `capacity`, `is_active`. Persistence columns: ADR-004 only.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| ROOM-01 | P1: List rooms | Execute | Implemented |
| ROOM-02 | P1: Create a room | Execute | Implemented |
| ROOM-03 | P1: Edit a room | Execute | Implemented |
| ROOM-04 | P1: Delete a room after confirmation | Execute | Implemented |
| ROOM-05 | P1: Protect room routes | Execute | Implemented |
| ROOM-06 | P1: List rooms (admin shell + flash) | Execute | Implemented |
| ROOM-07 | P1: Create/Edit forms | Execute | Implemented |

**Coverage:** 7 total, 7 mapped to T1–T7, 0 unmapped.

ROOM-01 = AC-001/AC-002/AC-003 (list). ROOM-02 = AC-004/AC-005 (create + validation). ROOM-03 = AC-006/AC-008 (update + 404). ROOM-04 = AC-007/AC-008 (soft delete). ROOM-05 = AC-009/AC-012 (auth + no Illuminate). ROOM-06 = AC-013 (list screen). ROOM-07 = AC-010/AC-011/AC-014 (schema, separate adapters, form fields). AC-015 is anti-scope for all tasks.
