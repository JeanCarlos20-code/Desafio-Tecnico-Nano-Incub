# Harness review contract

The harness owns the loop. This skill only produces structured findings.

```text
EXECUTE (worktree)
  ↓
CHECKS (php artisan test, pint, …)
  ↓
TRACK architecture  ─┐
TRACK security      ─┤  this skill (author ≠ executor)
TRACK smells        ─┤
TRACK tests         ─┘
  ↓
merge_tracks.py + review_gate.py
  ↓
APPROVED → complete
REJECTED → EXECUTE repair with execute_handoff.blocking_ids
```

Specialized tracks never approve the task. They emit candidate findings only (`verdict` omitted or null).

The consolidated report is the only artifact that may set `verdict` and `execute_handoff`.

## Gate (deterministic)

- Any `blocker` or `high` → `REJECTED` and `execute_handoff.action = repair`
- Only `medium` or empty findings → `APPROVED` and `action = complete`
- The LLM must not contradict the gate. `scripts/review_gate.py` and `scripts/merge_tracks.py` enforce it.

## What Execute receives on repair

The harness should pass the consolidated JSON (or at least `findings` where `id` is in `blocking_ids`) to the Execute agent:

- fix only those items;
- do not expand scope;
- do not delete or weaken valid tests to go green;
- after the fix, the harness re-runs checks and all four tracks.

Medium findings stay in the report for humans. They do not block Execute completion.

## Files

Write each track to the run directory, then merge:

```bash
python3 "$SKILL_DIR/scripts/review_gate.py" architecture.json
python3 "$SKILL_DIR/scripts/review_gate.py" security.json
python3 "$SKILL_DIR/scripts/review_gate.py" smells.json
python3 "$SKILL_DIR/scripts/review_gate.py" tests.json
python3 "$SKILL_DIR/scripts/merge_tracks.py" \
  --architecture architecture.json \
  --security security.json \
  --smells smells.json \
  --tests tests.json \
  --out consolidated.json
```
