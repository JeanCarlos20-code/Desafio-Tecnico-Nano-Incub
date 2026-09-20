# Specification

## Context

Original request: "e a tela login tem hora q fica bem pequeno na tela, e tem hora q fica normal, da uma aumentada nesse login quando a tela é maior, deixa ele responsivo a parte do login"

`User/Login` already implements the two-column guest card from `docs/screens/screen-login.md`. The card uses `max-w-5xl` without `w-full`, and `lg` columns are percentage tracks on an auto-sized grid. That combination shrinks to form content on some viewports and only looks “normal” when the grid happens to get a definite width. Auth, validation, and Breeze stay unchanged.

## Problem

An unauthenticated administrator opening `/` or `/login` on a large monitor sometimes sees a narrow, content-sized card and sometimes a mid-size card. The login block does not consistently fill the available width or grow when the viewport grows.

## Problem Statement

The login card SHALL take the full padded viewport width up to a desktop maximum that is larger than the current `max-w-5xl` (64rem). WHILE the viewport is Tailwind `lg` or wider the card SHALL stay two columns near `46% / 54%`. WHILE the viewport is below `lg` the card SHALL stay one column with the large hero hidden. Authentication, validation, and copy SHALL remain unchanged.

## Goal

- Stop the shrink-to-content flicker by giving `[data-layout="login-card"]` an explicit `w-full`.
- Enlarge the card on large screens: `max-w-6xl` (72rem) from `lg`, `xl:max-w-7xl` (80rem) from `xl`.
- Keep the existing `lg` two-column split, hidden hero below `lg`, 24px horizontal inset, and no horizontal scroll.
- Extend the existing Login Vitest layout case. Update the Responsiveness section of `screen-login.md`.
- No PHP, Breeze, session, or Playwright work.

## User Stories

### P1: Stable, larger, responsive login card ⭐ MVP

**User Story**: As an administrator on `/` or `/login`, I want the login card to stay a consistent width and to grow when my screen is larger so that the form does not look tiny on a desktop and still works on a phone.

**Why P1**: This is the entire requested slice.

**Covered ACs**: AC-001 through AC-006

**Acceptance Criteria**:

1. WHEN the login page is shown THEN the system SHALL render `[data-layout="login-card"]` with `w-full` so the card uses the padded viewport width up to its max instead of shrinking to form content
2. WHEN the viewport is Tailwind `lg` (1024px) or wider THEN the system SHALL cap the card at `max-w-6xl` (72rem / 1152px) and SHALL keep `lg:grid-cols-[minmax(0,46%)_minmax(0,54%)]`
3. WHEN the viewport is Tailwind `xl` (1280px) or wider THEN the system SHALL raise the card cap to `xl:max-w-7xl` (80rem / 1280px)
4. WHILE the viewport is below Tailwind `lg` the system SHALL keep a single column, SHALL keep `[data-layout="register-hero"]` with `hidden`, and SHALL keep the `ReservaSalas` wordmark visible in the form
5. WHILE the login page is shown the system SHALL keep at least 24px horizontal inset (`px-6` on the page shell), SHALL keep `overflow-x-hidden` on that shell, and SHALL keep the `Entrar` control `w-full`
6. The login page SHALL keep heading `Acesse sua conta`, supporting text `Entre para gerenciar as salas e reservas.`, email and password fields, and SHALL NOT add cadastro copy or a password-recovery control

**Independent Test**: Vitest extends `uses a two-column card on wide viewports…` to require `w-full`, `max-w-6xl`, and `xl:max-w-7xl` on the card, plus the existing hero / button / overflow checks. Feature and PHP login tests stay green without edits.

## Acceptance Criteria

Traceable copies (same outcomes):

1. WHEN the login page is shown THEN the system SHALL render `[data-layout="login-card"]` with `w-full`
2. WHEN the viewport is Tailwind `lg` or wider THEN the system SHALL apply `max-w-6xl` and `lg:grid-cols-[minmax(0,46%)_minmax(0,54%)]` on the card
3. WHEN the viewport is Tailwind `xl` or wider THEN the system SHALL apply `xl:max-w-7xl` on the card
4. WHILE the viewport is below `lg` the hero SHALL stay `hidden` and the form SHALL still show `ReservaSalas`
5. WHILE the login page is shown the shell SHALL keep `px-6` and `overflow-x-hidden` and `Entrar` SHALL stay `w-full`
6. The login page SHALL keep the specified heading, supporting text, and fields, and SHALL NOT add cadastro or password recovery

- **AC-001** WHEN the login page is shown THEN the system SHALL render `[data-layout="login-card"]` with `w-full`
- **AC-002** WHEN the viewport is Tailwind `lg` or wider THEN the system SHALL apply `max-w-6xl` and the existing `46% / 54%` `lg` columns
- **AC-003** WHEN the viewport is Tailwind `xl` or wider THEN the system SHALL apply `xl:max-w-7xl`
- **AC-004** WHILE the viewport is below `lg` the hero SHALL stay `hidden` and the form SHALL still show `ReservaSalas`
- **AC-005** WHILE the login page is shown the shell SHALL keep `px-6` and `overflow-x-hidden` and `Entrar` SHALL stay `w-full`
- **AC-006** The login page SHALL keep the specified heading, supporting text, and fields, and SHALL NOT add cadastro or password recovery

## Edge Cases

- IF the viewport is between `lg` and `xl` THEN the card SHALL use `max-w-6xl` (not stay at `max-w-5xl` and not jump to `max-w-7xl`)
- IF the viewport is below `lg` THEN `w-full` still applies and the card SHALL fill the padded width (one column)
- IF the BrandPanel background image is slow or missing THEN the card width SHALL still follow `w-full` + max-width (must not depend on image intrinsic size)
- IF a virtual keyboard opens on mobile THEN the form SHALL remain usable; do not add `overflow-x` scroll
- Remaining implicit-requirement dimensions (auth, persistence, concurrency, retries, rate limits, observability) are N/A for this CSS-only scope

## Out of Scope

| Feature | Reason |
| --- | --- |
| Breeze / `LoginRequest` / session / rate limit | Already shipped; user asked only for layout |
| Field validation, credentials banner, loading copy | Unchanged contracts |
| Register, password recovery, `/register` | Forbidden by the login screen and ADR-007 |
| Rewriting BrandPanel markup or the hero image asset | Card `w-full` is enough to give the hero a definite width |
| Other screens (rooms, reservations) | User asked only for the login block |
| Playwright / new E2E project | Not in `package.json`; do not bootstrap here |
| Pixel-perfect match to `.local/image/screen-login.png` | Screen doc already says exact image dimensions are not required |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| Why the card flickers | Missing `w-full` + percentage columns on an auto-sized grid | Matches the markup and the “sometimes small, sometimes normal” report | n |
| “Aumentada quando a tela é maior” | `max-w-6xl` from `lg`, `xl:max-w-7xl` from `xl`; drop lone `max-w-5xl` | 1024px looks small on 1440–1920; 1152 then 1280 grows with the screen without going edge-to-edge | n |
| Two-column breakpoint | Keep Tailwind `lg` (1024px) | Already in Login.jsx and BrandPanel | n |
| Column split | Keep `46% / 54%` | Screen-login desktop rule | n |
| Horizontal inset | Keep `px-6` (24px) | Screen-login minimum | n |
| Pixel-perfect jsdom geometry | Assert Tailwind class contracts, not computed offsets | jsdom does not apply `lg`/`xl` media queries | n |
| BrandPanel rewrite | None unless `w-full` is insufficient | Hero already stretches once the card has a definite width | n |
| Remaining implicit dimensions | N/A for this scope | Layout-only; no auth or persistence change | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Add `w-full` only, keep `max-w-5xl`.** Fixes the flicker. Trade-off: the card still caps at 1024px, so large desktops still look small — the user asked for an enlarge.
2. **`w-full` plus progressive `max-w-6xl` / `xl:max-w-7xl`, keep the `lg` 46/54 grid.** Fixes flicker and grows on larger screens. Trade-off: still a class-contract test in jsdom, not measured pixels.
3. **Full-bleed card (`w-full` with no max-width) on desktop.** Maximum size. Trade-off: fights the screen doc (“center the card and apply a maximum width”) and the reference composition.

## Selected Approach

Approach 2. Edit `Login.jsx` card classes only: `w-full max-w-6xl xl:max-w-7xl`, keep the existing `lg` columns, hero, padding, and form behavior. Extend the existing Vitest layout case. Document the tokens in `screen-login.md` Responsiveness. No PHP, no BrandPanel rewrite, no Playwright.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| --- | --- | --- | --- |
| LOGIN-01 | P1: Stable, larger, responsive login card | Execute | Done |
| LOGIN-02 | P1: Stable, larger, responsive login card | Execute | Done |
| LOGIN-03 | P1: Stable, larger, responsive login card | Execute | Done |
| LOGIN-04 | P1: Stable, larger, responsive login card | Execute | Done |
| LOGIN-05 | P1: Stable, larger, responsive login card | Execute | Done |
| LOGIN-06 | P1: Stable, larger, responsive login card | Execute | Done |

**ID format:** `LOGIN-NN` maps 1:1 to AC-00N.

**Coverage:** 6 total, 6 mapped to T1–T2, 0 unmapped.

## Success Criteria

- [x] Login card always has `w-full` and no longer shrinks to form content.
- [x] Desktop cap is `max-w-6xl`, growing to `xl:max-w-7xl`.
- [x] `lg` two-column split, hidden hero below `lg`, 24px inset, and full-width `Entrar` remain.
- [x] Auth copy and behavior unchanged.
- [ ] Frontend unit gate plus existing PHP Unit/Feature/lint/build gates pass. No Playwright.
