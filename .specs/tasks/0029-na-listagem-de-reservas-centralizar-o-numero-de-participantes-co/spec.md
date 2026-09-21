# Specification

## Context

The reservations table left-aligns `Participantes` while rooms already center `Capacidade`. Rooms `GET /rooms` still treats omitted `status` as `all` (Todas), unlike reservations which already default omitted status to `active`. `IndexReservationController::toListItem` labels every non-cancelled row `Ativa`, including meetings whose `ends_at` is already past. Task 0028 explicitly deferred a completed state; this request now requires it for the table badge only.

## Problem

Administrators misread participant counts because the integer is not centered under its header. Opening `/rooms` shows inactive rooms by default. Ended, non-cancelled meetings still look Active (`Ativa`) in Situação.

## Problem Statement

The system SHALL center the reservations `Participantes` column like rooms `Capacidade`. Omitted `status` on `GET /rooms` SHALL default to `active` (Ativas). A listed reservation SHALL show `Passada` when it is not cancelled and `ends_at` is before now, `Cancelada` when cancelled, and `Ativa` only when it is not cancelled and has not yet ended.

## Goal

- Center `Participantes` header and cells with `text-center`.
- Default rooms listing to Ativas when `status` is omitted; omit `status` from the rooms URL when Ativas.
- Derive list `status` / `status_label` as `passed`/`Passada`, `cancelled`/`Cancelada`, or `active`/`Ativa`.
- Keep occupancy, cancel PATCH, reservation filters (`all`/`active`/`cancelled`), and period/range unchanged.

## User Stories

### P1: Read participant counts under the header ⭐ MVP

**User Story**: As an administrator, I want the participant count centered like room capacity so that the number lines up with `Participantes`.

**Why P1**: First requested list fix.

**Covered ACs**: AC-001

**Acceptance Criteria**:

1. WHEN the reservations desktop table renders THEN the system SHALL apply `text-center` to both the `Participantes` column header and each participant-count cell

**Independent Test**: Vitest on `Reservation/Index` matching rooms Capacidade `text-center` contract.

### P1: Open rooms on Ativas ⭐ MVP

**User Story**: As an administrator, I want `/rooms` without `status` to show only active rooms so that the default list matches Ativas.

**Why P1**: Requested rooms default; today omitted is Todas.

**Covered ACs**: AC-002, AC-003, AC-004, AC-012

**Acceptance Criteria**:

1. WHEN `GET /rooms` omits `status` THEN the system SHALL treat status as `active` and echo `filters.status` `active`
2. WHEN `GET /rooms` omits `status` THEN the system SHALL return only rooms with `is_active` true and SHALL still omit soft-deleted rooms
3. WHEN the administrator selects Ativas THEN the system SHALL visit `/rooms` omitting `status`; WHEN Todas THEN include `status=all`; WHEN Inativas THEN include `status=inactive`; and SHALL reset `page` to 1
4. WHEN the rooms list renders without a status filter prop THEN the system SHALL show the Status select at `active` (`Ativas`)

**Independent Test**: `IndexRoomRequest` still omits `status` from `validated()` when absent. `ListRooms` default third argument is `active`. Feature omitted GET excludes an inactive factory room. Vitest omit/include query + default select.

### P1: See ended meetings as Passada ⭐ MVP

**User Story**: As an administrator, I want a non-cancelled meeting that already ended to show as Passada so that Ativa only means the meeting has not ended.

**Why P1**: Requested table status; cancelled stays Cancelada.

**Covered ACs**: AC-005, AC-006, AC-007, AC-008, AC-009, AC-010, AC-011

**Acceptance Criteria**:

1. WHEN a listed reservation has `cancelledAt` set THEN the system SHALL set item `status` to `cancelled` and `status_label` to `Cancelada`
2. WHEN a listed reservation has `cancelledAt` null AND `endsAt` is before now THEN the system SHALL set item `status` to `passed` and `status_label` to `Passada`
3. WHEN a listed reservation has `cancelledAt` null AND `endsAt` is not before now THEN the system SHALL set item `status` to `active` and `status_label` to `Ativa`
4. IF a reservation is cancelled AND `endsAt` is before now THEN the system SHALL still set `cancelled` / `Cancelada`
5. WHEN listing status is `active` THEN the system SHALL still return rows with `cancelled_at` null including those with `ends_at` in the past
6. WHILE a row has `status` other than `active` THEN the system SHALL hide `Editar` and `Cancelar` and show `—`
7. WHEN a listed reservation has `cancelledAt` null AND `startsAt` is before now AND `endsAt` is after now THEN the system SHALL set `active` / `Ativa`

**Independent Test**: Domain unit on `listStatus`. Feature GET with `travelTo` 12:00: past ends → Passada; future ends → Ativa; cancelled past → Cancelada; in-progress → Ativa. Vitest Passada row has slate badge and dash.

## Acceptance Criteria

Traceable copies (same outcomes):

- **AC-001** WHEN the reservations desktop table renders THEN the system SHALL apply `text-center` to both the `Participantes` column header and each participant-count cell
- **AC-002** WHEN `GET /rooms` omits `status` THEN the system SHALL treat status as `active` and echo `filters.status` `active`
- **AC-003** WHEN `GET /rooms` omits `status` THEN the system SHALL return only rooms with `is_active` true and SHALL still omit soft-deleted rooms
- **AC-004** WHEN the administrator selects Ativas THEN the system SHALL visit `/rooms` omitting `status`; WHEN Todas THEN include `status=all`; WHEN Inativas THEN include `status=inactive`; and SHALL reset `page` to 1
- **AC-005** WHEN a listed reservation has `cancelledAt` set THEN the system SHALL set item `status` to `cancelled` and `status_label` to `Cancelada`
- **AC-006** WHEN a listed reservation has `cancelledAt` null AND `endsAt` is before now THEN the system SHALL set item `status` to `passed` and `status_label` to `Passada`
- **AC-007** WHEN a listed reservation has `cancelledAt` null AND `endsAt` is not before now THEN the system SHALL set item `status` to `active` and `status_label` to `Ativa`
- **AC-008** IF a reservation is cancelled AND `endsAt` is before now THEN the system SHALL still set `cancelled` / `Cancelada`
- **AC-009** WHEN listing status is `active` THEN the system SHALL still return rows with `cancelled_at` null including those with `ends_at` in the past
- **AC-010** WHILE a row has `status` other than `active` THEN the system SHALL hide `Editar` and `Cancelar` and show `—`
- **AC-011** WHEN a listed reservation has `cancelledAt` null AND `startsAt` is before now AND `endsAt` is after now THEN the system SHALL set `active` / `Ativa`
- **AC-012** WHEN the rooms list renders without a status filter prop THEN the system SHALL show the Status select at `active` (`Ativas`)

## Edge Cases

- WHEN `endsAt` equals now THEN the system SHALL treat the row as `active` / `Ativa` (strictly before now is Passada)
- WHEN formatted list times are `H:i` on a single-day window THEN the system SHALL still derive Passada from datetime instants, not from the displayed string
- WHEN `period=today` lists a morning meeting after noon THEN the system SHALL keep the row on default Ativas and label it `Passada`
- WHEN pagination runs WHILE rooms status is `all` THEN the href SHALL include `status=all`
- WHEN pagination runs WHILE rooms status is `active` THEN the href SHALL omit `status`
- IF only inactive rooms exist and rooms status is omitted THEN the system SHALL show the filtered-empty copy (`hasAny` true), not “Nenhuma sala cadastrada”
- Remaining implicit-requirement dimensions (auth beyond existing `auth`, list-read concurrency, observability, rate limits) are N/A for this list-display scope

## Out of Scope

| Feature | Reason |
| --- | --- |
| Persist `completed_at` / a status column | ADR-005 occupancy stays `cancelled_at`; Passada is display-only |
| New filter value `passed` / Passadas | User asked for the table badge, not a fourth Status option |
| Hide past meetings from Ativas | Ativas remains `cancelled_at` null; period/range already window time |
| Copy rooms `w-0` / `xl:w-[16%]` onto reservations | Request is centering, not the rooms leftover-width recipe |
| Rename room badges to `Ativo` | Visible labels stay `Ativa`/`Ativas`; query value is `active` |
| Occupancy, cancel PATCH, create/edit validation | RF12–RF18 stay |
| Playwright / new E2E project | No runner in `package.json` |
| New ADR | Screen docs record the display contract |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Passada vs hide vs new filter | Display-only `passed`/`Passada`; Ativas still includes past non-cancelled | User: “na tabela”; “Ativa só para o que ainda não passou”; no Passadas option asked | y |
| Comparison | `endsAt < now` (instants via `Clock`) | “no passado” is strictly before; `== now` stays Ativa | y |
| Cancelled vs past | Cancelled wins | User: “Cancelada continua Cancelada” | y |
| Actions on Passada | Hide Editar/Cancelar (`status !== 'active'`) | Existing 0028 rule; cancelling an ended meeting was not requested | y |
| Passada badge color | Existing non-active slate `StatusBadge` | Text distinguishes Passada vs Cancelada; color is not the only cue | y |
| Rooms URL | Omit `status` when Ativas; include `status=all` for Todas | Same omit-the-default pattern as reservations | y |
| `ListRooms` default arg | `'active'` | Matches controller omitted default and reservations `ListReservations` | y |
| Participants CSS | Shared `text-center` only | Mirror rooms Capacidade alignment, not compact-column widths | y |
| Remaining implicit dimensions | N/A for this scope | List alignment + defaults + derived badge | y |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Center participants in CSS only; leave status as-is.** Incomplete: ignores rooms default and Passada.
2. **Derive Passada only in React from formatted `ends_at`.** Rejected: single-day props are `H:i`, so the client cannot know the calendar day vs now.
3. **Persist a completed column or add `status=passed` to the listing filter.** Rejected: extra schema/filter not requested; occupancy stays `cancelled_at`.
4. **Backend `Reservation::listStatus(now)` + controller labels; rooms omitted → `active` with omit-when-Ativas URL; `text-center` on Participantes.** Smallest change that matches all three asks. Occupancy unchanged.

## Selected Approach

Approach 4. Add `Reservation::listStatus(DateTimeImmutable $now)` returning `cancelled` / `passed` / `active`. `IndexReservationController` injects `Clock`, maps labels `Cancelada` / `Passada` / `Ativa`, and keeps filter `active` as `cancelled_at` null. Change rooms controller/use case/repository default from `all` to `active` and flip Room/Index query omit to when `active`. Add `text-center` on reservations `Participantes` `th`/`td`. Update both screen docs. Retarget Feature tests that currently expect `Ativa` on rows whose `ends_at` is before the frozen now.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| ALIGN-01 | P1: Read participant counts under the header | Execute | Implemented |
| ROOM-01 | P1: Open rooms on Ativas | Execute | Implemented |
| ROOM-02 | P1: Open rooms on Ativas | Execute | Implemented |
| ROOM-03 | P1: Open rooms on Ativas | Execute | Implemented |
| ROOM-04 | P1: Open rooms on Ativas | Execute | Implemented |
| RSV-01 | P1: See ended meetings as Passada | Execute | Implemented |
| RSV-02 | P1: See ended meetings as Passada | Execute | Implemented |
| RSV-03 | P1: See ended meetings as Passada | Execute | Implemented |
| RSV-04 | P1: See ended meetings as Passada | Execute | Implemented |
| RSV-05 | P1: See ended meetings as Passada | Execute | Implemented |
| RSV-06 | P1: See ended meetings as Passada | Execute | Implemented |
| RSV-07 | P1: See ended meetings as Passada | Execute | Implemented |

**ID format:** `ALIGN-01` = AC-001; `ROOM-01`…`ROOM-03` = AC-002…AC-004; `ROOM-04` = AC-012; `RSV-01`…`RSV-07` = AC-005…AC-011.

**Coverage:** 12 total, 12 mapped to T1–T7, 0 unmapped.

## Success Criteria

- [x] `Participantes` header and cells share `text-center`
- [x] `/rooms` without `status` lists only active rooms and echoes `active`
- [x] Ended non-cancelled rows show `Passada`; cancelled show `Cancelada`; not-yet-ended non-cancelled show `Ativa`
- [x] Ativas still lists past non-cancelled rows; occupancy unchanged
- [ ] Unit + Feature + frontend coverage gates pass. No Playwright
