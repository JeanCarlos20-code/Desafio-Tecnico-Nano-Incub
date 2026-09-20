# Specification

## Context

Original request: "eu vi aqui que o projeto exige inertia.js 2 e o codigo usa o 3, faça ele usar o 2 e se precisa refatore pra usar o 2, apenas se necessário"

RNF03 and ADR-003 require Inertia.js 2. The repo currently declares `@inertiajs/react` `^3.7.1` and `inertiajs/inertia-laravel` `^3.3`. Most client APIs already exist on v2. The only product APIs that are v3-only are the visit callbacks `onHttpException` and `onNetworkError`. Those must be remapped or unexpected-failure banners go silent.

## Problem

The declared stack is Inertia.js 3 while the challenge, ADR-003, and project docs require Inertia.js 2. Leaving the v3 visit callbacks in place after a pin would drop the existing “stay on the page / show the general banner” behavior.

## Problem Statement

The system SHALL run Inertia.js 2 on both the React adapter and the Laravel adapter. WHILE a visit is in flight the system SHALL handle non-Inertia HTTP responses and unexpected XHR errors through Inertia 2 `invalid` / `exception` events so the current general-failure banners and “do not leave the page” behavior remain.

## Goal

- Constrain `@inertiajs/react` and `inertiajs/inertia-laravel` to `^2.0` and lock 2.x only.
- Refactor only the v3-only visit failure callbacks onto v2 `router.on('invalid'|'exception')`.
- Keep `createInertiaApp`, `useForm`, `Link`, `router.get`, form mutations, `Inertia::render`, and shared props unchanged.
- Keep React 19 and Laravel 12.
- Update the existing Vitest cases that name `onHttpException` / `onNetworkError`. No new PHP or Playwright suites.

## User Stories

### P1: Stack matches Inertia.js 2 ⭐ MVP

**User Story**: As the challenge evaluator, I want the installed Inertia adapters to be version 2 so that the project matches RNF03 and ADR-003.

**Why P1**: The request is the version mismatch.

**Covered ACs**: AC-001, AC-002, AC-003

**Acceptance Criteria**:

1. WHEN frontend dependencies are declared THEN the system SHALL set `@inertiajs/react` to the `^2.0` range and SHALL NOT declare a 3.x range
2. WHEN PHP dependencies are declared THEN the system SHALL set `inertiajs/inertia-laravel` to the `^2.0` range and SHALL NOT declare a 3.x range
3. WHEN lockfiles are resolved THEN the system SHALL install `@inertiajs/react` 2.x, `@inertiajs/core` 2.x, and `inertiajs/inertia-laravel` 2.x only

**Independent Test**: Read `package.json` / `composer.json` and the two lockfiles. `npm run build` and `composer run build` succeed on the 2.x adapters.

### P1: Unexpected failures still stay on the page ⭐ MVP

**User Story**: As an administrator, I want a non-validation Inertia failure to keep me on the form or list and show the existing general error so that I can retry without a blank modal or a thrown exception.

**Why P1**: Those callbacks are the only v3-only product API; they must be remapped or the UX regresses.

**Covered ACs**: AC-004, AC-005, AC-006, AC-007

**Acceptance Criteria**:

1. The product JavaScript SHALL NOT pass `onHttpException` or `onNetworkError` as Inertia visit options
2. WHEN a visit receives a non-Inertia response THEN the system SHALL subscribe to `router.on('invalid')` for that visit and SHALL call `event.preventDefault()` when the page handler returns `false`
3. WHEN a visit hits an unexpected XHR or network error THEN the system SHALL subscribe to `router.on('exception')` for that visit and SHALL call `event.preventDefault()` when the page handler returns `false`
4. WHEN login, room save, reservation save, reservation cancel, or a list retry hits that unexpected failure THEN the system SHALL keep the existing Portuguese general-failure copy and SHALL leave the user on the current screen

**Independent Test**: Vitest on the visit-failure helper plus the existing Login / Room Create / Reservation Create / Reservation Index cases, updated to the v2 event names. Feature PHP suites stay green without new cases.

## Acceptance Criteria

Traceable copies (same outcomes):

- **AC-001** WHEN frontend dependencies are declared THEN the system SHALL set `@inertiajs/react` to `^2.0` and SHALL NOT declare a 3.x range
- **AC-002** WHEN PHP dependencies are declared THEN the system SHALL set `inertiajs/inertia-laravel` to `^2.0` and SHALL NOT declare a 3.x range
- **AC-003** WHEN lockfiles are resolved THEN the system SHALL install Inertia React, Inertia core, and inertia-laravel 2.x only
- **AC-004** The product JavaScript SHALL NOT pass `onHttpException` or `onNetworkError` as Inertia visit options
- **AC-005** WHEN a visit receives a non-Inertia response THEN the system SHALL handle `router.on('invalid')` and SHALL `preventDefault` when the handler returns `false`
- **AC-006** WHEN a visit hits an unexpected XHR or network error THEN the system SHALL handle `router.on('exception')` and SHALL `preventDefault` when the handler returns `false`
- **AC-007** WHEN login, room save, reservation save, reservation cancel, or a list retry hits that unexpected failure THEN the system SHALL keep the existing general-failure copy and stay on the current screen

## Edge Cases

- IF a page handler omits a return value on login THEN `session.js` SHALL still treat the failure as `false` (prevent default), matching today’s wrap
- IF both `onInvalid` and `onException` are omitted THEN the helper SHALL NOT register listeners and SHALL leave Inertia’s default invalid/exception behavior
- IF `onFinish` is already provided THEN the helper SHALL unsubscribe first and SHALL still call the original `onFinish`
- IF `composer update` / `npm install` would pull 3.x THEN the declared `^2.0` range SHALL prevent that
- Remaining implicit-requirement dimensions (auth rules, persistence, concurrency, rate limits, observability) are N/A for this adapter-pin scope

## Out of Scope

| Feature | Reason |
| --- | --- |
| Changing React 19, Vite 7, Tailwind 4, or Laravel 12 | Compatible with Inertia 2.x; not requested |
| Rewriting pages, routes, props, or domain/use cases | User asked to refactor only if required for v2 |
| Publishing `config/inertia.php` | None exists; v2 adapter defaults are enough |
| SPA + API split or a second frontend | Forbidden by ADR-003 / ADR-001 |
| New Playwright project | No runner in `package.json`; do not bootstrap |
| New Feature tests for every `Inertia::render` | Existing Feature suite is the HTTP contract |
| Updating ADR-003 / screen docs | They already say Inertia.js 2 |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| Pin range | `^2.0` on both adapters (latest 2.x, never 3) | Official pairing; Laravel 12 supported since inertia-laravel 2.0.2; React 19 is a 2.x peer | n |
| How to replace v3 visit callbacks | Per-visit `router.on('invalid'\|'exception')` helper in Services, unsubscribe on `onFinish` | v2 `VisitCallbacks` has no `onHttpException` / `onInvalid`; events are global and cancelable | n |
| Page option names after the remap | `onInvalid` / `onException` | Match v2 event names; keep `session.js` default-`false` | n |
| PHP Inertia code | Leave `Inertia::render` and `HandleInertiaRequests` untouched | Those APIs exist on inertia-laravel 2.x; no v3-only PHP usage found | n |
| React 19 | Keep | `@inertiajs/react` 2.x peers include `^19` | n |
| Task 0005 “do not downgrade” | Superseded | This request explicitly asks for v2 | n |
| Remaining implicit dimensions | N/A for this scope | Adapter pin + callback remap only | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Pin both adapters to `^2.0` and leave JS/PHP as-is.** Smallest diff. Trade-off: `onHttpException` / `onNetworkError` never fire on v2, so general-failure banners and “stay on the page” die.
2. **Pin both adapters to `^2.0` and remap only the v3 failure callbacks** through a Services helper onto `router.on('invalid'|'exception')`, keep `preventDefault` when the handler returns `false`. Matches “refactor only if necessary”. Trade-off: one new helper and Vitest updates.
3. **Stay on Inertia 3 and only change docs to say 3.** Rejected: RNF03 / ADR-003 / the user ask for v2.
4. **Downgrade React to 18 to match older starters.** Unnecessary: Inertia 2.x already peers React 19.

## Selected Approach

Approach 2. Pin `@inertiajs/react` and `inertiajs/inertia-laravel` to `^2.0` and refresh lockfiles. Add a Services helper that binds v2 `invalid` / `exception` for one visit. Rename product callbacks to `onInvalid` / `onException`. Do not touch PHP Inertia usage, `createInertiaApp`, list/filter visits beyond wrapping options, or visual screens. Update the existing Vitest cases that name the v3 callbacks.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| --- | --- | --- | --- |
| INERTIA-01 | P1: Stack matches Inertia.js 2 | Execute | Implemented |
| INERTIA-02 | P1: Stack matches Inertia.js 2 | Execute | Implemented |
| INERTIA-03 | P1: Stack matches Inertia.js 2 | Execute | Implemented |
| INERTIA-04 | P1: Unexpected failures still stay on the page | Execute | Implemented |
| INERTIA-05 | P1: Unexpected failures still stay on the page | Execute | Implemented |
| INERTIA-06 | P1: Unexpected failures still stay on the page | Execute | Implemented |
| INERTIA-07 | P1: Unexpected failures still stay on the page | Execute | Implemented |

**ID format:** `INERTIA-NN` maps 1:1 to AC-00N.

**Coverage:** 7 total, 7 mapped to T1–T6, 0 unmapped.

## Success Criteria

- [x] `@inertiajs/react` and `inertiajs/inertia-laravel` are `^2.0`; locks resolve 2.x only.
- [x] No product visit option named `onHttpException` or `onNetworkError`.
- [x] Unexpected failures still show the existing banners and stay on the page via v2 `invalid` / `exception`.
- [x] `Inertia::render`, shared props, and `createInertiaApp` stay as they are.
- [x] Frontend unit gate plus existing PHP Unit/Feature/lint/build gates pass. No Playwright.
