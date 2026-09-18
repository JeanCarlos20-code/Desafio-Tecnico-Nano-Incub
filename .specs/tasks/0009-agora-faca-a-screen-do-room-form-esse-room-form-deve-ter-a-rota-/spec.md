# Specification

## Context

Task 0007 already added `app/Modules/Room`, authenticated `/rooms` CRUD routes, a rooms list, and stub create/edit Inertia pages. Those stubs collect `name`, `capacity`, and `is_active` on both modes. `docs/screens/screen-room-form.md` defines the real form. The user asked for that screen with the create route and to ignore a pending transaction with another database that does not exist yet (reservations).

Plan revision: create `is_active` is boolean `true` by default (including the SQL column default). On update, deactivation starts from a **Desativar sala** button; only after confirmation does `is_active` become false. Keep/cancel-meeting questions appear only when meetings are registered; otherwise the radio `Desativar sala sem reunião` stays selected.

## Problem

An administrator cannot register a meeting room through the documented create form. The current page shows a Status/Situação field, posts `is_active`, and the backend may persist a client-supplied inactive status. Edit reuses the same incomplete form: there is no deactivate button, no confirmation radios, and a missing `is_active` on `PUT` defaults to true (would reactivate). Reservation keep/cancel cannot be persisted because there is no reservations store.

## Problem Statement

An authenticated administrator must create a room from `GET /rooms/create` by submitting only `name` and `capacity` to `POST /rooms`. Laravel must validate those fields, persist an always-active ADR-004 room (`is_active` boolean true, also the MySQL column default), and redirect to `/rooms` with a success flash. The same React form component must serve edit mode: name and capacity save without changing status; deactivation uses a button plus confirmation radios; meeting-handling questions appear only when meetings exist; confirming deactivation sets `is_active` false without reservation side effects.

## Goal

- Ship the create room screen at the existing `GET /rooms/create` / `POST /rooms` pair per `docs/screens/screen-room-form.md`.
- Force every created room to `is_active=true` in `CreateRoom` and keep the MySQL boolean default `true`; do not trust a client status on store.
- Reuse one form component for edit: read-only status, `Desativar sala` button, confirmation radios, `PUT /rooms/{room}`.
- `Salvar` updates name/capacity and SHALL keep the current `is_active`. Confirmed deactivation persists `is_active` false.
- Show Laravel field errors in the UI; keep guests off the routes (already `auth`).
- Leave reservation deactivation transactions out of this task.

## User Stories

### P1: Create a meeting room ⭐ MVP

**User Story**: As an authenticated administrator, I want to open `/rooms/create` and save a room name and capacity so that an active room appears on `/rooms`.

**Why P1**: User asked for the room form with the create route (RF04). Human confirmed `is_active` boolean true as the create default.

**Acceptance Criteria**:

1. WHEN an authenticated administrator requests `GET /rooms/create` THEN the system SHALL render Inertia `Room/Create` inside `AppLayout` with title `Nova sala` and supporting text `Preencha as informações da sala de reunião.`
2. WHEN the create form is shown THEN the system SHALL display required fields `Nome` and `Capacidade` only, each with a visible red asterisk and `aria-required="true"`
3. WHEN the create form is shown THEN the system SHALL NOT render a Status/`is_active` control and SHALL NOT include `is_active` in the submitted payload
4. WHEN the administrator submits valid `name` and `capacity` THEN the client SHALL `POST /rooms` through `Services/rooms.js` `store`, `CreateRoom` SHALL persist `is_active` true, and the HTTP adapter SHALL redirect to `/rooms` flashing `Sala criada com sucesso.`
5. IF `POST /rooms` includes `is_active` false or any other unexpected status THEN the system SHALL ignore that value and SHALL persist `is_active` true
6. IF `name` is missing THEN the system SHALL return `Informe o nome da sala.` on `name` and SHALL NOT persist a row
7. IF `capacity` is missing THEN the system SHALL return `Informe a capacidade da sala.` on `capacity` and SHALL NOT persist a row
8. IF `capacity` is not an integer THEN the system SHALL return `A capacidade deve ser um número inteiro.` on `capacity` and SHALL NOT persist a row
9. IF `capacity` is less than 1 THEN the system SHALL return `A capacidade deve ser de pelo menos 1 pessoa.` on `capacity` and SHALL NOT persist a row
10. WHEN `name` contains leading or trailing whitespace THEN the system SHALL trim it before validation and persistence and SHALL preserve internal spaces
11. WHEN create submission is processing THEN the system SHALL disable `Salvar` and `Cancelar`, SHALL set the primary label to `Salvando...`, and SHALL NOT send a second `POST`
12. WHEN the administrator selects `Cancelar` without a successful submit THEN the system SHALL navigate to `/rooms` and SHALL NOT persist a room
13. IF Laravel returns field errors THEN the create page SHALL keep submitted values, show each error below its field with `aria-invalid` and `aria-describedby`, and move focus to the first invalid field
14. IF the create request fails unexpectedly THEN the system SHALL show `Não foi possível salvar a sala. Tente novamente.` above the form and SHALL keep field values
15. The `rooms.is_active` column SHALL default to boolean true in MySQL (`default(true)` already on the migration)

**Independent Test**: Vitest create page + `CreateRoom` fake + Feature `POST /rooms` + schema default.

---

### P1: Edit a room and deactivate with confirmation ⭐ MVP

**User Story**: As an authenticated administrator, I want to update a room's name and capacity, and deactivate it only after confirming a dedicated button, so that status does not flip until I confirm and meeting questions appear only when meetings exist.

**Why P1**: Human revision of RF05: button then rule; confirmed `is_active` false; radios depend on registered meetings.

**Acceptance Criteria**:

1. WHEN an authenticated administrator requests `GET /rooms/{room}/edit` for an existing non-deleted room THEN the system SHALL render Inertia `Room/Edit` with title `Editar sala`, supporting text `Atualize as informações da sala de reunião.`, the current `name`, `capacity`, and `is_active`, and `has_registered_meetings` false
2. WHEN the edit form is shown THEN the system SHALL reuse the create form component, SHALL show required `Nome` and `Capacidade`, and SHALL show the current status as read-only `Ativa` or `Inativa` without a Status select that submits on `Salvar`
3. WHILE the edited room is active the system SHALL show a `Desativar sala` button
4. WHEN the administrator selects `Desativar sala` THEN the system SHALL open a confirmation dialog before changing any stored data
5. WHILE the confirmation dialog is open and `has_registered_meetings` is false the system SHALL show radio `Desativar sala sem reunião` selected and SHALL NOT show keep/cancel-meeting questions
6. WHILE the confirmation dialog is open and `has_registered_meetings` is true the system SHALL show radios `Manter reuniões programadas` and `Cancelar reuniões programadas` and SHALL NOT show `Desativar sala sem reunião`
7. WHEN the administrator confirms deactivation THEN the client SHALL `PUT /rooms/{room}` with `name`, `capacity`, and `is_active` false, and the HTTP adapter SHALL persist `is_active` false and redirect to `/rooms` flashing `Sala atualizada com sucesso.`
8. WHEN the administrator dismisses the confirmation with `Cancelar` or Escape THEN the system SHALL close the dialog, SHALL return focus to `Desativar sala`, and SHALL NOT change the stored room
9. WHEN the administrator submits valid edit `name` and `capacity` with `Salvar` THEN the client SHALL `PUT /rooms/{room}` without sending a new status, and the system SHALL persist those fields and SHALL keep the previous `is_active`
10. IF `{room}` is unknown, malformed, or soft-deleted THEN `GET .../edit` and `PUT /rooms/{room}` SHALL respond 404
11. WHEN saving a confirmed inactive status THEN the system SHALL NOT write reservation rows and SHALL NOT start a second-database transaction
12. WHEN edit `Salvar` or confirmed deactivation is processing THEN the system SHALL disable the in-flight actions, SHALL show `Salvando...` or `Desativando...` respectively, and SHALL NOT send a second `PUT`
13. WHILE the edited room is inactive the system SHALL hide `Desativar sala` and SHALL show `Ativar sala`
14. WHEN the administrator confirms `Ativar sala` THEN the system SHALL `PUT /rooms/{room}` with `is_active` true and SHALL NOT show meeting radios

**Independent Test**: Vitest edit page (button, radios, no reservation writes) + `UpdateRoom` keep-current + Feature update HTTP.

---

### P1: Protect form routes ⭐ MVP

**User Story**: As the system, I want create and store (and existing edit/update) routes reachable only with an administrator session so that guests cannot mutate rooms.

**Why P1**: RF02; screen error state “Unauthorized session”.

**Acceptance Criteria**:

1. IF a guest requests `GET /rooms/create` or `POST /rooms` THEN the system SHALL redirect to `/login` and SHALL NOT persist a room
2. WHILE the administrator is authenticated the system SHALL allow `GET /rooms/create` and `POST /rooms` through `auth` middleware
3. The create/update use cases and Domain Room types SHALL NOT import `Illuminate`

**Independent Test**: Existing Feature guest matrix remains green; no new guest permutations required beyond create/store already listed.

---

## Acceptance Criteria

1. WHEN an authenticated administrator requests `GET /rooms/create` THEN the system SHALL render Inertia `Room/Create` with copy `Nova sala` / `Preencha as informações da sala de reunião.`
2. WHEN the create form is shown THEN the system SHALL show only required `Nome` and `Capacidade` and SHALL NOT display or submit Status/`is_active`
3. WHEN `POST /rooms` receives valid `name` and `capacity` THEN the system SHALL persist an active ADR-004 room (`is_active` boolean true) and redirect to `/rooms` with `Sala criada com sucesso.`
4. IF `POST /rooms` includes a client `is_active` THEN the system SHALL ignore it and persist `is_active` true
5. IF create `name` or `capacity` is invalid THEN the system SHALL return the Portuguese field messages defined in the create story and SHALL NOT persist a row
6. WHEN create or edit `Salvar` is processing THEN the system SHALL prevent duplicate submits and SHALL show `Salvando...`
7. WHEN form `Cancelar` is used on either mode without a completed save THEN the system SHALL go to `/rooms` and SHALL NOT mutate room data
8. WHEN `GET /rooms/{room}/edit` succeeds THEN the system SHALL reuse the create form component, prefill the room, show read-only status, and pass `has_registered_meetings` false
9. WHILE the room is active on edit the system SHALL show `Desativar sala`; WHEN that button is used THEN the system SHALL open confirmation before persisting
10. WHILE deactivation confirmation is open and no meetings are registered the system SHALL keep radio `Desativar sala sem reunião` selected and SHALL hide meeting keep/cancel questions
11. WHEN deactivation is confirmed THEN the system SHALL persist `is_active` false and redirect with `Sala atualizada com sucesso.`
12. WHEN `PUT /rooms/{room}` receives valid `name` and `capacity` without `is_active` THEN the system SHALL persist those values and SHALL keep the stored `is_active`
13. IF a guest hits create or store THEN the system SHALL redirect to `/login`
14. WHEN saving an inactive status in this task THEN the system SHALL NOT open a reservation-write path and SHALL NOT start a reservation transaction
15. The create and edit pages SHALL follow `docs/screens/screen-room-form.md` layout rules except where this revision replaces Status-select deactivation with the button and radios (shared admin shell, centered card, labels above fields, actions bottom-right on desktop, required asterisks, field errors)
16. The `rooms.is_active` column SHALL remain boolean with MySQL default true

Traceability aliases: AC-001 … AC-016 map to items 1 … 16 above.

## Edge Cases

- IF extra fields such as `location` are posted on create THEN the system SHALL ignore them
- IF two rooms share the same `name` THEN the system SHALL allow both
- IF capacity is a decimal such as `1.5` THEN the system SHALL reject it as non-integer and SHALL NOT round it
- IF the create form first loads THEN `Nome` and `Capacidade` SHALL be empty
- IF the edit form loads an already inactive room THEN the system SHALL hide `Desativar sala` and SHALL show `Ativar sala`
- IF `has_registered_meetings` is true THEN the confirmation SHALL show keep/cancel-meeting radios and SHALL still persist only `is_active` false on confirm
- IF the session expires on create or store THEN the backend SHALL redirect to `/login`
- IF `{room}` is missing on edit THEN the system SHALL 404 rather than render an empty create form
- IF `PUT` omits `is_active` for an inactive room THEN the system SHALL leave `is_active` false (must not default the controller to true)

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Persist keep/cancel of meetings / atomic room+reservation transaction | User: ignore pending transaction with another database not created yet |
| Query a real reservations store for `has_registered_meetings` | No `reservations` table; prop is false in this task |
| Block new reservations on inactive rooms (RF17 enforcement) | Needs reservation create flow |
| Unsaved-changes confirm on form `Cancelar` | Screen marks it optional (`may`) |
| Unique room name | ADR-004; existing Feature test allows duplicates |
| Arbitrary max capacity | Screen forbids inventing one |
| Extra room fields, public REST, roles | ADR-001 / ADR-003 / ADR-004 |
| Playwright / E2E runner | Not in the repo; do not add in this task |
| New HTTP routes | `GET /rooms/create` and `POST /rooms` already exist |
| Rooms list, delete dialog, login | Already delivered |
| Changing the MySQL default away from true | Human requires boolean true default; migration already has it |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Ignore “outro banco” | Skip reservation writes and any second-database transaction | User instruction; no reservations module | y |
| Create `is_active` | Boolean true: MySQL `default(true)` plus `CreateRoom` always persists true; Form Request omits `is_active` | Human revision; screen: do not trust client status | y |
| Deactivation UX | `Desativar sala` button then confirmation radios; confirm sets `is_active` false | Human revision overrides Status-select-as-the-control | y |
| No meetings | Radio `Desativar sala sem reunião` selected; hide keep/cancel questions | Human revision; `has_registered_meetings` false | y |
| Meetings exist (UI only) | Show keep/cancel radios; confirm still only writes `is_active` false | Human: questions only if meetings registered; persist path still deferred | y |
| `Salvar` vs deactivate | `Salvar` sends name/capacity only; omitted `is_active` keeps stored value | Human: rule starts from the deactivate button | y |
| Reactivation | `Ativar sala` when inactive; `PUT` `is_active` true; no meeting radios | RF05 still allows activate; human specified only the deactivate rule | n |
| Update verb | Keep `PUT /rooms/{room}` | Already registered; screen allows PUT or PATCH | n |
| Status wire format | Boolean `is_active` (Laravel `1`/`0`) | Matches ADR-004 column | n |
| Missing capacity message | `Informe a capacidade da sala.` | Screen lists integer/min copy; missing still needs a required message | n |
| Primary save label | `Salvar` in both modes; processing `Salvando...`; confirm deactivate `Desativar` / `Desativando...` | Screen lists `Salvar`; dialog uses `Desativar` | n |
| Unsaved-changes dialog | Not implemented | Optional in the screen spec | n |
| Name uniqueness | Not unique | ADR-004 | n |
| Visual PNG | Follow markdown guidelines; PNG missing from worktree | Do not block on `.local/image/screen-room-form.png` | n |
| E2E | Not in this task | No Playwright project | n |
| Remaining implicit dimensions (rate limit, observability, concurrency locks) | N/A for this scope | Create/update are single-row writes; reservation locks belong to the deferred transaction | n |

**Open questions:** none - all resolved or logged above.

## Considered Approaches

1. **Keep the previous plan (Status select + skip dialog)** — Matches the first screen reading, but the human revision asks for a deactivate button, confirmation, and a no-meeting radio.
2. **Full screen spec including reservation dialog and transaction** — Needs a reservations store the user said to ignore.
3. **Create always-active + edit deactivate button with confirmation radios, no reservation writes** — Honors the human revision and the create route; `has_registered_meetings` stays false until a meetings module exists.

## Selected Approach

Approach 3. Keep existing routes and `AppLayout`. Keep MySQL `is_active` default true. Change `CreateRoom` so new rooms are always active. Drop `is_active` from `StoreRoomRequest`. On update, omit-status keeps current; confirmed deactivation writes false. Extract a shared `RoomForm` with `mode`. Implement `Desativar sala` + radios. Do not add reservation writes.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| ROOM-01 | P1: Create a meeting room | Execute | Implemented |
| ROOM-02 | P1: Create a meeting room | Execute | Implemented |
| ROOM-03 | P1: Create a meeting room | Execute | Implemented |
| ROOM-04 | P1: Create a meeting room | Execute | Implemented |
| ROOM-05 | P1: Create a meeting room | Execute | Implemented |
| ROOM-06 | P1: Edit and deactivate with confirmation | Execute | Implemented |
| ROOM-07 | P1: Edit and deactivate with confirmation | Execute | Implemented |
| ROOM-08 | P1: Protect form routes | Execute | Implemented |

ROOM-01 maps to AC-001, AC-002, AC-015. ROOM-02 maps to AC-003, AC-004, AC-016. ROOM-03 maps to AC-005. ROOM-04 maps to AC-006. ROOM-05 maps to AC-007. ROOM-06 maps to AC-008, AC-009, AC-010, AC-011. ROOM-07 maps to AC-012, AC-014. ROOM-08 maps to AC-013.

**Coverage:** 8 total, 8 mapped to tasks, 0 unmapped.

## Success Criteria

- [x] Administrator can create an active room from `/rooms/create` with only name and capacity
- [x] Posted create status cannot persist an inactive room; MySQL default remains boolean true
- [x] Edit `Salvar` does not change `is_active`; `Desativar sala` plus confirm persists false
- [x] Without registered meetings the radio `Desativar sala sem reunião` stays selected; meeting questions stay hidden
- [x] Unit, frontend, and integration gates pass without weakening existing valid tests except where this spec changes behavior
