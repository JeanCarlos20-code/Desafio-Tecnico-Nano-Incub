# Task Context

## Relevant Documentation

- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF01 is login; RF02 is guest lockout; RF19 asks a seeder with at least one administrator plus rooms and reservations. This task fulfills the three known administrators via a Laravel migration (user request) and does not ship rooms/reservations seed data.
- `docs/adr/002-usuario-minimo-uuidv7-argon2-auth-laravel.md` — `users` columns `id` (UUIDv7), `name`, `email`, `password`, timestamps, `deleted_at`, `remember_token`. Passwords use Laravel `HASH_DRIVER=argon` (Argon2i). Soft-deleted emails stay unique.
- `docs/screens/screen-login.md` — currently documents divider `Não tem uma conta?` and `Ir para o cadastro` → `/register`. Update those lines and the matching acceptance checkbox. Keep `/` and `/login`, CSRF, session regenerate, generic credentials error, no password recovery.
- `docs/screens/screen-create-user.md` — delete this file. User authorized dropping the registration screen doc.
- `docs/architecture.md` / `docs/tree.md` — Domain ← Application ← Infra; thin controllers; Inertia pages under `resources/js/Pages`.
- `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md` — unit = isolated use case / FormRequest off-route / React; integration = Laravel HTTP + MySQL 8; e2e = browser only. No Playwright in `package.json`.
- `README.md` — still advertises public `/register` and seeder user `test@example.com` / `password`. Replace with the three default accounts.

## Relevant Components

- `app/Modules/User` — `CreateUser` is a thin `UserRepository::create` wrapper. `UserController` + `StoreUserRequest` render `User/Create` and POST-register with auto-login to `reservations.index`. `AuthenticateUser` / `LoginController` stay.
- `routes/web.php` — guest group exposes `GET/POST /register` and aliases `GET /users/create` + `POST /users`.
- `resources/js/Pages/User/Create.jsx` + `Services/users.js` — only callers of `/register`.
- `resources/js/Pages/User/Login.jsx` — cadastro divider + `Link` to `/register`. Shared `BrandPanel` (`data-layout="register-hero"`, `/images/register-hero.jpg`) stays; login still uses it.
- `database/migrations/0001_01_01_000000_create_users_table.php` — schema only. Do not rewrite it.
- `database/seeders/DatabaseSeeder.php` — factory-creates `Test User` / `test@example.com`. Remove that insert so seed does not add a fourth admin.

## Relevant Code

- `UserController::store` validates, `CreateUser::execute`, `Auth::login`, redirect `reservations.index`.
- `StoreUserRequest` unique email, `Password::defaults()`, lowercases/trims email.
- Tests to delete or replace: `tests/Unit/User/CreateUserTest.php`, `tests/Feature/User/CreateUserHttpTest.php`, `tests/Feature/User/CreateUserPersistenceTest.php`, `resources/js/Pages/User/Create.test.jsx`, `resources/js/Services/users.test.js`.
- `Login.test.jsx` asserts the cadastro divider/link (including the processing-state case).
- `tests/Feature/Database/UsersMigrationTest.php` — column contract only.
- `tests/Feature/Database/MysqlConnectionTest.php` — `assertDatabaseCount('users', 1)` after one factory row; becomes 4 once migrate inserts three defaults.
- `LoginHttpTest` already covers session regenerate, throttle, and factory-user login. Add default-account login without repeating that matrix.
- `welcome.blade.php` wraps Register in `Route::has('register')`; removing the named route hides the link. Not the app home (`/` is login).
- `phpunit.xml` Application coverage includes `app/Modules/User/Application`. After deleting `CreateUser`, only `AuthenticateUser` remains there; Room + Reservation Application still carry the 80% gate.
- `UserRepository::create` / `existsByEmail` have no remaining production caller once `CreateUser` is gone. Leave the port and `EloquentUserRepository` unchanged.

## Existing Constraints

- RNF06: schema and default rows only through Laravel migrations (no SQL dump).
- RNF10: persist Argon hashes, never plaintext passwords in MySQL. The requested passphrase `Senha123` may appear only as the `Hash::make` argument in the migration and in README/docs.
- RNF07 / RF01: keep Laravel session login. Do not invent a second auth path.
- Do not edit ADR-001 or ADR-002. RF19 rooms/reservations seed stays out of this task.
- Domain/Application stay free of Illuminate. The data migration may use `Hash`, `Str::uuid7()`, and `DB`.
- Existing Feature suites that factory-create users remain valid; they just see three extra migrated rows.
- No Playwright. Do not bootstrap an e2e runner.
- Do not weaken remaining `LoginHttpTest` or `AuthenticateUserTest` contracts.

## Important Decisions

- **Where the three accounts live:** a new data migration after `create_users_table`, not an edit of `0001_01_01_000000` and not `DatabaseSeeder`. Already-migrated local databases get the rows on the next `migrate`.
- **Accounts:** Gertrudes `teste@mail.com`, Marcelo `teste2@mail.com`, Emerson `teste3@mail.com`, password `Senha123` for all three. Insert with `Str::uuid7()` ids, `Hash::make('Senha123')`, null `remember_token` / `deleted_at`, timestamps now.
- **Public cadastro:** remove guest routes, `UserController`, `StoreUserRequest`, `CreateUser`, Inertia `User/Create`, `Services/users.js`, and the login cadastro controls. Guests hit 404 on the old URLs (named routes gone — tests call the path strings).
- **Seeder:** stop creating `test@example.com`. Administrators for local login come from the migration. Combined RF19 rooms/reservations seed is a later task.
- **Docs:** delete `docs/screens/screen-create-user.md`; strip cadastro from `screen-login.md` and README.
- **Tests:** Vitest for the login UI without cadastro; Feature/MySQL for migrated rows + Hash::check + 404s + POST `/login` with each default account. E2E not applicable.
