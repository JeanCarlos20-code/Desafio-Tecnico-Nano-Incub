from __future__ import annotations

from pathlib import Path

from project_harness.config import ContextLimits, GitConfig, HarnessConfig, WorkflowConfig
from project_harness.task_store import TaskStore
from project_harness.utils import slugify
from conftest import run


def config() -> HarnessConfig:
    return HarnessConfig(
        version=1,
        workflow=WorkflowConfig(
            specs_root=Path('.specs/tasks'),
            docs_root=Path('docs'),
            max_repair_rounds=3,
            context_limits=ContextLimits(12000, 5000, 10000),
            git=GitConfig('harness/', True, 'HARNESS_TEST_WORKTREES'),
        ),
    )


def test_task_ids_are_reserved_atomically(git_repo: Path) -> None:
    store_a = TaskStore(git_repo, config())
    store_b = TaskStore(git_repo, config())
    first = store_a.reserve_next_id()
    second = store_b.reserve_next_id()
    assert first == '0001'
    assert second == '0002'
    store_a.release_reservation(first)
    store_b.release_reservation(second)


def test_slugify_keeps_portuguese_words_readable() -> None:
    assert slugify('Criação de reserva e autenticação') == 'criacao-de-reserva-e-autenticacao'
