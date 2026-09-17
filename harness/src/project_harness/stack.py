from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

import yaml

from .errors import HarnessError
from .types import JSONObject, JSONValue
from .utils import as_list, as_object, as_str, read_yaml


@dataclass(frozen=True)
class Command:
    command: str


@dataclass(frozen=True)
class Project:
    name: str
    type: str
    languages: tuple[str, ...]
    frameworks: tuple[str, ...]
    data_access: tuple[str, ...]
    testing: tuple[str, ...]


@dataclass(frozen=True)
class Component:
    name: str
    root: str
    languages: tuple[str, ...]
    frameworks: tuple[str, ...]
    data_access: tuple[str, ...]
    testing: tuple[str, ...]
    commands: dict[str, Command]


@dataclass(frozen=True)
class Infrastructure:
    databases: tuple[str, ...]
    cache: tuple[str, ...]
    queues: tuple[str, ...]


@dataclass(frozen=True)
class ProjectStack:
    project: Project
    components: tuple[Component, ...]
    infrastructure: Infrastructure


class StackLoader:
    RELATIVE_PATH = Path("harness") / "stack.yml"

    def locate(self, root: Path) -> Path:
        path = root / self.RELATIVE_PATH
        if not path.is_file():
            raise HarnessError(f"YAML não encontrado: stack.yml ({path})")
        return path

    def load(self, root: Path) -> ProjectStack:
        return self.validate(self.locate(root))

    def validate(self, path: Path) -> ProjectStack:
        try:
            data = read_yaml(path)
        except yaml.YAMLError as error:
            raise HarnessError(f"YAML inválido em {path}: {error}") from error
        return _parse_stack(data, path)


def compact_summary(stack: ProjectStack) -> str:
    infra = stack.infrastructure
    lines = [
        "## Stack do projeto",
        "",
        f"- nome do projeto: `{stack.project.name}`",
        f"- tipo do projeto: `{stack.project.type}`",
        f"- linguagens do projeto: {_join(stack.project.languages)}",
        f"- frameworks do projeto: {_join(stack.project.frameworks)}",
        f"- data_access do projeto: {_join(stack.project.data_access)}",
        f"- testing do projeto: {_join(stack.project.testing)}",
        f"- infrastructure databases: {_join(infra.databases)}",
        f"- infrastructure cache: {_join(infra.cache)}",
        f"- infrastructure queues: {_join(infra.queues)}",
        "",
        "### Componentes",
        "",
    ]
    for component in stack.components:
        command_ids = ", ".join(component.commands.keys()) or "(nenhum)"
        lines.extend(
            [
                f"- `{component.name}`",
                f"  - root: `{component.root}`",
                f"  - languages: {_join(component.languages)}",
                f"  - frameworks: {_join(component.frameworks)}",
                f"  - data_access: {_join(component.data_access)}",
                f"  - testing: {_join(component.testing)}",
                f"  - command IDs: {command_ids}",
            ]
        )
    return "\n".join(lines) + "\n"


def select_affected_components(
    components: tuple[Component, ...],
    paths: tuple[str, ...],
) -> tuple[Component, ...]:
    selected: list[Component] = []
    seen: set[str] = set()
    for path in paths:
        match = _match_component(components, path)
        if match is None or match.name in seen:
            continue
        seen.add(match.name)
        selected.append(match)
    return tuple(selected)


def merge_verify_paths(
    components: tuple[Component, ...],
    porcelain: tuple[str, ...],
    ignored: tuple[str, ...],
) -> tuple[str, ...]:
    merged: list[str] = []
    seen: set[str] = set()
    for path in porcelain:
        if path in seen:
            continue
        seen.add(path)
        merged.append(path)
    for path in ignored:
        if path in seen:
            continue
        match = _match_component(components, path)
        if match is None or not _normalize_root(match.root):
            continue
        seen.add(path)
        merged.append(path)
    return tuple(merged)


def _match_component(components: tuple[Component, ...], path: str) -> Component | None:
    matches = [item for item in components if _path_matches_root(item.root, path)]
    if not matches:
        return None
    return max(matches, key=lambda item: len(_normalize_root(item.root)))


def component_cwd(worktree: Path, component: Component) -> Path:
    worktree_resolved = worktree.resolve()
    raw_root = component.root.strip() or "."
    cwd = (worktree_resolved / raw_root).resolve()
    try:
        cwd.relative_to(worktree_resolved)
    except ValueError as error:
        raise HarnessError(
            f"component.root escapa da worktree: {component.name} root={component.root!r}"
        ) from error
    return cwd


def _normalize_root(root: str) -> str:
    normalized = root.strip().replace("\\", "/").rstrip("/")
    if normalized in ("", "."):
        return ""
    return normalized


def _path_matches_root(root: str, path: str) -> bool:
    prefix = _normalize_root(root)
    posix = path.replace("\\", "/").lstrip("./")
    if prefix == "":
        return True
    return posix == prefix or posix.startswith(prefix + "/")


def _join(values: tuple[str, ...]) -> str:
    return ", ".join(values) if values else "(none)"


def _parse_stack(data: JSONObject, path: Path) -> ProjectStack:
    project_raw = data.get("project")
    if project_raw is None:
        raise HarnessError(f"{path}: project ausente.")
    components_raw = as_list(data.get("components"))
    if not components_raw:
        raise HarnessError(f"{path}: components não pode ser vazio.")
    components: list[Component] = []
    for index, raw in enumerate(components_raw):
        item = as_object(raw)
        components.append(_parse_component(item, f"{path} components[{index}]"))
    return ProjectStack(
        project=_parse_project(as_object(project_raw), f"{path} project"),
        components=tuple(components),
        infrastructure=_parse_infrastructure(as_object(data.get("infrastructure"))),
    )


def _parse_project(raw: JSONObject, label: str) -> Project:
    name = as_str(raw.get("name")).strip()
    project_type = as_str(raw.get("type")).strip()
    if not name or not project_type:
        raise HarnessError(f"{label}: name e type são obrigatórios.")
    return Project(
        name=name,
        type=project_type,
        languages=_str_seq(raw.get("languages"), f"{label} languages"),
        frameworks=_str_seq(raw.get("frameworks"), f"{label} frameworks"),
        data_access=_str_seq(raw.get("data_access"), f"{label} data_access"),
        testing=_str_seq(raw.get("testing"), f"{label} testing"),
    )


def _parse_component(raw: JSONObject, label: str) -> Component:
    name = as_str(raw.get("name")).strip()
    root = as_str(raw.get("root")).strip()
    if not name or not root:
        raise HarnessError(f"{label}: name e root são obrigatórios.")
    return Component(
        name=name,
        root=root,
        languages=_str_seq(raw.get("languages"), f"{label} languages"),
        frameworks=_str_seq(raw.get("frameworks"), f"{label} frameworks"),
        data_access=_str_seq(raw.get("data_access"), f"{label} data_access"),
        testing=_str_seq(raw.get("testing"), f"{label} testing"),
        commands=_parse_commands(raw.get("commands"), f"{label} commands"),
    )


def _parse_infrastructure(raw: JSONObject) -> Infrastructure:
    return Infrastructure(
        databases=_str_seq(raw.get("databases"), "infrastructure databases"),
        cache=_str_seq(raw.get("cache"), "infrastructure cache"),
        queues=_str_seq(raw.get("queues"), "infrastructure queues"),
    )


def _parse_commands(raw: JSONValue, label: str) -> dict[str, Command]:
    if raw is None:
        return {}
    obj = as_object(raw)
    commands: dict[str, Command] = {}
    for key, value in obj.items():
        item = as_object(value)
        command = as_str(item.get("command")).strip()
        if not command:
            raise HarnessError(f"{label}: comando '{key}' está vazio.")
        commands[key] = Command(command=command)
    return commands


def _str_seq(value: JSONValue, label: str) -> tuple[str, ...]:
    if value is None:
        return ()
    items = as_list(value)
    result: list[str] = []
    for item in items:
        if not isinstance(item, str):
            raise HarnessError(f"{label} precisa ser lista de strings.")
        result.append(item)
    return tuple(result)
