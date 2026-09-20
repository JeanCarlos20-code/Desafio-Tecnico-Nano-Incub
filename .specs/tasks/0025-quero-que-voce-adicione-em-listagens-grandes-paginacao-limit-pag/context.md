# Task Context

## Relevant Documentation

- `docs/architecture.md` — Inertia page → controller → FormRequest → use case → repository. Controllers stay thin. No extra DTO/mapper layer without a concrete need.
- `docs/tree.md` — Modules under `app/Modules/{Room,Reservation}`; list pages under `resources/js/Pages/{Room,Reservation}/Index.jsx`.
- `docs/context.md` — Admin panel for rooms and reservations; Laravel + Inertia + React + MySQL 8.
- `docs/screens/screen-rooms-list.md` — `GET /rooms`; server-driven pagination below the table; preserve query params; reset to page 1 on filter change. Page size is “configured”, not a named query param.
- `docs/screens/screen-reservations-list.md` — `GET /reservations`; same pagination rules; filters `room_id`, `period`, `starts_on`, `ends_on`.
- `docs/adr/008-identidade-autoincremento-rooms-e-reservations.md` — Next ADR number is **010**. Existing ADRs are MADR in Portuguese.
- `docs/adr/001` through `009` — No pagination contract. Do not rewrite their decision bodies.
- `docs/test/unit.md` — Use cases and FormRequest (query params, pagination bounds) without HTTP/DB; React Index pages with Inertia mocked.
- `docs/test/integration.md` — Authenticated GET index through Laravel + MySQL 8; prove FormRequest is wired; do not repeat the full validation matrix.
- `docs/test/e2e.md` — Browser only for selected full flows. `package.json` has no Playwright project. `harness/stack.yml` lists `npx playwright test` but it is not runnable. Do not bootstrap Playwright.
- `phpunit.xml` — Unit coverage ≥80% on Application only (`ListRooms` / `ListReservations` already covered; this plan does not change those use cases).
- `.cursor/skills/create-adr/SKILL.md` — ADR in the user’s language (Portuguese), MADR, `docs/adr/010-*.md`.

## Relevant Components

- `app` — Room and Reservation index HTTP + Inertia pages. Shared `resources/js/Components` has no pagination helper.
- Not in scope: `OccupancyRoomCatalog` / `filterRooms`, create/edit forms, User login, harness.

## Relevant Code

- `IndexRoomController` / `IndexReservationController` — read `page` (default 1), hardcode `perPage = 15`, wrap items in `LengthAwarePaginator` and pass `toArray()` as `rooms` / `reservations` (Laravel keys: `data`, `current_page`, `per_page`, `last_page`, `next_page_url`, `prev_page_url`, `links`, …).
- `IndexRoomRequest` — validates `status` and `page` (`sometimes|integer|min:1`). No `limit`. No Portuguese messages for `page`.
- `IndexReservationRequest` — validates filters only. Does **not** validate `page` or `limit`. `page=0` is clamped in `ListReservations`.
- `ListRooms` / `ListReservations` — already slice via `listPage($page, $perPage, …)` and return `{items, total, hasAny}`. Application default `perPage` 15 is unused once the controller always passes `limit`.
- `Room/Index.jsx` / `Reservation/Index.jsx` — render `*.data`; nav uses `last_page`, `prev_page_url`, `next_page_url`. Filter visits already reset `page: 1`.
- `Services/rooms.js` / `reservations.js` — `visitIndex(query)` → `router.get`.
- Tests to rewrite (not delete the scenarios): `RoomIndexHttpTest` (`per_page` 15, `next_page_url` with `foo=bar`), `ReservationIndexHttpTest` (`per_page` 15, `prev_page_url` keeps filters), `IndexRoomRequestTest`, `IndexReservationRequestTest`, `Room/Index.test.jsx`, `Reservation/Index.test.jsx`.
- Existing Application unit tests (`ListRoomsTest`, `ListReservationsTest`) stay; they already prove slicing.

## Existing Constraints

- Architecture: no Laravel types in Application; Domain stays pure PHP.
- Auth: both index routes already sit in the `auth` group (guest 302). Do not reopen login.
- Filters, ordering, `hasAny`, empty/filtered-empty UI, row actions stay.
- Do not paginate small catalogs (`filterRooms`, create-form room pickers).
- Do not add a separate JSON REST API. The user JSON is the **Inertia listing prop** shape.
- Do not add Playwright.
- Conventional Commits: `feat`/`test` per module (`room`, `reservation`), then `chore(docs)`.

## Important Decisions

- Large listings = `GET /rooms` and `GET /reservations` only.
- Query params: `page` (default **1**, min 1) and `limit` (default **20**, min 1, max **100**). User example used `limit: 20`.
- Envelope on the existing `rooms` / `reservations` Inertia prop:

```text
{ "data": [ ... ], "page": 1, "limit": 20, "total": 100 }
```

- Drop `LengthAwarePaginator` from both index controllers. No `per_page`, `current_page`, `last_page`, `*_page_url`, or `links` on those props.
- React derives last page as `ceil(total / limit)` and builds Anterior/Próxima query strings from current filters + `page` + `limit`.
- Invalid `page` / `limit` → FormRequest 422 / web redirect with session errors (same as invalid `status`). Portuguese messages.
- Page past the last page → empty `data`, echo requested `page`/`limit`, keep `total`.
- Filter change → `page=1`, keep current `limit`.
- ADR-010 MADR in Portuguese; do not edit ADR-001..009 decision bodies.
- No confirmed lessons in `.specs/lessons.json`.
