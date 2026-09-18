---
name: harness-review
description: "Evidence-first harness code review for this repo. Four independent tracks (architecture, security, smells, tests) load docs/reviews/* as source of truth, list every real finding, then a deterministic gate rejects on blocker or high so Execute must repair. Use when the user or harness says harness review, review architecture, review security, review smells, review tests, review this diff, or after Execute. Do NOT use for writing the fix (Execute), planning (tlc-spec-driven), GitHub PR posting (the-judge), or Lighthouse/page audits (best-practices)."
license: MIT
metadata:
  author: Painel Administrativo
  version: "1.0.0"
---

# Harness Review

Independent review of a local worktree/diff against `docs/reviews/`. The reviewer never implements. If the gate rejects, control returns to Execute with every blocking finding listed.

This skill is **not** The Judge. It does not call `gh`, does not post GitHub reviews, and does not use should-fix/nit. Severity is **blocker / high / medium**. Blocker **and** high fail the harness gate.

## Loading this skill's files

Resolve `SKILL_DIR` as the directory that contains this `SKILL.md`. Review policy lives in the **project** (never copy it into the skill):

| Track | Load completely |
| ----- | --------------- |
| architecture | `docs/reviews/review-architecture.md` plus `docs/context.md`, `docs/architecture.md`, `docs/tree.md` |
| security | `docs/reviews/review-security.md` (and `docs/adr/*` when identity, hash, or Inertia boundaries are in the diff) |
| smells | `docs/reviews/review-smells.md` |
| tests | `docs/reviews/review-tests.md` plus `docs/test/unit.md`, `docs/test/integration.md`, and `docs/test/e2e.md` |

Harness JSON contract: [references/harness-contract.md](references/harness-contract.md) and [references/output.schema.json](references/output.schema.json).

Run scripts as `python3 "$SKILL_DIR/scripts/<name>.py"`.

## Critical rules

1. **Author ≠ executor.** Do not edit application code, tests (except you do not touch them at all), or git. Findings only.
2. **Evidence or silence.** Internal claims need a verified `path:line` you actually read. External claims (framework behavior) need an `https://` official URL fetched this review, or drop the finding.
3. **List every real finding.** No nit cap. No “overflow counted in summary”. Do not invent items to fill a report. Preference is not a finding.
4. **Do not comment on what checks already catch.** If `php artisan test` or Pint already fail, treat that as a harness check fact, not a prose finding — unless the test was deleted/weakened to hide a regression (that **is** a tests-track finding).
5. **Specialized tracks do not verdict the task.** They write JSON with `verdict: null`. Only consolidate + `merge_tracks.py` set `APPROVED` / `REJECTED`.
6. **Gate:** any `blocker` or `high` → `REJECTED` → Execute repair. Only `medium` or zero findings → `APPROVED`, **and only if each clean track proved it judged**: empty `findings` requires at least one `positives` item with `path:line` of a file actually read. Empty findings + empty/unlocated positives → GATE FAIL (the track did not review). The LLM must not override this.
7. **Round 1 is the whole review.** Later harness loops only check previous blocking ids plus new blocker/high introduced by the repair. Do not open new medium fronts on repair rounds unless they are new blocker/high caused by the fix.
8. **Portuguese** for `summary`, `problem`, `impact`, `fix`, and `instructions`. Keep ids, paths, severity tokens, and verdicts in English.

## Severity

```text
❌ blocker — mandatory rule broken, exploit, data integrity, or security/auth failure
⚠️ high    — relevant defect or missing protection; must be fixed before complete
📝 medium  — real smell/maintainability; does not send the task back to Execute
```

Do not upgrade a preference to high. Do not downgrade a documented blocker in `docs/reviews/*`.

## Workflow

### 0. Scope the diff

Work only on the harness worktree (or the current git diff if run in Cursor). Classify files as core vs mechanical (lockfiles, generated, `vendor/`, `node_modules/`). Skip mechanical files; list them under `unverified` if needed.

Detect round: if the harness passes previous `blocking_ids`, this is a repair re-review (rule 7).

### 1. Deterministic checks (facts)

If the harness already ran checks, read their exit codes. Do not re-litigate red tests as architecture opinions.

### 2. Four tracks (prefer parallel subagents)

For each track, read the policy files above **to EOF**, inspect the diff, and write one JSON object matching [references/output.schema.json](references/output.schema.json).

Suggested finding id prefixes: `ARCH-001`, `SEC-001`, `SMELL-001`, `TEST-001`.

| Track | Focus | Do not do |
| ----- | ----- | --------- |
| architecture | Boundaries, `Infra → Application → Domain`, Inertia/React placement | Do not demand extra layers beyond `docs/architecture.md` / `docs/tree.md`. Do enforce the layers those docs already require. |
| security | Authn/authz, mass assignment, hashing, CSRF, XSS, secrets | Call something a vulnerability without an exploit/property story |
| smells | Complexity, AI slop, Laravel/React smells | Style-only nits; “Laravel Facade exists” |
| tests | Level and protection per `docs/test/unit.md`, `docs/test/integration.md`, and `docs/test/e2e.md` | Invent tests the strategy does not require |

Each track JSON:

```json
{
  "track": "architecture",
  "summary": "…",
  "verdict": null,
  "findings": [],
  "unverified": [],
  "positives": []
}
```

If the track is clean, `findings` is `[]` **and** `positives` cites at least one `path:line` you actually read. A track with empty findings and no located positive fails `review_gate.py` — it did not judge.

Then:

```bash
python3 "$SKILL_DIR/scripts/review_gate.py" <track>.json
```

Fix schema problems and re-run until GATE PASS. Do not start merge with a failing track file.

### 3. Consolidate (required)

```bash
python3 "$SKILL_DIR/scripts/merge_tracks.py" \
  --architecture architecture.json \
  --security security.json \
  --smells smells.json \
  --tests tests.json \
  --out consolidated.json
```

`merge_tracks.py` already applies the gate (dedupes exact path+line+problem). Do not hand-edit `verdict` after it runs.

### 4. Present and return control

From `consolidated.json`:

- Print a short human summary (Blocker / High / Medium tables).
- If `execute_handoff.action` is `repair`, tell the harness/Execute to fix **only** `blocking_ids`. Include `problem`, `impact`, and `fix` for each.
- If `complete`, stop. Medium items stay visible but do not block.

Never implement the repair yourself when running as the reviewer.

## Cursor (no harness)

User says “review this” / `@harness-review`: run all four tracks on the current diff, merge, show the report. Still do not patch code unless they explicitly ask to apply the blocking fixes afterwards (that is Execute, not this skill).

## Examples

### Harness after Execute

Actions: load the four `docs/reviews/*.md` files → four JSON reports → gate each → `merge_tracks.py` → `REJECTED` with `SEC-001` and `TEST-002` → return those ids to Execute.

### Clean diff

All tracks empty findings **with path:line positives** → merge `APPROVED` / `complete`. A track that writes `findings: []` and `positives: []` fails the gate.

### Repair round

Harness sends previous consolidated JSON. Re-check only those blocking ids plus new blocker/high from the fix diff. Do not add a backlog of new mediums.

## Troubleshooting

### Gate fails on missing path:line

Blocker/high without internal evidence. Re-read the file or drop the finding.

### Tests track cannot find a level document

Load `docs/test/unit.md`, `docs/test/integration.md`, and `docs/test/e2e.md`. If one is missing, record that exact path under `unverified` and do not invent a matrix for that level.

### Merge says missing tracks

All four JSON files are required even when a track is clean (`findings: []`).
