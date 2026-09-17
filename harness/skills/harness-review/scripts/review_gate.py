#!/usr/bin/env python3
"""Deterministic gate for harness-review JSON. Stdlib only. Fail closed."""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

TRACKS = frozenset({"architecture", "security", "smells", "tests", "consolidated"})
SPECIALIZED = frozenset({"architecture", "security", "smells", "tests"})
SEVERITIES = frozenset({"blocker", "high", "medium"})
VERDICTS = frozenset({"APPROVED", "REJECTED"})
ID_RE = re.compile(r"^[A-Z]+-[0-9]{3}$")
INTERNAL_REF = re.compile(r"^.+:\d+$")
HTTP_REF = re.compile(r"^https://", re.I)


def errors_for(report: dict) -> list[str]:
    out: list[str] = []
    track = report.get("track")
    if track not in TRACKS:
        out.append("track: must be architecture|security|smells|tests|consolidated")
        return out

    summary = report.get("summary")
    if not isinstance(summary, str) or not summary.strip():
        out.append("summary: required non-empty string")

    findings = report.get("findings")
    if not isinstance(findings, list):
        out.append("findings: must be an array")
        return out

    seen_ids: set[str] = set()
    blocking: list[str] = []
    for i, item in enumerate(findings):
        label = f"findings[{i}]"
        if not isinstance(item, dict):
            out.append(f"{label}: must be an object")
            continue
        fid = item.get("id")
        if not isinstance(fid, str) or not ID_RE.match(fid):
            out.append(f"{label}.id: must match /^[A-Z]+-[0-9]{{3}}$/")
        elif fid in seen_ids:
            out.append(f"{label}.id: duplicate {fid}")
        else:
            seen_ids.add(fid)

        ftrack = item.get("track")
        if ftrack not in SPECIALIZED:
            out.append(f"{label}.track: must be a specialized track")
        elif track in SPECIALIZED and ftrack != track:
            out.append(f"{label}.track: must equal report track {track}")

        severity = item.get("severity")
        if severity not in SEVERITIES:
            out.append(f"{label}.severity: must be blocker|high|medium")
        elif severity in {"blocker", "high"} and isinstance(fid, str):
            blocking.append(fid)

        path = item.get("path")
        if not isinstance(path, str) or not path.strip():
            out.append(f"{label}.path: required")

        line = item.get("line")
        if line is not None and (not isinstance(line, int) or line < 1):
            out.append(f"{label}.line: must be a positive integer or null")

        for field in ("problem", "impact", "fix"):
            value = item.get(field)
            if not isinstance(value, str) or not value.strip():
                out.append(f"{label}.{field}: required non-empty string")

        evidence = item.get("evidence")
        if not isinstance(evidence, list) or not evidence:
            out.append(f"{label}.evidence: at least one entry required")
        else:
            for j, ev in enumerate(evidence):
                elabel = f"{label}.evidence[{j}]"
                if not isinstance(ev, dict):
                    out.append(f"{elabel}: must be an object")
                    continue
                etype = ev.get("type")
                ref = ev.get("ref")
                if etype not in {"internal", "external"}:
                    out.append(f"{elabel}.type: internal|external")
                if not isinstance(ref, str) or not ref.strip():
                    out.append(f"{elabel}.ref: required")
                elif etype == "internal" and not INTERNAL_REF.match(ref):
                    out.append(f"{elabel}.ref: internal evidence must be path:line")
                elif etype == "external" and not HTTP_REF.match(ref):
                    out.append(f"{elabel}.ref: external evidence must be an https URL")

        if severity in {"blocker", "high"}:
            evs = item.get("evidence") if isinstance(item.get("evidence"), list) else []
            if not any(isinstance(ev, dict) and ev.get("type") == "internal" for ev in evs):
                out.append(f"{label}: blocker/high requires internal path:line evidence")

    if track == "consolidated":
        verdict = report.get("verdict")
        if verdict not in VERDICTS:
            out.append("consolidated.verdict: APPROVED|REJECTED")
        expected = "REJECTED" if blocking else "APPROVED"
        if verdict in VERDICTS and verdict != expected:
            out.append(
                f"consolidated.verdict: expected {expected} given "
                f"{len(blocking)} blocker/high finding(s)"
            )
        handoff = report.get("execute_handoff")
        if not isinstance(handoff, dict):
            out.append("consolidated.execute_handoff: required object")
        else:
            action = handoff.get("action")
            expected_action = "repair" if blocking else "complete"
            if action != expected_action:
                out.append(
                    f"execute_handoff.action: expected {expected_action} "
                    f"(blocker/high present={bool(blocking)})"
                )
            ids = handoff.get("blocking_ids")
            if not isinstance(ids, list) or set(ids) != set(blocking):
                out.append("execute_handoff.blocking_ids: must equal blocker+high ids")
            if expected_action == "repair":
                instructions = handoff.get("instructions")
                if not isinstance(instructions, str) or not instructions.strip():
                    out.append("execute_handoff.instructions: required when action=repair")
    return out


def main(argv: list[str]) -> int:
    if len(argv) != 2:
        print("usage: review_gate.py <report.json>", file=sys.stderr)
        return 2
    path = Path(argv[1])
    try:
        report = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        print(f"cannot read JSON: {exc}", file=sys.stderr)
        return 2
    if not isinstance(report, dict):
        print("report must be a JSON object", file=sys.stderr)
        return 1
    problems = errors_for(report)
    if problems:
        print("GATE FAIL")
        for problem in problems:
            print(f"- {problem}")
        return 1
    print("GATE PASS")
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
