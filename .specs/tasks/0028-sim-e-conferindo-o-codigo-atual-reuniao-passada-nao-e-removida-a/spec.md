# Specification

## Context

Cancelled reservations stay in MySQL (`cancelled_at` set) so RF12 can free the slot, but `ReservationRepository::listPage()` always applies `whereNull('cancelled_at')` and `IndexReservationController` hardcodes every row as `active` / `Ativa`. `period=all` already returns past and future **active** rows. The existing Período radios (`Todos` / `Hoje` / `Amanhã` / `1 semana`) and `Data inicial` / `Data final` (`starts_on` / `ends_on`) already cover time windows, including past days via the date range. The missing history is cancelled rows, not ended meetings.

The administrator asked for a listing `status` filter (`all` / `active` / `cancelled`), a Status select (Todas / Ativas / Canceladas), labels from `cancelledAt`, and no `completed` state. A later revision dropped a proposed “list past meetings” radio. This round’s human revision: the control is the same idea as rooms Ativas/Inativas, and the reservation default is `active` (`Ativas`). Rooms today still default to `all`; this spec does not change rooms.

## Problem

After cancel, the row vanishes from `/reservations` with no way to review cancelled history on the list. Past active meetings are not the bug.

## Problem Statement

The reservations list SHALL expose cancelled history through an explicit `status` filter (`all`, `active`, `cancelled`) instead of dropping every `cancelled_at` row with no recovery path. Omitted `status` SHALL default to `active`. Occupancy SHALL keep treating cancelled intervals as free. Existing period presets and `starts_on`/`ends_on` SHALL remain the only time filters.

## Goal

- List cancelled rows when `status` is `all` or `cancelled`.
- Keep omitted/`status=active` as the previous “hide cancelled” query (`Ativas`).
- Derive `status` / `status_label` from `cancelledAt`.
- Add a Status select that stays in the URL with the other filters (omit `status` when Ativas).
- Leave period, range, room, pagination, cancel dialog, overlap rules, and the rooms list unchanged.
- Do not model `completed`.
- Do not add a past-meetings radio or a yesterday period preset.

## User Stories

### P1: Filter and review cancelled history ⭐ MVP

**User Story**: As an administrator, I want to filter reservations by Todas / Ativas / Canceladas so that I can review cancelled history without mixing it into the default Ativas view.

**Why P1**: This is the reported failure: cancelled rows have no list path.

**Covered ACs**: AC-001, AC-002, AC-003, AC-004, AC-007, AC-008, AC-009, AC-010, AC-011, AC-013

**Acceptance Criteria**:

1. WHEN `GET /reservations` omits `status` THEN the system SHALL treat status as `active`
2. WHEN status is `all` THEN the system SHALL return active and cancelled rows that match room, period, and range filters
3. WHEN status is `active` THEN the system SHALL return only rows with `cancelled_at` null
4. WHEN status is `cancelled` THEN the system SHALL return only rows with `cancelled_at` not null
5. WHEN a listed reservation has `cancelledAt` not null THEN the system SHALL set item `status` to `cancelled` and `status_label` to `Cancelada`
6. WHEN a listed reservation has `cancelledAt` null THEN the system SHALL set item `status` to `active` and `status_label` to `Ativa`
7. IF `status` is not `all`, `active`, or `cancelled` THEN the system SHALL reject the request with HTTP 422 and `Informe um status válido.`
8. WHEN the reservations list renders THEN the system SHALL show a Status select labelled `Status` with options `Todas`, `Ativas`, and `Canceladas`
9. WHEN the administrator changes Status THEN the system SHALL visit `/reservations` with that `status` query param except omit `status` when Ativas, reset `page` to 1, and keep room, period, range, and `limit`
10. WHEN any reservation row exists including cancelled THEN the system SHALL set `hasAny` to true

**Independent Test**: FormRequest unit enum + omit + 422. ListReservations default active excludes cancelled; `all`/`cancelled` membership; hasAny true for cancelled-only. Feature GET omitted echoes `filters.status` `active`; `status=all` includes a cancelled factory row as `Cancelada`. Index Vitest: select, omit `status` on Ativas, include `status=all` on Todas.

### P1: Cancel keeps occupancy rules and list actions ⭐ MVP

**User Story**: As an administrator, I want cancel to keep freeing the slot and to keep cancelled rows action-less, so that cancelled history cannot be edited and the time stays bookable.

**Why P1**: RF12 and the existing cancel dialog must not regress.

**Covered ACs**: AC-005, AC-006, AC-012, AC-014, AC-015

**Acceptance Criteria**:

1. WHEN an administrator cancels a reservation WHILE `period` is `all` and status is `all` THEN the system SHALL keep that row on the list as `Cancelada`
2. WHEN an administrator cancels a reservation WHILE status is omitted or `active` THEN the system SHALL omit that row from the default Ativas list
3. The occupancy check SHALL continue to ignore rows with `cancelled_at` set so the cancelled interval stays bookable
4. WHILE a row has `status` other than `active` THEN the system SHALL hide `Editar` and `Cancelar` and show `—`
5. The reservations list SHALL keep the existing Período presets (`all`, `today`, `tomorrow`, `week`) and `starts_on`/`ends_on` range as the only time filters

**Independent Test**: Feature GET `period=all&status=all` after cancel / room deactivate-cancel / room delete-cancel lists those titles as `Cancelada`; omitted status still hides them. Existing cancel-then-store overlap test stays green. Index Vitest cancelled row has `Cancelada` and no actions. Existing Período radiogroup tests stay.

## Acceptance Criteria

Traceable copies (same outcomes):

- **AC-001** WHEN `GET /reservations` omits `status` THEN the system SHALL treat status as `active`
- **AC-002** WHEN status is `all` THEN the system SHALL return active and cancelled rows that match room, period, and range filters
- **AC-003** WHEN a listed reservation has `cancelledAt` not null THEN the system SHALL set item `status` to `cancelled` and `status_label` to `Cancelada`
- **AC-004** WHEN a listed reservation has `cancelledAt` null THEN the system SHALL set item `status` to `active` and `status_label` to `Ativa`
- **AC-005** WHEN an administrator cancels a reservation WHILE `period` is `all` and status is `all` THEN the system SHALL keep that row on the list as `Cancelada`
- **AC-006** The occupancy check SHALL continue to ignore rows with `cancelled_at` set so the cancelled interval stays bookable
- **AC-007** WHEN status is `active` THEN the system SHALL return only rows with `cancelled_at` null
- **AC-008** WHEN status is `cancelled` THEN the system SHALL return only rows with `cancelled_at` not null
- **AC-009** IF `status` is not `all`, `active`, or `cancelled` THEN the system SHALL reject the request with HTTP 422 and `Informe um status válido.`
- **AC-010** WHEN the reservations list renders THEN the system SHALL show a Status select labelled `Status` with options `Todas`, `Ativas`, and `Canceladas`
- **AC-011** WHEN the administrator changes Status THEN the system SHALL visit `/reservations` with that `status` query param except omit `status` when Ativas, reset `page` to 1, and keep room, period, range, and `limit`
- **AC-012** WHILE a row has `status` other than `active` THEN the system SHALL hide `Editar` and `Cancelar` and show `—`
- **AC-013** WHEN any reservation row exists including cancelled THEN the system SHALL set `hasAny` to true
- **AC-014** The reservations list SHALL keep the existing Período presets (`all`, `today`, `tomorrow`, `week`) and `starts_on`/`ends_on` range as the only time filters
- **AC-015** WHEN an administrator cancels a reservation WHILE status is omitted or `active` THEN the system SHALL omit that row from the default Ativas list

## Edge Cases

- IF only cancelled rows exist and status is omitted or `active` THEN the system SHALL show the filtered-empty copy, not “Nenhuma reserva cadastrada”
- WHEN `period=today` (or a range) AND status is `cancelled` THEN the system SHALL still restrict `starts_at` to that window
- WHEN `Limpar filtros` runs THEN the system SHALL visit `period=today` without `status`, `room_id`, or range
- WHEN pagination `Próxima` runs WHILE status is `cancelled` THEN the href SHALL include `status=cancelled`
- WHEN pagination `Próxima` runs WHILE status is `all` THEN the href SHALL include `status=all`
- WHEN pagination `Próxima` runs WHILE status is `active` THEN the href SHALL omit `status`
- IF `status=completed` or any other value THEN the system SHALL 422 (no third domain state)
- WHEN a past calendar day is needed THEN the system SHALL use `Data inicial` and `Data final`; it SHALL not add a `yesterday` preset or a past-meetings radio
- Remaining implicit-requirement dimensions (auth beyond existing `auth` middleware, concurrency of list reads, observability, rate limits) are N/A for this listing-filter scope

## Out of Scope

| Feature | Reason |
| --- | --- |
| `completed` / `Concluída` derived from `ends_at < now` | User deferred; past actives stay `Ativa` |
| Past-meetings radio / default hide ended meetings | Later human revision: period/range already exist; only cancelled filtering |
| New `yesterday` period preset | Past days are already `starts_on` + `ends_on`; current enum is `all,today,tomorrow,week` |
| Changing rooms list default from `all` to `active` | Rooms already ship `Todas`; this task is reservation cancelled history |
| Changing overlap, duration, capacity, or cancel PATCH | RF12 already covered; do not touch occupancy SQL |
| New history screen or hard-delete | Same list + filter is the requested fix |
| New ADR | ADR-005 already leaves listing cancelled as a gap; update the screen doc |
| Playwright / new E2E project | No runner in `package.json` |
| Changing period presets, range override, or pagination envelope | Task 0015 / ADR-010 stay |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | --------------- | --------- | ---------- |
| Default listing status | Omitted `status` → `active` | This-round human: default Ativas, same idea as rooms Ativo/Inativo. Rooms code still defaults to `all`; do not change rooms here | y |
| URL when Ativas | Omit `status` | Omit the param when it equals the reservation default; include `status=all` and `status=cancelled` | y |
| Where the `cancelled_at` predicate lives | `listPage()` only, default param `active` | Same split as rooms `is_active`; use case forwards the enum; omitted callers keep hide-cancelled | y |
| `hasAny` | Any row including cancelled | Otherwise cancelled-only catalogues look empty | y |
| Cancelled badge look | Existing non-active slate `StatusBadge` + label `Cancelada` | Index already styles `status !== 'active'` | y |
| Occupancy | Unchanged `whereNull('cancelled_at')` | User: cancelled still frees the slot | y |
| Time filters | Keep Período radios + Data inicial/Data final; no past-meetings radio | Human revision: “esquece o radiobutton”; past days via range | y |
| Default listing period | Omitted `period` → `today` | Repair human: “deixa o default do periodo em hoje”. Presets stay `all/today/tomorrow/week`. `Limpar filtros` visits `period=today` | y |
| Remaining implicit dimensions | N/A for this scope | Listing filter + labels + select only | y |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Hide past meetings (`ends_at < now`) automatically.** Rejected: `period=all` already lists past actives; the hole is cancelled history.
2. **Three-state Ativa / Concluída / Cancelada.** Deferred by the user; extra derived state, more UI and tests.
3. **Status filter with omitted → `all`.** Previous plan. Superseded this round: default must be `active` so the main list stays Ativas.
4. **Status filter `all` / `active` / `cancelled` on `listPage()`, omitted → `active`, map labels from `cancelledAt`, Status select on the list.** Smallest fix that matches the requested tests and this-round default. Occupancy stays exclusive of cancelled. Period/range stay as they are. Rooms list is untouched.
5. **Separate history route.** Extra screen and navigation; not requested.
6. **Also change rooms default from `all` to `active`.** Out of scope; rooms already document `Todas`.

## Selected Approach

Approach 4. Add `status` to `IndexReservationRequest` (`sometimes`, `in:all,active,cancelled`). Thread it `IndexReservationController` → `ListReservations` → `ReservationRepository::listPage()`. Replace the hardcoded `whereNull('cancelled_at')` in `listPage()` with the three predicates. Default omitted status to `active`. Map list item status from `cancelledAt`. Set `hasAny` to any row. Add the Status select; omit `status` in the query when Ativas. Do not add a past-meetings radio. Do not change rooms. Update `screen-reservations-list.md` and the tests that currently require cancelled rows to vanish even on `status=all`.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| --- | --- | --- | --- |
| LIST-01 | P1: Filter and review cancelled history | Execute | Implemented |
| LIST-02 | P1: Filter and review cancelled history | Execute | Implemented |
| LIST-03 | P1: Filter and review cancelled history | Execute | Implemented |
| LIST-04 | P1: Filter and review cancelled history | Execute | Implemented |
| LIST-05 | P1: Cancel keeps occupancy rules and list actions | Execute | Implemented |
| LIST-06 | P1: Cancel keeps occupancy rules and list actions | Execute | Implemented |
| LIST-07 | P1: Filter and review cancelled history | Execute | Implemented |
| LIST-08 | P1: Filter and review cancelled history | Execute | Implemented |
| LIST-09 | P1: Filter and review cancelled history | Execute | Implemented |
| LIST-10 | P1: Filter and review cancelled history | Execute | Implemented |
| LIST-11 | P1: Filter and review cancelled history | Execute | Implemented |
| LIST-12 | P1: Cancel keeps occupancy rules and list actions | Execute | Implemented |
| LIST-13 | P1: Filter and review cancelled history | Execute | Implemented |
| LIST-14 | P1: Cancel keeps occupancy rules and list actions | Execute | Implemented |
| LIST-15 | P1: Cancel keeps occupancy rules and list actions | Execute | Implemented |

**ID format:** `LIST-NN` maps 1:1 to AC-00N.

**Coverage:** 15 total, 15 mapped to T1–T5, 0 unmapped.

## Success Criteria

- [x] Default and `status=active` list only actives; `all` / `cancelled` isolate those sets
- [x] Cancelled rows show `Cancelada` without Editar/Cancelar; cancel on Todas keeps the row; cancel on Ativas hides it
- [x] Cancelled intervals remain bookable
- [x] Status select omits `status` when Ativas and keeps it for Todas/Canceladas
- [x] Período radios and Data inicial/Data final stay as the only time filters
- [x] Unit + Feature + frontend coverage gates pass. No Playwright
