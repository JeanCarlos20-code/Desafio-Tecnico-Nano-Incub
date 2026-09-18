# Task Context

## Relevant Documentation

- `harness/docs/WORKFLOW.md` — current graph: Execute → Checks → Review always, even when required checks are red. Repair then repeats Checks → Review. Human copy still says review runs with a red overlay.
- `harness/README.md` — “Review sempre acontece depois de Execute/Repair, inclusive quando há check vermelho.”
- `harness/docs/CONTEXT.md` — Review starts from spec/tasks/diff/checks; Repair receives blockers plus red checks, not the other phases’ chat.
- `harness/agents/harness.md` — `gate=repair_limit` is the only post-loop human interrupt (retry/stop). There is no distinct “checks stayed red” notify gate.
- `harness/skills/harness-review/SKILL.md` rule 7 and `harness/agents/review.md` item 7 — later rounds only revalidate previous blocking ids plus new blocker/high from the repair. Unchanged files from round 1 are skipped even if they were in the first corpus.
- `harness/agents/execute.md` item 8 and execute packet rule 2 already say “do not weaken valid tests,” but they do not state that a previously green contract test that turns red must be fixed in product code, nor that a stuck red suite notifies the human instead of Review.
- `docs/test/unit.md` “Existing tests are contracts” — do not remove scenarios, reduce assertions, or change expected results to match incorrect code. Same idea in integration.md / e2e.md. This task does not change Laravel HTTP, MySQL, or a browser flow. Punctual tests are isolated Python (pytest) of graph routing, packets, and the review-path union.
- Product `docs/architecture.md` / `docs/tree.md` / ADRs / screens — not in scope. No `app/Modules` change.

## Relevant Components

- `harness` (Python, pytest). Stack command IDs: `test`, `unit` (pytest ignoring `tests/test_graph_e2e.py`), `lint`, `build`.
- `app` — not affected.

## Relevant Code

- `harness/src/project_harness/graph_runtime.py`
  - `builder.add_edge("checks", "review_worker")` is unconditional.
  - `_checks` progress text says review still runs on red; it sets `phase` to review even when required checks failed.
  - `_review_worker` increments `review_round` only when Review actually starts (already correct).
  - `_repair_worker` always does `repair_round + 1` for every Repair, including check-fail loops that never reached Review.
  - `_route_review` (after Review) uses `repair_round < max_repair_rounds`. That mixed counter is the hole: three check-fail repairs can exhaust the review-repair budget without `review_round` ever increasing.
  - `_repair_escalation` is one human gate for “N repairs without going green”; it does not distinguish stuck red checks from too many rejected reviews.
- `harness/src/project_harness/types.py` — `HarnessState` has `repair_round` / `review_round` / `blocking_ids` / `check_results`; no `check_fix_round`; no persisted first-review file corpus.
- `harness/src/project_harness/config.py` + `harness/config.yaml` — single `workflow.max_repair_rounds` (default 3). No second config key today.
- `harness/src/project_harness/packets.py` — execute/repair already says do not weaken/remove valid tests; it does not forbid editing a previously green contract test to go green, and repair still talks as if a latest review always exists. Review packet tells later rounds to revalidate blocking ids only.
- `harness/src/project_harness/checks.py` — `required_passed` ignores optional gates; `_checks` already runs `plan.gates` plus stack verify (`test`, `lint`, `build` on affected components).
- `harness/src/project_harness/git_manager.py` — `changed_paths` is porcelain vs HEAD (Execute does not commit).
- `harness/src/project_harness/review_service.py` — findings/positives carry paths; usable to widen the first-review presented set after round 1 JSON exists.
- `harness/tests/test_graph_e2e.py` — happy path: execute success → action `phase == "review"` because the fixture gate is green. No red-gate walk that asserts repair without incrementing `review_round`.
- `harness/cli.py` — `complete-phase` stays `plan|execute|repair|review`. No new LLM “test” phase.

## Existing Constraints

- LangGraph node id `checks` must stay (checkpoint compatibility). The test stage is this node as a hard gate, not a new agent.
- Reviews stay append-only (`review/review-NN.md`). Repair still uses only the latest human report when one exists.
- Execute/Repair still must not commit. Deterministic gates run from the worktree root via `bash -lc`.
- Re-review must not dump the whole repository. Corpus = current dirty paths ∪ first-review presented paths.
- Product integration/e2e docs do not apply to this harness-only change.
- `docs/test/unit.md` allows updating tests only when the approved spec intentionally changes behavior. Tests that encoded the old “review-on-red” graph may be updated as part of this spec; previously green contract tests that still match the spec must not be weakened to go green.

## Important Decisions

- Treat existing `CheckRunner` (plan gates + stack verify) as the test stage. Do not add an LLM test worker.
- “All tests passed” means every **required** check is green (`required_passed`). Optional gates may fail without skipping review.
- Canonical loop: Execute → required test/build/lint → on red, Repair (product/harness code, not weaken tests) and re-run checks → all required green → Review → on REJECTED, Repair for findings → checks again → Review, until APPROVED. Review never starts on a red required check.
- Split counters. `review_round` increments only when the Review worker starts. A red required check that routes to Repair must not increment `review_round`. Add `check_fix_round`, incremented only when Repair is entered because required checks are red. Stop using `repair_round` as the mixed budget in `_route_review`.
- Review-repair budget: `_route_review` compares `review_round` to `max_repair_rounds` (same default 3, independent of `check_fix_round`). Prior check-fail loops must not consume this budget.
- Check-fix budget: `_route_checks` compares `check_fix_round` to the same `max_repair_rounds`. Reset `check_fix_round` to 0 when required checks pass and Review starts, so a later review-reject that turns checks red gets a fresh check-fix budget.
- Keep `repair_round` only as a progress/total Repair-invocation counter if useful for `repair-N` progress ids; it must not decide either budget. Prefer incrementing it only on review-originated Repair so it is no longer mixed.
- Stuck red checks: if `check_fix_round` is exhausted and required checks are still red, human interrupt `gate=check_fail_limit` (notify the user). Do not start Review. Do not increment `review_round`. Do not reuse the “review cycle” story. Options: `retry` (reset `check_fix_round`, more Repair) and `stop` (`needs_human_attention`), matching existing HITL shape so the task is not a dead end.
- Never weaken tests to go green: Execute/Repair packets, `agents/execute.md`, and the execute skill path must state: do not edit/delete/weaken an existing valid test solely to make the suite pass. If a previously green test that still matches the approved spec turns red after a code change, fix the product (or harness) code. New tests required by the approved spec may still be added. Tests that encoded superseded behavior may be updated only because the spec changed, not to hide a regression.
- Persist `review_presented_paths` on first review: porcelain at review start, then union paths cited in that round’s `consolidated.json` (findings, positives, unverified). Later packets list current dirty vs carried (presented but not dirty now).
- Later review rounds inspect that union for regressions (new blocker/high), including unchanged first-review files. They still must not hunt a new medium backlog on files outside the corpus.
- Repair entered only because checks failed and no review exists: packet already allows absent `latest_review`; Execute must fix the red gates in code, not invent review findings and not weaken tests.
