from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
import os

from .config import HarnessConfig
from .errors import HarnessError
from .utils import git, git_common_dir, run_process, slugify


@dataclass(frozen=True)
class MergeResult:
    status: str
    commit: str | None
    conflicts: tuple[str, ...]
    message: str


class GitManager:
    def __init__(self, root: Path, config: HarnessConfig) -> None:
        self.root = root
        self.config = config

    def current_branch(self) -> str:
        return git(self.root, "branch", "--show-current")

    def head(self, ref: str = "HEAD") -> str:
        return git(self.root, "rev-parse", ref)

    def ensure_target_exists(self, target_branch: str) -> None:
        result = run_process(
            ["git", "show-ref", "--verify", f"refs/heads/{target_branch}"],
            cwd=self.root,
        )
        if result.returncode != 0:
            raise HarnessError(f"Branch alvo local inexistente: {target_branch}")

    def changed_paths(self, cwd: Path | None = None) -> tuple[str, ...]:
        base = cwd or self.root
        output = git(base, "status", "--porcelain=v1", "--untracked-files=all")
        return _parse_porcelain_paths(output)

    def ignored_paths(self, cwd: Path | None = None) -> tuple[str, ...]:
        base = cwd or self.root
        output = git(
            base,
            "status",
            "--porcelain=v1",
            "--untracked-files=all",
            "--ignored",
        )
        return _parse_porcelain_paths(output, status="!!")

    def ensure_root_ready(self, target_branch: str, allow_dirty: bool) -> None:
        self.ensure_target_exists(target_branch)
        current = self.current_branch()
        if current != target_branch:
            raise HarnessError(
                f"A branch alvo precisa estar checkout no repositório principal. "
                f"Atual: {current or '(detached)'}; alvo: {target_branch}."
            )
        dirty = self.changed_paths(self.root)
        if dirty and not allow_dirty:
            raise HarnessError(
                "A branch alvo possui alterações locais. Faça commit/stash ou use --allow-dirty "
                "sabendo que a worktree parte apenas do HEAD commitado."
            )

    def worktree_root(self) -> Path:
        env_name = self.config.workflow.git.worktree_root_env
        configured = os.getenv(env_name)
        if configured:
            return Path(configured).expanduser().resolve()
        repo_key = slugify(self.root.name)
        return (Path.home() / ".cache" / "project-harness" / "worktrees" / repo_key).resolve()

    def create_worktree(self, task_id: str, slug: str, target_branch: str) -> tuple[Path, str, str]:
        base_commit = self.head(target_branch)
        branch = f"{self.config.workflow.git.task_branch_prefix}{task_id}-{slug}"
        path = self.worktree_root() / f"{task_id}-{slug}"
        if path.exists():
            raise HarnessError(f"Worktree já existe: {path}")
        path.parent.mkdir(parents=True, exist_ok=True)
        result = run_process(
            ["git", "worktree", "add", "-b", branch, str(path), target_branch],
            cwd=self.root,
        )
        if result.returncode != 0:
            raise HarnessError(result.stderr.strip() or result.stdout.strip())
        return path, branch, base_commit

    def diff_stat(self, worktree: Path) -> str:
        tracked = git(worktree, "diff", "--stat", "HEAD", check=False)
        untracked = git(worktree, "ls-files", "--others", "--exclude-standard", check=False)
        extras = [line for line in untracked.splitlines() if line.strip()]
        if not extras:
            return tracked
        suffix = "\n".join(f"new: {item}" for item in extras)
        return (tracked + "\n" + suffix).strip()

    def diff_text(self, worktree: Path, max_chars: int = 40000) -> str:
        tracked = git(worktree, "diff", "--no-ext-diff", "--unified=3", "HEAD", check=False)
        if len(tracked) > max_chars:
            return tracked[:max_chars] + "\n... diff truncado pelo harness ...\n"
        return tracked

    def stage_all(self, worktree: Path) -> None:
        git(worktree, "add", "-A")

    def commit_paths(self, worktree: Path, paths: tuple[str, ...], message: str) -> str:
        if not paths:
            raise HarnessError("Não há arquivos para este commit.")
        result = run_process(["git", "add", "--", *paths], cwd=worktree)
        if result.returncode != 0:
            raise HarnessError(result.stderr.strip() or result.stdout.strip())
        result = run_process(["git", "commit", "-m", message], cwd=worktree)
        if result.returncode != 0:
            raise HarnessError(result.stderr.strip() or result.stdout.strip())
        return git(worktree, "rev-parse", "HEAD")

    def commit(self, worktree: Path, message: str) -> str:
        paths = self.changed_paths(worktree)
        if not paths:
            raise HarnessError("Não há alterações para commit.")
        return self.commit_paths(worktree, paths, message)

    def try_merge(self, target_branch: str, task_branch: str, task_id: str) -> MergeResult:
        if self.current_branch() != target_branch:
            return MergeResult(
                status="blocked",
                commit=None,
                conflicts=(),
                message=f"Branch alvo não está ativa no repositório principal: {target_branch}",
            )
        dirty = self.changed_paths(self.root)
        if dirty:
            return MergeResult(
                status="blocked",
                commit=None,
                conflicts=(),
                message="Branch alvo possui alterações locais; integração pausada.",
            )
        args = ["git", "merge"]
        if self.config.workflow.git.merge_no_ff:
            args.append("--no-ff")
        args.extend([task_branch, "-m", f"merge(harness): integrate task {task_id}"])
        result = run_process(args, cwd=self.root)
        if result.returncode == 0:
            return MergeResult(
                status="merged",
                commit=self.head("HEAD"),
                conflicts=(),
                message="Merge concluído.",
            )
        conflicts_raw = git(
            self.root,
            "diff",
            "--name-only",
            "--diff-filter=U",
            check=False,
        )
        conflicts = tuple(line.strip() for line in conflicts_raw.splitlines() if line.strip())
        run_process(["git", "merge", "--abort"], cwd=self.root)
        return MergeResult(
            status="conflict" if conflicts else "blocked",
            commit=None,
            conflicts=conflicts,
            message=(
                "Conflito detectado; merge abortado e branch alvo restaurada."
                if conflicts
                else (result.stderr.strip() or result.stdout.strip() or "Merge não concluído.")
            ),
        )

    def cleanup(self, worktree: Path, task_branch: str) -> None:
        result = run_process(["git", "worktree", "remove", "--force", str(worktree)], cwd=self.root)
        combined = f"{result.stderr} {result.stdout}".lower()
        missing_worktree = "not a working tree" in combined or "is not a working tree" in combined
        if result.returncode != 0 and not missing_worktree:
            raise HarnessError(result.stderr.strip() or result.stdout.strip())
        delete = run_process(["git", "branch", "-d", task_branch], cwd=self.root)
        delete_text = f"{delete.stderr} {delete.stdout}".lower()
        missing_branch = "not found" in delete_text or "doesn't exist" in delete_text or "does not exist" in delete_text
        if delete.returncode != 0 and not missing_branch:
            raise HarnessError(delete.stderr.strip() or delete.stdout.strip())


def _parse_porcelain_paths(output: str, status: str | None = None) -> tuple[str, ...]:
    paths: list[str] = []
    for line in output.splitlines():
        if not line.strip():
            continue
        if status is not None and not line.startswith(status):
            continue
        if line.startswith("?? ") or line.startswith("!! "):
            raw = line[3:]
        elif len(line) >= 3 and line[2] == " ":
            raw = line[3:]
        elif len(line) >= 2 and line[1] == " ":
            raw = line[2:]
        else:
            raw = line[3:] if len(line) >= 4 else line
        if " -> " in raw:
            raw = raw.split(" -> ", 1)[1]
        path = raw.strip().strip('"')
        if path:
            paths.append(path)
    return tuple(paths)
