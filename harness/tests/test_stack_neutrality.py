from __future__ import annotations

from pathlib import Path
import ast
import re

from project_harness.stack import StackLoader


TECH = {"php", "laravel", "go", "angular", "pest"}
NAME_HINTS = {"language", "languages", "framework", "frameworks"}


def test_repo_stack_yml_describes_app_and_harness(harness_source: Path) -> None:
    stack = StackLoader().load(harness_source.parent)
    by_name = {item.name: item for item in stack.components}
    assert set(by_name) == {"app", "harness"}
    assert by_name["app"].root == "."
    assert "php" in by_name["app"].languages
    assert "typescript" in by_name["app"].languages
    assert "laravel" in by_name["app"].frameworks
    assert "eloquent" in by_name["app"].data_access
    assert "phpunit" in by_name["app"].testing
    assert "vitest" in by_name["app"].testing
    assert "playwright" in by_name["app"].testing
    assert by_name["harness"].root == "harness"
    assert "python" in by_name["harness"].languages
    assert "pytest" in by_name["harness"].testing
    for command_id in ("test", "lint", "build"):
        assert command_id in by_name["app"].commands
        assert command_id in by_name["harness"].commands
    assert "unit" in by_name["app"].commands
    assert "integration" in by_name["app"].commands
    assert "frontend" in by_name["app"].commands
    assert "e2e" in by_name["app"].commands
    assert "unit" in by_name["harness"].commands


def test_core_has_no_technology_control_flow(harness_source: Path) -> None:
    src = harness_source / "src" / "project_harness"
    language_if = re.compile(r"if\s+language\s*==")
    offenders: list[str] = []
    for path in src.rglob("*.py"):
        text = path.read_text(encoding="utf-8")
        relative = str(path.relative_to(harness_source))
        if language_if.search(text):
            offenders.append(f"{relative}: if language ==")
        tree = ast.parse(text)
        for node in ast.walk(tree):
            if not isinstance(node, ast.Compare):
                continue
            names = _names(node)
            constants = _string_constants(node)
            if names & NAME_HINTS and constants & TECH:
                offenders.append(f"{relative}:{node.lineno} tech branch")
    assert offenders == []


def _names(node: ast.Compare) -> set[str]:
    found: set[str] = set()
    for item in (node.left, *node.comparators):
        if isinstance(item, ast.Name):
            found.add(item.id.lower())
        if isinstance(item, ast.Attribute):
            found.add(item.attr.lower())
    return found


def _string_constants(node: ast.Compare) -> set[str]:
    found: set[str] = set()
    for item in (node.left, *node.comparators):
        if isinstance(item, ast.Constant) and isinstance(item.value, str):
            found.add(item.value.lower())
    return found
