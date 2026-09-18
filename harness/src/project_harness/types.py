from __future__ import annotations

from dataclasses import dataclass
from enum import StrEnum
from pathlib import Path
from typing import NotRequired, TypeAlias, TypedDict

JSONValue: TypeAlias = None | bool | int | float | str | list["JSONValue"] | dict[str, "JSONValue"]
JSONObject: TypeAlias = dict[str, JSONValue]


class Phase(StrEnum):
    PLAN = "plan"
    EXECUTE = "execute"
    REVIEW = "review"
    REPAIR = "repair"
    COMMIT = "commit"
    DONE = "done"
    NEEDS_HUMAN = "needs_human_attention"
    CANCELED = "canceled"


class ReviewVerdict(StrEnum):
    APPROVED = "APPROVED"
    REJECTED = "REJECTED"


@dataclass(frozen=True)
class GateSpec:
    id: str
    command: str
    required: bool = True


@dataclass(frozen=True)
class CheckResult:
    id: str
    command: str
    exit_code: int
    stdout: str
    stderr: str
    required: bool

    @property
    def passed(self) -> bool:
        return self.exit_code == 0


@dataclass(frozen=True)
class TaskMeta:
    task_id: str
    slug: str
    request: str
    target_branch: str
    base_commit: str
    task_branch: str
    worktree_path: Path
    task_dir_relative: Path
    thread_id: str


@dataclass(frozen=True)
class Target:
    id: str
    display_name: str
    agent_mode: str
    agent_dir: str
    skill_dirs: tuple[str, ...]
    instruction_mode: str
    mcp: JSONObject


@dataclass(frozen=True)
class AgentSource:
    name: str
    description: str
    role: str
    body: str
    path: Path


class HarnessState(TypedDict, total=False):
    task_id: str
    request: str
    slug: str
    target_branch: str
    base_commit: str
    task_branch: str
    worktree_path: str
    task_dir_relative: str
    phase: str
    plan_feedback: str
    code_feedback: str
    repair_round: int
    review_round: int
    check_fix_round: int
    review_presented_paths: list[str]
    blocking_ids: list[str]
    review_verdict: str
    check_results: list[dict[str, JSONValue]]
    integration_status: str
    merge_conflicts: list[str]
    final_commit: str
    last_worker_phase: str
    canceled_reason: str
    warnings: list[str]
    runtime_dir: str
    packet_path: str
    review_report_path: str
    commit_message: str
    status: str
    human_message: NotRequired[str]
