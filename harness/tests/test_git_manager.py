from __future__ import annotations

from pathlib import Path
import subprocess

import pytest

from project_harness.config import HarnessConfig, ContextLimits, GitConfig, WorkflowConfig
from project_harness.errors import HarnessError
from project_harness.git_manager import GitManager
from conftest import run


def _ref_exists(cwd: Path, ref: str) -> bool:
    result = subprocess.run(
        ["git", "show-ref", "--verify", ref],
        cwd=cwd,
        text=True,
        capture_output=True,
        check=False,
    )
    return result.returncode == 0


def config(tmp_path: Path) -> HarnessConfig:
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


def test_worktree_commit_merge_cleanup(git_repo: Path, tmp_path: Path, monkeypatch) -> None:
    monkeypatch.setenv("HARNESS_TEST_WORKTREES", str(tmp_path / "worktrees"))
    manager = GitManager(git_repo, config(tmp_path))
    wt, branch, _ = manager.create_worktree("0001", "add-file", "feature/challenge")
    (wt / "new.txt").write_text("hello\n", encoding="utf-8")
    manager.commit(wt, "feat(test): add file")
    merged = manager.try_merge("feature/challenge", branch, "0001")
    assert merged.status == "merged"
    manager.cleanup(wt, branch)
    assert not wt.exists()
    assert (git_repo / "new.txt").read_text(encoding="utf-8") == "hello\n"


def test_merge_conflict_is_aborted_and_preserved(git_repo: Path, tmp_path: Path, monkeypatch) -> None:
    monkeypatch.setenv("HARNESS_TEST_WORKTREES", str(tmp_path / "worktrees"))
    manager = GitManager(git_repo, config(tmp_path))
    (git_repo / "shared.txt").write_text("base\n", encoding="utf-8")
    run(git_repo, "git", "add", "shared.txt")
    run(git_repo, "git", "commit", "-m", "chore: shared")
    wt, branch, _ = manager.create_worktree("0002", "conflict", "feature/challenge")
    (wt / "shared.txt").write_text("task\n", encoding="utf-8")
    manager.commit(wt, "fix(test): task change")
    (git_repo / "shared.txt").write_text("target\n", encoding="utf-8")
    run(git_repo, "git", "add", "shared.txt")
    run(git_repo, "git", "commit", "-m", "fix: target change")
    result = manager.try_merge("feature/challenge", branch, "0002")
    assert result.status == "conflict"
    assert "shared.txt" in result.conflicts
    assert wt.exists()
    assert run(git_repo, "git", "status", "--porcelain") == ""


def test_changed_paths_omit_gitignored_harness(git_repo: Path, tmp_path: Path, monkeypatch) -> None:
    monkeypatch.setenv("HARNESS_TEST_WORKTREES", str(tmp_path / "worktrees"))
    (git_repo / ".gitignore").write_text("/harness\n/vendor\n", encoding="utf-8")
    run(git_repo, "git", "add", ".gitignore")
    run(git_repo, "git", "commit", "-m", "chore: ignore harness")
    (git_repo / "harness").mkdir()
    (git_repo / "harness" / "stack.yml").write_text("name: catalog\n", encoding="utf-8")
    manager = GitManager(git_repo, config(tmp_path))
    primary_porcelain = manager.changed_paths(git_repo)
    assert not any(path == "harness" or path.startswith("harness/") for path in primary_porcelain)
    assert any(path == "harness" or path.startswith("harness/") for path in manager.ignored_paths(git_repo))
    worktree, _, _ = manager.create_worktree("0003", "ignore-layout", "feature/challenge")
    assert not (worktree / "harness" / "stack.yml").is_file()
    specs = worktree / ".specs" / "tasks" / "0003-x"
    specs.mkdir(parents=True)
    (specs / "spec.md").write_text("spec\n", encoding="utf-8")
    (worktree / "harness").mkdir()
    (worktree / "harness" / "x.py").write_text("print(1)\n", encoding="utf-8")
    (worktree / "vendor").mkdir()
    (worktree / "vendor" / "autoload.php").write_text("<?php\n", encoding="utf-8")
    porcelain = manager.changed_paths(worktree)
    ignored = manager.ignored_paths(worktree)
    assert any(path.startswith(".specs/") for path in porcelain)
    assert not any(path == "harness" or path.startswith("harness/") for path in porcelain)
    assert any(path == "harness" or path.startswith("harness/") for path in ignored)
    assert any(path == "vendor" or path.startswith("vendor/") for path in ignored)


def test_cleanup_deletes_unmerged_worktree_and_branch(git_repo: Path, tmp_path: Path, monkeypatch) -> None:
    monkeypatch.setenv("HARNESS_TEST_WORKTREES", str(tmp_path / "worktrees"))
    manager = GitManager(git_repo, config(tmp_path))
    target_head = manager.head("feature/challenge")
    wt, branch, _ = manager.create_worktree("0004", "cancel-cleanup", "feature/challenge")
    (wt / "task-only.txt").write_text("unmerged\n", encoding="utf-8")
    manager.commit(wt, "feat(test): unmerged task file")
    (wt / "scratch.txt").write_text("uncommitted\n", encoding="utf-8")
    manager.cleanup(wt, branch, delete_unmerged=True)
    assert not wt.exists()
    assert not _ref_exists(git_repo, f"refs/heads/{branch}")
    assert manager.head("feature/challenge") == target_head
    assert not (git_repo / "task-only.txt").exists()
    assert not (git_repo / "scratch.txt").exists()


def test_cleanup_without_unmerged_flag_keeps_safe_branch_delete(
    git_repo: Path, tmp_path: Path, monkeypatch
) -> None:
    monkeypatch.setenv("HARNESS_TEST_WORKTREES", str(tmp_path / "worktrees"))
    manager = GitManager(git_repo, config(tmp_path))
    wt, branch, _ = manager.create_worktree("0005", "safe-delete", "feature/challenge")
    (wt / "task-only.txt").write_text("unmerged\n", encoding="utf-8")
    manager.commit(wt, "feat(test): unmerged task file")
    monkeypatch.setenv("LC_ALL", "C")
    with pytest.raises(HarnessError, match="not fully merged"):
        manager.cleanup(wt, branch)
    assert _ref_exists(git_repo, f"refs/heads/{branch}")
    assert not (git_repo / "task-only.txt").exists()
