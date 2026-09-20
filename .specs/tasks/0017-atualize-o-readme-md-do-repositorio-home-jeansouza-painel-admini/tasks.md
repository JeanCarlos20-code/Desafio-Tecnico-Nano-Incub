---
harness:
  commits:
    - "chore(readme): document local MySQL 8 setup and run steps"
    - "chore(specs): record readme local-run context"
    - "chore(specs): record readme local-run progress"
    - "chore(specs): record readme local-run reviews"
    - "chore(specs): record readme local-run spec"
    - "chore(specs): record readme local-run tasks"
    - "chore(specs): record readme local-run checks"
    - "chore(specs): record readme local-run validation"
  tests:
    unit: []
    integration: []
    e2e: []
  tests_not_applicable:
    unit: "README-only documentation. No Application use case, FormRequest, or React component changes. docs/test/unit.md forbids artificial tests for configuration and docs."
    integration: "No Laravel HTTP flow, controller, or MySQL persistence change. docs/test/integration.md requires a real request plus MySQL 8 boundary; README.md has none."
    e2e: "No administrator UI workflow change. docs/test/e2e.md requires a real browser route and forbids duplicating lower levels. Documenting local setup is not a product flow. Do not bootstrap Playwright."
  tests_not_applicable_reason: "README-only documentation of existing Compose, .env.example, and composer scripts. No product or test code changes; no punctual unit, integration, or e2e behavior to add."
  gates:
    - id: test
      command: "php artisan test && npm run test"
      required: true
    - id: lint
      command: "vendor/bin/pint --test && npm run lint"
      required: true
    - id: build
      command: "composer run build && npm run build"
      required: true
---

# Implementation Plan

## Summary

Rewrite `README.md` in Portuguese so a newcomer can start MySQL 8 with Docker, fill required `.env` **names** (no values), run migrations, and start the full app with `composer run dev`. Keep the existing `/login` intro and default-administrator table.

**Design (inline, no `design.md`):** one file. Host-run Laravel + Vite. Docker only for `mysql:8.0` via `compose.yml`. Env names taken from `.env.example` (see context). Optional `composer run setup` only after Compose is healthy.

## Affected Components

- `app` — `README.md` at repo root.
- Do not change `compose.yml`, `.env.example`, PHP, React, or tests unless Execute finds a blocking inconsistency (none found in PLAN).

## Tasks

Execute T1 → T2 as in **Task Breakdown**.

## Planned Tests

### Unit

Not applicable — see `harness.tests_not_applicable.unit`.

### Integration

Not applicable — see `harness.tests_not_applicable.integration`.

### E2E

Not applicable — see `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Those match `harness/stack.yml` `app` commands for `test`, `lint`, and `build` (`harness/config.yaml` verify.required). They are regression gates, not new README assertions.

Do not run `npx playwright test`.

## Definition of Done

- All P1 ACs are visible in `README.md` (Portuguese).
- Required env names match the spec list; no env/Compose values.
- Login intro and the three default accounts remain.
- `.env.example` and product/test code unchanged unless a real inconsistency appears.
- Gates `test`, `lint`, and `build` pass.
- No product commit in PLAN.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `AGENTS.md`, `harness/stack.yml`, `phpunit.xml`, `package.json`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| README markdown | none | Documentation contract reviewed against ACs; not a unit/integration/e2e layer | `README.md` | build gate only |
| Application / FormRequest / React | none | Unchanged; do not add docs-only tests | — | existing `test` gate |
| Laravel HTTP + MySQL | none | Unchanged | — | existing `test` gate |
| Playwright e2e | none | No runner in `package.json` | — | do not run |

## Gate Check Commands

> Generated from `composer.json` / `package.json` / `harness/stack.yml`.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | N/A for this docs task | — |
| Full | N/A for this docs task | — |
| Build | After README tasks / phase end | `composer run build && npm run build` |
| Regression | After Execute (harness.gates) | `php artisan test && npm run test` && `vendor/bin/pint --test && npm run lint` && `composer run build && npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: README local run

```
T1 -> T2
```

---

## Task Breakdown

### Phase 1: README local run

### T1: Document Docker MySQL 8, env names, and migrate

**What**: Add Portuguese README sections for prerequisites (PHP 8.2+, Composer, Node.js/npm, Docker Compose v2), `docker compose up -d` on `compose.yml` with MySQL 8 / `mysql:8.0`, wait-until-healthy before migrate, copy `.env.example` → `.env`, `php artisan key:generate` for `APP_KEY`, the exact required env **names** from README-04, and `php artisan migrate` (no `db:seed`). Print no env values.
**Where**: `README.md`
**Depends on**: None
**Reuses**: `compose.yml`, `.env.example`, current README login/admin block (leave it in place)
**Requirement**: README-01, README-02, README-03, README-04, README-05, README-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Docker section names `compose.yml`, `docker compose up -d`, MySQL 8, and `mysql:8.0`
- [x] Env section lists only the seven required names and has no values
- [x] `php artisan migrate` is documented after Compose is healthy; `db:seed` is not required
- [x] Gate check passes: `composer run build && npm run build`

**Tests**: none
**Gate**: build

---

### T2: Document full-app run and keep login accounts

**What**: Add Portuguese instructions for `composer run dev` as the whole-app command (`serve` + `queue:listen` + `pail` + Vite), default URL `http://127.0.0.1:8000`, and optional `composer run setup` only after Docker MySQL 8 is up. State that PHP/Vite run on the host. The run step tells the reader to open that URL and sign in with a table account; it does not require a later `/login` hop. Keep the existing `/login` paragraph and Gertrudes / Marcelo / Emerson table. Pass over the file so the whole README is Portuguese.
**Where**: `README.md`
**Depends on**: T1
**Reuses**: `composer.json` `dev` and `setup` scripts; existing admin table
**Requirement**: README-07, README-08, README-09

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `composer run dev` and `http://127.0.0.1:8000` are documented
- [x] Run step opens that URL and signs in with a table account; no later `/login` hop
- [x] `composer run setup` is optional and requires Docker first
- [x] Login intro and the three default accounts remain
- [x] README prose is Portuguese; no env/Compose secret values
- [x] Gate check passes: `composer run build && npm run build`

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1

Phase 1:  T1 -> T2
```

Execution is strictly sequential.

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Docker, env names, migrate | 1 file, one setup slice | Granular |
| T2: Full-app run + keep login | 1 file, run-all slice | Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (no inbound arrow) | Match |
| T2 | T1 | T1 -> T2 | Match |

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | README markdown | none | none | OK |
| T2 | README markdown | none | none | OK |
