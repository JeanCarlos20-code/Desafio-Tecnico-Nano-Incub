# Validation

## Acceptance Criteria

| AC | Spec-defined outcome | Evidence | Covered? |
| --- | --- | --- | --- |
| AC-001 | Omitted `status` is treated as `active` | `tests/Feature/Reservation/ReservationIndexHttpTest.php:58` `->where('filters.status', 'active')` | Yes |
| AC-002 | `status=all` returns active and cancelled rows that still match room/period/range | `tests/Feature/Reservation/ReservationIndexHttpTest.php:464` `->where('filters.status', 'all')` | Yes |
| AC-003 | `cancelledAt` not null → `cancelled` / `Cancelada` | `tests/Feature/Reservation/ReservationIndexHttpTest.php:453` `->where('filters.status', 'cancelled')` | Yes |
| AC-004 | `cancelledAt` null → `active` / `Ativa` | `tests/Feature/Reservation/ReservationIndexHttpTest.php:433` `->where('filters.status', 'active')` | Yes |
| AC-005 | Cancel while `period=all` and `status=all` keeps the row as `Cancelada` | `tests/Feature/Reservation/ReservationIndexHttpTest.php:389` `->where('filters.status', 'all')` after cancel | Yes |
| AC-006 | Occupancy still ignores `cancelled_at` rows | `tests/Feature/Reservation/ReservationCancelHttpTest.php` interval reuse remains | Yes |
| AC-007 | `status=active` returns only `cancelled_at` null | Feature `:444` explicit `status=active` | Yes |
| AC-008 | `status=cancelled` returns only `cancelled_at` not null | Feature `:453` `filters.status` `cancelled` | Yes |
| AC-009 | Unknown `status` is HTTP 422 with `Informe um status válido.` | Feature `:478` `status=weekend` | Yes |
| AC-010 | Status select labelled `Status` with Todas / Ativas / Canceladas | `resources/js/Pages/Reservation/Index.test.jsx` Status select default Ativas | Yes |
| AC-011 | Status change visits with the enum except omit when Ativas | Index.test.jsx Canceladas visit keeps room/period/range/`limit` | Yes |
| AC-012 | Non-active row hides Editar/Cancelar and shows `—` | Index.test.jsx cancelled row dash | Yes |
| AC-013 | `hasAny` is true when only cancelled rows exist | `tests/Unit/Reservation/ListReservationsTest.php` cancelled-only `hasAny` | Yes |
| AC-014 | Time filters stay Período `all/today/tomorrow/week` plus `starts_on`/`ends_on` | Index.test.jsx four radios; omitted period now defaults to `today` | Yes |
| AC-015 | Cancel while omitted/`active` omits the row from Ativas | Feature GET `period=all` still hides cancelled titles | Yes |
| Repair human | Keep status filter; omitted `period` is `today`; `Limpar filtros` visits `period=today` | Feature `:54` `->where('filters.period', 'today')` and excludes tomorrow; Index.test.jsx Hoje checked; Limpar filtros `{ period: 'today', page: 1, limit: 20 }` | Yes |

SMELL-001 (medium StatusBadge fallback) was not repaired: packet forbids opening medium findings.

## Test Results

Feature `ReservationIndexHttpTest` + `ReservationUpdateHttpTest`: 14 passed (369 assertions).

Vitest `resources/js/Pages/Reservation/Index.test.jsx`: 22 passed.

Worker ran the targeted suites after the period-default change. Full required gates are owned by harness `complete-phase`.

## Required Gates

Worker did not declare the harness gate final. Local checks:

| Gate | Worker result |
| --- | --- |
| Feature ReservationIndexHttpTest + ReservationUpdateHttpTest | 14 passed |
| Vitest Reservation/Index.test.jsx | 22 passed |
| Pint on touched PHP files | passed |

Extra files beyond the original T1–T5 product set: `docs/screens/screen-reservations-list.md`, `spec.md`, `context.md`, and `tasks.md` were updated because the repair human revision changed the documented period default. No occupancy, rooms, or cancel-PATCH files were touched.

## Review Result

Round 1 verdict was `APPROVED` with no blocker/high. Repair implements the packet human feedback only: keep the listing status filter, default Período to `today` (`Hoje`).

## Final Status

Repair for omitted `period` → `today` is implemented and locally green on the targeted Feature and Vitest suites. No product commit from this worker.

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
<!-- harness-checks:end -->
