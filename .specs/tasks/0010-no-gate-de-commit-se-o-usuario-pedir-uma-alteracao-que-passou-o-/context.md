# Task Context

## Relevant Documentation

- `docs/test/unit.md` — isolated behavior without real HTTP/DB; harness pytest maps here.
- `docs/test/integration.md` — Laravel request + MySQL 8; not this task.
- `docs/test/e2e.md` — browser + React + Laravel + MySQL; not this task.
- `docs/context.md`, `docs/tree.md`, `docs/architecture.md`, `docs/persona-agent.md` — product stack; no app-module change.
- `harness/docs/WORKFLOW.md` — HITL #2: revise → Repair, cancel → CANCELED, approve → commit/merge/cleanup.
- `harness/docs/CONTEXT.md` — conversation memory does not cross phase boundaries; workers stay fresh.
- `harness/agents/harness.md` — orchestrator at `gate=commit`: inspect, then `approve-commit` or `revise-code`.
- `harness/README.md` — Gate 2 copy; revise-code returns to Repair/checks/review.

## Relevant Components

- `harness` — Python LangGraph runtime, CLI, orchestrator agent, docs.
- Product `app` (Laravel/Inertia/React) — untouched.

## Relevant Code

- `HarnessGraph._commit_approval` (`harness/src/project_harness/graph_runtime.py`) — HITL options `approve` / `request_changes` / `cancel`. `approve` → `_commit` → `_merge` → `_cleanup`. `request_changes` sets `code_feedback` and routes to `repair_worker`. There is no scope classifier and no restart path.
- `_route_commit_approval` — `revise` → `repair_worker`; `cancel` → `canceled`; `approve` → `commit`.
- `_canceled` — only `store.clear_action(task_id)`. It does not call `GitManager.cleanup`, so the task worktree and unmerged task branch remain. It never calls `_commit` / `_merge`.
- `_cleanup` — used only after a successful merge: `git.cleanup` then `clear_action`.
- `GitManager.cleanup` — `git worktree remove --force`, then `git branch -d`. `-d` refuses an unmerged branch, so cancel cannot reuse this method as-is.
- CLI already covers the sequenced commands: `harness task approve-commit` (commit+merge+cleanup), `harness task cancel`, `harness task revise-code`, `harness task start` (new id, new worktree from target HEAD, new PLAN). `start` refuses a dirty target unless `--allow-dirty`.
- Orchestrator already delegates `kind=agent` to a fresh worker and must not forward the previous conversation. It cannot wipe the human Cursor chat.

Current cancel at the commit gate: does not merge (correct for replacement), but leaves worktree/branch leftovers. Current approve already merges completed work (correct for addition). The gap is orchestrator protocol: today every change request goes to `revise-code`.

## Existing Constraints

- Orchestrator does not replicate the graph and does not edit product code; it is the human interface to `harness` CLI.
- Workers are fresh per phase; packets are the contract; previous chat must not be forwarded into a new PLAN worker.
- Cancel is already a first-class HITL decision at plan and commit gates only (not `repair_limit` / `check_fail_limit`).
- Approve-commit already commits, merges, and cleans the worktree. Addition reuses that path; it must not cancel.
- Task metadata under `.git/harness/tasks/<id>.json` is runtime history; clearing the action is required, deleting the json is not.
- Harness pytest: `python3 -m pytest tests -q` (full); unit gate ignores `tests/test_graph_e2e.py`; graph walks live in that file.
- `harness sync` regenerates `.cursor/agents/harness.md` from `harness/agents/harness.md`.
- Merge conflict already aborts and goes to `NEEDS_HUMAN_ATTENTION`; do not start a follow-up task until the current one is integrated or canceled.

## Important Decisions

- Human revision (plan round 2): split out-of-contract commit-gate feedback into **addition** vs **replacement**. Addition must not delete the current worktree; merge the task, then start a new one. Replacement (almost complete change of the original request) asks to remove the task and start a new one. Both are suggestions; the user may refuse, and then current `revise-code` runs — that override is the user's problem.
- Scope vs local-adjustment vs addition vs replacement is orchestrator judgment with a written heuristic, not a Python classifier.
- **Addition accept:** `approve-commit` then `harness task start "<additional request only>"` on the same target branch. Do not cancel. The original reviewed work is kept via merge. The new PLAN sees only the added scope.
- **Replacement accept:** `cancel` then `harness task start "<replacement request>"` on the same target branch. Do not merge. Discard worktree and unmerged task branch.
- **Refuse either suggestion:** current `revise-code` with the user's change text.
- **Local adjustment** that does not change the original contract stays `revise-code` with no suggestion.
- Cancel (plan or commit) must discard the isolated worktree and unmerged task branch and must not merge. Merge-path cleanup keeps `git branch -d`.
- “Clear the context window” means fresh workers and no forwarding of the old task into the new PLAN/EXECUTE/REVIEW. It does not delete the user's Cursor chat.
- Uncertain local vs any scope change: suggest, do not silently `revise-code`. Uncertain addition vs replacement: if the original request is still wanted, treat as addition; if it is obsolete, treat as replacement; if still unsure, present both options and wait — do not default to cancel (that would discard valid work).
- No new `restart` CLI or graph option. Integration and product E2E are not applicable; harness pytest (including LangGraph walks) is unit in the product taxonomy.
