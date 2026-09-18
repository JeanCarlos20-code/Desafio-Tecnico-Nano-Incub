from __future__ import annotations

import json
from pathlib import Path
import shutil

import pytest

pytest.importorskip("langgraph")
pytest.importorskip("langgraph.checkpoint.sqlite")

from project_harness.artifacts import ArtifactService
from project_harness.config import load_config
from project_harness.git_manager import GitManager
from project_harness.graph_runtime import HarnessGraph
from project_harness.task_store import TaskStore
from conftest import run

E2E_STACK_YAML = """
project:
  name: e2e-fixture
  type: monolith
  languages: []
  frameworks: []
  data_access: []
  testing: []
components:
  - name: app
    root: .
    languages: []
    frameworks: []
    data_access: []
    testing: []
    commands:
      test:
        command: python3 -c 'print(1)'
      lint:
        command: python3 -c 'print(1)'
      build:
        command: python3 -c 'print(1)'
  - name: harness
    root: harness
    languages: []
    frameworks: []
    data_access: []
    testing: []
    commands:
      test:
        command: python3 -c 'print(1)'
      lint:
        command: python3 -c 'print(1)'
      build:
        command: python3 -c 'print(1)'
infrastructure:
  databases: []
  cache: []
  queues: []
"""

PLAN_TASKS_GREEN = (
    "---\nharness:\n  commits:\n"
    "    - \"feat(reservation): add booking\"\n"
    "    - \"chore(specs): record reservation plan artifacts\"\n"
    "  tests:\n"
    "    unit:\n      - \"CreateReservation rejects an overlapping slot\"\n"
    "    integration:\n      - \"POST /reservations persists the booking in MySQL\"\n"
    "    e2e:\n      - \"Administrator books a room through the real screen\"\n"
    "  gates:\n    - id: unit\n      command: \"python -c 'print(1)'\"\n      required: true\n---\n\n"
    "# Implementation Plan\n\n## Summary\nAdd reservation booking.\n\n## Considered Approaches\n- A: service.\n- B: use case.\n\n"
    "## Planned Tests\n### Unit\n- CreateReservation rejects an overlapping slot\n\n"
    "### Integration\n- POST /reservations persists the booking in MySQL\n\n"
    "### E2E\n- Administrator books a room through the real screen\n\n## Required Gates\n- unit\n"
)

PLAN_TASKS_RED = PLAN_TASKS_GREEN.replace(
    "command: \"python -c 'print(1)'\"",
    "command: \"python3 -c 'raise SystemExit(1)'\"",
)


def _write_plan_artifacts(task_dir: Path, tasks_md: str) -> None:
    (task_dir / "context.md").write_text("# Task Context\n\nReservation flow.\n", encoding="utf-8")
    (task_dir / "spec.md").write_text(
        "# Specification\n\n## User Stories\nAs a user, I want to create a reservation.\n\n"
        "## Acceptance Criteria\n- AC-001: the reservation is persisted.\n",
        encoding="utf-8",
    )
    (task_dir / "tasks.md").write_text(tasks_md, encoding="utf-8")


def _boot_graph(
    tmp_path: Path,
    harness_source: Path,
    monkeypatch,
    *,
    max_repair_rounds: int | None = None,
):
    root = tmp_path / "repo"
    root.mkdir()
    run(root, "git", "init", "-b", "feature/challenge")
    run(root, "git", "config", "user.email", "test@example.com")
    run(root, "git", "config", "user.name", "Harness Test")
    run(root, "git", "config", "commit.gpgsign", "false")
    shutil.copytree(harness_source, root / "harness")
    (root / "harness" / "stack.yml").write_text(E2E_STACK_YAML, encoding="utf-8")
    if max_repair_rounds is not None:
        config_path = root / "harness" / "config.yaml"
        config_path.write_text(
            config_path.read_text(encoding="utf-8").replace(
                "max_repair_rounds: 3",
                f"max_repair_rounds: {max_repair_rounds}",
            ),
            encoding="utf-8",
        )
    (root / "README.md").write_text("initial\n", encoding="utf-8")
    run(root, "git", "add", ".")
    run(root, "git", "commit", "-m", "chore: initial")

    monkeypatch.setenv("HARNESS_WORKTREE_ROOT", str(tmp_path / "worktrees"))
    config = load_config(root)
    store = TaskStore(root, config)
    git_manager = GitManager(root, config)
    task_id = store.reserve_next_id()
    worktree, task_branch, base_commit = git_manager.create_worktree(
        task_id, "create-reservation", "feature/challenge"
    )
    meta = store.build_meta(
        task_id=task_id,
        request="criar reserva",
        target_branch="feature/challenge",
        base_commit=base_commit,
        task_branch=task_branch,
        worktree_path=worktree,
    )
    store.save(meta)
    task_dir = ArtifactService(config).prepare(
        worktree, meta.task_dir_relative, meta.request, task_id
    )
    graph = HarnessGraph(root, config)
    return graph, store, meta, task_dir, task_id, worktree, root


def _approve_plan(graph: HarnessGraph, store: TaskStore, meta, task_id: str, tasks_md: str, task_dir: Path) -> None:
    graph.start(meta)
    _write_plan_artifacts(task_dir, tasks_md)
    graph.resume(meta, {"kind": "agent_result", "phase": "plan", "status": "success"})
    graph.resume(meta, {"kind": "human_decision", "decision": "approve"})
    action = store.read_action(task_id)
    assert action is not None and action.get("phase") == "execute"



def test_full_graph_plan_execute_review_commit_merge(tmp_path: Path, harness_source: Path, monkeypatch) -> None:
    root = tmp_path / "repo"
    root.mkdir()
    run(root, "git", "init", "-b", "feature/challenge")
    run(root, "git", "config", "user.email", "test@example.com")
    run(root, "git", "config", "user.name", "Harness Test")
    run(root, "git", "config", "commit.gpgsign", "false")
    shutil.copytree(harness_source, root / "harness")
    (root / "harness" / "stack.yml").write_text(E2E_STACK_YAML, encoding="utf-8")
    (root / "README.md").write_text("initial\n", encoding="utf-8")
    run(root, "git", "add", ".")
    run(root, "git", "commit", "-m", "chore: initial")

    monkeypatch.setenv("HARNESS_WORKTREE_ROOT", str(tmp_path / "worktrees"))
    config = load_config(root)
    store = TaskStore(root, config)
    git_manager = GitManager(root, config)
    task_id = store.reserve_next_id()
    worktree, task_branch, base_commit = git_manager.create_worktree(
        task_id, "create-reservation", "feature/challenge"
    )
    meta = store.build_meta(
        task_id=task_id,
        request="criar reserva",
        target_branch="feature/challenge",
        base_commit=base_commit,
        task_branch=task_branch,
        worktree_path=worktree,
    )
    store.save(meta)
    task_dir = ArtifactService(config).prepare(
        worktree, meta.task_dir_relative, meta.request, task_id
    )

    graph = HarnessGraph(root, config)
    try:
        graph.start(meta)
        action = store.read_action(task_id)
        assert action is not None and action.get("phase") == "plan"

        (task_dir / "context.md").write_text("# Task Context\n\nReservation flow.\n", encoding="utf-8")
        (task_dir / "spec.md").write_text(
            "# Specification\n\n## User Stories\nAs a user, I want to create a reservation.\n\n"
            "## Acceptance Criteria\n- AC-001: the reservation is persisted.\n",
            encoding="utf-8",
        )
        (task_dir / "tasks.md").write_text(
            "---\nharness:\n  commits:\n"
            "    - \"feat(reservation): add booking\"\n"
            "    - \"chore(specs): record reservation plan artifacts\"\n"
            "  tests:\n"
            "    unit:\n      - \"CreateReservation rejects an overlapping slot\"\n"
            "    integration:\n      - \"POST /reservations persists the booking in MySQL\"\n"
            "    e2e:\n      - \"Administrator books a room through the real screen\"\n"
            "  gates:\n    - id: unit\n      command: \"python -c 'print(1)'\"\n      required: true\n---\n\n"
            "# Implementation Plan\n\n## Summary\nAdd reservation booking.\n\n## Considered Approaches\n- A: service.\n- B: use case.\n\n"
            "## Planned Tests\n### Unit\n- CreateReservation rejects an overlapping slot\n\n"
            "### Integration\n- POST /reservations persists the booking in MySQL\n\n"
            "### E2E\n- Administrator books a room through the real screen\n\n## Required Gates\n- unit\n",
            encoding="utf-8",
        )
        graph.resume(meta, {"kind": "agent_result", "phase": "plan", "status": "success"})
        action = store.read_action(task_id)
        assert action is not None and action.get("gate") == "plan"
        summary = str(action.get("summary") or "")
        assert "## Plano" in summary
        assert "## Testes pontuais" in summary
        assert "## Comandos após o Execute" in summary
        assert summary.index("## Plano") < summary.index("## Testes pontuais")
        assert summary.index("## Testes pontuais") < summary.index("## Comandos após o Execute")
        assert "feat(reservation): add booking" not in summary
        assert "CreateReservation rejects an overlapping slot" in summary
        assert "### Unit" in summary
        assert "python -c 'print(1)'" in summary.split("## Comandos após o Execute", 1)[1]
        assert action.get("tests") == {
            "unit": ["CreateReservation rejects an overlapping slot"],
            "integration": ["POST /reservations persists the booking in MySQL"],
            "e2e": ["Administrator books a room through the real screen"],
        }
        assert action.get("gates") == [
            {"id": "unit", "command": "python -c 'print(1)'", "required": True}
        ]
        assert "Este gate não autoriza commit" in str(action.get("message") or "")

        graph.resume(meta, {"kind": "human_decision", "decision": "approve"})
        action = store.read_action(task_id)
        assert action is not None and action.get("phase") == "execute"

        (worktree / "reservation.txt").write_text("created\n", encoding="utf-8")
        graph.resume(meta, {"kind": "agent_result", "phase": "execute", "status": "success"})
        action = store.read_action(task_id)
        assert action is not None and action.get("phase") == "review"

        review_dir = store.review_dir(task_id, 1)
        consolidated = review_dir / "consolidated.json"
        consolidated.write_text(
            json.dumps(
                {
                    "track": "consolidated",
                    "summary": "Sem findings bloqueantes.",
                    "verdict": "APPROVED",
                    "findings": [],
                    "unverified": [],
                    "positives": ["AC-001 coberto."],
                    "execute_handoff": {
                        "action": "complete",
                        "blocking_ids": [],
                        "instructions": "complete",
                    },
                }
            ),
            encoding="utf-8",
        )
        graph.resume(
            meta,
            {
                "kind": "agent_result",
                "phase": "review",
                "status": "success",
                "result": str(consolidated),
            },
        )
        review_md = (task_dir / "review" / "review-01.md").read_text(encoding="utf-8")
        checks_md = (task_dir / "tests" / "checks-01.md").read_text(encoding="utf-8")
        assert (task_dir / "review" / "review-01.md").is_file()
        assert not (task_dir / "review.md").exists()
        assert "✅ APPROVED" in review_md.split("**Harness gate**", 1)[0]
        assert "**Summary**" in review_md
        assert "**❌ Blockers**" in review_md
        assert "**⚠️ High**" in review_md
        assert "**📝 Medium**" in review_md
        assert "**✅ Positive Findings**" in review_md
        assert "**Verdict**" in review_md
        assert "**Harness gate**" in review_md
        assert "**Deterministic checks**" not in review_md
        assert "required checks are green" in review_md
        assert "# Deterministic checks — round 01" in checks_md
        assert "python3 -c" in checks_md
        action = store.read_action(task_id)
        assert action is not None and action.get("gate") == "commit"
        values = graph.values(meta)
        assert values.get("review_round") == 1

        graph.resume(meta, {"kind": "human_decision", "decision": "approve"})
        values = graph.values(meta)
        assert values.get("status") == "completed"
        assert (root / "reservation.txt").read_text(encoding="utf-8") == "created\n"
        assert not worktree.exists()
    finally:
        graph.close()


def test_failed_required_checks_skip_review_and_do_not_increment_review_round(
    tmp_path: Path, harness_source: Path, monkeypatch
) -> None:
    graph, store, meta, task_dir, task_id, worktree, _root = _boot_graph(
        tmp_path, harness_source, monkeypatch
    )
    try:
        _approve_plan(graph, store, meta, task_id, PLAN_TASKS_RED, task_dir)
        (worktree / "reservation.txt").write_text("created\n", encoding="utf-8")
        graph.resume(meta, {"kind": "agent_result", "phase": "execute", "status": "success"})
        action = store.read_action(task_id)
        assert action is not None
        assert action.get("phase") == "repair"
        assert action.get("phase") != "review"
        values = graph.values(meta)
        assert values.get("review_round") == 0
        assert not (task_dir / "review" / "review-01.md").exists()

        graph.resume(meta, {"kind": "agent_result", "phase": "repair", "status": "success"})
        action = store.read_action(task_id)
        assert action is not None
        assert action.get("phase") == "repair"
        values = graph.values(meta)
        assert values.get("review_round") == 0
        assert values.get("check_fix_round") == 1
        assert not (task_dir / "review" / "review-01.md").exists()
    finally:
        graph.close()


def test_exhausted_check_fix_notifies_human_and_does_not_start_review(
    tmp_path: Path, harness_source: Path, monkeypatch
) -> None:
    graph, store, meta, task_dir, task_id, worktree, _root = _boot_graph(
        tmp_path, harness_source, monkeypatch, max_repair_rounds=1
    )
    try:
        _approve_plan(graph, store, meta, task_id, PLAN_TASKS_RED, task_dir)
        (worktree / "reservation.txt").write_text("created\n", encoding="utf-8")
        graph.resume(meta, {"kind": "agent_result", "phase": "execute", "status": "success"})
        action = store.read_action(task_id)
        assert action is not None and action.get("phase") == "repair"

        graph.resume(meta, {"kind": "agent_result", "phase": "repair", "status": "success"})
        action = store.read_action(task_id)
        assert action is not None
        assert action.get("gate") == "check_fail_limit"
        assert action.get("phase") != "review"
        values = graph.values(meta)
        assert values.get("review_round") == 0
        assert not (task_dir / "review" / "review-01.md").exists()
        failed = action.get("failed_checks")
        assert isinstance(failed, list) and "unit" in failed

        graph.resume(meta, {"kind": "human_decision", "decision": "retry"})
        action = store.read_action(task_id)
        assert action is not None
        assert action.get("phase") == "repair"
        assert action.get("gate") != "commit"
        values = graph.values(meta)
        assert values.get("review_round") == 0
        assert values.get("check_fix_round") == 0

        graph.resume(meta, {"kind": "agent_result", "phase": "repair", "status": "success"})
        action = store.read_action(task_id)
        assert action is not None and action.get("gate") == "check_fail_limit"

        graph.resume(meta, {"kind": "human_decision", "decision": "stop"})
        values = graph.values(meta)
        assert values.get("status") == "needs_human_attention"
        assert values.get("review_round") == 0
        assert store.read_action(task_id) is None or store.read_action(task_id).get("phase") != "review"
    finally:
        graph.close()

