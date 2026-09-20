---
harness:
  commits:
    - "feat(room): accept limit and return simple listing envelope"
    - "test(room): cover room listing page and limit envelope"
    - "feat(reservation): accept limit and return simple listing envelope"
    - "test(reservation): cover reservation listing page and limit envelope"
    - "chore(docs): record simple page and limit listing envelope"
    - "chore(specs): record page and limit listing plan artifacts"
  tests:
    unit:
      - "IndexRoomRequest accepts omitted page and limit and accepts page >= 1 with limit between 1 and 100"
      - "IndexRoomRequest rejects page 0 or a non-integer page with Informe uma página válida."
      - "IndexRoomRequest rejects limit 0, 101, or a non-integer limit with Informe um limite válido."
      - "IndexReservationRequest accepts omitted page and limit and accepts page >= 1 with limit between 1 and 100"
      - "IndexReservationRequest rejects page 0 or a non-integer page with Informe uma página válida."
      - "IndexReservationRequest rejects limit 0, 101, or a non-integer limit with Informe um limite válido."
      - "Room/Index with page 1, limit 2, total 3 shows Próxima to /rooms?page=2&limit=2 and keeps the current status filter"
      - "Room/Index on the last page shows Anterior and hides Próxima"
      - "Room/Index status change visits page=1 and the current limit"
      - "Reservation/Index with page 1, limit 2, total 3 shows Próxima including period, range, room_id, page, and limit"
      - "Reservation/Index filter change visits page=1 and the current limit"
    integration:
      - "Authenticated GET /rooms without page or limit returns rooms { data, page: 1, limit: 20, total } with at most 20 rows and no Laravel paginator keys"
      - "Authenticated GET /rooms?page=2&limit=10 returns the second slice of 10 and echoes page 2 and limit 10"
      - "Authenticated GET /rooms?limit=0 redirects with a session error on limit and does not change rooms"
      - "Authenticated GET /reservations without page or limit returns reservations { data, page: 1, limit: 20, total } with at most 20 rows and no Laravel paginator keys"
      - "Authenticated GET /reservations with filters plus page=2&limit=10 pages the filtered set, keeps filter props, and omits prev_page_url"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Page/limit validation is FormRequest unit; the envelope and MySQL slice are Feature HTTP; Anterior/Próxima hrefs are Room/Index and Reservation/Index Vitest. docs/test/e2e.md forbids repeating those matrices in the browser. Do not bootstrap Playwright in this task."
  gates:
    - id: unit
      command: "php artisan test --testsuite=Unit --coverage --min=80"
      required: true
    - id: frontend
      command: "npm run test:coverage"
      required: true
    - id: integration
      command: "php artisan test --testsuite=Feature"
      required: true
    - id: lint
      command: "vendor/bin/pint --test"
      required: true
    - id: frontend_lint
      command: "npm run lint"
      required: true
    - id: php_build
      command: "composer run build"
      required: true
    - id: frontend_build
      command: "npm run build"
      required: true
---

# Implementation Plan

## Summary

Add `?page` and `?limit` to the two large Inertia listings and replace Laravel's paginator array with `{ data, page, limit, total }`. Default `page=1`, `limit=20`, max `limit=100`. React builds Anterior/Próxima from that envelope. Record the choice in ADR-010. Leave `ListRooms` / `ListReservations` as they are.

**Design (inline, no `design.md`):**

- `IndexRoomRequest` / `IndexReservationRequest`: `page` `sometimes|integer|min:1`; `limit` `sometimes|integer|min:1|max:100`. Messages: `Informe uma página válida.` / `Informe um limite válido.`
- Controllers: `$page = (int) ($request->validated('page') ?? 1)`; `$limit = (int) ($request->validated('limit') ?? 20)`; pass `$limit` as `perPage`; return

```php
'rooms' => [
    'data' => $items,
    'page' => $page,
    'limit' => $limit,
    'total' => $result['total'],
],
```

  (same shape on `reservations`). Delete `LengthAwarePaginator` usage.
- Index pages: `lastPage = Math.max(1, Math.ceil(total / limit))` when `limit > 0`. Show nav when `total > limit`. `Anterior` when `page > 1`; `Próxima` when `page < lastPage`. Href = listing path + current filters + `page` + `limit` (omit empty filter values; do not copy unknown keys such as `foo`).
- Filter visits: `page: 1`, keep `limit` from the current envelope.
- Docs: `docs/adr/010-envelope-simples-de-paginacao-page-e-limit.md` (Portuguese MADR, Status Aceito, date 2026-09-20). Pagination sections on both list screens. Do not edit ADR-001..009 bodies.
- Rewrite existing Feature/Vitest assertions that read `per_page`, `current_page`, `next_page_url`, `prev_page_url`. Seed enough rows for two pages, or pass an explicit `limit`.

## Affected Components

- `app` — Room and Reservation index FormRequests, controllers, Index pages and their tests, ADR-010, `screen-rooms-list.md`, `screen-reservations-list.md`.
- Do not change Application use cases, repositories, filter catalogs, create/edit forms, or Playwright.

## Tasks

Execute T1 → T8 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. FormRequest tests stay outside the route (`create` + `validateResolved`), matching `IndexRoomRequestTest`. Vitest keeps Inertia mocked. Do not add `ListRooms` / `ListReservations` cases; slicing is already covered.

### Integration

See `harness.tests.integration`. One invalid-`limit` GET on `/rooms` proves the FormRequest is wired. Do not repeat the full page/limit matrix on the route. Replace `per_page` / `*_page_url` assertions; filter preservation on reservations stays on `filters.*` props.

### E2E

Not applicable — no Playwright project. See `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Unit coverage remains Application-only (≥80%). Frontend coverage via `npm run test:coverage` (≥80%). Do not run `npx playwright test`.

## Definition of Done

- Both index routes default to `page=1` and `limit=20` and honor explicit `page` / `limit`.
- Listing props are only `{ data, page, limit, total }`.
- Invalid page/limit never call the list use case.
- Anterior/Próxima use the envelope and keep known filters.
- ADR-010 and both list screen docs describe the contract.
- Pint, ESLint, PHP build, and Vite build pass.
- No product commit in PLAN.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `phpunit.xml` (Application coverage ≥80%), `package.json` (`npm run test:coverage`).

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| --- | --- | --- | --- | --- |
| IndexRoomRequest | unit | omitted defaults; valid page/limit; page < 1 / non-integer; limit 0 / 101 / non-integer | `tests/Unit/Room/IndexRoomRequestTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| IndexReservationRequest | unit | same page/limit contract; keep existing filter cases | `tests/Unit/Reservation/IndexReservationRequestTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| ListRooms / ListReservations | unit | Existing slice + clamp page; do not duplicate | `tests/Unit/Room/ListRoomsTest.php`, `tests/Unit/Reservation/ListReservationsTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| IndexRoomController | integration | default envelope; page+limit slice; one invalid limit on the route; no paginator keys | `tests/Feature/Room/RoomIndexHttpTest.php` | `php artisan test --testsuite=Feature` |
| IndexReservationController | integration | default envelope; filtered page+limit slice; no `prev_page_url` | `tests/Feature/Reservation/ReservationIndexHttpTest.php` | `php artisan test --testsuite=Feature` |
| Room/Index | unit | next/prev hrefs from envelope; hide on one page; filter reset keeps limit | `resources/js/Pages/Room/Index.test.jsx` | `npm run test:coverage` |
| Reservation/Index | unit | next href keeps filters + page + limit; filter reset keeps limit | `resources/js/Pages/Reservation/Index.test.jsx` | `npm run test:coverage` |
| ADR / screen docs | none | Copy only | `docs/adr/010-*.md`, `docs/screens/screen-*-list.md` | build gate only |

## Gate Check Commands

> Generated from `composer.json`, `package.json`, `phpunit.xml`.

| Gate Level | When to Use | Command |
| --- | --- | --- |
| Quick | After PHP unit-only tasks | `php artisan test --testsuite=Unit --coverage --min=80` |
| Frontend | After React tasks | `npm run test:coverage` |
| Full | After controller / Feature tasks | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` |
| Build | Phase end / docs / lint | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Decision record

```
T1
```

### Phase 2: Room listing envelope

```
T2 -> T3 -> T4
```

### Phase 3: Reservation listing envelope

```
T5 -> T6 -> T7
```

### Phase 4: Screen docs

```
T8
```

---

## Task Breakdown

### Phase 1: Decision record

### T1: Write ADR-010 for the simple listing envelope

**What**: Add Portuguese MADR `docs/adr/010-envelope-simples-de-paginacao-page-e-limit.md`. Record `?page` / `?limit`, defaults 1 / 20, max 100, Inertia `{ data, page, limit, total }`, and rejection of `LengthAwarePaginator` on the two large lists. Status Aceito, date 2026-09-20. Do not edit ADR-001..009 decision bodies.
**Where**: `docs/adr/010-envelope-simples-de-paginacao-page-e-limit.md`
**Depends on**: None
**Reuses**: ADR-009 MADR headings; create-adr skill
**Requirement**: PAGE-10

**Done when**:

- [x] ADR-010 exists with context, options, chosen envelope, and consequences
- [x] Next number is 010; language is Portuguese; existing ADR bodies untouched

**Tests**: none
**Gate**: build

---

### Phase 2: Room listing envelope

### T2: Validate page and limit on IndexRoomRequest

**What**: Add `limit` (`sometimes|integer|min:1|max:100`) beside existing `page`. Portuguese messages for `page` and `limit`. Cover the three Room FormRequest items in `harness.tests.unit`. Keep the existing `status` cases.
**Where**: `app/Modules/Room/Infra/Http/Requests/IndexRoomRequest.php`
**Depends on**: None
**Reuses**: `tests/Unit/Room/IndexRoomRequestTest.php` `create` + `validateResolved` helper
**Requirement**: PAGE-05, PAGE-06

**Done when**:

- [x] Omitted page/limit validate; valid integers pass
- [x] Invalid page → `Informe uma página válida.`; invalid limit → `Informe um limite válido.`
- [x] Gate check passes: `php artisan test --testsuite=Unit --coverage --min=80`

**Tests**: unit
**Gate**: quick

---

### T3: Return the simple rooms envelope from IndexRoomController

**What**: Read validated `page` (default 1) and `limit` (default 20). Pass `limit` into `ListRooms`. Replace `LengthAwarePaginator` with `{ data, page, limit, total }`. Cover the three Room Feature items in `harness.tests.integration`. Seed enough rows (or use explicit `limit`) so page 2 is non-empty. Keep `filters`, `hasAny`, and status-filter cases.
**Where**: `app/Modules/Room/Infra/Http/Controllers/IndexRoomController.php`
**Depends on**: T2
**Reuses**: `ListRooms::execute($page, $perPage, $status)`; `RoomIndexHttpTest`
**Requirement**: PAGE-01, PAGE-02, PAGE-09

**Done when**:

- [x] Default GET returns `page=1`, `limit=20`, `data` ≤ 20, `total` set, no paginator keys
- [x] `?page=2&limit=10` returns the second slice
- [x] `?limit=0` redirects with `limit` session errors
- [x] Gate check passes: `php artisan test --testsuite=Feature`

**Tests**: integration
**Gate**: full

---

### T4: Build Room/Index prev/next from the envelope

**What**: Stop reading `last_page` / `*_page_url`. Derive last page from `page`, `limit`, `total`. Build `/rooms?...` links with known filters plus `page` and `limit`. Status change sends `page: 1` and the current `limit`. Cover the three Room Index items in `harness.tests.unit`. Update `sampleRooms` fixtures to the new envelope.
**Where**: `resources/js/Pages/Room/Index.jsx`
**Depends on**: T3
**Reuses**: `visitIndex`; existing `Anterior` / `Próxima` copy and `aria-label="Paginação"`
**Requirement**: PAGE-07, PAGE-08

**Done when**:

- [x] `total > limit` shows Próxima on page 1 and Anterior on the last page
- [x] Hrefs include `page`, `limit`, and current `status` when not `all`
- [x] Status change visits `page=1` with the current `limit`
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: frontend

---

### Phase 3: Reservation listing envelope

### T5: Validate page and limit on IndexReservationRequest

**What**: Add the same `page` / `limit` rules and Portuguese messages as T2. Cover the three Reservation FormRequest items in `harness.tests.unit`. Keep existing period/range/`room_id` cases.
**Where**: `app/Modules/Reservation/Infra/Http/Requests/IndexReservationRequest.php`
**Depends on**: None
**Reuses**: `tests/Unit/Reservation/IndexReservationRequestTest.php` helpers
**Requirement**: PAGE-05, PAGE-06

**Done when**:

- [x] Omitted page/limit validate; valid integers pass
- [x] Invalid page/limit use the same Portuguese messages as rooms
- [x] Gate check passes: `php artisan test --testsuite=Unit --coverage --min=80`

**Tests**: unit
**Gate**: quick

---

### T6: Return the simple reservations envelope from IndexReservationController

**What**: Same controller pattern as T3 on `reservations`. Keep filter props and `filterRooms` unpaginated. Cover the two Reservation Feature items in `harness.tests.integration`. Rewrite `test_index_pagination_links_keep_period_range_and_room_id` to assert the envelope and `filters.*` (no `prev_page_url`).
**Where**: `app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php`
**Depends on**: T5
**Reuses**: `ListReservations::execute`; `ReservationIndexHttpTest`
**Requirement**: PAGE-03, PAGE-04, PAGE-09

**Done when**:

- [x] Default GET returns `page=1`, `limit=20`, `data` ≤ 20, no paginator keys
- [x] Filters + `page=2&limit=10` page the filtered set and keep `filters.*`
- [x] Gate check passes: `php artisan test --testsuite=Feature`

**Tests**: integration
**Gate**: full

---

### T7: Build Reservation/Index prev/next from the envelope

**What**: Same nav math as T4. Links keep `period`, `room_id`, `starts_on`, `ends_on` when set, plus `page` and `limit`. Filter visits send `page: 1` and the current `limit`. Cover the two Reservation Index items in `harness.tests.unit`.
**Where**: `resources/js/Pages/Reservation/Index.jsx`
**Depends on**: T6
**Reuses**: `visitIndex` / `visitFilters`; existing pagination nav markup
**Requirement**: PAGE-07, PAGE-08

**Done when**:

- [x] Próxima href includes active filters plus `page` and `limit`
- [x] Filter change visits `page=1` with the current `limit`
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: frontend

---

### Phase 4: Screen docs

### T8: Document page and limit on the list screens

**What**: Update the pagination sections of `screen-rooms-list.md` and `screen-reservations-list.md`: query params `page` and `limit`, defaults 1 and 20, max 100, envelope keys, reset page on filter change while keeping `limit`. Point to ADR-010. Do not rewrite unrelated screen sections.
**Where**: `docs/screens/screen-rooms-list.md`
**Depends on**: T1, T4, T7
**Reuses**: Existing “Data ordering and pagination” / “Pagination” headings
**Requirement**: PAGE-10

**Done when**:

- [x] Both list screens document `page`, `limit`, defaults, max, and `{ data, page, limit, total }`
- [x] Filter-change copy says page resets to 1 and `limit` is kept
- [x] ADR-010 is linked

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1:  T1
Phase 2:  T2 -> T3 -> T4
Phase 3:  T5 -> T6 -> T7
Phase 4:  T8
```

Execution is strictly sequential.

---

## Task Granularity Check

| Task | Scope | Status |
| --- | --- | --- |
| T1: ADR-010 | 1 file | Granular |
| T2: IndexRoomRequest | 1 FormRequest | Granular |
| T3: IndexRoomController | 1 controller | Granular |
| T4: Room/Index | 1 page | Granular |
| T5: IndexReservationRequest | 1 FormRequest | Granular |
| T6: IndexReservationController | 1 controller | Granular |
| T7: Reservation/Index | 1 page | Granular |
| T8: list screen docs | 1 screen file named in Where; pair file in Done when | Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| --- | --- | --- | --- |
| T1 | None | (root) | Match |
| T2 | None | (root) | Match |
| T3 | T2 | T2 -> T3 | Match |
| T4 | T3 | T3 -> T4 | Match |
| T5 | None | (root) | Match |
| T6 | T5 | T5 -> T6 | Match |
| T7 | T6 | T6 -> T7 | Match |
| T8 | T1, T4, T7 | (cross-phase; no intra-phase arrow) | Match |

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| --- | --- | --- | --- | --- |
| T1 | ADR / screen docs | none | none | OK |
| T2 | IndexRoomRequest | unit | unit | OK |
| T3 | IndexRoomController | integration | integration | OK |
| T4 | Room/Index | unit | unit | OK |
| T5 | IndexReservationRequest | unit | unit | OK |
| T6 | IndexReservationController | integration | integration | OK |
| T7 | Reservation/Index | unit | unit | OK |
| T8 | ADR / screen docs | none | none | OK |
