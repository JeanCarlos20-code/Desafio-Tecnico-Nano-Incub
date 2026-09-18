# Validation — Repair 0001

Repair local pronto para os checks do harness. Este arquivo não declara o gate final.

## O que foi reparado

- **ARCH-001**: `PacketService.plan()` e `HarnessGraph._checks` carregam `harness/stack.yml` da raiz primária (`self.root`), o mesmo boundary de `load_config()`. Worktree criada só com arquivos rastreados continua sem o catálogo gitignored; o resumo da stack no packet de PLAN não depende desse checkout.
- **ARCH-002**: `changed_paths` permanece sem ignored (dirty/commit/merge). O matching de verify junta porcelain com `ignored_paths` filtrados por `merge_verify_paths`: entra path ignored só quando casa com um `component.root` específico (não o catch-all `.`). O componente `harness` passa a ser selecionado mesmo com `/harness` no gitignore; `vendor`/`.env` não forçam `app`.
- **TEST-001**: testes do layout real (`/harness` ignorado, worktree sem `stack.yml` rastreado, porcelain omitindo harness/).
- Transições LangGraph inalteradas. Comandos continuam com `cwd = worktree / component.root`.

## Testes criados/alterados

| Arquivo | Papel |
| ------- | ----- |
| `harness/tests/test_packets.py` | PLAN carrega o catálogo da raiz primária quando a worktree não tem `stack.yml` |
| `harness/tests/test_stack_verify.py` | `merge_verify_paths`: ignored `harness/` seleciona harness; ignored `vendor`/`.env` não seleciona app |
| `harness/tests/test_git_manager.py` | porcelain omite `/harness`; `ignored_paths` lista o catálogo gitignored |
| `harness/tests/test_stack_worktree_boundary.py` | worktree com gitignore do repo: harness ignored → `harness:test`; só `.specs` → `app:test` |

## Arquivos extras (fora da lista de tasks.md)

| Arquivo | Razão |
| ------- | ----- |
| `harness/src/project_harness/git_manager.py` | `ignored_paths` para o matching de verify sem sujar dirty/commit |
| `harness/tests/test_git_manager.py` | regressão porcelain vs ignored |
| `harness/tests/test_stack_worktree_boundary.py` | integração gitignore + worktree + CHECKS matching |

Gate local (Repair, cwd=worktree): `python3 -m pytest harness/tests -q` → 47 passed. O harness reexecuta os gates determinísticos ao completar a fase.

## Leituras adicionais

`GitManager.create_worktree` / `changed_paths`, `.gitignore` `/harness`, `load_config(root)` como boundary do catálogo. Skill de segurança: core Python CLI; nenhuma referência Flask/Django/FastAPI/Laravel aplicável. YAML continua via `safe_load`; cwd recusa escape; `changed_paths` de dirty/commit não passou a incluir ignored.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `python3 -m pytest harness/tests -q --ignore=harness/tests/test_graph_e2e.py` — exit=0 (required)
- ✅ `python3 -m pytest harness/tests -q` — exit=0 (required)
- ✅ `python3 -m pytest harness/tests/test_no_any.py -q` — exit=0 (required)
<!-- harness-checks:end -->
