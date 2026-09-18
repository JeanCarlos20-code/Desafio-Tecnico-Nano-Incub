# Specification

## Context

Login (`GET /login`, Inertia `User/Login`) and public registration (`GET /register`, Inertia `User/Create`) already work. The application home `GET /` still renders Laravel’s Blade `welcome` view. The login screen currently has no control that opens cadastro; `Create.jsx` already links back to `/login`. The user asked for the main screen to be login and for login to call the existing cadastro page.

## Problem

A visitor who opens the project root sees the Laravel starter page instead of administrator login. From the login form there is no place to reach `/register`, so cadastro is only available if the visitor already knows that URL.

## Problem Statement

The application home for guests SHALL be the existing login screen. The login screen SHALL expose a cadastro control that navigates to the existing register page. Password recovery, new auth mechanisms, and a rebuilt register flow stay out of this change.

## Goal

- Guest `GET /` renders the same Inertia login page already used at `GET /login`.
- Authenticated `GET /` does not show login; it follows the existing authenticated redirect to `/reservations`.
- Login shows a cadastro control that navigates to `/register`.
- Existing `POST /login`, session regeneration, and create-user behavior stay unchanged.

## User Stories

### P1: Open the app on login ⭐ MVP

**User Story**: As a guest administrator, I want the project home to be the login screen so that I can sign in without seeing the Laravel welcome page.

**Why P1**: User asked for the main screen to be login (RF01 entry).

**Acceptance Criteria**:

1. WHEN a guest requests `GET /` THEN the system SHALL render Inertia `User/Login` with heading `Acesse sua conta` (same page as `GET /login`)
2. WHEN an authenticated administrator requests `GET /` THEN the system SHALL redirect to `/reservations` and SHALL NOT render `User/Login`
3. WHEN a guest requests `GET /login` THEN the system SHALL still render Inertia `User/Login`
4. WHEN an authenticated administrator requests `GET /login` THEN the system SHALL still redirect to `/reservations`

**Independent Test**: Feature HTTP `GET /` and `GET /login` as guest and as authenticated user.

---

### P1: Reach cadastro from login ⭐ MVP

**User Story**: As a guest on the login screen, I want a cadastro control so that I can open the existing registration page.

**Why P1**: User asked login to call the cadastro page. Registration already exists at `/register`.

**Acceptance Criteria**:

1. WHEN the login screen is shown THEN the system SHALL display divider text `Não tem uma conta?` and a secondary control `Ir para o cadastro`
2. WHEN the guest selects `Ir para o cadastro` THEN the system SHALL navigate to `/register` through an Inertia `Link` with `href="/register"`
3. IF the login screen is shown THEN the system SHALL NOT render a password-recovery control
4. The register page SHALL keep `Ir para o login` pointing at `/login`

**Independent Test**: Vitest `Login.test.jsx` for the new link; existing `Create.test.jsx` login href remains.

## Acceptance Criteria

- **AC-001** WHEN a guest requests `GET /` THEN the system SHALL render Inertia `User/Login`
- **AC-002** WHEN an authenticated user requests `GET /` THEN the system SHALL redirect to `route('reservations.index')`
- **AC-003** WHEN a guest requests `GET /login` THEN the system SHALL render Inertia `User/Login`
- **AC-004** WHEN an authenticated user requests `GET /login` THEN the system SHALL redirect to `route('reservations.index')`
- **AC-005** WHEN the login screen is shown THEN the system SHALL show `Não tem uma conta?` and link `Ir para o cadastro` with `href="/register"`
- **AC-006** IF the login screen is shown THEN the system SHALL NOT offer password recovery
- **AC-007** The named route `login` SHALL remain `GET /login`
- **AC-008** WHEN a guest submits valid credentials on `POST /login` THEN the system SHALL still regenerate the session and redirect to `/reservations` (existing contract)

## Edge Cases

- IF Vite assets are missing in HTTP tests THEN Inertia home/login tests SHALL use `withoutVite()` (same as `LoginHttpTest`)
- IF `tests/Feature/ExampleTest.php` still expects a non-Inertia 200 on `GET /` THEN Execute SHALL remove or replace it so the Feature suite stays green
- WHILE login submission is processing the system SHALL keep the cadastro link visible but SHALL NOT change `POST /login` loading behavior
- IF a guest opens `/register` from login THEN the system SHALL render the existing `User/Create` page (no new register implementation)

## Out of Scope

| Feature | Reason |
| --- | --- |
| Rebuild login authentication / `AuthenticateUser` / `LoginRequest` | Already delivered; user asked home + cadastro link |
| Rebuild create-user screen or `POST /register` | Already delivered; login only calls it |
| Password recovery | Screen and challenge exclude it |
| Drop `GET /login` in favor of `/` only | Breaks named `login`, screen contract, existing tests |
| Playwright / new E2E runner | No Playwright project; do not bootstrap here |
| Delete `welcome.blade.php` | Unused Laravel default; not requested |
| JWT, roles, public REST, extra README policy rewrite | ADR-001 / ADR-002 / README already covers public `/register` |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| How `/` becomes login | Render `LoginController::create` at `GET /` under `guest` (not 302 to `/login`) | Home URL is the login screen; one hop; authenticated users still redirected | n |
| Keep `/login` | Yes, named `login` | Screen doc, Laravel `redirectGuestsTo`, existing tests | n |
| Cadastro copy | Divider `Não tem uma conta?` + `Ir para o cadastro` | Mirrors create-user `Já tem uma conta?` / `Ir para o login` | n |
| Cadastro URL | `/register` | Existing public register route | n |
| README | Leave as-is | Already states `/register` is public for the challenge | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Redirect `GET /` → `/login`.** Smallest route change. Extra hop; authenticated users hit two redirects; home is not the login URL.
2. **Render `LoginController::create` at `GET /` under `guest`, keep `GET /login`.** Home is the login screen; named `login` stays. Selected.
3. **Move login exclusively to `/` and retarget `login`.** Breaks `docs/screens/screen-login.md`, `redirectGuestsTo(route('login'))` conventions, and current Feature tests.

## Selected Approach

Approach 2. Put `GET /` in the existing `guest` group pointing at `LoginController::create` (optional name `home`). Keep `GET /login` named `login`. Add the create-user-style secondary `Link` on `Login.jsx`. Update `Login.test.jsx` (mock `Link`; replace the “no registration link” case). Extend `LoginHttpTest` for guest/auth `GET /`. Drop `ExampleTest` welcome smoke if it conflicts. Update `docs/screens/screen-login.md` navigation. No Application-layer PHP change.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| --- | --- | --- | --- |
| AUTH-01 | P1: Open the app on login | Execute | Implemented |
| AUTH-02 | P1: Open the app on login | Execute | Implemented |
| AUTH-03 | P1: Open the app on login | Execute | Implemented |
| AUTH-04 | P1: Reach cadastro from login | Execute | Implemented |
| AUTH-05 | P1: Reach cadastro from login | Execute | Implemented |
| AUTH-06 | P1: Reach cadastro from login | Execute | Implemented |

**Coverage:** 6 total, 6 mapped to tasks, 0 unmapped
