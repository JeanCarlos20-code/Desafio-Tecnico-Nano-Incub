#!/usr/bin/env python3
"""Group dirty paths into atomic commits: module, tests separate, no oversized blocks."""

from __future__ import annotations

from dataclasses import dataclass
import argparse
import json
from pathlib import Path


TEST_PREFIXES = ("tests/", "harness/tests/")
TEST_SUFFIXES = (".test.js", ".test.ts", ".test.jsx", ".test.tsx", ".spec.ts", ".spec.js")
META_PREFIXES = ("docs/", ".specs/", ".cursor/")
META_ROOT_FILES = {".gitignore", "README.md", "LICENSE", "LICENSE.txt"}
MAX_PATHS_PER_COMMIT = 8
LAYER_DIRS = {"domain", "application", "infra", "http", "database", "unit", "feature"}


@dataclass(frozen=True)
class ChangeGroup:
    module: str
    kind: str
    paths: tuple[str, ...]
    area: str = ""


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


def _posix_parts(path: str) -> tuple[str, ...]:
    return Path(path.replace("\\", "/")).parts


def _common_area(paths: tuple[str, ...]) -> str:
    if not paths:
        return ""
    parts_list = [_posix_parts(item) for item in paths]
    common: list[str] = []
    for index, piece in enumerate(parts_list[0]):
        if all(len(item) > index and item[index] == piece for item in parts_list):
            common.append(piece)
        else:
            break
    return "/".join(common)


def _chunk(paths: list[str], size: int) -> list[tuple[str, ...]]:
    ordered = sorted(paths)
    return [tuple(ordered[index : index + size]) for index in range(0, len(ordered), size)]


def split_oversized(paths: list[str], depth: int = 0) -> list[tuple[str, ...]]:
    ordered = sorted({item.replace("\\", "/") for item in paths if item.strip()})
    if len(ordered) <= MAX_PATHS_PER_COMMIT:
        return [tuple(ordered)]
    buckets: dict[str, list[str]] = {}
    for path in ordered:
        parts = _posix_parts(path)
        key = "/".join(parts[: depth + 1]) if len(parts) > depth else path
        buckets.setdefault(key, []).append(path)
    if all(len(_posix_parts(path)) <= depth + 1 for path in ordered):
        return _chunk(ordered, MAX_PATHS_PER_COMMIT)
    if len(buckets) == 1:
        return split_oversized(ordered, depth + 1)
    result: list[tuple[str, ...]] = []
    for key in sorted(buckets):
        result.extend(split_oversized(buckets[key], depth + 1))
    return result


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
        for chunk in split_oversized(group_paths_list):
            groups.append(
                ChangeGroup(
                    module=module,
                    kind=kind,
                    paths=chunk,
                    area=_common_area(chunk),
                )
            )
    return tuple(groups)


def groups_as_json(groups: tuple[ChangeGroup, ...]) -> str:
    payload = [
        {
            "module": group.module,
            "kind": group.kind,
            "area": group.area,
            "paths": list(group.paths),
        }
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
        _ = root
    print(groups_as_json(group_paths(paths)), end="")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
