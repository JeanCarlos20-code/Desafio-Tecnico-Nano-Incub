# Stack YAML and Workflow Verify Specification

## Problem Statement

The harness core is stack-agnostic by design, but it currently has no typed description of the consuming project. Planner packets omit languages, frameworks, and command IDs. Deterministic CHECKS always run `tasks.md` shell strings at the worktree root, so they cannot honor per-component `cwd` or a project-level required command set. Technology-specific examples leak into docs while the core still has no `stack.yml` contract.

## Goals

- [ ] `harness/stack.yml` describes this project’s components, metadata, and generic commands.
- [ ] Typed models (`Project`, `Component`, `Command`, `Infrastructure`, `ProjectStack`) load YAML without `Any`.
- [ ] `StackLoader` locates, loads, validates, and returns `ProjectStack`.
- [ ] CHECKS run `verify.required` command IDs on affected components using each component `root` as `cwd`.
- [ ] Planner packet includes a compact stack summary; core stays technology-neutral.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Inferring commands from language/framework | Explicitly forbidden; YAML is the source of truth |
| Changing worktree / plan HITL / execute / review / repair / commit / merge graph | User constraint: only CHECKS resolution and PLAN packet content change |
| Replacing `tasks.md` frontmatter gates | Keep backward-compatible extra gates; `verify.required` is the workflow set |
| Splitting this Laravel app into `backend/` + `frontend/` | ADR-003: single Inertia project; example YAML is schema, not this repo’s layout |
| New root-level `harness.yml` file | Workflow config already lives in `harness/config.yaml` |
| RAG, adapters, language plugins | Out of MVP; would put stack logic in the core |
| Auto-detecting stack from composer/npm | Would infer technology in the core |

---

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Where `verify.required` is stored | Add `verify.required` to existing `harness/config.yaml` (the project’s harness config) | Avoid a second config file; `load_config()` already reads this path | n |
| Dual check sources | CHECKS runs `verify.required` on affected components **and** still runs `tasks.md` gates at worktree root | User forbade flow changes; e2e today depends on tasks.md gates | n |
| Affected-component matching | Longest prefix of `component.root` vs porcelain paths; `.` matches all with lowest priority | Needed for a monolith plus `harness/` without double-running the app commands on Python files | n |
| Missing required command ID | Raise `HarnessError` (misconfiguration), not a red `CheckResult` | Distinct from a command that exists and exits non-zero | n |
| Missing/invalid `stack.yml` at load | `HarnessError` with a clear path/reason | Matches `read_yaml()` today; listed test cases | n |
| Empty affected set | Skip stack verify commands; still run tasks.md gates | No component to execute against; dirty-empty after execute is rare | n |
| Command runner | Keep `["bash", "-lc", command]` with `cwd=worktree/component.root`; reject root escape | Same trust model as current gates (local YAML) | n |
| This repo’s `stack.yml` | Two components: `app` (root `.`) and `harness` (root `harness`) | Product is Laravel+Inertia; harness is Python in-tree. Example’s Angular/Pest/Postgres is not this repo | n |
| Planner without stack during mid-Execute | `PacketService.plan()` requires a valid stack.yml | After this feature ships, stack.yml is part of harness tree; e2e overwrites fixture | n |
| `ContextPacket` | Frozen dataclass used by `PacketService.plan()`; still rendered to `plan.md` | User asked to integrate `ProjectStack` into ContextPacket; packets remain markdown files | n |

**Open questions:** none - all resolved or logged above.

---

## User Stories

### P1: Declare and load a typed project stack ⭐ MVP

**User Story**: As a harness operator, I want `harness/stack.yml` loaded into frozen typed models so that the core knows what exists in the project without embedding PHP, Laravel, Go, or Angular logic.

**Why P1**: Without a typed catalog, Planner and CHECKS cannot stay generic.

**Acceptance Criteria**:

1. WHEN `harness/stack.yml` exists and is structurally valid THEN the system SHALL return a `ProjectStack` containing `Project`, a non-empty sequence of `Component`, and `Infrastructure`.
2. WHEN a component declares `commands` THEN the system SHALL store them as a mapping from arbitrary string IDs to `Command` values that each expose a `command` string.
3. The system SHALL NOT type `commands` as a closed enum of `unit` / `integration` / `e2e` / `test` / `lint` / `build`.
4. The system SHALL NOT use `typing.Any` in stack models or `StackLoader`.
5. IF `harness/stack.yml` is missing THEN the system SHALL raise `HarnessError` whose message includes `stack.yml`.
6. IF the YAML is syntactically invalid or the root value is not an object THEN the system SHALL raise `HarnessError`.
7. WHEN two components are declared THEN the system SHALL keep both names, roots, languages, frameworks, testing lists, and command ID sets.
8. WHEN `infrastructure` lists are empty THEN the system SHALL load them as empty sequences, not as missing/null failures.
9. WHEN a command ID is a custom key such as `foo` THEN the system SHALL accept it without requiring a core code change.

**Independent Test**: Load fixture YAML (valid, multi-component, empty infra, custom key, missing file, invalid YAML) through `StackLoader` in pytest.

---

### P1: Run workflow commands on affected components ⭐ MVP

**User Story**: As a harness operator, I want CHECKS to run `verify.required` command IDs only on components touched by the worktree diff, each with `cwd` equal to that component’s `root`, so that tests/lint/build use the commands declared in YAML rather than inferred from language.

**Why P1**: This is the runtime contract that makes stack.yml useful after Execute.

**Acceptance Criteria**:

1. WHEN CHECKS runs after execute or repair THEN the system SHALL resolve each `verify.required` ID against each affected component’s `commands` map and execute the declared string.
2. WHEN a command runs for a component THEN the system SHALL use `cwd` equal to `worktree / component.root` (`.` means the worktree root).
3. WHEN porcelain paths match more than one component root THEN the system SHALL assign each path to the component with the longest matching root prefix.
4. IF an affected component lacks a `verify.required` ID THEN the system SHALL raise `HarnessError` naming the component and the missing ID, and SHALL NOT invent a command from language or framework.
5. WHEN a required command exists and the process exits non-zero THEN the system SHALL record a failed `CheckResult` and SHALL NOT treat that as a missing-command configuration error.
6. WHEN stack verify results are produced THEN the system SHALL append them to `validation.md` inside the existing harness-checks block.
7. The graph nodes worktree, plan, HITL, execute, review, repair, commit, and merge SHALL keep the same transitions as today.

**Independent Test**: Temporary worktree-like dirs with two component roots; assert cwd used; assert missing ID errors; assert validation.md contains the rendered results.

---

### P1: Give the Planner a compact stack summary ⭐ MVP

**User Story**: As a Planner worker, I want the plan packet to include a compact `ProjectStack` summary so that I can reference command IDs in `tasks.md` instead of guessing `php artisan` or `npm test`.

**Why P1**: Packet isolation is the only context the Planner receives; stack must appear there.

**Acceptance Criteria**:

1. WHEN `PacketService.plan()` builds the packet THEN the system SHALL include a compact summary with project name/type, component names, roots, languages, frameworks, data_access, testing, available command IDs, and infrastructure.
2. The compact summary SHALL list command **IDs**, not a hardcoded technology-specific tutorial.
3. The core Python under `harness/src/project_harness/` SHALL NOT contain control-flow that branches on language or framework names such as `php`, `laravel`, `go`, `angular`, or `pest`.

**Independent Test**: Generate a plan packet against a fixture stack and assert the summary fields; grep/AST test that core `.py` files have no technology branch.

---

## Edge Cases

- IF `component.root` resolves outside the worktree THEN the system SHALL raise `HarnessError` (path escape).
- IF a command string is empty THEN the system SHALL raise `HarnessError`.
- IF no porcelain path matches any component and `verify.required` is non-empty THEN the system SHALL skip stack command execution and still run `tasks.md` gates.
- IF `verify.required` is omitted from config THEN the system SHALL treat it as an empty list (stack verify runs nothing; tasks.md gates still run).
- WHEN the same command ID exists on two affected components THEN the system SHALL run it once per component with that component’s cwd.
- IF YAML `commands` is missing on a component that is not affected THEN the system SHALL still load the stack; the missing-ID error applies only to affected components vs `verify.required`.

## Acceptance Criteria

Consolidated identifiers for traceability (same SHALL statements as the stories above):

- AC-001 through AC-009 map to P1 catalog/loader.
- AC-010 through AC-016 map to P1 verify/execution.
- AC-017 through AC-019 map to P1 planner/neutrality.

| ID | SHALL (short) |
| -- | ------------- |
| AC-001 | Valid stack.yml → `ProjectStack` |
| AC-002 | Generic command map ID → `{command}` |
| AC-003 | Command IDs are not a core enum |
| AC-004 | No `Any` in models/loader |
| AC-005 | Missing file → `HarnessError` mentioning stack.yml |
| AC-006 | Invalid YAML/root → `HarnessError` |
| AC-007 | Multiple components preserved |
| AC-008 | Empty infrastructure lists load |
| AC-009 | Custom command ID accepted |
| AC-010 | `verify.required` executed on affected components |
| AC-011 | cwd = component root |
| AC-012 | Longest-prefix affected matching |
| AC-013 | Missing required ID → `HarnessError` (no inference) |
| AC-014 | Non-zero exit is CheckResult failure |
| AC-015 | Results in validation.md |
| AC-016 | Graph transitions unchanged |
| AC-017 | Planner packet compact stack summary |
| AC-018 | Summary uses command IDs |
| AC-019 | No tech-specific control-flow in core |

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| STACK-01 | P1: Declare and load a typed project stack | Execute | Implementing |
| STACK-02 | P1: Declare and load a typed project stack | Execute | Implementing |
| STACK-03 | P1: Run workflow commands on affected components | Execute | Implementing |
| STACK-04 | P1: Run workflow commands on affected components | Execute | Implementing |
| STACK-05 | P1: Give the Planner a compact stack summary | Execute | Implementing |
| STACK-06 | P1: Give the Planner a compact stack summary | Execute | Implementing |

**ID format:** `STACK-NN`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

**Coverage:** 6 total, 6 mapped to tasks, 0 unmapped

STACK-01 = AC-001..AC-004 (models + generic commands + no Any).
STACK-02 = AC-005..AC-009 (loader errors + multi-component + empty infra + custom ID).
STACK-03 = AC-010..AC-012, AC-014..AC-015 (verify run, cwd, matching, CheckResult, validation.md).
STACK-04 = AC-013, AC-016 (missing ID error, no inference, graph unchanged).
STACK-05 = AC-017, AC-018 (planner summary).
STACK-06 = AC-019 (neutrality).

---

## Success Criteria

- [ ] `harness/stack.yml` exists for Painel-administrativo and is loaded by `StackLoader`.
- [ ] Planner `plan.md` packet contains the compact stack summary.
- [ ] After execute, CHECKS run `test` / `lint` / `build` from YAML on affected components only.
- [ ] Pytest proves the listed loader/verify/planner/neutrality cases; `test_no_any.py` stays green.
- [ ] LangGraph e2e still reaches commit/merge with a fixture stack.yml.
