from __future__ import annotations

from pathlib import Path

import pytest

from project_harness.artifacts import ArtifactService, TEMPLATES
from project_harness.config import load_config
from project_harness.errors import HarnessError


def write_valid(task: Path) -> None:
    (task / "context.md").write_text("# Task Context\n\nAuth uses a repository interface.\n", encoding="utf-8")
    (task / "spec.md").write_text(
        "# Specification\n\n## User Stories\n\nAs a user I want to sign in.\n\n"
        "## Acceptance Criteria\n\n- AC-001 valid login returns success.\n",
        encoding="utf-8",
    )
    (task / "tasks.md").write_text(
        "---\n"
        "harness:\n"
        "  commit_message: \"feat(auth): add login\"\n"
        "  gates:\n"
        "    - id: unit\n"
        "      command: \"python -m pytest\"\n"
        "      required: true\n"
        "---\n\n"
        "# Implementation Plan\n\n## Considered Approaches\n\nA and B; selected A.\n\n"
        "## Planned Tests\n\nUT-001 covers AC-001.\n\n"
        "## Required Gates\n\npytest.\n",
        encoding="utf-8",
    )


def test_templates_use_english_headings() -> None:
    assert "# Task Context" in TEMPLATES["context.md"]
    assert "## User Stories" in TEMPLATES["spec.md"]
    assert "## Acceptance Criteria" in TEMPLATES["spec.md"]
    assert "## Planned Tests" in TEMPLATES["tasks.md"]
    assert "## Required Gates" in TEMPLATES["tasks.md"]
    assert "## Verdict" in TEMPLATES["review.md"]


def test_prepare_writes_english_templates(harness_source: Path, tmp_path: Path) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = service.prepare(tmp_path, Path("task"), "add login", "0001")
    spec = (task / "spec.md").read_text(encoding="utf-8")
    assert "## User Stories" in spec
    assert "## História" not in spec
    progress = (task / "progress.md").read_text(encoding="utf-8")
    assert "Task created for:" in progress


def test_plan_validation(harness_source: Path, tmp_path: Path) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = tmp_path / "task"
    task.mkdir()
    write_valid(task)
    plan = service.validate_plan(task)
    assert plan.commit_message == "feat(auth): add login"
    assert [item.id for item in plan.gates] == ["unit"]


def test_plan_requires_user_story(harness_source: Path, tmp_path: Path) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = tmp_path / "task"
    task.mkdir()
    write_valid(task)
    (task / "spec.md").write_text("# Specification\n\n## Acceptance Criteria\n- AC-001 x\n", encoding="utf-8")
    with pytest.raises(HarnessError, match="User Stories"):
        service.validate_plan(task)
