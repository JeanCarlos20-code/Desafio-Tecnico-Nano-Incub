from __future__ import annotations

from pathlib import Path

import pytest

from project_harness.artifacts import ArtifactService
from project_harness.checks import CheckRunner
from project_harness.config import load_config
from project_harness.errors import HarnessError
from project_harness.stack import (
    Command,
    Component,
    Infrastructure,
    Project,
    ProjectStack,
    component_cwd,
    merge_verify_paths,
    select_affected_components,
)
from project_harness.types import GateSpec


def _component(
    *,
    name: str,
    root: str,
    commands: dict[str, str],
) -> Component:
    return Component(
        name=name,
        root=root,
        languages=(),
        frameworks=(),
        data_access=(),
        testing=(),
        commands={key: Command(command=value) for key, value in commands.items()},
    )


def test_component_command_uses_component_root_as_cwd(tmp_path: Path) -> None:
    backend = tmp_path / "backend"
    backend.mkdir()
    component = _component(
        name="backend",
        root="backend",
        commands={"test": "python3 -c 'import os; print(os.getcwd())'"},
    )
    result = CheckRunner().run_component_command(tmp_path, component, "test")
    assert result.exit_code == 0
    assert Path(result.stdout.strip()).resolve() == backend.resolve()
    assert str(Path(result.stdout.strip())).endswith("backend")


def test_custom_command_id_runs_yaml_string_and_prints_cwd(tmp_path: Path) -> None:
    service = tmp_path / "service"
    service.mkdir()
    component = _component(
        name="service",
        root="service",
        commands={"foo": "python3 -c 'import os; print(\"CUSTOM_FOO:\" + os.getcwd())'"},
    )
    result = CheckRunner().run_component_command(tmp_path, component, "foo")
    assert result.exit_code == 0
    assert "CUSTOM_FOO:" in result.stdout
    assert str(component_cwd(tmp_path, component)) in result.stdout
    assert result.command == "python3 -c 'import os; print(\"CUSTOM_FOO:\" + os.getcwd())'"


def _stack(*components: Component) -> ProjectStack:
    return ProjectStack(
        project=Project(
            name="fixture",
            type="monolith",
            languages=(),
            frameworks=(),
            data_access=(),
            testing=(),
        ),
        components=components,
        infrastructure=Infrastructure(databases=(), cache=(), queues=()),
    )


def test_missing_required_id_raises_harness_error_with_component_and_id(tmp_path: Path) -> None:
    (tmp_path / "backend").mkdir()
    component = _component(name="backend", root="backend", commands={"test": "true"})
    with pytest.raises(HarnessError, match="backend") as caught:
        CheckRunner().run_verify(
            tmp_path,
            _stack(component),
            ("backend/app.py",),
            ("lint",),
        )
    message = str(caught.value)
    assert "lint" in message
    assert "backend" in message


def test_longest_prefix_selects_harness_over_root() -> None:
    app = _component(name="app", root=".", commands={"test": "true"})
    harness = _component(name="harness", root="harness", commands={"test": "true"})
    affected = select_affected_components((app, harness), ("harness/x.py",))
    assert [item.name for item in affected] == ["harness"]


def test_merge_verify_paths_selects_ignored_harness_not_catch_all() -> None:
    app = _component(name="app", root=".", commands={"test": "true"})
    harness = _component(name="harness", root="harness", commands={"test": "true"})
    merged = merge_verify_paths(
        (app, harness),
        porcelain=(".specs/tasks/0001/spec.md",),
        ignored=("harness/x.py", "vendor/autoload.php", ".env"),
    )
    affected = select_affected_components((app, harness), merged)
    assert [item.name for item in affected] == ["app", "harness"]
    assert "harness/x.py" in merged
    assert "vendor/autoload.php" not in merged
    assert ".env" not in merged


def test_merge_verify_paths_without_porcelain_still_selects_ignored_harness() -> None:
    app = _component(name="app", root=".", commands={"test": "true"})
    harness = _component(name="harness", root="harness", commands={"test": "true"})
    merged = merge_verify_paths(
        (app, harness),
        porcelain=(),
        ignored=("harness/stack.yml",),
    )
    affected = select_affected_components((app, harness), merged)
    assert [item.name for item in affected] == ["harness"]


def test_merge_verify_paths_ignored_only_under_dot_does_not_select_app() -> None:
    app = _component(name="app", root=".", commands={"test": "true"})
    harness = _component(name="harness", root="harness", commands={"test": "true"})
    merged = merge_verify_paths(
        (app, harness),
        porcelain=(),
        ignored=("vendor/foo.php", "node_modules/pkg/index.js"),
    )
    assert merged == ()
    assert select_affected_components((app, harness), merged) == ()


def test_nonzero_exit_is_check_result_failure_not_harness_error(tmp_path: Path) -> None:
    component = _component(name="app", root=".", commands={"test": "false"})
    result = CheckRunner().run_component_command(tmp_path, component, "test")
    assert result.passed is False
    assert result.exit_code != 0


def test_stack_verify_results_are_appended_to_validation_md(
    tmp_path: Path, harness_source: Path
) -> None:
    (tmp_path / "backend").mkdir()
    component = _component(
        name="backend",
        root="backend",
        commands={"test": "python3 -c 'print(\"stack-verify-ok\")'"},
    )
    results = CheckRunner().run_verify(
        tmp_path,
        _stack(component),
        ("backend/main.py",),
        ("test",),
    )
    rendered = CheckRunner.render(results)
    task_dir = tmp_path / "task"
    task_dir.mkdir()
    ArtifactService(load_config(harness_source.parent)).append_validation_checks(task_dir, rendered)
    text = (task_dir / "validation.md").read_text(encoding="utf-8")
    assert "<!-- harness-checks:start -->" in text
    assert "<!-- harness-checks:end -->" in text
    assert results[0].command in text


def test_verify_required_runs_only_on_affected_component(tmp_path: Path) -> None:
    (tmp_path / "backend").mkdir()
    (tmp_path / "frontend").mkdir()
    backend = _component(
        name="backend",
        root="backend",
        commands={"test": "python3 -c 'print(\"RAN_BACKEND\")'"},
    )
    frontend = _component(
        name="frontend",
        root="frontend",
        commands={"test": "python3 -c 'print(\"RAN_FRONTEND\")'"},
    )
    results = CheckRunner().run_verify(
        tmp_path,
        _stack(backend, frontend),
        ("backend/service.py",),
        ("test",),
    )
    assert len(results) == 1
    assert results[0].id == "backend:test"
    assert "RAN_BACKEND" in results[0].stdout
    assert "RAN_FRONTEND" not in results[0].stdout


def test_empty_affected_set_skips_stack_commands(tmp_path: Path) -> None:
    component = _component(name="backend", root="backend", commands={"test": "false"})
    results = CheckRunner().run_verify(
        tmp_path,
        _stack(component),
        ("docs/readme.md",),
        ("test",),
    )
    assert results == ()


def test_omitted_verify_required_is_empty_tuple(tmp_path: Path) -> None:
    config_path = tmp_path / "harness" / "config.yaml"
    config_path.parent.mkdir(parents=True)
    config_path.write_text(
        "version: 1\n"
        "workflow:\n"
        "  specs_root: .specs/tasks\n"
        "  docs_root: docs\n"
        "  max_repair_rounds: 3\n"
        "  context_limits:\n"
        "    context_md_tokens: 12000\n"
        "    spec_md_tokens: 5000\n"
        "    tasks_md_tokens: 10000\n"
        "  git:\n"
        "    task_branch_prefix: harness/\n"
        "    merge_no_ff: true\n"
        "    worktree_root_env: HARNESS_WORKTREE_ROOT\n",
        encoding="utf-8",
    )
    config = load_config(tmp_path)
    assert config.verify.required == ()


def test_same_command_id_runs_once_per_affected_component(tmp_path: Path) -> None:
    (tmp_path / "backend").mkdir()
    (tmp_path / "frontend").mkdir()
    backend = _component(name="backend", root="backend", commands={"test": "true"})
    frontend = _component(name="frontend", root="frontend", commands={"test": "true"})
    results = CheckRunner().run_verify(
        tmp_path,
        _stack(backend, frontend),
        ("backend/a.py", "frontend/b.ts"),
        ("test",),
    )
    assert [item.id for item in results] == ["backend:test", "frontend:test"]


def test_component_root_escape_raises_harness_error(tmp_path: Path) -> None:
    component = _component(name="evil", root="..", commands={"test": "true"})
    with pytest.raises(HarnessError, match="escapa"):
        CheckRunner().run_component_command(tmp_path, component, "test")


def test_checks_keep_task_gates_and_append_stack_verify(tmp_path: Path) -> None:
    (tmp_path / "backend").mkdir()
    runner = CheckRunner()
    gates = (GateSpec(id="unit", command="python3 -c 'print(\"GATE_OK\")'", required=True),)
    component = _component(
        name="backend",
        root="backend",
        commands={"test": "python3 -c 'print(\"STACK_OK\")'"},
    )
    gate_results = runner.run(tmp_path, gates)
    stack_results = runner.run_verify(
        tmp_path,
        _stack(component),
        ("backend/x.py",),
        ("test",),
    )
    combined = gate_results + stack_results
    assert [item.id for item in combined] == ["unit", "backend:test"]
    assert "GATE_OK" in combined[0].stdout
    assert "STACK_OK" in combined[1].stdout


def test_project_config_lists_verify_required_ids(harness_source: Path) -> None:
    config = load_config(harness_source.parent)
    assert config.verify.required == ("test", "lint", "build")
