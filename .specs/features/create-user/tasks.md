# Create User Tasks

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

---

**Design**: `.specs/features/create-user/design.md`
**Status**: Complete

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/tests.md`, `harness/skills/php-test/SKILL.md`, `harness/skills/php-integration/SKILL.md`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Domain entity / repository interface | none | Build gate only (entidade passiva) | - | build gate |
| Application use case | unit | Delegação ao repositório; sem regras de input | `tests/Unit/User/` | `php artisan test --testsuite=Unit` |
| Infra repository + persistência | integration | Happy path persistência ADR-002 | `tests/Feature/User/` | `php artisan test` |
| Route / controller | e2e | GET create + POST happy + cada erro de validação + unique (inclui soft-deleted) | `tests/Feature/User/` | `php artisan test` |
| Schema / config (`HASH_DRIVER`, migration) | none | Build gate only | - | build gate |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After tasks with unit tests only | `php artisan test --testsuite=Unit` |
| Full | After tasks with e2e/integration tests | `php artisan test` |
| Build | After phase completion or config/entity-only tasks | `php artisan test && vendor/bin/pint --test app database/factories tests/Unit/User tests/Feature/User database/migrations/0001_01_01_000000_create_users_table.php` |

---

## Execution Plan

Phases are ordered and run sequentially - each phase completes before the next begins, and tasks within a phase execute in order.

### Phase 1: Persistence contract (ADR-002)

```
T1 → T2
```

### Phase 2: Domain and Application

```
T3 → T4
```

### Phase 3: Infra and wiring

```
T5 → T6
```

### Phase 4: HTTP and Inertia

```
T7 → T8 → T9
```

---

## Task Breakdown

### T1: Add soft deletes to users table

**What**: Add `$table->softDeletes()` to the `users` migration so `deleted_at` exists.
**Where**: `database/migrations/0001_01_01_000000_create_users_table.php`
**Depends on**: None
**Reuses**: migration atual; ADR-002
**Requirement**: USER-04, USER-10

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [x] A tabela `users` declara `deleted_at`
- [x] Gate check passes: `php artisan test && vendor/bin/pint --test app database/factories tests/Unit/User tests/Feature/User database/migrations/0001_01_01_000000_create_users_table.php`
- [x] Test count: no silent deletions

**Tests**: none
**Gate**: build

**Commit**: `fix(user): add soft deletes to users table`

---

### T2: Set HASH_DRIVER to argon

**What**: Configure Laravel hashing to the `argon` driver for app and tests.
**Where**: `.env.example`
**Depends on**: T1
**Reuses**: `vendor/laravel/framework/config/hashing.php` defaults; ADR-002
**Requirement**: USER-03

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [x] `.env.example` e `phpunit.xml` definem `HASH_DRIVER=argon`
- [x] Gate check passes: build gate
- [x] Test count: no silent deletions

**Tests**: none
**Gate**: build

**Commit**: `chore(user): set hash driver to argon`

---

### T3: Add User domain entity and repository contract

**What**: Create the pure-PHP User entity and UserRepository interface.
**Where**: `app/Modules/User/Domain/Entities/User.php`
**Depends on**: None
**Reuses**: contrato ADR-002; `docs/tree.md`
**Requirement**: USER-01, USER-04

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [x] Entidade `User` não importa Illuminate
- [x] `UserRepository` declara `existsByEmail` e `create`
- [x] Gate check passes: build gate
- [x] Test count: no silent deletions

**Tests**: none
**Gate**: build

**Commit**: `feat(user): add domain entity and repository contract`

---

### T4: Implement CreateUser use case with unit tests

**What**: Add application errors and the CreateUser use case; cover validation ACs with a fake repository.
**Where**: `app/Modules/User/Application/UseCases/CreateUser.php`
**Depends on**: T3
**Reuses**: Domain `UserRepository`; `docs/tests.md` unitário
**Requirement**: USER-05, USER-06, USER-07, USER-08

**Tools**:

- MCP: NONE
- Skill: `php-test`

**Done when**:

- [x] `execute` valida name, email e password sem Illuminate Validator
- [x] `InvalidUserInput` e `DuplicateEmail` são lançados conforme a spec
- [x] `existsByEmail` true impede `create`
- [x] Gate check passes: `php artisan test --testsuite=Unit`
- [x] Test count: unit tests for missing fields, invalid email, short password, duplicate email

**Tests**: unit
**Gate**: quick

**Commit**: `feat(user): add create user use case`

---

### T5: Move Eloquent User model into User module Infra

**What**: Place the Authenticatable User in Infra with HasUuids, SoftDeletes and hashed cast; point factory and auth at it; remove `app/Models/User.php`.
**Where**: `app/Modules/User/Infra/Database/Models/User.php`
**Depends on**: T1, T2
**Reuses**: modelo atual; `HasUuids`; factory
**Requirement**: USER-02, USER-03, USER-04, USER-10

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [x] Modelo Infra usa `HasUuids`, `SoftDeletes` e cast `hashed`
- [x] `config/auth.php` e `UserFactory` apontam para o modelo Infra
- [x] `app/Models/User.php` removido
- [x] Gate check passes: build gate
- [x] Test count: no silent deletions

**Tests**: none
**Gate**: build

**Commit**: `refactor(user): move eloquent model to user module`

---

### T6: Implement Eloquent user repository and persistence tests

**What**: Implement EloquentUserRepository, bind it, delete `app/Actions/CreateUser.php`, and cover persist ACs in Feature tests.
**Where**: `app/Modules/User/Infra/Database/Repositories/EloquentUserRepository.php`
**Depends on**: T4, T5
**Reuses**: modelo Infra; `docs/tests.md` integração
**Requirement**: USER-01, USER-02, USER-03, USER-04, USER-06, USER-09, USER-10

**Tools**:

- MCP: NONE
- Skill: `php-integration`

**Done when**:

- [x] `existsByEmail` usa `withTrashed()`
- [x] `create` persiste via Eloquent e devolve entidade Domain
- [x] `AppServiceProvider` faz bind da interface
- [x] `app/Actions/CreateUser.php` removido
- [x] Feature tests cobrem persistência, Argon2i, UUID, colunas, remember_token nulo, deleted_at nulo, unique incluindo soft-deleted
- [x] Gate check passes: `php artisan test`
- [x] Test count: no silent deletions

**Tests**: integration
**Gate**: full

**Commit**: `feat(user): persist users through eloquent repository`

---

### T7: Move input validation out of CreateUser use case

**What**: Strip required/email/password/unique checks from the use case; delete unused application errors; keep unit test only for repository delegation.
**Where**: `app/Modules/User/Application/UseCases/CreateUser.php`
**Depends on**: T6
**Reuses**: `UserRepository::create`
**Requirement**: USER-13

**Tools**:

- MCP: NONE
- Skill: `php-test`

**Done when**:

- [x] `CreateUser::execute` só chama o repositório
- [x] `InvalidUserInput` e `DuplicateEmail` removidos
- [x] Unit tests de validação no use case removidos; permanece teste de delegação
- [x] Gate check passes: `php artisan test --testsuite=Unit`
- [x] Test count: no silent deletions of persist tests

**Tests**: unit
**Gate**: quick

**Commit**: `refactor(user): keep create user use case free of validation`

---

### T8: Add HTTP create user controller and Form Request

**What**: Add StoreUserRequest, UserController, routes, Inertia middleware, and Feature HTTP tests for GET/POST including unique with soft-deleted.
**Where**: `app/Modules/User/Infra/Http/Controllers/UserController.php`
**Depends on**: T7
**Reuses**: `CreateUser` use case; `docs/tests.md` e2e
**Requirement**: USER-05, USER-06, USER-07, USER-08, USER-11, USER-12, USER-13

**Tools**:

- MCP: NONE
- Skill: `php-e2e`

**Done when**:

- [x] `StoreUserRequest` valida required, email, password min 8, unique na tabela `users`
- [x] `UserController@create` renderiza Inertia `User/Create`; `@store` chama o use case e redireciona
- [x] Rotas `GET /users/create` e `POST /users`
- [x] Feature tests cobrem happy path HTTP e cada erro de validação, inclusive email soft-deleted
- [x] Gate check passes: `php artisan test`
- [x] Test count: no silent deletions

**Tests**: e2e
**Gate**: full

**Commit**: `feat(user): add create user http endpoints`

---

### T9: Add Inertia User Create page, layout and service

**What**: Bootstrap React/Inertia client (`app.jsx`, layout, `Pages/User/Create.jsx`, `Services/users.js`) without empty Index/Edit/Hooks.
**Where**: `resources/js/Pages/User/Create.jsx`
**Depends on**: T8
**Reuses**: Tailwind; `@inertiajs/react`
**Requirement**: USER-11

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [x] `Create.jsx` envia o form pelo service `users.js`
- [x] Layout e `app.jsx` existem
- [x] Sem `Index.jsx`, `Edit.jsx` ou `Hooks/`
- [x] Gate check passes: `php artisan test`
- [x] Test count: no silent deletions

**Tests**: none
**Gate**: build

**Commit**: `feat(user): add inertia create user page`

---

## Phase Execution Map

```
T1 -> T2
T1 -> T5
T2 -> T5
T3 -> T4
T4 -> T6
T5 -> T6
T6 -> T7
T7 -> T8
T8 -> T9
```

Execution is strictly sequential - there is no intra-phase parallelism.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Add soft deletes to users table | 1 migration change | Granular |
| T2: Set HASH_DRIVER to argon | config | Granular |
| T3: Domain entity and repository contract | 2 cohesive Domain files | OK cohesive |
| T4: CreateUser use case with unit tests | 1 use case + errors + tests | OK cohesive |
| T5: Move Eloquent User model | 1 model move + wiring | OK cohesive |
| T6: Eloquent repository + persistence tests | 1 repository + bind + tests | OK cohesive |
| T7: Strip use case validation | 1 use case | Granular |
| T8: HTTP controller + Form Request + tests | controller + request + routes + tests | OK cohesive |
| T9: Inertia Create page | page + layout + service | OK cohesive |

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | no incoming arrow | Match |
| T2 | T1 | T1 → T2 | Match |
| T3 | None | no incoming arrow | Match |
| T4 | T3 | T3 → T4 | Match |
| T5 | T1, T2 | T1/T2 → T5 | Match |
| T6 | T4, T5 | T4 → T6, T5 → T6 | Match |
| T7 | T6 | T6 → T7 | Match |
| T8 | T7 | T7 → T8 | Match |
| T9 | T8 | T8 → T9 | Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | Schema | none | none | OK |
| T2 | Config | none | none | OK |
| T3 | Domain entity / repository interface | none | none | OK |
| T4 | Application use case | unit | unit | OK |
| T5 | Entity / Eloquent model | none | none | OK |
| T6 | Infra repository | integration | integration | OK |
| T7 | Application use case | unit | unit | OK |
| T8 | Route / controller | e2e | e2e | OK |
| T9 | Inertia page (no PHP layer test type) | none | none | OK |
