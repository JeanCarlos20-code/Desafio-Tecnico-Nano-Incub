from __future__ import annotations

from project_harness.review_scope import (
    paths_from_consolidated,
    route_after_checks,
    route_after_review,
    split_dirty_and_carried,
    union_presented_paths,
)


def test_failed_required_checks_route_to_repair_not_review() -> None:
    assert (
        route_after_checks(required_passed=False, check_fix_round=0, max_repair_rounds=3)
        == "repair"
    )
    assert (
        route_after_checks(required_passed=False, check_fix_round=2, max_repair_rounds=3)
        == "repair"
    )


def test_green_required_checks_route_to_review() -> None:
    assert (
        route_after_checks(required_passed=True, check_fix_round=3, max_repair_rounds=3)
        == "review"
    )


def test_optional_failure_still_routes_to_review_when_required_passed() -> None:
    assert (
        route_after_checks(required_passed=True, check_fix_round=0, max_repair_rounds=3)
        == "review"
    )


def test_exhausted_check_fix_budget_notifies_and_does_not_start_review() -> None:
    assert (
        route_after_checks(required_passed=False, check_fix_round=3, max_repair_rounds=3)
        == "notify"
    )
    assert (
        route_after_checks(required_passed=False, check_fix_round=4, max_repair_rounds=3)
        == "notify"
    )


def test_prior_check_fix_counts_do_not_exhaust_review_repair_budget() -> None:
    assert (
        route_after_review(
            approved=False,
            required_passed=True,
            review_round=1,
            max_repair_rounds=3,
        )
        == "repair"
    )


def test_review_budget_uses_review_round_not_check_fix_count() -> None:
    assert (
        route_after_review(
            approved=False,
            required_passed=True,
            review_round=3,
            max_repair_rounds=3,
        )
        == "escalate"
    )


def test_approved_green_review_routes_to_approved() -> None:
    assert (
        route_after_review(
            approved=True,
            required_passed=True,
            review_round=1,
            max_repair_rounds=3,
        )
        == "approved"
    )


def test_review_scope_union_keeps_first_review_files_that_are_no_longer_dirty() -> None:
    presented = union_presented_paths(("src/old.py", "src/new.py"), ("src/reviewed.py",))
    dirty, carried = split_dirty_and_carried(("src/new.py",), presented)
    assert dirty == ("src/new.py",)
    assert "src/old.py" in carried
    assert "src/reviewed.py" in carried
    assert "src/new.py" not in carried


def test_paths_from_consolidated_include_findings_positives_and_unverified() -> None:
    paths = paths_from_consolidated(
        {
            "findings": [{"path": "harness/src/project_harness/graph_runtime.py"}],
            "positives": ["harness/src/project_harness/packets.py:12 — packet lists dirty paths"],
            "unverified": ["docs/architecture.md:1 not loaded"],
        }
    )
    assert "harness/src/project_harness/graph_runtime.py" in paths
    assert "harness/src/project_harness/packets.py" in paths
    assert "docs/architecture.md" in paths
