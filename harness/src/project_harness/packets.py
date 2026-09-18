from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

from .checks import CheckRunner
from .context_service import ContextService
from .review_scope import split_dirty_and_carried
from .stack import ProjectStack, StackLoader, compact_summary
from .task_store import TaskStore
from .types import CheckResult, TaskMeta

NEVER_WEAKEN_TESTS = """
## Contrato de testes (obrigatório)

Não edite, apague, pule ou enfraqueça um teste válido existente só para fazer os checks obrigatórios passarem. Se um teste que já era verde e ainda corresponde à spec aprovada ficar vermelho depois de uma alteração, corrija o código de produto ou do harness sob teste, não esse teste. Novos testes exigidos pela spec aprovada podem ser adicionados. Testes que codificavam comportamento superado podem ser atualizados somente porque a spec mudou; não enfraqueça testes válidos não relacionados para esconder uma regressão.
"""

REVIEW_REPORT_CONTRACT = """
## Relatório humano da review

Quando existir, leia o último `review/review-NN.md`. Ele contém Summary, Blockers, High, Medium, Positive Findings, Verdict e Harness gate. **Não** contém o log de **Deterministic checks** — não despeje esse log no arquivo da review e não o use como contexto da Execute/Repair. O histórico de checks fica em `tests/checks-NN.md` (append-only, um arquivo por rodada, mesma numeração da review). O commit-gate/CLI pode mostrar `check_summary` ao humano; isso não é contexto da Execute.
"""

LATER_ROUND_REVIEW_RULE = """
Reinspeione a união de dirty ∪ carried (inclusive arquivos que já não estão dirty). Não varra o restante do repositório. Não abra backlog novo de mediums fora dessa união.

Revalide cada finding anterior, inclusive mediums, mesmo que não fossem obrigatórios de corrigir — confirme se cada ponto foi de fato tratado.

Rode de novo os quatro tracks (architecture, security, smells, tests) nos arquivos do diff atual, porque a rodada 1 pode ter deixado passar novo blocker/high.

Blocking ids da última review REJECTED ainda devem ser revalidados quando presentes.
"""


@dataclass(frozen=True)
class ContextPacket:
    stack: ProjectStack
    summary: str


class PacketService:
    def __init__(self, root: Path, store: TaskStore, context: ContextService) -> None:
        self.root = root
        self.store = store
        self.context = context

    def plan(self, meta: TaskMeta, feedback: str = "") -> Path:
        directory = self.store.packet_dir(meta.task_id)
        path = directory / "plan.md"
        task_dir = meta.worktree_path / meta.task_dir_relative
        docs_index = self.context.docs_index(meta.worktree_path)
        hints = self.context.docs_hint(meta.worktree_path)
        stack = StackLoader().load(self.root)
        packet = ContextPacket(stack=stack, summary=compact_summary(stack))
        feedback_block = (
            f"\n## Feedback humano da rodada anterior\n\n{feedback}\n"
            if feedback.strip()
            else ""
        )
        path.write_text(
            f"""# Harness Phase Packet — PLAN

Task: `{meta.task_id}-{meta.slug}`
Worktree: `{meta.worktree_path}`
Task artifacts: `{task_dir}`

## Pedido do usuário

{meta.request}

## Regra de contexto

Você é a única fase autorizada a investigar o projeto de forma ampla. Não despeje `docs/` inteiro no contexto.
Use o índice abaixo para selecionar somente os documentos relevantes, leia código por símbolo/área sob demanda e transforme a investigação em memória comprimida para as fases seguintes.

Prioridade global existente: {', '.join(f'`{item}`' for item in hints) if hints else 'nenhum documento-base detectado'}.

{docs_index}

{packet.summary}
## Skills obrigatórias

- `harness/skills/tlc-spec-driven/SKILL.md`

Use SPECIFY + TASKS. DESIGN não é obrigatório neste MVP; só use conceitos de design dentro do plano quando a complexidade realmente exigir, sem criar um agente de arquitetura.

- `harness/skills/conventional-commits/SKILL.md` — preencha `harness.commits` no frontmatter (um commit por módulo; testes separados). O **gate humano do PLAN** apresenta, nesta ordem: o plano, `harness.tests` (unit / integration / e2e, testes pontuais) e `harness.gates` + stack verify (comandos depois do Execute, antes da review). **Não** apresenta a lista de commits. Commits só entram no segundo gate. Não peça e não faça commit nesta fase.

Leia até o fim `docs/test/unit.md`, `docs/test/integration.md` e `docs/test/e2e.md` **antes** de preencher `harness.tests`. Eles são o contrato de fronteira (unit isolado sem HTTP/DB; integration = request Laravel + MySQL; e2e = browser seletivo). Classifique cada teste pontual nesses níveis **sem sobrepor** o mesmo cenário. Cada item deve dizer **o comportamento protegido**, não o comando que roda a suíte.

## Artefatos em `.specs/` (inglês)

Os arquivos da pasta da task (`context.md`, `spec.md`, `tasks.md`, `progress.md`, `validation.md`, `review/review-NN.md`) devem ser escritos em **inglês**, com estes headings. Não traduza identificadores de código. Texto fornecido pelo usuário pode permanecer no idioma original.

### `{task_dir / 'context.md'}`

```text
# Task Context
## Relevant Documentation
## Relevant Components
## Relevant Code
## Existing Constraints
## Important Decisions
```

Memória comprimida da investigação. Não copie arquivos inteiros e não registre raciocínio interno.

### `{task_dir / 'spec.md'}`

```text
# Specification
## Context
## Problem
## Goal
## User Stories
## Acceptance Criteria
## Edge Cases
## Out of Scope
## Considered Approaches
## Selected Approach
```

ACs identificáveis (AC-001...).

### `{task_dir / 'tasks.md'}`

Frontmatter YAML:

```yaml
---
harness:
  commits:
    - "feat(scope): English imperative description"
    - "test(scope): cover the same behavior"
  tests:
    unit:
      - "LoginRequest rejects empty email"
    integration:
      - "POST /login with valid credentials regenerates the session"
    e2e:
      - "Administrator submits the login screen and reaches /reservations"
  gates:
    - id: unit
      command: "comando real do projeto"
      required: true
---
```

Depois o corpo em inglês:

```text
# Implementation Plan
## Summary
## Affected Components
## Tasks
## Planned Tests
### Unit
### Integration
### E2E
## Required Gates
## Definition of Done
```

## Limites de contexto

- `context.md`: alvo <= 12k tokens; prefira bem menos.
- `spec.md`: alvo <= 5k tokens.
- `tasks.md`: alvo <= 10k tokens.
- Não coloque `progress.md` no contexto das fases seguintes.
{feedback_block}
## Encerramento

Não implemente código. Quando os três artefatos estiverem prontos:

`harness task complete-phase {meta.task_id} --phase plan`
""",
            encoding="utf-8",
        )
        return path

    def execute(
        self,
        meta: TaskMeta,
        *,
        repair: bool,
        feedback: str,
        blocking_ids: tuple[str, ...],
        failed_checks: tuple[CheckResult, ...],
        latest_review: Path | None = None,
        latest_verdict: str = "",
        latest_result: str = "",
    ) -> Path:
        directory = self.store.packet_dir(meta.task_id)
        name = "repair.md" if repair else "execute.md"
        path = directory / name
        task_dir = meta.worktree_path / meta.task_dir_relative
        findings = "\n".join(f"- `{item}`" for item in blocking_ids) or "- Nenhum finding estruturado."
        checks = "\n".join(
            f"- `{item.id}` `{item.command}` exit={item.exit_code}: {(item.stderr or item.stdout)[-1200:]}"
            for item in failed_checks
        ) or "- Nenhum check obrigatório vermelho informado."
        mode = "REPAIR" if repair else "EXECUTE"
        if not repair:
            extra = ""
        elif latest_review is None:
            extra = f"""
## Repair scope

Não há review anterior. Corrija os checks obrigatórios vermelhos no código (produto ou harness). Não invente findings de review. Não enfraqueça testes válidos para ficar verde.

Failed checks:
{checks}

Feedback humano:
{feedback.strip() or '(nenhum)'}
"""
        else:
            extra = f"""
## Repair scope

Corrija SOMENTE os blockers/high da **última** review, os checks obrigatórios vermelhos e o feedback humano abaixo. Não refaça a investigação ampla do Planner.

## Latest review (authoritative)

O histórico humano fica em `{task_dir / 'review'}` como `review-01.md`, `review-02.md`, ... Nunca substitua um arquivo anterior.

Use somente esta rodada:
- Human report: `{latest_review}`
- Verdict: `{latest_verdict.strip() or '(ausente)'}`
- Result JSON: `{latest_result.strip() or '(ausente)'}`

Não leve findings de um `review-NN.md` mais antigo. Leia o relatório acima e, quando necessário, os arquivos dos findings. Em repair, não abra novas frentes medium.

Blocking IDs:
{findings}

Failed checks:
{checks}

Feedback humano:
{feedback.strip() or '(nenhum)'}
"""
        latest_line = ""
        if repair:
            latest_line = (
                f"- `{latest_review}` — última review; veredito `{latest_verdict.strip() or '(ausente)'}`; "
                f"resultado `{latest_result.strip() or '(ausente)'}`\n"
                if latest_review is not None
                else "- última review: (ausente)\n"
            )
        path.write_text(
            f"""# Harness Phase Packet — {mode}

Task: `{meta.task_id}-{meta.slug}`
Worktree: `{meta.worktree_path}`

## Fonte de verdade do handoff

Comece SOMENTE por:
- `{task_dir / 'context.md'}`
- `{task_dir / 'spec.md'}`
- `{task_dir / 'tasks.md'}`
- `docs/test/unit.md`, `docs/test/integration.md` e `docs/test/e2e.md` (fronteira de testes; coloque cada item de `harness.tests` no nível declarado, sem sobrepor)
{latest_line}
A investigação ampla do Planner não atravessa esta fase. Use esses documentos como memória comprimida. Leia código inicialmente pelos arquivos/símbolos citados neles. Abra dependências adicionais apenas quando uma dependência concreta exigir.

**Não leia `progress.md` como contexto.** Ele é observabilidade humana.

Atualize `{task_dir / 'validation.md'}` em inglês, com:

```text
# Validation
## Acceptance Criteria
## Test Results
## Required Gates
## Review Result
## Final Status
```

## Skills obrigatórias

- `harness/skills/tlc-spec-driven/SKILL.md` — modo EXECUTE, implementação mínima guiada pelos ACs e testes.
- `harness/skills/security-best-practices/SKILL.md` — carregue somente referências da linguagem/framework realmente envolvidas.

## Regras

1. Trabalhe somente nesta worktree.
2. Testes derivam da spec, de `harness.tests` e de `docs/test/unit.md` / `docs/test/integration.md` / `docs/test/e2e.md`; crie um teste pontual para cada item **no nível declarado**.
3. Prefira testes antes ou junto da implementação conforme a skill TLC.
4. O `tasks.md` indica áreas esperadas, não é uma allowlist policialesca. Se um arquivo extra for necessário por dependência concreta, pode alterá-lo e registre a razão em `validation.md`.
5. Não faça scope creep / "while I'm here".
6. Você pode executar quick checks durante o trabalho; o harness executará os gates determinísticos de novo ao final.
7. Não declare gate final verde antes do harness rodá-lo.
8. Não faça commit. Depois do gate humano, o harness aplica `harness/skills/conventional-commits/SKILL.md` (um commit por módulo; testes separados).
{NEVER_WEAKEN_TESTS}{REVIEW_REPORT_CONTRACT}{extra}
## Encerramento

Quando a implementação/repair estiver pronta para os checks:

`harness task complete-phase {meta.task_id} --phase {'repair' if repair else 'execute'}`
""",
            encoding="utf-8",
        )
        return path

    def review(
        self,
        meta: TaskMeta,
        *,
        round_number: int,
        blocking_ids: tuple[str, ...],
        check_results: tuple[CheckResult, ...],
        dirty_paths: tuple[str, ...] = (),
        presented_paths: tuple[str, ...] = (),
    ) -> Path:
        directory = self.store.packet_dir(meta.task_id)
        path = directory / f"review-round-{round_number}.md"
        task_dir = meta.worktree_path / meta.task_dir_relative
        output = self.store.review_dir(meta.task_id, round_number)
        previous = "\n".join(f"- `{item}`" for item in blocking_ids) or "- primeira rodada"
        checks = CheckRunner.render(check_results)
        dirty, carried = split_dirty_and_carried(dirty_paths, presented_paths)
        dirty_block = "\n".join(f"- `{item}`" for item in dirty) or "- (nenhum path dirty)"
        if round_number <= 1:
            carried_block = (
                "Primeira rodada: o corpus é o dirty atual. Ainda não há arquivos carried "
                "de uma review anterior."
            )
        else:
            carried_list = "\n".join(f"- `{item}`" for item in carried) or "- (nenhum path carried)"
            carried_block = (
                "Apresentados na primeira review e não dirty agora (carried):\n"
                f"{carried_list}"
            )
        later_rule = LATER_ROUND_REVIEW_RULE if round_number > 1 else ""
        path.write_text(
            f"""# Harness Phase Packet — REVIEW ROUND {round_number}

Task: `{meta.task_id}-{meta.slug}`
Worktree: `{meta.worktree_path}`
Task dir: `{task_dir}`
Review runtime dir: `{output}`

## Contexto inicial permitido

Comece por:
- `{task_dir / 'spec.md'}`
- `{task_dir / 'tasks.md'}`
- estado atual do diff/status desta worktree;
- checks abaixo;
- para o track tests: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md` e `docs/reviews/review-tests.md`.

Não herde conversa do Executor. Leia arquivos alterados e contexto adjacente apenas para verificar evidência.

O harness grava cada rodada em `{task_dir / 'review' / 'review-NN.md'}` (append-only; nunca substitui `review-01.md`, `review-02.md`, ...). O markdown humano usa Summary, Blockers, High, Medium, Positive Findings, Verdict (da review consolidada, sem substituir por checks) e Harness gate. **Não** grave o log de **Deterministic checks** nesse arquivo. O harness grava o histórico de checks em `{task_dir / 'tests' / 'checks-NN.md'}` (append-only, um arquivo por rodada, mesma numeração da review).

## Checks determinísticos

{checks}

## Corpus desta rodada

Dirty (alterados agora):
{dirty_block}

{carried_block}
{later_rule}
## Skills obrigatórias

- `harness/skills/harness-review/SKILL.md`
- para o track security: `harness/skills/security-best-practices/SKILL.md`

Execute os **quatro tracks**: architecture, security, smells, tests. Não existe agente de arquitetura separado; arquitetura é somente um track independente da review. Track limpo exige `positives` com `path:line`; `findings: []` e `positives: []` falha o `review_gate.py`.

Arquivos esperados no diretório de runtime (não na pasta humana da task):
- `{output / 'architecture.json'}`
- `{output / 'security.json'}`
- `{output / 'smells.json'}`
- `{output / 'tests.json'}`
- `{output / 'consolidated.json'}`

## Repair re-review

Blocking IDs da rodada anterior:
{previous}

## Encerramento

Depois de gerar `consolidated.json` válido:

`harness task complete-phase {meta.task_id} --phase review --result {output / 'consolidated.json'}`
""",
            encoding="utf-8",
        )
        return path
