# Validation

## Acceptance Criteria

| AC | Outcome | Evidence |
| --- | --- | --- |
| AC-001 / PAGE-01 | Rooms default envelope `page=1`, `limit=20`, at most 20 rows | `tests/Feature/Room/RoomIndexHttpTest.php:140` `where('rooms.page', 1)`; `:141` `where('rooms.limit', 20)`; `:139` `has('rooms.data', 20)` |
| AC-002 / PAGE-02 | Rooms `page=2&limit=10` returns the second slice | `tests/Feature/Room/RoomIndexHttpTest.php:165` `where('rooms.page', 2)`; `:166` `where('rooms.limit', 10)`; `:171` second-slice id list |
| AC-003 / PAGE-03 | Reservations default envelope `page=1`, `limit=20`, at most 20 rows | `tests/Feature/Reservation/ReservationIndexHttpTest.php:58` `where('reservations.page', 1)`; `:59` `where('reservations.limit', 20)`; `:66` `has('reservations.data', 6)` |
| AC-004 / PAGE-04 | Filtered reservations page with `page` and `limit`; filters unchanged | `tests/Feature/Reservation/ReservationIndexHttpTest.php:222-227` `filters.period/starts_on/ends_on/room_id` plus `reservations.page` 2 and `limit` 10 |
| AC-005 / PAGE-05 | Invalid `page` fails with `Informe uma página válida.` and does not list | `tests/Unit/Room/IndexRoomRequestTest.php:57` and `tests/Unit/Reservation/IndexReservationRequestTest.php:71` `assertSame(['Informe uma página válida.'], ...['page'])` |
| AC-006 / PAGE-06 | Invalid `limit` fails with `Informe um limite válido.` and does not list | Unit: `tests/Unit/Room/IndexRoomRequestTest.php:69`. Wired on the route: `tests/Feature/Room/RoomIndexHttpTest.php:184` `assertSessionHasErrors(['limit' => 'Informe um limite válido.'])` and unchanged room rows |
| AC-007 / PAGE-07 | Anterior/Próxima built from `page`, `limit`, `total`, and known filters | `resources/js/Pages/Room/Index.test.jsx:333` `toHaveAttribute('href', '/rooms?page=2&limit=2&status=inactive')`; `resources/js/Pages/Reservation/Index.test.jsx:551-556` href contains period, range, `room_id`, `page`, `limit` |
| AC-008 / PAGE-08 | Filter change sends `page=1` and keeps current `limit` | `resources/js/Pages/Room/Index.test.jsx:372` `{ status: 'inactive', page: 1, limit: 2 }`; `resources/js/Pages/Reservation/Index.test.jsx:582` `{ period: 'today', room_id: 'room-1', page: 1, limit: 2 }` |
| AC-009 / PAGE-09 | Listing props are only `data`, `page`, `limit`, `total` | `tests/Feature/Room/RoomIndexHttpTest.php:144-149` `missing('rooms.per_page'|`current_page`|`last_page`|`*_page_url`|`links`)`; same for reservations at `:61-65` and `:231-234` |
| AC-010 / PAGE-10 | ADR-010 and both list screen docs | `docs/adr/010-envelope-simples-de-paginacao-page-e-limit.md`; `docs/screens/screen-rooms-list.md` pagination section; `docs/screens/screen-reservations-list.md` pagination section |

Edge cases covered: omitted defaults (FormRequest unit + Feature GET without params); numeric-string integers accepted by Laravel `integer`; `page=0` / `abc` and `limit=0` / `101` / `abc`; page past last is existing use-case slice (empty `data`, echo page/limit); `total > limit` shows nav; filter reset keeps `limit`. Unknown `foo=bar` is no longer copied onto listing props (paginator URLs removed).

## Test Results

Created or rewritten one test for each `harness.tests` item at the declared level.

**Unit (FormRequest + Vitest)**

- `IndexRoomRequest` accepts omitted / valid `page` and `limit`; rejects `page` 0 or non-integer; rejects `limit` 0, 101, or non-integer.
- `IndexReservationRequest` same contract; existing period/range/`room_id` cases kept.
- `Room/Index` Próxima `/rooms?page=2&limit=2` keeps `status`; last page shows Anterior and hides Próxima; status change visits `page=1` and current `limit`.
- `Reservation/Index` Próxima includes period, range, `room_id`, `page`, `limit`; filter change visits `page=1` and current `limit`.
- Existing `ListRooms` / `ListReservations` slice tests unchanged.

**Integration (Feature HTTP + MySQL 8)**

- GET `/rooms` without params: `{ data, page: 1, limit: 20, total }`, 20 rows when total > 20, no Laravel paginator keys.
- GET `/rooms?page=2&limit=10`: second slice of 10, echoed page/limit.
- GET `/rooms?limit=0`: redirect + session error on `limit`; rooms unchanged.
- GET `/reservations` without params: default envelope, no paginator keys.
- GET `/reservations` with filters + `page=2&limit=10`: filtered slice, `filters.*` kept, `prev_page_url` omitted.

**E2E**

- Not applicable. No Playwright project. See `harness.tests_not_applicable.e2e`.

Local runs in this worktree (harness will re-run the official gates):

- `php artisan test --testsuite=Unit --coverage --min=80` — 70 passed
- `php artisan test --testsuite=Feature` — 126 passed
- `npm run test:coverage` — 115 passed, statements 96.28%
- Focused listing Feature: 13 passed

## Required Gates

Ran locally from the worktree. Final green is owned by the harness re-run, not this report.

| Gate | Command | Local result |
| --- | --- | --- |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` | passed (70 tests) |
| frontend | `npm run test:coverage` | passed (115 tests, ≥80%) |
| integration | `php artisan test --testsuite=Feature` | passed (126 tests) |
| lint | `vendor/bin/pint --test` | passed |
| frontend_lint | `npm run lint` | passed |
| php_build | `composer run build` | passed |
| frontend_build | `npm run build` | passed |

## Review Result

Not started. Execute only. No `review/review-NN.md` in this round.

## Final Status

Implementation of T1–T8 is ready for harness deterministic checks and human review.

Product files: both index FormRequests and controllers, both Index pages, ADR-010, both list screen docs.

Test files: `IndexRoomRequestTest`, `IndexReservationRequestTest`, `RoomIndexHttpTest`, `ReservationIndexHttpTest`, `Room/Index.test.jsx`, `Reservation/Index.test.jsx`.

No extra product files beyond the plan. `listingHref` lives in each Index page (same files as T4/T7). Worktree needed `composer install`, `npm ci`, and a local `.env` copy from `.env.testing` so artisan and Vitest could run; those artifacts are gitignored and are not part of the feature.

No commit. No Playwright. Application use cases unchanged.

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
