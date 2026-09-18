# Project Harness — LangGraph MVP

Harness neutro para trabalhar com tarefas isoladas em Git worktrees, planejamento Spec-Driven, dois gates humanos e review/repair automático.

## Escopo deste MVP

Incluído:

- LangGraph como máquina de estados;
- SQLite checkpointer persistente por repositório;
- uma worktree/branch temporária por task;
- pasta humana por task: `.specs/tasks/NNNN-slug/`;
- PLAN com `tlc-spec-driven`;
- artefatos de `.specs/` em inglês (User Stories, ACs, testes e gates) antes da implementação;
- aprovação humana obrigatória do plano;
- EXECUTE com `tlc-spec-driven` + `security-best-practices`;
- contexto limpo entre PLAN / EXECUTE / REVIEW por ContextPacket;
- checks determinísticos definidos pelo plano;
- REVIEW independente com architecture + security + smells + tests usando `harness-review`;
- blocker/high após Review APPROVED recusada, ou check obrigatório vermelho após Execute/Repair, => repair (check vermelho não inicia Review);
- no máximo 3 ciclos de check-fix e, independentemente, 3 ciclos de review-repair antes de escalar ao humano;
- segunda aprovação humana antes de commit/merge;
- commit na task branch e merge na branch alvo;
- conflito de merge nunca é resolvido automaticamente; o merge é abortado e worktree/branch ficam preservadas;
- `sync` separado do workflow para agents, skills, instruction files e MCP.

Fora deste MVP: RAG, model router/scoring, architecture agent, deploy/push automático.

## Instalação

O diretório `harness/` deve ficar na raiz do projeto.

```bash
python3 -m venv .venv-harness
source .venv-harness/bin/activate
pip install -e "./harness[test]"
```

O `langgraph-checkpoint-sqlite` persiste interrupts e estado em `.git/harness/checkpoints.sqlite`. O harness força `LANGGRAPH_STRICT_MSGPACK=true`; o state contém somente valores simples serializáveis.

Depois do `pip install -e ./harness`, o comando canônico é `harness`. O wrapper `./harness/run` chama o mesmo CLI sem depender do PATH.

## Sync das CLIs

```bash
cp .env.mcp.example .env.mcp   # preencha as chaves
harness sync
harness check-sync
harness list-targets
```

O sync lê `.env.mcp` na raiz do repo e interpola `CONTEXT7_API_KEY`, `GITHUB_MCP_TOKEN` e, se presentes, `ARCHITECTURE_DATABASE_URL` / `SNYK_TOKEN`. O arquivo não vai para o git. Sem ele, o Cursor recebe placeholders `${env:...}` no `.cursor/mcp.json`.

Targets herdados do harness anterior:

- Claude Code
- Codex / Agent Skills
- GitHub Copilot
- Cursor
- Gemini CLI
- Generic Agent Skills
- JetBrains AI Assistant
- OpenCode
- Pi
- Windsurf
- Zed
- Antigravity

O sync é independente do LangGraph da task. Ele materializa os quatro entrypoints canônicos:

```text
harness
harness-plan
harness-execute
harness-review
```

mais as skills:

```text
tlc-spec-driven
harness-review
security-best-practices
```

Os formatos/path de cada target e MCP vêm de `harness/targets/*.yaml` e `harness/mcp/servers.yaml`, seguindo o desenho do harness antigo.

## Uma task

Na branch de trabalho do usuário, por exemplo `feature/desafio-salas`:

```bash
harness task start "implementar criação de reservas"
```

O harness cria:

```text
branch temporária: harness/0001-implementar-criacao-de-reservas
worktree: ~/.cache/project-harness/worktrees/<repo>/0001-implementar-criacao-de-reservas/

.specs/tasks/0001-implementar-criacao-de-reservas/
├── context.md
├── spec.md
├── tasks.md
├── progress.md
├── validation.md
└── review/
    ├── review-01.md
    └── review-02.md   # rodadas seguintes; nunca sobrescreve a anterior
```

`progress.md` é observabilidade humana e **não entra automaticamente no contexto dos modelos**. Workers registram apenas milestones curtos; isso não é cadeia de pensamento e não é relido nas fases seguintes.

Para acompanhar:

```bash
harness task progress 0001
```

Workers podem persistir um milestone sem carregar o histórico:

```bash
harness task note 0001 --phase execute "testes unitários adicionados"
```

## Context isolation / tokens

A conversa de uma fase não é a memória da fase seguinte.

```text
PLAN (investigação ampla e seletiva)
  -> context.md + spec.md + tasks.md
  -> CONTEXT RESET
EXECUTE (handoff comprimido + reads direcionados)
  -> diff + checks
  -> CONTEXT RESET
REVIEW (spec + tasks + diff/checks + docs de review sob demanda)
  -> findings
  -> CONTEXT RESET
REPAIR (blocking findings + failed checks + arquivos afetados)
```

PLAN recebe um índice leve de `docs/` (paths, headings e estimativa de tokens), não o conteúdo inteiro. Ele escolhe ADRs/modules/screens/context/tree/architecture relevantes e comprime a investigação em `context.md`.

Metas default em `harness/config.yaml`:

- `context.md <= 12k` tokens;
- `spec.md <= 5k`;
- `tasks.md <= 10k`.

São warnings de compactação, não uma allowlist rígida.

## Protocolo cooperativo das CLIs

O LangGraph usa `interrupt()` tanto para workers quanto para humanos. O arquivo de action fica em `.git/harness/actions/<task>.json`.

Consulte:

```bash
harness task action 0001
```

Uma action agent aponta para um packet e uma worktree:

```text
ACTION: agent:plan
Agent: harness-plan
Packet: .../.git/harness/packets/0001/plan.md
```

O orquestrador da CLI deve delegar para um subagent/contexto fresco quando o runtime suportar. O worker lê o packet e termina chamando:

```bash
harness task complete-phase 0001 --phase plan
harness task complete-phase 0001 --phase execute
harness task complete-phase 0001 --phase repair
harness task complete-phase 0001 --phase review --result <consolidated.json>
```

## Gate 1 — plano

PLAN deve preencher:

- `context.md`: memória comprimida da investigação;
- `spec.md`: problema, objetivo, User Stories, Acceptance Criteria e edge cases;
- `tasks.md`: solução, abordagens consideradas, abordagem escolhida, tarefas, testes pontuais em unit/integration/e2e (`harness.tests`) e Required Gates.

`tasks.md` começa com frontmatter:

```yaml
---
harness:
  commit_message: "feat(reservation): add room booking"
  tests:
    unit:
      - "CreateReservation rejects an overlapping slot"
    integration:
      - "POST /reservations persists the booking in MySQL"
    e2e:
      - "Administrator books a room through the real screen"
  gates:
    - id: unit
      command: "php artisan test --testsuite=Unit"
      required: true
    - id: full
      command: "php artisan test"
      required: true
---
```

O gate humano mostra o plano, depois os testes pontuais (unit / integration / e2e), depois os comandos que rodam após o Execute.

Aprovar:

```bash
harness task approve-plan 0001
```

Pedir mudança:

```bash
harness task revise-plan 0001 "quero integração cobrindo conflito de horário"
```

## Execute / checks / review

Execute não comita. O harness executa os gates do `tasks.md` e o stack verify (`test` / `lint` / `build`) depois do worker terminar.

Loop canônico: Execute → test/build/lint obrigatórios → se vermelho, Repair no código (não enfraquece teste válido; isso **não** conta ciclo de review) e re-roda checks → tudo verde → Review → se REJECTED, Repair dos findings → checks de novo → Review só se verde, até APPROVED.

Se o orçamento de check-fix se esgota e os checks obrigatórios continuam vermelhos, o harness notifica o humano (`gate=check_fail_limit`, retry/stop) e **não** inicia Review. `retry-repair` / `stop-repair` também respondem a esse gate.

Review só começa com checks obrigatórios verdes. São quatro tracks:

```text
architecture
security
smells
tests
```

Architecture é **review de boundaries/camadas**, não um architecture agent.

Saída humana em `review/review-NN.md` (histórico append-only; o Execute/Repair usa só a última, com o veredito e o `consolidated.json` daquela rodada). O log de checks **não** entra nesse arquivo: fica em `tests/checks-NN.md` (append-only, mesma numeração). Rodadas seguintes reinspecionam o dirty atual **e** os arquivos apresentados na primeira review (mesmo que já não estejam dirty), revalidam **cada** finding anterior (inclusive mediums) e re-rodam os quatro tracks no diff atual:

```text
🤖 AI Code Review (S)
Summary
❌ Blockers
⚠️ High
📝 Medium
✅ Positive Findings
Verdict: ✅ APPROVED / ❌ REJECTED   ← review consolidada (não é substituída por check vermelho)
Harness gate: open / blocked        ← review APPROVED **e** checks verdes
```

Blocker/High devolve a Execute/Repair. Check obrigatório vermelho **não** inicia Review: vai para Repair (código) ou `check_fail_limit`. Execute/Repair não pode enfraquecer um teste válido só para ficar verde.

## Gate 2 — antes do commit

Depois de checks verdes + review aprovada, o LangGraph interrompe novamente. O action mostra worktree, diff stat, review e comandos para inspecionar o código.

Mudança simples / local (ainda no contrato original: layout, popup na tela já planejada, teste dos ACs existentes, copy, rename, bugfix) chama `revise-code` imediatamente, sem sugestão. Sugestão só se a mudança refizer a tarefa ou adicionar outra tarefa. Adição de escopo (pedido original ainda desejado) sugere mergear esta task e iniciar uma nova só com o extra: aceite é `approve-commit` e depois `harness task start`; recusa é `revise-code`. Substituição quase completa do pedido original sugere cancelar sem merge (limpa worktree, branch não mergeada e action) e iniciar uma nova task que replaneja; aceite é `cancel` e depois `start`; recusa é `revise-code`. Não existe comando `restart`.

```bash
harness task approve-commit 0001
```

ou, para ajuste local / recusa de sugestão:

```bash
harness task revise-code 0001 "simplifique esse service antes de integrar"
```

A revisão volta a acontecer depois dessa correção.

## Git lifecycle

```text
feature/desafio-salas (target branch)
  └─ harness/0001-task (worktree temporária)
       PLAN -> EXECUTE -> CHECKS -> REVIEW -> Gate 2
       -> commit
       -> merge --no-ff na target branch
       -> remove worktree
       -> delete task branch
```

Se houver conflito:

1. lista os arquivos conflitantes;
2. executa `git merge --abort`;
3. não toca na resolução;
4. mantém task branch e worktree;
5. status vira `needs_human_attention`.

Depois de você integrar manualmente:

```bash
harness task resolve-integration 0001
```

O comando só limpa worktree/branch se Git provar que a task branch já é ancestral da target branch.

## Status

```bash
harness task status 0001
harness task action 0001
harness task inspect 0001
```

Cada task possui thread/checkpoint independente, branch independente, worktree independente e runtime reports independentes. Terminais diferentes não misturam o estado do harness.

## Docs do projeto

O harness não tem `init` e não tenta conhecer stack. O projeto deve manter suas fontes em `docs/`. O Planner usa o índice de `docs/` para escolher contexto seletivamente. O `harness-review` espera as políticas de review do próprio projeto, em especial `docs/reviews/` e `docs/test/`.
