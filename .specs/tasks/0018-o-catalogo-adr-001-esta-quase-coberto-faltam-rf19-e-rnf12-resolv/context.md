# Task Context

## Relevant Documentation

- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF19 seeder (admin + some rooms + some reservations). RNF07: authentication via official starter kit or Laravel Breeze; do not recreate login. RNF12: prepare and run from the README alone. RNF06: schema via migrations only. Do not edit this ADR.
- `docs/adr/002-usuario-minimo-uuidv7-argon2-auth-laravel.md` — User contract stays: UUID v7, soft delete, `HASH_DRIVER=argon`, no `email_verified_at`, model `App\Modules\User\Infra\Database\Models\User`. The “native auth without a kit” slice is superseded by a new ADR-007 (status line only; do not rewrite the decision body).
- `docs/adr/004-modulo-rooms-ciclo-de-vida-minimo.md` / `docs/adr/005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md` — seeder rows must match those tables and RF13–RF18 (consecutive end/start allowed; overlap is `starts_at < other.ends_at AND ends_at > other.starts_at`).
- `docs/architecture.md` / `docs/tree.md` — modules under `app/Modules/<Name>/{Domain,Application,Infra}`. Login currently uses a User Application use case; Breeze’s idiom is `LoginRequest::authenticate()`. ADR-007 records that exception. Seeders stay in `database/seeders`.
- `docs/screens/screen-login.md` — keep `User/Login`: GET `/` and `/login`, POST `/login`, success → `/reservations`, banner field `credentials`, no cadastro / password recovery.
- `docs/test/unit.md` — FormRequest input contract is unit (no HTTP/DB). Forbids artificial units for simple seeders. Do not unit-test `Auth::attempt`.
- `docs/test/integration.md` — Feature + real MySQL 8 + `RefreshDatabase` for HTTP login and persisted seeder effects.
- `docs/test/e2e.md` — browser only for selected full UI flows. `package.json` has no Playwright project; `stack.yml` lists `npx playwright test` but it is not runnable. Do not bootstrap Playwright here.
- Current `README.md` (task 0017) — Portuguese local-run path already has Docker MySQL 8, env **names**, `key:generate`, `composer install` / `npm install`, `php artisan migrate`, optional `composer run setup`, `composer run dev`. It still says `db:seed` is not required.

## Relevant Components

- `composer.json` — Laravel 12, `inertiajs/inertia-laravel` ^3.3. No `laravel/breeze` today. `setup` does not seed.
- `app/Modules/User` — custom `LoginController` + `AuthenticateUser` + `LaravelUserAuthenticator` (`Auth::attempt`, remember false). `LoginRequest` validates and throttles but does not authenticate. `config/auth.php` already points at the module User model.
- `routes/web.php` — guest: `/`, `/login`, POST `/login` (`login.store`); auth: POST `/logout` plus rooms/reservations. Task 0016 removed public register.
- `resources/js/Pages/User/Login.jsx` + `Services/session.js` — post `/login`; existing Vitest forbids `/register` and recovery.
- `database/seeders/DatabaseSeeder.php` — `run()` is empty. Factories: `RoomFactory`, `ReservationFactory`, `UserFactory` (do not use UserFactory in seeders).
- `database/migrations/2026_09_19_000000_insert_default_administrators.php` — Gertrudes / Marcelo / Emerson on migrate.
- Tests to keep green: `LoginHttpTest`, `DefaultAdministratorLoginHttpTest`, `RemovedRegistrationHttpTest`, `UsersMigrationTest` (schema + extra-admin + no `test@example.com`), `Login.test.jsx`.

## Relevant Code

- Breeze on Laravel 12 (current docs + `laravel/breeze` 2.x): `composer require laravel/breeze --dev` then `php artisan breeze:install react`. The installer publishes controllers, `routes/auth.php` (register / reset / verify / profile), Inertia `Auth/*` pages, and can overwrite `routes/web.php` / Vite / Tailwind / `App\Models\User`. Laravel 12 official starter kits use Fortify; Fortify alone does not match RNF07’s “starter kit or Breeze” text.
- Breeze login stubs to copy after the package is installed (do **not** run `breeze:install` on this app tree):
  - `vendor/laravel/breeze/stubs/inertia-common/app/Http/Controllers/Auth/AuthenticatedSessionController.php` — `store()` calls `$request->authenticate()`, regenerates the session, redirects to `dashboard`.
  - `vendor/laravel/breeze/stubs/default/app/Http/Requests/Auth/LoginRequest.php` — `authenticate()` uses `Auth::attempt` + RateLimiter (5 hits); failed auth on field `email` via `trans('auth.failed')`.
- Adapt those stubs into `app/Modules/User/Infra/Http/...` (tree.md). Render `User/Login`. Redirect to `reservations.index`. Keep POST name `login.store` and URL `/login`. Force remember false. Map failed auth and throttle to `credentials` => `E-mail ou senha inválidos.` so `screen-login.md` / `LoginHttpTest` stay. Keep Portuguese field messages and email normalize.
- `phpunit.xml` coverage include is Application-only (80%). Removing `AuthenticateUser` empties `app/Modules/User/Application` — drop that include path. Room + Reservation Application remain.
- `UserRepository` / `EloquentUserRepository` are leftover from removed CreateUser; leave them (out of scope).
- App timezone UTC. Seed starts at `now()->addDay()`. Overlap: `EloquentReservationRepository::hasActiveOverlap` on active rows (`cancelled_at` null).
- `harness/stack.yml` `app` verify: `test` = `php artisan test && npm run test`; `lint` = `vendor/bin/pint --test && npm run lint`; `build` = `composer run build && npm run build`. Required: `test`, `lint`, `build`.

## Existing Constraints

- RNF07 must be real Breeze login, not a documented deviation. Do not keep a parallel custom `AuthenticateUser` login path.
- Do not run `breeze:install` against this worktree (it would wipe rooms/reservations routes and the login UI).
- Do not reintroduce register, password reset, email verification, JWT, roles, Fortify 2FA, Breeze dashboard/profile, or `App\Models\User` / bigint / `email_verified_at` / bcrypt.
- Reuse the three migrated administrators; seeder inserts no `users` row; never recreate `test@example.com`.
- Seeded reservations must satisfy RF13–RF18. No SQL dump. Do not edit ADR-001. ADRs are immutable — new ADR-007; ADR-002 status/supersede line only.
- Feature tests on MySQL 8 via `RefreshDatabase`. Do not bootstrap Playwright.
- README stays Portuguese. Print no env/Compose secret values. No `CONTRIBUTING.md` / `docs/setup.md`.
- Do not weaken `UsersMigrationTest`, `LoginHttpTest`, `RemovedRegistrationHttpTest`, room/reservation matrices, or Login Vitest.

## Important Decisions

- One task, four sequential phases (RNF07 → leftover-stack cleanup → RF19 → RNF12). No second task.
- Install `laravel/breeze` as a require-dev dependency, then copy **only** the login controller + login FormRequest stubs and adapt them. Do not invoke `breeze:install`.
- Delete `LoginController`, `AuthenticateUser`, `InvalidCredentials`, `UserAuthenticator`, `LaravelUserAuthenticator`, and `AuthenticateUserTest` after the Breeze controller is wired. Unbind the authenticator in `AppServiceProvider`.
- New ADR-007 (MADR, Portuguese, date 2026-09-20): adopt Breeze for RNF07 without abandoning ADR-002’s user schema. Skill `create-adr`.
- Dedicated `RoomSeeder` then `ReservationSeeder` from `DatabaseSeeder`. Deterministic Eloquent rows (not factories, not `CreateReservation`). Catalog: three active rooms (`Sala Reunião Norte` 8, `Sala Treinamento` 20, `Sala Diretoria` 4) and three active future reservations (Norte 09:00–10:00 and consecutive 10:00–11:00 next calendar day; Treinamento 14:00–16:00 next calendar day).
- Seed is one-shot after migrate. Leave `composer run setup` without `--seed`.
- Punctual e2e is not applicable (no Playwright runner). Auth is proven by existing Feature HTTP + Vitest. New unit coverage is LoginRequest validation only.
