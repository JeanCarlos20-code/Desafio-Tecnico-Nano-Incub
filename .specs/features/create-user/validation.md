# Create User Validation

**Date**: 2026-09-16
**Spec**: `.specs/features/create-user/spec.md`
**Diff range**: working-tree (no git): create-user files
**Verifier**: independent sub-agent (author ≠ verifier)

## Validation

Feature `create-user`. Independent verifier.

---

## Task Completion

Tasks.md ausente (fase Tasks pulada). Passos implícitos do Execute:

| Task | Status | Notes |
| ---- | ------ | ----- |
| T1 Align User model + factory with users table | Done | `HasUuids`, fillable sem `email_verified_at`, cast `hashed`, factory alinhada às colunas da migration |
| T2 CreateUser action + validation tests | Done | Action valida e persiste; testes de feature cobrem happy path, erros e schema |

---

## Spec-Anchored Acceptance Criteria

| Criterion (WHEN X THEN Y) | Spec-defined outcome | `file:line` + assertion | Result |
| ------------------------- | -------------------- | ----------------------- | ------ |
| WHEN name, email e password válidos são fornecidos THEN persistir uma linha em `users` com esses valores de name e email | Linha persistida com `name` = `Ada Lovelace` e `email` = `ada@example.com` | `tests/Feature/User/CreateUserTest.php:30` - `$this->assertSame('Ada Lovelace', $user->name)`; `CreateUserTest.php:31` - `$this->assertSame('ada@example.com', $user->email)`; `CreateUserTest.php:41` - `$this->assertDatabaseCount('users', 1)`; `CreateUserTest.php:42` - `$this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com'])` | covered |
| WHEN o usuário é persistido THEN atribuir um `id` UUID | `id` é UUID | `tests/Feature/User/CreateUserTest.php:29` - `$this->assertTrue(Str::isUuid($user->id))` | covered |
| WHEN o usuário é persistido THEN armazenar `password` em hash diferente do texto puro e verificável com o texto original | Hash ≠ `'secret123'` e `Hash::check('secret123', stored)` é true | `tests/Feature/User/CreateUserTest.php:32` - `$this->assertNotSame('secret123', $user->getAuthPassword())`; `CreateUserTest.php:33` - `$this->assertTrue(Hash::check('secret123', $user->getAuthPassword()))` | covered |
| The system SHALL persistir somente as colunas `id`, `name`, `email`, `password`, `remember_token`, `created_at` e `updated_at` | Conjunto de atributos persistidos ⊆ essas 7 colunas (nenhuma extra) | `tests/Feature/User/CreateUserTest.php:38` - `$this->assertSame([], array_values(array_diff(array_keys($user->getAttributes()), $allowed)))`; `tests/Feature/User/UserSchemaTest.php:33` - mesma asserção no factory | covered |
| IF `name`, `email` ou `password` estiver ausente THEN lançar `ValidationException` na chave do campo ausente sem persistir registro | `ValidationException` com chave `name`/`email`/`password`; `users` permanece com 0 linhas | `tests/Feature/User/CreateUserTest.php:68` - `$this->assertArrayHasKey($field, $exception->errors())`; `CreateUserTest.php:71` - `$this->assertDatabaseCount('users', 0)`; provider em `CreateUserTest.php:133` cobre os três campos | covered |
| IF o email já existir em `users` THEN lançar `ValidationException` na chave `email` sem persistir registro adicional | `ValidationException` em `email`; count permanece 1; nome `Ada Two` não gravado | `tests/Feature/User/CreateUserTest.php:88` - `$this->assertArrayHasKey('email', $exception->errors())`; `CreateUserTest.php:91` - `$this->assertDatabaseCount('users', 1)`; `CreateUserTest.php:92` - `$this->assertDatabaseMissing('users', ['name' => 'Ada Two'])` | covered |
| IF `password` tiver menos de 8 caracteres THEN lançar `ValidationException` na chave `password` sem persistir registro | `ValidationException` em `password` para `'1234567'`; 0 linhas | `tests/Feature/User/CreateUserTest.php:105` - `$this->assertArrayHasKey('password', $exception->errors())`; `CreateUserTest.php:108` - `$this->assertDatabaseCount('users', 0)` | covered |
| IF `email` não for um endereço válido THEN lançar `ValidationException` na chave `email` sem persistir registro | `ValidationException` em `email` para `'not-an-email'`; 0 linhas | `tests/Feature/User/CreateUserTest.php:121` - `$this->assertArrayHasKey('email', $exception->errors())`; `CreateUserTest.php:124` - `$this->assertDatabaseCount('users', 0)` | covered |
| WHEN `remember_token` não é informado na criação THEN persistir `remember_token` nulo | `remember_token` is null | `tests/Feature/User/CreateUserTest.php:34` - `$this->assertNull($user->remember_token)` | covered |

**Status**: All ACs covered

**Payload/conjunction**: testes assertam valor/estado persistido (`name`, `email`, hash verificável, `remember_token` null, count/has/missing na tabela), não só que um método foi chamado.

---

## Discrimination Sensor

Isolamento: fallback de backup em `/tmp/create-user-sensor/CreateUser.php.bak` (sem git). Baseline sha256 pré-sensor:

- `app/Actions/CreateUser.php` = `c73bc7abd5508c56d81157088624f7c4e9b9e0fcfc460f6b685802f50dd5ab5c`
- `app/Models/User.php` = `e9e0db32c5e04dff9591fb2e91075c619eb1e340fbe3f6e9f967fd1309b2b552`
- `tests/Feature/User/CreateUserTest.php` = `b45076416d9d3ffe097dd39fc29b9c578ed93aeb82423e812e52fb2ff37388e4`
- `tests/Feature/User/UserSchemaTest.php` = `d88c94b4235bd296f3b85b5c26cf8d5cfddaa1d082c1c311c508c508bff1b376`

Pós-cleanup: hashes idênticos ao baseline.

| Mutation | File:line | Description | Killed? |
| -------- | --------- | ----------- | ------- |
| 1 | `app/Actions/CreateUser.php:21` | Removido `unique:users,email` da regra de email | killed (`CreateUserTest.php:81` — UniqueConstraintViolationException em vez de ValidationException) |
| 2 | `app/Actions/CreateUser.php:22` | `Password::defaults()` trocado por `Password::min(1)` | killed (`CreateUserTest.php:103` — `Expected ValidationException` para senha de 7 chars) |
| 3 | `app/Actions/CreateUser.php:25` | `User::query()->create` substituído por `return new User($validated)` sem gravar | killed (`CreateUserTest.php:29` — UUID false; `CreateUserTest.php:58` — count 0) |

**Sensor depth**: lightweight
**Result**: PASS

---

## Interactive UAT Results (if performed)

UAT pulado: feature backend-only, sem UI.

| # | Test | Result | Details |
| --- | ---- | ------ | ------- |
| 1 | UAT | skipped | backend-only, no UI |

---

## Code Quality

| Principle | Status |
| --------- | ------ |
| Minimum code | yes |
| Surgical changes | yes |
| No scope creep | yes |
| Matches patterns | yes |
| Spec-anchored outcome check (asserted values match spec) | yes |
| Per-layer Coverage Expectation met (domain 1:1 ACs; routes happy+edge+error) | yes |
| Every test maps to a spec requirement - no unclaimed tests | yes |
| Documented guidelines followed: `.cursor/skills/tlc-spec-driven/references/coding-principles.md` | yes |

Notas: Action mínima (Validator + create). Sem rota HTTP (fora de escopo). Factory gera `remember_token` só quando o factory informa o campo; AC9 cobre o caller da Action sem o campo. Testes não reclamados: `UserSchemaTest` mapeia USER-04 / T1 (contrato da tabela via factory); `test_eight_character_password_is_accepted` mapeia o edge case de 8 caracteres.

---

## Edge Cases

- [x] IF name, email ou password estiver ausente THEN rejeitar sem persistir — `CreateUserTest.php:68` + `CreateUserTest.php:71`
- [x] IF email duplicado THEN rejeitar sem persistir registro adicional — `CreateUserTest.php:88` + `CreateUserTest.php:91`
- [x] IF password tiver 7 caracteres THEN rejeitar; 8 caracteres válidos SHALL persistir — `CreateUserTest.php:105` (rejeita 7) e `CreateUserTest.php:58` (aceita 8)
- [x] IF o input incluir `email_verified_at` THEN ignorar e persistir só colunas da tabela — payload em `CreateUserTest.php:24` com `email_verified_at`; restrição de colunas em `CreateUserTest.php:38`

---

## Gate Check

- **Gate command**: `php artisan test` (autor também usou `vendor/bin/pint --test` nos PHP alterados)
- **Result line**: 11 passed, 0 failed, 0 skipped
- **Pint**: passed nos arquivos da feature
- **Test count before feature**: 2 (ExampleTest unit + feature)
- **Test count after feature**: 11
- **Delta**: +9 new tests
- **Skipped tests**: none
- **Failures**: none
- **Assertions**: 37

---

## Fix Plans (if issues found)

Nenhum. Sem ACs descobertos, sem mutantes sobreviventes, sem gaps de precisão da spec.

---

## Requirement Traceability Update

Spec.md não foi editado. Status observado no relatório:

| Requirement | Previous Status | New Status |
| ----------- | --------------- | ---------- |
| USER-01 | Implementing | Verified |
| USER-02 | Implementing | Verified |
| USER-03 | Implementing | Verified |
| USER-04 | Implementing | Verified |
| USER-05 | Implementing | Verified |
| USER-06 | Implementing | Verified |
| USER-07 | Implementing | Verified |
| USER-08 | Implementing | Verified |
| USER-09 | Implementing | Verified |

---

## Summary

**Overall**: Ready

**Spec-anchored check**: 9/9 ACs matched spec outcome
**Sensor**: 3/3 mutations killed
**Gate**: 11 passed

**What works**: CreateUser persiste name/email/UUID/hash, rejeita campos ausentes, email inválido/duplicado e senha curta sem gravar linha extra, ignora `email_verified_at`, deixa `remember_token` nulo.

**Issues found**: none

**Next steps**: nenhuma correção; feature pronta no working tree.
