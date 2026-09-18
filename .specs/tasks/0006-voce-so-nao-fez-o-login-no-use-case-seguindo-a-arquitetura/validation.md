# Validation

## Acceptance Criteria

AUTH-01 / AC-001 / AC-003: `AuthenticateUser` completes when the port returns true and Application/Domain sources do not import Illuminate.

- `tests/Unit/User/AuthenticateUserTest.php:19` — `$this->assertSame(1, count($authenticator->attempts));`
- `tests/Unit/User/AuthenticateUserTest.php:59` — `$this->assertStringNotContainsString('Illuminate', $source, $class);`

AUTH-02 / AC-002: false `attempt` throws `InvalidCredentials`.

- `tests/Unit/User/AuthenticateUserTest.php:28-32` — `$this->fail('Expected InvalidCredentials to be thrown.');` then `$this->assertSame([['email' => 'ada@example.com', 'password' => 'wrong-password']], $authenticator->attempts);`

AUTH-03 / AC-009 / AC-010: email and password are forwarded; Infra uses `Auth::attempt` with `remember=false`; `LoginRequest` does not authenticate.

- `tests/Unit/User/AuthenticateUserTest.php:43-46` — `$this->assertSame([['email' => 'ada@example.com', 'password' => 'secret123']], $authenticator->attempts);`
- Implementation: `app/Modules/User/Infra/Http/LaravelUserAuthenticator.php:12-15` — `Auth::attempt(['email' => $email, 'password' => $password], false);`
- `LoginRequest` has no `authenticate()` and no `Auth::attempt` (search: no matches).

AUTH-04 / AC-004: valid login authenticates through the use case, regenerates the session, and redirects.

- `tests/Feature/User/LoginHttpTest.php:53-56` — `assertRedirect(route('reservations.index'))`, `assertAuthenticatedAs($user)`, `assertNotSame($previousSessionId, session()->getId())`
- `tests/Feature/User/LoginHttpTest.php:73-75` — intended URL redirect + `assertAuthenticatedAs($user)`
- Controller wiring: `LoginController.php:30` — `$authenticateUser->execute(...)`; `LoginController.php:40` — `$request->session()->regenerate();`

AUTH-05 / AC-005 / AC-006: unknown email, wrong password, and soft-deleted user return the generic credentials error; `InvalidCredentials` is mapped and counts as a rate-limit hit.

- `tests/Feature/User/LoginHttpTest.php:125-127` — `assertSessionHasErrors(['credentials' => 'E-mail ou senha inválidos.'])` + `assertGuest()`
- Mapping: `LoginController.php:31-36` — catch `InvalidCredentials` then `$request->hit()` and `ValidationException` with that message
- Throttle counting: `tests/Feature/User/LoginHttpTest.php:174-191`

AUTH-06 / AC-007 / AC-008: success clears the limiter and regenerates the session; a sixth failure is throttled without authenticating.

- `LoginController.php:39-40` — `$request->clear();` then `session()->regenerate()`
- `tests/Feature/User/LoginHttpTest.php:56` — session id changes on success
- `tests/Feature/User/LoginHttpTest.php:188-192` — sixth attempt `assertSessionHasErrors('credentials')` + `assertGuest()` even with the correct password

AUTH-07 / AC-011 / AC-012: logout HTTP behavior unchanged; no password recovery, JWT, roles, remember-me, or logout use case.

- `tests/Feature/User/LoginHttpTest.php:213-217` — logout redirects to `/login`, guest, new session id and CSRF token
- `LaravelUserAuthenticator.php:15` — `Auth::attempt(..., false)` (remember-me off)

Edge cases:

- Extra `name` field ignored: `LoginHttpTest.php:233-237`
- Trim/lowercase email: `LoginHttpTest.php:142-144`
- Soft-deleted user: `LoginHttpTest.php:125` via data provider `soft-deleted user`
- Password absent from old input: `LoginHttpTest.php:161`

Existing `CreateUserPersistenceTest` (argon2i + `Hash::check`) and `LoginHttpTest` were not weakened or deleted.

## Test Results

Unit (`php artisan test --testsuite=Unit`): 6 passed (13 assertions). New file `tests/Unit/User/AuthenticateUserTest.php` adds 4 tests. Prior Unit tests (`ExampleTest`, `CreateUserTest`) remain.

Feature (`php artisan test --testsuite=Feature`): 38 passed (234 assertions). `LoginHttpTest` and `CreateUserPersistenceTest` remain green.

Frontend (`npm test` / `vitest run`): 10 files, 57 passed. Existing login Vitest files remain.

E2E: not applicable (no Playwright project).

No tests skipped, disabled, or deleted.

## Required Gates

| Gate | Command | Required | Local result |
| ---- | ------- | -------- | ------------ |
| unit | `php artisan test --testsuite=Unit` | yes | 6 passed |
| integration | `php artisan test --testsuite=Feature` | yes | 38 passed |
| frontend | `npm test` | yes | 57 passed |
| lint | `vendor/bin/pint --test` | yes | passed |

Harness re-runs these gates after complete-phase. Local results are not the final harness verdict.

## Review Result

Pending independent harness Review. Execute did not open extra product files beyond the plan.

Additional files touched only to run gates in this worktree (gitignored, not feature scope): local `vendor/`, `node_modules/`, and `.env` copied from `.env.example`. Reason: the worktree had no Composer/npm install.

## Final Status

READY FOR REVIEW. Credential verification lives in Application `AuthenticateUser` behind Domain `UserAuthenticator`. Infra `LaravelUserAuthenticator` calls Laravel `Auth::attempt` with email+password only and remember-me disabled. `HASH_DRIVER=argon` and Eloquent `hashed` cast are unchanged. Passwords are never stored or compared as plaintext in Application/Domain.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `php artisan test --testsuite=Unit` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `npm test` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `php artisan test` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
<!-- harness-checks:end -->
