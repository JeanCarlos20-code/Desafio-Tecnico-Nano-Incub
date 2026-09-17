from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
import importlib.util
import sys

from .errors import HarnessError
from .git_manager import GitManager


@dataclass(frozen=True)
class PreparedCommit:
    message: str
    paths: tuple[str, ...]


def skill_script(name: str) -> Path:
    return Path(__file__).resolve().parents[2] / "skills" / "conventional-commits" / "scripts" / name


def _load(name: str) -> object:
    path = skill_script(name)
    if not path.is_file():
        raise HarnessError(f"Skill conventional-commits ausente: {path}")
    spec = importlib.util.spec_from_file_location(f"harness_commit_{path.stem}", path)
    if spec is None or spec.loader is None:
        raise HarnessError(f"Não foi possível carregar {path}")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def validate_message(message: str) -> None:
    module = _load("check_commit.py")
    check = getattr(module, "check")
    errors, _warnings = check(message)
    if errors:
        raise HarnessError("commit message rejeitada pela skill conventional-commits: " + "; ".join(errors))


def parse_header(message: str) -> tuple[str, str]:
    module = _load("check_commit.py")
    header_re = getattr(module, "HEADER_RE")
    lines = [line for line in message.splitlines() if line.strip() and not line.lstrip().startswith("#")]
    if not lines:
        raise HarnessError("commit message vazia.")
    match = header_re.match(lines[0].rstrip())
    if match is None:
        raise HarnessError(f"commit message fora do formato type(module): {message!r}")
    return match.group("type"), match.group("module")


def group_changed_paths(paths: tuple[str, ...]) -> tuple[object, ...]:
    module = _load("group_commits.py")
    group_paths = getattr(module, "group_paths")
    return tuple(group_paths(paths))


def match_commits(paths: tuple[str, ...], messages: tuple[str, ...]) -> tuple[PreparedCommit, ...]:
    if not paths:
        raise HarnessError("Não há alterações para commit.")
    for message in messages:
        validate_message(message)
    groups = group_changed_paths(paths)
    if len(groups) == 1 and len(messages) == 1:
        group = groups[0]
        _type, module = parse_header(messages[0])
        if module != group.module:
            raise HarnessError(
                f"scope '{module}' não bate com o módulo agrupado '{group.module}'. "
                "Siga harness/skills/conventional-commits."
            )
        if _type == "test" and group.kind != "test":
            raise HarnessError("type test só pode commitar arquivos de teste.")
        if group.kind == "test" and _type != "test":
            raise HarnessError("arquivos de teste exigem type test(module).")
        return (PreparedCommit(message=messages[0], paths=group.paths),)

    unused = list(messages)
    prepared: list[PreparedCommit] = []
    for group in groups:
        match_index = None
        for index, message in enumerate(unused):
            commit_type, module = parse_header(message)
            if module != group.module:
                continue
            if group.kind == "test" and commit_type != "test":
                continue
            if group.kind != "test" and commit_type == "test":
                continue
            match_index = index
            break
        if match_index is None:
            raise HarnessError(
                f"tasks.md não tem commit para {group.kind}({group.module}). "
                "Separe por módulo e testes conforme harness/skills/conventional-commits."
            )
        message = unused.pop(match_index)
        prepared.append(PreparedCommit(message=message, paths=group.paths))
    if unused:
        raise HarnessError("commits planejados sem arquivos correspondentes: " + "; ".join(unused))
    return tuple(prepared)


def apply_commit_plan(git: GitManager, worktree: Path, messages: tuple[str, ...]) -> str:
    paths = git.changed_paths(worktree)
    prepared = match_commits(paths, messages)
    last = ""
    for item in prepared:
        last = git.commit_paths(worktree, item.paths, item.message)
    leftover = git.changed_paths(worktree)
    if leftover:
        raise HarnessError("arquivos ficaram de fora dos commits atômicos: " + ", ".join(leftover))
    return last
