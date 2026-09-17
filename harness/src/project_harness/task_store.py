from __future__ import annotations

from pathlib import Path
import json
import os
import re

from .config import HarnessConfig
from .errors import HarnessError
from .types import JSONValue, TaskMeta
from .utils import git_common_dir, read_json_object, sha256_text, slugify, write_json


class TaskStore:
    def __init__(self, root: Path, config: HarnessConfig) -> None:
        self.root = root
        self.config = config
        self.runtime_root = git_common_dir(root) / "harness"
        self.tasks_root = self.runtime_root / "tasks"
        self.packets_root = self.runtime_root / "packets"
        self.reviews_root = self.runtime_root / "reviews"
        self.actions_root = self.runtime_root / "actions"
        self.tasks_root.mkdir(parents=True, exist_ok=True)
        self.actions_root.mkdir(parents=True, exist_ok=True)

    def next_id(self) -> str:
        values: list[int] = []
        spec_root = self.root / self.config.workflow.specs_root
        if spec_root.is_dir():
            for item in spec_root.iterdir():
                match = re.match(r"^(\d{4,})-", item.name)
                if match:
                    values.append(int(match.group(1)))
        for item in self.tasks_root.glob("*.json"):
            if item.stem.isdigit():
                values.append(int(item.stem))
        for item in self.tasks_root.glob("*.reserve"):
            if item.stem.isdigit():
                values.append(int(item.stem))
        return f"{(max(values, default=0) + 1):04d}"

    def reserve_next_id(self) -> str:
        while True:
            task_id = self.next_id()
            path = self.tasks_root / f"{task_id}.reserve"
            try:
                descriptor = os.open(path, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
            except FileExistsError:
                continue
            os.close(descriptor)
            return task_id

    def release_reservation(self, task_id: str) -> None:
        path = self.tasks_root / f"{task_id}.reserve"
        if path.exists():
            path.unlink()

    def build_meta(
        self,
        *,
        task_id: str,
        request: str,
        target_branch: str,
        base_commit: str,
        task_branch: str,
        worktree_path: Path,
    ) -> TaskMeta:
        slug = slugify(request)
        directory = Path(f"{task_id}-{slug}")
        task_dir_relative = self.config.workflow.specs_root / directory
        thread_id = sha256_text(f"{self.root}:{task_id}")[:24]
        return TaskMeta(
            task_id=task_id,
            slug=slug,
            request=request,
            target_branch=target_branch,
            base_commit=base_commit,
            task_branch=task_branch,
            worktree_path=worktree_path,
            task_dir_relative=task_dir_relative,
            thread_id=thread_id,
        )

    def save(self, meta: TaskMeta) -> None:
        payload: dict[str, JSONValue] = {
            "task_id": meta.task_id,
            "slug": meta.slug,
            "request": meta.request,
            "target_branch": meta.target_branch,
            "base_commit": meta.base_commit,
            "task_branch": meta.task_branch,
            "worktree_path": str(meta.worktree_path),
            "task_dir_relative": str(meta.task_dir_relative),
            "thread_id": meta.thread_id,
        }
        write_json(self.tasks_root / f"{meta.task_id}.json", payload)
        self.release_reservation(meta.task_id)

    def load(self, task_id: str) -> TaskMeta:
        path = self.tasks_root / f"{task_id}.json"
        data = read_json_object(path)
        def need(name: str) -> str:
            value = data.get(name)
            if not isinstance(value, str) or not value:
                raise HarnessError(f"Metadado inválido {name} em {path}")
            return value
        return TaskMeta(
            task_id=need("task_id"),
            slug=need("slug"),
            request=need("request"),
            target_branch=need("target_branch"),
            base_commit=need("base_commit"),
            task_branch=need("task_branch"),
            worktree_path=Path(need("worktree_path")),
            task_dir_relative=Path(need("task_dir_relative")),
            thread_id=need("thread_id"),
        )

    def packet_dir(self, task_id: str) -> Path:
        path = self.packets_root / task_id
        path.mkdir(parents=True, exist_ok=True)
        return path

    def review_dir(self, task_id: str, round_number: int) -> Path:
        path = self.reviews_root / task_id / f"round-{round_number}"
        path.mkdir(parents=True, exist_ok=True)
        return path


    def write_action(self, task_id: str, payload: dict[str, JSONValue]) -> Path:
        path = self.actions_root / f"{task_id}.json"
        write_json(path, payload)
        return path

    def read_action(self, task_id: str) -> dict[str, JSONValue] | None:
        path = self.actions_root / f"{task_id}.json"
        if not path.is_file():
            return None
        return read_json_object(path)

    def clear_action(self, task_id: str) -> None:
        path = self.actions_root / f"{task_id}.json"
        if path.exists():
            path.unlink()

    @property
    def checkpoint_path(self) -> Path:
        self.runtime_root.mkdir(parents=True, exist_ok=True)
        return self.runtime_root / "checkpoints.sqlite"
