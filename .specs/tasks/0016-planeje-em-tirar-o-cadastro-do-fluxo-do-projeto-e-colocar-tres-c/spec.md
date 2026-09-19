# Specification

## Context

The panel currently lets any guest open `/register` (and `/users/create`), persist an administrator, and land on `/reservations`. Login still links to that cadastro. RF01 only requires administrator login. The user asked to remove registration from the product flow and to provision three known accounts through a migration.

## Problem

Guests can create administrators. There is no fixed local login set after `migrate`. Screen docs and README still describe public cadastro.

## Problem Statement

Remove public administrator registration from routes, HTTP, Inertia, login UI, and `screen-create-user.md`. After migrations run, MySQL SHALL contain three hashed default administrators that can log in with the requested emails and password.

## Goal

- Guest GET/POST `/register`, GET `/users/create`, and POST `/users` SHALL not create users and SHALL not render `User/Create`.
- `migrate` SHALL insert Gertrudes / Marcelo / Emerson with Argon hashes of `Senha123`.
- Login SHALL stay the only public identity screen and SHALL not offer cadastro.
- Docs and README SHALL match the new flow.

## User Stories

### P1: Log in with a default administrator ⭐ MVP

**User Story**: As an administrator, I want three known accounts created by migrate so that I can open `/login` without registering.

**Why P1**: User request; RF01 needs at least one administrator.

**Acceptance Criteria**:

1. WHEN migrations run on an empty `users` table THEN the system SHALL persist exactly these rows: name `Gertrudes` email `teste@mail.com`, name `Marcelo` email `teste2@mail.com`, name `Emerson` email `teste3@mail.com`.
2. WHEN those rows are persisted THEN the system SHALL store each `password` as an Argon2i hash that `Hash::check('Senha123', $hash)` accepts and SHALL NOT store `Senha123` in the `password` column.
3. WHEN a guest POSTs `/login` with any of those emails and password `Senha123` THEN the system SHALL authenticate that user and redirect to `/reservations`.

**Independent Test**: `RefreshDatabase` then assert the three emails, Argon2i + `Hash::check`, and three successful POSTs to `/login`.

### P1: Guests cannot register ⭐ MVP

**User Story**: As a project owner, I want public cadastro gone so that only the migrated accounts exist unless someone uses factories or artisan.

**Why P1**: User request to take cadastro out of the flow.

**Acceptance Criteria**:

1. WHEN a guest requests `GET /register`, `POST /register`, `GET /users/create`, or `POST /users` THEN the system SHALL return HTTP 404 and SHALL NOT insert a `users` row.
2. The system SHALL NOT register named routes `register`, `register.store`, `users.create`, or `users.store`.
3. The system SHALL NOT keep a production `UserController`, `StoreUserRequest`, `CreateUser` use case, Inertia page `User/Create`, or `resources/js/Services/users.js`.

**Independent Test**: Feature GETs/POSTs to the four paths assert 404 and unchanged user count; `route()` for the old names is unavailable.

### P1: Login screen without cadastro ⭐ MVP

**User Story**: As a guest, I want the login card to contain only email, password, and Entrar so that I am not sent to a removed register page.

**Why P1**: Login is the remaining public screen.

**Acceptance Criteria**:

1. WHEN `User/Login` renders THEN the system SHALL NOT show `Não tem uma conta?` or a control named `Ir para o cadastro`, and SHALL NOT link to `/register`.
2. WHEN `docs/screens/screen-login.md` is updated THEN it SHALL omit cadastro navigation and the cadastro acceptance checkbox.
3. WHEN Execute finishes THEN `docs/screens/screen-create-user.md` SHALL NOT exist, and README SHALL document the three default accounts instead of `/register` and `test@example.com`.

**Independent Test**: Vitest queries the cadastro copy and finds none; git shows `screen-create-user.md` deleted.

## Acceptance Criteria

1. WHEN migrations run on an empty `users` table THEN the system SHALL persist exactly these rows: name `Gertrudes` email `teste@mail.com`, name `Marcelo` email `teste2@mail.com`, name `Emerson` email `teste3@mail.com`.
2. WHEN those rows are persisted THEN the system SHALL store each `password` as an Argon2i hash that `Hash::check('Senha123', $hash)` accepts and SHALL NOT store `Senha123` in the `password` column.
3. WHEN a guest POSTs `/login` with any of those emails and password `Senha123` THEN the system SHALL authenticate that user and redirect to `/reservations`.
4. WHEN a guest requests `GET /register`, `POST /register`, `GET /users/create`, or `POST /users` THEN the system SHALL return HTTP 404 and SHALL NOT insert a `users` row.
5. The system SHALL NOT register named routes `register`, `register.store`, `users.create`, or `users.store`.
6. The system SHALL NOT keep a production `UserController`, `StoreUserRequest`, `CreateUser` use case, Inertia page `User/Create`, or `resources/js/Services/users.js`.
7. WHEN `User/Login` renders THEN the system SHALL NOT show `Não tem uma conta?` or a control named `Ir para o cadastro`, and SHALL NOT link to `/register`.
8. WHEN `docs/screens/screen-login.md` is updated THEN it SHALL omit cadastro navigation and the cadastro acceptance checkbox.
9. WHEN Execute finishes THEN `docs/screens/screen-create-user.md` SHALL NOT exist, and README SHALL document the three default accounts instead of `/register` and `test@example.com`.
10. WHEN `DatabaseSeeder` runs after migrate THEN the system SHALL NOT insert `test@example.com` or any additional administrator.

## Edge Cases

- IF a database already contains `teste@mail.com`, `teste2@mail.com`, or `teste3@mail.com` before the new migration runs THEN migrate SHALL fail on the unique email index; the operator resets or edits that environment. Fresh `migrate:fresh` and PHPUnit `RefreshDatabase` stay the supported paths.
- IF `down()` of the data migration runs THEN the system SHALL delete only those three emails and SHALL leave other `users` rows.
- IF Feature tests factory-create an extra user THEN `users` count SHALL be 3 (migrated) plus the factory rows (`MysqlConnectionTest` expects 4 after one factory create).
- IF a guest bookmarks `/register` THEN the system SHALL 404; it SHALL NOT redirect to login as if the form still existed.
- IF `welcome.blade.php` still contains a Register link THEN `Route::has('register')` SHALL be false so the link is hidden.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| RF19 rooms and reservations seed | Not requested |
| Rewriting ADR-001 / ADR-002 | Docs stay; behavior change is this spec |
| Password recovery, email verification, roles | ADR-001 / login screen |
| Changing login validation, throttle, or session regenerate | Already shipped |
| Removing `UserRepository::create` / `existsByEmail` | Persistence port stays |
| Renaming `BrandPanel` `register-hero` or the hero image | Login still uses them |
| Deleting `.local/image/screen-create-user.png` | Only the screen MD is requested |
| Bootstrapping Playwright | No runner in the repo |
| Editing `0001_01_01_000000_create_users_table.php` | Keep schema migration stable |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Placement of the three rows | New data migration, not the schema file and not `DatabaseSeeder` | User asked for migration defaults; existing DBs need a new file; RNF06 | n |
| Insert API | `DB::table` + `Str::uuid7()` + `Hash::make('Senha123')` | Avoids Eloquent `hashed` double-hash; matches ADR-002 | n |
| Removed register HTTP | 404 on the old paths | Named routes gone; no fake register page | n |
| CreateUser / HTTP / Inertia register | Delete production files | Dead cadastro surface | n |
| `UserRepository` create/existsByEmail | Keep | Out of scope port cleanup | n |
| `DatabaseSeeder` Test User | Remove | Avoids a fourth admin and stale README credentials | n |
| RF19 combined seeder | Later task | User only asked for the three accounts | n |
| E2E | Not applicable | No Playwright in `package.json` | n |
| Colliding emails on an old DB | Unique constraint fails; use `migrate:fresh` | Challenge / local environments | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **New data migration + delete the public cadastro surface** — selected. Matches “na migration”, works on already-migrated databases, keeps schema history, hashes at insert time.
2. **Insert inside `0001_01_01_000000_create_users_table`** — rejected. That migration already ran locally; mixing schema and data makes rollback and review harder.
3. **`DatabaseSeeder` only** — rejected. User asked for migration defaults; seed is optional (`migrate` vs `migrate --seed`) and RF19 rooms/reservations are out of scope.
4. **Keep `CreateUser` and only hide the UI** — rejected. Routes would stay a backdoor; dead UI with live POST is worse than deleting the surface.

## Selected Approach

Approach 1.

- Add `database/migrations/2026_09_19_000000_insert_default_administrators.php` (timestamp after the users schema). `up()` inserts the three rows. `down()` deletes those emails.
- Clear `DatabaseSeeder` of the factory Test User.
- Remove guest register/users.create routes, `UserController`, `StoreUserRequest`, `CreateUser`, `User/Create.jsx`, `Services/users.js`, and the login cadastro block.
- Replace register Feature/Unit/Vitest coverage with migration + 404 + default-login + login-without-cadastro tests.
- Delete `docs/screens/screen-create-user.md`. Edit `screen-login.md` and README.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| AUTH-01 | P1: Default administrators | Execute | Implemented |
| AUTH-02 | P1: Guests cannot register | Execute | Implemented |
| AUTH-03 | P1: Login without cadastro | Execute | Implemented |
| DOC-01 | P1: Login without cadastro | Execute | Implemented |

**Coverage:** 4 total, 4 mapped to tasks, 0 unmapped
