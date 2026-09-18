---
harness:
  commits:
    - "feat(.env): document mysql app and test credentials"
    - "feat(compose): add mysql 8 docker service"
    - "feat(config): default laravel connection to mysql"
    - "feat(docker): create isolated mysql test database on first boot"
    - "feat(phpunit): point phpunit at isolated mysql test database"
    - "test(database): cover mysql isolation and migrated users columns"
    - "chore(specs): record mysql docker task artifacts"
  gates:
    - id: unit
      command: "php artisan test --testsuite=Unit"
      required: true
    - id: integration
      command: "php artisan test --testsuite=Feature"
      required: true
    - id: lint
      command: "vendor/bin/pint --test"
      required: true
---

# Implementation Plan

## Summary

Add the user-specified MySQL 8 Compose service, point Laravel at `painel_administrativo` for app data, and point PHPUnit / `.env.testing` at isolated `painel_administrativo_test` on the same instance. Feature tests keep `RefreshDatabase`. Do not use SQLite for runtime or Feature tests.

RNF06 revision: application columns come only from `database/migrations`. The existing users migration already matches ADR-002, so Execute does not rewrite it and does not add an alter-users file. Compose init SQL creates the empty test database only. After MySQL is up, `php artisan migrate` (local) and `RefreshDatabase` (tests) apply columns. Rooms/reservations migrations wait for those modules.

Design is inline (medium scope). No `design.md`.

## Affected Components

- `app` (root `.`): `compose.yml`, `docker/mysql/init/`, `.env.example`, `config/database.php`, `phpunit.xml`, `.env.testing`, `tests/Feature/Database/`.
- `database/migrations/0001_01_01_000000_create_users_table.php` is read-only in this task (already complete).
- Existing User Feature tests become MySQL integration tests (no behavior change).
- `harness` component: untouched.

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/reviews/review-tests.md`, `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` (RNF06), `harness/stack.yml`, `phpunit.xml`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Compose / init SQL | none | Build/config only; names match the user YAML; init SQL has no table/column DDL | `compose.yml`, `docker/mysql/init/*.sql` | compose file review + `docker compose config` |
| Env / phpunit / database.php | none | Values match spec tables; `force="true"` on `DB_*` | `.env.example`, `.env.testing`, `phpunit.xml`, `config/database.php` | unit gate (no DB) then integration after T7 |
| Laravel migrations (users) | none | File already complete vs ADR-002; no rewrite; applied by migrate | `database/migrations/0001_01_01_000000_create_users_table.php` | build/config; covered by T8 integration |
| MySQL connection isolation | integration | AC-006..AC-009: driver mysql, database `painel_administrativo_test`, not sqlite, not dev schema; write via `RefreshDatabase` | `tests/Feature/Database/MysqlConnectionTest.php` | `php artisan test --testsuite=Feature` |
| Migrated users columns | integration | AC-014..AC-017: after `RefreshDatabase`, `users` listing is the ADR-002 set; no `Schema::create` of `users` in the test | `tests/Feature/Database/UsersMigrationTest.php` | `php artisan test --testsuite=Feature` |
| Existing User Feature persistence | integration | Existing ACs still pass on MySQL 8 | `tests/Feature/User/*Test.php` | `php artisan test --testsuite=Feature` |
| User use-case unit | unit | Stay fake-based; no MySQL writes (AC-010) | `tests/Unit/User/CreateUserTest.php` | `php artisan test --testsuite=Unit` |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After config-only tasks (T1–T6) | `php artisan test --testsuite=Unit` |
| Full | After T7, T8, and phase complete | `php artisan test --testsuite=Feature` |
| Build | Phase complete | `vendor/bin/pint --test` and `php artisan test --testsuite=Unit` |

Prerequisite for Full: `docker compose up -d` and empty database `painel_administrativo_test` exist. Host PHP needs `pdo_mysql`. Schema on that database comes from migrations, not init SQL.

## Execution Plan

Phases are ordered and run sequentially - each phase completes before the next begins, and tasks within a phase execute in order.

### Phase 1: MySQL Docker, Laravel wiring, and migrated schema

```
T1 -> T2 -> T3 -> T4 -> T5 -> T6 -> T7 -> T8
```

## Tasks

1. T1 — Add `compose.yml` with the frozen mysql service (plus healthcheck and init mount).
2. T2 — Add init SQL that creates empty `painel_administrativo_test` and grants `painel` (no table/column DDL).
3. T3 — Document local MySQL `DB_*` in `.env.example`.
4. T4 — Default `config/database.php` to `mysql`.
5. T5 — Point `phpunit.xml` at the isolated MySQL test database with `force="true"`.
6. T6 — Commit `.env.testing` with the same test `DB_*` values.
7. T7 — Add Feature coverage that PHPUnit talks to `painel_administrativo_test` and re-run existing Feature tests.
8. T8 — Add Feature coverage that `RefreshDatabase` applied ADR-002 `users` columns from `database/migrations`.

## Task Breakdown

### Phase 1: MySQL Docker, Laravel wiring, and migrated schema

### T1: Add MySQL 8 Compose service

**What**: Create root `compose.yml` with the user `mysql` service and `mysql_data` volume unchanged; add only a healthcheck and the init bind mount.
**Where**: `compose.yml`
**Depends on**: None
**Reuses**: User-supplied service YAML from the plan packet
**Requirement**: MYSQL-01

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `services.mysql.image` is `mysql:8.0` and `container_name` is `painel_mysql`
- [x] Environment, ports `3306:3306`, volume `mysql_data:/var/lib/mysql`, and named volume `mysql_data` match the user YAML
- [x] Init mount `./docker/mysql/init:/docker-entrypoint-initdb.d` and a `mysqladmin ping` healthcheck are present
- [x] Gate check passes: `php artisan test --testsuite=Unit`

**Tests**: none
**Gate**: quick

---

### T2: Create isolated test database on first boot

**What**: Add the official-image init script that creates empty `painel_administrativo_test` (utf8mb4 / utf8mb4_unicode_ci) and `GRANT`s it to `painel`, with no table or column DDL.
**Where**: `docker/mysql/init/01-create-test-database.sql`
**Depends on**: T1
**Reuses**: MySQL docker-entrypoint `/docker-entrypoint-initdb.d` contract
**Requirement**: MYSQL-03, MYSQL-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Script creates `painel_administrativo_test` and grants `painel@'%'`
- [x] Script contains no `CREATE TABLE`, `ALTER TABLE`, or column definitions
- [x] If `mysql_data` is already initialized, Execute documents and runs the equivalent root `CREATE DATABASE` / `GRANT` once (still no table DDL)
- [x] Gate check passes: `php artisan test --testsuite=Unit`

**Tests**: none
**Gate**: quick

---

### T3: Document local MySQL credentials in .env.example

**What**: Replace sqlite defaults with the local MySQL `DB_*` block from the spec.
**Where**: `.env.example`
**Depends on**: T2
**Reuses**: Existing commented `DB_HOST` / `DB_PORT` keys
**Requirement**: MYSQL-02, MYSQL-05

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] File contains exactly `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=painel_administrativo`, `DB_USERNAME=painel`, `DB_PASSWORD=painel`
- [x] SQLite is no longer the documented app connection
- [x] `.env` is not created or committed
- [x] Gate check passes: `php artisan test --testsuite=Unit`

**Tests**: none
**Gate**: quick

---

### T4: Default Laravel connection to mysql

**What**: Change the `config/database.php` default fallback from `sqlite` to `mysql`.
**Where**: `config/database.php`
**Depends on**: T3
**Reuses**: Existing `connections.mysql` env mapping
**Requirement**: MYSQL-02

**Tools**:

- MCP: context7
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `'default' => env('DB_CONNECTION', 'mysql')`
- [x] mysql connection still reads `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- [x] Gate check passes: `php artisan test --testsuite=Unit`

**Tests**: none
**Gate**: quick

---

### T5: Force PHPUnit onto the MySQL test database

**What**: Replace sqlite/` :memory:` in `phpunit.xml` with forced MySQL test `DB_*` values from the spec.
**Where**: `phpunit.xml`
**Depends on**: T4
**Reuses**: Current `<php>` env block (`APP_ENV=testing`, array/sync session/cache/queue)
**Requirement**: MYSQL-03, MYSQL-05

**Tools**:

- MCP: context7
- Skill: `tlc-spec-driven`

**Done when**:

- [x] phpunit env matches the spec XML (`mysql`, `127.0.0.1`, `3306`, `painel_administrativo_test`, `painel`, `painel`, empty `DB_URL`)
- [x] Every `DB_*` line (including `DB_URL`) has `force="true"`
- [x] `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` are gone
- [x] Gate check passes: `php artisan test --testsuite=Unit`

**Tests**: none
**Gate**: quick

---

### T6: Commit .env.testing with the same test values

**What**: Add `.env.testing` documenting the PHPUnit/CI database variables.
**Where**: `.env.testing`
**Depends on**: T5
**Reuses**: Spec testing table; do not copy a full `.env`
**Requirement**: MYSQL-05

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] File sets `APP_ENV=testing` plus `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=painel_administrativo_test`, `DB_USERNAME=painel`, `DB_PASSWORD=painel`
- [x] File is not listed in `.gitignore`
- [x] Gate check passes: `php artisan test --testsuite=Unit`

**Tests**: none
**Gate**: quick

---

### T7: Cover MySQL test connection isolation

**What**: Add a Feature test that proves PHPUnit is on MySQL `painel_administrativo_test` and that `RefreshDatabase` can persist a row there; run existing Feature tests as regression.
**Where**: `tests/Feature/Database/MysqlConnectionTest.php`
**Depends on**: T6
**Reuses**: `Tests\TestCase`, `RefreshDatabase`, existing User Feature tests
**Requirement**: MYSQL-04

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Test asserts `config('database.default')` is `mysql`
- [x] Test asserts `config('database.connections.mysql.database')` is `painel_administrativo_test`
- [x] Test asserts the driver is not `sqlite` and the database is not `painel_administrativo` or `:memory:`
- [x] Test uses `RefreshDatabase` and `assertDatabaseCount` / `assertDatabaseHas` on a migrated table
- [x] Test does not call `Schema::create` / `Schema::table` to build application tables
- [x] Existing `tests/Feature/User` tests still pass
- [x] Gate check passes: `php artisan test --testsuite=Feature`
- [x] Test count does not drop versus the pre-task Feature suite (3 User Feature files plus this new class)

**Tests**: integration
**Gate**: full

---

### T8: Cover users columns applied by migrations

**What**: Add a Feature test that proves `RefreshDatabase` created the ADR-002 `users` columns from `database/migrations`, not from init SQL or in-test `Schema::`.
**Where**: `tests/Feature/Database/UsersMigrationTest.php`
**Depends on**: T7
**Reuses**: `Tests\TestCase`, `RefreshDatabase`, `Schema::getColumnListing` as an inspector only
**Requirement**: MYSQL-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Test uses `RefreshDatabase` and does not `Schema::create` / `Schema::table` the `users` table
- [x] After migrate, `Schema::getColumnListing('users')` contains exactly `id`, `name`, `email`, `password`, `remember_token`, `created_at`, `updated_at`, `deleted_at` (order-insensitive)
- [x] Test asserts those columns exist (`Schema::hasColumn`) and that `email_verified_at` does not
- [x] `0001_01_01_000000_create_users_table.php` is left unchanged
- [x] No `database/schema` dump is added
- [x] Gate check passes: `php artisan test --testsuite=Feature`
- [x] Feature test count does not drop versus T7 plus this new class

**Tests**: integration
**Gate**: full

## Planned Tests

| ID | Level | File | Input | Expected result | AC |
| -- | ----- | ---- | ----- | --------------- | -- |
| IT-001 | integration | `tests/Feature/Database/MysqlConnectionTest.php` | PHPUnit boot | default connection `mysql` | AC-006, AC-009 |
| IT-002 | integration | `tests/Feature/Database/MysqlConnectionTest.php` | config | database name `painel_administrativo_test` | AC-006, AC-009 |
| IT-003 | integration | `tests/Feature/Database/MysqlConnectionTest.php` | config | not sqlite, not `:memory:`, not `painel_administrativo` | AC-007, AC-009 |
| IT-004 | integration | `tests/Feature/Database/MysqlConnectionTest.php` | `RefreshDatabase` + insert | row visible in the test connection | AC-008 |
| IT-005 | integration | `tests/Feature/User/*.php` | existing create-user cases | still green on MySQL 8 | AC-004 |
| IT-006 | integration | `tests/Feature/Database/UsersMigrationTest.php` | `RefreshDatabase` then `Schema::getColumnListing('users')` | exact ADR-002 column set; no `email_verified_at` | AC-014, AC-016, AC-017 |
| IT-007 | integration | `tests/Feature/Database/UsersMigrationTest.php` | test source | no `Schema::create`/`Schema::table` of `users` | AC-017 |
| UT-001 | unit | `tests/Unit/User/CreateUserTest.php` | fake repository | still green, no MySQL write | AC-010 |

Config-only coverage (no extra test class): AC-001, AC-002, AC-003, AC-011, AC-012, AC-013, AC-015, AC-018 via committed Compose/init/env/phpunit files reviewed at T1–T6 and T8 (no schema dump).

Create: `tests/Feature/Database/MysqlConnectionTest.php`, `tests/Feature/Database/UsersMigrationTest.php`.
Alter: none of the User tests unless a MySQL-specific assertion fails (fix the env, not the product rule). Do not alter `database/migrations/0001_01_01_000000_create_users_table.php`.
Execute: `php artisan test --testsuite=Unit` then `php artisan test --testsuite=Feature` with Compose up and migrations applied by `RefreshDatabase`.

Do not add Playwright, Vitest, or harness pytest for this task.

## Required Gates

Executed by the harness after Execute (cwd = worktree) and as each task Gate:

1. **unit** (required): `php artisan test --testsuite=Unit`
2. **integration** (required): `php artisan test --testsuite=Feature` (MySQL container healthy; empty `painel_administrativo_test` exists; columns come from migrate)
3. **lint** (required): `vendor/bin/pint --test`

`npm run build` / Playwright are not required; this task does not touch frontend.

## Definition of Done

- AC-001..AC-018 are covered by the planned tests or by committed config that those tests read.
- `compose.yml` matches the frozen names/credentials.
- App `.env.example` uses `painel_administrativo`; tests use `painel_administrativo_test`.
- `phpunit.xml` forces MySQL test `DB_*`; sqlite `:memory:` is gone.
- `.env.testing` is committed; `.env` is not.
- Init SQL has no table/column DDL.
- Users columns on MySQL 8 come from the existing Laravel migration via `php artisan migrate` / `RefreshDatabase`.
- No `database/schema` dump added; users migration file unchanged.
- Feature suite green on MySQL 8; unit suite green without writing to the dev volume.
- `harness.commits` messages stay valid Conventional Commits (`type(module):` English, lowercase start, no period).

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path.

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

**Design**: skipped (inline in Considered Approaches / Selected Approach)
**Status**: Implemented

## Phase Execution Map

```
Phase 1:  T1 -> T2 -> T3 -> T4 -> T5 -> T6 -> T7 -> T8
```

Execution is strictly sequential - there is no intra-phase parallelism.

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: compose.yml | 1 file | Granular |
| T2: init SQL | 1 file | Granular |
| T3: .env.example | 1 file | Granular |
| T4: database.php default | 1 assignment | Granular |
| T5: phpunit.xml DB env | 1 file | Granular |
| T6: .env.testing | 1 file | Granular |
| T7: MysqlConnectionTest | 1 test class | Granular |
| T8: UsersMigrationTest | 1 test class | Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (no inbound) | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | T2 | T2 -> T3 | Match |
| T4 | T3 | T3 -> T4 | Match |
| T5 | T4 | T4 -> T5 | Match |
| T6 | T5 | T5 -> T6 | Match |
| T7 | T6 | T6 -> T7 | Match |
| T8 | T7 | T7 -> T8 | Match |

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | Compose / init SQL | none | none | OK |
| T2 | Compose / init SQL | none | none | OK |
| T3 | Env / phpunit / database.php | none | none | OK |
| T4 | Env / phpunit / database.php | none | none | OK |
| T5 | Env / phpunit / database.php | none | none | OK |
| T6 | Env / phpunit / database.php | none | none | OK |
| T7 | MySQL connection isolation | integration | integration | OK |
| T8 | Migrated users columns | integration | integration | OK |

## Task to AC map

| Task | ACs | Requirement IDs |
| ---- | --- | --------------- |
| T1 | AC-001 | MYSQL-01 |
| T2 | AC-006, AC-008, AC-015 | MYSQL-03, MYSQL-06 |
| T3 | AC-002, AC-013 | MYSQL-02, MYSQL-05 |
| T4 | AC-003, AC-004, AC-005 | MYSQL-02 |
| T5 | AC-006, AC-007, AC-012 | MYSQL-03, MYSQL-05 |
| T6 | AC-011, AC-012 | MYSQL-05 |
| T7 | AC-004, AC-008, AC-009, AC-010 | MYSQL-04 |
| T8 | AC-014, AC-016, AC-017, AC-018 | MYSQL-06 |
