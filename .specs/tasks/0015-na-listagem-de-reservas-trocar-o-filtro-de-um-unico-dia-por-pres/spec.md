# Specification

## Context

The reservations index (`GET /reservations`) is an authenticated Inertia page. Filtering is server-side. Today the only time filter is a single `date` query (`Y-m-d`) that defaults to the current local calendar day. The administrator cannot list every active meeting, look at tomorrow, look at the next week, or pick an arbitrary inclusive range. Pagination already appends the query string.

## Problem

A single required day hides meetings outside that day. Sharing or paging a “all actives”, “tomorrow”, “one week”, or custom range view is impossible. The screen doc still describes `date`; the requested product is presets plus an optional range in the URL.

## Problem Statement

Replace the single-day `date` filter with URL presets `Todos` / `Hoje` / `Amanhã` / `1 semana` and an optional `starts_on`+`ends_on` range. Filter on the server. Keep filters in the URL across pagination. `Todos` without a range lists every active reservation. A complete range overrides the preset.

## Goal

- Server-side window: `period=all|today|tomorrow|week` and optional inclusive calendar dates `starts_on`/`ends_on` (no times in the filter).
- Default / `all` without a range: every `cancelled_at` null row, no day cut.
- Complete range overrides `period`. Preset + range stay representable in the URL and in pagination links.
- Unit-test `ListReservations` and `IndexReservationRequest`. Feature-test `GET /reservations` for each preset and for a range.
- Keep room filter, page size 15, `starts_at ASC, id ASC`, and canceled-row exclusion.

## User Stories

### P1: Filter the list with URL presets ⭐ MVP

**User Story**: As an administrator, I want Todos, Hoje, Amanhã, and 1 semana in the query string so that I can open today’s meetings, tomorrow’s, the next seven local days, or every active meeting without picking a single date.

**Why P1**: Replaces RF10’s single-day control; default must become “all actives”.

**Covered ACs**: AC-001, AC-002, AC-003, AC-004, AC-005, AC-012, AC-013

**Acceptance Criteria**:

1. WHEN an authenticated administrator opens `GET /reservations` with no `period` or with `period=all` and without both range dates THEN the system SHALL list every reservation whose `cancelled_at` is null, with no `starts_at` day cut.
2. WHEN the request uses `period=today` and no complete range THEN the system SHALL include only actives whose `starts_at` falls in `[today 00:00, tomorrow 00:00)` in `config('app.timezone')`.
3. WHEN the request uses `period=tomorrow` and no complete range THEN the system SHALL include only actives whose `starts_at` falls in `[tomorrow 00:00, tomorrow+1 day 00:00)` in that timezone.
4. WHEN the request uses `period=week` and no complete range THEN the system SHALL include only actives whose `starts_at` falls in `[today 00:00, today+7 days 00:00)` in that timezone (seven local calendar days, today inclusive).
5. IF `period` is present and is not `all`, `today`, `tomorrow`, or `week` THEN the system SHALL reject the request with HTTP 422 and SHALL not change persisted data.

**Independent Test**: Freeze clock to 2026-09-21; seed actives on 20, 21, 22, and 28 Sep; assert each preset’s ID set.

### P1: Optional inclusive range overrides the preset ⭐ MVP

**User Story**: As an administrator, I want optional start and end calendar dates (`dd/mm/yyyy`, no times) under the presets so that I can list meetings whose start falls in a chosen inclusive local period, even if a preset is also in the URL.

**Why P1**: User request: interval prevails over preset; both appear in the URL.

**Covered ACs**: AC-006, AC-007, AC-008, AC-009, AC-014, AC-021

**Acceptance Criteria**:

1. WHEN both `starts_on` and `ends_on` are valid `Y-m-d` and `starts_on` ≤ `ends_on` THEN the system SHALL ignore `period` for the window and SHALL include only actives whose `starts_at` falls in `[starts_on 00:00, ends_on+1 day 00:00)` in the app timezone.
2. IF only one of `starts_on` or `ends_on` is present THEN the system SHALL reject the request with HTTP 422.
3. IF `starts_on` is after `ends_on` THEN the system SHALL reject the request with HTTP 422.
4. IF `starts_on` or `ends_on` is not `Y-m-d` THEN the system SHALL reject the request with HTTP 422.
5. WHEN the administrator applies a complete range in the UI THEN the React page SHALL send calendar dates `starts_on` and `ends_on` (`Y-m-d`, no time) and reset `page` to 1, and MAY keep the last `period` in the query.

**Independent Test**: `?period=today&starts_on=2026-09-22&ends_on=2026-09-23` returns only the 22–23 Sep actives.

### P1: Keep filters in the URL and across pages ⭐ MVP

**User Story**: As an administrator, I want the active preset, optional range, and room to stay in the URL when I page or refresh so that the same filtered list can be shared.

**Why P1**: User request; paginator already has `withQueryString()`.

**Covered ACs**: AC-010, AC-011, AC-015, AC-016

**Acceptance Criteria**:

1. WHILE a list response is rendered the Inertia `filters` prop SHALL echo `period` (default `all`), `starts_on`, `ends_on`, and `room_id` (null when omitted).
2. WHEN filtered results span more than one page THEN pagination links SHALL keep `period`, `starts_on`, `ends_on`, and `room_id` when those query values were present.
3. WHEN the administrator changes preset, range, or room in the UI THEN the React page SHALL request page 1.
4. WHEN the administrator selects a preset in the UI THEN the React page SHALL send that `period` and SHALL omit `starts_on` and `ends_on`.

**Independent Test**: Sixteen actives on 2026-09-21; `GET /reservations?period=today&page=2` links still contain `period=today`.

## Acceptance Criteria

1. WHEN an authenticated administrator opens `GET /reservations` with no `period` or with `period=all` and without both range dates THEN the system SHALL list every reservation whose `cancelled_at` is null, with no `starts_at` day cut. (AC-001)
2. WHEN the request uses `period=today` and no complete range THEN the system SHALL include only actives whose `starts_at` falls in `[today 00:00, tomorrow 00:00)` in `config('app.timezone')`. (AC-002)
3. WHEN the request uses `period=tomorrow` and no complete range THEN the system SHALL include only actives whose `starts_at` falls in `[tomorrow 00:00, tomorrow+1 day 00:00)` in that timezone. (AC-003)
4. WHEN the request uses `period=week` and no complete range THEN the system SHALL include only actives whose `starts_at` falls in `[today 00:00, today+7 days 00:00)` in that timezone. (AC-004)
5. IF `period` is present and is not `all`, `today`, `tomorrow`, or `week` THEN the system SHALL reject the request with HTTP 422 and SHALL not change persisted data. (AC-005)
6. WHEN both `starts_on` and `ends_on` are valid `Y-m-d` and `starts_on` ≤ `ends_on` THEN the system SHALL ignore `period` for the window and SHALL include only actives whose `starts_at` falls in `[starts_on 00:00, ends_on+1 day 00:00)` in the app timezone. (AC-006)
7. IF only one of `starts_on` or `ends_on` is present THEN the system SHALL reject the request with HTTP 422. (AC-007)
8. IF `starts_on` is after `ends_on` THEN the system SHALL reject the request with HTTP 422. (AC-008)
9. IF `starts_on` or `ends_on` is not `Y-m-d` THEN the system SHALL reject the request with HTTP 422. (AC-009)
10. WHILE a list response is rendered the Inertia `filters` prop SHALL echo `period` (default `all`), `starts_on`, `ends_on`, and `room_id`. (AC-010)
11. WHEN filtered results span more than one page THEN pagination links SHALL keep `period`, `starts_on`, `ends_on`, and `room_id` when those query values were present. (AC-011)
12. The system SHALL keep listing only `cancelled_at` null rows, ordered by `starts_at ASC` then `id ASC`, at 15 rows per page. (AC-012)
13. WHEN `room_id` is a valid UUID THEN the system SHALL apply the room filter together with the resolved time window. (AC-013)
14. WHEN the administrator applies a complete range in the UI THEN the React page SHALL send calendar dates `starts_on` and `ends_on` (`Y-m-d`, no time) and reset `page` to 1. (AC-014)
15. WHEN the administrator changes preset, range, or room in the UI THEN the React page SHALL request page 1. (AC-015)
16. WHEN the administrator selects a preset in the UI THEN the React page SHALL send that `period` and SHALL omit `starts_on` and `ends_on`. (AC-016)
17. WHEN `Limpar filtros` is used THEN the React page SHALL request `period=all` with no `room_id`, `starts_on`, or `ends_on`. (AC-017)
18. WHEN the resolved window is a single local calendar day THEN the system SHALL format `starts_at` and `ends_at` as `H:i`. WHEN the window is unbounded or longer than one day THEN the system SHALL format them as `d/m/Y H:i`. The row SHALL still expose `date` as `d/m/Y` of the start for the cancel dialog. (AC-018)
19. IF `room_id` is present and is not a UUID THEN the system SHALL reject the request with HTTP 422. (AC-019)
20. The query parameter `date` SHALL no longer define the list window. (AC-020)
21. WHEN the optional interval filter is rendered THEN the React page SHALL expose Data inicial and Data final as date-only inputs with no hour or minute fields. (AC-021)

## Edge Cases

- IF `period` is omitted THEN the system SHALL treat it as `all`.
- IF a complete range is sent with any valid `period` THEN the system SHALL use the range window.
- IF an active reservation starts on the last instant before `rangeEndExclusive` THEN the system SHALL include it; IF it starts at `rangeEndExclusive` THEN the system SHALL exclude it.
- IF week is computed on 2026-09-21 THEN 2026-09-27 is included and 2026-09-28 is not.
- IF the UI has only one range date filled THEN the React page SHALL not navigate.
- IF a time-of-day is typed into the interval filter THEN the React page SHALL not send a time component; only `Y-m-d` query values are allowed.
- IF the UI clears one range date after both were set THEN the React page SHALL drop both dates and SHALL fall back to `period`.
- IF every active row is outside the window but other actives exist THEN the system SHALL show the filtered empty state, not “Nenhuma reserva cadastrada.”
- IF a meeting crosses midnight THEN the system SHALL classify it only by `starts_at` (same as today’s day filter).
- IF `page` is less than 1 THEN `ListReservations` SHALL clamp it to 1.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Updating `docs/screens/screen-reservations-list.md` | Not requested; spec overrides the old `date` control |
| Create / cancel / occupancy / room lifecycle | Unchanged 0013–0014 behavior |
| Filtering canceled rows back into the list | 0014 exclusion stays |
| Time-of-day on the list interval filter | Human: `dd/mm/yyyy` only, no hours |
| Recurrence, calendar widget, timezone picker | ADR-001 / ADR-005 |
| Client-only filtering | Server remains authoritative |
| Bootstrapping Playwright | No runner in the repo |
| New Clock/Transaction types | Reuse existing Reservation ports |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Query keys | `period`, `starts_on`, `ends_on` | English keys like rooms `status`; labels stay Portuguese | n |
| Period values | `all` \| `today` \| `tomorrow` \| `week` | Stable enum; UI: Todos / Hoje / Amanhã / 1 semana | n |
| Default when omitted | `period=all`, no range | User: Todos without interval = all actives | n |
| Week window | Today through today+6 local days | Inclusive “1 semana” from today; half-open `+7 days` | n |
| Range vs preset | Range wins when both dates present; both stay in the URL | User: interval prevails; URL represents both | n |
| Range filter shape | Calendar dates only; visible `dd/mm/yyyy`; query `Y-m-d`; no hour/minute | Human revision: “pode ser só o dd/mm/yyyy sem os horarios no filtro”; same as current `type="date"` | y |
| Preset click vs range | UI clears range when a preset is applied | Otherwise a leftover range would hide every preset | n |
| Drop `date` | No compatibility alias | User asked to replace the single-day filter | n |
| Timezone | `config('app.timezone')` via Clock + DateTimeZone | Same as the current day bound (UTC in config) | n |
| Display times | `H:i` on a one-day window; `d/m/Y H:i` otherwise | Matches the screen’s “cleared day” rule | n |
| E2E | Not applicable | No Playwright; Feature + Vitest cover the contract | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Resolve `period` + optional range in `ListReservations`, nullable `listPage` bounds, FormRequest enum/dates** — selected.
2. Keep `date` and let presets only write that one day — rejected; cannot express Todos, week, or a range.
3. Range-only query without presets — rejected; user asked for Todos / Hoje / Amanhã / 1 semana in the URL.

## Selected Approach

Approach 1.

- Inject `Clock` into `ListReservations`. Resolve a nullable `[rangeStart, rangeEndExclusive)` from interval-or-preset in the app timezone. Forward `room_id` and clamped page as today.
- Change `ReservationRepository::listPage` bounds to nullable; Eloquent applies `starts_at` constraints only when both bounds are set.
- `IndexReservationRequest`: `period` sometimes `in:all,today,tomorrow,week`; `starts_on`/`ends_on` nullable `date_format:Y-m-d`, `required_with` each other, `ends_on` `after_or_equal:starts_on`; keep `room_id` uuid. Remove `date` as a window field.
- Controller: default `period` to `all`; pass validated range; Inertia `filters` = `{ room_id, period, starts_on, ends_on }`; keep `withQueryString()`; format times from window length.
- React: preset control + two `type="date"` inputs (Data inicial / Data final) under it — no time fields; `visitIndex` writes `Y-m-d`; presets clear range; complete range sends both dates; `Limpar filtros` → `period=all` only.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| RSV-01 | P1: URL presets | Execute | Done |
| RSV-02 | P1: Range overrides preset | Execute | Done |
| RSV-03 | P1: URL + pagination | Execute | Done |
| HTTP-01 | P1: FormRequest + Inertia filters | Execute | Done |
| UI-01 | P1: Preset + date-only range controls | Execute | Done |

**Coverage:** 5 total, 5 mapped to tasks, 0 unmapped
