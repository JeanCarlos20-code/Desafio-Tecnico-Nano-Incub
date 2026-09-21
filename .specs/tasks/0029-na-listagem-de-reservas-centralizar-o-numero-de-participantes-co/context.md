# Task Context

## Relevant Documentation

- `docs/screens/screen-reservations-list.md` — Reservations table: `Participantes` is a positive integer with no alignment rule. `Situação` is derived from `cancelledAt` only (`active`/`Ativa`, `cancelled`/`Cancelada`). The screen currently forbids a completed status unless it is explicitly modeled. Status filter stays `all`/`active`/`cancelled` (default Ativas; omit `status` when Ativas). Actions (`Editar`/`Cancelar`) show only while row `status` is `active`.
- `docs/screens/screen-rooms-list.md` — `Capacidade` header and cells share `text-center` (plus `w-0` / `xl:w-[16%]`). Status filter documents default `all` (`Todas`); omitted `status` currently means active+inactive. Visible room badges stay `Ativa`/`Inativa`.
- `docs/test/unit.md` — Isolated use cases, domain rules, FormRequest off-route, React components. No real HTTP/DB.
- `docs/test/integration.md` — Laravel request + FormRequest on the route + controller + use case + Eloquent + MySQL 8. Do not repeat the exhaustive UI matrix.
- `docs/test/e2e.md` — Real browser only for complete flows. No Playwright project in `package.json`.
- `docs/architecture.md` — Domain stays pure PHP; controllers map HTTP/Inertia. Do not add a mapper layer without need.
- `docs/adr/005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md` — Persisted state is `cancelled_at` null (occupies) vs set (free). No `completed_at` column.
- Task 0028 — Added the reservations Status filter and forbade modeling `completed`. Task 0012 — Centered rooms `Capacidade`. This task supersedes those two “do not change” notes for rooms default and passed display.

## Relevant Components

- `app` — Reservation list Inertia page + `IndexReservationController`. Room list Inertia page + `IndexRoomController` / `ListRooms` / `RoomRepository`.

## Relevant Code

- `resources/js/Pages/Reservation/Index.jsx` — `Participantes` `th`/`td` are `px-4 py-3` without `text-center` (rooms `Capacidade` already has it). `StatusBadge` greens when `status === 'active'`. `RowActions` hides when `status !== 'active'`.
- `app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php` — `toListItem` maps `cancelledAt !== null` → `cancelled`/`Cancelada`, else `active`/`Ativa`. No `Clock`. Frozen Feature now is `2026-09-21 12:00`; several fixtures end at `09:30`/`10:30` and still assert `Ativa`.
- `app/Modules/Reservation/Domain/Entities/Reservation.php` — Data holder (`endsAt`, `cancelledAt`). No list-status behavior.
- `app/Modules/Reservation/Domain/Clock.php` + `LaravelClock` — Bound in `AppServiceProvider`; Feature `travelTo` flows through `Date::now()`.
- `app/Modules/Room/Infra/Http/Controllers/IndexRoomController.php` — `$status = $request->validated('status') ?? 'all'`.
- `ListRooms::execute(..., string $status = 'all')`, `RoomRepository::listPage(..., string $status = 'all')`, Eloquent + `FakeRoomRepository` same default.
- `resources/js/Pages/Room/Index.jsx` — `filters = { status: 'all' }`; `statusQuery`/`listingHref` omit `status` when `all` (reservations omit when `active`).
- Tests to retarget: `ReservationIndexHttpTest` Ativa assertions on ended rows; `RoomIndexHttpTest` omitted GET expecting `filters.status` `all` and inactive rows; `ListRoomsTest` `execute(0, 2)` expecting listed status `all`; `Room/Index.test.jsx` pagination href that omits `status=all`.

## Existing Constraints

- Do not persist a new reservation status column. Occupancy and cancel PATCH stay on `cancelled_at`.
- Do not add a `passed` / `Passadas` listing filter value. Keep `all`/`active`/`cancelled`.
- Do not add Playwright.
- Room badge copy stays `Ativa`/`Inativa` (feminine). User “Ativo” means query `active`, not a rename.
- Domain must not import Laravel. Portuguese labels stay in the controller.

## Important Decisions

- Center `Participantes` like rooms `Capacidade` (`text-center` on header and cells). Do not copy rooms `w-0` / `xl:w-[16%]` onto the reservations table.
- Rooms omitted `status` becomes `active` (Ativas), mirroring reservations. URL omits `status` when Ativas and includes `status=all` for Todas.
- List item status is derived, not stored: cancelled wins; else `endsAt < now` → `passed`/`Passada`; else `active`/`Ativa`. Compare instants (`Clock::now()`), not formatted `H:i`.
- `status=active` filter still means `cancelled_at` null (past non-cancelled rows remain on Ativas, labeled Passada).
- Passada uses the existing non-active slate badge. `RowActions` already hides when `status !== 'active'`.
- `ends_at == now` stays Ativa (`<`, not `<=`). An in-progress meeting (`startsAt < now < endsAt`) stays Ativa.
- Extract `Reservation::listStatus(DateTimeImmutable $now)` in Domain; controller maps keys to Portuguese labels and injects `Clock`.
