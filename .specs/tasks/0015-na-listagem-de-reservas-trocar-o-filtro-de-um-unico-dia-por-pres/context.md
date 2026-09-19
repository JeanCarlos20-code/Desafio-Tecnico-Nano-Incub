# Task Context

## Relevant Documentation

- `docs/test/unit.md` — use cases and FormRequest (no HTTP/DB); React pages/hooks with Inertia mocked.
- `docs/test/integration.md` — Feature = real GET `/reservations` through middleware + FormRequest + controller + use case + Eloquent + MySQL 8. Do not repeat the full validation matrix.
- `docs/test/e2e.md` — browser + React + Laravel + MySQL for selected full flows only. No Playwright project exists (`package.json` has Vitest only; `stack.yml` e2e is not runnable).
- `docs/architecture.md` — thin controller; FormRequest = HTTP shape; `ListReservations` owns the window; Domain has no Illuminate.
- `docs/adr/005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md` — RF08 list `starts_at ASC`; RF09 room; RF10 day; list is actives only (`cancelled_at` null). This task extends RF10 with presets + optional range.
- `docs/screens/screen-reservations-list.md` — still documents `date` defaulting to today. **Do not follow that day control.** Follow this task’s URL contract. Leave the screen doc unchanged.
- `docs/tree.md` / `docs/context.md` — Reservation module + `resources/js/Pages/Reservation`.
- Confirmed lessons: none.

## Relevant Components

- `app` Reservation module: `ListReservations`, `ReservationRepository::listPage`, `IndexReservationRequest`, `IndexReservationController`, `Reservation/Index.jsx`, `Services/reservations.js`.
- Tests: `ListReservationsTest`, `IndexReservationRequestTest`, `ReservationIndexHttpTest`, `Index.test.jsx`, `FakeReservationRepository`.
- Clock already bound: `Clock` → `LaravelClock` in `AppServiceProvider`. Reuse `FakeClock` in unit tests.
- Unchanged: create/cancel/occupancy, guest redirect, page size 15, `hasAny`, room filter `room_id`.

## Relevant Code

- `ListReservations::execute($page, $perPage, $roomId, $date, $timezone)` always builds `[date 00:00, +1 day)` in `$timezone` and never accepts “no day”.
- `IndexReservationController` defaults missing `date` to `now()->timezone(config('app.timezone'))->toDateString()` (config timezone is `UTC`). Inertia `filters` = `{ room_id, date }`. Times rendered as `H:i`. Paginator already `withQueryString()`.
- `EloquentReservationRepository::listPage` requires both bounds and filters `starts_at >= start AND starts_at < endExclusive`, `whereNull('cancelled_at')`, `orderBy starts_at, id`.
- `IndexReservationRequest` today: `room_id` nullable uuid; `date` nullable `Y-m-d`.
- React `applyFilters` always sends `date` + optional `room_id` + `page: 1`. `Limpar filtros` keeps `date`. Pagination uses backend `prev_page_url` / `next_page_url`.
- Existing Feature default case asserts `filters.date = 2026-09-21` and only today’s rows. Hide-canceled Feature still calls `?date=`. Those contracts must change with the default `period=all`.

## Existing Constraints

- Application coverage ≥ 80% (`phpunit.xml` includes Reservation Application). Frontend coverage ≥ 80% (`npm run test:coverage`).
- Use case must not import Illuminate. Inject `Clock`; parse calendar days with `DateTimeZone($timezone)`.
- Validation of query shape stays in FormRequest. Window math stays in `ListReservations`.
- Matching rule stays “reservation `starts_at` belongs to the local window”, not overlap-with-window.
- Do not add Playwright. Do not change occupancy, cancel, or room lifecycle.

## Important Decisions

- Query keys: `period=all|today|tomorrow|week`, optional `starts_on` + `ends_on` (`Y-m-d`), existing `room_id` + `page`. Drop `date`.
- Omitted `period` ⇒ `all`. `GET /reservations` lists every active row (no day cut).
- **Week** = seven local calendar days starting today: `[today 00:00, today+7 days 00:00)` in app timezone.
- Interval is present only when **both** dates are valid. It **overrides** `period` for the window. Both may stay in the URL.
- **Human revision:** the optional range is **calendar dates only** — no hour/minute in the filter. Visible inputs follow `dd/mm/yyyy` (native `type="date"`, same as today’s `Data` field). Query values stay `Y-m-d`. Do not add `datetime-local` or time fields. Server still expands each date to a full local day: `[starts_on 00:00, ends_on+1 day 00:00)`.
- One-sided or inverted range ⇒ FormRequest 422. Unknown `period` ⇒ 422. URL `21/09/2026` remains invalid (`date_format:Y-m-d`).
- Applying a preset in the UI **clears** `starts_on` / `ends_on` so presets remain usable. Applying a complete range keeps the last `period` in the query; the server still uses the range.
- Incomplete range in the UI does not navigate (avoids 422). Clearing one date after a complete range drops both dates and falls back to `period`.
- `Limpar filtros` ⇒ `period=all`, no room, no range (not “today”).
- Single-day window (`today`, `tomorrow`, or a one-day range) keeps `H:i`. Unbounded or multi-day uses `d/m/Y H:i`. Row `date` (`d/m/Y` of start) stays for the cancel dialog.
- `listPage` bounds become nullable; both null means no `starts_at` constraint.
