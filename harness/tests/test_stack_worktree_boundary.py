from __future__ import annotations

from pathlib import Path

from project_harness.checks import CheckRunner
from project_harness.config import ContextLimits, GitConfig, HarnessConfig, WorkflowConfig
from project_harness.git_manager import GitManager
from project_harness.stack import (
    Command,
    Component,
    Infrastructure,
    Project,
    ProjectStack,
    merge_verify_paths,
    select_affected_components,
)
from conftest import run


def _config(tmp_path: Path) -> HarnessConfig:
    return HarnessConfig(
        version=1,
        workflow=WorkflowConfig(
            specs_root=Path(".specs/tasks"),
            docs_root=Path("docs"),
            max_repair_rounds=3,
            context_limits=ContextLimits(12000, 5000, 10000),
            git=GitConfig("harness/", True, "HARNESS_TEST_WORKTREES"),
        ),
    )


def _component(*, name: str, root: str, commands: dict[str, str]) -> Component:
    return Component(
        name=name,
        root=root,
        languages=(),
        frameworks=(),
        data_access=(),
        testing=(),
        commands={key: Command(command=value) for key, value in commands.items()},
    )


def _stack() -> ProjectStack:
    return ProjectStack(
        project=Project(
            name="painel",
            type="monolith",
            languages=(),
            frameworks=(),
            data_access=(),
            testing=(),
        ),
        components=(
            _component(
                name="app",
                root=".",
                commands={"test": "python3 -c 'print(\"RAN_APP\")'"},
            ),
            _component(
                name="harness",
                root="harness",
                commands={"test": "python3 -c 'print(\"RAN_HARNESS\")'"},
            ),
        ),
        infrastructure=Infrastructure(databases=(), cache=(), queues=()),
    )


def _ignored_worktree(git_repo: Path, tmp_path: Path, monkeypatch) -> tuple[GitManager, Path]:
    monkeypatch.setenv("HARNESS_TEST_WORKTREES", str(tmp_path / "worktrees"))
    (git_repo / ".gitignore").write_text("/harness\n/vendor\n", encoding="utf-8")
    run(git_repo, "git", "add", ".gitignore")
    run(git_repo, "git", "commit", "-m", "chore: ignore harness")
    manager = GitManager(git_repo, _config(tmp_path))
    worktree, _, _ = manager.create_worktree("0010", "stack-boundary", "feature/challenge")
    return manager, worktree


def test_worktree_without_tracked_stack_selects_harness_from_ignored_porcelain(
    git_repo: Path, tmp_path: Path, monkeypatch
) -> None:
    manager, worktree = _ignored_worktree(git_repo, tmp_path, monkeypatch)
    assert not (worktree / "harness" / "stack.yml").is_file()
    (worktree / "harness").mkdir()
    (worktree / "harness" / "x.py").write_text("print(1)\n", encoding="utf-8")
    porcelain = manager.changed_paths(worktree)
    ignored = manager.ignored_paths(worktree)
    assert not any(path == "harness" or path.startswith("harness/") for path in porcelain)
    stack = _stack()
    paths = merge_verify_paths(stack.components, porcelain, ignored)
    affected = select_affected_components(stack.components, paths)
    assert [item.name for item in affected] == ["harness"]
    results = CheckRunner().run_verify(worktree, stack, paths, ("test",))
    assert [item.id for item in results] == ["harness:test"]
    assert "RAN_HARNESS" in results[0].stdout
    assert "RAN_APP" not in results[0].stdout


def test_worktree_specs_change_without_harness_dir_selects_app_only(
    git_repo: Path, tmp_path: Path, monkeypatch
) -> None:
    manager, worktree = _ignored_worktree(git_repo, tmp_path, monkeypatch)
    specs = worktree / ".specs" / "tasks" / "0010-x"
    specs.mkdir(parents=True)
    (specs / "spec.md").write_text("spec\n", encoding="utf-8")
    (worktree / "vendor").mkdir()
    (worktree / "vendor" / "autoload.php").write_text("<?php\n", encoding="utf-8")
    porcelain = manager.changed_paths(worktree)
    ignored = manager.ignored_paths(worktree)
    stack = _stack()
    paths = merge_verify_paths(stack.components, porcelain, ignored)
    affected = select_affected_components(stack.components, paths)
    assert [item.name for item in affected] == ["app"]
    results = CheckRunner().run_verify(worktree, stack, paths, ("test",))
    assert [item.id for item in results] == ["app:test"]
    assert "RAN_APP" in results[0].stdout
