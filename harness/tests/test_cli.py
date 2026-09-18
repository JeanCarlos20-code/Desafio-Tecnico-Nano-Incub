from __future__ import annotations

from project_harness.cli import _parser, _print_payload


def test_cli_prog_is_harness() -> None:
    parser = _parser()
    assert parser.prog == "harness"
    help_text = parser.format_help()
    assert help_text.startswith("usage: harness")


def test_cli_prints_test_barrier_not_commit_on_plan_gate(capsys) -> None:
    _print_payload(
        {
            "task_id": "0004",
            "action": {
                "kind": "human",
                "gate": "plan",
                "message": "Revise a solução e a barreira de teste. Este gate não autoriza commit.",
                "summary": "## Barreira de teste\n\n| Gate | Command |\n| unit | `npm test` |",
            },
        },
        json_output=False,
    )
    out = capsys.readouterr().out
    assert "ACTION: human:plan" in out
    assert "Barreira de teste" in out
    assert "npm test" in out
    assert "feat(" not in out


def test_cli_prints_check_summary_on_commit_gate(capsys) -> None:
    _print_payload(
        {
            "task_id": "0003",
            "action": {
                "kind": "human",
                "gate": "commit",
                "message": "Review e checks passaram.",
                "check_summary": "- ✅ `npm run build` — exit=0 (required)",
            },
        },
        json_output=False,
    )
    out = capsys.readouterr().out
    assert "Barreira de teste:" in out
    assert "npm run build" in out
