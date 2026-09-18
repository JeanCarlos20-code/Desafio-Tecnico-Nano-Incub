from __future__ import annotations

from pathlib import Path


def _commit_gate_section() -> str:
    text = (Path(__file__).resolve().parents[1] / "agents" / "harness.md").read_text(encoding="utf-8")
    _, rest = text.split("### `kind=human`, `gate=commit`", 1)
    return rest.split("### `gate=", 1)[0]


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
