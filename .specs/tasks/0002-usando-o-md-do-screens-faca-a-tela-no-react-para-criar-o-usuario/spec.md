# Specification

## Context

ReservaSalas already persists administrators (ADR-002) and serves a minimal Inertia form at `GET /users/create`. `docs/screens/screen-create-user.md` defines the public create-user screen: split card, Portuguese copy, `/register`, server-side validation in the UI, loading and error states, and post-success authentication.

## Problem

The current `User/Create` page does not match the screen contract. It uses the admin `AppLayout`, posts to `/users`, never logs the new user in, and redirects back to the form. Operators cannot complete the designed registration flow.

## Problem Statement

Administrators need a responsive Inertia screen at `/register` that collects name, email, and password, shows Laravel validation next to each field, and, on success, signs the new administrator in and sends them to `/reservations`.

## Goal

- Ship `User/Create` according to `docs/screens/screen-create-user.md` and `.local/image/screen-create-user.png` (layout, copy, states, a11y — not pixel-perfect dimensions).
- Submit through Inertia `POST /register` via `Services/users.js`.
- On success, authenticate the created user and redirect to `/reservations`.
- Keep server validation authoritative; display backend messages in the form.
- Document the public registration access policy in `README.md`.

## User Stories

### P1: Register screen matching the create-user spec ⭐ MVP

**User Story**: As a visitor creating the first administrator, I want the `/register` screen specified in `screen-create-user.md` so that I can enter name, email, and password in a usable, accessible form.

**Why P1**: Without this UI the persistence stack cannot be operated as designed.

**Acceptance Criteria**:

1. WHEN an unauthenticated visitor opens `GET /register` THEN the system SHALL respond 200 and render the Inertia page `User/Create`
2. The screen SHALL show heading `Criar usuário`, supporting text `Preencha os dados para criar uma nova conta de administrador.`, primary control `Criar usuário`, divider `Já tem uma conta?`, and secondary control `Ir para o login`
3. The screen SHALL render required fields Nome, E-mail, and Senha with placeholders `Seu nome completo`, `seu@email.com`, and `Mínimo de 8 caracteres`, autocomplete `name` / `email` / `new-password`, and `aria-required="true"`
4. The brand panel SHALL show wordmark `ReservaSalas` and copy `Salas organizadas. Reuniões que acontecem.` plus `Comece agora e ajude a manter o seu time mais produtivo.`
5. WHEN the visitor activates `Ir para o login` THEN the system SHALL navigate to `/login`
6. WHEN the visitor activates the password visibility control THEN the system SHALL toggle the input between `password` and `text` and SHALL expose accessible name `Mostrar senha` or `Ocultar senha`
7. WHEN the viewport is wide THEN the screen SHALL use a two-column card; WHEN the viewport is narrow THEN the screen SHALL use one column, compact or hide the hero image, keep controls full width, and avoid horizontal scrolling

**Independent Test**: Open `/register` in isolation (Feature assertInertia + Vitest render); no login or reservations CRUD required.

---

### P1: Submit, validate, and enter the session ⭐ MVP

**User Story**: As a visitor, I want to submit the form through Inertia and see Laravel errors or land authenticated on `/reservations` so that invalid data never creates a user and valid data starts an admin session.

**Why P1**: The screen’s success and error paths are the product behavior.

**Acceptance Criteria**:

1. WHEN the visitor submits the form THEN the system SHALL send an Inertia POST to `/register` with `name`, `email`, and `password` through `Services/users.js`
2. WHEN `POST /register` receives valid name, email, and password THEN the system SHALL persist the administrator, authenticate that user, and redirect to `/reservations`
3. IF `POST /register` omits `name`, `email`, or `password` THEN the system SHALL return a validation error on that field key without persisting a user
4. WHEN Laravel returns field validation errors THEN the screen SHALL show each backend message below the matching field with `aria-invalid` and `aria-describedby`
5. IF `POST /register` uses an email already in `users`, including a soft-deleted row, THEN the system SHALL return a validation error on `email` without persisting another row
6. IF `POST /register` sends a password shorter than 8 characters THEN the system SHALL return a validation error on `password` without persisting a user
7. WHILE the Inertia request is processing the screen SHALL disable the primary button, set its label to `Criando usuário...`, and ignore further submits
8. IF validation fails THEN the system SHALL keep name and email and SHALL clear the password field
9. IF a non-validation server failure occurs THEN the screen SHALL show `Não foi possível criar o usuário. Tente novamente.` in an `aria-live="polite"` region above the form
10. WHEN email is accepted THEN the system SHALL persist it trimmed and lowercased
11. WHEN validation errors are shown THEN the system SHALL move focus to the first invalid field or an accessible error summary
12. The system SHALL not flash or return the raw password in Inertia error payloads or old input

**Independent Test**: Feature HTTP for persist/auth/redirect/validation; Vitest for loading, field errors, password reset, banner, and toggle.

---

### P1: Access policy on README ⭐ MVP

**User Story**: As a reviewer of the technical challenge, I want `README.md` to explain why `/register` is public so that production risk is explicit.

**Why P1**: The screen spec requires this documentation when the page is delivered.

**Acceptance Criteria**:

1. The README SHALL state that `/register` is public because administrator login is not implemented yet, and that allowing any visitor to create an administrator is not recommended in production

**Independent Test**: README contains that policy in Portuguese or English.

---

## Acceptance Criteria

1. WHEN an unauthenticated visitor opens `GET /register` THEN the system SHALL respond 200 and render Inertia page `User/Create`
2. The screen SHALL display heading `Criar usuário`, supporting text `Preencha os dados para criar uma nova conta de administrador.`, primary control `Criar usuário`, divider `Já tem uma conta?`, and secondary control `Ir para o login`
3. The screen SHALL render required fields Nome, E-mail, and Senha with placeholders `Seu nome completo`, `seu@email.com`, and `Mínimo de 8 caracteres`, autocomplete `name` / `email` / `new-password`, and `aria-required="true"`
4. The brand panel SHALL display wordmark `ReservaSalas` and the copy `Salas organizadas. Reuniões que acontecem.` plus `Comece agora e ajude a manter o seu time mais produtivo.`
5. WHEN the visitor activates `Ir para o login` THEN the system SHALL navigate to `/login`
6. WHEN the visitor submits the form THEN the system SHALL send an Inertia POST to `/register` with `name`, `email`, and `password` through `Services/users.js`
7. WHEN `POST /register` receives valid name, email, and password THEN the system SHALL persist the administrator, authenticate that user, and redirect to `/reservations`
8. IF `POST /register` omits `name`, `email`, or `password` THEN the system SHALL return a validation error on that field key without persisting a user
9. WHEN Laravel returns field validation errors THEN the screen SHALL display each backend message below the matching field, using `aria-invalid` and `aria-describedby`
10. IF `POST /register` uses an email already present in `users`, including a soft-deleted row, THEN the system SHALL return a validation error on `email` without persisting another row
11. IF `POST /register` sends a password shorter than 8 characters THEN the system SHALL return a validation error on `password` without persisting a user
12. WHILE the Inertia request is processing the screen SHALL disable the primary button, set its label to `Criando usuário...`, and ignore further submits
13. WHEN the visitor activates the password visibility control THEN the system SHALL toggle the input between `password` and `text` and SHALL expose accessible name `Mostrar senha` or `Ocultar senha`
14. IF validation fails THEN the system SHALL keep the name and email values and SHALL clear the password field
15. IF a non-validation server failure occurs THEN the screen SHALL display `Não foi possível criar o usuário. Tente novamente.` in an `aria-live="polite"` region above the form
16. WHEN the viewport is wide THEN the screen SHALL use a two-column card; WHEN the viewport is narrow THEN the screen SHALL use one column, compact or hide the hero image, keep controls full width, and avoid horizontal scrolling
17. The README SHALL document that `/register` is public while administrator login is unimplemented, and that public administrator self-registration is not recommended in production
18. WHEN email is accepted THEN the system SHALL persist it trimmed and lowercased
19. WHEN validation errors are shown THEN the system SHALL move focus to the first invalid field or to an accessible error summary
20. The system SHALL not flash or return the raw password in Inertia error payloads or old input

Traceability aliases: AC-001 … AC-020 map to items 1 … 20 above.

Suggested backend messages (exact strings): `Informe seu nome.` / `Informe um endereço de e-mail válido.` / `Este e-mail já está cadastrado.` / `A senha deve possuir pelo menos 8 caracteres.`

## Edge Cases

- IF name, email, or password is missing THEN the system SHALL reject without persist
- IF email is malformed THEN the system SHALL reject on `email`
- IF email belongs to a soft-deleted user THEN the system SHALL reject as duplicate
- IF password has 7 characters THEN the system SHALL reject; IF it has 8 valid characters THEN the system SHALL persist
- IF the visitor clicks submit twice WHILE processing THEN the system SHALL not send a second request
- IF the server returns a non-422 failure THEN the system SHALL show the general banner and SHALL keep the form visible
- IF the password toggle is used from the keyboard THEN the system SHALL still change visibility

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Login page at `/login` | RF01; link target only |
| Reservation list/CRUD | RF07+; only a post-register URL stub |
| `auth` middleware on `/register` | Login does not exist yet; policy goes in README |
| Registration feature flag | Not in the screen spec; keep the route public |
| Password minimum of 6 | Project already uses `Password::defaults()` (8); UI copy follows backend |
| Changing `CreateUser` validation | Validation stays on the Form Request |
| Playwright / E2E bootstrap | No runner in the repo; HTTP Feature + Vitest cover this task |
| `Pages/User/Index.jsx` / `Edit.jsx` / generic Hooks | Not this screen |
| Public JSON API or Fortify | ADR-003 / ADR-001 |
| Pixel-identical mock dimensions | Screen spec allows responsive adaptation |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| HTTP URLs | `GET /register` + `POST /register`; keep `GET /users/create` and `POST /users` as aliases | Screen is SoT; do not break the existing create-user HTTP feature | n |
| Password length | Keep 8 via `Password::defaults()`; placeholder `Mínimo de 8 caracteres` | Screen says follow starter-kit default and align the UI | n |
| Post-success | `Auth::login` Eloquent user; redirect `/reservations` | Screen success path | n |
| `/reservations` target | Minimal Inertia `Reservation/Index` stub | Avoid 404 after redirect without building RF07 | n |
| `/login` | `Link` href `/login` only | RF01 is a later screen | n |
| Access control | Public `/register` until login exists | Same reason as previous unconfirmed `auth` assumption; document in README | n |
| Email normalize | `prepareForValidation` trim + lowercase | Screen validation section | n |
| Brand image | CSS navy overlay; optional local `public/images/register-hero.jpg`; no remote URL | Offline, no new vendor | n |
| Layout reuse | Feature components under `Pages/User/Components`; do not use `AppLayout` | Register is a marketing split card, not the admin shell | n |
| Remaining dimensions (rate limit, TTL, metrics, circuit breakers) | N/A | No external service or expiry in this screen | y |

**Open questions:** none - all resolved or logged above.

## Considered Approaches

| Option | Trade-off | Decision |
| ------ | --------- | -------- |
| A. Restyle `User/Create` at `/users/create` only | Smallest diff; ignores screen route and success path | Rejected |
| B. New `Pages/Auth/Register.jsx` plus keep the zinc `User/Create` | Duplicates the form | Rejected |
| C. Evolve `User/Create` to the screen; add `/register` aliases; login + redirect; Vitest + Feature tests | One page, matches screen and tree.md | **Selected** |
| D. Bootstrap Playwright for the full browser flow | Matches `docs/test/e2e.md` and stack.yml, but there is no Playwright install/config yet | Deferred |

## Selected Approach

Option C. Implement the screen on the existing Inertia page `User/Create`. Route it at `/register`, post through `Services/users.js`, keep `/users*` aliases, authenticate on success, redirect to a `/reservations` stub, localize Form Request messages, normalize email, and cover UI with Vitest and HTTP with Feature tests.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| AC-001 | P1: screen | Execute | Done |
| AC-002 | P1: screen | Execute | Done |
| AC-003 | P1: screen | Execute | Done |
| AC-004 | P1: screen | Execute | Done |
| AC-005 | P1: screen | Execute | Done |
| AC-006 | P1: submit | Execute | Done |
| AC-007 | P1: submit | Execute | Done |
| AC-008 | P1: submit | Execute | Done |
| AC-009 | P1: submit | Execute | Done |
| AC-010 | P1: submit | Execute | Done |
| AC-011 | P1: submit | Execute | Done |
| AC-012 | P1: submit | Execute | Done |
| AC-013 | P1: screen | Execute | Done |
| AC-014 | P1: submit | Execute | Done |
| AC-015 | P1: submit | Execute | Done |
| AC-016 | P1: screen | Execute | Done |
| AC-017 | P1: README | Execute | Done |
| AC-018 | P1: submit | Execute | Done |
| AC-019 | P1: submit | Execute | Done |
| AC-020 | P1: submit | Execute | Done |

**Coverage:** 20 total, 20 mapped to tasks, 0 unmapped
