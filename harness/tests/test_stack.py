from __future__ import annotations

from dataclasses import fields, is_dataclass
from pathlib import Path

import pytest

from project_harness.errors import HarnessError
from project_harness.stack import (
    Command,
    Component,
    Infrastructure,
    Project,
    ProjectStack,
    StackLoader,
)


VALID_YAML = """
project:
  name: example-app
  type: monolith
  languages:
    - go
    - typescript
  frameworks:
    - angular
  data_access:
    - postgres
  testing:
    - pest
components:
  - name: backend
    root: backend
    languages:
      - go
    frameworks: []
    data_access:
      - postgres
    testing:
      - pest
    commands:
      test:
        command: go test ./...
      foo:
        command: echo foo
  - name: frontend
    root: frontend
    languages:
      - typescript
    frameworks:
      - angular
    data_access: []
    testing: []
    commands:
      test:
        command: ng test
infrastructure:
  databases: []
  cache: []
  queues: []
"""


def write_stack(root: Path, content: str = VALID_YAML) -> Path:
    path = root / "harness" / "stack.yml"
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")
    return path


def test_valid_stack_returns_project_stack(tmp_path: Path) -> None:
    write_stack(tmp_path)
    stack = StackLoader().load(tmp_path)
    assert isinstance(stack, ProjectStack)
    assert isinstance(stack.project, Project)
    assert stack.project.name == "example-app"
    assert stack.project.type == "monolith"
    assert len(stack.components) >= 1
    assert isinstance(stack.infrastructure, Infrastructure)
    backend = next(item for item in stack.components if item.name == "backend")
    assert "test" in backend.commands
    assert isinstance(backend.commands["test"], Command)
    assert backend.commands["test"].command == "go test ./..."


def test_multiple_components_are_preserved(tmp_path: Path) -> None:
    write_stack(tmp_path)
    stack = StackLoader().load(tmp_path)
    by_name = {item.name: item for item in stack.components}
    assert set(by_name) == {"backend", "frontend"}
    assert by_name["backend"].root == "backend"
    assert by_name["backend"].languages == ("go",)
    assert by_name["backend"].frameworks == ()
    assert by_name["backend"].testing == ("pest",)
    assert set(by_name["backend"].commands) == {"test", "foo"}
    assert by_name["frontend"].root == "frontend"
    assert by_name["frontend"].languages == ("typescript",)
    assert by_name["frontend"].frameworks == ("angular",)
    assert by_name["frontend"].testing == ()
    assert set(by_name["frontend"].commands) == {"test"}


def test_empty_infrastructure_lists_load_as_empty_sequences(tmp_path: Path) -> None:
    write_stack(tmp_path)
    stack = StackLoader().load(tmp_path)
    assert stack.infrastructure.databases == ()
    assert stack.infrastructure.cache == ()
    assert stack.infrastructure.queues == ()


def test_custom_command_id_is_accepted(tmp_path: Path) -> None:
    write_stack(tmp_path)
    stack = StackLoader().load(tmp_path)
    backend = next(item for item in stack.components if item.name == "backend")
    assert "foo" in backend.commands
    assert backend.commands["foo"].command == "echo foo"
    commands_type = str(Component.__annotations__.get("commands", ""))
    assert "Enum" not in commands_type
    assert "dict[str, Command]" in commands_type or "Mapping[str, Command]" in commands_type


def test_invalid_yaml_raises_harness_error(tmp_path: Path) -> None:
    write_stack(tmp_path, ": [")
    with pytest.raises(HarnessError):
        StackLoader().load(tmp_path)


def test_non_object_root_raises_harness_error(tmp_path: Path) -> None:
    write_stack(tmp_path, "- just a list\n")
    with pytest.raises(HarnessError):
        StackLoader().load(tmp_path)


def test_missing_file_raises_harness_error_mentioning_stack_yml(tmp_path: Path) -> None:
    with pytest.raises(HarnessError, match="stack.yml") as caught:
        StackLoader().load(tmp_path)
    assert "stack.yml" in str(caught.value)


def test_stack_models_and_loader_do_not_use_any() -> None:
    token = "An" + "y"
    root = Path(__file__).resolve().parents[1]
    text = (root / "src" / "project_harness" / "stack.py").read_text(encoding="utf-8")
    assert token not in text
    for model in (Project, Component, Command, Infrastructure, ProjectStack):
        assert is_dataclass(model)
        assert model.__dataclass_params__.frozen is True
        for item in fields(model):
            assert token not in str(item.type)
