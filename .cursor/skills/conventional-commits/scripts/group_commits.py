#!/usr/bin/env python3
"""Group dirty paths into one commit per module, tests separate from production."""

from __future__ import annotations

from dataclasses import dataclass
import argparse
import json
from pathlib import Path


TEST_PREFIXES = ("tests/", "harness/tests/")
TEST_SUFFIXES = (".test.js", ".test.ts", ".test.jsx", ".test.tsx", ".spec.ts", ".spec.js")
META_PREFIXES = ("docs/", ".specs/", ".cursor/")
META_ROOT_FILES = {".gitignore", "README.md", "LICENSE", "LICENSE.txt"}


@dataclass(frozen=True)
class ChangeGroup:
    module: str
    kind: str
    paths: tuple[str, ...]


def is_test_path(path: str) -> bool:
    normalized = path.replace("\\", "/")
    name = Path(normalized).name
    if normalized.startswith(TEST_PREFIXES) or "/tests/" in f"/{normalized}":
        return True
    if name.startswith("test_") and name.endswith(".py"):
        return True
    if name.endswith("Test.php"):
        return True
    return any(normalized.endswith(suffix) for suffix in TEST_SUFFIXES)


def is_meta_path(path: str) -> bool:
    normalized = path.replace("\\", "/")
    if normalized in META_ROOT_FILES:
        return True
    return any(normalized.startswith(prefix) for prefix in META_PREFIXES)


LAYER_DIRS = {"domain", "application", "infra", "http", "database", "unit", "feature"}


def module_of(path: str) -> str:
    parts = Path(path.replace("\\", "/")).parts
    if not parts:
        return "repo"
    if parts[0] == "app" and len(parts) > 2 and parts[1] == "Modules":
        return parts[2].lower()
    if parts[0] == "tests" and len(parts) > 2 and parts[1] in {"Unit", "Feature"}:
        return parts[2].lower()
    if len(parts) >= 4 and parts[:3] == ("resources", "js", "Pages"):
        return parts[3].lower()
    if parts[0] == "harness":
        return "harness"
    if parts[0] == "docs":
        return "docs"
    if parts[0] == ".specs":
        return "specs"
    if parts[0] == ".cursor":
        return "cursor"
    if parts[0] == "app" and len(parts) > 1 and parts[1] == "Http":
        return "http"
    if parts[0] in {"database", "routes", "config"}:
        return parts[0]
    if parts[0] == "resources":
        return "resources"
    if len(parts) == 1:
        return Path(parts[0]).stem.lower().replace("_", "-")
    name = parts[0].lower()
    if name in LAYER_DIRS:
        return "repo"
    return name


def kind_of(path: str) -> str:
    if is_test_path(path):
        return "test"
    if is_meta_path(path):
        return "meta"
    return "code"


def group_paths(paths: tuple[str, ...] | list[str]) -> tuple[ChangeGroup, ...]:
    buckets: dict[tuple[str, str], list[str]] = {}
    for raw in paths:
        path = raw.strip().replace("\\", "/")
        if not path:
            continue
        key = (kind_of(path), module_of(path))
        buckets.setdefault(key, []).append(path)
    kind_order = {"code": 0, "test": 1, "meta": 2}
    ordered = sorted(buckets.items(), key=lambda item: (kind_order.get(item[0][0], 9), item[0][1]))
    groups: list[ChangeGroup] = []
    for (kind, module), group_paths_list in ordered:
        groups.append(ChangeGroup(module=module, kind=kind, paths=tuple(group_paths_list)))
    return tuple(groups)


def groups_as_json(groups: tuple[ChangeGroup, ...]) -> str:
    payload = [
        {"module": group.module, "kind": group.kind, "paths": list(group.paths)}
        for group in groups
    ]
    return json.dumps(payload, indent=2, ensure_ascii=True) + "\n"


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Group dirty paths for atomic commits.")
    parser.add_argument("--root", default=".", help="repository root (for display only)")
    parser.add_argument("--path", action="append", dest="paths", default=[])
    args = parser.parse_args(argv)
    paths = tuple(args.paths)
    if not paths:
        root = Path(args.root)
        # Caller should pass --path; empty means no groups.
        _ = root
    print(groups_as_json(group_paths(paths)), end="")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
