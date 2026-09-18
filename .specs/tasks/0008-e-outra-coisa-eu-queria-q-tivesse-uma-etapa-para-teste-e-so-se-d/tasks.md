---
harness:
  commits:
    - "chore(harness): update readme for test-before-review"
    - "feat(harness): instruct agents on check gate and re-review"
    - "chore(harness): document the test-before-review workflow"
    - "feat(harness): tighten re-review skill and commit grouping"
    - "feat(harness): gate review on green checks and split cycle counters"
    - "test(harness): cover skip-review routing re-review and check history"
    - "chore(specs): record harness test-gate plan artifacts"
  tests:
    unit:
      - "Failed required checks skip the review worker and route to repair"
      - "Green required checks still start the review worker"
      - "A check-fail Repair does not increment review_round"
      - "review_round increments only when the Review worker starts"
      - "Prior check-fix Repair counts do not exhaust the review-repair budget"
      - "Exhausted check-fix budget with red required checks notifies the human and does not start Review"
      - "Re-review packet lists current dirty paths and first-review presented paths that did not change"
      - "Review scope union keeps first-review files that are no longer dirty"
      - "Repair packet after a check-only failure does not require a previous review report"
      - "Execute and Repair packets forbid weakening an existing valid test solely to go green"
    integration: []
    e2e: []
  tests_not_applicable:
    integration: "This task changes the Python harness graph, packets, and review/execute contract. It does not add or change a Laravel HTTP flow against MySQL 8."
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

Stop sending red required checks into Review. Split the mixed `repair_round` budget: `check_fix_round` counts red test/build/lint Repair loops; `review_round` increments only when Review starts and is the review-cycle budget. If Repair cannot turn required checks green, notify the human (`check_fail_limit`) and do not start Review. Execute/Repair must fix code, not weaken existing valid tests, to go green. Persist the first-review presented path set and, on later rounds, put current dirty files plus unchanged first-review files in the review packet and skill.

Canonical loop: Execute → required test/build/lint → on red, Repair (code) and re-run checks → all required green → Review → on REJECTED, Repair for findings → checks again → Review, until APPROVED.

## Affected Components

- `harness/src/project_harness/graph_runtime.py` — routing after checks; split counters; check-fail human notify; snapshot/union of presented paths; progress copy.
- `harness/src/project_harness/types.py` — `check_fix_round` and `review_presented_paths` on `HarnessState`.
- `harness/src/project_harness/packets.py` — later-round file lists; never-weaken-tests contract; drop “only blocking ids” as the sole re-review scope; check-only repair without a review report.
- New small helper (preferred: `harness/src/project_harness/review_scope.py`) — union/split of dirty vs carried paths; extract paths from consolidated JSON; pure `route_after_checks` / `route_after_review` if kept next to the helper.
- `harness/agents/execute.md`, `harness/agents/review.md`, `harness/skills/harness-review/SKILL.md` — test-contract rule and re-review corpus rule.
- `harness/docs/WORKFLOW.md`, `harness/README.md`, `harness/agents/harness.md` — canonical loop; `gate=check_fail_limit`.
- `harness/tests/test_graph_e2e.py`, `harness/tests/test_packets.py`, new focused pytest for routing / `review_scope`.

## Tasks

Extract a pure `route_after_checks(*, required_passed, check_fix_round, max_repair_rounds) -> "review" | "repair" | "notify"` and `route_after_review(*, approved, required_passed, review_round, max_repair_rounds) -> "approved" | "repair" | "escalate"` so budgets are unit-tested without LangGraph.

## Execution Plan

### Phase 1: Routing and budgets

```
T1 -> T2
```

### Phase 2: Packets, test contract, and re-review corpus

```
T1 -> T3
T1 -> T4
T3 -> T5
T4 -> T5
```

### Phase 3: Docs

```
T1 -> T6
T2 -> T6
T5 -> T6
```

## Task Breakdown

### T1: Gate Review on green required checks and split cycle counters

**What**: Replace `builder.add_edge("checks", "review_worker")` with conditional edges from `_route_checks`. Add `check_fix_round` on `HarnessState`. Increment `review_round` only in `_review_worker`. Increment `check_fix_round` only when Repair is entered because required checks are red. Stop using mixed `repair_round` in `_route_review`; compare `review_round` to `max_repair_rounds` there. Reset `check_fix_round` to 0 when routing to Review on green. Change the red progress line so it no longer says review still runs.
**Where**: `harness/src/project_harness/graph_runtime.py`
**Depends on**: None
**Requirement**: AC-001, AC-002, AC-004, AC-005, AC-013, AC-014, AC-015, AC-016, AC-021
**Tests**: unit — routing helpers; graph walk — red fixture gate after execute yields repair, `review_round == 0`; green path still reaches review; prior check-fix repairs leave review-repair budget
**Gate**: harness-unit then harness-graph

### T2: Notify the human when check-fix cannot go green

**What**: When required checks stay red and `check_fix_round` is exhausted, interrupt with `gate=check_fail_limit` (failed check ids in the payload). Do not start Review. Do not increment `review_round`. `retry` resets `check_fix_round` and returns to Repair; `stop` goes to `needs_human_attention`. Keep existing `repair_limit` for exhausted **review** cycles only. Document the new gate in orchestrator copy as part of this task only if the handler lives next to `_repair_escalation`; otherwise T6 owns README/WORKFLOW prose.
**Where**: `harness/src/project_harness/graph_runtime.py`
**Depends on**: T1
**Requirement**: AC-003, AC-017
**Tests**: unit — exhausted check-fix returns notify; graph walk with a permanently failing required gate after max check-fix attempts is human `check_fail_limit`, not review
**Gate**: harness-unit then harness-graph

### T3: Repair without a review report; never weaken tests to go green

**What**: Confirm `_repair_worker` / `PacketService.execute(repair=True)` already tolerate absent `latest_review`. Adjust repair copy so a first-cycle red suite is “fix failed checks in code.” Add an explicit test-contract block to execute and repair packets and strengthen `harness/agents/execute.md`: do not edit/delete/skip/weaken an existing valid test solely to pass; if a previously green spec-matching test turns red, fix the code under test; new spec-required tests may be added; tests that encoded superseded behavior may be updated only because the spec changed.
**Where**: `harness/src/project_harness/packets.py`
**Depends on**: T1
**Requirement**: AC-006, AC-018, AC-019, AC-020
**Tests**: unit — repair packet with `latest_review=None` and failed checks; execute and repair packet text contains the never-weaken contract
**Gate**: harness-unit

### T4: Persist first-review presented paths

**What**: Add `review_presented_paths` to `HarnessState`. On review round 1, snapshot `git.changed_paths`. After `consolidated.json` loads, union paths from findings/positives/unverified via a helper. Keep the set growing across later rounds when new dirty files appear.
**Where**: `harness/src/project_harness/review_scope.py`
**Depends on**: T1
**Requirement**: AC-007, AC-008
**Tests**: unit — union keeps a path that left porcelain; JSON path extraction
**Gate**: harness-unit

### T5: Put dirty vs carried paths in later review packets and skill

**What**: Extend `PacketService.review` with current dirty paths and carried first-review paths. Round 1 can list dirty only. Round 2+ must list both. Replace skill/agent/packet text that says later rounds only revalidate previous blocking ids. Keep “no new medium backlog outside the union.”
**Where**: `harness/src/project_harness/packets.py`
**Depends on**: T3, T4
**Requirement**: AC-009, AC-010, AC-011, AC-012
**Tests**: unit — `test_packets.py` round 2 lists a carried unchanged path and still lists current dirty paths
**Gate**: harness-unit

### T6: Document the canonical test-before-review loop

**What**: Update `WORKFLOW.md`, `README.md`, and `harness/agents/harness.md` so they state: Execute → required test/build/lint → Repair on red (not a review cycle) → green → Review → Repair on REJECTED → checks again → Review until APPROVED; stuck red notifies `check_fail_limit`; later reviews re-read first-review presented files plus the current dirty set; workers must not weaken valid tests to go green.
**Where**: `harness/docs/WORKFLOW.md`
**Depends on**: T1, T2, T5
**Requirement**: AC-001, AC-004, AC-009, AC-013, AC-018, AC-021 (human-visible contract)
**Tests**: none
**Gate**: harness-lint

Do not rename the LangGraph node. Do not add `complete-phase --phase test`. Do not add a new `max_repair_rounds` default. Do not mix check-fail counts into `review_round`.

## Planned Tests

Guidelines: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, plus existing `harness/tests/*.py` as the style floor. Harness pytest is isolated Python (no Laravel HTTP, no MySQL, no browser), so it maps to **unit** in the product taxonomy.

### Unit

- Failed required checks skip the review worker and route to repair (`route_after_checks` + LangGraph walk with a failing `python3 -c` gate).
- Green required checks still start the review worker (existing happy-path graph e2e must keep `phase == "review"` after execute).
- A check-fail Repair does not increment `review_round`.
- `review_round` increments only when the Review worker starts.
- Prior check-fix Repair counts do not exhaust the review-repair budget (`route_after_review` still allows repair after `check_fix_round` is high and `review_round` is 1).
- Exhausted check-fix budget with red required checks notifies the human (`check_fail_limit`) and does not start Review.
- Re-review packet lists current dirty paths and first-review presented paths that did not change.
- Review scope union keeps first-review files that are no longer dirty.
- Repair packet after a check-only failure does not require a previous review report.
- Execute and Repair packets forbid weakening an existing valid test solely to go green.

Co-locate: helper tests next to `review_scope` / routing; packet cases in `test_packets.py`; graph walk in `test_graph_e2e.py`.

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

- Required red checks never start Review; Repair or `check_fail_limit` runs instead.
- Required green checks still start Review.
- `review_round` increments only when Review starts; check-fail Repair does not increment it.
- Exhausted check-fix with remaining red checks notifies the human and does not count as a review cycle.
- Execute/Repair packets and execute agent forbid weakening a still-valid test to go green; fix the code under test.
- Later review packets and the review skill inspect current dirty files plus first-review presented files that did not change.
- Docs/agents describe the canonical loop and no longer say review always runs on a red check.
- Punctual unit tests above pass; integration and e2e remain explicitly not applicable.
- No product app code changed. No commit in Execute.

## Test Coverage Matrix

> Generated from `docs/test/*.md`, `harness/stack.yml`, and sampled `harness/tests/*.py`. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Graph routing / review_scope helper | unit | All branches of green/red/notify, review approved/repair/escalate, and path union/split; 1:1 to AC-001–AC-021 routing/budget/corpus/test-contract rules that are machine-checkable | `harness/tests/test_*.py` excluding only what `stack.yml` unit already ignores when testing helpers | `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` |
| LangGraph walk | unit (harness pytest, not product E2E) | Happy path still reaches review; red required gate after execute reaches repair with `review_round == 0`; exhausted check-fix is human notify not review | `harness/tests/test_graph_e2e.py` | `cd harness && python3 -m pytest tests/test_graph_e2e.py -q` |
| Packets | unit | Round 2 lists dirty + carried; repair without latest review; execute/repair text forbids weakening tests | `harness/tests/test_packets.py` | same harness-unit command |
| Laravel HTTP / MySQL | none | N/A | — | — |
| Playwright / Inertia UI | none | N/A | — | — |

## Gate Check Commands

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After helper/packet tasks | `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` |
| Full | After graph routing changes | `cd harness && python3 -m pytest tests -q` |
| Build | After phase completion | `cd harness && python3 -m compileall -q src` and stack verify `test` / `lint` / `build` |

## Phase Execution Map

```
Phase 1 -> Phase 2 -> Phase 3

T1 -> T2
T1 -> T3
T1 -> T4
T3 -> T5
T4 -> T5
T1 -> T6
T2 -> T6
T5 -> T6
```

T2 depends on T1. T3 depends on T1. T4 depends on T1. T5 depends on T3 and T4. T6 depends on T1, T2, and T5.

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1 | Graph edges + split counters | Granular |
| T2 | Check-fail human notify | Granular |
| T3 | Repair packet + never-weaken contract | Cohesive same packet file |
| T4 | State + helper for presented paths | Granular |
| T5 | Packet + skill/agent re-review rule | Cohesive same contract |
| T6 | Workflow/README/orchestrator copy | Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (body) | Diagram | Status |
| ---- | ----------------- | ------- | ------ |
| T1 | None | Phase 1 start | Match |
| T2 | T1 | T1 → T2 | Match |
| T3 | T1 | T1 -> T3 | Match |
| T4 | T1 | T1 -> T4 | Match |
| T5 | T3, T4 | T3 -> T5, T4 -> T5 | Match |
| T6 | T1, T2, T5 | T1 -> T6, T2 -> T6, T5 -> T6 | Match |

## Test Co-location Validation

| Task | Layer | Matrix | Task says | Status |
| ---- | ----- | ------ | --------- | ------ |
| T1 | routing + graph walk | unit | unit | OK |
| T2 | routing + graph walk | unit | unit | OK |
| T3 | packets | unit | unit | OK |
| T4 | review_scope helper | unit | unit | OK |
| T5 | packets + skill contract | unit | unit | OK |
| T6 | docs | none | none | OK |
