from __future__ import annotations

from pathlib import Path
import json

from project_harness.review_service import ReviewService


def _write_report(path: Path, *, verdict: str, findings: list[dict[str, object]] | None = None) -> None:
    path.write_text(
        json.dumps(
            {
                "track": "consolidated",
                "summary": "Review concluída.",
                "verdict": verdict,
                "findings": findings or [],
                "unverified": [],
                "positives": ["Boa cobertura."],
                "execute_handoff": {"action": "complete", "blocking_ids": [], "instructions": "ok"},
            }
        ),
        encoding="utf-8",
    )


def test_review_renderer_uses_requested_sections(tmp_path: Path) -> None:
    report = tmp_path / "consolidated.json"
    _write_report(
        report,
        verdict="APPROVED",
        findings=[
            {
                "id": "SMELL-001",
                "track": "smells",
                "severity": "medium",
                "path": "app/service.py",
                "line": 10,
                "problem": "Código morto.",
                "impact": "Manutenção.",
                "fix": "Remover.",
                "evidence": [{"type": "internal", "ref": "app/service.py:10"}],
            }
        ],
    )
    service = ReviewService()
    result = service.load(report)
    text = service.render_markdown(result, True, "- ✅ `pytest` — exit=0 (required)")
    assert "AI Code Review (S)" in text
    assert "❌ Blockers" in text
    assert "⚠️ High" in text
    assert "📝 Medium" in text
    assert "SMELL-001" in text
    assert "✅ APPROVED" in text
    assert "**Deterministic checks**" in text
    assert "`pytest`" in text
    assert "**Harness gate**" in text
    assert "review APPROVED and required checks are green" in text


def test_review_markdown_keeps_approved_when_checks_are_red(tmp_path: Path) -> None:
    report = tmp_path / "consolidated.json"
    _write_report(report, verdict="APPROVED")
    service = ReviewService()
    result = service.load(report)
    text = service.render_markdown(
        result,
        False,
        "- ❌ `npm run build` — exit=127 (required)",
    )
    assert "**Verdict**" in text
    verdict_block = text.split("**Verdict**", 1)[1].split("**Harness gate**", 1)[0]
    assert "✅ APPROVED" in verdict_block
    assert "❌ REJECTED" not in verdict_block
    assert "Required deterministic checks are not green" not in text.split("**Deterministic checks**", 1)[0]
    assert "`npm run build`" in text
    assert "review found no blocker/high; required checks are red" in text
    assert "None found." in text


def test_review_markdown_keeps_rejected_when_checks_are_green(tmp_path: Path) -> None:
    report = tmp_path / "consolidated.json"
    _write_report(
        report,
        verdict="REJECTED",
        findings=[
            {
                "id": "ARCH-001",
                "track": "architecture",
                "severity": "high",
                "path": "app/x.py",
                "line": 1,
                "problem": "Camada invertida.",
                "impact": "Acoplamento.",
                "fix": "Mover.",
                "evidence": [{"type": "internal", "ref": "app/x.py:1"}],
            }
        ],
    )
    service = ReviewService()
    result = service.load(report)
    text = service.render_markdown(result, True, "- ✅ `pytest` — exit=0 (required)")
    verdict_block = text.split("**Verdict**", 1)[1].split("**Harness gate**", 1)[0]
    assert "❌ REJECTED" in verdict_block
    assert "review REJECTED (blocker/high)" in text
