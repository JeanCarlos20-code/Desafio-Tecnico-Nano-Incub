# Specification

## Context

Original request: "e outro, la no calendário e horario é bom o calendário pegar apenas os dias do dia atual pra frente, e mesma coisa com o horario, caso for marcado uma reunião fora do horario (ou seja marcar uma reunião no passado) deve dar erro (no react se der colocar essa trava tbm)"

RF18 already lives in `CreateReservation` (`starts_at < Clock::now()` → `StartsInPast`). The create screen still lets the native date and time pickers choose any calendar day and any clock time, so an administrator can aim at a past slot and only learn after POST.

## Problem

The create reservation calendar and time controls accept past values. A meeting in the past must fail, and React should block that path when it can, without weakening the server rule.

## Problem Statement

WHEN an administrator creates a reservation THEN the system SHALL offer only today and later dates, SHALL constrain start time on today so past clock times are not offered, SHALL refuse submit when the combined start datetime is before now, and SHALL keep rejecting a crafted past `starts_at` on the server with `A data não pode estar no passado.` and no new row.

## Goal

- Native date input on `Reservation/Create` uses `min` = today in the Inertia `timezone` prop.
- Native start-time input uses `min` = current `HH:MM` in that timezone while the selected date is today; no start-time `min` when the date is tomorrow or later.
- React submit does not call `store` / `form.post` when combined `starts_at` is before now; it shows `A data não pode estar no passado.`
- React submit does not call `store` / `form.post` when end time is not after start time; it shows `O término deve ser posterior ao início.`
- `CreateReservation` and `StoreReservationController` stay the authority (`< now`). Equal to now remains accepted. The FormRequest `ends_at` `after:starts_at` rule stays the write authority for order.
- List range filters and the edit screen occupancy widgets stay unchanged.
- `docs/screens/screen-reservation-create.md` records the React lock. No new ADR.

## User Stories

### P1: Block past create slots in the calendar and clock ⭐ MVP

**User Story**: As an administrator, I want the create form to offer only today-or-later dates and not-past start times, and I want a past booking to error even if I force the values, so that I cannot schedule a meeting in the past.

**Why P1**: This is the entire requested slice.

**Covered ACs**: AC-001 through AC-009

**Acceptance Criteria**:

1. WHEN `Reservation/Create` renders THEN the date input SHALL set `min` to the current calendar day in the page `timezone` prop (`YYYY-MM-DD`).
2. WHILE the selected date equals that current calendar day the start-time input SHALL set `min` to the current `HH:MM` in that timezone.
3. WHEN the selected date is after that current calendar day THEN the start-time input SHALL omit `min` (or treat it as empty) so any valid clock time may be chosen.
4. IF the administrator submits a date before today, or today plus a start time whose combined `starts_at` (date + `HH:MM` + `:00`) is before now in that timezone THEN React SHALL show `A data não pode estar no passado.` on the date or start-time field and SHALL NOT call `form.post`.
5. WHEN the combined `starts_at` is equal to now or after now and the rest of the form is filled THEN React SHALL proceed to the existing `store` / `form.post` transform.
6. IF `POST /reservations` receives `starts_at` before `Clock::now()` THEN `CreateReservation` SHALL throw `StartsInPast` and the HTTP layer SHALL return to create with `starts_at` = `A data não pode estar no passado.` and SHALL insert no reservation row.
7. WHEN `starts_at` equals `Clock::now()` THEN `CreateReservation` SHALL accept that start (existing RF18 boundary).
8. The list `starts_on` / `ends_on` inputs and the edit screen date/time widgets SHALL keep their current behavior (no today `min` on list filters; edit occupancy stays disabled).
9. IF the administrator submits an end time that is not after the start time THEN React SHALL show `O término deve ser posterior ao início.` and SHALL NOT call `form.post`.

**Independent Test**: Freeze the clock to `2026-09-21 08:00` UTC. Create page: date `min` is `2026-09-21`; with that date, start-time `min` is `08:00`; submit `2026-09-20` or `2026-09-21` + `07:00` does not POST and shows `A data não pode estar no passado.`; submit `2026-09-21` + `10:00` / `09:00` does not POST and shows `O término deve ser posterior ao início.`; submit `2026-09-21` + `08:00` still POSTs. Existing PHP unit + Feature past-start cases remain green with the date-focused copy.

## Acceptance Criteria

Traceable copies (same outcomes):

- **AC-001** WHEN `Reservation/Create` renders THEN the date input SHALL set `min` to the current calendar day in the page `timezone` prop (`YYYY-MM-DD`).
- **AC-002** WHILE the selected date equals that current calendar day the start-time input SHALL set `min` to the current `HH:MM` in that timezone.
- **AC-003** WHEN the selected date is after that current calendar day THEN the start-time input SHALL omit `min` (or treat it as empty).
- **AC-004** IF submit has a date before today or a today start whose combined `starts_at` is before now THEN React SHALL show `A data não pode estar no passado.` and SHALL NOT call `form.post`.
- **AC-005** WHEN combined `starts_at` is equal to now or later THEN React SHALL call the existing store transform.
- **AC-006** IF `POST /reservations` has `starts_at` before `Clock::now()` THEN the system SHALL return the existing `starts_at` error and SHALL insert no row.
- **AC-007** WHEN `starts_at` equals `Clock::now()` THEN `CreateReservation` SHALL accept that start.
- **AC-008** The list range date inputs and the edit occupancy widgets SHALL stay unchanged.
- **AC-009** IF submit has an end time that is not after the start time THEN React SHALL show `O término deve ser posterior ao início.` and SHALL NOT call `form.post`. The server `ends_at` `after:starts_at` rule stays the write authority.

## Edge Cases

- Typed past date despite native `min` (some browsers allow it) → AC-004 still blocks submit.
- Date empty or start time empty → existing required/backend path; do not invent a second required message.
- Today + start equal to current `HH:MM` while `now` has leftover seconds (`08:00:30` vs `08:00:00`) → React and PHP both treat `starts_at < now` as past; submit lock uses the same `<` rule.
- Tomorrow + `00:00` → allowed by the past rule (duration/order still apply).
- End time in the past while start is valid → not a new past rule; inverted end/start is AC-009 / existing RF14.
- Browser clock behind or ahead of the server → React may differ; PHP `Clock` remains the write gate.
- Guest POST → existing 302 to login; unchanged.
- Timezone prop missing → default to `UTC` (current `config('app.timezone')`).

## Out of Scope

| Feature | Reason |
| --- | --- |
| Changing RF18 comparison to `<=` | Existing unit accepts equal to now |
| `StoreReservationRequest` `after:now` | Business rule stays in the use case |
| List `starts_on` / `ends_on` `min` | Those filters must still show history |
| Edit date/time lock beyond disabled widgets | ADR-009; occupancy is not editable |
| End-time `min` as a past-start rule | Past rule is on `starts_at`; end-order lock is AC-009 against start, not against now |
| Overnight / multi-day create | Screen already models one calendar day |
| Recurrence, weekly calendar product, timezone picker | ADR-001 / ADR-005 leftovers |
| New ADR | RF18 already recorded |
| Playwright / new E2E project | Not in `package.json` |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| Which calendar | Create form only | User asked to block booking in the past, not listing history | n (planner default) |
| RF18 operator | Keep `starts_at < now`; equal allowed | Existing unit + Feature contract | n |
| React clock | `Date.now()` in Inertia `timezone` | Controller already sends `timezone`; live `min` as the page sits open | n |
| Error copy | `A data não pode estar no passado.` | Repair human feedback: date-focused copy; keep React and `StartsInPast` aligned | y |
| FormRequest `after:now` | Do not add | Architecture: business rule in Application | n |
| End-time picker | React lock when end is not after start; live FormRequest message | Repair human feedback; server `after:starts_at` stays authority | y |
| List / Edit | Unchanged | Different jobs (filter, frozen occupancy) | n |
| Auth / concurrency / overlap | Unchanged | Existing create flow | n |
| Remaining implicit dimensions (idempotency, rate limits, observability, external deps) | N/A for this scope | UX lock + existing RF18 | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **React `min` + submit lock; keep existing PHP RF18** — matches the request (“trava no React se der”) and leaves the server as authority. Trade-off: browser vs server clock can disagree; POST still wins.
2. **Add FormRequest `after:now` and skip React** — server-only. Trade-off: calendar still offers past days; user asked for the picker lock.
3. **Restrict list filters to today-forward as well** — over-reads “calendário”. Trade-off: administrators could not filter historical reservations.

## Selected Approach

Approach 1. Add a small timezone-aware helper next to the create page for today / current `HH:MM` / past-start. Wire `Create.jsx` `min` attributes and a submit guard. Do not change `CreateReservation`, FormRequest, list, or edit. Keep existing PHP unit and Feature RF18 tests. Update the create screen doc.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| --- | --- | --- | --- |
| PAST-01 | P1: Date input min = today | Execute | Implemented |
| PAST-02 | P1: Start-time min on today | Execute | Implemented |
| PAST-03 | P1: No start-time min on future dates | Execute | Implemented |
| PAST-04 | P1: React blocks past submit | Execute | Implemented |
| PAST-05 | P1: Valid start still posts | Execute | Implemented |
| PAST-06 | P1: Server past POST still errors | Execute | Implemented |
| PAST-07 | P1: Equal to now accepted | Execute | Implemented |
| PAST-08 | P1: List and edit unchanged | Execute | Implemented |
| PAST-09 | P1: React blocks end not after start | Repair | Implemented |

**ID format:** `PAST-NN` maps 1:1 to AC-00N.

**Coverage:** 9 total, 9 mapped to T1–T3 plus repair, 0 unmapped.
