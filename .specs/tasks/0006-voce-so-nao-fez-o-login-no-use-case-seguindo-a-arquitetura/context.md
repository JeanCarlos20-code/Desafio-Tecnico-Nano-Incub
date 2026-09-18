# Task Context

## Relevant Documentation

- `docs/architecture.md` — Controller → Request Validation → Use Case → Domain. Controllers stay thin and only adapt HTTP to application use cases. Application must not depend on HTTP, Inertia, Eloquent, or Laravel Requests. Domain is pure PHP (no `Illuminate`). Allowed direction: Infra → Application → Domain.
- `docs/tree.md` — User module: `Domain/{Entities,Repositories}`, `Application/{UseCases,Errors}`, `Infra/{Database,Http}`. Application errors live in `Errors`.
- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF01 administrator login, RF02 protected routes. RNF07 native Laravel auth (do not reinvent login). RNF08 server validation. RNF10 hashed passwords. RNF14 idiomatic Laravel. Closed scope: no password recovery, JWT, roles, email verification.
- `docs/adr/002-usuario-minimo-uuidv7-argon2-auth-laravel.md` — `Authenticatable` + session guard + `HASH_DRIVER=argon` + `SoftDeletes` (trashed users must not authenticate). Session/`remember_token` stay Laravel runtime at the Infra edge.
- `docs/screens/screen-login.md` — HTTP contract already shipped in task 0005: `GET/POST /login`, generic `E-mail ou senha inválidos.`, session regenerate, rate limit, logout. This task must not change that product contract.
- `docs/test/unit.md` — every use case needs unit tests; mock only the Domain port; no real database.
- `docs/test/integration.md` — every controller action stays covered by Feature tests on real HTTP + MySQL. Do not mock Eloquent/`Auth` in those tests.
- `docs/test/e2e.md` — Playwright is the E2E tool; this repo still has no Playwright runner. Do not bootstrap it.
- `docs/reviews/review-architecture.md` — Form Requests must not make decisions outside input validation; controllers must not own business workflows; do not reimplement framework auth without a documented reason.
- `harness/stack.yml` — gates: `php artisan test --testsuite=Unit|Feature`, `npm test`, `vendor/bin/pint --test`. `npx playwright test` is catalogued but not runnable.
- Task 0005 context explicitly avoided `AuthenticateUser` because Application cannot import `Auth`. This task supersedes that: introduce a Domain port so Application stays Illuminate-free.
- No confirmed TLC lessons.

## Relevant Components

- `app` monolith: Laravel 12, Inertia React, PHPUnit (`Unit` + `Feature`), Vitest. User module already has `CreateUser` (Application) + `UserRepository` (Domain) + `EloquentUserRepository` (Infra) + bind in `AppServiceProvider`.

## Relevant Code

- `app/Modules/User/Infra/Http/Controllers/LoginController.php` — `store()` calls `$request->authenticate()`, then `session()->regenerate()`, then `redirect()->intended(route('reservations.index'))`. `create()` renders Inertia `User/Login`. `destroy()` logs out, invalidates the session, regenerates the CSRF token. Logout stays here.
- `app/Modules/User/Infra/Http/Requests/LoginRequest.php` — HTTP rules (`email` required/email/max:255, `password` required), Portuguese messages, `prepareForValidation` trim+lowercase, `Auth::attempt($this->only('email', 'password'))`, `RateLimiter` 5 attempts / email+IP / 60s, generic `credentials` error. `authenticate()` must leave this class.
- `app/Modules/User/Application/UseCases/CreateUser.php` — pattern: `final` class, constructor-injected Domain port, `execute(...)`. No Illuminate imports. Follow this shape for `AuthenticateUser`.
- `app/Modules/User/Application/Errors/` — directory does not exist yet; create `InvalidCredentials` here (architecture: application errors live in `Errors`).
- `app/Modules/User/Domain/Repositories/UserRepository.php` — persistence only (`existsByEmail`, `create`). Do not add `attempt`/`login` onto this repository.
- `app/Modules/User/Domain/Entities/User.php` — pure PHP entity. Login does not need to return it; `Auth::attempt` already binds the session at Infra.
- `app/Modules/User/Infra/Database/Models/User.php` — `Authenticatable` + `SoftDeletes`. The Infra adapter must keep using Laravel's guard so soft-deleted users stay unauthenticated.
- `app/Providers/AppServiceProvider.php` — binds `UserRepository` → `EloquentUserRepository`. Bind the new authenticator port the same way.
- `tests/Unit/User/CreateUserTest.php` — PHPUnit `TestCase` (not Laravel), in-file fake of the Domain port. Mirror for `AuthenticateUserTest`.
- `tests/Feature/User/LoginHttpTest.php` — existing HTTP contract (success, intended URL, validation, generic credentials, trim/lowercase, throttle, logout, extra fields). Must stay green; do not weaken.
- `routes/web.php` — already has guest `/login` and `auth` `/logout` + `/reservations`. Do not change routes or middleware.
- React `Pages/User/Login.jsx` + `Services/session.js` — already post to `/login`. Do not change the frontend.
- No Playwright config. Vitest: `npm test` → `vitest run`.

## Existing Constraints

- Do not put `Illuminate`, `Auth`, `RateLimiter`, `FormRequest`, Inertia, or Eloquent in Application or Domain.
- Do not compare passwords in Application/Domain (`Hash::check` is Laravel; ADR-002 / RNF07 / screen-login: use the framework verifier).
- Do not add password recovery, JWT, roles, remember-me, email verification, or a REST login endpoint.
- Do not add a logout use case: session invalidate/regenerateToken is HTTP adaptation.
- Do not move rate limiting into Application (Illuminate `RateLimiter`; keep it at the Infra HTTP edge).
- Do not install Breeze/Fortify. Native session auth stays in the Infra adapter via `Auth::attempt`.
- Do not mock the database in Feature tests. Do not bootstrap Playwright.
- `app/Modules/User/**` groups as module `user`. `app/Providers/AppServiceProvider.php` groups as `providers`.
- Product HTTP messages stay: `Informe seu e-mail.` / `Informe um endereço de e-mail válido.` / `Informe sua senha.` / `E-mail ou senha inválidos.`

## Important Decisions

- Use case name: `AuthenticateUser` in `app/Modules/User/Application/UseCases/AuthenticateUser.php`.
- Domain port: `App\Modules\User\Domain\UserAuthenticator` with `attempt(string $email, string $password): bool`. Lives at Domain root (not on `UserRepository`) so persistence and credential verification stay separate. Pure PHP; no session types in the interface.
- Application error: `App\Modules\User\Application\Errors\InvalidCredentials` thrown when `attempt` returns false. No DTO / Result type (architecture: do not add layers without need).
- Infra adapter: `App\Modules\User\Infra\Http\LaravelUserAuthenticator` implements the port with `Auth::attempt(['email' => $email, 'password' => $password])` and `remember = false`. Soft delete remains Eloquent's job.
- Container: bind `UserAuthenticator` → `LaravelUserAuthenticator` in `AppServiceProvider`, same as `UserRepository`.
- `LoginController::store` becomes: validated email/password → ensure throttle → `AuthenticateUser::execute` → on `InvalidCredentials` hit limiter and throw `ValidationException` with `credentials` = `E-mail ou senha inválidos.` → on success clear limiter, `session()->regenerate()`, `redirect()->intended(route('reservations.index'))`.
- `LoginRequest` keeps HTTP rules, messages, email normalization, and throttle helpers (`ensureIsNotRateLimited` / hit / clear / `throttleKey`). Remove `authenticate()` and the `Auth` import.
- Session regeneration stays in the controller (HTTP adaptation after the use case succeeds). `Auth::attempt` inside the adapter already starts the session; regenerate remains a separate HTTP step, matching current Laravel behavior.
- Email trim/lowercase stays in `LoginRequest::prepareForValidation`. The use case receives already-normalized strings.
- Frontend, routes, logout, and GET `/login` stay unchanged.
