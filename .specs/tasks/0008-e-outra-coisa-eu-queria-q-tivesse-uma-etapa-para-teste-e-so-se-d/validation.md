# Validation

## Acceptance Criteria

| ID | Outcome | Evidence |
| -- | ------- | -------- |
| AC-001 | Required red checks do not start Review | `harness/tests/test_review_scope.py` `route_after_checks(...) == "repair"`; `harness/tests/test_graph_e2e.py:317` `assert action.get("phase") == "repair"` |
| AC-002 | Red checks with remaining check-fix budget route to Repair | `harness/tests/test_review_scope.py`; graph walk after execute with failing gate |
| AC-003 | Exhausted check-fix budget notifies `check_fail_limit` and does not start Review | `harness/tests/test_review_scope.py` `== "notify"`; `harness/tests/test_graph_e2e.py` `assert action.get("gate") == "check_fail_limit"` |
| AC-004 | Green required checks start Review | `harness/tests/test_review_scope.py` `== "review"`; existing graph happy path `action.get("phase") == "review"` |
| AC-005 | Red checks do not write `review/review-NN.md` | `harness/tests/test_graph_e2e.py:321` `assert not (task_dir / "review" / "review-01.md").exists()` |
| AC-006 | Check-only Repair packet does not require a previous review | `harness/tests/test_packets.py` `"última review: (ausente)"` and `"Não há review anterior"` |
| AC-007 / AC-008 | Round 1 snapshots porcelain then unions JSON paths | `harness/src/project_harness/graph_runtime.py` `_review_worker`; `harness/tests/test_review_scope.py` `paths_from_consolidated` |
| AC-009 | Later review packet lists dirty and carried paths | `harness/tests/test_packets.py` `` `src/new.py` `` and `` `src/old.py` `` in carried section |
| AC-010 / AC-011 / AC-012 | Later rounds re-inspect the union, not the whole repo, no new medium backlog outside it | `harness/tests/test_packets.py:306-310`; `harness/skills/harness-review/SKILL.md` rule 7 |
| AC-013 | Check-fail Repair does not increment `review_round` | `harness/tests/test_graph_e2e.py:320` `assert values.get("review_round") == 0` |
| AC-014 | `review_round` increments when Review runs | `harness/tests/test_graph_e2e.py:294` `assert values.get("review_round") == 1` after review completes |
| AC-015 | Review-repair budget uses `review_round`, not check-fix counts | `harness/tests/test_review_scope.py` `review_round=1` still `"repair"` |
| AC-016 | `check_fix_round` resets to 0 when required checks pass / Review starts | `_checks` sets `check_fix_round = 0` when green; `_review_worker` also writes `0` |
| AC-017 | `check_fail_limit` does not increment `review_round` | `harness/tests/test_graph_e2e.py:354` |
| AC-018 / AC-019 / AC-020 | Execute/Repair packets and execute agent forbid weakening valid tests; fix code; new spec tests allowed | `harness/tests/test_packets.py` never-weaken asserts |
| AC-021 | Canonical loop: REJECTED review → Repair → checks → Review only if green | `route_after_review` + conditional `checks` edges; WORKFLOW.md |
| Human FB-1 | Later rounds revalidate every previous finding including mediums, re-run four tracks on the current diff, and still revalidate last REJECTED blocking ids | `harness/tests/test_packets.py:306-310` and `:412-427`; packet `LATER_ROUND_REVIEW_RULE`; `harness/skills/harness-review/SKILL.md` rule 7; `harness/agents/review.md` item 7 |
| Human FB-2 | `review/review-NN.md` keeps Summary/Blockers/High/Medium/Positives/Verdict/Harness gate and does not embed the checks log; history is `tests/checks-NN.md` | `harness/tests/test_review.py` `assert "**Deterministic checks**" not in text`; `harness/tests/test_artifacts.py` `test_write_checks_appends_history_matching_review_numbering`; `harness/tests/test_graph_e2e.py:276-289`; Execute/Repair packets `REVIEW_REPORT_CONTRACT` |

## Test Results

Harness unit (pytest, ignore graph e2e): **93 passed**.

Harness graph walks (`tests/test_graph_e2e.py`): **3 passed**. Happy path now asserts `review-01.md` has no `**Deterministic checks**` and writes `tests/checks-01.md`.

No product Laravel/React test was edited, deleted, skipped, or weakened. Harness tests that previously required `**Deterministic checks**` inside `review-NN.md` were updated to the new contract (checks history is a sibling file), not weakened.

Integration: not applicable (no Laravel HTTP + MySQL 8 flow added in this task).

E2E: not applicable (no browser + React + Laravel + MySQL workflow). LangGraph walks remain harness pytest.

## Required Gates

Local pre-check during this Repair (harness re-runs the official gate at complete-phase):

| Gate | Command | Local result |
| ---- | ------- | ------------ |
| harness-unit | `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` | 93 passed |
| harness-graph | `cd harness && python3 -m pytest tests/test_graph_e2e.py -q` | 3 passed |
| harness-lint | `cd harness && python3 -m compileall -q src` | exit 0 |

Do not treat these as the final harness gate.

## Review Result

Round 2 verdict: **APPROVED**. Blocking IDs: none. This Repair implements human commit-gate feedback, not SMELL-001.

SMELL-001 (medium, `graph_runtime.py` checks progress `event_id`) was **not** implemented.

## Extra files (beyond tasks.md Where)

This Repair extras (real dependencies of the human feedback):

- `harness/src/project_harness/review_service.py` — `render_markdown` no longer embeds the checks log; `render_checks_markdown` writes per-round history.
- `harness/src/project_harness/artifacts.py` — `REVIEW_TEMPLATE` drops Deterministic checks; `write_checks` / `list_checks` persist `tests/checks-NN.md`.
- `harness/src/project_harness/graph_runtime.py` — writes checks history beside the human review file.
- `harness/src/project_harness/packets.py` — later-round rule plus Execute/Repair contract that the worker reads `review/review-NN.md` without the checks dump.
- `harness/agents/review.md`, `harness/skills/harness-review/SKILL.md`, `harness/agents/execute.md` — stronger re-review and checks-file contract.
- `harness/README.md`, `harness/docs/WORKFLOW.md` — human-visible review markdown no longer lists Deterministic checks inside `review-NN.md`.
- `harness/skills/conventional-commits/scripts/group_commits.py` — `.specs/` paths stay `meta(specs)` even when they contain `/tests/`, so `tests/checks-NN.md` does not demand `test(specs)`.
- `harness/tests/test_review.py`, `test_artifacts.py`, `test_packets.py`, `test_graph_e2e.py`, `test_commits.py` — new contract assertions.

No product Laravel/React source changed. SMELL-001 not touched. LangGraph node id `checks` was not renamed.

## Final Status

Human commit-gate feedback implemented: later-round review revalidates all prior findings (including mediums) and re-runs four tracks on the current diff; deterministic check logs live in `.specs/tasks/<task>/tests/checks-NN.md`, not in `review/review-NN.md`. Ready for harness complete-phase. Not committed.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` — exit=0 (required)
- ✅ `cd harness && python3 -m pytest tests/test_graph_e2e.py -q` — exit=0 (required)
- ✅ `cd harness && python3 -m compileall -q src` — exit=0 (required)
- ✅ `python3 -m pytest tests -q` — exit=0 (required)
- ✅ `python3 -m compileall -q src` — exit=0 (required)
- ✅ `PYTHONPATH=src python3 -c "import project_harness"` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
<!-- harness-checks:end -->
