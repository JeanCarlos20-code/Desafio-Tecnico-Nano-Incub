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
CHECKS
       ↓
REVIEW worker (fresh context, 4 tracks) → grava `review/review-NN.md` (não substitui rodadas anteriores; Verdict da review ≠ overlay de checks)
       ↓
Gate = checks green AND no blocker/high da **última** review?
  ├─ no → REPAIR worker (fresh context) → CHECKS → REVIEW
  │        max 3 then HITL escalation
  └─ yes
       ↓
HITL #2: commit approval
  ├─ revise → REPAIR → CHECKS → REVIEW
  ├─ cancel → CANCELED
  └─ approve
       ↓
COMMIT task branch (skill conventional-commits: type(module), tests split)
       ↓
TRY MERGE target branch
  ├─ conflict/blocked → merge --abort → NEEDS_HUMAN_ATTENTION
  └─ success → remove worktree → delete temp branch → DONE
```

A máquina de estados é LangGraph. Os agentes sincronizados são workers/entrypoints, não uma segunda implementação do fluxo.
