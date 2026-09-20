# Specification

## Context

Administrators edit a room on `PUT /rooms/{room}` (`UpdateRoom`). Capacity is a required integer ≥ 1. Reservations store `participants`. RF16 already rejects a **new** reservation when `participants` exceeds the room’s current capacity. RF05 still lets the administrator lower that capacity even when a future active meeting already booked more people (example from the user: room capacity 10, meeting with 10 people, attempt to set capacity to 8).

## Problem

After a capacity drop, RF16 is already violated for meetings that were valid when created. The administrator is not told which scheduled meetings block the change, and the room row is saved anyway.

## Problem Statement

When an administrator saves a room capacity that is smaller than `participants` on one or more future active reservations of that room, the system must refuse the save, leave the room and those reservations unchanged, and tell the administrator to change those meetings first and then return.

## Goal

- Refuse `UpdateRoom` when any future active reservation on that room has `participants` greater than the proposed capacity.
- Show a Portuguese `capacity` validation message with the conflicting meeting count and the instruction to change those meetings first.
- Leave name, status, deactivate-dialog, and create-reservation RF16 unchanged except for the new guard’s precedence over the deactivate dialog.
- Cover the rule in isolated `UpdateRoom` tests and in `PUT /rooms/{room}` on MySQL 8. Show the message on Room/Edit in Vitest. No Playwright.

## User Stories

### P1: Block a capacity drop that orphans scheduled meetings ⭐ MVP

**User Story**: As an administrator, I want the system to refuse a smaller room capacity when a scheduled meeting already has more people than that number, so that I change those meetings first and only then come back to the room.

**Why P1**: User request; closes the RF16 hole on room edit.

**Covered ACs**: AC-001, AC-002, AC-003, AC-004, AC-005, AC-006, AC-007, AC-008, AC-009, AC-010

**Acceptance Criteria**:

1. WHEN an authenticated administrator submits `PUT /rooms/{room}` with a proposed `capacity` C and that room has at least one reservation with `cancelled_at` null, `starts_at` after `Clock::now()`, `room_id` equal to that room, and `participants` greater than C THEN the system SHALL not persist the room row (name, capacity, and `is_active` stay as before) and SHALL not change any reservation.
2. WHEN the blocked save in AC-001 has exactly one such reservation THEN the system SHALL return a validation error on `capacity` whose message is exactly `Não é possível reduzir a capacidade. Existe 1 reunião marcada com mais participantes do que a nova capacidade. Altere essa reunião primeiro e depois volte.`
3. WHEN the blocked save in AC-001 has n such reservations and n is greater than 1 THEN the system SHALL return a validation error on `capacity` whose message is exactly `Não é possível reduzir a capacidade. Existem {n} reuniões marcadas com mais participantes do que a nova capacidade. Altere essas reuniões primeiro e depois volte.` with `{n}` replaced by that integer.
4. WHEN every future active reservation on that room has `participants` less than or equal to C THEN the system SHALL persist the proposed capacity (subject to existing deactivate rules).
5. WHEN the proposed capacity is greater than or equal to the current capacity THEN the system SHALL NOT apply the AC-001 refusal for this rule (increase and same-capacity saves follow today’s `UpdateRoom` behavior).
6. IF the only reservations that have `participants` greater than C are past (`ends_at` ≤ now), in-progress (`starts_at` ≤ now and `ends_at` > now), already canceled, or belong to another room THEN the system SHALL persist the proposed capacity.
7. WHILE a capacity conflict from AC-001 applies AND the payload would also deactivate the room without `scheduled_meetings_action` THEN the system SHALL emit the `capacity` message and SHALL NOT emit the deactivate-decision payload (`scheduled_meetings_action` / `future_active_count`).
8. WHEN `UpdateRoom` evaluates this rule THEN the system SHALL do so after `lockById` inside the existing room transaction and SHALL write nothing when the rule fails.
9. WHEN Room/Edit receives `errors.capacity` set to the AC-002 sentence THEN the page SHALL show that sentence on the Capacidade field (`id="capacity-error"`) and SHALL keep the administrator on the edit form.
10. The system SHALL NOT add reservation edit, SHALL NOT silently lower `participants`, and SHALL NOT cancel meetings as a side effect of a capacity change.

**Independent Test**: Freeze clock to 2026-09-21 12:00; room capacity 10; one future meeting with 10 participants; PUT capacity 8; assert unchanged rows and the singular message. Repeat with two future over-capacity meetings for the plural. PUT capacity 10 or 12 with the same meeting and assert persist.

## Acceptance Criteria

Traceable copies (same outcomes):

- **AC-001** WHEN an authenticated administrator submits `PUT /rooms/{room}` with a proposed `capacity` C and that room has at least one reservation with `cancelled_at` null, `starts_at` after `Clock::now()`, `room_id` equal to that room, and `participants` greater than C THEN the system SHALL not persist the room row and SHALL not change any reservation.
- **AC-002** WHEN the blocked save in AC-001 has exactly one such reservation THEN the system SHALL return a validation error on `capacity` whose message is exactly `Não é possível reduzir a capacidade. Existe 1 reunião marcada com mais participantes do que a nova capacidade. Altere essa reunião primeiro e depois volte.`
- **AC-003** WHEN the blocked save in AC-001 has n such reservations and n is greater than 1 THEN the system SHALL return a validation error on `capacity` whose message is exactly `Não é possível reduzir a capacidade. Existem {n} reuniões marcadas com mais participantes do que a nova capacidade. Altere essas reuniões primeiro e depois volte.`
- **AC-004** WHEN every future active reservation on that room has `participants` less than or equal to C THEN the system SHALL persist the proposed capacity (subject to existing deactivate rules).
- **AC-005** WHEN the proposed capacity is greater than or equal to the current capacity THEN the system SHALL NOT apply the AC-001 refusal for this rule.
- **AC-006** IF the only reservations that have `participants` greater than C are past, in-progress, already canceled, or belong to another room THEN the system SHALL persist the proposed capacity.
- **AC-007** WHILE a capacity conflict from AC-001 applies AND the payload would also deactivate the room without `scheduled_meetings_action` THEN the system SHALL emit the `capacity` message and SHALL NOT emit the deactivate-decision payload.
- **AC-008** WHEN `UpdateRoom` evaluates this rule THEN the system SHALL do so after `lockById` inside the existing room transaction and SHALL write nothing when the rule fails.
- **AC-009** WHEN Room/Edit receives `errors.capacity` set to the AC-002 sentence THEN the page SHALL show that sentence on the Capacidade field and SHALL keep the administrator on the edit form.
- **AC-010** The system SHALL NOT add reservation edit, SHALL NOT silently lower `participants`, and SHALL NOT cancel meetings as a side effect of a capacity change.

## Edge Cases

- Proposed capacity `1` with a future meeting of `2` participants → block (minimum valid capacity can still conflict).
- Future meeting with `participants ==` C → allow.
- Two future meetings, only one over C → message uses count `1` (singular), not the total future-active count.
- Capacity drop plus deactivate with `keep` or `cancel` while a conflict exists → still block; do not deactivate or cancel.
- Soft-deleted room → existing `RoomNotFound` / 404; this rule does not run.
- Unauthenticated PUT → existing guest redirect; this rule does not add auth.

## Out of Scope

| Feature | Reason |
| --- | --- |
| Reservation edit / changing `participants` in place | ADR-001 leftover; cancel + recreate remains the path |
| Listing meeting times or titles in the error | One `capacity` string; count is enough |
| Auto-cancel or auto-shrink on capacity drop | User asked to block and send the administrator back to the meetings |
| New ADR | Existing ADR-004/005/006 + this spec |
| Create-reservation RF16, overlap, deactivate radios, delete-room | Unchanged |
| Playwright / new E2E project | Not in `package.json` |
| Changing `UpdateRoomRequest` min/type rules | Already `required\|integer\|min:1` |
| Two-process concurrency worker for this rule | Check sits after the existing room lock |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| Which meetings block | Future actives on this room (`cancelled_at` null, `starts_at > now`) with `participants >` C | Same “reunião marcada” cut as ADR-006; user said before the meeting | n (planner default) |
| In-progress / past | Do not block | User: change capacity before the meeting; those meetings already started or finished | n |
| Equality `participants ==` C | Allow | RF16 is do-not-exceed | n |
| Message lists times? | No; count + instruction only | Keeps one validation string; RoomForm already shows `capacity` | n |
| How to “alterar a reunião” | Cancel (then create again if needed) | No reservation edit in routes or ADR-001 | n |
| New ADR? | No | Lock and future-active definition already in ADR-006; this is the RF16 write-side check | n |
| Check vs deactivate | Capacity conflict wins | Administrator cannot keep a 10-person meeting on an 8-person room | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Block on `UpdateRoom` after the existing room lock** — count future actives with `participants >` C; map a Room application error to `capacity`. Trade-off: administrator must leave the form and fix meetings; no extra UI.
2. **Dialog like deactivate (keep / force / cancel meetings)** — extra radios and a new ADR-006-like choice. Trade-off: user asked to prevent the save, not to choose a side effect.
3. **Silently lower participants or auto-cancel** — would “fix” RF16 without a round-trip. Trade-off: cancels or rewrites meetings the administrator did not confirm; conflicts with deactivate `keep`.

## Selected Approach

Approach 1. Add `countActiveFutureExceedingCapacity` on `ReservationRepository`. After `lockById`, if the count is > 0, throw `CapacityReductionBlocked` and persist nothing. `UpdateRoomController` maps it to `capacity`. React already displays that field. Document the sentence on `screen-room-form.md`. No new ADR.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| --- | --- | --- | --- |
| CAP-01 | P1: Block a capacity drop | Execute | Implemented |
| CAP-02 | P1: Singular message | Execute | Implemented |
| CAP-03 | P1: Plural message | Execute | Implemented |
| CAP-04 | P1: Allow when all future actives fit | Execute | Implemented |
| CAP-05 | P1: Allow increase / same capacity | Execute | Implemented |
| CAP-06 | P1: Ignore past / in-progress / canceled / other room | Execute | Implemented |
| CAP-07 | P1: Capacity error beats deactivate dialog | Execute | Implemented |
| CAP-08 | P1: After lock, write nothing on failure | Execute | Implemented |
| CAP-09 | P1: Edit form shows `capacity` error | Execute | Implemented |
| CAP-10 | P1: No edit / no silent mutate | Execute | Implemented |

**ID format:** `CAP-NN` maps 1:1 to AC-00N.

**Coverage:** 10 total, 10 mapped to T1–T6, 0 unmapped.
