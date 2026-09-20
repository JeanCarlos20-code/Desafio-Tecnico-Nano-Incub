# Task Context

## Relevant Documentation

- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF18: start datetime must not be in the past. Do not invent recurrence or a weekly calendar product.
- `docs/adr/005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md` — Reservation owns occupancy. Create already refuses `starts_at` before now. Edit of occupancy is out of this ADR (ADR-009 locks date/time on edit).
- `docs/adr/009-edicao-parcial-de-reserva-titulo-e-responsavel.md` — Edit may change only `title` and `responsible`. Date and time stay disabled. Do not add past-date locks on Edit.
- `docs/screens/screen-reservation-create.md` — Create form: `date` + `start_time` + `end_time` combined on the server. A booking for today is valid only when the full start datetime is still in the future (equal to now is already accepted by the use case). Suggested past message in the screen differs from the live error; keep the live string.
- `docs/screens/screen-reservations-list.md` — List `starts_on` / `ends_on` date inputs are history filters. Do not set `min` to today there.
- `docs/screens/screen-reservation-edit.md` — Occupancy widgets are display-only.
- `docs/architecture.md` — Backend is authoritative. React validation is UX only.
- `docs/test/unit.md` — New React picker/submit lock is unit (Vitest, clock mocked). Existing `CreateReservation` past rule stays unit on PHP with a fake Clock.
- `docs/test/integration.md` — Existing Feature `POST /reservations` past-start case stays the HTTP+MySQL contract. Do not add a second Feature matrix for the same RF18 path.
- `docs/test/e2e.md` — No Playwright project in `package.json`. Do not bootstrap one.

## Relevant Components

- `app` Reservation create: Application `CreateReservation` + `StartsInPast`; Infra `StoreReservationController`, `CreateReservationController` (already passes `timezone`), `StoreReservationRequest` (shape only: `starts_at` `required|date`).
- React `resources/js/Pages/Reservation/Create.jsx` — native `type="date"` / `type="time"` with no `min`, no client past check. Combines `date` + times into `starts_at` / `ends_at`.
- List `Index.jsx` date range and Edit disabled date/time: out of this task.

## Relevant Code

- `CreateReservation::execute` — `if ($startsAt < $this->clock->now()) throw new StartsInPast`. Equal to now is accepted (`CreateReservationTest::test_it_accepts_starts_at_equal_to_clock_now_and_exact_duration_bounds`).
- `StartsInPast` message: `A data não pode estar no passado.`
- `StoreReservationController` maps that exception to `back()->withErrors(['starts_at' => $exception->getMessage()])`. Feature freeze: `2026-09-21 08:00` in `config('app.timezone')` (UTC). Past payload `2026-09-21 07:00:00` is already asserted.
- `CreateReservationController` Inertia props: `rooms`, `timezone` (`config('app.timezone')`). `Create.jsx` currently ignores `timezone`.
- `Create.jsx` `combineDateTime` appends `:00` when time is `HH:MM`.
- `Create.test.jsx` covers fields, transform, capacity max, backend errors, processing, empty rooms. No `min` or past-submit cases.

## Existing Constraints

- Domain stays pure PHP. Do not put RF18 only in FormRequest (`after:now`). Keep the use case as the authority.
- Do not change duration, overlap, capacity, inactive-room, or lock rules.
- Do not change list filters, edit occupancy, schema, or timezone config.
- Frontend unit mocks Inertia and the clock (`Date` / `Intl`). No real Laravel or MySQL in Vitest.
- PHP coverage gate: Application ≥80%. Frontend coverage: `npm run test:coverage` ≥80%.
- `npx playwright test` is listed in `harness/stack.yml` but is not runnable.

## Important Decisions

- Scope is create only: date picker `min` = today in `timezone`; start-time `min` = current `HH:MM` while the selected date is today; submit does not POST when combined `starts_at` is before now.
- Comparison matches the use case: `starts_at < now` is past; `starts_at === now` is allowed.
- React uses `Date.now()` interpreted in the Inertia `timezone` prop. Server `Clock` remains authoritative if the browser clock drifts.
- List `Data inicial` / `Data final` stay unrestricted so administrators can still look up past reservations.
- No new PHP production code. Keep existing unit + Feature RF18 tests as-is.
- Screen doc: add the React `min` + submit lock; do not replace the backend rule. Keep the live Portuguese error string.
- No new ADR. RF18 already exists.
