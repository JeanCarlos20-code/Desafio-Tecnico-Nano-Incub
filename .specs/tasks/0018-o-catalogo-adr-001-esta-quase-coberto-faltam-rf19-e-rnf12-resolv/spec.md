# Specification

## Context

ADR-001 still has three holes: RF19 (`DatabaseSeeder::run` is empty), RNF12 (README still says `db:seed` is optional), and RNF07 (login is a custom `LoginController` + `AuthenticateUser`, not Laravel Breeze). Task 0016 already inserts Gertrudes, Marcelo, and Emerson on migrate and forbids extra admins / `test@example.com`. Human feedback on the previous 0018 plan: the documented RNF07 deviation is not acceptable; install Breeze (or an official kit), adapt it to the ADR-002 user, and record the swap in a **new** ADR.

Pedido do usuário (original): o catálogo ADR-001 está quase coberto; faltam RF19 e RNF12. Resolver os requisitos faltantes.

Feedback humano: RNF07 também deve ser corrigido. Autorizado instalar Laravel Breeze e documentar a troca num ADR novo.

## Problem

A fresh migrate already has administrators. Human repair asks that the same migrate also persist the demonstration rooms and reservations (same catalog as RF19). Seeders stay as an idempotent guarantee. Login already uses Breeze. The README must say migrate creates admins + demo catalog, and `db:seed` does not duplicate.

## Problem Statement

The panel SHALL authenticate administrators through Laravel Breeze login code (not a parallel custom use case), SHALL persist a demonstration room/reservation catalog on `php artisan migrate` (and again on `php artisan db:seed` without duplicating rows or inserting a fourth user), and SHALL document that path in the Portuguese README.

## Goal

- `laravel/breeze` SHALL be a Composer dependency. POST `/login` SHALL run Breeze `LoginRequest::authenticate()` (`Auth::attempt`) via a Breeze `AuthenticatedSessionController` adapted to this app.
- GET `/` and GET `/login` SHALL still render Inertia `User/Login`. Success SHALL regenerate the session and redirect to `/reservations`. Register, password reset, and email verification SHALL stay absent.
- After migrate, MySQL SHALL contain the three default administrators, the three demonstration rooms, and the three demonstration reservations that satisfy RF13–RF18.
- Seed after migrate SHALL keep that same 3+3 catalog and SHALL NOT insert `test@example.com` or a fourth administrator.
- README SHALL document that `php artisan migrate` already creates administrators plus demonstration rooms and reservations, and that `php artisan db:seed` remains the RF19 step that guarantees the same data without duplicating. Docker, install, `key:generate`, and `composer run dev` stay.
- ADR-001 SHALL remain unedited. ADR-007 SHALL record the Breeze swap. ADR-002’s user contract SHALL remain. No SQL dump.

## User Stories

### P1: Authenticate with Laravel Breeze ⭐ MVP

**User Story**: As an evaluator, I want administrator login to come from Laravel Breeze so that RNF07 is met without abandoning the ADR-002 user.

**Why P1**: Human gate rejected the documented custom-login deviation.

**Acceptance Criteria**:

1. WHEN Execute finishes THEN `composer.json` SHALL list `laravel/breeze` as a require-dev dependency.
2. WHEN a guest submits a valid POST `/login` THEN the Breeze-adapted `LoginRequest::authenticate()` SHALL call `Auth::attempt` with email and password only (remember-me false), the session identifier SHALL change, and the response SHALL redirect to `reservations.index`.
3. WHEN `GET /` or `GET /login` is requested by a guest THEN the system SHALL render Inertia `User/Login` (not Breeze `Auth/Login`).
4. IF register, password-reset, or email-verification URLs are requested THEN the system SHALL return HTTP 404 and SHALL NOT persist a `users` row.
5. The `users` table SHALL keep the ADR-002 columns only (no `email_verified_at`) and SHALL keep `HASH_DRIVER=argon` plus the module User model.
6. WHEN Execute finishes THEN `docs/adr/007-adotar-laravel-breeze-para-o-login.md` SHALL exist in MADR Portuguese dated 2026-09-20, ADR-001 SHALL be unedited, and ADR-002 SHALL change only its Status/supersede line for the “native auth without a kit” slice.

**Independent Test**: Feature `LoginHttpTest` / `DefaultAdministratorLoginHttpTest` stay green against MySQL 8; new 404 cases cover Breeze extras; `UsersMigrationTest` still forbids `email_verified_at`; Composer shows `laravel/breeze`; ADR-007 file exists.

### P1: Seed a demonstration catalog ⭐ MVP

**User Story**: As a developer, I want `php artisan migrate` to create some rooms and some reservations on top of the migrated administrators, and `php artisan db:seed` to guarantee the same catalog without duplicating, so that I can open the panel with catalog data.

**Why P1**: RF19; ADR-005 combined seeder.

**Acceptance Criteria**:

1. WHEN `DatabaseSeeder` runs after migrate THEN the system SHALL persist exactly three `users` rows (Gertrudes `teste@mail.com`, Marcelo `teste2@mail.com`, Emerson `teste3@mail.com`) and SHALL NOT insert `test@example.com` or any additional administrator.
2. WHEN migrate finishes THEN the system SHALL persist these three active rooms: `Sala Reunião Norte` capacity 8, `Sala Treinamento` capacity 20, `Sala Diretoria` capacity 4. WHEN `DatabaseSeeder` runs afterward THEN those three rooms SHALL remain and SHALL NOT be duplicated.
3. WHEN migrate finishes THEN the system SHALL persist three active reservations (`cancelled_at` null): Norte next calendar day 09:00–10:00 with 4 participants (Gertrudes, Reunião da manhã); Norte next calendar day 10:00–11:00 with 6 participants (Marcelo, Alinhamento seguinte); Treinamento next calendar day 14:00–16:00 with 12 participants (Emerson, Treinamento da tarde). WHEN `DatabaseSeeder` runs afterward THEN those three reservations SHALL remain and SHALL NOT be duplicated.
4. The demonstration reservations SHALL satisfy RF13–RF18: `ends_at` after `starts_at`; duration between 30 and 240 minutes inclusive; `starts_at` not in the past; participants less than or equal to the room capacity; room `is_active` true; no two active reservations on the same room overlap (consecutive end/start is allowed).

**Independent Test**: Feature `RefreshDatabase` on MySQL 8 already has 3 rooms + 3 reservations; `$this->seed()` afterward stays 3+3; keep `UsersMigrationTest` extra-admin case; Feature classes assert the three rooms and RF13–RF18 on the three reservations.

### P1: Follow the README including db:seed ⭐ MVP

**User Story**: As a newcomer, I want the README to tell me that `php artisan migrate` already creates demonstration rooms and reservations, and that `php artisan db:seed` guarantees the same data without duplicating, so that I can prepare and run the panel from that file alone.

**Why P1**: RNF12 after RF19 exists.

**Acceptance Criteria**:

1. WHEN documenting migrations THEN `README.md` SHALL instruct `php artisan migrate` then `php artisan db:seed`, and SHALL state that migrate already creates administrators plus demonstration rooms and reservations while seed guarantees the same data without duplicating.
2. The README SHALL keep, in Portuguese, `docker compose up -d`, `composer install`, `npm install`, `php artisan key:generate`, `composer run dev`, and the three default-administrator accounts table.
3. IF the README mentions environment variables THEN it SHALL NOT print values, passwords, or Compose `MYSQL_*` secrets.
4. The system SHALL NOT add `CONTRIBUTING.md` or `docs/setup.md`.

**Independent Test**: Read README; migrate already creates admins + demo catalog; seed is documented as an idempotent RF19 guarantee; existing local-run commands remain; no secret values; no new how-to files.

## Acceptance Criteria

1. WHEN Execute finishes THEN `composer.json` SHALL list `laravel/breeze` as a require-dev dependency.
2. WHEN a guest submits a valid POST `/login` THEN the Breeze-adapted `LoginRequest::authenticate()` SHALL call `Auth::attempt` with email and password only (remember-me false), the session identifier SHALL change, and the response SHALL redirect to `reservations.index`.
3. WHEN `GET /` or `GET /login` is requested by a guest THEN the system SHALL render Inertia `User/Login` (not Breeze `Auth/Login`).
4. IF register, password-reset, or email-verification URLs are requested THEN the system SHALL return HTTP 404 and SHALL NOT persist a `users` row.
5. The `users` table SHALL keep the ADR-002 columns only (no `email_verified_at`) and SHALL keep `HASH_DRIVER=argon` plus the module User model.
6. WHEN Execute finishes THEN `docs/adr/007-adotar-laravel-breeze-para-o-login.md` SHALL exist in MADR Portuguese dated 2026-09-20, ADR-001 SHALL be unedited, and ADR-002 SHALL change only its Status/supersede line for the “native auth without a kit” slice.
7. WHEN `DatabaseSeeder` runs after migrate THEN the system SHALL persist exactly three `users` rows (Gertrudes `teste@mail.com`, Marcelo `teste2@mail.com`, Emerson `teste3@mail.com`) and SHALL NOT insert `test@example.com` or any additional administrator.
8. WHEN migrate finishes THEN the system SHALL persist these three active rooms: `Sala Reunião Norte` capacity 8, `Sala Treinamento` capacity 20, `Sala Diretoria` capacity 4. WHEN `DatabaseSeeder` runs afterward THEN those three rooms SHALL remain and SHALL NOT be duplicated.
9. WHEN migrate finishes THEN the system SHALL persist three active reservations (`cancelled_at` null): Norte next calendar day 09:00–10:00 with 4 participants (Gertrudes, Reunião da manhã); Norte next calendar day 10:00–11:00 with 6 participants (Marcelo, Alinhamento seguinte); Treinamento next calendar day 14:00–16:00 with 12 participants (Emerson, Treinamento da tarde). WHEN `DatabaseSeeder` runs afterward THEN those three reservations SHALL remain and SHALL NOT be duplicated.
10. The demonstration reservations SHALL satisfy RF13–RF18: `ends_at` after `starts_at`; duration between 30 and 240 minutes inclusive; `starts_at` not in the past; participants less than or equal to the room capacity; room `is_active` true; no two active reservations on the same room overlap (consecutive end/start is allowed).
11. WHEN documenting migrations THEN `README.md` SHALL instruct `php artisan migrate` then `php artisan db:seed`, and SHALL state that migrate already creates administrators plus demonstration rooms and reservations while seed guarantees the same data without duplicating.
12. The README SHALL keep, in Portuguese, `docker compose up -d`, `composer install`, `npm install`, `php artisan key:generate`, `composer run dev`, and the three default-administrator accounts table.
13. IF the README mentions environment variables THEN it SHALL NOT print values, passwords, or Compose `MYSQL_*` secrets.
14. The system SHALL NOT add `CONTRIBUTING.md` or `docs/setup.md`.

## Edge Cases

- IF `php artisan breeze:install` would overwrite `routes/web.php` or `User/Login` THEN Execute SHALL NOT run that installer on this worktree.
- IF Breeze stubs put failed auth on the `email` field THEN the adapted FormRequest SHALL still expose `credentials` => `E-mail ou senha inválidos.` so the login banner contract stays.
- IF the sixth login attempt arrives THEN the system SHALL reject it with the same generic `credentials` error (existing `LoginHttpTest` throttle case).
- IF `DatabaseSeeder` runs THEN the system SHALL NOT call `User::factory` or insert any `users` row.
- IF two seeded reservations share `Sala Reunião Norte` THEN they SHALL be consecutive (10:00 join), not overlapping.
- IF `Sala Diretoria` is seeded THEN it SHALL remain without a reservation.
- IF `composer run setup` is used THEN README SHALL still document `php artisan db:seed` afterward as an idempotent guarantee (setup does not seed; migrate already created the catalog).
- IF seed runs after migrate on the same database THEN duplicate rooms/reservations SHALL NOT appear (first-or-create by room name; reservations only when those titles are still missing on those rooms).
- IF the reader looks for a SQL dump THEN none SHALL be added (RNF06).

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Editing ADR-001 | Packet forbids it |
| Rewriting ADR-002’s decision body | ADRs are immutable; status/supersede only |
| Official Laravel 12 React starter / Fortify-only auth | Fortify alone does not match RNF07’s Breeze-or-kit wording |
| Running `breeze:install` on this app | Overwrites rooms/reservations routes and the existing Inertia app |
| Register, password reset, email verification, profile, dashboard | ADR-001 / task 0016 / screen-login |
| JWT, roles, Fortify 2FA | ADR-001 |
| Breeze default User (bigint, `email_verified_at`, bcrypt, `App\Models\User`) | ADR-002 contract |
| SQL dump or schema-via-dump | RNF06 |
| Inserting or rehashing administrators | Already on migrate; extra-admin contract |
| Recreating `test@example.com` | Forbidden by 0016 / `UsersMigrationTest` |
| Calling `CreateReservation` from the seeder | Seed known-valid rows |
| Changing `composer.json` `setup` to `--seed` | Document artisan seed instead |
| New CONTRIBUTING.md or `docs/setup.md` | RNF12 is the README |
| Playwright / e2e runner | Not in `package.json`; not one of the three holes |
| Changing Room/Reservation use cases or React pages other than keeping `User/Login` | Catalog data + auth swap + README |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| How to install Breeze | `composer require laravel/breeze --dev` then copy login stubs from `vendor/laravel/breeze/stubs`; never `breeze:install` here | Installer blast radius would wipe `web.php` and the login UI | n |
| Where Breeze PHP lives | Relocate stubs into `app/Modules/User/Infra/Http` | `docs/tree.md` | n |
| Custom use case | Delete `AuthenticateUser` and the port/adapter after Breeze is wired | RNF07 forbids a parallel recreated login | n |
| Failed-auth field | Keep `credentials` + Portuguese message | `screen-login.md` and `LoginHttpTest` | n |
| Remember-me | Always false; no checkbox | Current contract; ADR-001 does not ask for it | n |
| Seeder layout | `RoomSeeder` + `ReservationSeeder` from `DatabaseSeeder` | Laravel `$this->call`; no `UserSeeder` | n |
| Data style | Deterministic Eloquent rows, not factories | Factories can violate RF16/RF13; `UserFactory` would add admins | n |
| Room/reservation catalog | Three named rooms; three named intervals above | “Algumas” = 3; Diretoria unused | n |
| Clock | `now()->addDay()` in app timezone UTC | RF18 | n |
| Idempotency | Required | Human repair: migrate already inserts the catalog; seed first-or-creates by room name and reservation title | y |
| `composer run setup` | Unchanged (no seed) | Packet asks to document `db:seed` | n |
| Punctual e2e | Not applicable | No Playwright project; Feature+Vitest cover login; do not bootstrap a runner | n |
| Second task | Not requested | Blast radius is contained by skipping the installer; user wants all three holes in 0018 | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Composer require Breeze + copy/adapt only login stubs + ADR-007 + dedicated seeders + README `db:seed`** — selected. Meets RNF07 literally without letting the installer destroy the app; RF19 and RNF12 stay as previously planned.
2. **Run `php artisan breeze:install react` then restore wiped files** — rejected. High chance of losing rooms/reservations routes, Vite/Tailwind, and `User/Login`.
3. **Official Laravel 12 React starter (Fortify)** — rejected. Packet: Fortify alone does not satisfy RNF07’s “starter kit or Laravel Breeze” wording.
4. **Keep `LoginController` / `AuthenticateUser` and only add the Breeze package** — rejected. Fake compliance; human said the documented deviation is not acceptable.
5. **Keep `AuthenticateUser` and have the Breeze controller call it** — rejected. Still a recreated login path beside the kit.
6. **Random factories inside `DatabaseSeeder`** — rejected. `UserFactory` adds admins; shared-room factory reservations can overlap or exceed capacity.
7. **Call `CreateReservation` from the seeder** — rejected. Couples seed to Application/clock.
8. **SQL dump** — rejected (RNF06).
9. **Split Breeze into a second harness task** — rejected. Same-task phases are enough if the installer is not used.

## Selected Approach

Approach 1.

- Require `laravel/breeze`, copy `AuthenticatedSessionController` and Breeze `LoginRequest`, relocate into the User module, adapt page/redirect/error field/remember, delete the custom login stack.
- Write ADR-007 (MADR, Portuguese, 2026-09-20). Mark only ADR-002’s Status for the superseded auth-without-kit slice. Add a one-line architecture.md note that login follows Breeze `LoginRequest::authenticate()`.
- Add a data migration that inserts the same three rooms and three reservations as the seeders. Keep `RoomSeeder` and `ReservationSeeder`; they first-or-create so migrate + seed stays 3+3.
- Add `tests/Feature/Database/DatabaseSeederTest.php` and `DemoCatalogMigrationTest.php`. Keep extra-admin coverage in `UsersMigrationTest`. Add FormRequest unit tests for `LoginRequest`. Extend 404 coverage for Breeze extra URLs.
- Update `README.md` so migrate already creates the demo catalog and seed stays the idempotent RF19 step. Print no secrets.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| AUTH-01 | P1: Authenticate with Laravel Breeze | Execute | Implemented |
| AUTH-02 | P1: Authenticate with Laravel Breeze | Execute | Implemented |
| AUTH-03 | P1: Authenticate with Laravel Breeze | Execute | Implemented |
| AUTH-04 | P1: Authenticate with Laravel Breeze | Execute | Implemented |
| AUTH-05 | P1: Authenticate with Laravel Breeze | Execute | Implemented |
| SEED-01 | P1: Seed a demonstration catalog | Execute | Implemented |
| SEED-02 | P1: Seed a demonstration catalog | Execute | Implemented |
| SEED-03 | P1: Seed a demonstration catalog | Execute | Implemented |
| README-01 | P1: Follow the README including db:seed | Execute | Implemented |
| README-02 | P1: Follow the README including db:seed | Execute | Implemented |

**Coverage:** 10 total, 10 mapped to tasks, 0 unmapped.

## Success Criteria

- [x] `laravel/breeze` is installed; POST `/login` uses Breeze authenticate; `User/Login` and ADR-002 schema remain.
- [x] Register / reset / verify stay 404. ADR-007 exists. ADR-001 untouched.
- [x] `php artisan migrate` yields three admins, three rooms, three valid reservations. `php artisan db:seed` afterward stays 3+3.
- [x] Extra-admin / `test@example.com` contract stays green.
- [x] README documents that migrate already creates the demo catalog and that `db:seed` is the idempotent RF19 guarantee, and keeps the Portuguese local-run path without secrets or extra how-to files.
