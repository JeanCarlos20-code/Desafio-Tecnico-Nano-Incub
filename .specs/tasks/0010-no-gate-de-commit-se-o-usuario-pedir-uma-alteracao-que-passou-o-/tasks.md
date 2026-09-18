---
harness:
  commits:
    - "feat(harness): discard unmerged worktree and split commit-gate scope"
    - "test(harness): cover cancel cleanup and commit-gate scope split"
    - "chore(cursor): sync orchestrator agent for commit-gate scope split"
    - "chore(specs): record commit-gate scope-split context"
    - "chore(specs): record commit-gate scope-split progress"
    - "chore(specs): record commit-gate scope-split reviews"
    - "chore(specs): record commit-gate scope-split spec"
    - "chore(specs): record commit-gate scope-split tasks"
    - "chore(specs): record commit-gate scope-split checks"
    - "chore(specs): record commit-gate scope-split validation"
  tests:
    unit:
      - "Cancel at the commit gate does not merge and leaves the target branch unchanged"
      - "Cancel removes the task worktree and deletes the unmerged task branch"
      - "Cancel clears the task action file"
      - "Unmerged-branch cleanup uses force delete; merge-path cleanup still uses git branch -d"
      - "Commit-gate request_changes still routes to Repair with code_feedback"
      - "Orchestrator agent instructs merge-then-start on commit-gate scope addition"
      - "Orchestrator agent instructs cancel-then-start on commit-gate scope replacement"
      - "Orchestrator agent keeps revise-code for local adjustments and for an explicit refuse"
      - "Orchestrator agent starts the new task with fresh workers and does not forward the previous conversation"
      - "Orchestrator agent does not default an uncertain addition-versus-replacement choice to cancel"
    integration: []
    e2e: []
  tests_not_applicable:
    integration: "This task changes the Python harness cancel path and orchestrator contract. It does not add or change a Laravel HTTP flow against MySQL 8."
    e2e: "This task does not change a browser + React + Laravel + MySQL administrator workflow. LangGraph walks stay in harness pytest, which is not product E2E."
  gates:
    - id: harness-unit
      command: "cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py"
      required: true
    - id: harness-graph
      command: "cd harness && python3 -m pytest tests/test_graph_e2e.py -q"
      required: true
    - id: harness-lint
      command: "cd harness && python3 -m compileall -q src"
      required: true
---

# Implementation Plan

## Summary

At the commit gate, a change that leaves the original contract is classified by the orchestrator.

- **Local adjustment:** current `revise-code`.
- **Scope addition** (original request still wanted): suggest merge then a new task. Accept is `approve-commit` then `harness task start` with only the extra request, fresh workers, full PLAN. Do not cancel. Do not delete completed work.
- **Scope replacement** (original request almost completely replaced): suggest cancel then a new task. Accept is `cancel` (no merge, discard worktree/branch/action) then `start` with the replacement request, fresh workers, full PLAN.
- **Refuse either suggestion:** current `revise-code`. That override is the user's problem.

Machine gap to close: `_canceled` today only clears the action; `GitManager.cleanup` uses `git branch -d`, which cannot delete an unmerged task branch. Cancel must discard that isolated git state. Addition reuses the existing approve-commit path. Do not add a `restart` CLI.

## Affected Components

- `harness/src/project_harness/git_manager.py` — cancel cleanup must delete an unmerged task branch (`-D`); merge-path cleanup keeps `-d`.
- `harness/src/project_harness/graph_runtime.py` — `_canceled` calls that cleanup and never commit/merge; commit-gate payload may hint the addition vs replacement split.
- `harness/agents/harness.md` — commit-gate heuristic and suggest / accept / refuse protocol. Run `harness sync` so generated targets match.
- `harness/docs/WORKFLOW.md`, `harness/README.md` — document the split next to Gate 2.
- `harness/tests/test_git_manager.py`, `harness/tests/test_graph_e2e.py`, `harness/tests/test_cli.py` (or a small agent-contract test) — punctual coverage.

## Tasks

Make cancel discard isolated git state. Teach the orchestrator the addition vs replacement split. Keep local and refused suggestions on `revise-code`. Do not classify scope in Python.

## Execution Plan

### Phase 1: Cancel discards isolated git state

```
T1 -> T2
```

### Phase 2: Orchestrator contract and docs

```
T2 -> T3
T3 -> T4
```

## Task Breakdown

### T1: Delete unmerged task branches on cancel cleanup

**What**: Extend `GitManager.cleanup` so a caller can force-delete an unmerged task branch (`git branch -D`) after `git worktree remove --force`. Keep the existing merge-success path on `git branch -d`. Missing worktree/branch stays non-fatal as today.
**Where**: `harness/src/project_harness/git_manager.py`
**Depends on**: None
**Requirement**: SCOPE-12, SCOPE-13, SCOPE-20
**Tests**: unit — create worktree, write an uncommitted file, cleanup with unmerged delete: worktree gone, branch gone, target branch lacks the file; merge-then-cleanup still succeeds with `-d`
**Gate**: harness-unit

### T2: Canceled node never merges and always cleans git leftovers

**What**: `_canceled` loads task meta, calls cleanup with unmerged delete, then `clear_action`. It must not call `_commit` or `_merge`. Optional: add one sentence to the commit-gate HITL payload that additive scope should merge then start a new task, and replacement should cancel then start, not silent `revise-code`. Plan-gate cancel uses the same node, so it gets the same cleanup.
**Where**: `harness/src/project_harness/graph_runtime.py`
**Depends on**: T1
**Requirement**: SCOPE-11, SCOPE-12, SCOPE-13, SCOPE-18, SCOPE-19, SCOPE-20
**Tests**: unit — LangGraph walk to commit gate then `cancel`: status `canceled`, worktree gone, task branch gone, action cleared, target HEAD unchanged; `request_changes` still routes to repair
**Gate**: harness-unit then harness-graph

### T3: Instruct the orchestrator on commit-gate scope split

**What**: In `harness/agents/harness.md` `gate=commit` section: (1) local adjustment → `revise-code`; (2) scope addition (original still wanted) → suggest merge + new task, show merge/no-cancel warning and drafted additional request, wait for explicit accept/refuse; accept → `approve-commit` then `harness task start "<additional request>"` on the same target branch; if merge conflicts, do not start; (3) scope replacement (original almost completely replaced) → suggest cancel + new task, show discard/no-merge warning and drafted replacement request; accept → `cancel` then `start "<replacement request>"`; (4) refuse either suggestion → current `revise-code`; (5) uncertain local vs scope → suggest; uncertain addition vs replacement → still-wanted original = addition, obsolete = replacement, still unsure = present both and wait, do not default to cancel; (6) silence / ambiguous “ok” is neither approve nor accept-cancel; (7) fresh PLAN worker, do not forward previous conversation/packets/spec. Run `harness sync` after the canonical agent change.
**Where**: `harness/agents/harness.md`
**Depends on**: T2
**Requirement**: SCOPE-01, SCOPE-02, SCOPE-03, SCOPE-04, SCOPE-05, SCOPE-06, SCOPE-07, SCOPE-08, SCOPE-09, SCOPE-10, SCOPE-14, SCOPE-15, SCOPE-16, SCOPE-17, SCOPE-21, SCOPE-22
**Tests**: unit — canonical agent file contains the three-way heuristic, addition=approve-commit+start, replacement=cancel+start, refuse=`revise-code`, no default-to-cancel when uncertain addition vs replacement, fresh-worker / do-not-forward rule
**Gate**: harness-unit

### T4: Document Gate 2 scope split

**What**: Update `WORKFLOW.md` HITL #2 and README Gate 2 so they state: local change → revise; addition → suggest merge then start a new task; replacement → suggest cancel (no merge, cleanup worktree/branch/action) then start a new task that replans; refuse → revise-code. Do not claim a new CLI command exists.
**Where**: `harness/docs/WORKFLOW.md`
**Depends on**: T3
**Requirement**: SCOPE-01, SCOPE-03, SCOPE-09, SCOPE-11, SCOPE-17 (human-visible contract)
**Tests**: none
**Gate**: harness-lint

Do not add a Python scope classifier. Do not add `harness task restart`. Do not reuse a canceled worktree. Do not cancel on addition accept. Do not change product Laravel/React tests.

## Planned Tests

Guidelines: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, plus existing `harness/tests/*.py` as the style floor. Harness pytest is isolated Python (no Laravel HTTP, no MySQL, no browser), so it maps to **unit** in the product taxonomy.

### Unit

- Cancel at the commit gate does not merge and leaves the target branch unchanged (LangGraph walk).
- Cancel removes the task worktree and deletes the unmerged task branch (`GitManager.cleanup` + graph walk).
- Cancel clears the task action file.
- Unmerged-branch cleanup uses force delete; merge-path cleanup still uses `git branch -d`.
- Commit-gate `request_changes` still routes to Repair with `code_feedback`.
- Orchestrator agent instructs merge-then-start on commit-gate scope addition.
- Orchestrator agent instructs cancel-then-start on commit-gate scope replacement.
- Orchestrator agent keeps `revise-code` for local adjustments and for an explicit refuse.
- Orchestrator agent starts the new task with fresh workers and does not forward the previous conversation.
- Orchestrator agent does not default an uncertain addition-versus-replacement choice to cancel.

Co-locate: git cleanup in `test_git_manager.py`; graph walk in `test_graph_e2e.py`; agent wording next to existing CLI/packet tests.

### Integration

Not applicable — no Laravel request + MySQL 8 flow.

### E2E

Not applicable — no browser + React + Laravel + MySQL workflow. Do not list `test_graph_e2e.py` as product E2E.

## Required Gates

Run from the task worktree (CheckRunner cwd is the worktree root):

| Gate | Command | Required |
| ---- | ------- | -------- |
| harness-unit | `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` | yes |
| harness-graph | `cd harness && python3 -m pytest tests/test_graph_e2e.py -q` | yes |
| harness-lint | `cd harness && python3 -m compileall -q src` | yes |

Stack verify still runs command IDs `test`, `lint`, and `build` on affected components (`harness` when those paths change). Do not add PHPUnit, Vitest, or Playwright gates for this task.

## Definition of Done

- Commit-gate scope addition is suggested as merge + new task; replacement is suggested as cancel + new task; local adjustment stays `revise-code`.
- Explicit addition accept merges without cancel, then starts a new task for the extra request, fresh workers, full replan.
- Explicit replacement accept cancels without merge, removes worktree and unmerged branch, clears action, starts a new task, fresh workers, full replan.
- Explicit refuse of either suggestion keeps current `revise-code` → Repair.
- Merge-path cleanup still uses `git branch -d`.
- Punctual unit tests above pass; integration and e2e remain explicitly not applicable.
- No product app code changed. No commit in Execute.

## Test Coverage Matrix

> Generated from `docs/test/*.md`, `harness/stack.yml`, and sampled `harness/tests/*.py`. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| GitManager cancel cleanup | unit | Unmerged worktree/branch deleted; target unchanged; merge-path `-d` still works | `harness/tests/test_git_manager.py` | `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` |
| LangGraph cancel / revise-code | unit (harness pytest, not product E2E) | Commit-gate cancel: canceled, no merge, leftovers gone; request_changes still repair | `harness/tests/test_graph_e2e.py` | `cd harness && python3 -m pytest tests/test_graph_e2e.py -q` |
| Orchestrator agent contract | unit | Heuristic + addition merge+start + replacement cancel+start + refuse + fresh workers present in canonical agent | `harness/tests/test_cli.py` or adjacent | same harness-unit command |
| Laravel HTTP / MySQL | none | N/A | — | — |
| Playwright / Inertia UI | none | N/A | — | — |

## Gate Check Commands

| Gate Level | When to Use | Command |
| ---------- | ----------- | -------- |
| Quick | After git/agent tasks | `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` |
| Full | After graph `_canceled` changes | `cd harness && python3 -m pytest tests -q` |
| Build | After phase completion | `cd harness && python3 -m compileall -q src` and stack verify `test` / `lint` / `build` |

## Phase Execution Map

```
Phase 1 -> Phase 2

T1 -> T2
T2 -> T3
T3 -> T4
```

T2 depends on T1. T3 depends on T2. T4 depends on T3.

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1 | GitManager cleanup flag | Granular |
| T2 | `_canceled` + optional payload hint | Granular |
| T3 | Orchestrator agent protocol | Granular |
| T4 | WORKFLOW/README copy | Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (body) | Diagram | Status |
| ---- | ----------------- | ------- | ------ |
| T1 | None | Phase 1 start | Match |
| T2 | T1 | T1 → T2 | Match |
| T3 | T2 | T2 → T3 | Match |
| T4 | T3 | T3 → T4 | Match |

## Task Granularity Check notes

T2 may edit one sentence of the commit-gate payload in the same file as `_canceled`. That is cohesive, not a second feature.

## Test Co-location Validation

| Task | Layer | Matrix | Task says | Status |
| ---- | ----- | ------ | --------- | ------ |
| T1 | GitManager | unit | unit | OK |
| T2 | LangGraph walk | unit | unit | OK |
| T3 | Orchestrator agent contract | unit | unit | OK |
| T4 | docs | none | none | OK |
