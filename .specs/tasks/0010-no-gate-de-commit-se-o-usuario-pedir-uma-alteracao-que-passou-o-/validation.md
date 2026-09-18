# Validation

## Acceptance Criteria

- AC-001 / AC-002 / AC-003 / AC-004 / AC-009 / AC-010 / AC-014 / AC-015 / AC-016 / AC-021 / AC-022: unchanged. Addition still suggests merge+start (`approve-commit` then `start`); replacement still suggests cancel+start; accept/refuse semantics kept. No `harness task restart`.
- AC-005: local/simple change (layout tweak, popup on a planned screen, test for existing ACs, copy, rename, bugfix) calls `revise-code` immediately and does not suggest merge-and-start or cancel-and-start. `harness/agents/harness.md` `gate=commit` plus generated `.cursor/agents/harness.md`.
- AC-006 (narrowed by human commit-gate feedback): simple local tweaks are not treated as uncertain scope. Suggestion only when the change would redo the task (replacement) or add another task (addition). The old “uncertain local vs scope → always suggest” rule was removed from the orchestrator contract.
- AC-007 / AC-008: uncertain addition vs replacement still treats a wanted original as addition, an obsolete original as replacement, and presents both when still unsure; does not default to cancel. Silence / ambiguous “ok” is not approve or cancel.
- AC-017: explicit refuse still calls `harness task revise-code`. TEST-001 now isolates the **Recusa** slice until `**Incerteza:**` so the refuse assert cannot pass on the uncertainty block.
- AC-011 / AC-012 / AC-013 / AC-018 / AC-019 / AC-020: cancel cleanup and `request_changes` → Repair unchanged in this repair.

Additional files beyond `tasks.md` areas: `harness/tests/test_orchestrator_agent.py` holds the orchestrator-agent contract tests (already required). `harness/docs/WORKFLOW.md` and `harness/README.md` restated the same commit-gate heuristic, so they were updated to match the narrowed local-vs-suggestion rule. `.cursor/agents/harness.md` is the generated Cursor copy after syncing the canonical agent (cursor target only).

No Python scope classifier. No `harness task restart` command. No product Laravel/React changes.

SPEC_DEVIATION: AC-006 originally required suggesting whenever local vs scope was uncertain. Human feedback at the commit gate replaced that with: simple local change → `revise-code` immediately; suggest only to redo the task or add another task.

## Test Results

Local quick checks (harness will re-run required gates):

- `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` → 101 passed
- `cd harness && python3 -m pytest tests/test_graph_e2e.py -q` → 5 passed
- `cd harness && python3 -m compileall -q src` → exit 0

Punctual unit coverage:

| harness.tests item | Evidence |
| --- | --- |
| Cancel at commit gate does not merge; target unchanged | `harness/tests/test_graph_e2e.py:442` `values.get("status") == "canceled"`; `:446` `rev-parse HEAD == target_head`; `:445` `reservation.txt` absent on target |
| Cancel removes worktree and unmerged task branch | `test_graph_e2e.py:444` `not worktree.exists()`; `:454` `show-ref` returncode != 0; `test_git_manager.py:107-108` worktree gone and branch ref gone after `delete_unmerged=True` |
| Cancel clears the action file | `test_graph_e2e.py:455` `store.read_action(task_id) is None` |
| Unmerged cleanup uses force delete; merge-path keeps `-d` | `test_git_manager.py:123-125` default cleanup raises `not fully merged` and branch remains; existing `test_worktree_commit_merge_cleanup` still cleans after merge |
| Commit-gate `request_changes` still routes to Repair | `test_graph_e2e.py:479` phase `repair`; `:481` `code_feedback == "simplifique o service"`; `:482` status is not canceled |
| Addition → merge then start | `test_orchestrator_agent.py:23-25` `approve-commit` + `start`, no `cancel` |
| Replacement → cancel then start | `test_orchestrator_agent.py:32-34` `cancel` + `start`, no `approve-commit` |
| Local and refuse stay `revise-code` | `test_orchestrator_agent.py:40-47` local slice requires `harness task revise-code` immediately with no suggestion; refuse slice ends at `**Incerteza:**` and requires `harness task revise-code` only there |
| Suggestion only when redo or add another task | `test_orchestrator_agent.py:14-18` intro requires `sugestão só` / `refizer a tarefa` / `adicionar outra tarefa` / `imediatamente` |
| Fresh workers; do not forward previous conversation | `test_orchestrator_agent.py:52-54` |
| Uncertain addition vs replacement does not default to cancel | `test_orchestrator_agent.py:58-64` uncertainty slice: no default cancel; old `local vs mudança de escopo` rule absent; simple tweak is not uncertain scope |

Integration and e2e: not applicable (harness pytest only).

## Required Gates

Declared required gates were run locally as quick checks. Final green/red is owned by the harness CheckRunner after this phase completes.

- harness-unit: ran locally, 101 passed
- harness-graph: ran locally, 5 passed
- harness-lint: ran locally, compileall exit 0

## Review Result

Latest review `review-01.md` verdict `APPROVED`. No blocker/high. Medium TEST-001 (loose Recusa slice) was tightened in this repair because the human-feedback change touched that test. Human feedback applied: suggestion only when redoing the task or adding another task; simple local change calls `revise-code` immediately.

## Final Status

Repair ready for harness deterministic checks. Orchestrator heuristic narrowed. Accept/refuse sequences unchanged. No restart CLI. No product app code changed. No commit made.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` — exit=0 (required)
- ✅ `cd harness && python3 -m pytest tests/test_graph_e2e.py -q` — exit=0 (required)
- ✅ `cd harness && python3 -m compileall -q src` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
- ✅ `python3 -m pytest tests -q` — exit=0 (required)
- ✅ `python3 -m compileall -q src` — exit=0 (required)
- ✅ `PYTHONPATH=src python3 -c "import project_harness"` — exit=0 (required)
<!-- harness-checks:end -->
