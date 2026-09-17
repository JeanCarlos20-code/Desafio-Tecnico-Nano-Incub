from __future__ import annotations

from pathlib import Path
import json
import os
import shutil

import yaml

from ..envfile import load_mcp_env
from ..errors import HarnessError
from ..types import AgentSource, JSONObject, JSONValue, Target
from ..utils import as_bool, as_list, as_object, as_str, parse_frontmatter, read_json_object, read_yaml, to_json_value, write_json

HEADER = """<!--\nGENERATED FILE.\nSource: {source}\nRun: harness sync\nDo not edit directly.\n-->\n\n"""
READ_ONLY_ROLES = {"harness"}
SUBAGENT_ROLES = {"plan", "execute", "review"}


class AgentGenerator:
    def __init__(self, root: Path) -> None:
        self.root = root
        self.source_root = root / "harness" / "agents"

    def sources(self) -> tuple[AgentSource, ...]:
        result: list[AgentSource] = []
        for path in sorted(self.source_root.glob("*.md")):
            meta, body = parse_frontmatter(path.read_text(encoding="utf-8"), path)
            name = as_str(meta.get("name")).strip()
            description = as_str(meta.get("description")).strip()
            role = as_str(meta.get("role")).strip()
            if not name or not description or role not in {"harness", "plan", "execute", "review"}:
                raise HarnessError(f"Agente canônico inválido: {path}")
            result.append(AgentSource(name, description, role, body, path))
        return tuple(result)

    def generate(self, target: Target) -> list[Path]:
        generated: list[Path] = []
        for source in self.sources():
            path = self._output(target, source)
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(self._render(target, source), encoding="utf-8")
            generated.append(path)
        return generated

    def _output(self, target: Target, source: AgentSource) -> Path:
        directory = self.root / target.agent_dir
        if target.agent_mode == "skill":
            return directory / source.name / "SKILL.md"
        if target.agent_mode == "antigravity":
            return directory / source.name / "agent.md"
        suffix = ".agent.md" if target.agent_mode == "copilot" else ".md"
        return directory / f"{source.name}{suffix}"

    def _render(self, target: Target, source: AgentSource) -> str:
        header = HEADER.format(source=source.path.relative_to(self.root))
        read_only = source.role in READ_ONLY_ROLES
        if target.agent_mode == "opencode":
            metadata: dict[str, JSONValue] = {
                "description": source.description,
                "mode": "subagent" if source.role in SUBAGENT_ROLES else "primary",
                "permission": {
                    "edit": "deny" if read_only else "allow",
                    "bash": {
                        "*": "ask",
                        "harness *": "allow",
                        "git status*": "allow",
                        "git diff*": "allow",
                    },
                },
            }
            return header + self._md(metadata, source.body)
        if target.agent_mode == "claude":
            metadata = {
                "name": source.name,
                "description": source.description,
                "tools": "Read, Grep, Glob, Bash" if read_only else "Read, Grep, Glob, Bash, Write, Edit",
            }
            if read_only:
                metadata["disallowedTools"] = "Write, Edit"
            return header + self._md(metadata, source.body)
        if target.agent_mode in {"cursor", "copilot", "antigravity"}:
            metadata = {"name": source.name, "description": source.description}
            if target.agent_mode == "copilot" and read_only:
                metadata["tools"] = ["read", "search"]
            return header + self._md(metadata, source.body)
        if target.agent_mode in {"windsurf-workflow", "jetbrains-rule"}:
            return header + f"# {source.name}\n\n{source.description}\n\n{source.body}\n"
        if target.agent_mode == "skill":
            return header + self._md(
                {
                    "name": source.name,
                    "description": source.description,
                    "disable-model-invocation": True,
                },
                source.body,
            )
        raise HarnessError(f"agent_mode não suportado: {target.agent_mode}")

    @staticmethod
    def _md(metadata: dict[str, JSONValue], body: str) -> str:
        frontmatter = yaml.safe_dump(metadata, sort_keys=False, allow_unicode=True).strip()
        return f"---\n{frontmatter}\n---\n\n{body.rstrip()}\n"


class SkillGenerator:
    def __init__(self, root: Path) -> None:
        self.root = root
        self.source_root = root / "harness" / "skills"

    def skills(self) -> tuple[Path, ...]:
        return tuple(
            item.parent
            for item in sorted(self.source_root.glob("*/SKILL.md"))
            if item.is_file()
        )

    def generate(self, target: Target) -> list[Path]:
        generated: list[Path] = []
        for skill_dir in self.skills():
            for relative in target.skill_dirs:
                destination = self.root / relative / skill_dir.name
                if destination.resolve() == skill_dir.resolve():
                    continue
                if destination.exists():
                    shutil.rmtree(destination)
                destination.parent.mkdir(parents=True, exist_ok=True)
                shutil.copytree(skill_dir, destination)
                generated.extend(path for path in destination.rglob("*") if path.is_file())
        return generated


class InstructionGenerator:
    START = "<!-- project-harness:start -->"
    END = "<!-- project-harness:end -->"

    def __init__(self, root: Path) -> None:
        self.root = root
        self.canonical = root / "harness" / "instructions.md"

    def generate(self, target: Target) -> list[Path]:
        mode = target.instruction_mode
        pointer = (
            "Toda tarefa de código deve passar pelo Project Harness. "
            "Use o agente `harness` como orquestrador e não pule os gates humanos.\n"
        )
        if mode == "agents-md":
            path = self.root / "AGENTS.md"
            self._managed_block(path, pointer)
            return [path]
        mapping = {
            "claude-import": ("CLAUDE.md", "@AGENTS.md\n"),
            "gemini-import": ("GEMINI.md", "@AGENTS.md\n"),
            "cursor-rule": (".cursor/rules/harness.mdc", "---\ndescription: Uso obrigatório do Project Harness\nalwaysApply: true\n---\n\n" + pointer),
            "copilot": (".github/copilot-instructions.md", pointer),
            "windsurf-rule": (".windsurf/rules/harness.md", "# Harness obrigatório\n\n" + pointer),
            "jetbrains-rule": (".aiassistant/rules/harness.md", "# Harness obrigatório\n\n" + pointer),
        }
        if mode not in mapping:
            return []
        relative, content = mapping[mode]
        path = self.root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        if relative in {"CLAUDE.md", "GEMINI.md"}:
            self._managed_block(path, content)
        else:
            path.write_text(HEADER.format(source="harness/instructions.md") + content, encoding="utf-8")
        return [path]

    def _managed_block(self, path: Path, content: str) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        existing = path.read_text(encoding="utf-8") if path.exists() else ""
        block = f"{self.START}\n{content.rstrip()}\n{self.END}"
        if self.START in existing and self.END in existing:
            before = existing.split(self.START, 1)[0]
            after = existing.split(self.END, 1)[1]
            prefix = before.rstrip()
            suffix = after.lstrip("\n").rstrip()
            parts = [part for part in (prefix, block, suffix) if part]
            result = "\n\n".join(parts) + "\n"
        else:
            result = existing.rstrip() + ("\n\n" if existing.strip() else "") + block + "\n"
        path.write_text(result, encoding="utf-8")


class MCPGenerator:
    def __init__(self, root: Path) -> None:
        self.root = root
        self.config = root / "harness" / "mcp" / "servers.yaml"
        self.file_env = load_mcp_env(root)

    def generate(self, target: Target) -> tuple[list[Path], tuple[str, ...]]:
        if not target.mcp:
            return [], ()
        servers = self._servers(target)
        mode = as_str(target.mcp.get("mode"))
        names = tuple(sorted(servers))
        if mode == "project-json":
            path = self.root / as_str(target.mcp.get("path"))
            root_key = as_str(target.mcp.get("root_key"))
            current: JSONObject = {}
            if path.is_file():
                current = read_json_object(path)
            existing = as_object(current.get(root_key))
            existing.update(servers)
            current[root_key] = existing
            write_json(path, current)
            return [path], names
        if mode in {"export-json", "user-json"}:
            path = self.root / as_str(target.mcp.get("export_path"))
            write_json(path, {as_str(target.mcp.get("root_key")): servers})
            return [path], names
        if mode == "export-toml":
            path = self.root / as_str(target.mcp.get("export_path"))
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(self._toml(servers), encoding="utf-8")
            return [path], names
        raise HarnessError(f"Modo MCP não suportado: {mode}")

    def _servers(self, target: Target) -> dict[str, JSONObject]:
        data = read_yaml(self.config)
        raw = as_object(data.get("servers"))
        result: dict[str, JSONObject] = {}
        for name, value in raw.items():
            item = as_object(value)
            if not as_bool(item.get("enabled"), True):
                continue
            if as_bool(item.get("optional"), False):
                env_name = as_str(item.get("optional_env"))
                if env_name and not self._has_env(env_name):
                    continue
            local = as_str(item.get("prefer_local"))
            if local and shutil.which(local):
                item["command"] = local
                local_args = as_list(item.get("local_args"))
                if local_args:
                    item["args"] = local_args
            result[name] = self._render(item, target)
        return result

    def _render(self, server: JSONObject, target: Target) -> JSONObject:
        transport = as_str(server.get("transport"), "stdio")
        style = as_str(target.mcp.get("style"), "standard")
        if style == "opencode":
            if transport == "stdio":
                command = [as_str(server.get("command"))]
                command.extend(str(item) for item in as_list(server.get("args")))
                result: JSONObject = {"type": "local", "command": command, "enabled": True}
                env = as_object(server.get("environment"))
                if env:
                    result["environment"] = self._values(env, target)
            else:
                result = {
                    "type": "remote",
                    "url": self._values(server.get("url"), target),
                    "enabled": True,
                    "oauth": False,
                }
                headers = as_object(server.get("headers"))
                if headers:
                    result["headers"] = self._values(headers, target)
        elif style == "antigravity":
            if transport == "stdio":
                result = {
                    "command": as_str(server.get("command")),
                    "args": [str(item) for item in as_list(server.get("args"))],
                }
                env = as_object(server.get("environment"))
                if env:
                    result["env"] = self._values(env, target)
            else:
                result = {
                    "serverUrl": self._values(server.get("url"), target),
                }
                headers = as_object(server.get("headers"))
                if headers:
                    result["headers"] = self._values(headers, target)
        elif transport == "stdio":
            result = {
                "command": as_str(server.get("command")),
                "args": [str(item) for item in as_list(server.get("args"))],
            }
            if style in {"standard", "cursor"}:
                result["type"] = "stdio"
            env = as_object(server.get("environment"))
            if env:
                result["env"] = self._values(env, target)
        else:
            result = {
                "type": "http",
                "url": self._values(server.get("url"), target),
            }
            headers = as_object(server.get("headers"))
            if headers:
                result["headers"] = self._values(headers, target)
        if as_bool(server.get("read_only"), False):
            result["readOnly"] = True
        return result

    def _has_env(self, name: str) -> bool:
        return bool(self.file_env.get(name) or os.getenv(name))

    def _resolve_env(self, env_name: str, target: Target, prefix: str, suffix: str) -> str:
        file_secret = self.file_env.get(env_name) or ""
        os_secret = os.getenv(env_name) or ""
        syntax = as_str(target.mcp.get("env_syntax"))
        token = ""
        if syntax:
            token = syntax % env_name if "%s" in syntax else syntax.replace("%s", env_name)
        if file_secret:
            return f"{prefix}{file_secret}{suffix}"
        if os_secret:
            if syntax:
                return f"{prefix}{token}{suffix}"
            return f"{prefix}{os_secret}{suffix}"
        if syntax:
            return f"{prefix}{token}{suffix}"
        raise HarnessError(f"Variável MCP ausente: {env_name}")

    def _values(self, value: JSONValue, target: Target) -> JSONValue:
        if isinstance(value, dict):
            if "env" in value and set(value).issubset({"env", "prefix", "suffix"}):
                return self._resolve_env(
                    as_str(value.get("env")),
                    target,
                    as_str(value.get("prefix")),
                    as_str(value.get("suffix")),
                )
            return {key: self._values(item, target) for key, item in value.items()}
        if isinstance(value, list):
            return [self._values(item, target) for item in value]
        return value

    @staticmethod
    def _toml(servers: dict[str, JSONObject]) -> str:
        lines = ["# GENERATED FILE", "# Project Harness MCP export", ""]
        for name, config in servers.items():
            lines.append(f'[mcp_servers."{name}"]')
            for key in ("command", "url"):
                value = config.get(key)
                if isinstance(value, str):
                    lines.append(f"{key} = {json.dumps(value)}")
            args = config.get("args")
            if isinstance(args, list):
                lines.append("args = " + json.dumps(args, ensure_ascii=False))
            env = config.get("env")
            if isinstance(env, dict):
                lines.append("env = " + json.dumps(env, ensure_ascii=False))
            lines.append("")
        return "\n".join(lines)
