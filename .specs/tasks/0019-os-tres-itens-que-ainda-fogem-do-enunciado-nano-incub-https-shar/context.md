# Task Context

## Relevant Documentation

- Challenge (Nano Incub): rooms list = ID, Nome, Capacidade, Situação, Data de criação; reservations list = ID, Sala, Responsável, Título, Início, Fim, Participantes, Situação. Section 05 README must cover setup (already present), technical decisions + why (including overlap trade-off), what was left out + what more time would add, and an AI-use declaration. RF19: seeder creates at least one administrator plus some rooms and reservations.
- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF03/RF08 list columns; RF19 seeder; RNF09 concurrency; RNF12 README-only setup. Cuts: public signup, reservation edit, calendar, email, extra roles. Do not expand that catalog.
- `docs/adr/002-usuario-minimo-uuidv7-argon2-auth-laravel.md` — `users.id` stays UUID v7 + `HasUuids`. Do not rewrite the decision body. Status already notes ADR-007 superseded only the “native auth without kit” slice.
- `docs/adr/004-modulo-rooms-ciclo-de-vida-minimo.md` — rooms columns, soft delete, `is_active` as situação. Identity slice today is UUID v7 / `HasUuids` / no bigint. **Do not rewrite the decision body.** Status/supersede only for identity.
- `docs/adr/005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md` — reservation columns, `cancelled_at`, no `deleted_at`, `responsible`/`title` are text. Identity slice today is UUID v7 + `foreignUuid`. **Do not rewrite the decision body.** Status/supersede only for identity.
- `docs/adr/006-serializar-ciclo-da-sala-com-reservas-novas-e-existentes.md` — overlap + create/inactivate/delete share `SELECT … FOR UPDATE` on the room row. App rules RF13–RF18 stay in Application. Do not add a unique index in this task.
- `docs/screens/screen-rooms-list.md` — table currently hides `id` and uses column `Status`. Filter label `Status` stays. Update table to show persisted `id` and column `Situação`.
- `docs/screens/screen-reservations-list.md` — already requires column `ID`; UI/tests hide it. Header today is `Status`; align table header to `Situação`.
- `docs/test/unit.md` — FormRequest (no route), React pages (no Laravel/MySQL). Exhaustive `room_id` type matrix lives here.
- `docs/test/integration.md` — Laravel request + MySQL 8: schema, migrate+seed, HTTP that uses room/reservation ids. Do not repeat the full FormRequest matrix.
- `docs/test/e2e.md` — browser + React + Laravel + MySQL. No Playwright project/config/dependency in `package.json`. Do not bootstrap Playwright.
- `docs/architecture.md` / `docs/tree.md` — Domain stays string ids via Infra cast; FormRequests validate HTTP types; Inertia pages consume list props including `id`.
- create-adr (MADR, Portuguese, date 2026-09-20): new ADR-008; ADRs are immutable except Status/supersede links.

## Relevant Components

- `app` monolith: Laravel 12 + Inertia 2 + React + Eloquent + MySQL 8.
- Modules: `Room`, `Reservation`, `User` (user identity unchanged).
- `database/migrations` create tables + demo catalog; `database/seeders` (`DatabaseSeeder` calls Room/Reservation only today).
- Screens: `resources/js/Pages/Room/Index.jsx`, `resources/js/Pages/Reservation/Index.jsx`.
- README at repo root (Portuguese setup already exists; section 05 narrative is missing).

## Relevant Code

- `database/migrations/2026_09_18_120000_create_rooms_table.php` — `$table->uuid('id')->primary()`.
- `database/migrations/2026_09_18_180000_create_reservations_table.php` — uuid PK + `foreignUuid('room_id')`.
- `database/migrations/2026_09_20_000000_insert_demo_rooms_and_reservations.php` — `Str::uuid7()` for room and reservation ids. `down()` already keys off names/titles.
- `database/migrations/2026_09_19_000000_insert_default_administrators.php` — Gertrudes `teste@mail.com`, Marcelo `teste2@mail.com`, Emerson `teste3@mail.com`, `Senha123` Argon, UUID v7. Leave this migration as-is.
- Models `Room` and `Reservation` use `HasUuids`. Factories do not set `id`. `User` keeps `HasUuids`.
- Domain `Room`, `Reservation`, `OccupancyRoom` type `id` / `roomId` as `string`. Repositories already cast `(string) $model->getKey()`. Use-case unit tests use opaque string ids (some look like UUIDs). **Keep Domain as opaque string**; do not rewrite Application unit fakes.
- `StoreReservationRequest` / `IndexReservationRequest`: `room_id` rule `uuid`. Unit tests use `'not-a-uuid'` and a UUID valid payload.
- `IndexRoomController::toListItem` / `IndexReservationController::toListItem` already send `id` in Inertia props. Lists use `id` as React key only.
- `Room/Index.jsx` headers: Nome, Capacidade, Status, Criada em, Ações. Vitest: “does not display the room id”; fixtures `id-1` / `id-2`.
- `Reservation/Index.jsx` headers omit ID; Status column. Vitest: `columnheader ID` absent; fixtures `res-1` / `res-2`.
- `DatabaseSeeder` does not call a `UserSeeder`. `UsersMigrationTest::test_database_seeder_does_not_insert_additional_administrators` already asserts count 3 and no `test@example.com` after `$this->seed()`.
- Feature tests that assert UUID v7 nibble `7`: `RoomSchemaTest`, `RoomStoreHttpTest`, `ReservationSchemaTest`, `ReservationStoreHttpTest`, `DemoCatalogMigrationTest`. Hardcoded UUID route ids: `RoomDestroyHttpTest`, `RoomGuestHttpTest`, `ReservationGuestHttpTest`, `ReservationCancelHttpTest`, `ReservationSchemaTest` unknown FK.
- `RoomSchemaTest` default-`is_active` insert currently supplies a UUID `id`; after `$table->id()` omit `id`.
- Eloquent `hashed` cast: `UserSeeder` must pass plaintext `Senha123` (not `Hash::make`) so Argon is applied once. `firstOrCreate` by email is idempotent with the admin migration.

## Existing Constraints

- No production database: edit the create migrations; do not add a convert-uuid-to-bigint migration.
- ADR-001 / ADR-002 decision text stays. Users remain UUID v7.
- ADR-004 / ADR-005: Status/supersede of the identity slice only. Soft delete on rooms, `cancelled_at` on reservations, business columns unchanged.
- Overlap strategy unchanged: Application RF13–RF18 + `lockForUpdate` on the room (ADR-006). No unique index.
- No `CONTRIBUTING.md`. No secrets in README. No `test@example.com`. After migrate+seed: exactly 3 users.
- PHP coverage gate is Application only (`phpunit.xml`). Frontend coverage via `npm run test:coverage`.
- `harness/stack.yml` lists `npx playwright test` but it is not runnable.

## Important Decisions

- New ADR-008 (MADR, Portuguese, 2026-09-20) supersedes UUID identity for `rooms` and `reservations` only. `$table->id()` / `foreignId('room_id')`. Users untouched.
- Domain identifiers stay `string` (decimal of the bigint). Application unit tests stay as-is except FormRequests.
- List table header `Status` → `Situação` (challenge column name). Room filter label and room form `Status` stay.
- `UserSeeder` `firstOrCreate` the three known admins; `DatabaseSeeder` calls it first. Seed may create them if migrate did not; must not add a fourth user.
- README adds the section 05 narrative in Portuguese: why app rules + room lock (not app-only, not unique-index-only); ADR-001 leftovers; Cursor agents used to plan/implement/test; author owns the code.
- E2E not applicable (no Playwright). Do not bootstrap it.
