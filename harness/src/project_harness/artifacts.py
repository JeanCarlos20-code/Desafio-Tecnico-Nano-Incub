from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from datetime import datetime, timezone
import re

import yaml

from .commits import validate_message
from .config import HarnessConfig
from .errors import HarnessError
from .types import GateSpec, JSONObject
from .utils import as_bool, as_list, as_object, as_str, estimate_tokens, parse_frontmatter, require_object


@dataclass(frozen=True)
class PlanData:
    gates: tuple[GateSpec, ...]
    commit_message: str
    commits: tuple[str, ...]
    tests_not_applicable_reason: str
    warnings: tuple[str, ...]


TEMPLATES = {
    "context.md": """# Task Context

## Relevant Documentation

## Relevant Components

## Relevant Code

## Existing Constraints

## Important Decisions
""",
    "spec.md": """# Specification

## Context

## Problem

## Goal

## User Stories

## Acceptance Criteria

## Edge Cases

## Out of Scope

## Considered Approaches

## Selected Approach
""",
    "tasks.md": """# Implementation Plan

## Summary

## Affected Components

## Tasks

## Planned Tests

## Required Gates

## Definition of Done
""",
    "progress.md": """# Progress

## Plan

## Execute

## Review

## Repair

## Integration
""",
    "validation.md": """# Validation

## Acceptance Criteria

## Test Results

## Required Gates

## Review Result

## Final Status
""",
}

REVIEW_TEMPLATE = """🤖 AI Code Review (S)

## Summary

## Deterministic checks

## Blockers

## High

## Medium

## Positive Findings

## Verdict

## Harness gate
"""
REVIEW_DIRNAME = "review"
REVIEW_FILE_RE = re.compile(r"^review-(\d{2,})\.md$")


class ArtifactService:
    NAMES = tuple(TEMPLATES)

    def __init__(self, config: HarnessConfig) -> None:
        self.config = config

    def task_dir(self, worktree: Path, relative: Path) -> Path:
        return worktree / relative

    def prepare(self, worktree: Path, relative: Path, request: str, task_id: str) -> Path:
        directory = self.task_dir(worktree, relative)
        directory.mkdir(parents=True, exist_ok=True)
        (directory / REVIEW_DIRNAME).mkdir(parents=True, exist_ok=True)
        for name in self.NAMES:
            path = directory / name
            if path.exists():
                continue
            body = TEMPLATES[name]
            if name == "progress.md":
                body = body.rstrip() + f"\n\n- Task created for: {request}\n"
            path.write_text(body, encoding="utf-8")
        return directory

    def reviews_dir(self, task_dir: Path) -> Path:
        return task_dir / REVIEW_DIRNAME

    def list_reviews(self, task_dir: Path) -> tuple[Path, ...]:
        directory = self.reviews_dir(task_dir)
        if not directory.is_dir():
            return ()
        found: list[tuple[int, Path]] = []
        for path in directory.iterdir():
            match = REVIEW_FILE_RE.match(path.name)
            if match and path.is_file():
                found.append((int(match.group(1)), path))
        found.sort(key=lambda item: item[0])
        return tuple(item[1] for item in found)

    def latest_review_path(self, task_dir: Path) -> Path | None:
        reviews = self.list_reviews(task_dir)
        return reviews[-1] if reviews else None

    def write_review(self, task_dir: Path, markdown: str) -> Path:
        directory = self.reviews_dir(task_dir)
        directory.mkdir(parents=True, exist_ok=True)
        existing = self.list_reviews(task_dir)
        number = 1
        if existing:
            match = REVIEW_FILE_RE.match(existing[-1].name)
            number = (int(match.group(1)) if match else len(existing)) + 1
        path = directory / f"review-{number:02d}.md"
        if path.exists():
            raise HarnessError(f"Review já existe e não pode ser substituída: {path.name}")
        path.write_text(markdown, encoding="utf-8")
        return path

    def append_progress(self, task_dir: Path, event_id: str, message: str) -> None:
        path = task_dir / "progress.md"
        marker = f"<!-- event:{event_id} -->"
        current = path.read_text(encoding="utf-8") if path.exists() else ""
        if marker in current:
            return
        timestamp = datetime.now(timezone.utc).astimezone().strftime("%Y-%m-%d %H:%M:%S %z")
        with path.open("a", encoding="utf-8") as handle:
            handle.write(f"\n{marker}\n- [{timestamp}] {message}\n")

    def validate_plan(self, task_dir: Path) -> PlanData:
        context_path = task_dir / "context.md"
        spec_path = task_dir / "spec.md"
        tasks_path = task_dir / "tasks.md"
        for path in (context_path, spec_path, tasks_path):
            if not path.is_file() or not path.read_text(encoding="utf-8").strip():
                raise HarnessError(f"Incomplete plan: {path.name} is empty.")

        context = context_path.read_text(encoding="utf-8")
        spec = spec_path.read_text(encoding="utf-8")
        tasks = tasks_path.read_text(encoding="utf-8")

        if not _has_heading(spec, "User Stories"):
            raise HarnessError("spec.md must contain a User Stories heading.")
        if not _has_heading(spec, "Acceptance Criteria"):
            raise HarnessError("spec.md must contain an Acceptance Criteria heading.")
        if not _has_heading(tasks, "Planned Tests"):
            raise HarnessError("tasks.md must contain a Planned Tests heading.")
        if not _has_heading(tasks, "Required Gates"):
            raise HarnessError("tasks.md must contain a Required Gates heading.")

        meta, _ = parse_frontmatter(tasks, tasks_path)
        harness = as_object(meta.get("harness"))
        raw_gates = as_list(harness.get("gates"))
        gates: list[GateSpec] = []
        for index, raw in enumerate(raw_gates, start=1):
            item = as_object(raw)
            gate_id = as_str(item.get("id"), f"gate-{index}").strip()
            command = as_str(item.get("command")).strip()
            if not command:
                raise HarnessError(f"tasks.md: gate {gate_id} has no command.")
            gates.append(
                GateSpec(
                    id=gate_id,
                    command=command,
                    required=as_bool(item.get("required"), True),
                )
            )
        no_tests_reason = as_str(harness.get("tests_not_applicable_reason")).strip()
        if not gates and not no_tests_reason:
            raise HarnessError(
                "tasks.md must declare at least one gate or tests_not_applicable_reason."
            )
        commit_messages = _commit_messages(harness)
        if not commit_messages:
            raise HarnessError(
                "tasks.md must declare harness.commits or harness.commit_message "
                "in type(module): format from the conventional-commits skill."
            )
        for message in commit_messages:
            validate_message(message)
        commit_message = "\n".join(commit_messages)

        warnings: list[str] = []
        limits = self.config.workflow.context_limits
        checks = (
            ("context.md", estimate_tokens(context), limits.context_md_tokens),
            ("spec.md", estimate_tokens(spec), limits.spec_md_tokens),
            ("tasks.md", estimate_tokens(tasks), limits.tasks_md_tokens),
        )
        for name, amount, limit in checks:
            if amount > limit:
                warnings.append(f"{name} ~{amount} tokens exceeds the {limit} target; compress before execute.")

        return PlanData(
            gates=tuple(gates),
            commit_message=commit_message,
            commits=commit_messages,
            tests_not_applicable_reason=no_tests_reason,
            warnings=tuple(warnings),
        )

    def plan_summary(self, task_dir: Path, max_chars: int = 12000) -> str:
        chunks: list[str] = []
        for name in ("spec.md", "tasks.md"):
            text = (task_dir / name).read_text(encoding="utf-8").strip()
            chunks.append(f"## {name}\n\n{text}")
        joined = "\n\n".join(chunks)
        if len(joined) > max_chars:
            return joined[:max_chars] + "\n\n... summary truncated; see the full files in the worktree ..."
        return joined

    def plan_barrier_summary(self, task_dir: Path) -> str:
        plan = self.validate_plan(task_dir)
        lines = [
            "## Barreira de teste",
            "",
            "Este gate libera implementação. Execute não comita. Commits só depois do segundo gate humano.",
            "",
        ]
        if plan.gates:
            lines.extend(
                [
                    "| Gate | Command | Required |",
                    "| ---- | ------- | -------- |",
                ]
            )
            for gate in plan.gates:
                required = "yes" if gate.required else "no"
                lines.append(f"| {gate.id} | `{gate.command}` | {required} |")
        elif plan.tests_not_applicable_reason:
            lines.append(plan.tests_not_applicable_reason)
        verify_ids = self.config.verify.required
        if verify_ids:
            ids = ", ".join(f"`{item}`" for item in verify_ids)
            lines.extend(
                [
                    "",
                    "## Stack verify",
                    "",
                    "Depois do Execute, o Harness também roda estes command IDs do "
                    f"`harness/stack.yml` nos componentes afetados: {ids}.",
                    "Eles entram na barreira de teste e podem bloquear repair mesmo "
                    "quando a review não tem blocker/high.",
                ]
            )
        planned = _section_body((task_dir / "tasks.md").read_text(encoding="utf-8"), "Planned Tests")
        if planned:
            lines.extend(["", "## Planned Tests", "", planned])
        goal = _section_body((task_dir / "spec.md").read_text(encoding="utf-8"), "Goal")
        if goal:
            lines.extend(["", "## Goal", "", goal])
        return "\n".join(lines).strip() + "\n"

    def append_validation_checks(self, task_dir: Path, rendered: str) -> None:
        path = task_dir / "validation.md"
        existing = path.read_text(encoding="utf-8") if path.exists() else ""
        if "<!-- harness-checks:start -->" in existing:
            before = existing.split("<!-- harness-checks:start -->", 1)[0]
            after = existing.split("<!-- harness-checks:end -->", 1)[1] if "<!-- harness-checks:end -->" in existing else ""
            existing = before.rstrip() + "\n\n" + after.lstrip()
        block = (
            "<!-- harness-checks:start -->\n"
            "## Harness deterministic checks\n\n"
            f"{rendered.strip()}\n"
            "<!-- harness-checks:end -->\n"
        )
        path.write_text(existing.rstrip() + "\n\n" + block, encoding="utf-8")


def _commit_messages(harness: JSONObject) -> tuple[str, ...]:
    messages: list[str] = []
    raw = harness.get("commits")
    if isinstance(raw, list):
        for item in raw:
            text = ""
            if isinstance(item, str):
                text = item.strip()
            elif isinstance(item, dict):
                text = as_str(item.get("message")).strip()
            if text:
                messages.append(text)
    if messages:
        return tuple(messages)
    single = as_str(harness.get("commit_message")).strip()
    return (single,) if single else ()


def _has_heading(text: str, title: str) -> bool:
    return bool(re.search(rf"(?im)^#{{1,4}}\s+{re.escape(title)}\s*$", text))


def _section_body(text: str, title: str) -> str:
    match = re.search(rf"(?im)^#{{1,4}}\s+{re.escape(title)}\s*$", text)
    if match is None:
        return ""
    start = match.end()
    next_heading = re.search(r"(?m)^#{1,4}\s+", text[start:])
    body = text[start : start + next_heading.start()] if next_heading else text[start:]
    return body.strip()
