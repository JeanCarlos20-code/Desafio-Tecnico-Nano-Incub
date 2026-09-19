# Validation

## Acceptance Criteria

| AC | Spec outcome | Evidence | Status |
| --- | --- | --- | --- |
| AUTH-01 / AC1 | RefreshDatabase persists Gertrudes/`teste@mail.com`, Marcelo/`teste2@mail.com`, Emerson/`teste3@mail.com` | `tests/Feature/Database/UsersMigrationTest.php:33` `assertDatabaseCount('users', 3)`; `:34-45` `assertDatabaseHas` for each name+email | Covered |
| AUTH-01 / AC2 | Each password is Argon2i, `Hash::check('Senha123', $hash)` is true, column is not plaintext | `UsersMigrationTest.php:54` `assertNotSame('Senha123', $hash)`; `:55` `Hash::check('Senha123', $hash)`; `:56` `password_get_info(...)['algoName'] === 'argon2i'` | Covered |
| AUTH-01 / AC3 | POST `/login` with each default email + `Senha123` authenticates and redirects to `/reservations` | `tests/Feature/User/DefaultAdministratorLoginHttpTest.php:25` `assertRedirect('/reservations')`; `:27` `assertAuthenticatedAs($user)`; provider `:36-38` three emails | Covered |
| AUTH-02 / AC4 | GET/POST `/register`, GET `/users/create`, POST `/users` return 404 and insert no users row | `RemovedRegistrationHttpTest.php:25` `assertNotFound()`; `:27` count stays 3; `:28` missing `attacker@example.com`; provider `:45-48` | Covered |
| AUTH-02 / AC5 | Named routes `register`, `register.store`, `users.create`, `users.store` are gone | `RemovedRegistrationHttpTest.php:33-36` `assertFalse(Route::has(...))` | Covered |
| AUTH-02 / AC6 | Production `UserController`, `StoreUserRequest`, `CreateUser`, `User/Create`, `Services/users.js` are gone | Files deleted from `app/Modules/User` and `resources/js` | Covered |
| AUTH-03 / AC7 | Login does not show `Não tem uma conta?`, `Ir para o cadastro`, or `/register` | `resources/js/Pages/User/Login.test.jsx:81-84` and processing-state `:145-147` | Covered |
| DOC-01 / AC8 | `screen-login.md` omits cadastro navigation and the cadastro checkbox | `docs/screens/screen-login.md` right column and acceptance list document absence only | Covered |
| DOC-01 / AC9 | `screen-create-user.md` deleted; README lists the three default accounts | File deleted; `README.md:8-11` Gertrudes / Marcelo / Emerson | Covered |
| AUTH-01 / AC10 | `DatabaseSeeder` after migrate does not insert `test@example.com` or a fourth admin | `UsersMigrationTest.php:64-65` count 3 and missing `test@example.com` | Covered |
| Edge: `down()` | Rollback deletes only the three emails | `UsersMigrationTest.php:80-84` missing defaults, `ada@example.com` remains, count 1 | Covered |
| Edge: factory + migrate | One factory user yields count 4 | `MysqlConnectionTest.php:35` `assertDatabaseCount('users', 4)` | Covered |
| Edge: bookmark `/register` | 404, not a login redirect | `RemovedRegistrationHttpTest.php:25` `assertNotFound()` on GET `/register` | Covered |

## Test Results

Repair observed (worktree-local, not the harness final gate):

- Combined check `php artisan test && npm run test` — 141 PHP passed (917 assertions), 88 Vitest passed
- Original red `app:test` was `SQLSTATE[42S01]` Table `users` already exists during `RefreshDatabase` remigrate after reservation concurrency workers share `painel_administrativo_test`
- No review findings (last review absent). No valid test was weakened.

## Required Gates

Observed locally before `complete-phase`. Harness re-runs these and owns the official result.

| Gate | Command | Observed |
| --- | --- | --- |
| app:test | `php artisan test && npm run test` | 141 PHP + 88 Vitest passed |

## Review Result

No prior review file. Repair addressed the required red `app:test` check only.

## Final Status

Repair ready for harness re-check of `php artisan test && npm run test`.

### Extra files

- `tests/Feature/User/RemovedRegistrationHttpTest.php` and `tests/Feature/User/DefaultAdministratorLoginHttpTest.php`: planned sibling Feature classes (Execute).
- `tests/TestCase.php`: `migrate:fresh` retries once after `Schema::dropAllTables()` when MySQL reports table already exists. Needed because Feature remigrates after concurrency workers on the shared test schema; the failing check was that race, not a product AC change.

### Deviations

- `DatabaseSeeder::run()` remains a single-line empty body for Pint `single_line_empty_body`.
- Default-administrator migration hashes `Senha123` once and reuses the Argon2i value for all three rows. `Hash::check('Senha123', $hash)` and `argon2i` still hold.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `php artisan test --testsuite=Unit --coverage --min=80` — exit=0 (required)
- ✅ `npm run test:coverage` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run lint` — exit=0 (required)
- ✅ `composer run build` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
<!-- harness-checks:end -->
