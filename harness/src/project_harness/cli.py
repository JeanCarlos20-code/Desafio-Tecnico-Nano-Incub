from __future__ import annotations

import argparse
import json
from pathlib import Path
import sys
import time

from .artifacts import ArtifactService
from .config import load_config
from .errors import HarnessError
from .git_manager import GitManager
from .task_store import TaskStore
from .types import JSONObject, JSONValue
from .utils import as_str, find_repository_root, git, run_process, slugify


def _parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="harness")
    commands = parser.add_subparsers(dest="command", required=True)

    sync = commands.add_parser("sync", help="Sincroniza agents/skills/MCP para CLIs/IDEs.")
    sync.add_argument("--targets", help="IDs separados por vírgula; vazio = todos.")
    commands.add_parser("check-sync")
    commands.add_parser("list-targets")

    task = commands.add_parser("task")
    task_commands = task.add_subparsers(dest="task_command", required=True)

    start = task_commands.add_parser("start")
    start.add_argument("request")
    start.add_argument("--target-branch")
    start.add_argument("--allow-dirty", action="store_true")
    start.add_argument("--json", action="store_true", dest="json_output")

    for name in ("action", "status", "inspect", "progress"):
        cmd = task_commands.add_parser(name)
        cmd.add_argument("task_id")
        cmd.add_argument("--json", action="store_true", dest="json_output")

    note = task_commands.add_parser("note")
    note.add_argument("task_id")
    note.add_argument("message")
    note.add_argument("--phase", choices=("plan", "execute", "repair", "review"), required=True)

    complete = task_commands.add_parser("complete-phase")
    complete.add_argument("task_id")
    complete.add_argument("--phase", choices=("plan", "execute", "repair", "review"), required=True)
    complete.add_argument("--result")
    complete.add_argument("--json", action="store_true", dest="json_output")

    approve_plan = task_commands.add_parser("approve-plan")
    approve_plan.add_argument("task_id")
    approve_plan.add_argument("--json", action="store_true", dest="json_output")

    revise_plan = task_commands.add_parser("revise-plan")
    revise_plan.add_argument("task_id")
    revise_plan.add_argument("message")
    revise_plan.add_argument("--json", action="store_true", dest="json_output")

    approve_commit = task_commands.add_parser("approve-commit")
    approve_commit.add_argument("task_id")
    approve_commit.add_argument("--json", action="store_true", dest="json_output")

    revise_code = task_commands.add_parser("revise-code")
    revise_code.add_argument("task_id")
    revise_code.add_argument("message")
    revise_code.add_argument("--json", action="store_true", dest="json_output")

    retry = task_commands.add_parser("retry-repair")
    retry.add_argument("task_id")
    retry.add_argument("--message", default="")
    retry.add_argument("--json", action="store_true", dest="json_output")

    stop = task_commands.add_parser("stop-repair")
    stop.add_argument("task_id")
    stop.add_argument("--json", action="store_true", dest="json_output")

    cancel = task_commands.add_parser("cancel")
    cancel.add_argument("task_id")
    cancel.add_argument("--message", default="")
    cancel.add_argument("--json", action="store_true", dest="json_output")

    resolved = task_commands.add_parser("resolve-integration")
    resolved.add_argument("task_id")
    resolved.add_argument("--json", action="store_true", dest="json_output")
    return parser


def _graph(root: Path):
    from .graph_runtime import HarnessGraph

    return HarnessGraph(root, load_config(root))


def _action_or_error(store: TaskStore, task_id: str) -> JSONObject:
    action = store.read_action(task_id)
    if action is None:
        raise HarnessError("A task não está aguardando uma action/interrupt neste momento.")
    return action


def _require_action(store: TaskStore, task_id: str, *, kind: str, phase: str = "", gate: str = "") -> JSONObject:
    action = _action_or_error(store, task_id)
    if as_str(action.get("kind")) != kind:
        raise HarnessError(f"Action atual não é {kind}: {as_str(action.get('kind'))}")
    if phase and as_str(action.get("phase")) != phase:
        raise HarnessError(f"Fase atual não é {phase}: {as_str(action.get('phase'))}")
    if gate and as_str(action.get("gate")) != gate:
        raise HarnessError(f"Gate atual não é {gate}: {as_str(action.get('gate'))}")
    return action


def _task_payload(root: Path, task_id: str) -> JSONObject:
    config = load_config(root)
    store = TaskStore(root, config)
    meta = store.load(task_id)
    graph = _graph(root)
    try:
        values = graph.values(meta)
    finally:
        graph.close()
    action = store.read_action(task_id)
    return {
        "task_id": meta.task_id,
        "request": meta.request,
        "target_branch": meta.target_branch,
        "task_branch": meta.task_branch,
        "base_commit": meta.base_commit,
        "worktree": str(meta.worktree_path),
        "task_dir": str(meta.worktree_path / meta.task_dir_relative),
        "state": values,
        "action": action,
    }


def _print_payload(payload: JSONObject, *, json_output: bool) -> None:
    if json_output:
        print(json.dumps(payload, ensure_ascii=False, indent=2))
        return
    task_id = as_str(payload.get("task_id"))
    print(f"Task: {task_id}")
    worktree = as_str(payload.get("worktree"))
    if worktree:
        print(f"Worktree: {worktree}")
    action_raw = payload.get("action")
    if isinstance(action_raw, dict):
        kind = as_str(action_raw.get("kind"))
        if kind == "agent":
            print(f"ACTION: agent:{as_str(action_raw.get('phase'))}")
            print(f"Agent: {as_str(action_raw.get('agent'))}")
            print(f"Packet: {as_str(action_raw.get('packet'))}")
        elif kind == "human":
            print(f"ACTION: human:{as_str(action_raw.get('gate'))}")
            message = as_str(action_raw.get("message"))
            if message:
                print(message)
            summary = as_str(action_raw.get("summary"))
            if summary:
                print("\n" + summary)
            check_summary = as_str(action_raw.get("check_summary"))
            if check_summary:
                print("\nBarreira de teste:\n" + check_summary)
            diff_stat = as_str(action_raw.get("diff_stat"))
            if diff_stat:
                print("\nDiff stat:\n" + diff_stat)
            inspect_commands = action_raw.get("inspect_commands")
            if isinstance(inspect_commands, list) and inspect_commands:
                print("\nPara inspecionar:")
                for item in inspect_commands:
                    if isinstance(item, str):
                        print(f"  {item}")
        return
    state_raw = payload.get("state")
    if isinstance(state_raw, dict):
        print(f"Status: {state_raw.get('status', 'unknown')}")
        print(f"Phase: {state_raw.get('phase', 'unknown')}")
        conflicts = state_raw.get("merge_conflicts")
        if isinstance(conflicts, list) and conflicts:
            print("Conflitos de integração:")
            for item in conflicts:
                print(f"- {item}")


def _resume(root: Path, task_id: str, payload: JSONObject, *, json_output: bool) -> int:
    config = load_config(root)
    store = TaskStore(root, config)
    meta = store.load(task_id)
    graph = _graph(root)
    try:
        graph.resume(meta, payload)
    finally:
        graph.close()
    result = _task_payload(root, task_id)
    _print_payload(result, json_output=json_output)
    return 0


def _start(root: Path, args: argparse.Namespace) -> int:
    config = load_config(root)
    git_manager = GitManager(root, config)
    target_branch = args.target_branch or git_manager.current_branch()
    if not target_branch:
        raise HarnessError("Não é possível iniciar task em detached HEAD.")

    # Falha cedo se LangGraph não estiver instalado, antes de criar worktree.
    graph = _graph(root)
    store = TaskStore(root, config)
    try:
        git_manager.ensure_root_ready(target_branch, bool(args.allow_dirty))
        task_id = store.reserve_next_id()
        slug = slugify(args.request)
        try:
            worktree, task_branch, base_commit = git_manager.create_worktree(task_id, slug, target_branch)
        except Exception:
            store.release_reservation(task_id)
            raise
        meta = store.build_meta(
            task_id=task_id,
            request=args.request,
            target_branch=target_branch,
            base_commit=base_commit,
            task_branch=task_branch,
            worktree_path=worktree,
        )
        store.save(meta)
        ArtifactService(config).prepare(worktree, meta.task_dir_relative, args.request, task_id)
        graph.start(meta)
    finally:
        graph.close()
    payload = _task_payload(root, task_id)
    _print_payload(payload, json_output=bool(args.json_output))
    return 0


def _resolve_integration(root: Path, task_id: str, *, json_output: bool) -> int:
    config = load_config(root)
    store = TaskStore(root, config)
    meta = store.load(task_id)
    result = run_process(
        ["git", "merge-base", "--is-ancestor", meta.task_branch, meta.target_branch],
        cwd=root,
    )
    if result.returncode != 0:
        raise HarnessError(
            "A task branch ainda não está integrada na target branch. Resolva/commite o merge manualmente primeiro."
        )
    GitManager(root, config).cleanup(meta.worktree_path, meta.task_branch)
    payload: JSONObject = {
        "task_id": task_id,
        "status": "completed_after_manual_integration",
        "target_branch": meta.target_branch,
    }
    if json_output:
        print(json.dumps(payload, ensure_ascii=False, indent=2))
    else:
        print(f"Task {task_id}: integração manual confirmada; worktree/branch temporária removidas.")
    return 0


def _progress(root: Path, task_id: str, *, json_output: bool) -> int:
    config = load_config(root)
    meta = TaskStore(root, config).load(task_id)
    path = meta.worktree_path / meta.task_dir_relative / "progress.md"
    text = path.read_text(encoding="utf-8") if path.is_file() else ""
    if json_output:
        print(json.dumps({"task_id": task_id, "progress_file": str(path), "content": text}, ensure_ascii=False, indent=2))
    else:
        print(text.rstrip())
    return 0


def _note(root: Path, task_id: str, phase: str, message: str) -> int:
    cleaned = " ".join(message.split()).strip()
    if not cleaned:
        raise HarnessError("A nota de progresso não pode ser vazia.")
    if len(cleaned) > 500:
        raise HarnessError("A nota de progresso deve ter no máximo 500 caracteres.")
    config = load_config(root)
    meta = TaskStore(root, config).load(task_id)
    task_dir = meta.worktree_path / meta.task_dir_relative
    event = f"worker-note-{time.time_ns()}"
    ArtifactService(config).append_progress(task_dir, event, f"[{phase.upper()}] {cleaned}")
    print(f"[{task_id}] {phase.upper()} {cleaned}")
    return 0


def main(argv: list[str] | None = None) -> int:
    args = _parser().parse_args(argv)
    try:
        root = find_repository_root()
        if args.command == "sync":
            from .sync.service import Synchronizer
            ids = tuple(item.strip() for item in args.targets.split(",") if item.strip()) if args.targets else None
            manifest = Synchronizer(root).sync(ids)
            print(json.dumps(manifest, ensure_ascii=False, indent=2))
            return 0
        if args.command == "check-sync":
            from .sync.service import Synchronizer
            ok, errors = Synchronizer(root).check()
            if ok:
                print("Sincronização válida.")
                return 0
            for item in errors:
                print(f"- {item}", file=sys.stderr)
            return 1
        if args.command == "list-targets":
            from .sync.registry import TargetRegistry
            for item in TargetRegistry(root).all():
                print(f"{item.id}\t{item.display_name}")
            return 0

        if args.command != "task":
            raise HarnessError("Comando inválido.")
        config = load_config(root)
        store = TaskStore(root, config)
        cmd = args.task_command
        if cmd == "start":
            return _start(root, args)
        if cmd in {"action", "status", "inspect"}:
            payload = _task_payload(root, args.task_id)
            _print_payload(payload, json_output=bool(args.json_output))
            return 0
        if cmd == "progress":
            return _progress(root, args.task_id, json_output=bool(args.json_output))
        if cmd == "note":
            return _note(root, args.task_id, args.phase, args.message)
        if cmd == "complete-phase":
            _require_action(store, args.task_id, kind="agent", phase=args.phase)
            payload: JSONObject = {
                "kind": "agent_result",
                "phase": args.phase,
                "status": "success",
            }
            if args.result:
                payload["result"] = args.result
            return _resume(root, args.task_id, payload, json_output=bool(args.json_output))
        if cmd == "approve-plan":
            _require_action(store, args.task_id, kind="human", gate="plan")
            return _resume(root, args.task_id, {"kind": "human_decision", "decision": "approve"}, json_output=bool(args.json_output))
        if cmd == "revise-plan":
            _require_action(store, args.task_id, kind="human", gate="plan")
            return _resume(root, args.task_id, {"kind": "human_decision", "decision": "request_changes", "message": args.message}, json_output=bool(args.json_output))
        if cmd == "approve-commit":
            _require_action(store, args.task_id, kind="human", gate="commit")
            return _resume(root, args.task_id, {"kind": "human_decision", "decision": "approve"}, json_output=bool(args.json_output))
        if cmd == "revise-code":
            _require_action(store, args.task_id, kind="human", gate="commit")
            return _resume(root, args.task_id, {"kind": "human_decision", "decision": "request_changes", "message": args.message}, json_output=bool(args.json_output))
        if cmd == "retry-repair":
            action = _require_action(store, args.task_id, kind="human")
            gate = as_str(action.get("gate"))
            if gate not in {"repair_limit", "check_fail_limit"}:
                raise HarnessError(
                    f"Gate atual não é repair_limit ou check_fail_limit: {gate}"
                )
            return _resume(root, args.task_id, {"kind": "human_decision", "decision": "retry", "message": args.message}, json_output=bool(args.json_output))
        if cmd == "stop-repair":
            action = _require_action(store, args.task_id, kind="human")
            gate = as_str(action.get("gate"))
            if gate not in {"repair_limit", "check_fail_limit"}:
                raise HarnessError(
                    f"Gate atual não é repair_limit ou check_fail_limit: {gate}"
                )
            return _resume(root, args.task_id, {"kind": "human_decision", "decision": "stop"}, json_output=bool(args.json_output))
        if cmd == "cancel":
            action = _require_action(store, args.task_id, kind="human")
            gate = as_str(action.get("gate"))
            if gate not in {"plan", "commit"}:
                raise HarnessError(f"Cancel não suportado no gate {gate}.")
            return _resume(root, args.task_id, {"kind": "human_decision", "decision": "cancel", "message": args.message}, json_output=bool(args.json_output))
        if cmd == "resolve-integration":
            return _resolve_integration(root, args.task_id, json_output=bool(args.json_output))
        raise HarnessError(f"task command desconhecido: {cmd}")
    except HarnessError as error:
        print(f"error: {error}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
