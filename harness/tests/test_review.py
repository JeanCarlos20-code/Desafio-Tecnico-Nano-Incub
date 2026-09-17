from __future__ import annotations

from pathlib import Path
import json

from project_harness.review_service import ReviewService


def test_review_renderer_uses_requested_sections(tmp_path: Path) -> None:
    report = tmp_path / "consolidated.json"
    report.write_text(
        json.dumps(
            {
                "track": "consolidated",
                "summary": "Review concluída.",
                "verdict": "APPROVED",
                "findings": [
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
                "unverified": [],
                "positives": ["Boa cobertura."],
                "execute_handoff": {"action": "complete", "blocking_ids": [], "instructions": "ok"},
            }
        ),
        encoding="utf-8",
    )
    service = ReviewService()
    result = service.load(report)
    text = service.render_markdown(result, True)
    assert "AI Code Review (S)" in text
    assert "❌ Blockers" in text
    assert "⚠️ High" in text
    assert "📝 Medium" in text
    assert "SMELL-001" in text
    assert "✅ APPROVED" in text
