from __future__ import annotations

from project_harness.cli import _parser


def test_cli_prog_is_harness() -> None:
    parser = _parser()
    assert parser.prog == "harness"
    help_text = parser.format_help()
    assert help_text.startswith("usage: harness")
