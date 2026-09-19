# Task Context

## Relevant Documentation

- `docs/adr/006-serializar-ciclo-da-sala-com-reservas-novas-e-existentes.md` — accepted contract: same-room `SELECT … FOR UPDATE` + transaction for create, deactivate, and delete; deactivate radios Keep/Cancel future actives; delete cancels every active then soft-deletes; canceled rows leave the reservation list; rooms filter `status=all|active|inactive` in the URL.
- `docs/adr/004-modulo-rooms-ciclo-de-vida-minimo.md` — Rooms owns `rooms` (`is_active`, soft delete). ADR-006 now fills the reservation side-effect gap ADR-004 left open.
- `docs/adr/005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md` — Reservation owns `cancelled_at`, overlap, and create lock. Create already locks `rooms` via `OccupancyRoomCatalog::lockById`.
- `docs/screens/screen-room-form.md` — edit Status select, amber warning, deactivation dialog with radios, atomic save.
- `docs/screens/screen-rooms-list.md` — URL status filter; delete dialog warns when the room has reservations.
- `docs/screens/screen-reservation-create.md` — create fields, participants ≤ selected room capacity, only active rooms.
- `docs/screens/screen-reservations-list.md` — list only `cancelled_at` null; no `Cancelada` badge.
- `docs/architecture.md` / `docs/tree.md` — Domain ← Application ← Infra; no Illuminate in Domain/Application; thin controllers.
- `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md` — unit isolated (fakes, no HTTP/DB); integration = Laravel request + MySQL 8 (locks/concurrency two real processes); e2e = browser only. No Playwright in `package.json`.
- Prior feature `0013` shipped Reservation CRUD and listed canceled rows (superseded).

## Relevant Components

- `app/Modules/Room` — `UpdateRoom` / `DeleteRoom` / `ListRooms` have no lock, transaction, reservation effect, or status filter.
- `app/Modules/Reservation` — `CreateReservation` already transactions + `lockForUpdate`. `ListReservations` / Eloquent `listPage` do **not** exclude `cancelled_at`.
- `resources/js/Pages/Room/*` — deactivate dialog exists but radios are not submitted; Status is buttons, not the documented select; delete dialog never warns.
- `resources/js/Pages/Reservation/Create.jsx` — `datetime-local` + unbounded participants.
- `resources/js/Pages/Reservation/Index.jsx` — still renders `Cancelada` rows.
- Tests: `tests/Feature/Reservation/ReservationConcurrencyHttpTest.php` + `Support/concurrency_create_worker.php` is the two-process pattern to copy.

## Relevant Code

- `UpdateRoom` only `findById` + `update`. `DeleteRoom` only `delete`. `EditRoomController` hardcodes `has_registered_meetings => false`.
- `UpdateRoomRequest`: `is_active` sometimes boolean; no `scheduled_meetings_action`.
- `IndexRoomController` / `ListRooms` / `RoomRepository::listPage` take page only.
- `EloquentRoomRepository::update`/`delete` do not lock. `EloquentOccupancyRoomCatalog::lockById` already `RoomModel::whereKey($id)->lockForUpdate()->first()` (soft-deleted → null).
- `EloquentReservationRepository::listPage` filters day/room only. `hasAny()` counts every row.
- `ReservationRepository` has `create`, `hasActiveOverlap`, `listPage`, `hasAny`, `findById`, `markCanceled` — no by-room cancel/count.
- `InactiveRoom` message: `Não é possível reservar uma sala inativa.` `OccupancyRoomNotFound`: `Sala não encontrada.`
- Room Application is already in `phpunit.xml` coverage. Reuse `Reservation\Application\Transaction` + `Reservation\Domain\Clock` + existing `LaravelTransaction` / `LaravelClock` bindings.
- Fakes: `tests/Unit/Room/FakeRoomRepository.php`, `tests/Unit/Reservation/FakeReservationRepository.php`, `FakeTransaction`, `FakeClock`.

## Existing Constraints

- Application/Domain must stay free of Illuminate. Controllers stay thin. FormRequest covers input only; futures-exist is a use-case rule (DB state).
- Integration concurrency must use two PHP processes + barrier + MySQL 8. Sequential or repository fakes do not prove ADR-006.
- Do not weaken 0013 occupancy tests. Replace the 0013 “list includes canceled” contract with ADR-006 (rows with `cancelled_at` must not appear).
- Soft-deleted rooms stay hidden via Eloquent `SoftDeletes`. Reservation rows are never soft-deleted.
- No Playwright / no e2e runner. Do not bootstrap one.
- Cross-module: Rooms may call Reservation **ports** to set `cancelled_at`; Reservation remains owner of that column. Do not add methods onto `OccupancyRoomCatalog` for deactivate/delete (Room owns the room row lock on those paths).
- Frontend validation is UX only; backend remains authoritative.

## Important Decisions

- **Lock owner:** add `RoomRepository::lockById()` (`lockForUpdate`) for Update/Delete. Create keeps `OccupancyRoomCatalog::lockById`. Same `rooms` row, same MySQL lock.
- **Transaction:** `UpdateRoom` / `DeleteRoom` wrap work in `Reservation\Application\Transaction::run` (no second Transaction type).
- **Future:** `starts_at > Clock::now()`. In-progress (`starts_at <= now < ends_at`) and past actives are **not** canceled on deactivate. Delete cancels **all** actives (`cancelled_at` null), including in-progress and past.
- **HTTP action:** `scheduled_meetings_action` = `keep` | `cancel`. Required by the use case only when deactivating an active room that has future actives; missing → `DeactivationDecisionRequired` (422, count). Already-inactive save does not touch reservations.
- **Edit props:** `future_active_count` (int) replaces the unused `has_registered_meetings`. List row prop `has_reservations` (any reservation, including canceled) drives the delete warning.
- **Rooms filter:** `IndexRoomRequest` `status` in `all|active|inactive`; omit or `all` = both. Changing filter resets `page` to 1. Server-side only.
- **Create screen:** React follows `screen-reservation-create.md` (labels, date + start + end, `Criar reserva`, capacity in the room option, participants max = selected capacity). POST still sends `responsible`, `starts_at`, `ends_at` so ADR-005 / 0013 workers stay stable; the page combines date + times in `config('app.timezone')`.
- **List empty:** `hasAny` becomes “any row with `cancelled_at` null”. All-canceled looks like the global empty state.
- **Flash:** keep → `Sala desativada. As reuniões programadas foram mantidas.`; cancel futures → `Sala desativada. As reuniões futuras foram canceladas.`; other updates → `Sala atualizada com sucesso.`; delete → `Sala excluída com sucesso.`
- **E2E:** not applicable (no Playwright).
