# Specification

## Context

The user asked to change only the meeting name and other non-occupancy fields. Date and time must stay locked.

Original request: "então pelo menos só fazer um alterar no nome da reunião e coisas surpefluas, data e hora não são alteraveis. Recorte claro: editar só o que não mexe em ocupação (título e responsável); data e hora travadas. basciamente é alterar nome da reunião e outros que não envolva data e hora"

ADR-005 still forbids a full reservation edit. Room capacity reduction (task 0020) tells the administrator to “Altere essa reunião primeiro”; that sentence does not mean this screen can shrink `participants`.

## Problem

Administrators can create and cancel reservations but cannot fix a typo in `title` or `responsible` without cancelling and recreating. Recreating would re-enter occupancy rules (RF13–RF18). Date, time, room, and participants must stay frozen.

## Problem Statement

An authenticated administrator must be able to change only `title` and `responsible` on an active reservation. The system SHALL keep `starts_at`, `ends_at`, `room_id`, `participants`, and `cancelled_at` unchanged, reject those keys if they appear on PUT, and return 404 for a missing or cancelled reservation.

## Goal

- Authenticated `GET /reservations/{reservation}/edit` and `PUT /reservations/{reservation}`.
- `UpdateReservation` writes `title` and `responsible` only. Thin controller, `UpdateReservationRequest`, Reservation module.
- Inertia edit page in the create / room-edit style: occupancy fields visible and disabled.
- List action `Editar` beside `Cancelar` for active rows.
- ADR-009 (MADR, Portuguese) supersedes only ADR-005’s “no reservation edit” slice. Occupancy stays immutable. ADR-001 gets an extra note, not a rewritten RF catalog.
- README and screens state: partial edit exists; occupancy is locked; capacity reduction still cannot lower participants here (cancel + recreate, or this screen only changes title/responsible).
- Tests at unit (use case, FormRequest, Vitest) and Feature/MySQL. No Playwright.

## User Stories

### P1: Edit meeting title and responsible ⭐ MVP

**User Story**: As an administrator, I want to change the title and responsible person of an active reservation without touching date, time, room, or participants, so that I can fix metadata without reopening occupancy.

**Why P1**: This is the whole requested slice.

**Covered ACs**: AC-001 through AC-012

**Acceptance Criteria**:

1. WHEN an authenticated administrator submits `PUT /reservations/{reservation}` with a non-empty `title` and `responsible` for a reservation whose `cancelled_at` is null THEN the system SHALL persist those two trimmed values and SHALL leave `starts_at`, `ends_at`, `room_id`, `participants`, and `cancelled_at` equal to their values before the request.
2. IF the reservation id does not exist THEN `UpdateReservation` SHALL throw `ReservationNotFound` and the HTTP layer SHALL respond 404 without writing any reservation row.
3. IF the reservation has `cancelled_at` not null THEN `UpdateReservation` SHALL throw `ReservationNotFound` and the HTTP layer SHALL respond 404 without changing that row.
4. WHEN the PUT payload includes `starts_at`, `ends_at`, `room_id`, `participants`, or `cancelled_at` THEN `UpdateReservationRequest` SHALL fail validation on each present prohibited key and SHALL not call the use case.
5. IF `title` or `responsible` is missing, not a string, or empty after trim THEN `UpdateReservationRequest` SHALL fail that field with the same Portuguese required messages as store (`Informe o título da reserva.` / `Informe o responsável.`) and SHALL not write.
6. WHEN an authenticated administrator opens `GET /reservations/{reservation}/edit` for an active reservation THEN the system SHALL render Inertia `Reservation/Edit` with the current `title`, `responsible`, `room_id`, `room_name`, `date` (`Y-m-d`), `start_time` (`H:i`), `end_time` (`H:i`), and `participants`.
7. IF `GET /reservations/{reservation}/edit` targets a missing or cancelled reservation THEN the system SHALL respond 404.
8. WHEN a guest sends `GET /reservations/{reservation}/edit` or `PUT /reservations/{reservation}` THEN the system SHALL redirect 302 to login and SHALL not change reservation rows.
9. WHEN `Reservation/Index` renders an active row THEN the page SHALL show an `Editar` link to `/reservations/{id}/edit` and SHALL keep the existing `Cancelar` action.
10. WHEN `UpdateReservation` runs THEN the system SHALL NOT check overlap, duration, past start, capacity, or room activity, and SHALL NOT open a reservation or room lock.
11. WHEN the edit form submits THEN the page SHALL send only `title` and `responsible` and SHALL keep room, date, start time, end time, and participants visible but disabled (or otherwise not included in the PUT body).
12. The system SHALL document partial edit (title/responsible) versus locked occupancy in ADR-009, a README leftover update, `docs/screens/screen-reservation-edit.md`, and the list screen, and SHALL state that Room capacity reduction still cannot lower `participants` through this screen.

**Independent Test**: Seed an active reservation; PUT new title and responsible plus a forged `starts_at`; assert 422 and unchanged occupancy; PUT only title/responsible; assert those two columns changed and occupancy did not; GET edit shows disabled occupancy; cancelled id returns 404; Index shows `Editar`.

## Acceptance Criteria

Traceable copies (same outcomes):

- **AC-001** WHEN an authenticated administrator submits `PUT /reservations/{reservation}` with a non-empty `title` and `responsible` for an active reservation THEN the system SHALL persist those two trimmed values and SHALL leave occupancy columns and `cancelled_at` unchanged.
- **AC-002** IF the reservation id does not exist THEN the system SHALL throw `ReservationNotFound` and respond 404 without writing.
- **AC-003** IF the reservation is cancelled THEN the system SHALL throw `ReservationNotFound` and respond 404 without writing.
- **AC-004** WHEN the PUT payload includes `starts_at`, `ends_at`, `room_id`, `participants`, or `cancelled_at` THEN the FormRequest SHALL reject each present prohibited key and SHALL not call the use case.
- **AC-005** IF `title` or `responsible` is missing or empty after trim THEN the FormRequest SHALL fail that field with the store Portuguese required message and SHALL not write.
- **AC-006** WHEN GET edit runs for an active reservation THEN the system SHALL render Inertia `Reservation/Edit` with current title, responsible, and occupancy display values.
- **AC-007** IF GET edit targets a missing or cancelled reservation THEN the system SHALL respond 404.
- **AC-008** WHEN a guest hits GET edit or PUT THEN the system SHALL redirect 302 to login and SHALL not write.
- **AC-009** WHEN the list renders an active row THEN the page SHALL show `Editar` to `/reservations/{id}/edit` beside `Cancelar`.
- **AC-010** WHEN `UpdateReservation` runs THEN the system SHALL NOT run occupancy rules or locks.
- **AC-011** WHEN the edit form submits THEN the page SHALL send only `title` and `responsible` and SHALL show occupancy fields disabled.
- **AC-012** The system SHALL record ADR-009, README, and screens for partial edit and SHALL state that capacity reduction still cannot lower `participants` here.

## Edge Cases

- Whitespace-only `title` or `responsible` → trim then required failure; no write.
- `title` / `responsible` longer than 255 → same `max:255` contract as store.
- Past or in-progress active reservation → metadata edit allowed (no RF18 on update).
- Cancelled reservation is absent from the list; direct GET/PUT still 404.
- Unknown extra keys besides the prohibited occupancy set → dropped by `validated()`, not persisted.
- PUT with both missing title and a prohibited `starts_at` → validation errors on both; no write.
- Soft-deleted rooms are irrelevant: `room_id` does not change.
- Concurrent title edits: last write wins; no lock (occupancy is untouched).

## Out of Scope

| Feature | Reason |
| --- | --- |
| Changing `starts_at`, `ends_at`, `room_id`, `participants` | Occupancy; would reopen RF13–RF18 and RNF09 |
| Overlap, duration, past-start, capacity, inactive-room, `FOR UPDATE` on update | Those fields do not change |
| Editing `cancelled_at` or un-cancelling | Cancel remains `PATCH .../cancel` |
| Schema / migration | Columns already exist |
| Recurrence, calendar, email, waitlist | ADR-001 leftovers |
| Rewriting ADR-005 or the ADR-001 RF catalog | New ADR-009 + extra note only |
| Lowering `participants` to satisfy a room capacity drop | Still cancel + recreate |
| Playwright / new E2E project | Not in `package.json` |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| Occupation keys on PUT | Reject with Laravel `prohibited` | Packet: locked in UI and refused if present | n (planner default) |
| Cancelled reservation | 404 via `ReservationNotFound` | Packet allows 404 or a clear error; Feature list says 404 | n |
| Missing reservation | Same 404 | Matches cancel | n |
| Past / in-progress active | Allow title/responsible edit | Only occupancy is frozen | n |
| Auth | Existing `auth` middleware; guest 302 | Same as other reservation routes | n |
| Concurrency / lock | None on update | No occupancy mutation | n |
| GET cancelled guard | Controller `abort(404)` if `cancelledAt !== null` | Avoids a second use case; PUT still tested on the use case | n |
| List cancelled rows | Unchanged (still hidden) | No Editar for cancelled | n |
| Success flash | `Reserva atualizada com sucesso.` | Mirrors create/cancel Portuguese flashes | n |
| ADR-005 file | Status/link line only | Skill: do not edit the old decision | n |
| Capacity-reduction copy | Keep 0020 sentences; document that this screen does not shrink participants | User leftover from RF16 write-side | n |
| Remaining implicit dimensions (idempotency, rate limits, observability, external deps) | N/A for this scope | Local authenticated metadata write | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Partial metadata update (title + responsible), occupancy immutable, prohibited occupation keys** — matches the user cut. Trade-off: “Altere essa reunião” on capacity drop still cannot fix participant count here.
2. **Full reservation edit that reuses RF13–RF18 and room lock** — would satisfy a richer product. Trade-off: packet forbids reopening occupancy; much larger surface.
3. **Ignore extra keys via `validated()` only** — simpler FormRequest. Trade-off: a crafted payload looks accepted while occupancy silently stays; packet asked to refuse those keys.

## Selected Approach

Approach 1. Add `UpdateReservation` and `updateTitleAndResponsible` on the reservation port. FormRequest requires `title`/`responsible` and prohibits occupancy keys. GET/PUT routes mirror room edit. Inertia `Reservation/Edit` disables occupancy widgets and PUTs only the two fields. List gains `Editar`. ADR-009 records the slice. No schema. No Playwright.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| --- | --- | --- | --- |
| EDIT-01 | P1: Persist title/responsible only | Execute | Implemented |
| EDIT-02 | P1: Missing → 404 | Execute | Implemented |
| EDIT-03 | P1: Cancelled → 404 | Execute | Implemented |
| EDIT-04 | P1: Prohibit occupancy keys | Execute | Implemented |
| EDIT-05 | P1: Required title/responsible | Execute | Implemented |
| EDIT-06 | P1: GET edit Inertia props | Execute | Implemented |
| EDIT-07 | P1: GET edit 404 | Execute | Implemented |
| EDIT-08 | P1: Guest 302 | Execute | Implemented |
| EDIT-09 | P1: List Editar link | Execute | Implemented |
| EDIT-10 | P1: No occupancy rules on update | Execute | Implemented |
| EDIT-11 | P1: Form sends only metadata | Execute | Implemented |
| EDIT-12 | P1: ADR/README/screens | Execute | Implemented |

**ID format:** `EDIT-NN` maps 1:1 to AC-00N.

**Coverage:** 12 total, 12 mapped to T1–T7, 0 unmapped.
