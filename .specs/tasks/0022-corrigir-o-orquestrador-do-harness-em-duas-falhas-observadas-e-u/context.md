# Task Context

## Relevant Documentation

- `harness/agents/harness.md` — orchestrator contract. Hole 1: `## Início` runs `harness task start` on a new request without checking an open human-gate task. Hole 2: `gate=plan` tells the agent to rebuild three sections from `summary` / `tests` / `gates` and never says “paste the CLI summary verbatim” or “ask if the human approves”. Commit-gate classification (local / addition / replacement / wait) already exists from task 0010.
- `harness/agents/plan.md` — planner still requires `tests_not_applicable.<level>` for every empty level. No rule for a single “no new tests” sentence.
- `harness/docs/WORKFLOW.md` and `harness/README.md` — Gate 1 lists plan then punctual tests then commands; Gate 2 documents merge-then-start / cancel-then-start. Neither says paste the CLI summary in full, ask for plan approval, or refuse `task start` while a human gate is open.
- `docs/test/unit.md` — isolated rules, no real HTTP, no real DB. Harness pytest belongs here.
- `docs/test/integration.md` — Laravel request + middleware + FormRequest + controller + use case + MySQL 8. Not this task.
- `docs/test/e2e.md` — browser + React + Laravel + MySQL, selected full flows. Not this task.
- Product ADRs, screens, and `docs/architecture.md` are out of scope (Laravel rooms/reservations must not change).

## Relevant Components

- `harness` — Python CLI, LangGraph, agent prompts, pytest. `harness/stack.yml` commands: `python3 -m pytest tests -q` (`test`), `python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` (`unit`), `python3 -m compileall -q src` (`lint`), `PYTHONPATH=src python3 -c "import project_harness"` (`build`).
- `app` — do not touch.

## Relevant Code

- `harness/src/project_harness/artifacts.py`
  - `plan_barrier_summary` already emits `## Plano`, `## Testes pontuais`, `## Comandos após o Execute`.
  - Empty test levels always render `- not applicable: {reason}` per level (`TEST_LEVELS` loop).
  - `_parse_planned_tests` requires `tests_not_applicable.<level>` whenever a list is empty. There is no path for “no new tests at all”.
  - `tests_not_applicable_reason` today is only the no-gates fallback under **Comandos** (`if not gates and not no_tests_reason`).
- `harness/src/project_harness/cli.py` — `_print_payload` already prints `action.summary` in full after the human-gate message. The CLI is not the cutter; the orchestrator is.
- `harness/src/project_harness/graph_runtime.py` — `_plan_approval` sets `summary` to `plan_barrier_summary`, plus structured `tests` / `tests_not_applicable` / `gates`. No `tests_not_applicable_reason` on the payload.
- `harness/src/project_harness/task_store.py` — tasks and actions live under `.git/harness/tasks/` and `.git/harness/actions/`. There is no `harness task list`. Open-gate detection is prompt + existing `harness task action <id>` / action files, not a new verb.
- `harness/src/project_harness/packets.py` — generated PLAN packet still says the human gate presents unit / integration / e2e lists; it does not mention the single no-new-tests sentence.
- `harness/tests/test_orchestrator_agent.py` — reads `harness.md` `gate=commit` only. No Início / plan-gate contract tests.
- `harness/tests/test_artifacts.py` — `test_plan_barrier_summary_lists_punctual_tests_by_level` locks the three-section order when tests exist. `test_plan_requires_reason_when_a_level_has_no_tests` locks per-level skip. No all-empty-tests case.
- `harness/tests/test_cli.py` — `test_cli_prints_test_barrier_not_commit_on_plan_gate` only checks that a fixture summary is printed.

## Existing Constraints

- Do not change Laravel rooms/reservations or product PHP/React tests.
- Do not add a Python classifier for local / addition / replacement (task 0010 decision).
- Do not add `harness task restart`.
- Do not hard-block `task start` in the CLI: the user may explicitly authorize a new task while others remain open (this task 0022 is that case).
- `task start` after addition accept still requires a finished merge; after replacement accept, cancel first.
- Silence / ambiguous “ok” is not accept, approve-plan, or approve-commit.
- Harness pytest is isolated Python → product taxonomy **unit**. Do not label `test_graph_e2e.py` as product E2E.
- Conventional Commits: `type(module):` English; production and tests split; one module per commit.

## Important Decisions

- Paste `action.summary` (`plan_barrier_summary`) in full. Do not rewrite or drop sections. After the summary, ask whether the human approves the plan. Do not implement at the plan gate.
- When every `harness.tests` level is empty, require `tests_not_applicable_reason` starting with `sem testes para esse plano pois ele é apenas` and render that single sentence under `## Testes pontuais`. Do not emit per-level “not applicable” and do not invent empty coverage. Mixed plans (some levels have tests) keep `tests_not_applicable.<level>`.
- Reuse `tests_not_applicable_reason` for the no-new-tests sentence. It already exists; it also remains the no-gates fallback under **Comandos**.
- Before `task start`, if a task in this conversation (or a readable action under `.git/harness/actions/`) is `kind=human`, classify on that gate. Do not start. Addition: suggest merge + new task and wait. Replacement: suggest cancel + new task and wait. Local: `revise-code` immediately at commit (or `revise-plan` at plan). Loose report: do not start. `task start` only after explicit accept of a classified suggestion, or a clear new request when no human gate is open.
- No new `harness task list` command in this task.
- This task has new harness tests → three-section punctual-test presentation (not the no-new-tests sentence).
