# Workflow

```text
START
  (no `task start` while a `kind=human` action is open: classify on that gate;
   wait on addition/replacement; loose report does not open a task)
  ↓
PLAN worker (fresh context)
  ↓
validate context/spec/tasks
  ↓
HITL #1: plan approval
  (orchestrator pastes the CLI summary in full and asks if the human approves the plan;
   no-new-tests → one sentence under Testes pontuais, not empty per-level lists)
  ├─ revise → PLAN
  ├─ cancel → CANCELED
  └─ approve
       ↓
EXECUTE worker (fresh context)
       ↓
CHECKS (required test/build/lint — hard gate)
  ├─ red, check_fix_round < max → REPAIR (not a review cycle; do not weaken valid tests) → CHECKS
  ├─ red, check-fix exhausted → HITL check_fail_limit (notify; retry/stop; Review does not start)
  └─ green
       ↓
REVIEW worker (fresh context, 4 tracks; review_round += 1)
  → grava `review/review-NN.md` (não substitui rodadas anteriores; sem log de checks)
  → grava `tests/checks-NN.md` (histórico de checks, mesma numeração)
  → rodada 2+ reinspeciona dirty atual ∪ paths apresentados na primeira review;
    revalida cada finding anterior (inclusive mediums); re-roda os quatro tracks no diff atual
       ↓
REVIEW REJECTED e review_round < max → REPAIR (findings) → CHECKS → REVIEW só se checks verdes
REVIEW REJECTED no limite → HITL repair_limit
REVIEW APPROVED e checks verdes
       ↓
HITL #2: commit approval
  (do not `task start` while this human gate is open; classify first; wait on addition/replacement; loose report does not open a task)
  ├─ local/simple change (layout, planned-screen popup, test for existing ACs) → revise-code immediately, no suggestion → REPAIR → CHECKS → REVIEW só se checks verdes
  ├─ addition of scope → suggest merge then start a new task
  │    accept: approve-commit (commit+merge+cleanup) then `harness task start` with the extra request
  │    refuse: revise-code
  ├─ replacement of original request → suggest cancel then start a new task that replans
  │    accept: cancel (no merge; discard worktree, unmerged task branch, and action) then `harness task start`
  │    refuse: revise-code
  ├─ cancel → CANCELED (no merge; discard worktree and unmerged task branch)
  └─ approve
       ↓
COMMIT task branch (skill conventional-commits: type(module), tests split)
       ↓
TRY MERGE target branch
  ├─ conflict/blocked → merge --abort → NEEDS_HUMAN_ATTENTION
  └─ success → remove worktree → delete temp branch → DONE
```

Execute/Repair não pode editar, apagar, pular ou enfraquecer um teste válido existente só para os checks passarem; corrige o código sob teste.

A máquina de estados é LangGraph. Os agentes sincronizados são workers/entrypoints, não uma segunda implementação do fluxo.
