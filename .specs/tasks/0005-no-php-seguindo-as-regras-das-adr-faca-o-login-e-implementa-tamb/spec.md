# Specification

## Context

ReservaSalas already persists administrators (ADR-002) and registers them at `/register`. There is no `/login` route, no session login via `Auth::attempt`, and `/reservations` is public. `docs/screens/screen-login.md` defines the administrator login screen and RF02 protection. README still claims login is unimplemented.

## Problem

An existing administrator cannot sign in. Guests can open the panel stub. Register's "Ir para o login" link 404s. RF01 and RF02 are unmet.

## Problem Statement

Administrators need a guest-only Inertia screen at `/login` that authenticates email and password through Laravel session auth, shows server validation and a generic credentials error, regenerates the session on success, and sends them to `/reservations`. Unauthenticated visitors must not reach panel routes.

## Goal

- Ship `User/Login` per `docs/screens/screen-login.md` and `.local/image/screen-login.png` (layout, copy, states, a11y — not pixel-perfect dimensions).
- Submit through Inertia `POST /login` via `Services/session.js` using `Auth::attempt`, session regeneration, and rate limiting.
- Protect panel routes with `auth`; keep `/login` (and register) for guests.
- Provide `POST /logout` that invalidates the session and regenerates the CSRF token.
- Keep server validation authoritative; never recover passwords, issue JWT, or add roles.
- Update `README.md` so it no longer says login is missing.

## User Stories

### P1: Login screen matching screen-login ⭐ MVP

**User Story**: As an administrator, I want the `/login` screen specified in `screen-login.md` so that I can enter email and password in a usable, accessible form.

**Why P1**: Without this UI, RF01 cannot be operated as designed.

**Acceptance Criteria**:

1. WHEN an unauthenticated visitor opens `GET /login` THEN the system SHALL respond 200 and render the Inertia page `User/Login`
2. The screen SHALL show heading `Acesse sua conta`, supporting text `Entre para gerenciar as salas e reservas.`, and primary control `Entrar`
3. The screen SHALL render required fields E-mail and Senha with placeholder `seu@email.com` on email, autocomplete `email` / `current-password`, and `aria-required="true"`
4. The brand panel SHALL show wordmark `ReservaSalas`, copy `Salas organizadas. Reuniões que acontecem.`, and footer `Mais produtividade para o seu time.`
5. The screen SHALL NOT render a password-recovery control or a registration link
6. WHEN the viewport is wide THEN the screen SHALL use a two-column card; WHEN the viewport is narrow THEN the screen SHALL use one column, compact or hide the hero image, keep controls full width, and avoid horizontal scrolling
7. WHEN the visitor activates the password visibility control THEN the system SHALL toggle the input between `password` and `text` and SHALL expose accessible name `Mostrar senha` or `Ocultar senha`

**Independent Test**: Open `/login` in isolation (Feature `assertInertia` + Vitest render); no reservations CRUD required.

---

### P1: Authenticate, reject, and end the session ⭐ MVP

**User Story**: As an administrator, I want to submit credentials through Inertia and either enter a regenerated session or see a safe error so that only valid, non-deleted users reach the panel.

**Why P1**: Success, validation, lockout, and logout are the product behavior.

**Acceptance Criteria**:

1. WHEN the visitor submits the form THEN the system SHALL send an Inertia POST to `/login` with `email` and `password` through `Services/session.js`
2. WHEN `POST /login` receives credentials of an active user THEN the system SHALL authenticate that user, regenerate the session identifier, and redirect to `/reservations` or to the originally requested protected URL
3. IF `POST /login` omits `email` THEN the system SHALL return `Informe seu e-mail.` on `email` without authenticating
4. IF `POST /login` omits `password` THEN the system SHALL return `Informe sua senha.` on `password` without authenticating
5. IF `POST /login` sends a malformed email THEN the system SHALL return `Informe um endereço de e-mail válido.` on `email` without authenticating
6. IF `POST /login` sends unknown email, wrong password, or a soft-deleted user THEN the system SHALL return `E-mail ou senha inválidos.` on `credentials` without authenticating and SHALL NOT reveal which part failed
7. WHILE the Inertia request is processing the screen SHALL disable `Entrar`, set its label to `Entrando...`, show a loading indicator, and ignore further submits
8. IF validation or credentials fail THEN the system SHALL keep the email value, clear the password field, and SHALL NOT put the raw password in old input or the response body
9. IF credentials fail THEN the screen SHALL show the generic error in an `aria-live="polite"` region and move focus to the password field
10. WHEN Laravel returns field validation errors THEN the screen SHALL show each backend message below the matching field with `aria-invalid` and `aria-describedby`
11. IF a non-validation, non-credentials server failure occurs THEN the screen SHALL show `Não foi possível entrar. Tente novamente.` in an `aria-live="polite"` region above the form
12. WHEN email is submitted THEN the system SHALL trim and lowercase it before authentication
13. IF the same email and IP fail authentication more than 5 times THEN the system SHALL reject further attempts for at least 60 seconds without authenticating
14. WHEN an authenticated administrator sends `POST /logout` THEN the system SHALL log them out, invalidate the session, regenerate the CSRF token, and redirect to `/login`

**Independent Test**: Feature HTTP for attempt/redirect/validation/soft-delete/throttle/logout; Vitest for loading, field errors, credentials banner, password reset.

---

### P1: Protected panel routes ⭐ MVP

**User Story**: As the system, I want panel routes to require a session so that guests cannot use the administrative area.

**Why P1**: RF02.

**Acceptance Criteria**:

1. IF an unauthenticated visitor opens `GET /reservations` THEN the system SHALL redirect to `/login`
2. WHEN an authenticated administrator opens `GET /login` THEN the system SHALL redirect to `/reservations` without rendering the login form
3. WHILE the visitor is unauthenticated the system SHALL keep `GET /login` and `GET /register` reachable

**Independent Test**: Feature HTTP with and without `actingAs`.

---

### P1: README access policy ⭐ MVP

**User Story**: As a reviewer of the technical challenge, I want `README.md` to describe login and remaining public registration so that production risk is explicit.

**Why P1**: The previous README claim that login does not exist becomes false.

**Acceptance Criteria**:

1. The README SHALL state that administrators sign in at `/login` and that `/register` remains publicly reachable as a challenge convenience, which is not recommended in production

**Independent Test**: README contains that policy in Portuguese or English.

---

## Acceptance Criteria

1. WHEN an unauthenticated visitor opens `GET /login` THEN the system SHALL respond 200 and render Inertia page `User/Login`
2. The screen SHALL display heading `Acesse sua conta`, supporting text `Entre para gerenciar as salas e reservas.`, and primary control `Entrar`
3. The screen SHALL render required fields E-mail and Senha with placeholder `seu@email.com` on email, autocomplete `email` / `current-password`, and `aria-required="true"`
4. The brand panel SHALL display wordmark `ReservaSalas`, copy `Salas organizadas. Reuniões que acontecem.`, and footer `Mais produtividade para o seu time.`
5. The screen SHALL NOT include password recovery or a registration link
6. WHEN the visitor submits the form THEN the system SHALL send an Inertia POST to `/login` with `email` and `password` through `Services/session.js`
7. WHEN `POST /login` receives credentials of an active user THEN the system SHALL authenticate that user, regenerate the session identifier, and redirect to `/reservations` or to the originally requested protected URL
8. IF `POST /login` omits `email` THEN the system SHALL return `Informe seu e-mail.` on `email` without authenticating
9. IF `POST /login` omits `password` THEN the system SHALL return `Informe sua senha.` on `password` without authenticating
10. IF `POST /login` sends a malformed email THEN the system SHALL return `Informe um endereço de e-mail válido.` on `email` without authenticating
11. IF `POST /login` sends unknown email, wrong password, or a soft-deleted user THEN the system SHALL return `E-mail ou senha inválidos.` on `credentials` without authenticating
12. WHILE the Inertia request is processing the screen SHALL disable `Entrar`, set its label to `Entrando...`, show a loading indicator, and ignore further submits
13. IF validation or credentials fail THEN the system SHALL keep the email value, clear the password, and SHALL NOT put the raw password in old input or the response body
14. IF credentials fail THEN the screen SHALL show the generic error in an `aria-live="polite"` region and move focus to the password field
15. WHEN Laravel returns field validation errors THEN the screen SHALL display each backend message below the matching field with `aria-invalid` and `aria-describedby`
16. IF a non-validation, non-credentials server failure occurs THEN the screen SHALL display `Não foi possível entrar. Tente novamente.` in an `aria-live="polite"` region above the form
17. IF an unauthenticated visitor opens `GET /reservations` THEN the system SHALL redirect to `/login`
18. WHEN an authenticated administrator opens `GET /login` THEN the system SHALL redirect to `/reservations`
19. WHEN an authenticated administrator sends `POST /logout` THEN the system SHALL log them out, invalidate the session, regenerate the CSRF token, and redirect to `/login`
20. IF the same email and IP fail authentication more than 5 times THEN the system SHALL reject further attempts for at least 60 seconds without authenticating
21. WHEN email is submitted THEN the system SHALL trim and lowercase it before authentication
22. WHEN the viewport is wide THEN the screen SHALL use a two-column card; WHEN the viewport is narrow THEN the screen SHALL use one column, compact or hide the hero image, keep controls full width, and avoid horizontal scrolling
23. The README SHALL document that administrators sign in at `/login` and that public `/register` is a challenge convenience, not recommended in production
24. The system SHALL NOT flash or return the raw password in Inertia error payloads or old input

Traceability aliases: AC-001 … AC-024 map to items 1 … 24 above.

Suggested validation messages (exact strings): `Informe seu e-mail.` / `Informe um endereço de e-mail válido.` / `Informe sua senha.` / `E-mail ou senha inválidos.` / `Não foi possível entrar. Tente novamente.`

## Edge Cases

- IF name-like extra fields are posted THEN the system SHALL ignore them and authenticate only on email and password
- IF email is ` Ada@Example.com ` for an active user stored as `ada@example.com` THEN the system SHALL authenticate
- IF the user row is soft-deleted THEN the system SHALL treat it as invalid credentials
- IF the visitor clicks `Entrar` twice WHILE processing THEN the system SHALL not send a second request
- IF the server returns a non-422 failure THEN the system SHALL show the general banner and SHALL keep the form visible
- IF login succeeds after a guest was sent from `/reservations` THEN the system SHALL return them to `/reservations`
- IF throttle is active THEN the system SHALL not authenticate even with the correct password until the window expires
- IF the password toggle is used from the keyboard THEN the system SHALL still change visibility

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Password recovery | ADR-001 and screen-login closed scope |
| Remember-me checkbox | Not in screen-login |
| JWT / Sanctum SPA / Fortify / Breeze package install | ADR-002/003; use native session auth in this app |
| Email verification, roles, API tokens | ADR-001 / ADR-002 |
| Reservation CRUD (RF07+) | Only the existing `/reservations` stub is the post-login target |
| Changing `CreateUser` / register validation | Separate feature; keep auto-login after register |
| Closing public `/register` behind a flag | Keep guest register; document it |
| RF19 seeder for rooms/reservations | Separate requirement; existing user seeder is enough to try login |
| Playwright / E2E bootstrap | No runner in the repo |
| Pixel-identical mock dimensions | Screen spec allows responsive adaptation |
| Domain `AuthenticateUser` use case | Would import Laravel into Application |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Page path | `User/Login` Inertia component | Colocates with BrandPanel; `group_commits` scope `user` | n |
| HTTP shape | `LoginController` + `LoginRequest`; no Breeze install | RNF07 means Laravel `Auth::attempt`, not a new identity stack | n |
| Credentials error key | `credentials` (banner), not `email` | Screen forbids implying which field was wrong | n |
| Rate limit | 5 failures per normalized email+IP, 60s lockout via `RateLimiter` | Laravel starter-kit default from current docs | n |
| Intended URL | `redirect()->intended(route('reservations.index'))` | Screen allows return to the protected URL that triggered login | n |
| Remember me | Off; `Auth::attempt` without remember | Not on the screen | n |
| Password placeholder | None (masking via `type=password`) | Mock dots are typed characters, not English copy | n |
| Register link on login | Omit | Screen says do not include unless enabled and documented; README covers `/register` instead | n |
| Register access | Stay `guest` public | Challenge still needs a way to create an admin besides the seeder | n |
| Logout UI | `POST /logout` + AppLayout `Sair` | Screen requires session invalidation on logout; login page has no logout control | n |
| BrandPanel | Optional `footer` prop; login passes the productivity copy | Avoid duplicating the hero | n |
| Remaining dimensions (metrics, circuit breakers, TTL) | N/A | No external IdP or token expiry in this feature | y |

**Open questions:** none - all resolved or logged above.

## Considered Approaches

| Option | Trade-off | Decision |
| ------ | --------- | -------- |
| A. Install `laravel/breeze` or Fortify | Matches RNF07 literally; overwrites custom User module, pages, and hash/UUID decisions | Rejected |
| B. `AuthenticateUser` use case wrapping `Auth::attempt` | Looks layered; pulls Illuminate into Application | Rejected |
| C. Infra `LoginController` + `LoginRequest` + Inertia `User/Login` + `session.js` | Idiomatic Laravel session auth inside the existing module | **Selected** |
| D. `Pages/Auth/Login.jsx` | Starter-kit folder name; splits commits from User HTTP and BrandPanel | Rejected |
| E. Bootstrap Playwright for the full browser flow | Matches `docs/test/e2e.md`; no runner yet | Deferred |

## Selected Approach

Option C. Add Laravel session login in User Infra (`Auth::attempt`, session regenerate, RateLimiter, guest/auth redirects). Render `User/Login` from `screen-login.md`, post through `Services/session.js`, protect `/reservations`, add `POST /logout` plus AppLayout `Sair`, and cover UI with Vitest and HTTP with Feature tests.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| AC-001 | P1: screen | Execute | Implemented |
| AC-002 | P1: screen | Execute | Implemented |
| AC-003 | P1: screen | Execute | Implemented |
| AC-004 | P1: screen | Execute | Implemented |
| AC-005 | P1: screen | Execute | Implemented |
| AC-006 | P1: authenticate | Execute | Implemented |
| AC-007 | P1: authenticate | Execute | Implemented |
| AC-008 | P1: authenticate | Execute | Implemented |
| AC-009 | P1: authenticate | Execute | Implemented |
| AC-010 | P1: authenticate | Execute | Implemented |
| AC-011 | P1: authenticate | Execute | Implemented |
| AC-012 | P1: authenticate | Execute | Implemented |
| AC-013 | P1: authenticate | Execute | Implemented |
| AC-014 | P1: authenticate | Execute | Implemented |
| AC-015 | P1: authenticate | Execute | Implemented |
| AC-016 | P1: authenticate | Execute | Implemented |
| AC-017 | P1: protect | Execute | Implemented |
| AC-018 | P1: protect | Execute | Implemented |
| AC-019 | P1: authenticate | Execute | Implemented |
| AC-020 | P1: authenticate | Execute | Implemented |
| AC-021 | P1: authenticate | Execute | Implemented |
| AC-022 | P1: screen | Execute | Implemented |
| AC-023 | P1: README | Execute | Implemented |
| AC-024 | P1: authenticate | Execute | Implemented |

**Coverage:** 24 total, 24 mapped to tasks, 0 unmapped
