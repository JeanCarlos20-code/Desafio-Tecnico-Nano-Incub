# Specification

## Context

Rooms and Reservation modules exist (ADR-004 / ADR-005, task 0013). Deactivate and delete do not lock the room or touch reservations. The reservation list still shows canceled rows. The rooms list has no status URL filter. Create reservation does not cap participants by the selected room. ADR-006 and the updated screens close that gap.

## Problem

An administrator can deactivate or delete a room while another request creates a booking on it, leave active meetings on a soft-deleted room, or hide inactive rooms. Canceled meetings still appear on the list. The create form accepts a participant count above the room capacity.

## Problem Statement

The panel must serialize room lifecycle with new and existing reservations: deactivate with an explicit Keep/Cancel choice, delete with automatic cancel + warning, hide canceled rows, filter rooms in the URL, and stop the create form from accepting more participants than the selected room.

## Goal

- Deactivate and delete a room inside one transaction with `SELECT … FOR UPDATE` on that room, applying ADR-006 reservation effects.
- Hide canceled reservations from `ListReservations` and the list screen.
- Filter `GET /rooms` by `status=all|active|inactive` on the server.
- Align create-reservation UX with `screen-reservation-create.md` and cap participants at the selected room capacity.
- Prove same-room races with two real PHP processes against MySQL 8.

## User Stories

### P1: Deactivate a room with Keep or Cancel ⭐ MVP

**User Story**: As an administrator, I want to deactivate a room and choose whether to keep or cancel future meetings so that new bookings stop without silently wiping the calendar.

**Why P1**: ADR-006 + room edit screen.

**Covered ACs**: AC-001, AC-002, AC-003, AC-004, AC-005, AC-006, AC-018

**Independent Test**: Seed a future active booking; PUT `is_active=false` + `keep` leaves it active; PUT `cancel` sets `cancelled_at`; omit action → 422 and no writes.

### P1: Delete a room and cancel its actives ⭐ MVP

**User Story**: As an administrator, I want deleting a room to cancel its active meetings after a warning so that no active booking points at a soft-deleted room.

**Why P1**: ADR-006 + rooms list delete dialog.

**Covered ACs**: AC-007, AC-008, AC-009, AC-018

**Independent Test**: DELETE a room with mixed reservations; all actives get `cancelled_at`; room is soft-deleted; canceled/already-canceled rows stay; list warning copy is shown when `has_reservations` is true.

### P1: Serialize create against deactivate and delete ⭐ MVP

**User Story**: As an administrator, I want a create that loses the room lock after deactivate/delete to fail cleanly so that no new row is stored on an inactive or missing room.

**Why P1**: ADR-006 concurrency tests; RF17 + RNF09.

**Covered ACs**: AC-010, AC-011

**Independent Test**: Two PHP processes, shared barrier, same `room_id`: POST `/reservations` vs PUT deactivate; POST vs DELETE. After the lifecycle commit, create yields `InactiveRoom` or `OccupancyRoomNotFound` and inserts nothing.

### P1: Hide canceled reservations ⭐ MVP

**User Story**: As an administrator, I want canceled meetings gone from the list so that the board only shows meetings that still occupy a slot.

**Why P1**: ADR-006 list rule (supersedes 0013 AC-013).

**Covered ACs**: AC-012, AC-013

**Independent Test**: Seed active + canceled on the same day; GET `/reservations` returns only the active row; no `Cancelada` badge.

### P1: Filter rooms by status in the URL ⭐ MVP

**User Story**: As an administrator, I want Todas / Ativas / Inativas in the query string so that I can share and reload a filtered rooms list.

**Why P1**: ADR-006 + rooms list screen.

**Covered ACs**: AC-014, AC-015

**Independent Test**: Mix active/inactive/soft-deleted; `?status=inactive` returns only inactive non-deleted rooms; filter change resets page to 1.

### P1: Cap create participants to room capacity ⭐ MVP

**User Story**: As an administrator, I want the create form to follow the documented screen and refuse a participant count above the selected room so that I cannot type an impossible headcount.

**Why P1**: User request + create screen.

**Covered ACs**: AC-016, AC-017, AC-019

**Independent Test**: Select a room of capacity 8; the participants control cannot go to 9; POST `participants=9` still fails on the server.

## Acceptance Criteria

1. WHEN an authenticated administrator deactivates a room that has no future active reservation THEN the system SHALL set `is_active` false in one transaction after `SELECT … FOR UPDATE` on that room, SHALL leave every reservation unchanged, and SHALL redirect to `rooms.index` with flash `Sala atualizada com sucesso.`
2. WHEN the administrator deactivates a room that has one or more future active reservations (`starts_at` > Clock::now(), `cancelled_at` null) and submits `scheduled_meetings_action=keep` THEN the system SHALL set `is_active` false, SHALL leave those reservations active, and SHALL flash `Sala desativada. As reuniões programadas foram mantidas.`
3. WHEN the administrator deactivates a room that has future active reservations and submits `scheduled_meetings_action=cancel` THEN the system SHALL set `is_active` false and SHALL set `cancelled_at` to Clock::now() only on those future actives, in the same transaction as the room update, and SHALL flash `Sala desativada. As reuniões futuras foram canceladas.`
4. IF the administrator deactivates a room that has future active reservations and omits a valid `scheduled_meetings_action` THEN the system SHALL reject the request (HTTP 422), SHALL persist neither the status change nor any `cancelled_at`, and SHALL return the current future-active count so the edit dialog can open.
5. IF deactivation is confirmed with `cancel` THEN the system SHALL leave past, in-progress (`starts_at` ≤ now < `ends_at`), and already-canceled reservations unchanged.
6. WHEN the edit form status moves from Ativa to Inativa and `future_active_count` > 0 THEN the React page SHALL open the dialog titled `Desativar sala?` with radios `Manter reuniões programadas` (default) and `Cancelar reuniões programadas` under `O que deseja fazer com as reuniões programadas?`, SHALL send data only after `Desativar`, and SHALL close without writes on `Cancelar` or Escape.
7. WHEN an authenticated administrator deletes a room THEN the system SHALL, in one transaction after `SELECT … FOR UPDATE` on that room, set `cancelled_at` on every active reservation of that room and then soft-delete the room, and SHALL flash `Sala excluída com sucesso.`
8. WHEN the rooms list opens delete for a room with `has_reservations` true THEN the dialog SHALL include `As reuniões ativas desta sala serão canceladas e deixarão de aparecer na listagem.` The system SHALL still cancel and soft-delete if DELETE arrives without the UI warning.
9. WHILE a room is inactive the system SHALL reject new reservations for it with `Não é possível reservar uma sala inativa.` and persist no reservation row.
10. WHEN `POST /reservations` and a same-room deactivation run concurrently THEN the system SHALL serialize on the room lock so that if create obtains the lock after the room is inactive it SHALL fail with the inactive-room message and SHALL insert no row.
11. WHEN `POST /reservations` and a same-room deletion run concurrently THEN the system SHALL serialize on the room lock so that if create obtains the lock after the room is soft-deleted it SHALL fail with `Sala não encontrada.` and SHALL insert no row.
12. WHEN `ListReservations` or `GET /reservations` runs THEN the system SHALL return only rows whose `cancelled_at` is null (standalone cancel, deactivate-cancel, and delete-cancel). The system SHALL NOT render a `Cancelada` badge.
13. WHEN every reservation is canceled THEN the system SHALL treat the list as globally empty (`Nenhuma reserva cadastrada.` + `Nova reserva`).
14. WHEN `GET /rooms` is called with no `status` or `status=all` THEN the system SHALL list active and inactive rooms, exclude soft-deleted rooms, default the filter label to `Todas`, and keep `id` ascending order and page size 15.
15. WHEN `GET /rooms?status=active` or `status=inactive` THEN the system SHALL return only matching non-deleted rooms, store the filter in the URL, reset to page 1 when the filter changes, and preserve `status` on pagination links. IF `status` is not `all`, `active`, or `inactive` THEN the system SHALL return a FormRequest error and persist nothing.
16. WHEN the administrator opens `GET /reservations/create` THEN the system SHALL render the documented create screen: only active rooms (option may show capacity), required Sala / Responsável / Título / finalidade / Data / Horário de início / Horário de término / Participantes, primary `Criar reserva`, `Cancelar` → `/reservations`.
17. WHILE a room is selected on create the participants control SHALL not accept an integer greater than that room’s current `capacity` (and not less than 1). IF POST `participants` exceeds the locked room capacity THEN the system SHALL reject with `O número de participantes excede a capacidade da sala.` and persist nothing.
18. IF any step inside deactivate or delete fails THEN the system SHALL roll back both the room write and every reservation write for that request.
19. WHEN create succeeds THEN the system SHALL keep storing ADR-005 columns (`responsible`, `starts_at`, `ends_at`, …) and SHALL still enforce 0013 occupancy rules (duration, past start, overlap, consecutive).
20. WHEN a guest hits rooms or reservations mutation/list routes THEN the system SHALL redirect to `/login` and persist nothing.

## Edge Cases

- IF the room is already inactive and the administrator saves name/capacity/inactive THEN the system SHALL not open the deactivation dialog and SHALL not change reservations.
- IF `scheduled_meetings_action` is sent while activating or while no future actives exist THEN the system SHALL ignore the action.
- IF two deactivates with `cancel` run on the same room THEN the system SHALL leave `cancelled_at` of already-canceled futures unchanged (idempotent cancel).
- IF delete runs on a room with only canceled reservations THEN the system SHALL soft-delete the room and SHALL not rewrite those `cancelled_at` values.
- IF create and deactivate target different `room_id` values THEN the system SHALL not block one with the other.
- IF participants is a decimal or non-integer THEN the system SHALL reject it (no silent rounding).
- IF the selected create room later becomes inactive before submit THEN the system SHALL reject with the inactive-room message.
- IF rooms filter yields zero rows but other rooms exist THEN the system SHALL show a filtered empty state, not the “no rooms registered” CTA.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Edit / hard-delete reservation | ADR-005 |
| Recurrence, email, waitlist, completed status | ADR-001 |
| Changing occupancy duration/overlap math | Already shipped in 0013 |
| Unique room names, capacity max besides room capacity | Not requested |
| Combined RF19 seeder | Separate delivery |
| Bootstrapping Playwright | No runner in the repo |
| New Transaction/Clock types | Reuse Reservation ports |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| HTTP keep/cancel field | `scheduled_meetings_action` = `keep` \| `cancel` | Stable English key; radios stay Portuguese | n |
| Future vs in-progress | Future = `starts_at` > now; in-progress not canceled on deactivate | ADR-006 table | n |
| Delete cancels all actives | Including in-progress and past-still-active | ADR-006 “todas as reservas ativas” | n |
| Create POST fields | Still `starts_at` / `ends_at` / `responsible`; UI splits date + times | Keeps 0013 workers and use case; screen is UX | n |
| Capacity server message | Keep `O número de participantes excede a capacidade da sala.` | Already 0013; UX cap is the new rule | n |
| `is_active` on update | Required boolean | Edit screen Status is required | n |
| Transaction/Clock | Reuse Reservation ports in Room use cases | Smallest architecture; no extra layer | n |
| `hasAny` on reservations | Any non-canceled row | Canceled ≡ deleted for the list | n |
| E2E | Not applicable | No Playwright | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Room use cases lock the room and call ReservationRepository cancel/count methods** inside the existing Transaction port — selected.
2. Block DELETE while any reservation exists — rejected by ADR-006.
3. Always cancel on deactivate, no radios — rejected by the edit screen.
4. Put cancel SQL only in Room Infra (Eloquent Reservation model) — rejected; Reservation stays owner of `cancelled_at`.
5. Change create HTTP to `date` + `start_time` + `end_time` — deferred; UI can combine without rewriting 0013 Feature workers.

## Selected Approach

Approach 1.

- Add `RoomRepository::lockById`. `UpdateRoom` / `DeleteRoom` run inside `Transaction`: lock room → apply ADR-006 → update/delete. Inject `ReservationRepository` + `Clock`.
- Add reservation port methods: `countActiveFutureByRoom`, `countByRoomIds`, `cancelActiveFutureByRoom`, `cancelAllActiveByRoom`. Eloquent `listPage` / `hasAny` add `whereNull('cancelled_at')`.
- HTTP: `scheduled_meetings_action`, required `is_active`, `IndexRoomRequest` status, `future_active_count` / `has_reservations` Inertia props, mapped flashes.
- React: Status select + warning + dialog on save; rooms `status` filter in the URL; delete warning; create screen + participants max; list drops canceled rows.
- Feature: two-process POST vs PUT deactivate and POST vs DELETE, copied from `concurrency_create_worker.php`.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| ROOM-01 | P1: Deactivate Keep/Cancel | Tasks | Pending |
| ROOM-02 | P1: Delete cancels actives | Tasks | Pending |
| ROOM-03 | P1: Rooms status filter | Tasks | Pending |
| RSV-01 | P1: Hide canceled | Tasks | Pending |
| RSV-02 | P1: Create vs lifecycle races | Tasks | Pending |
| RSV-03 | P1: Create screen + capacity cap | Tasks | Pending |
| HTTP-01 | P1: FormRequests + flashes + props | Tasks | Pending |
| UI-01 | P1: Room + reservation screens | Tasks | Pending |

**Coverage:** 8 total, 8 mapped to tasks, 0 unmapped
