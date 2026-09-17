from __future__ import annotations

from pathlib import Path
import re

from .config import HarnessConfig
from .utils import estimate_tokens


class ContextService:
    def __init__(self, root: Path, config: HarnessConfig) -> None:
        self.root = root
        self.config = config

    def docs_index(self, worktree: Path) -> str:
        docs = worktree / self.config.workflow.docs_root
        if not docs.is_dir():
            return "docs/ não existe neste projeto."
        lines = ["# Docs index", ""]
        for path in sorted(docs.rglob("*.md")):
            relative = path.relative_to(worktree)
            text = path.read_text(encoding="utf-8", errors="replace")
            headings = [
                line.strip()
                for line in text.splitlines()
                if re.match(r"^#{1,3}\s+", line)
            ][:8]
            title = headings[0].lstrip("# ") if headings else "(sem heading)"
            lines.append(f"- `{relative}` — {title} — ~{estimate_tokens(text)} tokens")
            for heading in headings[1:4]:
                lines.append(f"  - {heading}")
        return "\n".join(lines) + "\n"

    def docs_hint(self, worktree: Path) -> tuple[str, ...]:
        candidates = (
            "docs/context.md",
            "docs/tree.md",
            "docs/architecture.md",
        )
        return tuple(item for item in candidates if (worktree / item).is_file())
