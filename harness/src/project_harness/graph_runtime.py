from __future__ import annotations

from pathlib import Path
import os
import sqlite3
from typing import cast

from .artifacts import ArtifactService
from .commits import apply_commit_plan
from .config import HarnessConfig
from .context_service import ContextService
from .errors import HarnessError
from .git_manager import GitManager
from .packets import PacketService
from .review_service import ReviewService
from .stack import StackLoader, merge_verify_paths
from .task_store import TaskStore
from .types import CheckResult, HarnessState, JSONObject, JSONValue, ReviewVerdict, TaskMeta
from .utils import as_object, as_str, require_object, to_json_value


def _langgraph_imports() -> tuple[type[object], object, object, object, type[object]]:
    os.environ.setdefault("LANGGRAPH_STRICT_MSGPACK", "true")
    try:
        from langgraph.graph import END, START, StateGraph
        from langgraph.types import Command, interrupt
        from langgraph.checkpoint.sqlite import SqliteSaver
    except ImportError as error:
        raise HarnessError(
            "Dependências LangGraph ausentes. Rode `pip install -e ./harness` antes de usar task."
        ) from error
    return StateGraph, START, END, interrupt, SqliteSaver


class HarnessGraph:
    def __init__(self, root: Path, config: HarnessConfig) -> None:
        StateGraph, START, END, interrupt_fn, SqliteSaver = _langgraph_imports()
        self.root = root
        self.config = config
        self.store = TaskStore(root, config)
        self.git = GitManager(root, config)
        self.artifacts = ArtifactService(config)
        self.context = ContextService(root, config)
        self.packets = PacketService(root, self.store, self.context)
        self.checks = CheckRunner()
        self.reviews = ReviewService()
        self._interrupt = interrupt_fn
        self._connection = sqlite3.connect(self.store.checkpoint_path, check_same_thread=False)
        self._checkpointer = SqliteSaver(self._connection)
        if hasattr(self._checkpointer, "setup"):
            self._checkpointer.setup()

        builder = StateGraph(HarnessState)
        builder.add_node("plan_worker", self._plan_worker)
        builder.add_node("plan_approval", self._plan_approval)
        builder.add_node("execute_worker", self._execute_worker)
        builder.add_node("checks", self._checks)
        builder.add_node("review_worker", self._review_worker)
        builder.add_node("review_gate", self._review_gate)
        builder.add_node("repair_worker", self._repair_worker)
        builder.add_node("repair_escalation", self._repair_escalation)
        builder.add_node("commit_approval", self._commit_approval)
        builder.add_node("commit", self._commit)
        builder.add_node("merge", self._merge)
        builder.add_node("cleanup", self._cleanup)
        builder.add_node("canceled", self._canceled)
        builder.add_node("needs_human", self._needs_human)

        builder.add_edge(START, "plan_worker")
        builder.add_edge("plan_worker", "plan_approval")
        builder.add_conditional_edges(
            "plan_approval",
            self._route_plan_approval,
            {
                "approve": "execute_worker",
                "revise": "plan_worker",
                "cancel": "canceled",
            },
        )
        builder.add_edge("execute_worker", "checks")
        builder.add_edge("checks", "review_worker")
        builder.add_edge("review_worker", "review_gate")
        builder.add_conditional_edges(
            "review_gate",
            self._route_review,
            {
                "approved": "commit_approval",
                "repair": "repair_worker",
                "escalate": "repair_escalation",
            },
        )
        builder.add_edge("repair_worker", "checks")
        builder.add_conditional_edges(
            "repair_escalation",
            self._route_escalation,
            {"retry": "repair_worker", "stop": "needs_human"},
        )
        builder.add_conditional_edges(
            "commit_approval",
            self._route_commit_approval,
            {
                "approve": "commit",
                "revise": "repair_worker",
                "cancel": "canceled",
            },
        )
        builder.add_edge("commit", "merge")
        builder.add_conditional_edges(
            "merge",
            self._route_merge,
            {"merged": "cleanup", "human": "needs_human"},
        )
        builder.add_edge("cleanup", END)
        builder.add_edge("canceled", END)
        builder.add_edge("needs_human", END)
        self.graph = builder.compile(checkpointer=self._checkpointer)

    def close(self) -> None:
        self._connection.close()

    def _config_for(self, meta: TaskMeta) -> dict[str, dict[str, str]]:
        return {"configurable": {"thread_id": meta.thread_id}}

    def start(self, meta: TaskMeta) -> object:
        initial: HarnessState = {
            "task_id": meta.task_id,
            "request": meta.request,
            "slug": meta.slug,
            "target_branch": meta.target_branch,
            "base_commit": meta.base_commit,
            "task_branch": meta.task_branch,
            "worktree_path": str(meta.worktree_path),
            "task_dir_relative": str(meta.task_dir_relative),
            "phase": "plan",
            "repair_round": 0,
            "review_round": 0,
            "blocking_ids": [],
            "check_results": [],
            "runtime_dir": str(self.store.runtime_root),
            "status": "running",
        }
        return self.graph.invoke(initial, self._config_for(meta))

    def resume(self, meta: TaskMeta, payload: JSONObject) -> object:
        try:
            from langgraph.types import Command
        except ImportError as error:
            raise HarnessError("LangGraph indisponível.") from error
        return self.graph.invoke(Command(resume=payload), self._config_for(meta))

    def values(self, meta: TaskMeta) -> JSONObject:
        snapshot = self.graph.get_state(self._config_for(meta))
        raw: object = snapshot.values
        value = to_json_value(raw, "LangGraph state")
        if not isinstance(value, dict):
            return {}
        return value

    def _meta(self, state: HarnessState) -> TaskMeta:
        return TaskMeta(
            task_id=state["task_id"],
            slug=state["slug"],
            request=state["request"],
            target_branch=state["target_branch"],
            base_commit=state["base_commit"],
            task_branch=state["task_branch"],
            worktree_path=Path(state["worktree_path"]),
            task_dir_relative=Path(state["task_dir_relative"]),
            thread_id=self.store.load(state["task_id"]).thread_id,
        )

    def _task_dir(self, state: HarnessState) -> Path:
        meta = self._meta(state)
        return meta.worktree_path / meta.task_dir_relative

    def _agent_interrupt(self, meta: TaskMeta, phase: str, packet: Path) -> JSONObject:
        payload: JSONObject = {
            "kind": "agent",
            "phase": phase,
            "task_id": meta.task_id,
            "agent": f"harness-{phase if phase != 'repair' else 'execute'}",
            "packet": str(packet),
            "worktree": str(meta.worktree_path),
        }
        self.store.write_action(meta.task_id, payload)
        raw: object = self._interrupt(payload)
        response = require_object(raw, f"resume {phase}")
        if as_str(response.get("kind")) != "agent_result":
            raise HarnessError(f"Resposta inválida para fase {phase}.")
        if as_str(response.get("phase")) != phase:
            raise HarnessError(f"Resultado recebido para fase errada: {as_str(response.get('phase'))}")
        if as_str(response.get("status")) != "success":
            raise HarnessError(f"Agente {phase} não concluiu com sucesso.")
        return response

    def _human_interrupt(self, meta: TaskMeta, payload: JSONObject) -> JSONObject:
        self.store.write_action(meta.task_id, payload)
        raw: object = self._interrupt(payload)
        response = require_object(raw, "human resume")
        if as_str(response.get("kind")) != "human_decision":
            raise HarnessError("Resposta humana inválida.")
        return response

    def _plan_worker(self, state: HarnessState) -> HarnessState:
        meta = self._meta(state)
        feedback = state.get("plan_feedback", "")
        packet = self.packets.plan(meta, feedback)
        self._agent_interrupt(meta, "plan", packet)
        task_dir = self._task_dir(state)
        plan = self.artifacts.validate_plan(task_dir)
        event = f"plan-ready-{abs(hash(feedback))}"
        self.artifacts.append_progress(task_dir, event, "Plan, spec, and tests are ready for human approval.")
        return {
            "phase": "plan_approval",
            "packet_path": str(packet),
            "warnings": list(plan.warnings),
            "commit_message": plan.commit_message,
            "plan_feedback": "",
            "status": "waiting_plan_approval",
        }

    def _plan_approval(self, state: HarnessState) -> HarnessState:
        meta = self._meta(state)
        task_dir = self._task_dir(state)
        summary = self.artifacts.plan_summary(task_dir)
        warnings = state.get("warnings", [])
        payload: JSONObject = {
            "kind": "human",
            "gate": "plan",
            "task_id": meta.task_id,
            "message": "Revise o plano, a solução proposta e as barreiras de teste antes de liberar implementação.",
            "summary": summary,
            "warnings": [str(item) for item in warnings],
            "options": ["approve", "request_changes", "cancel"],
        }
        decision = self._human_interrupt(meta, payload)
        value = as_str(decision.get("decision"))
        if value == "approve":
            self.artifacts.append_progress(task_dir, "plan-approved", "Plan approved by the user; implementation is unblocked.")
            return {"phase": "execute", "status": "running", "human_message": "approve"}
        if value == "request_changes":
            feedback = as_str(decision.get("message")).strip()
            if not feedback:
                raise HarnessError("request_changes exige uma mensagem.")
            self.artifacts.append_progress(task_dir, f"plan-revision-{abs(hash(feedback))}", "User requested plan changes.")
            return {"phase": "plan", "plan_feedback": feedback, "human_message": "revise"}
        if value == "cancel":
            return {"phase": "canceled", "status": "canceled", "canceled_reason": as_str(decision.get("message")), "human_message": "cancel"}
        raise HarnessError(f"Decisão de plano inválida: {value}")

    @staticmethod
    def _route_plan_approval(state: HarnessState) -> str:
        return state.get("human_message", "revise")

    def _execute_worker(self, state: HarnessState) -> HarnessState:
        meta = self._meta(state)
        packet = self.packets.execute(meta, repair=False, feedback="", blocking_ids=(), failed_checks=())
        self._agent_interrupt(meta, "execute", packet)
        self.artifacts.append_progress(self._task_dir(state), "execute-complete", "Initial implementation finished; starting deterministic checks.")
        return {"phase": "checks", "last_worker_phase": "execute", "packet_path": str(packet)}

    def _repair_worker(self, state: HarnessState) -> HarnessState:
        meta = self._meta(state)
        next_round = int(state.get("repair_round", 0)) + 1
        checks = self._checks_from_state(state)
        failed = tuple(item for item in checks if item.required and not item.passed)
        blocking = tuple(state.get("blocking_ids", []))
        feedback = state.get("code_feedback", "")
        packet = self.packets.execute(
            meta,
            repair=True,
            feedback=feedback,
            blocking_ids=blocking,
            failed_checks=failed,
        )
        self._agent_interrupt(meta, "repair", packet)
        self.artifacts.append_progress(
            self._task_dir(state),
            f"repair-{next_round}",
            f"Repair round {next_round} finished; checks will run again.",
        )
        return {
            "phase": "checks",
            "last_worker_phase": "repair",
            "repair_round": next_round,
            "code_feedback": "",
            "packet_path": str(packet),
        }

    def _checks(self, state: HarnessState) -> HarnessState:
        task_dir = self._task_dir(state)
        plan = self.artifacts.validate_plan(task_dir)
        worktree = Path(state["worktree_path"])
        results = self.checks.run(worktree, plan.gates)
        required_ids = self.config.verify.required
        if required_ids:
            stack = StackLoader().load(self.root)
            stack_results = self.checks.run_verify(
                worktree,
                stack,
                merge_verify_paths(
                    stack.components,
                    self.git.changed_paths(worktree),
                    self.git.ignored_paths(worktree),
                ),
                required_ids,
            )
            results = results + stack_results
        rendered = self.checks.render(results)
        self.artifacts.append_validation_checks(task_dir, rendered)
        payloads: list[dict[str, JSONValue]] = []
        for item in results:
            payloads.append(
                {
                    "id": item.id,
                    "command": item.command,
                    "exit_code": item.exit_code,
                    "stdout": item.stdout,
                    "stderr": item.stderr,
                    "required": item.required,
                }
            )
        green = self.checks.required_passed(results)
        self.artifacts.append_progress(
            task_dir,
            f"checks-{state.get('repair_round', 0)}-{state.get('review_round', 0)}",
            "Required checks are green." if green else "Required checks failed; review still runs before repair.",
        )
        return {"phase": "review", "check_results": payloads}

    def _review_worker(self, state: HarnessState) -> HarnessState:
        meta = self._meta(state)
        next_round = int(state.get("review_round", 0)) + 1
        checks = self._checks_from_state(state)
        blocking = tuple(state.get("blocking_ids", []))
        packet = self.packets.review(meta, round_number=next_round, blocking_ids=blocking, check_results=checks)
        response = self._agent_interrupt(meta, "review", packet)
        expected = self.store.review_dir(meta.task_id, next_round) / "consolidated.json"
        result_raw = as_str(response.get("result"))
        report_path = Path(result_raw) if result_raw else expected
        if not report_path.is_file():
            raise HarnessError(f"Review consolidada não encontrada: {report_path}")
        review = self.reviews.load(report_path)
        checks_green = self.checks.required_passed(checks)
        task_dir = self._task_dir(state)
        (task_dir / "review.md").write_text(
            self.reviews.render_markdown(review, checks_green),
            encoding="utf-8",
        )
        self.artifacts.append_progress(
            task_dir,
            f"review-{next_round}",
            f"Review round {next_round}: {review.verdict.value}; blockers/high: {len(review.blocking_ids)}.",
        )
        return {
            "phase": "review_gate",
            "review_round": next_round,
            "review_verdict": review.verdict.value,
            "blocking_ids": list(review.blocking_ids),
            "review_report_path": str(report_path),
            "packet_path": str(packet),
        }

    def _review_gate(self, state: HarnessState) -> HarnessState:
        return {"phase": "review_gate"}

    def _route_review(self, state: HarnessState) -> str:
        checks_green = self.checks.required_passed(self._checks_from_state(state))
        approved = state.get("review_verdict") == ReviewVerdict.APPROVED.value
        if approved and checks_green:
            return "approved"
        if int(state.get("repair_round", 0)) < self.config.workflow.max_repair_rounds:
            return "repair"
        return "escalate"

    def _repair_escalation(self, state: HarnessState) -> HarnessState:
        meta = self._meta(state)
        payload: JSONObject = {
            "kind": "human",
            "gate": "repair_limit",
            "task_id": meta.task_id,
            "message": f"A tarefa atingiu {self.config.workflow.max_repair_rounds} repairs sem ficar verde.",
            "blocking_ids": list(state.get("blocking_ids", [])),
            "options": ["retry", "stop"],
        }
        decision = self._human_interrupt(meta, payload)
        value = as_str(decision.get("decision"))
        if value == "retry":
            return {
                "human_message": "retry",
                "repair_round": max(0, self.config.workflow.max_repair_rounds - 1),
                "code_feedback": as_str(decision.get("message")),
            }
        if value == "stop":
            return {"human_message": "stop", "status": "needs_human_attention"}
        raise HarnessError(f"Decisão inválida no limite de repair: {value}")

    @staticmethod
    def _route_escalation(state: HarnessState) -> str:
        return state.get("human_message", "stop")

    def _commit_approval(self, state: HarnessState) -> HarnessState:
        meta = self._meta(state)
        task_dir = self._task_dir(state)
        checks = self._checks_from_state(state)
        payload: JSONObject = {
            "kind": "human",
            "gate": "commit",
            "task_id": meta.task_id,
            "message": "Review e checks passaram. Inspecione o código antes de autorizar commit + integração na branch alvo.",
            "worktree": str(meta.worktree_path),
            "target_branch": meta.target_branch,
            "task_branch": meta.task_branch,
            "commit_message": state.get("commit_message", ""),
            "commit_skill": "harness/skills/conventional-commits/SKILL.md",
            "diff_stat": self.git.diff_stat(meta.worktree_path),
            "review_file": str(task_dir / "review.md"),
            "validation_file": str(task_dir / "validation.md"),
            "check_summary": self.checks.render(checks),
            "inspect_commands": [
                f"git -C {meta.worktree_path} status --short",
                f"git -C {meta.worktree_path} diff HEAD",
            ],
            "options": ["approve", "request_changes", "cancel"],
        }
        decision = self._human_interrupt(meta, payload)
        value = as_str(decision.get("decision"))
        if value == "approve":
            self.artifacts.append_progress(task_dir, "commit-approved", "Commit and integration authorized by the user.")
            return {"phase": "commit", "human_message": "approve", "status": "running"}
        if value == "request_changes":
            feedback = as_str(decision.get("message")).strip()
            if not feedback:
                raise HarnessError("request_changes exige feedback para o Execute.")
            self.artifacts.append_progress(task_dir, f"code-revision-{abs(hash(feedback))}", "User requested code changes before commit.")
            return {"phase": "repair", "human_message": "revise", "code_feedback": feedback}
        if value == "cancel":
            return {"phase": "canceled", "human_message": "cancel", "status": "canceled", "canceled_reason": as_str(decision.get("message"))}
        raise HarnessError(f"Decisão de commit inválida: {value}")

    @staticmethod
    def _route_commit_approval(state: HarnessState) -> str:
        return state.get("human_message", "revise")

    def _commit(self, state: HarnessState) -> HarnessState:
        meta = self._meta(state)
        task_dir = self._task_dir(state)
        plan = self.artifacts.validate_plan(task_dir)
        commit = apply_commit_plan(self.git, meta.worktree_path, plan.commits)
        return {"phase": "merge", "final_commit": commit, "commit_message": plan.commit_message}

    def _merge(self, state: HarnessState) -> HarnessState:
        meta = self._meta(state)
        result = self.git.try_merge(meta.target_branch, meta.task_branch, meta.task_id)
        return {
            "integration_status": result.status,
            "merge_conflicts": list(result.conflicts),
            "status": "running" if result.status == "merged" else "needs_human_attention",
            "phase": "cleanup" if result.status == "merged" else "needs_human_attention",
            "human_message": result.message,
        }

    @staticmethod
    def _route_merge(state: HarnessState) -> str:
        return "merged" if state.get("integration_status") == "merged" else "human"

    def _cleanup(self, state: HarnessState) -> HarnessState:
        meta = self._meta(state)
        self.git.cleanup(meta.worktree_path, meta.task_branch)
        self.store.clear_action(meta.task_id)
        return {"phase": "done", "status": "completed"}

    def _canceled(self, state: HarnessState) -> HarnessState:
        self.store.clear_action(state["task_id"])
        return {"phase": "canceled", "status": "canceled"}

    def _needs_human(self, state: HarnessState) -> HarnessState:
        self.store.clear_action(state["task_id"])
        return {"phase": "needs_human_attention", "status": "needs_human_attention"}

    @staticmethod
    def _checks_from_state(state: HarnessState) -> tuple[CheckResult, ...]:
        results: list[CheckResult] = []
        for raw in state.get("check_results", []):
            exit_code_raw = raw.get("exit_code")
            required_raw = raw.get("required")
            results.append(
                CheckResult(
                    id=str(raw.get("id", "gate")),
                    command=str(raw.get("command", "")),
                    exit_code=exit_code_raw if isinstance(exit_code_raw, int) and not isinstance(exit_code_raw, bool) else 1,
                    stdout=str(raw.get("stdout", "")),
                    stderr=str(raw.get("stderr", "")),
                    required=required_raw if isinstance(required_raw, bool) else True,
                )
            )
        return tuple(results)
