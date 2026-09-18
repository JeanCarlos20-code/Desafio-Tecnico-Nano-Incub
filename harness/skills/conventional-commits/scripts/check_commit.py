#!/usr/bin/env python3
"""Validate type(module): English Conventional Commit messages."""

from __future__ import annotations

import argparse
import re
import sys

TYPES = ("feat", "fix", "chore", "test")
HEADER_RE = re.compile(
    r"^(?P<type>feat|fix|chore|test)\((?P<module>[^)]+)\): (?P<desc>.+)$"
)


def read_message(args: argparse.Namespace) -> str:
    if args.message is not None:
        return args.message
    if args.msgfile:
        with open(args.msgfile, encoding="utf-8") as handle:
            return handle.read()
    if not sys.stdin.isatty():
        return sys.stdin.read()
    return ""


def check(message: str) -> tuple[list[str], list[str]]:
    errors: list[str] = []
    warnings: list[str] = []
    lines = [line for line in message.splitlines() if not line.lstrip().startswith("#")]
    while lines and not lines[0].strip():
        lines.pop(0)
    if not lines:
        return (["empty commit message"], warnings)

    header = lines[0].rstrip()
    if len(header) > 72:
        warnings.append(f"header is {len(header)} chars (>72)")

    match = HEADER_RE.match(header)
    if not match:
        errors.append(
            f"header must be 'type(module): message' with type in {', '.join(TYPES)}: {header!r}"
        )
        return (errors, warnings)

    desc = match.group("desc")
    module = match.group("module").strip()
    if not module:
        errors.append("module scope is empty")
    if not desc.strip():
        errors.append("description is empty")
    else:
        if desc[:1].isupper():
            errors.append(f"description should start lowercase: {desc[:40]!r}")
        if desc.rstrip().endswith("."):
            errors.append("description should not end with a period")
    return (errors, warnings)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Validate Conventional Commit messages.")
    parser.add_argument("msgfile", nargs="?", default=None)
    parser.add_argument("--message", default=None)
    args = parser.parse_args(argv)
    message = read_message(args)
    if not message.strip():
        print("check_commit: no message provided.", file=sys.stderr)
        return 2
    errors, warnings = check(message)
    for warning in warnings:
        print(f"  WARN  {warning}")
    for error in errors:
        print(f"  ERROR {error}")
    if errors:
        print("\ncheck_commit: FAIL")
        return 1
    print("check_commit: OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
