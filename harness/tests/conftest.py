from __future__ import annotations

from pathlib import Path
import shutil
import subprocess

import pytest


def run(cwd: Path, *args: str) -> str:
    result = subprocess.run(args, cwd=cwd, text=True, capture_output=True, check=False)
    if result.returncode != 0:
        raise RuntimeError(result.stderr or result.stdout)
    return result.stdout.strip()


@pytest.fixture
def git_repo(tmp_path: Path) -> Path:
    root = tmp_path / "repo"
    root.mkdir()
    run(root, "git", "init", "-b", "feature/challenge")
    run(root, "git", "config", "user.email", "test@example.com")
    run(root, "git", "config", "user.name", "Harness Test")
    run(root, "git", "config", "commit.gpgsign", "false")
    (root / "README.md").write_text("initial\n", encoding="utf-8")
    run(root, "git", "add", "README.md")
    run(root, "git", "commit", "-m", "chore: initial")
    return root


@pytest.fixture
def harness_source() -> Path:
    return Path(__file__).resolve().parents[1]
