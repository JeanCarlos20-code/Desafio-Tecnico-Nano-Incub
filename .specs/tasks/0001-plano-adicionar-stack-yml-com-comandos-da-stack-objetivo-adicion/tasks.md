---
harness:
  commit_message: "feat(harness): add stack.yml loader and component-scoped verify"
  gates:
    - id: unit
      command: "python3 -m pytest harness/tests -q --ignore=harness/tests/test_graph_e2e.py"
      required: true
    - id: test
      command: "python3 -m pytest harness/tests -q"
      required: true
    - id: lint
      command: "python3 -m pytest harness/tests/test_no_any.py -q"
      required: true
---

# Stack YAML + Verify — Tasks

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user - do not proceed without it.**

---

**Design**: skipped (MVP; decisions captured in Alternatives below and in spec assumptions)
**Status**: Complete

---

## Resumo da solução

Introduzir `harness/stack.yml` como catálogo do projeto e modelos frozen (`Project`, `Component`, `Command`, `Infrastructure`, `ProjectStack`) carregados por `StackLoader`. O core trata `commands` como `dict[str, Command]` — a chave é um ID opaco. Estender `harness/config.yaml` com `verify.required: [test, lint, build]`. No nó CHECKS já existente, além dos gates de `tasks.md`, identificar componentes afetados pelo porcelain da worktree (prefixo mais longo de `root`), resolver os IDs obrigatórios no mapa do componente, executar com `cwd=worktree/root` e gravar em `validation.md`. `PacketService.plan()` passa a montar um `ContextPacket` com resumo compacto da stack. Nenhuma transição LangGraph muda.

Este repositório é um monólito Laravel+Inertia: `stack.yml` terá `app` (root `.`) e `harness` (root `harness`). O YAML de exemplo do pedido (Angular, Pest, PostgreSQL, pastas `backend/`/`frontend/`) é só o *schema*, não o layout deste repo.

**Antes de editar:** copiar o `harness/` untracked do repositório principal para a worktree desta task. Sem isso os arquivos-fonte não existem no checkout isolado.

## Alternativas / opções consideradas

| Opção | Prós | Contras | Decisão |
| ----- | ---- | ------- | ------- |
| A. `verify.required` em `harness/config.yaml` | Um loader (`load_config`); arquivo já versionado no tree do harness | Nome do pedido era `harness.yml` | **Escolhida** |
| B. Novo `harness.yml` na raiz do repo | Nome literal do pedido | Duplica config; `load_config` ignora hoje | Rejeitada |
| C. CHECKS só com stack+verify (drop tasks.md gates) | Uma fonte de verdade | Quebra e2e atual e o contrato do packet de plan | Rejeitada neste MVP |
| D. CHECKS = verify.required (component cwd) **mais** gates de tasks.md (worktree cwd) | Fluxo LangGraph intacto; e2e continua | Risco de comando duplicado se o planner repetir o mesmo ID como shell | **Escolhida** — planner deve listar IDs no texto; frontmatter pode manter commands literais |
| E. Inferir `php artisan` / `npm` a partir de languages | Menos YAML | Viola neutralidade e o pedido | Rejeitada |
| F. Um componente `app` com root `.` apenas | Mais simples | Mudança em `harness/` rodaria Pint/artisan | Rejeitada; dois componentes + longest prefix |
| G. ID obrigatório ausente → CheckResult fail | Não aborta o grafo | Confunde misconfig com teste vermelho | Rejeitada; `HarnessError` |
| H. Módulo único `stack.py` vs pacote `stack/` | Menos abstração; alinhado a `checks.py` | Arquivo pode crescer | **Escolhida** `stack.py` + extensão de `checks.py` / `config.py` / `packets.py` / `graph_runtime.py` |

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec - confirm before Execute. Guidelines found: `docs/tests.md`, `docs/review-harness.md`, `harness/pyproject.toml` (`[tool.pytest.ini_options]`), `harness/tests/test_no_any.py`.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Stack domain (models + loader) | unit | All branches; 1:1 to AC-001..AC-009; empty infra, custom ID, invalid YAML, missing file | `harness/tests/test_stack.py` | `python3 -m pytest harness/tests/test_stack.py -q` |
| Command / verify execution | unit | cwd correto, custom command, missing required ID, longest prefix, validation.md; no language inference | `harness/tests/test_stack_verify.py` | `python3 -m pytest harness/tests/test_stack_verify.py -q` |
| Packet / ContextPacket | unit | Summary contains project, components, roots, languages, frameworks, data_access, testing, command IDs, infra | `harness/tests/test_packets.py` | `python3 -m pytest harness/tests/test_packets.py -q` |
| Neutralidade do core | unit | `harness/src/project_harness/**/*.py` sem branch de tecnologia; `Any` continua proibido | `harness/tests/test_stack_neutrality.py` + `test_no_any.py` | `python3 -m pytest harness/tests/test_stack_neutrality.py harness/tests/test_no_any.py -q` |
| Graph CHECKS + merge | integration | e2e existente ainda completa o grafo com fixture `stack.yml` cobrindo verify.required | `harness/tests/test_graph_e2e.py` | `python3 -m pytest harness/tests/test_graph_e2e.py -q` |
| `harness/stack.yml` / `config.yaml` | none | build/unit gate only (YAML de projeto) | `harness/stack.yml` | build gate |

## Gate Check Commands

> Generated from codebase - confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After unit-only tasks | `python3 -m pytest harness/tests -q --ignore=harness/tests/test_graph_e2e.py` |
| Full | After e2e/graph changes | `python3 -m pytest harness/tests -q` |
| Build | Phase complete / config-only | `python3 -m pytest harness/tests -q` |

Cwd de Execute: raiz da **worktree** (onde `harness/` deve existir após a cópia inicial). Pytest precisa estar no ambiente que já instala `pip install -e ./harness`.

---

## Execution Plan

Phases are ordered and run sequentially - each phase completes before the next begins, and tasks within a phase execute in order.

### Phase 1: Contrato e execução

```
T1 -> T2 -> T3
```

### Phase 2: Planner, YAML do projeto e e2e

```
T4 -> T5
```

---

## Task Breakdown

### Phase 1: Contrato e execução

### T1: Create typed stack models and StackLoader

**What**: Frozen dataclasses `Project`, `Component`, `Command`, `Infrastructure`, `ProjectStack` plus `StackLoader` that locates `harness/stack.yml`, loads YAML, validates, and returns `ProjectStack`.
**Where**: `harness/src/project_harness/stack.py`
**Depends on**: None
**Reuses**: `utils.read_yaml`, `require_object`, `as_str`, `as_list`, `as_object`; `errors.HarnessError`; frozen dataclass style of `types.py` / `config.py`
**Requirement**: STACK-01, STACK-02

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Models exist, frozen, no `Any`
- [x] `commands` is `dict[str, Command]` (or equivalent mapping) with opaque keys
- [x] Loader implements locate + load + validate
- [x] Tests in `harness/tests/test_stack.py` cover AC-001..AC-009
- [x] Gate check passes: `python3 -m pytest harness/tests -q --ignore=harness/tests/test_graph_e2e.py`

**Tests**: unit
**Gate**: quick

---

### T2: Execute stack commands using component root as cwd

**What**: Resolve a command ID on a component and run it with cwd = worktree / component.root; reject empty command and root path escape.
**Where**: `harness/src/project_harness/checks.py`
**Depends on**: T1
**Reuses**: `CheckRunner`, `CheckResult`, `utils.run_process` (`bash -lc`)
**Requirement**: STACK-03

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Running ID `test` on component root `backend` uses that directory as cwd
- [x] Custom command IDs execute the YAML string unchanged
- [x] Tests in `harness/tests/test_stack_verify.py` cover AC-011 and AC-009 (runtime)
- [x] Gate check passes: `python3 -m pytest harness/tests -q --ignore=harness/tests/test_graph_e2e.py`

**Tests**: unit
**Gate**: quick

---

### T3: Wire verify.required onto affected components in CHECKS

**What**: Parse `verify.required` from `harness/config.yaml`; map porcelain paths to components (longest prefix); run required IDs; missing ID → `HarnessError`; append results to `validation.md`; keep existing tasks.md gates and graph transitions.
**Where**: `harness/src/project_harness/graph_runtime.py`
**Depends on**: T2
**Reuses**: `GitManager.changed_paths`, `ArtifactService.append_validation_checks`, `load_config`, `HarnessGraph._checks`
**Requirement**: STACK-03, STACK-04

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `HarnessConfig` includes `verify.required: tuple[str, ...]`
- [x] `harness/config.yaml` lists `test`, `lint`, `build`
- [x] Affected matching uses longest root prefix
- [x] Missing required ID raises `HarnessError` with component + ID
- [x] Non-zero command exit becomes `CheckResult` (not HarnessError)
- [x] `validation.md` receives rendered stack checks
- [x] Tests cover AC-010..AC-016 except graph e2e (e2e is T5)
- [x] Gate check passes: `python3 -m pytest harness/tests -q --ignore=harness/tests/test_graph_e2e.py`

**Tests**: unit
**Gate**: quick

---

### Phase 2: Planner, YAML do projeto e e2e

### T4: Add ProjectStack summary to the Planner ContextPacket

**What**: Introduce `ContextPacket` that carries `ProjectStack` and render a compact summary into `plan.md`.
**Where**: `harness/src/project_harness/packets.py`
**Depends on**: T3
**Reuses**: `PacketService.plan`, `StackLoader`, `ContextService`
**Requirement**: STACK-05

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] Plan packet includes project, components, roots, languages, frameworks, data_access, testing, command IDs, infrastructure
- [x] Summary does not hardcode php/artisan/npm as core logic
- [x] Tests in `harness/tests/test_packets.py` cover AC-017, AC-018
- [x] Gate check passes: `python3 -m pytest harness/tests -q --ignore=harness/tests/test_graph_e2e.py`

**Tests**: unit
**Gate**: quick

---

### T5: Add this repo stack.yml, neutrality test, and e2e fixture

**What**: Write `harness/stack.yml` for Painel-administrativo; add a core neutrality test; update graph e2e so the copied harness has a fixture stack whose `test`/`lint`/`build` succeed in the tmp repo.
**Where**: `harness/tests/test_stack_neutrality.py`
**Depends on**: T4
**Reuses**: `harness/tests/test_graph_e2e.py`, `harness/tests/test_no_any.py`, spec example schema
**Requirement**: STACK-06

**Tools**:

- MCP: NONE
- Skill: `tlc-spec-driven`

**Done when**:

- [x] `harness/stack.yml` describes `app` (php, laravel, eloquent, phpunit, Inertia/React commands) and `harness` (python, pytest) with `test`/`lint`/`build` plus extra IDs (`unit`, `integration` as applicable)
- [x] Core `.py` has no technology control-flow (AC-019)
- [x] E2E still reaches `status=completed` (overwrite fixture stack after copytree)
- [x] Gate check passes: `python3 -m pytest harness/tests -q`

**Tests**: unit
**Gate**: full

**Commit**: `feat(harness): add stack.yml loader and component-scoped verify`

---

## Áreas / arquivos / símbolos esperados

Orientação, não allowlist:

- `harness/src/project_harness/stack.py` — `Project`, `Component`, `Command`, `Infrastructure`, `ProjectStack`, `StackLoader`, resumo compacto
- `harness/src/project_harness/checks.py` — execução com cwd do componente
- `harness/src/project_harness/config.py` — `VerifyConfig` / `verify.required`
- `harness/src/project_harness/graph_runtime.py` — `_checks`
- `harness/src/project_harness/packets.py` — `ContextPacket` + `plan()`
- `harness/src/project_harness/git_manager.py` — reusar `changed_paths` (só alterar se o mapeamento precisar de helper)
- `harness/config.yaml`, `harness/stack.yml`
- Testes: `test_stack.py`, `test_stack_verify.py`, `test_packets.py`, `test_stack_neutrality.py`, ajuste em `test_graph_e2e.py`

## Mapeamento task → AC

| Task | ACs | Requirement IDs |
| ---- | --- | --------------- |
| T1 | AC-001..AC-009 | STACK-01, STACK-02 |
| T2 | AC-011, AC-009 (runtime) | STACK-03 |
| T3 | AC-010, AC-012..AC-016 | STACK-03, STACK-04 |
| T4 | AC-017, AC-018 | STACK-05 |
| T5 | AC-019 + e2e regressão | STACK-06 |

## Testes que serão criados/alterados/executados

| ID | Nível | Arquivo | Entrada | Resultado esperado | AC |
| -- | ----- | ------- | ------- | ------------------ | -- |
| UT-001 | unit | `test_stack.py` | YAML válido (schema do pedido, dirs fictícios) | `ProjectStack` populado | AC-001, AC-002 |
| UT-002 | unit | `test_stack.py` | dois componentes | ambos preservados | AC-007 |
| UT-003 | unit | `test_stack.py` | infra lists `[]` | sequências vazias | AC-008 |
| UT-004 | unit | `test_stack.py` | comando `foo:` | ID `foo` presente | AC-003, AC-009 |
| UT-005 | unit | `test_stack.py` | YAML quebrado | `HarnessError` | AC-006 |
| UT-006 | unit | `test_stack.py` | arquivo ausente | `HarnessError` com `stack.yml` | AC-005 |
| UT-007 | unit | `test_stack.py` | modelos | nenhum `Any` | AC-004 |
| UT-008 | unit | `test_stack_verify.py` | component root `backend`, command `test` | process cwd termina com `backend` | AC-011 |
| UT-009 | unit | `test_stack_verify.py` | ID custom + script que imprime cwd | stdout contém o root | AC-009 |
| UT-010 | unit | `test_stack_verify.py` | required `lint` ausente no componente afetado | `HarnessError` com nome + `lint` | AC-013 |
| UT-011 | unit | `test_stack_verify.py` | paths `harness/x.py` vs roots `.` e `harness` | só componente `harness` | AC-012 |
| UT-012 | unit | `test_stack_verify.py` | comando `false` / exit 1 | `CheckResult.passed is False` | AC-014 |
| UT-013 | unit | `test_stack_verify.py` | resultados renderizados | `validation.md` contém bloco harness-checks | AC-015 |
| UT-014 | unit | `test_packets.py` | fixture stack | `plan.md` contém nome, roots, languages, frameworks, data_access, testing, IDs, infra | AC-017, AC-018 |
| UT-015 | unit | `test_stack_neutrality.py` | AST/grep em `src/project_harness` | sem `if language ==` / branches php/laravel/go/angular/pest | AC-019 |
| IT-001 | integration | `test_graph_e2e.py` | repo tmp + fixture stack com test/lint/build no-op | grafo completa `completed` | AC-016 |

Não alterar testes de produto PHP (`tests/Unit`, `tests/Feature`).

## Barreiras / gates

Executadas pelo harness após Execute (cwd = worktree) e também como Gate de cada task:

1. **unit** (required): `python3 -m pytest harness/tests -q --ignore=harness/tests/test_graph_e2e.py`
2. **test** (required): `python3 -m pytest harness/tests -q`
3. **lint** (required): `python3 -m pytest harness/tests/test_no_any.py -q`

Depois desta feature, o nó CHECKS também rodará IDs `test`, `lint`, `build` de `harness/stack.yml` nos componentes afetados. Planner deve citar esses IDs em `tasks.md` (exemplo: Backend/app gates: `unit`, `integration`, `lint`, `build`; harness gates: `test`, `lint`, `build`).

## Definition of Done

- Spec ACs cobertos pelos testes da tabela (nenhum AC só “testado na cabeça”).
- `harness/stack.yml` + `verify.required` presentes.
- Planner packet mostra o resumo compacto.
- Core sem `Any` e sem lógica de tecnologia.
- Grafo e2e verde.
- Fluxo LangGraph inalterado.
- `validation.md` da task 0001 receberá os checks do harness na fase Execute (não agora).

## Riscos / incertezas (aprovar ciente)

1. **`harness/` não está no git.** A worktree 0001 não tem o core. Execute **deve copiar** `/home/jeansouza/Painel-administrativo/harness` para a worktree antes de editar. Sem isso o commit da task não inclui o Python e os gates pytest falham.
2. Nome `harness.yml` vs `harness/config.yaml`: se o humano quiser um arquivo extra na raiz, pedir `request_changes`.
3. Gates `lint`/`build` do componente `harness` no YAML do projeto: sem Ruff. Plano: `compileall` como lint e import do pacote como build. Trocar se o humano preferir outra ferramenta.
4. `verify.required` + gates do `tasks.md` podem duplicar pytest nesta própria task (aceitável).
5. Comandos YAML via `bash -lc` herdam o modelo de confiança atual (arquivo local). Não introduzir `shell=True` extra.

---

## Phase Execution Map

```
Phase 1 → Phase 2

Phase 1:  T1 -> T2 -> T3
Phase 2:  T4 -> T5
```

Execution is strictly sequential - there is no intra-phase parallelism.

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: models + StackLoader | 1 module | ✅ Granular |
| T2: cwd execution | 1 function/area in checks.py | ✅ Granular |
| T3: verify + affected + graph _checks | cohesive wiring | ✅ Granular |
| T4: ContextPacket summary | 1 packet path | ✅ Granular |
| T5: stack.yml + neutrality + e2e fixture | cohesive closing | ✅ Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | (no inbound) | ✅ Match |
| T2 | T1 | T1 -> T2 | ✅ Match |
| T3 | T2 | T2 -> T3 | ✅ Match |
| T4 | T3 | (cross-phase; Phase 2 starts at T4) | ✅ Match |
| T5 | T4 | T4 -> T5 | ✅ Match |

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | Stack domain | unit | unit | ✅ OK |
| T2 | Command / verify execution | unit | unit | ✅ OK |
| T3 | Command / verify execution | unit | unit | ✅ OK |
| T4 | Packet / ContextPacket | unit | unit | ✅ OK |
| T5 | Neutralidade + graph e2e (+ YAML none) | unit (highest) | unit | ✅ OK |
