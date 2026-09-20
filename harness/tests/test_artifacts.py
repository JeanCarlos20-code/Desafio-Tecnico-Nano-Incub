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


NO_NEW_TESTS_REASON = (
    "sem testes para esse plano pois ele é apenas um ajuste de copy do orquestrador"
)


def write_all_empty_tests(
    task: Path,
    *,
    reason: str | None = NO_NEW_TESTS_REASON,
    include_gate: bool = True,
) -> None:
    (task / "context.md").write_text("# Task Context\n\nDocs-only orchestrator copy.\n", encoding="utf-8")
    (task / "spec.md").write_text(
        "# Specification\n\n## User Stories\n\nAs a reviewer I want an honest plan gate.\n\n"
        "## Acceptance Criteria\n\n- AC-001 no invented coverage.\n",
        encoding="utf-8",
    )
    reason_yaml = f'  tests_not_applicable_reason: "{reason}"\n' if reason else ""
    gates = (
        "  gates:\n"
        "    - id: lint\n"
        "      command: \"python3 -m compileall -q src\"\n"
        "      required: true\n"
        if include_gate
        else "  gates: []\n"
    )
    (task / "tasks.md").write_text(
        "---\n"
        "harness:\n"
        "  commit_message: \"chore(harness): adjust orchestrator copy\"\n"
        "  tests:\n"
        "    unit: []\n"
        "    integration: []\n"
        "    e2e: []\n"
        f"{reason_yaml}"
        f"{gates}"
        "---\n\n"
        "# Implementation Plan\n\n## Summary\n\nAdjust orchestrator copy only.\n\n"
        "## Planned Tests\n\n### Unit\n\n### Integration\n\n### E2E\n\n"
        "## Required Gates\n\nlint.\n",
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
    assert "## Deterministic checks" not in REVIEW_TEMPLATE
    assert "## Blockers" in REVIEW_TEMPLATE
    assert "## High" in REVIEW_TEMPLATE
    assert "## Medium" in REVIEW_TEMPLATE
    assert "## Positive Findings" in REVIEW_TEMPLATE
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


def test_write_checks_appends_history_matching_review_numbering(
    harness_source: Path, tmp_path: Path
) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = service.prepare(tmp_path, Path("task"), "add login", "0001")
    first = service.write_checks(task, 1, "# Deterministic checks — round 01\n\n- ✅ pytest\n")
    second = service.write_checks(task, 2, "# Deterministic checks — round 02\n\n- ✅ lint\n")
    assert first.name == "checks-01.md"
    assert second.name == "checks-02.md"
    assert first.parent.name == "tests"
    assert [path.name for path in service.list_checks(task)] == ["checks-01.md", "checks-02.md"]
    assert first.read_text(encoding="utf-8") == "# Deterministic checks — round 01\n\n- ✅ pytest\n"
    with pytest.raises(HarnessError, match="Checks history já existe"):
        service.write_checks(task, 1, "must not replace\n")


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


def test_plan_barrier_summary_shows_single_no_new_tests_sentence(
    harness_source: Path, tmp_path: Path
) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = tmp_path / "task"
    task.mkdir()
    write_all_empty_tests(task)
    text = service.plan_barrier_summary(task)
    tests_section = text.split("## Testes pontuais", 1)[1].split("## Comandos após o Execute", 1)[0]
    assert NO_NEW_TESTS_REASON in tests_section
    assert "### Unit" not in tests_section
    assert "### Integration" not in tests_section
    assert "### E2E" not in tests_section
    assert "- not applicable:" not in tests_section
    assert "## Comandos após o Execute" in text


def test_validate_plan_rejects_all_empty_tests_without_required_reason_prefix(
    harness_source: Path, tmp_path: Path
) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = tmp_path / "empty-missing"
    task.mkdir()
    write_all_empty_tests(task, reason=None)
    with pytest.raises(HarnessError, match="tests_not_applicable_reason"):
        service.validate_plan(task)
    other = tmp_path / "empty-wrong-prefix"
    other.mkdir()
    write_all_empty_tests(other, reason="no tests needed for this docs change")
    with pytest.raises(HarnessError, match="sem testes para esse plano pois ele é apenas"):
        service.validate_plan(other)


def test_validate_plan_accepts_all_empty_tests_with_no_new_tests_reason(
    harness_source: Path, tmp_path: Path
) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = tmp_path / "task"
    task.mkdir()
    write_all_empty_tests(task)
    plan = service.validate_plan(task)
    assert plan.planned_tests.unit == ()
    assert plan.planned_tests.integration == ()
    assert plan.planned_tests.e2e == ()
    assert plan.planned_tests.skipped == ()
    assert plan.tests_not_applicable_reason == NO_NEW_TESTS_REASON


def test_plan_barrier_summary_lists_three_levels_when_any_level_has_tests(
    harness_source: Path, tmp_path: Path
) -> None:
    config = load_config(harness_source.parent)
    service = ArtifactService(config)
    task = tmp_path / "task"
    task.mkdir()
    write_valid(task)
    tasks = (task / "tasks.md").read_text(encoding="utf-8")
    (task / "tasks.md").write_text(
        tasks.replace(
            "    e2e:\n      - \"Administrator submits the login screen and reaches /reservations\"\n",
            "    e2e: []\n"
            "  tests_not_applicable:\n"
            "    e2e: \"No browser flow changes.\"\n"
            "  tests_not_applicable_reason: "
            "\"sem testes para esse plano pois ele é apenas um ajuste misto\"\n",
        ),
        encoding="utf-8",
    )
    text = service.plan_barrier_summary(task)
    assert "### Unit" in text
    assert "### Integration" in text
    assert "### E2E" in text
    assert "LoginRequest rejects empty email" in text
    assert "- not applicable: No browser flow changes." in text
    assert NO_NEW_TESTS_REASON not in text.split("## Testes pontuais", 1)[1].split(
        "## Comandos após o Execute", 1
    )[0]
