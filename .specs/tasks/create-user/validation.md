# Create User Validation

**Date**: 2026-09-16
**Spec**: `.specs/features/create-user/spec.md`
**Diff range**: working-tree (git deferred)
**Verifier**: independent sub-agent (author ≠ verifier)

---

## Validation

**Result**: PASS

---

## Task Completion

| Task | Status | Notes |
| ---- | ------ | ----- |
| T1 | ✅ Done | `deleted_at` via `$table->softDeletes()` na migration `users` |
| T2 | ✅ Done | `HASH_DRIVER=argon` em `.env.example` e `phpunit.xml` |
| T3 | ✅ Done | Entidade Domain sem Illuminate; `UserRepository` declara `existsByEmail` e `create` |
| T4 | ✅ Done | Histórico: validação saiu do use case em T7 |
| T5 | ✅ Done | Model Infra com `HasUuids`, `SoftDeletes`, cast `hashed`; `app/Models/User.php` removido |
| T6 | ✅ Done | `EloquentUserRepository`; bind em `AppServiceProvider`; `app/Actions/CreateUser.php` removido |
| T7 | ✅ Done | `CreateUser::execute` só delega ao repositório |
| T8 | ✅ Done | `StoreUserRequest`, `UserController`, rotas GET/POST, testes HTTP |
| T9 | ✅ Done | `Pages/User/Create.jsx`, layout, `users.js`, `app.jsx`; sem Index/Edit/Hooks |

---

## Spec-Anchored Acceptance Criteria

Re-derived from `spec.md`. Assertions checked against the spec-defined outcome, not the implementation shape.

### P1: Criar usuário no módulo User

| Criterion (WHEN X THEN Y) | Spec-defined outcome | `file:line` + assertion | Result |
| ------------------------- | -------------------- | ----------------------- | ------ |
| WHEN name, email e password válidos THEN persistir linha em `users` com esses name e email | Row in `users` with given name and email | `tests/Feature/User/CreateUserPersistenceTest.php:27` - `assertSame('Ada Lovelace', $user->name)`; `:28` - `assertSame('ada@example.com', $user->email)`; `:29-30` same on Eloquent model; `:43-46` - `assertDatabaseHas('users', ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])` | ✅ PASS |
| WHEN o usuário é persistido THEN atribuir um `id` UUID | Persisted `id` is a UUID | `tests/Feature/User/CreateUserPersistenceTest.php:26` - `assertTrue(Str::isUuid($user->id))` | ✅ PASS |
| WHEN o usuário é persistido THEN `password` em hash Argon2i, diferente do texto puro, verificável com o original | Algo Argon2i; hash ≠ plaintext; `Hash::check` true | `tests/Feature/User/CreateUserPersistenceTest.php:31` - `assertNotSame('secret123', $user->passwordHash)`; `:32` - `assertTrue(Hash::check('secret123', $user->passwordHash))`; `:33` - `assertSame('argon2i', password_get_info(...)['algoName'])` | ✅ PASS |
| The system SHALL persistir somente as colunas ADR-002 | Attributes ⊆ `{id,name,email,password,remember_token,created_at,updated_at,deleted_at}` | `tests/Feature/User/CreateUserPersistenceTest.php:39-40` - `assertSame([], array_values(array_diff(array_keys($model->getAttributes()), $allowed)))`; `tests/Feature/User/UserSchemaTest.php:33-34` - same allowlist on factory persist | ✅ PASS |
| WHEN `remember_token` não é informado THEN persistir `remember_token` nulo | `remember_token` is null | `tests/Feature/User/CreateUserPersistenceTest.php:34` - `assertNull($user->rememberToken)`; `:48` - `'remember_token' => null` in `assertDatabaseHas` | ✅ PASS |
| WHEN o usuário é persistido THEN persistir `deleted_at` nulo | `deleted_at` is null | `tests/Feature/User/CreateUserPersistenceTest.php:35` - `assertNull($user->deletedAt)`; `:47` - `'deleted_at' => null` in `assertDatabaseHas` | ✅ PASS |

### P2: HTTP de criação com validação no Form Request

| Criterion (WHEN X THEN Y) | Spec-defined outcome | `file:line` + assertion | Result |
| ------------------------- | -------------------- | ----------------------- | ------ |
| WHEN `GET /users/create` THEN 200 com a página Inertia `User/Create` | HTTP 200; Inertia component `User/Create` | `tests/Feature/User/CreateUserHttpTest.php:24` - `assertOk()`; `:26` - `$page->component('User/Create')` | ✅ PASS |
| WHEN `POST /users` com name, email e password válidos THEN persistir a linha e redirecionar para `users.create` | Redirect to `users.create`; row with given name and email | `tests/Feature/User/CreateUserHttpTest.php:37` - `assertRedirect(route('users.create'))`; `:39` - `assertDatabaseCount('users', 1)`; `:40-43` - `assertDatabaseHas('users', ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])` | ✅ PASS |
| IF `POST /users` omitir `name`, `email` ou `password` THEN erro de validação na chave do campo sem persistir | Session error on omitted key; 0 rows | `tests/Feature/User/CreateUserHttpTest.php:52` - `assertSessionHasErrors($field)` (provider `:161-172` covers name, email, password); `:54` - `assertDatabaseCount('users', 0)` | ✅ PASS |
| IF `POST /users` enviar email já existente, inclusive com `deleted_at` preenchido, THEN erro na chave `email` sem registro adicional | Session error on `email`; count stays 1; no `Ada Two` row | Active: `tests/Feature/User/CreateUserHttpTest.php:70` - `assertSessionHasErrors('email')`; `:72` - `assertDatabaseCount('users', 1)`; `:73` - `assertDatabaseMissing('users', ['name' => 'Ada Two'])`. Soft-deleted: `:90` - `assertSessionHasErrors('email')`; `:92` - `assertSame(1, UserModel::withTrashed()->count())`; `:93` - `assertDatabaseMissing('users', ['name' => 'Ada Two'])` | ✅ PASS |
| IF `POST /users` enviar `password` com menos de 8 caracteres THEN erro na chave `password` sem persistir | Session error on `password`; 0 rows | `tests/Feature/User/CreateUserHttpTest.php:105` - `assertSessionHasErrors('password')`; `:107` - `assertDatabaseCount('users', 0)` | ✅ PASS |
| IF `POST /users` enviar `email` inválido THEN erro na chave `email` sem persistir | Session error on `email`; 0 rows | `tests/Feature/User/CreateUserHttpTest.php:119` - `assertSessionHasErrors('email')`; `:121` - `assertDatabaseCount('users', 0)` | ✅ PASS |
| The system SHALL validar name, email, password e unicidade no Form Request, não no use case `CreateUser` | Validation on HTTP Form Request; use case only delegates persist | HTTP errors above prove Form Request. `tests/Unit/User/CreateUserTest.php:18-19` - `assertSame` name/email on returned entity; `:20-23` - `assertSame([['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'password' => 'secret123']], $users->created)` | ✅ PASS |

**Status**: ✅ All ACs covered. 13/13 matched the spec-defined outcome. 0 spec-precision gaps.

Payload/conjunction: P1 persist and P2 happy-path POST assert the stored `name` and `email` values, not only that create ran.

---

## Discrimination Sensor

Scratch isolation: `git worktree add` + vendor symlink was invalid here. Composer classmap/`$baseDir` resolved to the real app, so mutants in the worktree were not loaded. Worktree removed. Fallback used: backups of the two target files, mutate the real copies one at a time, run tests, restore from backups. `git stash` was not used.

Baseline before sensor: `git status --porcelain` saved; sha256 `StoreUserRequest.php` = `8334a48e046e420cbda334d8a175fcfe5a57f383cb7a50e9fd480df8be8fc34b`; `UserController.php` = `933aa9373cb331a8173f172088bd1e70170861faa21df60598b88fb38fa689d6`. After cleanup, porcelain and both hashes matched.

Command against scratch (real files while mutated): `php artisan test tests/Feature/User/CreateUserHttpTest.php`

| Mutation | File:line | Description | Killed? |
| -------- | --------- | ----------- | ------- |
| 1 | `app/Modules/User/Infra/Http/Requests/StoreUserRequest.php:23` | Dropped `Rule::unique('users', 'email')` from email | ✅ Killed (`CreateUserHttpTest.php:70` duplicate; `:90` soft-deleted; 2 failed, 9 passed; tests expected session error, got 500 unique constraint) |
| 2 | `app/Modules/User/Infra/Http/Requests/StoreUserRequest.php:24` | `Password::defaults()` → `Password::min(7)` so 7-char passwords pass | ✅ Killed (`CreateUserHttpTest.php:105` - session missing `errors`; 1 failed, 10 passed) |
| 3 | `app/Modules/User/Infra/Http/Controllers/UserController.php:16` | `Inertia::render('User/Create')` → `'User/Index'` | ✅ Killed (`CreateUserHttpTest.php:26` - expected `User/Create`, got `User/Index`; 1 failed, 10 passed) |

**Sensor depth**: lightweight
**Result**: 3/3 killed - PASS ✅

---

## Interactive UAT Results (if performed)

UAT skipped. Verifier is a sub-agent; cannot wait on a human. GET Inertia `User/Create` and POST contracts are covered by Feature HTTP tests. The create form is a single page without complex interaction that needs human judgment.

| # | Test | Result | Details |
| --- | ---- | ------ | ------- |
| 1 | Interactive UAT | ⏭️ Skip | Automated HTTP covers the user-facing contract |

---

## Code Quality

Checked against `.cursor/skills/tlc-spec-driven/references/coding-principles.md` and `docs/tests.md`.

| Principle | Status |
| --------- | ------ |
| Minimum code | ✅ |
| Surgical changes | ✅ |
| No scope creep | ✅ (no Index/Edit/Hooks; seeder RF19 not added; seeder only retargets the Infra model) |
| Matches patterns | ✅ (module Domain/Application/Infra; Domain has no Illuminate; thin controller + Form Request) |
| Spec-anchored outcome check (asserted values match spec) | ✅ |
| Per-layer Coverage Expectation met (domain 1:1 ACs; routes happy+edge+error) | ✅ (unit: use case delegation; Feature persist: ADR-002; HTTP: GET + POST happy + each validation error + unique including soft-deleted) |
| Every test maps to a spec requirement - no unclaimed tests | ✅ (`UserSchemaTest` → USER-04 allowlist; 8-char tests → password edge; `email_verified_at` HTTP test → listed edge) |
| Documented guidelines followed: `docs/tests.md` | ✅ |

`StoreUserRequest` also applies `max:255` on name/email. That bound is not an AC. It does not change the spec outcomes above.

`UserRepository::existsByEmail` is unused by `CreateUser` after T7. Unique is `Rule::unique` on the table. Leftover contract, not extra product scope.

---

## Edge Cases

- [x] name, email ou password ausente no POST: rejeita sem persistir (`CreateUserHttpTest.php:52`, `:54`)
- [x] email duplicado no POST: rejeita sem registro adicional (`CreateUserHttpTest.php:70`, `:72-73`)
- [x] email de usuário soft-deleted: rejeita como duplicado (`CreateUserHttpTest.php:90`, `:92-93`)
- [x] password com 7 caracteres rejeitada; 8 caracteres persiste (`CreateUserHttpTest.php:105`, `:107`; `:132-134`; `CreateUserPersistenceTest.php:60-61`)
- [x] `email_verified_at` no input é ignorado; só colunas ADR-002 (`CreateUserHttpTest.php:148-152` name/email persistidos após campo extra; `CreateUserPersistenceTest.php:39-40` allowlist)

---

## Gate Check

- **Gate command**: `php artisan test && vendor/bin/pint --test app database/factories tests/Unit/User tests/Feature/User database/migrations/0001_01_01_000000_create_users_table.php`
- **Result**: 17 passed, 0 failed, 0 skipped
- **Assertions**: 87
- **Test count before feature**: 9 (HEAD: 2 example + 6 old Feature `CreateUserTest` + 1 `UserSchemaTest`)
- **Test count after feature**: 17
- **Delta**: +8 new tests
- **Skipped tests**: none
- **Failures**: none
- **Pint**: passed
- **Deleted tests**: old `tests/Feature/User/CreateUserTest.php` (6 cases) replaced by Unit delegation + persist Feature + HTTP Feature. Coverage moved with T7/T8; not a silent deletion.

---

## Fix Plans (if issues found)

None. No surviving mutants. No AC gaps.

---

## Requirement Traceability Update

`spec.md` was not edited (verifier is read-only except this report). Recorded here only.

| Requirement | Previous Status | New Status |
| ----------- | --------------- | ---------- |
| USER-01 | Verified | ✅ Verified |
| USER-02 | Verified | ✅ Verified |
| USER-03 | Verified | ✅ Verified |
| USER-04 | Verified | ✅ Verified |
| USER-05 | Verified | ✅ Verified |
| USER-06 | Verified | ✅ Verified |
| USER-07 | Verified | ✅ Verified |
| USER-08 | Verified | ✅ Verified |
| USER-09 | Verified | ✅ Verified |
| USER-10 | Verified | ✅ Verified |
| USER-11 | Verified | ✅ Verified |
| USER-12 | Verified | ✅ Verified |
| USER-13 | Verified | ✅ Verified |

---

## Summary

**Overall**: ✅ Ready

**Spec-anchored check**: 13/13 ACs matched spec outcome; 0 spec-precision gaps
**Sensor**: 3/3 mutations killed
**Gate**: 17 passed, 0 failed

**What works**: CreateUser persists ADR-002 columns, Argon2i password, UUID id, null `remember_token` and `deleted_at`. GET `/users/create` renders Inertia `User/Create`. POST `/users` persists name/email and redirects to `users.create`. Invalid input and duplicate email (active and soft-deleted) fail on the field key without an extra row. Validation lives in `StoreUserRequest`, not in the use case.

**Issues found**: none

**Next steps**: none from this verifier pass
