# Task Context

## Relevant Documentation

- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RNF05 MySQL 8; RNF06 schema only via Laravel migrations; RNF11 complete `.env.example`, real `.env` untracked; RNF12 prepare and run the project from the README alone.
- `docs/adr/002-usuario-minimo-uuidv7-argon2-auth-laravel.md` — `HASH_DRIVER=argon` (Argon2i). Do not invent another hasher.
- `docs/adr/003-react-e-php-no-mesmo-projeto-laravel.md` — one Laravel app + Inertia React; one `.env`; one README. App is not Dockerized.
- `docs/context.md` — PHP 8.2+, Laravel 12, Inertia/React, MySQL 8.
- `docs/architecture.md` / `docs/tree.md` — monolith layout; not needed for README prose beyond naming the single app.
- `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md` — README is not a use case, FormRequest, Laravel HTTP+MySQL flow, or browser workflow. No punctual tests at those levels.
- Current `README.md` — Portuguese login note plus the three default administrators after `php artisan migrate`. No Docker, env, or run-all instructions.

## Relevant Components

- `app` (repo root) — only `README.md` is in scope.
- Docker MySQL lives in `compose.yml` (`mysql:8.0`, service `mysql`, container `painel_mysql`, port `3306:3306`, volume `mysql_data`). Init `docker/mysql/init/01-create-test-database.sql` creates `painel_administrativo_test` for PHPUnit; do not document that as the local app database.
- Laravel env contract: `.env.example`. Do not edit it unless Execute finds a real inconsistency (none found: `APP_KEY` empty on purpose; `DB_*` names match Compose; `HASH_DRIVER=argon`).
- `.env.testing` and `.env.mcp.example` are not local-run docs.

## Relevant Code

- `composer.json` scripts:
  - `setup`: `composer install`, copy `.env.example` → `.env` if missing, `php artisan key:generate`, `php artisan migrate --force`, `npm install`, `npm run build`. Fails if MySQL 8 is down.
  - `dev`: `npx concurrently` — `php artisan serve`, `php artisan queue:listen --tries=1 --timeout=0`, `php artisan pail --timeout=0`, `npm run dev`. This is the “run the whole app” command.
- `package.json` — `dev` = Vite; `build` = `vite build`. No Node engine pin.
- `database/migrations/2026_09_19_000000_insert_default_administrators.php` — default admins on migrate, not `db:seed`. `DatabaseSeeder::run` is empty.
- `php artisan serve` default URL is `http://127.0.0.1:8000`. `.env.example` `APP_URL` is `http://localhost`.
- Required `.env` **names** that the README may list as required (copied from `.env.example`; do not invent; do not print values in README):
  - Generate, must not stay empty: `APP_KEY` via `php artisan key:generate`.
  - Database: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- Do **not** list as required: `APP_URL`, `HASH_DRIVER`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE`, `REDIS_*`, `AWS_*`, `MAIL_*`, `VITE_APP_NAME`, `BCRYPT_ROUNDS`, `LOG_*`, `MEMCACHED_*`, locale/maintenance keys, Compose `MYSQL_*`, MCP tokens.

## Existing Constraints

- README body in Portuguese (user request). Spec/plan artifacts stay English.
- Do not paste env values, Compose passwords, or generated keys. Keep the existing default-admin table (names/emails/password already shipped for RNF12 login).
- Do not add CONTRIBUTING, Dockerize PHP/Vite, or document `php artisan db:seed` / SQLite (`post-create-project-cmd` leftover).
- Do not change product code, tests, or `.env.example` unless Execute proves the example file cannot support the documented steps.
- No Playwright project in `package.json`. Do not add README tests that invent a new test level.

## Important Decisions

- Scope is `README.md` only. Design skipped (docs change, no new architecture).
- Document host-run app + Docker **only** for MySQL 8 (`docker compose up -d` on `compose.yml`, image `mysql:8.0`). Start Compose and wait until healthy before migrate/`composer run setup`.
- Document both the explicit steps (copy env, `key:generate`, `php artisan migrate`, `composer run dev`) and `composer run setup` as an optional shortcut after Docker is up.
- Keep the current title, login intro, and default-admin table. Add prerequisites: PHP 8.2+, Composer, Node.js + npm, Docker Engine with Compose v2. No invented Node version.
- Punctual unit / integration / e2e tests are not applicable. After Execute, stack verify still runs `test`, `lint`, and `build` on `app`.
