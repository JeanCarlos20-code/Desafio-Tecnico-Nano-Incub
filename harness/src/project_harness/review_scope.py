from __future__ import annotations

from collections.abc import Iterable, Mapping
import re

_PATH_IN_TEXT = re.compile(r"(?:^|[`\s])([^\s`:]+?\.\w+)(?::\d+)?")


def route_after_checks(
    *,
    required_passed: bool,
    check_fix_round: int,
    max_repair_rounds: int,
) -> str:
    if required_passed:
        return "review"
    if check_fix_round < max_repair_rounds:
        return "repair"
    return "notify"


def route_after_review(
    *,
    approved: bool,
    required_passed: bool,
    review_round: int,
    max_repair_rounds: int,
) -> str:
    if approved and required_passed:
        return "approved"
    if review_round < max_repair_rounds:
        return "repair"
    return "escalate"


def _normalize_path(value: str) -> str:
    cleaned = value.strip().strip("`")
    if ":" in cleaned:
        head, tail = cleaned.rsplit(":", 1)
        if tail.isdigit():
            cleaned = head
    return cleaned.replace("\\", "/")


def _unique(paths: Iterable[str]) -> tuple[str, ...]:
    seen: list[str] = []
    for raw in paths:
        path = _normalize_path(str(raw))
        if path and path not in seen:
            seen.append(path)
    return tuple(seen)


def _paths_from_text(text: str) -> tuple[str, ...]:
    found: list[str] = []
    for match in _PATH_IN_TEXT.finditer(f" {text}"):
        found.append(match.group(1))
    return _unique(found)


def _string_items(value: object) -> tuple[str, ...]:
    if not isinstance(value, list):
        return ()
    return tuple(item for item in value if isinstance(item, str))


def cited_review_paths(
    *,
    finding_paths: Iterable[str],
    positives: Iterable[str],
    unverified: Iterable[str],
) -> tuple[str, ...]:
    extracted: list[str] = list(finding_paths)
    for text in positives:
        extracted.extend(_paths_from_text(text))
    for text in unverified:
        extracted.extend(_paths_from_text(text))
    return _unique(extracted)


def paths_from_consolidated(data: Mapping[str, object]) -> tuple[str, ...]:
    finding_paths: list[str] = []
    findings = data.get("findings")
    if isinstance(findings, list):
        for item in findings:
            if isinstance(item, Mapping):
                path = item.get("path")
                if isinstance(path, str) and path.strip():
                    finding_paths.append(path)
    return cited_review_paths(
        finding_paths=finding_paths,
        positives=_string_items(data.get("positives")),
        unverified=_string_items(data.get("unverified")),
    )


def union_presented_paths(*groups: Iterable[str]) -> tuple[str, ...]:
    merged: list[str] = []
    for group in groups:
        merged.extend(group)
    return _unique(merged)


def split_dirty_and_carried(
    dirty: Iterable[str],
    presented: Iterable[str],
) -> tuple[tuple[str, ...], tuple[str, ...]]:
    dirty_paths = union_presented_paths(dirty)
    dirty_set = set(dirty_paths)
    carried = tuple(path for path in union_presented_paths(presented) if path not in dirty_set)
    return dirty_paths, carried
