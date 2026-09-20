# Validation

## Acceptance Criteria

| ID | Result | Evidence |
| -- | ------ | -------- |
| AC-001 | Implemented | `harness/agents/harness.md` `gate=plan` pastes `action.summary` in full and forbids rewrite/cut of `## Plano`, `## Testes pontuais`, `## Comandos após o Execute`. Test: `test_orchestrator_plan_gate_pastes_summary_and_asks_approval`. |
| AC-002 | Implemented | Same section asks whether the human approves the plan. |
| AC-003 | Implemented | Same section forbids implementing product or harness code at the plan gate. |
| AC-004 | Implemented | `plan_barrier_summary` renders `tests_not_applicable_reason` under `## Testes pontuais` when every level is empty and the required prefix is present; no `### Unit` / `### Integration` / `### E2E` or `- not applicable:`. Test: `test_plan_barrier_summary_shows_single_no_new_tests_sentence`. |
| AC-005 | Implemented | `validate_plan` / `_parse_planned_tests` reject all-empty tests when the reason is missing or lacks `sem testes para esse plano pois ele é apenas`. Test: `test_validate_plan_rejects_all_empty_tests_without_required_reason_prefix`. |
| AC-006 | Implemented | Mixed plans still require `tests_not_applicable.<level>` and still list the three subsections. Existing `test_plan_requires_reason_when_a_level_has_no_tests` plus `test_plan_barrier_summary_lists_three_levels_when_any_level_has_tests`. |
| AC-007 | Implemented | Commands section remains after the tests block when a plan has new tests (existing `test_plan_barrier_summary_lists_punctual_tests_by_level`) and when the no-new-tests sentence is used. |
| AC-008 | Implemented | `harness/agents/plan.md` and the generated PLAN packet instruct the no-new-tests sentence and forbid invented empty coverage. Tests: `test_planner_instructs_no_new_tests_sentence`, packet assertion in `test_packets.py`. |
| AC-009 | Implemented | `## Início` forbids `harness task start` while `kind=human` is open. Test: `test_orchestrator_inicio_checks_open_human_gate_before_start`. |
| AC-010 | Implemented | Início + commit-gate: addition suggests merge + new task and waits. Test: `test_orchestrator_keeps_wait_revise_and_no_start_on_loose_report`. |
| AC-011 | Implemented | Replacement suggests cancel + new task and waits. Same test plus existing cancel-then-start test. |
| AC-012 | Implemented | Local commit-gate adjustment still calls `revise-code` immediately. Existing `test_orchestrator_keeps_revise_code_for_local_and_explicit_refuse`. |
| AC-013 | Implemented | Início: loose report does not call `task start`. |
| AC-014 | Implemented | `## Início` requires the open-gate check before `task start`. |

Extra files beyond `tasks.md` (real dependency):

- `harness/src/project_harness/graph_runtime.py` — plan-gate payload now includes `tests_not_applicable_reason` so the structured action matches the reused field.
- `harness/tests/test_packets.py` — PLAN packet now states the no-new-tests rule.
- `.cursor/agents/harness.md` and `.cursor/agents/harness-plan.md` — generated from the canonical agent files by a worktree-local cursor sync. `harness sync` from this worktree writes the primary checkout (`find_repository_root`); the worktree copies were generated with `Synchronizer(worktree).sync(("cursor",))`.

Repair extra files (gitignored, not feature scope):

- `vendor/` — `composer install --no-interaction --prefer-dist --no-progress` created `vendor/autoload.php` and `vendor/bin/pint` so `app:test`, `app:lint`, and `app:build` can boot Artisan.
- `node_modules/` — `npm install --no-fund --no-audit` restored the JS toolchain for `npm run test`, `npm run lint`, and `npm run build`.
- `.env` — copied from the primary checkout so Artisan can boot. Not committed.

An incidental `package-lock.json` `name` rewrite from `npm install` (worktree directory name) was reverted. No Laravel / rooms / reservations / product test files were changed. Tests were not weakened. No `harness task list`, no CLI hard-block of `task start`, no Python classifier.

## Test Results

Local quick check from Execute (not the harness final gate; not re-run in this repair):

```text
cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py
109 passed in 1.40s
```

Repair did not add, delete, skip, or weaken tests. Existing commit-gate tests remain. `test_graph_e2e.py` was not required: `graph_runtime.py` only gained a payload field.

Local smoke after restore (not a final gate): `php artisan --version` reports Laravel 12.69.2; `vendor/bin/pint --version` reports Pint 1.32.1.

## Required Gates

| Gate | Local quick check | Harness final |
| ---- | ----------------- | ------------- |
| harness-unit | 109 passed (Execute) | Not declared here; harness re-runs after complete-phase |
| harness-lint | `python3 -m compileall -q src` exit 0 (Execute) | Same |
| stack verify `build` | `PYTHONPATH=src python3 -c "import project_harness"` exit 0 (Execute) | Same |
| app:test / app:lint / app:build | Worktree now has `vendor/autoload.php`, `vendor/bin/pint`, and `node_modules` | Harness re-runs; do not declare green here |

## Review Result

No prior review file. Repair corrected failed required stack checks only. No review findings were invented.

## Final Status

Repair restored the worktree PHP/JS stack so the previously failing `app:test`, `app:lint`, and `app:build` gates can run. Product Laravel code and harness tests were not changed. No commit was created.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` — exit=0 (required)
- ✅ `cd harness && python3 -m compileall -q src` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
- ✅ `python3 -m pytest tests -q` — exit=0 (required)
- ✅ `python3 -m compileall -q src` — exit=0 (required)
- ✅ `PYTHONPATH=src python3 -c "import project_harness"` — exit=0 (required)
<!-- harness-checks:end -->
