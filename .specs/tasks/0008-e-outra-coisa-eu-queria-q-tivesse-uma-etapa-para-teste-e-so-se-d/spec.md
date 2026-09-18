# Specification

## Context

Pedido do usuário (original): *e outra coisa, eu queria q tivesse uma etapa para teste, e só se dps q todos os testes passarem ele vai para review para ver se tem coisa errada. E eu vi um possivel furo no fluxo, sempre q for fazer uma review dnv ele deve ver dnv tudo q foi mudado, n necessariamente precisa ser tudo, mas ele deve ver os codigo que foi mudado e tbm os q n foi mudado (q foi apresentado pela primeira review) e esse faça a review dnv para ver se não quebrou nada. se o teste tiver falha, nem ve a review, arruma o teste*

Feedback humano da revisão do plano: *se o teste fica vermelho e volta, n conta o ciclo de review, o ciclo só conta quando for para review, no caso dos testes se a ia não conseguir fazer o teste vermelho ficar verde avisa o usuário apenas, e coloca uma regra importante, a ia não pode mudar o teste só pra ficar verde, ela deve mudar o codigo pois pode ter caso de um teste passando a eras, e fez alguma alteração e esse teste q valida uma regra importante fica vermelho, a ia n pode intervir nesse caso no teste, deve consertar o codigo. Então é sempre assim, dps da execução, testa, build, lint, achou erro conserta, tudo verde passa pra review, review acha erro volta execute, execute conserta roda testes e vai pra review até tudo estar aprovado*

The Project Harness already runs deterministic gates after Execute/Repair (`plan.gates` plus stack verify). Today those checks never block the Review worker: `checks` always edges to `review_worker`. `review_round` already increments only inside `_review_worker`, but `_repair_worker` increments `repair_round` on every Repair, and `_route_review` uses that mixed counter as the review-repair budget. A second hole lives in the review skill: round 2+ is told to revalidate previous blocking ids plus new blocker/high from the repair, so files presented in round 1 that the repair did not touch are not re-inspected for breakage.

## Problem

Review can start on a worktree whose required tests/gates are already red, wasting a review round and mixing “fix the suite” with “judge the design.” Check-fail Repair currently consumes the same `repair_round` budget as review-repair, so a red suite can exhaust “review cycles” without Review ever starting. After a repair, re-review is too narrow: it can miss regressions in files the first review already presented. Execute/Repair is not forbidden strongly enough from editing a previously green contract test just to go green.

## Problem Statement

The harness SHALL treat required checks as a hard test stage before Review, count a review cycle only when Review starts, notify a human when Repair cannot turn required checks green, forbid weakening existing valid tests to pass, and on later reviews re-inspect current dirty files union first-review presented files.

## Goal

- Make the existing checks node a hard **test stage**: Review starts only when every required check passed.
- If any required check failed, skip Review and send the task to Repair (code, not weaken tests) while check-fix budget remains.
- If Repair cannot turn required checks green, notify the user only; do not start Review; do not increment `review_round`.
- Increment `review_round` (and consume review-repair budget) only when Review actually starts.
- Canonical loop: Execute → test/build/lint → Repair on red → green → Review → Repair on REJECTED → checks again → Review until APPROVED.
- On every later review round, present the union of current dirty paths and paths presented in the first review.

## User Stories

### P1: Tests gate Review ⭐ MVP

**User Story**: As the person running a harness task, I want Review to start only after required tests/gates are green so that a red suite is fixed instead of reviewed.

**Why P1**: The user asked for a test stage and said that if tests fail, do not look at review — fix the failure in code.

**Acceptance Criteria**:

1. WHEN required checks finish after Execute or Repair, IF any required check failed, THEN the system SHALL NOT start the Review worker
2. WHEN required checks finish after Execute or Repair, IF any required check failed AND `check_fix_round` is below `max_repair_rounds`, THEN the system SHALL route to the Repair worker with those failed checks
3. WHEN every required check passed after Execute or Repair, THEN the system SHALL start the Review worker
4. WHILE required checks are red, the system SHALL treat Repair or human check-fail notify as the next work and SHALL NOT write a new `review/review-NN.md` for that cycle

**Independent Test**: Isolated routing helper plus a LangGraph walk whose fixture gate exits non-zero after Execute; next action is repair, not review; `review_round` stays 0.

---

### P1: Review cycle vs check-fail loop ⭐ MVP

**User Story**: As the person running a harness task, I want a red test/build/lint loop not to count as a review cycle so that review budget is spent only when Review actually runs.

**Why P1**: Human feedback: the cycle counts only when going to review.

**Acceptance Criteria**:

1. WHEN Repair is entered because a required check is red, THEN the system SHALL NOT increment `review_round`
2. WHEN the Review worker starts, THEN the system SHALL increment `review_round` by 1
3. WHEN `_route_review` decides whether another review-repair is allowed after a REJECTED review, THEN the system SHALL compare `review_round` to `max_repair_rounds` and SHALL NOT use `check_fix_round` or a mixed check-fail count as that budget
4. WHEN required checks pass and Review starts, THEN the system SHALL reset `check_fix_round` to 0

**Independent Test**: Unit tests on `route_after_checks` / `route_after_review`; a red-check walk that performs Repair leaves `review_round == 0`; a later green-then-REJECTED path still has review-repair budget left after prior check-fix repairs.

---

### P1: Stuck red checks notify the user ⭐ MVP

**User Story**: As the person running a harness task, I want to be told when Repair cannot turn required checks green so that the flow does not fake a review cycle or start Review on a red suite.

**Why P1**: Human feedback: if the IA cannot make the red test green, notify the user only.

**Acceptance Criteria**:

1. WHEN required checks are still red AND `check_fix_round` is not below `max_repair_rounds`, THEN the system SHALL interrupt the human with `gate=check_fail_limit` and SHALL NOT start the Review worker
2. WHEN that check-fail notify runs, THEN the system SHALL NOT increment `review_round` and SHALL NOT write a new `review/review-NN.md`
3. WHEN the human chooses `stop` on `check_fail_limit`, THEN the system SHALL end in `needs_human_attention`
4. WHEN the human chooses `retry` on `check_fail_limit`, THEN the system SHALL reset `check_fix_round` and SHALL route to Repair without starting Review

**Independent Test**: Routing helper returns escalate-to-human for exhausted check-fix; graph walk with a permanently failing gate after max check-fix attempts yields a human `check_fail_limit` action, not `phase == "review"`.

---

### P1: Never weaken tests to go green ⭐ MVP

**User Story**: As the person running a harness task, I want Execute/Repair to fix product code when a previously green contract test turns red so that an important rule cannot be deleted just to pass checks.

**Why P1**: Human feedback: the IA must not change the test only to go green; it must fix the code.

**Acceptance Criteria**:

1. The Execute packet, the Repair packet, and `harness/agents/execute.md` SHALL instruct the worker not to edit, delete, skip, or weaken an existing valid test solely to make required checks pass
2. IF a previously green test that still matches the approved spec turns red after a code change, THEN the worker SHALL be instructed to fix the product or harness code under test, not that test
3. WHEN the approved spec requires new tests, THEN the worker SHALL still be allowed to add those tests
4. WHEN the approved spec intentionally changes behavior that old tests encoded, THEN the worker SHALL be allowed to update those tests to match the spec and SHALL NOT be allowed to weaken unrelated still-valid tests to hide a regression

**Independent Test**: Packet unit tests assert the contract wording is present in execute.md and repair.md packets.

---

### P1: Re-review the first-review corpus plus current dirty files ⭐ MVP

**User Story**: As the person running a harness task, I want every later review to re-inspect files that changed now and files the first review already presented so that a repair cannot hide breakage in untouched files.

**Why P1**: The user called out this hole and asked to re-review changed code plus unchanged code from the first review, not the entire repo.

**Acceptance Criteria**:

1. WHEN Review round 1 starts, THEN the system SHALL snapshot the presented path set from worktree porcelain at that moment
2. WHEN Review round 1 finishes with a `consolidated.json`, THEN the system SHALL union that snapshot with paths cited in that JSON’s findings, positives, and unverified entries
3. WHEN Review round number is greater than 1, THEN the review packet SHALL list current dirty paths and SHALL list first-review presented paths that are not in the current dirty set
4. WHEN Review round number is greater than 1, THEN the Review worker SHALL re-inspect that union for regressions (new blocker/high), including unchanged files from the first-review presented set
5. The later-round review SHALL NOT require inspecting the rest of the repository outside that union
6. WHILE a later review round runs, the worker SHALL still revalidate previous blocking ids and SHALL NOT open a new medium backlog on files outside the union

**Independent Test**: Packet unit tests with a fake presented set; helper unit tests for the union; graph/packet assertions that round 2 lists a carried path that is no longer dirty.

## Acceptance Criteria

- **AC-001** WHEN required checks finish after Execute or Repair, IF any required check failed, THEN the system SHALL NOT start the Review worker
- **AC-002** WHEN required checks are red and `check_fix_round` is below `max_repair_rounds`, THEN the system SHALL route to Repair with the failed required checks
- **AC-003** WHEN required checks are red and `check_fix_round` is not below `max_repair_rounds`, THEN the system SHALL notify the human with `gate=check_fail_limit` and SHALL NOT start Review
- **AC-004** WHEN every required check passed after Execute or Repair, THEN the system SHALL start the Review worker
- **AC-005** WHILE required checks are red, the system SHALL NOT write a new human `review/review-NN.md` for that cycle
- **AC-006** WHEN Repair starts because checks failed and no review report exists yet, THEN the Repair packet SHALL include the failed checks and SHALL NOT require a previous review report
- **AC-007** WHEN Review round 1 starts, THEN the system SHALL snapshot presented paths from worktree porcelain
- **AC-008** WHEN Review round 1 produces `consolidated.json`, THEN the system SHALL add paths cited in findings, positives, and unverified to the presented set
- **AC-009** WHEN Review round number is greater than 1, THEN the review packet SHALL list current dirty paths and first-review presented paths that are not currently dirty
- **AC-010** WHEN Review round number is greater than 1, THEN the Review worker SHALL re-inspect the union of those lists for new blocker/high (breakage), not only previous blocking ids
- **AC-011** The later-round review SHALL NOT require a whole-repository scan
- **AC-012** WHILE a later review round runs, the worker SHALL NOT open a new medium backlog on files outside the union
- **AC-013** WHEN Repair is entered because a required check is red, THEN the system SHALL NOT increment `review_round`
- **AC-014** WHEN the Review worker starts, THEN the system SHALL increment `review_round` by 1
- **AC-015** WHEN `_route_review` decides whether another review-repair is allowed, THEN the system SHALL use `review_round` versus `max_repair_rounds` and SHALL NOT treat prior check-fail Repair counts as that budget
- **AC-016** WHEN required checks pass and Review starts, THEN the system SHALL reset `check_fix_round` to 0
- **AC-017** WHEN `check_fail_limit` is shown, THEN the system SHALL NOT increment `review_round`
- **AC-018** The Execute packet, the Repair packet, and `harness/agents/execute.md` SHALL instruct the worker not to edit, delete, skip, or weaken an existing valid test solely to make required checks pass
- **AC-019** IF a previously green test that still matches the approved spec turns red after a code change, THEN those instructions SHALL require fixing the code under test, not that test
- **AC-020** WHEN the approved spec requires new tests, THEN those instructions SHALL still allow adding them
- **AC-021** WHEN Review REJECTS after required checks were green, THEN the system SHALL route to Repair for findings, THEN the system SHALL run required checks again, and SHALL start Review again only when required checks are green

## Edge Cases

- IF only an optional (`required: false`) gate fails THEN the system SHALL still start Review (same `required_passed` rule as today).
- IF Execute’s first checks fail THEN Repair SHALL run with empty blocking ids and no latest review file, and `review_round` SHALL stay 0.
- IF a previous review exists and later checks fail THEN the system SHALL skip a new review round, SHALL NOT increment `review_round`, and SHALL still pass the latest review plus failed checks to Repair.
- IF a file was in the first-review presented set and a repair reverts it to HEAD (it leaves porcelain) THEN later review packets SHALL still list it as carried.
- IF current dirty is empty on a later round but the presented set is not empty THEN the packet SHALL still list the carried paths.
- IF Review round 1 never ran (checks never went green) THEN there is no presented set to carry; the first successful review is round 1.
- IF `repair_round` still exists on state for progress ids THEN it SHALL NOT be the review-cycle budget.
- IF `check_fail_limit` retry is chosen THEN Review SHALL still not start until required checks are green.
- IF a harness test encoded the old review-on-red behavior THEN Execute MAY update that test because this spec changed the graph, and SHALL NOT weaken unrelated contract tests to hide a regression.

## Out of Scope

| Item | Reason |
| ---- | ------ |
| New LLM “test” worker or `complete-phase --phase test` | Checks are already deterministic; a second agent would duplicate `CheckRunner` |
| Renaming the LangGraph node `checks` | Would break existing checkpoints |
| Changing the numeric default of `max_repair_rounds` | Split counters; keep the same default 3 independently for each budget |
| New `config.yaml` key unless Execute finds `max_repair_rounds` cannot be reused independently | Avoid config churn; both loops share the number, not the counter |
| Whole-repository re-review | User said the corpus is not everything |
| Product Laravel/React/Playwright tests | This task only changes the harness graph and review/execute contract |
| Opening a new medium backlog on later rounds | Existing medium policy stays; later rounds hunt breakage (blocker/high) on the union |
| Human plan/commit gate UX redesign | Copy may mention that checks must be green before review; commit list still hidden at plan gate |
| Auto-continuing stuck red checks into Review | Human asked to notify only |

## Considered Approaches

1. **Keep review-on-red, overlay the harness gate (current)** — Review still runs; markdown stays APPROVED while the gate is blocked. Rejected: wastes a review and ignores “nem ve a review.”
2. **Add an LLM test worker** — Extra phase and packet. Rejected: tests are already shell commands; the gap is routing, not another model.
3. **Hard-gate `checks`; keep one `repair_round` for both loops (previous plan draft)** — Rejected by human feedback: a red-test return must not count as a review cycle.
4. **Hard-gate `checks`; split `check_fix_round` vs `review_round`; persist first-review presented paths; later packets list dirty ∪ carried; never-weaken-tests in packets/agents** — Selected.
5. **Re-review only current porcelain** — Rejected: that is the hole when a file leaves porcelain or the skill forbids looking past blocking ids.
6. **Re-review the entire worktree every round** — Rejected: user said it does not need to be everything.
7. **Notify-only with no retry on stuck red** — Considered; selected retry/stop on `check_fail_limit` so the user is notified and can still unblock, without starting Review.

## Selected Approach

Use `CheckRunner` as the test stage. Replace `checks → review_worker` with a conditional edge:

- green required checks → `review_worker` (`review_round += 1`, `check_fix_round = 0`)
- red + `check_fix_round < max_repair_rounds` → `repair_worker` (increment `check_fix_round` only; do not increment `review_round`)
- red + check-fix exhausted → human `check_fail_limit` (notify; not a review cycle)

After Review: APPROVED + green → commit gate. REJECTED + `review_round < max_repair_rounds` → Repair for findings, then checks, then Review only if green. REJECTED at the review budget → existing `repair_limit` HITL.

Keep node id `checks`. Progress and docs must describe the canonical loop and must not say review still runs on red.

Persist `review_presented_paths` on `HarnessState`. Round 1 snapshots porcelain, then unions paths from that round’s consolidated JSON. Round 2+ packets list current dirty vs carried paths. Update `PacketService.review`, `harness-review` rule 7, `agents/review.md`, `WORKFLOW.md`, `README.md`, execute/repair packets, and `agents/execute.md`.

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| “All tests” | Every required check (`plan.gates` + stack verify ids `test`, `lint`, `build`), not only pytest | That is already the post-Execute command set; lint/build red is the same waste as a red test | y (prior plan; unchanged) |
| First-review “presented” files | Porcelain at round 1 start ∪ paths cited in round 1 JSON | Diff is what the packet already shows; JSON paths are what the reviewer actually judged | y (prior plan; unchanged) |
| Later-round mediums | No new medium backlog outside the union; blocker/high on the union still fail | Matches current severity gate while closing the regression hole | y (prior plan; unchanged) |
| Check-fix vs review budgets | Independent counters; both use `max_repair_rounds` (default 3); no new config key | Human asked to split cycles, not to change the numeric cap | n (default for this revision) |
| `repair_round` after the split | Must not decide either budget; increment it only on review-originated Repair if kept for progress ids | Today it mixes both loops; leaving it as mixed budget would ignore the feedback | n (default for this revision) |
| `check_fix_round` reset | Reset to 0 when Review starts on green checks | Each attempt to enter Review gets a fresh check-fix budget after a later REJECTED repair turns checks red | n (default for this revision) |
| Stuck-red human options | `gate=check_fail_limit` with `retry` and `stop`, distinct from `repair_limit` | “Notify only” means do not start Review; retry/stop matches existing HITL so the task is not a dead end | n (default for this revision) |
| Never-weaken vs spec-driven test updates | May add spec-required tests and update tests that encoded superseded behavior; must not weaken a still-valid contract test to go green | Matches `docs/test/unit.md` “Existing tests are contracts” and the human example of a previously green rule test | n (default for this revision) |

**Open questions:** none - all resolved or logged above.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| GATE-01 | P1: Tests gate Review | Tasks | Pending |
| GATE-02 | P1: Review cycle vs check-fail loop | Tasks | Pending |
| GATE-03 | P1: Stuck red checks notify the user | Tasks | Pending |
| TEST-01 | P1: Never weaken tests to go green | Tasks | Pending |
| LOOP-01 | P1: Tests gate Review / canonical loop | Tasks | Pending |
| CORP-01 | P1: Re-review the first-review corpus | Tasks | Pending |
