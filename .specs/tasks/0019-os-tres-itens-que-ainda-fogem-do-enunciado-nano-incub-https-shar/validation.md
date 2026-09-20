# Validation

## Acceptance Criteria

Rooms and reservations persist incrementing bigint ids (`$table->id()` / `foreignId`). Lists show `ID` and `Situação`. Users stay UUID v7. README section 05 covers overlap (Application RF13–RF18 + room `lockForUpdate`), ADR-001 leftovers, and Cursor-only AI use. `DatabaseSeeder` `firstOrCreate`s Gertrudes, Marcelo, and Emerson; migrate+seed leaves exactly three users and never `test@example.com`.

Extra files beyond `tasks.md` Where (concrete dependency):

- `app/Modules/Reservation/Infra/Http/Controllers/StoreReservationController.php` and `IndexReservationController.php`: Domain `roomId` stays `string`; FormRequest now validates `integer`, so Infra casts before the use case.
- `tests/Feature/Room/RoomUpdateHttpTest.php` and `ReservationIndexHttpTest.php`: Inertia props are Domain strings; Eloquent keys are ints after `HasUuids` removal.
- `database/seeders/DatabaseSeeder.php`: T7 requires calling `UserSeeder` first.
- List pages and their Vitest files: T5/T6.

Security override (documented in ADR-008): incrementing public ids on rooms/reservations. Users remain UUID v7. Routes stay behind `auth`. Incrementing catalog ids are the approved challenge contract, not a silent weakening of LARAVEL-ID-001 for users.

## Test Results

Planned unit items: Store/Index ReservationRequest integer `room_id` contract (container, no route); Room/Index and Reservation/Index show `ID`, persisted id, and `Situação`.

Planned integration items: incrementing PKs/FK; factory and demo catalog integer ids; POST /rooms and POST /reservations persist integer ids; GET list Inertia payloads include ids; migrate+seed three admins; seeder recreates missing emails; unknown/malformed route ids return 404.

E2E: not applicable (no Playwright).

Worker-run evidence (not the harness final gate): FormRequest unit 5 passed; identity/seeder Feature filter 76 passed; Room/Reservation Index Vitest 27 passed.

## Required Gates

Harness re-runs `unit`, `integration`, `frontend`, `lint`, `build`, and `test` after this report. This file does not declare those gates green.

## Review Result

Pending independent review.

## Final Status

Implementation complete for T1–T8. No commit. Ready for harness deterministic checks.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `php artisan test --testsuite=Unit --coverage --min=80` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `npm run test:coverage` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
<!-- harness-checks:end -->
