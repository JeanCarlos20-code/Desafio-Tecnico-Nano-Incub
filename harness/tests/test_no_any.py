from __future__ import annotations

from pathlib import Path
import re


def test_source_does_not_use_typing_any() -> None:
    root = Path(__file__).resolve().parents[1]
    offenders: list[str] = []
    pattern = re.compile(r"\bAny\b")
    for directory in (root / "src", root / "tests"):
        for path in directory.rglob("*.py"):
            if path.name == "test_no_any.py":
                continue
            text = path.read_text(encoding="utf-8")
            if pattern.search(text):
                offenders.append(str(path.relative_to(root)))
    assert offenders == []
