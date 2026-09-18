# Specification

## Context

Pedido do usuário (original): *no gate de commit, se o usuario pedir uma alteração que passou o escopo ou mudou o escopo do pedido original, o orquestrador deve sugerir cancelar essa tarefa e começar uma nova. se o usuario aceitar, cancela a tarefa, nao mergeia nada, limpa worktree/branch/action, limpa a janela de contexto (workers frescos, nao encaminhar a conversa anterior) e inicia uma nova task com o pedido atualizado, que replaneja e faz o fluxo inteiro de novo. se o usuario recusar a sugestao, segue o revise-code atual. ajuste local que nao muda contrato continua sendo revise-code.*

Feedback humano da revisão do plano: *pensei em algo aqui, se é adição de escopo n é bom deletar a worktree e sim mergear a tarefa e fazer uma nova, mudou o escopo quase que completo, pedido para remover a tarefa e fazer uma nova, lembrando q é pedido o usuário pode aceitar ou não, ae é problema do usuário se ele não quiser.*

At the commit HITL, the orchestrator today maps every change request to `harness task revise-code`, which routes to Repair on the same worktree and approved spec. `approve-commit` already commits, merges, and cleans up. Cancel exists but only clears the action file; `_canceled` does not remove the worktree or the unmerged task branch, and `GitManager.cleanup` uses `git branch -d`, which refuses unmerged branches. Starting a new task is already `harness task start`.

## Problem

A commit-gate change that exceeds the original request is still treated as Repair. That keeps an approved spec that is no longer true. Treating every out-of-contract change as cancel would discard completed, reviewed work when the user only added scope. Treating every out-of-contract change as Repair would replan nothing. Local fixes that stay inside the contract must keep the cheap revise-code loop.

## Problem Statement

WHEN the user asks for a commit-gate change that adds scope while the original request remains wanted, the orchestrator SHALL suggest merging this task and starting a new one for the extra request; WHEN the user asks for a change that almost completely replaces the original request, the orchestrator SHALL suggest canceling this task without merge and starting a new one; WHEN the user accepts either suggestion, the system SHALL run that path with fresh workers; IF the user refuses, or the change is a local adjustment that does not change the contract, THEN the system SHALL keep current `revise-code`.

## Goal

- At `gate=commit`, classify the change as local, scope addition, or scope replacement, and suggest before calling `revise-code` when the contract would change.
- On explicit accept of **addition**: approve-commit (commit + merge + cleanup), then start a new task with only the additional request, same target branch, fresh workers, full PLAN flow.
- On explicit accept of **replacement**: cancel without merge, discard worktree and unmerged task branch, clear action, start a new task with the replacement request, fresh workers, full PLAN flow.
- On explicit refuse of either suggestion: current `revise-code` (Repair → checks → Review if green). That override is the user's problem.
- Local adjustments that do not change the original contract stay `revise-code` with no suggestion.
- Do not treat silence or ambiguous “ok” as approve-commit, accept-merge, or accept-cancel.

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Who classifies local / addition / replacement | Orchestrator heuristic in `harness/agents/harness.md`, not a Python classifier | User put judgment on the orchestrator; a rules engine cannot read intent | n |
| Addition accept commands | `harness task approve-commit` then `harness task start "<additional request>"` on the same target branch | Original reviewed work must be kept; approve already merges; a `restart` CLI would duplicate start | n |
| Replacement accept commands | `harness task cancel` then `harness task start "<replacement request>"` on the same target branch | User asked to remove the obsolete task and not merge it | n |
| New-task request text | Addition starts with the extra scope only; replacement starts with the rewritten full request | After merge, replanning the original work would duplicate it; after cancel, PLAN must see the new goal | n |
| Cancel cleanup | `_canceled` always discards worktree + unmerged branch (plan and commit gates share the node) | Replacement require cleanup; leftover worktrees are already a leak | n |
| Unmerged branch delete | `git branch -D` only on cancel cleanup; merge-path cleanup keeps `-d` | Cancel never merges, so `-d` would fail | n |
| “Clear the context window” | Fresh PLAN/EXECUTE/REVIEW workers; do not forward old packets, spec, or conversation. Do not wipe the human Cursor chat | Workers are already isolated; the CLI cannot erase the chat UI | n |
| Task json / checkpoint | Keep `.git/harness/tasks/<id>.json` and sqlite checkpoint; only the action file is cleared | Runtime history; user asked to clear action, not audit records | n |
| Uncertain addition vs replacement | If the original request is still wanted → addition; if obsolete → replacement; if still unsure, present both options and wait | Defaulting to cancel would discard valid work, which the revision forbade for additions | n |
| Refuse | Current `revise-code`; no extra graph guard | User said refusal is allowed and is the user's problem | n |
| Plan-gate suggestion protocol | Out of scope for orchestrator copy | User named the commit gate only | n |

**Open questions:** none — all resolved or logged above.

## User Stories

### P1: Suggest merge or cancel from commit-gate scope ⭐ MVP

**User Story**: As the person at the commit gate, I want the orchestrator to warn me when my change adds scope or replaces the original request so that completed work is merged when it is still wanted and discarded only when the original request is obsolete.

**Why P1**: This is the trigger and the revision split the user asked for.

**Acceptance Criteria**:

1. WHEN the current action is `kind=human` and `gate=commit` AND the user asks for extra work while the original request remains wanted, THEN the orchestrator SHALL suggest merging this task and starting a new task for the additional request AND SHALL NOT call `revise-code` until the user answers
2. WHEN that addition suggestion is shown, THEN the orchestrator SHALL state that accept commits and merges the reviewed work, does not cancel, and starts a new task, AND SHALL show the drafted additional request
3. WHEN the current action is `kind=human` and `gate=commit` AND the user asks for a change that almost completely replaces the original request, THEN the orchestrator SHALL suggest canceling this task and starting a new task with the replacement request AND SHALL NOT call `revise-code` until the user answers
4. WHEN that replacement suggestion is shown, THEN the orchestrator SHALL state that accept discards uncommitted work, does not merge, and replans the full flow, AND SHALL show the drafted replacement request
5. IF the commit-gate change is a local adjustment that stays inside the original request and approved ACs (rename, simplify, copy, bugfix of the planned solution), THEN the orchestrator SHALL call `revise-code` and SHALL NOT suggest merge-and-start or cancel-and-start
6. IF the orchestrator is uncertain whether the change is local or a scope change, THEN the orchestrator SHALL suggest rather than silently calling `revise-code`
7. IF the orchestrator is uncertain between addition and replacement, THEN it SHALL treat a still-wanted original request as addition, an obsolete original request as replacement, and SHALL present both options and wait when still unsure AND SHALL NOT default to cancel
8. IF the user is silent or answers with ambiguous assent (“ok”, “segue”) THEN the orchestrator SHALL NOT treat that as approve-commit AND SHALL NOT treat that as accept-cancel

**Independent Test**: Canonical `harness/agents/harness.md` contains the three-way heuristic and the suggest-before-revise-code rule; commit-gate payload copy may hint at the same split.

---

### P1: Accept addition merges then starts a new task ⭐ MVP

**User Story**: As the person who still wants the current work plus extra scope, I want this task merged and a new task started for the addition so that completed reviewed work is not deleted.

**Why P1**: Human revision: addition must merge, not delete the worktree.

**Acceptance Criteria**:

1. WHEN the user explicitly accepts the addition suggestion at the commit gate, THEN the orchestrator SHALL call `harness task approve-commit` AND SHALL NOT call `harness task cancel`
2. WHEN that approve-commit succeeds, THEN the system SHALL commit, merge onto the target branch, and clean up the current worktree/task branch as the existing approve path already does
3. WHEN that merge has completed, THEN the orchestrator SHALL start a new task with `harness task start` using only the additional request and the same target branch
4. WHEN the new task starts, THEN the orchestrator SHALL delegate PLAN to a fresh worker and SHALL NOT forward the previous conversation, packets, spec, or code_feedback into that worker
5. WHEN the new task starts, THEN the system SHALL run the full flow from PLAN and SHALL NOT resume Repair on the merged task
6. IF approve-commit ends in merge conflict / `needs_human_attention`, THEN the orchestrator SHALL NOT start the new task until the current task is integrated or otherwise closed

**Independent Test**: Existing approve path still merges onto the target; agent instructions require `approve-commit` then `start` on addition accept, with no conversation forwarding.

---

### P1: Accept replacement discards the task and starts a new one ⭐ MVP

**User Story**: As the person who no longer wants the original request, I want the old task gone without a merge and a new task started from the replacement request so that PLAN runs on a clean worktree.

**Why P1**: Human revision: almost-complete scope change asks to remove the task and make a new one.

**Acceptance Criteria**:

1. WHEN the user explicitly accepts the replacement suggestion at the commit gate, THEN the system SHALL cancel the current task AND SHALL NOT run `_commit` or `_merge`
2. WHEN that cancel completes, THEN the system SHALL remove the task worktree, SHALL delete the unmerged task branch, and SHALL clear the action file
3. WHEN that cancel completes, THEN the target branch SHALL NOT contain the task’s uncommitted or unmerged files
4. WHEN cancel has completed, THEN the orchestrator SHALL start a new task with `harness task start` using the replacement request and the same target branch
5. WHEN the new task starts, THEN the orchestrator SHALL delegate PLAN to a fresh worker and SHALL NOT forward the previous conversation, packets, spec, or code_feedback into that worker
6. WHEN the new task starts, THEN the system SHALL run the full flow from PLAN and SHALL NOT resume Repair on the canceled worktree

**Independent Test**: LangGraph walk to commit gate then `cancel`: status `canceled`, worktree gone, task branch gone, target HEAD unchanged; agent instructions require `cancel` then `start` after replacement accept.

---

### P1: Refuse keeps revise-code ⭐ MVP

**User Story**: As the person who wants the extra or replaced work on this same task anyway, I want to refuse the suggestion so that current Repair still runs.

**Why P1**: User said refusal follows current revise-code and is the user's problem.

**Acceptance Criteria**:

1. WHEN the user explicitly refuses the addition suggestion or the replacement suggestion, THEN the orchestrator SHALL call `harness task revise-code` with the user’s change text
2. WHEN `revise-code` runs at the commit gate, THEN the graph SHALL route to `repair_worker` with that text as `code_feedback` AND SHALL NOT cancel or start a new task
3. WHILE a local adjustment is in progress via `revise-code`, the system SHALL keep the existing Repair → checks → Review-if-green loop

**Independent Test**: Existing commit-gate `request_changes` path still reaches repair; agent text says refuse → `revise-code`.

## Acceptance Criteria

- **AC-001** WHEN `gate=commit` and the user asks for extra work while the original request remains wanted, THEN the orchestrator SHALL suggest merging this task and starting a new one for the additional request and SHALL NOT call `revise-code` until the user answers
- **AC-002** WHEN that addition suggestion is shown, THEN the orchestrator SHALL warn that accept commits and merges the reviewed work and SHALL present the drafted additional request
- **AC-003** WHEN `gate=commit` and the user asks for a change that almost completely replaces the original request, THEN the orchestrator SHALL suggest canceling the task and starting a new one and SHALL NOT call `revise-code` until the user answers
- **AC-004** WHEN that replacement suggestion is shown, THEN the orchestrator SHALL warn that uncommitted work will be discarded and SHALL NOT merge, and SHALL present the drafted replacement request
- **AC-005** IF the change is a local adjustment that does not change the original contract, THEN the orchestrator SHALL call `revise-code` and SHALL NOT suggest merge-and-start or cancel-and-start
- **AC-006** IF the orchestrator is uncertain whether the change is local or a scope change, THEN it SHALL suggest rather than silently calling `revise-code`
- **AC-007** IF the orchestrator is uncertain between addition and replacement, THEN it SHALL treat a still-wanted original request as addition, an obsolete original as replacement, and SHALL present both options and wait when still unsure AND SHALL NOT default to cancel
- **AC-008** IF the user is silent or answers ambiguously, THEN the orchestrator SHALL NOT approve the commit and SHALL NOT cancel
- **AC-009** WHEN the user explicitly accepts the addition suggestion, THEN the orchestrator SHALL call `approve-commit` and SHALL NOT cancel
- **AC-010** WHEN that approve-commit succeeds, THEN the orchestrator SHALL start a new task with only the additional request on the same target branch
- **AC-011** WHEN the user explicitly accepts the replacement suggestion, THEN the system SHALL cancel the task and SHALL NOT merge
- **AC-012** WHEN that cancel runs, THEN the system SHALL remove the task worktree, delete the unmerged task branch, and clear the action
- **AC-013** WHEN that cancel runs, THEN the target branch SHALL remain without the task’s unmerged files
- **AC-014** WHEN cancel has completed after replacement accept, THEN the orchestrator SHALL start a new task with the replacement request on the same target branch
- **AC-015** WHEN the new task starts after either accept, THEN PLAN SHALL run in a fresh worker without the previous conversation, packets, or spec
- **AC-016** WHEN the new task starts, THEN the system SHALL replan and run the full harness flow and SHALL NOT resume Repair on the previous worktree
- **AC-017** WHEN the user explicitly refuses either suggestion, THEN the orchestrator SHALL call `revise-code` with the change text
- **AC-018** WHEN `revise-code` runs at the commit gate, THEN the graph SHALL route to Repair with `code_feedback` and SHALL NOT cancel
- **AC-019** The cancel node SHALL NOT call `_commit` or `_merge`
- **AC-020** Merge-path cleanup SHALL keep `git branch -d`; cancel cleanup SHALL delete an unmerged task branch (`-D`)
- **AC-021** `harness/agents/harness.md` SHALL contain the commit-gate heuristic (local / addition / replacement), both accept protocols, refuse → `revise-code`, and the fresh-worker rule
- **AC-022** IF addition accept hits a merge conflict, THEN the orchestrator SHALL NOT start the new task while the current task is in `needs_human_attention`

## Edge Cases

- IF the user accepts replacement cancel and `start` fails THEN the old task SHALL stay canceled (no merge, worktree already gone) and the orchestrator SHALL retry `start` without calling `revise-code` on the dead task.
- IF the user accepts addition and `start` fails after a successful merge THEN the old task SHALL stay completed/merged and the orchestrator SHALL retry `start` without calling `revise-code` on the completed task.
- IF the worktree has uncommitted files AND the user accepts replacement THEN cancel SHALL still remove it (`worktree remove --force`) and SHALL NOT stash or cherry-pick them onto the target branch.
- IF the worktree or branch is already missing THEN cancel SHALL still clear the action and SHALL NOT merge.
- IF cancel is chosen at the plan gate THEN the same `_canceled` cleanup SHALL run (shared node); the orchestrator suggestion protocol stays commit-gate-only.
- IF the user writes both a local tweak and extra scope in one message THEN the orchestrator SHALL treat it as addition when the original request is still wanted.
- IF the user writes both a local tweak and a replacement of the original goal THEN the orchestrator SHALL treat it as replacement.
- IF the updated request would only repeat the original request with no added or replaced scope THEN the orchestrator SHALL NOT suggest merge-and-start or cancel-and-start.
- IF `revise-code` is a refused-suggestion with out-of-scope text THEN Repair MAY implement it on the old spec; that is the user’s override, not a graph change.
- IF generated `.cursor/agents/harness.md` exists THEN Execute SHALL run `harness sync` after editing the canonical agent so targets do not drift.

## Out of Scope

| Item | Reason |
| ---- | ------ |
| Python/LLM classifier inside LangGraph | User put judgment on the orchestrator |
| New `harness task restart` CLI or graph option `restart` | `approve-commit`+`start` and `cancel`+`start` are enough |
| Plan-gate “suggest restart” protocol | User named the commit gate |
| Reusing a canceled worktree or cherry-picking canceled diffs | Replacement asked to clean the worktree and replan from scratch |
| Wiping the human Cursor chat transcript | CLI cannot do that; worker isolation is the real requirement |
| Deleting `.git/harness/tasks/<id>.json` or sqlite checkpoints | Action clear is enough |
| Product Laravel / React / Playwright changes | Harness-only |
| Changing Repair routing for local `revise-code` | Keep current loop |
| Auto-merge on replacement cancel | User forbade merge when removing the obsolete task |
| Supporting `cancel` on `repair_limit` / `check_fail_limit` | CLI already rejects those gates |
| Changing the approve-commit commit/merge/cleanup sequence itself | Addition reuses it as-is |

## Considered Approaches

1. **Always cancel+start on any scope change** — Matches the first request, but the revision rejected it for additions because it deletes completed reviewed work. Rejected as the only path.
2. **Always merge+start on any scope change** — Keeps work the user no longer wants when the original request was almost completely replaced. Rejected as the only path.
3. **New graph option / CLI `restart`** — Fewer orchestrator steps, extra surface, and `start` belongs to a new thread/id. Rejected; sequence existing commands.
4. **Orchestrator split: addition → approve-commit+start; replacement → cancel+start; local/refuse → revise-code; cancel cleanup (`-D`)** — Selected. Merge path keeps `-d`. Suggestions remain optional.

## Selected Approach

Keep HITL options at the commit gate as `approve` / `request_changes` / `cancel`. Teach the orchestrator when to suggest merge+start, cancel+start, or `revise-code`. Make `_canceled` actually discard the isolated git state without merging. After addition accept, approve then start. After replacement accept, cancel then start. After refuse, current Repair.

Heuristic for the orchestrator (commit gate only):

- **Local (revise-code):** stays inside the original request and approved ACs; implementation detail, rename, simplify, copy, or bugfix of the planned solution.
- **Addition (suggest merge+start):** original goal still wanted; extra feature/module/screen/RF, “also do X”, or any additive work that would make the current spec incomplete but not false as a finished slice.
- **Replacement (suggest cancel+start):** original goal is obsolete or almost completely replaced; the current spec/tasks would no longer be the request.
- **Uncertain local vs scope:** suggest, do not silently `revise-code`.
- **Uncertain addition vs replacement:** still-wanted original → addition; obsolete original → replacement; still unsure → present both, wait, do not default to cancel.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| SCOPE-01 | P1: Suggest merge or cancel | Tasks | Pending |
| SCOPE-02 | P1: Suggest merge or cancel | Tasks | Pending |
| SCOPE-03 | P1: Suggest merge or cancel | Tasks | Pending |
| SCOPE-04 | P1: Suggest merge or cancel | Tasks | Pending |
| SCOPE-05 | P1: Suggest merge or cancel | Tasks | Pending |
| SCOPE-06 | P1: Suggest merge or cancel | Tasks | Pending |
| SCOPE-07 | P1: Suggest merge or cancel | Tasks | Pending |
| SCOPE-08 | P1: Suggest merge or cancel | Tasks | Pending |
| SCOPE-09 | P1: Accept addition | Tasks | Pending |
| SCOPE-10 | P1: Accept addition | Tasks | Pending |
| SCOPE-11 | P1: Accept replacement | Tasks | Pending |
| SCOPE-12 | P1: Accept replacement | Tasks | Pending |
| SCOPE-13 | P1: Accept replacement | Tasks | Pending |
| SCOPE-14 | P1: Accept replacement | Tasks | Pending |
| SCOPE-15 | P1: Accept addition | Tasks | Pending |
| SCOPE-16 | P1: Accept addition | Tasks | Pending |
| SCOPE-17 | P1: Refuse keeps revise-code | Tasks | Pending |
| SCOPE-18 | P1: Refuse keeps revise-code | Tasks | Pending |
| SCOPE-19 | P1: Accept replacement | Tasks | Pending |
| SCOPE-20 | P1: Accept replacement | Tasks | Pending |
| SCOPE-21 | P1: Suggest merge or cancel | Tasks | Pending |
| SCOPE-22 | P1: Accept addition | Tasks | Pending |

**ID format:** `SCOPE-NN` maps to AC-0NN.

**Coverage:** 22 total, 22 mapped to tasks, 0 unmapped.
