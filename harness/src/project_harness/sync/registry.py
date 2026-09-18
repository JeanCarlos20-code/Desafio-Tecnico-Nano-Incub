from __future__ import annotations

from pathlib import Path

from ..errors import HarnessError
from ..types import Target
from ..utils import as_list, as_object, as_str, read_yaml


class TargetRegistry:
    def __init__(self, root: Path) -> None:
        self.root = root
        self.targets_root = root / "harness" / "targets"

    def all(self) -> tuple[Target, ...]:
        targets = tuple(self._load(path) for path in sorted(self.targets_root.glob("*.yaml")))
        ids = [item.id for item in targets]
        if len(ids) != len(set(ids)):
            raise HarnessError("Targets duplicados.")
        return targets

    def select(self, ids: tuple[str, ...] | None) -> tuple[Target, ...]:
        available = {item.id: item for item in self.all()}
        if not ids:
            return tuple(available.values())
        unknown = [item for item in ids if item not in available]
        if unknown:
            raise HarnessError("Targets desconhecidos: " + ", ".join(unknown))
        return tuple(available[item] for item in ids)

    def _load(self, path: Path) -> Target:
        data = read_yaml(path)
        skills = tuple(str(item) for item in as_list(data.get("skill_dirs")) if isinstance(item, str))
        if not skills:
            raise HarnessError(f"Target sem skill_dirs: {path}")
        return Target(
            id=as_str(data.get("id")),
            display_name=as_str(data.get("display_name")),
            agent_mode=as_str(data.get("agent_mode")),
            agent_dir=as_str(data.get("agent_dir")),
            skill_dirs=skills,
            instruction_mode=as_str(data.get("instruction_mode")),
            mcp=as_object(data.get("mcp")),
        )
