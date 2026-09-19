# Specification

## Context

Administrators already authenticate and land on a stub `GET /reservations`. Rooms exist. Occupancy (RF07–RF18, RNF09) has no owner. ADR-005 and `docs/screens/screen-reservations-list.md` define the contract and the list UI. This feature adds module `Reservation` and replaces the stub list. Create is included because RF07 and `Nova reserva` require `GET /reservations/create`. Update/delete stay out.

## Problem

Without a Reservation module, administrators cannot book, list, filter, or cancel rooms, and concurrent creates can overlap.

## Problem Statement

The panel has rooms and auth but no occupancy owner. Administrators need to create, list/filter, and cancel reservations under ADR-005 rules, and use the documented reservations list screen.

## Goal

- Ship `app/Modules/Reservation` with UUID v7 rows, occupancy rules, and cancel-via-`cancelled_at`.
- Replace the list stub with the documented screen (filters, badges, cancel modal).
- Add a minimal create page for RF07.
- Protect rules with unit + MySQL integration tests, including real same-room concurrency.

## User Stories

### P1: Create a reservation

**User Story**: As an administrator, I want to book an active room with responsible, title, start, end, and participants so that the slot is reserved without overlapping another active booking.

**Why P1**: RF07 and RF13–RF18 / RNF09 are the core of the module.

**Covered ACs**: AC-001, AC-002, AC-003, AC-004, AC-005, AC-006, AC-007, AC-008, AC-009, AC-010, AC-018, AC-021, AC-022

**Independent Test**: Authenticated POST of a valid 30-minute future slot persists one UUID v7 row; overlap/inactive/capacity/past/duration cases persist nothing.

### P1: List and filter reservations

**User Story**: As an administrator, I want today’s reservations (filterable by room and day) in chronological order so that I can see active and canceled history.

**Why P1**: RF08–RF10 and the list screen.

**Covered ACs**: AC-011, AC-012, AC-013, AC-014, AC-019, AC-020, AC-023

**Independent Test**: Seed two rooms and mixed-day rows; `GET /reservations?date=&room_id=` returns only the matching day/room, canceled included, `starts_at ASC`.

### P1: Cancel a reservation

**User Story**: As an administrator, I want to confirm cancellation of an active reservation so that the interval becomes free and the row stays in history.

**Why P1**: RF11–RF12.

**Covered ACs**: AC-015, AC-016, AC-017, AC-018, AC-020

**Independent Test**: PATCH cancel sets `cancelled_at`; a new booking in the same interval succeeds; a second cancel does not change the timestamp.

## Acceptance Criteria

1. WHEN an authenticated administrator submits a valid create payload THEN the system SHALL persist one `reservations` row with UUID v7 `id`, `room_id`, trimmed `responsible`, trimmed `title`, `starts_at`, `ends_at`, `participants`, `cancelled_at` null, and timestamps, then redirect to `reservations.index` with flash `Reserva criada com sucesso.`
2. IF create input is missing or malformed THEN the system SHALL reject it with these messages and persist nothing: `room_id` required `Informe a sala.`; `room_id` not UUID `Selecione uma sala válida.`; `responsible` required `Informe o responsável.`; `title` required `Informe o título da reserva.`; `starts_at` required `Informe o início.`; `starts_at` not a date `Informe um início válido.`; `ends_at` required `Informe o término.`; `ends_at` not a date `Informe um término válido.`; `ends_at` not after `starts_at` `O término deve ser posterior ao início.`; `participants` required `Informe o número de participantes.`; `participants` not integer `Os participantes devem ser um número inteiro.`; `participants` < 1 `Informe pelo menos 1 participante.`
3. IF duration is below 30 minutes or above 240 minutes THEN the system SHALL reject create with `A reserva deve durar entre 30 minutos e 4 horas.` and persist nothing.
4. IF `starts_at` is before Clock::now() THEN the system SHALL reject create with `O horário inicial não pode estar no passado.` and persist nothing.
5. IF the room is missing or soft-deleted THEN the system SHALL reject create with `Sala não encontrada.` and persist nothing.
6. IF the locked room is inactive THEN the system SHALL reject create with `Não é possível reservar uma sala inativa.` and persist nothing.
7. IF `participants` is greater than the locked room capacity THEN the system SHALL reject create with `O número de participantes excede a capacidade da sala.` and persist nothing.
8. IF an active reservation on the same room satisfies `existing.starts_at < new.ends_at AND existing.ends_at > new.starts_at` THEN the system SHALL reject create with `Já existe uma reserva ativa neste horário para a sala.` and persist nothing.
9. WHEN a new reservation starts at exactly another active reservation’s `ends_at` on the same room THEN the system SHALL persist the new row.
10. WHEN two overlapping creates for the same room run concurrently THEN the system SHALL lock that room with `SELECT … FOR UPDATE` inside a transaction so that exactly one overlapping active row is stored and the other request receives the overlap error.
11. WHEN an authenticated administrator opens `GET /reservations` THEN the system SHALL render Inertia `Reservation/Index` with page size 15, filters defaulting `date` to today in `config('app.timezone')`, and rows ordered by `starts_at ASC`, then `id ASC`.
12. WHEN `room_id` and/or `date` query params are valid THEN the system SHALL return only reservations whose `starts_at` falls in that timezone calendar day and, if `room_id` is present, that room.
13. The system SHALL keep canceled reservations in the list in chronological order and SHALL expose status `canceled` with visible label `Cancelada` (active → `active` / `Ativa`).
14. WHEN zero reservations exist THEN the system SHALL show `Nenhuma reserva cadastrada.` plus `Nova reserva`; WHEN some exist but none match filters THEN the system SHALL show `Nenhuma reserva encontrada para os filtros selecionados.` and `Limpar filtros`.
15. WHEN the administrator confirms cancel on an active reservation THEN the system SHALL set `cancelled_at`, keep the row, exclude it from later overlap checks, and flash `Reserva cancelada com sucesso. O horário está disponível novamente.`
16. WHEN cancel is repeated on an already canceled reservation THEN the system SHALL leave `cancelled_at` unchanged and SHALL not insert another row.
17. WHEN a guest calls `/reservations`, `/reservations/create`, `POST /reservations`, or `PATCH /reservations/{id}/cancel` THEN the system SHALL redirect to `/login` and persist no reservation.
18. WHEN the administrator selects `Nova reserva` THEN the system SHALL go to `GET /reservations/create`; that page SHALL offer only active rooms and fields sala, responsável, título, início, fim, participantes.
19. WHILE the list shows an active reservation the system SHALL offer only `Cancelar` (no calendar or details icon); WHILE it shows a canceled reservation the system SHALL show a dash instead of cancel.
20. WHEN `Cancelar` is pressed THEN the system SHALL open a confirm dialog naming room, date, start, and end, move initial focus to `Voltar`, and SHALL send `PATCH /reservations/{id}/cancel` only after `Cancelar reserva`.
21. IF create or cancel fails unexpectedly THEN the system SHALL show `Não foi possível salvar a reserva. Tente novamente.` or `Não foi possível cancelar a reserva. Tente novamente.` and SHALL leave persisted state unchanged.
22. The system SHALL store filters in the URL, reset to page 1 on filter change, include inactive rooms with history in the room filter, and format start/end as `HH:mm` while a day is selected.

## Edge Cases

- IF duration is exactly 30 minutes or exactly 4 hours THEN the system SHALL accept the create.
- IF `starts_at` equals Clock::now() THEN the system SHALL accept the create.
- IF the only overlapping row is canceled THEN the system SHALL accept the create.
- IF the two concurrent creates use different `room_id` values THEN the system SHALL persist both.
- IF `date` or `room_id` query values are malformed THEN the system SHALL return FormRequest errors and SHALL not change rows.
- IF the reservation id on cancel does not exist THEN the system SHALL respond 404 and persist nothing.
- IF list load fails THEN the system SHALL show `Não foi possível carregar as reservas.` with retry.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Edit reservation | ADR-005 / ADR-001 |
| Hard delete / soft delete on reservations | Cancel only |
| Recurrence, calendar widget, waitlist, email, check-in | ADR-001 |
| Combined RF19 seeder | Separate delivery; factory covers tests |
| Cancel-on-room-deactivate / delete-room cascade | Room module still does not write reservations |
| Public API, extra roles, `completed` status | Not in RF/ADR |
| Changing `config('app.timezone')` | Use the configured timezone as-is |
| Bootstrapping Playwright | No runner in the repo |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Create UI without a screen doc | Minimal Room-form-like page at `/reservations/create` | RF07 + list `Nova reserva` | n |
| List includes canceled rows | Always show them in chronological order | Screen + RF12 history | n |
| Default day filter | Today in `config('app.timezone')` (UTC today) | Screen default; do not retune timezone | n |
| Room deactivation cancel | Not in this task | Rooms only flip `is_active` today | n |
| Participants minimum | 1 | Positive count; RF16 is the max | n |
| Past-start bound | Reject only `starts_at < now` | RF18 says past, not “now” | n |
| FormRequest `exists:rooms` | Omitted; use case checks the room | Unit validation stays off MySQL | n |
| E2E | Not applicable | No Playwright project | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Own Reservation module** (create + list/filter + cancel) following Rooms layering and ADR-005 locking.
2. Put occupancy inside Rooms — rejected by ADR-005.
3. Full CRUD including edit/delete — rejected by ADR-005.
4. List-only now, create later — rejected; user asked for the module and RF07 is unused without create.

## Selected Approach

Approach 1. Copy the Rooms slice: Domain entity/ports, Application use cases (Create with Clock + Transaction + OccupancyRoomCatalog lock, List, Cancel), Eloquent + `lockForUpdate` on `rooms`, thin HTTP, Inertia pages, `Services/reservations.js`. Replace the index closure. Add `Reservation/Application` to PHPUnit coverage. Frontend unit tests for list + create; Feature tests for HTTP, schema, and two-process same-room concurrency.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| RSV-01 | P1: Create | Tasks | Done |
| RSV-02 | P1: Create validation | Tasks | Done |
| RSV-03 | P1: Occupancy rules | Tasks | Done |
| RSV-04 | P1: Concurrency | Tasks | Done |
| RSV-05 | P1: List and filter | Tasks | Done |
| RSV-06 | P1: Cancel | Tasks | Done |
| RSV-07 | P1: Auth boundary | Tasks | Done |
| RSV-08 | P1: List + create UI | Tasks | Done |

**Coverage:** 8 total, 8 mapped to tasks, 0 unmapped
