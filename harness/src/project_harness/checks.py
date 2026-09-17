from __future__ import annotations

from pathlib import Path
import shlex

from .errors import HarnessError
from .stack import Component, ProjectStack, component_cwd, select_affected_components
from .types import CheckResult, GateSpec
from .utils import run_process


class CheckRunner:
    def run(self, worktree: Path, gates: tuple[GateSpec, ...]) -> tuple[CheckResult, ...]:
        results: list[CheckResult] = []
        for gate in gates:
            process = run_process(
                ["bash", "-lc", gate.command],
                cwd=worktree,
                check=False,
            )
            results.append(
                CheckResult(
                    id=gate.id,
                    command=gate.command,
                    exit_code=process.returncode,
                    stdout=process.stdout[-12000:],
                    stderr=process.stderr[-12000:],
                    required=gate.required,
                )
            )
        return tuple(results)

    def run_component_command(
        self,
        worktree: Path,
        component: Component,
        command_id: str,
        *,
        required: bool = True,
    ) -> CheckResult:
        command = component.commands.get(command_id)
        if command is None:
            raise HarnessError(
                f"Componente '{component.name}' não declara o command ID '{command_id}'."
            )
        if not command.command.strip():
            raise HarnessError(
                f"Comando vazio no componente '{component.name}' ID '{command_id}'."
            )
        cwd = component_cwd(worktree, component)
        process = run_process(
            ["bash", "-lc", command.command],
            cwd=cwd,
            check=False,
        )
        return CheckResult(
            id=f"{component.name}:{command_id}",
            command=command.command,
            exit_code=process.returncode,
            stdout=process.stdout[-12000:],
            stderr=process.stderr[-12000:],
            required=required,
        )

    def run_verify(
        self,
        worktree: Path,
        stack: ProjectStack,
        changed_paths: tuple[str, ...],
        required_ids: tuple[str, ...],
    ) -> tuple[CheckResult, ...]:
        if not required_ids:
            return ()
        affected = select_affected_components(stack.components, changed_paths)
        if not affected:
            return ()
        results: list[CheckResult] = []
        for component in affected:
            for command_id in required_ids:
                results.append(self.run_component_command(worktree, component, command_id))
        return tuple(results)

    @staticmethod
    def required_passed(results: tuple[CheckResult, ...]) -> bool:
        return all(item.passed or not item.required for item in results)

    @staticmethod
    def render(results: tuple[CheckResult, ...]) -> str:
        if not results:
            return "Nenhum comando automático configurado para esta tarefa."
        lines: list[str] = []
        for item in results:
            mark = "✅" if item.passed else "❌"
            required = "required" if item.required else "optional"
            lines.append(f"- {mark} `{item.command}` — exit={item.exit_code} ({required})")
            if not item.passed:
                detail = (item.stderr.strip() or item.stdout.strip())[-2000:]
                if detail:
                    lines.append("\n```text")
                    lines.append(detail)
                    lines.append("```")
        return "\n".join(lines)
