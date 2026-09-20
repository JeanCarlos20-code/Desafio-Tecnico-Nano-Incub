from __future__ import annotations

from pathlib import Path


def _harness_md() -> str:
    return (Path(__file__).resolve().parents[1] / "agents" / "harness.md").read_text(encoding="utf-8")


def _inicio_section() -> str:
    _, rest = _harness_md().split("## Início", 1)
    return rest.split("## Como tratar actions", 1)[0]


def _plan_gate_section() -> str:
    _, rest = _harness_md().split("### `kind=human`, `gate=plan`", 1)
    return rest.split("### `kind=human`, `gate=commit`", 1)[0]


def _commit_gate_section() -> str:
    _, rest = _harness_md().split("### `kind=human`, `gate=commit`", 1)
    return rest.split("### `gate=", 1)[0]


def test_planner_instructs_no_new_tests_sentence() -> None:
    text = (Path(__file__).resolve().parents[1] / "agents" / "plan.md").read_text(encoding="utf-8")
    assert "sem testes para esse plano pois ele é apenas" in text
    assert "Não invente cobertura vazia" in text


def test_orchestrator_plan_gate_pastes_summary_and_asks_approval() -> None:
    section = _plan_gate_section()
    lower = section.lower()
    assert "action.summary" in section
    assert "íntegra" in lower
    assert "não reescreva" in lower
    assert "não corte" in lower
    assert "## Plano" in section
    assert "## Testes pontuais" in section
    assert "## Comandos após o Execute" in section
    assert "aprova o plano" in lower
    assert "não implemente" in lower
    assert "harness.commits" in section


def test_orchestrator_inicio_checks_open_human_gate_before_start() -> None:
    section = _inicio_section()
    lower = section.lower()
    assert "kind=human" in section
    assert ".git/harness/actions" in section
    assert "harness task action" in section
    assert "não" in lower
    assert "harness task start" in section
    assert "classifique" in lower
    assert "relato solto" in lower


def test_orchestrator_keeps_wait_revise_and_no_start_on_loose_report() -> None:
    inicio = _inicio_section().lower()
    commit = _commit_gate_section()
    addition = commit.split("**Adição de escopo**", 1)[1].split("**Substituição de escopo**", 1)[0]
    replacement = commit.split("**Substituição de escopo**", 1)[1].split("**Recusa**", 1)[0]
    local = commit.split("**Ajuste local**", 1)[1].split("**Adição de escopo**", 1)[0]
    assert "espere" in inicio
    assert "espere" in addition.lower()
    assert "espere" in replacement.lower()
    assert "harness task revise-code" in local
    assert "imediatamente" in local.lower()
    assert "relato solto" in inicio
    assert "não chame `harness task start`" in inicio or "não chame `task start`" in inicio


def test_orchestrator_suggests_only_when_redo_or_add_another_task() -> None:
    section = _commit_gate_section()
    intro = section.split("**Ajuste local**", 1)[0].lower()
    assert "sugestão só" in intro
    assert "refizer a tarefa" in intro or "refaz a tarefa" in intro
    assert "adicionar outra tarefa" in intro
    assert "imediatamente" in intro


def test_orchestrator_instructs_merge_then_start_on_commit_gate_scope_addition() -> None:
    addition = _commit_gate_section().split("**Adição de escopo**", 1)[1].split("**Substituição de escopo**", 1)[0]
    assert "harness task approve-commit" in addition
    assert "harness task start" in addition
    assert "harness task cancel" not in addition
    assert "pedido adicional" in addition.lower()
    assert "não cancela" in addition.lower() or "não cancele" in addition.lower()


def test_orchestrator_instructs_cancel_then_start_on_commit_gate_scope_replacement() -> None:
    replacement = _commit_gate_section().split("**Substituição de escopo**", 1)[1].split("**Recusa**", 1)[0]
    assert "harness task cancel" in replacement
    assert "harness task start" in replacement
    assert "harness task approve-commit" not in replacement
    assert "não mergeia" in replacement.lower() or "não mergeie" in replacement.lower()


def test_orchestrator_keeps_revise_code_for_local_and_explicit_refuse() -> None:
    section = _commit_gate_section()
    local = section.split("**Ajuste local**", 1)[1].split("**Adição de escopo**", 1)[0]
    refuse = section.split("**Recusa**", 1)[1].split("**Incerteza:**", 1)[0]
    assert "harness task revise-code" in local
    assert "imediatamente" in local.lower()
    assert "não sugira" in local.lower()
    assert "layout" in local.lower()
    assert "popup" in local.lower()
    assert "harness task revise-code" in refuse


def test_orchestrator_starts_new_task_with_fresh_workers_without_forwarding() -> None:
    section = _commit_gate_section().lower()
    assert "worker fresco" in section
    assert "não encaminhe" in section
    assert "conversa anterior" in section


def test_orchestrator_does_not_default_uncertain_addition_versus_replacement_to_cancel() -> None:
    uncertainty = _commit_gate_section().split("**Incerteza:**", 1)[1].lower()
    assert "não use cancel como padrão" in uncertainty
    assert "apresente as duas" in uncertainty
    assert "local vs mudança de escopo" not in uncertainty
    assert "ajuste simples" in uncertainty
    assert "sugestão só" in uncertainty
    assert "outra tarefa" in uncertainty
