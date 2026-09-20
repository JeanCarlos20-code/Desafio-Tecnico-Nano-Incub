---
harness:
  commits:
    - "fix(harness): paste plan summary and refuse start on an open gate"
    - "test(harness): cover plan-barrier no-tests phrase and open-task start rule"
    - "chore(cursor): sync generated orchestrator agent prompts"
    - "chore(specs): record orchestrator plan-gate and open-task plan artifacts"
  tests:
    unit:
      - "plan_barrier_summary shows the single no-new-tests sentence and no per-level not-applicable rows when every harness.tests level is empty"
      - "validate_plan rejects all-empty harness.tests when tests_not_applicable_reason is missing or lacks the required prefix"
      - "validate_plan accepts all-empty harness.tests with tests_not_applicable_reason and does not require tests_not_applicable.<level>"
      - "plan_barrier_summary still lists Unit, Integration, and E2E when at least one level has punctual tests"
      - "validate_plan still requires tests_not_applicable.<level> when only some levels are empty"
      - "Orchestrator plan-gate section requires pasting action.summary in full and asking whether the human approves the plan"
      - "Orchestrator Início requires an open human-gate check and does not call task start while kind=human is open"
      - "Orchestrator keeps merge-then-start wait, cancel-then-start wait, immediate revise-code for local commit-gate adjustments, and no start on a loose report"
    integration: []
    e2e: []
  tests_not_applicable:
    integration: "This task changes the Python harness plan-barrier renderer and orchestrator prompts. It does not add or change a Laravel HTTP flow against MySQL 8."
    e2e: "This task does not change a browser + React + Laravel + MySQL administrator workflow. Harness pytest stays at unit in the product taxonomy."
  gates:
    - id: harness-unit
      command: "cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py"
      required: true
    - id: harness-lint
      command: "cd harness && python3 -m compileall -q src"
      required: true
---

# Implementation Plan

## Summary

Fix two orchestrator holes and the no-new-tests presentation.

**Falha 1.** `plan_barrier_summary` already builds Plano + Testes pontuais + Comandos. `_print_payload` already prints `summary`. `harness.md` `gate=plan` must say: paste `action.summary` in full, do not cut, then ask if the human approves the plan. Do not implement. Do not show commits.

**No-new-tests exception.** When every `harness.tests` level is empty, require `tests_not_applicable_reason` starting with `sem testes para esse plano pois ele é apenas` and render that sentence under `## Testes pontuais`. Do not print three `not applicable` rows. Mixed plans keep per-level `tests_not_applicable.<level>`. Teach `plan.md` and the generated PLAN packet the same rule.

**Falha 2.** Change `## Início`: if a task in this conversation or `.git/harness/actions/` is `kind=human`, classify on that gate and do not call `task start`. Addition: suggest merge + new task and wait. Replacement: suggest cancel + new task and wait. Local at commit: `revise-code` immediately. Local at plan: `revise-plan`. Loose report: do not start. Clear new request with no human gate open: start as today. Do not add `task list`. Do not hard-block start in the CLI.

**Design (inline, no `design.md`):** reuse `tests_not_applicable_reason`; keep classification in the prompt; no Python classifier.

## Affected Components

- `harness/src/project_harness/artifacts.py` — `validate_plan` / `_parse_planned_tests` / `plan_barrier_summary`
- `harness/src/project_harness/graph_runtime.py` — optional: put `tests_not_applicable_reason` on the plan-gate payload (summary remains the source the orchestrator pastes)
- `harness/src/project_harness/packets.py` — PLAN packet text for the no-new-tests exception
- `harness/agents/harness.md` — `## Início` and `gate=plan`
- `harness/agents/plan.md` — empty-level vs no-new-tests rule
- `harness/docs/WORKFLOW.md`, `harness/README.md` — Gate 1 paste + approval question; start only after open-gate classification
- `harness/tests/test_artifacts.py`, `harness/tests/test_orchestrator_agent.py` — and `test_packets.py` / `test_cli.py` only if those files must change with the packet or print contract
- Do not change `app/`, rooms, reservations, PHPUnit, Vitest, or Playwright

## Tasks

Execute T1 → T5 in order. Run `harness sync` after the canonical agent change so generated targets match.

### T1: Collapse all-empty plan tests into one sentence

**What**: When `harness.tests.unit`, `integration`, and `e2e` are all empty, require `tests_not_applicable_reason` that starts with `sem testes para esse plano pois ele é apenas`. Accept the plan without `tests_not_applicable.<level>`. `plan_barrier_summary` prints that sentence under `## Testes pontuais` and does not print `### Unit` / `### Integration` / `### E2E` or `- not applicable:`. Mixed plans stay as today. Keep the no-gates use of the same field under **Comandos**.
**Where**: `harness/src/project_harness/artifacts.py`; optionally `graph_runtime.py` payload; `packets.py` PLAN text
**Depends on**: None
**Requirement**: AC-004, AC-005, AC-006, AC-007, AC-008
**Tests**: unit in `test_artifacts.py` (and packet/CLI tests if those files change)
**Gate**: harness-unit

### T2: Instruct the orchestrator to paste the plan summary

**What**: In `harness.md` `gate=plan`: (1) paste `action.summary` in full; (2) do not rewrite or cut; (3) if the summary already has the no-new-tests sentence, show it as-is; (4) ask whether the human approves the plan; (5) do not implement; (6) do not show commits. In `plan.md`: empty level → `tests_not_applicable.<level>`; all levels empty → `tests_not_applicable_reason` sentence, do not invent coverage.
**Where**: `harness/agents/harness.md`, `harness/agents/plan.md`
**Depends on**: T1
**Requirement**: AC-001, AC-002, AC-003, AC-008
**Tests**: unit — plan-gate section of `harness.md` (extend `test_orchestrator_agent.py`)
**Gate**: harness-unit

### T3: Refuse `task start` while a human gate is open

**What**: Rewrite `## Início`: before `harness task start`, check this conversation and `.git/harness/actions/` via existing `harness task action <id>`. If `kind=human`, classify on that gate. Commit addition → suggest merge+new and wait. Commit replacement → suggest cancel+new and wait. Commit local → `revise-code` immediately. Plan local → `revise-plan`. Loose report → do not start. No open human gate + clear new request → start. Explicit accept of a suggestion still uses the existing approve-commit/start or cancel/start sequences. Do not add a list command. Run `harness sync`.
**Where**: `harness/agents/harness.md`
**Depends on**: T2
**Requirement**: AC-009, AC-010, AC-011, AC-012, AC-013, AC-014
**Tests**: unit — Início + commit-gate wait/loose-report wording in `test_orchestrator_agent.py`
**Gate**: harness-unit

### T4: Document Gate 1 paste and the open-task start rule

**What**: README Gate 1 and WORKFLOW HITL #1: orchestrator pastes the CLI summary in full and asks for plan approval; no-new-tests sentence when there are no new tests. README / WORKFLOW start + Gate 2: do not `task start` while a human gate is open; classify; wait on addition/replacement; loose report does not open a task.
**Where**: `harness/README.md`, `harness/docs/WORKFLOW.md`
**Depends on**: T3
**Requirement**: AC-001, AC-002, AC-004, AC-009, AC-013, AC-014 (human-visible contract)
**Tests**: none
**Gate**: harness-lint

### T5: Punctual harness tests

**What**: Add the unit behaviors listed under Planned Tests. Co-locate renderer tests in `test_artifacts.py` and prompt tests in `test_orchestrator_agent.py`. Keep existing commit-gate tests green. Do not weaken them.
**Where**: `harness/tests/test_artifacts.py`, `harness/tests/test_orchestrator_agent.py`
**Depends on**: T1, T2, T3
**Requirement**: AC-001 through AC-014
**Tests**: the new unit cases themselves
**Gate**: harness-unit

Do not classify scope in Python. Do not add `harness task list` or `restart`. Do not hard-block `task start` in the CLI. Do not change product Laravel/React tests.

## Planned Tests

Guidelines: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, plus existing `harness/tests/*.py` as the style floor. Harness pytest is isolated Python (no Laravel HTTP, no MySQL, no browser), so it maps to **unit**.

### Unit

- `plan_barrier_summary` shows the single no-new-tests sentence and no per-level not-applicable rows when every `harness.tests` level is empty.
- `validate_plan` rejects all-empty `harness.tests` when `tests_not_applicable_reason` is missing or lacks the required prefix.
- `validate_plan` accepts all-empty `harness.tests` with a valid `tests_not_applicable_reason` and does not require `tests_not_applicable.<level>`.
- `plan_barrier_summary` still lists Unit, Integration, and E2E when at least one level has punctual tests.
- `validate_plan` still requires `tests_not_applicable.<level>` when only some levels are empty.
- Orchestrator plan-gate section requires pasting `action.summary` in full and asking whether the human approves the plan.
- Orchestrator `## Início` requires an open human-gate check and does not call `task start` while `kind=human` is open.
- Orchestrator keeps merge-then-start wait, cancel-then-start wait, immediate `revise-code` for local commit-gate adjustments, and no start on a loose report.

### Integration

Not applicable — no Laravel request + MySQL 8 flow.

### E2E

Not applicable — no browser + React + Laravel + MySQL workflow. Do not list `test_graph_e2e.py` as product E2E.

## Required Gates

Run from the task worktree (CheckRunner cwd is the worktree root):

| Gate | Command | Required |
| ---- | ------- | -------- |
| harness-unit | `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` | yes |
| harness-lint | `cd harness && python3 -m compileall -q src` | yes |

Stack verify from `harness/config.yaml` also runs command IDs `test`, `lint`, and `build` on the dirty `harness` component (`python3 -m pytest tests -q`, `python3 -m compileall -q src`, `PYTHONPATH=src python3 -c "import project_harness"`).

If Execute touches `graph_runtime.py` in a way that changes graph walks, add `cd harness && python3 -m pytest tests/test_graph_e2e.py -q` before complete-phase. The default plan does not require that file.

## Definition of Done

- `harness.md` pastes the plan-gate CLI summary, asks for approval, and refuses `task start` while a human gate is open.
- `plan_barrier_summary` / `validate_plan` implement the no-new-tests sentence and keep mixed-level skips.
- `plan.md`, README, and WORKFLOW match those rules.
- Punctual harness unit tests above are green.
- Required gates and stack verify pass.
- No Laravel room/reservation files changed.
