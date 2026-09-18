# Specification

## Context

ReservaSalas already authenticates administrators at `/login` with Laravel session auth (task 0005). `LoginController::store` delegates to `LoginRequest::authenticate()`, which calls `Auth::attempt` directly. `docs/architecture.md` requires Controller → Request Validation → Use Case → Domain. `CreateUser` already follows that path; login does not.

## Problem

Login business workflow lives in Infra HTTP. Application has no `AuthenticateUser` use case. Controllers are not thin adapters, and Form Request code both validates input and authenticates. That violates the documented dependency rule (Infra → Application → Domain) even though the HTTP product contract is already correct.

## Problem Statement

Administrators must keep signing in with email and password through native Laravel session auth, but the workflow that decides whether credentials are accepted must run in an Application use case that depends only on a Domain port. Illuminate (`Auth`, `RateLimiter`, Form Requests, session) must stay at the Infra edge.

## Goal

- Add `AuthenticateUser` in Application, driven by a Domain `UserAuthenticator` port, with `InvalidCredentials` as the failure.
- Implement the port in Infra with `Auth::attempt` (ADR-002 / RNF07). Bind it in the container.
- Thin `LoginController::store` and strip `Auth::attempt` from `LoginRequest`.
- Keep the existing HTTP contract: validation messages, generic credentials error, session regenerate, throttle 5/email+IP, intended redirect, logout.
- Cover the use case with unit tests that fake the Domain port. Keep `LoginHttpTest` green.
- Do not change React, routes, or product features.

## User Stories

### P1: Authenticate through AuthenticateUser ⭐ MVP

**User Story**: As the backend, I want login credential verification to run in `AuthenticateUser` against a Domain `UserAuthenticator` port so that Application and Domain stay free of Illuminate while Infra still uses Laravel session auth.

**Why P1**: This is the architectural gap called out for this task. Without it, login remains an Infra HTTP procedure.

**Acceptance Criteria**:

1. WHEN `AuthenticateUser` is given email and password whose `UserAuthenticator::attempt` returns true THEN the use case SHALL complete without throwing
2. IF `UserAuthenticator::attempt` returns false THEN `AuthenticateUser` SHALL throw `App\Modules\User\Application\Errors\InvalidCredentials`
3. WHEN `AuthenticateUser` runs THEN it SHALL pass the given email and password to `UserAuthenticator::attempt` and SHALL NOT import `Illuminate`
4. The `UserAuthenticator` interface SHALL live in Domain, SHALL be pure PHP, and SHALL NOT import `Illuminate`
5. `InvalidCredentials` SHALL live in `Application/Errors` and SHALL NOT import `Illuminate`

**Independent Test**: `tests/Unit/User/AuthenticateUserTest.php` with an in-file fake of `UserAuthenticator`, same style as `CreateUserTest`.

---

### P1: Preserve the HTTP login contract ⭐ MVP

**User Story**: As an administrator, I want `POST /login` to keep authenticating, rejecting, throttling, and regenerating the session exactly as today so that moving the workflow into a use case does not change the product.

**Why P1**: RF01/RF02 and `screen-login.md` are already shipped. This task is a structural move, not a new feature.

**Acceptance Criteria**:

1. WHEN `POST /login` receives credentials of an active user THEN the system SHALL authenticate that user through `AuthenticateUser`, regenerate the session identifier, and redirect to `/reservations` or to the originally requested protected URL
2. IF `POST /login` omits `email` THEN the system SHALL return `Informe seu e-mail.` on `email` without authenticating
3. IF `POST /login` omits `password` THEN the system SHALL return `Informe sua senha.` on `password` without authenticating
4. IF `POST /login` sends a malformed email THEN the system SHALL return `Informe um endereço de e-mail válido.` on `email` without authenticating
5. IF `POST /login` sends unknown email, wrong password, or a soft-deleted user THEN the system SHALL return `E-mail ou senha inválidos.` on `credentials` without authenticating
6. IF `AuthenticateUser` throws `InvalidCredentials` THEN `LoginController` SHALL map that error to the same `credentials` ValidationException and SHALL hit the login rate limiter
7. WHEN login succeeds THEN `LoginController` SHALL clear the login rate limiter and SHALL regenerate the session identifier
8. IF the same email and IP fail authentication more than 5 times THEN the system SHALL reject further attempts for at least 60 seconds without authenticating, even with the correct password
9. WHEN email is submitted THEN `LoginRequest` SHALL trim and lowercase it before `AuthenticateUser` runs
10. The Infra `UserAuthenticator` implementation SHALL call `Auth::attempt` with only `email` and `password` and SHALL NOT enable remember-me
11. `LoginRequest` SHALL NOT call `Auth::attempt` or own the authentication workflow
12. WHEN an authenticated administrator sends `POST /logout` THEN the system SHALL still log them out, invalidate the session, regenerate the CSRF token, and redirect to `/login`

**Independent Test**: existing `tests/Feature/User/LoginHttpTest.php` remains green; no new product assertions required beyond the use-case wiring.

---

## Acceptance Criteria

1. WHEN `AuthenticateUser` is given credentials whose port `attempt` returns true THEN the use case SHALL complete without throwing
2. IF the port `attempt` returns false THEN `AuthenticateUser` SHALL throw `InvalidCredentials`
3. The Application use case, Domain port, and `InvalidCredentials` SHALL NOT import `Illuminate`
4. WHEN `POST /login` receives credentials of an active user THEN the system SHALL authenticate through `AuthenticateUser`, regenerate the session identifier, and redirect to `/reservations` or the intended URL
5. IF `POST /login` sends unknown email, wrong password, or a soft-deleted user THEN the system SHALL return `E-mail ou senha inválidos.` on `credentials` without authenticating
6. IF `AuthenticateUser` throws `InvalidCredentials` THEN the HTTP adapter SHALL map it to that generic credentials error and SHALL record a rate-limit hit
7. WHEN login succeeds THEN the HTTP adapter SHALL clear the rate limiter and SHALL regenerate the session identifier
8. IF the same email and IP fail more than 5 times THEN the system SHALL reject further attempts for at least 60 seconds without authenticating
9. The Infra adapter SHALL use Laravel `Auth::attempt` and SHALL NOT compare passwords in Application or Domain
10. `LoginRequest` SHALL validate and normalize HTTP input and SHALL NOT call `Auth::attempt`
11. WHEN an authenticated administrator sends `POST /logout` THEN the system SHALL keep the current logout HTTP behavior
12. The system SHALL NOT add password recovery, JWT, roles, remember-me, or a logout use case

Traceability aliases: AC-001 … AC-012 map to items 1 … 12 above.

## Edge Cases

- IF extra fields such as `name` are posted THEN the system SHALL ignore them and authenticate only on email and password
- IF email is ` Ada@Example.com ` for an active user stored as `ada@example.com` THEN the system SHALL authenticate
- IF the user row is soft-deleted THEN `Auth::attempt` SHALL fail and `AuthenticateUser` SHALL throw `InvalidCredentials`
- IF throttle is active THEN the system SHALL not call a successful authentication path even with the correct password until the window expires
- IF `UserAuthenticator::attempt` returns true THEN `AuthenticateUser` SHALL NOT throw and SHALL NOT apply HTTP status or redirect itself

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Password recovery | ADR-001 and screen-login closed scope |
| JWT / Sanctum SPA / custom tokens | ADR-002 / RNF07 native session auth |
| Roles / RBAC | ADR-001 |
| Remember-me checkbox | Not in screen-login |
| Logout use case | Session invalidate/regenerateToken is HTTP adaptation; user asked to move login only |
| Rate limiter port in Domain | Illuminate `RateLimiter`; stays at Infra HTTP |
| Password hashing or `Hash::check` in Application | ADR-002: Laravel hasher and `Auth::attempt` at the Infra edge |
| React / Inertia login screen changes | Already shipped; HTTP contract unchanged |
| Playwright bootstrap | No runner in the repo |
| Register / `CreateUser` changes | Unrelated |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Port method shape | `attempt(email, password): bool`; use case throws `InvalidCredentials` on false | Smallest port; matches `CreateUser` (no DTO/Result); HTTP maps the error | n |
| Where `Auth::attempt` runs | Infra adapter `LaravelUserAuthenticator`, not the controller and not Application | Packet: native Laravel session auth at Infra; Illuminate out of Application | n |
| Session regenerate | Stays in `LoginController` after a successful use case | HTTP adaptation; Laravel starter-kit order (attempt then regenerate) | n |
| Rate limiting | Stays on `LoginRequest` helpers called by the controller | Illuminate `RateLimiter` needs email+IP from the HTTP request | n |
| Email normalization | Stays in `LoginRequest::prepareForValidation` | HTTP input cleanup; use case receives normalized strings | n |
| Logout | Remains `LoginController::destroy` | Not requested; session lifecycle is Infra HTTP | n |
| Container bind | `AppServiceProvider`, same as `UserRepository` | Existing project pattern; no new module provider | n |
| Port file location | `Domain/UserAuthenticator.php` (not `UserRepository`) | Persistence ≠ credential verification; avoid extra Domain folders | n |
| Adapter file location | `Infra/Http/LaravelUserAuthenticator.php` | Infra HTTP edge; avoids a new `Infra/Auth` folder not in `tree.md` | n |
| Frontend | Unchanged | Product UI already matches screen-login | n |
| Task 0005 "do not add AuthenticateUser" | Superseded by this task | User now requires the use case with a Domain port | n |

**Open questions:** none - all resolved or logged above.

## Considered Approaches

| Approach | Summary | Trade-off |
| -------- | ------- | --------- |
| A. Call `Auth::attempt` from Application | Shortest move of `authenticate()` into a use case | Rejected: Application would import Illuminate |
| B. `UserRepository::findByEmail` + `Hash::check` in Application, `Auth::login` in the controller | Use case owns verification; controller starts the session | Rejected: reimplements Laravel auth (RNF07 / ADR-002); pulls hashing into Application; duplicates soft-delete rules |
| C. Domain `UserAuthenticator` port + `AuthenticateUser` + Infra `Auth::attempt` adapter | Use case orchestrates; Illuminate stays in Infra | Selected: matches architecture.md and the packet |
| D. Keep `LoginRequest::authenticate()` and inject the use case there | Form Request still orchestrates login | Rejected: Form Requests would keep decisions outside input validation |

## Selected Approach

Approach C.

Flow:

```text
POST /login
  → LoginRequest (rules, email normalize, throttle helpers)
  → LoginController::store
       → AuthenticateUser::execute(email, password)
            → UserAuthenticator::attempt   (Domain port)
                 ↑ LaravelUserAuthenticator::attempt → Auth::attempt
       → InvalidCredentials → ValidationException credentials + RateLimiter hit
       → success → RateLimiter clear → session()->regenerate() → intended /reservations
```

Dependency direction: Infra (`LoginController`, `LoginRequest`, `LaravelUserAuthenticator`) → Application (`AuthenticateUser`, `InvalidCredentials`) → Domain (`UserAuthenticator`).

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| AUTH-01 | P1: Authenticate through AuthenticateUser | Execute | Implemented |
| AUTH-02 | P1: Authenticate through AuthenticateUser | Execute | Implemented |
| AUTH-03 | P1: Authenticate through AuthenticateUser | Execute | Implemented |
| AUTH-04 | P1: Preserve the HTTP login contract | Execute | Implemented |
| AUTH-05 | P1: Preserve the HTTP login contract | Execute | Implemented |
| AUTH-06 | P1: Preserve the HTTP login contract | Execute | Implemented |
| AUTH-07 | P1: Preserve the HTTP login contract | Execute | Implemented |

**Coverage:** 7 total, 7 mapped to T1–T2, 0 unmapped.

AUTH-01 = AC-001/AC-003 (success + no Illuminate). AUTH-02 = AC-002 (`InvalidCredentials`). AUTH-03 = AC-009/AC-010 (Infra `Auth::attempt`, Form Request does not authenticate). AUTH-04 = AC-004 (HTTP success + regenerate). AUTH-05 = AC-005/AC-006 (generic credentials mapping). AUTH-06 = AC-007/AC-008 (throttle + clear). AUTH-07 = AC-011/AC-012 (logout unchanged, no extra features).
