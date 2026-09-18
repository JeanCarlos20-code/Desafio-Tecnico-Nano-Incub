# Task Context

## Relevant Documentation

- `docs/adr/004-modulo-rooms-ciclo-de-vida-minimo.md` — `rooms` columns: `id` (UUID v7 via `HasUuids` / `Str::uuid7()`), `name`, `capacity`, `is_active`, `created_at`, `updated_at`, `deleted_at` (`SoftDeletes`). Module owns RF03–RF06 only. Inactive rooms stay listable; soft-deleted rooms drop out of default Eloquent. Delete-with-reservations is an ADR-001 gap; do not invent extra room fields.
- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF03 list, RF04 create, RF05 edit, RF06 delete after confirm, RF02 auth on internal routes, RNF08/RNF14 server Form Requests. RF07–RF19 (reservations, seeder bundle) stay out of this task.
- `docs/adr/002-usuario-minimo-uuidv7-argon2-auth-laravel.md` — persistence pattern to copy: `$table->uuid('id')->primary()`, `HasUuids` (UUIDv7 by default in Laravel 12), timestamps, `SoftDeletes`. No `newUniqueId()` override on User today.
- `docs/adr/003-react-e-php-no-mesmo-projeto-laravel.md` — Inertia pages, not a public REST API.
- `docs/architecture.md` — Controller → Form Request → Use Case → Domain → repository port; Infra Eloquent implements the port. Application/Domain must not import `Illuminate`. Thin HTTP adapters. No extra DTO/service/mapper layers without need.
- `docs/tree.md` — `app/Modules/<Module>/{Domain,Application,Infra}` and `resources/js/Pages/<Feature>/{Index,Create,Edit,Components}`. Module name is `Room` (same singular style as `User`).
- `docs/screens/screen-rooms-list.md` — authenticated `GET /rooms` list; `GET /rooms/create`; `GET /rooms/{room}/edit`; `DELETE /rooms/{room}`; columns ID, Nome, Capacidade, Status (`Ativa`/`Inativa`), Criada em `DD/MM/YYYY`; Nova sala; delete confirm modal; empty/loading/error/flash; sidebar `Salas` current. Visual file `.local/image/screen-rooms-list.png` (not present in this worktree). No create/edit screen markdown exists.
- `docs/test/unit.md` — use cases with fakes, no DB/HTTP; exhaustive input validation; React pages/components/states with Inertia mocked. PHP coverage target ≥80% on Application.
- `docs/test/integration.md` — every controller action: Laravel request + `auth` + Form Request on the route + use case + Eloquent + MySQL 8 (`painel_administrativo_test`). Representative validation only.
- `docs/test/e2e.md` — browser + React + Laravel + MySQL for selected full flows. No Playwright project or config exists; `package.json` has Vitest only. `harness/stack.yml` lists `npx playwright test` but it is not runnable today.

## Relevant Components

- `app` — Laravel 12 + Inertia 2 + React 19 + Tailwind 4 + PHPUnit + Vitest + MySQL 8.
- Existing product module: `app/Modules/User` (pattern to copy, not a fat CRUD template).
- Shared UI: `resources/js/Layouts/AppLayout.jsx` (stub shell + logout only; must become the admin sidebar layout the rooms screen requires).
- Auth already live: `auth` middleware, guest → `/login`, authenticated default → `/reservations` (`bootstrap/app.php`).

## Relevant Code

- `app/Modules/User/Application/UseCases/CreateUser.php` — one use case, constructor-injected Domain port, no Illuminate.
- `app/Modules/User/Domain/Entities/User.php` + `Domain/Repositories/UserRepository.php` — pure PHP entity + port.
- `app/Modules/User/Infra/Database/Models/User.php` — `HasUuids`, `SoftDeletes`, factory; no custom UUID generator.
- `app/Modules/User/Infra/Database/Repositories/EloquentUserRepository.php` — Eloquent → Domain mapping.
- `app/Modules/User/Infra/Http/Controllers/UserController.php` — **do not copy for Room**: `create`+`store` in one class. Login uses a separate `LoginController`. Room must use **one invokable controller per HTTP action**.
- `app/Modules/User/Infra/Http/Requests/StoreUserRequest.php` — Form Request `rules` + Portuguese `messages` + `prepareForValidation`. `authorize(): true` with route middleware.
- `app/Providers/AppServiceProvider.php` — bind Domain ports to Infra implementations.
- `routes/web.php` — guest register/login; `auth` group has logout + stub `GET /reservations`. **No `/rooms` routes.**
- `app/Http/Middleware/HandleInertiaRequests.php` — `share()` empty; layout needs `auth.user.name` and flash.
- `resources/js/Services/users.js` / `session.js` — isolate Inertia `form.post` URLs.
- `resources/js/Pages/Reservation/Index.jsx` — placeholder inside `AppLayout`; must keep working after the shell upgrade.
- Tests to mirror: `tests/Unit/User/CreateUserTest.php` (PHPUnit fake, no Laravel app); `tests/Feature/User/CreateUserHttpTest.php` (`RefreshDatabase`, `withoutVite`, Inertia assertions); `tests/Feature/Database/UsersMigrationTest.php` + `UserSchemaTest.php`; `resources/js/Pages/User/Login.test.jsx` + `Layouts/AppLayout.test.jsx`.
- `phpunit.xml` `<source>` currently includes only `app/Modules/User/Application`. Comment: add each module Application path. Unit gate: `--coverage --min=80`.
- `config/app.php` `timezone` is `UTC`. Format list dates in that timezone as `DD/MM/YYYY`.

## Existing Constraints

- Persist only ADR-004 columns. No location, photo, equipment, unique `name`, or reservation FKs.
- Authenticated administrators only (same session guard as User/login). No roles.
- Create/edit UI is in scope only to honor list routes + RF04/RF05, using ADR-004 writable fields (`name`, `capacity`, `is_active`). Do not invent a product screen beyond ADR-001.
- Reservations module does not exist. Do not implement “cannot delete room with reservations” against a missing table.
- Application pagination must not return `LengthAwarePaginator` (Illuminate). Repository returns a plain PHP page (`items` + `total`); HTTP builds Inertia props.
- Do not add DTOs, API Resources, or a module service provider unless necessary. Bind in `AppServiceProvider`.
- Do not bootstrap Playwright in this task.
- Frontend coverage threshold 80% (`vite.config.js`). New Room pages and the upgraded layout must be tested.

## Important Decisions

- Module path: `app/Modules/Room/{Domain,Application,Infra}` matching User + `docs/tree.md`.
- One Application use case per action: `ListRooms`, `CreateRoom`, `FindRoom`, `UpdateRoom`, `DeleteRoom`. `GET /rooms/create` has no use case (render form only).
- One invokable Infra controller per route (not `Route::resource`, not a fat `RoomController`):
  - `GET /rooms` → `IndexRoomController`
  - `GET /rooms/create` → `CreateRoomController`
  - `POST /rooms` → `StoreRoomController`
  - `GET /rooms/{room}/edit` → `EditRoomController`
  - `PUT /rooms/{room}` → `UpdateRoomController` (Inertia `_method` spoof; still a POST body)
  - `DELETE /rooms/{room}` → `DestroyRoomController`
- Form Requests only on writes: `StoreRoomRequest`, `UpdateRoomRequest`.
- `HasUuids` default UUIDv7 (Laravel 12 docs); do not override `newUniqueId()`.
- List order: `id` ascending (UUID v7 is time-ordered). Page size 15, `?page=`.
- Soft delete on destroy. Inactive (`is_active=false`) remains in the list. Missing/trashed `{room}` → 404 via `RoomNotFound`.
- Default `is_active=true` on create. `capacity` integer `min:1`. No unique on `name`.
- Upgrade `AppLayout` to the `screen-rooms-list.md` admin shell (sidebar ReservaSalas / Reservas / Salas, account menu, flash live region). Share `{ auth: { user: { name } }, flash: { success, error } }`.
- Inertia pages: `Room/Index`, `Room/Create`, `Room/Edit`. Frontend service `resources/js/Services/rooms.js`.
- Flash copy: `Sala criada com sucesso.` / `Sala atualizada com sucesso.` / `Sala excluída com sucesso.`
