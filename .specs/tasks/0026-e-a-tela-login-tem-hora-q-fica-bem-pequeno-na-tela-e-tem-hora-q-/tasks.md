---
harness:
  commits:
    - "fix(user): enlarge login card and lock full width"
    - "test(user): cover login card responsive max widths"
    - "chore(docs): specify login card responsive max widths"
    - "chore(specs): record login card responsive plan artifacts"
  tests:
    unit:
      - "User/Login card has w-full so it uses the padded viewport instead of shrinking to form content"
      - "User/Login card uses max-w-6xl and keeps lg:grid-cols- with the 46/54 split"
      - "User/Login card uses xl:max-w-7xl for viewports at xl and above"
      - "User/Login hero stays hidden below lg and lg:flex on wide viewports; form still shows ReservaSalas"
      - "User/Login shell keeps px-6 and overflow-x-hidden; Entrar stays w-full"
      - "User/Login still shows Acesse sua conta and does not render cadastro or password-recovery controls"
    integration: []
    e2e: []
  tests_not_applicable:
    integration: "CSS-only change on User/Login.jsx. No Laravel route, middleware, FormRequest, controller, session, or MySQL behavior changes. Existing Feature login HTTP tests remain the auth contract. Do not add a React integration suite."
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Card width is a React class contract covered by Vitest. docs/test/e2e.md forbids repeating that matrix in the browser. Do not bootstrap Playwright in this task."
  gates:
    - id: unit
      command: "php artisan test --testsuite=Unit --coverage --min=80"
      required: true
    - id: frontend
      command: "npm run test:coverage"
      required: true
    - id: integration
      command: "php artisan test --testsuite=Feature"
      required: true
    - id: lint
      command: "vendor/bin/pint --test"
      required: true
    - id: frontend_lint
      command: "npm run lint"
      required: true
    - id: php_build
      command: "composer run build"
      required: true
    - id: frontend_build
      command: "npm run build"
      required: true
---

# Implementation Plan

## Summary

Stop the login card from shrinking to form content and enlarge it on large screens. Add `w-full` to `[data-layout="login-card"]`, replace `max-w-5xl` with `max-w-6xl xl:max-w-7xl`, and keep the existing `lg` 46/54 grid, hidden hero below `lg`, `px-6`, and form behavior. Extend the existing Vitest layout case. Document the tokens in `screen-login.md`.

**Design (inline, no `design.md`):**

- Card classes become `mx-auto grid w-full max-w-6xl overflow-hidden rounded-3xl bg-white shadow-xl xl:max-w-7xl lg:grid-cols-[minmax(0,46%)_minmax(0,54%)]` (keep radius/shadow; order of utilities may follow the file’s current style).
- Do not rewrite `BrandPanel`, `IconTextField`, `PasswordField`, or `Services/session`.
- Do not change Breeze, `LoginRequest`, or login Feature tests.
- Vitest: extend `uses a two-column card on wide viewports…` (or add one sibling case in the same file) for `w-full`, `max-w-6xl`, `xl:max-w-7xl`. Keep hero `hidden` / `lg:flex`, `Entrar` `w-full`, shell `overflow-x-hidden` / `px-6`, Reserva/Salas in the form, and the no-cadastro / no-recovery cases.

## Affected Components

- `app` — `resources/js/Pages/User/Login.jsx`, `resources/js/Pages/User/Login.test.jsx`, `docs/screens/screen-login.md`.
- PHP User module, BrandPanel source, and Playwright stay as-is.

## Tasks

Execute T1 → T2 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. Vitest keeps Inertia mocked. CSS class contracts are the layout proof (`docs/test/unit.md` allows that when styling is the behavior). Do not add PHP Unit cases; login validation stays untouched.

### Integration

Not applicable — no Laravel/MySQL change. See `harness.tests_not_applicable.integration`. Existing Feature login tests remain green.

### E2E

Not applicable — no Playwright project. See `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Unit coverage remains Application-only (≥80%). Frontend coverage via `npm run test:coverage` (≥80%). Do not run `npx playwright test`.

| Gate | Command | Required |
| ---- | ------- | -------- |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` | yes |
| frontend | `npm run test:coverage` | yes |
| integration | `php artisan test --testsuite=Feature` | yes |
| lint | `vendor/bin/pint --test` | yes |
| frontend_lint | `npm run lint` | yes |
| php_build | `composer run build` | yes |
| frontend_build | `npm run build` | yes |

## Definition of Done

- AC-001…AC-006 have Vitest coverage (layout case extended; existing heading / no-cadastro cases remain).
- Card is `w-full`, `max-w-6xl`, `xl:max-w-7xl`; `lg` 46/54 split and hidden hero below `lg` remain.
- `screen-login.md` Responsiveness names those tokens.
- Gates above pass. No product commit in PLAN.

---

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

---

**Design**: inline in Summary (MVP; no `design.md`)
**Status**: Complete

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `docs/reviews/review-tests.md`, `harness/stack.yml`, `phpunit.xml`, `package.json`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| React login page layout | unit | `w-full`; `max-w-6xl`; `xl:max-w-7xl`; `lg` 46/54 columns; hero hidden below `lg`; shell `px-6` / `overflow-x-hidden`; `Entrar` `w-full`; heading and no-cadastro cases remain | `resources/js/Pages/User/Login.test.jsx` | `npm run test:coverage` |
| User HTTP / FormRequest | none | Unchanged; existing Unit + Feature suites remain | `tests/Unit/User/*`, `tests/Feature/User/*` | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` |
| Screen doc | none | Responsiveness tokens only | `docs/screens/screen-login.md` | build / lint |
| Playwright e2e | none | No runner in repo | — | do not run |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After React unit tasks | `npm run test:coverage` |
| Full | After page + existing HTTP suites | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run test:coverage` |
| Build | Phase end / lint / docs | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: Stabilize and enlarge the login card

```
T1 -> T2
```

---

## Task Breakdown

### Phase 1: Stabilize and enlarge the login card

#### T1: Lock login card width and raise the desktop cap

**What**: On `Login.jsx`, add `w-full` to `[data-layout="login-card"]`, replace `max-w-5xl` with `max-w-6xl xl:max-w-7xl`, and keep `lg:grid-cols-[minmax(0,46%)_minmax(0,54%)]`, radius, and shadow. Do not change the form submit path, fields, banners, or BrandPanel. Extend the existing Vitest layout case (or add one sibling in the same file) so it requires `w-full`, `max-w-6xl`, and `xl:max-w-7xl`, and keep the hero `hidden` / `lg:flex`, `Entrar` `w-full`, shell `overflow-x-hidden` / `px-6`, and Reserva/Salas-in-form assertions. Existing heading, field, loading, error, and no-cadastro cases stay.
**Where**: `resources/js/Pages/User/Login.jsx`
**Depends on**: None
**Reuses**: current login-card markup; `Login.test.jsx` two-column layout case
**Requirement**: LOGIN-01, LOGIN-02, LOGIN-03, LOGIN-04, LOGIN-05, LOGIN-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Card class list includes `w-full`, `max-w-6xl`, `xl:max-w-7xl`, and `lg:grid-cols-`
- [x] Card class list does not include a standalone `max-w-5xl` cap
- [x] Hero still `hidden` + `lg:flex`; `Entrar` still `w-full`; shell still `px-6` + `overflow-x-hidden`
- [x] Existing Login Vitest cases still pass (no silent deletions)
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: quick

---

#### T2: Document login card responsive max widths

**What**: Update the Responsiveness section of `docs/screens/screen-login.md`: the card is `w-full`; desktop max is `max-w-6xl` (72rem) from `lg` and `xl:max-w-7xl` (80rem) from `xl`; two columns stay near `46% / 54%` at `lg`; below `lg` one column and hidden hero; ≥24px horizontal inset; no horizontal scroll. Do not rewrite auth, validation, or accessibility sections.
**Where**: `docs/screens/screen-login.md`
**Depends on**: T1
**Reuses**: existing Login Screen Responsiveness headings
**Requirement**: LOGIN-02, LOGIN-03, LOGIN-04, LOGIN-05

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Screen doc names `w-full`, `max-w-6xl`, and `xl:max-w-7xl`
- [x] Desktop still documents the 46/54 split; mobile still hides the large hero
- [x] Auth/validation sections are unchanged
- [x] Gate check passes: `vendor/bin/pint --test` && `npm run lint`

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1

Phase 1:  T1 -> T2
```

Execution is strictly sequential.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Lock login card width and raise the desktop cap | 1 page + its Vitest file | Granular |
| T2: Document login card responsive max widths | 1 markdown file | Granular |

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (start) | Match |
| T2 | T1 | T1 -> T2 | Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | React login page layout | unit | unit | OK |
| T2 | Screen doc | none | none | OK |
