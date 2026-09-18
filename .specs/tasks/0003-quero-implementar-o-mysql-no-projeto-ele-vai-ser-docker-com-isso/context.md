# Task Context

## Relevant Documentation

- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — **RNF06**: schema only via Laravel migrations; no SQL dump as database creation. RNF05 is MySQL 8. RF03/RF07 name room and reservation fields but those modules do not exist yet.
- `docs/adr/002-usuario-minimo-uuidv7-argon2-auth-laravel.md` — `users` contract: `id` (UUID v7 / `$table->uuid()`), `name`, `email` (unique), `password`, `created_at`, `updated_at`, `deleted_at` (`$table->softDeletes()`). `remember_token` exists for Laravel session runtime, not as a create field. No `email_verified_at`, no bigint PK.
- `docs/context.md` — stack already lists MySQL 8; one Laravel + Inertia project.
- `docs/architecture.md` — persistence path is Eloquent / MySQL; Domain stays pure PHP.
- `docs/tree.md` — Eloquent models and repositories live under `app/Modules/<Module>/Infra/Database`.
- `docs/test/integration.md` — Feature/controller tests must use a real isolated MySQL 8 test database; silent SQLite substitution is forbidden.
- `docs/test/e2e.md` — browser flows also target a MySQL test database (no E2E work in this task).
- `docs/test/unit.md` — unit tests must not hit the real database (fakes/mocks at repository boundary).
- `docs/reviews/review-tests.md` — reviewers must check the approved DB reset/isolation strategy and reject a different engine when it invalidates behavior.
- `docs/persona-agent.md` — migrations are the expected Laravel schema tool.
- `harness/stack.yml` — infrastructure.databases already includes `mysql`; app commands: `php artisan test`, `--testsuite=Unit`, `--testsuite=Feature`, `vendor/bin/pint --test`.
- Laravel docs (Context7 `/laravel/docs`): columns via `Schema::create` / `Schema::table` in migration files; `php artisan migrate` applies them; `RefreshDatabase` migrates if needed then wraps each test in a transaction; `schema:dump` is optional load, not a substitute for migrations (RNF06).

## Relevant Components

- `app` (root `.`): Laravel 12, Eloquent, PHPUnit 11. PHP runs on the host; MySQL will run in Docker and publish `3306`.
- No existing Compose file. `laravel/sail` is in `require-dev` but unused — do not switch this task to Sail.
- No CI workflow in-tree. CI values are specified for a future job; this task does not add GitHub Actions.
- No `database/schema` dump file. Do not add one as the only schema.

## Relevant Code

- `database/migrations/0001_01_01_000000_create_users_table.php` — already complete vs ADR-002 / create-user spec: `uuid` PK `id`, `name`, unique `email`, `password`, `rememberToken()`, `timestamps()`, `softDeletes()`. Also creates `sessions` with `foreignUuid('user_id')`. **Do not rewrite; do not add an alter-users migration.**
- `database/migrations/0001_01_01_000001_create_cache_table.php` and `0001_01_01_000002_create_jobs_table.php` — Laravel skeleton (`cache`, `jobs`). Not RF product columns.
- No `rooms` / `reservations` migrations. RF03 (id, name, capacity, situation, created_at) and RF07 (room, responsible, title, start, end, participants) wait for those modules.
- `.env.example` — `DB_CONNECTION=sqlite`; MySQL host/port/database/user/password are commented Laravel defaults (`laravel` / `root` / empty password).
- `config/database.php` — `'default' => env('DB_CONNECTION', 'sqlite')`; `mysql` connection already maps `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- `phpunit.xml` — `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` (overrides any `.env` and is why Feature tests never touch MySQL today).
- `.env.testing` — missing. `.gitignore` ignores `.env` / `.env.backup` / `.env.production` / `.env.mcp` but not `.env.testing` or `.env.example`.
- Feature tests already use `RefreshDatabase`: `tests/Feature/User/CreateUserPersistenceTest.php`, `CreateUserHttpTest.php`, `UserSchemaTest.php`. They assert the ADR-002 attribute allowlist after migrate; they do not `Schema::create` tables.
- Unit tests (`tests/Unit/User/CreateUserTest.php`) extend `PHPUnit\Framework\TestCase` with a fake repository — they must stay DB-free.
- `composer.json` `setup` runs `artisan migrate --force` (will need MySQL up after the env switch). `post-create-project-cmd` still touches `database/database.sqlite` (create-project only; leave unless a one-line cleanup is cheap).
- `SESSION_DRIVER` / `QUEUE_CONNECTION` / `CACHE_STORE` are `database` in `.env.example`; phpunit already forces `array` / `sync` / `array` so tests do not persist sessions/jobs/cache.
- Official MySQL image creates only `MYSQL_DATABASE` on first boot. Extra test DB requires `/docker-entrypoint-initdb.d` (runs only on an empty `mysql_data` volume). That script may create the empty database and GRANT only — no `CREATE TABLE` / `ALTER TABLE` / column DDL.

## Existing Constraints

- User-supplied service names and credentials are frozen: image `mysql:8.0`, container `painel_mysql`, database `painel_administrativo`, user `painel`, password `painel`, root password `root`, port `3306:3306`, volume `mysql_data`.
- Runtime persistence must be MySQL 8, not SQLite.
- **RNF06 / human revision:** PHP/Laravel must create and change table columns only through `database/migrations`. Compose init SQL is not the schema. Tests are not the schema. `php artisan schema:dump` / `database/schema` is not the only schema.
- Tests must not wipe the development volume (`mysql_data` / `painel_administrativo`).
- Host PHP connects with `DB_HOST=127.0.0.1` (published port). `DB_HOST=mysql` is only correct if PHP later runs inside the Compose network.
- PHP needs `pdo_mysql` on the host.
- Do not commit `.env`. Local passwords in examples are the user-given challenge values (dev-only).

## Important Decisions

- Keep the approved approach: root `compose.yml` with the user service block unchanged; additive `healthcheck` + init mount that creates empty `painel_administrativo_test` and GRANTs `painel`.
- App `.env` / `.env.example`: `painel_administrativo` on `127.0.0.1:3306` as `painel` / `painel`.
- Feature/Unit PHPUnit process: same MySQL instance, isolated schema `painel_administrativo_test` (not sqlite `:memory:`).
- `phpunit.xml` is the enforced test source of truth; set `force="true"` on all `DB_*` keys so a developer `.env` cannot point tests at the dev database.
- Commit `.env.testing` with the same test values (not gitignored) for `artisan --env=testing` and to answer the user request.
- `RefreshDatabase` remains the reset strategy on Feature tests (Laravel docs: migrate if needed, then per-test transactions). That is how test MySQL gets columns — by running `database/migrations`.
- Apply schema on empty MySQL with `php artisan migrate` (local) / `RefreshDatabase` (tests). Existing users migration is the source of truth; it is already complete vs ADR-002, so no rewrite and no extra alter migration.
- Room/reservation tables are out of this task. Their columns land in later module migrations.
- Init SQL grants `painel` on `painel_administrativo_test`. If `mysql_data` already exists, Execute must create that empty database once with root (`CREATE DATABASE` + `GRANT` only).
- No Sail, no second MySQL container, no CI workflow file, no E2E/Playwright change, no production hardening of these passwords.
