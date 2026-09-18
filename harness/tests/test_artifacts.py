from __future__ import annotations

from pathlib import Path

import pytest

from project_harness.artifacts import ArtifactService, REVIEW_TEMPLATE, TEMPLATES
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
        "  tests:\n"
        "    unit:\n"
        "      - \"LoginRequest rejects empty email\"\n"
        "    integration:\n"
        "      - \"POST /login with valid credentials regenerates the session\"\n"
        "    e2e:\n"
        "      - \"Administrator submits the login screen and reaches /reservations\"\n"
        "  gates:\n"
        "    - id: unit\n"
        "      command: \"python -m pytest\"\n"
        "      required: true\n"
        "---\n\n"
        "# Implementation Plan\n\n## Summary\n\nAdd native login for the administrator.\n\n"
        "## Considered Approaches\n\nA and B; selected A.\n\n"
        "## Planned Tests\n\n### Unit\n\n- LoginRequest rejects empty email\n\n"
        "### Integration\n\n- POST /login with valid credentials regenerates the session\n\n"
        "### E2E\n\n- Administrator submits the login screen and reaches /reservations\n\n"
        "## Required Gates\n\npytest.\n",
        encoding="utf-8",
    )


def test_templates_use_english_headings() -> None:
    assert "# Task Context" in TEMPLATES["context.md"]
    assert "## User Stories" in TEMPLATES["spec.md"]
    assert "## Acceptance Criteria" in TEMPLATES["spec.md"]
    assert "## Planned Tests" in TEMPLATES["tasks.md"]
    assert "### Unit" in TEMPLATES["tasks.md"]
    assert "### Integration" in TEMPLATES["tasks.md"]
    assert "### E2E" in TEMPLATES["tasks.md"]
    assert "## Required Gates" in TEMPLATES["tasks.md"]
    assert "## Verdict" in REVIEW_TEMPLATE
    assert "## Deterministic checks" in REVIEW_TEMPLATE
    assert "## Harness gate" in REVIEW_TEMPLATE


def test_prepare_writes_english_templates(harness_source: Path, tmp_path: Path) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = service.prepare(tmp_path, Path("task"), "add login", "0001")
    spec = (task / "spec.md").read_text(encoding="utf-8")
    assert "## User Stories" in spec
    assert "## História" not in spec
    progress = (task / "progress.md").read_text(encoding="utf-8")
    assert "Task created for:" in progress
    assert (task / "review").is_dir()
    assert not (task / "review.md").exists()


def test_write_review_appends_history_without_replacing(harness_source: Path, tmp_path: Path) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = service.prepare(tmp_path, Path("task"), "add login", "0001")
    first = service.write_review(task, "round one REJECTED\n")
    second = service.write_review(task, "round two APPROVED\n")
    assert first.name == "review-01.md"
    assert second.name == "review-02.md"
    assert first.read_text(encoding="utf-8") == "round one REJECTED\n"
    assert second.read_text(encoding="utf-8") == "round two APPROVED\n"
    assert service.latest_review_path(task) == second
    service.prepare(tmp_path, Path("task"), "add login", "0001")
    assert first.read_text(encoding="utf-8") == "round one REJECTED\n"
    assert not (task / "review.md").exists()


def test_plan_validation(harness_source: Path, tmp_path: Path) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = tmp_path / "task"
    task.mkdir()
    write_valid(task)
    plan = service.validate_plan(task)
    assert plan.commit_message == "feat(auth): add login"
    assert [item.id for item in plan.gates] == ["unit"]
    assert plan.planned_tests.unit == ("LoginRequest rejects empty email",)
    assert plan.planned_tests.integration == (
        "POST /login with valid credentials regenerates the session",
    )
    assert plan.planned_tests.e2e == (
        "Administrator submits the login screen and reaches /reservations",
    )


def test_plan_requires_user_story(harness_source: Path, tmp_path: Path) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = tmp_path / "task"
    task.mkdir()
    write_valid(task)
    (task / "spec.md").write_text("# Specification\n\n## Acceptance Criteria\n- AC-001 x\n", encoding="utf-8")
    with pytest.raises(HarnessError, match="User Stories"):
        service.validate_plan(task)


def test_plan_barrier_summary_lists_punctual_tests_by_level(harness_source: Path, tmp_path: Path) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = tmp_path / "task"
    task.mkdir()
    write_valid(task)
    text = service.plan_barrier_summary(task)
    assert text.index("## Plano") < text.index("## Testes pontuais")
    assert text.index("## Testes pontuais") < text.index("## Comandos após o Execute")
    assert text.index("Add native login for the administrator") < text.index("### Unit")
    assert text.index("### Unit") < text.index("### Integration") < text.index("### E2E")
    assert text.index("LoginRequest rejects empty email") < text.index("## Comandos após o Execute")
    assert "python -m pytest" in text.split("## Comandos após o Execute", 1)[1]
    assert "`build`" in text.split("## Comandos após o Execute", 1)[1]
    assert "feat(auth): add login" not in text


def test_plan_rejects_runner_command_as_planned_test(harness_source: Path, tmp_path: Path) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = tmp_path / "task"
    task.mkdir()
    write_valid(task)
    tasks = (task / "tasks.md").read_text(encoding="utf-8")
    (task / "tasks.md").write_text(
        tasks.replace("- \"LoginRequest rejects empty email\"", "- \"npm test\""),
        encoding="utf-8",
    )
    with pytest.raises(HarnessError, match="runner command"):
        service.validate_plan(task)


def test_plan_requires_reason_when_a_level_has_no_tests(harness_source: Path, tmp_path: Path) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = tmp_path / "task"
    task.mkdir()
    write_valid(task)
    tasks = (task / "tasks.md").read_text(encoding="utf-8")
    (task / "tasks.md").write_text(
        tasks.replace(
            "    e2e:\n      - \"Administrator submits the login screen and reaches /reservations\"\n",
            "    e2e: []\n",
        ),
        encoding="utf-8",
    )
    with pytest.raises(HarnessError, match="tests_not_applicable.e2e"):
        service.validate_plan(task)
