from __future__ import annotations

from pathlib import Path

import pytest

from project_harness.commits import (
    apply_commit_plan,
    group_changed_paths,
    match_commits,
    parse_header,
    validate_message,
)
from project_harness.errors import HarnessError
from project_harness.git_manager import GitManager
from conftest import run
from test_git_manager import config


def test_validate_message_requires_module() -> None:
    with pytest.raises(HarnessError, match="type\\(module\\)"):
        validate_message("feat: add login")


def test_validate_message_accepts_project_types() -> None:
    validate_message("feat(user): add create-user use case")
    validate_message("test(user): cover create-user use case")
    assert parse_header("chore(harness): add stack catalog") == ("chore", "harness")


def test_scope_is_module_not_layer_folder() -> None:
    groups = group_changed_paths(
        (
            "app/Modules/User/Domain/Entities/User.php",
            "app/Modules/User/Application/CreateUser.php",
            "app/Modules/User/Infra/Http/Controllers/UserController.php",
            "tests/Feature/User/CreateUserTest.php",
            "resources/js/Pages/User/Create.jsx",
        )
    )
    assert [(item.kind, item.module) for item in groups] == [
        ("code", "user"),
        ("test", "user"),
    ]
    assert "application" not in {item.module for item in groups}
    assert "infra" not in {item.module for item in groups}
    assert "feature" not in {item.module for item in groups}


def test_outside_module_uses_area_name() -> None:
    groups = group_changed_paths(
        (
            "harness/src/project_harness/stack.py",
            "app/Http/Kernel.php",
            "database/migrations/0001_create_users_table.php",
        )
    )
    assert [(item.kind, item.module) for item in groups] == [
        ("code", "database"),
        ("code", "harness"),
        ("code", "http"),
    ]


def test_groups_split_module_and_tests() -> None:
    groups = group_changed_paths(
        (
            "app/Modules/User/Application/CreateUser.php",
            "tests/Unit/User/CreateUserTest.php",
            "docs/architecture.md",
        )
    )
    assert [(item.kind, item.module) for item in groups] == [
        ("code", "user"),
        ("test", "user"),
        ("meta", "docs"),
    ]


def test_specs_check_history_under_tests_is_meta_not_product_test() -> None:
    groups = group_changed_paths(
        (
            ".specs/tasks/0001-foo/review/review-01.md",
            ".specs/tasks/0001-foo/tests/checks-01.md",
        )
    )
    assert [(item.kind, item.module) for item in groups] == [("meta", "specs")]
    assert groups[0].paths == (
        ".specs/tasks/0001-foo/review/review-01.md",
        ".specs/tasks/0001-foo/tests/checks-01.md",
    )


def test_match_requires_separate_test_commit() -> None:
    paths = (
        "app/Modules/User/Application/CreateUser.php",
        "tests/Unit/User/CreateUserTest.php",
    )
    with pytest.raises(HarnessError, match="testes"):
        match_commits(paths, ("feat(user): add create-user use case",))


def test_match_splits_production_and_tests() -> None:
    prepared = match_commits(
        (
            "app/Modules/User/Application/CreateUser.php",
            "tests/Unit/User/CreateUserTest.php",
        ),
        (
            "feat(user): add create-user use case",
            "test(user): cover create-user use case",
        ),
    )
    assert [item.message for item in prepared] == [
        "feat(user): add create-user use case",
        "test(user): cover create-user use case",
    ]


def test_apply_commit_plan_creates_two_commits(git_repo: Path, tmp_path: Path, monkeypatch) -> None:
    monkeypatch.setenv("HARNESS_TEST_WORKTREES", str(tmp_path / "worktrees"))
    manager = GitManager(git_repo, config(tmp_path))
    worktree, branch, _ = manager.create_worktree("0004", "split-commits", "feature/challenge")
    module = worktree / "app" / "Modules" / "User" / "Application"
    tests = worktree / "tests" / "Unit" / "User"
    module.mkdir(parents=True)
    tests.mkdir(parents=True)
    (module / "CreateUser.php").write_text("<?php\n", encoding="utf-8")
    (tests / "CreateUserTest.php").write_text("<?php\n", encoding="utf-8")
    apply_commit_plan(
        manager,
        worktree,
        (
            "feat(user): add create-user use case",
            "test(user): cover create-user use case",
        ),
    )
    log = run(worktree, "git", "log", "--format=%s", "feature/challenge..HEAD")
    assert log.splitlines() == [
        "test(user): cover create-user use case",
        "feat(user): add create-user use case",
    ]
    assert run(worktree, "git", "status", "--porcelain") == ""
    _ = branch


def test_oversized_same_folder_is_chunked() -> None:
    paths = tuple(f"harness/src/project_harness/mod_{index:02d}.py" for index in range(9))
    groups = group_changed_paths(paths)
    assert all(item.module == "harness" and item.kind == "code" for item in groups)
    assert [len(item.paths) for item in groups] == [8, 1]
    assert all(len(item.paths) <= 8 for item in groups)


def test_oversized_module_splits_by_subdirectory() -> None:
    paths = tuple(
        [f"harness/src/project_harness/a_{index}.py" for index in range(5)]
        + [f"harness/skills/foo/file_{index}.md" for index in range(5)]
    )
    groups = group_changed_paths(paths)
    assert [(item.kind, item.module, item.area) for item in groups] == [
        ("code", "harness", "harness/skills/foo"),
        ("code", "harness", "harness/src/project_harness"),
    ]
    assert all(len(item.paths) <= 8 for item in groups)


def test_match_requires_one_message_per_oversized_chunk() -> None:
    paths = tuple(f"harness/src/project_harness/mod_{index:02d}.py" for index in range(9))
    with pytest.raises(HarnessError, match="no máximo 8"):
        match_commits(paths, ("chore(harness): add core runtime",))
    prepared = match_commits(
        paths,
        (
            "chore(harness): add core runtime",
            "chore(harness): add remaining runtime modules",
        ),
    )
    assert len(prepared) == 2
    assert all(item.message.startswith("chore(harness):") for item in prepared)
