# Workflow

```text
START
  ↓
PLAN worker (fresh context)
  ↓
validate context/spec/tasks
  ↓
HITL #1: plan approval
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
  ├─ revise → REPAIR → CHECKS → REVIEW só se checks verdes
  ├─ cancel → CANCELED
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
