# Contexto comprimido — stack.yml + verify

## Documentos consultados

- `docs/context.md` — app único Laravel + Inertia + React; PHP 8.2+, Laravel 12, MySQL 8. Não é monorepo backend/frontend.
- `docs/architecture.md` + `docs/adr/003-react-e-php-no-mesmo-projeto-laravel.md` — um projeto, UI em `resources/js`, backend em `app/`. Não criar `backend/` + `frontend/` só para copiar o exemplo do pedido.
- `docs/tree.md` — árvore do produto (PHP/React). Harness Python não aparece.
- `docs/review-harness.md` — bloqueia `Any` no domínio; `Any` só na fronteira YAML se validado cedo; bloqueia `if language == "go"` no core; subprocess deve checar returncode; CHECKS é nó próprio.
- `docs/tests.md` — testes determinísticos de contrato; harness impõe invariantes.
- `docs/persona-agent.md` — não inventar stack; não afirmar comando executado sem evidência.
- `harness/docs/WORKFLOW.md` + `harness/docs/CONTEXT.md` — PLAN → HITL → EXECUTE → CHECKS → REVIEW → REPAIR → HITL commit → merge. PLAN recebe índice de docs; `progress.md` não entra no packet.
- `harness/README.md` — hoje o harness “não tenta conhecer stack”; gates vêm do frontmatter de `tasks.md`.
- `.specs/STATE.md` — sem decisão prévia sobre stack.yml. Lessons TLC: nenhuma confirmada.

## O que o código faz hoje

- Config: `harness/config.yaml` via `load_config()` em `harness/src/project_harness/config.py`. Sem seção `verify`. Não existe `harness.yml` nem `harness/stack.yml`.
- Packet do Planner: `PacketService.plan()` em `packets.py` escreve markdown. Não há tipo `ContextPacket`. Não há resumo de stack.
- Checks: `HarnessGraph._checks()` lê `PlanData.gates` (`artifacts.validate_plan`) e chama `CheckRunner.run(worktree, gates)`. cwd **sempre** a raiz da worktree. Comando via `["bash", "-lc", gate.command]` em `utils.run_process` (`shell=True` não é usado).
- Resultado: `append_validation_checks()` grava bloco `<!-- harness-checks:start -->` em `validation.md`.
- Paths alterados: `GitManager.changed_paths()` (`git status --porcelain=v1 --untracked-files=all`). Ainda não mapeia componente.
- Tipagem: `JSONValue` / `JSONObject` em `types.py`; parsers `as_str` / `as_list` / `as_object` / `require_object` em `utils.py`. `read_yaml()` exige arquivo e objeto. `test_no_any.py` falha se `Any` aparecer em `src/` ou `tests/`.
- Erros: `HarnessError` (mensagem para o usuário).
- Testes: pytest em `harness/tests/` (`conftest.py` com `git_repo` / `harness_source`). E2E `test_graph_e2e.py` faz `copytree` do harness para um repo temporário e usa gate `python -c 'print(1)'`.
- Comandos reais deste repo (produto): `php artisan test` / `--testsuite=Unit` / `--testsuite=Feature`; Pint em `composer.json`; `npm run build` no `package.json`. PHPUnit, não Pest. React, não Angular. MySQL, não PostgreSQL.

## Restrições

- Core em `harness/src/project_harness/` permanece **neutro**: componentes, languages, frameworks, data_access, testing, commands genéricos. Sem branch por PHP/Laravel/Go/Angular/Pest.
- `commands` é mapa chave → `{command: string}`. Chaves (`unit`, `lint`, `foo`, …) **não** são enum do core.
- Não inferir comando a partir de language/framework.
- Não alterar a máquina de estados: worktree, plan, HITL, execute, review, repair, commit/merge. Só mudar **como** o nó CHECKS resolve/executa comandos e o que o packet de PLAN contém.
- `stack.yml` = o que existe e como rodar. Config do harness = quais IDs são obrigatórios no workflow (`verify.required`).
- Sem `typing.Any`. Validar YAML na borda com `isinstance` / dataclasses frozen.

## Padrões a reutilizar

- Dataclasses frozen (`GateSpec`, `CheckResult`, `HarnessConfig`).
- `read_yaml` + `HarnessError` para arquivo ausente / tipo errado.
- `CheckResult` + `CheckRunner.render` + `append_validation_checks` para gravar verify em `validation.md`.
- `CheckRunner.required_passed` continua a decidir repair vs commit (exit code ≠ 0).
- Pytest + fixtures `tmp_path` / `harness_source`; e2e copia `harness/`.

## Dependências concretas

- PyYAML já é dependência (`PyYAML>=6.0,<7.0`).
- Python ≥ 3.11.
- `GitManager.changed_paths` para componentes afetados (porcelain da worktree; execute ainda não comita).

## Fato operacional (Execute precisa disto)

`harness/` está **untracked** (`git status: ?? harness/`). A worktree da task 0001 **não contém** o core Python. Execute deve materializar/editar `harness/` **dentro da worktree** (copiar o tree atual do repo principal e então alterar), senão o commit do harness não pega o código e os gates pytest não rodam.

## Ideias adiadas

- Inferência automática de stack (composer.json, package.json).
- Plugin/adapter por linguagem.
- Substituir os gates literais do `tasks.md` (permanecem; verify.required é adicional e determinístico).
- Novo arquivo `harness.yml` na raiz do repo (usar `harness/config.yaml`).
- RAG / model router.
