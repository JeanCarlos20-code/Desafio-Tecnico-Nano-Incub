from __future__ import annotations

from pathlib import Path
import json
import shutil

import pytest

from project_harness.envfile import load_mcp_env, parse_env_file
from project_harness.errors import HarnessError
from project_harness.sync.service import Synchronizer


MCP_KEYS = (
    "CONTEXT7_API_KEY",
    "GITHUB_MCP_TOKEN",
    "ARCHITECTURE_DATABASE_URL",
    "SNYK_TOKEN",
)


@pytest.fixture
def isolated_mcp_env(monkeypatch: pytest.MonkeyPatch) -> None:
    for key in MCP_KEYS:
        monkeypatch.delenv(key, raising=False)


def test_parse_env_file_supports_comments_export_and_quotes(tmp_path: Path) -> None:
    path = tmp_path / ".env.mcp"
    path.write_text(
        "\n".join(
            [
                "# comment",
                "CONTEXT7_API_KEY=ctx-plain",
                'GITHUB_MCP_TOKEN="gh-quoted"',
                "export SNYK_TOKEN='snyk-quoted'",
                "",
            ]
        ),
        encoding="utf-8",
    )
    assert parse_env_file(path) == {
        "CONTEXT7_API_KEY": "ctx-plain",
        "GITHUB_MCP_TOKEN": "gh-quoted",
        "SNYK_TOKEN": "snyk-quoted",
    }


def test_parse_env_file_rejects_invalid_line(tmp_path: Path) -> None:
    path = tmp_path / ".env.mcp"
    path.write_text("NOT A KEY\n", encoding="utf-8")
    with pytest.raises(HarnessError, match="linha inválida"):
        parse_env_file(path)


def test_load_mcp_env_returns_empty_when_missing(tmp_path: Path) -> None:
    assert load_mcp_env(tmp_path) == {}


def test_sync_reads_env_mcp_for_optional_server_and_inlines_secrets(
    tmp_path: Path,
    harness_source: Path,
    isolated_mcp_env: None,
) -> None:
    root = tmp_path / "repo"
    root.mkdir()
    shutil.copytree(harness_source, root / "harness")
    (root / ".env.mcp").write_text(
        "\n".join(
            [
                "CONTEXT7_API_KEY=ctx-from-file",
                "GITHUB_MCP_TOKEN=gh-from-file",
                "SNYK_TOKEN=snyk-from-file",
            ]
        )
        + "\n",
        encoding="utf-8",
    )
    Synchronizer(root).sync(("cursor",))
    data = json.loads((root / ".cursor/mcp.json").read_text(encoding="utf-8"))
    servers = data["mcpServers"]
    assert servers["context7"]["headers"]["CONTEXT7_API_KEY"] == "ctx-from-file"
    assert servers["github"]["headers"]["Authorization"] == "Bearer gh-from-file"
    assert servers["snyk"]["env"]["SNYK_TOKEN"] == "snyk-from-file"


def test_sync_keeps_placeholders_when_only_os_env_is_set(
    tmp_path: Path,
    harness_source: Path,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    for key in MCP_KEYS:
        monkeypatch.delenv(key, raising=False)
    monkeypatch.setenv("CONTEXT7_API_KEY", "ctx-from-os")
    root = tmp_path / "repo"
    root.mkdir()
    shutil.copytree(harness_source, root / "harness")
    Synchronizer(root).sync(("cursor",))
    data = json.loads((root / ".cursor/mcp.json").read_text(encoding="utf-8"))
    assert data["mcpServers"]["context7"]["headers"]["CONTEXT7_API_KEY"] == "${env:CONTEXT7_API_KEY}"
    assert "snyk" not in data["mcpServers"]
