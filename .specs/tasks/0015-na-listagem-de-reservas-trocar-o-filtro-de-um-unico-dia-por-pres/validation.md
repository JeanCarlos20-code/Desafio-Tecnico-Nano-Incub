# Validation

## Acceptance Criteria

| AC / Done-when / edge | `file:line` + assertion | Spec-defined outcome | Covered? |
| --------------------- | ----------------------- | -------------------- | -------- |
| Human: Hoje fills start and end with the current local date | `Index.test.jsx:170-171` `toHaveValue('2026-09-21')` on Data inicial and Data final | Start and end are today | Yes |
| Human: Amanhã fills start with today and end with today+1 | `Index.test.jsx:175-176` start `2026-09-21`, end `2026-09-22` | Start today, end one day later | Yes |
| Human: 1 semana fills the seven-day inclusive window | `Index.test.jsx:180-181` start `2026-09-21`, end `2026-09-27` | Today through today+6 | Yes |
| Human: Todos brings every active and clears the date fields | `Index.test.jsx:185-188` empty date inputs; `{ period: 'all', page: 1 }` without `starts_on`/`ends_on` | List all actives, no range | Yes |
| AC-016 preset still omits range from the query | `Index.test.jsx:145-148` `period: 'today'`, `not.toHaveProperty('starts_on'/'ends_on')` | Send period, omit range | Yes |
| Derived preset dates do not uncheck radios | `Index.test.jsx:193-197` period=today shows dates and Hoje stays checked | Display dates are not an applied range | Yes |
| AC-014 complete range still writes Y-m-d and may keep period | `Index.test.jsx:292-298` `period: 'today'`, `starts_on: '2026-09-21'`, `ends_on: '2026-09-23'` | Calendar dates, page 1, last period kept | Yes |
| AC-017 Limpar filtros | `Index.test.jsx:330-334` `{ period: 'all', page: 1 }`, no room/range | `period=all` only | Yes |
| AC-021 date-only inputs | `Index.test.jsx` type=date, no time / datetime-local | Data inicial / Data final, no time fields | Yes |

Prior execute ACs (presets, range override, pagination, 422, time format) stay covered by `ListReservationsTest`, `IndexReservationRequestTest`, and `ReservationIndexHttpTest`. This repair did not change PHP.

## Test Results

Repair Vitest `Index.test.jsx`: 13 passed (was 11; added preset date-field fill and load-time derived dates). ESLint on `Index.jsx` / `Index.test.jsx`: passed.

Backend unit/Feature suites were not re-run in this repair; no PHP files changed. Harness re-runs required gates after complete-phase.

## Required Gates

Local repair checks (harness will re-run deterministically):

- frontend: `npx vitest run resources/js/Pages/Reservation/Index.test.jsx` — 13 passed
- frontend_lint: eslint on touched Reservation Index files — passed
- unit / integration / php lint / builds: not re-run here (no PHP product change)

## Review Result

Round 2 verdict was APPROVED with no structured blockers or high findings. Repair implements the human report only: Data inicial / Data final follow the selected preset (today / today→tomorrow / week / empty for Todos) while the query still sends `period` and omits the range.

## Final Status

Repair is ready for harness checks. No blocker. No product commit.

### Extra files (concrete dependencies)

None. Repair touched `resources/js/Pages/Reservation/Index.jsx` and `Index.test.jsx` only (plus this file).

### Deviations

- Preset clicks still omit `starts_on`/`ends_on` from the URL (AC-016). The date inputs show the matching local calendar window so the fields move with the preset. Amanhã shows today→today+1 as requested; the server window for `period=tomorrow` remains tomorrow-only until the administrator edits the inputs into a real range.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `php artisan test --testsuite=Unit --coverage --min=80` — exit=0 (required)
- ✅ `npm run test:coverage` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run lint` — exit=0 (required)
- ✅ `composer run build` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
- ✅ `python3 -m pytest tests -q` — exit=0 (required)
- ✅ `python3 -m compileall -q src` — exit=0 (required)
- ✅ `PYTHONPATH=src python3 -c "import project_harness"` — exit=0 (required)
<!-- harness-checks:end -->
