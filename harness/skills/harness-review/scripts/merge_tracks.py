#!/usr/bin/env python3
"""Merge specialized track reports and apply the harness gate. Stdlib only."""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from review_gate import errors_for

TRACK_ORDER = ("architecture", "security", "smells", "tests")


def load_report(path: Path) -> dict:
    data = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(data, dict):
        raise ValueError(f"{path}: not a JSON object")
    problems = errors_for(data)
    if problems:
        joined = "; ".join(problems)
        raise ValueError(f"{path}: {joined}")
    return data


def merge(reports: list[dict]) -> dict:
    findings: list[dict] = []
    unverified: list[str] = []
    positives: list[str] = []
    summaries: list[str] = []
    seen: set[tuple[str, str, int | None, str]] = set()

    by_track = {item["track"]: item for item in reports}
    missing = [track for track in TRACK_ORDER if track not in by_track]
    if missing:
        raise ValueError(f"missing tracks: {', '.join(missing)}")

    for track in TRACK_ORDER:
        report = by_track[track]
        summaries.append(f"{track}: {report['summary'].strip()}")
        unverified.extend(report.get("unverified") or [])
        positives.extend(report.get("positives") or [])
        for finding in report.get("findings") or []:
            key = (
                finding["track"],
                finding["path"],
                finding.get("line"),
                finding["problem"].strip(),
            )
            if key in seen:
                continue
            seen.add(key)
            findings.append(finding)

    blocking_ids = [
        item["id"] for item in findings if item["severity"] in {"blocker", "high"}
    ]
    rejected = bool(blocking_ids)
    return {
        "track": "consolidated",
        "summary": " ".join(summaries),
        "verdict": "REJECTED" if rejected else "APPROVED",
        "findings": findings,
        "unverified": unverified,
        "positives": positives,
        "execute_handoff": {
            "action": "repair" if rejected else "complete",
            "blocking_ids": blocking_ids,
            "instructions": (
                "Corrija todos os findings blocker e high listados. "
                "Não altere o escopo. Preserve testes válidos. "
                "Depois devolva o controle ao harness para nova review."
                if rejected
                else "Nenhum blocker/high. Encerrar a tarefa."
            ),
        },
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--architecture", type=Path, required=True)
    parser.add_argument("--security", type=Path, required=True)
    parser.add_argument("--smells", type=Path, required=True)
    parser.add_argument("--tests", type=Path, required=True)
    parser.add_argument("--out", type=Path, required=True)
    args = parser.parse_args()

    reports = [
        load_report(args.architecture),
        load_report(args.security),
        load_report(args.smells),
        load_report(args.tests),
    ]
    consolidated = merge(reports)
    problems = errors_for(consolidated)
    if problems:
        print("GATE FAIL")
        for problem in problems:
            print(f"- {problem}")
        return 1
    args.out.write_text(
        json.dumps(consolidated, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    print(f"GATE PASS verdict={consolidated['verdict']} action={consolidated['execute_handoff']['action']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
