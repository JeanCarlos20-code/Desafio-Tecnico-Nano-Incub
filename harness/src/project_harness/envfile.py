from __future__ import annotations

from pathlib import Path
import re

from .errors import HarnessError

MCP_ENV_FILENAME = ".env.mcp"
_KEY = re.compile(r"^[A-Za-z_][A-Za-z0-9_]*$")


def parse_env_file(path: Path) -> dict[str, str]:
    text = path.read_text(encoding="utf-8")
    if text.startswith("\ufeff"):
        text = text[1:]
    result: dict[str, str] = {}
    for index, raw in enumerate(text.splitlines(), start=1):
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if line.startswith("export "):
            line = line.removeprefix("export ").strip()
        if "=" not in line:
            raise HarnessError(f"{path.name}:{index} linha inválida.")
        key, _, value = line.partition("=")
        key = key.strip()
        if not _KEY.match(key):
            raise HarnessError(f"{path.name}:{index} chave inválida: {key}")
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in {'"', "'"}:
            value = value[1:-1]
        result[key] = value
    return result


def load_mcp_env(root: Path) -> dict[str, str]:
    path = root / MCP_ENV_FILENAME
    if not path.is_file():
        return {}
    return parse_env_file(path)
