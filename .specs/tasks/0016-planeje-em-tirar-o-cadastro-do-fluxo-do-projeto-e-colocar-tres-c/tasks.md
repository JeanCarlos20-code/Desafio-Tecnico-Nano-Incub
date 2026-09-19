---
harness:
  commits:
    - "feat(database): insert three default administrators on migrate"
    - "feat(resources): remove users registration client"
    - "feat(routes): drop public registration routes"
    - "feat(user): remove public registration from the guest flow"
    - "test(database): cover default administrator rows after migrate"
    - "test(resources): drop users registration client tests"
    - "test(tests): retry migrate-fresh after table-exists race"
    - "test(user): cover removed registration and login without cadastro"
    - "chore(docs): drop create-user screen and login cadastro"
    - "chore(readme): document default administrator accounts"
    - "chore(specs): record remove-registration plan"
  tests:
    unit:
      - "User/Login does not render Não tem uma conta? or Ir para o cadastro and does not link to /register"
    integration:
      - "RefreshDatabase persist Gertrudes, Marcelo, and Emerson with Argon2i hashes that accept Senha123"
      - "POST /login with each default email and Senha123 authenticates and redirects to /reservations"
      - "GET/POST /register, GET /users/create, and POST /users return 404 and insert no users row"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Default-account login and removed register URLs are covered by Feature HTTP tests; login without cadastro is covered by Vitest. Do not bootstrap Playwright in this task."
  gates:
    - id: unit
      command: "php artisan test --testsuite=Unit --coverage --min=80"
      required: true
    - id: frontend
      command: "npm run test:coverage"
      required: true
    - id: integration
      command: "php artisan test --testsuite=Feature"
      required: true
    - id: lint
      command: "vendor/bin/pint --test"
      required: true
    - id: frontend_lint
      command: "npm run lint"
      required: true
    - id: php_build
      command: "composer run build"
      required: true
    - id: frontend_build
      command: "npm run build"
      required: true
---

# Implementation Plan

## Summary

Stop public administrator registration. Provision Gertrudes, Marcelo, and Emerson through a new users data migration with Argon hashes of `Senha123`. Login remains the only guest identity screen.

**Design (inline, no `design.md`):**

- New migration `database/migrations/2026_09_19_000000_insert_default_administrators.php`. `up()`: `DB::table('users')->insert` three rows (`Str::uuid7()`, `Hash::make('Senha123')`, timestamps now, `remember_token` and `deleted_at` null). `down()`: delete those emails.
- Do not edit `0001_01_01_000000_create_users_table.php`.
- `DatabaseSeeder::run` becomes a no-op for users (remove `test@example.com`).
- Delete `UserController`, `StoreUserRequest`, `CreateUser`, `User/Create.jsx`, `users.js`. Remove the four guest routes. Leave `UserRepository` as-is.
- `Login.jsx`: drop the divider and `Ir para o cadastro` `Link`. Keep `BrandPanel`.
- Replace register tests. Extend `UsersMigrationTest`. Fix `MysqlConnectionTest` count to 4. Add default-account cases to `LoginHttpTest` (or a sibling Feature class). Rewrite the two Login Vitest cadastro assertions.

## Affected Components

- `app` — User HTTP/Application register surface, `routes/web.php`, `resources/js/Pages/User/Login.jsx`, new migration, `DatabaseSeeder`, Feature/Unit/Vitest tests listed in context.
- `docs` — delete `docs/screens/screen-create-user.md`; edit `docs/screens/screen-login.md` and `README.md`.
- Do not change login use case, Room, Reservation, or add Playwright.

## Tasks

Execute T1 → T4 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. Vitest only. Inertia mocked. No new PHP unit for the deleted `CreateUser` wrapper. Do not unit-test the migration.

### Integration

See `harness.tests.integration`. Feature suite on MySQL 8 via `RefreshDatabase`. Assert migrated rows + `Hash::check` + `password_get_info` algo `argon2i`. Assert 404 on old paths using URL strings (named routes are gone). Assert POST `/login` for each default account. Do not repeat `LoginHttpTest` session-id, throttle, or field-error matrices.

### E2E

Not applicable — no Playwright project. See `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Unit coverage remains Application-only (`phpunit.xml`). Frontend coverage via `npm run test:coverage` (≥80%).

Do not run `npx playwright test`.

## Definition of Done

- All P1 ACs have a unit and/or integration test as classified above; no duplicated scenario across levels.
- `CreateUser*` production and test files that described public register are gone.
- Existing `LoginHttpTest` and `AuthenticateUserTest` still pass.
- `MysqlConnectionTest` count accounts for the three migrated rows.
- Pint, ESLint, PHP build, and Vite build pass.
- No product commit in PLAN.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `phpunit.xml` (Application coverage ≥80%), `package.json` (`npm run test:coverage`).

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| AuthenticateUser (unchanged) | unit | Existing success/failure cases stay green | `tests/Unit/User/AuthenticateUserTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| React login page | unit | No cadastro copy or `/register` link | `resources/js/Pages/User/Login.test.jsx` | `npm run test:coverage` |
| Data migration + login/register HTTP | integration | Three hashed defaults; each can POST `/login`; old paths 404 | `tests/Feature/Database/*`, `tests/Feature/User/*` | `php artisan test --testsuite=Feature` |
| Eloquent User model / schema migration | none | Schema unchanged | — | build gate only |
| Playwright e2e | none | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from `composer.json` / `package.json` / `harness/stack.yml`.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After PHP unit-only work | `php artisan test --testsuite=Unit --coverage --min=80` |
| Frontend | After React tasks | `npm run test:coverage` |
| Full | After Feature / page+HTTP tasks | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run test:coverage` |
| Build | Phase end / lint | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Default administrators

```
T1
```

### Phase 2: Remove cadastro

```
T2 → T3
```

### Phase 3: Docs

```
T4
```

---

## Task Breakdown

### Phase 1: Default administrators

#### T1: Insert three default administrators in a data migration

**What**: Add `2026_09_19_000000_insert_default_administrators` that inserts Gertrudes / Marcelo / Emerson with UUIDv7 ids and `Hash::make('Senha123')`. `down()` deletes those emails. Remove the `DatabaseSeeder` Test User. Extend `UsersMigrationTest` with the three integration row/hash cases. Change `MysqlConnectionTest` to expect `users` count 4 after one factory create.
**Where**: `database/migrations/2026_09_19_000000_insert_default_administrators.php`
**Depends on**: None
**Reuses**: `0001_01_01_000000_create_users_table.php` schema; ADR-002 hash/uuid rules; `UsersMigrationTest`, `MysqlConnectionTest`
**Requirement**: AUTH-01

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `RefreshDatabase` leaves the three named emails
- [x] Each password is Argon2i and `Hash::check('Senha123', …)` is true
- [x] `MysqlConnectionTest` still proves MySQL persist
- [x] Gate check passes: `php artisan test --testsuite=Feature --filter=Database`

**Tests**: integration
**Gate**: full

---

### Phase 2: Remove cadastro

#### T2: Remove public registration HTTP and CreateUser

**What**: Delete guest `/register` and `/users` create/store routes, `UserController`, `StoreUserRequest`, and `CreateUser`. Delete `CreateUserTest`, `CreateUserHttpTest`, and `CreateUserPersistenceTest`. Add Feature coverage: the four old paths return 404 and do not insert a row; POST `/login` with each default email + `Senha123` authenticates and redirects to `reservations.index`. Do not change `LoginController` / `AuthenticateUser` / `UserRepository`.
**Where**: `routes/web.php`
**Depends on**: T1
**Reuses**: `LoginHttpTest` session/throttle cases (leave them); Feature HTTP helpers
**Requirement**: AUTH-02

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Named register/users.create routes are gone
- [x] Old paths 404 with no extra `users` row
- [x] Each default account can log in
- [x] Gate check passes: `php artisan test --testsuite=Feature --filter=User`

**Tests**: integration
**Gate**: full

---

#### T3: Remove register Inertia page and login cadastro controls

**What**: Delete `resources/js/Pages/User/Create.jsx`, `Create.test.jsx`, `Services/users.js`, and `users.test.js`. Remove the login divider and `Ir para o cadastro` link. Replace the two Vitest cadastro assertions with the unit case that those strings and `/register` are absent, including the processing-state test.
**Where**: `resources/js/Pages/User/Login.jsx`
**Depends on**: T2
**Reuses**: `BrandPanel`, `Services/session.js`, existing Login Vitest setup
**Requirement**: AUTH-03

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Login Vitest has no cadastro / `/register` expectation
- [x] Create page and users service files are gone
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: frontend

---

### Phase 3: Docs

#### T4: Drop create-user screen and document default accounts

**What**: Delete `docs/screens/screen-create-user.md`. Remove cadastro divider, link, and acceptance checkbox from `docs/screens/screen-login.md`. Rewrite README so it lists the three migrated accounts and no longer mentions public `/register` or `test@example.com` / `password`.
**Where**: `docs/screens/screen-login.md`
**Depends on**: T3
**Reuses**: current login screen structure (fields, errors, no recovery)
**Requirement**: DOC-01

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `screen-create-user.md` is deleted
- [x] Login screen MD and README match AUTH-01 / AUTH-03
- [x] Gate: build (docs only)

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1 → Phase 2 → Phase 3

Phase 1:  T1
Phase 2:  T2 ------→ T3
Phase 3:  T4
```

Execution is sequential. T3 depends on T2. T4 depends on T3.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Data migration + Database tests | One migration + seeder cleanup | ✅ Cohesive |
| T2: Remove register HTTP | Routes + dead CreateUser surface + Feature | ✅ Cohesive |
| T3: Login UI without cadastro | One page + delete unused register assets | ✅ Cohesive |
| T4: Screen/README docs | Docs only | ✅ Granular |

**Granularity check**: T2 touches several User files in one module because they are one deleted surface.

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | No inbound arrow | ✅ Match |
| T2 | T1 | T1 → T2 (phase 2 after T1) | ✅ Match |
| T3 | T2 | T2 → T3 | ✅ Match |
| T4 | T3 | T3 → T4 | ✅ Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | Data migration + MySQL | integration | integration | ✅ OK |
| T2 | Controllers / routes + MySQL | integration | integration | ✅ OK |
| T3 | React login page | unit | unit | ✅ OK |
| T4 | Docs / README | none | none | ✅ OK |
