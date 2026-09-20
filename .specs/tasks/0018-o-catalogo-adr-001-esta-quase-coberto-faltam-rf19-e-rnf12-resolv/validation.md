# Validation

## Acceptance Criteria

1. `composer.json` lists `laravel/breeze` as require-dev (`composer.json:16`).
2. POST `/login` calls Breeze `LoginRequest::authenticate()` with `Auth::attempt($this->only('email', 'password'), false)`, regenerates the session, and redirects to `reservations.index` (`tests/Feature/User/LoginHttpTest.php:71` — `$this->assertNotSame($previousSessionId, session()->getId())`; `:69` — `assertRedirect(route('reservations.index'))`).
3. Guest GET `/` and GET `/login` render Inertia `User/Login` (`LoginHttpTest.php:26` and `:42` — `$page->component('User/Login')`).
4. GET/POST `/register`, `/forgot-password`, `/reset-password`, and `/email/verification-notification` return 404 and insert no users row (`RemovedRegistrationHttpTest.php:25` — `assertNotFound()`; `:27` — `assertDatabaseCount('users', 3)`).
5. Users table keeps ADR-002 columns only, no `email_verified_at` (`UsersMigrationTest.php:28` — `assertFalse(Schema::hasColumn('users', 'email_verified_at'))`).
6. ADR-007 exists (MADR, Portuguese, 2026-09-20). ADR-001 is unedited. ADR-002 Status points at ADR-007 for the auth-without-kit slice only.
7. After `$this->seed()`, users stay Gertrudes / Marcelo / Emerson; `test@example.com` is absent (`DatabaseSeederTest.php:54` — `assertDatabaseCount('users', 3)`; `:58` — `assertDatabaseMissing('users', ['email' => 'test@example.com'])`).
8. After RefreshDatabase (migrate only) the three named active rooms exist (`DemoCatalogMigrationTest.php:17` — `assertDatabaseCount('rooms', 3)`; `:18-31` — `assertDatabaseHas` Norte 8 / Treinamento 20 / Diretoria 4). Seed afterward stays 3 rooms (`DatabaseSeederTest.php:42` — `assertDatabaseCount('rooms', 3)`; `:44` — `assertSame(1, Room::query()->where('name', 'Sala Reunião Norte')->count())`).
9. After RefreshDatabase (migrate only) the three named active reservations exist (`DemoCatalogMigrationTest.php:51` — `assertDatabaseCount('reservations', 3)`; `:54-82` — Gertrudes Reunião da manhã 09:00–10:00 / Marcelo Alinhamento seguinte 10:00–11:00 / Emerson Treinamento da tarde 14:00–16:00). Seed afterward stays 3 reservations (`DatabaseSeederTest.php:43` — `assertDatabaseCount('reservations', 3)`).
10. Demonstration reservations satisfy RF13–RF18, including consecutive Norte slots and no active overlap (`DemoCatalogMigrationTest.php:84` — `ends_at->equalTo($norteNext->starts_at)`; `:98-104`; `:116` — `assertFalse($overlaps)`).
11. README states `php artisan migrate` already creates administrators plus demonstration rooms and reservations, and `php artisan db:seed` guarantees the same data without duplicating (`README.md:67`).
12. README keeps Portuguese `docker compose up -d`, `composer install`, `npm install`, `php artisan key:generate`, `composer run dev`, and the three default-administrator accounts.
13. README prints no env/Compose secret values.
14. No `CONTRIBUTING.md` or `docs/setup.md` was added.

## Test Results

Unit (`php artisan test --testsuite=Unit`): 48 passed (201 assertions). LoginRequest input-contract cases remain. No seeder units were added (`docs/test/unit.md` forbids artificial units for simple seeders).

Feature (`php artisan test --testsuite=Feature`): 111 passed (985 assertions) on MySQL 8 via `RefreshDatabase`. New `DemoCatalogMigrationTest` covers migrate-alone 3+3, UUID v7, RF13–RF18, and rollback of only those rows. `DatabaseSeederTest` covers migrate-alone 3+3 and seed idempotency (still 3+3). HTTP tests that assumed an empty catalog now use baseline 3/3 or a relative increment and keep the business-rule assertions.

Frontend (`npm run test`): not re-run this REPAIR round. User/Login Vitest was unchanged.

E2E: not applicable. No Playwright runner. See `harness.tests_not_applicable.e2e`.

## Required Gates

Quick checks run during REPAIR (harness re-runs the official gates after this phase):

- `php artisan test --testsuite=Unit` — passed (48 / 201)
- `php artisan test --testsuite=Feature` — passed (111 / 985)
- `vendor/bin/pint --test` — passed

Do not treat these as the harness `test` / `lint` / `build` final verdict.

## Review Result

Previous review (review-01) was APPROVED with no blockers or highs. This REPAIR applies human feedback only: a data migration for the demo catalog, idempotent seeders, README and spec/tasks updates, and Feature tests adjusted to the migrate 3/3 baseline.

## Final Status

REPAIR is ready for harness deterministic gates. No product commit was created.

### Extra files

`database/migrations/2026_09_20_000000_insert_demo_rooms_and_reservations.php` is the data migration requested by human feedback (same pattern as `2026_09_19_000000_insert_default_administrators.php`). `tests/Feature/Database/DemoCatalogMigrationTest.php` is the integration home for migrate-alone 3+3, UUID v7, RF13–RF18, and rollback-only-those-rows. `RoomIndexHttpTest.php`, `RoomDestroyHttpTest.php`, and `ReservationRoomLifecycleConcurrencyHttpTest.php` were also adjusted because RefreshDatabase now starts with 3 rooms + 3 reservations; leaving those empty-table counts would fail without weakening business-rule assertions.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
<!-- harness-checks:end -->
