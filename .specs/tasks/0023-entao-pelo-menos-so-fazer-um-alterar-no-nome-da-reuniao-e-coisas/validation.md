# Validation

## Acceptance Criteria

| AC | Outcome | Evidence |
| --- | --- | --- |
| AC-001 | PUT persists trimmed title/responsible and leaves occupancy + cancelled_at unchanged | `tests/Unit/Reservation/UpdateReservationTest.php` persist test; `tests/Feature/Reservation/ReservationUpdateHttpTest.php` MySQL PUT |
| AC-002 | Missing id throws ReservationNotFound and HTTP 404 without write | Unit missing-id test; Feature GET/PUT `/reservations/999999` |
| AC-003 | Cancelled reservation throws ReservationNotFound and HTTP 404 without write | Unit cancelled test; Feature GET/PUT cancelled row |
| AC-004 | Occupancy keys on PUT fail FormRequest and do not call the use case | `UpdateReservationRequestTest` prohibited keys; Feature prohibited `starts_at` wiring |
| AC-005 | Missing/blank title or responsible uses store Portuguese required messages | `UpdateReservationRequestTest` required + trim + whitespace |
| AC-006 | GET edit renders Inertia Reservation/Edit with current metadata and occupancy display values | Feature GET edit Inertia props (`Y-m-d`, `H:i`, room_name) |
| AC-007 | GET edit missing/cancelled returns 404 | Feature cancelled/missing GET |
| AC-008 | Guest GET edit and PUT redirect 302 to login and write nothing | `ReservationGuestHttpTest` data provider |
| AC-009 | Index active row shows Editar to `/reservations/{id}/edit` beside Cancelar | `Index.test.jsx`; Feature listed id is GET-edit reachable |
| AC-010 | UpdateReservation does not run occupancy rules or locks | Unit no occupancy-method calls; use case has only ReservationRepository |
| AC-011 | Edit form sends only title and responsible; occupancy fields disabled | `Edit.test.jsx` transform keys + disabled controls |
| AC-012 | ADR-009, README leftover, screen-reservation-edit, list screen, room-form capacity leftover | `docs/adr/009-edicao-parcial-de-reserva-titulo-e-responsavel.md` and the leftover doc edits |

## Test Results

Local quick checks in this worktree (not the harness deterministic gate):

- `php artisan test --testsuite=Unit --filter=UpdateReservation` — 6 passed
- `php artisan test --testsuite=Feature --filter=Reservation` — Reservation HTTP including guest/update passed
- `php artisan test --testsuite=Feature` — 124 passed
- `php artisan test --testsuite=Unit --coverage --min=80` — 64 passed (exit 0)
- `npm run test:coverage` — 103 passed, statements 95.54%
- `vendor/bin/pint --test` — passed
- `npm run lint` — passed
- `composer run build` — passed
- `npm run build` — passed

E2E: not applicable. No Playwright project. See `harness.tests_not_applicable.e2e`.

## Required Gates

The harness re-runs `harness.gates` after this phase. Local checks above are not the final gate verdict.

## Review Result

Pending harness review.

## Final Status

Implementation of T1–T7 is in the worktree and ready for harness checks. No commit.

## Extra files

`tasks.md` named a primary `Where` per task. These extra paths were required by concrete dependencies:

- `app/Modules/Reservation/Infra/Http/Controllers/EditReservationController.php` — GET edit (T4)
- `app/Modules/Reservation/Infra/Http/Requests/UpdateReservationRequest.php` and its unit test — T3
- `tests/Feature/Reservation/ReservationUpdateHttpTest.php` — T4 integration list
- `resources/js/Pages/Reservation/Edit.jsx` and `Edit.test.jsx` — T5
- `docs/adr/009-edicao-parcial-de-reserva-titulo-e-responsavel.md` and `docs/screens/screen-reservation-edit.md` — T7
- `tests/Unit/Reservation/FakeReservationRepository.php` occupancy-call tracking — T1/T2 unit contract
- ADR-005 status pointer, ADR-001 extra note, list/room-form screens, README leftover — T7 leftovers

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
