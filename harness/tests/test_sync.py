from __future__ import annotations

from pathlib import Path
import json
import shutil

from project_harness.sync.service import Synchronizer


def test_sync_generates_agents_skills_and_mcp(tmp_path: Path, harness_source: Path) -> None:
    root = tmp_path / "repo"
    root.mkdir()
    shutil.copytree(harness_source, root / "harness")
    manifest = Synchronizer(root).sync(("claude", "cursor", "opencode", "generic"))
    assert (root / ".claude/agents/harness-plan.md").is_file()
    assert (root / ".cursor/agents/harness-execute.md").is_file()
    assert (root / ".opencode/agents/harness-review.md").is_file()
    assert (root / ".agents/skills/tlc-spec-driven/SKILL.md").is_file()
    assert (root / ".mcp.json").is_file()
    assert (root / ".cursor/mcp.json").is_file()
    assert "source_fingerprint" in manifest
    cursor_agent = (root / ".cursor/agents/harness.md").read_text(encoding="utf-8")
    assert "harness task start" in cursor_agent
    assert "./harness/run" not in cursor_agent
    opencode_agent = (root / ".opencode/agents/harness-plan.md").read_text(encoding="utf-8")
    assert "harness *" in opencode_agent
    assert "./harness/run *" not in opencode_agent


def test_sync_all_registered_targets(tmp_path: Path, harness_source: Path) -> None:
    root = tmp_path / "repo-all"
    root.mkdir()
    shutil.copytree(harness_source, root / "harness")
    manifest = Synchronizer(root).sync(None)
    targets = manifest.get("targets")
    assert isinstance(targets, list)
    assert set(targets) == {
        "antigravity", "claude", "codex", "copilot", "cursor", "gemini", "generic",
        "jetbrains", "opencode", "pi", "windsurf", "zed",
    }
    assert (root / ".claude/agents/harness-plan.md").is_file()
    assert (root / ".cursor/agents/harness-review.md").is_file()
    assert (root / ".github/agents/harness-execute.agent.md").is_file()
    assert (root / ".gemini/skills/harness-plan/SKILL.md").is_file()
    assert (root / ".pi/skills/harness-review/SKILL.md").is_file()
    assert (root / ".windsurf/workflows/harness-plan.md").is_file()
    assert (root / ".agents/agents/harness/agent.md").is_file()


def test_antigravity_sync_is_idempotent_and_preserves_manual_mcp(
    tmp_path: Path, harness_source: Path
) -> None:
    root = tmp_path / "repo-ag"
    root.mkdir()
    shutil.copytree(harness_source, root / "harness")
    mcp_path = root / ".agents" / "mcp_config.json"
    mcp_path.parent.mkdir(parents=True, exist_ok=True)
    mcp_path.write_text(
        json.dumps(
            {
                "mcpServers": {
                    "manual": {"command": "echo", "args": ["ok"]},
                }
            }
        ),
        encoding="utf-8",
    )
    first = Synchronizer(root).sync(("antigravity",))
    second = Synchronizer(root).sync(("antigravity",))
    assert first["generated_hashes"] == second["generated_hashes"]
    agent = root / ".agents/agents/harness-plan/agent.md"
    assert agent.is_file()
    body = agent.read_text(encoding="utf-8")
    assert "harness task action" in body
    assert "./harness/run" not in body
    assert (root / ".agents/skills/tlc-spec-driven/SKILL.md").is_file()
    data = json.loads(mcp_path.read_text(encoding="utf-8"))
    servers = data["mcpServers"]
    assert servers["manual"]["command"] == "echo"
    assert "serverUrl" in servers["context7"]
    assert servers["context7"]["serverUrl"] == "https://mcp.context7.com/mcp"
    assert "url" not in servers["context7"]
