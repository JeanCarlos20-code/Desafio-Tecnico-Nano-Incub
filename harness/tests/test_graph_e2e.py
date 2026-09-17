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
            "---\nharness:\n  commit_message: \"feat(reservation): add booking\"\n"
            "  gates:\n    - id: unit\n      command: \"python -c 'print(1)'\"\n      required: true\n---\n\n"
            "# Implementation Plan\n\n## Considered Approaches\n- A: service.\n- B: use case.\n\n"
            "## Planned Tests\n- UT-001 covers AC-001.\n\n## Required Gates\n- unit\n",
            encoding="utf-8",
        )
        graph.resume(meta, {"kind": "agent_result", "phase": "plan", "status": "success"})
        action = store.read_action(task_id)
        assert action is not None and action.get("gate") == "plan"

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
        assert (task_dir / "review" / "review-01.md").is_file()
        assert not (task_dir / "review.md").exists()
        action = store.read_action(task_id)
        assert action is not None and action.get("gate") == "commit"

        graph.resume(meta, {"kind": "human_decision", "decision": "approve"})
        values = graph.values(meta)
        assert values.get("status") == "completed"
        assert (root / "reservation.txt").read_text(encoding="utf-8") == "created\n"
        assert not worktree.exists()
    finally:
        graph.close()
