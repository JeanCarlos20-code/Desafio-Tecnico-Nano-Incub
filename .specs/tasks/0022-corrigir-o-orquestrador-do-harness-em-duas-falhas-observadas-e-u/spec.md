# Specification

## Context

Pedido do usuário (original): *Corrigir o orquestrador do Harness em duas falhas observadas e um recorte extra de apresentação do gate de plano.*

Falha 1 — gate de plano: o agente reescreveu o summary e mostrou só o plano, sem testes pontuais e sem a tabela de comandos. O CLI já monta as três seções em `plan_barrier_summary`. O orquestrador deve colar o summary do CLI na íntegra (plano + testes pontuais + comandos após o Execute), sem cortar, e terminar perguntando se o humano aprova o plano. Não implementar nesse gate.

Exceção: se a tarefa realmente NÃO tiver que fazer testes novos, NÃO invente lista vazia nem finja cobertura. Mostre uma frase no formato: `sem testes para esse plano pois ele é apenas ...` e explique o porquê. Se houver testes novos, continue no padrão das três seções com unit/integration/e2e e a tabela de comandos.

Falha 2 — gate de commit / task aberta: o humano relatou outro bug (popup cancelar reserva) enquanto a 0020 estava no gate de commit. O orquestrador ignorou a task aberta e rodou `harness task start`, criando a 0021 sem consultar. Com task aberta em gate humano, NÃO chamar `task start`. Classificar no gate atual. Adição de escopo: sugerir merge+nova task e ESPERAR sim/não. Substituição: sugerir cancel+nova task e ESPERAR. Ajuste local: revise-code imediato. Relato solto não abre task. `task start` só depois de aceite explícito.

The CLI already prints `action.summary` in full. The cutter is `harness/agents/harness.md`. `plan_barrier_summary` still lists `- not applicable: {reason}` per empty test level. `_parse_planned_tests` requires a per-level skip even when the plan has no new tests at all. Commit-gate classification already exists; `## Início` still starts a task without checking an open human gate.

## Problem

The plan-gate human never sees the punctual tests and post-Execute commands when the orchestrator rewrites the CLI summary. When a plan truly has no new tests, the CLI still invents three “not applicable” rows instead of one honest sentence. A new report while a task is waiting at a human gate still follows “new request → `task start`”, so a second task is created without asking.

## Goal

- At `gate=plan`, the orchestrator pastes the CLI `summary` in full and asks whether the human approves the plan. It does not implement.
- When a plan has no new tests, the summary shows one sentence `sem testes para esse plano pois ele é apenas ...` instead of fake or per-level empty coverage. When it has new tests, it keeps the three-level list and the commands table.
- While any task is at a human gate, the orchestrator does not call `task start`. It classifies the report on that gate, waits for accept on addition/replacement, and calls `revise-code` immediately only for a local commit-gate adjustment.
- A loose report does not open a task. `task start` runs only after explicit accept of a classified suggestion, or on a clear new request when no human gate is open.

## User Stories

### P1: Paste the plan-gate CLI summary ⭐ MVP

**User Story**: As the person at the plan gate, I want the orchestrator to show the CLI summary in full so that I can approve the plan, the punctual tests, and the post-Execute commands together.

**Why P1**: Observed failure — the agent showed only the plan.

**Acceptance Criteria**:

1. WHEN the current action is `kind=human` and `gate=plan` AND `action.summary` is present, THEN the orchestrator SHALL paste that `summary` text in full AND SHALL NOT rewrite it AND SHALL NOT drop `## Plano`, `## Testes pontuais`, or `## Comandos após o Execute`
2. WHEN that summary has been shown, THEN the orchestrator SHALL ask whether the human approves the plan
3. WHILE the current action is `kind=human` and `gate=plan`, the orchestrator SHALL NOT implement product or harness code
4. The orchestrator SHALL NOT present `harness.commits` at the plan gate

**Independent Test**: Canonical `harness/agents/harness.md` `gate=plan` section requires pasting `summary` in full, the approval question, and no implementation.

---

### P1: Single sentence when a plan has no new tests ⭐ MVP

**User Story**: As the person reviewing a plan that only changes docs or orchestrator copy, I want one honest sentence instead of invented test lists so that I do not approve fake coverage.

**Why P1**: User asked for this exception in the same authorization as the two failures.

**Acceptance Criteria**:

1. WHEN every `harness.tests` level is empty AND `tests_not_applicable_reason` starts with `sem testes para esse plano pois ele é apenas`, THEN `plan_barrier_summary` SHALL put that sentence under `## Testes pontuais` AND SHALL NOT emit `### Unit`, `### Integration`, `### E2E`, or `- not applicable:`
2. WHEN every `harness.tests` level is empty AND `tests_not_applicable_reason` is missing or does not start with `sem testes para esse plano pois ele é apenas`, THEN `validate_plan` SHALL reject the plan
3. WHEN every `harness.tests` level is empty AND `tests_not_applicable_reason` is set, THEN `validate_plan` SHALL NOT require `tests_not_applicable.unit`, `tests_not_applicable.integration`, or `tests_not_applicable.e2e`
4. WHEN at least one `harness.tests` level lists a punctual behavior, THEN `plan_barrier_summary` SHALL keep `### Unit`, `### Integration`, and `### E2E` AND SHALL still require `tests_not_applicable.<level>` for each empty level
5. WHEN a plan has new tests, THEN `plan_barrier_summary` SHALL still include `## Comandos após o Execute` with the gates table and stack verify
6. The planner prompt SHALL tell PLAN to set `tests_not_applicable_reason` to that sentence when there are no new tests, and SHALL NOT tell it to invent empty unit/integration/e2e coverage

**Independent Test**: Artifact unit tests for all-empty vs mixed plans; `harness/agents/plan.md` (and the generated PLAN packet if it repeats the rule) states the exception.

---

### P1: Do not start a task while a human gate is open ⭐ MVP

**User Story**: As the person who reports another bug while a task is waiting at a human gate, I want the orchestrator to classify that report on the open task so that a new task is not created without my explicit accept.

**Why P1**: Observed failure — task 0021 was started while 0020 was at the commit gate.

**Acceptance Criteria**:

1. WHEN a task in the current conversation, or a readable `.git/harness/actions/*.json`, is `kind=human`, THEN the orchestrator SHALL NOT call `harness task start`
2. WHEN that open gate is `commit` AND the report is a scope addition (original request still wanted), THEN the orchestrator SHALL suggest merge then a new task AND SHALL wait for explicit yes/no AND SHALL NOT call `approve-commit` or `task start` until the user accepts
3. WHEN that open gate is `commit` AND the report is a scope replacement (original request almost completely replaced), THEN the orchestrator SHALL suggest cancel then a new task AND SHALL wait for explicit yes/no AND SHALL NOT call `cancel` or `task start` until the user accepts
4. WHEN that open gate is `commit` AND the report is a local adjustment that stays inside the original request and approved ACs, THEN the orchestrator SHALL call `harness task revise-code` immediately AND SHALL NOT suggest merge-and-start or cancel-and-start
5. WHEN that open gate is `plan` AND the report is a local adjustment to the plan under review, THEN the orchestrator SHALL call `harness task revise-plan` AND SHALL NOT call `task start`
6. IF the user message is a loose report (a bug or remark that is not a clear new-work request and not a classified addition/replacement/local change), THEN the orchestrator SHALL NOT call `task start`
7. WHEN no human-gate action is open AND the user makes a clear new-work request, THEN the orchestrator MAY call `harness task start`
8. WHEN the user explicitly accepts an addition or replacement suggestion, THEN the orchestrator SHALL run the existing accept sequence (`approve-commit` then `start`, or `cancel` then `start`) and SHALL NOT start before that accept
9. `## Início` in `harness/agents/harness.md` SHALL require the open-gate check before `task start`

**Independent Test**: Canonical agent file contains the open-gate check in `## Início`, the wait-before-start rule, and the loose-report rule. Existing commit-gate addition/replacement/local tests remain.

---

## Acceptance Criteria

| ID | Story | Criterion |
| -- | ----- | --------- |
| AC-001 | Paste summary | WHEN `gate=plan` and `action.summary` is present, THEN the orchestrator SHALL paste that summary in full without rewriting or dropping the three headings |
| AC-002 | Ask approval | WHEN the plan-gate summary has been shown, THEN the orchestrator SHALL ask whether the human approves the plan |
| AC-003 | No implement | WHILE `gate=plan`, the orchestrator SHALL NOT implement code |
| AC-004 | No-tests sentence | WHEN all test levels are empty and `tests_not_applicable_reason` starts with `sem testes para esse plano pois ele é apenas`, THEN `plan_barrier_summary` SHALL show that sentence under `## Testes pontuais` and SHALL NOT list per-level not-applicable rows |
| AC-005 | No-tests validation | WHEN all test levels are empty without a valid `tests_not_applicable_reason` prefix, THEN `validate_plan` SHALL reject |
| AC-006 | Mixed levels | WHEN at least one level has tests, THEN empty levels SHALL still use `tests_not_applicable.<level>` and the summary SHALL keep the three subsections |
| AC-007 | Commands stay | WHEN a plan has new tests, THEN the summary SHALL still include `## Comandos após o Execute` |
| AC-008 | Planner rule | The PLAN agent SHALL instruct the no-new-tests sentence and SHALL NOT instruct invented empty coverage |
| AC-009 | Open-gate start | WHILE a `kind=human` action is open, the orchestrator SHALL NOT call `task start` |
| AC-010 | Addition wait | WHEN an open commit-gate report is a scope addition, THEN the orchestrator SHALL suggest merge+new task and SHALL wait |
| AC-011 | Replacement wait | WHEN an open commit-gate report is a scope replacement, THEN the orchestrator SHALL suggest cancel+new task and SHALL wait |
| AC-012 | Local revise-code | WHEN an open commit-gate report is a local adjustment, THEN the orchestrator SHALL call `revise-code` immediately |
| AC-013 | Loose report | IF the message is a loose report, THEN the orchestrator SHALL NOT call `task start` |
| AC-014 | Início check | `## Início` SHALL require the open-gate check before `task start` |

## Edge Cases

- IF `action.summary` is missing at the plan gate, THEN the orchestrator SHALL fall back to the three action fields (`summary` / `tests` / `gates`) in the same order and SHALL still ask for approval — it SHALL NOT invent tests.
- IF a human-gate action exists for another task id in `.git/harness/actions/` but the user explicitly authorizes a new task (as with this task 0022), THEN the orchestrator MAY call `task start` after that explicit accept.
- IF addition accept hits merge conflict / `needs_human_attention`, THEN the orchestrator SHALL NOT start the new task until the current task is integrated or closed (existing rule).
- IF the user is silent or answers with ambiguous assent (“ok”, “segue”), THEN the orchestrator SHALL NOT treat that as approve-plan, approve-commit, or accept of a suggestion.
- IF only one test level is empty, THEN the system SHALL keep per-level `tests_not_applicable.<level>` and SHALL NOT use the single no-new-tests sentence.
- IF `tests_not_applicable_reason` is set while a test level also lists behaviors, THEN `validate_plan` SHALL still accept the listed tests and SHALL NOT replace them with the sentence.
- IF `tests_not_applicable_reason` is used because there are no gates, THEN the commands section SHALL still show that reason; when the same field is also the no-new-tests sentence, the tests section SHALL show it first.

## Out of Scope

| Item | Reason |
| ---- | ------ |
| Laravel rooms / reservations / popups | User forbade product app changes |
| Python classifier for local / addition / replacement | Task 0010: judgment stays in `harness.md` |
| `harness task list` / `harness task restart` | Not required; start must stay possible after explicit accept |
| CLI hard-block of `task start` while another task is open | This task 0022 is an authorized start beside 0020/0021 |
| Product Playwright / PHPUnit / Vitest | No product UI or HTTP change |
| Wiping the human chat UI | Workers stay fresh; the CLI cannot erase the Cursor thread |

## Considered Approaches

| Approach | Trade-off | Verdict |
| -------- | --------- | ------- |
| Prompt-only: tell the orchestrator to paste `summary` | Fixes the observed rewrite; CLI already prints the text | Selected for Falha 1 presentation |
| Change only `plan_barrier_summary` and hope the agent stops rewriting | The agent still rebuilds from structured fields | Rejected as the only fix |
| Per-level `tests_not_applicable` forever | Honest mixed skips; invents three rows when there are no tests | Keep for mixed plans only |
| New field `no_new_tests_reason` | Clearer name; extra API beside existing `tests_not_applicable_reason` | Rejected — reuse the existing string |
| New `harness task list` | Deterministic discovery; extra CLI surface | Rejected this task |
| CLI refuses `task start` if any action is human | Stops 0021-style accidents; blocks authorized parallel starts | Rejected |

## Selected Approach

Keep classification in the orchestrator prompt. Make `gate=plan` paste `action.summary` and ask for approval. Extend `plan_barrier_summary` / `validate_plan` so an all-empty `harness.tests` is valid only with `tests_not_applicable_reason` starting with `sem testes para esse plano pois ele é apenas`, and render that sentence once. Teach `## Início` to classify on an open human gate instead of starting. Update PLAN agent, README, WORKFLOW, and harness pytest. Do not touch the Laravel app.
