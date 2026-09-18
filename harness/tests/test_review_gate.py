from __future__ import annotations

import importlib.util
from pathlib import Path


def _load_review_gate():
    path = Path(__file__).resolve().parents[1] / "skills" / "harness-review" / "scripts" / "review_gate.py"
    spec = importlib.util.spec_from_file_location("review_gate", path)
    assert spec is not None and spec.loader is not None
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def _clean_track() -> dict:
    return {
        "track": "architecture",
        "summary": "Camadas ok.",
        "verdict": None,
        "findings": [],
        "unverified": [],
        "positives": [],
    }


def test_empty_track_without_located_positive_fails_gate() -> None:
    module = _load_review_gate()
    problems = module.errors_for(_clean_track())
    assert any("did not judge" in item for item in problems)


def test_empty_track_with_path_line_positive_passes_gate() -> None:
    module = _load_review_gate()
    report = _clean_track()
    report["positives"] = [
        "Inertia page stays in Pages at resources/js/Pages/User/Create.jsx:46"
    ]
    assert module.errors_for(report) == []


def test_empty_track_with_unlocated_positive_fails_gate() -> None:
    module = _load_review_gate()
    report = _clean_track()
    report["positives"] = ["Looks good."]
    problems = module.errors_for(report)
    assert any("did not judge" in item for item in problems)


def test_track_with_finding_does_not_require_positives() -> None:
    module = _load_review_gate()
    report = _clean_track()
    report["findings"] = [
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
    ]
    assert module.errors_for(report) == []
