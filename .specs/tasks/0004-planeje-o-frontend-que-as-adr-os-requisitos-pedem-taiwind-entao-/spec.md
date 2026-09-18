# Specification

## Context

ReservaSalas already ships an Inertia register screen at `/register` (`User/Create`) and a post-register stub at `/reservations` (`Reservation/Index`). ADR-001 **RNF04** and `docs/context.md` require Tailwind CSS. Tailwind CSS v4 is already wired through `@tailwindcss/vite` and `resources/css/app.css`. The register card is only horizontally centered (`mx-auto`); the reservations stub has no responsive page shell.

## Problem

Administrators opening `/register` do not see the card in the middle of the viewport as `docs/screens/screen-create-user.md` requires. The other existing React screen is not a responsive shell. The user asked to plan the Tailwind frontend the ADRs require, apply Tailwind on React, make screens responsive, and put the registration screen in the middle of the page.

## Problem Statement

Existing React Inertia pages must be styled with Tailwind CSS v4 so the register card is centered in the viewport, both existing screens adapt to desktop/tablet/mobile without horizontal scrolling, and register form/HTTP behavior stays unchanged.

## Goal

- Keep Tailwind CSS v4 as the only styling system for React (RNF04).
- Center the `/register` card horizontally and vertically in the viewport.
- Keep the register two-column / one-column responsive contract from the screen spec.
- Give `Reservation/Index` a responsive admin shell via `AppLayout`.
- Protect layout with Vitest; do not regress create-user HTTP or UI behavior.

## User Stories

### P1: Centered register card ⭐ MVP

**User Story**: As a visitor on `/register`, I want the registration card in the middle of the screen so that the form is the visual focus on desktop and mobile.

**Why P1**: Explicit user request; screen spec requires a centered card filling the viewport.

**Acceptance Criteria**:

1. WHEN `User/Create` renders THEN the page shell SHALL be a Tailwind flex container that occupies at least the full viewport height and centers the `[data-layout="register-card"]` element on both axes
2. IF the register card is taller than the viewport THEN the system SHALL allow vertical scrolling and SHALL keep the start of the card reachable (no clipped top)
3. The register page shell SHALL keep at least 24px of horizontal padding and SHALL set `overflow-x-hidden` so the page does not scroll horizontally
4. The system SHALL continue to style `User/Create` only with Tailwind utility classes (no new CSS framework and no page-level CSS module)

**Independent Test**: Render `Create` in Vitest and assert centering / padding / overflow classes on the page shell and card.

---

### P1: Responsive existing screens ⭐ MVP

**User Story**: As a visitor on a phone, tablet, or desktop, I want the existing React screens to reflow so that I can use them without horizontal scrolling.

**Why P1**: Explicit user request; screen spec already defines desktop vs mobile register layout.

**Acceptance Criteria**:

1. WHEN the viewport is wide (`lg` and up) THEN the register card SHALL use a two-column grid with proportions close to 44% / 56% and SHALL show the brand hero
2. WHEN the viewport is narrow (below `lg`) THEN the register card SHALL use a single column, the brand hero SHALL stay hidden or compact, the wordmark SHALL remain at the top of the form, and primary/secondary controls SHALL be full width
3. WHEN `Reservation/Index` renders THEN the system SHALL wrap the stub heading in `AppLayout` so the page uses a constrained, padded, full-viewport shell without horizontal overflow
4. WHILE `AppLayout` is used the layout SHALL apply Tailwind utilities for `min-h-screen`, horizontal padding, `overflow-x-hidden`, and a max width on the main column

**Independent Test**: Existing `Create.test.jsx` responsive class assertions plus new `AppLayout` and `Reservation/Index` Vitest cases.

---

### P1: Tailwind remains the React styling system ⭐ MVP

**User Story**: As a reviewer of the technical challenge, I want React screens styled with Tailwind CSS so that RNF04 is visible in the delivered UI.

**Why P1**: ADRs and `docs/context.md` name Tailwind; the user asked to implement it on React.

**Acceptance Criteria**:

1. The system SHALL keep Tailwind CSS v4 installed and compiled through `@tailwindcss/vite` with `@import 'tailwindcss'` in `resources/css/app.css`
2. IF Execute changes styles on React pages THEN those styles SHALL be Tailwind utilities (or `@theme` tokens consumed as Tailwind utilities), not a second design system

**Independent Test**: `package.json` / `vite.config.js` / `app.css` remain the v4 Vite pipeline; page class names stay Tailwind utilities.

## Acceptance Criteria

Traceability aliases: AC-001 … AC-010 map 1:1 to UI-01 … UI-10.

1. WHEN `User/Create` renders THEN the page shell SHALL be a Tailwind flex container that occupies at least the full viewport height and centers `[data-layout="register-card"]` on both axes
2. IF the register card is taller than the viewport THEN the system SHALL allow vertical scrolling and SHALL keep the start of the card reachable
3. The register page shell SHALL keep at least 24px of horizontal padding and SHALL set `overflow-x-hidden`
4. The system SHALL continue to style `User/Create` only with Tailwind utility classes (no new CSS framework and no page-level CSS module)
5. WHEN the viewport is wide (`lg` and up) THEN the register card SHALL use a two-column grid with proportions close to 44% / 56% and SHALL show the brand hero
6. WHEN the viewport is narrow (below `lg`) THEN the register card SHALL use a single column, the brand hero SHALL stay hidden or compact, the wordmark SHALL remain at the top of the form, and primary/secondary controls SHALL be full width
7. WHEN `Reservation/Index` renders THEN the system SHALL wrap the stub heading in `AppLayout` so the page uses a constrained, padded, full-viewport shell without horizontal overflow
8. WHILE `AppLayout` is used the layout SHALL apply Tailwind utilities for `min-h-screen`, horizontal padding, `overflow-x-hidden`, and a max width on the main column
9. The system SHALL keep Tailwind CSS v4 installed and compiled through `@tailwindcss/vite` with `@import 'tailwindcss'` in `resources/css/app.css`
10. IF Execute changes styles on React pages THEN those styles SHALL be Tailwind utilities (or `@theme` tokens consumed as Tailwind utilities), not a second design system

## Edge Cases

- IF the viewport is short and the two-column card is taller than the window THEN the system SHALL not clip the top of the card; vertical scroll is allowed
- IF the viewport is a portrait tablet or phone THEN the system SHALL not show a horizontal scrollbar on `/register` or `/reservations`
- IF the user resizes across the `lg` breakpoint THEN register SHALL switch between two columns with hero and one column without hero
- IF `AppLayout` receives no `title` THEN it SHALL still render `children` inside the padded shell

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Reinstall or downgrade Tailwind (v3 `tailwind.config.js`) | v4 Vite plugin already satisfies RNF04 |
| Login page at `/login` | RF01; link target only |
| Rooms / reservation CRUD UI | Later RFs; stub heading only |
| New CSS framework, CSS modules as the design system, or styled-components | Violates RNF04 |
| Pixel-identical mock dimensions | Screen spec allows responsive adaptation |
| Playwright / E2E bootstrap | No runner in the repo; layout is unit-tested |
| Register POST, `CreateUser`, Form Request, session | Already shipped; must not regress |
| Public JSON API or a separate React app | ADR-003 |
| Auth middleware on `/register` | Unchanged from create-user |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Tailwind version | Keep v4 + `@tailwindcss/vite`; do not add v3 config | Already installed and compiling; Context7 Vite plugin is the v4 path | n |
| “Middle of the screen” | Horizontal **and** vertical center of the viewport | Screen spec: centered card filling the viewport; user said “no meio da tela” | n |
| Overflow when card is taller than the viewport | `justify-center-safe` (or `items-start` + vertical padding) so the top stays reachable | Mobile landscape / zoom must not clip the form | n |
| Viewport height utility | Prefer `min-h-dvh` if available in Tailwind 4; otherwise `min-h-screen` | Avoid mobile URL-bar jump when possible | n |
| Other “telas” in this task | Only existing React pages: `User/Create` and `Reservation/Index` (+ `AppLayout`) | Login and room screens do not exist yet | n |
| Admin shell | Wrap reservations stub in `AppLayout`; register stays a dedicated split-card page | Matches create-user decision and `docs/tree.md` Layouts | n |
| `@theme` tokens | Optional; keep current `slate`/`blue` utilities unless tokens reduce duplication | Screen colors already approximated; avoid extra css module unless needed | n |
| Class assertions in Vitest | Allowed for this task | `docs/test/unit.md` permits CSS assertions when styling is the behavior; existing `Create.test.jsx` already does this | n |
| Remaining implicit dimensions (auth, retries, persistence, rate limits, observability) | N/A | Layout-only change; no new I/O or state machine | y |

**Open questions:** none - all resolved or logged above.

## Considered Approaches

| Option | Trade-off | Decision |
| ------ | --------- | -------- |
| A. Keep Tailwind v4 Vite plugin; center register with flex utilities; wrap stub in `AppLayout` | Smallest change; matches RNF04 and screen spec | **Selected** |
| B. Downgrade to Tailwind v3 + `tailwind.config.js` / PostCSS | Extra files, no ADR gain, fights current Vite plugin | Rejected |
| C. Extract a `GuestLayout` only to center the card | Extra layer for one page; architecture says no layer without need | Rejected |
| D. Bootstrap Playwright visual tests for centering | No Playwright project; overkill vs class assertions | Rejected |

## Selected Approach

Option A. Keep the existing Tailwind CSS v4 pipeline. On `User/Create`, change the page shell to a full-viewport flex container that centers `[data-layout="register-card"]` and preserves the current `lg` two-column / mobile one-column classes. Point `Reservation/Index` at `AppLayout` and give that layout a responsive, overflow-safe shell. Cover the new layout contracts with Vitest. Do not touch register HTTP or form logic.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| UI-01 | P1: Centered register card | T1 | Implemented |
| UI-02 | P1: Centered register card | T1 | Implemented |
| UI-03 | P1: Centered register card | T1 | Implemented |
| UI-04 | P1: Centered register card | T1 | Implemented |
| UI-05 | P1: Responsive existing screens | T1 | Implemented |
| UI-06 | P1: Responsive existing screens | T1 | Implemented |
| UI-07 | P1: Responsive existing screens | T3 | Implemented |
| UI-08 | P1: Responsive existing screens | T2, T3 | Implemented |
| UI-09 | P1: Tailwind remains the React styling system | T1, T2 | Implemented |
| UI-10 | P1: Tailwind remains the React styling system | T1, T2 | Implemented |

**Coverage:** 10 total, 10 mapped to tasks, 0 unmapped

## Success Criteria

- [x] `/register` card sits in the middle of the viewport on a typical desktop height
- [x] `/register` reflows to one column on a mobile width without horizontal scroll
- [x] `/reservations` stub is padded and constrained on mobile and desktop
- [x] `npm test` and existing PHPUnit Feature tests pass with no silent deletions
