# Specification

## Context

Original request: "quero que você adicione em listagens grandes paginação ?limit ?page como query params faça uma adr da decisão e faça de maneira simples, seria { \"data\": [ ... ], \"page\": 1, \"limit\": 20, \"total\": 100 }"

`GET /rooms` and `GET /reservations` already paginate with `?page` and a hardcoded page size of 15. The Inertia listing prop is Laravel `LengthAwarePaginator::toArray()` (`per_page`, `current_page`, `last_page`, `next_page_url`, `prev_page_url`, `links`). The user wants a small envelope and an explicit `limit` query param, recorded in an ADR.

## Problem

Large admin tables load a Laravel paginator payload and ignore page size. Clients cannot choose `limit`, and the contract is heavier than `{ data, page, limit, total }`. There is no ADR for that choice.

## Problem Statement

Authenticated index listings for rooms and reservations SHALL accept `page` and `limit` query params and SHALL return the listing Inertia prop as `{ data, page, limit, total }`. The system SHALL record that contract in ADR-010.

## Goal

- `GET /rooms` and `GET /reservations` read `?page` and `?limit`.
- Default `page=1`, default `limit=20`, `limit` max 100.
- Listing props use only `data`, `page`, `limit`, `total`.
- React Anterior/Próxima links are built from that envelope plus current filters.
- ADR-010 (MADR, Portuguese) records the simple envelope. Screen docs mention `page` / `limit`.
- Unit + Feature + Vitest. No Playwright. No Application use-case rewrite.

## User Stories

### P1: Simple page and limit on large listings ⭐ MVP

**User Story**: As an administrator, I want rooms and reservations lists to accept `?page` and `?limit` and return `{ data, page, limit, total }` so that large tables stay small and the contract stays obvious.

**Why P1**: This is the entire requested slice (query params, envelope, ADR).

**Covered ACs**: AC-001 through AC-010

**Acceptance Criteria**:

1. WHEN an authenticated administrator opens `GET /rooms` without `page` or `limit` THEN the system SHALL render Inertia `Room/Index` with `rooms` equal to `{ data, page: 1, limit: 20, total }` where `data` has at most 20 room rows and `total` is the filtered room count.
2. WHEN an authenticated administrator opens `GET /rooms?page=2&limit=10` THEN the system SHALL set `rooms.page` to 2, `rooms.limit` to 10, `rooms.data` to the second slice of 10 (or fewer) filtered rooms, and `rooms.total` to the filtered count.
3. WHEN an authenticated administrator opens `GET /reservations` without `page` or `limit` THEN the system SHALL render Inertia `Reservation/Index` with `reservations` equal to `{ data, page: 1, limit: 20, total }` where `data` has at most 20 reservation rows and `total` is the filtered reservation count.
4. WHEN an authenticated administrator opens `GET /reservations` with existing filters plus `page` and `limit` THEN the system SHALL apply those filters first and SHALL page the filtered result with the requested `page` and `limit` in the same envelope.
5. IF `page` is present and is not an integer ≥ 1 THEN `IndexRoomRequest` and `IndexReservationRequest` SHALL fail `page` with `Informe uma página válida.` and SHALL not call the list use case.
6. IF `limit` is present and is not an integer between 1 and 100 inclusive THEN `IndexRoomRequest` and `IndexReservationRequest` SHALL fail `limit` with `Informe um limite válido.` and SHALL not call the list use case.
7. WHEN `total` is greater than `limit` THEN `Room/Index` and `Reservation/Index` SHALL show the pagination nav and SHALL build `Anterior` / `Próxima` hrefs from `page`, `limit`, `total`, and the current filters, without reading `prev_page_url` or `next_page_url`.
8. WHEN a list filter changes THEN the page SHALL request `page=1` and SHALL keep the current `limit`.
9. The `rooms` and `reservations` listing props SHALL contain only the keys `data`, `page`, `limit`, and `total` (no `per_page`, `current_page`, `last_page`, `links`, or `*_page_url`).
10. The system SHALL record the simple envelope and `page`/`limit` bounds in ADR-010 (Portuguese MADR) and in the rooms and reservations list screen docs.

**Independent Test**: Seed more than 20 rooms; GET `/rooms` asserts `page=1`, `limit=20`, `data` length 20, `total` > 20, and missing Laravel paginator keys. GET `/rooms?page=2&limit=10` returns the second slice. Invalid `limit=0` redirects with `limit` errors. Room Index with `{ data, page: 1, limit: 2, total: 3 }` shows `Próxima` to `/rooms?page=2&limit=2`. Repeat the HTTP slice for reservations with a filter. ADR-010 exists.

## Acceptance Criteria

Traceable copies (same outcomes):

- **AC-001** WHEN an authenticated administrator opens `GET /rooms` without `page` or `limit` THEN the system SHALL return `rooms` as `{ data, page: 1, limit: 20, total }` with at most 20 rows.
- **AC-002** WHEN an authenticated administrator opens `GET /rooms?page=2&limit=10` THEN the system SHALL return the second slice of 10 and echo `page=2` and `limit=10`.
- **AC-003** WHEN an authenticated administrator opens `GET /reservations` without `page` or `limit` THEN the system SHALL return `reservations` as `{ data, page: 1, limit: 20, total }` with at most 20 rows.
- **AC-004** WHEN `GET /reservations` includes filters plus `page` and `limit` THEN the system SHALL page the filtered set and SHALL keep the filter props unchanged.
- **AC-005** IF `page` is present and is not an integer ≥ 1 THEN both index FormRequests SHALL fail `page` with `Informe uma página válida.` and SHALL not list.
- **AC-006** IF `limit` is present and is not an integer between 1 and 100 inclusive THEN both index FormRequests SHALL fail `limit` with `Informe um limite válido.` and SHALL not list.
- **AC-007** WHEN `total` is greater than `limit` THEN both Index pages SHALL show Anterior/Próxima built from `page`, `limit`, `total`, and current filters.
- **AC-008** WHEN a list filter changes THEN the page SHALL send `page=1` and SHALL keep the current `limit`.
- **AC-009** The listing props SHALL contain only `data`, `page`, `limit`, and `total`.
- **AC-010** The system SHALL write ADR-010 and update the two list screen docs for `page` / `limit` and the envelope.

## Edge Cases

- Omitted `page` / `limit` → defaults 1 and 20.
- `page` or `limit` as numeric strings (`"2"`, `"20"`) → accepted by Laravel `integer`.
- `page=0`, `page=-1`, `page=abc` → `page` validation error.
- `limit=0`, `limit=101`, `limit=abc` → `limit` validation error.
- `page` greater than the last page → empty `data`, echo the requested `page` and `limit`, keep `total`.
- `total=0` → no pagination nav; existing empty / filtered-empty copy stays.
- `total=limit` → one page; no Anterior/Próxima.
- Unknown extra query keys (`foo=bar`) are not copied onto listing props; React links only include known filters plus `page` and `limit`.
- Guest GET index remains 302 to login (existing `auth` middleware; not reopened).

## Out of Scope

| Feature | Reason |
| --- | --- |
| Separate JSON REST API / `Accept: application/json` resource | The app is Inertia; the JSON is the listing prop |
| Paginating `filterRooms` or create-form room catalogs | Small lists, not “listagens grandes” |
| Changing list filters, sort, `hasAny`, or row actions | Already shipped |
| Rewriting `ListRooms` / `ListReservations` | They already slice by page/perPage |
| Cursor / infinite scroll / numbered page buttons | User asked for a simple envelope |
| Editing ADR-001..009 decision bodies | New ADR-010 only |
| Playwright / new E2E project | Not in `package.json` |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| Which lists | Only `GET /rooms` and `GET /reservations` | The two large Inertia tables | n |
| Default `limit` | 20 | User envelope example | n |
| Max `limit` | 100 | Prevents unbounded `SELECT` | n |
| Default `page` | 1 | Existing product + user example | n |
| Transport | Inertia props, not a new JSON route | Architecture is Inertia | n |
| Paginator | Remove `LengthAwarePaginator` from both index controllers | User asked for a simple envelope | n |
| Invalid params | FormRequest fail + web session errors | Matches invalid `status` | n |
| Page past last | Empty `data`, echo `page`/`limit` | Use cases already slice that way | n |
| Filter change | `page=1`, keep `limit` | Screen docs already reset page | n |
| Auth / guest | Unchanged `auth` middleware | Not part of the request | n |
| Application layer | No use-case change | `perPage` already exists | n |
| ADR language / number | Portuguese MADR, `010` | Matches `docs/adr` and create-adr | n |
| Remaining implicit dimensions (concurrency, observability, rate limits, external deps) | N/A for this scope | Read-only listing contract | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Simple `{ data, page, limit, total }` on the existing Inertia listing props, `?page` + `?limit`** — matches the user example. Trade-off: React must build next/prev URLs; Feature tests that assert `next_page_url` must move that assertion to Vitest.
2. **Keep `LengthAwarePaginator::toArray()` and only add `limit`** — smaller React change. Trade-off: the payload stays Laravel-shaped; user asked for the four-key envelope and an ADR.
3. **New JSON API beside Inertia** — extra route surface. Trade-off: the panel has no JSON clients; architecture forbids a second app.

## Selected Approach

Approach 1. Validate `page` and `limit` on both index FormRequests (defaults 1 and 20, `limit` 1..100). Controllers pass `limit` into the existing list use cases and return the four-key envelope. Index pages derive Anterior/Próxima from `page` / `limit` / `total` plus current filters. ADR-010 records the decision. No Playwright.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| --- | --- | --- | --- |
| PAGE-01 | P1: Rooms default envelope | Execute | Implemented |
| PAGE-02 | P1: Rooms page+limit slice | Execute | Implemented |
| PAGE-03 | P1: Reservations default envelope | Execute | Implemented |
| PAGE-04 | P1: Reservations filtered slice | Execute | Implemented |
| PAGE-05 | P1: Invalid page | Execute | Implemented |
| PAGE-06 | P1: Invalid limit | Execute | Implemented |
| PAGE-07 | P1: React prev/next from envelope | Execute | Implemented |
| PAGE-08 | P1: Filter keeps limit, resets page | Execute | Implemented |
| PAGE-09 | P1: Envelope keys only | Execute | Implemented |
| PAGE-10 | P1: ADR-010 and screen docs | Execute | Implemented |

**ID format:** `PAGE-NN` maps 1:1 to AC-00N.

**Coverage:** 10 total, 10 mapped to T1–T8, 0 unmapped.

## Success Criteria

- [x] Both index routes honor `?page` and `?limit` with defaults 1 and 20.
- [x] Listing Inertia props are only `{ data, page, limit, total }`.
- [x] Invalid page/limit never hit the list use case.
- [x] Anterior/Próxima work from the envelope and keep filters.
- [x] ADR-010 and both list screen docs describe the contract.
- [ ] Unit, Feature, and Vitest gates pass. No Playwright.
