from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

from .types import JSONObject
from .utils import as_bool, as_list, as_object, as_str, read_yaml


@dataclass(frozen=True)
class ContextLimits:
    context_md_tokens: int
    spec_md_tokens: int
    tasks_md_tokens: int


@dataclass(frozen=True)
class GitConfig:
    task_branch_prefix: str
    merge_no_ff: bool
    worktree_root_env: str


@dataclass(frozen=True)
class WorkflowConfig:
    specs_root: Path
    docs_root: Path
    max_repair_rounds: int
    context_limits: ContextLimits
    git: GitConfig


@dataclass(frozen=True)
class VerifyConfig:
    required: tuple[str, ...] = ()


@dataclass(frozen=True)
class HarnessConfig:
    version: int
    workflow: WorkflowConfig
    verify: VerifyConfig = VerifyConfig()


def _int(value: object, default: int) -> int:
    if isinstance(value, int) and not isinstance(value, bool):
        return value
    return default


def load_config(root: Path) -> HarnessConfig:
    data = read_yaml(root / "harness" / "config.yaml")
    workflow = as_object(data.get("workflow"))
    limits = as_object(workflow.get("context_limits"))
    git = as_object(workflow.get("git"))
    verify = as_object(data.get("verify"))
    required: list[str] = []
    for item in as_list(verify.get("required")):
        if isinstance(item, str) and item.strip():
            required.append(item.strip())
    return HarnessConfig(
        version=_int(data.get("version"), 1),
        workflow=WorkflowConfig(
            specs_root=Path(as_str(workflow.get("specs_root"), ".specs/tasks")),
            docs_root=Path(as_str(workflow.get("docs_root"), "docs")),
            max_repair_rounds=_int(workflow.get("max_repair_rounds"), 3),
            context_limits=ContextLimits(
                context_md_tokens=_int(limits.get("context_md_tokens"), 12000),
                spec_md_tokens=_int(limits.get("spec_md_tokens"), 5000),
                tasks_md_tokens=_int(limits.get("tasks_md_tokens"), 10000),
            ),
            git=GitConfig(
                task_branch_prefix=as_str(git.get("task_branch_prefix"), "harness/"),
                merge_no_ff=as_bool(git.get("merge_no_ff"), True),
                worktree_root_env=as_str(git.get("worktree_root_env"), "HARNESS_WORKTREE_ROOT"),
            ),
        ),
        verify=VerifyConfig(required=tuple(required)),
    )
