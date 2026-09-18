from __future__ import annotations

from hashlib import sha256
from pathlib import Path
import json
import os
import re
import subprocess
import unicodedata
from typing import cast

import yaml

from .errors import HarnessError
from .types import JSONObject, JSONValue


def run_process(
    command: list[str],
    *,
    cwd: Path,
    check: bool = False,
    env: dict[str, str] | None = None,
) -> subprocess.CompletedProcess[str]:
    merged_env = dict(os.environ)
    if env:
        merged_env.update(env)
    result = subprocess.run(
        command,
        cwd=cwd,
        text=True,
        capture_output=True,
        env=merged_env,
        check=False,
    )
    if check and result.returncode != 0:
        raise HarnessError(
            f"Comando falhou ({result.returncode}): {' '.join(command)}\n"
            f"{result.stderr.strip() or result.stdout.strip()}"
        )
    return result


def git(root: Path, *args: str, check: bool = True) -> str:
    result = run_process(["git", *args], cwd=root, check=check)
    return result.stdout.strip()


def find_repository_root(start: Path | None = None) -> Path:
    """Return the primary worktree root even when invoked inside a task worktree."""
    current = (start or Path.cwd()).resolve()
    result = run_process(["git", "rev-parse", "--show-toplevel"], cwd=current)
    if result.returncode != 0:
        raise HarnessError("Execute o harness dentro de um repositório Git.")
    local_root = Path(result.stdout.strip()).resolve()

    worktrees = run_process(["git", "worktree", "list", "--porcelain"], cwd=local_root)
    primary = local_root
    if worktrees.returncode == 0:
        for line in worktrees.stdout.splitlines():
            if line.startswith("worktree "):
                primary = Path(line.removeprefix("worktree ").strip()).resolve()
                break

    root = primary if (primary / "harness" / "pyproject.toml").is_file() else local_root
    if not (root / "harness" / "pyproject.toml").is_file():
        raise HarnessError("O repositório não contém harness/pyproject.toml.")
    return root


def git_common_dir(root: Path) -> Path:
    raw = git(root, "rev-parse", "--git-common-dir")
    path = Path(raw)
    if not path.is_absolute():
        path = root / path
    return path.resolve()


def slugify(value: str) -> str:
    ascii_value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode("ascii")
    normalized = ascii_value.lower()
    normalized = re.sub(r"[^a-z0-9]+", "-", normalized).strip("-")
    return normalized[:64] or "task"


def read_yaml(path: Path) -> JSONObject:
    if not path.is_file():
        raise HarnessError(f"YAML não encontrado: {path}")
    raw: object = yaml.safe_load(path.read_text(encoding="utf-8")) or {}
    return require_object(raw, f"YAML {path}")


def write_json(path: Path, value: JSONValue) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def read_json_object(path: Path) -> JSONObject:
    if not path.is_file():
        raise HarnessError(f"JSON não encontrado: {path}")
    try:
        raw: object = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as error:
        raise HarnessError(f"JSON inválido em {path}: {error}") from error
    return require_object(raw, f"JSON {path}")


def require_object(value: object, label: str) -> JSONObject:
    if not isinstance(value, dict):
        raise HarnessError(f"{label} precisa ser objeto.")
    result: JSONObject = {}
    for key, item in value.items():
        if not isinstance(key, str):
            raise HarnessError(f"{label} contém chave não textual.")
        result[key] = to_json_value(item, label)
    return result


def to_json_value(value: object, label: str) -> JSONValue:
    if value is None or isinstance(value, (bool, int, float, str)):
        return value
    if isinstance(value, list):
        return [to_json_value(item, label) for item in value]
    if isinstance(value, dict):
        result: dict[str, JSONValue] = {}
        for key, item in value.items():
            if not isinstance(key, str):
                raise HarnessError(f"{label} contém chave não textual.")
            result[key] = to_json_value(item, label)
        return result
    raise HarnessError(f"{label} contém valor não serializável: {type(value).__name__}")


def parse_frontmatter(content: str, path: Path) -> tuple[JSONObject, str]:
    if not content.startswith("---\n"):
        raise HarnessError(f"Frontmatter ausente: {path}")
    parts = content.split("---", 2)
    if len(parts) != 3:
        raise HarnessError(f"Frontmatter inválido: {path}")
    raw: object = yaml.safe_load(parts[1]) or {}
    return require_object(raw, f"Frontmatter {path}"), parts[2].lstrip("\n")


def sha256_file(path: Path) -> str:
    digest = sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def sha256_text(value: str) -> str:
    return sha256(value.encode("utf-8")).hexdigest()


def estimate_tokens(text: str) -> int:
    return max(1, len(text) // 4)


def as_str(value: JSONValue, default: str = "") -> str:
    return value if isinstance(value, str) else default


def as_bool(value: JSONValue, default: bool = False) -> bool:
    return value if isinstance(value, bool) else default


def as_list(value: JSONValue) -> list[JSONValue]:
    return list(value) if isinstance(value, list) else []


def as_object(value: JSONValue) -> JSONObject:
    return cast(JSONObject, value) if isinstance(value, dict) else {}
