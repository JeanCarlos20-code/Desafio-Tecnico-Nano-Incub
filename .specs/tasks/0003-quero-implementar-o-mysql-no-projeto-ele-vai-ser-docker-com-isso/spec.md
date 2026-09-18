# Specification

## Context

Painel-administrativo is a Laravel 12 + Inertia monolith. Docs and `harness/stack.yml` already name MySQL 8, but the running app still defaults to SQLite (`.env.example`, `config/database.php`) and PHPUnit uses `sqlite` / `:memory:`. The user wants MySQL 8 in Docker with a fixed service contract and PHP persisting application data there. They also asked to see the exact test environment variables. A plan revision adds **RNF06**: PHP must add and change columns only through Laravel migrations.

## Problem

## Problem Statement

The application cannot persist challenge data on MySQL 8 because no Compose service exists and Laravel/PHPUnit still target SQLite. Feature tests that use `RefreshDatabase` would either miss MySQL-specific behavior or, if pointed at the same schema as local development, wipe the `mysql_data` volume. Creating tables or columns in Compose init SQL, in tests via `Schema::`, or via a schema dump alone would violate RNF06.

## Goal

- Run the user-specified MySQL 8 container and connect Laravel to it for application persistence.
- Keep Feature tests on MySQL 8 using an isolated database so `RefreshDatabase` never migrates or truncates `painel_administrativo`.
- Publish the exact local, testing, phpunit, and CI `DB_*` values.
- Materialize required columns on MySQL 8 only by running `database/migrations` (`php artisan migrate` / `RefreshDatabase`).

## User Stories

### P1: Persist application data on Docker MySQL 8

**User Story**: As a developer, I want Laravel to use the Docker MySQL 8 instance (`painel_mysql`) so that rooms, reservations, and users are stored in `painel_administrativo` instead of SQLite.

**Why P1**: This is the requested runtime outcome.

**Acceptance Criteria**:

1. WHEN `docker compose up -d` is run from the worktree root THEN the system SHALL start container `painel_mysql` from image `mysql:8.0` with `restart: unless-stopped`, port mapping `3306:3306`, volume `mysql_data` mounted at `/var/lib/mysql`, and environment `MYSQL_DATABASE=painel_administrativo`, `MYSQL_USER=painel`, `MYSQL_PASSWORD=painel`, `MYSQL_ROOT_PASSWORD=root`.
2. The committed `.env.example` SHALL set `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=painel_administrativo`, `DB_USERNAME=painel`, `DB_PASSWORD=painel`.
3. The Laravel default connection fallback in `config/database.php` SHALL be `mysql` (not `sqlite`).
4. WHEN a Feature persistence test or `CreateUser` writes a user THEN the system SHALL store that row in MySQL database `painel_administrativo` for local/runtime `.env` and SHALL NOT use the `sqlite` driver for application runtime.
5. IF the MySQL container is not reachable on `127.0.0.1:3306` THEN Feature tests and `php artisan migrate` SHALL fail with a connection error and SHALL NOT silently fall back to SQLite.

**Independent Test**: Start Compose, copy `.env.example` → `.env`, `php artisan migrate`, create a user through the existing use case or HTTP flow, inspect `painel_administrativo.users` in MySQL.

---

### P1: Isolated MySQL test environment

**User Story**: As a developer, I want PHPUnit to use a separate MySQL database on the same instance so that `RefreshDatabase` cannot wipe my development volume.

**Why P1**: Project test docs forbid SQLite substitution for integration tests, and the user asked for the exact test env vars.

**Acceptance Criteria**:

1. WHEN PHPUnit starts THEN the process SHALL use `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=painel_administrativo_test`, `DB_USERNAME=painel`, `DB_PASSWORD=painel`.
2. The `phpunit.xml` `DB_*` entries SHALL set `force="true"` so a local `.env` pointing at `painel_administrativo` cannot override the test database.
3. WHEN a Feature test uses `RefreshDatabase` THEN the system SHALL migrate and wrap transactions only on `painel_administrativo_test`.
4. IF a Feature test asserts the configured database name THEN the system SHALL report `painel_administrativo_test` and SHALL NOT report `painel_administrativo`, `:memory:`, or a sqlite file path.
5. Unit tests that extend `PHPUnit\Framework\TestCase` with fakes SHALL keep passing without inserting rows into MySQL.

**Independent Test**: With Compose up and the test database present, run `php artisan test --testsuite=Feature` and `php artisan test --testsuite=Unit`; confirm `users` in `painel_administrativo` is unchanged after Feature tests.

---

### P1: Document testing and CI variables

**User Story**: As a developer, I want `.env.testing` and the planned CI values written down so that I know how the test environment looks.

**Why P1**: Explicit user request.

**Acceptance Criteria**:

1. WHEN the repo is cloned THEN `.env.testing` SHALL exist with the testing `DB_*` values listed in Selected Approach.
2. The specification and implementation plan SHALL list the CI `DB_*` values as identical to PHPUnit (same isolated database), assuming a job that starts this Compose file.
3. The system SHALL NOT commit a developer `.env` file.

**Independent Test**: Open `.env.testing` and `phpunit.xml`; values match the tables below; `.env` remains gitignored.

---

### P1: Schema columns via Laravel migrations (RNF06)

**User Story**: As a developer, I want MySQL 8 tables and columns to come from Laravel migrations so that PHP applies the challenge schema with `php artisan migrate` instead of raw SQL or dumps.

**Why P1**: Human revision; ADR-001 RNF06 is eliminatory.

**Acceptance Criteria**:

1. The system SHALL create and change application table columns only through Laravel migration files under `database/migrations`.
2. IF Compose `/docker-entrypoint-initdb.d` SQL runs THEN the system SHALL create only the empty `painel_administrativo_test` database and GRANT `painel`; it SHALL NOT `CREATE TABLE`, `ALTER TABLE`, or declare columns.
3. WHEN `php artisan migrate` or `RefreshDatabase` runs against MySQL 8 THEN the system SHALL create `users` with columns `id`, `name`, `email`, `password`, `remember_token`, `created_at`, `updated_at`, and `deleted_at` from `0001_01_01_000000_create_users_table.php`.
4. Feature tests SHALL inspect the migrated `users` column list and SHALL NOT create the application `users` table with `Schema::create` / `Schema::table` as the schema source of truth.
5. The repository SHALL NOT use `php artisan schema:dump` or `database/schema` as the only schema.

**Independent Test**: After Compose + `php artisan migrate` (or Feature `RefreshDatabase`), `Schema::getColumnListing('users')` matches the ADR-002 set; init SQL file contains no table/column DDL.

## Acceptance Criteria

Consolidated identifiers (same SHALL statements as the stories):

| ID | SHALL (short) |
| -- | ------------- |
| AC-001 | Compose starts `painel_mysql` with the frozen image, names, credentials, port, and `mysql_data` volume |
| AC-002 | `.env.example` documents local MySQL `DB_*` for `painel_administrativo` |
| AC-003 | `config/database.php` default fallback is `mysql` |
| AC-004 | Runtime persistence uses MySQL, not SQLite |
| AC-005 | Missing MySQL does not fall back to SQLite |
| AC-006 | PHPUnit uses `painel_administrativo_test` on the same MySQL instance |
| AC-007 | `phpunit.xml` forces `DB_*` so `.env` cannot retarget tests at the dev schema |
| AC-008 | `RefreshDatabase` only touches the test database |
| AC-009 | Feature assertions see the test database name, never sqlite or the dev schema |
| AC-010 | Fake-based unit tests stay green without MySQL writes |
| AC-011 | `.env.testing` is committed with the test `DB_*` values |
| AC-012 | CI `DB_*` are documented as equal to PHPUnit |
| AC-013 | `.env` is not committed |
| AC-014 | Application columns are created/changed only in `database/migrations` |
| AC-015 | Compose init SQL creates the empty test database + GRANT only (no table/column DDL) |
| AC-016 | `php artisan migrate` / `RefreshDatabase` apply ADR-002 `users` columns on MySQL 8 |
| AC-017 | Tests inspect migrated columns; they are not the schema source of truth |
| AC-018 | Schema dump is not the only schema |

### Exact environment variables

Local / `.env` (not committed) and `.env.example`:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=painel_administrativo
DB_USERNAME=painel
DB_PASSWORD=painel
```

`.env.testing` (committed) and PHPUnit / CI:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=painel_administrativo_test
DB_USERNAME=painel
DB_PASSWORD=painel
```

`phpunit.xml` (enforced; `force="true"` on every line):

```xml
<env name="DB_CONNECTION" value="mysql" force="true"/>
<env name="DB_HOST" value="127.0.0.1" force="true"/>
<env name="DB_PORT" value="3306" force="true"/>
<env name="DB_DATABASE" value="painel_administrativo_test" force="true"/>
<env name="DB_USERNAME" value="painel" force="true"/>
<env name="DB_PASSWORD" value="painel" force="true"/>
<env name="DB_URL" value="" force="true"/>
```

Keep existing phpunit overrides (`APP_ENV=testing`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`). Remove `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`.

`MYSQL_ROOT_PASSWORD=root` is Docker-only; Laravel does not use the root user.

## Edge Cases

- IF `mysql_data` already exists from a previous start THEN `/docker-entrypoint-initdb.d` SHALL NOT run again; Execute SHALL create `painel_administrativo_test` once via root (`CREATE DATABASE` + `GRANT` only, no table DDL) so tests still isolate.
- IF PHPUnit ran without `force="true"` and `.env` said `DB_DATABASE=painel_administrativo` THEN tests could migrate the dev schema — the forced phpunit values exist to prevent that.
- IF host PHP lacks `pdo_mysql` THEN the mysql connection SHALL fail at PDO load (install the extension; do not add a sqlite fallback).
- IF two Feature test processes share `painel_administrativo_test` THEN `RefreshDatabase` transactions MAY conflict; default is a single PHPUnit process (no parallel suite in this task).
- WHEN connecting from the host THEN `DB_HOST` SHALL be `127.0.0.1`, not `localhost` (avoids a Unix socket that does not exist) and not `mysql` (Compose DNS is invisible to host PHP).
- IF Execute added `CREATE TABLE` to init SQL to “help” MySQL THEN that SHALL fail AC-015 / RNF06; columns stay in migrations.
- IF a Feature test called `Schema::create('users', …)` to build columns THEN that SHALL fail AC-017; `RefreshDatabase` must apply `database/migrations`.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Laravel Sail as the runtime | User supplied a concrete Compose service; Sail is unused `require-dev` |
| Second MySQL container only for tests | Same instance + extra database is enough isolation |
| SQLite in-memory Feature tests | Forbidden by `docs/test/integration.md` and the persistence goal |
| GitHub Actions / CI workflow file | No workflow exists; values are documented only |
| Playwright / E2E database wiring | No E2E suite to change in this task |
| Production password policy, TLS, or bind restrictions | Challenge/local credentials were given and must stay |
| Putting the PHP app itself in Docker | Host PHP + published 3306 is enough |
| Redis, queues, or cache infrastructure | `stack.yml` has none; phpunit already uses array/sync |
| New domain modules or User behavior changes | Persistence engine only |
| Rooms / reservations migrations (RF03–RF07 columns) | No Room/Reservation module yet; later features add those migrations |
| Rewrite or alter of the existing users migration | Already complete vs ADR-002; still unused in production MySQL |
| Committing a `database/schema` dump as the schema | Violates RNF06 |
| Committing `.env` | Secrets/local file; gitignored |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Test engine | Isolated MySQL database `painel_administrativo_test` on `painel_mysql` | Matches integration.md; RefreshDatabase cannot see the dev schema; Laravel Sail pattern | n |
| PHP location | Host process, `DB_HOST=127.0.0.1` | No app container in the given Compose file | n |
| Test credentials | Same `painel` / `painel` user, different database name | User froze credentials; isolation is by schema name | n |
| Reset strategy | Keep `RefreshDatabase` on Feature tests | Already used; Laravel docs: migrate then transaction | n |
| phpunit vs `.env` | `force="true"` on all `DB_*` | Prevents wiping `mysql_data` if `.env` is loaded first | n |
| Test DB provisioning | Init SQL in `docker/mysql/init` plus documented one-time root create | Official image only auto-creates `MYSQL_DATABASE` | n |
| Compose extras | Allow healthcheck + init volume; do not rename service/user/db | Needed for readiness and the test schema; names stay frozen | n |
| CI | Same `DB_*` as PHPUnit; no workflow committed | User asked how CI vars look; repo has no `.github/workflows` | n |
| Unit tests | Remain fake-based; phpunit env may still be mysql | `docs/test/unit.md`; existing `CreateUserTest` does not boot Laravel | n |
| Users columns | Keep `0001_01_01_000000_create_users_table.php` unchanged | Inventory matches ADR-002; rewrite would be noise | n |
| Future RF tables | Do not add rooms/reservations migrations in this task | ADR-001: do not invent modules; RNF06 still applies when those features ship | n |
| Schema dump | Do not add `database/schema` | RNF06: migrations are the schema | n |

**Open questions:** none - all resolved or logged above.

## Considered Approaches

| Option | Pros | Cons | Decision |
| ------ | ---- | ---- | -------- |
| A. User Compose + Laravel `.env` MySQL + isolated `painel_administrativo_test` + schema only via existing migrations | Meets persistence goal; honors frozen names; tests cannot migrate the dev volume; satisfies RNF06 | Feature tests need Docker up; first-boot-only init script | **Selected** |
| B. Feature tests keep `sqlite` `:memory:` | Fast, no Docker for PHPUnit | Violates `docs/test/integration.md`; misses MySQL UUID/unique/transaction semantics | Rejected |
| C. PHPUnit uses `painel_administrativo` (same as app) | One database to create | `RefreshDatabase` wipes developer data on `mysql_data` | Rejected |
| D. Adopt Laravel Sail | Official second `testing` database; known docs | Different service names/layout than the user YAML | Rejected |
| E. Second Compose service `mysql_test` | Hard isolation | Extra container, extra port, unused given the frozen single service | Rejected |
| F. Create `users` / rooms tables in Compose init SQL | MySQL would have tables before PHP runs | Violates RNF06 and the human revision | Rejected |
| G. New alter-users migration or rewrite of the users file | Would look like “we added migrations” | Current file already has every ADR-002 column; unused in production MySQL | Rejected |
| H. `schema:dump` as the only schema | Faster migrate in some CI setups | Violates RNF06 (“sem dump SQL como criação do banco”) | Rejected |

## Selected Approach

Option A.

Compose file: `compose.yml` at the repo root. Keep the user `services.mysql` / `volumes.mysql_data` block as given. Execute may add only:

- `./docker/mysql/init:/docker-entrypoint-initdb.d` to create and grant empty `painel_administrativo_test` (no table/column DDL)
- a `mysqladmin ping` healthcheck using the given root password

Laravel runtime: `.env.example` + `config/database.php` fallback → mysql / `painel_administrativo`.

Laravel tests: `phpunit.xml` (forced) + committed `.env.testing` → mysql / `painel_administrativo_test`. Feature tests keep `RefreshDatabase`, which applies `database/migrations`.

Schema: leave `0001_01_01_000000_create_users_table.php` as the users source of truth. After MySQL is up, `php artisan migrate` creates ADR-002 columns on `painel_administrativo`; tests do the same on `painel_administrativo_test`. A Feature test asserts `Schema::getColumnListing('users')` equals that set. Do not add rooms/reservations migrations here.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| MYSQL-01 | P1: Persist application data on Docker MySQL 8 | Execute | Verified |
| MYSQL-02 | P1: Persist application data on Docker MySQL 8 | Execute | Verified |
| MYSQL-03 | P1: Isolated MySQL test environment | Execute | Verified |
| MYSQL-04 | P1: Isolated MySQL test environment | Execute | Verified |
| MYSQL-05 | P1: Document testing and CI variables | Execute | Verified |
| MYSQL-06 | P1: Schema columns via Laravel migrations (RNF06) | Execute | Verified |

**ID format:** `MYSQL-NN`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

**Coverage:** 6 total, 6 mapped to tasks, 0 unmapped

- MYSQL-01 = AC-001 (Compose service)
- MYSQL-02 = AC-002, AC-003, AC-004, AC-005 (Laravel runtime)
- MYSQL-03 = AC-006, AC-007, AC-008 (phpunit isolation)
- MYSQL-04 = AC-009, AC-010 (Feature assertion + unit stay fake)
- MYSQL-05 = AC-011, AC-012, AC-013 (`.env.testing`, CI table, no `.env` commit)
- MYSQL-06 = AC-014, AC-015, AC-016, AC-017, AC-018 (migrations are the schema)
