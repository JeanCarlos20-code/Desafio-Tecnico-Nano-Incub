# Validation

## Acceptance Criteria

| ID | Result | Evidence |
| -- | ------ | -------- |
| AC-001 | Pass | `compose.yml` starts `painel_mysql` from `mysql:8.0` with frozen env, `3306:3306`, `mysql_data`, healthcheck, init mount. Container was healthy after `docker compose up -d`. |
| AC-002 | Pass | `.env.example` sets `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=painel_administrativo`, `DB_USERNAME=painel`, `DB_PASSWORD=painel`. |
| AC-003 | Pass | `config/database.php` default fallback is `env('DB_CONNECTION', 'mysql')`. mysql connection still maps `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. |
| AC-004 | Pass | Feature persistence tests store users on MySQL. `CreateUserPersistenceTest` and `UserSchemaTest` passed on MySQL 8. |
| AC-005 | Pass | Default connection is mysql. phpunit no longer sets sqlite/` :memory:`. No sqlite fallback was added. |
| AC-006 | Pass | `tests/Feature/Database/MysqlConnectionTest.php:16-18,23` asserts default `mysql` and database `painel_administrativo_test`. |
| AC-007 | Pass | `phpunit.xml` sets every `DB_*` line including `DB_URL` with `force="true"`. |
| AC-008 | Pass | `MysqlConnectionTest` uses `RefreshDatabase` and `assertDatabaseCount` / `assertDatabaseHas`. After Feature suite, `painel_administrativo` still had no tables; only `painel_administrativo_test` received migrations. |
| AC-009 | Pass | `MysqlConnectionTest.php:19-23` asserts not sqlite, not `painel_administrativo`, not `:memory:`, and live PDO database name is `painel_administrativo_test`. |
| AC-010 | Pass | `php artisan test --testsuite=Unit`: 2 passed. `CreateUserTest` stays on a fake repository. |
| AC-011 | Pass | `.env.testing` is present and not gitignored. Testing `DB_*` match the spec table. |
| AC-012 | Pass | Spec Selected Approach / env tables document CI `DB_*` as identical to PHPUnit. No workflow file added (out of scope). |
| AC-013 | Pass | `.env` was not created. `.gitignore` still ignores `.env`. |
| AC-014 | Pass | Application columns come from `database/migrations`. Users migration file was not rewritten. No `database/schema` dump. |
| AC-015 | Pass | `docker/mysql/init/01-create-test-database.sql` creates empty `painel_administrativo_test` (utf8mb4 / utf8mb4_unicode_ci) and GRANTs `painel@'%'`. No `CREATE TABLE` / `ALTER TABLE`. First-boot logs show the init script ran. Test schema had no tables until `RefreshDatabase`. |
| AC-016 | Pass | `UsersMigrationTest.php:19` after `RefreshDatabase`: `users` columns are exactly `id`, `name`, `email`, `password`, `remember_token`, `created_at`, `updated_at`, `deleted_at`. |
| AC-017 | Pass | Tests inspect with `Schema::getColumnListing` / `Schema::hasColumn` only. No `Schema::create` / `Schema::table` in `tests/Feature/Database`. |
| AC-018 | Pass | No `database/schema` directory or dump was added. |

## Test Results

Execute ran PHP gates locally in the worktree. Repair re-ran only the red required check locally. Do not treat this as the final harness gate.

- Unit: `php artisan test --testsuite=Unit` — 2 passed (4 assertions).
- Feature: `php artisan test --testsuite=Feature` — 22 passed (136 assertions). Pre-task Feature files remain; added `MysqlConnectionTest` (2 tests) and `UsersMigrationTest` (1 test). Count did not drop.
- Lint: `vendor/bin/pint --test` — passed.
- Build (repair): `npm run build` — passed locally after `npm install` in the worktree (`vite v7.3.6`, client production build).

IT-001..IT-007 and UT-001 are covered by the files in `tasks.md` Planned Tests.

## Required Gates

Harness required gates (commands unchanged):

1. unit: `php artisan test --testsuite=Unit`
2. integration: `php artisan test --testsuite=Feature` (MySQL container healthy; empty `painel_administrativo_test` exists; columns from migrate)
3. lint: `vendor/bin/pint --test`
4. build: `npm run build` (stack.yml `app.commands.build`; worktree must have `node_modules` so `vite` exists)

Prerequisite: `docker compose up -d` from the worktree root. Host PHP uses `DB_HOST=127.0.0.1`. Host needs `pdo_mysql`. Host Node needs `npm install` so `node_modules/.bin/vite` is present.

## Review Result

Round 1 verdict: `APPROVED` (0 blocker, 0 high). Human feedback: none.

Repair was required only because the harness required check `app:build` (`npm run build`) was red with exit=127 (`sh: 1: vite: not found`). The worktree had no `node_modules`.

## Final Status

REPAIRED_PENDING_HARNESS_GATES

Repair installed gitignored `node_modules` via `npm install` and confirmed `npm run build` exits 0. No Compose, migration, env, or product source files were changed. An incidental `package-lock.json` `name` rewrite from `npm install` (worktree directory name) was reverted.

### Extra files (concrete dependency)

`.env.testing` includes `APP_KEY` in addition to the spec `DB_*` block. Reason: this worktree has no developer `.env`. Laravel loads `.env.testing` when `APP_ENV=testing`. HTTP Feature tests (`CreateUserHttpTest`, `ExampleTest`) need an application encryption key. Without it they fail with `MissingAppKeyException`. The key is a testing value, not a production secret. `.env` was still not created.

No other extra production files. `vendor/` and `node_modules/` were installed locally to run gates and are gitignored. `public/build` is also gitignored.

### Test database provisioning

`mysql_data` did not exist. First `docker compose up -d` ran `/docker-entrypoint-initdb.d/01-create-test-database.sql`. Empty `painel_administrativo_test` and GRANT were present. No one-time root `CREATE DATABASE` was required.

If a future volume is already initialized, create the empty test database once as root:

```sql
CREATE DATABASE IF NOT EXISTS `painel_administrativo_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `painel_administrativo_test`.* TO 'painel'@'%';
```

No table or column DDL.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `php artisan test --testsuite=Unit` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `php artisan test` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
<!-- harness-checks:end -->
