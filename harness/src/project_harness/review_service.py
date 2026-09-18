from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

from .errors import HarnessError
from .types import JSONObject, JSONValue, ReviewVerdict
from .utils import as_list, as_object, as_str, read_json_object


@dataclass(frozen=True)
class Finding:
    id: str
    track: str
    severity: str
    path: str
    line: int | None
    problem: str
    impact: str
    fix: str


@dataclass(frozen=True)
class ReviewResult:
    verdict: ReviewVerdict
    summary: str
    findings: tuple[Finding, ...]
    positives: tuple[str, ...]
    unverified: tuple[str, ...]
    blocking_ids: tuple[str, ...]


class ReviewService:
    def load(self, path: Path) -> ReviewResult:
        data = read_json_object(path)
        track = as_str(data.get("track"))
        if track != "consolidated":
            raise HarnessError("Review final precisa ter track=consolidated.")
        verdict_raw = as_str(data.get("verdict"))
        if verdict_raw not in {ReviewVerdict.APPROVED, ReviewVerdict.REJECTED}:
            raise HarnessError("Review consolidada sem verdict válido.")
        findings: list[Finding] = []
        for raw in as_list(data.get("findings")):
            item = as_object(raw)
            line_raw = item.get("line")
            line = line_raw if isinstance(line_raw, int) and not isinstance(line_raw, bool) else None
            severity = as_str(item.get("severity"))
            if severity not in {"blocker", "high", "medium"}:
                raise HarnessError("Review contém severity inválida.")
            findings.append(
                Finding(
                    id=as_str(item.get("id")),
                    track=as_str(item.get("track")),
                    severity=severity,
                    path=as_str(item.get("path")),
                    line=line,
                    problem=as_str(item.get("problem")),
                    impact=as_str(item.get("impact")),
                    fix=as_str(item.get("fix")),
                )
            )
        handoff = as_object(data.get("execute_handoff"))
        blocking = tuple(
            str(item) for item in as_list(handoff.get("blocking_ids")) if isinstance(item, str)
        )
        positives = tuple(str(item) for item in as_list(data.get("positives")) if isinstance(item, str))
        unverified = tuple(str(item) for item in as_list(data.get("unverified")) if isinstance(item, str))
        return ReviewResult(
            verdict=ReviewVerdict(verdict_raw),
            summary=as_str(data.get("summary")),
            findings=tuple(findings),
            positives=positives,
            unverified=unverified,
            blocking_ids=blocking,
        )

    def render_markdown(
        self,
        result: ReviewResult,
        checks_green: bool,
    ) -> str:
        blockers = [item for item in result.findings if item.severity == "blocker"]
        highs = [item for item in result.findings if item.severity == "high"]
        mediums = [item for item in result.findings if item.severity == "medium"]
        review_approved = result.verdict is ReviewVerdict.APPROVED
        gate_open = review_approved and checks_green

        def section(items: list[Finding], empty: str) -> str:
            if not items:
                return empty
            lines: list[str] = []
            for item in items:
                location = f"`{item.path}`" + (f":{item.line}" if item.line else "")
                lines.append(
                    f"- **{item.id}** — {location}: {item.problem} "
                    f"Impact: {item.impact} Recommended fix: {item.fix}"
                )
            return "\n".join(lines)

        positives = "\n".join(f"- {item}" for item in result.positives) or "None noted."
        summary = result.summary.strip() or "Review complete."
        review_verdict = "✅ APPROVED" if review_approved else "❌ REJECTED"
        if gate_open:
            gate_line = "✅ open — review APPROVED and required checks are green."
        elif review_approved:
            gate_line = "❌ blocked — review found no blocker/high; required checks are red."
        elif checks_green:
            gate_line = "❌ blocked — review REJECTED (blocker/high)."
        else:
            gate_line = "❌ blocked — review REJECTED and required checks are red."
        return (
            "🤖 **AI Code Review (S)**\n\n"
            "**Summary**\n\n"
            f"{summary}\n\n"
            "**❌ Blockers**\n\n"
            f"{section(blockers, 'None found.')}\n\n"
            "**⚠️ High**\n\n"
            f"{section(highs, 'None found.')}\n\n"
            "**📝 Medium**\n\n"
            f"{section(mediums, 'None found.')}\n\n"
            "**✅ Positive Findings**\n\n"
            f"{positives}\n\n"
            "**Verdict**\n\n"
            f"{review_verdict}\n\n"
            "**Harness gate**\n\n"
            f"{gate_line}\n"
        )

    def render_checks_markdown(self, check_summary: str, *, round_number: int) -> str:
        body = check_summary.strip() or "Nenhum comando automático configurado para esta tarefa."
        return f"# Deterministic checks — round {round_number:02d}\n\n{body}\n"
