---
harness:
  commits:
    - "feat(app): drop user authenticator binding"
    - "chore(composer): require laravel breeze"
    - "feat(database): seed demo rooms and reservations"
    - "chore(phpunit): drop empty user application coverage path"
    - "feat(routes): point login logout at breeze session"
    - "feat(user): switch login to breeze authenticated session"
    - "test(database): cover seeded rooms and reservations"
    - "test(reservation): adjust counts for migrated demo catalog"
    - "test(room): adjust counts for migrated demo catalog"
    - "test(user): cover breeze login request and removed paths"
    - "chore(docs): record breeze login adoption"
    - "chore(readme): document db seed for demonstration data"
    - "chore(specs): record task context"
    - "chore(specs): record task progress"
    - "chore(specs): record review reports"
    - "chore(specs): record revised catalog spec"
    - "chore(specs): record revised catalog plan"
    - "chore(specs): record check history"
    - "chore(specs): record validation report"
  tests:
    unit:
      - "LoginRequest rejects a missing email with Informe seu e-mail"
      - "LoginRequest rejects a missing password with Informe sua senha"
      - "LoginRequest rejects a malformed email with Informe um endereço de e-mail válido"
    integration:
      - "POST /login with valid credentials authenticates, regenerates the session, and redirects to reservations.index"
      - "POST /login with unknown email, wrong password, or a soft-deleted user returns credentials E-mail ou senha inválidos and stays guest"
      - "The sixth POST /login is throttled with the same credentials error even when the password is correct"
      - "Each default administrator (teste@mail.com, teste2@mail.com, teste3@mail.com) can POST /login with Senha123 and reach /reservations"
      - "GET/POST /register, /forgot-password, /reset-password, and /email/verification-notification return 404 and insert no users row"
      - "After RefreshDatabase, users still has the ADR-002 columns only (no email_verified_at)"
      - "After RefreshDatabase, DatabaseSeeder keeps exactly the three migrated administrators and does not insert test@example.com"
      - "After RefreshDatabase (migrate only), MySQL contains Sala Reunião Norte (8), Sala Treinamento (20), and Sala Diretoria (4), all active, plus the three demonstration reservations"
      - "After RefreshDatabase then DatabaseSeeder, rooms stay 3 and reservations stay 3 (idempotent first-or-create)"
      - "After RefreshDatabase and DatabaseSeeder, MySQL contains Sala Reunião Norte (8), Sala Treinamento (20), and Sala Diretoria (4), all active"
      - "After DatabaseSeeder, the three demonstration reservations are active, sit on those rooms, and satisfy RF13–RF18 (consecutive Norte slots, duration 30–240 minutes, start not in the past, participants within capacity, no active overlap)"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Login/session is already covered by Feature HTTP (MySQL 8) and User/Login Vitest. docs/test/e2e.md forbids repeating those matrices in the browser. Do not bootstrap Playwright in this task."
  tests_not_applicable_reason: "E2E would only duplicate Feature login and Vitest Login.jsx. RF19 is Feature/MySQL persistence. RNF12 is README documentation."
  gates:
    - id: test
      command: "php artisan test && npm run test"
      required: true
    - id: lint
      command: "vendor/bin/pint --test && npm run lint"
      required: true
    - id: build
      command: "composer run build && npm run build"
      required: true
---

# Implementation Plan

## Summary

Close the three ADR-001 holes in one task, four phases: adopt Laravel Breeze for login (RNF07), drop the custom authenticate-user stack, persist a demonstration catalog on migrate and keep idempotent seeders (RF19), and document that path in the README (RNF12).

**Design (inline, no `design.md`):**

- `composer require laravel/breeze --dev`. Do **not** run `php artisan breeze:install` on this worktree.
- Copy only `AuthenticatedSessionController` and Breeze `LoginRequest` from `vendor/laravel/breeze/stubs`. Relocate into `app/Modules/User/Infra/Http`. Render `User/Login`. Redirect to `reservations.index`. Keep URLs `/`, `/login`, POST `/login` (`login.store`), POST `/logout`. Remember-me false. Failed auth and throttle stay on `credentials` => `E-mail ou senha inválidos.`
- Delete `LoginController`, `AuthenticateUser`, `InvalidCredentials`, `UserAuthenticator`, `LaravelUserAuthenticator`, and `AuthenticateUserTest`. Unbind the authenticator. Drop empty `app/Modules/User/Application` from `phpunit.xml`.
- ADR-007 (MADR, Portuguese, 2026-09-20) via skill `create-adr`. ADR-002 Status/supersede line only. One-line `architecture.md` note that login uses Breeze `LoginRequest::authenticate()`.
- Data migration inserts the same three rooms and three reservations as the seeders. `RoomSeeder` then `ReservationSeeder` from `DatabaseSeeder` first-or-create those rows. Feature `DatabaseSeederTest` + `DemoCatalogMigrationTest` on MySQL 8. Keep `UsersMigrationTest` extra-admin case.
- README: `php artisan migrate` already creates admins + demo rooms/reservations. `php artisan db:seed` stays documented as the RF19 guarantee that does not duplicate. Keep Docker / install / `key:generate` / `composer run dev` / admin table. No secrets. No extra how-to files.

## Affected Components

- `app` — User HTTP login, `routes/web.php`, `composer.json` / lock, `AppServiceProvider`, `phpunit.xml`, `database/seeders/*`, `tests/Unit/User/*`, `tests/Feature/User/*`, `tests/Feature/Database/DatabaseSeederTest.php`, `docs/adr/007-*`, ADR-002 status, `docs/architecture.md`, `README.md`.
- Keep `User/Login.jsx`, `Login.test.jsx`, `LoginHttpTest` behavior, `DefaultAdministratorLoginHttpTest`, `UsersMigrationTest` schema/extra-admin cases.
- Do not change room/reservation use cases, `.env.example` `HASH_DRIVER`, `compose.yml`, or ADR-001.

## Tasks

Execute T1 → T8 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. New `tests/Unit/User/LoginRequestTest.php` following `StoreRoomRequestTest` (`FormRequest::create`, `validateResolved`, no `authenticate()`, no database). Do not add a unit for `Auth::attempt`. Delete `AuthenticateUserTest` with the use case. Do not add seeder units (`docs/test/unit.md`).

### Integration

See `harness.tests.integration`. Feature suite on MySQL 8 via `RefreshDatabase`. Keep `LoginHttpTest` as the login/session contract (URLs, `credentials`, throttle, intended URL, logout). Keep `DefaultAdministratorLoginHttpTest`. Extend 404 coverage for Breeze extras without reintroducing named `register` / password / verification routes. Extra-admin contract stays in `UsersMigrationTest`. `DatabaseSeederTest` and `DemoCatalogMigrationTest` assert migrate-alone 3+3, seed stays 3+3, and RF13–RF18. Do not mock Eloquent.

### E2E

Not applicable — see `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Those match `harness/stack.yml` `app` commands for `test`, `lint`, and `build` (`harness/config.yaml` verify.required).

Do not run `npx playwright test`.

## Definition of Done

- All P1 ACs are implemented: Breeze login + ADR-007 + migrate catalog + idempotent seed + README migrate/seed steps.
- Unit items in `harness.tests.unit` and integration items in `harness.tests.integration` pass on MySQL 8.
- Extra-admin / `test@example.com` / no-`email_verified_at` contracts still pass.
- No `breeze:install` damage, no SQL dump, no ADR-001 edit, no extra how-to files, no secrets in README.
- Gates `test`, `lint`, and `build` pass.
- No product commit in PLAN.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `AGENTS.md`, `harness/stack.yml`, `phpunit.xml`, `package.json`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Login FormRequest (input rules) | unit | Missing/malformed email and missing password; Portuguese messages; do not call `authenticate()` | `tests/Unit/User/LoginRequestTest.php` | `php artisan test --testsuite=Unit` |
| Breeze session controller + login HTTP | integration | Existing LoginHttpTest matrix stays: success + regenerate, intended URL, credentials errors, throttle, logout, guest/auth GET `/` and `/login` | `tests/Feature/User/LoginHttpTest.php`, `tests/Feature/User/DefaultAdministratorLoginHttpTest.php` | `php artisan test --testsuite=Feature` |
| Disabled Breeze extras | integration | Register / reset / verify URLs 404 and insert no user | `tests/Feature/User/RemovedRegistrationHttpTest.php` | `php artisan test --testsuite=Feature` |
| Laravel seeders + MySQL | integration | After migrate: three named rooms and three RF13–RF18 reservations; after `$this->seed()` the catalog stays 3+3; existing extra-admin case stays | `tests/Feature/Database/DatabaseSeederTest.php`, `tests/Feature/Database/DemoCatalogMigrationTest.php`, `tests/Feature/Database/UsersMigrationTest.php` | `php artisan test --testsuite=Feature` |
| User Application use case | none | Removed with Breeze login; delete `AuthenticateUserTest` | — | remaining Unit suite |
| React `User/Login` | unit | Unchanged Vitest (no cadastro / recovery) | `resources/js/Pages/User/Login.test.jsx` | `npm run test` |
| ADR / README / composer / phpunit config | none | Documentation and dependency contracts reviewed against ACs | `docs/adr/*`, `README.md`, `composer.json` | build gate only |
| Playwright e2e | none | No runner in `package.json` | — | do not run |

## Gate Check Commands

> Generated from `composer.json` / `package.json` / `harness/stack.yml`.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After unit-only tasks | `php artisan test --testsuite=Unit` |
| Full | After HTTP / seeder Feature tasks | `php artisan test --testsuite=Feature` |
| Build | After docs/README/composer tasks | `composer run build && npm run build` |
| Regression | After Execute (`harness.gates`) | `php artisan test && npm run test` && `vendor/bin/pint --test && npm run lint` && `composer run build && npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Record the Breeze swap

```
T1
```

### Phase 2: Install and adapt Breeze login

```
T2 -> T3 -> T4
```

### Phase 3: Remove the custom login stack

```
T5 -> T6
```

### Phase 4: RF19 seeder and RNF12 README

```
T7 -> T8
```

---

## Task Breakdown

### Phase 1: Record the Breeze swap

### T1: Write ADR-007 and supersede native-only auth

**What**: Create `docs/adr/007-adotar-laravel-breeze-para-o-login.md` (MADR, Portuguese, date 2026-09-20, Status Aceito) using skill `create-adr`. Decision: adopt `laravel/breeze` Inertia/React login stubs, adapted to the ADR-002 User (UUID v7, soft delete, `HASH_DRIVER=argon`, no `email_verified_at`, no `App\Models\User`). Do not rewrite ADR-002’s decision body; change only its Status line to supersede the “auth nativa sem kit” slice. Add one sentence to `docs/architecture.md` that administrator login uses Breeze `AuthenticatedSessionController` + `LoginRequest::authenticate()`. Do not edit ADR-001.
**Where**: `docs/adr/007-adotar-laravel-breeze-para-o-login.md`
**Depends on**: None
**Reuses**: existing ADR MADR format (`docs/adr/002-*.md`); skill `create-adr`
**Requirement**: AUTH-04, AUTH-05

**Tools**:

- MCP: NONE
- Skill: `create-adr`, `tlc-spec-driven`

**Done when**:

- [x] ADR-007 exists with context, drivers, options (Breeze adapted / Fortify starter / keep custom login), consequences, and a supersedes link to ADR-002’s auth-without-kit slice
- [x] ADR-002 decision text is unchanged; Status points at ADR-007 for that slice only
- [x] ADR-001 is unmodified
- [x] Gate check passes: `composer run build && npm run build`

**Tests**: none
**Gate**: build

---

### Phase 2: Install and adapt Breeze login

### T2: Require laravel/breeze

**What**: Add `laravel/breeze` as a Composer require-dev dependency (`composer require laravel/breeze --dev`). Commit `composer.json` and `composer.lock`. Do not run `php artisan breeze:install`. Do not publish Breeze frontend pages, `routes/auth.php`, dashboard, or profile.
**Where**: `composer.json`
**Depends on**: T1
**Reuses**: current Laravel 12 `composer.json`
**Requirement**: AUTH-01

**Tools**:

- MCP: `context7` (Breeze install/stubs only if Execute needs a refresh)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `composer.json` require-dev contains `laravel/breeze`
- [x] `vendor/laravel/breeze/stubs` is present for the next task
- [x] `php artisan breeze:install` was not executed
- [x] Gate check passes: `composer run build && npm run build`

**Tests**: none
**Gate**: build

---

### T3: Switch LoginRequest to Breeze authenticate

**What**: Replace the current `LoginRequest` helpers with Breeze `LoginRequest::authenticate()` copied from `vendor/laravel/breeze/stubs/default/app/Http/Requests/Auth/LoginRequest.php`. Keep Portuguese `rules`/`messages`, `prepareForValidation` email normalize, and `max:255` on email. `Auth::attempt` uses email + password only and remember false. Failed attempt and throttle throw `credentials` => `E-mail ou senha inválidos.` (do not switch the UI to `email` / `auth.failed`). Add `tests/Unit/User/LoginRequestTest.php` for missing email, missing password, and malformed email only — do not call `authenticate()` in unit tests.
**Where**: `app/Modules/User/Infra/Http/Requests/LoginRequest.php`
**Depends on**: T2
**Reuses**: Breeze stub `LoginRequest`; `tests/Unit/Room/StoreRoomRequestTest.php` helper pattern
**Requirement**: AUTH-02

**Tools**:

- MCP: `context7`
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `LoginRequest` has `authenticate()` that calls `Auth::attempt` with remember false
- [x] Unit items in `harness.tests.unit` pass
- [x] Gate check passes: `php artisan test --testsuite=Unit`

**Tests**: unit
**Gate**: quick

---

### T4: Add Breeze AuthenticatedSessionController

**What**: Add `AuthenticatedSessionController` from the Breeze inertia-common stub into `app/Modules/User/Infra/Http/Controllers/AuthenticatedSessionController.php`. `create()` renders `User/Login`. `store()` calls `$request->authenticate()`, regenerates the session, redirects to `reservations.index` (not `dashboard`). `destroy()` logs out, invalidates the session, regenerates the CSRF token, redirects to `login`. Point `routes/web.php` guest `/`, `/login`, POST `/login` (`login.store`) and auth POST `/logout` at this controller. Delete `LoginController`. Keep rooms/reservations routes. Keep `User/Login.jsx` and `session.js`. Do not add Breeze `Auth/Login` or `canResetPassword`.
**Where**: `app/Modules/User/Infra/Http/Controllers/AuthenticatedSessionController.php`
**Depends on**: T3
**Reuses**: Breeze stub `AuthenticatedSessionController`; existing `LoginHttpTest` and `DefaultAdministratorLoginHttpTest`
**Requirement**: AUTH-01, AUTH-02

**Tools**:

- MCP: `context7`
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Guest GET `/` and GET `/login` render Inertia `User/Login`
- [x] POST `/login` success/failure/throttle/logout cases in `LoginHttpTest` pass
- [x] Default administrators still log in
- [x] Gate check passes: `php artisan test --testsuite=Feature`

**Tests**: integration
**Gate**: full

---

### Phase 3: Remove the custom login stack

### T5: Remove AuthenticateUser and the authenticator port

**What**: Delete `AuthenticateUser`, `InvalidCredentials`, `UserAuthenticator`, `LaravelUserAuthenticator`, and `tests/Unit/User/AuthenticateUserTest.php`. Remove the `UserAuthenticator` binding from `AppServiceProvider`. Remove `app/Modules/User/Application` from `phpunit.xml` coverage include so the 80% Application gate still measures Room + Reservation only. Leave `UserRepository` as-is.
**Where**: `app/Modules/User/Application/UseCases/AuthenticateUser.php`
**Depends on**: T4
**Reuses**: remaining Room/Reservation Application coverage
**Requirement**: AUTH-01

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] No production reference to `AuthenticateUser` or `UserAuthenticator` remains
- [x] Unit suite passes without `AuthenticateUserTest`
- [x] Gate check passes: `php artisan test --testsuite=Unit`

**Tests**: unit
**Gate**: quick

---

### T6: Reject leftover Breeze auth URLs

**What**: Extend `RemovedRegistrationHttpTest` so GET/POST `/forgot-password`, `/reset-password`, and `/email/verification-notification` (and existing `/register` / `/users` paths) return 404 and insert no `users` row. Keep named routes `register`, `register.store`, `users.create`, `users.store`, `password.request`, and `verification.notice` absent. Do not add Breeze `routes/auth.php`.
**Where**: `tests/Feature/User/RemovedRegistrationHttpTest.php`
**Depends on**: T5
**Reuses**: existing 404 data provider
**Requirement**: AUTH-03

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Breeze extra URLs 404 with users count still 3
- [x] Named password/verification routes are absent
- [x] Gate check passes: `php artisan test --testsuite=Feature`

**Tests**: integration
**Gate**: full

---

### Phase 4: RF19 seeder and RNF12 README

### T7: Seed demonstration rooms and reservations

**What**: Add a data migration that inserts the same three active rooms and three active future reservations as the seeders (UUID v7, timestamps, `is_active` true, `cancelled_at` null, `deleted_at` null). Keep `RoomSeeder` and `ReservationSeeder`. Wire `DatabaseSeeder::run` to `$this->call` rooms then reservations. Seeders first-or-create by room name and by reservation title on those rooms. Do not insert users or use factories. Add `tests/Feature/Database/DatabaseSeederTest.php` and `DemoCatalogMigrationTest.php` that assert migrate-alone 3+3, seed stays 3+3, and RF13–RF18. Keep `UsersMigrationTest` extra-admin assertions unchanged.
**Where**: `database/seeders/DatabaseSeeder.php`
**Depends on**: T6
**Reuses**: `Room` and `Reservation` models; `UsersMigrationTest` extra-admin contract; Laravel `$this->call` / `$this->seed()`
**Requirement**: SEED-01, SEED-02, SEED-03

**Tools**:

- MCP: `context7` (Laravel seeder/`db:seed` API only if Execute needs a refresh)
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `php artisan migrate` persists the three named rooms and three named reservations; `php artisan db:seed` afterward stays 3+3
- [x] Seeded reservations satisfy RF13–RF18
- [x] `users` count stays 3 and `test@example.com` is absent
- [x] Feature seeder items in `harness.tests.integration` pass
- [x] Gate check passes: `php artisan test --testsuite=Feature`

**Tests**: integration
**Gate**: full

---

### T8: Document db:seed in the README

**What**: In Portuguese `README.md`, state that `php artisan migrate` already creates administrators plus demonstration rooms and reservations, and that `php artisan db:seed` (RF19) guarantees the same data without duplicating. After optional `composer run setup`, the catalog already came from migrate; `db:seed` remains available and idempotent. Keep `docker compose up -d`, `composer install`, `npm install`, `php artisan key:generate`, `composer run dev`, `http://127.0.0.1:8000`, and the Gertrudes / Marcelo / Emerson table. Print no env/Compose values. Do not add CONTRIBUTING.md or `docs/setup.md`.
**Where**: `README.md`
**Depends on**: T7
**Reuses**: current README local-run sections from task 0017
**Requirement**: README-01, README-02

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] README instructs migrate then `php artisan db:seed` and states migrate already creates admins + demo rooms/reservations while seed does not duplicate
- [x] Docker, install, `key:generate`, and `composer run dev` remain
- [x] No env/Compose secret values; no new how-to files
- [x] Gate check passes: `composer run build && npm run build`

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1 -> Phase 2 -> Phase 3 -> Phase 4

Phase 1:  T1
Phase 2:  T2 -> T3 -> T4
Phase 3:  T5 -> T6
Phase 4:  T7 -> T8
```

Execution is strictly sequential. Cross-phase dependencies (T2 on T1, T5 on T4, T7 on T6) point backward only.

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: ADR-007 + ADR-002 status | Docs decision record | Granular |
| T2: Composer require breeze | 1 manifest | Granular |
| T3: LoginRequest + unit validation | 1 FormRequest | Granular |
| T4: Breeze session controller + routes | 1 controller (routes in What) | Granular |
| T5: Remove custom auth stack | 1 use-case removal | Granular |
| T6: Extra Breeze URL 404s | 1 Feature class | Granular |
| T7: Seeders + Feature catalog test | One catalog slice | Granular |
| T8: README db:seed step | 1 file | Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (no inbound arrow) | Match |
| T2 | T1 | (cross-phase; Phase 2 starts at T2) | Match |
| T3 | T2 | T2 -> T3 | Match |
| T4 | T3 | T3 -> T4 | Match |
| T5 | T4 | (cross-phase; Phase 3 starts at T5) | Match |
| T6 | T5 | T5 -> T6 | Match |
| T7 | T6 | (cross-phase; Phase 4 starts at T7) | Match |
| T8 | T7 | T7 -> T8 | Match |

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | ADR / architecture docs | none | none | OK |
| T2 | Composer manifest | none | none | OK |
| T3 | Login FormRequest (input rules) | unit | unit | OK |
| T4 | Breeze session controller + login HTTP | integration | integration | OK |
| T5 | User Application use case (removed) | none / unit suite | unit | OK |
| T6 | Disabled Breeze extras | integration | integration | OK |
| T7 | Laravel seeders + MySQL | integration | integration | OK |
| T8 | README markdown | none | none | OK |
