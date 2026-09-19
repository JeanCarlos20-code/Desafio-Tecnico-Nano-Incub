# Task Context

## Relevant Documentation

- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF07–RF18, RNF08/RNF09/RNF14. No edit, recurrence, calendar, email, waitlist, or public API.
- `docs/adr/005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md` — owner of occupancy. Table `reservations`: `id` (UUID v7), `room_id` (`foreignUuid` → `rooms.id`), `responsible`, `title`, `starts_at`, `ends_at`, `participants`, `cancelled_at`, `created_at`, `updated_at`. No `deleted_at`. Active = `cancelled_at` null. Overlap: `existing.start < new.end AND existing.end > new.start` (consecutives allowed). Create: MySQL transaction, `SELECT … FOR UPDATE` on the **room** row, then room rules, then active overlap, then insert. Different rooms do not block each other.
- `docs/adr/004-modulo-rooms-ciclo-de-vida-minimo.md` — Reservation only reads `id`, `capacity`, `is_active`, `name`. Rooms stay the owner of room CRUD.
- `docs/architecture.md` / `docs/tree.md` — `app/Modules/Reservation/{Domain,Application,Infra}`. Domain/Application: no `Illuminate`. Controllers thin. FormRequest = HTTP types only. Occupancy rules live in use cases.
- `docs/screens/screen-reservations-list.md` — `GET /reservations`, `GET /reservations/create`, `PATCH /reservations/{reservation}/cancel`. Shared `AppLayout`. Filters `room_id` + `date` (default today). Order `starts_at ASC` then `id`. Status badges `active`/`canceled`. Cancel modal. Empty/loading/error states. No calendar/details icon.
- `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md` — unit = use cases + FormRequest off-route + React; integration = Laravel HTTP + MySQL 8 (locks/concurrency real); e2e = browser only. No Playwright in `package.json`.
- `docs/reviews/review-architecture.md` — do not add DTOs/services without need; do not put occupancy in Rooms.

## Relevant Components

- `app` monolith: Laravel 12, Inertia 2, React 19, Tailwind 4, Eloquent, MySQL 8, PHPUnit + Vitest.
- Existing modules: `User`, `Room`. No `Reservation` module yet.
- Stub only: `GET /reservations` closure → `Reservation/Index` (`<h1>Reservas</h1>` + `AppLayout`). Login/register already redirect here (`reservations.index`).
- Shared UI: `Layouts/AppLayout.jsx` (Reservas/Salas nav), `Components/FlashToast.jsx`.
- Coverage include in `phpunit.xml` is User+Room Application only — add `app/Modules/Reservation/Application`.

## Relevant Code

- Room vertical slice to copy: Domain entity + port, Application use case + `Fake*Repository` unit tests, Eloquent repo, thin invokable controllers, FormRequest + `tests/Unit/Room/*RequestTest.php` (container, no route), Feature HTTP + `RefreshDatabase` + `assertInertia`, `HasUuids` (tests assert UUID version nibble `7`), factory, schema/migration Feature tests, `RoomIlluminateImportTest`.
- `IndexRoomController`: page size 15, `LengthAwarePaginator`, timezone via `config('app.timezone')` (currently `UTC`).
- `StoreRoomController`: `redirect()->route(...)->with('success', ...)`.
- `resources/js/Services/rooms.js`: `form.post` / `form.put` / `form.delete` / `router.get`. Reuse this helper style; cancel uses `form.patch`.
- `Room/Index.jsx` + `Room/Index.test.jsx`: empty/error/loading, confirm dialog (focus `Voltar`, Escape, processing). List screen follows that pattern, not the Room column set (Reservation **does** show ID).
- `AppServiceProvider` binds module ports. `routes/web.php` auth group must replace the reservations closure.
- Laravel query builder: wrap `lockForUpdate()` in `DB::transaction()` (locks release on commit/rollback).

## Existing Constraints

- User asked for “CRUD” + list screen. ADR-005 forbids update/delete; cancel replaces delete. Create is in scope (RF07 + `Nova reserva` → `/reservations/create`) even though there is no reservation-form screen doc — keep a minimal Room-form-like page.
- `responsible` and `title` are reservation text, not user FKs.
- Soft-deleted rooms are not reservable (Eloquent default). Inactive rooms cannot take **new** reservations (RF17) but stay on the list filter when they have history.
- Room deactivation today only flips `is_active` (`has_registered_meetings` is still false; no reservation writes). Do not implement room-deactivation cancellation here. List already shows `cancelled_at` rows as `Cancelada`.
- RF19 combined seeder is out of scope (factory is enough for tests).
- Do not invent recurrence, calendar, email, waitlist, check-in, public API, extra roles, or a `completed` status.
- Existing Feature tests (`LoginHttpTest`, `CreateUserHttpTest`) require `route('reservations.index')` and Inertia `Reservation/Index` — keep both.
- Domain/Application must stay `Illuminate`-free (`ReservationIlluminateImportTest`).
- No Playwright runner; do not bootstrap it.

## Important Decisions

- New module `Reservation` mirroring Rooms. OccupancyRoomCatalog (Reservation Domain port) locks/reads rooms; do not add `lockById` to `RoomRepository`.
- `CreateReservation` owns RF13–RF18 + RNF09: Clock + Transaction + catalog.lockById + overlap + persist. Fake Transaction in unit tests runs the callback immediately.
- HTTP status prop is `canceled` (screen). DB column is `cancelled_at` (ADR).
- List default `date` = today in `config('app.timezone')`. Do not change app timezone. Day window is `[date 00:00, next day 00:00)` in that timezone.
- Filter dropdown: non-deleted rooms that are active **or** have at least one reservation. Create dropdown: active rooms only.
- Pagination 15, order `starts_at ASC, id ASC`, canceled rows remain visible.
- Date filter always has a value (default today) → start/end display `HH:mm`.
- FormRequest: types/required/uuid/date/after/min only. `exists:rooms` stays out so unit tests stay off MySQL. Room existence/active/capacity/overlap are use-case errors mapped with `back()->withErrors`.
- Cancel is idempotent (second PATCH keeps the same `cancelled_at`).
- Participants minimum is 1; maximum is the locked room’s capacity (use case).
- `starts_at` before Clock::now() is rejected; equal-to-now is allowed.
- Duration inclusive 30 minutes … 4 hours (240 minutes).
- E2E: not applicable (no Playwright). Concurrency is an integration test with two real MySQL-backed PHP processes, not a sequential double-call.
