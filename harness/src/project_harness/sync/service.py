from __future__ import annotations

from hashlib import sha256
from pathlib import Path

from ..errors import HarnessError
from ..types import JSONValue
from ..utils import sha256_file, write_json
from .generators import AgentGenerator, InstructionGenerator, MCPGenerator, SkillGenerator
from .registry import TargetRegistry

MANIFEST = ".harness-manifest.json"


class Synchronizer:
    def __init__(self, root: Path) -> None:
        self.root = root
        self.registry = TargetRegistry(root)

    def sync(self, target_ids: tuple[str, ...] | None = None) -> dict[str, JSONValue]:
        generated: list[Path] = []
        mcp_names: dict[str, JSONValue] = {}
        targets = self.registry.select(target_ids)
        for target in targets:
            generated.extend(AgentGenerator(self.root).generate(target))
            generated.extend(SkillGenerator(self.root).generate(target))
            generated.extend(InstructionGenerator(self.root).generate(target))
            mcp_files, names = MCPGenerator(self.root).generate(target)
            generated.extend(mcp_files)
            mcp_names[target.id] = list(names)
        files = sorted({path.resolve() for path in generated if path.is_file()})
        manifest: dict[str, JSONValue] = {
            "version": 1,
            "targets": [item.id for item in targets],
            "source_fingerprint": self.source_fingerprint(),
            "generated_hashes": {
                str(path.relative_to(self.root)): sha256_file(path)
                for path in files
            },
            "mcp_servers": mcp_names,
        }
        write_json(self.root / MANIFEST, manifest)
        return manifest

    def check(self) -> tuple[bool, tuple[str, ...]]:
        path = self.root / MANIFEST
        if not path.is_file():
            return False, ("Manifesto ausente. Execute harness sync.",)
        import json
        raw: object = json.loads(path.read_text(encoding="utf-8"))
        if not isinstance(raw, dict):
            return False, ("Manifesto inválido.",)
        errors: list[str] = []
        if raw.get("source_fingerprint") != self.source_fingerprint():
            errors.append("As fontes canônicas mudaram após o último sync.")
        hashes = raw.get("generated_hashes")
        if isinstance(hashes, dict):
            for relative, expected in hashes.items():
                if not isinstance(relative, str) or not isinstance(expected, str):
                    continue
                file = self.root / relative
                if not file.is_file():
                    errors.append(f"Arquivo gerado ausente: {relative}")
                elif sha256_file(file) != expected:
                    errors.append(f"Arquivo gerado alterado: {relative}")
        return not errors, tuple(errors)

    def source_fingerprint(self) -> str:
        paths: list[Path] = []
        for relative in (
            "harness/instructions.md",
            "harness/agents",
            "harness/skills",
            "harness/mcp",
            "harness/targets",
        ):
            path = self.root / relative
            if path.is_file():
                paths.append(path)
            elif path.is_dir():
                paths.extend(item for item in path.rglob("*") if item.is_file())
        digest = sha256()
        for path in sorted(paths):
            digest.update(str(path.relative_to(self.root)).encode("utf-8"))
            digest.update(path.read_bytes())
        return digest.hexdigest()
