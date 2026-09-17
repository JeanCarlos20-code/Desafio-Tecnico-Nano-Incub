from __future__ import annotations

from pathlib import Path

from project_harness.config import load_config
from project_harness.context_service import ContextService
from project_harness.git_manager import GitManager
from project_harness.packets import ContextPacket, PacketService
from project_harness.stack import StackLoader
from project_harness.task_store import TaskStore
from project_harness.types import TaskMeta
from conftest import run


STACK_YAML = """
project:
  name: acme-board
  type: monolith
  languages:
    - elixir
  frameworks:
    - phoenix
  data_access:
    - ecto
  testing:
    - exunit
components:
  - name: api
    root: apps/api
    languages:
      - elixir
    frameworks:
      - phoenix
    data_access:
      - ecto
    testing:
      - exunit
    commands:
      test:
        command: mix test
      lint:
        command: mix format --check-formatted
      build:
        command: mix compile
      foo:
        command: mix foo
  - name: worker
    root: apps/worker
    languages:
      - elixir
    frameworks: []
    data_access:
      - ecto
    testing:
      - exunit
    commands:
      test:
        command: mix test
infrastructure:
  databases:
    - mysql
  cache: []
  queues:
    - redis
"""

MIN_CONFIG = """
version: 1
workflow:
  specs_root: .specs/tasks
  docs_root: docs
  max_repair_rounds: 3
  context_limits:
    context_md_tokens: 12000
    spec_md_tokens: 5000
    tasks_md_tokens: 10000
  git:
    task_branch_prefix: harness/
    merge_no_ff: true
    worktree_root_env: HARNESS_WORKTREE_ROOT
"""


def test_plan_packet_includes_compact_stack_summary(git_repo: Path) -> None:
    harness_dir = git_repo / "harness"
    harness_dir.mkdir()
    (harness_dir / "config.yaml").write_text(MIN_CONFIG, encoding="utf-8")
    (harness_dir / "stack.yml").write_text(STACK_YAML, encoding="utf-8")
    config = load_config(git_repo)
    store = TaskStore(git_repo, config)
    context = ContextService(git_repo, config)
    packets = PacketService(git_repo, store, context)
    meta = TaskMeta(
        task_id="0009",
        slug="add-widget",
        request="add widget",
        target_branch="feature/challenge",
        base_commit="abc",
        task_branch="harness/0009-add-widget",
        worktree_path=git_repo,
        task_dir_relative=Path(".specs/tasks/0009-add-widget"),
        thread_id="thread-packets",
    )
    path = packets.plan(meta)
    text = path.read_text(encoding="utf-8")
    stack = StackLoader().load(git_repo)
    packet = ContextPacket(stack=stack, summary=text)
    stack_section = text.split("## Stack do projeto", 1)[1].split("## Encerramento", 1)[0]
    assert "acme-board" in stack_section
    assert "monolith" in stack_section
    assert "`api`" in stack_section
    assert "`worker`" in stack_section
    assert "`apps/api`" in stack_section
    assert "`apps/worker`" in stack_section
    assert "elixir" in stack_section
    assert "phoenix" in stack_section
    assert "ecto" in stack_section
    assert "exunit" in stack_section
    assert "command IDs:" in stack_section
    assert "foo" in stack_section
    assert "test" in stack_section
    assert "lint" in stack_section
    assert "build" in stack_section
    assert "mysql" in stack_section
    assert "redis" in stack_section
    assert "mix test" not in stack_section
    assert "php artisan" not in stack_section
    assert "npm test" not in stack_section
    assert "## User Stories" in text
    assert "## Acceptance Criteria" in text
    assert "harness task complete-phase 0009 --phase plan" in text
    assert "./harness/run" not in text
    assert "Pedido do usuário" in text


def test_repair_packet_points_at_latest_review_verdict_and_result(git_repo: Path) -> None:
    harness_dir = git_repo / "harness"
    harness_dir.mkdir()
    (harness_dir / "config.yaml").write_text(MIN_CONFIG, encoding="utf-8")
    (harness_dir / "stack.yml").write_text(STACK_YAML, encoding="utf-8")
    config = load_config(git_repo)
    store = TaskStore(git_repo, config)
    packets = PacketService(git_repo, store, ContextService(git_repo, config))
    meta = TaskMeta(
        task_id="0009",
        slug="add-widget",
        request="add widget",
        target_branch="feature/challenge",
        base_commit="abc",
        task_branch="harness/0009-add-widget",
        worktree_path=git_repo,
        task_dir_relative=Path(".specs/tasks/0009-add-widget"),
        thread_id="thread-packets",
    )
    history = git_repo / meta.task_dir_relative / "review"
    history.mkdir(parents=True)
    first = history / "review-01.md"
    latest = history / "review-02.md"
    first.write_text("old REJECTED\n", encoding="utf-8")
    latest.write_text("new APPROVED\n", encoding="utf-8")
    result = git_repo / "consolidated-round-2.json"
    result.write_text("{}", encoding="utf-8")
    text = packets.execute(
        meta,
        repair=True,
        feedback="fix the blocker",
        blocking_ids=("ARCH-001",),
        failed_checks=(),
        latest_review=latest,
        latest_verdict="REJECTED",
        latest_result=str(result),
    ).read_text(encoding="utf-8")
    assert str(latest) in text
    assert "REJECTED" in text
    assert str(result) in text
    assert "`review.md`" not in text
    assert "última review" in text


def test_plan_loads_stack_from_primary_when_worktree_lacks_gitignored_catalog(
    git_repo: Path, tmp_path: Path, monkeypatch
) -> None:
    (git_repo / ".gitignore").write_text("/harness\n", encoding="utf-8")
    run(git_repo, "git", "add", ".gitignore")
    run(git_repo, "git", "commit", "-m", "chore: ignore harness")
    harness_dir = git_repo / "harness"
    harness_dir.mkdir()
    (harness_dir / "config.yaml").write_text(MIN_CONFIG, encoding="utf-8")
    (harness_dir / "stack.yml").write_text(STACK_YAML, encoding="utf-8")
    monkeypatch.setenv("HARNESS_WORKTREE_ROOT", str(tmp_path / "worktrees"))
    config = load_config(git_repo)
    store = TaskStore(git_repo, config)
    git_manager = GitManager(git_repo, config)
    worktree, task_branch, base_commit = git_manager.create_worktree(
        "0009", "add-widget", "feature/challenge"
    )
    assert not (worktree / "harness" / "stack.yml").is_file()
    packets = PacketService(git_repo, store, ContextService(git_repo, config))
    meta = store.build_meta(
        task_id="0009",
        request="add widget",
        target_branch="feature/challenge",
        base_commit=base_commit,
        task_branch=task_branch,
        worktree_path=worktree,
    )
    text = packets.plan(meta).read_text(encoding="utf-8")
    assert "## Stack do projeto" in text
    assert "acme-board" in text
    assert "`api`" in text
    assert "foo" in text
